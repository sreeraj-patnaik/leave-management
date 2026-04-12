<?php
include '../config/db.php';

session_unset();
session_destroy();
redirect('/auth/login.php');
?>