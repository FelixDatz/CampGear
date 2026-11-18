/**
 * Form booking: cek ketersediaan stok lewat API (fetch + async/await)
 * dan menghitung ringkasan biaya di browser.
 *
 * Perhitungan di sini hanya untuk tampilan. Server menghitung ulang
 * dan memvalidasi stok sebelum booking disimpan.
 */
(() => {
    'use strict';

    const form = document.getElementById('booking-form');
    const dataEl = document.getElementById('booking-data');
    if (!form || !dataEl) return;

    const data = JSON.parse(dataEl.textContent);
    const equipment = new Map(data.equipment.map((e) => [e.id, e]));
    const packages = new Map(data.packages.map((p) => [p.id, p]));

    const startInput = form.querySelector('#start_date');
    const endInput = form.querySelector('#end_date');
    const dateFeedback = document.getElementById('date-feedback');
    const submitButton = document.getElementById('booking-submit');
    const summary = {
        dates: document.getElementById('summary-dates'),
        lines: document.getElementById('summary-lines'),
        subtotal: document.getElementById('summary-subtotal'),
        discount: document.getElementById('summary-discount'),
        total: document.getElementById('summary-total'),
        warning: document.getElementById('summary-warning'),
    };

    /** Hasil API terakhir: { days, equipment: {id: n}, packages: {id: n} } atau null. */
    let availability = null;
    let dateError = '';
    let requestCounter = 0;

    const rupiah = (n) => 'Rp' + Math.round(n).toLocaleString('id-ID');
    const quantityOf = (input) => {
        const value = parseInt(input.value, 10);
        return Number.isFinite(value) && value > 0 ? value : 0;
    };
    const formatDate = (iso) => new Date(iso + 'T00:00:00').toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });

    // ---- Ketersediaan stok (AJAX) -------------------------------------------

    async function refreshAvailability() {
        const start = startInput.value;
        const end = endInput.value;
        availability = null;
        dateError = '';

        if (!start || !end) {
            setBadges('Pilih tanggal dulu');
            render();
            return;
        }
        if (end < start) {
            dateError = 'Tanggal selesai tidak boleh sebelum tanggal mulai.';
            setBadges('—');
            render();
            return;
        }

        setBadges('Mengecek…');
        const requestId = ++requestCounter;
        try {
            const params = new URLSearchParams({ start, end });
            const result = await CampGear.api('/api/availability?' + params.toString());
            if (requestId !== requestCounter) return; // ada request lebih baru, abaikan yang lama
            availability = result;
        } catch (error) {
            if (requestId !== requestCounter) return;
            dateError = error.status === 422 ? error.message : 'Gagal mengecek stok. Periksa koneksi lalu coba lagi.';
            setBadges('—');
        }
        render();
    }

    function setBadges(text) {
        form.querySelectorAll('.avail-badge').forEach((badge) => {
            badge.textContent = text;
            badge.className = 'avail-badge';
        });
    }

    // ---- Perhitungan & tampilan ---------------------------------------------

    function collect() {
        const days = availability ? availability.days : 0;
        const demand = new Map(); // equipment_id -> total unit (satuan + dari paket)
        const lines = [];
        let subtotal = 0;
        let discount = 0;

        form.querySelectorAll('[data-package-id]').forEach((input) => {
            const qty = quantityOf(input);
            if (qty === 0) return;
            const pkg = packages.get(Number(input.dataset.packageId));
            let lineTotal = 0;
            let lineDiscount = 0;
            pkg.items.forEach((item) => {
                const units = qty * item.quantity;
                demand.set(item.equipment_id, (demand.get(item.equipment_id) || 0) + units);
                const total = units * equipment.get(item.equipment_id).rate * days;
                lineTotal += total;
                lineDiscount += Math.round(total * pkg.discount / 100); // sama dengan pembulatan di server
            });
            subtotal += lineTotal;
            discount += lineDiscount;
            lines.push({ name: `${pkg.name} ×${qty}`, amount: lineTotal });
        });

        form.querySelectorAll('[data-equipment-id]').forEach((input) => {
            const qty = quantityOf(input);
            if (qty === 0) return;
            const item = equipment.get(Number(input.dataset.equipmentId));
            demand.set(item.id, (demand.get(item.id) || 0) + qty);
            const total = qty * item.rate * days;
            subtotal += total;
            lines.push({ name: `${item.name} ×${qty}`, amount: total });
        });

        return { days, demand, lines, subtotal, discount };
    }

    function render() {
        const { days, demand, lines, subtotal, discount } = collect();

        form.querySelectorAll('.qty-stepper input').forEach((input) => {
            input.closest('.pick-row').classList.toggle('is-picked', quantityOf(input) > 0);
        });

        // Badge ketersediaan dan penanda baris yang kekurangan stok.
        const shortages = [];
        if (availability) {
            form.querySelectorAll('[data-avail-equipment]').forEach((badge) => {
                const id = Number(badge.dataset.availEquipment);
                const available = availability.equipment[id] ?? 0;
                const needed = demand.get(id) || 0;
                badge.textContent = available > 0 ? `Tersedia ${available} unit` : 'Habis di tanggal ini';
                badge.className = 'avail-badge ' + (available > 0 ? 'is-ok' : 'is-empty');
                const short = needed > available;
                badge.closest('.pick-row').classList.toggle('is-short', short);
                if (short) shortages.push(`${equipment.get(id).name}: butuh ${needed}, tersedia ${available}`);
            });
            form.querySelectorAll('[data-avail-package]').forEach((badge) => {
                const sets = availability.packages[Number(badge.dataset.availPackage)] ?? 0;
                badge.textContent = sets > 0 ? `Tersedia ${sets} set` : 'Tidak tersedia di tanggal ini';
                badge.className = 'avail-badge ' + (sets > 0 ? 'is-ok' : 'is-empty');
            });
        } else {
            form.querySelectorAll('.pick-row.is-short').forEach((row) => row.classList.remove('is-short'));
        }

        // Ringkasan.
        dateFeedback.textContent = dateError;
        summary.dates.textContent = availability
            ? `${formatDate(startInput.value)} – ${formatDate(endInput.value)} · ${days} hari`
            : 'Tanggal belum dipilih';

        summary.lines.replaceChildren();
        if (lines.length === 0) {
            const li = document.createElement('li');
            li.className = 'text-muted';
            li.textContent = 'Belum ada alat dipilih';
            summary.lines.append(li);
        }
        lines.forEach((line) => {
            const li = document.createElement('li');
            const name = document.createElement('span');
            const amount = document.createElement('span');
            name.textContent = line.name;
            amount.textContent = availability ? rupiah(line.amount) : '—';
            li.append(name, amount);
            summary.lines.append(li);
        });
        summary.subtotal.textContent = rupiah(subtotal);
        summary.discount.textContent = '−' + rupiah(discount);
        summary.total.textContent = rupiah(subtotal - discount);

        // Tombol hanya aktif bila tanggal valid, ada alat, dan stok cukup.
        let warning = '';
        if (dateError) warning = dateError;
        else if (!availability) warning = 'Pilih tanggal mulai dan selesai untuk mengecek stok.';
        else if (lines.length === 0) warning = 'Pilih minimal satu alat atau paket.';
        else if (shortages.length > 0) warning = 'Stok tidak cukup — ' + shortages.join('; ') + '.';

        summary.warning.textContent = warning;
        summary.warning.classList.toggle('d-none', warning === '');
        submitButton.disabled = warning !== '';
    }

    // ---- Event --------------------------------------------------------------

    form.addEventListener('click', (event) => {
        const button = event.target.closest('[data-step]');
        if (!button) return;
        const input = button.closest('.qty-stepper').querySelector('input');
        input.value = Math.min(999, Math.max(0, quantityOf(input) + Number(button.dataset.step)));
        input.dispatchEvent(new Event('input', { bubbles: true }));
    });

    form.addEventListener('input', (event) => {
        if (event.target.matches('[data-equipment-id], [data-package-id]')) render();
    });

    startInput.addEventListener('change', () => {
        endInput.min = startInput.value || startInput.min;
        if (startInput.value && (!endInput.value || endInput.value < startInput.value)) {
            endInput.value = startInput.value;
        }
        refreshAvailability();
    });
    endInput.addEventListener('change', refreshAvailability);

    // Tombol submit tetap aktif tanpa JavaScript; dengan JavaScript, statusnya diatur render().
    if (startInput.value) endInput.min = startInput.value;
    refreshAvailability();
})();
