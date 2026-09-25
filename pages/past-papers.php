<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();

$user = current_user();
$uid = (int)$user['id'];
$conn = db();

if (is_student()) {
    $stmt = $conn->prepare("SELECT pp.*, c.code, c.title AS course_title
                            FROM past_papers pp
                            JOIN courses c ON c.id = pp.course_id
                            JOIN enrollments e ON e.course_id = c.id AND e.user_id = ?
                            ORDER BY c.title, pp.year DESC");
    $stmt->bind_param('i', $uid);
} else {
    $stmt = $conn->prepare("SELECT pp.*, c.code, c.title AS course_title
                            FROM past_papers pp
                            JOIN courses c ON c.id = pp.course_id
                            WHERE c.instructor_id = ?
                            ORDER BY c.title, pp.year DESC");
    $stmt->bind_param('i', $uid);
}
$stmt->execute();
$papers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$grouped = [];
foreach ($papers as $p) $grouped[$p['course_title']][] = $p;

$pageTitle = 'Past papers';
$extraCSS = ['courses.css'];
include __DIR__ . '/../includes/header.php';
?>

<?php if (!$grouped): ?>
    <p class="empty">No past papers available yet.</p>
<?php else: ?>
    <?php foreach ($grouped as $courseTitle => $list): ?>
        <section class="panel">
            <header class="panel__head"><h3><?php echo e($courseTitle); ?></h3></header>
            <ul class="paper-list">
                <?php foreach ($list as $p): ?>
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
        </section>
    <?php endforeach; ?>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>