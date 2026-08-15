import { parseNum } from './helpers';

/**
 * Alpine component behind the "Harga Beli per Supplier" and "Harga Jual Khusus
 * per Customer" tables on the product form.
 *
 * Rows are added on demand instead of listing every partner, so the form stays
 * usable when there are hundreds of them. A partner already in the table is
 * removed from the picker, which is what prevents duplicate rows (the database
 * also has a unique index as the real guard).
 */
export default function partnerPriceRows(config = {}) {
    return {
        /** Form field prefix: `supplier_prices` or `customer_prices`. */
        name: config.name ?? 'supplier_prices',
        /** [{ id, label }] */
        options: config.options ?? [],
        rows: [],
        picker: '',
        /** Only meaningful for suppliers. */
        preferredId: config.preferredId ? String(config.preferredId) : '',
        withSupplierFields: config.withSupplierFields ?? false,

        init() {
            this.rows = (config.rows ?? []).map((r) => this.normalize(r));
        },

        normalize(row = {}) {
            return {
                partner_id: String(row.partner_id ?? ''),
                price: parseNum(row.price ?? 0),
                min_qty: parseNum(row.min_qty ?? 0),
                min_order_qty: parseNum(row.min_order_qty ?? 0),
                lead_time_days: parseNum(row.lead_time_days ?? 0),
                supplier_sku: row.supplier_sku ?? '',
                notes: row.notes ?? '',
                last_purchased_at: row.last_purchased_at ?? '',
            };
        },

        /** Partners not yet in the table. */
        get available() {
            const used = this.rows.map((r) => String(r.partner_id));

            return this.options.filter((o) => !used.includes(String(o.id)));
        },

        get isEmpty() {
            return this.rows.length === 0;
        },

        addRow() {
            if (!this.picker) return;

            this.rows.push(this.normalize({ partner_id: this.picker }));

            // First supplier added becomes the preferred one by default.
            if (this.withSupplierFields && !this.preferredId) {
                this.preferredId = String(this.picker);
            }

            this.picker = '';
        },

        removeRow(index) {
            const removed = this.rows.splice(index, 1)[0];

            if (removed && String(removed.partner_id) === this.preferredId) {
                this.preferredId = this.rows.length > 0 ? String(this.rows[0].partner_id) : '';
            }
        },

        labelOf(partnerId) {
            const found = this.options.find((o) => String(o.id) === String(partnerId));

            return found ? found.label : '—';
        },

        /** Cheapest row gets a badge so the best offer is obvious. */
        isCheapest(row) {
            const priced = this.rows.filter((r) => parseNum(r.price) > 0);
            if (priced.length < 2) return false;

            const min = Math.min(...priced.map((r) => parseNum(r.price)));

            return parseNum(row.price) > 0 && parseNum(row.price) === min;
        },
    };
}
