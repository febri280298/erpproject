<?php

namespace Database\Seeders;

use App\Models\NumberSequence;
use App\Models\Setting;
use App\Services\AccountMap;
use App\Services\DocumentNumberService;
use App\Services\ModuleRegistry;
use App\Services\SettingService;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    /** Pengaturan pajak sesuai PMK 131/2024; dimatikan agar angka lama tidak berubah. */
    private const TAX_DEFAULTS = [
        'use_dpp_nilai_lain' => ['0', 'boolean'],
        'dpp_ratio_numerator' => ['11', 'number'],
        'dpp_ratio_denominator' => ['12', 'number'],
        'wht_service_rate' => ['2', 'number'],
    ];

    /**
     * Menulis nilai bawaan hanya bila kuncinya belum pernah ada.
     *
     * Seeder ini boleh dijalankan ulang kapan saja (mis. saat menambah
     * pengaturan baru), jadi ia tidak boleh menimpa nilai yang sudah diubah
     * pengguna lewat halaman Pengaturan.
     */
    private function bawaan(array $nilai, string $grup): int
    {
        $baru = 0;

        foreach ($nilai as $kunci => $isi) {
            $type = match (true) {
                is_bool($isi) => 'boolean',
                is_numeric($isi) && ! is_string($isi) => 'number',
                default => 'string',
            };

            $dibuat = Setting::firstOrCreate(
                ['key' => $kunci],
                [
                    'group' => $grup,
                    'type' => $type,
                    'value' => is_bool($isi) ? ($isi ? '1' : '0') : (string) $isi,
                ],
            )->wasRecentlyCreated;

            $baru += $dibuat ? 1 : 0;
        }

        return $baru;
    }

    public function run(): void
    {
        foreach (self::TAX_DEFAULTS as $key => [$value, $type]) {
            Setting::firstOrCreate(
                ['key' => $key],
                ['group' => 'accounting', 'value' => $value, 'type' => $type],
            );
        }

        $settings = app(SettingService::class);

        $this->bawaan([
            'company_name' => env('ERP_COMPANY_NAME', 'PT Bonecom Tricom'),
            'company_address' => 'Jl. Raya Industri No. 1, Jakarta',
            'company_phone' => '(021) 1234-5678',
            'company_email' => 'info@bonecomtricom.com',
            'company_npwp' => '00.000.000.0-000.000',
        ], 'company');

        // Account mappings default to the COA codes seeded by ChartOfAccountSeeder.
        $this->bawaan(
            collect(AccountMap::MAPPINGS)->map(fn ($mapping) => $mapping[1])->all(),
            'accounting'
        );

        $this->bawaan([
            'fiscal_year_start' => env('ERP_FISCAL_YEAR_START', '01-01'),
            'default_tax_rate' => (float) env('ERP_TAX_RATE', 11),
        ], 'accounting');

        $this->bawaan([
            'allow_negative_stock' => false,
            'require_po_approval' => true,
            'work_start_time' => '08:00',
            'work_end_time' => '17:00',
            'payroll_bpjs_percent' => 4,
            'payroll_pph21_percent' => 0,
            'payroll_overtime_rate' => 25000,
            'invoice_footer_note' => 'Pembayaran ditransfer ke rekening perusahaan. Terima kasih atas kepercayaan Anda.',
        ], 'operations');

        foreach (DocumentNumberService::DEFAULTS as $module => $config) {
            NumberSequence::firstOrCreate(
                ['module' => $module],
                [
                    'prefix' => $config['prefix'],
                    'reset_period' => $config['reset_period'],
                    'padding' => 4,
                    'next_number' => 1,
                    'period_year' => now()->year,
                    'period_month' => now()->month,
                ]
            );
        }

        // Menuliskan flag modul hanya bila belum pernah ada, supaya menjalankan
        // ulang seeder tidak menghapus pilihan modul yang sudah diatur pengguna
        // lewat Pengaturan → Modul.
        $modules = app(ModuleRegistry::class);

        if (! Setting::where('key', 'modules_enabled')->exists()) {
            $modules->save(collect($modules->catalogue())
                ->map(fn (array $meta) => $meta['default'] ?? true)
                ->all());
        }

        $settings->flush();

        $this->command?->info('Pengaturan sistem & format nomor dokumen disiapkan.');
        $this->command?->info('Modul aktif: '.collect($modules->enabledKeys())
            ->map(fn (string $key) => $modules->label($key))->implode(', '));
    }
}
