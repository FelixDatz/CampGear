/**
 * Halaman baca artikel: bilah progres baca dan daftar isi dari subjudul (h2).
 */
(() => {
    'use strict';

    const body = document.querySelector('[data-read-body]');
    if (!body) return;

    // Progres baca: seberapa jauh isi artikel sudah dilewati.
    const bar = document.querySelector('[data-read-progress]');
    let queued = false;
    const update = () => {
        queued = false;
        const rect = body.getBoundingClientRect();
        const total = rect.height - innerHeight * 0.6;
        const done = Math.min(1, Math.max(0, -rect.top / Math.max(1, total)));
        if (bar) bar.style.transform = `scaleX(${done})`;
    };
    addEventListener('scroll', () => { if (!queued) { queued = true; requestAnimationFrame(update); } }, { passive: true });
    update();

    // Daftar isi: hanya bila artikel punya minimal dua subjudul.
    const headings = Array.from(body.querySelectorAll('h2'));
    const toc = document.querySelector('[data-read-toc]');
    const list = document.querySelector('[data-read-toc-list]');
    if (!toc || !list || headings.length < 2) return;
    headings.forEach((heading, i) => {
        heading.id ||= `bagian-${i + 1}`;
        const li = document.createElement('li');
        const link = document.createElement('a');
        link.href = `#${heading.id}`;
        link.textContent = heading.textContent;
        li.append(link);
        list.append(li);
    });
    toc.hidden = false;

    // Tandai subjudul yang sedang dibaca.
    const links = Array.from(list.querySelectorAll('a'));
    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (!entry.isIntersecting) return;
            links.forEach((link) => link.classList.toggle('is-here', link.getAttribute('href') === `#${entry.target.id}`));
        });
    }, { rootMargin: '-20% 0px -70% 0px' });
    headings.forEach((heading) => observer.observe(heading));
})();
