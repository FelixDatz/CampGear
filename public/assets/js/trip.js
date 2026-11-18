/**
 * CampGear — rencana perjalanan dan ransel (vanilla JavaScript).
 *
 *   Trip     tanggal ambil/kembali + jumlah orang. Disimpan di localStorage
 *            supaya navbar, katalog, beranda, dan form /sewa memakai tanggal yang sama.
 *   Kalender popover pemilih rentang tanggal dengan aturan sewa yang sama seperti
 *            server: mulai paling cepat hari ini dan paling lambat H+90, durasi
 *            maksimal 14 hari termasuk tanggal ambil dan kembali.
 *   Stok     GET /api/availability?start=…&end=… (unit per alat, set per paket).
 *   Ransel   keranjang sementara di localStorage. "Lanjut ke booking" membuka /sewa
 *            dengan tanggal, alat, dan paket terisi. Server tetap memvalidasi ulang.
 */
(() => {
    'use strict';

    const CG = window.CampGear;
    const $ = (s, r = document) => r.querySelector(s);
    const $$ = (s, r = document) => Array.from(r.querySelectorAll(s));
    const clamp = (v, min, max) => Math.min(max, Math.max(min, v));
    const store = CG.store;
    const rupiah = (n) => 'Rp' + Math.round(n).toLocaleString('id-ID');
    const escapeHtml = (text) => String(text).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

    // ---- Tanggal ---------------------------------------------------------------

    const pad = (n) => String(n).padStart(2, '0');
    const iso = (d) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
    const parseIso = (s) => {
        const m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(s || '');
        return m ? new Date(Number(m[1]), Number(m[2]) - 1, Number(m[3])) : null;
    };
    const addDays = (d, n) => new Date(d.getFullYear(), d.getMonth(), d.getDate() + n);
    const daysBetween = (a, b) => Math.round((b - a) / 864e5) + 1;
    const HARI = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];
    const HARI_PANJANG = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
    const BULAN = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    const BULAN_PANJANG = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    const fmtShort = (d) => `${HARI[d.getDay()]}, ${d.getDate()} ${BULAN[d.getMonth()]}`;
    const fmtRange = (a, b) => (a.getMonth() === b.getMonth()
        ? `${a.getDate()}–${b.getDate()} ${BULAN[b.getMonth()]}`
        : `${a.getDate()} ${BULAN[a.getMonth()]} – ${b.getDate()} ${BULAN[b.getMonth()]}`);

    const calendar = $('#pop-kalender');
    const TODAY = parseIso(calendar?.dataset.today) || (() => { const d = new Date(); return new Date(d.getFullYear(), d.getMonth(), d.getDate()); })();
    const MAX_DAYS = Number(calendar?.dataset.maxDays || 14);
    const MAX_ADVANCE = Number(calendar?.dataset.maxAdvance || 90);
    const LAST_START = addDays(TODAY, MAX_ADVANCE);

    // ---- Trip ------------------------------------------------------------------

    const TRIP_KEY = 'cg-trip';
    const state = { start: null, end: null, people: 2 };
    const validRange = (s, e) => s >= TODAY && s <= LAST_START && e >= s && daysBetween(s, e) <= MAX_DAYS;

    try {
        const saved = JSON.parse(store.get(TRIP_KEY) || 'null');
        if (saved) {
            const s = parseIso(saved.start);
            const e = parseIso(saved.end);
            if (s && e && validRange(s, e)) { state.start = s; state.end = e; }
            state.people = clamp(parseInt(saved.people, 10) || 2, 1, 20);
        }
    } catch { /* data rusak: mulai dari kosong */ }

    let availabilityCache = { key: '', request: null };

    const trip = {
        get start() { return state.start; },
        get end() { return state.end; },
        get people() { return state.people; },
        hasDates: () => state.start !== null && state.end !== null,
        days: () => (trip.hasDates() ? daysBetween(state.start, state.end) : 0),
        range: () => (trip.hasDates() ? fmtRange(state.start, state.end) : ''),
        fmtShort,
        /** Bagian tanggal untuk tiket: "21 Nov", "Sabtu", 2026, "Sabtu, 21 November". */
        parts: (d) => ({
            date: `${d.getDate()} ${BULAN[d.getMonth()]}`,
            day: HARI_PANJANG[d.getDay()],
            year: d.getFullYear(),
            long: `${HARI_PANJANG[d.getDay()]}, ${d.getDate()} ${BULAN_PANJANG[d.getMonth()]}`,
        }),
        daysBetween,
        setDates(start, end) {
            if (!start || !end || !validRange(start, end)) return false;
            state.start = start;
            state.end = end;
            save();
            return true;
        },
        setPeople(n) { state.people = clamp(n, 1, 20); save(); },
        /** Ketersediaan stok untuk tanggal trip (hasil di-cache per rentang). */
        availability() {
            if (!trip.hasDates()) return Promise.resolve(null);
            const key = `${iso(state.start)}|${iso(state.end)}`;
            if (availabilityCache.key !== key) {
                const params = new URLSearchParams({ start: iso(state.start), end: iso(state.end) });
                availabilityCache = { key, request: CG.api('/api/availability?' + params.toString()) };
                availabilityCache.request.catch(() => { availabilityCache = { key: '', request: null }; });
            }
            return availabilityCache.request;
        },
        /** Parameter tanggal untuk URL /sewa. */
        params() {
            return trip.hasDates() ? { mulai: iso(state.start), selesai: iso(state.end) } : {};
        },
        open: (anchor, onApply) => openCalendar(anchor, onApply),
    };
    CG.trip = trip;

    function save() {
        store.set(TRIP_KEY, JSON.stringify({
            start: state.start ? iso(state.start) : null,
            end: state.end ? iso(state.end) : null,
            people: state.people,
        }));
        refreshTripUi();
        document.dispatchEvent(new CustomEvent('campgear:trip', { detail: trip }));
    }

    function refreshTripUi() {
        const has = trip.hasDates();
        $$('[data-trip-chip]').forEach((chip) => { chip.hidden = !has; });
        $$('[data-trip-label]').forEach((el) => { el.textContent = has ? `${trip.range()} · ${state.people} org` : ''; });
        $$('[data-trip-dates]').forEach((el) => { el.textContent = has ? `${fmtShort(state.start)} – ${fmtShort(state.end)}` : el.dataset.tripEmpty || 'Pilih tanggal'; });
        $$('[data-trip-range]').forEach((el) => { el.textContent = has ? trip.range() : el.dataset.tripEmpty || 'Pilih tanggal'; });
        // Tautan /sewa membawa tanggal trip.
        $$('[data-sewa-link]').forEach((link) => {
            const url = new URL(link.href, window.location.href);
            url.searchParams.delete('mulai');
            url.searchParams.delete('selesai');
            Object.entries(trip.params()).forEach(([k, v]) => url.searchParams.set(k, v));
            link.href = url.pathname + url.search;
        });
        renderAvailability();
    }

    document.addEventListener('click', (event) => {
        const opener = event.target.closest('[data-trip-open]');
        if (!opener) return;
        event.preventDefault();
        openCalendar(opener);
    });

    // ---- Kalender --------------------------------------------------------------

    const cal = { view: new Date(TODAY.getFullYear(), TODAY.getMonth(), 1), start: null, end: null, onApply: null };
    const firstMonth = new Date(TODAY.getFullYear(), TODAY.getMonth(), 1);
    const lastDay = addDays(LAST_START, MAX_DAYS - 1);
    const lastMonth = new Date(lastDay.getFullYear(), lastDay.getMonth(), 1);

    function dayState(date) {
        const t = date.getTime();
        if (t < TODAY.getTime()) return { off: true, why: 'Tanggal sudah lewat' };
        if (cal.start && !cal.end && t >= cal.start.getTime()) {
            return t > addDays(cal.start, MAX_DAYS - 1).getTime()
                ? { off: true, why: `Maksimal ${MAX_DAYS} hari per booking` }
                : { off: false };
        }
        if (t > LAST_START.getTime()) return { off: true, why: `Paling jauh ${MAX_ADVANCE} hari dari hari ini` };
        return { off: false };
    }

    function renderCalendar() {
        if (!calendar) return;
        const y = cal.view.getFullYear();
        const m = cal.view.getMonth();
        $('[data-cal-title]', calendar).textContent = `${BULAN_PANJANG[m]} ${y}`;
        $('[data-cal-step="-1"]', calendar).disabled = cal.view <= firstMonth;
        $('[data-cal-step="1"]', calendar).disabled = cal.view >= lastMonth;

        const offset = (new Date(y, m, 1).getDay() + 6) % 7; // minggu dimulai Senin
        const days = new Date(y, m + 1, 0).getDate();
        let html = ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'].map((d) => `<span class="cal-wd">${d}</span>`).join('');
        html += '<span></span>'.repeat(offset);
        for (let d = 1; d <= days; d++) {
            const date = new Date(y, m, d);
            const st = dayState(date);
            const label = `${HARI_PANJANG[date.getDay()]}, ${d} ${BULAN_PANJANG[m]} ${y}${st.off ? '. ' + st.why : ''}`;
            html += `<button type="button" class="cal-day${st.off ? ' is-off' : ''}${date.getTime() === TODAY.getTime() ? ' is-today' : ''}"`
                + ` data-time="${date.getTime()}" aria-label="${label}"${st.off ? ` aria-disabled="true" title="${st.why}"` : ''}>${d}</button>`;
        }
        $('[data-cal-grid]', calendar).innerHTML = html;
        paintRange(null);

        const sum = $('[data-cal-sum]', calendar);
        const hint = $('[data-cal-hint]', calendar);
        hint.classList.remove('is-error');
        if (cal.start && cal.end) {
            sum.textContent = `${fmtShort(cal.start)} – ${fmtShort(cal.end)} · ${daysBetween(cal.start, cal.end)} hari`;
            hint.textContent = 'Durasi dihitung termasuk tanggal ambil dan tanggal kembali.';
        } else if (cal.start) {
            sum.textContent = `Ambil ${fmtShort(cal.start)}`;
            hint.textContent = `Pilih tanggal kembali, paling lambat ${fmtShort(addDays(cal.start, MAX_DAYS - 1))} (maksimal ${MAX_DAYS} hari).`;
        } else {
            sum.textContent = 'Pilih tanggal ambil';
            hint.textContent = `Booking bisa dibuat untuk hari ini sampai ${fmtShort(LAST_START)} (${MAX_ADVANCE} hari ke depan).`;
        }
        $('[data-cal-apply]', calendar).disabled = !(cal.start && cal.end);
    }

    function paintRange(hoverTime) {
        const s = cal.start ? cal.start.getTime() : null;
        let e = cal.end ? cal.end.getTime() : null;
        if (e === null && s !== null && hoverTime && hoverTime >= s && hoverTime <= addDays(cal.start, MAX_DAYS - 1).getTime()) e = hoverTime;
        $$('.cal-day', calendar).forEach((btn) => {
            const t = Number(btn.dataset.time);
            btn.classList.toggle('is-start', s !== null && t === s);
            btn.classList.toggle('is-end', e !== null && t === e);
            btn.classList.toggle('is-in', s !== null && e !== null && t > s && t < e);
        });
    }

    function openCalendar(anchor, onApply) {
        if (!calendar) return;
        cal.start = state.start;
        cal.end = state.end;
        const v = state.start || TODAY;
        cal.view = new Date(v.getFullYear(), v.getMonth(), 1);
        cal.onApply = onApply || null;
        renderCalendar();
        CG.pop.show(calendar, anchor, 'start');
        $('.cal-day:not(.is-off)', calendar)?.focus({ preventScroll: true });
    }

    if (calendar) {
        const grid = $('[data-cal-grid]', calendar);
        grid.addEventListener('mouseover', (event) => {
            const btn = event.target.closest('.cal-day');
            if (btn && cal.start && !cal.end) paintRange(Number(btn.dataset.time));
        });
        grid.addEventListener('click', (event) => {
            const btn = event.target.closest('.cal-day');
            if (!btn) return;
            const date = new Date(Number(btn.dataset.time));
            const st = dayState(date);
            if (st.off) {
                const hint = $('[data-cal-hint]', calendar);
                hint.textContent = st.why.startsWith('Maksimal') && cal.start
                    ? `${st.why}. Pilih tanggal kembali paling lambat ${fmtShort(addDays(cal.start, MAX_DAYS - 1))}.`
                    : `${st.why}.`;
                hint.classList.add('is-error');
                return;
            }
            if (!cal.start || cal.end || date < cal.start) { cal.start = date; cal.end = null; } else { cal.end = date; }
            renderCalendar();
            $(`.cal-day[data-time="${date.getTime()}"]`, calendar)?.focus();
        });
        $$('[data-cal-step]', calendar).forEach((btn) => btn.addEventListener('click', () => {
            cal.view = new Date(cal.view.getFullYear(), cal.view.getMonth() + Number(btn.dataset.calStep), 1);
            renderCalendar();
        }));
        $('[data-cal-reset]', calendar).addEventListener('click', () => { cal.start = null; cal.end = null; renderCalendar(); });
        $('[data-cal-apply]', calendar).addEventListener('click', () => {
            if (!trip.setDates(cal.start, cal.end)) return;
            CG.pop.hide(calendar);
            if (cal.onApply) cal.onApply();
            else CG.toast(`Tanggal sewa ${trip.range()} disimpan. Stok di katalog menyesuaikan tanggal ini.`);
        });
    }

    // ---- Stok di katalog -------------------------------------------------------

    /**
     * Isi elemen [data-avail-id] (alat) dan [data-avail-pkg] (paket) dengan stok tanggal trip.
     * Elemen dengan data-avail-short memakai teks pendek ("6 unit", "Sisa 2", "Habis").
     */
    function renderAvailability() {
        const els = $$('[data-avail-id], [data-avail-pkg]');
        if (els.length === 0) return;
        const setLine = (el, text, tone) => {
            el.textContent = text;
            el.classList.remove('is-ok', 'is-low', 'is-out');
            if (tone) el.classList.add(tone);
            el.closest('.gear-card, [data-avail-row]')?.classList.toggle('is-out', tone === 'is-out');
        };
        const isShort = (el) => el.hasAttribute('data-avail-short');
        if (!trip.hasDates()) {
            els.forEach((el) => setLine(el, el.dataset.availEmpty || 'Pilih tanggal untuk cek stok', ''));
            return;
        }
        els.forEach((el) => setLine(el, isShort(el) ? '…' : 'Mengecek stok…', ''));
        trip.availability().then((data) => {
            const range = trip.range();
            els.forEach((el) => {
                const isPackage = el.dataset.availPkg !== undefined;
                const n = Number((isPackage ? data.packages[el.dataset.availPkg] : data.equipment[el.dataset.availId]) ?? 0);
                const unit = isPackage ? 'set' : 'unit';
                const short = isShort(el);
                if (n <= 0) setLine(el, short ? 'Habis' : `Habis ${range}`, 'is-out');
                else if (n <= 2) setLine(el, short ? `Sisa ${n}` : `Sisa ${n} ${unit} · ${range}`, 'is-low');
                else setLine(el, short ? `${n} ${unit}` : `${n} ${unit} tersedia · ${range}`, 'is-ok');
            });
        }).catch(() => els.forEach((el) => setLine(el, isShort(el) ? '–' : 'Gagal mengecek stok. Coba muat ulang.', '')));
    }

    // Form /sewa: isi tanggal dari trip bila kosong, dan simpan tanggal baru ke trip.
    const bookingForm = $('#booking-form');
    if (bookingForm) {
        const startInput = $('#start_date', bookingForm);
        const endInput = $('#end_date', bookingForm);
        if (startInput && endInput) {
            if (!startInput.value && !endInput.value && trip.hasDates()) {
                startInput.value = iso(state.start);
                endInput.value = iso(state.end);
                startInput.dispatchEvent(new Event('change', { bubbles: true }));
            }
            const sync = () => { const s = parseIso(startInput.value); const e = parseIso(endInput.value); if (s && e) trip.setDates(s, e); };
            startInput.addEventListener('change', sync);
            endInput.addEventListener('change', sync);
        }
        // Booking dikirim: isi ransel sudah dipindahkan ke form, ransel dikosongkan.
        bookingForm.addEventListener('submit', (event) => { if (!event.defaultPrevented) ransel.clear(false); });
    }

    // ---- Ransel ----------------------------------------------------------------

    const RANSEL_KEY = 'cg-ransel';
    const bag = { item: {}, package: {} };
    try {
        const saved = JSON.parse(store.get(RANSEL_KEY) || 'null');
        ['item', 'package'].forEach((type) => {
            Object.entries(saved?.[type] || {}).forEach(([id, qty]) => {
                const n = parseInt(qty, 10);
                if (/^\d+$/.test(id) && n > 0) bag[type][id] = Math.min(n, 99);
            });
        });
    } catch { /* abaikan */ }

    const drawer = $('#ransel');
    const count = () => Object.values(bag.item).concat(Object.values(bag.package)).reduce((s, n) => s + n, 0);

    const ransel = {
        count,
        /** Salinan isi ransel: { item: {id: qty}, package: {id: qty} }. */
        contents: () => ({ item: { ...bag.item }, package: { ...bag.package } }),
        /** URL /sewa berisi tanggal trip, alat (items[id]) dan paket (packages[id]). */
        url() {
            const params = new URLSearchParams(trip.params());
            Object.entries(bag.package).forEach(([id, n]) => params.append(`packages[${id}]`, n));
            Object.entries(bag.item).forEach(([id, n]) => params.append(`items[${id}]`, n));
            const query = params.toString();
            return CG.url('/sewa') + (query ? `?${query}` : '');
        },
        async add(type, id, qty = 1, { from = null, name = '' } = {}) {
            const key = String(id);
            let wanted = (bag[type][key] || 0) + qty;
            if (trip.hasDates()) {
                try {
                    const data = await trip.availability();
                    const available = Number((type === 'package' ? data.packages[key] : data.equipment[key]) ?? 0);
                    if (available <= 0) { CG.toast(`${name || 'Alat ini'} habis untuk ${trip.range()}.`); return; }
                    if (wanted > available) {
                        wanted = available;
                        CG.toast(`Stok ${name || 'alat ini'} untuk ${trip.range()} tinggal ${available}. Jumlah di ransel disesuaikan.`);
                    }
                } catch { /* stok dicek lagi di halaman booking */ }
            }
            bag[type][key] = Math.min(wanted, 99);
            persist();
            if (from && CG.prefersMotion()) fly(from); else bump();
            CG.toast(`${name || 'Item'} masuk ransel${trip.hasDates() ? '' : '. Pilih tanggal untuk cek stok'}.`);
        },
        step(type, id, delta) {
            const key = String(id);
            const n = (bag[type][key] || 0) + delta;
            if (n <= 0) delete bag[type][key]; else bag[type][key] = Math.min(n, 99);
            persist();
        },
        clear(update = true) {
            bag.item = {};
            bag.package = {};
            store.remove(RANSEL_KEY);
            if (update) persist();
        },
        open() { renderDrawer(); CG.openDialog(drawer); },
    };
    CG.ransel = ransel;

    function persist() {
        store.set(RANSEL_KEY, JSON.stringify(bag));
        const n = count();
        $$('[data-ransel-count]').forEach((badge) => { badge.textContent = String(n); badge.hidden = n === 0; });
        $$('[data-ransel-open]').forEach((btn) => btn.setAttribute('aria-label', n ? `Ransel, ${n} barang` : 'Ransel, kosong'));
        if (drawer?.open) renderDrawer();
        document.dispatchEvent(new CustomEvent('campgear:ransel', { detail: { count: n } }));
    }

    const ranselTarget = () => $$('[data-ransel-open]').find((el) => CG.pop.visible(el));

    function bump() {
        const target = ranselTarget();
        if (!target) return;
        target.classList.remove('bump');
        void target.offsetWidth; // mulai ulang animasi
        target.classList.add('bump');
    }

    /** Titik kecil "terbang" dari tombol ke ikon ransel. */
    function fly(from) {
        const target = ranselTarget();
        if (!target || !CG.pop.visible(from)) { bump(); return; }
        const a = from.getBoundingClientRect();
        const b = target.getBoundingClientRect();
        const x0 = a.left + a.width / 2; const y0 = a.top + a.height / 2;
        const x1 = b.left + b.width / 2; const y1 = b.top + b.height / 2;
        const dot = document.createElement('div');
        dot.className = 'ransel-fly';
        document.body.appendChild(dot);
        dot.animate([
            { transform: `translate(${x0}px, ${y0}px) scale(1)` },
            { transform: `translate(${(x0 + x1) / 2}px, ${Math.min(y0, y1) - 110}px) scale(1.3)`, offset: 0.45 },
            { transform: `translate(${x1}px, ${y1}px) scale(.4)`, opacity: 0.6 },
        ], { duration: 720, easing: 'cubic-bezier(.45,0,.3,1)' }).finished
            .then(() => { dot.remove(); bump(); }, () => dot.remove());
    }

    async function renderDrawer() {
        if (!drawer) return;
        const listEl = $('[data-ransel-list]', drawer);
        const goEl = $('[data-ransel-go]', drawer);
        $('[data-ransel-trip]', drawer).textContent = trip.hasDates() ? `${fmtShort(state.start)} – ${fmtShort(state.end)} · ${trip.days()} hari` : 'Tanggal sewa belum dipilih';
        goEl.href = ransel.url();
        goEl.classList.toggle('disabled', count() === 0);

        if (count() === 0) {
            listEl.innerHTML = '<li class="drawer-empty"><i class="bi bi-backpack" aria-hidden="true"></i><b>Ransel masih kosong.</b><span>Tambahkan alat dari katalog atau paket dari beranda.</span></li>';
            setTotals(0);
            return;
        }
        let data;
        let stock = null;
        try {
            data = await CG.catalog();
            stock = trip.hasDates() ? await trip.availability() : null;
        } catch {
            if (!data) { listEl.innerHTML = '<li class="drawer-empty"><b>Data alat gagal dimuat.</b><span>Periksa koneksi lalu buka ransel lagi.</span></li>'; return; }
        }
        const equipment = new Map(data.equipment.map((e) => [String(e.id), e]));
        const packages = new Map(data.packages.map((p) => [String(p.id), p]));
        const lines = [];
        Object.entries(bag.package).forEach(([id, qty]) => {
            const p = packages.get(id);
            if (p) lines.push({ type: 'package', id, qty, name: p.name, sub: `Paket · hemat ${p.discount}%`, rate: p.daily_price, available: stock ? stock.packages[id] : null });
        });
        Object.entries(bag.item).forEach(([id, qty]) => {
            const e = equipment.get(id);
            if (e) lines.push({ type: 'item', id, qty, name: e.name, sub: e.category, rate: e.rate, available: stock ? stock.equipment[id] : null });
        });
        listEl.innerHTML = lines.map((l) => {
            const short = l.available !== null && l.available !== undefined && l.qty > l.available;
            return `<li class="drawer-line"><div><b>${escapeHtml(l.name)}</b><span>${escapeHtml(l.sub)} · ${rupiah(l.rate)}/hari</span>`
                + (short ? `<span class="d-block text-danger">Stok untuk tanggalmu tinggal ${l.available}</span>` : '')
                + `</div><div class="stepper"><button type="button" data-ransel-step="${l.type}:${l.id}:-1" aria-label="Kurangi ${escapeHtml(l.name)}"><i class="bi bi-dash" aria-hidden="true"></i></button>`
                + `<output>${l.qty}</output><button type="button" data-ransel-step="${l.type}:${l.id}:1" aria-label="Tambah ${escapeHtml(l.name)}"><i class="bi bi-plus" aria-hidden="true"></i></button></div>`
                + `<span class="line-total">${rupiah(l.rate * l.qty)}/hari</span></li>`;
        }).join('');
        setTotals(lines.reduce((sum, l) => sum + l.rate * l.qty, 0));
    }

    function setTotals(perDay) {
        $('[data-ransel-perday]', drawer).textContent = rupiah(perDay);
        const daysEl = $('[data-ransel-days]', drawer);
        const totalEl = $('[data-ransel-total]', drawer);
        if (trip.hasDates()) {
            daysEl.textContent = `Perkiraan ${trip.days()} hari`;
            totalEl.textContent = rupiah(perDay * trip.days());
        } else {
            daysEl.textContent = 'Pilih tanggal untuk menghitung total';
            totalEl.textContent = '';
        }
    }

    document.addEventListener('click', (event) => {
        const adder = event.target.closest('[data-ransel-add]');
        if (adder) {
            event.preventDefault();
            const [type, id] = adder.dataset.ranselAdd.split(':');
            ransel.add(type === 'package' ? 'package' : 'item', id, Number(adder.dataset.qty || 1), { from: adder, name: adder.dataset.name || '' });
            return;
        }
        if (event.target.closest('[data-ransel-open]')) { ransel.open(); return; }
        const stepper = event.target.closest('[data-ransel-step]');
        if (stepper) {
            const [type, id, delta] = stepper.dataset.ranselStep.split(':');
            ransel.step(type, id, Number(delta));
            return;
        }
        if (event.target.closest('[data-ransel-clear]')) { ransel.clear(); return; }
        const go = event.target.closest('[data-ransel-go]');
        if (go && count() === 0) event.preventDefault();
    });

    persist();
    refreshTripUi();
})();
