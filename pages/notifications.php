<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();

$user = current_user();
$uid = (int)$user['id'];
$conn = db();

/* Mark one as read */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_read'])) {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) { set_flash('error','Invalid session.'); redirect($_SERVER['PHP_SELF']); }
    $stmt = $conn->prepare("UPDATE notifications SET is_read=1 WHERE id=? AND user_id=?");
    $id = (int)$_POST['mark_read'];
    $stmt->bind_param('ii', $id, $uid); $stmt->execute();
    redirect('notifications.php');
}

/* Mark all read */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_all'])) {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) { set_flash('error','Invalid session.'); redirect($_SERVER['PHP_SELF']); }
    $conn->query("UPDATE notifications SET is_read=1 WHERE user_id=" . (int)$uid);
    set_flash('success','All marked as read.');
    redirect('notifications.php');
}

/* Delete one */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) { set_flash('error','Invalid session.'); redirect($_SERVER['PHP_SELF']); }
    $stmt = $conn->prepare("DELETE FROM notifications WHERE id=? AND user_id=?");
    $id = (int)$_POST['delete_id'];
    $stmt->bind_param('ii', $id, $uid); $stmt->execute();
    redirect('notifications.php');
}

$stmt = $conn->prepare("SELECT * FROM notifications WHERE user_id=? ORDER BY is_read ASC, created_at DESC LIMIT 100");
$stmt->bind_param('i', $uid); $stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$unread = 0;
foreach ($rows as $r) if (!$r['is_read']) $unread++;

$pageTitle = 'Notifications';
$extraCSS = ['dashboard.css'];
include __DIR__ . '/../includes/header.php';
?>

<div class="toolbar">
    <p class="muted">You have <strong><?php echo $unread; ?></strong> unread notification<?php echo $unread === 1 ? '' : 's'; ?>.</p>
    <form method="post" style="display:inline">
        <?php echo csrf_field(); ?>
        <button class="btn btn--ghost btn--sm" name="mark_all" value="1" type="submit">Mark all read</button>
    </form>
</div>

<?php if (!$rows): ?>
    <p class="empty">No notifications yet.</p>
<?php else: ?>
    <ul class="notif-list">
        <?php foreach ($rows as $n): ?>
            <li class="notif-item <?php echo $n['is_read'] ? 'is-read' : 'is-unread'; ?>">
                <div class="notif-item__body">
                    <div class="notif-item__head">
                        <strong><?php echo e($n['title']); ?></strong>
                        <span class="muted"><?php echo e(time_ago($n['created_at'])); ?></span>
                    </div>
                    <?php if ($n['body']): ?><p><?php echo nl2br(e($n['body'])); ?></p><?php endif; ?>
                    <?php if ($n['link']): ?>
                        <a class="link" href="<?php echo e(SITE_URL . '/' . ltrim($n['link'], '/')); ?>">Open</a>
                    <?php endif; ?>
                </div>
                <div class="notif-item__actions">
                    <?php if (!$n['is_read']): ?>
                        <form method="post" style="display:inline">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="mark_read" value="<?php echo (int)$n['id']; ?>">
                            <button class="btn btn--ghost btn--sm" type="submit">Mark read</button>
                        </form>
                    <?php endif; ?>
                    <form method="post" style="display:inline" onsubmit="return confirm('Delete this notification?');">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="delete_id" value="<?php echo (int)$n['id']; ?>">
                        <button class="btn btn--danger btn--sm" type="submit">Delete</button>
                    </form>
                </div>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>