<?php
/**
 * EduSphere LMS — Global configuration
 */

// --- Database (change for InfinityFree) ---
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'virtual_classroom');

// --- Site ---
define('SITE_URL', 'http://localhost/virtual_classroom');
define('SITE_NAME', 'EduSphere');

// --- Uploads ---
define('UPLOAD_DIR', __DIR__ . '/../assets/uploads/');
define('UPLOAD_URL', SITE_URL . '/assets/uploads/');

// --- Session ---
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- Errors ---
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);