<?php

/**
 * Single source of truth for the module map.
 *
 * `permissions` drives the RBAC seeder and the `can:` checks on routes.
 * `menu` drives the sidebar — a menu entry is hidden when the user lacks the
 * permission attached to it, so navigation and authorisation never drift apart.
 * `modules` lets an installation switch whole areas off; the flags live in
 * `settings` so they can be toggled from Pengaturan → Modul without a deploy.
 */
return [

    'version' => '1.0.0',

    'currency' => env('ERP_CURRENCY', 'IDR'),
    'default_tax_rate' => (float) env('ERP_TAX_RATE', 11),

    /*
    |--------------------------------------------------------------------------
    | Modules
    |--------------------------------------------------------------------------
    | `core` modules can never be switched off — the rest of the system depends
    | on them. `default` only applies to a fresh install; after that the value
    | in `settings.modules_enabled` wins.
    |
    | Switching a module off hides its menu and returns 404 for its routes. It
    | never deletes data, and accounting entries keep being written in the
    | background so the books stay complete if it is switched back on later.
    */
    'modules' => [
        'master' => [
            'label' => 'Master Data',
            'description' => 'Produk, kategori, satuan, pajak, termin, gudang, mitra bisnis.',
            'icon' => 'ti ti-database',
            'core' => true,
            'default' => true,
        ],
        'inventory' => [
            'label' => 'Persediaan',
            'description' => 'Stok per gudang, kartu stok, transfer, penyesuaian/opname.',
            'icon' => 'ti ti-building-warehouse',
            'core' => true,
            'default' => true,
        ],
        'purchasing' => [
            'label' => 'Pembelian',
            'description' => 'Pesanan pembelian, penerimaan barang, faktur pembelian, pembayaran pemasok.',
            'icon' => 'ti ti-shopping-cart',
            'default' => true,
        ],
        'purchase_requisition' => [
            'label' => 'Permintaan Pembelian',
            'description' => 'Tahap pengajuan sebelum pesanan pembelian dibuat. Matikan agar alur langsung ke PO.',
            'icon' => 'ti ti-clipboard-list',
            'requires' => 'purchasing',
            'default' => false,
        ],
        'sales' => [
            'label' => 'Penjualan',
            'description' => 'Pesanan penjualan, surat jalan, faktur pelanggan, penerimaan pembayaran.',
            'icon' => 'ti ti-receipt',
            'default' => true,
        ],
        'quotation' => [
            'label' => 'Penawaran',
            'description' => 'Tahap penawaran harga sebelum pesanan penjualan. Matikan agar alur langsung ke SO.',
            'icon' => 'ti ti-file-text',
            'requires' => 'sales',
            'default' => false,
        ],
        'manufacturing' => [
            'label' => 'Produksi',
            'description' => 'Bill of Materials dan perintah produksi. Matikan untuk usaha dagang/distribusi.',
            'icon' => 'ti ti-tools',
            'default' => false,
        ],
        'accounting' => [
            'label' => 'Akuntansi',
            'description' => 'Bagan akun, jurnal, buku besar, laba rugi, neraca, periode fiskal. '
                .'Jurnal tetap dicatat di belakang layar meski modul ini dimatikan.',
            'icon' => 'ti ti-calculator',
            'default' => false,
        ],
        'hr' => [
            'label' => 'SDM',
            'description' => 'Karyawan, departemen, jabatan, absensi, cuti, penggajian.',
            'icon' => 'ti ti-users',
            'default' => false,
        ],
        'reports' => [
            'label' => 'Laporan',
            'description' => 'Laporan penjualan, pembelian, persediaan, umur piutang & utang.',
            'icon' => 'ti ti-chart-bar',
            'default' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Permissions — "<subject>.<action>"
    |--------------------------------------------------------------------------
    */
    'permissions' => [
        'dashboard' => ['view'],

        // Master data
        'product' => ['view', 'create', 'edit', 'delete'],
        'category' => ['view', 'create', 'edit', 'delete'],
        'uom' => ['view', 'create', 'edit', 'delete'],
        'tax' => ['view', 'create', 'edit', 'delete'],
        'price-level' => ['view', 'create', 'edit', 'delete'],
        'payment-term' => ['view', 'create', 'edit', 'delete'],
        'warehouse' => ['view', 'create', 'edit', 'delete'],
        'partner' => ['view', 'create', 'edit', 'delete'],

        // Purchasing
        'purchase-requisition' => ['view', 'create', 'edit', 'delete', 'approve'],
        'purchase-order' => ['view', 'create', 'edit', 'delete', 'approve'],
        'goods-receipt' => ['view', 'create', 'edit', 'delete', 'post'],
        'purchase-invoice' => ['view', 'create', 'edit', 'delete', 'post'],
        'supplier-payment' => ['view', 'create', 'edit', 'delete', 'post'],

        // Sales
        'quotation' => ['view', 'create', 'edit', 'delete'],
        'sales-order' => ['view', 'create', 'edit', 'delete', 'approve'],
        'delivery-order' => ['view', 'create', 'edit', 'delete', 'post'],
        'sales-invoice' => ['view', 'create', 'edit', 'delete', 'post'],
        'customer-payment' => ['view', 'create', 'edit', 'delete', 'post'],

        // Inventory
        'stock' => ['view'],
        'stock-transfer' => ['view', 'create', 'edit', 'delete', 'post'],
        'stock-adjustment' => ['view', 'create', 'edit', 'delete', 'post'],

        // Manufacturing
        'bom' => ['view', 'create', 'edit', 'delete'],
        'production-order' => ['view', 'create', 'edit', 'delete', 'post'],

        // Accounting
        'account' => ['view', 'create', 'edit', 'delete'],
        'journal' => ['view', 'create', 'edit', 'delete', 'post'],
        'fiscal-period' => ['view', 'edit'],
        'accounting-report' => ['view'],

        // HR
        'employee' => ['view', 'create', 'edit', 'delete'],
        'department' => ['view', 'create', 'edit', 'delete'],
        'position' => ['view', 'create', 'edit', 'delete'],
        'attendance' => ['view', 'create', 'edit', 'delete'],
        'leave' => ['view', 'create', 'edit', 'delete', 'approve'],
        'payroll' => ['view', 'create', 'edit', 'delete', 'approve'],

        // Reports & system
        'report' => ['view'],
        'user' => ['view', 'create', 'edit', 'delete'],
        'role' => ['view', 'create', 'edit', 'delete'],
        'setting' => ['view', 'edit'],
        'activity-log' => ['view'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Permission matrix labels
    |--------------------------------------------------------------------------
    | Drives the role editor: `permission_actions` are the table columns and
    | `permission_groups` the rows, grouped exactly like the sidebar so an admin
    | recognises what each switch controls.
    */
    'permission_actions' => [
        'view' => 'Lihat',
        'create' => 'Buat',
        'edit' => 'Ubah',
        'delete' => 'Hapus',
        'post' => 'Posting',
        'approve' => 'Setujui',
    ],

    'permission_groups' => [
        'Umum' => [
            'module' => null,
            'subjects' => [
                'dashboard' => 'Dashboard',
                'report' => 'Laporan Operasional',
            ],
        ],
        'Data Master' => [
            'module' => 'master',
            'subjects' => [
                'product' => 'Produk / Barang',
                'partner' => 'Customer & Supplier',
                'category' => 'Kategori Produk',
                'uom' => 'Satuan (UOM)',
                'tax' => 'Pajak (PPN)',
                'price-level' => 'Tingkat Harga',
                'payment-term' => 'Termin Pembayaran (TOP)',
                'warehouse' => 'Gudang',
            ],
        ],
        'Pembelian' => [
            'module' => 'purchasing',
            'subjects' => [
                'purchase-requisition' => 'Permintaan Pembelian (PR)',
                'purchase-order' => 'Pesanan Pembelian (PO)',
                'goods-receipt' => 'Penerimaan Barang (GRN)',
                'purchase-invoice' => 'Faktur Pembelian',
                'supplier-payment' => 'Pembayaran ke Supplier',
            ],
        ],
        'Penjualan' => [
            'module' => 'sales',
            'subjects' => [
                'quotation' => 'Penawaran',
                'sales-order' => 'Pesanan Penjualan (SO)',
                'delivery-order' => 'Surat Jalan (DO)',
                'sales-invoice' => 'Faktur Penjualan',
                'customer-payment' => 'Pembayaran dari Customer',
            ],
        ],
        'Stok & Gudang' => [
            'module' => 'inventory',
            'subjects' => [
                'stock' => 'Stok Barang & Kartu Stok',
                'stock-transfer' => 'Transfer Gudang',
                'stock-adjustment' => 'Penyesuaian Stok',
            ],
        ],
        'Produksi' => [
            'module' => 'manufacturing',
            'subjects' => [
                'bom' => 'Resep Produk (BOM)',
                'production-order' => 'Perintah Produksi (WO)',
            ],
        ],
        'Akuntansi' => [
            'module' => 'accounting',
            'subjects' => [
                'account' => 'Bagan Akun (COA)',
                'journal' => 'Jurnal Umum',
                'accounting-report' => 'Laporan Keuangan',
                'fiscal-period' => 'Periode Fiskal',
            ],
        ],
        'SDM' => [
            'module' => 'hr',
            'subjects' => [
                'employee' => 'Karyawan',
                'department' => 'Departemen',
                'position' => 'Jabatan',
                'attendance' => 'Absensi',
                'leave' => 'Cuti',
                'payroll' => 'Penggajian',
            ],
        ],
        'Sistem' => [
            'module' => null,
            'subjects' => [
                'user' => 'Pengguna',
                'role' => 'Peran & Izin',
                'setting' => 'Pengaturan Sistem',
                'activity-log' => 'Log Aktivitas',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Roles — permission patterns; `*` grants everything
    |--------------------------------------------------------------------------
    */
    'roles' => [
        'Super Admin' => ['*'],
        'Manajer' => [
            'dashboard.*', 'report.*', 'accounting-report.*',
            'product.view', 'partner.view', 'warehouse.view', 'stock.view',
            'purchase-requisition.*', 'purchase-order.*', 'sales-order.*',
            'leave.*', 'payroll.view', 'employee.view', 'journal.view', 'account.view',
        ],
        'Pembelian' => [
            'dashboard.view', 'report.view',
            'product.view', 'partner.view', 'partner.create', 'partner.edit', 'warehouse.view', 'stock.view',
            'purchase-requisition.*', 'purchase-order.view', 'purchase-order.create', 'purchase-order.edit',
            'goods-receipt.view', 'purchase-invoice.view',
        ],
        'Penjualan' => [
            'dashboard.view', 'report.view',
            'product.view', 'partner.view', 'partner.create', 'partner.edit', 'stock.view',
            'quotation.*', 'sales-order.view', 'sales-order.create', 'sales-order.edit',
            'delivery-order.view', 'sales-invoice.view', 'customer-payment.view',
        ],
        'Gudang' => [
            'dashboard.view', 'report.view',
            'product.view', 'warehouse.view', 'stock.view',
            'goods-receipt.*', 'delivery-order.*', 'stock-transfer.*', 'stock-adjustment.*',
            'purchase-order.view', 'sales-order.view', 'bom.view', 'production-order.*',
        ],
        'Akuntansi' => [
            'dashboard.view', 'report.view', 'accounting-report.*',
            'product.view', 'partner.view', 'stock.view',
            'account.*', 'journal.*', 'fiscal-period.*',
            'purchase-invoice.*', 'supplier-payment.*', 'sales-invoice.*', 'customer-payment.*',
            'purchase-order.view', 'sales-order.view', 'goods-receipt.view', 'delivery-order.view',
        ],
        'HRD' => [
            'dashboard.view', 'report.view',
            'employee.*', 'department.*', 'position.*', 'attendance.*', 'leave.*', 'payroll.*',
        ],
        'Staf' => [
            'dashboard.view', 'product.view', 'partner.view', 'stock.view', 'report.view',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Sidebar
    |--------------------------------------------------------------------------
    | Labels carry the abbreviation people actually say out loud (PO, SO, DO,
    | GRN, Invoice) so a menu can be found by the word used on the floor.
    | `step` numbers the transaction chain; `hint` is the one-line explanation
    | shown under the label.
    */
    'menu' => [
        [
            'label' => 'Dashboard',
            'icon' => 'ti ti-home',
            'route' => 'dashboard',
            'permission' => 'dashboard.view',
        ],
        [
            'label' => 'Data Master',
            'icon' => 'ti ti-database',
            'children' => [
                ['label' => 'Produk / Barang', 'hint' => 'Daftar barang & jasa', 'route' => 'products.index', 'permission' => 'product.view'],
                ['label' => 'Upload Produk', 'hint' => 'Impor massal dari Excel', 'route' => 'products.import', 'permission' => 'product.create'],
                ['label' => 'Customer & Supplier', 'hint' => 'Data pelanggan dan pemasok', 'route' => 'partners.index', 'permission' => 'partner.view'],
                ['label' => 'Kategori Produk', 'hint' => 'Pengelompokan barang', 'route' => 'categories.index', 'permission' => 'category.view'],
                ['label' => 'Satuan (UOM)', 'hint' => 'Pcs, box, kg, unit', 'route' => 'uoms.index', 'permission' => 'uom.view'],
                ['label' => 'Pajak (PPN)', 'hint' => 'Tarif pajak dokumen', 'route' => 'taxes.index', 'permission' => 'tax.view'],
                ['label' => 'Tingkat Harga', 'hint' => 'Eceran, grosir, proyek', 'route' => 'price-levels.index', 'permission' => 'price-level.view'],
                ['label' => 'Termin Pembayaran (TOP)', 'hint' => 'Tunai, Net 30, dst', 'route' => 'payment-terms.index', 'permission' => 'payment-term.view'],
                ['label' => 'Gudang', 'hint' => 'Lokasi penyimpanan stok', 'route' => 'warehouses.index', 'permission' => 'warehouse.view'],
            ],
        ],
        [
            'label' => 'Pembelian',
            'icon' => 'ti ti-shopping-cart',
            'module' => 'purchasing',
            'children' => [
                ['label' => 'Permintaan Pembelian (PR)', 'hint' => 'Pengajuan kebutuhan barang', 'step' => '0', 'route' => 'purchase-requisitions.index', 'permission' => 'purchase-requisition.view', 'module' => 'purchase_requisition'],
                ['label' => 'Pesanan Pembelian (PO)', 'hint' => 'Pesan barang ke supplier', 'step' => '1', 'route' => 'purchase-orders.index', 'permission' => 'purchase-order.view'],
                ['label' => 'Penerimaan Barang (GRN)', 'hint' => 'Barang datang, stok bertambah', 'step' => '2', 'route' => 'goods-receipts.index', 'permission' => 'goods-receipt.view'],
                ['label' => 'Faktur Pembelian (Invoice)', 'hint' => 'Tagihan masuk dari supplier', 'step' => '3', 'route' => 'purchase-invoices.index', 'permission' => 'purchase-invoice.view'],
                ['label' => 'Pembayaran ke Supplier', 'hint' => 'Lunasi tagihan supplier', 'step' => '4', 'route' => 'supplier-payments.index', 'permission' => 'supplier-payment.view'],
            ],
        ],
        [
            'label' => 'Penjualan',
            'icon' => 'ti ti-receipt',
            'module' => 'sales',
            'children' => [
                ['label' => 'Penawaran (Quotation)', 'hint' => 'Penawaran harga ke calon customer', 'step' => '0', 'route' => 'quotations.index', 'permission' => 'quotation.view', 'module' => 'quotation'],
                ['label' => 'Pesanan Penjualan (SO)', 'hint' => 'Order masuk dari customer', 'step' => '1', 'route' => 'sales-orders.index', 'permission' => 'sales-order.view'],
                ['label' => 'Surat Jalan (DO)', 'hint' => 'Kirim barang, stok berkurang', 'step' => '2', 'route' => 'delivery-orders.index', 'permission' => 'delivery-order.view'],
                ['label' => 'Faktur Penjualan (Invoice)', 'hint' => 'Tagihan ke customer', 'step' => '3', 'route' => 'sales-invoices.index', 'permission' => 'sales-invoice.view'],
                ['label' => 'Pembayaran dari Customer', 'hint' => 'Customer melunasi tagihan', 'step' => '4', 'route' => 'customer-payments.index', 'permission' => 'customer-payment.view'],
            ],
        ],
        [
            'label' => 'Stok & Gudang',
            'icon' => 'ti ti-building-warehouse',
            'module' => 'inventory',
            'children' => [
                ['label' => 'Stok Barang', 'hint' => 'Sisa stok per gudang', 'route' => 'stocks.index', 'permission' => 'stock.view'],
                ['label' => 'Kartu Stok', 'hint' => 'Riwayat keluar-masuk barang', 'route' => 'stocks.card', 'permission' => 'stock.view'],
                ['label' => 'Transfer Gudang', 'hint' => 'Pindah stok antar gudang', 'route' => 'stock-transfers.index', 'permission' => 'stock-transfer.view'],
                ['label' => 'Penyesuaian Stok', 'hint' => 'Stok opname: fisik vs sistem', 'route' => 'stock-adjustments.index', 'permission' => 'stock-adjustment.view'],
            ],
        ],
        [
            'label' => 'Produksi',
            'icon' => 'ti ti-tools',
            'module' => 'manufacturing',
            'children' => [
                ['label' => 'Resep Produk (BOM)', 'hint' => 'Komposisi bahan per produk jadi', 'step' => '1', 'route' => 'boms.index', 'permission' => 'bom.view'],
                ['label' => 'Perintah Produksi (WO)', 'hint' => 'Jalankan produksi & hitung HPP', 'step' => '2', 'route' => 'production-orders.index', 'permission' => 'production-order.view'],
            ],
        ],
        [
            'label' => 'Akuntansi',
            'icon' => 'ti ti-calculator',
            'module' => 'accounting',
            'children' => [
                ['label' => 'Bagan Akun (COA)', 'hint' => 'Daftar akun pembukuan', 'route' => 'accounts.index', 'permission' => 'account.view'],
                ['label' => 'Jurnal Umum', 'hint' => 'Entri debit & kredit', 'route' => 'journals.index', 'permission' => 'journal.view'],
                ['label' => 'Buku Besar', 'hint' => 'Mutasi per akun', 'route' => 'accounting.ledger', 'permission' => 'accounting-report.view'],
                ['label' => 'Neraca Saldo', 'hint' => 'Saldo semua akun', 'route' => 'accounting.trial-balance', 'permission' => 'accounting-report.view'],
                ['label' => 'Laba Rugi', 'hint' => 'Pendapatan dikurangi beban', 'route' => 'accounting.income-statement', 'permission' => 'accounting-report.view'],
                ['label' => 'Neraca', 'hint' => 'Aset, utang, dan modal', 'route' => 'accounting.balance-sheet', 'permission' => 'accounting-report.view'],
                ['label' => 'Periode Fiskal', 'hint' => 'Kunci bulan yang sudah ditutup', 'route' => 'fiscal-periods.index', 'permission' => 'fiscal-period.view'],
            ],
        ],
        [
            'label' => 'SDM',
            'icon' => 'ti ti-users',
            'module' => 'hr',
            'children' => [
                ['label' => 'Karyawan', 'hint' => 'Data pegawai & gaji pokok', 'route' => 'employees.index', 'permission' => 'employee.view'],
                ['label' => 'Departemen', 'hint' => 'Struktur bagian perusahaan', 'route' => 'departments.index', 'permission' => 'department.view'],
                ['label' => 'Jabatan', 'hint' => 'Posisi & gaji dasarnya', 'route' => 'positions.index', 'permission' => 'position.view'],
                ['label' => 'Absensi', 'hint' => 'Kehadiran harian & lembur', 'route' => 'attendances.index', 'permission' => 'attendance.view'],
                ['label' => 'Cuti', 'hint' => 'Pengajuan & persetujuan cuti', 'route' => 'leaves.index', 'permission' => 'leave.view'],
                ['label' => 'Penggajian (Payroll)', 'hint' => 'Hitung gaji & slip bulanan', 'route' => 'payrolls.index', 'permission' => 'payroll.view'],
            ],
        ],
        [
            'label' => 'Laporan',
            'icon' => 'ti ti-chart-bar',
            'module' => 'reports',
            'children' => [
                ['label' => 'Omzet Penjualan', 'hint' => 'Penjualan per periode & customer', 'route' => 'reports.sales', 'permission' => 'report.view', 'module' => 'sales'],
                ['label' => 'Belanja Pembelian', 'hint' => 'Pembelian per periode & supplier', 'route' => 'reports.purchasing', 'permission' => 'report.view', 'module' => 'purchasing'],
                ['label' => 'Nilai Persediaan', 'hint' => 'Nilai stok saat ini', 'route' => 'reports.inventory', 'permission' => 'report.view'],
                ['label' => 'Umur Piutang (AR)', 'hint' => 'Tagihan customer jatuh tempo', 'route' => 'reports.receivable-aging', 'permission' => 'report.view', 'module' => 'sales'],
                ['label' => 'Umur Utang (AP)', 'hint' => 'Tagihan supplier jatuh tempo', 'route' => 'reports.payable-aging', 'permission' => 'report.view', 'module' => 'purchasing'],
                ['label' => 'Produk Terlaris', 'hint' => 'Peringkat barang paling laku', 'route' => 'reports.top-products', 'permission' => 'report.view', 'module' => 'sales'],
            ],
        ],
        [
            'label' => 'Pengaturan',
            'icon' => 'ti ti-settings',
            'children' => [
                ['label' => 'Pengguna', 'hint' => 'Akun login karyawan', 'route' => 'users.index', 'permission' => 'user.view'],
                ['label' => 'Peran & Izin', 'hint' => 'Hak akses tiap peran', 'route' => 'roles.index', 'permission' => 'role.view'],
                ['label' => 'Pengaturan Sistem', 'hint' => 'Profil, modul, nomor dokumen', 'route' => 'settings.edit', 'permission' => 'setting.view'],
                ['label' => 'Log Aktivitas', 'hint' => 'Jejak perubahan data', 'route' => 'activity-logs.index', 'permission' => 'activity-log.view'],
            ],
        ],
    ],
];
