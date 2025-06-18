<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lecturer Login - UNIOSUN</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #6B8E23, #8FBC8F);
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        
        .login-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        
        .login-header {
            background: linear-gradient(135deg, #6B8E23, #556B2F);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .btn-primary {
            background: #6B8E23;
            border-color: #6B8E23;
        }
        
        .btn-primary:hover {
            background: #556B2F;
            border-color: #556B2F;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-4">
                <div class="login-card">
                    <div class="login-header">
                        <i class="fas fa-university fa-3x mb-3"></i>
                        <h3>UNIOSUN</h3>
                        <p class="mb-0">Lecturer Portal</p>
                    </div>
                    
                    <div class="p-4">
                        <?php
                        session_start();
                        
                        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                            // Database connection
                            $host = 'localhost';
                            $db   = 'septsoft24';
                            $user = 'root';
                            $pass = 'novirus123';
                            $dsn = "mysql:host=$host;dbname=$db;charset=utf8mb4";
                            
                            try {
                                $pdo = new PDO($dsn, $user, $pass);
                                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                                
                                $staff_id = $_POST['staff_id'];
                                $password = $_POST['password'];
                                
                                // Get lecturer by email OR staff_id
                                $stmt = $pdo->prepare("SELECT * FROM lecturers WHERE (email = ? OR staff_id = ?) AND status = 'active'");
                                $stmt->execute([$staff_id, $staff_id]);
                                $lecturer = $stmt->fetch(PDO::FETCH_ASSOC);
                                
                                if ($lecturer) {
                                    // Check if password column exists and has value
                                    if (!empty($lecturer['password'])) {
                                        // Use password_verify for hashed passwords
                                        if (password_verify($password, $lecturer['password'])) {
                                            // Login successful
                                            $_SESSION['lecturer_logged_in'] = true;
                                            $_SESSION['lecturer_id'] = $lecturer['lecturer_id'];
                                            $_SESSION['lecturer_name'] = $lecturer['first_name'] . ' ' . $lecturer['last_name'];
                                            $_SESSION['staff_id'] = $lecturer['staff_id'];
                                            
                                            header("Location: lecturer_dashboard.php");
                                            exit;
                                        } else {
                                            $error = "Invalid password";
                                        }
                                    } else {
                                        // Fallback: if no password set, use staff_id as password
                                        if ($password === $staff_id) {
                                            $_SESSION['lecturer_logged_in'] = true;
                                            $_SESSION['lecturer_id'] = $lecturer['lecturer_id'];
                                            $_SESSION['lecturer_name'] = $lecturer['first_name'] . ' ' . $lecturer['last_name'];
                                            $_SESSION['staff_id'] = $lecturer['staff_id'];
                                            
                                            header("Location: lecturer_dashboard.php");
                                            exit;
                                        } else {
                                            $error = "Invalid password (use your Staff ID as password)";
                                        }
                                    }
                                } else {
                                    $error = "Staff ID not found or account inactive";
                                }
                                
                            } catch (PDOException $e) {
                                $error = "Database connection error";
                            }
                        }
                        ?>
                        
                        <?php if (isset($error)): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST">
                            <div class="mb-3">
                                <label for="staff_id" class="form-label">Staff ID or Email</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-id-card"></i></span>
                                    <input type="text" class="form-control" id="staff_id" name="staff_id" 
                                           placeholder="Enter your Staff ID or Email" required 
                                           value="<?php echo isset($_POST['staff_id']) ? htmlspecialchars($_POST['staff_id']) : ''; ?>">
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="password" class="form-label">Password</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                    <input type="password" class="form-control" id="password" name="password" 
                                           placeholder="Enter password" required>
                                </div>
                                <small class="text-muted">Use the password set by admin</small>
                            </div>
                            
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-sign-in-alt"></i> Login
                            </button>
                        </form>
                        
                        <div class="text-center mt-3">
                            <a href="admin_login.php" class="text-muted">Admin Login</a>
                        </div>
                        
                        <!-- Debug Info - Remove this after testing -->
                        <?php if (isset($_POST['staff_id'])): ?>
                            <div class="mt-3 p-3 bg-light rounded">
                                <small class="text-muted">
                                    <strong>Debug Info:</strong><br>
                                    Staff ID: <?php echo htmlspecialchars($_POST['staff_id']); ?><br>
                                    Try these passwords:<br>
                                    • The password you entered when creating the lecturer<br>
                                    • Or your Staff ID (<?php echo htmlspecialchars($_POST['staff_id']); ?>)
                                </small>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>