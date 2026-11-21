/**
 * Panel staff: bantuan form checklist serah terima dan form dinamis lain.
 */
(() => {
    'use strict';

    const toInt = (input) => {
        const value = parseInt(input?.value ?? '0', 10);
        return Number.isFinite(value) && value > 0 ? value : 0;
    };

    // ---- Checklist serah terima ------------------------------------------
    // Saat jumlah rusak/hilang diubah, jumlah "baik" dihitung ulang otomatis.
    // Baris detail kerusakan muncul bila ada unit rusak/hilang.
    document.querySelectorAll('.handover-form').forEach((form) => {
        const rows = [...form.querySelectorAll('[data-handover-row]')];
        const errorBox = form.querySelector('.handover-error');

        const refreshRow = (row, changedRole) => {
            const qty = Number(row.dataset.qty);
            const good = row.querySelector('[data-role="good"]');
            const damaged = row.querySelector('[data-role="damaged"]');
            const lost = row.querySelector('[data-role="lost"]');

            if (changedRole === 'damaged' || changedRole === 'lost') {
                good.value = Math.max(0, qty - toInt(damaged) - toInt(lost));
            }
            const total = toInt(good) + toInt(damaged) + toInt(lost);
            row.classList.toggle('table-danger', total !== qty);

            const detail = row.nextElementSibling;
            if (detail && detail.hasAttribute('data-detail-for')) {
                detail.classList.toggle('d-none', toInt(damaged) + toInt(lost) === 0);
                detail.querySelector('[data-charge="damaged"]')?.classList.toggle('d-none', toInt(damaged) === 0);
                detail.querySelector('[data-charge="lost"]')?.classList.toggle('d-none', toInt(lost) === 0);
            }
            return total === qty;
        };

        form.addEventListener('input', (event) => {
            const row = event.target.closest('[data-handover-row]');
            if (row) refreshRow(row, event.target.dataset.role);
        });

        // Dipasang dalam fase capture agar berjalan sebelum konfirmasi & penonaktifan tombol di app.js.
        form.addEventListener('submit', (event) => {
            const invalid = rows.filter((row) => !refreshRow(row));
            if (invalid.length > 0) {
                event.preventDefault();
                event.stopImmediatePropagation();
                errorBox.textContent = 'Jumlah baik + rusak/cacat + hilang harus sama dengan jumlah disewa pada baris yang ditandai merah.';
                errorBox.classList.remove('d-none');
            }
        }, true);

        rows.forEach((row) => refreshRow(row));
    });

    // ---- Baris dinamis (isi paket) ---------------------------------------
    // <div data-repeater> berisi <template> dan [data-repeater-list]; tombol [data-repeater-add] / [data-repeater-remove].
    document.querySelectorAll('[data-repeater]').forEach((repeater) => {
        const list = repeater.querySelector('[data-repeater-list]');
        const template = repeater.querySelector('template');
        let counter = list.children.length;

        repeater.addEventListener('click', (event) => {
            if (event.target.closest('[data-repeater-add]')) {
                const html = template.innerHTML.replaceAll('__INDEX__', String(counter++));
                list.insertAdjacentHTML('beforeend', html);
            }
            const remove = event.target.closest('[data-repeater-remove]');
            if (remove && list.children.length > 1) {
                remove.closest('[data-repeater-item]').remove();
            }
        });
    });

    // ---- Pratinjau gambar sebelum diunggah -------------------------------
    document.querySelectorAll('input[type="file"][data-preview]').forEach((input) => {
        const preview = document.querySelector(input.dataset.preview);
        input.addEventListener('change', () => {
            const file = input.files?.[0];
            if (!file || !preview) return;
            if (file.size > 2 * 1024 * 1024) {
                window.alert('Ukuran gambar maksimal 2 MB.');
                input.value = '';
                return;
            }
            preview.src = URL.createObjectURL(file);
            preview.classList.remove('d-none');
        });
    });

    // ---- Tema terang/gelap -----------------------------------------------
    // Disimpan di kunci yang sama dengan situs publik (u = posisi matahari:
    // 0,5 = siang, 1 = malam), jadi pilihan tema ikut terbawa ke halaman publik.
    const themeButton = document.querySelector('[data-panel-theme]');
    const applyTheme = (dark) => {
        const root = document.documentElement;
        root.setAttribute('data-theme', dark ? 'dark' : 'light');
        root.setAttribute('data-bs-theme', dark ? 'dark' : 'light');
        root.setAttribute('data-langit', dark ? 'malam' : 'siang');
        if (themeButton) themeButton.innerHTML = `<i class="bi ${dark ? 'bi-sun' : 'bi-moon-stars'}" aria-hidden="true"></i>`;
        document.dispatchEvent(new CustomEvent('campgear:theme', { detail: { theme: dark ? 'dark' : 'light' } }));
    };
    if (themeButton) {
        applyTheme(document.documentElement.getAttribute('data-theme') === 'dark');
        themeButton.addEventListener('click', () => {
            const dark = document.documentElement.getAttribute('data-theme') !== 'dark';
            try { localStorage.setItem('cg-langit', JSON.stringify({ u: dark ? 1 : 0.5, manual: true })); } catch { /* penyimpanan tidak tersedia */ }
            applyTheme(dark);
        });
    }
})();
