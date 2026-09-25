<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();

$user = current_user();
$uid = (int)$user['id'];
$conn = db();

$search = clean($_GET['q'] ?? '');
$dept = (int)($_GET['dept'] ?? 0);

$sql = "SELECT c.*, u.full_name AS instructor_name, d.name AS dept_name, d.code AS dept_code,
    (SELECT COUNT(*) FROM lessons WHERE course_id=c.id) AS lessons,
    (SELECT COUNT(*) FROM enrollments WHERE course_id=c.id) AS enrolled,
    (SELECT COUNT(*) FROM enrollments WHERE course_id=c.id AND user_id=?) AS mine
    FROM courses c
    JOIN users u ON u.id=c.instructor_id
    LEFT JOIN departments d ON d.id=c.department_id
    WHERE c.is_published=1";
$params = [$uid]; $types = 'i';

if ($search !== '') {
    $sql .= " AND (c.title LIKE ? OR c.code LIKE ? OR c.description LIKE ?)";
    $like = '%' . $search . '%';
    array_push($params, $like, $like, $like); $types .= 'sss';
}
if ($dept > 0) {
    $sql .= " AND c.department_id = ?";
    $params[] = $dept; $types .= 'i';
}
$sql .= " ORDER BY c.code ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$courses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$departments = $conn->query("SELECT id, code, name FROM departments ORDER BY name")->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'Course catalogue';
$extraCSS = ['courses.css'];
$extraJS = ['courses.js'];
include __DIR__ . '/../includes/header.php';
?>

<div class="toolbar">
    <form class="toolbar__search" method="get" action="">
        <input type="search" name="q" placeholder="Search by title, code or keyword" value="<?php echo e($search); ?>">
        <button class="btn btn--ghost" type="submit">Search</button>
    </form>
    <div class="chip-row">
        <a class="chip <?php echo $dept === 0 ? 'is-active' : ''; ?>" href="?dept=0<?php echo $search ? '&q='.urlencode($search) : ''; ?>">All departments</a>
        <?php foreach ($departments as $d): ?>
            <a class="chip <?php echo $dept === (int)$d['id'] ? 'is-active' : ''; ?>" href="?dept=<?php echo (int)$d['id']; ?><?php echo $search ? '&q='.urlencode($search) : ''; ?>"><?php echo e($d['code']); ?></a>
        <?php endforeach; ?>
    </div>
</div>

<?php if (!$courses): ?>
    <p class="empty">No courses match your search.</p>
<?php else: ?>
    <div class="card-grid">
        <?php foreach ($courses as $c): ?>
            <article class="course-card">
                <?php if (!empty($c['thumbnail']) && file_exists(UPLOAD_DIR . $c['thumbnail'])): ?>
                    <div class="course-card__thumb" style="background-image:url('<?php echo e(UPLOAD_URL . $c['thumbnail']); ?>')"></div>
                <?php else: ?>
                    <div class="course-card__thumb course-card__thumb--<?php echo e(strtolower($c['dept_code'] ?: 'gen')); ?>">
                        <span><?php echo e($c['dept_code'] ?: 'GEN'); ?></span>
                    </div>
                <?php endif; ?>
                <div class="course-card__body">
                    <div class="course-card__head">
                        <span class="badge badge--level-<?php echo e($c['level']); ?>"><?php echo e(ucfirst($c['level'])); ?></span>
                        <span class="course-card__code"><?php echo e($c['code']); ?></span>
                    </div>
                    <h3 class="course-card__title">
                        <a href="course-view.php?id=<?php echo (int)$c['id']; ?>"><?php echo e($c['title']); ?></a>
                    </h3>
                    <p class="course-card__desc"><?php echo e(mb_substr($c['description'] ?? '', 0, 110)); ?><?php echo mb_strlen($c['description'] ?? '') > 110 ? '…' : ''; ?></p>
                    <dl class="course-card__stats">
                        <div><dt>Instructor</dt><dd><?php echo e($c['instructor_name']); ?></dd></div>
                        <div><dt>Lessons</dt><dd><?php echo (int)$c['lessons']; ?></dd></div>
                        <div><dt>Enrolled</dt><dd><?php echo (int)$c['enrolled']; ?></dd></div>
                        <div><dt>Duration</dt><dd><?php echo (int)$c['duration_hours']; ?>h</dd></div>
                    </dl>
                    <?php if (is_student()): ?>
                        <?php if ((int)$c['mine'] > 0): ?>
                            <a class="btn btn--secondary btn--block" href="course-view.php?id=<?php echo (int)$c['id']; ?>">Continue course</a>
                        <?php else: ?>
                            <button class="btn btn--primary btn--block js-enroll" data-course-id="<?php echo (int)$c['id']; ?>">Enroll in this course</button>
                        <?php endif; ?>
                    <?php else: ?>
                        <a class="btn btn--secondary btn--block" href="course-view.php?id=<?php echo (int)$c['id']; ?>">Open course</a>
                    <?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>