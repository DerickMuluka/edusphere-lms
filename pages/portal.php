<?php
/**
 * EduSphere LMS — Portal (login + register) inside the iframe modal.
 * Not linked directly from any crawlable page.
 */

require_once __DIR__ . '/../includes/auth.php';

if (is_logged_in()) {
    redirect(SITE_URL . '/pages/dashboard.php');
}

$mode = ($_GET['mode'] ?? 'login') === 'register' ? 'register' : 'login';
$error = '';

$departments = db()->query("SELECT id, code, name FROM departments ORDER BY name")->fetch_all(MYSQLI_ASSOC);

/* ---- Handle login ---- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'login') {
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
                        // Tell the parent page to redirect
                        echo '<script>parent.postMessage({type:"edusphere:portal-success",redirect:' .
                             json_encode(SITE_URL . '/pages/dashboard.php') . '},"*");</script>';
                        exit;
                    case 'pending':
                        $error = 'Your account is still awaiting administrator approval.';
                        break;
                    case 'rejected':
                        $error = 'Your application was rejected.' . (!empty($user['rejection_reason']) ? ' Reason: ' . $user['rejection_reason'] : '');
                        break;
                    case 'suspended':
                        $error = 'Your account has been suspended. Contact the administrator.';
                        break;
                    default:
                        $error = 'Your account cannot sign in right now.';
                }
            }
        }
    }
    $mode = 'login';
}

/* ---- Handle registration ---- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'register') {
    $mode = 'register';
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $error = 'Your session expired. Please try again.';
    } else {
        $full     = clean($_POST['full_name'] ?? '');
        $username = clean($_POST['username'] ?? '');
        $email    = clean($_POST['email'] ?? '');
        $role     = $_POST['role'] ?? 'student';
        $deptId   = (int)($_POST['department_id'] ?? 0);
        $phone    = clean($_POST['phone'] ?? '');
        $password = (string)($_POST['password'] ?? '');
        $confirm  = (string)($_POST['confirm_password'] ?? '');

        if ($full === '' || $username === '' || $email === '' || $password === '') {
            $error = 'All required fields must be filled.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please provide a valid email address.';
        } elseif (strlen($password) < 6) {
            $error = 'Password must be at least 6 characters.';
        } elseif ($password !== $confirm) {
            $error = 'Passwords do not match.';
        } elseif (!in_array($role, ['student','instructor'], true)) {
            $error = 'Please choose a valid account type.';
        } else {
            $conn = db();
            $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? OR username = ? LIMIT 1");
            $stmt->bind_param('ss', $email, $username);
            $stmt->execute();
            if ($stmt->get_result()->num_rows) {
                $error = 'That email or username is already taken.';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $dept = $deptId > 0 ? $deptId : null;
                $admission = null; $staffId = null;

                if ($deptId > 0) {
                    $deptStmt = $conn->prepare("SELECT code FROM departments WHERE id = ?");
                    $deptStmt->bind_param('i', $deptId); $deptStmt->execute();
                    $code = $deptStmt->get_result()->fetch_assoc()['code'] ?? 'GEN';
                    if ($role === 'student') {
                        $cntStmt = $conn->query("SELECT COUNT(*) c FROM users WHERE role='student'");
                        $n = ((int)$cntStmt->fetch_assoc()['c']) + 1;
                        $admission = sprintf('%s/%d/%03d', $code, date('Y'), $n);
                    } else {
                        $cntStmt = $conn->query("SELECT COUNT(*) c FROM users WHERE role='instructor'");
                        $n = ((int)$cntStmt->fetch_assoc()['c']) + 1;
                        $staffId = sprintf('STF-%04d', 1000 + $n);
                    }
                }

                $status = 'pending';
                $stmt = $conn->prepare("
                    INSERT INTO users
                        (full_name, username, email, password, role,
                         department_id, admission_no, staff_id, phone, status)
                    VALUES (?,?,?,?,?,?,?,?,?,?)
                ");
                $stmt->bind_param('sssssissis',
                    $full, $username, $email, $hash, $role,
                    $dept, $admission, $staffId, $phone, $status);

                if ($stmt->execute()) {
                    $newId = $conn->insert_id;
                    $hist = $conn->prepare("INSERT INTO status_history (user_id, old_status, new_status, reason) VALUES (?,?,?,?)");
                    $old = null; $reason = 'Self-registration';
                    $hist->bind_param('isss', $newId, $old, $status, $reason);
                    $hist->execute();

                    $notif = $conn->prepare("INSERT INTO notifications (user_id, title, body) VALUES (?,?,?)");
                    $title = 'Application received';
                    $body  = 'Thank you for registering. Your application is under review. You will be notified once approved.';
                    $notif->bind_param('iss', $newId, $title, $body);
                    $notif->execute();

                    $error = '';
                    $success = 'Application submitted. An administrator will review your account shortly.';
                    // reload as login with success
                    $mode = 'login';
                } else {
                    $error = 'Registration failed: ' . $conn->error;
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
    <meta name="robots" content="noindex, nofollow">
    <title>Portal · EduSphere</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Fraunces:opsz,wght@9..144,600;9..144,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo e(SITE_URL); ?>/assets/css/base.css">
    <link rel="stylesheet" href="<?php echo e(SITE_URL); ?>/assets/css/portal.css">
</head>
<body class="portal-body">

<main class="portal-card">
    <header class="portal-card__head">
        <span class="portal-brand">
            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 3 L3 8 L12 13 L21 8 Z"/>
                <path d="M3 13 L12 18 L21 13"/>
            </svg>
        </span>
        <h1><?php echo $mode === 'register' ? 'Create your account' : 'Welcome back'; ?></h1>
        <p><?php echo $mode === 'register'
            ? 'Applications are reviewed before access is granted.'
            : 'Sign in to continue to your portal.'; ?></p>
    </header>

    <?php if (!empty($success)): ?>
        <div class="alert alert--success"><?php echo e($success); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert--error"><?php echo e($error); ?></div>
    <?php endif; ?>

    <?php if ($mode === 'login'): ?>
        <form method="post" class="form" autocomplete="on">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="form" value="login">

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
                        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                    </button>
                </span>
            </label>

            <button class="btn btn--primary btn--block" type="submit">Sign in</button>
        </form>

        <!--
        <div class="demo">
            <strong>Demo accounts (password: password)</strong>
            <span>admin@demo.com · Administrator</span>
            <span>instructor@demo.com · Instructor</span>
            <span>student@demo.com · Student</span>
        </div>
    -->
    <?php else: ?>
        <form method="post" class="form" autocomplete="on">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="form" value="register">

            <div class="form__row">
                <label class="field">
                    <span class="field__label">Full name</span>
                    <input class="field__input" type="text" name="full_name" required value="<?php echo e($_POST['full_name'] ?? ''); ?>">
                </label>
                <label class="field">
                    <span class="field__label">Username</span>
                    <input class="field__input" type="text" name="username" required value="<?php echo e($_POST['username'] ?? ''); ?>">
                </label>
            </div>

            <div class="form__row">
                <label class="field">
                    <span class="field__label">Email</span>
                    <input class="field__input" type="email" name="email" required value="<?php echo e($_POST['email'] ?? ''); ?>">
                </label>
                <label class="field">
                    <span class="field__label">Phone (optional)</span>
                    <input class="field__input" type="text" name="phone" value="<?php echo e($_POST['phone'] ?? ''); ?>">
                </label>
            </div>

            <div class="form__row">
                <label class="field">
                    <span class="field__label">Role</span>
                    <select class="field__input" name="role" required>
                        <option value="student" <?php echo (($_POST['role'] ?? '') === 'student') ? 'selected' : ''; ?>>Student</option>
                        <option value="instructor" <?php echo (($_POST['role'] ?? '') === 'instructor') ? 'selected' : ''; ?>>Instructor</option>
                    </select>
                </label>
                <label class="field">
                    <span class="field__label">Department</span>
                    <select class="field__input" name="department_id">
                        <option value="0">— choose —</option>
                        <?php foreach ($departments as $d): ?>
                            <option value="<?php echo (int)$d['id']; ?>" <?php echo ((int)($_POST['department_id'] ?? 0) === (int)$d['id']) ? 'selected' : ''; ?>>
                                <?php echo e($d['code'] . ' — ' . $d['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>

            <div class="form__row">
                <label class="field">
                    <span class="field__label">Password</span>
                    <span class="field__password">
                        <input class="field__input" type="password" name="password" required minlength="6">
                        <button type="button" class="field__toggle" data-password-toggle aria-label="Show password">
                            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        </button>
                    </span>
                </label>
                <label class="field">
                    <span class="field__label">Confirm password</span>
                    <span class="field__password">
                        <input class="field__input" type="password" name="confirm_password" required>
                        <button type="button" class="field__toggle" data-password-toggle aria-label="Show password">
                            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        </button>
                    </span>
                </label>
            </div>

            <p class="notice">New accounts require administrator approval before you can sign in.</p>

            <button class="btn btn--primary btn--block" type="submit">Submit application</button>
        </form>
    <?php endif; ?>
</main>

<script src="<?php echo e(SITE_URL); ?>/assets/js/core.js"></script>
</body>
</html>