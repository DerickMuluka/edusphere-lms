<?php
/**
 * EduSphere LMS - Course Detail
 * View course content, lessons, and assignments
 */

require_once '../includes/functions.php';

// Require login
if (!isLoggedIn()) {
    redirect(SITE_URL . '/pages/login.php');
}

$currentUser = getCurrentUser();
$userId = $currentUser['id'];

$courseId = intval($_GET['id'] ?? 0);

if ($courseId <= 0) {
    setFlash('error', 'Invalid course.');
    redirect(SITE_URL . '/pages/courses.php');
}

$course = getCourse($courseId);

if (!$course) {
    setFlash('error', 'Course not found.');
    redirect(SITE_URL . '/pages/courses.php');
}

$isEnrolled = isEnrolled($userId, $courseId);
$lessons = getCourseLessons($courseId);
$assignments = getCourseAssignments($courseId, $userId);

// Get enrollment progress
$progress = 0;
if ($isEnrolled) {
    $db = getDB();
    $stmt = $db->prepare("SELECT progress_percentage FROM enrollments WHERE user_id = ? AND course_id = ?");
    $stmt->bind_param("ii", $userId, $courseId);
    $stmt->execute();
    $progress = $stmt->get_result()->fetch_assoc()['progress_percentage'] ?? 0;
}

// Get lesson completion status
$completedLessons = [];
if ($isEnrolled) {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT lp.lesson_id 
        FROM lesson_progress lp
        JOIN lessons l ON lp.lesson_id = l.id
        WHERE lp.user_id = ? AND l.course_id = ? AND lp.completed = TRUE
    ");
    $stmt->bind_param("ii", $userId, $courseId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $completedLessons[] = $row['lesson_id'];
    }
}

$pageTitle = $course['title'];
$bodyClass = 'course-detail-page';
$extraCSS = ['courses.css'];
$extraJS = ['courses.js'];

include '../includes/header.php';
?>

<div class="course-hero">
    <div class="container">
        <div class="course-hero-content">
            <h1><?php echo sanitize($course['title']); ?></h1>
            <p><?php echo sanitize($course['description']); ?></p>
            <div class="course-hero-meta">
                <span>👤 <?php echo sanitize($course['instructor_name']); ?></span>
                <span>📚 <?php echo $course['lesson_count']; ?> lessons</span>
                <span>⏱ <?php echo $course['duration_hours']; ?> hours</span>
                <span>👥 <?php echo $course['student_count']; ?> students</span>
                <span class="badge badge-<?php echo $course['difficulty'] === 'beginner' ? 'success' : ($course['difficulty'] === 'intermediate' ? 'warning' : 'danger'); ?>">
                    <?php echo ucfirst($course['difficulty']); ?>
                </span>
            </div>
        </div>
    </div>
</div>

