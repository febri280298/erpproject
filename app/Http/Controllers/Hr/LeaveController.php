<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\Hr\Attendance;
use App\Models\Hr\Employee;
use App\Models\Hr\Leave;
use App\Models\Hr\LeaveType;
use App\Services\DocumentNumberService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class LeaveController extends Controller
{
    public function __construct(private readonly DocumentNumberService $numbers) {}

    public function index(Request $request): View
    {
        return view('hr.leaves.index', [
            'leaves' => Leave::query()
                ->with('employee:id,nik,name', 'leaveType:id,name', 'approver:id,name')
                ->filter($request->query())
                ->latest('start_date')
                ->paginate(20)
                ->withQueryString(),
            'employees' => Employee::active()->orderBy('name')->pluck('name', 'id'),
            'leaveTypes' => LeaveType::active()->orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function create(): View
    {
        return view('hr.leaves.form', [
            'leave' => null,
            'employees' => Employee::active()->orderBy('name')->pluck('name', 'id'),
            'leaveTypes' => LeaveType::active()->orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate($this->rules());

        $leave = Leave::create(array_merge($data, [
            'leave_no' => $this->numbers->next('leave', $data['start_date']),
            'days' => $this->workingDays($data['start_date'], $data['end_date']),
            'status' => 'pending',
        ]));

        return redirect()->route('leaves.show', $leave)
            ->with('success', "Pengajuan cuti {$leave->leave_no} berhasil dibuat.");
    }

    public function show(Leave $leave): View
    {
        $leave->load('employee.department', 'leaveType', 'approver');

        return view('hr.leaves.show', [
            'leave' => $leave,
            'remaining' => $leave->employee->remainingLeave($leave->leave_type_id),
        ]);
    }

    public function edit(Leave $leave): View
    {
        abort_unless($leave->isEditable(), 403, 'Pengajuan yang sudah diproses tidak dapat diubah.');

        return view('hr.leaves.form', [
            'leave' => $leave,
            'employees' => Employee::active()->orderBy('name')->pluck('name', 'id'),
            'leaveTypes' => LeaveType::active()->orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function update(Request $request, Leave $leave): RedirectResponse
    {
        abort_unless($leave->isEditable(), 403);

        $data = $request->validate($this->rules());

        $leave->update(array_merge($data, [
            'days' => $this->workingDays($data['start_date'], $data['end_date']),
        ]));

        return redirect()->route('leaves.show', $leave)
            ->with('success', "Pengajuan cuti {$leave->leave_no} berhasil diperbarui.");
    }

    public function destroy(Leave $leave): RedirectResponse
    {
        abort_unless($leave->isEditable(), 403);

        $number = $leave->leave_no;
        $leave->delete();

        return redirect()->route('leaves.index')->with('success', "Pengajuan {$number} berhasil dihapus.");
    }

    /** Approving also stamps the attendance rows for the leave period. */
    public function approve(Leave $leave): RedirectResponse
    {
        if ($leave->status !== 'pending') {
            return back()->with('error', 'Pengajuan ini sudah diproses.');
        }

        DB::transaction(function () use ($leave) {
            $leave->forceFill([
                'status' => 'approved',
                'approved_by' => Auth::id(),
                'approved_at' => now(),
            ])->save();

            $status = str_contains(strtolower((string) $leave->leaveType?->name), 'sakit') ? 'sick' : 'leave';
            $cursor = CarbonImmutable::parse($leave->start_date);
            $end = CarbonImmutable::parse($leave->end_date);

            while ($cursor->lessThanOrEqualTo($end)) {
                if (! $cursor->isWeekend()) {
                    Attendance::updateOrCreate(
                        ['employee_id' => $leave->employee_id, 'date' => $cursor->toDateString()],
                        ['status' => $status, 'notes' => 'Cuti '.$leave->leave_no, 'check_in' => null, 'check_out' => null]
                    );
                }
                $cursor = $cursor->addDay();
            }
        });

        $leave->recordActivity('approved', "Cuti {$leave->leave_no} disetujui");

        return back()->with('success', 'Pengajuan cuti disetujui dan absensi diperbarui.');
    }

    public function reject(Request $request, Leave $leave): RedirectResponse
    {
        $data = $request->validate(['reject_reason' => ['required', 'string', 'max:255']]);

        if ($leave->status !== 'pending') {
            return back()->with('error', 'Pengajuan ini sudah diproses.');
        }

        $leave->forceFill([
            'status' => 'rejected',
            'approved_by' => Auth::id(),
            'approved_at' => now(),
            'reject_reason' => $data['reject_reason'],
        ])->save();

        $leave->recordActivity('rejected', "Cuti {$leave->leave_no} ditolak");

        return back()->with('success', 'Pengajuan cuti ditolak.');
    }

    private function rules(): array
    {
        return [
            'employee_id' => ['required', 'exists:employees,id'],
            'leave_type_id' => ['required', 'exists:leave_types,id'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /** Weekends do not consume leave quota. */
    private function workingDays(string $start, string $end): float
    {
        $cursor = CarbonImmutable::parse($start);
        $last = CarbonImmutable::parse($end);
        $days = 0;

        while ($cursor->lessThanOrEqualTo($last)) {
            if (! $cursor->isWeekend()) {
                $days++;
            }
            $cursor = $cursor->addDay();
        }

        return max($days, 1);
    }
}
