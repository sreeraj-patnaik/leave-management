<?php
include '../config/db.php';
include '../includes/auth_check.php';

$user_id = $_SESSION['user_id'];

$res = $conn->query("SELECT * FROM leaves WHERE current_approver_id=$user_id");

echo "<h2>Pending Requests</h2>";

while ($row = $res->fetch_assoc()) {
    echo "Leave ID: ".$row['id']."<br>";
    echo "User: ".$row['user_id']."<br>";

    echo "<a href='../actions/approve_leave.php?id=".$row['id']."&action=approve'>Approve</a> | ";
    echo "<a href='../actions/approve_leave.php?id=".$row['id']."&action=reject'>Reject</a>";

    echo "<hr>";
}
?>