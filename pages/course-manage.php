<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['instructor','admin']);

$user = current_user();
$uid = (int)$user['id'];
$conn = db();

/* Course save */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_course'])) {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) { set_flash('error','Invalid session.'); redirect($_SERVER['PHP_SELF']); }
    $id = (int)($_POST['id'] ?? 0);
    $code = clean($_POST['code'] ?? '');
    $title = clean($_POST['title'] ?? '');
    $desc = clean($_POST['description'] ?? '');
    $cat = clean($_POST['category'] ?? '');
    $deptId = (int)($_POST['department_id'] ?? 0) ?: null;
    $level = in_array($_POST['level'] ?? '', ['beginner','intermediate','advanced'], true) ? $_POST['level'] : 'beginner';
    $hours = (int)($_POST['duration_hours'] ?? 0);
    $published = isset($_POST['is_published']) ? 1 : 0;
    $thumb = upload_file('thumbnail', 'courses', ['png','jpg','jpeg','gif','webp']);

    if ($code === '' || $title === '') {
        set_flash('error','Course code and title required.');
    } elseif ($id > 0) {
        if ($thumb) {
            $stmt = $conn->prepare("UPDATE courses SET code=?, title=?, description=?, category=?, department_id=?, level=?, duration_hours=?, is_published=?, thumbnail=? WHERE id=?");
            $stmt->bind_param('ssssisii si', $code,$title,$desc,$cat,$deptId,$level,$hours,$published,$thumb,$id);
            $stmt = $conn->prepare("UPDATE courses SET code=?, title=?, description=?, category=?, department_id=?, level=?, duration_hours=?, is_published=?, thumbnail=? WHERE id=?");
            $stmt->bind_param('ssssisisi i', $code,$title,$desc,$cat,$deptId,$level,$hours,$published,$thumb,$id);
            $stmt = $conn->prepare("UPDATE courses SET code=?, title=?, description=?, category=?, department_id=?, level=?, duration_hours=?, is_published=?, thumbnail=? WHERE id=?");
            $stmt->bind_param('ssssisissi', $code,$title,$desc,$cat,$deptId,$level,$hours,$published,$thumb,$id);
        } else {
            $stmt = $conn->prepare("UPDATE courses SET code=?, title=?, description=?, category=?, department_id=?, level=?, duration_hours=?, is_published=? WHERE id=?");
            $stmt->bind_param('ssssisiii', $code,$title,$desc,$cat,$deptId,$level,$hours,$published,$id);
        }
        $stmt->execute();
        set_flash('success','Course updated.');
    } else {
        $stmt = $conn->prepare("INSERT INTO courses (code,title,description,category,department_id,level,duration_hours,is_published,thumbnail,instructor_id) VALUES (?,?,?,?,?,?,?,?,?,?)");
        $stmt->bind_param('ssssisissi', $code,$title,$desc,$cat,$deptId,$level,$hours,$published,$thumb,$uid);
        $stmt->execute();
        set_flash('success','Course created.');
    }
    redirect('course-manage.php' . ($id ? '?id=' . $id : ''));
}

/* Course delete */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_course']) && is_admin()) {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) { set_flash('error','Invalid session.'); redirect($_SERVER['PHP_SELF']); }
    $stmt = $conn->prepare("DELETE FROM courses WHERE id=?");
    $stmt->bind_param('i', (int)$_POST['delete_course']); $stmt->execute();
    set_flash('success','Course deleted.'); redirect('course-manage.php');
}

/* Add lesson */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_lesson'])) {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) { set_flash('error','Invalid session.'); redirect($_SERVER['PHP_SELF']); }
    $courseId = (int)$_POST['course_id'];

    $own = $conn->prepare("SELECT id FROM courses WHERE id=? " . (is_admin() ? "" : "AND instructor_id=?"));
    if (is_admin()) { $own->bind_param('i', $courseId); }
    else            { $own->bind_param('ii', $courseId, $uid); }
    $own->execute();
    if (!$own->get_result()->num_rows) { set_flash('error','Not your course.'); redirect($_SERVER['PHP_SELF']); }

    $title = clean($_POST['title'] ?? '');
    $content = clean($_POST['content'] ?? '');
    $video = clean($_POST['video_url'] ?? '');
    $order = (int)($_POST['lesson_order'] ?? 0);
    $mins = (int)($_POST['duration_minutes'] ?? 0);
    $attachment = upload_file('attachment', 'notes');

    if ($title === '') set_flash('error','Lesson title required.');
    else {
        $stmt = $conn->prepare("INSERT INTO lessons (course_id,title,content,video_url,attachment,lesson_order,duration_minutes) VALUES (?,?,?,?,?,?,?)");
        $stmt->bind_param('issssii', $courseId,$title,$content,$video,$attachment,$order,$mins);
        $stmt->execute();
        set_flash('success','Lesson added.');
    }
    redirect('course-manage.php?id=' . $courseId);
}

