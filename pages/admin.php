<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['admin']);

$user = current_user();
$conn = db();

/* Toggle user active (suspend/reactivate) */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_user'])) {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) { set_flash('error','Invalid session.'); redirect($_SERVER['PHP_SELF']); }
    $id = (int)$_POST['toggle_user'];
    if ($id === (int)$user['id']) { set_flash('error','Cannot change your own status.'); redirect('admin.php'); }

    $stmt = $conn->prepare("SELECT status FROM users WHERE id=?");
    $stmt->bind_param('i', $id); $stmt->execute();
    $cur = $stmt->get_result()->fetch_assoc()['status'] ?? 'pending';

    if ($cur === 'active') {
        set_user_status($id, 'suspended', 'Suspended by administrator.');
    } else {
        set_user_status($id, 'active', '');
    }
    set_flash('success','User status updated.');
    redirect('admin.php');
}

/* Change role */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_role'])) {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) { set_flash('error','Invalid session.'); redirect($_SERVER['PHP_SELF']); }
    $id = (int)$_POST['user_id'];
    $role = in_array($_POST['role'] ?? '', ['student','instructor','admin'], true) ? $_POST['role'] : 'student';
    if ($id === (int)$user['id']) { set_flash('error','Cannot change your own role.'); redirect('admin.php'); }

    $stmt = $conn->prepare("UPDATE users SET role=? WHERE id=?");
    $stmt->bind_param('si', $role, $id); $stmt->execute();
    set_flash('success','Role updated.');
    redirect('admin.php');
}

$users = $conn->query("
    SELECT u.*, d.code AS dept_code
    FROM users u LEFT JOIN departments d ON d.id = u.department_id
    ORDER BY
        FIELD(u.status,'pending','suspended','active','rejected'),
        u.role, u.full_name
")->fetch_all(MYSQLI_ASSOC);

$counts = $conn->query("
    SELECT
        (SELECT COUNT(*) FROM users)                              AS total_users,
        (SELECT COUNT(*) FROM users WHERE status='pending')       AS pending_users,
        (SELECT COUNT(*) FROM users WHERE status='active')        AS active_users,
        (SELECT COUNT(*) FROM users WHERE role='student')         AS total_students,
        (SELECT COUNT(*) FROM users WHERE role='instructor')      AS total_instructors,
        (SELECT COUNT(*) FROM courses)                            AS total_courses
")->fetch_assoc();

$pageTitle = 'Users & system';
$extraCSS = ['admin.css','dashboard.css'];
include __DIR__ . '/../includes/header.php';
?>

<div class="stat-grid">
    <div class="stat"><span class="stat__label">Total users</span><span class="stat__value"><?php echo (int)$counts['total_users']; ?></span></div>
    <div class="stat"><span class="stat__label">Pending review</span><span class="stat__value"><?php echo (int)$counts['pending_users']; ?></span></div>
    <div class="stat"><span class="stat__label">Active accounts</span><span class="stat__value"><?php echo (int)$counts['active_users']; ?></span></div>
    <div class="stat"><span class="stat__label">Courses</span><span class="stat__value"><?php echo (int)$counts['total_courses']; ?></span></div>
</div>

<?php if ((int)$counts['pending_users'] > 0): ?>
    <div class="alert alert--warning">
        <strong><?php echo (int)$counts['pending_users']; ?></strong> account(s) awaiting approval.
        <a class="link" href="approvals.php">Open approval queue &rarr;</a>
    </div>
<?php endif; ?>

<section class="panel">
    <header class="panel__head"><h3>All users</h3></header>
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>Name</th><th>Email</th><th>Role</th><th>Department</th>
                    <th>Status</th><th>Last login</th><th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($users as $u): ?>
                <tr>
                    <td><?php echo e($u['full_name']); ?><br><span class="muted"><?php echo e($u['username']); ?></span></td>
                    <td><?php echo e($u['email']); ?></td>
                    <td><?php echo e(ucfirst($u['role'])); ?></td>
                    <td><?php echo e($u['dept_code'] ?: '—'); ?></td>
                    <td><span class="badge badge--status-<?php echo e($u['status']); ?>"><?php echo e(status_label($u['status'])); ?></span></td>
                    <td><?php echo e($u['last_login_at'] ? fmt_datetime($u['last_login_at']) : '—'); ?></td>
                    <td class="table__actions">
                        <?php if ((int)$u['id'] !== (int)$user['id']): ?>
                            <form method="post" style="display:inline">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="toggle_user" value="<?php echo (int)$u['id']; ?>">
                                <button class="btn btn--ghost btn--sm" type="submit">
                                    <?php echo $u['status'] === 'active' ? 'Suspend' : 'Activate'; ?>
                                </button>
                            </form>
                            <form method="post" style="display:inline">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="change_role" value="1">
                                <input type="hidden" name="user_id" value="<?php echo (int)$u['id']; ?>">
                                <select class="field__input" name="role" onchange="this.form.submit()" style="display:inline-block;width:auto;padding:0.3rem 0.5rem">
                                    <?php foreach (['student','instructor','admin'] as $r): ?>
                                        <option value="<?php echo $r; ?>" <?php echo $u['role']===$r?'selected':''; ?>><?php echo ucfirst($r); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </form>
                        <?php else: ?>
                            <span class="muted">— you —</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>