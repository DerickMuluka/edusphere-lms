<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['student']);

$user = current_user();
$uid = (int)$user['id'];
$conn = db();

$examId = (int)($_GET['id'] ?? 0);
$stmt = $conn->prepare("SELECT x.*, c.title AS course_title FROM exams x JOIN courses c ON c.id=x.course_id WHERE x.id=? AND x.is_published=1");
$stmt->bind_param('i', $examId); $stmt->execute();
$exam = $stmt->get_result()->fetch_assoc();
if (!$exam) { set_flash('error','Exam not available.'); redirect(SITE_URL . '/pages/exams.php'); }

/* Enrollment */
$stmt = $conn->prepare("SELECT 1 FROM enrollments WHERE user_id=? AND course_id=?");
$stmt->bind_param('ii', $uid, $exam['course_id']); $stmt->execute();
if (!$stmt->get_result()->num_rows) { set_flash('error','You must enroll first.'); redirect(SITE_URL . '/pages/exams.php'); }

/* Window enforcement */
$now = time();
$start = $exam['start_at'] ? strtotime($exam['start_at']) : null;
$end   = $exam['end_at']   ? strtotime($exam['end_at'])   : null;
if ($start && $now < $start) { set_flash('error','This exam has not yet started.'); redirect(SITE_URL . '/pages/exams.php'); }
if ($end   && $now > $end)   { set_flash('error','This exam window has closed.'); redirect(SITE_URL . '/pages/exams.php'); }

/* Attempt */
$stmt = $conn->prepare("SELECT * FROM exam_attempts WHERE exam_id=? AND user_id=?");
$stmt->bind_param('ii', $examId, $uid); $stmt->execute();
$attempt = $stmt->get_result()->fetch_assoc();

if (!$attempt) {
    $stmt = $conn->prepare("INSERT INTO exam_attempts (exam_id,user_id) VALUES (?,?)");
    $stmt->bind_param('ii', $examId, $uid); $stmt->execute();
    $attempt = ['id' => $conn->insert_id, 'status' => 'in_progress', 'score' => null];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_exam']) && $attempt['status'] === 'in_progress') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) { set_flash('error','Invalid session.'); redirect($_SERVER['PHP_SELF'] . '?id=' . $examId); }
    $answers = $_POST['answers'] ?? [];

    $stmt = $conn->prepare("SELECT * FROM exam_questions WHERE exam_id=? ORDER BY question_order, id");
    $stmt->bind_param('i', $examId); $stmt->execute();
    $qs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $score = 0;
    foreach ($qs as $q) {
        $sel = strtoupper($answers[$q['id']] ?? '');
        $ok = ($sel === strtoupper($q['correct_option'])) ? 1 : 0;
        if ($ok) $score += (int)$q['points'];

        $stmt = $conn->prepare("INSERT INTO exam_answers (attempt_id,question_id,selected_option,is_correct) VALUES (?,?,?,?)");
        $stmt->bind_param('iisi', $attempt['id'], $q['id'], $sel, $ok);
        $stmt->execute();
    }

    $stmt = $conn->prepare("UPDATE exam_attempts SET submitted_at=NOW(), score=?, status='submitted' WHERE id=?");
    $stmt->bind_param('ii', $score, $attempt['id']);
    $stmt->execute();

    set_flash('success', 'Exam submitted. Score: ' . $score);
    redirect(SITE_URL . '/pages/exams.php');
}

$stmt = $conn->prepare("SELECT * FROM exam_questions WHERE exam_id=? ORDER BY question_order, id");
$stmt->bind_param('i', $examId); $stmt->execute();
$questions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$pageTitle = $exam['title'];
$extraCSS = ['exams.css'];
$extraJS = ['exams.js'];
include __DIR__ . '/../includes/header.php';
?>

<article class="exam-shell" data-exam-duration="<?php echo (int)$exam['duration_minutes']; ?>">
    <header class="exam-shell__head">
        <div>
            <h2><?php echo e($exam['title']); ?></h2>
            <p class="muted"><?php echo e($exam['course_title']); ?> · <?php echo (int)$exam['duration_minutes']; ?> minutes</p>
        </div>
        <div class="exam-timer" id="examTimer">--:--</div>
    </header>

    <?php if ($attempt['status'] !== 'in_progress'): ?>
        <div class="alert alert--success">
            Attempt submitted. Final score: <strong><?php echo (int)$attempt['score']; ?></strong>
        </div>
    <?php else: ?>
        <form method="post" id="examForm">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="submit_exam" value="1">

            <?php foreach ($questions as $i => $q): ?>
                <fieldset class="question">
                    <legend>Question <?php echo $i + 1; ?> · <?php echo (int)$q['points']; ?> pts</legend>
                    <p class="question__text"><?php echo e($q['question_text']); ?></p>
                    <?php foreach (['A'=>'option_a','B'=>'option_b','C'=>'option_c','D'=>'option_d'] as $letter => $field): ?>
                        <?php if (!empty($q[$field])): ?>
                            <label class="option">
                                <input type="radio" name="answers[<?php echo (int)$q['id']; ?>]" value="<?php echo $letter; ?>" required>
                                <span><?php echo $letter; ?>. <?php echo e($q[$field]); ?></span>
                            </label>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </fieldset>
            <?php endforeach; ?>

            <button class="btn btn--primary btn--block" type="submit">Submit exam</button>
        </form>
    <?php endif; ?>
</article>

<?php include __DIR__ . '/../includes/footer.php'; ?>