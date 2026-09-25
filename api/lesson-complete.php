<?php
require_once __DIR__ . '/../includes/auth.php';
header('Content-Type: application/json');

if (!is_logged_in()) { echo json_encode(['success' => false, 'message' => 'Auth required.']); exit; }
$user = current_user();
if ($user['role'] !== 'student') { echo json_encode(['success' => false, 'message' => 'Students only.']); exit; }

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$lessonId = (int)($input['lesson_id'] ?? 0);
$courseId = (int)($input['course_id'] ?? 0);
$done = !empty($input['completed']);
if (!$lessonId || !$courseId) { echo json_encode(['success' => false, 'message' => 'Missing data.']); exit; }

$conn = db();
$stmt = $conn->prepare("SELECT 1 FROM enrollments WHERE user_id=? AND course_id=?");
$stmt->bind_param('ii', $user['id'], $courseId); $stmt->execute();
if (!$stmt->get_result()->num_rows) { echo json_encode(['success' => false, 'message' => 'Not enrolled.']); exit; }

$stmt = $conn->prepare("SELECT 1 FROM lessons WHERE id=? AND course_id=?");
$stmt->bind_param('ii', $lessonId, $courseId); $stmt->execute();
if (!$stmt->get_result()->num_rows) { echo json_encode(['success' => false, 'message' => 'Lesson not found.']); exit; }

if ($done) {
    $stmt = $conn->prepare("INSERT INTO lesson_progress (user_id, lesson_id, completed, completed_at)
                            VALUES (?,?,1,NOW())
                            ON DUPLICATE KEY UPDATE completed=1, completed_at=NOW()");
} else {
    $stmt = $conn->prepare("INSERT INTO lesson_progress (user_id, lesson_id, completed)
                            VALUES (?,?,0)
                            ON DUPLICATE KEY UPDATE completed=0, completed_at=NULL");
}
$stmt->bind_param('ii', $user['id'], $lessonId);
$stmt->execute();

/* Recalculate course progress */
$stmt = $conn->prepare("SELECT COUNT(*) c FROM lessons WHERE course_id=?");
$stmt->bind_param('i', $courseId); $stmt->execute();
$total = (int)$stmt->get_result()->fetch_assoc()['c'];

$stmt = $conn->prepare("SELECT COUNT(*) c FROM lesson_progress lp JOIN lessons l ON l.id=lp.lesson_id
                        WHERE lp.user_id=? AND l.course_id=? AND lp.completed=1");
$stmt->bind_param('ii', $user['id'], $courseId); $stmt->execute();
$doneCount = (int)$stmt->get_result()->fetch_assoc()['c'];

$pct = $total ? (int)round(($doneCount / $total) * 100) : 0;
$stmt = $conn->prepare("UPDATE enrollments SET progress_percentage=? WHERE user_id=? AND course_id=?");
$stmt->bind_param('iii', $pct, $user['id'], $courseId); $stmt->execute();

echo json_encode(['success' => true, 'progress' => $pct, 'completed' => $done]);