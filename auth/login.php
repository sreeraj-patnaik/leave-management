<?php
include '../config/db.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'];
    $password = $_POST['password'];

    $res = $conn->query("SELECT * FROM users WHERE email='$email'");
    $user = $res->fetch_assoc();

    if ($user && $password == $user['password']) { // simple auth (no hashing now)
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role'] = $user['role'];

        header("Location: ../index.php");
        exit;
    } else {
        echo "Invalid login";
    }
}
?>

<form method="POST">
    Email: <input name="email"><br>
    Password: <input type="password" name="password"><br>
    <button>Login</button>
</form>