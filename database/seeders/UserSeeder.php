<?php

namespace Database\Seeders;

use App\Models\Hr\Employee;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /** [name, email, role, employee NIK] */
    private const USERS = [
        ['Administrator', 'admin@bonecomtricom.com', 'Super Admin', null],
        ['Hendra Wijaya', 'manajer@bonecomtricom.com', 'Manajer', 'EMP-0001'],
        ['Sri Rahayu', 'akuntansi@bonecomtricom.com', 'Akuntansi', 'EMP-0002'],
        ['Rina Marlina', 'pembelian@bonecomtricom.com', 'Pembelian', 'EMP-0004'],
        ['Lestari Handayani', 'penjualan@bonecomtricom.com', 'Penjualan', 'EMP-0006'],
        ['Joko Susilo', 'gudang@bonecomtricom.com', 'Gudang', 'EMP-0008'],
        ['Fitriani Putri', 'hrd@bonecomtricom.com', 'HRD', 'EMP-0010'],
    ];

    public function run(): void
    {
        foreach (self::USERS as [$name, $email, $role, $nik]) {
            $user = User::withoutEvents(fn () => User::updateOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'password' => Hash::make('password'),
                    'is_active' => true,
                    'email_verified_at' => now(),
                ]
            ));

            $user->syncRoles([$role]);

            if ($nik) {
                Employee::where('nik', $nik)->update(['user_id' => $user->id]);
            }
        }

        $this->command?->warn('Semua akun demo memakai kata sandi: password — ganti sebelum dipakai produksi.');
    }
}
