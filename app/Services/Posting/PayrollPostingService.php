<?php

namespace App\Services\Posting;

use App\Models\Hr\Attendance;
use App\Models\Hr\Employee;
use App\Models\Hr\Payroll;
use App\Services\AccountMap;
use App\Services\JournalService;
use App\Services\SettingService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Builds a monthly payroll from employee master data + attendance, then books it:
 *
 *   Dr Beban Gaji        Cr Utang Gaji / Kas
 *                        Cr Utang BPJS
 *                        Cr Utang PPh 21
 */
class PayrollPostingService
{
    public function __construct(
        private readonly JournalService $journals,
        private readonly AccountMap $accounts,
        private readonly SettingService $settings,
    ) {}

    /**
     * Fills the payroll with one line per active employee, deriving overtime and
     * absence counts from attendance for the period.
     */
    public function generate(Payroll $payroll): int
    {
        if ($payroll->status !== 'draft') {
            throw new RuntimeException('Hanya payroll draft yang bisa digenerate ulang.');
        }

        return DB::transaction(function () use ($payroll) {
            $payroll->items()->delete();

            $start = $payroll->periodStart()->toDateString();
            $end = $payroll->periodEnd()->toDateString();

            $bpjsRate = (float) $this->settings->get('payroll_bpjs_percent', 4);
            $pphRate = (float) $this->settings->get('payroll_pph21_percent', 0);
            $overtimeRate = (float) $this->settings->get('payroll_overtime_rate', 25000);

            $employees = Employee::query()->active()->orderBy('name')->get();

            foreach ($employees as $employee) {
                $stats = Attendance::query()
                    ->where('employee_id', $employee->id)
                    ->whereBetween('date', [$start, $end])
                    ->selectRaw("
                        SUM(CASE WHEN status IN ('present','late') THEN 1 ELSE 0 END) as present_days,
                        SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent_days,
                        COALESCE(SUM(overtime_hours), 0) as overtime_hours
                    ")
                    ->first();

                $gross = (float) $employee->basic_salary + (float) $employee->allowance;
                $overtime = round((float) $stats->overtime_hours * $overtimeRate, 2);

                $item = $payroll->items()->make([
                    'employee_id' => $employee->id,
                    'basic_salary' => (float) $employee->basic_salary,
                    'allowance' => (float) $employee->allowance,
                    'overtime' => $overtime,
                    'bonus' => 0,
                    'bpjs' => round($gross * $bpjsRate / 100, 2),
                    'tax_pph21' => round($gross * $pphRate / 100, 2),
                    'other_deduction' => 0,
                    'present_days' => (int) $stats->present_days,
                    'absent_days' => (int) $stats->absent_days,
                ]);

                $item->recalculate()->save();
            }

            $payroll->recalculateTotals();

            return $employees->count();
        });
    }

    public function approve(Payroll $payroll): void
    {
        if ($payroll->status !== 'draft') {
            throw new RuntimeException('Payroll ini sudah disetujui.');
        }

        if ($payroll->items()->doesntExist()) {
            throw new RuntimeException('Generate daftar karyawan terlebih dahulu.');
        }

        $payroll->recalculateTotals();
        $payroll->refresh()->forceFill(['status' => 'approved'])->save();
        $payroll->recordActivity('approved', "Payroll {$payroll->payroll_no} disetujui");
    }

    /** Books the payroll and marks it paid; credits cash when paid directly. */
    public function pay(Payroll $payroll, ?int $cashAccountId = null, ?string $paymentDate = null): void
    {
        if ($payroll->status !== 'approved') {
            throw new RuntimeException('Payroll harus disetujui sebelum dibayar.');
        }

        DB::transaction(function () use ($payroll, $cashAccountId, $paymentDate) {
            $payroll->load('items');
            $date = $paymentDate ?? now()->toDateString();

            $bpjs = round((float) $payroll->items->sum('bpjs'), 2);
            $pph = round((float) $payroll->items->sum('tax_pph21'), 2);
            $other = round((float) $payroll->items->sum('other_deduction'), 2);
            $gross = round((float) $payroll->total_gross, 2);
            $net = round((float) $payroll->total_net, 2);

            $this->journals->post(
                [
                    ['account_id' => $this->accounts->id('acc_salary_expense'), 'debit' => $gross],
                    ['account_id' => $this->accounts->id('acc_bpjs_payable'), 'credit' => $bpjs],
                    ['account_id' => $this->accounts->id('acc_pph21_payable'), 'credit' => $pph],
                    ['account_id' => $this->accounts->id('acc_salary_payable'), 'credit' => $other],
                    ['account_id' => $cashAccountId ?? $this->accounts->id('acc_bank'), 'credit' => $net],
                ],
                $date,
                'payroll',
                'Penggajian '.$payroll->periodLabel(),
                $payroll,
                $payroll->payroll_no,
            );

            $payroll->forceFill(['status' => 'paid', 'payment_date' => $date])->save();
            $payroll->recordActivity('paid', "Payroll {$payroll->payroll_no} dibayar");
        });
    }

    public function cancel(Payroll $payroll): void
    {
        if ($payroll->status === 'cancelled') {
            throw new RuntimeException('Payroll sudah dibatalkan.');
        }

        DB::transaction(function () use ($payroll) {
            if ($payroll->status === 'paid') {
                $this->journals->reverseForSource($payroll);
            }

            $payroll->forceFill(['status' => 'cancelled'])->save();
            $payroll->recordActivity('cancelled', "Payroll {$payroll->payroll_no} dibatalkan");
        });
    }
}
