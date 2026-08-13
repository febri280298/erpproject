<?php

namespace App\Exports;

use App\Models\Master\Product;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Exports the product master using exactly the same column names the importer
 * reads, so an exported file can be edited and uploaded straight back.
 */
class ProductsExport implements FromCollection, ShouldAutoSize, WithHeadings, WithStyles, WithTitle
{
    public function __construct(private readonly bool $templateOnly = false) {}

    public function title(): string
    {
        return $this->templateOnly ? 'Template Produk' : 'Data Produk';
    }

    public function headings(): array
    {
        return [
            'sku', 'nama', 'tipe', 'kategori', 'satuan', 'pajak',
            'harga_beli', 'harga_jual', 'stok_min', 'stok_max',
            'barcode', 'deskripsi', 'aktif',
        ];
    }

    public function collection(): Collection
    {
        if ($this->templateOnly) {
            return collect([
                ['BRG-0001', 'Contoh Barang', 'stock', 'Elektronik', 'PCS', 'PPN11', 100000, 135000, 5, 0, '', 'Baris contoh — hapus sebelum diunggah', 'ya'],
                ['JSA-0001', 'Contoh Jasa', 'jasa', 'Jasa', 'JAM', 'NONPPN', 150000, 250000, 0, 0, '', '', 'ya'],
            ]);
        }

        return Product::query()
            ->with('category:id,name', 'uom:id,code', 'tax:id,code')
            ->orderBy('sku')
            ->get()
            ->map(fn (Product $p) => [
                $p->sku,
                $p->name,
                $p->type,
                $p->category?->name,
                $p->uom?->code,
                $p->tax?->code,
                (float) $p->purchase_price,
                (float) $p->sale_price,
                (float) $p->min_stock,
                (float) $p->max_stock,
                $p->barcode,
                $p->description,
                $p->is_active ? 'ya' : 'tidak',
            ]);
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
