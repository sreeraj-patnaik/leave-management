<?php
include '../config/db.php';
include '../includes/auth_check.php';

$user_id = $_SESSION['user_id'];

// get assigned faculty
$u = $conn->query("SELECT * FROM users WHERE id=$user_id")->fetch_assoc();
$faculty_id = $u['assigned_faculty_id'];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $from = $_POST['from'];
    $to = $_POST['to'];
    $reason = $_POST['reason'];

    // fallback: if no faculty → go to HOD
    if (!$faculty_id) {
        $hod = $conn->query("SELECT id FROM users WHERE role='hod' AND department='".$u['department']."'")->fetch_assoc();
        $faculty_id = $hod['id'];
    }

    $conn->query("INSERT INTO leaves (user_id, from_date, to_date, reason, current_approver_id)
                  VALUES ($user_id, '$from', '$to', '$reason', $faculty_id)");

    echo "Leave Applied";
}
?>

<form method="POST">
From: <input type="date" name="from"><br>
To: <input type="date" name="to"><br>
Reason: <textarea name="reason"></textarea><br>
<button>Apply</button>
</form>