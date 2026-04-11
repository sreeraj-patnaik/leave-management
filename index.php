<?php
include 'config/db.php';
include 'includes/auth_check.php';

if ($_SESSION['role'] == 'student') {
    header("Location: student/my_leaves.php");
}
else {
    header("Location: approver/dashboard.php");
}
?>