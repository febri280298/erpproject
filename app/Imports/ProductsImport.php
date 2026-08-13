<?php

namespace App\Imports;

use App\Models\Master\Product;
use App\Models\Master\ProductCategory;
use App\Models\Master\Tax;
use App\Models\Master\Uom;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Bulk product import from Excel/CSV.
 *
 * Rows are validated one by one so a single bad line never aborts the whole
 * file: valid rows are saved, invalid ones are reported back with their sheet
 * row number and the reason.
 *
 * Reference columns (kategori, satuan, pajak) are matched on code first, then
 * on name, so a user can type either. When `$createMissingRefs` is on, an
 * unknown category or unit is created instead of failing the row.
 */
class ProductsImport implements ToCollection, WithHeadingRow
{
    public int $created = 0;

    public int $updated = 0;

    public int $skipped = 0;

    /** @var array<int,array{row:int,sku:string,message:string}> */
    public array $errors = [];

    public function __construct(
        private readonly bool $updateExisting = true,
        private readonly bool $createMissingRefs = false,
    ) {}

    public function collection(Collection $rows): void
    {
        // Heading row is row 1, so the first data row is sheet row 2.
        foreach ($rows as $index => $row) {
            $this->handleRow($row->toArray(), $index + 2);
        }
    }

    private function handleRow(array $raw, int $rowNumber): void
    {
        $row = $this->normalise($raw);

        if ($row['sku'] === '' && $row['nama'] === '') {
            return; // blank spacer line
        }

        $validator = Validator::make($row, [
            'sku' => ['required', 'string', 'max:50'],
            'nama' => ['required', 'string', 'max:200'],
            'tipe' => ['required', 'in:stock,service'],
            'harga_beli' => ['required', 'numeric', 'min:0'],
            'harga_jual' => ['required', 'numeric', 'min:0'],
            'stok_min' => ['nullable', 'numeric', 'min:0'],
            'stok_max' => ['nullable', 'numeric', 'min:0'],
            'barcode' => ['nullable', 'string', 'max:50'],
            'deskripsi' => ['nullable', 'string', 'max:2000'],
        ], [], [
            'sku' => 'SKU',
            'nama' => 'Nama produk',
            'tipe' => 'Tipe',
            'harga_beli' => 'Harga beli',
            'harga_jual' => 'Harga jual',
        ]);

        if ($validator->fails()) {
            $this->fail($rowNumber, $row['sku'], implode(' ', $validator->errors()->all()));

            return;
        }

        $existing = Product::withTrashed()->where('sku', $row['sku'])->first();

        // Not an error — the user chose "tambah baru saja", so existing SKUs pass by.
        if ($existing && ! $this->updateExisting) {
            $this->skipped++;

            return;
        }

        try {
            $payload = [
                'name' => $row['nama'],
                'type' => $row['tipe'],
                'product_category_id' => $this->categoryId($row['kategori']),
                'uom_id' => $this->uomId($row['satuan']),
                'tax_id' => $this->taxId($row['pajak']),
                'purchase_price' => round((float) $row['harga_beli'], 2),
                'sale_price' => round((float) $row['harga_jual'], 2),
                'min_stock' => round((float) ($row['stok_min'] ?: 0), 4),
                'max_stock' => round((float) ($row['stok_max'] ?: 0), 4),
                'barcode' => $row['barcode'] ?: null,
                'description' => $row['deskripsi'] ?: null,
                'is_active' => $this->boolean($row['aktif']),
            ];

            DB::transaction(function () use ($existing, $row, $payload) {
                if ($existing) {
                    $existing->restore();
                    $existing->update($payload);
                    $this->updated++;
                } else {
                    Product::create(array_merge($payload, ['sku' => $row['sku']]));
                    $this->created++;
                }
            });
        } catch (\Throwable $e) {
            $this->fail($rowNumber, $row['sku'], $e->getMessage());
        }
    }

