<?php
session_start();
session_destroy();
header("Location: /leave_management_system/auth/login.php");
exit();
?>
