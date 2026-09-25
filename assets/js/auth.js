/* =========================================================
   EduSphere LMS — Auth helper (small, page-scoped)
   Password visibility is handled by core.js; this file only
   adds inline form validation for the auth pages.
   ========================================================= */
(function () {
    'use strict';
    document.querySelectorAll('.auth-card form').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            const password = form.querySelector('input[name="password"]');
            const confirm = form.querySelector('input[name="confirm_password"]');
            if (password && confirm && password.value !== confirm.value) {
                e.preventDefault();
                alert('Passwords do not match.');
            }
        });
    });
})();