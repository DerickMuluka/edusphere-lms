<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();

$user = current_user();
$uid = (int)$user['id'];
$conn = db();

$stats = ['courses'=>0,'pending'=>0,'avg'=>0,'exams'=>0];

if (is_student()) {
    $stmt = $conn->prepare("SELECT COUNT(*) c, COALESCE(AVG(progress_percentage),0) a FROM enrollments WHERE user_id = ?");
    $stmt->bind_param('i', $uid); $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stats['courses'] = (int)$row['c']; $stats['avg'] = (int)round($row['a']);

    $stmt = $conn->prepare("SELECT COUNT(*) c FROM assignments a
        JOIN enrollments e ON e.course_id=a.course_id AND e.user_id=?
        LEFT JOIN submissions s ON s.assignment_id=a.id AND s.user_id=?
        WHERE a.is_published=1 AND s.id IS NULL");
    $stmt->bind_param('ii', $uid, $uid); $stmt->execute();
    $stats['pending'] = (int)$stmt->get_result()->fetch_assoc()['c'];

    $stmt = $conn->prepare("SELECT COUNT(*) c FROM exams x
        JOIN enrollments e ON e.course_id=x.course_id AND e.user_id=?
        WHERE x.is_published=1 AND (x.end_at IS NULL OR x.end_at >= NOW())");
    $stmt->bind_param('i', $uid); $stmt->execute();
    $stats['exams'] = (int)$stmt->get_result()->fetch_assoc()['c'];
} else {
    $stmt = $conn->prepare("SELECT COUNT(*) c FROM courses WHERE instructor_id = ?");
    $stmt->bind_param('i', $uid); $stmt->execute();
    $stats['courses'] = (int)$stmt->get_result()->fetch_assoc()['c'];

    $stmt = $conn->prepare("SELECT COUNT(*) c FROM submissions s
        JOIN assignments a ON a.id=s.assignment_id
        JOIN courses c ON c.id=a.course_id
        WHERE c.instructor_id=? AND s.grade IS NULL");
    $stmt->bind_param('i', $uid); $stmt->execute();
    $stats['pending'] = (int)$stmt->get_result()->fetch_assoc()['c'];

    $stmt = $conn->prepare("SELECT COUNT(DISTINCT e.user_id) c FROM enrollments e
        JOIN courses c ON c.id=e.course_id WHERE c.instructor_id=?");
    $stmt->bind_param('i', $uid); $stmt->execute();
    $stats['avg'] = (int)$stmt->get_result()->fetch_assoc()['c'];

    $stmt = $conn->prepare("SELECT COUNT(*) c FROM exams x
        JOIN courses c ON c.id=x.course_id WHERE c.instructor_id=?");
    $stmt->bind_param('i', $uid); $stmt->execute();
    $stats['exams'] = (int)$stmt->get_result()->fetch_assoc()['c'];
}

if (is_student()) {
    $stmt = $conn->prepare("SELECT c.*, u.full_name AS instructor_name, e.progress_percentage
        FROM courses c JOIN enrollments e ON e.course_id=c.id AND e.user_id=?
        JOIN users u ON u.id=c.instructor_id
        ORDER BY e.enrolled_at DESC LIMIT 6");
    $stmt->bind_param('i', $uid);
} else {
    $stmt = $conn->prepare("SELECT c.*, u.full_name AS instructor_name,
        (SELECT COUNT(*) FROM enrollments WHERE course_id=c.id) AS enrolled,
        (SELECT COUNT(*) FROM lessons WHERE course_id=c.id) AS lessons
        FROM courses c JOIN users u ON u.id=c.instructor_id
        WHERE c.instructor_id=? ORDER BY c.created_at DESC LIMIT 6");
    $stmt->bind_param('i', $uid);
}
$stmt->execute();
$activeCourses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

if (is_student()) {
    $stmt = $conn->prepare("SELECT a.*, c.title AS course_title, u.full_name AS author
        FROM announcements a
        JOIN courses c ON c.id=a.course_id
        JOIN enrollments e ON e.course_id=c.id AND e.user_id=?
        JOIN users u ON u.id=a.user_id
        ORDER BY a.created_at DESC LIMIT 4");
    $stmt->bind_param('i', $uid);
} else {
    $stmt = $conn->prepare("SELECT a.*, c.title AS course_title, u.full_name AS author
        FROM announcements a
        JOIN courses c ON c.id=a.course_id
        JOIN users u ON u.id=a.user_id
        WHERE c.instructor_id=? ORDER BY a.created_at DESC LIMIT 4");
    $stmt->bind_param('i', $uid);
}
$stmt->execute();
$announcements = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

if (is_student()) {
    $stmt = $conn->prepare("SELECT a.id, a.title, a.due_date, c.title AS course_title
        FROM assignments a
        JOIN enrollments e ON e.course_id=a.course_id AND e.user_id=?
        JOIN courses c ON c.id=a.course_id
        LEFT JOIN submissions s ON s.assignment_id=a.id AND s.user_id=?
        WHERE a.is_published=1 AND s.id IS NULL AND (a.due_date IS NULL OR a.due_date >= NOW())
        ORDER BY a.due_date ASC LIMIT 5");
    $stmt->bind_param('ii', $uid, $uid);
} else {
    $stmt = $conn->prepare("SELECT a.id, a.title, a.due_date, c.title AS course_title
        FROM assignments a JOIN courses c ON c.id=a.course_id
        WHERE c.instructor_id=? AND a.is_published=1
        ORDER BY a.due_date ASC LIMIT 5");
    $stmt->bind_param('i', $uid);
}
$stmt->execute();
$deadlines = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'Dashboard';
$extraCSS = ['dashboard.css'];
include __DIR__ . '/../includes/header.php';
?>

<div class="dash-hero">
    <div>
        <p class="dash-hero__eyebrow"><?php echo is_admin() ? 'Administrator console' : (is_student() ? 'Student console' : 'Instructor console'); ?></p>
        <h2 class="dash-hero__name"><?php echo e($user['full_name']); ?></h2>
        <p class="dash-hero__meta">
            <?php echo e($user['department_name'] ?: 'No department'); ?>
            <?php if (is_student() && $user['admission_no']): ?> · <?php echo e($user['admission_no']); ?><?php endif; ?>
            <?php if (is_staff() && $user['staff_id']): ?> · <?php echo e($user['staff_id']); ?><?php endif; ?>
        </p>
    </div>
    <div class="dash-hero__actions">
        <a class="btn btn--primary" href="<?php echo e(SITE_URL); ?>/pages/courses.php">
            <?php echo is_student() ? 'Browse catalogue' : 'Manage courses'; ?>
        </a>
    </div>
</div>

<div class="stat-grid">
    <div class="stat"><span class="stat__label"><?php echo is_student() ? 'Enrolled courses' : 'Courses taught'; ?></span>
        <span class="stat__value"><?php echo $stats['courses']; ?></span></div>
    <div class="stat"><span class="stat__label"><?php echo is_student() ? 'Pending assignments' : 'Ungraded submissions'; ?></span>
        <span class="stat__value"><?php echo $stats['pending']; ?></span></div>
    <div class="stat"><span class="stat__label"><?php echo is_student() ? 'Average progress' : 'Students reached'; ?></span>
        <span class="stat__value"><?php echo $stats['avg']; ?><?php echo is_student() ? '%' : ''; ?></span></div>
    <div class="stat"><span class="stat__label">Active exams</span>
        <span class="stat__value"><?php echo $stats['exams']; ?></span></div>
</div>

<div class="dash-grid">
    <section class="panel">
        <header class="panel__head"><h3><?php echo is_student() ? 'Continue learning' : 'Your courses'; ?></h3>
            <a class="panel__link" href="<?php echo e(SITE_URL); ?>/pages/courses.php">View all</a></header>
        <?php if (!$activeCourses): ?><p class="empty">No courses to display yet.</p>
        <?php else: ?>
            <ul class="course-list">
                <?php foreach ($activeCourses as $c): ?>
                    <li class="course-list__item">
                        <div>
                            <p class="course-list__code"><?php echo e($c['code']); ?></p>
                            <a class="course-list__title" href="<?php echo e(SITE_URL); ?>/pages/course-view.php?id=<?php echo (int)$c['id']; ?>"><?php echo e($c['title']); ?></a>
                            <p class="course-list__meta"><?php echo e($c['instructor_name']); ?> · <?php echo (int)$c['duration_hours']; ?>h
                                <?php if (is_staff()): ?> · <?php echo (int)$c['enrolled']; ?> students · <?php echo (int)$c['lessons']; ?> lessons<?php endif; ?>
                            </p>
                        </div>
                        <?php if (is_student()): ?>
                            <div class="course-list__progress">
                                <span><?php echo (int)$c['progress_percentage']; ?>%</span>
                                <div class="progress"><div class="progress__bar" style="width:<?php echo (int)$c['progress_percentage']; ?>%"></div></div>
                            </div>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <section class="panel">
        <header class="panel__head"><h3><?php echo is_student() ? 'Due soon' : 'Assignments'; ?></h3>
            <a class="panel__link" href="<?php echo e(SITE_URL); ?>/pages/assignments.php">Open</a></header>
        <?php if (!$deadlines): ?><p class="empty">Nothing scheduled.</p>
        <?php else: ?>
            <ul class="deadline-list">
                <?php foreach ($deadlines as $d): ?>
                    <li>
                        <div>
                            <a class="deadline-list__title" href="<?php echo e(SITE_URL); ?>/pages/assignment-view.php?id=<?php echo (int)$d['id']; ?>"><?php echo e($d['title']); ?></a>
                            <p class="deadline-list__meta"><?php echo e($d['course_title']); ?></p>
                        </div>
                        <span class="deadline-list__date"><?php echo e(fmt_date($d['due_date'], 'd M')); ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
</div>

<section class="panel panel--wide">
    <header class="panel__head"><h3>Announcements</h3></header>
    <?php if (!$announcements): ?><p class="empty">No announcements posted.</p>
    <?php else: ?>
        <ul class="ann-list">
            <?php foreach ($announcements as $a): ?>
                <li>
                    <div class="ann-list__head">
                        <strong><?php echo e($a['title']); ?></strong>
                        <span class="muted"><?php echo e(time_ago($a['created_at'])); ?></span>
                    </div>
                    <p><?php echo nl2br(e($a['content'])); ?></p>
                    <p class="ann-list__meta"><?php echo e($a['course_title']); ?> · <?php echo e($a['author']); ?></p>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>