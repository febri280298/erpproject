<?php

namespace Database\Seeders;

use App\Models\Master\Partner;
use App\Models\Master\PaymentTerm;
use App\Models\Master\Product;
use App\Models\Master\ProductCategory;
use App\Models\Master\Tax;
use App\Models\Master\Uom;
use App\Models\Master\Warehouse;
use Illuminate\Database\Seeder;

/**
 * Master data for a building-materials trader (toko bangunan / material):
 * besi, cat, semen, kayu, pipa, listrik dan perkakas.
 */
class MasterDataSeeder extends Seeder
{
    /** [code, name] */
    private const UOMS = [
        ['BTG', 'Batang'],
        ['SAK', 'Sak / Zak'],
        ['KLG', 'Kaleng'],
        ['PAIL', 'Pail'],
        ['LBR', 'Lembar'],
        ['ROLL', 'Roll'],
        ['DUS', 'Dus'],
        ['KG', 'Kilogram'],
        ['M3', 'Meter Kubik'],
        ['MTR', 'Meter'],
        ['PCS', 'Pieces'],
        ['SET', 'Set'],
        ['UNIT', 'Unit'],
        ['RIT', 'Rit / Angkutan'],
    ];

    /** [code, name] */
    private const CATEGORIES = [
        ['BSI', 'Besi & Baja'],
        ['CAT', 'Cat & Finishing'],
        ['SMN', 'Semen & Agregat'],
        ['KYU', 'Kayu & Panel'],
        ['PIP', 'Pipa & Sanitasi'],
        ['LST', 'Listrik'],
        ['ALT', 'Alat & Perkakas'],
        ['JAS', 'Jasa'],
    ];

