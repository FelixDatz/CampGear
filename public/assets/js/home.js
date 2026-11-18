/**
 * CampGear — interaksi beranda (vanilla JavaScript + Canvas 2D).
 *
 *   Hero      langit & gunung digambar di canvas. Matahari bisa digeser
 *             sepanjang busur dan mengatur tema (CampGear.sky di site.js).
 *             Nama brand berada di antara lapisan gunung belakang dan depan.
 *   Trip      kartu tanggal & rombongan; saat di-scroll ia "parkir" ke navbar.
 *   Tumpuk    tiap section naik menutupi section sebelumnya (position: sticky).
 *   Medan     grid kategori "bernapas": kartu yang disentuh membesar dua arah,
 *             kartu lain menyusut; isinya mengikuti stok di tanggal trip.
 *   Loadout   tab paket, hotspot alat, total harga sesuai tanggal trip.
 *   Jalur     garis tergambar mengikuti scroll; pos bisa di-hover dan diklik,
 *             halaman lalu scroll ke pos itu (dua arah).
 *   Tiket     contoh tiket ekspedisi; isinya mengikuti tanggal trip dan ransel,
 *             statusnya bisa dijalankan langkah demi langkah.
 *   Altimeter posisi scroll = ketinggian (mdpl), sekaligus navigasi section.
 *
 * Semua efek gerak mati bila pengguna memilih "reduce motion" atau di layar kecil.
 */
