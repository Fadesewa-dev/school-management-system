<?php
session_start();

$host = 'localhost';
$db   = 'septsoft24';
$db_user = 'root';        // Changed from $user to $db_user
$db_pass = 'novirus123';  // Changed from $pass to $db_pass
$dsn = "mysql:host=$host;dbname=$db;charset=utf8mb4";

$login_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    try {
        $pdo = new PDO($dsn, $db_user, $db_pass);  // Using new variable names
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $email = $_POST['login_email'] ?? '';
        $password = $_POST['login_password'] ?? '';  // Now this won't conflict!

        $stmt = $pdo->prepare("SELECT * FROM studrec WHERE Email = ?");
        $stmt->execute([$email]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);  // Changed from $user to $student

        if ($student && password_verify($password, $student['PASSWORD'])) {
            if (isset($_POST['remember'])) {
                setcookie('user_email', $email, time() + (86400 * 30), "/");
            } else {
                setcookie('user_email', '', time() - 3600, "/");
            }

            // Set the correct session variables for dashboard
            $_SESSION['student_logged_in'] = true;
            $_SESSION['student_id'] = $student['ID'];
            $_SESSION['student_name'] = $student['FIRST_NAME'] . ' ' . $student['LAST_NAME'];
            $_SESSION['matric_number'] = $student['MATRIC_NUMBER'];

            header("Location: student_dashboard.php");
            exit;
        } else {
            $login_error = "Invalid email or password.";
        }
    } catch (PDOException $e) {
        $login_error = "Database error: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Font Awesome Test</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
 <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
 <link rel="stylesheet" href="style.css">

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


<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-5">
            <div class="card shadow p-4 mb-4">
                <h2 class="mb-4"><i class="fas fa-sign-in-alt me-2"></i>Student Login</h2>

                <?php if ($login_error): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($login_error); ?></div>
                <?php endif; ?>

                <form method="POST" action="" id="loginForm" novalidate>
                    <div class="mb-3">
                        <label><i class="fas fa-envelope me-2"></i>Email</label>
                        <input 
                            type="email" 
                            name="login_email" 
                            class="form-control" 
                            required 
                            value="<?php echo isset($_COOKIE['user_email']) ? htmlspecialchars($_COOKIE['user_email']) : ''; ?>"
                        >
                    </div>
                    <div class="mb-3">
                        <label><i class="fas fa-lock me-2"></i>Password</label>
                        <input 
                            type="password" 
                            name="login_password" 
                            class="form-control" 
                            required
                        >
                    </div>
                    <div class="form-check mb-2">
                        <input 
                            class="form-check-input" 
                            type="checkbox" 
                            name="remember" 
                            id="remember" 
                            <?php echo isset($_COOKIE['user_email']) ? 'checked' : ''; ?>
                        >
                        <label class="form-check-label" for="remember">
                            <i class="fas fa-check-square me-1"></i>Remember Me
                        </label>
                    </div>
                    <div class="form-check mb-3">
                        <input 
                            class="form-check-input" 
                            type="checkbox" 
                            name="terms" 
                            id="terms" 
                            required
                        >
                        <label class="form-check-label" for="terms">
                            <i class="fas fa-file-contract me-1"></i>I agree to the <a href="#" style="color:  #6B8E23;">Terms and Conditions</a>
                        </label>
                    </div>
                    <button type="submit" name="login" style="background-color:  #6B8E23;" class="w-100">
                        <i class="fas fa-sign-in-alt me-2"></i>Login
                    </button>

                    <div class="text-center mt-3">
                        <a href="landing.php" style="color:  #6B8E23;"><i class="fas fa-home me-1"></i>Back to Main Page</a>
                    </div>
                </form>
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

<script>
document.getElementById('loginForm')?.addEventListener('submit', function(e) {
    const terms = document.getElementById('terms');
    if (!terms.checked) {
        e.preventDefault();
        alert('You must agree to the terms and conditions to continue.');
    }
});
</script>
</body>