<?php
/**
 * EduSphere LMS — Registration Page (v4)
 * Creates pending accounts that must be approved by an admin.
 */

require_once __DIR__ . '/../includes/auth.php';

// If already logged in as ACTIVE, go to dashboard
if (is_logged_in()) redirect(SITE_URL . '/pages/dashboard.php');

// If a non-active session exists, clear it
if (has_session()) {
    $u = current_user();
    if ($u && $u['status'] !== 'active') {
        $_SESSION = [];
        session_destroy();
        session_start();
    }
}

$error = '';
$departments = db()->query("SELECT id, code, name FROM departments ORDER BY name")->fetch_all(MYSQLI_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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

            // Check duplicates
            $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? OR username = ? LIMIT 1");
            $stmt->bind_param('ss', $email, $username);
            $stmt->execute();
            if ($stmt->get_result()->num_rows) {
                $error = 'That email or username is already taken.';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);

                $dept      = $deptId > 0 ? $deptId : null;
                $admission = null;
                $staffId   = null;

                // Provisional ID generation
                if ($deptId > 0) {
                    $deptStmt = $conn->prepare("SELECT code FROM departments WHERE id = ?");
                    $deptStmt->bind_param('i', $deptId);
                    $deptStmt->execute();
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

                /*
                 * Placeholders: 10
                 *   1. full_name    s
                 *   2. username     s
                 *   3. email        s
                 *   4. password     s
                 *   5. role         s
                 *   6. department   i
                 *   7. admission    s
                 *   8. staff_id     s
                 *   9. phone        s
                 *  10. status       s
                 *
                 * Types string must be exactly: s s s s s i s s s s  =>  "sssss isss s"
                 * i.e. "sssssissis" (10 characters, i at position 6)
                 */
                $stmt = $conn->prepare("
                    INSERT INTO users
                        (full_name, username, email, password, role,
                         department_id, admission_no, staff_id, phone, status)
                    VALUES (?,?,?,?,?,?,?,?,?,?)
                ");
                $stmt->bind_param(
                    'sssssissis',
                    $full, $username, $email, $hash, $role,
                    $dept, $admission, $staffId, $phone, $status
                );

                if ($stmt->execute()) {
                    $newId = $conn->insert_id;

                    // Record initial status history
                    $hist = $conn->prepare("
                        INSERT INTO status_history (user_id, old_status, new_status, reason)
                        VALUES (?,?,?,?)
                    ");
                    $old = null;
                    $reason = 'Self-registration';
                    $hist->bind_param('isss', $newId, $old, $status, $reason);
                    $hist->execute();

                    // Welcome notification
                    $notif = $conn->prepare("
                        INSERT INTO notifications (user_id, title, body)
                        VALUES (?,?,?)
                    ");
                    $title = 'Application received';
                    $body  = 'Thank you for registering. Your application is under review. You will be notified once approved.';
                    $notif->bind_param('iss', $newId, $title, $body);
                    $notif->execute();

                    set_flash('success', 'Application submitted. An administrator will review your account shortly.');
                    redirect(SITE_URL . '/pages/login.php');
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
    <title>Create account · <?php echo e(SITE_NAME); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Source+Serif+4:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo e(SITE_URL); ?>/assets/css/base.css">
    <link rel="stylesheet" href="<?php echo e(SITE_URL); ?>/assets/css/auth.css">
</head>
<body class="auth-page auth-page--alt">
    <main class="auth-card">
        <div class="auth-card__head">
            <span class="auth-card__logo">ES</span>
            <h1>Create account</h1>
            <p>Applications are reviewed before access is granted.</p>
        </div>

        <?php if ($error): ?><div class="alert alert--error"><?php echo e($error); ?></div><?php endif; ?>

        <form method="post" action="" class="form" autocomplete="on">
            <?php echo csrf_field(); ?>

            <label class="field">
                <span class="field__label">Full name</span>
                <input class="field__input" type="text" name="full_name" required
                       value="<?php echo e($_POST['full_name'] ?? ''); ?>">
            </label>

            <label class="field">
                <span class="field__label">Username</span>
                <input class="field__input" type="text" name="username" required
                       value="<?php echo e($_POST['username'] ?? ''); ?>">
            </label>

            <label class="field">
                <span class="field__label">Email address</span>
                <input class="field__input" type="email" name="email" required
                       value="<?php echo e($_POST['email'] ?? ''); ?>">
            </label>

            <label class="field">
                <span class="field__label">Phone (optional)</span>
                <input class="field__input" type="text" name="phone"
                       value="<?php echo e($_POST['phone'] ?? ''); ?>">
            </label>

            <label class="field">
                <span class="field__label">Account type</span>
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

            <label class="field">
                <span class="field__label">Password</span>
                <span class="field__password">
                    <input class="field__input" type="password" name="password" required minlength="6">
                    <button type="button" class="field__toggle" data-password-toggle aria-label="Show password">
                        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
                        </svg>
                    </button>
                </span>
            </label>

            <label class="field">
                <span class="field__label">Confirm password</span>
                <span class="field__password">
                    <input class="field__input" type="password" name="confirm_password" required>
                    <button type="button" class="field__toggle" data-password-toggle aria-label="Show password">
                        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
                        </svg>
                    </button>
                </span>
            </label>

            <div class="auth-card__notice">
                <strong>Note:</strong> New accounts require administrator approval
                before you can access the system.
            </div>

            <button class="btn btn--primary btn--block" type="submit">Submit application</button>
        </form>

        <p class="auth-card__alt">Already registered? <a href="login.php">Sign in</a></p>
    </main>

    <script src="<?php echo e(SITE_URL); ?>/assets/js/core.js"></script>
    <script src="<?php echo e(SITE_URL); ?>/assets/js/auth.js"></script>
</body>
</html>