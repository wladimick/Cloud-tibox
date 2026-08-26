(function () {
    'use strict';

    function initSlider(root) {
        var slides = Array.prototype.slice.call(root.querySelectorAll('[data-tbx-slide]'));
        if (slides.length < 2) return;

        var prev = root.querySelector('[data-tbx-slider-prev]');
        var next = root.querySelector('[data-tbx-slider-next]');
        var dots = Array.prototype.slice.call(root.querySelectorAll('[data-tbx-slider-dot]'));
        var current = 0;

        function show(index, focusControl) {
            if (index < 0) index = slides.length - 1;
            if (index >= slides.length) index = 0;
            current = index;

            slides.forEach(function (slide, i) {
                var active = i === current;
                slide.hidden = !active;
                slide.classList.toggle('is-active', active);
            });

            dots.forEach(function (dot, i) {
                var active = i === current;
                dot.classList.toggle('is-active', active);
                dot.setAttribute('aria-current', active ? 'true' : 'false');
            });

            if (focusControl && dots[current]) {
                dots[current].focus({ preventScroll: true });
            }
        }

        if (prev) prev.addEventListener('click', function () { show(current - 1, false); });
        if (next) next.addEventListener('click', function () { show(current + 1, false); });

        dots.forEach(function (dot) {
            dot.addEventListener('click', function () {
                show(parseInt(dot.getAttribute('data-tbx-slider-dot'), 10) || 0, false);
            });
        });

        root.addEventListener('keydown', function (event) {
            if (event.key === 'ArrowLeft') show(current - 1, false);
            if (event.key === 'ArrowRight') show(current + 1, false);
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-tbx-home-slider]').forEach(initSlider);
    });
})();
