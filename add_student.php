<?php
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: admin_login.php");
    exit;
}

// Database connection
$host = 'localhost';
$db   = 'septsoft24';
$db_user = 'root';
$db_pass = 'novirus123';
$dsn = "mysql:host=$host;dbname=$db;charset=utf8mb4";

try {
    $pdo = new PDO($dsn, $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Get departments for dropdown
try {
    $dept_stmt = $pdo->query("SELECT department_id, department_name, department_code FROM departments ORDER BY department_name");
    $departments = $dept_stmt->fetchAll();
} catch (PDOException $e) {
    $departments = [];
}

$success_message = '';
$error_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_student'])) {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $department = $_POST['department'] ?? '';
    $password = $_POST['password'] ?? '';
    $status = $_POST['status'] ?? 'active';

    // Validation
    $errors = [];
    
    if (empty($first_name)) $errors[] = "First name is required";
    if (empty($last_name)) $errors[] = "Last name is required";
    if (empty($email)) $errors[] = "Email is required";
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Valid email is required";
    if (empty($password)) $errors[] = "Password is required";
    if (strlen($password) < 6) $errors[] = "Password must be at least 6 characters";

    // Check if email already exists
    if (empty($errors)) {
        try {
            $check_stmt = $pdo->prepare("SELECT ID FROM studrec WHERE EMAIL = ?");
            $check_stmt->execute([$email]);
            if ($check_stmt->fetch()) {
                $errors[] = "Email address already exists";
            }
        } catch (PDOException $e) {
            $errors[] = "Database error: " . $e->getMessage();
        }
    }

    // Insert student if no errors
    if (empty($errors)) {
        try {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            $stmt = $pdo->prepare("
                INSERT INTO studrec (FIRST_NAME, LAST_NAME, EMAIL, PHONE, DEPARTMENT, PASSWORD, STATUS, CREATED_AT) 
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $first_name,
                $last_name, 
                $email,
                $phone,
                $department,
                $hashed_password,
                $status
            ]);

            $success_message = "Student added successfully! Student ID: " . $pdo->lastInsertId();
            
            // Clear form data
            $first_name = $last_name = $email = $phone = $department = '';
            
        } catch (PDOException $e) {
            $error_message = "Error adding student: " . $e->getMessage();
        }
    } else {
        $error_message = implode("<br>", $errors);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Student - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #F5F8F0, #E8F0E0);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
        }

        /* Top Header */
        .top-header {
            background: linear-gradient(135deg, #6B8E23, #5A7A1C);
            padding: 12px 0;
            box-shadow: 0 2px 8px rgba(107, 142, 35, 0.3);
            border-bottom: 3px solid #556B1F;
        }

        .top-header a {
            color: white !important;
            text-decoration: none;
            font-weight: 500;
            margin-right: 25px;
            transition: all 0.3s ease;
            padding: 8px 12px;
            border-radius: 6px;
        }

        .top-header a:hover {
            background: rgba(255, 255, 255, 0.15);
            color: #E8F5E8 !important;
        }

        .search-box {
            background: rgba(255, 255, 255, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 25px;
            padding: 8px 16px;
            color: white;
            width: 200px;
            margin-right: 20px;
        }

        .search-box::placeholder {
            color: rgba(255, 255, 255, 0.7);
        }

        .auth-btn {
            background: rgba(220, 53, 69, 0.9);
            color: white !important;
            padding: 8px 15px;
            border-radius: 20px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .main-content {
            max-width: 800px;
            margin: 30px auto;
            padding: 0 20px;
        }

        .page-header {
            background: linear-gradient(135deg, #ffffff, #f8fdf8);
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 8px 20px rgba(107, 142, 35, 0.1);
            border: 2px solid rgba(107, 142, 35, 0.1);
        }

        .page-header h1 {
            color: #556B1F;
            margin: 0;
        }

        .form-card {
            background: linear-gradient(135deg, #ffffff, #f8fdf8);
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 5px 15px rgba(107, 142, 35, 0.08);
            border: 1px solid rgba(107, 142, 35, 0.1);
        }

        .form-section {
            margin-bottom: 25px;
        }

        .form-section h5 {
            color: #6B8E23;
            border-bottom: 1px solid rgba(107, 142, 35, 0.3);
            padding-bottom: 8px;
            margin-bottom: 15px;
            font-size: 1rem;
            font-weight: 600;
        }

        .form-control:focus {
            border-color: #6B8E23;
            box-shadow: 0 0 0 0.2rem rgba(107, 142, 35, 0.25);
        }

        .form-select:focus {
            border-color: #6B8E23;
            box-shadow: 0 0 0 0.2rem rgba(107, 142, 35, 0.25);
        }

        .btn-olive {
            background: #6B8E23;
            border-color: #6B8E23;
            color: white;
            padding: 10px 25px;
            font-weight: 500;
        }

        .btn-olive:hover {
            background: #5A7A1C;
            border-color: #5A7A1C;
            color: white;
        }

        .btn-back {
            background: #6c757d;
            border-color: #6c757d;
            color: white;
            padding: 10px 25px;
            font-weight: 500;
        }

        .btn-back:hover {
            background: #5a6268;
            border-color: #5a6268;
            color: white;
        }

        .required {
            color: #dc3545;
        }

        .alert {
            border: none;
            border-radius: 10px;
            padding: 15px 20px;
        }

        .alert-success {
            background: linear-gradient(135deg, #d4edda, #c3e6cb);
            color: #155724;
            border-left: 4px solid #28a745;
        }

        .alert-danger {
            background: linear-gradient(135deg, #f8d7da, #f1aeb5);
            color: #721c24;
            border-left: 4px solid #dc3545;
        }

        .form-label {
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
        }

        .form-text {
            font-size: 0.875rem;
            color: #6B8E23;
        }

        .password-strength {
            margin-top: 5px;
        }

        .strength-weak { color: #dc3545; }
        .strength-medium { color: #ffc107; }
        .strength-strong { color: #28a745; }

        .preview-section {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-top: 15px;
            border-left: 3px solid #6B8E23;
        }

        .preview-section h6 {
            font-size: 0.95rem;
            font-weight: 500;
            margin-bottom: 10px;
            color: #6B8E23;
        }
    </style>
</head>
<body>

<!-- Top Header -->
<div class="top-header">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-6">
                <a href="admin_dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                <a href="manage_students.php"><i class="fas fa-users"></i> Students</a>
                <a href="#"><i class="fas fa-globe"></i> E-Portals</a>
            </div>
            <div class="col-md-6 text-end d-flex align-items-center justify-content-end">
                <input type="text" class="search-box" placeholder="Quick Search...">
                <div class="auth-toggle">
                    <span class="text-white me-3">
                        <i class="fas fa-user-shield"></i> 
                        Welcome, <?php echo htmlspecialchars($_SESSION['admin_name'] ?? 'Admin'); ?>
                    </span>
                    <a href="admin_logout.php" class="auth-btn">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="main-content">
    <!-- Page Header -->
    <div class="page-header">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h1><i class="fas fa-user-plus"></i> Add New Student</h1>
                <p class="mb-0 text-muted">Create a new student account in the system</p>
            </div>
            <div class="col-md-4 text-end">
                <a href="manage_students.php" class="btn btn-back">
                    <i class="fas fa-arrow-left"></i> Back to Students
                </a>
            </div>
        </div>
    </div>

    <!-- Success/Error Messages -->
    <?php if ($success_message): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
            <div class="mt-2">
                <a href="manage_students.php" class="btn btn-sm btn-success me-2">
                    <i class="fas fa-users"></i> View All Students
                </a>
                <button onclick="location.reload()" class="btn btn-sm btn-outline-success">
                    <i class="fas fa-plus"></i> Add Another Student
                </button>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($error_message): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-triangle"></i> <?php echo $error_message; ?>
        </div>
    <?php endif; ?>

    <!-- Add Student Form -->
    <div class="form-card">
        <form method="POST" action="" id="addStudentForm" novalidate>
            <!-- Personal Information -->
            <div class="form-section">
                <h5><i class="fas fa-user"></i> Personal Information</h5>
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="first_name" class="form-label">
                                First Name <span class="required">*</span>
                            </label>
                            <input 
                                type="text" 
                                class="form-control" 
                                id="first_name"
                                name="first_name" 
                                value="<?php echo htmlspecialchars($first_name ?? ''); ?>"
                                required
                                placeholder="Enter first name"
                            >
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="last_name" class="form-label">
                                Last Name <span class="required">*</span>
                            </label>
                            <input 
                                type="text" 
                                class="form-control" 
                                id="last_name"
                                name="last_name" 
                                value="<?php echo htmlspecialchars($last_name ?? ''); ?>"
                                required
                                placeholder="Enter last name"
                            >
                        </div>
                    </div>
                </div>
            </div>

            <!-- Contact Information -->
            <div class="form-section">
                <h5><i class="fas fa-envelope"></i> Contact Information</h5>
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="email" class="form-label">
                                Email Address <span class="required">*</span>
                            </label>
                            <input 
                                type="email" 
                                class="form-control" 
                                id="email"
                                name="email" 
                                value="<?php echo htmlspecialchars($email ?? ''); ?>"
                                required
                                placeholder="student@example.com"
                            >
                            <div class="form-text">This will be used for student login</div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="phone" class="form-label">Phone Number</label>
                            <input 
                                type="tel" 
                                class="form-control" 
                                id="phone"
                                name="phone" 
                                value="<?php echo htmlspecialchars($phone ?? ''); ?>"
                                placeholder="+234 123 456 7890"
                            >
                        </div>
                    </div>
                </div>
            </div>

            <!-- Academic Information -->
            <div class="form-section">
                <h5><i class="fas fa-graduation-cap"></i> Academic Information</h5>
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="department" class="form-label">Department</label>
                            <select class="form-select" id="department" name="department">
                                <option value="">Select Department (Optional)</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?php echo htmlspecialchars($dept['department_name']); ?>"
                                            <?php echo (($department ?? '') === $dept['department_name']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($dept['department_code'] . ' - ' . $dept['department_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="status" class="form-label">Student Status</label>
                            <select class="form-select" id="status" name="status">
                                <option value="active" <?php echo (($status ?? 'active') === 'active') ? 'selected' : ''; ?>>
                                    Active
                                </option>
                                <option value="inactive" <?php echo (($status ?? '') === 'inactive') ? 'selected' : ''; ?>>
                                    Inactive
                                </option>
                                <option value="suspended" <?php echo (($status ?? '') === 'suspended') ? 'selected' : ''; ?>>
                                    Suspended
                                </option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Security -->
            <div class="form-section">
                <h5><i class="fas fa-lock"></i> Account Security</h5>
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="password" class="form-label">
                                Password <span class="required">*</span>
                            </label>
                            <input 
                                type="password" 
                                class="form-control" 
                                id="password"
                                name="password" 
                                required
                                placeholder="Enter secure password"
                                minlength="6"
                            >
                            <div class="form-text">Minimum 6 characters required</div>
                            <div class="password-strength" id="passwordStrength"></div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">
                                Confirm Password <span class="required">*</span>
                            </label>
                            <input 
                                type="password" 
                                class="form-control" 
                                id="confirm_password"
                                name="confirm_password" 
                                required
                                placeholder="Confirm password"
                            >
                            <div class="form-text" id="passwordMatch"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Preview Section -->
            <div class="preview-section">
                <h6><i class="fas fa-eye"></i> Student Preview</h6>
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Name:</strong> <span id="preview-name">-</span></p>
                        <p><strong>Email:</strong> <span id="preview-email">-</span></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Department:</strong> <span id="preview-dept">Not Assigned</span></p>
                        <p><strong>Status:</strong> <span id="preview-status">Active</span></p>
                    </div>
                </div>
            </div>

            <!-- Submit Buttons -->
            <div class="row mt-4">
                <div class="col-md-6">
                    <a href="manage_students.php" class="btn btn-back w-100">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                </div>
                <div class="col-md-6">
                    <button type="submit" name="add_student" class="btn btn-olive w-100">
                        <i class="fas fa-plus"></i> Add Student
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Real-time form preview
function updatePreview() {
    const firstName = document.getElementById('first_name').value;
    const lastName = document.getElementById('last_name').value;
    const email = document.getElementById('email').value;
    const department = document.getElementById('department');
    const status = document.getElementById('status').value;

    document.getElementById('preview-name').textContent = 
        (firstName || lastName) ? `${firstName} ${lastName}`.trim() : '-';
    document.getElementById('preview-email').textContent = email || '-';
    document.getElementById('preview-dept').textContent = 
        department.options[department.selectedIndex].text === 'Select Department (Optional)' 
            ? 'Not Assigned' 
            : department.options[department.selectedIndex].text;
    document.getElementById('preview-status').textContent = status.charAt(0).toUpperCase() + status.slice(1);
}

// Password strength checker
function checkPasswordStrength(password) {
    const strengthDiv = document.getElementById('passwordStrength');
    let strength = 0;
    let text = '';
    let className = '';

    if (password.length >= 6) strength++;
    if (password.match(/[a-z]/) && password.match(/[A-Z]/)) strength++;
    if (password.match(/[0-9]/)) strength++;
    if (password.match(/[^a-zA-Z0-9]/)) strength++;

    switch(strength) {
        case 0:
        case 1:
            text = 'Weak';
            className = 'strength-weak';
            break;
        case 2:
        case 3:
            text = 'Medium';
            className = 'strength-medium';
            break;
        case 4:
            text = 'Strong';
            className = 'strength-strong';
            break;
    }

    strengthDiv.innerHTML = `<small class="${className}">Password Strength: ${text}</small>`;
}

// Password confirmation
function checkPasswordMatch() {
    const password = document.getElementById('password').value;
    const confirmPassword = document.getElementById('confirm_password').value;
    const matchDiv = document.getElementById('passwordMatch');

    if (confirmPassword) {
        if (password === confirmPassword) {
            matchDiv.innerHTML = '<small class="text-success">Passwords match</small>';
        } else {
            matchDiv.innerHTML = '<small class="text-danger">Passwords do not match</small>';
        }
    } else {
        matchDiv.innerHTML = '';
    }
}

// Event listeners
document.getElementById('first_name').addEventListener('input', updatePreview);
document.getElementById('last_name').addEventListener('input', updatePreview);
document.getElementById('email').addEventListener('input', updatePreview);
document.getElementById('department').addEventListener('change', updatePreview);
document.getElementById('status').addEventListener('change', updatePreview);

document.getElementById('password').addEventListener('input', function() {
    checkPasswordStrength(this.value);
    checkPasswordMatch();
});
document.getElementById('confirm_password').addEventListener('input', checkPasswordMatch);

// Form validation
document.getElementById('addStudentForm').addEventListener('submit', function(e) {
    const password = document.getElementById('password').value;
    const confirmPassword = document.getElementById('confirm_password').value;

    if (password !== confirmPassword) {
        e.preventDefault();
        alert('Passwords do not match!');
        return false;
    }

    if (password.length < 6) {
        e.preventDefault();
        alert('Password must be at least 6 characters long!');
        return false;
    }
});

// Initialize preview
updatePreview();
</script>
</body>
</html>