    /** @return array<string,string> every expected column as a trimmed string */
    private function normalise(array $raw): array
    {
        $get = function (string ...$keys) use ($raw) {
            foreach ($keys as $key) {
                if (isset($raw[$key]) && $raw[$key] !== null) {
                    return trim((string) $raw[$key]);
                }
            }

            return '';
        };

        $type = Str::lower($get('tipe', 'type'));

        return [
            'sku' => $get('sku', 'kode'),
            'nama' => $get('nama', 'nama_produk', 'name'),
            // Accept the Indonesian words people type instead of the stored value.
            'tipe' => match (true) {
                in_array($type, ['jasa', 'service', 'layanan'], true) => 'service',
                $type === '' => 'stock',
                default => 'stock',
            },
            'kategori' => $get('kategori', 'category'),
            'satuan' => $get('satuan', 'uom', 'unit'),
            'pajak' => $get('pajak', 'tax', 'ppn'),
            'harga_beli' => $this->number($get('harga_beli', 'purchase_price')),
            'harga_jual' => $this->number($get('harga_jual', 'sale_price')),
            'stok_min' => $this->number($get('stok_min', 'min_stock')),
            'stok_max' => $this->number($get('stok_max', 'max_stock')),
            'barcode' => $get('barcode'),
            'deskripsi' => $get('deskripsi', 'description', 'keterangan'),
            'aktif' => $get('aktif', 'is_active', 'status'),
        ];
    }

    /**
     * Accepts `1.250.000,50`, `1250000.5` and `Rp 1.250.000`.
     *
     * A value that cannot be parsed is returned untouched so validation rejects
     * the row — turning a typo into 0 would corrupt prices silently.
     */
    private function number(string $original): string
    {
        if (trim($original) === '') {
            return '0';
        }

        $value = preg_replace('/[^\d,.\-]/', '', $original) ?? '';

        if ($value === '') {
            return $original;
        }

        $lastComma = strrpos($value, ',');
        $lastDot = strrpos($value, '.');

        if ($lastComma !== false) {
            // Whichever separator comes last is the decimal one:
            // "1.250.000,50" → comma decimal, "1,250,000.50" → dot decimal.
            $value = $lastDot === false || $lastComma > $lastDot
                ? str_replace(['.', ','], ['', '.'], $value)
                : str_replace(',', '', $value);
        } elseif (substr_count($value, '.') > 1) {
            $value = str_replace('.', '', $value);           // 1.250.000
        } elseif (preg_match('/\.\d{3}$/', $value)) {
            // A lone dot with exactly three digits after it is the Indonesian
            // thousand separator: "10.500" means ten thousand five hundred.
            $value = str_replace('.', '', $value);
        }

        return is_numeric($value) ? $value : $original;
    }

    private function boolean(string $value): bool
    {
        if ($value === '') {
            return true;
        }

        return in_array(Str::lower($value), ['1', 'ya', 'y', 'aktif', 'true', 'active'], true);
    }

    private function categoryId(string $value): ?int
    {
        if ($value === '') {
            return null;
        }

        $category = ProductCategory::where('code', $value)->orWhere('name', $value)->first();

        if (! $category && $this->createMissingRefs) {
            $category = ProductCategory::create([
                'code' => Str::upper(Str::limit(Str::slug($value, ''), 28, '')),
                'name' => $value,
                'is_active' => true,
            ]);
        }

        if (! $category) {
            throw new \RuntimeException("Kategori \"{$value}\" tidak ditemukan.");
        }

        return $category->id;
    }

    private function uomId(string $value): ?int
    {
        if ($value === '') {
            return null;
        }

        $uom = Uom::where('code', $value)->orWhere('name', $value)->first();

        if (! $uom && $this->createMissingRefs) {
            $uom = Uom::create([
                'code' => Str::upper(Str::limit($value, 18, '')),
                'name' => $value,
                'is_active' => true,
            ]);
        }

        if (! $uom) {
            throw new \RuntimeException("Satuan \"{$value}\" tidak ditemukan.");
        }

        return $uom->id;
    }

    private function taxId(string $value): ?int
    {
        if ($value === '') {
            return Tax::where('is_default', true)->value('id');
        }

        $tax = Tax::where('code', $value)->orWhere('name', $value)->first();

        // Taxes are never auto-created: an invented rate would silently skew invoices.
        if (! $tax) {
            throw new \RuntimeException("Pajak \"{$value}\" tidak ditemukan.");
        }

        return $tax->id;
    }

    private function fail(int $row, string $sku, string $message): void
    {
        $this->errors[] = ['row' => $row, 'sku' => $sku, 'message' => $message];
    }

    public function total(): int
    {
        return $this->created + $this->updated;
    }
}
