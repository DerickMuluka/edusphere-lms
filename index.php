<?php
require_once __DIR__ . '/includes/auth.php';
if (is_logged_in()) redirect(SITE_URL . '/pages/dashboard.php');
redirect(SITE_URL . '/pages/login.php');