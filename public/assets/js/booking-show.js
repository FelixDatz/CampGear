/**
 * Halaman detail booking: QR kartu pengambilan dan tombol salin kode.
 */
(() => {
    'use strict';

    // QR berisi kode booking; staf memindainya untuk membuka booking saat pengambilan.
    document.querySelectorAll('[data-qr]').forEach((box) => {
        if (typeof window.QRCode === 'undefined') {
            box.textContent = box.dataset.qr; // pustaka QR gagal dimuat: tampilkan kode sebagai teks
            box.classList.add('qr-fallback');
            return;
        }
        new window.QRCode(box, {
            text: box.dataset.qr,
            width: 132,
            height: 132,
            correctLevel: window.QRCode.CorrectLevel.M,
        });
    });

    document.querySelectorAll('[data-copy]').forEach((button) => {
        button.addEventListener('click', async () => {
            try {
                await navigator.clipboard.writeText(button.dataset.copy);
                button.innerHTML = '<i class="bi bi-check2"></i> Tersalin';
            } catch {
                window.prompt('Salin kode booking:', button.dataset.copy);
            }
        });
    });
})();
