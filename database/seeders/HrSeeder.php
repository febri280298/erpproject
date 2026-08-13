<?php

namespace Database\Seeders;

use App\Models\Hr\Department;
use App\Models\Hr\Employee;
use App\Models\Hr\LeaveType;
use App\Models\Hr\Position;
use Illuminate\Database\Seeder;

class HrSeeder extends Seeder
{
    public function run(): void
    {
        $departments = [
            'DIR' => 'Direksi',
            'FIN' => 'Keuangan & Akuntansi',
            'PUR' => 'Pembelian',
            'SLS' => 'Penjualan',
            'WHS' => 'Gudang & Logistik',
            'HRD' => 'Sumber Daya Manusia',
            'IT' => 'Teknologi Informasi',
        ];

        foreach ($departments as $code => $name) {
            Department::updateOrCreate(['code' => $code], ['name' => $name, 'is_active' => true]);
        }

        $positions = [
            ['DIR-01', 'Direktur', 'DIR', 35000000],
            ['FIN-01', 'Manajer Keuangan', 'FIN', 18000000],
            ['FIN-02', 'Staf Akuntansi', 'FIN', 7500000],
            ['PUR-01', 'Manajer Pembelian', 'PUR', 15000000],
            ['PUR-02', 'Staf Pembelian', 'PUR', 6500000],
            ['SLS-01', 'Manajer Penjualan', 'SLS', 16000000],
            ['SLS-02', 'Sales Executive', 'SLS', 6000000],
            ['WHS-01', 'Kepala Gudang', 'WHS', 9000000],
            ['WHS-02', 'Staf Gudang', 'WHS', 5000000],
            ['HRD-01', 'Manajer HRD', 'HRD', 15000000],
            ['IT-01', 'IT Support', 'IT', 7000000],
        ];

        foreach ($positions as [$code, $name, $deptCode, $salary]) {
            Position::updateOrCreate(['code' => $code], [
                'name' => $name,
                'department_id' => Department::where('code', $deptCode)->value('id'),
                'base_salary' => $salary,
                'is_active' => true,
            ]);
        }

        foreach ([
            ['TAHUNAN', 'Cuti Tahunan', 12, true],
            ['SAKIT', 'Cuti Sakit', 14, true],
            ['MELAHIRKAN', 'Cuti Melahirkan', 90, true],
            ['PENTING', 'Cuti Alasan Penting', 3, true],
            ['BESAR', 'Cuti Besar', 30, true],
            ['TANPA', 'Cuti Tanpa Gaji', 30, false],
        ] as [$code, $name, $maxDays, $isPaid]) {
            LeaveType::updateOrCreate(['code' => $code], [
                'name' => $name,
                'max_days' => $maxDays,
                'is_paid' => $isPaid,
                'is_active' => true,
            ]);
        }

        $employees = [
            ['EMP-0001', 'Hendra Wijaya', 'DIR', 'DIR-01', 'L'],
            ['EMP-0002', 'Sri Rahayu', 'FIN', 'FIN-01', 'P'],
            ['EMP-0003', 'Agus Purnomo', 'FIN', 'FIN-02', 'L'],
            ['EMP-0004', 'Rina Marlina', 'PUR', 'PUR-01', 'P'],
            ['EMP-0005', 'Dimas Prakoso', 'PUR', 'PUR-02', 'L'],
            ['EMP-0006', 'Lestari Handayani', 'SLS', 'SLS-01', 'P'],
            ['EMP-0007', 'Bayu Setiawan', 'SLS', 'SLS-02', 'L'],
            ['EMP-0008', 'Joko Susilo', 'WHS', 'WHS-01', 'L'],
            ['EMP-0009', 'Nur Aini', 'WHS', 'WHS-02', 'P'],
            ['EMP-0010', 'Fitriani Putri', 'HRD', 'HRD-01', 'P'],
            ['EMP-0011', 'Rizky Ramadhan', 'IT', 'IT-01', 'L'],
        ];

        foreach ($employees as $index => [$nik, $name, $deptCode, $posCode, $gender]) {
            $position = Position::where('code', $posCode)->first();

            Employee::updateOrCreate(['nik' => $nik], [
                'name' => $name,
                'department_id' => Department::where('code', $deptCode)->value('id'),
                'position_id' => $position?->id,
                'gender' => $gender,
                'birth_date' => now()->subYears(28 + $index % 12)->subDays($index * 37)->toDateString(),
                'join_date' => now()->subYears(1 + $index % 5)->startOfMonth()->toDateString(),
                'employment_type' => 'permanent',
                'phone' => '08'.random_int(1000000000, 1999999999),
                'email' => strtolower(str_replace(' ', '.', $name)).'@bonecomtricom.com',
                'basic_salary' => (float) ($position?->base_salary ?? 5000000),
                'allowance' => round((float) ($position?->base_salary ?? 5000000) * 0.15, 2),
                'status' => 'active',
            ]);
        }

        // Department managers point at the first employee holding a manager role.
        foreach (['FIN' => 'EMP-0002', 'PUR' => 'EMP-0004', 'SLS' => 'EMP-0006', 'WHS' => 'EMP-0008', 'HRD' => 'EMP-0010'] as $deptCode => $nik) {
            Department::where('code', $deptCode)->update([
                'manager_id' => Employee::where('nik', $nik)->value('id'),
            ]);
        }

        $this->command?->info('Data SDM (departemen, jabatan, karyawan, jenis cuti) disiapkan.');
    }
}
