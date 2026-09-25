<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();

$user = current_user();
$uid = (int)$user['id'];
$conn = db();

if (is_student()) {
    $stmt = $conn->prepare("SELECT c.*, e.progress_percentage, e.enrolled_at,
                                   (SELECT COUNT(*) FROM lessons WHERE course_id = c.id) AS lessons,
                                   (SELECT COUNT(*) FROM lesson_progress lp JOIN lessons l ON lp.lesson_id = l.id
                                     WHERE l.course_id = c.id AND lp.user_id = ? AND lp.completed = 1) AS done
                            FROM enrollments e
                            JOIN courses c ON c.id = e.course_id
                            WHERE e.user_id = ?
                            ORDER BY e.enrolled_at DESC");
    $stmt->bind_param('ii', $uid, $uid); $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} else {
    $stmt = $conn->prepare("SELECT c.*,
                                   (SELECT AVG(progress_percentage) FROM enrollments WHERE course_id = c.id) AS progress_percentage,
                                   (SELECT COUNT(*) FROM enrollments WHERE course_id = c.id) AS enrolled,
                                   (SELECT COUNT(*) FROM lessons WHERE course_id = c.id) AS lessons,
                                   0 AS done
                            FROM courses c WHERE c.instructor_id = ?
                            ORDER BY c.title");
    $stmt->bind_param('i', $uid); $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

$pageTitle = 'Progress';
$extraCSS = ['dashboard.css'];
include __DIR__ . '/../includes/header.php';
?>

<?php if (!$rows): ?>
    <p class="empty">Nothing to show yet.</p>
<?php else: ?>
    <div class="progress-grid">
        <?php foreach ($rows as $r): ?>
            <article class="progress-card">
                <header>
                    <div>
                        <p class="muted"><?php echo e($r['code']); ?></p>
                        <h3><?php echo e($r['title']); ?></h3>
                    </div>
                </header>
                <div class="progress-card__meter">
                    <span><?php echo (int)round($r['progress_percentage']); ?>%</span>
                    <div class="progress"><div class="progress__bar" style="width:<?php echo (int)round($r['progress_percentage']); ?>%"></div></div>
                </div>
                <?php if (is_student()): ?>
                    <p class="muted"><?php echo (int)$r['done']; ?> of <?php echo (int)$r['lessons']; ?> lessons complete</p>
                <?php else: ?>
                    <p class="muted"><?php echo (int)$r['enrolled']; ?> students · <?php echo (int)$r['lessons']; ?> lessons</p>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>