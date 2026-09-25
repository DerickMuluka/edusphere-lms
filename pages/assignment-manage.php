<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['instructor','admin']);

$user = current_user();
$uid = (int)$user['id'];
$conn = db();

/* Save assignment */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_assignment'])) {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) { set_flash('error','Invalid session.'); redirect($_SERVER['PHP_SELF']); }
    $id = (int)($_POST['id'] ?? 0);
    $courseId = (int)($_POST['course_id'] ?? 0);
    $title = clean($_POST['title'] ?? '');
    $instructions = clean($_POST['instructions'] ?? '');
    $due = clean($_POST['due_date'] ?? '');
    $max = (int)($_POST['max_points'] ?? 100);
    $allowLate = isset($_POST['allow_late']) ? 1 : 0;
    $attachment = upload_file('attachment', 'assignments');

    $own = $conn->prepare("SELECT id FROM courses WHERE id=? " . (is_admin() ? "" : "AND instructor_id=?"));
    if (is_admin()) $own->bind_param('i', $courseId); else $own->bind_param('ii', $courseId, $uid);
    $own->execute();
    if (!$own->get_result()->num_rows) { set_flash('error','Not your course.'); redirect($_SERVER['PHP_SELF']); }

    $dueVal = $due ? date('Y-m-d H:i:s', strtotime($due)) : null;

    if ($title === '') set_flash('error','Title is required.');
    else if ($id > 0) {
        if ($attachment) {
            $stmt = $conn->prepare("UPDATE assignments SET title=?, instructions=?, due_date=?, allow_late=?, max_points=?, attachment=? WHERE id=?");
            $stmt->bind_param('sssii si', $title,$instructions,$dueVal,$allowLate,$max,$attachment,$id);
            $stmt = $conn->prepare("UPDATE assignments SET title=?, instructions=?, due_date=?, allow_late=?, max_points=?, attachment=? WHERE id=?");
            $stmt->bind_param('sssissi', $title,$instructions,$dueVal,$allowLate,$max,$attachment,$id);
        } else {
            $stmt = $conn->prepare("UPDATE assignments SET title=?, instructions=?, due_date=?, allow_late=?, max_points=? WHERE id=?");
            $stmt->bind_param('sssiii', $title,$instructions,$dueVal,$allowLate,$max,$id);
        }
        $stmt->execute();
        set_flash('success','Assignment updated.');
    } else {
        $stmt = $conn->prepare("INSERT INTO assignments (course_id,title,instructions,due_date,allow_late,max_points,attachment) VALUES (?,?,?,?,?,?,?)");
        $stmt->bind_param('isssiis', $courseId,$title,$instructions,$dueVal,$allowLate,$max,$attachment);
        $stmt->execute();
        set_flash('success','Assignment created.');
    }
    redirect('assignment-manage.php?id=' . ($id ?: $conn->insert_id));
}

/* Grade submission */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['grade_submission'])) {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) { set_flash('error','Invalid session.'); redirect($_SERVER['PHP_SELF']); }
    $subId = (int)$_POST['submission_id'];
    $grade = (int)$_POST['grade'];
    $feedback = clean($_POST['feedback'] ?? '');

    $stmt = $conn->prepare("UPDATE submissions SET grade=?, feedback=?, graded_by=?, graded_at=NOW() WHERE id=?");
    $stmt->bind_param('issi', $grade, $feedback, $uid, $subId);
    $stmt->execute();
    set_flash('success','Grade saved.');
    redirect($_SERVER['PHP_SELF'] . '?id=' . (int)$_POST['assignment_id']);
}

$editId = (int)($_GET['id'] ?? 0);

if (is_admin()) $stmt = $conn->prepare("SELECT id, code, title FROM courses ORDER BY code");
else $stmt = $conn->prepare("SELECT id, code, title FROM courses WHERE instructor_id=? ORDER BY code");
if (!is_admin()) $stmt->bind_param('i', $uid);
$stmt->execute();
$courses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$edit = null;
if ($editId) {
    $stmt = $conn->prepare("SELECT a.* FROM assignments a JOIN courses c ON c.id=a.course_id WHERE a.id=?" . (is_admin() ? "" : " AND c.instructor_id=?"));
    if (is_admin()) $stmt->bind_param('i', $editId); else $stmt->bind_param('ii', $editId, $uid);
    $stmt->execute();
    $edit = $stmt->get_result()->fetch_assoc();
}

