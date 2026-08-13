<?php

namespace Database\Seeders;

use App\Models\Accounting\Account;
use Illuminate\Database\Seeder;

/**
 * Standard Indonesian chart of accounts. Codes here match the fallbacks in
 * App\Services\AccountMap, so posting works out of the box.
 */
class ChartOfAccountSeeder extends Seeder
{
    /** [code, name, type, subtype, normal_balance, is_postable, parent_code] */
    private const ACCOUNTS = [
        ['1000', 'ASET', 'asset', null, 'debit', false, null],
        ['1100', 'Aset Lancar', 'asset', null, 'debit', false, '1000'],
        ['1110', 'Kas', 'asset', 'cash', 'debit', true, '1100'],
        ['1111', 'Bank', 'asset', 'bank', 'debit', true, '1100'],
        ['1120', 'Piutang Usaha', 'asset', 'receivable', 'debit', true, '1100'],
        ['1130', 'Piutang Lain-lain', 'asset', 'receivable', 'debit', true, '1100'],
        ['1140', 'Persediaan Barang', 'asset', 'inventory', 'debit', true, '1100'],
        ['1150', 'PPN Masukan', 'asset', 'tax', 'debit', true, '1100'],
        ['1160', 'Biaya Dibayar di Muka', 'asset', null, 'debit', true, '1100'],
        ['1200', 'Aset Tetap', 'asset', null, 'debit', false, '1000'],
        ['1210', 'Tanah dan Bangunan', 'asset', 'fixed', 'debit', true, '1200'],
        ['1220', 'Kendaraan', 'asset', 'fixed', 'debit', true, '1200'],
        ['1230', 'Peralatan Kantor', 'asset', 'fixed', 'debit', true, '1200'],
        ['1290', 'Akumulasi Penyusutan', 'asset', 'fixed', 'credit', true, '1200'],

        ['2000', 'KEWAJIBAN', 'liability', null, 'credit', false, null],
        ['2100', 'Kewajiban Lancar', 'liability', null, 'credit', false, '2000'],
        ['2110', 'Utang Usaha', 'liability', 'payable', 'credit', true, '2100'],
        ['2120', 'Penerimaan Barang Belum Ditagih', 'liability', 'payable', 'credit', true, '2100'],
        ['2130', 'PPN Keluaran', 'liability', 'tax', 'credit', true, '2100'],
        ['2140', 'Utang Gaji', 'liability', 'payable', 'credit', true, '2100'],
        ['2150', 'Utang BPJS', 'liability', 'payable', 'credit', true, '2100'],
        ['2160', 'Utang PPh 21', 'liability', 'tax', 'credit', true, '2100'],
        ['2200', 'Kewajiban Jangka Panjang', 'liability', null, 'credit', false, '2000'],
        ['2210', 'Utang Bank', 'liability', null, 'credit', true, '2200'],

        ['3000', 'EKUITAS', 'equity', null, 'credit', false, null],
        ['3110', 'Modal Disetor', 'equity', null, 'credit', true, '3000'],
        ['3120', 'Laba Ditahan', 'equity', null, 'credit', true, '3000'],
        ['3130', 'Prive', 'equity', null, 'debit', true, '3000'],

        ['4000', 'PENDAPATAN', 'revenue', null, 'credit', false, null],
        ['4110', 'Pendapatan Penjualan', 'revenue', null, 'credit', true, '4000'],
        ['4120', 'Potongan Penjualan', 'revenue', null, 'debit', true, '4000'],
        ['4130', 'Retur Penjualan', 'revenue', null, 'debit', true, '4000'],

        ['5000', 'HARGA POKOK', 'expense', null, 'debit', false, null],
        ['5110', 'Harga Pokok Penjualan', 'expense', 'cogs', 'debit', true, '5000'],
        ['5120', 'Potongan Pembelian', 'expense', 'cogs', 'credit', true, '5000'],
        ['5130', 'Beban Angkut Pembelian', 'expense', 'cogs', 'debit', true, '5000'],
        ['5140', 'Overhead Produksi Dibebankan', 'expense', 'cogs', 'credit', true, '5000'],

        ['6000', 'BEBAN OPERASIONAL', 'expense', null, 'debit', false, null],
        ['6110', 'Beban Gaji', 'expense', 'operating', 'debit', true, '6000'],
        ['6120', 'Beban Sewa', 'expense', 'operating', 'debit', true, '6000'],
        ['6130', 'Beban Listrik, Air & Telepon', 'expense', 'operating', 'debit', true, '6000'],
        ['6140', 'Beban Angkut Penjualan', 'expense', 'operating', 'debit', true, '6000'],
        ['6150', 'Selisih Persediaan (Rugi)', 'expense', 'operating', 'debit', true, '6000'],
        ['6160', 'Beban Penyusutan', 'expense', 'operating', 'debit', true, '6000'],
        ['6170', 'Beban Administrasi & Umum', 'expense', 'operating', 'debit', true, '6000'],
        ['6180', 'Beban Pemasaran', 'expense', 'operating', 'debit', true, '6000'],

        ['7000', 'PENDAPATAN LAIN', 'revenue', null, 'credit', false, null],
        ['7110', 'Selisih Persediaan (Laba)', 'revenue', 'other', 'credit', true, '7000'],
        ['7120', 'Pendapatan Bunga', 'revenue', 'other', 'credit', true, '7000'],
        ['7130', 'Pendapatan Lain-lain', 'revenue', 'other', 'credit', true, '7000'],

        ['8000', 'BEBAN LAIN', 'expense', null, 'debit', false, null],
        ['8110', 'Beban Bunga', 'expense', 'other', 'debit', true, '8000'],
        ['8120', 'Beban Administrasi Bank', 'expense', 'other', 'debit', true, '8000'],
        ['8130', 'Beban Pajak', 'expense', 'other', 'debit', true, '8000'],
    ];

    public function run(): void
    {
        // Parents are inserted before children because the list is ordered by code.
        foreach (self::ACCOUNTS as [$code, $name, $type, $subtype, $normalBalance, $isPostable, $parentCode]) {
            Account::updateOrCreate(
                ['code' => $code],
                [
                    'name' => $name,
                    'type' => $type,
                    'subtype' => $subtype,
                    'normal_balance' => $normalBalance,
                    'is_postable' => $isPostable,
                    'parent_id' => $parentCode ? Account::where('code', $parentCode)->value('id') : null,
                    'is_active' => true,
                ]
            );
        }

        $this->command?->info(count(self::ACCOUNTS).' akun COA disiapkan.');
    }
}
