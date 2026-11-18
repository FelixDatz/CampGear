/**
 * Grafik statistik: ambil data dari GET /api/stats (fetch + async/await),
 * lalu gambar dengan Chart.js dan isi tabel data sebagai alternatif aksesibel.
 *
 * Warna data memakai palet kategorikal yang sudah divalidasi untuk buta warna
 * (biru, oranye, aqua). Grafik satu seri memakai warna pertama.
 */
(() => {
    'use strict';

    const root = document.querySelector('[data-stats-root]');
    if (!root) return;

    const COLORS = { series1: '#2a78d6', series2: '#eb6834', series3: '#1baf7a' };
    const INK = { muted: '#898781', grid: '#e1e0d9', axis: '#c3c2b7', surface: '#ffffff' };
    const RANGES = { month: [6, 12], week: [8, 12] };
    const CANCEL_LABELS = { approved: 'Disetujui', rejected: 'Ditolak', pending: 'Menunggu' };

    const showRevenue = root.dataset.showRevenue === '1';
    const rangeSelect = root.querySelector('[data-range]');
    const rangeLabel = root.querySelector('[data-range-label]');
    const errorBox = root.querySelector('[data-stats-error]');
    const charts = {};

    const number = (n) => Number(n).toLocaleString('id-ID');
    const rupiah = (n) => 'Rp' + Math.round(n).toLocaleString('id-ID');
    const percent = (n) => Number(n).toLocaleString('id-ID', { maximumFractionDigits: 1 }) + '%';
    const formatDate = (iso) => new Date(iso + 'T00:00:00').toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });

    if (window.Chart) {
        Chart.defaults.font.family = getComputedStyle(document.body).fontFamily;
        Chart.defaults.color = INK.muted;
        Chart.defaults.plugins.tooltip.padding = 10;
        Chart.defaults.plugins.tooltip.boxPadding = 4;
    }

    // ---- Kontrol periode & rentang ---------------------------------------

    const currentPeriod = () => root.querySelector('[data-period]:checked')?.value ?? 'month';

    function fillRanges() {
        const period = currentPeriod();
        const unit = period === 'week' ? 'minggu' : 'bulan';
        rangeSelect.replaceChildren(...RANGES[period].map((n) => new Option(`${n} ${unit} terakhir`, String(n))));
    }

    root.querySelectorAll('[data-period]').forEach((input) => input.addEventListener('change', () => { fillRanges(); load(); }));
    rangeSelect.addEventListener('change', load);

    // ---- Ambil data --------------------------------------------------------

    let requestCounter = 0;
    async function load() {
        const requestId = ++requestCounter;
        root.classList.add('is-loading');
        try {
            const params = new URLSearchParams({ period: currentPeriod(), range: rangeSelect.value });
            const data = await CampGear.api('/api/stats?' + params.toString());
            if (requestId !== requestCounter) return;
            errorBox.classList.add('d-none');
            render(data);
        } catch (error) {
            if (requestId !== requestCounter) return;
            errorBox.textContent = 'Gagal memuat statistik: ' + error.message;
            errorBox.classList.remove('d-none');
        } finally {
            if (requestId === requestCounter) root.classList.remove('is-loading');
        }
    }

    // ---- Tampilan ----------------------------------------------------------

    function render(data) {
        rangeLabel.textContent = `${formatDate(data.from)} – ${formatDate(data.to)}`;
        setTotal('rentals', number(data.totals.rentals));
        setTotal('cancellations', number(data.totals.cancellations));
        const note = root.querySelector('[data-total-note="cancellations"]');
        if (note) note.textContent = `${number(data.totals.approved)} disetujui`;
        setTotal('utilization', percent(data.totals.utilization));
        if (showRevenue) setTotal('revenue', rupiah(data.totals.revenue ?? 0));

        const labels = data.labels;
        const cancelSeries = ['approved', 'rejected', 'pending'];
        const cancelColors = [COLORS.series1, COLORS.series2, COLORS.series3];

        draw('rentals', 'bar', labels, [barSet('Penyewaan', data.rentals, COLORS.series1)], { format: number, integer: true });
        draw('cancellations', 'bar', labels,
            cancelSeries.map((key, i) => barSet(CANCEL_LABELS[key], data.cancellations[key], cancelColors[i], true)),
            { format: number, integer: true, stacked: true, legend: true });
        draw('utilization', 'line', labels, [lineSet('Pemakaian stok', data.utilization, COLORS.series1)], { format: percent, max: 100 });
        draw('categories', 'bar', data.categories.map((c) => c.name),
            [barSet('Pemakaian stok', data.categories.map((c) => c.percent), COLORS.series1)],
            { format: percent, horizontal: true, max: 100 });
        if (showRevenue && data.revenue) {
            draw('revenue', 'bar', labels, [barSet('Pendapatan', data.revenue, COLORS.series1)], { format: rupiah, compact: true });
        }

        table('rentals', ['Periode', 'Penyewaan'], labels.map((l, i) => [l, number(data.rentals[i])]));
        table('cancellations', ['Periode', 'Disetujui', 'Ditolak', 'Menunggu'],
            labels.map((l, i) => [l, ...cancelSeries.map((k) => number(data.cancellations[k][i]))]));
        table('utilization', ['Periode', 'Pemakaian'], labels.map((l, i) => [l, percent(data.utilization[i])]));
        table('categories', ['Kategori', 'Unit-hari tersewa', 'Kapasitas', 'Pemakaian'],
            data.categories.map((c) => [c.name, number(c.unit_days), number(c.capacity), percent(c.percent)]));
        if (showRevenue && data.revenue) {
            table('revenue', ['Periode', 'Pendapatan'], labels.map((l, i) => [l, rupiah(data.revenue[i])]));
        }
    }

    function setTotal(key, text) {
        const el = root.querySelector(`[data-total="${key}"]`);
        if (el) el.textContent = text;
    }

    function barSet(label, values, color, stacked = false) {
        return {
            label, data: values, backgroundColor: color,
            borderColor: INK.surface, borderWidth: stacked ? { top: 2 } : 0, borderSkipped: 'start',
            borderRadius: stacked ? 2 : 4, maxBarThickness: 32,
        };
    }

    function lineSet(label, values, color) {
        return {
            label, data: values, borderColor: color, backgroundColor: color, borderWidth: 2,
            pointRadius: 4, pointHoverRadius: 6, pointBorderColor: INK.surface, pointBorderWidth: 2, tension: 0.25,
        };
    }

    function draw(key, type, labels, datasets, opts) {
        const canvas = root.querySelector(`[data-chart="${key}"]`);
        const empty = root.querySelector(`[data-empty="${key}"]`);
        if (!canvas) return;

        const hasData = datasets.some((d) => d.data.some((v) => Number(v) > 0));
        empty.classList.toggle('d-none', hasData);

        if (!window.Chart) {
            empty.textContent = 'Pustaka grafik gagal dimuat. Lihat tabel data di bawah.';
            empty.classList.remove('d-none');
            return;
        }

        charts[key]?.destroy();
        const valueAxis = {
            beginAtZero: true,
            stacked: !!opts.stacked,
            suggestedMax: opts.max && !hasData ? opts.max : undefined,
            grid: { color: INK.grid, drawTicks: false },
            border: { display: false },
            ticks: {
                padding: 6,
                precision: opts.integer ? 0 : undefined,
                callback: (v) => opts.compact ? 'Rp' + Number(v).toLocaleString('id-ID', { notation: 'compact' }) : opts.format(v),
            },
        };
        const categoryAxis = { stacked: !!opts.stacked, grid: { display: false }, border: { color: INK.axis } };

        charts[key] = new Chart(canvas, {
            type,
            data: { labels, datasets },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                indexAxis: opts.horizontal ? 'y' : 'x',
                interaction: { mode: 'index', intersect: false, axis: opts.horizontal ? 'y' : 'x' },
                animation: { duration: 250 },
                scales: opts.horizontal ? { x: valueAxis, y: categoryAxis } : { x: categoryAxis, y: valueAxis },
                plugins: {
                    legend: { display: !!opts.legend, position: 'top', align: 'start', labels: { boxWidth: 12, boxHeight: 12, useBorderRadius: true, borderRadius: 2 } },
                    tooltip: { callbacks: { label: (ctx) => `${ctx.dataset.label}: ${opts.format(ctx.parsed[opts.horizontal ? 'x' : 'y'])}` } },
                },
            },
        });
    }

    function table(key, headers, rows) {
        const holder = root.querySelector(`[data-table-for="${key}"]`);
        if (!holder) return;
        const tableEl = document.createElement('table');
        tableEl.className = 'table table-sm mb-0';
        const head = tableEl.createTHead().insertRow();
        headers.forEach((h, i) => {
            const th = document.createElement('th');
            th.textContent = h;
            th.scope = 'col';
            if (i > 0) th.className = 'text-end';
            head.append(th);
        });
        const body = tableEl.createTBody();
        rows.forEach((cells) => {
            const tr = body.insertRow();
            cells.forEach((value, i) => {
                const td = tr.insertCell();
                td.textContent = value;
                if (i > 0) td.className = 'text-end';
            });
        });
        holder.replaceChildren(tableEl);
    }

    fillRanges();
    // Tunggu font web siap agar Chart.js mengukur lebar label dengan font yang benar.
    (document.fonts?.ready ?? Promise.resolve()).then(load);
})();
