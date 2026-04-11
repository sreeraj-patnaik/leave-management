<?php
include '../config/db.php';
include '../includes/auth_check.php';

$leave_id = $_GET['id'];
$action = $_GET['action'];

$user_id = $_SESSION['user_id'];
$user = $conn->query("SELECT * FROM users WHERE id=$user_id")->fetch_assoc();
$role = $user['role'];

$leave = $conn->query("SELECT * FROM leaves WHERE id=$leave_id")->fetch_assoc();

if ($action == 'reject') {
    $conn->query("UPDATE leaves SET status='rejected' WHERE id=$leave_id");
}
else {

    if ($role == 'faculty') {
        $hod = $conn->query("SELECT id FROM users WHERE role='hod' AND department='".$user['department']."'")->fetch_assoc();
        $conn->query("UPDATE leaves SET current_approver_id=".$hod['id']." WHERE id=$leave_id");
    }

    elseif ($role == 'hod') {
        $days = (strtotime($leave['to_date']) - strtotime($leave['from_date'])) / 86400;

        if ($days > 3) {
            $p = $conn->query("SELECT id FROM users WHERE role='principal'")->fetch_assoc();
            $conn->query("UPDATE leaves SET current_approver_id=".$p['id']." WHERE id=$leave_id");
        } else {
            $conn->query("UPDATE leaves SET status='approved' WHERE id=$leave_id");
        }
    }

    elseif ($role == 'principal') {
        $conn->query("UPDATE leaves SET status='approved' WHERE id=$leave_id");
    }
}

// log
$conn->query("INSERT INTO approval_log (leave_id, approved_by, role, status)
              VALUES ($leave_id, $user_id, '$role', '$action')");

header("Location: ../approver/dashboard.php");
?>