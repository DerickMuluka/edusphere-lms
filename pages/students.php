<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['instructor','admin']);

$user = current_user();
$uid = (int)$user['id'];
$conn = db();

$courseId = (int)($_GET['course_id'] ?? 0);

$stmt = $conn->prepare("SELECT * FROM courses WHERE instructor_id=? ORDER BY title");
$stmt->bind_param('i', $uid); $stmt->execute();
$courses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$rows = [];
if ($courseId) {
    $stmt = $conn->prepare("SELECT u.id, u.full_name, u.username, u.email, u.admission_no,
                                   e.progress_percentage, e.enrolled_at, e.completed_at,
                                   (SELECT COUNT(*) FROM submissions s
                                    JOIN assignments a ON a.id = s.assignment_id
                                    WHERE a.course_id = ? AND s.user_id = u.id) AS submitted,
                                   (SELECT COUNT(*) FROM assignments WHERE course_id = ? AND is_published = 1) AS total_assignments
                            FROM enrollments e
                            JOIN users u ON u.id = e.user_id
                            JOIN courses c ON c.id = e.course_id
                            WHERE e.course_id = ? AND c.instructor_id = ?
                            ORDER BY u.full_name");
    $stmt->bind_param('iiii', $courseId, $courseId, $courseId, $uid);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

$pageTitle = 'Enrolled students';
$extraCSS = ['admin.css'];
include __DIR__ . '/../includes/header.php';
?>

<section class="panel">
    <header class="panel__head"><h3>Select a course</h3></header>
    <form method="get" class="form form--inline">
        <label class="field">
            <span class="field__label">Course</span>
            <select class="field__input" name="course_id" onchange="this.form.submit()">
                <option value="0">— choose —</option>
                <?php foreach ($courses as $c): ?>
                    <option value="<?php echo (int)$c['id']; ?>" <?php echo $courseId === (int)$c['id'] ? 'selected' : ''; ?>>
                        <?php echo e($c['code'] . ' — ' . $c['title']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <noscript><button class="btn btn--primary" type="submit">Show</button></noscript>
    </form>
</section>

<?php if ($courseId && !$rows): ?>
    <p class="empty">No students enrolled in this course yet.</p>
<?php elseif ($rows): ?>
    <section class="panel">
        <header class="panel__head"><h3>Students (<?php echo count($rows); ?>)</h3></header>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Name</th><th>Admission</th><th>Username</th><th>Email</th>
                        <th>Progress</th><th>Submissions</th><th>Enrolled</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td><?php echo e($r['full_name']); ?></td>
                        <td><?php echo e($r['admission_no'] ?: '—'); ?></td>
                        <td><?php echo e($r['username']); ?></td>
                        <td><?php echo e($r['email']); ?></td>
                        <td><?php echo (int)$r['progress_percentage']; ?>%</td>
                        <td><?php echo (int)$r['submitted']; ?>/<?php echo (int)$r['total_assignments']; ?></td>
                        <td><?php echo e(fmt_date($r['enrolled_at'])); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>