<?php
session_start();

$host = 'localhost';
$db   = 'septsoft24';
$user = 'root';
$pass = 'novirus123';
$dsn = "mysql:host=$host;dbname=$db;charset=utf8mb4";


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo = new PDO($dsn, $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Check for duplicate user
        $stmt = $pdo->prepare("SELECT * FROM studrec WHERE Email = ? OR Telephone = ?");
        $stmt->execute([$_POST['Email'], $_POST['Telephone']]);
        if ($stmt->fetch()) {
            echo "<div class='alert alert-warning'>
                    This user already exists. <a href='login.php'>Click here to log in</a>
                  </div>";
            exit;
        }

        // Picture upload
        $target_dir = "uploads/";
        $picture_name = basename($_FILES["Picture"]["name"]);
        $target_file = $target_dir . $picture_name;
        if (!move_uploaded_file($_FILES["Picture"]["tmp_name"], $target_file)) {
            throw new Exception("Failed to upload picture.");
        }

        $hashedPassword = password_hash($_POST['Password'], PASSWORD_DEFAULT);

        // Only student registration logic - NO FINANCIAL FIELDS
        $stmt = $pdo->prepare("INSERT INTO studrec 
        (FIRST_NAME, LAST_NAME, EMAIL, DOB, TELEPHONE, ADDRESS, PICTURE, PASSWORD, role)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");

        $result = $stmt->execute([
            $_POST['First_Name'],
            $_POST['Last_Name'],
            $_POST['Email'],
            $_POST['DOB'],
            $_POST['Telephone'],
            $_POST['Address'],
            $target_file,
            $hashedPassword,
            'student'
        ]);

        if ($result) {
            echo "<div class='alert alert-success'>Student registered successfully! Student ID: " . $pdo->lastInsertId() . "</div>";
        } else {
            echo "<div class='alert alert-danger'>Registration failed!</div>";
        }

        echo "<div class='alert alert-success'>Student registered successfully!</div>";

    } catch (PDOException $e) {
        echo "<div class='alert alert-danger'>Database Error: " . $e->getMessage() . "</div>";
    } catch (Exception $e) {
        echo "<div class='alert alert-danger'>Error: " . $e->getMessage() . "</div>";
    }
}
?>
<!-- HTML Form here (your existing form without changes) -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Student Registration</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
     <link rel="stylesheet" href="style.css">
    <style>
    body {
        background-color: #f8f9fa;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }
    .my-icon {
  color: #6B8E23 !important;
  font-size: 18px !important;
}
</style>
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
    <div class="row  justify-content-center g-4">
        <div class="col-lg-7">
            <div class="card shadow p-4 h-100">
                <h2 class="mb-4"><i class="fas fa-user-plus me-2"></i>Register</h2>
                <form method="POST" enctype="multipart/form-data">
                    <div class="row">
                        <!-- Basic Info -->
                        <div class="col-md-6 mb-3">
                            <label><i class="fas fa-user my-icon me-2"></i>First Name</label>
                            <input type="text" name="First_Name" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label><i class="fas fa-user my-icon me-2"></i>Last Name</label>
                            <input type="text" name="Last_Name" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label><i class="fas fa-envelope my-icon me-2"></i>Email</label>
                            <input type="email" name="Email" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label><i class="fas fa-lock my-icon me-2"></i>Password</label>
                            <input type="password" name="Password" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label><i class="fas fa-calendar my-icon me-2"></i>Date of Birth</label>
                            <input type="date" name="DOB" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label><i class="fas fa-phone my-icon me-2"></i>Telephone</label>
                            <input type="text" name="Telephone" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label><i class="fas fa-map-marker-alt my-icon me-2"></i>Address</label>
                            <select name="Address" class="form-control" required>
                                <option value="">-- Select --</option>
                                <option value="Banjul">Banjul</option>
                                <option value="Brikama">Brikama</option>
                                <option value="Kotu">Kotu</option>
                                <option value="Lamin">Lamin</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label><i class="fas fa-image my-icon me-2"></i>Picture</label>
                            <input type="file" name="Picture" class="form-control" accept="image/*" required>
                        </div>

                        <!-- Role Selector -->
                        <div class="col-md-6 mb-3">
                            <label><i class="fas fa-users-cog my-icon me-2"></i>Role</label>
                            <select name="role" id="role" class="form-control" required>
                                <option value="student">Student</option>
                            </select>
                        </div>
                    </div>

                    <button type="submit" style="background-color: #6B8E23; margin-top:10px;"><i class="fas fa-paper-plane me-2"></i>Register</button>
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
    document.addEventListener('DOMContentLoaded', function () {
    // Helper function to validate email
    function isValidEmail(email) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    }

    // Helper function to validate phone (simple: 7+ digits)
    function isValidPhone(phone) {
        return /^[0-9]{7,}$/.test(phone);
    }

    // First Name
    const firstName = document.querySelector('input[name="First_Name"]');
    firstName.addEventListener('input', () => {
        if (firstName.value.trim().length >= 2) {
            firstName.classList.add('is-valid');
            firstName.classList.remove('is-invalid');
        } else {
            firstName.classList.add('is-invalid');
            firstName.classList.remove('is-valid');
        }
    });

    // Last Name
    const lastName = document.querySelector('input[name="Last_Name"]');
    lastName.addEventListener('input', () => {
        if (lastName.value.trim().length >= 2) {
            lastName.classList.add('is-valid');
            lastName.classList.remove('is-invalid');
        } else {
            lastName.classList.add('is-invalid');
            lastName.classList.remove('is-valid');
        }
    });

    // Email
    const email = document.querySelector('input[name="Email"]');
    email.addEventListener('input', () => {
        if (isValidEmail(email.value.trim())) {
            email.classList.add('is-valid');
            email.classList.remove('is-invalid');
        } else {
            email.classList.add('is-invalid');
            email.classList.remove('is-valid');
        }
    });

    // Password
    const password = document.querySelector('input[name="Password"]');
    password.addEventListener('input', () => {
        if (password.value.length >= 6) {
            password.classList.add('is-valid');
            password.classList.remove('is-invalid');
        } else {
            password.classList.add('is-invalid');
            password.classList.remove('is-valid');
        }
    });

    // Telephone
    const phone = document.querySelector('input[name="Telephone"]');
    phone.addEventListener('input', () => {
        if (isValidPhone(phone.value.trim())) {
            phone.classList.add('is-valid');
            phone.classList.remove('is-invalid');
        } else {
            phone.classList.add('is-invalid');
            phone.classList.remove('is-valid');
        }
    });
});
 </script>
</div>
</body>
</html>