$subs = [];
if ($editId) {
    $stmt = $conn->prepare("SELECT s.*, u.full_name, u.admission_no FROM submissions s
        JOIN users u ON u.id=s.user_id WHERE s.assignment_id=? ORDER BY s.submitted_at DESC");
    $stmt->bind_param('i', $editId); $stmt->execute();
    $subs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

if (is_admin()) $stmt = $conn->prepare("SELECT a.*, c.title AS course_title FROM assignments a JOIN courses c ON c.id=a.course_id ORDER BY a.created_at DESC");
else $stmt = $conn->prepare("SELECT a.*, c.title AS course_title FROM assignments a JOIN courses c ON c.id=a.course_id WHERE c.instructor_id=? ORDER BY a.created_at DESC");
if (!is_admin()) $stmt->bind_param('i', $uid);
$stmt->execute();
$myAssignments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'Manage assignments';
$extraCSS = ['assignments.css'];
include __DIR__ . '/../includes/header.php';
?>

<section class="panel">
    <header class="panel__head"><h3><?php echo $edit ? 'Edit assignment' : 'Create assignment'; ?></h3></header>
    <form method="post" class="form form--grid" enctype="multipart/form-data">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="save_assignment" value="1">
        <input type="hidden" name="id" value="<?php echo (int)($edit['id'] ?? 0); ?>">

        <label class="field"><span class="field__label">Course</span>
            <select class="field__input" name="course_id" required>
                <?php foreach ($courses as $c): ?>
                    <option value="<?php echo (int)$c['id']; ?>" <?php echo (($edit['course_id'] ?? 0) == $c['id']) ? 'selected' : ''; ?>>
                        <?php echo e($c['code'] . ' — ' . $c['title']); ?>
                    </option>
                <?php endforeach; ?>
            </select></label>
        <label class="field"><span class="field__label">Title</span>
            <input class="field__input" name="title" required value="<?php echo e($edit['title'] ?? ''); ?>"></label>
        <label class="field"><span class="field__label">Max points</span>
            <input class="field__input" type="number" name="max_points" value="<?php echo (int)($edit['max_points'] ?? 100); ?>"></label>
        <label class="field"><span class="field__label">Due date</span>
            <input class="field__input" type="datetime-local" name="due_date"
                value="<?php echo e($edit && $edit['due_date'] ? date('Y-m-d\TH:i', strtotime($edit['due_date'])) : ''); ?>"></label>
        <label class="field field--wide"><span class="field__label">Instructions</span>
            <textarea class="field__input" name="instructions" rows="4"><?php echo e($edit['instructions'] ?? ''); ?></textarea></label>
        <label class="field field--wide"><span class="field__label">Attachment (optional)</span>
            <input class="field__input" type="file" name="attachment"></label>
        <label class="field field--check">
            <input type="checkbox" name="allow_late" value="1" <?php echo !empty($edit['allow_late']) ? 'checked' : ''; ?>>
            <span>Allow late submissions</span>
        </label>
        <div class="form__actions">
            <button class="btn btn--primary" type="submit"><?php echo $edit ? 'Update' : 'Create'; ?></button>
            <?php if ($edit): ?><a class="btn btn--ghost" href="assignment-manage.php">Cancel</a><?php endif; ?>
        </div>
    </form>
</section>

<?php if ($editId && $subs): ?>
<section class="panel">
    <header class="panel__head"><h3>Submissions</h3></header>
    <?php foreach ($subs as $s): ?>
        <div class="submission-block" id="sub-<?php echo (int)$s['id']; ?>">
            <header>
                <div>
                    <strong><?php echo e($s['full_name']); ?></strong>
                    <span class="muted"><?php echo e($s['admission_no'] ?: ''); ?> · submitted <?php echo e(fmt_datetime($s['submitted_at'])); ?><?php echo $s['is_late'] ? ' (late)' : ''; ?></span>
                </div>
                <span class="badge <?php echo $s['grade'] !== null ? 'badge--success' : 'badge--warn'; ?>">
                    <?php echo $s['grade'] !== null ? 'Graded ' . (int)$s['grade'] : 'Ungraded'; ?>
                </span>
            </header>
            <p class="submission-block__content"><?php echo nl2br(e($s['content'])); ?></p>
            <?php if ($s['file_path']): ?>
                <a class="link" href="<?php echo e(UPLOAD_URL . $s['file_path']); ?>" download>Download attachment</a>
            <?php endif; ?>
            <form method="post" class="form form--inline">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="grade_submission" value="1">
                <input type="hidden" name="submission_id" value="<?php echo (int)$s['id']; ?>">
                <input type="hidden" name="assignment_id" value="<?php echo (int)$editId; ?>">
                <label class="field"><span class="field__label">Grade</span>
                    <input class="field__input" type="number" name="grade" min="0" max="<?php echo (int)$edit['max_points']; ?>"
                        value="<?php echo $s['grade'] !== null ? (int)$s['grade'] : ''; ?>" required></label>
                <label class="field field--wide"><span class="field__label">Feedback</span>
                    <input class="field__input" name="feedback" value="<?php echo e($s['feedback'] ?? ''); ?>"></label>
                <button class="btn btn--primary" type="submit">Save grade</button>
            </form>
        </div>
    <?php endforeach; ?>
</section>
<?php endif; ?>

<section class="panel">
    <header class="panel__head"><h3>Your assignments</h3></header>
    <?php if (!$myAssignments): ?><p class="empty">No assignments created.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Course</th><th>Title</th><th>Due</th><th>Late</th><th>Points</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($myAssignments as $a): ?>
                    <tr>
                        <td><?php echo e($a['course_title']); ?></td>
                        <td><?php echo e($a['title']); ?></td>
                        <td><?php echo e(fmt_datetime($a['due_date'])); ?></td>
                        <td><?php echo $a['allow_late'] ? 'Allowed' : 'No'; ?></td>
                        <td><?php echo (int)$a['max_points']; ?></td>
                        <td><a class="btn btn--ghost btn--sm" href="assignment-manage.php?id=<?php echo (int)$a['id']; ?>">Edit / grade</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>