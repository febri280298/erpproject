/**
 * Mengukur kontras teks terhadap latar yang benar-benar terlihat di belakangnya.
 *
 * Warnanya TIDAK diurai dari teks CSS. Tabler memakai color-mix(), yang oleh
 * peramban dilaporkan sebagai `color(srgb 0.97 0.98 0.98)` — berskala 0–1, bukan
 * 0–255. Mengurainya dengan regex angka menghasilkan luminansi nyaris nol untuk
 * semua warna, sehingga setiap elemen tampak berkontras 1:1 dan pemeriksaannya
 * ramai memberi kegagalan palsu. Kanvas dipakai supaya peramban sendiri yang
 * menormalkan warnanya, apa pun bentuk penulisannya.
 */

/** Sumber pengukur yang dijalankan DI DALAM halaman. */
export const SUMBER_PENGUKUR = `(() => {
    const ctx = document.createElement('canvas').getContext('2d', { willReadFrequently: true });

    const rgba = (warna) => {
        ctx.clearRect(0, 0, 1, 1);
        ctx.fillStyle = warna;
        ctx.fillRect(0, 0, 1, 1);

        const [r, g, b, a] = ctx.getImageData(0, 0, 1, 1).data;

        return [r, g, b, a / 255];
    };

    /** Latar efektif: elemen terdekat yang latarnya tidak tembus pandang. */
    const latar = (el) => {
        for (let n = el; n; n = n.parentElement) {
            const w = rgba(getComputedStyle(n).backgroundColor);

            if (w[3] > 0.95) {
                return w;
            }
        }

        return [255, 255, 255, 1];
    };

    const kanal = (c) => {
        const v = c / 255;

        return v <= 0.03928 ? v / 12.92 : ((v + 0.055) / 1.055) ** 2.4;
    };
    const luminansi = ([r, g, b]) => 0.2126 * kanal(r) + 0.7152 * kanal(g) + 0.0722 * kanal(b);

    return (el) => {
        const bg = latar(el);
        const fg = rgba(getComputedStyle(el).color);

        // Teks separuh tembus dipadu dulu dengan latarnya, kalau tidak
        // kontrasnya terhitung lebih baik daripada yang sebenarnya terlihat.
        const padu = fg.slice(0, 3).map((c, i) => c * fg[3] + bg[i] * (1 - fg[3]));

        const [a, b] = [luminansi(padu), luminansi(bg)].sort((x, y) => y - x);

        return {
            rasio: Math.round(((a + 0.05) / (b + 0.05)) * 10) / 10,
            teks: \`rgb(\${padu.map(Math.round).join(', ')})\`,
            latar: \`rgb(\${bg.slice(0, 3).join(', ')})\`,
        };
    };
})()`;

/** Kontras satu elemen pertama yang cocok dengan pemilihnya. */
export const kontrasDari = (page, ambilElemen) => page.evaluate(
    ([sumber, kode]) => {
        const ukur = eval(sumber);
        const el = eval(kode)();

        return el ? ukur(el) : null;
    },
    [SUMBER_PENGUKUR, `() => { ${ambilElemen} }`],
);

/** Ambang WCAG AA untuk teks berukuran biasa. */
export const AMBANG_AA = 4.5;
