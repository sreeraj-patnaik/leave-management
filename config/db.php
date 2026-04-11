<?php
$conn = new mysqli("localhost", "root", "", "leave_management");
if ($conn->connect_error) die("DB Error");
session_start();
?>