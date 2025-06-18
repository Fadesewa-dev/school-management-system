<?php
session_start();

$host = 'localhost';
$db   = 'septsoft24';
$user = 'root';
$pass = 'novirus123';
$dsn  = "mysql:host=$host;dbname=$db;charset=utf8mb4";

try {
    $pdo = new PDO($dsn, $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    // Enhanced query to get role and lecturer info
    $stmt = $pdo->prepare("
        SELECT a.*, l.first_name, l.last_name 
        FROM admin a 
        LEFT JOIN lecturers l ON a.lecturer_id = l.lecturer_id 
        WHERE a.email = ?
    ");
    $stmt->execute([$email]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($admin && password_verify($password, $admin['password'])) {
        // Your existing session variables
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_name'] = $admin['name'];
        
        // Add role detection
        $_SESSION['role'] = $admin['role'] ?? 'admin';
        
        if ($_SESSION['role'] === 'lecturer') {
            $_SESSION['lecturer_id'] = $admin['lecturer_id'];
            $_SESSION['lecturer_name'] = $admin['first_name'] . ' ' . $admin['last_name'];
            $_SESSION['full_name'] = $_SESSION['lecturer_name'];
        } else {
            $_SESSION['full_name'] = $admin['name'];
        }
        
        header("Location: admin_dashboard.php");
        exit;
    } else {
        $_SESSION['error_message'] = "Invalid email or password";
        header("Location: admin_login.php");
        exit;
    }

} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>