/* Delete lesson */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_lesson'])) {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) { set_flash('error','Invalid session.'); redirect($_SERVER['PHP_SELF']); }
    $stmt = $conn->prepare("DELETE l FROM lessons l JOIN courses c ON c.id=l.course_id WHERE l.id=? " . (is_admin() ? "" : "AND c.instructor_id=?"));
    if (is_admin()) { $stmt->bind_param('i', (int)$_POST['delete_lesson']); }
    else            { $stmt->bind_param('ii', (int)$_POST['delete_lesson'], $uid); }
    $stmt->execute();
    set_flash('success','Lesson removed.');
    redirect('course-manage.php?id=' . (int)$_POST['course_id']);
}

/* Add past paper */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_paper'])) {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) { set_flash('error','Invalid session.'); redirect($_SERVER['PHP_SELF']); }
    $courseId = (int)$_POST['course_id'];

    $title = clean($_POST['paper_title'] ?? '');
    $year = (int)($_POST['year'] ?? date('Y'));
    $type = in_array($_POST['exam_type'] ?? '', ['cat','midterm','final','supplementary'], true) ? $_POST['exam_type'] : 'final';
    $external = clean($_POST['external_url'] ?? '');
    $file = upload_file('paper_file', 'papers');

    if ($title === '') set_flash('error','Paper title required.');
    else {
        $stmt = $conn->prepare("INSERT INTO past_papers (course_id,title,year,exam_type,file_path,external_url,uploaded_by) VALUES (?,?,?,?,?,?,?)");
        $stmt->bind_param('isssssi', $courseId,$title,$year,$type,$file,$external,$uid);
        $stmt->execute();
        set_flash('success','Past paper added.');
    }
    redirect('course-manage.php?id=' . $courseId);
}

/* Add note */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_note'])) {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) { set_flash('error','Invalid session.'); redirect($_SERVER['PHP_SELF']); }
    $courseId = (int)$_POST['course_id'];
    $title = clean($_POST['note_title'] ?? '');
    $desc = clean($_POST['note_description'] ?? '');
    $file = upload_file('note_file', 'notes');

    if ($title === '' || !$file) set_flash('error','Title and file required.');
    else {
        $size = (int)@filesize(UPLOAD_DIR . $file);
        $stmt = $conn->prepare("INSERT INTO notes (course_id,title,description,file_path,file_size,uploaded_by) VALUES (?,?,?,?,?,?)");
        $stmt->bind_param('isssii', $courseId,$title,$desc,$file,$size,$uid);
        $stmt->execute();
        set_flash('success','Note uploaded.');
    }
    redirect('course-manage.php?id=' . $courseId);
}

/* Delete note */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_note'])) {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) { set_flash('error','Invalid session.'); redirect($_SERVER['PHP_SELF']); }
    $stmt = $conn->prepare("DELETE FROM notes WHERE id=?");
    $stmt->bind_param('i', (int)$_POST['delete_note']); $stmt->execute();
    set_flash('success','Note removed.');
    redirect('course-manage.php?id=' . (int)$_POST['course_id']);
}

