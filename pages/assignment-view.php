<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();

$user = current_user();
$uid = (int)$user['id'];
$aid = (int)($_GET['id'] ?? 0);
$conn = db();

$stmt = $conn->prepare("SELECT a.*, c.title AS course_title, c.id AS course_id, c.instructor_id
    FROM assignments a JOIN courses c ON c.id=a.course_id WHERE a.id=?");
$stmt->bind_param('i', $aid); $stmt->execute();
$a = $stmt->get_result()->fetch_assoc();
if (!$a) { set_flash('error','Assignment not found.'); redirect(SITE_URL . '/pages/assignments.php'); }

$isOwner = ((int)$a['instructor_id'] === $uid) || is_admin();
$enrolled = false;
if (is_student()) {
    $stmt = $conn->prepare("SELECT 1 FROM enrollments WHERE user_id=? AND course_id=?");
    $stmt->bind_param('ii', $uid, $a['course_id']); $stmt->execute();
    $enrolled = (bool)$stmt->get_result()->num_rows;
}

$dueTs = $a['due_date'] ? strtotime($a['due_date']) : null;
$isPastDue = $dueTs && $dueTs < time();
$canSubmit = $enrolled && (!$isPastDue || (int)$a['allow_late'] === 1);

/* Submit */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_work']) && $enrolled) {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) { set_flash('error','Invalid session.'); redirect($_SERVER['PHP_SELF'] . '?id=' . $aid); }
    if (!$canSubmit) { set_flash('error','The deadline has passed.'); redirect($_SERVER['PHP_SELF'] . '?id=' . $aid); }

    $content = clean($_POST['content'] ?? '');
    $file = upload_file('file', 'assignments');

    if ($content === '' && !$file) {
        set_flash('error','Provide a response or attach a file.');
    } else {
        $lateFlag = $isPastDue ? 1 : 0;
        $stmt = $conn->prepare("INSERT INTO submissions (assignment_id,user_id,content,file_path,is_late)
            VALUES (?,?,?,?,?)
            ON DUPLICATE KEY UPDATE content=VALUES(content), file_path=COALESCE(VALUES(file_path),file_path), submitted_at=NOW(), is_late=VALUES(is_late)");
        $stmt->bind_param('iissi', $aid, $uid, $content, $file, $lateFlag);
        $stmt->execute();
        set_flash('success', $lateFlag ? 'Late submission saved.' : 'Submission saved.');
    }
    redirect('assignment-view.php?id=' . $aid);
}

$submission = null;
if (is_student()) {
    $stmt = $conn->prepare("SELECT * FROM submissions WHERE assignment_id=? AND user_id=?");
    $stmt->bind_param('ii', $aid, $uid); $stmt->execute();
    $submission = $stmt->get_result()->fetch_assoc();
}

$allSubs = [];
if ($isOwner) {
    $stmt = $conn->prepare("SELECT s.*, u.full_name, u.admission_no FROM submissions s
        JOIN users u ON u.id=s.user_id WHERE s.assignment_id=? ORDER BY s.submitted_at DESC");
    $stmt->bind_param('i', $aid); $stmt->execute();
    $allSubs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

$pageTitle = $a['title'];
$extraCSS = ['assignments.css'];
include __DIR__ . '/../includes/header.php';
?>

<article class="detail">
    <header class="detail__head">
        <a class="link" href="course-view.php?id=<?php echo (int)$a['course_id']; ?>">&larr; <?php echo e($a['course_title']); ?></a>
        <h2><?php echo e($a['title']); ?></h2>
        <p class="muted">Due <?php echo e(fmt_datetime($a['due_date'])); ?> · <?php echo (int)$a['max_points']; ?> points
            <?php if ((int)$a['allow_late'] === 1): ?> · late submissions allowed<?php endif; ?>
        </p>
    </header>

    <section class="detail__body">
        <h3>Instructions</h3>
        <p><?php echo nl2br(e($a['instructions'])); ?></p>
        <?php if (!empty($a['attachment'])): ?>
            <a class="link" href="<?php echo e(UPLOAD_URL . $a['attachment']); ?>" download>Download assignment attachment</a>
        <?php endif; ?>
    </section>

    <?php if (is_student() && $enrolled): ?>
        <section class="detail__body">
            <h3>Your submission</h3>

            <?php if ($isPastDue && (int)$a['allow_late'] === 0 && !$submission): ?>
                <div class="alert alert--error">The deadline for this assignment has passed.</div>
            <?php endif; ?>

            <?php if ($submission && $submission['grade'] !== null): ?>
                <div class="grade-box">
                    <span class="grade-box__score"><?php echo (int)$submission['grade']; ?></span>
                    <span class="grade-box__max">/ <?php echo (int)$a['max_points']; ?></span>
                </div>
                <?php if ($submission['feedback']): ?>
                    <div class="feedback"><strong>Feedback</strong><p><?php echo nl2br(e($submission['feedback'])); ?></p></div>
                <?php endif; ?>
            <?php endif; ?>

            <?php if ($submission): ?>
                <p class="muted">Last saved <?php echo e(fmt_datetime($submission['submitted_at'])); ?><?php echo $submission['is_late'] ? ' (late)' : ''; ?></p>
                <?php if ($submission['file_path']): ?>
                    <a class="link" href="<?php echo e(UPLOAD_URL . $submission['file_path']); ?>" download>Download submitted file</a>
                <?php endif; ?>
            <?php endif; ?>

            <?php if ($canSubmit): ?>
                <form method="post" class="form" enctype="multipart/form-data">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="submit_work" value="1">
                    <label class="field"><span class="field__label">Response</span>
                        <textarea class="field__input" name="content" rows="6" required><?php echo e($submission['content'] ?? ''); ?></textarea></label>
                    <label class="field"><span class="field__label">Attach file</span>
                        <input class="field__input" type="file" name="file"></label>
                    <button class="btn btn--primary" type="submit"><?php echo $submission ? 'Update submission' : 'Submit assignment'; ?></button>
                </form>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <?php if ($isOwner): ?>
        <section class="detail__body">
            <h3>Submissions (<?php echo count($allSubs); ?>)</h3>
            <?php if (!$allSubs): ?>
                <p class="empty">No submissions yet.</p>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="table">
                        <thead><tr><th>Student</th><th>Admission</th><th>Submitted</th><th>Late</th><th>Grade</th><th></th></tr></thead>
                        <tbody>
                        <?php foreach ($allSubs as $s): ?>
                            <tr>
                                <td><?php echo e($s['full_name']); ?></td>
                                <td><?php echo e($s['admission_no'] ?: '—'); ?></td>
                                <td><?php echo e(fmt_datetime($s['submitted_at'])); ?></td>
                                <td><?php echo $s['is_late'] ? 'Yes' : 'No'; ?></td>
                                <td><?php echo $s['grade'] !== null ? (int)$s['grade'] : '—'; ?></td>
                                <td><a class="btn btn--ghost btn--sm" href="assignment-manage.php?id=<?php echo (int)$aid; ?>#sub-<?php echo (int)$s['id']; ?>">Grade</a></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    <?php endif; ?>
</article>

<?php include __DIR__ . '/../includes/footer.php'; ?>