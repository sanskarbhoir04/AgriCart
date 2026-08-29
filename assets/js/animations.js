/* ==========================================================================
   🎬 AgriCart – Global Animation Engine
   Runs on every page (loaded from includes/footer.php). Progressive
   enhancement only: if anything here fails, the page still looks and
   works exactly as before — nothing is hidden unless this script
   successfully tags it first.
   ========================================================================== */
(function () {
    "use strict";

    try { document.documentElement.classList.add('agri-anim-ready'); } catch (e) {}

    function ready(fn) {
        if (document.readyState !== 'loading') fn();
        else document.addEventListener('DOMContentLoaded', fn);
    }

    ready(function () {
        try { initScrollReveal(); } catch (e) { /* fail safe */ }
        try { initRipples(); } catch (e) {}
        try { initCounters(); } catch (e) {}
    });

    /* ------------------------------------------------------------------ */
    /* 1. SCROLL REVEAL                                                    */
    /* ------------------------------------------------------------------ */
    function initScrollReveal() {
        if (!('IntersectionObserver' in window)) return; // graceful skip, nothing hidden

        var selector = [
            '.product-card', '.gallery-card', '.widget-card', '.test-card',
            '.scheme-item', '.stat-item', '.cat-item', '.sidebar',
            '.offer-strip', '.section-label', '.section-title', '.section-sub',
            '.gallery-section > *', '.testimonials-section > *', '.widget-section > *',
            '[class*="-card"]', '[class*="-item"]', '[class*="-box"]',
            '[class*="-panel"]', '[class*="-tile"]', '.footer-col'
        ].join(',');

        var seen = new Set();
        var nodes = [];
        document.querySelectorAll(selector).forEach(function (el) {
            // Skip nav/header/critical chrome and anything nested inside a modal/drawer (must stay usable immediately)
            if (el.closest('#main-header, #flash-bar, .nav-menu, .cart-drawer, .profile-modal, .auth-modal, .cart-overlay')) return;
            if (seen.has(el)) return;
            seen.add(el);
            nodes.push(el);
        });

        // group by parent for a nice staggered wave
        var groups = new Map();
        nodes.forEach(function (el) {
            var p = el.parentElement || document.body;
            if (!groups.has(p)) groups.set(p, []);
            groups.get(p).push(el);
        });

        groups.forEach(function (siblings) {
            siblings.forEach(function (el, i) {
                el.classList.add('agri-reveal');
                var delay = Math.min(i * 70, 420);
                el.style.transitionDelay = delay + 'ms';
            });
        });

        var observer = new IntersectionObserver(function (entries, obs) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    entry.target.classList.add('agri-in');
                    obs.unobserve(entry.target);
                }
            });
        }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });

        nodes.forEach(function (el) { observer.observe(el); });

        // Safety net: if something is never observed into view (edge cases,
        // e.g. zero-height containers), reveal everything after 4s anyway.
        setTimeout(function () {
            document.querySelectorAll('.agri-reveal:not(.agri-in)').forEach(function (el) {
                el.classList.add('agri-in');
            });
        }, 4000);
    }

    /* ------------------------------------------------------------------ */
    /* 2. BUTTON RIPPLE                                                    */
    /* ------------------------------------------------------------------ */
    function initRipples() {
        document.addEventListener('click', function (e) {
            var target = e.target.closest('button, .btn, [class*="btn"], a.gallery-btn, .save-btn, .checkout-btn, .add-btn');
            if (!target) return;
            if (target.disabled) return;

            var rect = target.getBoundingClientRect();
            var size = Math.max(rect.width, rect.height);
            var ripple = document.createElement('span');
            ripple.className = 'agri-ripple';
            ripple.style.width = ripple.style.height = size + 'px';
            ripple.style.left = (e.clientX - rect.left - size / 2) + 'px';
            ripple.style.top = (e.clientY - rect.top - size / 2) + 'px';

            var prevPosition = getComputedStyle(target).position;
            if (prevPosition === 'static') target.style.position = 'relative';
            target.classList.add('agri-ripple-wrap');
            target.appendChild(ripple);

            setTimeout(function () {
                if (ripple.parentNode) ripple.parentNode.removeChild(ripple);
            }, 650);
        }, true);
    }

    /* ------------------------------------------------------------------ */
    /* 3. NUMBER COUNT-UP for stat blocks (e.g. "50,000+", "500+")         */
    /* ------------------------------------------------------------------ */
    function initCounters() {
        if (!('IntersectionObserver' in window)) return;

        var els = document.querySelectorAll('.stat-item h3, .stats h3');
        if (!els.length) return;

        var observer = new IntersectionObserver(function (entries, obs) {
            entries.forEach(function (entry) {
                if (!entry.isIntersecting) return;
                animateCount(entry.target);
                obs.unobserve(entry.target);
            });
        }, { threshold: 0.4 });

        els.forEach(function (el) {
            var raw = el.textContent.trim();
            var match = raw.match(/^([\d,]+)(.*)$/);
            if (!match) return; // e.g. "24/7" — leave untouched
            el.classList.add('agri-counted');
            el.setAttribute('data-agri-target', match[1].replace(/,/g, ''));
            el.setAttribute('data-agri-suffix', match[2] || '');
            el.textContent = '0' + (match[2] || '');
            observer.observe(el);
        });
    }

    function animateCount(el) {
        var target = parseInt(el.getAttribute('data-agri-target'), 10);
        var suffix = el.getAttribute('data-agri-suffix') || '';
        if (isNaN(target)) return;
        var duration = 1200;
        var start = null;

        function step(ts) {
            if (start === null) start = ts;
            var progress = Math.min((ts - start) / duration, 1);
            var eased = 1 - Math.pow(1 - progress, 3); // ease-out cubic
            var value = Math.floor(eased * target);
            el.textContent = value.toLocaleString('en-IN') + suffix;
            if (progress < 1) {
                window.requestAnimationFrame(step);
            } else {
                el.textContent = target.toLocaleString('en-IN') + suffix;
            }
        }
        window.requestAnimationFrame(step);
    }
})();