/* Load context */
$editId = (int)($_GET['id'] ?? 0);
$editCourse = null; $editLessons = []; $editPapers = []; $editNotes = [];
if ($editId) {
    $stmt = $conn->prepare("SELECT * FROM courses WHERE id=?");
    $stmt->bind_param('i', $editId); $stmt->execute();
    $editCourse = $stmt->get_result()->fetch_assoc();
    if (!$editCourse) { set_flash('error','Course not found.'); redirect('course-manage.php'); }

    $stmt = $conn->prepare("SELECT * FROM lessons WHERE course_id=? ORDER BY lesson_order, id");
    $stmt->bind_param('i', $editId); $stmt->execute();
    $editLessons = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $stmt = $conn->prepare("SELECT * FROM past_papers WHERE course_id=? ORDER BY year DESC, id DESC");
    $stmt->bind_param('i', $editId); $stmt->execute();
    $editPapers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $stmt = $conn->prepare("SELECT * FROM notes WHERE course_id=? ORDER BY created_at DESC");
    $stmt->bind_param('i', $editId); $stmt->execute();
    $editNotes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

$departments = $conn->query("SELECT id, code, name FROM departments ORDER BY name")->fetch_all(MYSQLI_ASSOC);

if (is_admin()) $stmt = $conn->prepare("SELECT c.*, d.code AS dept_code,
    (SELECT COUNT(*) FROM lessons WHERE course_id=c.id) lessons,
    (SELECT COUNT(*) FROM enrollments WHERE course_id=c.id) students
    FROM courses c LEFT JOIN departments d ON d.id=c.department_id ORDER BY c.code");
else $stmt = $conn->prepare("SELECT c.*, d.code AS dept_code,
    (SELECT COUNT(*) FROM lessons WHERE course_id=c.id) lessons,
    (SELECT COUNT(*) FROM enrollments WHERE course_id=c.id) students
    FROM courses c LEFT JOIN departments d ON d.id=c.department_id
    WHERE c.instructor_id=? ORDER BY c.code");
if (!is_admin()) $stmt->bind_param('i', $uid);
$stmt->execute();
$myCourses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'Manage courses';
$extraCSS = ['courses.css'];
include __DIR__ . '/../includes/header.php';
?>

<section class="panel">
    <header class="panel__head"><h3><?php echo $editCourse ? 'Edit course' : 'Create a new course'; ?></h3></header>
    <form method="post" class="form form--grid" enctype="multipart/form-data">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="id" value="<?php echo (int)($editCourse['id'] ?? 0); ?>">
        <input type="hidden" name="save_course" value="1">

        <label class="field"><span class="field__label">Course code</span>
            <input class="field__input" name="code" required value="<?php echo e($editCourse['code'] ?? ''); ?>"></label>
        <label class="field"><span class="field__label">Title</span>
            <input class="field__input" name="title" required value="<?php echo e($editCourse['title'] ?? ''); ?>"></label>
        <label class="field field--wide"><span class="field__label">Description</span>
            <textarea class="field__input" name="description" rows="3"><?php echo e($editCourse['description'] ?? ''); ?></textarea></label>
        <label class="field"><span class="field__label">Department</span>
            <select class="field__input" name="department_id">
                <option value="0">— none —</option>
                <?php foreach ($departments as $d): ?>
                    <option value="<?php echo (int)$d['id']; ?>" <?php echo (($editCourse['department_id'] ?? 0) == $d['id']) ? 'selected' : ''; ?>>
                        <?php echo e($d['code'] . ' — ' . $d['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select></label>
        <label class="field"><span class="field__label">Category</span>
            <input class="field__input" name="category" value="<?php echo e($editCourse['category'] ?? ''); ?>"></label>
        <label class="field"><span class="field__label">Level</span>
            <select class="field__input" name="level">
                <?php foreach (['beginner','intermediate','advanced'] as $lv): ?>
                    <option value="<?php echo $lv; ?>" <?php echo (($editCourse['level'] ?? '') === $lv) ? 'selected' : ''; ?>><?php echo ucfirst($lv); ?></option>
                <?php endforeach; ?>
            </select></label>
        <label class="field"><span class="field__label">Duration (hours)</span>
            <input class="field__input" type="number" min="0" name="duration_hours" value="<?php echo (int)($editCourse['duration_hours'] ?? 0); ?>"></label>
        <label class="field"><span class="field__label">Thumbnail (optional)</span>
            <input class="field__input" type="file" name="thumbnail" accept="image/*"></label>
        <label class="field field--check">
            <input type="checkbox" name="is_published" value="1" <?php echo !empty($editCourse['is_published']) ? 'checked' : ''; ?>>
            <span>Publish this course</span>
        </label>
        <div class="form__actions">
            <button class="btn btn--primary" type="submit"><?php echo $editCourse ? 'Update course' : 'Create course'; ?></button>
            <?php if ($editCourse): ?><a class="btn btn--ghost" href="course-manage.php">Cancel</a><?php endif; ?>
        </div>
    </form>
</section>

<?php if ($editCourse): ?>
<section class="panel">
    <header class="panel__head"><h3>Add lesson</h3></header>
    <form method="post" class="form form--grid" enctype="multipart/form-data">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="course_id" value="<?php echo (int)$editCourse['id']; ?>">
        <input type="hidden" name="save_lesson" value="1">
        <label class="field"><span class="field__label">Title</span>
            <input class="field__input" name="title" required></label>
        <label class="field"><span class="field__label">Video embed URL</span>
            <input class="field__input" name="video_url" placeholder="https://www.youtube.com/embed/..."></label>
        <label class="field"><span class="field__label">Order</span>
            <input class="field__input" type="number" name="lesson_order" value="0"></label>
        <label class="field"><span class="field__label">Duration (min)</span>
            <input class="field__input" type="number" name="duration_minutes" value="30"></label>
        <label class="field field--wide"><span class="field__label">Content</span>
            <textarea class="field__input" name="content" rows="3"></textarea></label>
        <label class="field field--wide"><span class="field__label">Attachment</span>
            <input class="field__input" type="file" name="attachment"></label>
        <div class="form__actions"><button class="btn btn--primary" type="submit">Add lesson</button></div>
    </form>

    <?php if ($editLessons): ?>
        <ul class="list list--stacked">
            <?php foreach ($editLessons as $l): ?>
                <li>
                    <div>
                        <strong><?php echo e($l['title']); ?></strong>
                        <p class="muted">Order <?php echo (int)$l['lesson_order']; ?> · <?php echo (int)$l['duration_minutes']; ?> min</p>
                    </div>
                    <form method="post" onsubmit="return confirm('Delete this lesson?');">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="delete_lesson" value="<?php echo (int)$l['id']; ?>">
                        <input type="hidden" name="course_id" value="<?php echo (int)$editCourse['id']; ?>">
                        <button class="btn btn--danger btn--sm" type="submit">Delete</button>
                    </form>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>

<section class="panel">
    <header class="panel__head"><h3>Course notes</h3></header>
    <form method="post" class="form form--grid" enctype="multipart/form-data">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="course_id" value="<?php echo (int)$editCourse['id']; ?>">
        <input type="hidden" name="save_note" value="1">
        <label class="field"><span class="field__label">Title</span>
            <input class="field__input" name="note_title" required></label>
        <label class="field"><span class="field__label">Description</span>
            <input class="field__input" name="note_description"></label>
        <label class="field field--wide"><span class="field__label">File (PDF, DOCX, etc.)</span>
            <input class="field__input" type="file" name="note_file" required></label>
        <div class="form__actions"><button class="btn btn--primary" type="submit">Upload note</button></div>
    </form>

    <?php if ($editNotes): ?>
        <ul class="list">
            <?php foreach ($editNotes as $n): ?>
                <li>
                    <div>
                        <strong><?php echo e($n['title']); ?></strong>
                        <p class="muted"><?php echo e(human_size($n['file_size'])); ?> · <?php echo e(time_ago($n['created_at'])); ?></p>
                    </div>
                    <form method="post" onsubmit="return confirm('Delete this note?');">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="delete_note" value="<?php echo (int)$n['id']; ?>">
                        <input type="hidden" name="course_id" value="<?php echo (int)$editCourse['id']; ?>">
                        <button class="btn btn--danger btn--sm" type="submit">Delete</button>
                    </form>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>

<section class="panel">
    <header class="panel__head"><h3>Past papers</h3></header>
    <form method="post" class="form form--grid" enctype="multipart/form-data">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="course_id" value="<?php echo (int)$editCourse['id']; ?>">
        <input type="hidden" name="save_paper" value="1">
        <label class="field"><span class="field__label">Title</span>
            <input class="field__input" name="paper_title" required></label>
        <label class="field"><span class="field__label">Year</span>
            <input class="field__input" type="number" name="year" value="<?php echo date('Y'); ?>"></label>
        <label class="field"><span class="field__label">Type</span>
            <select class="field__input" name="exam_type">
                <option value="cat">CAT</option>
                <option value="midterm">Midterm</option>
                <option value="final" selected>Final</option>
                <option value="supplementary">Supplementary</option>
            </select></label>
        <label class="field field--wide"><span class="field__label">External URL (optional)</span>
            <input class="field__input" name="external_url"></label>
        <label class="field field--wide"><span class="field__label">Upload file</span>
            <input class="field__input" type="file" name="paper_file"></label>
        <div class="form__actions"><button class="btn btn--primary" type="submit">Add past paper</button></div>
    </form>

    <?php if ($editPapers): ?>
        <ul class="list">
            <?php foreach ($editPapers as $p): ?>
                <li>
                    <div>
                        <strong><?php echo e($p['title']); ?></strong>
                        <p class="muted"><?php echo (int)$p['year']; ?> · <?php echo e(strtoupper($p['exam_type'])); ?></p>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
<?php endif; ?>

<section class="panel">
    <header class="panel__head"><h3><?php echo is_admin() ? 'All courses' : 'Your courses'; ?></h3></header>
    <?php if (!$myCourses): ?><p class="empty">No courses yet.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Code</th><th>Dept</th><th>Title</th><th>Lessons</th><th>Students</th><th>Status</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($myCourses as $c): ?>
                    <tr>
                        <td><?php echo e($c['code']); ?></td>
                        <td><?php echo e($c['dept_code'] ?: '—'); ?></td>
                        <td><?php echo e($c['title']); ?></td>
                        <td><?php echo (int)$c['lessons']; ?></td>
                        <td><?php echo (int)$c['students']; ?></td>
                        <td><?php echo $c['is_published'] ? 'Published' : 'Draft'; ?></td>
                        <td class="table__actions">
                            <a class="btn btn--ghost btn--sm" href="course-manage.php?id=<?php echo (int)$c['id']; ?>">Manage</a>
                            <a class="btn btn--ghost btn--sm" href="course-view.php?id=<?php echo (int)$c['id']; ?>">Open</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>