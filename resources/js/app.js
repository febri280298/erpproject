// The ESM build both registers Bootstrap's data-api (dropdowns, modals,
// collapse) and lets us reach Tooltip, which Tabler does not auto-initialise.
import { Dropdown, Tooltip } from '@tabler/core/dist/js/tabler.esm.min.js';
import Alpine from 'alpinejs';
import ApexCharts from 'apexcharts';

import deliveryItems from './delivery-items';
import docItems from './doc-items';
import partnerPriceRows from './partner-price-rows';
import { fmtNumber, fmtMoney, parseNum } from './helpers';

window.Alpine = Alpine;
window.ApexCharts = ApexCharts;
window.erp = { fmtNumber, fmtMoney, parseNum };

Alpine.data('docItems', docItems);
Alpine.data('partnerPriceRows', partnerPriceRows);
Alpine.data('deliveryItems', deliveryItems);

Alpine.start();

// Auto-dismiss flash alerts after 6s
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach((el) => new Tooltip(el));

    document.querySelectorAll('[data-auto-dismiss]').forEach((el) => {
        setTimeout(() => {
            el.classList.remove('show');
            setTimeout(() => el.remove(), 300);
        }, 6000);
    });

    // Confirm-before-submit for destructive actions
    document.body.addEventListener('submit', (e) => {
        const form = e.target;
        const msg = form.dataset.confirm;
        if (msg && !window.confirm(msg)) {
            e.preventDefault();
        }
    });

    // Submit the closest form when a filter control changes
    document.querySelectorAll('[data-filter-submit]').forEach((el) => {
        el.addEventListener('change', () => el.closest('form')?.submit());
    });
});


/**
 * Dropdown di dalam tabel tidak boleh terpotong pembungkusnya.
 *
 * Tabel dibungkus .table-responsive yang ber-overflow:auto agar tabel lebar
 * bisa digulir mendatar. Efek sampingnya, menu "Aksi" yang diposisikan absolut
 * ikut terpotong di tepi bawah pembungkus itu — pada baris terakhir hanya
 * tersisa beberapa piksel. Overflow tidak bisa dibuat visible pada satu sumbu
 * saja (CSS memaksa sumbu lain ikut jadi auto), jadi yang dipindahkan adalah
 * menunya: Popper memakai strategy 'fixed' sehingga menu diposisikan terhadap
 * viewport dan lolos dari kliping, dan boundary-nya diarahkan ke elemen akar
 * supaya preventOverflow tidak memeras menu kembali ke dalam kotak tabel.
 *
 * Instans lama harus dibuang lebih dulu. Bundel Tabler membuat instans Dropdown
 * untuk setiap toggle begitu modulnya dimuat, lengkap dengan
 * boundary 'clippingParents' — justru pengaturan yang mengunci menu ke dalam
 * pembungkus. Selama instans itu masih ada, getOrCreateInstance mengembalikannya
 * dan konfigurasi di sini tidak pernah terpakai.
 */
const dropdownTerpotong = (el) => {
    for (let n = el.parentElement; n && n !== document.body; n = n.parentElement) {
        const gaya = getComputedStyle(n);

        if (gaya.overflowX !== 'visible' || gaya.overflowY !== 'visible') {
            return true;
        }
    }

    return false;
};

const lepaskanDropdown = (toggle) => {
    if (toggle.dataset.erpDropdownLepas === '1' || !dropdownTerpotong(toggle)) {
        return;
    }

    Dropdown.getInstance(toggle)?.dispose();

    new Dropdown(toggle, {
        boundary: document.documentElement,
        popperConfig: (bawaan) => ({ ...bawaan, strategy: 'fixed' }),
    });

    toggle.dataset.erpDropdownLepas = '1';
};

const lepaskanSemuaDropdown = () => {
    document.querySelectorAll('[data-bs-toggle="dropdown"]').forEach(lepaskanDropdown);
};

document.addEventListener('DOMContentLoaded', lepaskanSemuaDropdown);

// Baris yang ditambahkan belakangan (mis. lewat Alpine) belum tersentuh sapuan
// di atas, jadi ditangani saat disentuh. Fase capture dipakai supaya berjalan
// sebelum penangan bawaan Bootstrap memakai instans yang lama.
['pointerdown', 'keydown'].forEach((peristiwa) => {
    document.addEventListener(peristiwa, (e) => {
        const toggle = e.target?.closest?.('[data-bs-toggle="dropdown"]');

        if (toggle) {
            lepaskanDropdown(toggle);
        }
    }, true);
});

// Footer melayang di atas isi halaman, jadi tingginya harus dicadangkan sebagai
// ruang kosong di bawah konten. Tingginya diukur langsung — bukan ditebak —
// karena ikut berubah saat teksnya membungkus di layar sempit.
document.addEventListener('DOMContentLoaded', () => {
    const footer = document.querySelector('.footer-fixed');
    if (!footer) {
        return;
    }

    const ukur = () => {
        const tinggi = Math.ceil(footer.getBoundingClientRect().height);
        document.documentElement.style.setProperty('--erp-footer-h', `${tinggi}px`);
    };

    ukur();
    new ResizeObserver(ukur).observe(footer);
});
