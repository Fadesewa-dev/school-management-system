<?php
session_start();
$isLoggedIn = isset($_SESSION['user_id']);
$username = $_SESSION['fullname'] ?? ''; // from login.php
?>
<!DOCTYPE html>

<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Osun State University - Dispensing of Knowledge and Culture</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css?v=1.3">
</head>
<body>
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
<!-- Navigation -->
<nav class="navbar navbar-expand-lg main-nav">
    <div class="container">
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav w-100">
                <li class="nav-item">
                    <a class="nav-link active" href="#">
                        <i class="fas fa-home"></i> Home
                    </a>
                </li>
                
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="aboutDropdown" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-info-circle"></i> About Us
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="#"><i class="fas fa-university"></i> History</a></li>
                        <li><a class="dropdown-item" href="#"><i class="fas fa-eye"></i> Vision & Mission</a></li>
                        <li><a class="dropdown-item" href="#"><i class="fas fa-medal"></i> Achievements</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="#"><i class="fas fa-map-marker-alt"></i> Campus Tour</a></li>
                    </ul>
                </li>
                
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="academicsDropdown" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-graduation-cap"></i> Academics
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="#"><i class="fas fa-building"></i> Faculties</a></li>
                        <li><a class="dropdown-item" href="#"><i class="fas fa-book"></i> Programmes</a></li>
                        <li><a class="dropdown-item" href="#"><i class="fas fa-calendar-alt"></i> Academic Calendar</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="#"><i class="fas fa-user-graduate"></i> Undergraduate</a></li>
                        <li><a class="dropdown-item" href="#"><i class="fas fa-user-graduate"></i> Postgraduate</a></li>
                    </ul>
                </li>
                
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="portalDropdown" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-laptop"></i> E-Portal
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="#"><i class="fas fa-user-circle"></i> Student Portal</a></li>
                        <li><a class="dropdown-item" href="lecturer_login.php"><i class="fas fa-chalkboard-teacher"></i> Staff Portal</a></li>
                        <li><a class="dropdown-item" href="#"><i class="fas fa-clipboard-check"></i> Application Portal</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="#"><i class="fas fa-envelope"></i> Webmail</a></li>
                    </ul>
                </li>
                
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="adminDropdown" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-building"></i> Administration
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="#"><i class="fas fa-user-plus"></i> Admissions</a></li>
                        <li><a class="dropdown-item" href="#"><i class="fas fa-money-check-alt"></i> Bursary</a></li>
                        <li><a class="dropdown-item" href="#"><i class="fas fa-users"></i> Registry</a></li>
                        <li><a class="dropdown-item" href="#"><i class="fas fa-graduation-cap"></i> Academic Affairs</a></li>
                    </ul>
                </li>
                
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="libraryDropdown" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-book-open"></i> Library
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="#"><i class="fas fa-search"></i> Library Catalog</a></li>
                        <li><a class="dropdown-item" href="#"><i class="fas fa-digital-tachograph"></i> Digital Resources</a></li>
                        <li><a class="dropdown-item" href="#"><i class="fas fa-clock"></i> Opening Hours</a></li>
                        <li><a class="dropdown-item" href="#"><i class="fas fa-info-circle"></i> Library Services</a></li>
                    </ul>
                </li>
                
                <!-- System Portals Section -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="systemPortalsDropdown" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-cogs"></i> System Portals
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="lecturer_login.php">
                            <i class="fas fa-chalkboard-teacher"></i> Lecturer Portal
                        </a></li>
                        
                        <?php if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true): ?>
                            <li><a class="dropdown-item" href="admin_dashboard.php">
                                <i class="fas fa-user-shield"></i> Admin Dashboard
                            </a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="admin_logout.php">
                                <i class="fas fa-sign-out-alt"></i> Admin Logout
                            </a></li>
                        <?php else: ?>
                            <li><a class="dropdown-item" href="admin_login.php">
                                <i class="fas fa-user-shield"></i> Admin Login
                            </a></li>
                        <?php endif; ?>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>

<!-- Image Carousel -->
<div id="heroCarousel" class="carousel slide carousel-fade mt-4" data-bs-ride="carousel">
    <div class="carousel-indicators">
        <button type="button" data-bs-target="#heroCarousel" data-bs-slide-to="0" class="active" aria-current="true" aria-label="Slide 1"></button>
        <button type="button" data-bs-target="#heroCarousel" data-bs-slide-to="1" aria-label="Slide 2"></button>
        <button type="button" data-bs-target="#heroCarousel" data-bs-slide-to="2" aria-label="Slide 3"></button>
    </div>
    <div class="carousel-inner">
        <div class="carousel-item active">
            <img src="uniosun.jpg" class="d-block w-100" alt="Slide 1">
        </div>
        <div class="carousel-item">
            <img src="lab.jpg" class="d-block w-100" alt="Slide 2">
        </div>
        <div class="carousel-item">
            <img src="smiling.jpg" class="d-block w-100" alt="Slide 3">
        </div>
    </div>
    <button class="carousel-control-prev" type="button" data-bs-target="#heroCarousel" data-bs-slide="prev">
        <span class="carousel-control-prev-icon" aria-hidden="true"></span>
    </button>
    <button class="carousel-control-next" type="button" data-bs-target="#heroCarousel" data-bs-slide="next">
        <span class="carousel-control-next-icon" aria-hidden="true"></span>
    </button>
</div>

<!-- Content Section -->
<div class="content-section">
    <div class="container">
        <h2 class="section-title">Welcome to Osun State University</h2>
        <div class="row">
            <div class="col-lg-4 col-md-6">
                <div class="feature-card text-center">
                    <div class="feature-icon">
                        <i class="fas fa-graduation-cap"></i>
                    </div>
                    <h4>Quality Education</h4>
                    <p>We provide world-class education with modern facilities and experienced faculty members dedicated to academic excellence.</p>
                </div>
            </div>
            <div class="col-lg-4 col-md-6">
                <div class="feature-card text-center">
                    <div class="feature-icon">
                        <i class="fas fa-microscope"></i>
                    </div>
                    <h4>Research Excellence</h4>
                    <p>Our research programs contribute to knowledge advancement and provide solutions to societal challenges.</p>
                </div>
            </div>
            <div class="col-lg-4 col-md-6">
                <div class="feature-card text-center">
                    <div class="feature-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <h4>Community Impact</h4>
                    <p>We actively engage with our community through outreach programs and collaborative initiatives.</p>
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

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>

<script>
    // Login/Register Toggle Functionality
    document.addEventListener('DOMContentLoaded', function() {
        const loginBtn = document.getElementById('loginBtn');
        const registerBtn = document.getElementById('registerBtn');
        
        // Toggle active states
        loginBtn.addEventListener('click', function() {
            loginBtn.classList.add('active');
            registerBtn.classList.remove('active');
        });
        
        registerBtn.addEventListener('click', function() {
            registerBtn.classList.add('active');
            loginBtn.classList.remove('active');
        });
        
        // Reset active states after a delay (since opening in new tab)
        setTimeout(() => {
            loginBtn.classList.remove('active');
            registerBtn.classList.remove('active');
        }, 2000);
    });
</script>


</body>
</html>