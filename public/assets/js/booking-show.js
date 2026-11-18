/**
 * Halaman detail booking (tiket ekspedisi): QR pengambilan, salin kode,
 * hitung mundur batas bayar, dan tombol cetak.
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

    document.querySelectorAll('[data-print]').forEach((button) => button.addEventListener('click', () => window.print()));

    // Hitung mundur batas bayar. Sisa detik dihitung server, jadi zona waktu
    // perangkat tidak berpengaruh. Kedaluwarsa sendiri diproses server saat halaman dimuat.
    const countdown = document.querySelector('[data-countdown]');
    if (countdown) {
        const end = Date.now() + Number(countdown.dataset.countdown) * 1000;
        const pad = (n) => String(n).padStart(2, '0');
        const tick = () => {
            const left = Math.max(0, Math.round((end - Date.now()) / 1000));
            countdown.textContent = `${pad(Math.floor(left / 3600))}:${pad(Math.floor((left % 3600) / 60))}:${pad(left % 60)}`;
            if (left === 0) {
                clearInterval(timer);
                const label = document.querySelector('[data-countdown-label]');
                if (label) label.textContent = 'waktu bayar habis, muat ulang halaman';
            }
        };
        const timer = setInterval(tick, 1000);
        tick();
    }
})();
