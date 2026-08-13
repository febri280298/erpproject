<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\Accounting\Account;
use App\Models\Hr\Payroll;
use App\Services\DocumentNumberService;
use App\Services\Posting\PayrollPostingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use RuntimeException;

class PayrollController extends Controller
{
    public function __construct(
        private readonly DocumentNumberService $numbers,
        private readonly PayrollPostingService $posting,
    ) {}

    public function index(Request $request): View
    {
        return view('hr.payrolls.index', [
            'payrolls' => Payroll::query()
                ->withCount('items')
                ->filter($request->query())
                ->orderByDesc('period_year')
                ->orderByDesc('period_month')
                ->paginate(20)
                ->withQueryString(),
        ]);
    }

    public function create(): View
    {
        return view('hr.payrolls.form', [
            'nextNumber' => $this->numbers->peek('payroll'),
            'defaultYear' => now()->year,
            'defaultMonth' => now()->month,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'period_year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'period_month' => ['required', 'integer', 'min:1', 'max:12'],
            'payment_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        if (Payroll::where('period_year', $data['period_year'])->where('period_month', $data['period_month'])->exists()) {
            return back()->withInput()->with('error', 'Payroll untuk periode ini sudah ada.');
        }

        $payroll = Payroll::create(array_merge($data, [
            'payroll_no' => $this->numbers->next('payroll'),
            'status' => 'draft',
            'created_by' => Auth::id(),
        ]));

        $this->posting->generate($payroll);

        return redirect()->route('payrolls.show', $payroll)
            ->with('success', "Payroll {$payroll->payroll_no} dibuat dan daftar karyawan digenerate.");
    }

    public function show(Payroll $payroll): View
    {
        $payroll->load('items.employee.department', 'creator', 'journals');

        return view('hr.payrolls.show', [
            'payroll' => $payroll,
            'cashAccounts' => Account::cashAndBank()->orderBy('code')->get()
                ->mapWithKeys(fn (Account $a) => [$a->id => $a->label()]),
        ]);
    }

    /** Manual edits to individual components before approval. */
    public function updateItems(Request $request, Payroll $payroll): RedirectResponse
    {
        abort_unless($payroll->isDraft(), 403, 'Payroll yang sudah disetujui tidak dapat diubah.');

        $data = $request->validate([
            'items' => ['required', 'array'],
            'items.*.bonus' => ['nullable', 'numeric', 'min:0'],
            'items.*.overtime' => ['nullable', 'numeric', 'min:0'],
            'items.*.bpjs' => ['nullable', 'numeric', 'min:0'],
            'items.*.tax_pph21' => ['nullable', 'numeric', 'min:0'],
            'items.*.other_deduction' => ['nullable', 'numeric', 'min:0'],
            'items.*.notes' => ['nullable', 'string', 'max:255'],
        ]);

        foreach ($data['items'] as $itemId => $values) {
            $item = $payroll->items()->find($itemId);
            if (! $item) {
                continue;
            }

            $item->fill([
                'bonus' => $values['bonus'] ?? 0,
                'overtime' => $values['overtime'] ?? 0,
                'bpjs' => $values['bpjs'] ?? 0,
                'tax_pph21' => $values['tax_pph21'] ?? 0,
                'other_deduction' => $values['other_deduction'] ?? 0,
                'notes' => $values['notes'] ?? null,
            ])->recalculate()->save();
        }

        $payroll->recalculateTotals();

        return back()->with('success', 'Rincian gaji diperbarui.');
    }

    public function regenerate(Payroll $payroll): RedirectResponse
    {
        try {
            $count = $this->posting->generate($payroll);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "{$count} karyawan dimuat ulang ke payroll ini.");
    }

    public function approve(Payroll $payroll): RedirectResponse
    {
        try {
            $this->posting->approve($payroll);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Payroll {$payroll->payroll_no} disetujui.");
    }

    public function pay(Request $request, Payroll $payroll): RedirectResponse
    {
        $data = $request->validate([
            'account_id' => ['required', 'exists:accounts,id'],
            'payment_date' => ['required', 'date'],
        ]);

        try {
            $this->posting->pay($payroll, (int) $data['account_id'], $data['payment_date']);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Payroll {$payroll->payroll_no} dibayar dan dijurnal.");
    }

    public function cancel(Payroll $payroll): RedirectResponse
    {
        try {
            $this->posting->cancel($payroll);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Payroll {$payroll->payroll_no} dibatalkan.");
    }

    public function destroy(Payroll $payroll): RedirectResponse
    {
        abort_unless($payroll->isDraft(), 403, 'Hanya payroll draft yang dapat dihapus.');

        $number = $payroll->payroll_no;
        $payroll->delete();

        return redirect()->route('payrolls.index')->with('success', "Payroll {$number} berhasil dihapus.");
    }

    public function slip(Payroll $payroll, int $itemId): View
    {
        $item = $payroll->items()->with('employee.department', 'employee.position')->findOrFail($itemId);

        return view('hr.payrolls.slip', ['payroll' => $payroll, 'item' => $item]);
    }
}
