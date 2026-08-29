/* =====================================================================
 * assets/js/form-scroll-validate.js
 * -----------------------------------------------------------------
 * GLOBAL AgriCart form-validation UX helper.
 *
 * What this file does (and only this):
 *   - When any form on the site is submitted and one or more fields
 *     are invalid, it smoothly scrolls to the FIRST invalid field,
 *     opens any tab/accordion/modal it might be hidden inside,
 *     focuses it, and (if the page hasn't already styled the error
 *     itself) applies a small highlight so it's easy to spot.
 *
 * What this file deliberately does NOT do:
 *   - It does not replace, remove, or duplicate any existing HTML5,
 *     JavaScript, or PHP/backend validation. Existing "required"
 *     messages, custom error text, and backend checks all keep
 *     working exactly as before — this only changes where the page
 *     scrolls to when validation fails.
 *   - It does not change form markup, styling, layout, or database
 *     logic.
 *
 * How it finds the "first invalid field":
 *   1. Native HTML5 constraint validation (required, pattern, min,
 *      max, minlength, maxlength, type="email"/"number", etc.) —
 *      the browser already fires an `invalid` event, in document
 *      order, for every field that fails when a form is submitted.
 *      We listen for that (capture phase, since `invalid` doesn't
 *      bubble) and scroll to the first one only.
 *   2. Existing custom validation error markers already used around
 *      the AgriCart codebase (aria-invalid="true", .is-invalid,
 *      .has-error, .field-error, .input-error) — checked right
 *      after a submit attempt / submit-button click, so forms that
 *      do their own JS validation (password mismatch, GST/PIN
 *      format, required dropdown, required file, required
 *      checkbox/radio group, etc.) are picked up the same way,
 *      without this file needing to know about each one individually.
 *
 * Public API (for any current or future custom validation code to
 * call directly if it wants to trigger the same scroll behaviour):
 *
 *   AgriCartFormValidate.scrollToFirstInvalidField(form)
 *   AgriCartFormValidate.scrollToField(fieldOrSelector)
 *
 * Include this script once per page (already wired into the shared
 * header/footer/layout includes) — do not duplicate this logic in
 * individual pages.
 * ===================================================================== */
