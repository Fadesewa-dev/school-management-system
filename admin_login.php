<?php
session_start();
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header("Location: admin_dashboard.php"); // redirect if already logged in
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Admin Login</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
     <link rel="stylesheet" href="style.css">
</head>
<!-- Top Header -->
    <div class="top-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <a href="#"><i class="fas fa-tachometer-alt"></i> Quick Access</a>
                    <a href="#"><i class="fas fa-graduation-cap"></i> TAMDVS</a>
                    <a href="#"><i class="fas fa-globe"></i> E-Portals</a>
                </div>
                <div class="col-md-6 text-end d-flex align-items-center justify-content-end">
                    <input type="text" class="search-box" placeholder="Search...">
                    <div class="auth-toggle">
                        <a href="login.php" target="_blank" class="auth-btn" id="loginBtn">
                            <i class="fas fa-sign-in-alt"></i> Login
                        </a>
                        <span class="auth-separator">|</span>
                        <a href="registration.php" target="_blank" class="auth-btn" id="registerBtn">
                            <i class="fas fa-user-plus"></i> Register
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>


<!-- Main Header -->
<div class="main-header">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-12">
                <div class="logo-section">
                    <div class="logo">
                        <i class="fas fa-university"></i>
                    </div>
                    <div class="university-name">
                        <h1>OSUN STATE UNIVERSITY</h1>
                        <p class="university-tagline">Dispensing of Knowledge and Culture</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>


<body class="bg-light">
<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-5">
            <div class="card shadow">
                <div class="card-header text-center"style="background-color: #6B8E23;">
                    <h4>Admin Login</h4>
                </div>
                <div class="card-body">
                   <?php
                        if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
                        header("Location: admin_dashboard.php");
                        exit;
                        }
                    ?>

                    <!-- Inside your card-body -->
                    <?php
                        if (isset($_SESSION['error_message'])) {
                        echo '<div class="alert alert-danger">' . $_SESSION['error_message'] . '</div>';
                        unset($_SESSION['error_message']);
                      }
                    ?>
                    <form action="admin_login_process.php" method="POST">
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" required autofocus>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" name="password" class="form-control" required>
                        </div>
                        <div class="d-grid">
                            <button type="submit" style="background-color: #6B8E23;">Login</button>
                        </div>
                        <div class="text-center mt-3">
                            <a href="landing.php" style="color: #6B8E23;"><i class="fas fa-home me-1"></i>Back to Main Page</a>
                        </div>
                    </form>
                </div>
                <div class="card-footer text-center">
                    <small>&copy; <?= date("Y") ?> Admin Panel</small>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Footer -->
<footer class="footer">
    <div class="container">
        <div class="row">
            <div class="col-lg-4 col-md-6">
                <h5>Contact Information</h5>
                <p><i class="fas fa-map-marker-alt"></i> Osogbo, Osun State, Nigeria</p>
                <p><i class="fas fa-phone"></i> +234 816 486 7901</p>
                <p><i class="fas fa-envelope"></i> info@uniosun.edu.ng</p>
            </div>
            <div class="col-lg-4 col-md-6">
                <h5>Quick Links</h5>
                <ul class="list-unstyled">
                    <li><a href="#">Admissions</a></li>
                    <li><a href="#">Student Portal</a></li>
                    <li><a href="#">Faculty Directory</a></li>
                    <li><a href="#">Alumni</a></li>
                </ul>
            </div>
            <div class="col-lg-4 col-md-12">
                <h5>Follow Us</h5>
                <div>
                    <a href="#" class="me-3"><i class="fab fa-facebook fa-2x"></i></a>
                    <a href="#" class="me-3"><i class="fab fa-twitter fa-2x"></i></a>
                    <a href="#" class="me-3"><i class="fab fa-instagram fa-2x"></i></a>
                    <a href="#"><i class="fab fa-linkedin fa-2x"></i></a>
                </div>
            </div>
        </div>
        <hr style="border-color: rgba(255,255,255,0.3);">
        <div class="text-center">
            <p>&copy; 2024 Osun State University. All rights reserved.</p>
        </div>
    </div>
</footer>

</body>
</html>