/**
 * Detail alat: jumlah unit dibatasi stok di tanggal trip, perkiraan biaya
 * (jumlah × tarif × hari), dan tautan "Sewa alat ini" membawa jumlah tersebut.
 */
(() => {
    'use strict';

    const CG = window.CampGear;
    const root = document.querySelector('[data-alat]');
    if (!CG || !root) return;

    const id = root.dataset.id;
    const rate = Number(root.dataset.rate);
    const stock = Number(root.dataset.stock);
    const qtyEl = root.querySelector('[data-alat-qty]');
    const dec = root.querySelector('[data-alat-step="-1"]');
    const inc = root.querySelector('[data-alat-step="1"]');
    const add = root.querySelector('[data-ransel-add]');
    const sewa = root.querySelector('[data-alat-sewa]');
    const estimate = root.querySelector('[data-alat-estimate]');
    const rupiah = (n) => 'Rp' + Math.round(n).toLocaleString('id-ID');
    let qty = 1;
    let max = stock;

    function render() {
        qty = Math.min(Math.max(1, qty), Math.max(1, max));
        qtyEl.textContent = String(qty);
        dec.disabled = qty <= 1;
        inc.disabled = qty >= max;
        add.disabled = max <= 0;
        add.dataset.qty = String(qty);
        // /sewa?items[id]=jumlah, ditambah tanggal trip bila sudah dipilih.
        const params = new URLSearchParams(CG.trip.params());
        params.set(`items[${id}]`, String(qty));
        sewa.href = CG.url('/sewa') + '?' + params.toString();
        if (!CG.trip.hasDates()) {
            estimate.textContent = 'Pilih tanggal untuk melihat perkiraan biaya.';
        } else if (max <= 0) {
            estimate.textContent = `Habis untuk ${CG.trip.range()}. Coba tanggal lain.`;
        } else {
            const days = CG.trip.days();
            estimate.innerHTML = `Perkiraan: ${qty} unit × ${rupiah(rate)} × ${days} hari = <b>${rupiah(qty * rate * days)}</b>`;
        }
    }

    async function refresh() {
        max = stock;
        if (CG.trip.hasDates()) {
            try {
                const data = await CG.trip.availability();
                max = Number(data.equipment[id] ?? 0);
            } catch { /* stok dicek lagi di form booking */ }
        }
        render();
    }

    [dec, inc].forEach((button) => button.addEventListener('click', () => {
        qty += Number(button.dataset.alatStep);
        render();
    }));
    document.addEventListener('campgear:trip', refresh);
    refresh();
})();
