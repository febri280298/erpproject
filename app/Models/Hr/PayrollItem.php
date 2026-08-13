<?php

namespace App\Models\Hr;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollItem extends Model
{
    protected $fillable = [
        'payroll_id', 'employee_id', 'basic_salary', 'allowance', 'overtime', 'bonus',
        'gross_salary', 'bpjs', 'tax_pph21', 'other_deduction', 'total_deduction',
        'net_salary', 'present_days', 'absent_days', 'notes',
    ];

    protected $casts = [
        'basic_salary' => 'decimal:2',
        'allowance' => 'decimal:2',
        'overtime' => 'decimal:2',
        'bonus' => 'decimal:2',
        'gross_salary' => 'decimal:2',
        'bpjs' => 'decimal:2',
        'tax_pph21' => 'decimal:2',
        'other_deduction' => 'decimal:2',
        'total_deduction' => 'decimal:2',
        'net_salary' => 'decimal:2',
        'present_days' => 'integer',
        'absent_days' => 'integer',
    ];

    public function payroll(): BelongsTo
    {
        return $this->belongsTo(Payroll::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /** Recomputes gross, deductions and net from the component columns. */
    public function recalculate(): static
    {
        $gross = (float) $this->basic_salary + (float) $this->allowance
            + (float) $this->overtime + (float) $this->bonus;
        $deduction = (float) $this->bpjs + (float) $this->tax_pph21 + (float) $this->other_deduction;

        $this->gross_salary = round($gross, 2);
        $this->total_deduction = round($deduction, 2);
        $this->net_salary = round($gross - $deduction, 2);

        return $this;
    }
}
