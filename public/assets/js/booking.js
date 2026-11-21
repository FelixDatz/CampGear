/**
 * Form booking (mode fokus): cek ketersediaan stok lewat API (fetch + async/await),
 * menghitung ringkasan biaya di browser, dan membagi form menjadi empat langkah
 * (tanggal → isi ransel → data penyewa → periksa).
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
    const wizTotal = form.querySelector('[data-wiz-total]');
    const wizDays = form.querySelector('[data-wiz-days]');

    /** Hasil API terakhir: { days, equipment: {id: n}, packages: {id: n} } atau null. */
    let availability = null;
    let dateError = '';
    let requestCounter = 0;
    /** Hasil render() terakhir, dipakai pemeriksaan tiap langkah wizard. */
    let last = { lines: [], shortages: [], days: 0 };

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

        // Total di bar aksi (di HP menempel di bawah layar).
        last = { lines, shortages, days };
        if (wizTotal) wizTotal.textContent = rupiah(subtotal - discount);
        if (wizDays) wizDays.textContent = days ? `Total ${days} hari` : 'Total';
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

    // ---- Wizard: satu langkah sekali waktu ------------------------------------

    const panels = Array.from(form.querySelectorAll('[data-wiz-panel]'));
    const navButtons = Array.from(form.querySelectorAll('[data-wiz-nav]'));
    const backButton = form.querySelector('[data-wiz-back]');
    const nextButton = form.querySelector('[data-wiz-next]');
    const hint = form.querySelector('[data-wiz-hint]');
    const stepLabel = document.querySelector('[data-wiz-label]');
    const progress = document.querySelector('[data-wiz-progress]');
    const titles = navButtons.map((button) => button.querySelector('.wiz-step-label')?.textContent.trim() || '');
    const lastStep = panels.length;
    let current = 1;
    let reached = 1;

    /** Hal yang menahan sebuah langkah: { text, focus, report } atau null bila boleh lanjut. */
    function problem(step) {
        if (step === 1) {
            if (!startInput.value || !endInput.value) return { text: 'Pilih tanggal mulai dan selesai.', focus: startInput.value ? endInput : startInput };
            if (dateError) return { text: dateError, focus: startInput };
            if (!availability) return { text: 'Stok sedang dicek, tunggu sebentar.' };
        }
        if (step === 2) {
            if (last.lines.length === 0) return { text: 'Pilih minimal satu alat atau paket.', focus: form.querySelector('.qty-stepper input') };
            if (last.shortages.length > 0) return { text: 'Stok tidak cukup: ' + last.shortages.join('; ') + '.', focus: form.querySelector('.pick-row.is-short input') };
        }
        if (step === 3) {
            const invalid = Array.from(panels[2].querySelectorAll('input, textarea')).find((el) => !el.checkValidity());
            if (invalid) return { text: 'Lengkapi data penyewa.', focus: invalid, report: true };
        }
        return null;
    }

    function fillReview() {
        const set = (key, text) => { form.querySelector(`[data-review="${key}"]`).textContent = text; };
        const value = (id) => form.querySelector('#' + id).value.trim();
        set('dates', summary.dates.textContent);
        set('items', last.lines.map((line) => line.name).join(', ') || '–');
        set('contact', [value('customer_name'), value('customer_email'), value('customer_phone')].filter(Boolean).join(' · ') || '–');
        set('notes', value('notes') || '–');
    }

    function show(step, moveFocus = true) {
        current = step;
        reached = Math.max(reached, step);
        form.dataset.at = String(step);
        panels.forEach((panel) => { panel.hidden = Number(panel.dataset.wizPanel) !== step; });
        navButtons.forEach((button) => {
            const n = Number(button.dataset.wizGo);
            button.disabled = n > reached;
            button.classList.toggle('is-done', n < step);
            if (n === step) button.setAttribute('aria-current', 'step'); else button.removeAttribute('aria-current');
        });
        backButton.hidden = step === 1;
        nextButton.hidden = step === lastStep;
        submitButton.hidden = step !== lastStep;
        hint.textContent = '';
        if (stepLabel) stepLabel.textContent = `${step}/${lastStep} · ${titles[step - 1]}`;
        if (progress) progress.style.width = `${(step / lastStep) * 100}%`;
        if (step === lastStep) fillReview();
        if (moveFocus) {
            window.scrollTo({ top: 0, behavior: CampGear.prefersMotion() ? 'smooth' : 'auto' });
            panels[step - 1].querySelector('.wiz-title')?.focus({ preventScroll: true });
        }
    }

    /** Pindah ke langkah tujuan; semua langkah sebelumnya harus lolos pemeriksaan. */
    function goTo(target) {
        for (let step = 1; step < target; step++) {
            const issue = problem(step);
            if (issue) {
                if (step !== current) show(step);
                hint.textContent = issue.text;
                if (issue.report) issue.focus.reportValidity();
                else issue.focus?.focus();
                return;
            }
        }
        show(target);
    }

    if (panels.length > 0 && nextButton) {
        form.classList.add('is-wizard');
        form.querySelector('[data-wiz-steps]').hidden = false;
        nextButton.addEventListener('click', () => goTo(current + 1));
        backButton.addEventListener('click', () => show(current - 1));
        form.addEventListener('click', (event) => {
            const go = event.target.closest('[data-wiz-go]');
            if (go && !go.disabled) goTo(Number(go.dataset.wizGo));
        });
        // Enter di langkah 1–3 berarti "Lanjut", bukan kirim. Didaftarkan di fase capture
        // pada document agar berjalan sebelum trip.js mengosongkan ransel saat form dikirim.
        document.addEventListener('submit', (event) => {
            if (event.target !== form || current === lastStep) return;
            event.preventDefault();
            goTo(current + 1);
        }, true);
        // Setelah gagal validasi di server, mulai dari langkah yang berisi pesan error.
        const serverError = form.querySelector('.is-invalid, .alert-danger');
        reached = serverError ? lastStep : 1;
        show(serverError ? Number(serverError.closest('[data-wiz-panel]')?.dataset.wizPanel || 1) : 1, false);
    }

    // Tombol submit tetap aktif tanpa JavaScript; dengan JavaScript, statusnya diatur render().
    if (startInput.value) endInput.min = startInput.value;
    refreshAvailability();
})();
