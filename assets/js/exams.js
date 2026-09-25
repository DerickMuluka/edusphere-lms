/* assets/js/exams.js */
(function () {
    'use strict';
    const shell = document.querySelector('[data-exam-duration]');
    const timerEl = document.getElementById('examTimer');
    if (!shell || !timerEl) return;
    const minutes = parseInt(shell.dataset.examDuration, 10) || 0;
    let remaining = minutes * 60;

    function tick() {
        if (remaining < 0) { remaining = 0; }
        const m = String(Math.floor(remaining / 60)).padStart(2, '0');
        const s = String(remaining % 60).padStart(2, '0');
        timerEl.textContent = m + ':' + s;
        if (remaining === 0) {
            const form = document.getElementById('examForm');
            if (form) form.submit();
            return;
        }
        remaining -= 1;
        setTimeout(tick, 1000);
    }
    tick();
})();