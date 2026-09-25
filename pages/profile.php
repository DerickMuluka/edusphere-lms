<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();

$user = current_user();
$uid = (int)$user['id'];
$conn = db();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $error = 'Session expired.';
    } elseif (isset($_POST['update_profile'])) {
        $full = clean($_POST['full_name'] ?? '');
        $email = clean($_POST['email'] ?? '');
        $phone = clean($_POST['phone'] ?? '');
        $bio = clean($_POST['bio'] ?? '');
        $avatar = upload_file('avatar', 'avatars', ['png','jpg','jpeg','gif','webp']);

        if ($full === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Provide a name and valid email.';
        } else {
            if ($avatar) {
                $stmt = $conn->prepare("UPDATE users SET full_name=?, email=?, phone=?, bio=?, avatar=? WHERE id=?");
                $stmt->bind_param('sssssi', $full, $email, $phone, $bio, $avatar, $uid);
            } else {
                $stmt = $conn->prepare("UPDATE users SET full_name=?, email=?, phone=?, bio=? WHERE id=?");
                $stmt->bind_param('ssssi', $full, $email, $phone, $bio, $uid);
            }
            $stmt->execute();
            set_flash('success','Profile updated.');
            redirect('profile.php');
        }
    } elseif (isset($_POST['change_password'])) {
        $cur = (string)$_POST['current_password'];
        $new = (string)$_POST['new_password'];
        $conf = (string)$_POST['confirm_password'];

        if (!password_verify($cur, $user['password'])) $error = 'Current password is incorrect.';
        elseif (strlen($new) < 6) $error = 'New password must be at least 6 characters.';
        elseif ($new !== $conf) $error = 'Passwords do not match.';
        else {
            $hash = password_hash($new, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password=? WHERE id=?");
            $stmt->bind_param('si', $hash, $uid); $stmt->execute();
            set_flash('success','Password changed.');
            redirect('profile.php');
        }
    }
}

$pageTitle = 'Account settings';
$extraCSS = ['dashboard.css'];
include __DIR__ . '/../includes/header.php';
?>

<?php if ($error): ?><div class="alert alert--error"><?php echo e($error); ?></div><?php endif; ?>

<div class="dash-grid">
    <section class="panel">
        <header class="panel__head"><h3>Profile</h3></header>

        <div class="profile-header">
            <?php if (!empty($user['avatar']) && file_exists(UPLOAD_DIR . $user['avatar'])): ?>
                <img class="profile-avatar" src="<?php echo e(UPLOAD_URL . $user['avatar']); ?>" alt="Avatar">
            <?php else: ?>
                <div class="profile-avatar profile-avatar--fallback"><?php echo e(initials($user['full_name'])); ?></div>
            <?php endif; ?>
            <div>
                <h3><?php echo e($user['full_name']); ?></h3>
                <p class="muted"><?php echo e($user['role']); ?><?php echo $user['department_name'] ? ' · ' . e($user['department_name']) : ''; ?></p>
            </div>
        </div>

        <form method="post" class="form form--grid" enctype="multipart/form-data">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="update_profile" value="1">
            <label class="field field--wide"><span class="field__label">Full name</span>
                <input class="field__input" name="full_name" value="<?php echo e($user['full_name']); ?>" required></label>
            <label class="field field--wide"><span class="field__label">Email</span>
                <input class="field__input" type="email" name="email" value="<?php echo e($user['email']); ?>" required></label>
            <label class="field"><span class="field__label">Phone</span>
                <input class="field__input" name="phone" value="<?php echo e($user['phone'] ?? ''); ?>"></label>
            <label class="field"><span class="field__label">Profile photo</span>
                <input class="field__input" type="file" name="avatar" accept="image/*"></label>
            <label class="field field--wide"><span class="field__label">Bio</span>
                <textarea class="field__input" name="bio" rows="3"><?php echo e($user['bio'] ?? ''); ?></textarea></label>
            <div class="form__actions"><button class="btn btn--primary" type="submit">Save changes</button></div>
        </form>
    </section>

    <section class="panel">
        <header class="panel__head"><h3>Change password</h3></header>
        <form method="post" class="form form--grid">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="change_password" value="1">
            <label class="field field--wide"><span class="field__label">Current password</span>
                <input class="field__input" type="password" name="current_password" required></label>
            <label class="field"><span class="field__label">New password</span>
                <input class="field__input" type="password" name="new_password" required minlength="6"></label>
            <label class="field"><span class="field__label">Confirm</span>
                <input class="field__input" type="password" name="confirm_password" required></label>
            <div class="form__actions"><button class="btn btn--secondary" type="submit">Update password</button></div>
        </form>
    </section>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>