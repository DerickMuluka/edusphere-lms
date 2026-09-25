<?php
require_once __DIR__ . '/db.php';

function e($v)   { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function clean($v){ return trim((string)$v); }
function redirect($url) { header('Location: ' . $url); exit; }

function set_flash($type, $msg) { $_SESSION['flash'] = ['type'=>$type,'message'=>$msg]; }
function take_flash() {
    if (!isset($_SESSION['flash'])) return null;
    $f = $_SESSION['flash']; unset($_SESSION['flash']); return $f;
}

function csrf_token() {
    if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf_token'];
}
function csrf_verify($t) { return !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], (string)$t); }
function csrf_field() { return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">'; }

function fmt_date($dt, $fmt = 'd M Y') {
    if (!$dt) return '—';
    $t = strtotime($dt); return $t ? date($fmt, $t) : '—';
}
function fmt_datetime($dt) { return fmt_date($dt, 'd M Y, H:i'); }
function time_ago($dt) {
    if (!$dt) return '';
    $d = time() - strtotime($dt);
    if ($d < 60) return 'just now';
    if ($d < 3600) return floor($d/60).'m ago';
    if ($d < 86400) return floor($d/3600).'h ago';
    if ($d < 604800) return floor($d/86400).'d ago';
    return fmt_date($dt);
}

/**
 * Upload a file into a specific subfolder under assets/uploads/.
 * Returns the relative path (subfolder/filename) or null on failure.
 */
function upload_file($inputName, $subfolder = '', $allowedExt = ['pdf','doc','docx','ppt','pptx','zip','png','jpg','jpeg','gif','txt','py','java','sql']) {
    if (empty($_FILES[$inputName]) || $_FILES[$inputName]['error'] !== UPLOAD_ERR_OK) return null;
    $f = $_FILES[$inputName];
    $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExt, true)) return null;
    if ($f['size'] > 20 * 1024 * 1024) return null;

    $subfolder = trim($subfolder, '/');
    $dir = UPLOAD_DIR . ($subfolder ? $subfolder . '/' : '');
    if (!is_dir($dir)) @mkdir($dir, 0755, true);

    $base = preg_replace('/[^A-Za-z0-9._-]/', '_', pathinfo($f['name'], PATHINFO_FILENAME));
    $safe = date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '_' . $base . '.' . $ext;
    if (move_uploaded_file($f['tmp_name'], $dir . $safe)) {
        return ($subfolder ? $subfolder . '/' : '') . $safe;
    }
    return null;
}

/** Human readable file size */
function human_size($bytes) {
    if (!$bytes) return '—';
    if ($bytes < 1024) return $bytes . ' B';
    if ($bytes < 1048576) return round($bytes / 1024, 1) . ' KB';
    return round($bytes / 1048576, 2) . ' MB';
}

/** Get initials for avatar */
function initials($name) {
    $parts = preg_split('/\s+/', trim($name));
    $out = '';
    foreach (array_slice($parts, 0, 2) as $p) $out .= strtoupper(substr($p, 0, 1));
    return $out ?: 'U';
}

/**
 * Log a status change to status_history + optionally notify the user.
 */
function set_user_status($userId, $newStatus, $reason = '', $notify = true, $notifyTitle = '', $notifyBody = '') {
    $conn = db();
    $admin = current_user();

    // Load old status
    $stmt = $conn->prepare("SELECT status, role, full_name FROM users WHERE id=?");
    $stmt->bind_param('i', $userId); $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if (!$row) return false;

    $old = $row['status'];

    // Update user row
    if ($newStatus === 'rejected') {
        $stmt = $conn->prepare("UPDATE users SET status=?, rejection_reason=?, approved_by=?, approved_at=NOW() WHERE id=?");
        $stmt->bind_param('ssii', $newStatus, $reason, $admin['id'], $userId);
    } elseif ($newStatus === 'active') {
        $stmt = $conn->prepare("UPDATE users SET status=?, rejection_reason=NULL, approved_by=?, approved_at=NOW() WHERE id=?");
        $stmt->bind_param('sii', $newStatus, $admin['id'], $userId);
    } else {
        $stmt = $conn->prepare("UPDATE users SET status=? WHERE id=?");
        $stmt->bind_param('si', $newStatus, $userId);
    }
    $stmt->execute();

    // Record history
    $stmt = $conn->prepare("INSERT INTO status_history (user_id, old_status, new_status, reason, changed_by) VALUES (?,?,?,?,?)");
    $stmt->bind_param('isssi', $userId, $old, $newStatus, $reason, $admin['id']);
    $stmt->execute();

    // Optional notification
    if ($notify) {
        if ($notifyTitle === '') {
            $map = [
                'active'    => ['Account approved', 'Your account has been approved. You can now sign in.'],
                'rejected'  => ['Account application rejected', $reason ?: 'Your application was rejected.'],
                'suspended' => ['Account suspended', $reason ?: 'Your account has been temporarily suspended.'],
                'pending'   => ['Account pending review', 'Your account is awaiting administrator approval.'],
            ];
            [$notifyTitle, $notifyBody] = $map[$newStatus] ?? ['Account update', 'Your account status has changed.'];
        }
        $stmt = $conn->prepare("INSERT INTO notifications (user_id, title, body) VALUES (?,?,?)");
        $stmt->bind_param('iss', $userId, $notifyTitle, $notifyBody);
        $stmt->execute();
    }

    return true;
}

/** Count unread notifications for the current user. */
function unread_notifications_count($userId) {
    $stmt = db()->prepare("SELECT COUNT(*) c FROM notifications WHERE user_id=? AND is_read=0");
    $stmt->bind_param('i', $userId); $stmt->execute();
    return (int)$stmt->get_result()->fetch_assoc()['c'];
}

/** Human label for a status value. */
function status_label($s) {
    return [
        'pending'   => 'Pending review',
        'active'    => 'Active',
        'rejected'  => 'Rejected',
        'suspended' => 'Suspended',
    ][$s] ?? ucfirst($s);
}