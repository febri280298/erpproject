<?php

namespace App\Services;

use App\Models\NumberSequence;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Generates gap-free, per-module document numbers such as `PO/2026/08/0001`.
 *
 * The sequence row is locked for the duration of the enclosing transaction so
 * two concurrent posts can never claim the same number.
 */
class DocumentNumberService
{
    /**
     * Modul yang nomornya menyisipkan inisial mitra: `PO/GB/2026/08/0001`.
     *
     * Urutan pencacahnya tetap satu per modul, bukan per mitra. Dengan begitu
     * nomornya dijamin unik walau inisial mitra kelak diubah, dan tidak ada dua
     * dokumen berbeda yang bisa berakhir dengan nomor sama.
     */
    public const WITH_PARTNER_INITIAL = [
        'purchase_order',
        'goods_receipt',
        'delivery_order',
    ];

    /**
     * Ditampilkan pada pratinjau di form, saat mitranya belum dipilih.
     * Tanpa penanda ini pratinjau memperlihatkan nomor yang berbeda dari yang
     * akhirnya tersimpan, dan itu terbaca seperti sistem yang salah hitung.
     */
    private const PLACEHOLDER = '(mitra)';

    /**
     * Tabel dan kolom nomor tiap modul, dipakai memeriksa apakah sebuah nomor
     * sudah terpakai sebelum diberikan.
     *
     * Pencacah saja tidak cukup sejak nomor dokumen boleh diketik manual —
     * lihat catatan pada next(). Modul yang tidak terdaftar di sini tidak
     * diperiksa, dan itu aman: perilakunya kembali seperti semula.
     */
    private const TABLES = [
        'purchase_requisition' => ['purchase_requisitions', 'pr_no'],
        'purchase_order' => ['purchase_orders', 'po_no'],
        'goods_receipt' => ['goods_receipts', 'grn_no'],
        'purchase_invoice' => ['purchase_invoices', 'invoice_no'],
        'supplier_payment' => ['supplier_payments', 'payment_no'],
        'quotation' => ['quotations', 'quotation_no'],
        'sales_order' => ['sales_orders', 'so_no'],
        'delivery_order' => ['delivery_orders', 'do_no'],
        'sales_invoice' => ['sales_invoices', 'invoice_no'],
        'customer_payment' => ['customer_payments', 'payment_no'],
        'sales_return' => ['sales_returns', 'return_no'],
        'stock_transfer' => ['stock_transfers', 'transfer_no'],
        'stock_adjustment' => ['stock_adjustments', 'adjustment_no'],
        'journal' => ['journals', 'journal_no'],
        'production_order' => ['production_orders', 'order_no'],
        'bom' => ['boms', 'bom_no'],
        'leave' => ['leaves', 'leave_no'],
        'payroll' => ['payrolls', 'payroll_no'],
    ];

    /**
     * Batas berapa nomor yang boleh dilewati sekali jalan.
     *
     * Ada supaya kesalahan data tidak berubah menjadi putaran tak berujung
     * yang menggantung permintaan. Angkanya longgar: melewati seribu nomor
     * berturut-turut berarti ada yang jauh lebih salah daripada sekadar
     * bentrok satu-dua nomor.
     */
    private const MAKS_LEWATI = 1000;

    /** Modules seeded on install; `prefix` doubles as the fallback. */
    public const DEFAULTS = [
        'purchase_requisition' => ['prefix' => 'PR', 'reset_period' => 'monthly'],
        'purchase_order' => ['prefix' => 'PO', 'reset_period' => 'monthly'],
        'goods_receipt' => ['prefix' => 'GRN', 'reset_period' => 'monthly'],
        'purchase_invoice' => ['prefix' => 'BLI', 'reset_period' => 'monthly'],
        'supplier_payment' => ['prefix' => 'BYR', 'reset_period' => 'monthly'],
        'quotation' => ['prefix' => 'QT', 'reset_period' => 'monthly'],
        'sales_order' => ['prefix' => 'SO', 'reset_period' => 'monthly'],
        'delivery_order' => ['prefix' => 'SJ', 'reset_period' => 'monthly'],
        'sales_invoice' => ['prefix' => 'INV', 'reset_period' => 'monthly'],
        'customer_payment' => ['prefix' => 'RCP', 'reset_period' => 'monthly'],
        'sales_return' => ['prefix' => 'RTR', 'reset_period' => 'monthly'],
        'stock_transfer' => ['prefix' => 'TRF', 'reset_period' => 'monthly'],
        'stock_adjustment' => ['prefix' => 'ADJ', 'reset_period' => 'monthly'],
        'journal' => ['prefix' => 'JV', 'reset_period' => 'monthly'],
        'production_order' => ['prefix' => 'WO', 'reset_period' => 'monthly'],
        'bom' => ['prefix' => 'BOM', 'reset_period' => 'yearly'],
        'leave' => ['prefix' => 'CTI', 'reset_period' => 'yearly'],
        'payroll' => ['prefix' => 'PAY', 'reset_period' => 'yearly'],
    ];

