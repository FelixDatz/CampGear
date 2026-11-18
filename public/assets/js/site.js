/**
 * CampGear — antarmuka global halaman publik (vanilla JavaScript).
 *
 *   1. Langit & tema   posisi matahari u (0 = fajar, 0,5 = siang, 1 = malam)
 *                      menentukan tema terang/gelap. Tersimpan di localStorage.
 *   2. Lampu tenda     cahaya hover yang mengikuti kursor pada elemen .lamp.
 *   3. Navbar          bar melayang saat halaman di-scroll, menu Jelajahi & Panduan.
 *   4. Popover         penempatan di dekat tombol pemicu (Popover API bawaan browser).
 *   5. Palet cari      Ctrl+K atau "/" untuk mencari alat, paket, halaman, kode booking.
 *   6. Dialog & toast
 */
(() => {
    'use strict';

    const CG = window.CampGear;
    const root = document.documentElement;
    const $ = (s, r = document) => r.querySelector(s);
    const $$ = (s, r = document) => Array.from(r.querySelectorAll(s));
    const clamp = (v, min, max) => Math.min(max, Math.max(min, v));
    const reduceMotion = matchMedia('(prefers-reduced-motion: reduce)');
    const store = {
        get(key) { try { return localStorage.getItem(key); } catch { return null; } },
        set(key, value) { try { localStorage.setItem(key, value); } catch { /* penyimpanan diblokir: abaikan */ } },
        remove(key) { try { localStorage.removeItem(key); } catch { /* abaikan */ } },
    };
    CG.store = store;
    CG.url = (path) => CG.baseUrl + path;
    CG.prefersMotion = () => !reduceMotion.matches;

    // ---- 1. Langit & tema -----------------------------------------------------

    const SKY_KEY = 'cg-langit';
    const NIGHT = 0.88; // matahari melewati cakrawala: tema berganti gelap
    const PHASES = [['fajar', 0.2], ['siang', 0.66], ['senja', NIGHT], ['malam', 2]];
    const PHASE_LABEL = { fajar: 'Fajar', siang: 'Siang', senja: 'Senja', malam: 'Malam' };
    const PHASE_ICON = { fajar: 'bi-sunrise', siang: 'bi-sun', senja: 'bi-sunset', malam: 'bi-moon-stars' };
    const phaseOf = (u) => PHASES.find(([, max]) => u < max)[0];

    /** Posisi bawaan: dark mode perangkat = malam; selain itu ikut jam, dibatasi fajar–senja. */
    function autoU() {
        if (matchMedia('(prefers-color-scheme: dark)').matches) return 1;
        const now = new Date();
        return clamp((now.getHours() * 60 + now.getMinutes() - 330) / 780, 0, 0.86);
    }

    const sky = {
        u: window.__langit ? window.__langit.u : autoU(),
        manual: !!(window.__langit && window.__langit.manual),
        NIGHT,
        theme(u = this.u) { return u >= NIGHT ? 'dark' : 'light'; },
        phase(u = this.u) { return phaseOf(u); },
        label(u = this.u) { return PHASE_LABEL[phaseOf(u)]; },
        /** Perkiraan jam untuk posisi u (05.30–18.30), dipakai label aksesibilitas. */
        time(u = this.u) {
            if (u >= NIGHT) return 'malam';
            const m = Math.round(330 + u * 780);
            return `${String(Math.floor(m / 60)).padStart(2, '0')}.${String(m % 60).padStart(2, '0')}`;
        },
        /**
         * Ubah posisi matahari.
         * opts.persist  simpan pilihan (false selama matahari sedang digeser)
         * opts.origin   {x, y}: tema baru menyebar melingkar dari titik ini
         */
        set(u, { manual = true, persist = true, origin = null } = {}) {
            u = clamp(u, 0, 1);
            const themeChanges = this.theme(u) !== this.theme();
            this.u = u;
            this.manual = manual;
            if (origin && themeChanges && document.startViewTransition && !reduceMotion.matches) {
                revealTheme(origin);
            } else {
                applySky();
            }
            if (persist) {
                if (manual) store.set(SKY_KEY, JSON.stringify({ u: Math.round(u * 1000) / 1000, manual: true }));
                else store.remove(SKY_KEY);
            }
        },
        auto(origin = null) { this.set(autoU(), { manual: false, origin }); },
    };
    CG.sky = sky;

    function applySky() {
        const theme = sky.theme();
        const changed = root.getAttribute('data-theme') !== theme;
        const phase = sky.phase();
        root.setAttribute('data-theme', theme);
        root.setAttribute('data-bs-theme', theme);
        root.setAttribute('data-langit', phase);

        $$('[data-sky-icon]').forEach((icon) => { icon.className = `bi ${PHASE_ICON[phase]}`; });
        $$('[data-sky-tip]').forEach((tip) => { tip.textContent = `Langit: ${PHASE_LABEL[phase]}`; });
        $$('[data-sky-set]').forEach((btn) => btn.setAttribute('aria-pressed', String(btn.dataset.skyPhase === phase)));
        $$('[data-sky-auto]').forEach((btn) => btn.classList.toggle('active', !sky.manual));

        document.dispatchEvent(new CustomEvent('campgear:sky', { detail: { u: sky.u, phase, theme } }));
        if (changed) document.dispatchEvent(new CustomEvent('campgear:theme', { detail: { theme } }));
    }

    /** Pergantian tema dengan lingkaran yang membesar dari posisi matahari/tombol. */
    function revealTheme({ x, y }) {
        const radius = Math.hypot(Math.max(x, innerWidth - x), Math.max(y, innerHeight - y));
        const transition = document.startViewTransition(applySky);
        transition.ready.then(() => {
            root.animate(
                { clipPath: [`circle(0px at ${x}px ${y}px)`, `circle(${radius}px at ${x}px ${y}px)`] },
                { duration: 650, easing: 'cubic-bezier(.2,.8,.2,1)', pseudoElement: '::view-transition-new(root)' },
            );
        }).catch(() => { /* transisi dibatalkan: tema tetap diterapkan */ });
    }

    const centerOf = (el) => { const r = el.getBoundingClientRect(); return { x: r.left + r.width / 2, y: r.top + r.height / 2 }; };
    document.addEventListener('click', (event) => {
        const station = event.target.closest('[data-sky-set]');
        if (station) sky.set(Number(station.dataset.skySet), { origin: centerOf(station) });
        const auto = event.target.closest('[data-sky-auto]');
        if (auto) sky.auto(centerOf(auto));
    });
    // Bila belum memilih manual, ikuti perubahan dark mode perangkat.
    matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => { if (!sky.manual) sky.auto(); });
    applySky();

    // ---- 2. Lampu tenda --------------------------------------------------------

    let lampEvent = null;
    let lampFrame = 0;
    document.addEventListener('pointermove', (event) => {
        if (event.pointerType !== 'mouse') return;
        lampEvent = event;
        if (lampFrame) return;
        lampFrame = requestAnimationFrame(() => {
            lampFrame = 0;
            const el = lampEvent.target instanceof Element ? lampEvent.target.closest('.lamp') : null;
            if (!el) return;
            const r = el.getBoundingClientRect();
            el.style.setProperty('--lx', `${lampEvent.clientX - r.left}px`);
            el.style.setProperty('--ly', `${lampEvent.clientY - r.top}px`);
        });
    }, { passive: true });

    // ---- 3. Navbar -------------------------------------------------------------

    const sitebar = $('[data-sitebar]');
    const updateBar = () => sitebar?.classList.toggle('is-scrolled', scrollY > 24);
    addEventListener('scroll', updateBar, { passive: true });
    updateBar();

    const menus = $$('[data-menu]');
    const hoverCapable = matchMedia('(hover: hover) and (pointer: fine)').matches;

    function setMenu(group, open) {
        const button = $('[data-menu-toggle]', group);
        $('[data-menu-panel]', group).classList.toggle('is-open', open);
        button.setAttribute('aria-expanded', String(open));
        if (open) {
            group.dataset.openedAt = String(Date.now());
            menus.forEach((other) => { if (other !== group) setMenu(other, false); });
        }
    }
    CG.closeMenus = () => menus.forEach((group) => setMenu(group, false));

    menus.forEach((group) => {
        const button = $('[data-menu-toggle]', group);
        button.addEventListener('click', () => {
            const open = button.getAttribute('aria-expanded') === 'true';
            // Menu yang baru saja terbuka karena hover tidak langsung ditutup oleh klik.
            if (open && Date.now() - Number(group.dataset.openedAt || 0) < 450) return;
            setMenu(group, !open);
        });
        if (hoverCapable) {
            let timer = 0;
            group.addEventListener('mouseenter', () => { clearTimeout(timer); timer = setTimeout(() => setMenu(group, true), 110); });
            group.addEventListener('mouseleave', () => { clearTimeout(timer); timer = setTimeout(() => setMenu(group, false), 240); });
        }
        group.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') { setMenu(group, false); button.focus(); }
        });
        group.addEventListener('focusout', (event) => {
            if (!group.contains(event.relatedTarget)) setMenu(group, false);
        });
    });
    document.addEventListener('click', (event) => menus.forEach((group) => { if (!group.contains(event.target)) setMenu(group, false); }));

    // Pratinjau kategori di mega panel.
    $$('[data-preview]').forEach((link) => {
        const show = () => {
            $$('[data-preview]').forEach((l) => l.classList.toggle('is-on', l === link));
            $$('[data-preview-for]').forEach((panel) => { panel.hidden = panel.dataset.previewFor !== link.dataset.preview; });
        };
        link.addEventListener('mouseenter', show);
        link.addEventListener('focus', show);
    });

    // ---- 4. Popover ------------------------------------------------------------

    const POPOVER = 'showPopover' in HTMLElement.prototype;
    if (!POPOVER) root.classList.add('no-popover');
    const isOpen = (pop) => {
        try { return POPOVER ? pop.matches(':popover-open') : pop.classList.contains('is-open'); } catch { return false; }
    };
    const visible = (el) => !!el && el.getClientRects().length > 0;

    /** Letakkan popover di bawah (atau di atas bila tidak muat) tombol pemicunya. */
    function place(pop) {
        const anchor = pop._anchor;
        if (!visible(anchor)) return;
        const r = anchor.getBoundingClientRect();
        const pw = pop.offsetWidth;
        const ph = pop.offsetHeight;
        const align = pop.dataset.align || 'end';
        let left = align === 'start' ? r.left : align === 'center' ? r.left + r.width / 2 - pw / 2 : r.right - pw;
        left = clamp(left, 10, Math.max(10, innerWidth - pw - 10));
        let top = r.bottom + 10;
        if (top + ph > innerHeight - 10 && r.top - ph - 10 > 10) top = r.top - ph - 10;
        top = clamp(top, 10, Math.max(10, innerHeight - ph - 10));
        pop.style.left = `${left}px`;
        pop.style.top = `${top}px`;
    }

    function showPop(pop, anchor, align) {
        if (anchor) pop._anchor = anchor;
        if (align) pop.dataset.align = align;
        if (POPOVER) { if (!isOpen(pop)) pop.showPopover(); } else pop.classList.add('is-open');
        place(pop);
        pop.classList.add('is-placed');
    }

    function hidePop(pop) {
        if (POPOVER) { if (isOpen(pop)) pop.hidePopover(); } else pop.classList.remove('is-open');
        pop.classList.remove('is-placed');
    }
    CG.pop = { show: showPop, hide: hidePop, isOpen, place, visible };

    // Catat tombol pemicu terakhir (dipakai untuk penempatan). Fase capture berjalan
    // sebelum browser membuka popover.
    document.addEventListener('click', (event) => {
        const invoker = event.target.closest('[popovertarget]');
        if (!invoker) {
            if (!POPOVER) $$('[popover].is-open').forEach((p) => { if (!p.contains(event.target)) hidePop(p); });
            return;
        }
        const pop = document.getElementById(invoker.getAttribute('popovertarget'));
        if (!pop) return;
        pop._anchor = invoker;
        if (!POPOVER) {
            event.preventDefault();
            if (isOpen(pop)) hidePop(pop); else showPop(pop, invoker);
        }
    }, true);

    $$('[popover]').forEach((pop) => pop.addEventListener('toggle', (event) => {
        const open = event.newState === 'open';
        $$(`[popovertarget="${pop.id}"]`).forEach((btn) => btn.setAttribute('aria-expanded', String(open && btn === pop._anchor)));
        if (open) {
            place(pop);
            pop.classList.add('is-placed');
            pop.querySelector('[data-autofocus]')?.focus();
        } else {
            pop.classList.remove('is-placed');
        }
    }));
    const replaceOpen = () => $$('[popover]').forEach((pop) => { if (isOpen(pop)) place(pop); });
    addEventListener('resize', replaceOpen);
    addEventListener('scroll', replaceOpen, { passive: true });

    // ---- 6. Dialog & toast -----------------------------------------------------

    CG.openDialog = (dialog) => {
        if (!dialog) return;
        CG.closeMenus();
        if (!dialog.open) dialog.showModal();
        document.dispatchEvent(new CustomEvent('campgear:dialog', { detail: { open: true } }));
    };
    $$('dialog').forEach((dialog) => {
        dialog.addEventListener('click', (event) => { if (event.target === dialog) dialog.close(); });
        dialog.addEventListener('close', () => {
            if (!$$('dialog').some((d) => d.open)) document.dispatchEvent(new CustomEvent('campgear:dialog', { detail: { open: false } }));
        });
    });
    document.addEventListener('click', (event) => {
        event.target.closest('[data-dialog-close]')?.closest('dialog')?.close();
        const opener = event.target.closest('[data-sheet-open]');
        if (opener) CG.openDialog(document.getElementById(opener.dataset.sheetOpen));
    });

    let toastTimer = 0;
    CG.toast = (message) => {
        const toast = $('#site-toast');
        if (!toast) return;
        toast.textContent = message;
        toast.classList.add('is-on');
        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => toast.classList.remove('is-on'), 3800);
    };

    // ---- 5. Palet pencarian ----------------------------------------------------

    /** Data katalog untuk pencarian & ransel: diambil sekali, saat pertama dibutuhkan. */
    let catalogRequest = null;
    CG.catalog = () => {
        catalogRequest ??= CG.api('/api/katalog').catch((error) => { catalogRequest = null; throw error; });
        return catalogRequest;
    };

    const palette = $('#palette');
    const input = $('#palette-input');
    const list = $('#palette-list');
    const PAGES = [
        ['Katalog alat', '/alat', 'bi-grid'],
        ['Paket sewa', '/paket', 'bi-box2-heart'],
        ['Sewa sekarang', '/sewa', 'bi-calendar-check'],
        ['Cek booking', '/cek-booking', 'bi-ticket-perforated'],
        ['Cara reservasi', '/cara-reservasi', 'bi-signpost-2'],
        ['Artikel', '/artikel', 'bi-journal-text'],
        ['Statistik penyewaan', '/statistik', 'bi-bar-chart'],
        ['Kontak & FAQ', '/kontak', 'bi-chat-dots'],
        ['Akun saya', '/akun', 'bi-person'],
    ];
    const normalize = (text) => String(text).toLowerCase().normalize('NFD').replace(/[^a-z0-9]/g, '');
    const escapeHtml = (text) => String(text).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const rupiah = (n) => 'Rp' + Math.round(n).toLocaleString('id-ID');
    let results = [];
    let active = 0;
    let catalogData = null;

    function search(query) {
        const raw = query.trim();
        const q = normalize(raw);
        const match = (text) => normalize(text).includes(q);
        const out = [];

        if (raw.length >= 2 && /^cg\d{0,6}(-[a-z0-9]{0,4})?$/i.test(raw)) {
            out.push({ group: 'Kode booking', label: `Cek booking ${raw.toUpperCase()}`, hint: 'Enter', icon: 'bi-ticket-perforated', run: () => openLookup(raw.toUpperCase()) });
        }
        if (q && catalogData) {
            catalogData.equipment.filter((e) => match(e.name) || match(e.category)).slice(0, 6)
                .forEach((e) => out.push({ group: 'Alat', label: e.name, hint: `${rupiah(e.rate)}/hari`, icon: 'bi-backpack', href: `/alat/${e.slug}` }));
            catalogData.packages.filter((p) => match(p.name))
                .forEach((p) => out.push({ group: 'Paket', label: p.name, hint: `${rupiah(p.daily_price)}/hari`, icon: 'bi-box2-heart', href: `/paket#${p.slug}` }));
            catalogData.articles.filter((a) => match(a.title)).slice(0, 4)
                .forEach((a) => out.push({ group: 'Artikel', label: a.title, hint: 'artikel', icon: 'bi-journal-text', href: `/artikel/${a.slug}` }));
        }
        PAGES.filter(([label, path]) => !q || match(label) || match(path))
            .forEach(([label, path, icon]) => out.push({ group: 'Halaman', label, hint: path, icon, href: path }));
        if (q && !out.some((r) => r.group !== 'Halaman')) {
            out.push({ group: 'Katalog', label: `Cari "${raw}" di katalog`, hint: 'Enter', icon: 'bi-search', href: `/alat?q=${encodeURIComponent(raw)}` });
        }
        return out;
    }

    function renderResults() {
        results = search(input.value);
        active = 0;
        if (results.length === 0) {
            list.innerHTML = `<p class="palette-empty">Tidak ada hasil. Coba nama alat, misalnya tenda atau carrier.</p>`;
            input.removeAttribute('aria-activedescendant');
            return;
        }
        let html = '';
        let lastGroup = '';
        results.forEach((item, i) => {
            if (item.group !== lastGroup) { html += `<p class="palette-group">${escapeHtml(item.group)}</p>`; lastGroup = item.group; }
            html += `<button type="button" class="palette-opt" role="option" id="palette-opt-${i}" data-index="${i}" aria-selected="${i === 0}">`
                + `<i class="bi ${item.icon}" aria-hidden="true"></i><span class="palette-label">${escapeHtml(item.label)}</span>`
                + `<span class="palette-hint">${escapeHtml(item.hint)}</span></button>`;
        });
        list.innerHTML = html;
        input.setAttribute('aria-activedescendant', 'palette-opt-0');
    }

    function select(index) {
        active = clamp(index, 0, results.length - 1);
        $$('.palette-opt', list).forEach((opt) => opt.setAttribute('aria-selected', String(Number(opt.dataset.index) === active)));
        const current = document.getElementById(`palette-opt-${active}`);
        if (current) { current.scrollIntoView({ block: 'nearest' }); input.setAttribute('aria-activedescendant', current.id); }
    }

    function run(index) {
        const item = results[index];
        if (!item) return;
        palette.close();
        if (item.href) window.location.href = CG.url(item.href);
        else setTimeout(item.run, 0);
    }

    function openLookup(code) {
        const pop = $('#pop-cek');
        const anchor = $$('[popovertarget="pop-cek"]').find(visible);
        if (!pop || !anchor) { window.location.href = CG.url('/cek-booking'); return; }
        pop.querySelector('[name="code"]').value = code;
        showPop(pop, anchor);
        pop.querySelector('[name="contact"]').focus();
    }

    function openPalette() {
        if (!palette) return;
        input.value = '';
        renderResults();
        CG.openDialog(palette);
        input.focus();
        CG.catalog().then((data) => { catalogData = data; if (palette.open) renderResults(); }).catch(() => { /* pencarian halaman tetap jalan */ });
    }
    CG.openPalette = openPalette;

    if (palette) {
        input.addEventListener('input', renderResults);
        input.addEventListener('keydown', (event) => {
            if (event.key === 'ArrowDown') { event.preventDefault(); select(active + 1); }
            else if (event.key === 'ArrowUp') { event.preventDefault(); select(active - 1); }
            else if (event.key === 'Enter') { event.preventDefault(); run(active); }
        });
        list.addEventListener('click', (event) => { const opt = event.target.closest('.palette-opt'); if (opt) run(Number(opt.dataset.index)); });
        list.addEventListener('mousemove', (event) => { const opt = event.target.closest('.palette-opt'); if (opt && Number(opt.dataset.index) !== active) select(Number(opt.dataset.index)); });
    }
    document.addEventListener('click', (event) => { if (event.target.closest('[data-palette-open]')) openPalette(); });
    document.addEventListener('keydown', (event) => {
        const el = document.activeElement;
        const typing = el && (/^(INPUT|TEXTAREA|SELECT)$/.test(el.tagName) || el.isContentEditable);
        if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') { event.preventDefault(); openPalette(); }
        else if (event.key === '/' && !typing && !$('dialog[open]')) { event.preventDefault(); openPalette(); }
        else if (event.key === 'Escape') CG.closeMenus();
    });
})();
