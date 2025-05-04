<?php
session_start();

// Log logout activity if admin was logged in
if (isset($_SESSION['admin_id'])) {
    require_once '../partials/config.php';
    require_once '../partials/functions.php';
    require_once '../db/db_connect.php';
    $stmt = $pdo->prepare("INSERT INTO admin_activity_log 
                          (admin_id, action_type, ip_address, user_agent) 
                          VALUES (?, ?, ?, ?)");
    $stmt->execute([
        $_SESSION['admin_id'],
        'logout',
        $_SERVER['REMOTE_ADDR'],
        $_SERVER['HTTP_USER_AGENT']
    ]);
}

// Destroy session
session_unset();
session_destroy();

// Redirect to login page
header("Location: admin_login.php");
exit;
