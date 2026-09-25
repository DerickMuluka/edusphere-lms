<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['instructor','admin']);

$user = current_user();
$uid = (int)$user['id'];
$conn = db();

/* Create/update exam */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_exam'])) {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) { set_flash('error','Invalid session.'); redirect($_SERVER['PHP_SELF']); }
    $id = (int)($_POST['id'] ?? 0);
    $courseId = (int)$_POST['course_id'];
    $title = clean($_POST['title'] ?? '');
    $instructions = clean($_POST['instructions'] ?? '');
    $duration = (int)($_POST['duration_minutes'] ?? 60);
    $points = (int)($_POST['total_points'] ?? 100);
    $start = clean($_POST['start_at'] ?? '');
    $end = clean($_POST['end_at'] ?? '');
    $published = isset($_POST['is_published']) ? 1 : 0;

    $own = $conn->prepare("SELECT id FROM courses WHERE id=? AND instructor_id=?");
    $own->bind_param('ii', $courseId, $uid); $own->execute();
    if (!$own->get_result()->num_rows) { set_flash('error','Not your course.'); redirect($_SERVER['PHP_SELF']); }

    if ($title === '') set_flash('error','Title is required.');
    else if ($id > 0) {
        $stmt = $conn->prepare("UPDATE exams SET title=?, instructions=?, duration_minutes=?, total_points=?, start_at=?, end_at=?, is_published=? WHERE id=?");
        $stmt->bind_param('ssiissii', $title, $instructions, $duration, $points, $start, $end, $published, $id);
        $stmt->execute(); set_flash('success','Exam updated.');
    } else {
        $stmt = $conn->prepare("INSERT INTO exams (course_id,title,instructions,duration_minutes,total_points,start_at,end_at,is_published) VALUES (?,?,?,?,?,?,?,?)");
        $stmt->bind_param('issiissi', $courseId, $title, $instructions, $duration, $points, $start, $end, $published);
        $stmt->execute();
        $id = $conn->insert_id;
        set_flash('success','Exam created.');
    }
    redirect('exam-manage.php?id=' . $id);
}

/* Add question */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_question'])) {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) { set_flash('error','Invalid session.'); redirect($_SERVER['PHP_SELF']); }
    $examId = (int)$_POST['exam_id'];
    $own = $conn->prepare("SELECT x.id FROM exams x JOIN courses c ON c.id=x.course_id WHERE x.id=? AND c.instructor_id=?");
    $own->bind_param('ii', $examId, $uid); $own->execute();
    if (!$own->get_result()->num_rows) { set_flash('error','Not authorized.'); redirect($_SERVER['PHP_SELF']); }

    $text = clean($_POST['question_text'] ?? '');
    $a = clean($_POST['option_a'] ?? '');
    $b = clean($_POST['option_b'] ?? '');
    $c = clean($_POST['option_c'] ?? '');
    $d = clean($_POST['option_d'] ?? '');
    $correct = strtoupper(clean($_POST['correct_option'] ?? 'A'));
    $pts = (int)($_POST['points'] ?? 1);
    $ord = (int)($_POST['question_order'] ?? 0);

    if ($text === '' || $a === '' || $b === '') set_flash('error','Fill required fields.');
    else {
        $stmt = $conn->prepare("INSERT INTO exam_questions (exam_id,question_text,option_a,option_b,option_c,option_d,correct_option,points,question_order) VALUES (?,?,?,?,?,?,?,?,?)");
        $stmt->bind_param('issssssii', $examId, $text, $a, $b, $c, $d, $correct, $pts, $ord);
        $stmt->execute(); set_flash('success','Question added.');
    }
    redirect('exam-manage.php?id=' . $examId);
}