    /** [sku, name, category, uom, harga beli, harga jual, stok minimum] */
    private const PRODUCTS = [
        // Besi & baja
        ['BSI-0001', 'Besi Beton Polos 8 mm x 12 m', 'BSI', 'BTG', 52000, 68000, 100],
        ['BSI-0002', 'Besi Beton Ulir 10 mm x 12 m', 'BSI', 'BTG', 78000, 98000, 80],
        ['BSI-0003', 'Besi Beton Ulir 13 mm x 12 m', 'BSI', 'BTG', 128000, 158000, 60],
        ['BSI-0004', 'Besi Hollow 4x4 cm tebal 1,2 mm', 'BSI', 'BTG', 95000, 122000, 50],
        ['BSI-0005', 'Besi Siku 40x40x3 mm x 6 m', 'BSI', 'BTG', 118000, 148000, 40],
        ['BSI-0006', 'Wiremesh M8 ukuran 2,1 x 5,4 m', 'BSI', 'LBR', 585000, 690000, 20],
        ['BSI-0007', 'Kawat Bendrat', 'BSI', 'KG', 22000, 30000, 50],
        ['BSI-0008', 'Paku Beton 7 cm', 'BSI', 'KG', 24000, 33000, 40],

        // Cat & finishing
        ['CAT-0001', 'Cat Tembok Interior 25 kg', 'CAT', 'PAIL', 385000, 465000, 15],
        ['CAT-0002', 'Cat Tembok Eksterior 20 kg', 'CAT', 'PAIL', 512000, 615000, 12],
        ['CAT-0003', 'Cat Kayu & Besi 1 kg', 'CAT', 'KLG', 78000, 98000, 30],
        ['CAT-0004', 'Cat Dasar / Primer 5 kg', 'CAT', 'KLG', 145000, 182000, 20],
        ['CAT-0005', 'Thinner A Spesial 1 liter', 'CAT', 'KLG', 32000, 45000, 40],
        ['CAT-0006', 'Kuas Cat 3 inci', 'CAT', 'PCS', 18000, 27000, 50],
        ['CAT-0007', 'Roll Cat 9 inci + Bak', 'CAT', 'SET', 45000, 65000, 30],

        // Semen & agregat
        ['SMN-0001', 'Semen PCC 40 kg', 'SMN', 'SAK', 58000, 68000, 200],
        ['SMN-0002', 'Semen Putih 40 kg', 'SMN', 'SAK', 92000, 112000, 30],
        ['SMN-0003', 'Semen Instan Perekat Bata 40 kg', 'SMN', 'SAK', 62000, 78000, 60],
        ['SMN-0004', 'Pasir Beton', 'SMN', 'M3', 285000, 360000, 10],
        ['SMN-0005', 'Split / Koral 1-2 cm', 'SMN', 'M3', 320000, 395000, 10],
        ['SMN-0006', 'Bata Ringan 7,5 cm', 'SMN', 'M3', 620000, 730000, 8],

        // Kayu & panel
        ['KYU-0001', 'Triplek 9 mm 122 x 244 cm', 'KYU', 'LBR', 165000, 205000, 40],
        ['KYU-0002', 'Multipleks 12 mm 122 x 244 cm', 'KYU', 'LBR', 245000, 298000, 30],
        ['KYU-0003', 'Kaso 5x7 cm x 4 m', 'KYU', 'BTG', 62000, 82000, 60],
        ['KYU-0004', 'Gypsum Board 9 mm', 'KYU', 'LBR', 68000, 88000, 50],

        // Pipa & sanitasi
        ['PIP-0001', 'Pipa PVC 3 inci x 4 m', 'PIP', 'BTG', 98000, 128000, 40],
        ['PIP-0002', 'Pipa PVC 1/2 inci x 4 m', 'PIP', 'BTG', 24000, 35000, 60],
        ['PIP-0003', 'Keran Air Kuningan 1/2 inci', 'PIP', 'PCS', 42000, 62000, 40],
        ['PIP-0004', 'Lem Pipa PVC 100 gram', 'PIP', 'KLG', 18000, 28000, 50],

        // Listrik
        ['LST-0001', 'Kabel NYM 2 x 1,5 mm (50 m)', 'LST', 'ROLL', 385000, 465000, 15],
        ['LST-0002', 'Saklar Ganda', 'LST', 'PCS', 22000, 34000, 60],
        ['LST-0003', 'Stop Kontak', 'LST', 'PCS', 19000, 30000, 60],

        // Alat & perkakas
        ['ALT-0001', 'Cangkul + Gagang', 'ALT', 'PCS', 68000, 92000, 20],
        ['ALT-0002', 'Sekop Semen', 'ALT', 'PCS', 55000, 78000, 20],
        ['ALT-0003', 'Meteran 5 m', 'ALT', 'PCS', 32000, 48000, 30],
        ['ALT-0004', 'Ember Cor 20 liter', 'ALT', 'PCS', 25000, 38000, 40],
    ];

    /** [sku, name, uom, harga beli, harga jual] */
    private const SERVICES = [
        ['JAS-0001', 'Jasa Pengiriman dalam Kota', 'RIT', 150000, 250000],
        ['JAS-0002', 'Jasa Potong & Bengkok Besi', 'BTG', 5000, 10000],
    ];

    /** [code, name, type, contact, city] */
    private const PARTNERS = [
        ['CUS-0001', 'CV Karya Bangun Sejahtera', Partner::TYPE_CUSTOMER, 'Andi Wijaya', 'Bekasi'],
        ['CUS-0002', 'Toko Bangunan Sumber Rejeki', Partner::TYPE_CUSTOMER, 'Budi Santoso', 'Cikarang'],
        ['CUS-0003', 'PT Griya Persada Development', Partner::TYPE_CUSTOMER, 'Citra Dewi', 'Jakarta'],
        ['SUP-0001', 'PT Baja Perkasa Utama', Partner::TYPE_SUPPLIER, 'Dedi Kurniawan', 'Bekasi'],
        ['SUP-0002', 'PT Indo Cat Nusantara', Partner::TYPE_SUPPLIER, 'Erna Susanti', 'Tangerang'],
        ['SUP-0003', 'CV Mitra Material Jaya', Partner::TYPE_BOTH, 'Fajar Nugroho', 'Karawang'],
    ];

