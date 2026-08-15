<?php

namespace Database\Seeders;

use App\Models\Master\Partner;
use App\Models\Master\PriceLevel;
use App\Models\Master\Product;
use App\Services\PricingService;
use Illuminate\Database\Seeder;

/**
 * Sale tiers typical for a building-materials trader, plus a starting price
 * list per supplier so PO forms have something to pull from.
 */
class PricingSeeder extends Seeder
{
    /** [code, name, description, urutan, diskon % dari harga jual dasar, default] */
    private const LEVELS = [
        ['ECERAN', 'Eceran', 'Pembeli umum / satuan', 1, 0, true],
        ['GROSIR', 'Grosir', 'Pembelian dalam jumlah banyak', 2, 4, false],
        ['PROYEK', 'Proyek', 'Kontraktor & proyek pembangunan', 3, 8, false],
        ['RESELLER', 'Reseller', 'Toko bangunan mitra', 4, 6, false],
        ['KARYAWAN', 'Karyawan', 'Harga khusus karyawan', 5, 10, false],
    ];

    public function run(PricingService $pricing): void
    {
        foreach (self::LEVELS as [$code, $name, $description, $order, $discount, $isDefault]) {
            PriceLevel::updateOrCreate(['code' => $code], [
                'name' => $name,
                'description' => $description,
                'sort_order' => $order,
                'is_default' => $isDefault,
                'is_active' => true,
            ]);
        }

        $levels = PriceLevel::ordered()->get();
        $discounts = collect(self::LEVELS)->mapWithKeys(fn ($l) => [$l[0] => $l[4]]);

        // Customers get a tier that matches what they are.
        Partner::where('code', 'CUS-0001')->update(['price_level_id' => $levels->firstWhere('code', 'PROYEK')?->id]);
        Partner::where('code', 'CUS-0002')->update(['price_level_id' => $levels->firstWhere('code', 'RESELLER')?->id]);
        Partner::where('code', 'CUS-0003')->update(['price_level_id' => $levels->firstWhere('code', 'PROYEK')?->id]);

        // Which supplier carries which category — mirrors how a real yard buys.
        $byCategory = [
            'BSI' => ['SUP-0001', 'SUP-0003'],
            'SMN' => ['SUP-0003', 'SUP-0001'],
            'CAT' => ['SUP-0002', 'SUP-0003'],
            'PIP' => ['SUP-0002', 'SUP-0003'],
            'KYU' => ['SUP-0003'],
            'LST' => ['SUP-0002'],
            'ALT' => ['SUP-0003'],
        ];

        $suppliers = Partner::whereIn('code', ['SUP-0001', 'SUP-0002', 'SUP-0003'])->get()->keyBy('code');
        $products = Product::with('category')->get();
        $salePriced = 0;
        $supplierPriced = 0;

        foreach ($products as $product) {
            $base = (float) $product->sale_price;

            if ($base > 0) {
                $rows = $levels->map(fn (PriceLevel $level) => [
                    'price_level_id' => $level->id,
                    // Round to the nearest 500 rupiah — how prices are quoted here.
                    'price' => round($base * (1 - ($discounts[$level->code] ?? 0) / 100) / 500) * 500,
                    'min_qty' => 0,
                ])->all();

                $pricing->syncSalePrices($product, $rows, 'import');
                $salePriced++;
            }

            $codes = $byCategory[$product->category?->code] ?? [];
            $cost = (float) $product->purchase_price;

            if ($codes === [] || $cost <= 0) {
                continue;
            }

            $rows = [];

            foreach ($codes as $index => $code) {
                $supplier = $suppliers->get($code);

                if (! $supplier) {
                    continue;
                }

                // The first listed supplier is the preferred one and quotes best.
                $rows[] = [
                    'partner_id' => $supplier->id,
                    'price' => round($cost * (1 + $index * 0.035) / 500) * 500,
                    'lead_time_days' => $index === 0 ? 3 : 7,
                    'min_order_qty' => 0,
                    'is_preferred' => $index === 0,
                ];
            }

            if ($rows !== []) {
                $pricing->syncSupplierPrices($product, $rows, 'import');
                $supplierPriced++;
            }
        }

        $this->command?->info(sprintf(
            'Harga disiapkan: %d tingkat, %d produk berharga jual, %d produk punya harga supplier.',
            $levels->count(),
            $salePriced,
            $supplierPriced,
        ));
    }
}
