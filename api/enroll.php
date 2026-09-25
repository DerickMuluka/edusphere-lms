<?php
require_once __DIR__ . '/../includes/auth.php';
header('Content-Type: application/json');

if (!is_logged_in()) { echo json_encode(['success'=>false,'message'=>'Please sign in.']); exit; }

$user = current_user();
if ($user['role'] !== 'student') {
    echo json_encode(['success'=>false,'message'=>'Only students can enroll.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$courseId = (int)($input['course_id'] ?? 0);
if ($courseId <= 0) { echo json_encode(['success'=>false,'message'=>'Invalid course.']); exit; }

$conn = db();
$stmt = $conn->prepare("SELECT id FROM courses WHERE id=? AND is_published=1");
$stmt->bind_param('i', $courseId); $stmt->execute();
if (!$stmt->get_result()->fetch_assoc()) {
    echo json_encode(['success'=>false,'message'=>'Course not available.']);
    exit;
}

$stmt = $conn->prepare("SELECT id FROM enrollments WHERE user_id=? AND course_id=?");
$stmt->bind_param('ii', $user['id'], $courseId); $stmt->execute();
if ($stmt->get_result()->num_rows) {
    echo json_encode(['success'=>true,'message'=>'Already enrolled.','course_id'=>$courseId]);
    exit;
}

$stmt = $conn->prepare("INSERT INTO enrollments (user_id, course_id) VALUES (?,?)");
$stmt->bind_param('ii', $user['id'], $courseId);
if ($stmt->execute()) {
    echo json_encode(['success'=>true,'message'=>'Enrolled.','course_id'=>$courseId]);
} else {
    echo json_encode(['success'=>false,'message'=>'Enrollment failed.']);
}