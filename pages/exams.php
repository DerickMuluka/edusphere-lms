<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();

$user = current_user();
$uid = (int)$user['id'];
$conn = db();

if (is_student()) {
    $stmt = $conn->prepare("SELECT x.*, c.title AS course_title, xa.status, xa.score
                            FROM exams x
                            JOIN courses c ON c.id = x.course_id
                            JOIN enrollments e ON e.course_id = c.id AND e.user_id = ?
                            LEFT JOIN exam_attempts xa ON xa.exam_id = x.id AND xa.user_id = ?
                            WHERE x.is_published = 1 ORDER BY x.start_at ASC");
    $stmt->bind_param('ii', $uid, $uid);
} else {
    $stmt = $conn->prepare("SELECT x.*, c.title AS course_title, NULL AS status, NULL AS score
                            FROM exams x JOIN courses c ON c.id = x.course_id
                            WHERE c.instructor_id = ? ORDER BY x.created_at DESC");
    $stmt->bind_param('i', $uid);
}
$stmt->execute();
$exams = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'Exams';
$extraCSS = ['exams.css'];
include __DIR__ . '/../includes/header.php';
?>

<div class="toolbar">
    <p class="muted"><?php echo is_student() ? 'Exams available in your enrolled courses.' : 'Exams you have scheduled for your courses.'; ?></p>
    <?php if (is_instructor()): ?>
        <a class="btn btn--primary" href="exam-manage.php">Create exam</a>
    <?php endif; ?>
</div>

<?php if (!$exams): ?>
    <p class="empty">No exams available.</p>
<?php else: ?>
    <ul class="assignment-list">
        <?php foreach ($exams as $x): ?>
            <li class="assignment-row">
                <div>
                    <strong><?php echo e($x['title']); ?></strong>
                    <p class="muted"><?php echo e($x['course_title']); ?> · <?php echo (int)$x['duration_minutes']; ?> min · <?php echo (int)$x['total_points']; ?> points</p>
                    <p class="muted"><?php echo e(fmt_datetime($x['start_at'])); ?> — <?php echo e(fmt_datetime($x['end_at'])); ?></p>
                </div>
                <div class="assignment-row__status">
                    <?php if (is_student()): ?>
                        <?php if ($x['status'] === 'submitted' || $x['status'] === 'graded'): ?>
                            <span class="badge badge--success">Score <?php echo (int)$x['score']; ?></span>
                        <?php else: ?>
                            <a class="btn btn--primary btn--sm" href="exam-take.php?id=<?php echo (int)$x['id']; ?>">Start</a>
                        <?php endif; ?>
                    <?php else: ?>
                        <a class="btn btn--ghost btn--sm" href="exam-manage.php?id=<?php echo (int)$x['id']; ?>">Manage</a>
                    <?php endif; ?>
                </div>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>