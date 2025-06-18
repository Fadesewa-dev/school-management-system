<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: admin_login.php");
    exit;
}

$userRole = $_SESSION['role'] ?? 'admin';
$userName = $_SESSION['admin_name'] ?? 'User';
?>
<!DOCTYPE html>
<html>
<head>
    <title>Test Dashboard</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: #6B8E23; padding: 20px; }
        .container { background: white; padding: 30px; border-radius: 10px; }
        .sidebar { background: #f8f9fa; padding: 20px; border-radius: 10px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="row">
            <div class="col-md-3">
                <div class="sidebar">
                    <h4><?php echo $userName; ?></h4>
                    <p>Role: <?php echo $userRole; ?></p>
                    <hr>
                    <ul class="list-unstyled">
                        <li><a href="admin_dashboard.php">Dashboard</a></li>
                        <?php if ($userRole === 'admin'): ?>
                            <li><a href="manage_students.php">Students</a></li>
                            <li><a href="manage_lecturers.php">Lecturers</a></li>
                            <li><a href="manage_departments.php">Departments</a></li>
                        <?php endif; ?>
                        <li><a href="manage_results.php">Results Management</a></li>
                        <li><a href="admin_logout.php">Logout</a></li>
                    </ul>
                </div>
            </div>
            <div class="col-md-9">
                <h2>Dashboard Working!</h2>
                <p>Role: <strong><?php echo $userRole; ?></strong></p>
                <p>User: <strong><?php echo $userName; ?></strong></p>
                
                <?php if ($userRole === 'lecturer'): ?>
                    <div class="alert alert-info">
                        <h4>Lecturer Portal</h4>
                        <p>You have limited access. You can upload results only.</p>
                        <a href="manage_results.php" class="btn btn-primary">Upload Results</a>
                    </div>
                <?php else: ?>
                    <div class="alert alert-success">
                        <h4>Admin Portal</h4>
                        <p>You have full access to all features.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>