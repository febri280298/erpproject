import { fmtMoney, fmtNumber, parseNum, round } from './helpers';

/**
 * Alpine component driving every line-item document (Quotation, SO, PO, PR,
 * Invoices, BOM, Adjustment...). Keeps one calculation path so totals shown in
 * the browser always match what the server recomputes on save.
 *
 * Usage:
 *   <div x-data="docItems({ products: [...], rows: [...], priceField: 'purchase_price' })">
 */
export default function docItems(config = {}) {
    return {
        products: config.products ?? [],
        taxes: config.taxes ?? [],
        priceField: config.priceField ?? 'sale_price',
        /** Some documents (PR, BOM, Adjustment) have no money columns. */
        withPrice: config.withPrice ?? true,
        rows: [],
        discountAmount: parseNum(config.discountAmount ?? 0),
        shippingCost: parseNum(config.shippingCost ?? 0),

        init() {
            const incoming = Array.isArray(config.rows) ? config.rows : [];
            this.rows = incoming.map((r) => this.normalizeRow(r));
            if (this.rows.length === 0) this.addRow();
        },

        normalizeRow(r = {}) {
            return {
                product_id: r.product_id ?? '',
                description: r.description ?? '',
                quantity: parseNum(r.quantity ?? 1),
                unit_price: parseNum(r.unit_price ?? 0),
                discount_percent: parseNum(r.discount_percent ?? 0),
                tax_rate: parseNum(r.tax_rate ?? 0),
                /** Reference columns used by receipt/delivery screens. */
                ordered_qty: r.ordered_qty !== undefined ? parseNum(r.ordered_qty) : null,
                outstanding_qty: r.outstanding_qty !== undefined ? parseNum(r.outstanding_qty) : null,
                source_item_id: r.source_item_id ?? null,
                uom: r.uom ?? '',
            };
        },

        addRow() {
            this.rows.push(this.normalizeRow({}));
        },

        removeRow(index) {
            this.rows.splice(index, 1);
            if (this.rows.length === 0) this.addRow();
        },

        product(id) {
            return this.products.find((p) => String(p.id) === String(id));
        },

        /** Pull default price / tax / uom when a product is picked. */
        onProductChange(index) {
            const row = this.rows[index];
            const p = this.product(row.product_id);
            if (!p) return;

            row.description = p.name ?? '';
            row.uom = p.uom ?? '';
            if (this.withPrice) {
                row.unit_price = parseNum(p[this.priceField] ?? 0);
                row.tax_rate = parseNum(p.tax_rate ?? 0);
            }
        },

        lineGross(row) {
            return round(parseNum(row.quantity) * parseNum(row.unit_price));
        },

        lineDiscount(row) {
            return round((this.lineGross(row) * parseNum(row.discount_percent)) / 100);
        },

        lineSubtotal(row) {
            return round(this.lineGross(row) - this.lineDiscount(row));
        },

        lineTax(row) {
            return round((this.lineSubtotal(row) * parseNum(row.tax_rate)) / 100);
        },

        lineTotal(row) {
            return round(this.lineSubtotal(row) + this.lineTax(row));
        },

        get subtotal() {
            return round(this.rows.reduce((sum, r) => sum + this.lineSubtotal(r), 0));
        },

        get taxTotal() {
            return round(this.rows.reduce((sum, r) => sum + this.lineTax(r), 0));
        },

        get grandTotal() {
            return round(this.subtotal - parseNum(this.discountAmount) + parseNum(this.shippingCost) + this.taxTotal);
        },

        get totalQuantity() {
            return round(this.rows.reduce((sum, r) => sum + parseNum(r.quantity), 0), 4);
        },

        /** Guard: a product must be chosen on every row before submitting. */
        validate(event) {
            const empty = this.rows.some((r) => !r.product_id);
            if (empty || this.rows.length === 0) {
                event.preventDefault();
                window.alert('Setiap baris harus memiliki produk. Hapus baris kosong terlebih dahulu.');
            }
        },

        money: (v) => fmtMoney(v),
        num: (v) => fmtNumber(v),
    };
}
