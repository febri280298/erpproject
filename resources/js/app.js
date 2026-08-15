// The ESM build both registers Bootstrap's data-api (dropdowns, modals,
// collapse) and lets us reach Tooltip, which Tabler does not auto-initialise.
import { Tooltip } from '@tabler/core/dist/js/tabler.esm.min.js';
import Alpine from 'alpinejs';
import ApexCharts from 'apexcharts';

import docItems from './doc-items';
import partnerPriceRows from './partner-price-rows';
import { fmtNumber, fmtMoney, parseNum } from './helpers';

window.Alpine = Alpine;
window.ApexCharts = ApexCharts;
window.erp = { fmtNumber, fmtMoney, parseNum };

Alpine.data('docItems', docItems);
Alpine.data('partnerPriceRows', partnerPriceRows);

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
