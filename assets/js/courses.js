/* =========================================================
   EduSphere LMS — Course interactions
   Enrollment + lesson completion (fixed: uses window.APP_BASE
   which is injected by footer.php, so no more SITE_URL issues)
   ========================================================= */
(function () {
    'use strict';

    const base = window.APP_BASE || '';

    /* Enrollment */
    document.querySelectorAll('.js-enroll').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const courseId = btn.dataset.courseId;
            if (!courseId) return;

            const original = btn.textContent;
            btn.disabled = true;
            btn.textContent = 'Enrolling...';

            fetch(base + '/api/enroll.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ course_id: parseInt(courseId, 10) })
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.success) {
                    btn.textContent = 'Enrolled';
                    btn.classList.remove('btn--primary');
                    btn.classList.add('btn--secondary');
                    setTimeout(function () {
                        window.location.href = base + '/pages/course-view.php?id=' + courseId;
                    }, 500);
                } else {
                    btn.disabled = false;
                    btn.textContent = original;
                    alert(data.message || 'Enrollment failed.');
                }
            })
            .catch(function () {
                btn.disabled = false;
                btn.textContent = original;
                alert('Network error. Please try again.');
            });
        });
    });

    /* Lesson completion */
    document.querySelectorAll('.js-toggle-lesson').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const lessonId = parseInt(btn.dataset.lessonId, 10);
            const courseId = parseInt(btn.dataset.courseId, 10);
            const done = btn.dataset.done === '1';
            const nextState = !done;

            btn.disabled = true;
            const original = btn.textContent;
            btn.textContent = 'Saving...';

            fetch(base + '/api/lesson-complete.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ lesson_id: lessonId, course_id: courseId, completed: nextState })
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.success) {
                    btn.dataset.done = nextState ? '1' : '0';
                    btn.textContent = nextState ? 'Mark as not complete' : 'Mark as complete';
                    const lessonEl = btn.closest('.lesson');
                    if (lessonEl) lessonEl.classList.toggle('is-done', nextState);
                    const meter = document.querySelector('.course-hero__progress-value');
                    if (meter) meter.textContent = data.progress + '%';
                    const bar = document.querySelector('.course-hero__progress .progress__bar');
                    if (bar) bar.style.width = data.progress + '%';
                } else {
                    btn.textContent = original;
                    alert(data.message || 'Update failed.');
                }
            })
            .catch(function () {
                btn.textContent = original;
                alert('Network error.');
            })
            .finally(function () { btn.disabled = false; });
        });
    });
})();