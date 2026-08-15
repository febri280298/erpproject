/**
 * Editor baris untuk surat jalan lepas (tanpa pesanan penjualan).
 *
 * Sengaja terpisah dari docItems: surat jalan tidak membawa harga, diskon,
 * maupun pajak, sehingga tidak perlu seluruh mesin perhitungan dokumen.
 */
export default function deliveryItems(config = {}) {
    return {
        products: config.products ?? [],
        rows: [],

        init() {
            this.rows = (config.rows ?? []).map((r) => this.normalize(r));
            if (this.rows.length === 0) this.addRow();
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
