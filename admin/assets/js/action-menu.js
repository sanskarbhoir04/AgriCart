/* =====================================================================
   admin/assets/js/action-menu.js
   ---------------------------------------------------------------------
   ONE shared engine for every "⋮" Action dropdown in the Admin Panel.
   Loaded once from includes/team_layout_bottom.php.

   Backward compatible with both markup conventions already used across
   the panel:
     - toggleActionsMenu(event, 'menuElementId')   (Team Members, Inventory)
     - toggleActionMenu(event, buttonEl)           (Accounts, Products,
                                                      Seller Invoices/Payouts)
   Both call into the same internal engine, so every table gets:
     - single-menu-open-at-a-time
     - portalled to <body> so table overflow-x never clips it
     - viewport-edge-aware positioning (flips above / clamps sideways)
     - closes on outside click, Escape, scroll, resize
     - basic roving keyboard navigation (Arrow keys, Home/End)
     - ARIA: aria-haspopup / aria-expanded on the trigger, role="menu"
       on the panel, role="menuitem" on each entry
   ===================================================================== */
(function () {
    'use strict';

    function closeAll(exceptMenu) {
        document.querySelectorAll('.actions-menu.open, .action-menu.open').forEach(function (m) {
            if (m === exceptMenu) return;
            m.classList.remove('open');
            var trigger = m.__amTrigger;
            if (trigger) trigger.setAttribute('aria-expanded', 'false');
        });
    }

    function positionMenu(menu, trigger) {
        if (menu.parentElement !== document.body) document.body.appendChild(menu);
        menu.style.left = '-9999px';
        menu.style.top = '-9999px';
        menu.classList.add('open'); // needs to be visible to measure offsetWidth/Height
        var r = trigger.getBoundingClientRect();
        var menuW = menu.offsetWidth || 210;
        var menuH = menu.offsetHeight || 120;
        var left = r.right - menuW;
        left = Math.max(8, Math.min(left, window.innerWidth - menuW - 8));
        // Always open below the trigger button (no upward flip). The menu's
        // own max-height + overflow-y:auto (see action-menu.css) handles the
        // rare case where there isn't enough room below.
        var top = r.bottom + 4;
        menu.style.left = left + 'px';
        menu.style.top = top + 'px';
    }

    function openMenu(menu, trigger, viaKeyboard) {
        var isOpen = menu.classList.contains('open');
        closeAll();
        if (isOpen) return;

        menu.__amTrigger = trigger;
        menu.setAttribute('role', 'menu');
        menu.querySelectorAll('a, button').forEach(function (item) {
            item.setAttribute('role', 'menuitem');
            item.setAttribute('tabindex', '-1');
        });

        positionMenu(menu, trigger);
        trigger.setAttribute('aria-haspopup', 'true');
        trigger.setAttribute('aria-expanded', 'true');

        if (viaKeyboard) {
            var first = menu.querySelector('a, button');
            if (first) first.focus();
        }
    }

    /* ---- Public, backward-compatible entry points ---- */
    window.toggleActionsMenu = function (e, idOrEl) {
        e.stopPropagation();
        var menu = typeof idOrEl === 'string' ? document.getElementById(idOrEl) : idOrEl;
        if (!menu) return;
        openMenu(menu, e.currentTarget, e.detail === 0 /* 0 clicks = keyboard/Enter activation */);
    };
    window.toggleActionMenu = function (e, btn) {
        e.stopPropagation();
        // positionMenu() portals the menu to <body> the first time it opens
        // (so it can't be clipped by a table's overflow-x wrapper), which
        // means btn.nextElementSibling only finds it correctly on the very
        // first click — after that it's no longer next to the button in the
        // DOM. Cache the reference on the button itself the first time so
        // every click after that (and every re-render of this same button)
        // still finds the right menu.
        var menu = btn.__amMenu || btn.nextElementSibling;
        if (!menu) return;
        btn.__amMenu = menu;
        openMenu(menu, btn, e.detail === 0);
    };
    window.closeAllActionsMenus = function () { closeAll(); };

    /* ---- Global close triggers ---- */
    document.addEventListener('click', function () { closeAll(); });
    document.addEventListener('keydown', function (e) {
        var openMenuEl = document.querySelector('.actions-menu.open, .action-menu.open');
        if (!openMenuEl) return;

        if (e.key === 'Escape') {
            var trigger = openMenuEl.__amTrigger;
            closeAll();
            if (trigger) trigger.focus();
            return;
        }

        var items = Array.prototype.slice.call(openMenuEl.querySelectorAll('a, button'));
        if (!items.length) return;
        var idx = items.indexOf(document.activeElement);

        if (e.key === 'ArrowDown') {
            e.preventDefault();
            items[(idx + 1 + items.length) % items.length].focus();
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            items[(idx - 1 + items.length) % items.length].focus();
        } else if (e.key === 'Home') {
            e.preventDefault();
            items[0].focus();
        } else if (e.key === 'End') {
            e.preventDefault();
            items[items.length - 1].focus();
        }
    });
    window.addEventListener('scroll', function () { closeAll(); }, true);
    window.addEventListener('resize', function () { closeAll(); });

    /* =====================================================================
       Shared confirm modal — confirmAction(message, onYes, opts)
       Reuses a page's own #modalConfirm if present (older pages already
       define one); otherwise lazily builds one so ANY page can call
       confirmAction() with zero extra markup. Delete always renders with
       the danger button style.
       ===================================================================== */
    function ensureConfirmModal() {
        var existing = document.getElementById('modalConfirm');
        if (existing) return existing;

        var overlay = document.createElement('div');
        overlay.className = 'modal-overlay';
        overlay.id = 'modalConfirm';
        overlay.innerHTML =
            '<div class="modal-box" style="max-width:420px">' +
            '  <h3 id="confirmTitle">Please confirm</h3>' +
            '  <p id="confirmMsg"></p>' +
            '  <div class="modal-actions">' +
            '    <button type="button" class="btn outline" data-am-cancel>Cancel</button>' +
            '    <button type="button" class="btn danger" id="confirmYesBtn">Yes, continue</button>' +
            '  </div>' +
            '</div>';
        document.body.appendChild(overlay);
        overlay.addEventListener('click', function (e) {
            if (e.target === overlay) overlay.classList.remove('open');
        });
        overlay.querySelector('[data-am-cancel]').addEventListener('click', function () {
            overlay.classList.remove('open');
        });
        return overlay;
    }

    window.confirmAction = function (msg, onYes, opts) {
        opts = opts || {};
        var overlay = ensureConfirmModal();
        overlay.querySelector('#confirmTitle') && (overlay.querySelector('#confirmTitle').textContent = opts.title || 'Please confirm');
        overlay.querySelector('#confirmMsg').textContent = msg;
        var btn = overlay.querySelector('#confirmYesBtn');
        btn.textContent = opts.confirmLabel || 'Yes, continue';
        var clone = btn.cloneNode(true);
        btn.parentNode.replaceChild(clone, btn);
        clone.addEventListener('click', function () {
            overlay.classList.remove('open');
            onYes();
        });
        overlay.classList.add('open');
    };
})();
