<?php
$host = 'localhost';
$db   = 'septsoft24';
$user = 'root';
$pass = 'novirus123';

$dsn = "mysql:host=$host;dbname=$db;charset=utf8mb4";
try {
    $pdo = new PDO($dsn, $user, $pass);
} catch (PDOException $e) {
    echo "❌ Database connection failed: " . $e->getMessage();
    exit();
}