<?php

namespace Database\Seeders;

use App\Models\NumberSequence;
use App\Services\AccountMap;
use App\Services\DocumentNumberService;
use App\Services\ModuleRegistry;
use App\Services\SettingService;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        $settings = app(SettingService::class);

        $settings->setMany([
            'company_name' => env('ERP_COMPANY_NAME', 'PT Bonecom Tricom'),
            'company_address' => 'Jl. Raya Industri No. 1, Jakarta',
            'company_phone' => '(021) 1234-5678',
            'company_email' => 'info@bonecomtricom.com',
            'company_npwp' => '00.000.000.0-000.000',
        ], 'company');

        // Account mappings default to the COA codes seeded by ChartOfAccountSeeder.
        $settings->setMany(
            collect(AccountMap::MAPPINGS)->map(fn ($mapping) => $mapping[1])->all(),
            'accounting'
        );

        $settings->setMany([
            'fiscal_year_start' => env('ERP_FISCAL_YEAR_START', '01-01'),
            'default_tax_rate' => (float) env('ERP_TAX_RATE', 11),
        ], 'accounting');

        $settings->setMany([
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

        // Materialise the module flags so Pengaturan → Modul shows real values.
        $modules = app(ModuleRegistry::class);
        $modules->save(collect($modules->catalogue())
            ->map(fn (array $meta) => $meta['default'] ?? true)
            ->all());

        $settings->flush();

        $this->command?->info('Pengaturan sistem & format nomor dokumen disiapkan.');
        $this->command?->info('Modul aktif: '.collect($modules->enabledKeys())
            ->map(fn (string $key) => $modules->label($key))->implode(', '));
    }
}
