<?php

namespace Database\Seeders;

use App\Models\Hr\Employee;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * [nama, email, peran, NIK karyawan]
     *
     * Peran Gudang ikut dibuat karena hanya peran itu yang boleh memposting
     * penerimaan barang dan surat jalan; tanpanya alur pembelian dan penjualan
     * berhenti di tengah jalan.
     */
    private const USERS = [
        ['Administrator', 'admin@bonecomtricom.com', 'Super Admin', null],
        ['Manajer', 'manajer@bonecomtricom.com', 'Manajer', 'EMP-0001'],
        ['Staf Pembelian', 'pembelian@bonecomtricom.com', 'Pembelian', 'EMP-0004'],
        ['Staf Gudang', 'gudang@bonecomtricom.com', 'Gudang', 'EMP-0008'],
        ['Staf Penjualan', 'penjualan@bonecomtricom.com', 'Penjualan', 'EMP-0006'],
        ['Staf Akuntansi', 'akuntansi@bonecomtricom.com', 'Akuntansi', 'EMP-0002'],
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

            // Karyawan hanya ditautkan bila datanya memang ada.
            if ($nik && Employee::where('nik', $nik)->exists()) {
                Employee::where('nik', $nik)->update(['user_id' => $user->id]);
            }
        }

        $this->command?->warn('Semua akun demo memakai kata sandi: password — ganti sebelum dipakai produksi.');
    }
}