(function () {
    "use strict";

    if (window.AgriCartFormValidate) return; // already loaded on this page

    var SCROLL_OFFSET_FALLBACK = 100; // px — matches the requested scroll-margin-top
    var FIELD_SELECTOR =
        'input:not([type="hidden"]):not([type="submit"]):not([type="button"]), select, textarea, [contenteditable="true"]';
    var CUSTOM_ERROR_SELECTOR =
        '.is-invalid, .has-error, .field-error, .input-error, .invalid-field, [aria-invalid="true"]';
    var ERROR_TEXT_SELECTOR =
        '.error-text, .error-message, .field-error, .invalid-feedback, .help-error, .form-error';

    /* ---------------------------------------------------------------
     * One-time styling: a subtle highlight used ONLY as a fallback
     * when a page has no error styling of its own on the field yet.
     * Kept intentionally simple — no flashy/repeated animation.
     * ------------------------------------------------------------- */
    function injectHighlightStyle() {
        if (document.getElementById("agri-scroll-validate-style")) return;
        var style = document.createElement("style");
        style.id = "agri-scroll-validate-style";
        style.textContent =
            ".agri-scroll-highlight{outline:2px solid #d9534f !important;" +
            "outline-offset:1px;border-color:#d9534f !important;" +
            "transition:outline-color .2s ease, border-color .2s ease;}";
        document.head.appendChild(style);
    }

    /* ---------------------------------------------------------------
     * Work out a safe top offset so the field doesn't land underneath
     * a fixed/sticky navbar, admin sidebar-topbar, or sticky form
     * action bar.
     * ------------------------------------------------------------- */
    function getScrollOffset() {
        var candidates = document.querySelectorAll(
            '#main-header, .main-header, header.sticky, header.fixed-top, ' +
            '.navbar.fixed-top, .navbar.sticky-top, .admin-topbar, .topbar, ' +
            '.sidebar-topbar, [class*="sticky-header"], [class*="fixed-header"]'
        );
        var maxH = 0;
        for (var i = 0; i < candidates.length; i++) {
            var el = candidates[i];
            var pos = window.getComputedStyle(el).position;
            if (pos === "fixed" || pos === "sticky") {
                maxH = Math.max(maxH, el.getBoundingClientRect().height);
            }
        }
        return Math.max(maxH + 20, SCROLL_OFFSET_FALLBACK);
    }

    /* ---------------------------------------------------------------
     * If the invalid field lives inside a modal's scrollable body,
     * scroll that container instead of the whole page (per spec:
     * "do not scroll the entire background page unnecessarily").
     * ------------------------------------------------------------- */
    function findModalScroller(field) {
        var node = field.parentElement;
        while (node && node !== document.body) {
            if (
                node.matches &&
                node.matches(
                    '.modal, .modal-body, .modal-content, [role="dialog"] .modal-body, [class*="modal"][class*="body"]'
                )
            ) {
                var style = window.getComputedStyle(node);
                if (/(auto|scroll)/.test(style.overflowY) || node.scrollHeight > node.clientHeight) {
                    return node;
                }
            }
            node = node.parentElement;
        }
        return null;
    }

    /* ---------------------------------------------------------------
     * Open whatever ancestor is currently hiding the field: a closed
     * <details>, an inactive tab pane / accordion collapse, or an
     * element hidden via [hidden] / display:none. Also clicks the
     * matching tab trigger if one exists, so the UI stays consistent.
     * ------------------------------------------------------------- */
    function revealHiddenAncestors(field) {
        var node = field;
        while (node && node !== document.body) {
            if (node.tagName === "DETAILS" && !node.open) {
                node.open = true;
            }
            if (node.classList) {
                var looksLikeTabOrCollapse =
                    node.classList.contains("tab-pane") ||
                    node.classList.contains("collapse") ||
                    node.classList.contains("accordion-collapse") ||
                    node.getAttribute("role") === "tabpanel";
                var looksInactive =
                    !node.classList.contains("show") &&
                    !node.classList.contains("active") &&
                    (window.getComputedStyle(node).display === "none" ||
                        node.hidden === true);

                if (looksLikeTabOrCollapse && looksInactive) {
                    var paneId = node.id;
                    var trigger = paneId
                        ? document.querySelector(
                              '[data-bs-target="#' + paneId + '"], [href="#' + paneId + '"], [aria-controls="' + paneId + '"]'
                          )
                        : null;
                    if (trigger && typeof trigger.click === "function") {
                        trigger.click();
                    } else {
                        node.classList.add("show", "active");
                    }
                }
            }
            if (node.hasAttribute && node.hasAttribute("hidden")) {
                node.removeAttribute("hidden");
            }
            if (node.style && node.style.display === "none") {
                node.style.display = "";
            }
            node = node.parentElement;
        }
    }

    /* ---------------------------------------------------------------
     * Wire aria-invalid / aria-describedby to whatever error text is
     * already sitting near the field, without inventing new copy.
     * ------------------------------------------------------------- */
    function wireAccessibility(field) {
        field.setAttribute("aria-invalid", "true");
        var container = field.closest("label, .form-group, .form-field, .input-group, .field, div") || field.parentElement;
        if (!container) return;
        var errorEl = container.querySelector(ERROR_TEXT_SELECTOR);
        if (errorEl) {
            if (!errorEl.id) {
                errorEl.id = "agri-err-" + Math.random().toString(36).slice(2, 9);
            }
            var described = field.getAttribute("aria-describedby") || "";
            if (described.indexOf(errorEl.id) === -1) {
                field.setAttribute("aria-describedby", (described + " " + errorEl.id).trim());
            }
        }
    }

    /* ---------------------------------------------------------------
     * The core, reusable utility.
     * ------------------------------------------------------------- */
    function scrollToField(field) {
        if (!field) return;
        if (typeof field === "string") field = document.querySelector(field);
        if (!field) return;

        injectHighlightStyle();
        revealHiddenAncestors(field);

        var offset = getScrollOffset();
        try {
            field.style.scrollMarginTop = offset + "px";
        } catch (e) {}

        var modalScroller = findModalScroller(field);
        if (modalScroller) {
            var fieldRect = field.getBoundingClientRect();
            var modalRect = modalScroller.getBoundingClientRect();
            var targetTop = modalScroller.scrollTop + (fieldRect.top - modalRect.top) - 24;
            try {
                modalScroller.scrollTo({ top: Math.max(targetTop, 0), behavior: "smooth" });
            } catch (e) {
                modalScroller.scrollTop = Math.max(targetTop, 0);
            }
        } else {
            try {
                field.scrollIntoView({ behavior: "smooth", block: "center" });
            } catch (e) {
                field.scrollIntoView();
            }
        }

        window.setTimeout(function () {
            try {
                field.focus({ preventScroll: true });
            } catch (e) {
                try {
                    field.focus();
                } catch (e2) {}
            }

            wireAccessibility(field);

            // Only add our own highlight if the page doesn't already
            // show an error state on this field — avoids stacking two
            // different error looks on top of each other.
            var alreadyStyled =
                field.matches(CUSTOM_ERROR_SELECTOR) ||
                (field.className || "").toString().indexOf("error") !== -1;
            if (!alreadyStyled) {
                field.classList.add("agri-scroll-highlight");
                window.setTimeout(function () {
                    field.classList.remove("agri-scroll-highlight");
                }, 2500);
            }
        }, 320);
    }

    function firstNativeInvalid(form) {
        if (!form || !form.querySelectorAll) return null;
        var invalids = form.querySelectorAll(":invalid");
        for (var i = 0; i < invalids.length; i++) {
            if (invalids[i].matches(FIELD_SELECTOR) && !invalids[i].disabled) {
                return invalids[i];
            }
        }
        return null;
    }

    function firstCustomInvalid(form) {
        if (!form || !form.querySelector) return null;
        var el = form.querySelector(CUSTOM_ERROR_SELECTOR);
        if (!el) return null;
        if (el.matches(FIELD_SELECTOR)) return el;
        var inner = el.querySelector(FIELD_SELECTOR);
        return inner || el; // fall back to the group container (e.g. a radio/checkbox set)
    }

    // Only scroll to ONE field per submit attempt (spec: "first error priority").
    function scrollToFirstInvalidField(form) {
        if (!form) return false;
        var target = firstNativeInvalid(form) || firstCustomInvalid(form);
        if (target) {
            scrollToField(target);
            return true;
        }
        return false;
    }

    window.AgriCartFormValidate = {
        scrollToFirstInvalidField: scrollToFirstInvalidField,
        scrollToField: scrollToField
    };

    /* ---------------------------------------------------------------
     * Auto-wiring — no per-page code required.
     * ------------------------------------------------------------- */

    // 1) Native HTML5 validation: the browser fires one `invalid`
    //    event per failing field, in DOM order, before it blocks
    //    submission. We react to only the first one per attempt.
    var lastForm = null;
    var lastAt = 0;
    document.addEventListener(
        "invalid",
        function (e) {
            var field = e.target;
            if (!field || !field.matches || !field.matches(FIELD_SELECTOR)) return;
            var form = field.form || field.closest("form");
            if (!form) return;
            var now = Date.now();
            if (lastForm === form && now - lastAt < 400) return; // same burst, already handled
            lastForm = form;
            lastAt = now;
            scrollToField(field);
        },
        true
    );

    // 2) Existing custom JS/PHP validation: after a submit attempt
    //    (or a click on something that looks like a submit control,
    //    for forms whose own script prevents default before a real
    //    `submit` fires) check briefly afterwards for the error
    //    markers the codebase already uses.
    function checkCustomValidationSoon(form) {
        if (!form) return;
        window.setTimeout(function () {
            if (form.querySelector(":invalid")) return; // native path already covered it
            scrollToFirstInvalidField(form);
        }, 80);
    }

    document.addEventListener(
        "submit",
        function (e) {
            if (e.target instanceof HTMLFormElement) {
                checkCustomValidationSoon(e.target);
            }
        },
        true
    );

    document.addEventListener(
        "click",
        function (e) {
            var trigger = e.target.closest(
                'button[type="submit"], input[type="submit"], [data-submit], .btn-submit, .submit-btn'
            );
            if (!trigger) return;
            var form = trigger.form || trigger.closest("form");
            if (!form) return;
            checkCustomValidationSoon(form);
        },
        true
    );
})();
