<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();

$user = current_user();
$uid = (int)$user['id'];
$cid = (int)($_GET['id'] ?? 0);
$conn = db();

$stmt = $conn->prepare("SELECT c.*, u.full_name AS instructor_name, u.id AS instructor_id, d.name AS dept_name
                        FROM courses c
                        JOIN users u ON u.id=c.instructor_id
                        LEFT JOIN departments d ON d.id=c.department_id
                        WHERE c.id = ?");
$stmt->bind_param('i', $cid); $stmt->execute();
$course = $stmt->get_result()->fetch_assoc();
if (!$course) { set_flash('error','Course not found.'); redirect(SITE_URL . '/pages/courses.php'); }

$isOwner = ((int)$course['instructor_id'] === $uid) || is_admin();
$enrolled = false; $progress = 0;
if (is_student()) {
    $stmt = $conn->prepare("SELECT progress_percentage FROM enrollments WHERE user_id=? AND course_id=?");
    $stmt->bind_param('ii', $uid, $cid); $stmt->execute();
    if ($row = $stmt->get_result()->fetch_assoc()) { $enrolled = true; $progress = (int)$row['progress_percentage']; }
}

$stmt = $conn->prepare("SELECT * FROM lessons WHERE course_id=? ORDER BY lesson_order, id");
$stmt->bind_param('i', $cid); $stmt->execute();
$lessons = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$done = [];
if ($enrolled) {
    $stmt = $conn->prepare("SELECT lp.lesson_id FROM lesson_progress lp
        JOIN lessons l ON l.id=lp.lesson_id
        WHERE lp.user_id=? AND l.course_id=? AND lp.completed=1");
    $stmt->bind_param('ii', $uid, $cid); $stmt->execute();
    $r = $stmt->get_result();
    while ($x = $r->fetch_assoc()) $done[(int)$x['lesson_id']] = true;
}

$stmt = $conn->prepare("SELECT a.*, s.id AS submission_id, s.grade
    FROM assignments a LEFT JOIN submissions s ON s.assignment_id=a.id AND s.user_id=?
    WHERE a.course_id=? AND a.is_published=1 ORDER BY a.due_date ASC");
$stmt->bind_param('ii', $uid, $cid); $stmt->execute();
$assignments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$stmt = $conn->prepare("SELECT x.*, xa.score, xa.status
    FROM exams x LEFT JOIN exam_attempts xa ON xa.exam_id=x.id AND xa.user_id=?
    WHERE x.course_id=? AND x.is_published=1 ORDER BY x.start_at ASC");
$stmt->bind_param('ii', $uid, $cid); $stmt->execute();
$exams = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$stmt = $conn->prepare("SELECT * FROM past_papers WHERE course_id=? ORDER BY year DESC, id DESC");
$stmt->bind_param('i', $cid); $stmt->execute();
$papers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$stmt = $conn->prepare("SELECT n.*, u.full_name AS uploader FROM notes n
    JOIN users u ON u.id=n.uploaded_by WHERE n.course_id=? ORDER BY n.created_at DESC");
$stmt->bind_param('i', $cid); $stmt->execute();
$notes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$roster = [];
if ($isOwner) {
    $stmt = $conn->prepare("SELECT u.id, u.full_name, u.email, u.admission_no, u.avatar,
                                   e.progress_percentage, e.enrolled_at
        FROM enrollments e JOIN users u ON u.id=e.user_id
        WHERE e.course_id=? ORDER BY u.full_name");
    $stmt->bind_param('i', $cid); $stmt->execute();
    $roster = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

$pageTitle = $course['title'];
$extraCSS = ['courses.css'];
$extraJS = ['courses.js'];
include __DIR__ . '/../includes/header.php';
?>

<div class="course-hero">
    <?php if (!empty($course['thumbnail']) && file_exists(UPLOAD_DIR . $course['thumbnail'])): ?>
        <img class="course-hero__thumb" src="<?php echo e(UPLOAD_URL . $course['thumbnail']); ?>" alt="">
    <?php endif; ?>
    <div class="course-hero__info">
        <span class="course-hero__code"><?php echo e($course['code']); ?> · <?php echo e($course['dept_name'] ?: 'General'); ?></span>
        <h2><?php echo e($course['title']); ?></h2>
        <p><?php echo e($course['description']); ?></p>
        <p class="course-hero__meta">
            Instructor <?php echo e($course['instructor_name']); ?> ·
            <?php echo e(ucfirst($course['level'])); ?> ·
            <?php echo (int)$course['duration_hours']; ?> hours
        </p>
    </div>
    <div class="course-hero__side">
        <?php if (is_student()): ?>
            <?php if ($enrolled): ?>
                <div class="course-hero__progress">
                    <span class="course-hero__progress-value"><?php echo $progress; ?>%</span>
                    <div class="progress"><div class="progress__bar" style="width:<?php echo $progress; ?>%"></div></div>
                    <span class="muted">Overall progress</span>
                </div>
            <?php else: ?>
                <button class="btn btn--primary js-enroll" data-course-id="<?php echo (int)$cid; ?>">Enroll in this course</button>
            <?php endif; ?>
        <?php elseif ($isOwner): ?>
            <a class="btn btn--primary" href="course-manage.php?id=<?php echo (int)$cid; ?>">Manage course</a>
        <?php endif; ?>
    </div>
</div>

<div class="tabs" data-tabs>
    <button class="tab is-active" data-tab="lessons">Lessons</button>
    <button class="tab" data-tab="notes">Notes</button>
    <button class="tab" data-tab="assignments">Assignments</button>
    <button class="tab" data-tab="exams">Exams</button>
    <button class="tab" data-tab="papers">Past papers</button>
    <?php if ($isOwner): ?>
        <button class="tab" data-tab="roster">Enrolled students</button>
    <?php endif; ?>
</div>

<section class="tab-panel is-active" data-panel="lessons">
    <?php if (!$lessons): ?><p class="empty">No lessons added yet.</p>
    <?php else: ?>
        <ul class="lesson-list">
            <?php foreach ($lessons as $i => $l): ?>
                <li class="lesson <?php echo isset($done[(int)$l['id']]) ? 'is-done' : ''; ?>">
                    <div class="lesson__index"><?php echo $i + 1; ?></div>
                    <div class="lesson__body">
                        <h4><?php echo e($l['title']); ?></h4>
                        <p class="muted"><?php echo (int)$l['duration_minutes']; ?> minutes</p>
                        <?php if (!empty($l['video_url'])): ?>
                            <div class="video-embed"><iframe src="<?php echo e($l['video_url']); ?>" allowfullscreen loading="lazy"></iframe></div>
                        <?php endif; ?>
                        <?php if (!empty($l['content'])): ?><p class="lesson__text"><?php echo nl2br(e($l['content'])); ?></p><?php endif; ?>
                        <?php if (!empty($l['attachment'])): ?>
                            <a class="link" href="<?php echo e(UPLOAD_URL . $l['attachment']); ?>" download>Download lesson material</a>
                        <?php endif; ?>
                        <?php if ($enrolled): ?>
                            <button class="btn btn--ghost js-toggle-lesson"
                                data-lesson-id="<?php echo (int)$l['id']; ?>"
                                data-course-id="<?php echo (int)$cid; ?>"
                                data-done="<?php echo isset($done[(int)$l['id']]) ? '1' : '0'; ?>">
                                <?php echo isset($done[(int)$l['id']]) ? 'Mark as not complete' : 'Mark as complete'; ?>
                            </button>
                        <?php endif; ?>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>

<section class="tab-panel" data-panel="notes">
    <?php if (!$notes): ?><p class="empty">No notes uploaded for this course yet.</p>
    <?php else: ?>
        <ul class="paper-list">
            <?php foreach ($notes as $n): ?>
                <li>
                    <div>
                        <strong><?php echo e($n['title']); ?></strong>
                        <p class="muted"><?php echo e($n['description'] ?: 'No description'); ?> ·
                            <?php echo e(human_size($n['file_size'])); ?> · <?php echo e(time_ago($n['created_at'])); ?></p>
                    </div>
                    <a class="btn btn--ghost btn--sm" href="<?php echo e(UPLOAD_URL . $n['file_path']); ?>" download>Download</a>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>

<section class="tab-panel" data-panel="assignments">
    <?php if (!$assignments): ?><p class="empty">No assignments yet.</p>
    <?php else: ?>
        <ul class="assignment-list">
            <?php foreach ($assignments as $a): 
                $overdue = $a['due_date'] && strtotime($a['due_date']) < time() && !$a['submission_id'];
            ?>
                <li class="assignment-row">
                    <div>
                        <a class="assignment-row__title" href="assignment-view.php?id=<?php echo (int)$a['id']; ?>"><?php echo e($a['title']); ?></a>
                        <p class="muted">Due <?php echo e(fmt_datetime($a['due_date'])); ?> · <?php echo (int)$a['max_points']; ?> points
                            <?php if ($overdue): ?> · <span class="text-danger">Overdue</span><?php endif; ?>
                        </p>
                    </div>
                    <div class="assignment-row__status">
                        <?php if ($a['submission_id']): ?>
                            <?php if ($a['grade'] !== null): ?>
                                <span class="badge badge--success">Graded <?php echo (int)$a['grade']; ?>/<?php echo (int)$a['max_points']; ?></span>
                            <?php else: ?>
                                <span class="badge">Submitted</span>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="badge badge--warn">Pending</span>
                        <?php endif; ?>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>

<section class="tab-panel" data-panel="exams">
    <?php if (!$exams): ?><p class="empty">No exams scheduled.</p>
    <?php else: ?>
        <ul class="assignment-list">
            <?php foreach ($exams as $x): ?>
                <li class="assignment-row">
                    <div>
                        <a class="assignment-row__title" href="exam-take.php?id=<?php echo (int)$x['id']; ?>"><?php echo e($x['title']); ?></a>
                        <p class="muted"><?php echo (int)$x['duration_minutes']; ?> min · <?php echo (int)$x['total_points']; ?> points</p>
                    </div>
                    <div class="assignment-row__status">
                        <?php if ($x['status'] === 'submitted' || $x['status'] === 'graded'): ?>
                            <span class="badge badge--success">Score <?php echo (int)$x['score']; ?></span>
                        <?php else: ?>
                            <a class="btn btn--primary btn--sm" href="exam-take.php?id=<?php echo (int)$x['id']; ?>">Start exam</a>
                        <?php endif; ?>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>

<section class="tab-panel" data-panel="papers">
    <?php if (!$papers): ?><p class="empty">No past papers uploaded.</p>
    <?php else: ?>
        <ul class="paper-list">
            <?php foreach ($papers as $p): ?>
                <li>
                    <div>
                        <strong><?php echo e($p['title']); ?></strong>
                        <p class="muted"><?php echo (int)$p['year']; ?> · <?php echo e(strtoupper($p['exam_type'])); ?></p>
                    </div>
                    <?php if ($p['file_path']): ?>
                        <a class="btn btn--ghost btn--sm" href="<?php echo e(UPLOAD_URL . $p['file_path']); ?>" download>Download</a>
                    <?php elseif ($p['external_url']): ?>
                        <a class="btn btn--ghost btn--sm" href="<?php echo e($p['external_url']); ?>" target="_blank" rel="noopener">Open</a>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>

<?php if ($isOwner): ?>
<section class="tab-panel" data-panel="roster">
    <?php if (!$roster): ?><p class="empty">No students enrolled yet.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Name</th><th>Admission</th><th>Email</th><th>Progress</th><th>Enrolled</th></tr></thead>
                <tbody>
                <?php foreach ($roster as $r): ?>
                    <tr>
                        <td><?php echo e($r['full_name']); ?></td>
                        <td><?php echo e($r['admission_no'] ?: '—'); ?></td>
                        <td><?php echo e($r['email']); ?></td>
                        <td><?php echo (int)$r['progress_percentage']; ?>%</td>
                        <td><?php echo e(fmt_date($r['enrolled_at'])); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>