(() => {
    'use strict';

    const CG = window.CampGear;
    const hero = document.querySelector('[data-hero]');
    if (!hero || !CG.sky) return;

    const $ = (s, r = document) => r.querySelector(s);
    const $$ = (s, r = document) => Array.from(r.querySelectorAll(s));
    const clamp = (v, min, max) => Math.min(max, Math.max(min, v));
    const rupiah = (n) => 'Rp' + Math.round(n).toLocaleString('id-ID');
    const motion = CG.prefersMotion();

    const stack = $('[data-stack]');
    const scenes = $$('.stack > .scene');
    const jalur = $('[data-jalur]');
    const stackKids = [...scenes, jalur].filter(Boolean);
    const medan = $('#medan');
    let stackOn = false;
    let pinOn = false;
    let peek = 0;

    // ---- Smooth scroll (Lenis) -------------------------------------------------

    let lenis = null;
    function initLenis() {
        if (lenis || !motion || innerWidth < 992 || typeof window.Lenis !== 'function') return;
        try { lenis = new window.Lenis({ lerp: 0.09, smoothWheel: true }); } catch { lenis = null; return; }
        const loop = (time) => { if (!lenis) return; lenis.raf(time); requestAnimationFrame(loop); };
        requestAnimationFrame(loop);
    }
    document.addEventListener('campgear:dialog', (event) => {
        if (!lenis) return;
        if (event.detail.open) lenis.stop(); else lenis.start();
    });
    function scrollToY(y, duration = 1.1) {
        if (lenis) lenis.scrollTo(y, { duration });
        else window.scrollTo({ top: y, behavior: motion ? 'smooth' : 'auto' });
    }
    CG.scrollToY = scrollToY; // dipakai katalog.js agar scroll tetap lewat Lenis

    /** Posisi asli section di dokumen (section sticky tidak bisa diukur langsung). */
    function naturalTop(el) {
        if (!stackKids.includes(el)) return el.getBoundingClientRect().top + scrollY;
        let top = stack.getBoundingClientRect().top + scrollY;
        for (const kid of stackKids) {
            top += parseFloat(getComputedStyle(kid).marginTop) || 0;
            if (kid === el) return top;
            top += kid.offsetHeight;
        }
        return top;
    }
    function scrollToSection(el) {
        if (!el) return;
        let y = naturalTop(el);
        if (el === medan) y += peek; // section 2 lebih tinggi; isinya mulai setelah area intip
        scrollToY(Math.max(0, y));
    }
    document.addEventListener('click', (event) => {
        const go = event.target.closest('[data-go]');
        if (!go) return;
        event.preventDefault();
        scrollToSection(document.getElementById(go.dataset.go));
    });

    // ---- Hero: langit, gunung, matahari ----------------------------------------

    const skyCanvas = $('[data-sky]', hero);
    const backCanvas = $('[data-ridge="back"]', hero);
    const frontCanvas = $('[data-ridge="front"]', hero);
    const sun = $('[data-sun]', hero);
    const word = $('[data-hero-word]', hero);
    const BLEED = 24; // kanvas gunung sedikit lebih lebar agar parallax tidak memperlihatkan tepi
    const geo = { w: 0, h: 0, cx: 0, rx: 0, ry: 0, top: 0, horizon: 0, wordTop: 0, wordH: 0, wordW: 0, far: null, mid: null };

    // Warna langit & gunung pada beberapa titik hari; di antaranya diinterpolasi.
    const PALETTE = [
        { u: 0, top: '#dfe4ee', bot: '#f5c9a6', far: '#aaa3b5', mid: '#7e8a8d', near: '#25322b', glow: '#ffd2a1', stars: 0 },
        { u: 0.25, top: '#cfe3f1', bot: '#eef3ea', far: '#a8bdbf', mid: '#7b938c', near: '#203029', glow: '#fff1c9', stars: 0 },
        { u: 0.5, top: '#bcdcf0', bot: '#e5f1f2', far: '#9fb8b8', mid: '#6f8e88', near: '#1c2b24', glow: '#fff6d8', stars: 0 },
        { u: 0.75, top: '#e3daea', bot: '#f2b47f', far: '#b39297', mid: '#7f6e6e', near: '#241e23', glow: '#ffc27c', stars: 0 },
        { u: 0.86, top: '#cbb8d2', bot: '#eb9a62', far: '#9a7b88', mid: '#5d5460', near: '#1b1720', glow: '#ffae6a', stars: 0 },
        { u: 0.9, top: '#141a32', bot: '#383152', far: '#38405c', mid: '#222a41', near: '#0c1018', glow: '#c9d5ff', stars: 0.6 },
        { u: 1, top: '#060a14', bot: '#16203a', far: '#27324c', mid: '#172038', near: '#070b13', glow: '#d6dfff', stars: 1 },
    ];
    const hex = (h) => [parseInt(h.slice(1, 3), 16), parseInt(h.slice(3, 5), 16), parseInt(h.slice(5, 7), 16)];
    const mixRgb = (a, b, t) => hex(a).map((v, i) => Math.round(v + (hex(b)[i] - v) * t));
    const rgb = (arr, alpha = 1) => `rgba(${arr[0]},${arr[1]},${arr[2]},${alpha})`;
    function paletteAt(u) {
        let i = PALETTE.findIndex((p) => p.u >= u);
        if (i <= 0) i = 1;
        const a = PALETTE[i - 1];
        const b = PALETTE[i];
        const t = clamp((u - a.u) / (b.u - a.u || 1), 0, 1);
        const out = { stars: a.stars + (b.stars - a.stars) * t };
        ['top', 'bot', 'far', 'mid', 'near', 'glow'].forEach((k) => { out[k] = mixRgb(a[k], b[k], t); });
        return out;
    }

    // Bentuk punggungan dibuat dari gelombang sinus dengan fase acak tetap (seed).
    let seed = 20261118;
    const random = () => { seed = (seed * 16807) % 2147483647; return (seed - 1) / 2147483646; };
    const PHASES = Array.from({ length: 3 }, () => [random() * 6.28, random() * 6.28, random() * 6.28]);
    const STARS = Array.from({ length: 150 }, () => [random(), random() * 0.55, 0.4 + random() * 1.1, 0.3 + random() * 0.7]);
    const TREES = Array.from({ length: 70 }, () => [random(), 0.55 + random() * 0.6]);

    let shownU = CG.sky.u; // posisi yang sedang digambar (bisa beranimasi saat intro)
    let pointer = { x: 0, y: 0, tx: 0, ty: 0 };

    function measure() {
        geo.w = hero.offsetWidth;
        geo.h = hero.offsetHeight;
        const stage = word.offsetParent;
        geo.wordTop = (stage ? stage.offsetTop : 0) + word.offsetTop;
        geo.wordH = word.offsetHeight;
        geo.wordW = word.offsetWidth;
        geo.far = { crest: geo.wordTop + geo.wordH * 0.28, amp: geo.wordH * 0.62 };
        geo.mid = { crest: geo.wordTop + geo.wordH * 0.62, amp: geo.wordH * 0.42 };
        const navBottom = innerWidth < 992 ? 64 : 76;
        geo.top = Math.max(navBottom + 30, geo.wordTop - Math.max(90, geo.h * 0.24));
        geo.cx = geo.w / 2;
        geo.rx = Math.min(geo.w * 0.42, 620);

        // Ujung busur diletakkan tepat di atas punggungan gunung di titik itu,
        // supaya matahari/bulan selalu terlihat dan bisa diraih.
        const width = geo.w + BLEED * 2;
        const s = Math.sin(Math.PI * ARC_START);
        let yEnd = Infinity;
        [-1, 1].forEach((side) => {
            const x = geo.cx + side * geo.rx * Math.cos(Math.PI * ARC_START) + BLEED;
            for (let dx = -30; dx <= 30; dx += 10) {
                yEnd = Math.min(yEnd, ridgeY(x + dx, 0, geo.far.crest, geo.far.amp, width), ridgeY(x + dx, 1, geo.mid.crest, geo.mid.amp, width));
            }
        });
        yEnd -= 34;
        geo.horizon = Math.max(geo.top + 60, (yEnd - geo.top * s) / (1 - s));
        geo.ry = geo.horizon - geo.top;
    }

    function sizeCanvas(canvas, width, height, maxDpr) {
        const dpr = Math.min(maxDpr, window.devicePixelRatio || 1);
        const W = Math.round(width * dpr);
        const H = Math.round(height * dpr);
        if (canvas.width !== W || canvas.height !== H) { canvas.width = W; canvas.height = H; }
        const ctx = canvas.getContext('2d');
        ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
        ctx.clearRect(0, 0, width, height);
        return ctx;
    }

    // Busur dipakai dari 8% sampai 92%: di ujung, matahari/bulan rendah dekat punggungan
    // tetapi tetap terlihat sehingga selalu bisa diraih.
    const ARC_START = 0.08;
    const ARC_SPAN = 0.84;
    const sunPoint = (u) => {
        const angle = Math.PI * (ARC_START + ARC_SPAN * u);
        return { x: geo.cx - geo.rx * Math.cos(angle), y: geo.horizon - geo.ry * Math.sin(angle) };
    };

    /** Garis punggungan: y lebih kecil = lebih tinggi. */
    function ridgeY(x, layer, crest, amp, w) {
        const [a, b, c] = PHASES[layer];
        const t = x / w;
        const k1 = 2 + layer * 0.9;
        return crest - amp * (0.62 * (1 - Math.abs(Math.sin(t * k1 * Math.PI + a))) + 0.28 * Math.sin(t * (5 + layer * 2) + b) + 0.1 * Math.sin(t * (13 + layer * 3) + c));
    }

    function drawSky() {
        const { w, h } = geo;
        const ctx = sizeCanvas(skyCanvas, w, h, 2);
        const P = paletteAt(shownU);
        const g = ctx.createLinearGradient(0, 0, 0, geo.horizon + geo.wordH * 0.4);
        g.addColorStop(0, rgb(P.top));
        g.addColorStop(1, rgb(P.bot));
        ctx.fillStyle = g;
        ctx.fillRect(0, 0, w, h);

        if (P.stars > 0.01) {
            ctx.fillStyle = '#f4f1e8';
            for (const [sx, sy, r, a] of STARS) {
                ctx.globalAlpha = a * P.stars;
                ctx.beginPath();
                ctx.arc(sx * w - pointer.x * 4, sy * h - pointer.y * 3, r, 0, Math.PI * 2);
                ctx.fill();
            }
            ctx.globalAlpha = 1;
        }

        // Lintasan matahari: garis putus-putus samar sebagai petunjuk bisa digeser.
        ctx.save();
        ctx.setLineDash([2, 10]);
        ctx.lineWidth = 1.5;
        ctx.strokeStyle = shownU >= CG.sky.NIGHT ? 'rgba(236,230,218,.28)' : 'rgba(21,32,26,.22)';
        ctx.beginPath();
        for (let i = 0; i <= 64; i++) { const p = sunPoint(i / 64); if (i === 0) ctx.moveTo(p.x, p.y); else ctx.lineTo(p.x, p.y); }
        ctx.stroke();
        ctx.restore();

        const s = sunPoint(shownU);
        const glow = ctx.createRadialGradient(s.x, s.y, 0, s.x, s.y, 220);
        glow.addColorStop(0, rgb(P.glow, shownU >= CG.sky.NIGHT ? 0.22 : 0.55));
        glow.addColorStop(1, rgb(P.glow, 0));
        ctx.fillStyle = glow;
        ctx.fillRect(0, 0, w, h);
    }

    function drawRidges() {
        const w = geo.w + BLEED * 2;
        const { h } = geo;
        const P = paletteAt(shownU);
        const night = shownU >= CG.sky.NIGHT;
        const { wordTop, wordH } = geo;

        // Belakang: dua lapis (jauh & tengah) dengan kabut di antaranya.
        let ctx = sizeCanvas(backCanvas, w, h, 1.5);
        const layers = [
            { layer: 0, crest: geo.far.crest, amp: geo.far.amp, color: P.far },
            { layer: 1, crest: geo.mid.crest, amp: geo.mid.amp, color: P.mid },
        ];
        layers.forEach(({ layer, crest, amp, color }, i) => {
            ctx.fillStyle = rgb(color);
            ctx.beginPath();
            ctx.moveTo(0, h);
            for (let x = 0; x <= w; x += 6) ctx.lineTo(x, ridgeY(x, layer, crest, amp, w));
            ctx.lineTo(w, h);
            ctx.closePath();
            ctx.fill();
            if (i === 0 && !night) {
                const mist = ctx.createLinearGradient(0, crest - amp * 0.2, 0, crest + wordH * 0.5);
                mist.addColorStop(0, 'rgba(255,255,255,0)');
                mist.addColorStop(0.6, 'rgba(255,255,255,0.22)');
                mist.addColorStop(1, 'rgba(255,255,255,0)');
                ctx.fillStyle = mist;
                ctx.fillRect(0, crest - amp * 0.2, w, wordH * 0.9);
            }
        });

        // Depan: punggungan dekat + pohon cemara, menutup bagian bawah huruf.
        ctx = sizeCanvas(frontCanvas, w, h, 1.5);
        const crest = wordTop + wordH * 0.9;
        const amp = wordH * 0.1;
        ctx.fillStyle = rgb(P.near);
        ctx.beginPath();
        ctx.moveTo(0, h);
        for (let x = 0; x <= w; x += 6) ctx.lineTo(x, ridgeY(x, 2, crest, amp, w));
        ctx.lineTo(w, h);
        ctx.closePath();
        ctx.fill();
        const center = w / 2;
        const keepClear = geo.wordW / 2 + 12; // di depan nama brand pohonnya jarang dan kecil agar tetap terbaca
        TREES.forEach(([t, size], i) => {
            const x = t * w;
            const near = Math.abs(x - center) < keepClear;
            if (near && i % 2 === 0) return;
            const scale = (near ? 0.32 : 1) * size;
            const treeH = wordH * 0.3 * scale;
            const base = ridgeY(x, 2, crest, amp, w) + 2;
            for (let k = 0; k < 3; k++) {
                const bw = treeH * (0.5 - k * 0.11);
                const top = base - treeH * (0.45 + k * 0.28);
                ctx.beginPath();
                ctx.moveTo(x, top);
                ctx.lineTo(x - bw / 2, base - treeH * k * 0.26);
                ctx.lineTo(x + bw / 2, base - treeH * k * 0.26);
                ctx.closePath();
                ctx.fill();
            }
        });
        if (night) {
            // Cahaya api unggun samar di kaki gunung.
            const fire = ctx.createRadialGradient(center, h, 0, center, h, w * 0.45);
            fire.addColorStop(0, 'rgba(240,150,70,.30)');
            fire.addColorStop(1, 'rgba(240,150,70,0)');
            ctx.fillStyle = fire;
            ctx.fillRect(0, crest - amp, w, h);
        }
    }

    function placeSun() {
        const p = sunPoint(shownU);
        sun.style.transform = `translate(${p.x}px, ${p.y}px)`;
        sun.classList.toggle('is-moon', shownU >= CG.sky.NIGHT);
        const value = Math.round(CG.sky.u * 100);
        sun.setAttribute('aria-valuenow', String(value));
        const time = CG.sky.time();
        sun.setAttribute('aria-valuetext', `${CG.sky.label()}${time === 'malam' ? '' : `, sekitar pukul ${time}`} · tema ${CG.sky.theme() === 'dark' ? 'gelap' : 'terang'}`);
    }

    let drawQueued = false;
    function drawHero() {
        if (drawQueued) return;
        drawQueued = true;
        requestAnimationFrame(() => {
            drawQueued = false;
            drawSky();
            drawRidges();
            placeSun();
        });
    }

    document.addEventListener('campgear:sky', () => { if (!introRunning) shownU = CG.sky.u; drawHero(); });

    // Geser matahari (mouse, sentuh) dan keyboard.
    function uFromPointer(event) {
        const r = hero.getBoundingClientRect();
        const px = (event.clientX - r.left) * (geo.w / r.width);
        const py = (event.clientY - r.top) * (geo.h / r.height);
        const dx = (px - geo.cx) / geo.rx;
        const dy = (geo.horizon - py) / geo.ry;
        if (dy <= 0) return dx < 0 ? 0 : 1;
        return clamp((Math.atan2(dy, -dx) / Math.PI - ARC_START) / ARC_SPAN, 0, 1);
    }
    let dragging = false;
    sun.addEventListener('pointerdown', (event) => {
        dragging = true;
        stopIntro();
        hideHint(true);
        sun.setPointerCapture(event.pointerId);
        event.preventDefault();
    });
    sun.addEventListener('pointermove', (event) => {
        if (!dragging) return;
        CG.sky.set(uFromPointer(event), { persist: false });
    });
    const endDrag = () => { if (!dragging) return; dragging = false; CG.sky.set(CG.sky.u); };
    sun.addEventListener('pointerup', endDrag);
    sun.addEventListener('pointercancel', endDrag);
    sun.addEventListener('keydown', (event) => {
        const steps = { ArrowLeft: -0.04, ArrowDown: -0.04, ArrowRight: 0.04, ArrowUp: 0.04, PageDown: -0.2, PageUp: 0.2 };
        let u = null;
        if (event.key in steps) u = CG.sky.u + steps[event.key];
        else if (event.key === 'Home') u = 0;
        else if (event.key === 'End') u = 1;
        if (u === null) return;
        event.preventDefault();
        stopIntro();
        hideHint(true);
        CG.sky.set(u);
    });

    // Intro: matahari terbit dari kiri bawah ke posisi jam sekarang (sekali per sesi, siang saja).
    let introRunning = false;
    let introFrame = 0;
    function stopIntro() { if (introRunning) { cancelAnimationFrame(introFrame); introRunning = false; shownU = CG.sky.u; drawHero(); } }
    function playIntro() {
        let seen = false;
        try { seen = sessionStorage.getItem('cg-sunrise') === '1'; sessionStorage.setItem('cg-sunrise', '1'); } catch { seen = true; }
        if (seen || !motion || CG.sky.u >= CG.sky.NIGHT) { showHint(); return; }
        introRunning = true;
        const target = CG.sky.u;
        const start = performance.now();
        const step = (now) => {
            const k = Math.min(1, (now - start) / 1700);
            shownU = target * (1 - Math.pow(1 - k, 3));
            drawSky();
            drawRidges();
            const p = sunPoint(shownU);
            sun.style.transform = `translate(${p.x}px, ${p.y}px)`;
            if (k < 1) introFrame = requestAnimationFrame(step);
            else { introRunning = false; shownU = target; placeSun(); showHint(); }
        };
        introFrame = requestAnimationFrame(step);
    }

    let hintEl = null;
    let hintTimer = 0;
    function showHint() {
        if (CG.store.get('cg-sun-hint')) return;
        const p = sunPoint(shownU);
        hintEl = document.createElement('p');
        hintEl.className = 'sun-hint';
        hintEl.textContent = 'Geser matahari untuk mengganti tema';
        hintEl.style.left = `${clamp(p.x, 140, geo.w - 140)}px`;
        hintEl.style.top = `${p.y + 38}px`;
        hero.appendChild(hintEl);
        hintTimer = setTimeout(() => hideHint(true), 7000);
    }
    function hideHint(remember) {
        clearTimeout(hintTimer);
        hintEl?.remove();
        hintEl = null;
        if (remember) CG.store.set('cg-sun-hint', '1');
    }

    // Parallax ringan mengikuti kursor.
    let pointerFrame = 0;
    hero.addEventListener('pointermove', (event) => {
        if (!motion || event.pointerType !== 'mouse' || dragging) return;
        const r = hero.getBoundingClientRect();
        pointer.tx = ((event.clientX - r.left) / r.width - 0.5) * 2;
        pointer.ty = ((event.clientY - r.top) / r.height - 0.5) * 2;
        if (!pointerFrame) pointerFrame = requestAnimationFrame(easePointer);
    });
    function easePointer() {
        pointer.x += (pointer.tx - pointer.x) * 0.08;
        pointer.y += (pointer.ty - pointer.y) * 0.08;
        applyParallax();
        pointerFrame = Math.abs(pointer.tx - pointer.x) > 0.002 || Math.abs(pointer.ty - pointer.y) > 0.002 ? requestAnimationFrame(easePointer) : 0;
    }
    let heroCover = 0;
    function applyParallax() {
        if (!motion) return;
        const vh = innerHeight;
        backCanvas.style.transform = `translate3d(${-BLEED - pointer.x * 6}px, ${-heroCover * vh * 0.03}px, 0)`;
        frontCanvas.style.transform = `translate3d(${-BLEED - pointer.x * 14}px, ${-heroCover * vh * 0.12}px, 0)`;
        word.style.transform = `translate3d(${-pointer.x * 3}px, ${-heroCover * vh * 0.03}px, 0)`;
    }

    // ---- Trip card --------------------------------------------------------------

    const tripCard = $('[data-trip-card]');
    const tripResult = $('[data-trip-result]');
    const peopleOut = $('[data-people-out]');
    const chip = $('[data-trip-chip]');

    function renderPeople() {
        peopleOut.textContent = `${CG.trip.people} orang`;
        $$('[data-people]').forEach((btn) => {
            const d = Number(btn.dataset.people);
            btn.disabled = (d < 0 && CG.trip.people <= 1) || (d > 0 && CG.trip.people >= 20);
        });
    }
    $$('[data-people]').forEach((btn) => btn.addEventListener('click', () => {
        CG.trip.setPeople(CG.trip.people + Number(btn.dataset.people));
        renderPeople();
    }));

    tripCard.addEventListener('submit', (event) => {
        event.preventDefault();
        if (!CG.trip.hasDates()) CG.trip.open($('[data-trip-open]', tripCard), checkTrip);
        else checkTrip();
    });
    // Tombol tanggal di kartu: setelah memilih, langsung cek stok.
    $('[data-trip-open]', tripCard).addEventListener('click', (event) => {
        event.stopPropagation();
        CG.trip.open(event.currentTarget, checkTrip);
    });

    async function checkTrip() {
        tripResult.hidden = false;
        tripResult.textContent = 'Mengecek stok…';
        try {
            const [stock, catalog] = await Promise.all([CG.trip.availability(), CG.catalog()]);
            const names = new Map(catalog.equipment.map((e) => [String(e.id), e.name]));
            const ids = Object.keys(stock.equipment).filter((id) => names.has(id));
            const out = ids.filter((id) => Number(stock.equipment[id]) <= 0).map((id) => names.get(id));
            const escape = (t) => t.replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
            tripResult.innerHTML = `<strong>${ids.length - out.length} dari ${ids.length} alat tersedia</strong> untuk ${CG.trip.range()} (${CG.trip.days()} hari).`
                + (out.length ? ` Habis: ${out.map(escape).join(', ')}.` : '')
                + ` <button type="button" class="btn btn-link" data-go="loadout">Lihat paket untuk ${CG.trip.people} orang</button>`
                + ` · <button type="button" class="btn btn-link" data-go="katalog">Pilih alat satuan</button>`;
            suggestPackage();
        } catch (error) {
            tripResult.textContent = error.status === 422 ? error.message : 'Gagal mengecek stok. Periksa koneksi lalu coba lagi.';
        }
    }
    document.addEventListener('campgear:trip', () => {
        updatePackageTotals();
        renderMedanMeta();
        fillTicket();
        if (!tripResult.hidden && CG.trip.hasDates()) checkTrip();
    });

    /** Kartu trip yang naik bersama section 2 berubah menjadi chip di navbar. */
    function dock() {
        const docked = tripCard.getBoundingClientRect().top < (innerWidth < 992 ? 66 : 82);
        tripCard.classList.toggle('is-docked', docked);
        if (chip) chip.style.visibility = docked ? '' : 'hidden';
    }

    // ---- Section 2: grid bernapas ----------------------------------------------
    //
    // Kartu yang dibuka mendapat kolom 2fr dan baris 3fr, kartu lain .5fr dan 1fr.
    // Perbandingan ini membuat kartu yang membesar selalu menutupi posisi asalnya,
    // jadi kursor tidak pernah "jatuh" ke kartu lain di tengah animasi.
    // Mouse: hover membuka (dengan jeda singkat), keluar dari grid menutup.
    // Klik/sentuh/Enter: membuka dan menahan kartu; klik lagi atau Esc menutup.

    const medanGrid = $('[data-medan]');
    const medanCards = medanGrid ? $$('[data-medan-card]', medanGrid) : [];
    const COLS = Math.max(1, Math.min(3, medanCards.length));
    const ROWS = Math.max(1, Math.ceil(medanCards.length / COLS));
    const breathing = () => innerWidth >= 992; // di layar kecil kartu cukup melebar ke bawah
    let openCard = null;
    let pinned = false;
    let medanTimer = 0;

    function layoutMedan() {
        if (!medanGrid) return;
        if (!breathing()) {
            medanGrid.style.gridTemplateColumns = '';
            medanGrid.style.gridTemplateRows = '';
            return;
        }
        const i = openCard ? medanCards.indexOf(openCard) : -1;
        const col = i % COLS;
        const row = Math.floor(i / COLS);
        medanGrid.style.gridTemplateColumns = Array.from({ length: COLS }, (_, k) => (i < 0 ? '1fr' : k === col ? '2fr' : '.5fr')).join(' ');
        medanGrid.style.gridTemplateRows = Array.from({ length: ROWS }, (_, k) => (i < 0 || ROWS === 1 ? '1fr' : k === row ? '3fr' : '1fr')).join(' ');
    }

    /** Lebar akhir kartu terbuka; isi detail memakai lebar ini sejak awal animasi. */
    function measureMedan() {
        if (!medanGrid || !breathing()) return;
        const gap = parseFloat(getComputedStyle(medanGrid).columnGap) || 0;
        const width = (medanGrid.clientWidth - gap * (COLS - 1)) * (2 / (2 + 0.5 * (COLS - 1)));
        medanGrid.style.setProperty('--open-w', `${Math.floor(width)}px`);
    }

    function setMedan(card, pin = false) {
        clearTimeout(medanTimer);
        pinned = card ? pin : false;
        if (card === openCard) return;
        if (openCard) {
            openCard.classList.remove('is-open');
            $('.medan-face', openCard).setAttribute('aria-expanded', 'false');
        }
        openCard = card;
        if (card) {
            card.classList.add('is-open');
            $('.medan-face', card).setAttribute('aria-expanded', 'true');
        }
        medanGrid.classList.toggle('has-open', !!card);
        layoutMedan();
        // Di HP kartu yang dibuka pindah ke baris penuh; pastikan tetap terlihat.
        if (card && !breathing()) requestAnimationFrame(() => card.scrollIntoView({ block: 'nearest', behavior: motion ? 'smooth' : 'auto' }));
    }

    medanCards.forEach((card) => {
        card.addEventListener('pointerenter', (event) => {
            if (event.pointerType !== 'mouse' || !breathing()) return;
            clearTimeout(medanTimer);
            if (card !== openCard) medanTimer = setTimeout(() => setMedan(card), openCard ? 60 : 110);
        });
        $('.medan-face', card).addEventListener('click', () => {
            if (card === openCard && pinned) setMedan(null);
            else setMedan(card, true);
        });
    });
    medanGrid?.addEventListener('pointerleave', (event) => {
        if (event.pointerType !== 'mouse') return;
        clearTimeout(medanTimer);
        if (!pinned) medanTimer = setTimeout(() => setMedan(null), 260);
    });
    document.addEventListener('click', (event) => {
        if (openCard && pinned && !medanGrid.contains(event.target) && !event.target.closest('dialog, [popover]')) setMedan(null);
    });
    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape' || !openCard || !medanGrid.contains(document.activeElement)) return;
        const face = $('.medan-face', openCard);
        setMedan(null);
        face.focus();
    });

    /** Ringkasan per kategori: "3 dari 3 alat tersedia" untuk tanggal trip. */
    async function renderMedanMeta() {
        const metas = $$('[data-medan-meta]');
        const reset = () => metas.forEach((m) => { m.textContent = m.dataset.base; m.classList.remove('is-low', 'is-out'); });
        if (!CG.trip.hasDates()) { reset(); return; }
        try {
            const data = await CG.trip.availability();
            metas.forEach((m) => {
                const ids = m.dataset.ids ? m.dataset.ids.split(',') : [];
                if (ids.length === 0) return;
                const free = ids.filter((id) => Number(data.equipment[id] ?? 0) > 0).length;
                m.textContent = `${free} dari ${ids.length} alat tersedia`;
                m.classList.toggle('is-out', free === 0);
                m.classList.toggle('is-low', free > 0 && free < ids.length);
            });
        } catch {
            reset();
        }
    }

    // "Ada di paket" membuka tab paket di section 3; "Lihat N alat" menyaring katalog di beranda.
    document.addEventListener('click', (event) => {
        if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey) return;
        const pkg = event.target.closest('[data-go-pkg]');
        if (pkg && tabs.some((tab) => tab.dataset.pkgTab === pkg.dataset.goPkg)) {
            event.preventDefault();
            pkg.closest('dialog')?.close();
            pickedByUser = true;
            selectPackage(pkg.dataset.goPkg);
            scrollToSection(document.getElementById('loadout'));
            return;
        }
        const cat = event.target.closest('[data-katalog-cat]');
        if (cat && CG.katalog) {
            event.preventDefault();
            CG.katalog.filter(cat.dataset.katalogCat, { animate: false });
            scrollToSection(document.getElementById('katalog'));
        }
    });

    // ---- Loadout ---------------------------------------------------------------

    const tabs = $$('[data-pkg-tab]');
    let pickedByUser = false;
    function selectPackage(id, focusTab = false) {
        tabs.forEach((tab) => {
            const on = tab.dataset.pkgTab === id;
            tab.setAttribute('aria-selected', String(on));
            tab.tabIndex = on ? 0 : -1;
            if (on && focusTab) tab.focus();
        });
        $$('[data-pkg-panel]').forEach((panel) => { panel.hidden = panel.dataset.pkgPanel !== id; });
        $$('[data-board]').forEach((board) => {
            const on = board.dataset.board === id;
            if (on && board.hidden) {
                board.hidden = false;
                if (motion) $$('.obj', board).forEach((obj, i) => obj.animate(
                    [{ opacity: 0, transform: 'translateY(14px) scale(.94)' }, { opacity: 1, transform: 'none' }],
                    { duration: 460, delay: i * 45, easing: 'cubic-bezier(.2,.8,.2,1)', fill: 'backwards' },
                ));
            } else if (!on) {
                board.hidden = true;
            }
        });
        placeTips();
    }
    tabs.forEach((tab, i) => {
        tab.addEventListener('click', () => { pickedByUser = true; selectPackage(tab.dataset.pkgTab); });
        tab.addEventListener('keydown', (event) => {
            const d = { ArrowRight: 1, ArrowLeft: -1 }[event.key];
            if (!d) return;
            event.preventDefault();
            pickedByUser = true;
            selectPackage(tabs[(i + d + tabs.length) % tabs.length].dataset.pkgTab, true);
        });
    });
    /** Saran paket: jumlah sleeping bag paling pas dengan jumlah orang. */
    function suggestPackage() {
        if (tabs.length === 0) return;
        const people = CG.trip.people;
        const withSleepers = tabs.filter((t) => Number(t.dataset.sleepers) > 0);
        if (withSleepers.length === 0) return;
        const fits = withSleepers.filter((t) => Number(t.dataset.sleepers) >= people)
            .sort((a, b) => Number(a.dataset.sleepers) - Number(b.dataset.sleepers));
        const best = fits[0] || withSleepers.sort((a, b) => Number(b.dataset.sleepers) - Number(a.dataset.sleepers))[0];
        tabs.forEach((t) => {
            const hint = $(`[data-pkg-panel="${t.dataset.pkgTab}"] [data-pkg-hint]`);
            if (!hint) return;
            hint.dataset.base ??= hint.textContent;
            hint.textContent = hint.dataset.base + (t === best ? ` Cocok untuk rombonganmu (${people} orang).` : '');
        });
        if (!pickedByUser) selectPackage(best.dataset.pkgTab);
    }
    function updatePackageTotals() {
        $$('[data-pkg-panel]').forEach((panel) => {
            const row = $('[data-pkg-total]', panel);
            if (!row) return;
            row.hidden = !CG.trip.hasDates();
            if (!CG.trip.hasDates()) return;
            $('[data-pkg-total-label]', panel).textContent = `${CG.trip.days()} hari · ${CG.trip.range()}`;
            $('[data-pkg-total-value]', panel).textContent = rupiah(Number(panel.dataset.price) * CG.trip.days());
        });
    }
    function placeTips() {
        $$('[data-board]:not([hidden]) .obj').forEach((obj) => obj.classList.toggle('tip-below', obj.offsetTop < 60));
    }
    document.addEventListener('click', (event) => {
        const hot = event.target.closest('.hot');
        $$('.obj.is-open').forEach((obj) => {
            if (!hot || obj !== hot.parentElement) { obj.classList.remove('is-open'); obj.querySelector('.hot').setAttribute('aria-expanded', 'false'); }
        });
        if (hot) {
            const open = hot.parentElement.classList.toggle('is-open');
            hot.setAttribute('aria-expanded', String(open));
        }
    });

    // ---- Jalur sewa ------------------------------------------------------------

    const trail = $('[data-trail]');
    const base = $('[data-trail-base]');
    const prog = $('[data-trail-prog]');
    const hiker = $('[data-hiker]');
    const marks = $$('.trail-mark');
    const cards = $$('.pos-card');
    const tip = $('[data-trail-tip]');
    const length = base ? base.getTotalLength() : 0;
    if (prog) prog.style.strokeDasharray = `${length} ${length}`;

    /** Posisi tiap pos di sepanjang garis (0–1), dicari dari titik terdekat. */
    const stops = marks.map((mark) => {
        const m = mark.transform.baseVal.consolidate().matrix;
        let best = 0;
        let bestDistance = Infinity;
        for (let i = 0; i <= 500; i++) {
            const p = base.getPointAtLength((length * i) / 500);
            const d = (p.x - m.e) ** 2 + (p.y - m.f) ** 2;
            if (d < bestDistance) { bestDistance = d; best = i / 500; }
        }
        return best;
    });
    const toTrail = (p) => clamp((p - 0.03) / 0.94, 0, 1);
    const toProgress = (f) => f * 0.94 + 0.03;

    function setTrail(f) {
        if (!prog) return;
        prog.style.strokeDashoffset = String(length * (1 - f));
        const p = base.getPointAtLength(length * f);
        hiker.setAttribute('cx', p.x.toFixed(1));
        hiker.setAttribute('cy', p.y.toFixed(1));
        let active = -1;
        stops.forEach((s, i) => { if (f >= s - 0.012) active = i; });
        marks.forEach((m, i) => m.classList.toggle('is-on', i <= active));
        cards.forEach((c, i) => { c.classList.toggle('is-on', i <= active); c.classList.toggle('is-active', i === active); });
    }

    function jalurProgress() {
        const r = jalur.getBoundingClientRect();
        const total = r.height - innerHeight;
        return { p: total > 0 ? clamp(-r.top / total, 0, 1) : 0, total };
    }

    /** Pergi ke pos i: halaman scroll ke posisi pos tersebut, garis ikut tergambar. */
    function travelTo(i) {
        if (!pinOn) {
            cards.forEach((c, k) => c.classList.toggle('is-active', k === i));
            cards[i]?.scrollIntoView({ behavior: motion ? 'smooth' : 'auto', block: 'center' });
            return;
        }
        const { p, total } = jalurProgress();
        const target = toProgress(stops[i]);
        const top = naturalTop(jalur);
        scrollToY(top + target * total, clamp(Math.abs(target - p) * 2.6, 0.6, 1.6));
    }

    function showTip(mark) {
        if (!tip) return;
        tip.innerHTML = '';
        const title = document.createElement('b');
        title.textContent = mark.dataset.title;
        const key = document.createElement('span');
        key.className = 'key';
        key.textContent = mark.dataset.key;
        const line = document.createElement('p');
        line.textContent = mark.dataset.line;
        tip.append(title, key, line);
        const tr = trail.getBoundingClientRect();
        const mr = mark.getBoundingClientRect();
        tip.style.left = `${clamp(mr.left + mr.width / 2 - tr.left, 120, tr.width - 120)}px`;
        tip.style.top = `${mr.top - tr.top}px`;
        tip.hidden = false;
    }
    const hideTip = () => { if (tip) tip.hidden = true; };

    marks.forEach((mark, i) => {
        mark.addEventListener('mouseenter', () => showTip(mark));
        mark.addEventListener('mouseleave', hideTip);
        mark.addEventListener('focus', () => showTip(mark));
        mark.addEventListener('blur', hideTip);
        mark.addEventListener('click', () => travelTo(i));
        mark.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); travelTo(i); }
        });
    });
    cards.forEach((card, i) => {
        card.addEventListener('click', () => travelTo(i));
        card.addEventListener('mouseenter', () => marks[i]?.classList.add('is-hover'));
        card.addEventListener('mouseleave', () => marks[i]?.classList.remove('is-hover'));
    });

    // Saat scroll berhenti di dekat sebuah pos, halaman ditarik lembut ke pos itu.
    let pointerIsDown = false;
    let snapTimer = 0;
    addEventListener('pointerdown', () => { pointerIsDown = true; }, { passive: true });
    addEventListener('pointerup', () => { pointerIsDown = false; }, { passive: true });
    function snapToStop() {
        if (!pinOn || pointerIsDown) return;
        const { p, total } = jalurProgress();
        if (p <= 0.01 || p >= 0.99) return;
        let best = null;
        let distance = 1;
        stops.forEach((s) => { const d = Math.abs(toProgress(s) - p); if (d < distance) { distance = d; best = toProgress(s); } });
        if (best !== null && distance > 0.006 && distance < 0.09) scrollToY(scrollY + (best - p) * total, 0.7);
    }

    // ---- Tiket contoh -----------------------------------------------------------

    const demo = $('[data-tiket-demo]');
    const demoTicket = demo ? $('[data-ticket]', demo) : null;
    const demoSteps = demo ? $$('[data-demo-trail] li', demo) : [];
    const demoActions = demo ? $$('[data-demo-pay], [data-demo-next], [data-demo-reset]', demo) : [];
    const field = (key) => $(`[data-t="${key}"]`, demoTicket);
    let demoStep = 0;
    let demoDeadline = 0;

    /** Tanggal tiket: tanggal trip, atau contoh Sabtu terdekat (minimal 3 hari lagi) sampai Senin. */
    function demoDates() {
        if (CG.trip.hasDates()) return [CG.trip.start, CG.trip.end];
        const today = new Date();
        let start = new Date(today.getFullYear(), today.getMonth(), today.getDate() + 3);
        while (start.getDay() !== 6) start = new Date(start.getFullYear(), start.getMonth(), start.getDate() + 1);
        return [start, new Date(start.getFullYear(), start.getMonth(), start.getDate() + 2)];
    }

    function renderDemoBody() {
        const [start, end] = demoDates();
        $('[data-demo-body]', demo).textContent = demoSteps[demoStep].dataset.body
            .replace('{ambil}', CG.trip.parts(start).long)
            .replace('{kembali}', CG.trip.parts(end).long);
    }

    /** Isi tiket mengikuti ransel; bila ransel kosong, memakai paket contoh dari server. */
    async function fillTicket() {
        if (!demoTicket) return;
        const [start, end] = demoDates();
        const days = CG.trip.daysBetween(start, end);
        const a = CG.trip.parts(start);
        const b = CG.trip.parts(end);
        field('start').textContent = a.date;
        field('startSub').textContent = `${a.day} ${a.year}`;
        field('end').textContent = b.date;
        field('endSub').textContent = `${b.day} · paling lambat`;
        field('days').textContent = `${days} hari`;
        let isi = demo.dataset.sampleName || '–';
        let perDay = Number(demo.dataset.samplePrice) || 0;
        if (CG.ransel.count() > 0) {
            try {
                const bag = CG.ransel.contents();
                const data = await CG.catalog();
                const packs = new Map(data.packages.map((p) => [String(p.id), p]));
                const gear = new Map(data.equipment.map((e) => [String(e.id), e]));
                const names = [];
                let units = 0;
                let sum = 0;
                Object.entries(bag.package).forEach(([id, n]) => {
                    const p = packs.get(id);
                    if (p) { names.push(n > 1 ? `${p.name} ×${n}` : p.name); sum += p.daily_price * n; }
                });
                Object.entries(bag.item).forEach(([id, n]) => {
                    const e = gear.get(id);
                    if (e) { units += n; sum += e.rate * n; }
                });
                if (units > 0) names.push(`${units} alat`);
                if (names.length > 0) { isi = names.join(' + '); perDay = sum; }
            } catch { /* tetap memakai paket contoh */ }
        }
        field('isi').textContent = isi;
        field('total').textContent = perDay > 0 ? rupiah(perDay * days) : '–';
        renderDemoBody();
    }

    function setDemoStep(i, animate = true) {
        demoStep = i;
        const step = demoSteps[i];
        demoSteps.forEach((li, k) => {
            li.classList.toggle('is-done', k < i);
            li.classList.toggle('is-now', k === i);
            if (k === i) li.setAttribute('aria-current', 'step'); else li.removeAttribute('aria-current');
        });
        $('[data-demo-trail]', demo).style.setProperty('--prog', String(i / (demoSteps.length - 1)));
        const stamp = field('stamp');
        stamp.textContent = step.dataset.stamp;
        stamp.dataset.tone = step.dataset.tone;
        if (animate && motion) {
            stamp.animate([{ scale: '1.7', opacity: 0 }, { scale: '1', opacity: 1 }], { duration: 420, easing: 'cubic-bezier(.3,1.4,.5,1)' });
        }
        $('[data-demo-now]', demo).dataset.tone = step.dataset.tone;
        $('[data-demo-title]', demo).textContent = $('.st-label', step).textContent;
        renderDemoBody();
        $('[data-demo-countdown]', demo).hidden = i !== 0;
        demoTicket.classList.toggle('is-locked', i === 0);
        $('[data-demo-pay]', demo).hidden = i !== 0;
        $('[data-demo-next]', demo).hidden = i === 0 || i === demoSteps.length - 1;
        $('[data-demo-reset]', demo).hidden = i !== demoSteps.length - 1;
        if (i === 0) demoDeadline = Date.now() + (Number(demo.dataset.hours) || 24) * 3600e3 - 47e3;
        tickDemo();
    }

    function tickDemo() {
        if (!demo || demoStep !== 0) return;
        const pad = (n) => String(n).padStart(2, '0');
        const left = Math.max(0, Math.round((demoDeadline - Date.now()) / 1000));
        $('[data-demo-clock]', demo).textContent = `${pad(Math.floor(left / 3600))}:${pad(Math.floor((left % 3600) / 60))}:${pad(left % 60)}`;
    }

    /** Pola QR hiasan (bukan QR asli): tiga penanda sudut + kotak acak dengan seed tetap. */
    function drawDemoQr() {
        const canvas = demoTicket ? $('[data-ticket-qr]', demoTicket) : null;
        if (!canvas) return;
        const n = 25;
        const size = 132;
        const m = size / n;
        const dpr = Math.min(2, window.devicePixelRatio || 1);
        canvas.width = size * dpr;
        canvas.height = size * dpr;
        const c = canvas.getContext('2d');
        c.setTransform(dpr, 0, 0, dpr, 0, 0);
        c.fillStyle = '#fff';
        c.fillRect(0, 0, size, size);
        c.fillStyle = '#14191c';
        let s = 7331;
        const rnd = () => { s = (s * 16807) % 2147483647; return (s - 1) / 2147483646; };
        const finder = (r, k) => {
            for (const [r0, k0] of [[0, 0], [0, n - 7], [n - 7, 0]]) {
                const dr = r - r0;
                const dk = k - k0;
                if (dr >= -1 && dr <= 7 && dk >= -1 && dk <= 7) {
                    if (dr < 0 || dk < 0 || dr > 6 || dk > 6) return false;
                    return Math.max(Math.abs(dr - 3), Math.abs(dk - 3)) !== 2;
                }
            }
            return null;
        };
        for (let r = 0; r < n; r++) {
            for (let k = 0; k < n; k++) {
                const f = finder(r, k);
                const on = f !== null ? f : (r === 6 || k === 6) ? (r + k) % 2 === 0 : rnd() > 0.52;
                if (on) c.fillRect(k * m, r * m, m + 0.3, m + 0.3);
            }
        }
    }

    if (demoTicket && demoSteps.length > 0) {
        // Bila tombol yang diklik ikut tersembunyi, fokus pindah ke tombol berikutnya.
        const keepFocus = (from) => { if (from.hidden) demoActions.find((b) => !b.hidden)?.focus(); };
        $('[data-demo-pay]', demo).addEventListener('click', (event) => {
            setDemoStep(1);
            CG.toast('Pembayaran contoh diterima. QR pengambilan sekarang terbuka.');
            keepFocus(event.currentTarget);
        });
        $('[data-demo-next]', demo).addEventListener('click', (event) => {
            setDemoStep(Math.min(demoSteps.length - 1, demoStep + 1));
            keepFocus(event.currentTarget);
        });
        $('[data-demo-reset]', demo).addEventListener('click', (event) => {
            setDemoStep(0);
            keepFocus(event.currentTarget);
        });
        document.addEventListener('campgear:ransel', fillTicket);
        setInterval(tickDemo, 1000);
        drawDemoQr();
        setDemoStep(0, false);
        fillTicket();
    }

    // ---- Altimeter -------------------------------------------------------------

    const alti = $('[data-alti]');
    const altiValue = $('[data-alti-value]');
    const altiMarker = $('[data-alti-marker]');
    const rail = $('[data-alti-rail]');
    const peaks = $$('[data-mdpl]').map((el) => ({ el, mdpl: Number(el.dataset.mdpl), label: el.dataset.label }));
    const TOP = Math.max(...peaks.map((p) => p.mdpl));
    const ticks = peaks.map((peak) => {
        const tick = document.createElement('button');
        tick.type = 'button';
        tick.className = 'alti-tick';
        tick.style.bottom = `${(peak.mdpl / TOP) * 100}%`;
        tick.setAttribute('aria-label', `${peak.label}, ${peak.mdpl.toLocaleString('id-ID')} mdpl`);
        const label = document.createElement('span');
        label.textContent = `${peak.label} · ${peak.mdpl.toLocaleString('id-ID')} mdpl`;
        tick.append(label);
        tick.addEventListener('click', () => scrollToSection(peak.el));
        rail?.append(tick);
        return tick;
    });

    function updateAltimeter() {
        if (!alti || getComputedStyle(alti).display === 'none') return;
        // Muncul setelah meninggalkan basecamp, supaya hero tetap bersih.
        alti.classList.toggle('is-shown', scrollY > innerHeight * 0.45);
        const y = scrollY;
        const maxScroll = document.documentElement.scrollHeight - innerHeight;
        // Posisi scroll saat tiap section "tiba" (sama dengan tujuan klik tick).
        const tops = peaks.map((p, k) => Math.min(maxScroll, k === 0 ? 0 : naturalTop(p.el) + (p.el === medan ? peek : 0)));
        let i = 0;
        while (i < tops.length - 1 && y >= tops[i + 1]) i++;
        const next = Math.min(i + 1, peaks.length - 1);
        const span = tops[next] - tops[i];
        const t = next === i || span <= 0 ? 0 : clamp((y - tops[i]) / span, 0, 1);
        const value = peaks[i].mdpl + (peaks[next].mdpl - peaks[i].mdpl) * t;
        altiValue.textContent = Math.round(value).toLocaleString('id-ID');
        altiMarker.style.bottom = `${(value / TOP) * 100}%`;
        ticks.forEach((tick, k) => tick.classList.toggle('is-here', k === i));
    }

    // ---- Tata letak & scroll ---------------------------------------------------

    function setup() {
        const wide = innerWidth >= 992;
        stackOn = motion && wide;
        pinOn = motion && wide;
        document.documentElement.classList.toggle('stack-on', stackOn);
        document.documentElement.classList.toggle('pin-on', pinOn);
        document.documentElement.dataset.motion = motion ? 'on' : 'off';
        peek = medan ? -parseFloat(getComputedStyle(medan).marginTop) || 0 : 0;
        scenes.forEach((scene) => {
            scene.style.top = stackOn ? `${Math.min(0, innerHeight - scene.offsetHeight)}px` : '';
            if (!stackOn) scene.style.setProperty('--cover', '0');
        });
        if (!motion) [backCanvas, frontCanvas, word].forEach((el) => { el.style.transform = el === word ? '' : `translateX(${-BLEED}px)`; });
        if (!pinOn) setTrail(1);
        measureMedan();
        layoutMedan();
        measure();
        drawSky();
        drawRidges();
        placeSun();
        placeTips();
        initLenis();
        frame();
    }

    let frameQueued = false;
    function frame() {
        frameQueued = false;
        const vh = innerHeight;
        if (stackOn) {
            scenes.forEach((scene, i) => {
                const next = stackKids[i + 1];
                if (!next) return;
                const rest = vh - (i === 0 ? peek : 0);
                scene.style.setProperty('--cover', clamp((rest - next.getBoundingClientRect().top) / rest, 0, 1).toFixed(3));
            });
        }
        heroCover = clamp(scrollY / Math.max(1, vh - peek), 0, 1);
        applyParallax();
        if (hintEl && scrollY > 40) hideHint(false);
        dock();
        if (pinOn && jalur) setTrail(toTrail(jalurProgress().p));
        updateAltimeter();
    }
    const requestFrame = () => { if (!frameQueued) { frameQueued = true; requestAnimationFrame(frame); } };
    addEventListener('scroll', () => {
        requestFrame();
        clearTimeout(snapTimer);
        if (pinOn) snapTimer = setTimeout(snapToStop, 180);
    }, { passive: true });
    let resizeTimer = 0;
    addEventListener('resize', () => { clearTimeout(resizeTimer); resizeTimer = setTimeout(setup, 150); });

    renderPeople();
    updatePackageTotals();
    renderMedanMeta();
    if (CG.trip.hasDates()) suggestPackage();
    setup();
    (document.fonts?.ready ?? Promise.resolve()).then(() => { setup(); playIntro(); });
})();
