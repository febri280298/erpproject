<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Models\Accounting\Account;
use App\Models\Master\Tax;
use App\Models\NumberSequence;
use App\Services\AccountMap;
use App\Services\ModuleRegistry;
use App\Services\SettingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class SettingController extends Controller
{
    public function __construct(
        private readonly SettingService $settings,
        private readonly ModuleRegistry $modules,
    ) {}

    public function edit(): View
    {
        return view('system.settings.edit', [
            'settings' => $this->settings->all(),
            'accounts' => Account::postable()->orderBy('code')->get()
                ->mapWithKeys(fn (Account $a) => [$a->code => $a->label()]),
            'accountMappings' => AccountMap::MAPPINGS,
            'sequences' => NumberSequence::orderBy('module')->get(),
            'moduleCatalogue' => $this->modules->catalogue(),
            'moduleRegistry' => $this->modules,
        ]);
    }

    /** Turns whole areas of the ERP on or off. Data is never removed. */
    public function updateModules(Request $request): RedirectResponse
    {
        $request->validate([
            'modules' => ['nullable', 'array'],
            'modules.*' => ['in:0,1'],
        ]);

        $this->modules->save($request->input('modules', []));

        $active = collect($this->modules->enabledKeys())
            ->map(fn (string $key) => $this->modules->label($key))
            ->implode(', ');

        return back()->with('success', "Modul aktif diperbarui: {$active}.");
    }

    public function updateCompany(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'company_name' => ['required', 'string', 'max:150'],
            'company_address' => ['nullable', 'string', 'max:500'],
            'company_phone' => ['nullable', 'string', 'max:50'],
            'company_email' => ['nullable', 'email', 'max:150'],
            'company_npwp' => ['nullable', 'string', 'max:30'],
            'company_logo' => ['nullable', 'image', 'max:1024'],
        ]);

        if ($request->hasFile('company_logo')) {
            if ($existing = $this->settings->get('company_logo')) {
                Storage::disk('public')->delete($existing);
            }
            $data['company_logo'] = $request->file('company_logo')->store('company', 'public');
        } else {
            unset($data['company_logo']);
        }

        $this->settings->setMany($data, 'company');

        return back()->with('success', 'Profil perusahaan berhasil disimpan.');
    }

    public function updateAccounting(Request $request): RedirectResponse
    {
        $keys = array_keys(AccountMap::MAPPINGS);

        $data = $request->validate(array_merge(
            array_fill_keys($keys, ['nullable', 'string', 'exists:accounts,code']),
            [
                'fiscal_year_start' => ['nullable', 'string', 'max:5'],
                'default_tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            ],
        ));

        $this->settings->setMany($data, 'accounting');

        return back()->with('success', 'Pemetaan akun berhasil disimpan.');
    }

    /**
     * DPP Nilai Lain (PMK 131/2024): tarif 12% dikenakan atas 11/12 harga jual,
     * setara 11% dari harga jual.
     */
    public function updateTax(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'use_dpp_nilai_lain' => ['boolean'],
            'dpp_ratio_numerator' => ['required_if:use_dpp_nilai_lain,1', 'integer', 'min:1', 'max:100'],
            'dpp_ratio_denominator' => ['required_if:use_dpp_nilai_lain,1', 'integer', 'min:1', 'max:100'],
        ], [], [
            'dpp_ratio_numerator' => 'Pembilang rasio',
            'dpp_ratio_denominator' => 'Penyebut rasio',
        ]);

        $aktif = $request->boolean('use_dpp_nilai_lain');

        if ($aktif && $data['dpp_ratio_numerator'] > $data['dpp_ratio_denominator']) {
            return back()->withInput()->with('error',
                'Pembilang rasio tidak boleh melebihi penyebutnya — DPP Nilai Lain selalu lebih kecil dari harga jual.');
        }

        $this->settings->setMany([
            'use_dpp_nilai_lain' => $aktif,
            'dpp_ratio_numerator' => (int) $data['dpp_ratio_numerator'],
            'dpp_ratio_denominator' => (int) $data['dpp_ratio_denominator'],
        ], 'accounting');

        // Tarif pajak default harus 12% agar hasilnya setara 11% dari harga jual.
        $tarifDefault = (float) Tax::where('is_default', true)->value('rate');
        $peringatan = ($aktif && abs($tarifDefault - 12) >= 0.01)
            ? " Perhatian: tarif pajak default masih {$tarifDefault}%. Ubah menjadi 12% di Data Master → Pajak, "
                .'karena DPP Nilai Lain dirancang untuk dipasangkan dengan tarif 12%.'
            : '';

        return back()->with($peringatan ? 'warning' : 'success',
            'Pengaturan pajak berhasil disimpan.'.$peringatan);
    }

    public function updateOperations(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'allow_negative_stock' => ['boolean'],
            'require_po_approval' => ['boolean'],
            'work_start_time' => ['nullable', 'date_format:H:i'],
            'work_end_time' => ['nullable', 'date_format:H:i'],
            'payroll_bpjs_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'payroll_pph21_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'payroll_overtime_rate' => ['nullable', 'numeric', 'min:0'],
            'invoice_footer_note' => ['nullable', 'string', 'max:500'],
        ]);

        $data['allow_negative_stock'] = $request->boolean('allow_negative_stock');
        $data['require_po_approval'] = $request->boolean('require_po_approval');

        $this->settings->setMany($data, 'operations');

        return back()->with('success', 'Pengaturan operasional berhasil disimpan.');
    }

    public function updateSequence(Request $request, NumberSequence $sequence): RedirectResponse
    {
        $data = $request->validate([
            'prefix' => ['required', 'string', 'max:20'],
            'reset_period' => ['required', 'in:never,yearly,monthly'],
            'padding' => ['required', 'integer', 'min:1', 'max:10'],
            'next_number' => ['required', 'integer', 'min:1'],
        ]);

        $sequence->update($data);

        return back()->with('success', "Format nomor {$sequence->module} berhasil diperbarui.");
    }
}
