<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['admin']);

$conn = db();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_department'])) {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $error = 'Session expired.';
    } else {
        $id = (int)($_POST['id'] ?? 0);
        $code = strtoupper(clean($_POST['code'] ?? ''));
        $name = clean($_POST['name'] ?? '');
        $desc = clean($_POST['description'] ?? '');
        $head = clean($_POST['head_name'] ?? '');

        if ($code === '' || $name === '') {
            $error = 'Code and name are required.';
        } elseif ($id > 0) {
            $stmt = $conn->prepare("UPDATE departments SET code=?, name=?, description=?, head_name=? WHERE id=?");
            $stmt->bind_param('ssssi', $code, $name, $desc, $head, $id);
            $stmt->execute();
            set_flash('success','Department updated.');
            redirect('departments.php');
        } else {
            $stmt = $conn->prepare("INSERT INTO departments (code, name, description, head_name) VALUES (?,?,?,?)");
            $stmt->bind_param('ssss', $code, $name, $desc, $head);
            $stmt->execute();
            set_flash('success','Department created.');
            redirect('departments.php');
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_department'])) {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) { $error = 'Session expired.'; }
    else {
        $stmt = $conn->prepare("DELETE FROM departments WHERE id=?");
        $stmt->bind_param('i', (int)$_POST['delete_department']); $stmt->execute();
        set_flash('success','Department removed.');
        redirect('departments.php');
    }
}

$editId = (int)($_GET['edit'] ?? 0);
$edit = null;
if ($editId) {
    $stmt = $conn->prepare("SELECT * FROM departments WHERE id=?");
    $stmt->bind_param('i', $editId); $stmt->execute();
    $edit = $stmt->get_result()->fetch_assoc();
}

$departments = $conn->query("
    SELECT d.*,
      (SELECT COUNT(*) FROM courses WHERE department_id=d.id) AS course_count,
      (SELECT COUNT(*) FROM users WHERE department_id=d.id AND role='student') AS student_count,
      (SELECT COUNT(*) FROM users WHERE department_id=d.id AND role='instructor') AS instructor_count
    FROM departments d ORDER BY d.name
")->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'Departments';
$extraCSS = ['admin.css'];
include __DIR__ . '/../includes/header.php';
?>

<?php if ($error): ?><div class="alert alert--error"><?php echo e($error); ?></div><?php endif; ?>

<section class="panel">
    <header class="panel__head"><h3><?php echo $edit ? 'Edit department' : 'Create department'; ?></h3></header>
    <form method="post" class="form form--grid">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="save_department" value="1">
        <input type="hidden" name="id" value="<?php echo (int)($edit['id'] ?? 0); ?>">
        <label class="field"><span class="field__label">Code (short)</span>
            <input class="field__input" name="code" required value="<?php echo e($edit['code'] ?? ''); ?>" placeholder="CS"></label>
        <label class="field"><span class="field__label">Name</span>
            <input class="field__input" name="name" required value="<?php echo e($edit['name'] ?? ''); ?>"></label>
        <label class="field field--wide"><span class="field__label">Description</span>
            <textarea class="field__input" name="description" rows="2"><?php echo e($edit['description'] ?? ''); ?></textarea></label>
        <label class="field"><span class="field__label">Head of department</span>
            <input class="field__input" name="head_name" value="<?php echo e($edit['head_name'] ?? ''); ?>"></label>
        <div class="form__actions">
            <button class="btn btn--primary" type="submit"><?php echo $edit ? 'Update' : 'Create'; ?></button>
            <?php if ($edit): ?><a class="btn btn--ghost" href="departments.php">Cancel</a><?php endif; ?>
        </div>
    </form>
</section>

<section class="panel">
    <header class="panel__head"><h3>All departments</h3></header>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Code</th><th>Name</th><th>Head</th><th>Courses</th><th>Students</th><th>Instructors</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($departments as $d): ?>
                <tr>
                    <td><?php echo e($d['code']); ?></td>
                    <td><?php echo e($d['name']); ?></td>
                    <td><?php echo e($d['head_name'] ?: '—'); ?></td>
                    <td><?php echo (int)$d['course_count']; ?></td>
                    <td><?php echo (int)$d['student_count']; ?></td>
                    <td><?php echo (int)$d['instructor_count']; ?></td>
                    <td class="table__actions">
                        <a class="btn btn--ghost btn--sm" href="?edit=<?php echo (int)$d['id']; ?>">Edit</a>
                        <form method="post" style="display:inline" onsubmit="return confirm('Delete this department? Courses will lose their department link.');">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="delete_department" value="<?php echo (int)$d['id']; ?>">
                            <button class="btn btn--danger btn--sm" type="submit">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>