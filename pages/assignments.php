<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();

$user = current_user();
$uid = (int)$user['id'];
$conn = db();

$filter = clean($_GET['filter'] ?? 'all');
$params = [$uid, $uid]; $types = 'ii';

if (is_student()) {
    $sql = "SELECT a.*, c.title AS course_title, c.id AS course_id,
                   s.id AS submission_id, s.grade, s.submitted_at, s.is_late
            FROM assignments a
            JOIN courses c ON c.id=a.course_id
            JOIN enrollments e ON e.course_id=c.id AND e.user_id=?
            LEFT JOIN submissions s ON s.assignment_id=a.id AND s.user_id=?
            WHERE a.is_published=1";
} else {
    $sql = "SELECT a.*, c.title AS course_title, c.id AS course_id,
                   NULL AS submission_id, NULL AS grade, NULL AS submitted_at, NULL AS is_late
            FROM assignments a
            JOIN courses c ON c.id=a.course_id
            WHERE c.instructor_id=? AND 1=?"; 
}
if ($filter === 'pending' && is_student()) $sql .= " AND s.id IS NULL";
if ($filter === 'submitted') $sql .= " AND s.id IS NOT NULL AND s.grade IS NULL";
if ($filter === 'graded') $sql .= " AND s.grade IS NOT NULL";
$sql .= " ORDER BY a.due_date ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params); $stmt->execute();
$assignments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'Assignments';
$extraCSS = ['assignments.css'];
include __DIR__ . '/../includes/header.php';
?>

<div class="toolbar">
    <div class="chip-row">
        <?php foreach (['all'=>'All','pending'=>'Pending','submitted'=>'Submitted','graded'=>'Graded'] as $k=>$label): ?>
            <a class="chip <?php echo $filter === $k ? 'is-active' : ''; ?>" href="?filter=<?php echo $k; ?>"><?php echo $label; ?></a>
        <?php endforeach; ?>
    </div>
    <?php if (is_staff()): ?>
        <a class="btn btn--primary" href="assignment-manage.php">Create assignment</a>
    <?php endif; ?>
</div>

<?php if (!$assignments): ?>
    <p class="empty">No assignments found.</p>
<?php else: ?>
    <ul class="assignment-list">
        <?php foreach ($assignments as $a): ?>
            <li class="assignment-row">
                <div>
                    <a class="assignment-row__title" href="assignment-view.php?id=<?php echo (int)$a['id']; ?>"><?php echo e($a['title']); ?></a>
                    <p class="muted"><?php echo e($a['course_title']); ?> · due <?php echo e(fmt_datetime($a['due_date'])); ?> · <?php echo (int)$a['max_points']; ?> pts</p>
                </div>
                <div class="assignment-row__status">
                    <?php if (is_student()): ?>
                        <?php if ($a['submission_id']): ?>
                            <?php if ($a['grade'] !== null): ?>
                                <span class="badge badge--success"><?php echo (int)$a['grade']; ?>/<?php echo (int)$a['max_points']; ?></span>
                            <?php else: ?>
                                <span class="badge">Awaiting grade</span>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="badge badge--warn">Pending</span>
                        <?php endif; ?>
                    <?php else: ?>
                        <a class="btn btn--ghost btn--sm" href="assignment-manage.php?id=<?php echo (int)$a['id']; ?>">Open</a>
                    <?php endif; ?>
                </div>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>