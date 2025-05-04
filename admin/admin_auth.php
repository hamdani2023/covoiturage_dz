<?php
// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: admin_login.php");
    exit;
}

// Check if admin has necessary permissions
if (!function_exists('check_admin_permission')) {

    function check_admin_permission($required_role = 'admin')
    {
        if ($_SESSION['admin_role'] !== $required_role && $_SESSION['admin_role'] !== 'super_admin') {
            header("Location: admin_dashboard.php?error=unauthorized");
            exit;
        }
    }
}
// Log admin activity
function log_admin_activity($action_type, $target_table = null, $target_id = null, $details = null)
{
    global $pdo;

    $stmt = $pdo->prepare("INSERT INTO admin_activity_log 
                          (admin_id, action_type, target_table, target_id, action_details, ip_address, user_agent) 
                          VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $_SESSION['admin_id'],
        $action_type,
        $target_table,
        $target_id,
        $details,
        $_SERVER['REMOTE_ADDR'],
        $_SERVER['HTTP_USER_AGENT']
    ]);
}
