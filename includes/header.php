<?php
/**
 * EduSphere LMS - Shared header (document head + top bar)
 * v4: Includes global account-status banner for non-active users
 */
require_once __DIR__ . '/auth.php';

$__user      = current_user();
$__pageTitle = $pageTitle ?? 'Dashboard';
$__extraCSS  = $extraCSS ?? [];
$__extraJS   = $extraJS  ?? [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e($__pageTitle); ?> · <?php echo e(SITE_NAME); ?></title>

    <!-- Base URL exposed to JavaScript -->
    <meta name="app-base" content="<?php echo e(SITE_URL); ?>">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Source+Serif+4:wght@600;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="<?php echo e(SITE_URL); ?>/assets/css/base.css">
    <link rel="stylesheet" href="<?php echo e(SITE_URL); ?>/assets/css/layout.css">
    <link rel="stylesheet" href="<?php echo e(SITE_URL); ?>/assets/css/components.css">
    <?php foreach ($__extraCSS as $css): ?>
        <link rel="stylesheet" href="<?php echo e(SITE_URL . '/assets/css/' . $css); ?>">
    <?php endforeach; ?>
</head>
<body>
<?php $flash = take_flash(); ?>
<?php if ($flash): ?>
    <div class="flash flash--<?php echo e($flash['type']); ?>" id="flashBar">
        <span><?php echo e($flash['message']); ?></span>
        <button type="button" aria-label="Dismiss" onclick="this.parentElement.remove()">&times;</button>
    </div>
<?php endif; ?>

<div class="app">
    <?php include __DIR__ . '/sidebar.php'; ?>

    <div class="app__main">
        <header class="topbar">
            <button class="topbar__burger" id="sidebarToggle" type="button" aria-label="Toggle navigation">
                <span></span><span></span><span></span>
            </button>
            <div class="topbar__title">
                <h1><?php echo e($__pageTitle); ?></h1>
            </div>
            <div class="topbar__user">
                <div class="user-chip" id="userChip">
                    <?php if (!empty($__user['avatar']) && file_exists(UPLOAD_DIR . $__user['avatar'])): ?>
                        <img class="user-chip__avatar-img" src="<?php echo e(UPLOAD_URL . $__user['avatar']); ?>" alt="">
                    <?php else: ?>
                        <span class="user-chip__avatar"><?php echo e(initials($__user['full_name'])); ?></span>
                    <?php endif; ?>
                    <span class="user-chip__name"><?php echo e($__user['full_name']); ?></span>
                    <span class="user-chip__role"><?php echo e(ucfirst($__user['role'])); ?></span>
                </div>
                <div class="user-menu" id="userMenu">
                    <a href="<?php echo e(SITE_URL); ?>/pages/profile.php">My profile</a>
                    <?php if (is_admin()): ?>
                        <a href="<?php echo e(SITE_URL); ?>/pages/admin.php">Administration</a>
                    <?php endif; ?>
                    <a href="<?php echo e(SITE_URL); ?>/pages/logout.php">Sign out</a>
                </div>
            </div>
        </header>

        <section class="content">
            <?php
            /**
             * Global account-status banner.
             * Shows a warning when the signed-in user's status
             * is not 'active' (pending / suspended / rejected).
             * Skipped on the login page since it's unauthenticated.
             */
            $__me = current_user();
            $__currentScript = basename($_SERVER['PHP_SELF']);
            if ($__me && $__me['status'] !== 'active' && $__currentScript !== 'login.php'):
            ?>
                <div class="alert alert--warning">
                    <strong>Account status: <?php echo e(status_label($__me['status'])); ?>.</strong>
                    Some features are restricted.
                    <?php if ($__me['status'] === 'rejected' && !empty($__me['rejection_reason'])): ?>
                        <br>Reason: <?php echo e($__me['rejection_reason']); ?>
                    <?php endif; ?>
                    Contact an administrator if you believe this is a mistake.
                </div>
            <?php endif; ?>