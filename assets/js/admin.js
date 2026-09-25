/* EduSphere LMS — Admin bulk selection */
(function () {
    'use strict';
    const selectAll = document.getElementById('bulkSelectAll');
    if (!selectAll) return;

    const rowChecks = document.querySelectorAll('.js-row-check');

    selectAll.addEventListener('change', function () {
        rowChecks.forEach(function (cb) { cb.checked = selectAll.checked; });
    });

    rowChecks.forEach(function (cb) {
        cb.addEventListener('change', function () {
            const allChecked = Array.prototype.every.call(rowChecks, c => c.checked);
            const anyChecked = Array.prototype.some.call(rowChecks, c => c.checked);
            selectAll.checked = allChecked;
            selectAll.indeterminate = !allChecked && anyChecked;
        });
    });
})();