<?php
include '../config/db.php';
include '../includes/auth_check.php';

$user_id = $_SESSION['user_id'];

$res = $conn->query("SELECT * FROM leaves WHERE user_id=$user_id");

echo "<h2>My Leaves</h2>";

while ($row = $res->fetch_assoc()) {
    echo "From: ".$row['from_date']." To: ".$row['to_date']." | Status: ".$row['status']."<br>";
}
?>

<a href="apply_leave.php">Apply New</a>