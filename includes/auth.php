<?php
/**
 * EduSphere LMS — Authentication & authorization
 * v4 — Fixed: undefined $u in require_login()
 */

require_once __DIR__ . '/helpers.php';

/**
 * Load the current user row.
 * Loads regardless of status so we can detect pending/suspended/rejected
 * accounts on the login screen and show the correct message.
 */
function current_user() {
    static $user = null, $loaded = false;
    if ($loaded) return $user;
    $loaded = true;

    if (empty($_SESSION['user_id'])) return null;

    $stmt = db()->prepare("
        SELECT u.*, d.name AS department_name, d.code AS department_code
        FROM users u
        LEFT JOIN departments d ON d.id = u.department_id
        WHERE u.id = ?
    ");
    $stmt->bind_param('i', $_SESSION['user_id']);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc() ?: null;
    return $user;
}

/** Logged in AND approved (status = active). */
function is_logged_in() {
    $u = current_user();
    return $u && $u['status'] === 'active';
}

/** Any user session is present (used by login redirect logic). */
function has_session() {
    return !empty($_SESSION['user_id']);
}

function require_login() {
    if (!is_logged_in()) {
        // Preserve reason if the account isn't active
        $u = current_user();               // <-- FIX: assign $u here
        if ($u && $u['status'] !== 'active') {
            $_SESSION['account_status']   = $u['status'];
            $_SESSION['rejection_reason'] = $u['rejection_reason'] ?? '';
        }
        session_unset();
        session_destroy();
        session_start();
        redirect(SITE_URL . '/pages/login.php');
    }

    // Record last login timestamp
    $u = current_user();                   // <-- FIX: assign $u here too
    if ($u) {
        db()->query("UPDATE users SET last_login_at = NOW() WHERE id = " . (int)$u['id']);
    }
}

function require_role($roles) {
    require_login();
    $u = current_user();
    if (!in_array($u['role'], (array)$roles, true)) {
        http_response_code(403);
        die('Access denied.');
    }
}

function is_student()    { $u = current_user(); return $u && $u['role'] === 'student'; }
function is_instructor() { $u = current_user(); return $u && $u['role'] === 'instructor'; }
function is_admin()      { $u = current_user(); return $u && $u['role'] === 'admin'; }
function is_staff()      { $u = current_user(); return $u && in_array($u['role'], ['instructor','admin'], true); }