<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\Hr\Attendance;
use App\Models\Hr\Department;
use App\Models\Hr\Employee;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AttendanceController extends Controller
{
    public function index(Request $request): View
    {
        $filters = array_merge([
            'from' => now()->startOfMonth()->toDateString(),
            'to' => now()->toDateString(),
        ], $request->query());

        return view('hr.attendances.index', [
            'attendances' => Attendance::query()
                ->with('employee:id,nik,name,department_id', 'employee.department:id,name')
                ->filter($filters)
                ->orderByDesc('date')
                ->orderBy('employee_id')
                ->paginate(30)
                ->withQueryString(),
            'employees' => Employee::active()->orderBy('name')->pluck('name', 'id'),
            'departments' => Department::active()->orderBy('name')->pluck('name', 'id'),
            'filters' => $filters,
        ]);
    }

    /** Daily roster: one row per active employee, prefilled from existing records. */
    public function create(Request $request): View
    {
        $date = $request->query('date', now()->toDateString());
        $departmentId = $request->query('department_id');

        $employees = Employee::query()
            ->active()
            ->when($departmentId, fn ($q) => $q->where('department_id', $departmentId))
            ->with('department:id,name')
            ->orderBy('name')
            ->get();

        $existing = Attendance::whereDate('date', $date)
            ->whereIn('employee_id', $employees->pluck('id'))
            ->get()
            ->keyBy('employee_id');

        return view('hr.attendances.form', [
            'date' => $date,
            'departmentId' => $departmentId,
            'employees' => $employees,
            'existing' => $existing,
            'departments' => Department::active()->orderBy('name')->pluck('name', 'id'),
            'defaultCheckIn' => setting('work_start_time', '08:00'),
            'defaultCheckOut' => setting('work_end_time', '17:00'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'date' => ['required', 'date'],
            'rows' => ['required', 'array', 'min:1'],
            'rows.*.employee_id' => ['required', 'exists:employees,id'],
            'rows.*.status' => ['required', Rule::in(['present', 'late', 'absent', 'leave', 'sick', 'holiday'])],
            'rows.*.check_in' => ['nullable', 'date_format:H:i'],
            'rows.*.check_out' => ['nullable', 'date_format:H:i'],
            'rows.*.overtime_hours' => ['nullable', 'numeric', 'min:0', 'max:24'],
            'rows.*.notes' => ['nullable', 'string', 'max:255'],
        ]);

        $workStart = (string) setting('work_start_time', '08:00');
        $saved = 0;

        DB::transaction(function () use ($data, $workStart, &$saved) {
            foreach ($data['rows'] as $row) {
                $checkIn = $row['check_in'] ?? null;

                // Late minutes are derived, never trusted from the form.
                $lateMinutes = 0;
                if ($checkIn && in_array($row['status'], ['present', 'late'], true)) {
                    $expected = CarbonImmutable::parse($data['date'].' '.$workStart);
                    $actual = CarbonImmutable::parse($data['date'].' '.$checkIn);
                    $lateMinutes = $actual->greaterThan($expected) ? $expected->diffInMinutes($actual) : 0;
                }

                Attendance::updateOrCreate(
                    ['employee_id' => $row['employee_id'], 'date' => $data['date']],
                    [
                        'check_in' => in_array($row['status'], ['present', 'late'], true) ? $checkIn : null,
                        'check_out' => in_array($row['status'], ['present', 'late'], true) ? ($row['check_out'] ?? null) : null,
                        'late_minutes' => $lateMinutes,
                        'overtime_hours' => $row['overtime_hours'] ?? 0,
                        'status' => $lateMinutes > 0 && $row['status'] === 'present' ? 'late' : $row['status'],
                        'notes' => $row['notes'] ?? null,
                    ]
                );

                $saved++;
            }
        });

        return redirect()->route('attendances.index', ['from' => $data['date'], 'to' => $data['date']])
            ->with('success', "{$saved} data absensi tanggal ".fdate($data['date']).' tersimpan.');
    }

    public function destroy(Attendance $attendance): RedirectResponse
    {
        $attendance->delete();

        return back()->with('success', 'Data absensi berhasil dihapus.');
    }

    /** Monthly recap per employee — the source for payroll present/absent counts. */
    public function recap(Request $request): View
    {
        $year = (int) $request->query('year', now()->year);
        $month = (int) $request->query('month', now()->month);

        $start = CarbonImmutable::create($year, $month, 1);

        $rows = Employee::query()
            ->active()
            ->with('department:id,name')
            ->withCount([
                'attendances as present_days' => fn ($q) => $q->whereBetween('date', [$start->toDateString(), $start->endOfMonth()->toDateString()])->whereIn('status', ['present', 'late']),
                'attendances as late_days' => fn ($q) => $q->whereBetween('date', [$start->toDateString(), $start->endOfMonth()->toDateString()])->where('status', 'late'),
                'attendances as absent_days' => fn ($q) => $q->whereBetween('date', [$start->toDateString(), $start->endOfMonth()->toDateString()])->where('status', 'absent'),
                'attendances as leave_days' => fn ($q) => $q->whereBetween('date', [$start->toDateString(), $start->endOfMonth()->toDateString()])->whereIn('status', ['leave', 'sick']),
            ])
            ->withSum(['attendances as overtime_hours' => fn ($q) => $q->whereBetween('date', [$start->toDateString(), $start->endOfMonth()->toDateString()])], 'overtime_hours')
            ->orderBy('name')
            ->get();

        return view('hr.attendances.recap', [
            'rows' => $rows,
            'year' => $year,
            'month' => $month,
            'period' => $start,
        ]);
    }
}
