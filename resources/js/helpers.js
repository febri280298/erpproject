const nf = new Intl.NumberFormat('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
const mf = new Intl.NumberFormat('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

/** Parse a user-typed number that may use Indonesian thousand/decimal separators. */
export function parseNum(value) {
    if (typeof value === 'number') return isFinite(value) ? value : 0;
    if (value === null || value === undefined || value === '') return 0;

    let s = String(value).trim().replace(/\s/g, '');
    const lastComma = s.lastIndexOf(',');
    const lastDot = s.lastIndexOf('.');

    if (lastComma > -1 && lastComma > lastDot) {
        // "1.234.567,89" — dots are thousand separators
        s = s.replace(/\./g, '').replace(',', '.');
    } else {
        // "1,234,567.89" or plain "1234.89"
        s = s.replace(/,/g, '');
    }

    const n = parseFloat(s);
    return isFinite(n) ? n : 0;
}

export function fmtNumber(value) {
    return nf.format(parseNum(value));
}

export function fmtMoney(value) {
    return mf.format(parseNum(value));
}

/** Round to `digits` decimals without float drift on .005 cases. */
export function round(value, digits = 2) {
    const f = Math.pow(10, digits);
    return Math.round((parseNum(value) + Number.EPSILON) * f) / f;
}
