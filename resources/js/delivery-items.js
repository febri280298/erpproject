/**
 * Editor baris untuk surat jalan lepas (tanpa pesanan penjualan).
 *
 * Sengaja terpisah dari docItems: surat jalan tidak membawa harga, diskon,
 * maupun pajak, sehingga tidak perlu seluruh mesin perhitungan dokumen.
 */
export default function deliveryItems(config = {}) {
    return {
        products: config.products ?? [],

        /**
         * Sisa stok seluruh gudang: { gudangId: { produkId: jumlah } }.
         *
         * Dikirim utuh dari server, bukan diambil per baris lewat permintaan
         * terpisah: gudang bisa diganti setelah halaman terbuka, dan menunggu
         * jawaban server tiap kali produk dipilih membuat isian terasa berat.
         */
        stok: config.stok ?? {},
        warehouseId: config.warehouseId ?? '',

        rows: [],

        init() {
            this.rows = (config.rows ?? []).map((r) => this.normalize(r));
            if (this.rows.length === 0) this.addRow();

            // Nilai awal gudang dibaca dari isiannya sendiri, supaya kolom Stok
            // sudah terisi benar sejak halaman pertama kali tampil.
            const isian = this.$el.querySelector('select[name="warehouse_id"]');

            if (isian && ! this.warehouseId) {
                this.warehouseId = isian.value;
            }
        },

        /** Sisa stok produk pada baris ini di gudang yang sedang dipilih. */
        stokBaris(row) {
            if (! row.product_id || ! this.warehouseId) {
                return '—';
            }

            const jumlah = this.stok?.[this.warehouseId]?.[row.product_id] ?? 0;

            return window.erp.fmtNumber(jumlah);
        },

        kurangStok(row) {
            if (! row.product_id || ! this.warehouseId) {
                return false;
            }

            const tersedia = this.stok?.[this.warehouseId]?.[row.product_id] ?? 0;

            return Number(row.quantity) > tersedia;
        },

        normalize(row = {}) {
            return {
                product_id: row.product_id ?? '',
                quantity: row.quantity ?? 1,
                notes: row.notes ?? '',
                uom: row.uom ?? '',
            };
        },

        addRow() {
            this.rows.push(this.normalize({}));
        },

        removeRow(index) {
            this.rows.splice(index, 1);
            if (this.rows.length === 0) this.addRow();
        },

        onProductChange(index) {
            const row = this.rows[index];
            const product = this.products.find((p) => String(p.id) === String(row.product_id));

            row.uom = product?.uom ?? '';
        },

        validate(event) {
            const kosong = this.rows.some((r) => !r.product_id || !(Number(r.quantity) > 0));

            if (kosong) {
                event.preventDefault();
                window.alert('Setiap baris harus memiliki produk dan jumlah kirim lebih dari nol.');
            }
        },
    };
}
