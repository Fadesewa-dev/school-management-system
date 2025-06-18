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
$user = 'root';
$pass = 'novirus123';
$dsn = "mysql:host=$host;dbname=$db;charset=utf8mb4";

try {
    $pdo = new PDO($dsn, $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'add_lecturer':
            addLecturer($pdo);
            break;
        case 'get_lecturers':
            getLecturers($pdo);
            break;
        case 'delete_lecturer':
            deleteLecturer($pdo);
            break;
        case 'update_lecturer':
            updateLecturer($pdo);
            break;
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
    exit;
}

function addLecturer($pdo) {
    try {
        // Validate required fields
        if (empty($_POST['first_name']) || empty($_POST['last_name']) || empty($_POST['password'])) {
            echo json_encode(['success' => false, 'message' => 'First name, last name, and password are required']);
            return;
        }
        
        // Generate staff_id
        $staff_id = generateStaffId($pdo);
        
        // Hash the password
        $hashedPassword = password_hash($_POST['password'], PASSWORD_DEFAULT);
        
        $stmt = $pdo->prepare("
            INSERT INTO lecturers (
                staff_id, first_name, last_name, email, phone, 
                department_id, title, academic_rank, status, 
                salary, date_employed, password
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $result = $stmt->execute([
            $staff_id,
            $_POST['first_name'],
            $_POST['last_name'],
            $_POST['email'],
            $_POST['phone'],
            $_POST['department_id'],
            $_POST['title'],
            $_POST['academic_rank'],
            $_POST['status'],
            $_POST['salary'] ?: null,
            $_POST['date_employed'] ?: null,
            $hashedPassword
        ]);
        
        if ($result) {
            echo json_encode([
                'success' => true, 
                'message' => 'Lecturer added successfully!',
                'staff_id' => $staff_id
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to add lecturer']);
        }
        
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}function getLecturers($pdo) {
    try {
        $stmt = $pdo->query("
            SELECT l.*, d.department_name 
            FROM lecturers l 
            LEFT JOIN departments d ON l.department_id = d.department_id 
            ORDER BY l.created_at DESC
        ");
        $lecturers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'data' => $lecturers]);
        
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function deleteLecturer($pdo) {
    try {
        $stmt = $pdo->prepare("DELETE FROM lecturers WHERE lecturer_id = ?");
        $result = $stmt->execute([$_POST['lecturer_id']]);
        
        if ($result) {
            echo json_encode(['success' => true, 'message' => 'Lecturer deleted successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to delete lecturer']);
        }
        
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function updateLecturer($pdo) {
    try {
        $stmt = $pdo->prepare("
            UPDATE lecturers SET 
                first_name = ?, last_name = ?, email = ?, phone = ?,
                department_id = ?, title = ?, academic_rank = ?, 
                status = ?, salary = ?, date_employed = ?
            WHERE lecturer_id = ?
        ");
        
        $result = $stmt->execute([
            $_POST['first_name'],
            $_POST['last_name'],
            $_POST['email'],
            $_POST['phone'],
            $_POST['department_id'],
            $_POST['title'],
            $_POST['academic_rank'],
            $_POST['status'],
            $_POST['salary'] ?: null,
            $_POST['date_employed'] ?: null,
            $_POST['lecturer_id']
        ]);
        
        if ($result) {
            echo json_encode(['success' => true, 'message' => 'Lecturer updated successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update lecturer']);
        }
        
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function generateStaffId($pdo) {
    // Generate unique staff ID like LECT2024001
    $year = date('Y');
    $stmt = $pdo->query("SELECT COUNT(*) + 1 as next_id FROM lecturers");
    $next_id = $stmt->fetch()['next_id'];
    return 'LECT' . $year . str_pad($next_id, 3, '0', STR_PAD_LEFT);
}

// Get departments for dropdown
try {
    $stmt = $pdo->query("SELECT department_id, department_name FROM departments ORDER BY department_name");
    $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $departments = [];
}

// Get lecturer statistics
try {
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM lecturers");
    $totalLecturers = $stmt->fetch()['total'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as active FROM lecturers WHERE status = 'active'");
    $activeLecturers = $stmt->fetch()['active'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as new_this_month FROM lecturers WHERE MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE())");
    $newThisMonth = $stmt->fetch()['new_this_month'];
    
} catch (PDOException $e) {
    $totalLecturers = 0;
    $activeLecturers = 0;
    $newThisMonth = 0;
}

$currentPage = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Lecturers - School Management System</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #6B8E23;
            min-height: 100vh;
        }
        
        .container-fluid {
            padding: 20px;
        }
        
        .main-content {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            margin-top: 20px;
        }
        
        .page-header {
            background: linear-gradient(135deg, #6B8E23, #8FBC8F);
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
        }
        
        .btn-primary {
            background: #6B8E23;
            border-color: #6B8E23;
        }
        
        .btn-primary:hover {
            background: #556B2F;
            border-color: #556B2F;
        }
        
        .form-control:focus {
            border-color: #6B8E23;
            box-shadow: 0 0 0 0.2rem rgba(107, 142, 35, 0.25);
        }
        
        .lecturer-card {
            border: 1px solid #e0e0e0;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            background: white;
            transition: transform 0.3s ease;
        }
        
        .lecturer-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
        
        .lecturer-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(45deg, #6B8E23, #8FBC8F);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
            margin-right: 15px;
        }
        
        .department-badge {
            background: #e3f2fd;
            color: #1976d2;
            padding: 4px 8px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .status-active {
            color: #28a745;
            font-weight: bold;
        }
        
        .status-inactive {
            color: #dc3545;
            font-weight: bold;
        }

        .status-retired {
            color: #6c757d;
            font-weight: bold;
        }
        
        .action-buttons .btn {
            margin-right: 5px;
            padding: 5px 10px;
            font-size: 12px;
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
                    <a href="#"><i class="fas fa-graduation-cap"></i> UNIOSUN</a>
                    <a href="#"><i class="fas fa-globe"></i> E-Portals</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Header -->
    <div class="main-header" >
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

    <!-- Include your existing header here -->
    
    <div class="container-fluid">
        <div class="main-content">
            <!-- Page Header -->
            <div class="page-header">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h1><i class="fas fa-chalkboard-teacher"></i> Lecturer Management</h1>
                        <p class="mb-0">Manage faculty members and teaching staff</p>
                    </div>
                    <div>
                        <a href="admin_dashboard.php" class="btn btn-secondary me-2">
                            <i class="fas fa-arrow-left"></i> Back to Dashboard
                        </a>

                        <button class="btn btn-light" data-bs-toggle="modal" data-bs-target="#addLecturerModal">
                            <i class="fas fa-plus"></i> Add New Lecturer
                        </button>
                    </div>    
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card bg-primary text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h5 class="card-title">Total Lecturers</h5>
                                    <h3 class="mb-0"><?php echo $totalLecturers; ?></h3>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-users fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-success text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h5 class="card-title">Active Faculty</h5>
                                    <h3 class="mb-0"><?php echo $activeLecturers; ?></h3>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-user-check fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-info text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h5 class="card-title">Departments</h5>
                                    <h3 class="mb-0"><?php echo count($departments); ?></h3>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-building fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-warning text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h5 class="card-title">New This Month</h5>
                                    <h3 class="mb-0"><?php echo $newThisMonth; ?></h3>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-user-plus fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Search and Filter -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-search"></i></span>
                        <input type="text" class="form-control" id="searchLecturer" placeholder="Search lecturers by name, email, or staff ID...">
                    </div>
                </div>
                <div class="col-md-3">
                    <select class="form-select" id="filterDepartment">
                        <option value="">All Departments</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?php echo $dept['department_id']; ?>">
                                <?php echo htmlspecialchars($dept['department_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <select class="form-select" id="filterStatus">
                        <option value="">All Status</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                        <option value="retired">Retired</option>
                    </select>
                </div>
            </div>

            <!-- Lecturers List -->
            <div id="lecturersList">
                <!-- This will be populated by JavaScript -->
            </div>
        </div>
    </div>

    <!-- Add Lecturer Modal -->
    <div class="modal fade" id="addLecturerModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="fas fa-user-plus"></i> Add New Lecturer</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="addLecturerForm">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="firstName" class="form-label">First Name *</label>
                                    <input type="text" class="form-control" id="firstName" name="first_name" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="lastName" class="form-label">Last Name *</label>
                                    <input type="text" class="form-control" id="lastName" name="last_name" required>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email Address</label>
                                    <input type="email" class="form-control" id="email" name="email">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="phone" class="form-label">Phone Number</label>
                                    <input type="tel" class="form-control" id="phone" name="phone">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="department" class="form-label">Department</label>
                                    <select class="form-select" id="department" name="department_id">
                                        <option value="">Select Department</option>
                                        <?php foreach ($departments as $dept): ?>
                                            <option value="<?php echo $dept['department_id']; ?>">
                                                <?php echo htmlspecialchars($dept['department_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="title" class="form-label">Title</label>
                                    <select class="form-select" id="title" name="title">
                                        <option value="Mr.">Mr.</option>
                                        <option value="Mrs.">Mrs.</option>
                                        <option value="Miss">Miss</option>
                                        <option value="Dr.">Dr.</option>
                                        <option value="Prof.">Prof.</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="academicRank" class="form-label">Academic Rank</label>
                                    <select class="form-select" id="academicRank" name="academic_rank">
                                        <option value="Lecturer III">Lecturer III</option>
                                        <option value="Lecturer II">Lecturer II</option>
                                        <option value="Lecturer I">Lecturer I</option>
                                        <option value="Senior Lecturer">Senior Lecturer</option>
                                        <option value="Reader">Reader</option>
                                        <option value="Professor">Professor</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="status" class="form-label">Status</label>
                                    <select class="form-select" id="status" name="status">
                                        <option value="active">Active</option>
                                        <option value="inactive">Inactive</option>
                                        <option value="retired">Retired</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
    <label for="password">Password *</label>
    <input type="password" class="form-control" name="password" required>
    <small class="text-muted">Default password for lecturer login</small>
</div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="salary" class="form-label">Salary (₦)</label>
                                    <input type="number" class="form-control" id="salary" name="salary" step="0.01">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="dateEmployed" class="form-label">Date Employed</label>
                                    <input type="date" class="form-control" id="dateEmployed" name="date_employed">
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="saveLecturer()">
                        <i class="fas fa-save"></i> Save Lecturer
                    </button>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function saveLecturer() {
            const form = document.getElementById('addLecturerForm');
            const formData = new FormData(form);
            formData.append('action', 'add_lecturer');
            
            fetch('manage_lecturers.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Lecturer added successfully! Staff ID: ' + data.staff_id);
                    form.reset();
                    const modal = bootstrap.Modal.getInstance(document.getElementById('addLecturerModal'));
                    modal.hide();
                    loadLecturers();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while saving the lecturer.');
            });
        }

        function loadLecturers() {
            fetch('manage_lecturers.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=get_lecturers'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displayLecturers(data.data);
                } else {
                    console.error('Error loading lecturers:', data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
            });
        }

        function displayLecturers(lecturers) {
            const container = document.getElementById('lecturersList');
            
            if (lecturers.length === 0) {
                container.innerHTML = `
                    <div class="text-center py-5">
                        <i class="fas fa-user-plus fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">No Lecturers Found</h5>
                        <p class="text-muted">Start by adding your first lecturer to the system.</p>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addLecturerModal">
                            <i class="fas fa-plus"></i> Add First Lecturer
                        </button>
                    </div>
                `;
                return;
            }

            let html = '';
            lecturers.forEach(lecturer => {
                const statusClass = lecturer.status === 'active' ? 'status-active' : 
                                   lecturer.status === 'retired' ? 'status-retired' : 'status-inactive';
                
                html += `
                    <div class="lecturer-card">
                        <div class="d-flex align-items-center">
                            <div class="lecturer-avatar">
                                <i class="fas fa-user"></i>
                            </div>
                            <div class="flex-grow-1">
                                <h5 class="mb-1">${lecturer.title} ${lecturer.first_name} ${lecturer.last_name}</h5>
                                <p class="mb-1 text-muted">${lecturer.email || 'No email'} | Staff ID: ${lecturer.staff_id}</p>
                                <div class="d-flex align-items-center">
                                    <span class="department-badge me-2">${lecturer.department_name || 'No Department'}</span>
                                    <span class="${statusClass}"><i class="fas fa-circle"></i> ${lecturer.status}</span>
                                </div>
                            </div>
                            <div class="action-buttons">
                                <button class="btn btn-sm btn-outline-primary" onclick="viewLecturer(${lecturer.lecturer_id})">
                                    <i class="fas fa-eye"></i> View
                                </button>
                                <button class="btn btn-sm btn-outline-warning" onclick="editLecturer(${lecturer.lecturer_id})">
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                                <button class="btn btn-sm btn-outline-danger" onclick="deleteLecturer(${lecturer.lecturer_id})">
                                    <i class="fas fa-trash"></i> Delete
                                </button>
                            </div>
                        </div>
                        <div class="mt-2">
                            <small class="text-muted">
                                <i class="fas fa-phone"></i> ${lecturer.phone || 'No phone'} | 
                                <i class="fas fa-graduation-cap"></i> ${lecturer.academic_rank} | 
                                <i class="fas fa-calendar"></i> Employed: ${lecturer.date_employed || 'N/A'}
                            </small>
                        </div>
                    </div>
                `;
            });
            
            container.innerHTML = html;
        }

        function deleteLecturer(id) {
            if (confirm('Are you sure you want to delete this lecturer?')) {
                fetch('manage_lecturers.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=delete_lecturer&lecturer_id=${id}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Lecturer deleted successfully!');
                        loadLecturers();
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while deleting the lecturer.');
                });
            }
        }

        function viewLecturer(id) {
            alert('View lecturer details for ID: ' + id);
        }

        function editLecturer(id) {
            alert('Edit lecturer with ID: ' + id);
        }

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            loadLecturers();
        });
    </script>
</body>
</html>