/* Delete question */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_question'])) {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) { set_flash('error','Invalid session.'); redirect($_SERVER['PHP_SELF']); }
    $qid = (int)$_POST['delete_question'];
    $own = $conn->prepare("DELETE q FROM exam_questions q JOIN exams x ON x.id=q.exam_id JOIN courses c ON c.id=x.course_id WHERE q.id=? AND c.instructor_id=?");
    $own->bind_param('ii', $qid, $uid); $own->execute();
    set_flash('success','Question removed.');
    redirect('exam-manage.php?id=' . (int)$_POST['exam_id']);
}

/* Load exam context */
$editId = (int)($_GET['id'] ?? 0);
$exam = null;
$questions = [];
$attempts = [];

if ($editId) {
    $stmt = $conn->prepare("SELECT x.* FROM exams x JOIN courses c ON c.id=x.course_id WHERE x.id=? AND c.instructor_id=?");
    $stmt->bind_param('ii', $editId, $uid); $stmt->execute();
    $exam = $stmt->get_result()->fetch_assoc();
    if (!$exam) { set_flash('error','Exam not found.'); redirect('exam-manage.php'); }

    $stmt = $conn->prepare("SELECT * FROM exam_questions WHERE exam_id=? ORDER BY question_order, id");
    $stmt->bind_param('i', $editId); $stmt->execute();
    $questions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $stmt = $conn->prepare("SELECT xa.*, u.full_name, u.admission_no FROM exam_attempts xa
                            JOIN users u ON u.id=xa.user_id WHERE xa.exam_id=? ORDER BY xa.started_at DESC");
    $stmt->bind_param('i', $editId); $stmt->execute();
    $attempts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

$stmt = $conn->prepare("SELECT * FROM courses WHERE instructor_id=? ORDER BY title");
$stmt->bind_param('i', $uid); $stmt->execute();
$courses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'Manage exams';
$extraCSS = ['exams.css'];
include __DIR__ . '/../includes/header.php';
?>

<section class="panel">
    <header class="panel__head"><h3><?php echo $exam ? 'Edit exam' : 'Create exam'; ?></h3></header>
    <form method="post" class="form form--grid">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="save_exam" value="1">
        <input type="hidden" name="id" value="<?php echo (int)($exam['id'] ?? 0); ?>">

        <label class="field"><span class="field__label">Course</span>
            <select class="field__input" name="course_id" required>
                <?php foreach ($courses as $c): ?>
                    <option value="<?php echo (int)$c['id']; ?>" <?php echo (($exam['course_id'] ?? 0) == $c['id']) ? 'selected' : ''; ?>>
                        <?php echo e($c['code'] . ' — ' . $c['title']); ?>
                    </option>
                <?php endforeach; ?>
            </select></label>
        <label class="field"><span class="field__label">Title</span>
            <input class="field__input" name="title" required value="<?php echo e($exam['title'] ?? ''); ?>"></label>
        <label class="field"><span class="field__label">Duration (min)</span>
            <input class="field__input" type="number" name="duration_minutes" value="<?php echo (int)($exam['duration_minutes'] ?? 60); ?>"></label>
        <label class="field"><span class="field__label">Total points</span>
            <input class="field__input" type="number" name="total_points" value="<?php echo (int)($exam['total_points'] ?? 100); ?>"></label>
        <label class="field"><span class="field__label">Starts</span>
            <input class="field__input" type="datetime-local" name="start_at" value="<?php echo e($exam ? date('Y-m-d\TH:i', strtotime($exam['start_at'])) : ''); ?>"></label>
        <label class="field"><span class="field__label">Ends</span>
            <input class="field__input" type="datetime-local" name="end_at" value="<?php echo e($exam ? date('Y-m-d\TH:i', strtotime($exam['end_at'])) : ''); ?>"></label>
        <label class="field field--wide"><span class="field__label">Instructions</span>
            <textarea class="field__input" name="instructions" rows="3"><?php echo e($exam['instructions'] ?? ''); ?></textarea></label>
        <label class="field field--check">
            <input type="checkbox" name="is_published" value="1" <?php echo !empty($exam['is_published']) ? 'checked' : ''; ?>>
            <span>Publish exam</span>
        </label>
        <div class="form__actions">
            <button class="btn btn--primary" type="submit"><?php echo $exam ? 'Update exam' : 'Create exam'; ?></button>
            <?php if ($exam): ?><a class="btn btn--ghost" href="exam-manage.php">Cancel</a><?php endif; ?>
        </div>
    </form>
</section>

<?php if ($exam): ?>
<section class="panel">
    <header class="panel__head"><h3>Add question</h3></header>
    <form method="post" class="form form--grid">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="add_question" value="1">
        <input type="hidden" name="exam_id" value="<?php echo (int)$exam['id']; ?>">

        <label class="field field--wide"><span class="field__label">Question</span>
            <textarea class="field__input" name="question_text" rows="3" required></textarea></label>
        <label class="field"><span class="field__label">Option A</span>
            <input class="field__input" name="option_a" required></label>
        <label class="field"><span class="field__label">Option B</span>
            <input class="field__input" name="option_b" required></label>
        <label class="field"><span class="field__label">Option C</span>
            <input class="field__input" name="option_c"></label>
        <label class="field"><span class="field__label">Option D</span>
            <input class="field__input" name="option_d"></label>
        <label class="field"><span class="field__label">Correct option</span>
            <select class="field__input" name="correct_option">
                <option value="A">A</option><option value="B">B</option>
                <option value="C">C</option><option value="D">D</option>
            </select></label>
        <label class="field"><span class="field__label">Points</span>
            <input class="field__input" type="number" name="points" value="1"></label>
        <label class="field"><span class="field__label">Order</span>
            <input class="field__input" type="number" name="question_order" value="0"></label>
        <div class="form__actions"><button class="btn btn--primary" type="submit">Add question</button></div>
    </form>

    <?php if ($questions): ?>
        <ol class="question-list">
            <?php foreach ($questions as $q): ?>
                <li>
                    <div>
                        <p><strong><?php echo e($q['question_text']); ?></strong></p>
                        <ul class="options">
                            <li class="<?php echo $q['correct_option'] === 'A' ? 'is-correct' : ''; ?>">A. <?php echo e($q['option_a']); ?></li>
                            <li class="<?php echo $q['correct_option'] === 'B' ? 'is-correct' : ''; ?>">B. <?php echo e($q['option_b']); ?></li>
                            <?php if ($q['option_c']): ?><li class="<?php echo $q['correct_option'] === 'C' ? 'is-correct' : ''; ?>">C. <?php echo e($q['option_c']); ?></li><?php endif; ?>
                            <?php if ($q['option_d']): ?><li class="<?php echo $q['correct_option'] === 'D' ? 'is-correct' : ''; ?>">D. <?php echo e($q['option_d']); ?></li><?php endif; ?>
                        </ul>
                    </div>
                    <form method="post" onsubmit="return confirm('Delete question?');">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="delete_question" value="<?php echo (int)$q['id']; ?>">
                        <input type="hidden" name="exam_id" value="<?php echo (int)$exam['id']; ?>">
                        <button class="btn btn--danger btn--sm" type="submit">Delete</button>
                    </form>
                </li>
            <?php endforeach; ?>
        </ol>
    <?php endif; ?>
</section>

<?php if ($attempts): ?>
<section class="panel">
    <header class="panel__head"><h3>Attempts</h3></header>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Student</th><th>Admission</th><th>Started</th><th>Submitted</th><th>Score</th></tr></thead>
            <tbody>
            <?php foreach ($attempts as $a): ?>
                <tr>
                    <td><?php echo e($a['full_name']); ?></td>
                    <td><?php echo e($a['admission_no'] ?: '—'); ?></td>
                    <td><?php echo e(fmt_datetime($a['started_at'])); ?></td>
                    <td><?php echo e(fmt_datetime($a['submitted_at'])); ?></td>
                    <td><?php echo $a['score'] !== null ? (int)$a['score'] : '—'; ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php endif; ?>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>