/**
 * CampGear — katalog alat (beranda dan /alat).
 *
 *   Filter      chip kategori menyaring kartu tanpa memuat ulang halaman. Di /alat,
 *               URL, judul, dan deskripsi halaman ikut berganti (?kategori=...).
 *   Bar chip    menempel di bawah navbar selama katalog di-scroll (position: sticky)
 *               dan diberi bayangan saat sedang menempel.
 *   Quick view  klik kartu membuka ringkasan alat: stok di tanggal trip, paket yang
 *               memuatnya, dan tombol ransel dengan jumlah. Gambar alat berpindah dari
 *               kartu ke dialog lewat View Transitions bila browser mendukung.
 *
 * Tanpa JavaScript, chip dan kartu tetap tautan biasa ke /alat?kategori=... dan /alat/{slug}.
 */
(() => {
    'use strict';

    const CG = window.CampGear;
    const root = document.querySelector('[data-katalog]');
    if (!CG || !root) return;

    const $ = (s, r = document) => r.querySelector(s);
    const $$ = (s, r = document) => Array.from(r.querySelectorAll(s));
    const clamp = (v, min, max) => Math.min(max, Math.max(min, v));
    const rupiah = (n) => 'Rp' + Math.round(n).toLocaleString('id-ID');
    const escapeHtml = (text) => String(text).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const motion = CG.prefersMotion();
    /** Klik biasa (bukan Ctrl/Cmd/Shift+klik yang membuka tab baru). */
    const plainClick = (event) => event.button === 0 && !event.metaKey && !event.ctrlKey && !event.shiftKey && !event.altKey;

    // ---- Bar chip menempel -----------------------------------------------------

    const bar = $('[data-catbar]', root);
    const sentinel = document.createElement('div'); // penanda posisi asli bar
    sentinel.setAttribute('aria-hidden', 'true');
    bar.before(sentinel);
    let stuckQueued = false;
    function checkStuck() {
        stuckQueued = false;
        const stickyTop = parseFloat(getComputedStyle(bar).top) || 0;
        bar.classList.toggle('is-stuck', sentinel.getBoundingClientRect().top < stickyTop);
    }
    addEventListener('scroll', () => {
        if (!stuckQueued) { stuckQueued = true; requestAnimationFrame(checkStuck); }
    }, { passive: true });
    checkStuck();

    // ---- Filter kategori -------------------------------------------------------

    const cards = $$('.gear-card', root);
    const chips = $$('[data-chip]', root);
    const chipRow = $('.chips', root);

    /** Tandai baris chip yang masih bisa digeser, dan pastikan chip aktif terlihat. */
    function markChipRow() {
        chipRow.classList.toggle('has-more', chipRow.scrollWidth - chipRow.clientWidth - chipRow.scrollLeft > 4);
    }
    function revealChip(chip) {
        const left = chip.offsetLeft - chipRow.offsetLeft;
        if (left < chipRow.scrollLeft || left + chip.offsetWidth > chipRow.scrollLeft + chipRow.clientWidth) {
            chipRow.scrollTo({ left: Math.max(0, left - 24), behavior: motion ? 'smooth' : 'auto' });
        }
    }
    chipRow.addEventListener('scroll', markChipRow, { passive: true });
    addEventListener('resize', markChipRow);
    markChipRow();
    const initialChip = chips.find((c) => c.hasAttribute('aria-current'));
    if (initialChip) revealChip(initialChip);
    const countEl = $('[data-katalog-count]', root);
    const emptyEl = $('[data-katalog-empty]', root);
    const syncUrl = root.hasAttribute('data-katalog-url');
    const titleEl = $('[data-katalog-title]');
    const pageDescEl = $('[data-katalog-desc]');
    const search = root.dataset.search || '';

    function setFilter(slug, { animate = true } = {}) {
        const chip = chips.find((c) => c.dataset.chip === slug) || chips[0];
        const active = chip ? chip.dataset.chip : '';
        chips.forEach((c) => { if (c === chip) c.setAttribute('aria-current', 'true'); else c.removeAttribute('aria-current'); });
        if (chip) revealChip(chip);

        const shown = cards.filter((card) => {
            const on = active === '' || card.dataset.cat === active;
            card.hidden = !on;
            return on;
        });
        if (emptyEl) emptyEl.hidden = shown.length > 0;
        if (countEl) {
            countEl.textContent = `Menampilkan ${shown.length} alat`
                + (active ? ` di ${chip.dataset.name}` : '')
                + (search ? ` untuk "${search}"` : '');
        }
        if (animate && motion) {
            shown.forEach((card, i) => card.animate(
                [{ opacity: 0, transform: 'translateY(10px)' }, { opacity: 1, transform: 'none' }],
                { duration: 340, delay: Math.min(i, 12) * 28, easing: 'cubic-bezier(.2,.8,.2,1)', fill: 'backwards' },
            ));
        }
        if (syncUrl) {
            const url = new URL(window.location.href);
            if (active) url.searchParams.set('kategori', active); else url.searchParams.delete('kategori');
            history.replaceState(history.state, '', url.pathname + url.search + url.hash);
            if (titleEl) titleEl.textContent = active ? chip.dataset.name : titleEl.dataset.default;
            if (pageDescEl) pageDescEl.textContent = active && chip.dataset.desc ? chip.dataset.desc : pageDescEl.dataset.default;
        }
    }

    chips.forEach((chip) => chip.addEventListener('click', (event) => {
        if (!plainClick(event)) return;
        event.preventDefault();
        setFilter(chip.dataset.chip);
        // Bila pengguna sudah di tengah grid, bawa kembali ke awal katalog agar hasilnya terlihat.
        if (bar.classList.contains('is-stuck')) {
            const y = sentinel.getBoundingClientRect().top + scrollY - (parseFloat(getComputedStyle(bar).top) || 0) - 4;
            if (CG.scrollToY) CG.scrollToY(y, 0.8);
            else window.scrollTo({ top: y, behavior: motion ? 'smooth' : 'auto' });
        }
    }));

    CG.katalog = { filter: setFilter };

    // ---- Quick view ------------------------------------------------------------

    const dialog = $('#quick-view');
    if (!dialog) return;
    const q = (name) => $(`[data-quick-${name}]`, dialog);
    const art = q('art');
    const availEl = q('avail');
    const qtyEl = q('qty');
    const addBtn = q('add');
    const dec = $('[data-quick-step="-1"]', dialog);
    const inc = $('[data-quick-step="1"]', dialog);
    let card = null;
    let gear = null;
    let qty = 1;
    let max = 1;

    function renderQty() {
        qtyEl.textContent = String(qty);
        dec.disabled = qty <= 1;
        inc.disabled = qty >= max;
        addBtn.disabled = max <= 0;
        addBtn.dataset.qty = String(qty);
    }

    /** Stok di tanggal trip; jumlah yang bisa dimasukkan ransel dibatasi stok itu. */
    async function renderAvail() {
        availEl.className = '';
        if (!CG.trip.hasDates()) {
            availEl.innerHTML = '<button type="button" data-quick-dates>Pilih tanggal</button>';
            max = gear.stock;
            renderQty();
            return;
        }
        const id = gear.id;
        availEl.textContent = 'Mengecek…';
        try {
            const data = await CG.trip.availability();
            if (!gear || gear.id !== id) return; // dialog sudah menampilkan alat lain
            const n = Number(data.equipment[id] ?? 0);
            availEl.textContent = n <= 0 ? `Habis ${CG.trip.range()}` : `${n} unit · ${CG.trip.range()}`;
            availEl.className = n <= 0 ? 'is-out' : n <= 2 ? 'is-low' : 'is-ok';
            max = Math.max(0, n);
            qty = clamp(qty, 1, Math.max(1, max));
            renderQty();
        } catch {
            availEl.textContent = 'Gagal mengecek stok';
        }
    }

    function fill(target) {
        card = target;
        gear = JSON.parse(target.dataset.gear);
        art.innerHTML = $('.gear-thumb', target)?.innerHTML || '';
        q('cat').textContent = gear.cat;
        q('name').textContent = gear.name;
        q('desc').textContent = gear.desc;
        q('rate').textContent = `${rupiah(gear.rate)} / hari`;
        q('stock').textContent = `${gear.stock} unit`;
        q('link').href = gear.url;
        q('pkgs').innerHTML = gear.packages.length
            ? 'Termasuk dalam ' + gear.packages.map((p) => `<a href="${escapeHtml(p.url)}" data-go-pkg="${Number(p.id)}">${escapeHtml(p.name)}</a>`).join(', ') + '.'
            : 'Belum termasuk paket mana pun.';
        addBtn.dataset.ranselAdd = `item:${gear.id}`;
        addBtn.dataset.name = gear.name;
        qty = 1;
        max = gear.stock;
        renderQty();
        renderAvail();
    }

    function open(target, morph = true) {
        const thumb = $('.gear-thumb', target);
        const show = () => { fill(target); CG.openDialog(dialog); };
        if (!morph || !motion || !document.startViewTransition || !thumb) { show(); return; }
        // Gambar di kartu dan di dialog diberi nama transisi yang sama selama perpindahan.
        thumb.style.viewTransitionName = 'gear-art';
        const transition = document.startViewTransition(() => {
            thumb.style.viewTransitionName = '';
            show();
            art.style.viewTransitionName = 'gear-art';
        });
        transition.finished.finally(() => { art.style.viewTransitionName = ''; });
    }

    root.addEventListener('click', (event) => {
        const link = event.target.closest('[data-quick]');
        if (!link || !plainClick(event)) return;
        event.preventDefault();
        open(link.closest('.gear-card'));
    });

    [dec, inc].forEach((btn) => btn.addEventListener('click', () => {
        qty = clamp(qty + Number(btn.dataset.quickStep), 1, Math.max(1, max));
        renderQty();
    }));

    // Tombol ransel memakai data-ransel-add (diproses trip.js); dialog cukup ditutup.
    addBtn.addEventListener('click', () => dialog.close());

    // Kalender tidak bisa dipakai di atas dialog modal, jadi dialog ditutup dulu,
    // kalender dibuka di chip tanggal, lalu quick view dibuka lagi setelah tanggal dipilih.
    dialog.addEventListener('click', (event) => {
        if (!event.target.closest('[data-quick-dates]')) return;
        const reopen = card;
        dialog.close();
        CG.trip.open($('.chip-trip', root), () => { if (reopen) open(reopen, false); });
    });

    document.addEventListener('campgear:trip', () => { if (dialog.open && gear) renderAvail(); });
})();
