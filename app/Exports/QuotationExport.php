<?php

namespace App\Exports;

use App\Models\Sales\Quotation;
use App\Services\SettingService;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * One quotation as a formatted worksheet — letterhead, customer block, item
 * table, and totals — so it can be sent to a customer who prefers a spreadsheet
 * or wants to rework the figures themselves.
 *
 * Totals are written as live formulas rather than fixed numbers: editing a
 * quantity in Excel recalculates the sheet the same way the application would.
 */
class QuotationExport implements FromArray, ShouldAutoSize, WithColumnFormatting, WithEvents, WithStyles, WithTitle
{
    /** Baris pertama tabel item (setelah kop dan header kolom). */
    private const ITEM_START = 12;

    private int $itemEnd;

    private int $rowSubtotal;

    private int $rowDiscount;

    private int $rowShipping;

    private int $rowTax;

    private int $rowTotal;

    public function __construct(
        private readonly Quotation $quotation,
        private readonly SettingService $settings,
    ) {
        $count = max(1, $this->quotation->items->count());

        $this->itemEnd = self::ITEM_START + $count - 1;
        $this->rowSubtotal = $this->itemEnd + 2;
        $this->rowDiscount = $this->rowSubtotal + 1;
        $this->rowShipping = $this->rowSubtotal + 2;
        $this->rowTax = $this->rowSubtotal + 3;
        $this->rowTotal = $this->rowSubtotal + 4;
    }

    public function title(): string
    {
        return 'Penawaran';
    }

    public function array(): array
    {
        $q = $this->quotation;
        $company = $this->settings->company();

        $rows = [
            [$company['name'], null, null, null, null, 'PENAWARAN HARGA'],
            [$company['address'], null, null, null, null, $q->quotation_no],
            [trim(($company['phone'] ?? '').'  '.($company['email'] ?? '')), null, null, null, null, null],
            // Baris pemisah harus berisi satu sel kosong: array kosong dilewati
            // oleh PhpSpreadsheet sehingga nomor baris rumus ikut bergeser.
            [null],
            ['Kepada', $q->customer?->name, null, null, 'Tanggal', $q->date?->format('d/m/Y')],
            ['Alamat', $q->customer?->address, null, null, 'Berlaku s.d.', $q->valid_until?->format('d/m/Y')],
            ['Telepon', $q->customer?->phone, null, null, 'Status', __('status.'.$q->status)],
            ['NPWP', $q->customer?->npwp, null, null, 'Dibuat oleh', $q->creator?->name],
            [null],
            ['RINCIAN PENAWARAN'],
            ['No', 'Kode', 'Deskripsi', 'Satuan', 'Qty', 'Harga Satuan', 'Disc %', 'Pajak %', 'Jumlah'],
        ];

        foreach ($q->items as $i => $item) {
            $row = self::ITEM_START + $i;

            $rows[] = [
                $i + 1,
                $item->product?->sku,
                $item->description ?: $item->product?->name,
                $item->product?->uom?->code,
                (float) $item->quantity,
                (float) $item->unit_price,
                (float) $item->discount_percent,
                (float) $item->tax_rate,
                // Jumlah = qty × harga × (1 − disc%), dihitung ulang oleh Excel.
                "=ROUND(E{$row}*F{$row}*(1-G{$row}/100),2)",
            ];
        }

        if ($q->items->isEmpty()) {
            $rows[] = [1, null, 'Belum ada item', null, 0, 0, 0, 0, 0];
        }

        $first = self::ITEM_START;
        $last = $this->itemEnd;

        $rows[] = [null];
        $rows[] = [null, null, null, null, null, null, null, 'Subtotal', "=ROUND(SUM(I{$first}:I{$last}),2)"];
        $rows[] = [null, null, null, null, null, null, null, 'Diskon Nota', (float) $q->discount_amount];
        $rows[] = [null, null, null, null, null, null, null, 'Biaya Kirim', (float) $q->shipping_cost];
        $rows[] = [null, null, null, null, null, null, null, 'PPN', "=ROUND(SUMPRODUCT(I{$first}:I{$last},H{$first}:H{$last})/100,2)"];
        $rows[] = [
            null, null, null, null, null, null, null, 'TOTAL',
            "=ROUND(I{$this->rowSubtotal}-I{$this->rowDiscount}+I{$this->rowShipping}+I{$this->rowTax},2)",
        ];

        $rows[] = [null];
        $rows[] = ['Terbilang', terbilang((float) $q->total)];

        if ($q->notes) {
            $rows[] = ['Catatan', $q->notes];
        }

        if ($q->terms) {
            $rows[] = ['Syarat', $q->terms];
        }

        return $rows;
    }

    public function columnFormats(): array
    {
        // Mengikuti pengaturan desimal yang sama dengan tampilan layar dan
        // dokumen cetak, supaya berkas ekspor tidak berbeda dari yang dilihat.
        $money = desimal() === 2 ? '#,##0.00' : '#,##0';

        return [
            'E' => '#,##0.####',
            'F' => $money,
            'G' => '0.00',
            'H' => '0.00',
            'I' => $money,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true, 'size' => 14]],
            11 => ['font' => ['bold' => true]],
            $this->rowTotal => ['font' => ['bold' => true, 'size' => 12]],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                $sheet->getStyle('F1:I2')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 12],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
                ]);

                // Label pada blok "Kepada" dan blok total.
                $sheet->getStyle('A5:A8')->getFont()->setBold(true);
                $sheet->getStyle('E5:E8')->getFont()->setBold(true);
                $sheet->getStyle('A10')->getFont()->setBold(true);
                $sheet->getStyle("H{$this->rowSubtotal}:H{$this->rowTotal}")
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                // Header kolom tabel item.
                $sheet->getStyle('A11:I11')->applyFromArray([
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E3EAEA']],
                    'borders' => ['bottom' => ['borderStyle' => Border::BORDER_THIN]],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);

                $sheet->getStyle('A'.self::ITEM_START.":I{$this->itemEnd}")
                    ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_HAIR);

                $sheet->getStyle("H{$this->rowTotal}:I{$this->rowTotal}")->applyFromArray([
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E3EAEA']],
                    'borders' => ['top' => ['borderStyle' => Border::BORDER_THIN]],
                ]);

                $sheet->getStyle('C'.self::ITEM_START.":C{$this->itemEnd}")
                    ->getAlignment()->setWrapText(true);

                $sheet->getColumnDimension('C')->setAutoSize(false)->setWidth(38);
                $sheet->getStyle("I{$this->rowTotal}")
                    ->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);

                $sheet->getPageSetup()->setOrientation('portrait')->setFitToWidth(1)->setFitToHeight(0);
                $sheet->getPageSetup()->setPaperSize(9); // A4
                $sheet->freezePane('A'.self::ITEM_START);
            },
        ];
    }
}
