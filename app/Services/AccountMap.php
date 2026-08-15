<?php

namespace App\Services;

use App\Models\Accounting\Account;
use RuntimeException;

/**
 * Resolves the "which account does this posting hit?" question.
 *
 * Every mapping lives in `settings` under the `accounting` group and stores an
 * account *code*, so the chart of accounts can be renumbered from the UI
 * without touching code.
 */
class AccountMap
{
    /** setting key => [label, fallback account code] */
    public const MAPPINGS = [
        'acc_inventory' => ['Persediaan Barang', '1140'],
        'acc_receivable' => ['Piutang Usaha', '1120'],
        'acc_payable' => ['Utang Usaha', '2110'],
        'acc_grni' => ['Penerimaan Barang Belum Ditagih', '2120'],
        'acc_tax_input' => ['PPN Masukan', '1150'],
        'acc_tax_output' => ['PPN Keluaran', '2130'],
        'acc_wht_prepaid' => ['Uang Muka PPh 23', '1170'],
        'acc_sales' => ['Pendapatan Penjualan', '4110'],
        'acc_sales_discount' => ['Potongan Penjualan', '4120'],
        'acc_sales_return' => ['Retur Penjualan', '4130'],
        'acc_damaged_goods' => ['Kerugian Barang Rusak', '6190'],
        'acc_cogs' => ['Harga Pokok Penjualan', '5110'],
        'acc_purchase_discount' => ['Potongan Pembelian', '5120'],
        'acc_freight_in' => ['Beban Angkut Pembelian', '5130'],
        'acc_freight_out' => ['Beban Angkut Penjualan', '6140'],
        'acc_inventory_gain' => ['Selisih Persediaan (Laba)', '7110'],
        'acc_inventory_loss' => ['Selisih Persediaan (Rugi)', '6150'],
        'acc_overhead_applied' => ['Overhead Produksi Dibebankan', '5140'],
        'acc_salary_expense' => ['Beban Gaji', '6110'],
        'acc_salary_payable' => ['Utang Gaji', '2140'],
        'acc_bpjs_payable' => ['Utang BPJS', '2150'],
        'acc_pph21_payable' => ['Utang PPh 21', '2160'],
        'acc_cash' => ['Kas', '1110'],
        'acc_bank' => ['Bank', '1111'],
    ];

    /** @var array<string,int> */
    private array $resolved = [];

    public function __construct(private readonly SettingService $settings) {}

    /**
     * @throws RuntimeException when neither the configured nor the fallback
     *                          account exists — posting must not silently skip a leg.
     */
    public function id(string $key): int
    {
        if (isset($this->resolved[$key])) {
            return $this->resolved[$key];
        }

        [$label, $fallbackCode] = self::MAPPINGS[$key] ?? [$key, null];

        $code = (string) ($this->settings->get($key) ?: $fallbackCode);
        $id = $code ? Account::where('code', $code)->value('id') : null;

        if (! $id) {
            throw new RuntimeException(
                "Akun untuk \"{$label}\" belum diatur. Buka Pengaturan → Akuntansi dan pilih akun yang sesuai."
            );
        }

        return $this->resolved[$key] = (int) $id;
    }

    public function code(string $key): ?string
    {
        [, $fallbackCode] = self::MAPPINGS[$key] ?? [null, null];

        return (string) ($this->settings->get($key) ?: $fallbackCode) ?: null;
    }

    /** True when every mapping resolves — surfaced as a warning on the dashboard. */
    public function isComplete(): bool
    {
        foreach (array_keys(self::MAPPINGS) as $key) {
            try {
                $this->id($key);
            } catch (RuntimeException) {
                return false;
            }
        }

        return true;
    }

    /** @return array<int,string> keys that cannot be resolved */
    public function missing(): array
    {
        $missing = [];

        foreach (array_keys(self::MAPPINGS) as $key) {
            try {
                $this->id($key);
            } catch (RuntimeException) {
                $missing[] = $key;
            }
        }

        return $missing;
    }
}
