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

        /** Multi-price lookup: customer tier map and the fallback tier. */
        partnerLevels: config.partnerLevels ?? {},
        defaultLevelId: config.defaultLevelId ?? null,
        partnerId: '',
        priceSourceLabel: '',
        invoiceType: config.invoiceType ?? 'ppn',

        /** Rasio DPP Nilai Lain; 1 berarti fitur tidak aktif. */
        dppRatio: config.dppRatio ?? 1,
        dppRatioLabel: config.dppRatioLabel ?? '',

        get usesDppOther() {
            return this.dppRatio !== 1;
        },

        get isPurchase() {
            return this.priceField === 'purchase_price';
        },

        init() {
            const incoming = Array.isArray(config.rows) ? config.rows : [];
            this.rows = incoming.map((r) => this.normalizeRow(r));
            if (this.rows.length === 0) this.addRow();

            // Watch the partner picker in the document header so prices follow
            // whichever supplier / customer is chosen, without wiring each view.
            this.partnerSelect = this.$root.querySelector('[name="partner_id"]');

            if (this.partnerSelect) {
                this.partnerId = this.partnerSelect.value;
                this.describeSource();

                this.partnerSelect.addEventListener('change', () => {
                    this.partnerId = this.partnerSelect.value;
                    this.describeSource();
                    this.repriceAll();
                });
            }

            // Tipe faktur mengatur perlakuan PPN seluruh baris sekaligus.
            this.typeSelect = this.$root.querySelector('[name="invoice_type"]');

            if (this.typeSelect) {
                this.invoiceType = this.typeSelect.value;

                this.typeSelect.addEventListener('change', () => {
                    this.invoiceType = this.typeSelect.value;
                    this.applyInvoiceType();
                });
            }
        },

        /**
         * Non-PPN menolkan pajak seluruh baris. PPN dan Jasa mengembalikan tarif
         * milik produknya masing-masing — produk yang memang bebas PPN tetap 0%
         * walau fakturnya bertipe PPN.
         */
        applyInvoiceType() {
            this.rows.forEach((row) => {
                if (!row.product_id) return;

                if (this.invoiceType === 'non_ppn') {
                    row.tax_rate = 0;
                    return;
                }

                const product = this.product(row.product_id);
                row.tax_rate = parseNum(product?.tax_rate ?? 0);
            });
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
                row.unit_price = this.priceFor(p);
                // Faktur non-PPN menolkan pajak apa pun tarif produknya.
                row.tax_rate = this.invoiceType === 'non_ppn' ? 0 : parseNum(p.tax_rate ?? 0);
            }
        },

        /**
         * Resolves a product's price for the partner currently selected:
         * supplier price for purchases, tier price for sales, and the product's
         * base price when neither is set.
         */
        priceFor(product) {
            if (!product) return 0;

            if (this.isPurchase) {
                const supplierPrice = parseNum(product.supplier_prices?.[String(this.partnerId)] ?? 0);
                return supplierPrice > 0 ? supplierPrice : parseNum(product.purchase_price ?? 0);
            }

            // A negotiated price for this customer beats their tier.
            const special = parseNum(product.customer_prices?.[String(this.partnerId)] ?? 0);
            if (special > 0) return special;

            const levelId = this.partnerLevels?.[String(this.partnerId)] ?? this.defaultLevelId;
            const tierPrice = parseNum(product.prices?.[String(levelId)] ?? 0);

            return tierPrice > 0 ? tierPrice : parseNum(product.sale_price ?? 0);
        },

        /** Re-reads every row's price — used after the partner changes. */
        repriceAll() {
            if (!this.withPrice) return;

            this.rows.forEach((row) => {
                if (!row.product_id) return;
                const p = this.product(row.product_id);
                if (p) row.unit_price = this.priceFor(p);
            });
        },

        describeSource() {
            if (!this.partnerId) {
                this.priceSourceLabel = '';
                return;
            }

            this.priceSourceLabel = this.isPurchase
                ? 'Harga mengikuti daftar harga supplier'
                : 'Harga mengikuti tingkat harga customer';
        },

        lineGross(row) {
            return round(parseNum(row.quantity) * parseNum(row.unit_price));
        },

        lineDiscount(row) {
            return round((this.lineGross(row) * parseNum(row.discount_percent)) / 100);
        },

        /** DPP baris: harga setelah diskon, belum kena pajak. */
        lineSubtotal(row) {
            return round(this.lineGross(row) - this.lineDiscount(row));
        },

        /**
         * Dasar pengenaan pajak baris. Bila DPP Nilai Lain aktif, dasarnya
         * adalah rasio (mis. 11/12) dari DPP — baris bebas pajak tidak terpengaruh.
         */
        lineTaxBase(row) {
            return parseNum(row.tax_rate) > 0
                ? round(this.lineSubtotal(row) * this.dppRatio)
                : this.lineSubtotal(row);
        },

        lineTax(row) {
            return round((this.lineTaxBase(row) * parseNum(row.tax_rate)) / 100);
        },

        lineTotal(row) {
            return round(this.lineSubtotal(row) + this.lineTax(row));
        },

        get subtotal() {
            return round(this.rows.reduce((sum, r) => sum + this.lineSubtotal(r), 0));
        },

        get dppOtherTotal() {
            return round(this.rows.reduce(
                (sum, r) => sum + (parseNum(r.tax_rate) > 0 ? this.lineTaxBase(r) : 0), 0,
            ));
        },

        get taxTotal() {
            return round(this.rows.reduce((sum, r) => sum + this.lineTax(r), 0));
        },

        /** Tarif PPh 23 dibaca dari input di header; kosong bila bukan faktur jasa. */
        get whtRate() {
            if (this.invoiceType !== 'jasa') return 0;

            const input = this.$root.querySelector('[name="wht_rate"]');

            return input ? parseNum(input.value) : 0;
        },

        /** Dasar PPh 23 adalah nilai jasa di luar PPN. */
        get whtAmount() {
            return round((this.subtotal * this.whtRate) / 100);
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