    public function run(): void
    {
        foreach (self::UOMS as [$code, $name]) {
            Uom::updateOrCreate(['code' => $code], ['name' => $name, 'is_active' => true]);
        }

        foreach ([
            ['PPN11', 'PPN 11%', 11, true],
            ['PPN12', 'PPN 12%', 12, false],
            ['NONPPN', 'Tanpa PPN', 0, false],
        ] as [$code, $name, $rate, $default]) {
            Tax::updateOrCreate(['code' => $code], ['name' => $name, 'rate' => $rate, 'is_default' => $default, 'is_active' => true]);
        }

        foreach ([
            ['CASH', 'Tunai', 0], ['NET7', 'Net 7 hari', 7], ['NET14', 'Net 14 hari', 14],
            ['NET30', 'Net 30 hari', 30], ['NET45', 'Net 45 hari', 45], ['NET60', 'Net 60 hari', 60],
        ] as [$code, $name, $days]) {
            PaymentTerm::updateOrCreate(['code' => $code], ['name' => $name, 'days' => $days, 'is_active' => true]);
        }

        Warehouse::updateOrCreate(['code' => 'GDG-UTM'], [
            'name' => 'Gudang Utama',
            'address' => 'Area gudang depan, lokasi utama',
            'keeper_name' => 'Kepala Gudang',
            'is_default' => true,
            'is_active' => true,
        ]);

        Warehouse::updateOrCreate(['code' => 'GDG-BRT'], [
            'name' => 'Gudang Material Berat',
            'address' => 'Area terbuka untuk pasir, split, dan bata ringan',
            'is_default' => false,
            'is_active' => true,
        ]);

        foreach (self::CATEGORIES as [$code, $name]) {
            ProductCategory::updateOrCreate(['code' => $code], ['name' => $name, 'is_active' => true]);
        }

        $uom = fn (string $code) => Uom::where('code', $code)->value('id');
        $category = fn (string $code) => ProductCategory::where('code', $code)->value('id');
        $ppn = Tax::where('code', 'PPN11')->value('id');
        $nonPpn = Tax::where('code', 'NONPPN')->value('id');

        foreach (self::PRODUCTS as [$sku, $name, $categoryCode, $uomCode, $buy, $sell, $min]) {
            Product::updateOrCreate(['sku' => $sku], [
                'name' => $name,
                'type' => Product::TYPE_STOCK,
                'product_category_id' => $category($categoryCode),
                'uom_id' => $uom($uomCode),
                'tax_id' => $ppn,
                'purchase_price' => $buy,
                'sale_price' => $sell,
                'min_stock' => $min,
                'is_active' => true,
            ]);
        }

        foreach (self::SERVICES as [$sku, $name, $uomCode, $buy, $sell]) {
            Product::updateOrCreate(['sku' => $sku], [
                'name' => $name,
                'type' => Product::TYPE_SERVICE,
                'product_category_id' => $category('JAS'),
                'uom_id' => $uom($uomCode),
                'tax_id' => $nonPpn,
                'purchase_price' => $buy,
                'sale_price' => $sell,
                'is_active' => true,
            ]);
        }

        $net30 = PaymentTerm::where('code', 'NET30')->value('id');

        foreach (self::PARTNERS as [$code, $name, $type, $contact, $city]) {
            Partner::updateOrCreate(['code' => $code], [
                'name' => $name,
                'type' => $type,
                'contact_person' => $contact,
                'city' => $city,
                'phone' => '021-'.random_int(1000000, 9999999),
                'email' => strtolower(str_replace(' ', '', explode(' ', $name)[1] ?? 'info')).'@example.co.id',
                'address' => 'Jl. Raya Industri No. '.random_int(1, 99).", {$city}",
                'payment_term_id' => $net30,
                'credit_limit' => 150000000,
                'is_active' => true,
            ]);
        }

        $this->command?->info(sprintf(
            'Data master material bangunan disiapkan: %d produk, %d jasa, %d mitra.',
            count(self::PRODUCTS),
            count(self::SERVICES),
            count(self::PARTNERS),
        ));
    }
}
