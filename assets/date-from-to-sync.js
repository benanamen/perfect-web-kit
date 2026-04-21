/**
 * Keep a From/To date-range pair coherent on the client:
 *  - whenever #from changes, set #to[min]=#from and clamp #to up if it was earlier;
 *  - when #from is cleared, drop #to[min].
 *
 * The server must independently clamp `to >= from` on any submitted or
 * URL-provided range; this script is UX-only.
 */
(function () {
    'use strict';

    const fromEl = document.getElementById('from');
    const toEl = document.getElementById('to');
    if (!fromEl || !toEl) {
        return;
    }

    function syncFromToDates() {
        const f = (fromEl.value || '').trim();
        if (f !== '') {
            toEl.min = f;
            if (!toEl.value || toEl.value < f) {
                toEl.value = f;
            }
        } else {
            toEl.removeAttribute('min');
        }
    }

    fromEl.addEventListener('change', syncFromToDates);
    fromEl.addEventListener('input', syncFromToDates);
    syncFromToDates();
}());
