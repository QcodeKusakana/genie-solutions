(() => {
    'use strict';

    document.querySelectorAll('[data-hero-carousel]').forEach((carousel) => {
        const slides = [...carousel.querySelectorAll('[data-hero-slide]')];
        const dots = [...carousel.querySelectorAll('[data-hero-dot]')];
        if (slides.length < 2) return;

        const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
        const interval = Math.max(3500, Number(carousel.dataset.interval) || 5500);
        let current = Math.max(0, slides.findIndex((slide) => slide.classList.contains('is-active')));
        let timer = null;

        const show = (index) => {
            current = (index + slides.length) % slides.length;
            slides.forEach((slide, i) => {
                const active = i === current;
                slide.classList.toggle('is-active', active);
                slide.setAttribute('aria-hidden', String(!active));
                const image = slide.querySelector('img');
                if (image && active) image.loading = 'eager';
            });
            dots.forEach((dot, i) => {
                const active = i === current;
                dot.classList.toggle('is-active', active);
                if (active) dot.setAttribute('aria-current', 'true');
                else dot.removeAttribute('aria-current');
            });
        };

        const stop = () => {
            window.clearInterval(timer);
            timer = null;
        };
        const start = () => {
            stop();
            if (!reducedMotion.matches && !document.hidden) {
                timer = window.setInterval(() => show(current + 1), interval);
            }
        };

        carousel.querySelector('[data-hero-prev]')?.addEventListener('click', () => { show(current - 1); start(); });
        carousel.querySelector('[data-hero-next]')?.addEventListener('click', () => { show(current + 1); start(); });
        dots.forEach((dot, i) => dot.addEventListener('click', () => { show(i); start(); }));
        carousel.addEventListener('mouseenter', stop);
        carousel.addEventListener('mouseleave', start);
        carousel.addEventListener('focusin', stop);
        carousel.addEventListener('focusout', (event) => {
            if (!carousel.contains(event.relatedTarget)) start();
        });
        document.addEventListener('visibilitychange', start);
        reducedMotion.addEventListener?.('change', start);
        start();
    });
})();
