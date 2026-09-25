<?php
require_once __DIR__ . '/../includes/auth.php';

if (is_logged_in()) redirect(SITE_URL . '/pages/dashboard.php');

$error = '';
$statusMessage = '';

// Show flash message posted by set_flash()
// Show a status message if a non-active session was just cleared
if (!empty($_SESSION['account_status'])) {
    $s = $_SESSION['account_status'];
    if ($s === 'pending')   $statusMessage = 'Your account is still awaiting administrator approval.';
    if ($s === 'rejected')  $statusMessage = 'Your application was rejected.' . (!empty($_SESSION['rejection_reason']) ? ' Reason: ' . $_SESSION['rejection_reason'] : '');
    if ($s === 'suspended') $statusMessage = 'Your account has been suspended. Contact the administrator.';
    unset($_SESSION['account_status'], $_SESSION['rejection_reason']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $error = 'Your session expired. Please try again.';
    } else {
        $email = clean($_POST['email'] ?? '');
        $password = (string)($_POST['password'] ?? '');
        if ($email === '' || $password === '') {
            $error = 'Enter your email and password.';
        } else {
            $stmt = db()->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();

            if (!$user || !password_verify($password, $user['password'])) {
                $error = 'Invalid credentials.';
            } else {
                switch ($user['status']) {
                    case 'active':
                        $_SESSION['user_id'] = (int)$user['id'];
                        db()->query("UPDATE users SET last_login_at=NOW() WHERE id=" . (int)$user['id']);
                        set_flash('success', 'Signed in as ' . $user['full_name'] . '.');
                        redirect(SITE_URL . '/pages/dashboard.php');
                        break;

                    case 'pending':
                        $statusMessage = 'Your account is still awaiting administrator approval. You will be notified once approved.';
                        break;

                    case 'rejected':
                        $statusMessage = 'Your application was rejected.' . (!empty($user['rejection_reason']) ? ' Reason: ' . $user['rejection_reason'] : '');
                        break;

                    case 'suspended':
                        $statusMessage = 'Your account has been suspended. Contact the administrator.';
                        break;

                    default:
                        $statusMessage = 'Your account cannot sign in right now.';
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign in · <?php echo e(SITE_NAME); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Source+Serif+4:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo e(SITE_URL); ?>/assets/css/base.css">
    <link rel="stylesheet" href="<?php echo e(SITE_URL); ?>/assets/css/auth.css">
</head>
<body class="auth-page">
    <main class="auth-card">
        <div class="auth-card__head">
            <span class="auth-card__logo">ES</span>
            <h1><?php echo e(SITE_NAME); ?></h1>
            <p>Access your learning environment.</p>
        </div>

        <?php if ($error): ?><div class="alert alert--error"><?php echo e($error); ?></div><?php endif; ?>
        <?php if ($statusMessage): ?><div class="alert alert--warning"><?php echo e($statusMessage); ?></div><?php endif; ?>

        <form method="post" action="" class="form" autocomplete="on">
            <?php echo csrf_field(); ?>
            <label class="field">
                <span class="field__label">Email address</span>
                <input class="field__input" type="email" name="email" required autocomplete="email"
                       value="<?php echo e($_POST['email'] ?? ''); ?>">
            </label>
            <label class="field">
                <span class="field__label">Password</span>
                <span class="field__password">
                    <input class="field__input" type="password" name="password" required autocomplete="current-password">
                    <button type="button" class="field__toggle" data-password-toggle aria-label="Show password">
                        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                            <circle cx="12" cy="12" r="3"/>
                        </svg>
                    </button>
                </span>
            </label>
            <button class="btn btn--primary btn--block" type="submit">Sign in</button>
        </form>

        <!--<div class="auth-card__demo">
            <strong>Demo accounts (password: password)</strong>
            <span>admin@demo.com &mdash; Administrator</span>
            <span>instructor@demo.com &mdash; Instructor</span>
            <span>student@demo.com &mdash; Active student</span>
            <span>alex@demo.com &mdash; Pending applicant</span>
        </div>-->

        <p class="auth-card__alt">No account yet? <a href="register.php">Apply for access</a></p>
    </main>

    <script src="<?php echo e(SITE_URL); ?>/assets/js/core.js"></script>
    <script src="<?php echo e(SITE_URL); ?>/assets/js/auth.js"></script>
</body>
</html>