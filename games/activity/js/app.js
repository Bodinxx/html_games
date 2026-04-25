/**
 * app.js – Core application utilities
 * Exposed as window.App
 */
(function () {
    'use strict';

    const LOGOUT_TIMEOUT_MS = 24 * 60 * 60 * 1000; // 24 hours
    let logoutTimerInterval = null;

    /* ── Toast notifications ── */
    function ensureToastContainer() {
        let container = document.getElementById('toast-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'toast-container';
            document.body.appendChild(container);
        }
        return container;
    }

    const TOAST_ICONS = {
        success: '✓',
        error:   '✕',
        warning: '⚠',
        info:    'ℹ',
    };

    function showToast(msg, type) {
        type = type || 'info';
        const container = ensureToastContainer();
        const toast = document.createElement('div');
        toast.className = 'toast ' + type;
        toast.innerHTML =
            '<span class="toast-icon">' + (TOAST_ICONS[type] || 'ℹ') + '</span>' +
            '<span class="toast-message">' + escapeHtml(msg) + '</span>' +
            '<button class="toast-close" aria-label="Close">&times;</button>';

        toast.querySelector('.toast-close').addEventListener('click', function () {
            removeToast(toast);
        });

        container.appendChild(toast);

        const autoRemoveMs = type === 'error' ? 6000 : 4000;
        setTimeout(function () { removeToast(toast); }, autoRemoveMs);
    }

    function removeToast(toast) {
        if (!toast.parentNode) return;
        toast.classList.add('removing');
        setTimeout(function () {
            if (toast.parentNode) toast.parentNode.removeChild(toast);
        }, 300);
    }

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.appendChild(document.createTextNode(String(str)));
        return div.innerHTML;
    }

    /* ── Theme ── */
    function applyTheme(themeName) {
        document.body.dataset.theme = themeName || 'dark';
        try { localStorage.setItem('apt_theme', themeName); } catch (_) {}
    }

    /* ── Fetch wrapper ── */
    function fetchJSON(url, data) {
        const opts = {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data || {}),
            credentials: 'same-origin',
        };
        return fetch(url, opts).then(function (res) {
            return res.text().then(function (text) {
                var json;
                try {
                    json = JSON.parse(text);
                } catch (e) {
                    // Server returned non-JSON (e.g. a PHP warning prepended to output).
                    // Surface a structured error so callers see a friendly message
                    // rather than triggering the generic "Network error" catch path.
                    json = { error: 'Unexpected server response' };
                }
                json._status = res.status;
                return json;
            });
        });
    }

    /* ── Logout timer ── */
    function startLogoutTimer() {
        const now = Date.now();
        try { localStorage.setItem('apt_login_time', now); } catch (_) {}

        if (logoutTimerInterval) clearInterval(logoutTimerInterval);

        logoutTimerInterval = setInterval(function () {
            let loginTime;
            try { loginTime = parseInt(localStorage.getItem('apt_login_time'), 10); } catch (_) { return; }
            if (!loginTime) return;

            if (Date.now() - loginTime >= LOGOUT_TIMEOUT_MS) {
                clearInterval(logoutTimerInterval);
                fetchJSON('./api/auth.php', { action: 'logout' }).finally(function () {
                    try { localStorage.removeItem('apt_login_time'); } catch (_) {}
                    window.location.href = './index.php?reason=timeout';
                });
            }
        }, 60000); // check every minute
    }

    /* ── Auth init ── */
    function initAuth(options) {
        options = options || {};
        const publicPages = ['index.php', ''];
        const currentPage = window.location.pathname.split('/').pop() || 'index.php';
        const isPublic    = publicPages.indexOf(currentPage) !== -1;

        // Apply stored theme immediately to avoid flash
        let storedTheme;
        try { storedTheme = localStorage.getItem('apt_theme'); } catch (_) {}
        if (storedTheme) applyTheme(storedTheme);

        return fetchJSON('./api/auth.php', { action: 'check' }).then(function (data) {
            if (data.logged_in) {
                applyTheme(data.theme || 'dark');
                try { localStorage.setItem('apt_theme', data.theme || 'dark'); } catch (_) {}

                // Update username display if element exists
                const userEl = document.getElementById('navbar-username');
                if (userEl) userEl.textContent = data.username;

                const roleEl = document.getElementById('navbar-role');
                if (roleEl) roleEl.textContent = data.role;

                // Show admin link if admin
                if (data.role === 'admin') {
                    document.querySelectorAll('.admin-only').forEach(function (el) {
                        el.style.display = '';
                    });
                }

                if (options.onLogin) options.onLogin(data);
            } else {
                if (!isPublic) {
                    window.location.href = './index.php';
                }
                if (options.onNotLoggedIn) options.onNotLoggedIn();
            }
            return data;
        }).catch(function () {
            if (!isPublic) window.location.href = './index.php';
        });
    }

    /* ── Navbar toggle init ── */
    function initNavbarToggle() {
        var toggle = document.getElementById('navbar-toggle');
        var navbar = document.querySelector('.navbar');
        var navMenu = document.querySelector('.navbar-nav');
        if (!toggle || !navbar || !navMenu) return;
        
        toggle.addEventListener('click', function () {
            var isExpanded = navbar.classList.contains('expanded');
            navbar.classList.toggle('expanded');
            toggle.setAttribute('aria-expanded', !isExpanded);
        });
        
        // Close menu when a nav link is clicked
        navMenu.querySelectorAll('a').forEach(function(link) {
            link.addEventListener('click', function() {
                navbar.classList.remove('expanded');
                toggle.setAttribute('aria-expanded', 'false');
            });
        });
        
        // Close menu when clicking outside
        document.addEventListener('click', function(event) {
            if (!navbar.contains(event.target)) {
                navbar.classList.remove('expanded');
                toggle.setAttribute('aria-expanded', 'false');
            }
        });
    }

    document.addEventListener('DOMContentLoaded', initNavbarToggle);

    /* ── Expose ── */
    window.App = {
        initAuth:         initAuth,
        startLogoutTimer: startLogoutTimer,
        applyTheme:       applyTheme,
        showToast:        showToast,
        fetchJSON:        fetchJSON,
        escapeHtml:       escapeHtml,
    };
})();
