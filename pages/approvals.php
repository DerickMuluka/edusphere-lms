<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['admin']);

$user = current_user();
$conn = db();

/* ---- Single action ---- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && isset($_POST['user_id'])) {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) { set_flash('error','Invalid session.'); redirect($_SERVER['PHP_SELF']); }

    $targetId = (int)$_POST['user_id'];
    $action = $_POST['action'];
    $reason = clean($_POST['reason'] ?? '');

    if ($targetId === (int)$user['id']) { set_flash('error','You cannot change your own status.'); redirect($_SERVER['PHP_SELF']); }

    switch ($action) {
        case 'approve':
            set_user_status($targetId, 'active', '');
            set_flash('success', 'Account approved.');
            break;
        case 'reject':
            set_user_status($targetId, 'rejected', $reason ?: 'No reason provided.');
            set_flash('success', 'Account rejected.');
            break;
        case 'suspend':
            set_user_status($targetId, 'suspended', $reason ?: 'Policy violation.');
            set_flash('success', 'Account suspended.');
            break;
        case 'reactivate':
            set_user_status($targetId, 'active', '');
            set_flash('success', 'Account reactivated.');
            break;
    }
    redirect('approvals.php');
}

/* ---- Bulk action ---- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action'])) {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) { set_flash('error','Invalid session.'); redirect($_SERVER['PHP_SELF']); }
    $ids = array_map('intval', $_POST['ids'] ?? []);
    $action = $_POST['bulk_action'];
    $reason = clean($_POST['bulk_reason'] ?? '');
    $done = 0;
    foreach ($ids as $id) {
        if ($id === (int)$user['id']) continue;
        if ($action === 'approve') { set_user_status($id, 'active', ''); $done++; }
        if ($action === 'reject')  { set_user_status($id, 'rejected', $reason ?: 'Batch rejection'); $done++; }
    }
    set_flash('success', "$done account(s) processed.");
    redirect('approvals.php');
}

/* ---- Load queues ---- */
$pending = $conn->query("
    SELECT u.*, d.name AS dept_name, d.code AS dept_code
    FROM users u LEFT JOIN departments d ON d.id = u.department_id
    WHERE u.status = 'pending'
    ORDER BY u.created_at ASC
")->fetch_all(MYSQLI_ASSOC);

$suspended = $conn->query("
    SELECT u.*, d.name AS dept_name, d.code AS dept_code
    FROM users u LEFT JOIN departments d ON d.id = u.department_id
    WHERE u.status = 'suspended'
    ORDER BY u.created_at DESC
")->fetch_all(MYSQLI_ASSOC);

$recentHistory = $conn->query("
    SELECT h.*, u.full_name AS subject_name, a.full_name AS actor_name
    FROM status_history h
    JOIN users u ON u.id = h.user_id
    LEFT JOIN users a ON a.id = h.changed_by
    ORDER BY h.changed_at DESC LIMIT 40
")->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'Approvals';
$extraCSS = ['admin.css'];
$extraJS  = ['admin.js'];
include __DIR__ . '/../includes/header.php';
?>

<section class="panel">
    <header class="panel__head">
        <h3>Pending applications (<?php echo count($pending); ?>)</h3>
    </header>

    <?php if (!$pending): ?>
        <p class="empty">No pending applications. Queue is clear.</p>
    <?php else: ?>
        <form method="post" id="bulkForm">
            <?php echo csrf_field(); ?>
            <div class="bulk-bar">
                <label class="field--check">
                    <input type="checkbox" id="bulkSelectAll">
                    <span>Select all</span>
                </label>
                <select class="field__input" name="bulk_action" style="max-width:180px">
                    <option value="approve">Approve selected</option>
                    <option value="reject">Reject selected</option>
                </select>
                <input class="field__input" name="bulk_reason" placeholder="Reason (for rejections)" style="max-width:280px">
                <button class="btn btn--primary btn--sm" type="submit">Apply</button>
            </div>

            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th style="width:32px"></th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Department</th>
                            <th>Identifier</th>
                            <th>Applied</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($pending as $p): ?>
                        <tr>
                            <td><input type="checkbox" name="ids[]" value="<?php echo (int)$p['id']; ?>" class="js-row-check"></td>
                            <td><?php echo e($p['full_name']); ?><br><span class="muted"><?php echo e($p['username']); ?></span></td>
                            <td><?php echo e($p['email']); ?></td>
                            <td><?php echo e(ucfirst($p['role'])); ?></td>
                            <td><?php echo e($p['dept_name'] ?: '—'); ?></td>
                            <td><?php echo e($p['admission_no'] ?: ($p['staff_id'] ?: '—')); ?></td>
                            <td><?php echo e(fmt_date($p['created_at'])); ?></td>
                            <td class="table__actions">
                                <form method="post" style="display:inline">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="action" value="approve">
                                    <input type="hidden" name="user_id" value="<?php echo (int)$p['id']; ?>">
                                    <button class="btn btn--primary btn--sm" type="submit">Approve</button>
                                </form>
                                <form method="post" style="display:inline-flex;gap:0.3rem;align-items:center">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="action" value="reject">
                                    <input type="hidden" name="user_id" value="<?php echo (int)$p['id']; ?>">
                                    <input class="field__input" name="reason" placeholder="Reason" style="width:140px;padding:0.3rem 0.5rem">
                                    <button class="btn btn--danger btn--sm" type="submit">Reject</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </form>
    <?php endif; ?>
</section>

<section class="panel">
    <header class="panel__head"><h3>Suspended accounts (<?php echo count($suspended); ?>)</h3></header>
    <?php if (!$suspended): ?>
        <p class="empty">No suspended accounts.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Actions</th></tr></thead>
                <tbody>
                <?php foreach ($suspended as $s): ?>
                    <tr>
                        <td><?php echo e($s['full_name']); ?></td>
                        <td><?php echo e($s['email']); ?></td>
                        <td><?php echo e(ucfirst($s['role'])); ?></td>
                        <td class="table__actions">
                            <form method="post" style="display:inline">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="action" value="reactivate">
                                <input type="hidden" name="user_id" value="<?php echo (int)$s['id']; ?>">
                                <button class="btn btn--primary btn--sm" type="submit">Reactivate</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<section class="panel">
    <header class="panel__head"><h3>Recent status changes</h3></header>
    <?php if (!$recentHistory): ?>
        <p class="empty">No history yet.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Subject</th><th>From</th><th>To</th><th>Reason</th><th>By</th><th>When</th></tr></thead>
                <tbody>
                <?php foreach ($recentHistory as $h): ?>
                    <tr>
                        <td><?php echo e($h['subject_name']); ?></td>
                        <td><?php echo e($h['old_status'] ? status_label($h['old_status']) : '—'); ?></td>
                        <td><span class="badge badge--status-<?php echo e($h['new_status']); ?>"><?php echo e(status_label($h['new_status'])); ?></span></td>
                        <td><?php echo e($h['reason'] ?: '—'); ?></td>
                        <td><?php echo e($h['actor_name'] ?: 'System'); ?></td>
                        <td><?php echo e(fmt_datetime($h['changed_at'])); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>