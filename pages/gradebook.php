<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();

$user = current_user();
$uid = (int)$user['id'];
$conn = db();

if (is_student()) {
    $stmt = $conn->prepare("SELECT a.title AS assignment_title, a.max_points, c.title AS course_title,
                                   s.grade, s.feedback, s.submitted_at, s.graded_at
                            FROM submissions s
                            JOIN assignments a ON a.id = s.assignment_id
                            JOIN courses c ON c.id = a.course_id
                            WHERE s.user_id = ? AND s.grade IS NOT NULL
                            ORDER BY s.graded_at DESC");
    $stmt->bind_param('i', $uid); $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} else {
    $stmt = $conn->prepare("SELECT s.id, s.grade, s.feedback, s.graded_at,
                                   a.title AS assignment_title, a.max_points, a.id AS assignment_id,
                                   c.title AS course_title,
                                   u.full_name AS student_name, u.admission_no
                            FROM submissions s
                            JOIN assignments a ON a.id = s.assignment_id
                            JOIN courses c ON c.id = a.course_id
                            JOIN users u ON u.id = s.user_id
                            WHERE c.instructor_id = ?
                            ORDER BY s.submitted_at DESC");
    $stmt->bind_param('i', $uid); $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

$pageTitle = 'Gradebook';
$extraCSS = ['assignments.css'];
include __DIR__ . '/../includes/header.php';
?>

<section class="panel">
    <header class="panel__head"><h3><?php echo is_student() ? 'Your graded work' : 'Student grades'; ?></h3></header>
    <?php if (!$rows): ?>
        <p class="empty">Nothing graded yet.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <?php if (is_student()): ?>
                    <thead><tr><th>Course</th><th>Assignment</th><th>Score</th><th>Feedback</th><th>Graded</th></tr></thead>
                    <tbody>
                    <?php foreach ($rows as $r): ?>
                        <tr>
                            <td><?php echo e($r['course_title']); ?></td>
                            <td><?php echo e($r['assignment_title']); ?></td>
                            <td><?php echo (int)$r['grade']; ?>/<?php echo (int)$r['max_points']; ?></td>
                            <td><?php echo e($r['feedback'] ?? ''); ?></td>
                            <td><?php echo e(fmt_date($r['graded_at'])); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                <?php else: ?>
                    <thead><tr><th>Student</th><th>Admission</th><th>Course</th><th>Assignment</th><th>Score</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($rows as $r): ?>
                        <tr>
                            <td><?php echo e($r['student_name']); ?></td>
                            <td><?php echo e($r['admission_no'] ?: '—'); ?></td>
                            <td><?php echo e($r['course_title']); ?></td>
                            <td><?php echo e($r['assignment_title']); ?></td>
                            <td><?php echo $r['grade'] !== null ? (int)$r['grade'] . '/' . (int)$r['max_points'] : '<span class="muted">Ungraded</span>'; ?></td>
                            <td><a class="btn btn--ghost btn--sm" href="assignment-manage.php?id=<?php echo (int)$r['assignment_id']; ?>#sub-<?php echo (int)$r['id']; ?>">Open</a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                <?php endif; ?>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>