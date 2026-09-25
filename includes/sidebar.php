<?php
$__u = current_user();
$__current = basename($_SERVER['PHP_SELF']);
$__unread  = $__u ? unread_notifications_count((int)$__u['id']) : 0;

function nav_item($href, $label, $key, $current, $suffix = '') {
    $active = ($current === $key) ? ' is-active' : '';
    echo '<a class="nav-item' . $active . '" href="' . e($href) . '">'
       . e($label) . $suffix . '</a>';
}
?>
<aside class="sidebar" id="sidebar">
    <div class="sidebar__brand">
        <span class="sidebar__logo">ES</span>
        <span class="sidebar__brandtext"><?php echo e(SITE_NAME); ?></span>
    </div>

    <nav class="sidebar__nav">
        <div class="nav-section">Overview</div>
        <?php nav_item(SITE_URL . '/pages/dashboard.php', 'Dashboard', 'dashboard.php', $__current); ?>
        <?php nav_item(SITE_URL . '/pages/notifications.php', 'Notifications', 'notifications.php', $__current,
              $__unread ? ' <span class="nav-badge">' . $__unread . '</span>' : ''); ?>

        <div class="nav-section">Learning</div>
        <?php nav_item(SITE_URL . '/pages/courses.php', 'Courses', 'courses.php', $__current); ?>
        <?php nav_item(SITE_URL . '/pages/assignments.php', 'Assignments', 'assignments.php', $__current); ?>
        <?php nav_item(SITE_URL . '/pages/exams.php', 'Exams', 'exams.php', $__current); ?>
        <?php nav_item(SITE_URL . '/pages/past-papers.php', 'Past papers', 'past-papers.php', $__current); ?>

        <div class="nav-section">Records</div>
        <?php nav_item(SITE_URL . '/pages/gradebook.php', 'Gradebook', 'gradebook.php', $__current); ?>
        <?php nav_item(SITE_URL . '/pages/progress.php', 'Progress', 'progress.php', $__current); ?>

        <?php if (is_instructor() || is_admin()): ?>
            <div class="nav-section">Teaching</div>
            <?php nav_item(SITE_URL . '/pages/course-manage.php', 'Manage courses', 'course-manage.php', $__current); ?>
            <?php nav_item(SITE_URL . '/pages/assignment-manage.php', 'Manage assignments', 'assignment-manage.php', $__current); ?>
            <?php nav_item(SITE_URL . '/pages/exam-manage.php', 'Manage exams', 'exam-manage.php', $__current); ?>
            <?php nav_item(SITE_URL . '/pages/students.php', 'Enrolled students', 'students.php', $__current); ?>
        <?php endif; ?>

        <?php if (is_admin()): ?>
            <?php
            $pendingCount = (int)db()->query("SELECT COUNT(*) c FROM users WHERE status='pending'")->fetch_assoc()['c'];
            ?>
            <div class="nav-section">Administration</div>
            <?php nav_item(SITE_URL . '/pages/approvals.php', 'Approvals', 'approvals.php', $__current,
                  $pendingCount ? ' <span class="nav-badge">' . $pendingCount . '</span>' : ''); ?>
            <?php nav_item(SITE_URL . '/pages/departments.php', 'Departments', 'departments.php', $__current); ?>
            <?php nav_item(SITE_URL . '/pages/admin.php', 'Users & system', 'admin.php', $__current); ?>
        <?php endif; ?>
    </nav>

    <div class="sidebar__footer">
        <a class="nav-item" href="<?php echo e(SITE_URL); ?>/pages/profile.php">Account settings</a>
    </div>
</aside>