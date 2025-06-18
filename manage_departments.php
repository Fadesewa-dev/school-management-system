<?php
// manage_departments.php - Complete Department Management System

// Include your database connection
$host = 'localhost';
$db   = 'septsoft24';
$user = 'root';
$pass = 'novirus123';
$dsn = "mysql:host=$host;dbname=$db;charset=utf8mb4";

// Create PDO connection
try {
    $pdo = new PDO($dsn, $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'add':
                    $stmt = $pdo->prepare("INSERT INTO departments (department_name, department_code, head_of_department, description, created_at) VALUES (?, ?, ?, ?, NOW())");
                    $stmt->execute([
                        $_POST['department_name'],
                        $_POST['department_code'],
                        $_POST['head_of_department'] ?: null,
                        $_POST['description']
                    ]);
                    $success = "Department added successfully!";
                    break;
                
                case 'edit':
                    $stmt = $pdo->prepare("UPDATE departments SET department_name = ?, department_code = ?, head_of_department = ?, description = ? WHERE department_id = ?");
                    $stmt->execute([
                        $_POST['department_name'],
                        $_POST['department_code'],
                        $_POST['head_of_department'] ?: null,
                        $_POST['description'],
                        $_POST['department_id']
                    ]);
                    $success = "Department updated successfully!";
                    break;
                
                case 'delete':
                    // Check if department has associated courses or students
                    $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM courses WHERE department_id = ?");
                    $checkStmt->execute([$_POST['department_id']]);
                    $courseCount = $checkStmt->fetchColumn();
                    
                    if ($courseCount > 0) {
                        $error = "Cannot delete department. It has {$courseCount} associated courses.";
                    } else {
                        $stmt = $pdo->prepare("DELETE FROM departments WHERE department_id = ?");
                        $stmt->execute([$_POST['department_id']]);
                        $success = "Department deleted successfully!";
                    }
                    break;
            }
        }
    } catch (PDOException $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Fetch all departments with statistics
try {
    // First, let's get departments with course counts only
    $stmt = $pdo->query("
        SELECT 
            d.*,
            COUNT(DISTINCT c.course_id) as course_count,
            CONCAT(l.first_name, ' ', l.last_name) as hod_name
        FROM departments d
        LEFT JOIN courses c ON d.department_id = c.department_id
        LEFT JOIN lecturers l ON d.head_of_department = l.lecturer_id
        GROUP BY d.department_id
        ORDER BY d.department_name
    ");
    $departments = $stmt->fetchAll();
    
    // Try to get student counts if the department column exists in studrec
    try {
        // Check what columns exist in studrec table
        $checkStmt = $pdo->query("SHOW COLUMNS FROM studrec LIKE '%department%'");
        $deptColumns = $checkStmt->fetchAll();
        
        if (!empty($deptColumns)) {
            // If department column exists, get student counts
            $columnName = $deptColumns[0]['Field']; // Use the first department-related column
            
            foreach ($departments as &$dept) {
                $studentStmt = $pdo->prepare("SELECT COUNT(*) as student_count FROM studrec WHERE {$columnName} = ? AND role = 'student'");
                $studentStmt->execute([$dept['department_name']]);
                $dept['student_count'] = $studentStmt->fetchColumn();
            }
        } else {
            // No department column found, set student count to 0
            foreach ($departments as &$dept) {
                $dept['student_count'] = 0;
            }
        }
    } catch (PDOException $e) {
        // If there's any error with student counts, just set to 0
        foreach ($departments as &$dept) {
            $dept['student_count'] = 0;
        }
    }
    
    // Get lecturers for dropdown
    $lecturerStmt = $pdo->query("SELECT lecturer_id, CONCAT(first_name, ' ', last_name) as lecturer_name FROM lecturers WHERE status = 'active' ORDER BY first_name");
    $lecturers = $lecturerStmt->fetchAll();
    
} catch (PDOException $e) {
    $departments = [];
    $lecturers = [];
    $error = "Error fetching departments: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Departments - UNIOSUN</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #6B8E23 0%, #8FBC8F 100%);
            min-height: 100vh;
            color: #333;
        }
        
        /* Remove duplicate styles - they're in style.css */
        
        /* Content Container */
        .content-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .page-header {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .btn-primary { background: #6B8E23; color: white; border: none; }
        .btn-success { background: #6B8E23; color: white; border: none; }
        .btn-warning { background: #ffc107; color: #212529; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-info { background: #6B8E23; color: white; border: none; }
        
        .btn:hover { transform: translateY(-2px); opacity: 0.9; }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .stat-value {
            font-size: 2em;
            font-weight: bold;
            color: #6B8E23;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #666;
            font-size: 0.9em;
        }
        
        .departments-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .department-card {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: transform 0.3s;
        }
        
        .department-card:hover {
            transform: translateY(-5px);
        }
        
        .department-header {
            background: linear-gradient(135deg, #6B8E23 0%, #556B2F 100%);
            color: white;
            padding: 20px;
        }
        
        .department-code {
            font-size: 0.8em;
            opacity: 0.9;
            margin-bottom: 5px;
        }
        
        .department-name {
            font-size: 1.2em;
            font-weight: bold;
            margin-bottom: 10px;
        }
        
        .department-hod {
            font-size: 0.9em;
            opacity: 0.9;
        }
        
        .department-body {
            padding: 20px;
        }
        
        .department-stats {
            display: flex;
            justify-content: space-around;
            margin-bottom: 15px;
        }
        
        .stat {
            text-align: center;
        }
        
        .stat-number {
            font-size: 1.5em;
            font-weight: bold;
            color: #6B8E23;
        }
        
        .stat-text {
            font-size: 0.8em;
            color: #666;
        }
        
        .department-actions {
            display: flex;
            gap: 10px;
            justify-content: center;
        }
        
        .description {
            color: #666;
            font-size: 0.9em;
            margin-bottom: 15px;
            line-height: 1.4;
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
        }
        
        .modal-content {
            background: white;
            margin: 50px auto;
            padding: 30px;
            border-radius: 10px;
            max-width: 500px;
            width: 90%;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }
        
        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #6B8E23;
            box-shadow: 0 0 0 0.2rem rgba(107, 142, 35, 0.25);
        }
        
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .close {
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        
        .close:hover {
            color: #999;
        }
        /* Footer Styles */
.footer { #556B2F;
    color: white;
    padding: 40px 0 20px 0;
    margin-top: 50px;
}

.footer .container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 30px;
}

.footer .row {
    display: flex;
    flex-wrap: wrap;
    margin: 0 -15px;
}

.footer .col-lg-4,
.footer .col-md-6,
.footer .col-md-12 {
    padding: 0 15px;
    margin-bottom: 30px;
}

.footer .col-lg-4 {
    flex: 0 0 33.333333%;
    max-width: 33.333333%;
}

.footer .col-md-6 {
    flex: 0 0 33.333333%;
    max-width: 33.333333%;
}

.footer .col-md-12 {
    flex: 0 0 33.333333%;
    max-width: 33.333333%;
}

.footer h5 {
    color: #9ACD32;
    margin-bottom: 20px;
    font-weight: 600;
    font-size: 1.2em;
}

.footer p {
    margin-bottom: 10px;
    line-height: 1.6;
}

.footer i {
    margin-right: 10px;
    color:#9ACD32;
    width: 20px;
}

.footer .list-unstyled {
    list-style: none;
    padding: 0;
    margin: 0;
}

.footer .list-unstyled li {
    margin-bottom: 8px;
}

.footer .list-unstyled a {
    color: #ecf0f1;
    text-decoration: none;
    transition: color 0.3s;
}

.footer .list-unstyled a:hover {
    color: #6B8E23;
}

.footer .social-links a {
    color: #ecf0f1;
    margin-right: 15px;
    transition: color 0.3s;
}

.footer .social-links a:hover {
    color: #6B8E23;
}

.footer hr {
    border: 0;
    border-top: 1px solid rgba(255,255,255,0.3);
    margin: 30px 0 20px 0;
}

.footer .text-center {
    text-align: center;
}

.footer .text-center p {
    margin: 0;
    color: #bdc3c7;
    font-size: 0.9em;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .footer .col-lg-4,
    .footer .col-md-6,
    .footer .col-md-12 {
        flex: 0 0 100%;
        max-width: 100%;
        text-align: center;
    }
    
    .footer .row {
        text-align: center;
    }
}

/* Fix for social media icons */
.footer .me-3 {
    margin-right: 15px !important;
}

.footer .fab {
    font-size: 1.5em;
}
    </style>
</head>
<body>
    <!-- Top Header -->
    <div class="top-header" style="padding-left:70px;">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <a href="admin_dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                    <a href="#"><i class="fas fa-graduation-cap"></i> UNIOSUN</a>
                    <a href="#"><i class="fas fa-globe"></i> E-Portals</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Header -->
    <div class="main-header" style="padding-left:70px;">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-12">
                    <div class="logo-section">
                        <div class="logo">
                            <i class="fas fa-university"></i>
                        </div>
                        <div class="university-name">
                            <h1>OSUN STATE UNIVERSITY</h1>
                            <p class="university-tagline">School Management System - Department Management</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Content Container -->
    <div class="content-container">
        <!-- Page Header -->
        <div class="page-header">
            <div>
                <h1><i class="fas fa-building"></i> Department Management</h1>
                <p>Manage academic departments and their information</p>
            </div>
            <div style="display: flex; gap: 10px; align-items: center;">
                <a href="admin_dashboard.php" class="btn btn-danger" title="Back to Dashboard">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
                <button class="btn btn-primary" onclick="openModal('addModal')">
                    <i class="fas fa-plus"></i> Add Department
                </button>
            </div>
        </div>

        <!-- Success/Error Messages -->
        <?php if (isset($success)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo $success; ?>
            </div>
        <?php endif; ?>

        <?php if (isset($error)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?php echo count($departments); ?></div>
                <div class="stat-label">Total Departments</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo array_sum(array_column($departments, 'course_count')); ?></div>
                <div class="stat-label">Total Courses</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo array_sum(array_column($departments, 'student_count')); ?></div>
                <div class="stat-label">Total Students</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo count(array_filter($departments, function($d) { return !empty($d['head_of_department']); })); ?></div>
                <div class="stat-label">Departments with HOD</div>
            </div>
        </div>

        <!-- Departments Grid -->
        <div class="departments-grid">
            <?php foreach ($departments as $dept): ?>
                <div class="department-card">
                    <div class="department-header">
                        <div class="department-code"><?php echo htmlspecialchars($dept['department_code']); ?></div>
                        <div class="department-name"><?php echo htmlspecialchars($dept['department_name']); ?></div>
                        <div class="department-hod">
                            <i class="fas fa-user"></i> 
                            HOD: <?php echo $dept['hod_name'] ? htmlspecialchars($dept['hod_name']) : 'Not Assigned'; ?>
                        </div>
                    </div>
                    <div class="department-body">
                        <div class="description">
                            <?php echo htmlspecialchars(substr($dept['description'], 0, 100)) . (strlen($dept['description']) > 100 ? '...' : ''); ?>
                        </div>
                        
                        <div class="department-stats">
                            <div class="stat">
                                <div class="stat-number"><?php echo $dept['course_count']; ?></div>
                                <div class="stat-text">Courses</div>
                            </div>
                            <div class="stat">
                                <div class="stat-number"><?php echo $dept['student_count']; ?></div>
                                <div class="stat-text">Students</div>
                            </div>
                        </div>
                        
                        <div class="department-actions">
                            <button class="btn btn-info btn-sm" onclick="viewDepartment(<?php echo $dept['department_id']; ?>)">
                                <i class="fas fa-eye"></i> View
                            </button>
                            <button class="btn btn-warning btn-sm" onclick="editDepartment(<?php echo htmlspecialchars(json_encode($dept)); ?>)">
                                <i class="fas fa-edit"></i> Edit
                            </button>
                            <button class="btn btn-danger btn-sm" onclick="deleteDepartment(<?php echo $dept['department_id']; ?>, '<?php echo htmlspecialchars($dept['department_name']); ?>')">
                                <i class="fas fa-trash"></i> Delete
                            </button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Add Department Modal -->
    <div id="addModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('addModal')">&times;</span>
            <h2><i class="fas fa-plus"></i> Add New Department</h2>
            <form method="POST">
                <input type="hidden" name="action" value="add">
                
                <div class="form-group">
                    <label for="department_name">Department Name *</label>
                    <input type="text" class="form-control" name="department_name" required>
                </div>
                
                <div class="form-group">
                    <label for="department_code">Department Code *</label>
                    <input type="text" class="form-control" name="department_code" required maxlength="10">
                </div>
                
                <div class="form-group">
                    <label for="head_of_department">Head of Department</label>
                    <select class="form-control" name="head_of_department">
                        <option value="">Select HOD (Optional)</option>
                        <?php foreach ($lecturers as $lecturer): ?>
                            <option value="<?php echo $lecturer['lecturer_id']; ?>">
                                <?php echo htmlspecialchars($lecturer['lecturer_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea class="form-control" name="description" rows="3"></textarea>
                </div>
                
                <div style="display: flex; gap: 10px; justify-content: flex-end;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('addModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Department</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Department Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('editModal')">&times;</span>
            <h2><i class="fas fa-edit"></i> Edit Department</h2>
            <form method="POST" id="editForm">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="department_id" id="edit_department_id">
                
                <div class="form-group">
                    <label for="edit_department_name">Department Name *</label>
                    <input type="text" class="form-control" name="department_name" id="edit_department_name" required>
                </div>
                
                <div class="form-group">
                    <label for="edit_department_code">Department Code *</label>
                    <input type="text" class="form-control" name="department_code" id="edit_department_code" required maxlength="10">
                </div>
                
                <div class="form-group">
                    <label for="edit_head_of_department">Head of Department</label>
                    <select class="form-control" name="head_of_department" id="edit_head_of_department">
                        <option value="">Select HOD (Optional)</option>
                        <?php foreach ($lecturers as $lecturer): ?>
                            <option value="<?php echo $lecturer['lecturer_id']; ?>">
                                <?php echo htmlspecialchars($lecturer['lecturer_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="edit_description">Description</label>
                    <textarea class="form-control" name="description" id="edit_description" rows="3"></textarea>
                </div>
                
                <div style="display: flex; gap: 10px; justify-content: flex-end;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('editModal')">Cancel</button>
                    <button type="submit" class="btn btn-success">Update Department</button>
                </div>
            </form>
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
                        <li><a href="manage_admissions.php">Admissions</a></li>
                        <li><a href="manage_students.php">Student Portal</a></li>
                        <li><a href="manage_lecturers.php">Faculty Directory</a></li>
                        <li><a href="reports.php">Reports</a></li>
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
        function openModal(modalId) {
            document.getElementById(modalId).style.display = 'block';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        function editDepartment(dept) {
            document.getElementById('edit_department_id').value = dept.department_id;
            document.getElementById('edit_department_name').value = dept.department_name;
            document.getElementById('edit_department_code').value = dept.department_code;
            document.getElementById('edit_head_of_department').value = dept.head_of_department || '';
            document.getElementById('edit_description').value = dept.description;
            openModal('editModal');
        }

        function deleteDepartment(id, name) {
            if (confirm(`Are you sure you want to delete "${name}" department?`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="department_id" value="${id}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function viewDepartment(id) {
            // Redirect to department details page
            window.location.href = `department_details.php?id=${id}`;
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                if (event.target === modal) {
                    modal.style.display = 'none';
                }
            });
        }
    </script>
</body>
</html>