    public function next(string $module, ?string $date = null, ?string $initial = null): string
    {
        $when = $date ? Carbon::parse($date) : Carbon::now();

        return DB::transaction(function () use ($module, $when, $initial) {
            $sequence = NumberSequence::query()
                ->where('module', $module)
                ->lockForUpdate()
                ->first();

            if (! $sequence) {
                $defaults = self::DEFAULTS[$module] ?? ['prefix' => strtoupper(substr($module, 0, 3)), 'reset_period' => 'monthly'];
                $sequence = NumberSequence::create([
                    'module' => $module,
                    'prefix' => $defaults['prefix'],
                    'reset_period' => $defaults['reset_period'],
                    'padding' => 4,
                    'next_number' => 1,
                    'period_year' => $when->year,
                    'period_month' => $when->month,
                ]);
            }

            if ($this->periodChanged($sequence, $when)) {
                $sequence->next_number = 1;
                $sequence->period_year = $when->year;
                $sequence->period_month = $when->month;
            }

            $segment = $this->segment($module, $initial);

            /*
             * Nomor yang sudah terpakai dilewati.
             *
             * Pencacah saja tidak lagi menjamin keunikan sejak nomor dokumen
             * boleh diketik manual: seseorang menamai faktur SL/2026/09/0031,
             * lalu berbulan-bulan kemudian pencacah sampai di 0031 dan
             * pembuatan faktur gagal di constraint unik — jauh dari sebabnya,
             * dan tidak ada petunjuk apa pun di layar selain "Server Error".
             *
             * Diperiksa terhadap nomor yang SUDAH TERBENTUK, bukan terhadap
             * angka pencacahnya, karena bentuk akhirnya memuat prefix, periode,
             * dan inisial mitra — dan itulah yang dijaga indeks uniknya.
             */
            $number = $sequence->next_number;
            $batas = $number + self::MAKS_LEWATI;
            $formatted = $this->format($sequence, $when, $number, $segment);

            while ($this->sudahTerpakai($module, $formatted)) {
                if (++$number >= $batas) {
                    throw new RuntimeException(
                        "Tidak menemukan nomor kosong untuk {$module} setelah ".self::MAKS_LEWATI
                        .' percobaan. Periksa penomoran dokumen di Pengaturan.'
                    );
                }

                $formatted = $this->format($sequence, $when, $number, $segment);
            }

            $sequence->next_number = $number + 1;
            $sequence->period_year = $when->year;
            $sequence->period_month = $when->month;
            $sequence->save();

            return $formatted;
        });
    }

    /** Nomor ini sudah dipakai dokumen lain? Modul tak terdaftar dianggap bebas. */
    private function sudahTerpakai(string $module, string $number): bool
    {
        if (! isset(self::TABLES[$module])) {
            return false;
        }

        [$tabel, $kolom] = self::TABLES[$module];

        // Sengaja lewat query builder, bukan model: baris yang dihapus lunak
        // tetap memegang nomornya di indeks unik, jadi harus ikut terhitung.
        return DB::table($tabel)->where($kolom, $number)->exists();
    }

    private function periodChanged(NumberSequence $sequence, Carbon $when): bool
    {
        return match ($sequence->reset_period) {
            'yearly' => $sequence->period_year !== $when->year,
            'monthly' => $sequence->period_year !== $when->year || $sequence->period_month !== $when->month,
            default => false,
        };
    }

    /**
     * Ruas inisial untuk modul ini, atau null bila tidak dipakai.
     *
     * Mitra yang belum berinisial tidak diberi penanda apa pun — nomornya
     * kembali ke bentuk tanpa ruas itu. Menuliskan penanda ke nomor yang
     * benar-benar tersimpan justru mengabadikan kekurangan data.
     */
    private function segment(string $module, ?string $initial): ?string
    {
        if (! in_array($module, self::WITH_PARTNER_INITIAL, true)) {
            return null;
        }

        $bersih = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $initial));

        return $bersih !== '' ? $bersih : null;
    }

    private function format(NumberSequence $sequence, Carbon $when, int $number, ?string $initial = null): string
    {
        $padded = str_pad((string) $number, $sequence->padding, '0', STR_PAD_LEFT);

        $ruas = array_filter([$sequence->prefix, $initial]);

        $ruas = match ($sequence->reset_period) {
            'monthly' => [...$ruas, $when->format('Y'), $when->format('m'), $padded],
            'yearly' => [...$ruas, $when->format('Y'), $padded],
            default => [...$ruas, $padded],
        };

        return implode('/', $ruas);
    }

    /**
     * Pratinjau saja — tidak memakai nomor.
     *
     * Mitranya belum dipilih saat form dibuka, jadi ruas inisialnya ditampilkan
     * sebagai penanda agar bentuk nomornya sudah terbaca sejak awal.
     */
    public function peek(string $module, ?string $date = null, ?string $initial = null): string
    {
        $when = $date ? Carbon::parse($date) : Carbon::now();
        $sequence = NumberSequence::where('module', $module)->first();

        if (! $sequence) {
            $defaults = self::DEFAULTS[$module] ?? ['prefix' => strtoupper(substr($module, 0, 3)), 'reset_period' => 'monthly'];
            $sequence = new NumberSequence(array_merge($defaults, ['padding' => 4, 'next_number' => 1]));
        }

        $number = $this->periodChanged($sequence, $when) ? 1 : $sequence->next_number;

        $ruas = $this->segment($module, $initial)
            ?? (in_array($module, self::WITH_PARTNER_INITIAL, true) ? self::PLACEHOLDER : null);

        return $this->format($sequence, $when, $number, $ruas);
    }
}
