<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\Hr\Department;
use App\Models\Hr\Employee;
use App\Models\Hr\Position;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class EmployeeController extends Controller
{
    public function index(Request $request): View
    {
        return view('hr.employees.index', [
            'employees' => Employee::query()
                ->with('department:id,name', 'position:id,name')
                ->search($request->query('q'))
                ->when($request->filled('department_id'), fn ($q) => $q->where('department_id', $request->query('department_id')))
                ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
                ->orderBy('name')
                ->paginate(20)
                ->withQueryString(),
            'departments' => Department::active()->orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function create(): View
    {
        return view('hr.employees.form', $this->formData(new Employee([
            'status' => 'active',
            'employment_type' => 'permanent',
            'join_date' => now()->toDateString(),
        ])));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['photo'] = $request->hasFile('photo') ? $request->file('photo')->store('employees', 'public') : null;

        $employee = Employee::create($data);

        return redirect()->route('employees.show', $employee)
            ->with('success', "Karyawan \"{$employee->name}\" berhasil ditambahkan.");
    }

    public function show(Employee $employee): View
    {
        $employee->load('department', 'position', 'user');

        return view('hr.employees.show', [
            'employee' => $employee,
            'recentAttendances' => $employee->attendances()->latest('date')->limit(15)->get(),
            'recentLeaves' => $employee->leaves()->with('leaveType')->latest('start_date')->limit(10)->get(),
            'attendanceSummary' => $employee->attendances()
                ->whereYear('date', now()->year)
                ->whereMonth('date', now()->month)
                ->selectRaw("
                    SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present,
                    SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late,
                    SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent,
                    COALESCE(SUM(overtime_hours), 0) as overtime
                ")
                ->first(),
        ]);
    }

    public function edit(Employee $employee): View
    {
        return view('hr.employees.form', $this->formData($employee));
    }

    public function update(Request $request, Employee $employee): RedirectResponse
    {
        $data = $this->validated($request, $employee);

        if ($request->hasFile('photo')) {
            if ($employee->photo) {
                Storage::disk('public')->delete($employee->photo);
            }
            $data['photo'] = $request->file('photo')->store('employees', 'public');
        }

        $employee->update($data);

        return redirect()->route('employees.show', $employee)
            ->with('success', "Data karyawan \"{$employee->name}\" berhasil diperbarui.");
    }

    public function destroy(Employee $employee): RedirectResponse
    {
        if ($employee->payrollItems()->exists()) {
            return back()->with('error', 'Karyawan ini sudah masuk dalam penggajian. Ubah statusnya menjadi resign.');
        }

        $name = $employee->name;
        $employee->delete();

        return redirect()->route('employees.index')->with('success', "Karyawan \"{$name}\" berhasil dihapus.");
    }

    private function formData(Employee $employee): array
    {
        return [
            'employee' => $employee,
            'departments' => Department::active()->orderBy('name')->pluck('name', 'id'),
            'positions' => Position::active()->orderBy('name')->pluck('name', 'id'),
            'users' => User::active()->orderBy('name')->pluck('name', 'id'),
        ];
    }

    private function validated(Request $request, ?Employee $employee = null): array
    {
        $data = $request->validate([
            'nik' => ['required', 'string', 'max:30', Rule::unique('employees', 'nik')->ignore($employee?->id)],
            'name' => ['required', 'string', 'max:150'],
            'user_id' => ['nullable', 'exists:users,id', Rule::unique('employees', 'user_id')->ignore($employee?->id)],
            'department_id' => ['nullable', 'exists:departments,id'],
            'position_id' => ['nullable', 'exists:positions,id'],
            'gender' => ['nullable', Rule::in(['L', 'P'])],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'join_date' => ['nullable', 'date'],
            'resign_date' => ['nullable', 'date', 'after_or_equal:join_date'],
            'employment_type' => ['required', Rule::in(['permanent', 'contract', 'intern'])],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:150'],
            'address' => ['nullable', 'string', 'max:1000'],
            'bank_name' => ['nullable', 'string', 'max:100'],
            'bank_account' => ['nullable', 'string', 'max:50'],
            'npwp' => ['nullable', 'string', 'max:30'],
            'basic_salary' => ['required', 'numeric', 'min:0'],
            'allowance' => ['nullable', 'numeric', 'min:0'],
            'status' => ['required', Rule::in(['active', 'resigned', 'terminated'])],
            'photo' => ['nullable', 'image', 'max:2048'],
        ]);

        unset($data['photo']);

        return $data;
    }
}
