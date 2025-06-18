<?php
session_start();

// Destroy all lecturer session data
unset($_SESSION['lecturer_logged_in']);
unset($_SESSION['lecturer_id']);
unset($_SESSION['lecturer_name']);
unset($_SESSION['lecturer_email']);
unset($_SESSION['lecturer_department']);

// Destroy the session completely
session_destroy();

// Redirect to lecturer login page
header("Location: lecturer_login.php");
exit;
?>