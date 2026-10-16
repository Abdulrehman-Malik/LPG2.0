/*
 * Perfect LPG back-office shell behaviour (Phase 11.2): sidebar collapse / off-canvas, menu
 * search, theme switch, form double-submit guard, confirm dialogs and toasts.
 * Exposes window.LPG.toast(message, type) and window.LPG.confirm(message, options).
 */
(function () {
    'use strict';

    var store = {
        get: function (key) { try { return window.localStorage.getItem(key); } catch (e) { return null; } },
        set: function (key, value) { try { window.localStorage.setItem(key, value); } catch (e) { /* storage unavailable */ } }
    };
    var body = document.body;
    var root = document.documentElement;
    var isMobile = function () { return window.innerWidth < 992; };

    /* ---------- Sidebar ---------- */
    var toggle = document.getElementById('sidebarToggle');
    var backdrop = document.getElementById('sidebarBackdrop');
    var closeBtn = document.getElementById('sidebarClose');

    function setMobileOpen(open) {
        body.classList.toggle('sidebar-open', open);
        if (toggle) toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        if (open) {
            var search = document.getElementById('navSearch');
            if (search) search.focus({ preventScroll: true });
        }
    }

    if (store.get('lpgSidebarCollapsed') === '1' && !isMobile()) body.classList.add('sidebar-collapsed');
    if (toggle) {
        toggle.addEventListener('click', function () {
            if (isMobile()) {
                setMobileOpen(!body.classList.contains('sidebar-open'));
            } else {
                body.classList.toggle('sidebar-collapsed');
                store.set('lpgSidebarCollapsed', body.classList.contains('sidebar-collapsed') ? '1' : '0');
            }
        });
    }
    if (backdrop) backdrop.addEventListener('click', function () { setMobileOpen(false); });
    if (closeBtn) closeBtn.addEventListener('click', function () { setMobileOpen(false); if (toggle) toggle.focus(); });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && body.classList.contains('sidebar-open')) { setMobileOpen(false); if (toggle) toggle.focus(); }
    });
    window.addEventListener('resize', function () { if (!isMobile()) setMobileOpen(false); });

    // Menu groups
    document.querySelectorAll('[data-nav-group]').forEach(function (button) {
        button.addEventListener('click', function () {
            if (body.classList.contains('sidebar-collapsed') && !isMobile()) {
                body.classList.remove('sidebar-collapsed');
                store.set('lpgSidebarCollapsed', '0');
            }
            var panel = document.getElementById(button.getAttribute('aria-controls'));
            if (!panel) return;
            var open = panel.classList.toggle('is-open');
            button.setAttribute('aria-expanded', open ? 'true' : 'false');
            if (open) setTimeout(function () { panel.scrollIntoView({ block: 'nearest', behavior: 'smooth' }); }, 30);
        });
    });
    var activeLink = document.querySelector('.app-nav-link.active');
    if (activeLink) setTimeout(function () { activeLink.scrollIntoView({ block: 'nearest' }); }, 50);

    // Menu search: filters items by label (and their group label) as you type.
    var navSearch = document.getElementById('navSearch');
    var nav = document.getElementById('appNav');
    if (navSearch && nav) {
        var empty = document.getElementById('navEmpty');
        navSearch.addEventListener('input', function () {
            var q = navSearch.value.trim().toLowerCase();
            var any = false;
            nav.classList.toggle('is-filtering', q !== '');
            nav.querySelectorAll('[data-nav-item]').forEach(function (li) {
                var group = li.closest('[data-nav-group-item]');
                var text = (li.textContent + ' ' + (group ? group.getAttribute('data-label') : '')).toLowerCase();
                var show = q === '' || text.indexOf(q) !== -1;
                li.hidden = !show;
                if (show) any = true;
            });
            nav.querySelectorAll('[data-nav-group-item]').forEach(function (group) {
                group.hidden = q !== '' && !group.querySelector('[data-nav-item]:not([hidden])');
            });
            if (empty) empty.style.display = any ? 'none' : 'block';
        });
        navSearch.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                var first = nav.querySelector('[data-nav-item]:not([hidden]) a');
                if (first && navSearch.value.trim() !== '') { e.preventDefault(); window.location.href = first.href; }
            } else if (e.key === 'Escape') {
                navSearch.value = '';
                navSearch.dispatchEvent(new Event('input'));
            }
        });
    }

    /* ---------- Theme (light / dark / auto, per browser; branch setting is the default) ---------- */
    var media = window.matchMedia ? window.matchMedia('(prefers-color-scheme: dark)') : null;
    function applyTheme(choice) {
        var mode = choice === 'auto' ? (media && media.matches ? 'dark' : 'light') : choice;
        root.setAttribute('data-bs-theme', mode);
        document.querySelectorAll('[data-theme-choice]').forEach(function (btn) {
            var on = btn.getAttribute('data-theme-choice') === choice;
            btn.classList.toggle('active', on);
            btn.setAttribute('aria-pressed', on ? 'true' : 'false');
        });
    }
    var themeChoice = store.get('lpgTheme') || root.getAttribute('data-default-theme') || 'light';
    applyTheme(themeChoice);
    document.querySelectorAll('[data-theme-choice]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            themeChoice = btn.getAttribute('data-theme-choice');
            store.set('lpgTheme', themeChoice);
            applyTheme(themeChoice);
        });
    });
    if (media && media.addEventListener) media.addEventListener('change', function () { if (themeChoice === 'auto') applyTheme('auto'); });

    /* ---------- Toasts ---------- */
    function toast(message, type) {
        var container = document.getElementById('toastContainer');
        if (!container || !window.bootstrap) return;
        var icons = { success: 'bi-check-circle-fill text-success', danger: 'bi-exclamation-octagon-fill text-danger', warning: 'bi-exclamation-triangle-fill text-warning', info: 'bi-info-circle-fill text-info' };
        var el = document.createElement('div');
        el.className = 'toast align-items-center';
        el.setAttribute('role', type === 'danger' ? 'alert' : 'status');
        el.setAttribute('aria-live', type === 'danger' ? 'assertive' : 'polite');
        el.innerHTML = '<div class="d-flex"><div class="toast-body d-flex gap-2 align-items-start"><i class="bi"></i><span></span></div>' +
            '<button type="button" class="btn-close me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button></div>';
        el.querySelector('.bi').className = 'bi ' + (icons[type] || icons.info);
        el.querySelector('span').textContent = message;
        container.appendChild(el);
        el.addEventListener('hidden.bs.toast', function () { el.remove(); });
        new window.bootstrap.Toast(el, { delay: type === 'danger' ? 8000 : 4500 }).show();
    }

    /* ---------- Confirm dialog ---------- */
    function confirmDialog(message, options) {
        options = options || {};
        return new Promise(function (resolve) {
            var modalEl = document.getElementById('confirmModal');
            if (!modalEl || !window.bootstrap) { resolve(window.confirm(message)); return; }
            modalEl.querySelector('[data-confirm-title]').textContent = options.title || 'Please confirm';
            modalEl.querySelector('[data-confirm-message]').textContent = message;
            var ok = modalEl.querySelector('[data-confirm-ok]');
            ok.textContent = options.okLabel || 'Confirm';
            ok.className = 'btn ' + (options.danger === false ? 'btn-primary' : 'btn-danger');
            var result = false;
            var modal = window.bootstrap.Modal.getOrCreateInstance(modalEl);
            var onOk = function () { result = true; modal.hide(); };
            ok.addEventListener('click', onOk, { once: true });
            modalEl.addEventListener('hidden.bs.modal', function () { ok.removeEventListener('click', onOk); resolve(result); }, { once: true });
            modal.show();
        });
    }

    // Forms or buttons with data-confirm="message" ask before submitting.
    document.addEventListener('submit', function (e) {
        var form = e.target;
        var submitter = e.submitter || null;
        var source = submitter && submitter.hasAttribute('data-confirm') ? submitter : form;
        var message = source.getAttribute && source.getAttribute('data-confirm');
        if (!message || form.dataset.confirmed === '1') return;
        e.preventDefault();
        e.stopImmediatePropagation();
        confirmDialog(message, { title: source.getAttribute('data-confirm-title'), okLabel: source.getAttribute('data-confirm-ok'), danger: source.getAttribute('data-confirm-danger') !== 'false' })
            .then(function (yes) {
                if (!yes) return;
                form.dataset.confirmed = '1';
                if (form.requestSubmit) form.requestSubmit(submitter && form.contains(submitter) ? submitter : undefined); else form.submit();
                setTimeout(function () { delete form.dataset.confirmed; }, 0);
            });
    }, true);

    /* ---------- Double-submit guard for POST forms that navigate ---------- */
    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (e.defaultPrevented || (form.method || '').toLowerCase() !== 'post' || form.target || form.hasAttribute('data-no-loading')) return;
        var button = e.submitter || form.querySelector('[type="submit"], button:not([type])');
        if (!button || button.classList.contains('is-loading')) return;
        // Defer so the submitter's name/value are already part of the submission.
        setTimeout(function () {
            button.classList.add('is-loading');
            button.setAttribute('aria-disabled', 'true');
            button.dataset.originalHtml = button.innerHTML;
            button.innerHTML = '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>' + button.innerHTML;
            // Re-enable if the page did not navigate (e.g. a file download).
            setTimeout(function () { restore(button); }, 10000);
        }, 0);
    });
    function restore(button) {
        if (!button.classList.contains('is-loading')) return;
        button.classList.remove('is-loading');
        button.removeAttribute('aria-disabled');
        if (button.dataset.originalHtml !== undefined) button.innerHTML = button.dataset.originalHtml;
    }
    window.addEventListener('pageshow', function () { document.querySelectorAll('.is-loading').forEach(restore); });

    /* ---------- Misc ---------- */
    // Stop the mouse wheel from changing a focused number input.
    document.addEventListener('wheel', function (event) {
        var field = event.target instanceof Element ? event.target.closest('input[type="number"]') : null;
        if (field && document.activeElement === field) field.blur();
    }, { capture: true, passive: true });

    window.LPG = Object.assign(window.LPG || {}, { toast: toast, confirm: confirmDialog });

    if (window.jQuery && window.jQuery.fn.DataTable) {
        // order: [] keeps the server's row order (often newest first) until the user sorts a column.
        window.jQuery('.datatable').DataTable({ pageLength: 25, order: [] });
    }
})();
