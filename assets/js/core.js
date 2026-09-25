/* =========================================================
   EduSphere LMS — Core scripts (loaded on every page)
   ========================================================= */
(function () {
    'use strict';

    /* Sidebar toggle on mobile */
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebar = document.getElementById('sidebar');
    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', function () {
            sidebar.classList.toggle('is-open');
        });
        document.addEventListener('click', function (e) {
            if (window.innerWidth <= 900 &&
                sidebar.classList.contains('is-open') &&
                !sidebar.contains(e.target) &&
                !sidebarToggle.contains(e.target)) {
                sidebar.classList.remove('is-open');
            }
        });
    }

    /* User menu toggle */
    const userChip = document.getElementById('userChip');
    const userMenu = document.getElementById('userMenu');
    if (userChip && userMenu) {
        userChip.addEventListener('click', function (e) {
            e.stopPropagation();
            userMenu.classList.toggle('is-open');
        });
        document.addEventListener('click', function () {
            userMenu.classList.remove('is-open');
        });
    }

    /* Auto-dismiss flash bar */
    const flash = document.getElementById('flashBar');
    if (flash) {
        setTimeout(function () {
            flash.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
            flash.style.opacity = '0';
            flash.style.transform = 'translateX(20px)';
            setTimeout(function () { flash.remove(); }, 320);
        }, 4000);
    }

    /* Tabs */
    document.querySelectorAll('[data-tabs]').forEach(function (tabsEl) {
        const tabs = tabsEl.querySelectorAll('.tab');
        tabs.forEach(function (tab) {
            tab.addEventListener('click', function () {
                const name = tab.dataset.tab;
                tabs.forEach(function (t) { t.classList.remove('is-active'); });
                tab.classList.add('is-active');
                document.querySelectorAll('[data-panel]').forEach(function (p) {
                    p.classList.toggle('is-active', p.dataset.panel === name);
                });
            });
        });
    });

    /* Password toggle (login / register) */
    document.querySelectorAll('[data-password-toggle]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const wrap = btn.closest('.field__password');
            if (!wrap) return;
            const input = wrap.querySelector('input');
            if (!input) return;
            if (input.type === 'password') {
                input.type = 'text';
                btn.setAttribute('aria-label', 'Hide password');
            } else {
                input.type = 'password';
                btn.setAttribute('aria-label', 'Show password');
            }
        });
    });
})();