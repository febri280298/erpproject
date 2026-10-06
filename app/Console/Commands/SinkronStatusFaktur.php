<?php

namespace App\Console\Commands;

use App\Models\Purchasing\PurchaseInvoice;
use App\Models\Sales\SalesInvoice;
use Illuminate\Console\Command;

/**
 * Menyelaraskan status faktur dengan sisa tagihannya yang sebenarnya.
 *
 * Status tersimpan di kolomnya sendiri, jadi faktur yang statusnya terlanjur
 * salah tidak ikut membaik hanya karena rumusnya diperbaiki — ia tetap salah
 * sampai dihitung ulang. Perintah ini yang menghitungnya ulang.
 *
 * Sengaja memakai statusPembayaran() milik modelnya, bukan UPDATE SQL yang
 * menuliskan ulang aturannya: aturan yang disalin ke dua tempat akan berbeda
 * pada perubahan berikutnya, dan itu persis cacat yang sedang diperbaiki.
 */
class SinkronStatusFaktur extends Command
{
    protected $signature = 'faktur:sinkron-status {--tulis : Simpan perubahannya; tanpa ini hanya laporan}';

    protected $description = 'Menyelaraskan status lunas/sebagian faktur dengan sisa tagihan yang sebenarnya';

    public function handle(): int
    {
        $tulis = (bool) $this->option('tulis');
        $jumlah = 0;

        foreach ([
            'Faktur Pembelian' => PurchaseInvoice::class,
            'Faktur Penjualan' => SalesInvoice::class,
        ] as $judul => $kelas) {
            $this->newLine();
            $this->line($judul);
            $berubah = 0;

            $kelas::query()
                ->whereNotIn('status', ['draft', 'cancelled'])
                ->chunkById(200, function ($daftar) use (&$berubah, $tulis) {
                    foreach ($daftar as $faktur) {
                        $semestinya = $faktur->statusPembayaran();

                        if ($semestinya === $faktur->status) {
                            continue;
                        }

                        $berubah++;
                        $this->line(sprintf(
                            '  %-20s %-8s -> %-8s   sisa %s',
                            $faktur->invoice_no,
                            $faktur->status,
                            $semestinya,
                            number_format($faktur->outstandingAmount(), 2, ',', '.')
                        ));

                        if ($tulis) {
                            $faktur->syncPaymentStatus();
                        }
                    }
                });

            $this->line($berubah === 0 ? '  (tidak ada yang perlu diubah)' : "  {$berubah} faktur");
            $jumlah += $berubah;
        }

        $this->newLine();

        if ($jumlah === 0) {
            $this->info('Semua status sudah sesuai.');
        } elseif ($tulis) {
            $this->info("{$jumlah} faktur diperbarui.");
        } else {
            $this->warn("{$jumlah} faktur perlu diperbarui. Jalankan ulang dengan --tulis untuk menyimpannya.");
        }

        return self::SUCCESS;
    }
}