<div class="container">
    <div class="course-content-layout">
        <!-- Main Content -->
        <div class="course-main">
            <?php if (!$isEnrolled): ?>
            <div class="alert alert-info mb-4">
                <strong>Preview Mode:</strong> Enroll in this course to access all lessons and assignments.
                <button class="btn btn-primary btn-sm enroll-btn mt-2" data-course-id="<?php echo $courseId; ?>">
                    Enroll Now
                </button>
            </div>
            <?php endif; ?>
            
            <!-- Lessons -->
            <div class="section-header">
                <h2>Course Lessons</h2>
            </div>
            
            <?php if (empty($lessons)): ?>
            <div class="empty-state">
                <div class="empty-state-icon">📖</div>
                <h3>No lessons yet</h3>
                <p>Lessons will be added soon.</p>
            </div>
            <?php else: ?>
            <div class="lessons-list">
                <?php foreach ($lessons as $index => $lesson): 
                    $isCompleted = in_array($lesson['id'], $completedLessons);
                ?>
                <div class="lesson-item <?php echo $isCompleted ? 'completed' : ''; ?>">
                    <div class="lesson-header">
                        <div class="lesson-number">
                            <span><?php echo $index + 1; ?></span>
                        </div>
                        <div class="lesson-info">
                            <div class="lesson-title"><?php echo sanitize($lesson['title']); ?></div>
                            <div class="lesson-duration"><?php echo $lesson['duration_minutes']; ?> minutes</div>
                        </div>
                        <div class="lesson-toggle">▼</div>
                    </div>
                    <div class="lesson-content">
                        <?php if (!empty($lesson['video_url'])): ?>
                        <div class="video-container">
                            <iframe src="<?php echo sanitize($lesson['video_url']); ?>" 
                                    allowfullscreen 
                                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture">
                            </iframe>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($lesson['content'])): ?>
                        <div class="lesson-text">
                            <?php echo nl2br(sanitize($lesson['content'])); ?>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($isEnrolled): ?>
                        <div class="lesson-actions">
                            <button class="btn <?php echo $isCompleted ? 'btn-secondary completed' : 'btn-primary'; ?> complete-lesson-btn" 
                                    data-lesson-id="<?php echo $lesson['id']; ?>"
                                    data-course-id="<?php echo $courseId; ?>">
                                <?php echo $isCompleted ? '✓ Completed' : 'Mark as Complete'; ?>
                            </button>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            
            <!-- Assignments -->
            <?php if ($isEnrolled && !empty($assignments)): ?>
            <div class="section-header mt-4">
                <h2>Assignments</h2>
            </div>
            
            <div class="assignment-list">
                <?php foreach ($assignments as $assignment): 
                    $dueDate = strtotime($assignment['due_date']);
                    $isOverdue = $dueDate < time() && !$assignment['submission_id'];
                    $statusClass = $assignment['submission_id'] ? 'submitted' : ($isOverdue ? 'overdue' : 'pending');
                ?>
                <a href="assignment-detail.php?id=<?php echo $assignment['id']; ?>" class="assignment-item">
                    <div class="assignment-status <?php echo $statusClass; ?>"></div>
                    <div class="assignment-info">
                        <div class="assignment-title"><?php echo sanitize($assignment['title']); ?></div>
                        <div class="assignment-due <?php echo $isOverdue ? 'overdue' : ''; ?>">
                            Due: <?php echo formatDate($assignment['due_date'], 'M j, Y g:i A'); ?>
                            <?php echo $isOverdue ? ' (Overdue)' : ''; ?>
                        </div>
                    </div>
                    <?php if ($assignment['grade'] !== null): ?>
                    <div class="assignment-grade">
                        <span class="score"><?php echo $assignment['grade']; ?></span>
                        <span class="max">/<?php echo $assignment['max_points']; ?></span>
                    </div>
                    <?php endif; ?>
                </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Sidebar -->
        <div class="course-sidebar">
            <?php if ($isEnrolled): ?>
            <div class="sidebar-card">
                <div class="sidebar-card-header">
                    <h3>Your Progress</h3>
                </div>
                <div class="sidebar-card-body">
                    <div class="progress-circle-wrapper">
                        <div class="progress-circle">
                            <svg width="120" height="120">
                                <defs>
                                    <linearGradient id="progressGradient" x1="0%" y1="0%" x2="100%" y2="100%">
                                        <stop offset="0%" style="stop-color: var(--primary); stop-opacity: 1" />
                                        <stop offset="100%" style="stop-color: var(--accent); stop-opacity: 1" />
                                    </linearGradient>
                                </defs>
                                <circle class="progress-circle-bg" cx="60" cy="60" r="50"></circle>
                                <circle class="progress-circle-fill" cx="60" cy="60" r="50" 
                                        data-progress="<?php echo $progress; ?>"></circle>
                            </svg>
                            <div class="progress-circle-text">
                                <span class="value"><?php echo $progress; ?>%</span>
                                <span class="label">Complete</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="progress mt-3">
                        <div class="progress-bar" style="width: <?php echo $progress; ?>%" data-course-progress></div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="sidebar-card">
                <div class="sidebar-card-header">
                    <h3>Course Info</h3>
                </div>
                <div class="sidebar-card-body">
                    <p><strong>Instructor:</strong><br><?php echo sanitize($course['instructor_name']); ?></p>
                    <p><strong>Category:</strong><br><?php echo sanitize($course['category']); ?></p>
                    <p><strong>Level:</strong><br><?php echo ucfirst($course['difficulty']); ?></p>
                    <p><strong>Duration:</strong><br><?php echo $course['duration_hours']; ?> hours</p>
                    <p class="mb-0"><strong>Lessons:</strong><br><?php echo $course['lesson_count']; ?> lessons</p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>