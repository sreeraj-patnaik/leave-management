<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

// Redirect based on login status
if (isLoggedIn()) {
    if (mustChangePassword()) {
        redirect('/leave_management_system/auth/change_password.php');
    }
    redirect(getDashboardPathForRole($_SESSION['role']));
} else {
    redirect("/leave_management_system/auth/login.php");
}
?>
