/**
 * CampGear — interaksi umum di semua halaman (vanilla JavaScript).
 */
(() => {
    'use strict';

    // Tampilkan/sembunyikan password: <button data-toggle-password="#password">
    document.addEventListener('click', (event) => {
        const button = event.target.closest('[data-toggle-password]');
        if (!button) return;
        const input = document.querySelector(button.dataset.togglePassword);
        if (!input) return;
        const show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        button.setAttribute('aria-label', show ? 'Sembunyikan password' : 'Tampilkan password');
        button.querySelector('.bi')?.classList.toggle('bi-eye', !show);
        button.querySelector('.bi')?.classList.toggle('bi-eye-slash', show);
    });

    // Isi form login dari tabel akun demo: <button data-fill-login data-email data-password>
    document.addEventListener('click', (event) => {
        const button = event.target.closest('[data-fill-login]');
        if (!button) return;
        const email = document.querySelector('#email');
        const password = document.querySelector('#password');
        if (email && password) {
            email.value = button.dataset.email;
            password.value = button.dataset.password;
            password.focus();
        }
    });

    // Konfirmasi sebelum submit: <form data-confirm="Yakin hapus?">
    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (form.dataset.confirm && !window.confirm(form.dataset.confirm)) {
            event.preventDefault();
        }
    });

    // Cegah submit ganda: tombol submit dinonaktifkan setelah diklik.
    document.addEventListener('submit', (event) => {
        if (event.defaultPrevented) return;
        const button = event.target.querySelector('button[type="submit"]');
        if (button) {
            setTimeout(() => { button.disabled = true; }, 0);
        }
    });
})();

/**
 * Pembungkus fetch() untuk memanggil API JSON aplikasi.
 * Token CSRF otomatis dikirim untuk request selain GET.
 *
 *   const data = await CampGear.api('/api/availability?equipment_id=1&start=...');
 */
window.CampGear = {
    baseUrl: document.querySelector('meta[name="base-path"]')?.content ?? '',

    async api(path, { method = 'GET', body = null } = {}) {
        const headers = { Accept: 'application/json' };
        if (method !== 'GET') {
            headers['X-CSRF-Token'] = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
        }
        if (body !== null) {
            headers['Content-Type'] = 'application/json';
        }

        const response = await fetch(this.baseUrl + path, {
            method,
            headers,
            body: body !== null ? JSON.stringify(body) : null,
            credentials: 'same-origin',
        });

        const data = await response.json().catch(() => ({}));
        if (!response.ok) {
            const error = new Error(data.error || `Permintaan gagal (${response.status})`);
            error.status = response.status;
            error.data = data;
            throw error;
        }
        return data;
    },
};
