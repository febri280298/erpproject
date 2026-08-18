<?php

namespace App\Exports;

use App\Models\Sales\Quotation;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * The quotation list as a flat sheet, honouring whatever filters are active on
 * the index page — what you see on screen is what lands in the file.
 */
class QuotationsExport implements FromCollection, ShouldAutoSize, WithColumnFormatting, WithHeadings, WithStyles, WithTitle
{
    /** @param array<string,mixed> $filters */
    public function __construct(private readonly array $filters = []) {}

    public function title(): string
    {
        return 'Daftar Penawaran';
    }

    public function headings(): array
    {
        return [
            'No. Penawaran', 'Tanggal', 'Berlaku s.d.', 'Customer', 'Status',
            'Jumlah Item', 'Subtotal', 'Diskon', 'Biaya Kirim', 'PPN', 'Total', 'Dibuat Oleh',
        ];
    }

    public function collection(): Collection
    {
        return Quotation::query()
            ->with('customer:id,name', 'creator:id,name')
            ->withCount('items')
            ->filter($this->filters)
            ->latest('date')
            ->latest('id')
            ->get()
            ->map(fn (Quotation $q) => [
                $q->quotation_no,
                $q->date?->format('d/m/Y'),
                $q->valid_until?->format('d/m/Y'),
                $q->customer?->name,
                __('status.'.$q->status),
                $q->items_count,
                (float) $q->subtotal,
                (float) $q->discount_amount,
                (float) $q->shipping_cost,
                (float) $q->tax_amount,
                (float) $q->total,
                $q->creator?->name,
            ]);
    }

    public function columnFormats(): array
    {
        // Mengikuti pengaturan desimal, sama seperti tampilan layar.
        return array_fill_keys(['G', 'H', 'I', 'J', 'K'], desimal() === 2 ? '#,##0.00' : '#,##0');
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
