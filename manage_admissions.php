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

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'import_from_studrec':
                    if (isset($_POST['student_ids']) && is_array($_POST['student_ids'])) {
                        $imported = 0;
                        foreach ($_POST['student_ids'] as $student_id) {
                            $studentStmt = $pdo->prepare("SELECT * FROM studrec WHERE ID = ?");
                            $studentStmt->execute([$student_id]);
                            $student = $studentStmt->fetch();
                            
                            if ($student) {
                                $appNumber = 'APP' . date('Y') . str_pad($student_id, 4, '0', STR_PAD_LEFT);
                                
                                $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM admissions WHERE student_id = ? OR application_number = ?");
                                $checkStmt->execute([$student_id, $appNumber]);
                                
                                if ($checkStmt->fetchColumn() == 0) {
                                    $insertStmt = $pdo->prepare("INSERT INTO admissions (application_number, first_name, last_name, email, phone, date_of_birth, gender, state_of_origin, lga, address, first_choice_department, admission_status, matric_number, student_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                                    
                                    $insertStmt->execute([
                                        $appNumber,
                                        $student['FIRST_NAME'],
                                        $student['LAST_NAME'],
                                        $student['EMAIL'],
                                        $student['TELEPHONE'] ?? 'N/A',
                                        $student['DOB'] ?? '2000-01-01',
                                        'Male',
                                        'N/A',
                                        'N/A',
                                        $student['ADDRESS'] ?? 'N/A',
                                        $student['department_id'] ?? 1,
                                        'approved',
                                        $student['matric_number'],
                                        $student_id
                                    ]);
                                    $imported++;
                                }
                            }
                        }
                        $success = "Successfully imported $imported student(s) as admission records!";
                    } else {
                        $error = "Please select at least one student to import.";
                    }
                    break;
                
                case 'add':
                    $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM admissions WHERE application_number = ?");
                    $checkStmt->execute([trim($_POST['application_number'])]);
                    if ($checkStmt->fetchColumn() > 0) {
                        $error = "Application number already exists!";
                        break;
                    }
                    
                    $stmt = $pdo->prepare("INSERT INTO admissions (application_number, first_name, last_name, middle_name, email, phone, date_of_birth, gender, state_of_origin, lga, address, first_choice_department, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                    $stmt->execute([
                        trim($_POST['application_number']),
                        trim($_POST['first_name']),
                        trim($_POST['last_name']),
                        trim($_POST['middle_name']),
                        trim($_POST['email']),
                        trim($_POST['phone']),
                        $_POST['date_of_birth'],
                        $_POST['gender'],
                        trim($_POST['state_of_origin']),
                        trim($_POST['lga']),
                        trim($_POST['address']),
                        $_POST['first_choice_department']
                    ]);
                    $success = "Application added successfully!";
                    break;
                
                case 'edit':
                    $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM admissions WHERE application_number = ? AND admission_id != ?");
                    $checkStmt->execute([trim($_POST['application_number']), $_POST['admission_id']]);
                    if ($checkStmt->fetchColumn() > 0) {
                        $error = "Application number already exists!";
                        break;
                    }
                    
                    $stmt = $pdo->prepare("UPDATE admissions SET application_number = ?, first_name = ?, last_name = ?, middle_name = ?, email = ?, phone = ?, date_of_birth = ?, gender = ?, state_of_origin = ?, lga = ?, address = ?, first_choice_department = ? WHERE admission_id = ?");
                    $stmt->execute([
                        trim($_POST['application_number']),
                        trim($_POST['first_name']),
                        trim($_POST['last_name']),
                        trim($_POST['middle_name']),
                        trim($_POST['email']),
                        trim($_POST['phone']),
                        $_POST['date_of_birth'],
                        $_POST['gender'],
                        trim($_POST['state_of_origin']),
                        trim($_POST['lga']),
                        trim($_POST['address']),
                        $_POST['first_choice_department'],
                        $_POST['admission_id']
                    ]);
                    $success = "Application updated successfully!";
                    break;
                
                case 'delete':
                    $stmt = $pdo->prepare("DELETE FROM admissions WHERE admission_id = ?");
                    $stmt->execute([$_POST['admission_id']]);
                    $success = "Application deleted successfully!";
                    break;
                    
                case 'update_status':
                    $stmt = $pdo->prepare("UPDATE admissions SET admission_status = ? WHERE admission_id = ?");
                    $stmt->execute([$_POST['status'], $_POST['admission_id']]);
                    $success = "Application status updated successfully!";
                    break;
            }
        }
    } catch (PDOException $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Handle search and filters
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$department_filter = $_GET['department'] ?? '';
$gender_filter = $_GET['gender'] ?? '';

$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(a.first_name LIKE ? OR a.last_name LIKE ? OR a.application_number LIKE ? OR a.email LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
}

if (!empty($status_filter)) {
    $where_conditions[] = "a.admission_status = ?";
    $params[] = $status_filter;
}

if (!empty($department_filter)) {
    $where_conditions[] = "a.first_choice_department = ?";
    $params[] = $department_filter;
}

if (!empty($gender_filter)) {
    $where_conditions[] = "a.gender = ?";
    $params[] = $gender_filter;
}

$where_sql = '';
if (!empty($where_conditions)) {
    $where_sql = "WHERE " . implode(" AND ", $where_conditions);
}

// Get all admissions with department information
try {
    $sql = "SELECT a.*, d.department_name, d.department_code 
            FROM admissions a 
            LEFT JOIN departments d ON a.first_choice_department = d.department_id 
            $where_sql 
            ORDER BY a.created_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $admissions = $stmt->fetchAll();
} catch (PDOException $e) {
    $admissions = [];
    $error = "Error loading admissions: " . $e->getMessage();
}

// Get students from studrec that are not yet in admissions
try {
    $studentsStmt = $pdo->query("SELECT s.*, d.department_name, d.department_code 
    FROM studrec s 
    LEFT JOIN departments d ON s.department_id = d.department_id 
    WHERE s.ID NOT IN (SELECT COALESCE(student_id, 0) FROM admissions WHERE student_id IS NOT NULL)
    AND s.role = 'student'
    ORDER BY s.FIRST_NAME, s.LAST_NAME");
    $availableStudents = $studentsStmt->fetchAll();
} catch (PDOException $e) {
    $availableStudents = [];
}

// Get departments for dropdown
try {
    $dept_stmt = $pdo->query("SELECT department_id, department_name, department_code FROM departments ORDER BY department_name");
    $departments = $dept_stmt->fetchAll();
} catch (PDOException $e) {
    $departments = [];
}

// Calculate statistics
$totalApplications = count($admissions);
$statusCounts = array_count_values(array_column($admissions, 'admission_status'));

// Nigerian states for dropdown
$nigerianStates = [
    'Abia', 'Adamawa', 'Akwa Ibom', 'Anambra', 'Bauchi', 'Bayelsa', 'Benue', 'Borno', 'Cross River', 'Delta',
    'Ebonyi', 'Edo', 'Ekiti', 'Enugu', 'FCT', 'Gombe', 'Imo', 'Jigawa', 'Kaduna', 'Kano', 'Katsina', 'Kebbi',
    'Kogi', 'Kwara', 'Lagos', 'Nasarawa', 'Niger', 'Ogun', 'Ondo', 'Osun', 'Oyo', 'Plateau', 'Rivers',
    'Sokoto', 'Taraba', 'Yobe', 'Zamfara'
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Admissions - UNIOSUN Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        body {
            background: linear-gradient(135deg, #F5F8F0, #E8F0E0);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
        }

        .main-content {
            max-width: 1400px;
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

        .btn-olive {
            background: #6B8E23;
            border-color: #6B8E23;
            color: white;
        }

        .btn-olive:hover {
            background: #5A7A1C;
            border-color: #5A7A1C;
            color: white;
        }

        .stat-card {
            background: linear-gradient(135deg, #ffffff, #f8fdf8);
            border-radius: 15px;
            padding: 25px;
            text-align: center;
            box-shadow: 0 8px 20px rgba(107, 142, 35, 0.1);
            border-left: 4px solid #6B8E23;
            transition: transform 0.3s ease;
            height: 100%;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-icon {
            font-size: 2.5rem;
            color: #6B8E23;
            margin-bottom: 15px;
        }

        .filters-card {
            background: linear-gradient(135deg, #ffffff, #f8fdf8);
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 8px 20px rgba(107, 142, 35, 0.1);
            border: 2px solid rgba(107, 142, 35, 0.1);
        }

        .admissions-table-card {
            background: linear-gradient(135deg, #ffffff, #f8fdf8);
            border-radius: 15px;
            box-shadow: 0 8px 20px rgba(107, 142, 35, 0.1);
            overflow: hidden;
            border: 2px solid rgba(107, 142, 35, 0.1);
        }

        .table-header {
            background: linear-gradient(135deg, #6B8E23, #5A7A1C);
            color: white;
            padding: 20px;
        }

        .table th {
            background: #6B8E23;
            color: white;
            border: none;
            font-weight: 600;
            vertical-align: middle;
            font-size: 0.9rem;
        }

        .table td {
            vertical-align: middle;
            border-color: rgba(107, 142, 35, 0.1);
            font-size: 0.9rem;
        }

        .application-number {
            background: #6B8E23;
            color: white;
            padding: 5px 10px;
            border-radius: 15px;
            font-weight: bold;
            font-size: 0.8em;
        }

        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8em;
            font-weight: bold;
            text-transform: uppercase;
        }

        .status-pending {
            background: #ffc107;
            color: #212529;
        }

        .status-approved {
            background: #28a745;
            color: white;
        }

        .status-rejected {
            background: #dc3545;
            color: white;
        }

        .status-under-review {
            background: #17a2b8;
            color: white;
        }

        .gender-badge {
            background: #17a2b8;
            color: white;
            padding: 4px 8px;
            border-radius: 10px;
            font-size: 0.7em;
        }

        .action-buttons {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
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
            backdrop-filter: blur(5px);
        }

        .modal-content {
            background: white;
            margin: 30px auto;
            padding: 0;
            border-radius: 15px;
            max-width: 800px;
            width: 95%;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-header {
            background: linear-gradient(135deg, #6B8E23, #5A7A1C);
            color: white;
            padding: 25px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 1001;
        }

        .modal-body {
            padding: 30px;
        }

        .close {
            background: none;
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
            padding: 5px;
            border-radius: 50%;
            width: 35px;
            height: 35px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.3s;
        }

        .close:hover {
            background: rgba(255,255,255,0.2);
        }

        .form-control:focus, .form-select:focus {
            border-color: #6B8E23;
            box-shadow: 0 0 0 0.2rem rgba(107, 142, 35, 0.25);
        }

        .alert {
            padding: 15px 20px;
            margin-bottom: 25px;
            border-radius: 10px;
            border: none;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-success {
            background: linear-gradient(135deg, #d4edda, #c3e6cb);
            color: #155724;
        }

        .alert-danger {
            background: linear-gradient(135deg, #f8d7da, #f5c6cb);
            color: #721c24;
        }

        .alert-info {
            background: linear-gradient(135deg, #d1ecf1, #bee5eb);
            color: #0c5460;
        }

        .no-admissions {
            text-align: center;
            padding: 60px 20px;
            color: #6c757d;
        }

        .student-card {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 10px;
            transition: all 0.3s ease;
        }

        .student-card:hover {
            border-color: #6B8E23;
            background: #f8fdf8;
        }

        .student-card.selected {
            border-color: #6B8E23;
            background: #f0f8e8;
        }

        @media (max-width: 768px) {
            .table-responsive {
                font-size: 0.8rem;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .modal-content {
                margin: 10px auto;
                width: 98%;
            }
        }
    </style>
</head>
<body>

<!-- Top Header -->
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

    <!-- Page Header -->
    <div class="page-header">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h1><i class="fas fa-user-graduate"></i> Manage Admissions</h1>
                <p class="mb-0 text-muted">Complete admission management system - track applications, update status, and manage student data</p>
                <a href="admin_dashboard.php" class="btn btn-danger" title="Back to Dashboard" style="margin-bottom:10px;">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>
            
            <div class="col-md-4 text-end">
                <button class="btn btn-info me-2" onclick="openModal('importModal')">
                    <i class="fas fa-download"></i> Import from Students
                </button>
               
            </div>
            
              <div class="col-md-4 text-end"> 
                <button class="btn btn-olive" onclick="openModal('addModal')">
                    <i class="fas fa-plus"></i> Add New Application
                </button>
            </div>
        </div>
    </div>

    <!-- Import Alert -->
    <?php if (!empty($availableStudents)): ?>
    <div class="alert alert-info">
        <i class="fas fa-info-circle"></i> 
        <strong>Import Available:</strong> You have <?php echo count($availableStudents); ?> student(s) in your registration table that haven't been imported as admission records yet. 
        <button class="btn btn-sm btn-info ms-2" onclick="openModal('importModal')">
            <i class="fas fa-download"></i> Import Now
        </button>
    </div>
    <?php endif; ?>

    <!-- Quick Stats -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="stat-card">
                <i class="fas fa-file-alt stat-icon"></i>
                <h3><?php echo $totalApplications; ?></h3>
                <p class="text-muted mb-0">Total Applications</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <i class="fas fa-check-circle stat-icon text-success"></i>
                <h3><?php echo $statusCounts['approved'] ?? 0; ?></h3>
                <p class="text-muted mb-0">Approved</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <i class="fas fa-clock stat-icon text-warning"></i>
                <h3><?php echo $statusCounts['pending'] ?? 0; ?></h3>
                <p class="text-muted mb-0">Pending</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <i class="fas fa-users stat-icon text-info"></i>
                <h3><?php echo count($availableStudents); ?></h3>
                <p class="text-muted mb-0">Ready to Import</p>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="filters-card">
        <h5 class="mb-3"><i class="fas fa-filter"></i> Search & Filter Applications</h5>
        <form method="GET" action="">
            <div class="row">
                <div class="col-md-3">
                    <label class="form-label">Search</label>
                    <input 
                        type="text" 
                        name="search" 
                        class="form-control" 
                        placeholder="Name, email, or app number..."
                        value="<?php echo htmlspecialchars($search); ?>"
                    >
                </div>
                <div class="col-md-2">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="">All Status</option>
                        <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="approved" <?php echo $status_filter === 'approved' ? 'selected' : ''; ?>>Approved</option>
                        <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                        <option value="under-review" <?php echo $status_filter === 'under-review' ? 'selected' : ''; ?>>Under Review</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Department</label>
                    <select name="department" class="form-select">
                        <option value="">All Departments</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?php echo $dept['department_id']; ?>" 
                                    <?php echo $department_filter == $dept['department_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($dept['department_code'] . ' - ' . $dept['department_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Gender</label>
                    <select name="gender" class="form-select">
                        <option value="">All Genders</option>
                        <option value="Male" <?php echo $gender_filter === 'Male' ? 'selected' : ''; ?>>Male</option>
                        <option value="Female" <?php echo $gender_filter === 'Female' ? 'selected' : ''; ?>>Female</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">&nbsp;</label>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-olive">
                            <i class="fas fa-search"></i> Search
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <!-- Admissions Table -->
    <div class="admissions-table-card">
        <div class="table-header">
            <h4><i class="fas fa-table"></i> Applications List</h4>
            <p class="mb-0">
                Showing <?php echo count($admissions); ?> application(s)
                <?php if (!empty($search) || !empty($status_filter) || !empty($department_filter) || !empty($gender_filter)): ?>
                    with current filters
                    <a href="<?php echo $_SERVER['PHP_SELF']; ?>" class="text-white text-decoration-underline ms-2">
                        <i class="fas fa-times"></i> Clear Filters
                    </a>
                <?php endif; ?>
            </p>
        </div>

        <div class="p-4">
            <?php if (empty($admissions)): ?>
                <div class="no-admissions">
                    <i class="fas fa-user-graduate fa-3x mb-3"></i>
                    <h5>No Applications Found</h5>
                    <?php if (!empty($availableStudents)): ?>
                        <p>You have <?php echo count($availableStudents); ?> students ready to import from your registration table.</p>
                        <button class="btn btn-info me-2" onclick="openModal('importModal')">
                            <i class="fas fa-download"></i> Import Students
                        </button>
                    <?php else: ?>
                        <p>No applications match your current search criteria.</p>
                    <?php endif; ?>
                    <button class="btn btn-olive" onclick="openModal('addModal')">
                        <i class="fas fa-plus"></i> Add New Application
                    </button>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>App Number</th>
                                <th>Full Name</th>
                                <th>Contact</th>
                                <th>Department</th>
                                <th>Status</th>
                                <th>Gender</th>
                                <th>Source</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($admissions as $admission): ?>
                                <tr>
                                    <td>
                                        <span class="application-number"><?php echo htmlspecialchars($admission['application_number']); ?></span>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($admission['first_name'] . ' ' . $admission['last_name']); ?></strong>
                                        <?php if (!empty($admission['middle_name'])): ?>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($admission['middle_name']); ?></small>
                                        <?php endif; ?>
                                        <?php if (!empty($admission['matric_number'])): ?>
                                            <br><small class="badge bg-secondary"><?php echo htmlspecialchars($admission['matric_number']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="small">
                                            <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($admission['email']); ?><br>
                                            <i class="fas fa-phone"></i> <?php echo htmlspecialchars($admission['phone']); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($admission['department_name']): ?>
                                            <span class="badge bg-primary">
                                                <?php echo htmlspecialchars($admission['department_code']); ?>
                                            </span>
                                            <br><small><?php echo htmlspecialchars($admission['department_name']); ?></small>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Not Selected</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?php echo str_replace(' ', '-', strtolower($admission['admission_status'] ?? 'pending')); ?>">
                                            <?php echo htmlspecialchars($admission['admission_status'] ?? 'Pending'); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="gender-badge"><?php echo htmlspecialchars($admission['gender']); ?></span>
                                    </td>
                                    <td>
                                        <?php if (!empty($admission['student_id'])): ?>
                                            <span class="badge bg-success" title="Imported from student records">
                                                <i class="fas fa-download"></i> Imported
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-warning" title="Added manually">
                                                <i class="fas fa-plus"></i> Manual
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <button class="btn btn-sm btn-outline-info" onclick="viewAdmission(<?php echo htmlspecialchars(json_encode($admission)); ?>)" title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-warning" onclick="editAdmission(<?php echo htmlspecialchars(json_encode($admission)); ?>)" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <div class="btn-group">
                                                <button type="button" class="btn btn-sm btn-outline-success dropdown-toggle" data-bs-toggle="dropdown" title="Update Status">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                                <ul class="dropdown-menu">
                                                    <li><a class="dropdown-item" href="#" onclick="updateStatus(<?php echo $admission['admission_id']; ?>, 'pending')">
                                                        <i class="fas fa-clock text-warning"></i> Pending</a></li>
                                                    <li><a class="dropdown-item" href="#" onclick="updateStatus(<?php echo $admission['admission_id']; ?>, 'under-review')">
                                                        <i class="fas fa-search text-info"></i> Under Review</a></li>
                                                    <li><a class="dropdown-item" href="#" onclick="updateStatus(<?php echo $admission['admission_id']; ?>, 'approved')">
                                                        <i class="fas fa-check-circle text-success"></i> Approved</a></li>
                                                    <li><a class="dropdown-item" href="#" onclick="updateStatus(<?php echo $admission['admission_id']; ?>, 'rejected')">
                                                        <i class="fas fa-times-circle text-danger"></i> Rejected</a></li>
                                                </ul>
                                            </div>
                                            <button class="btn btn-sm btn-outline-danger" onclick="deleteAdmission(<?php echo $admission['admission_id']; ?>, '<?php echo htmlspecialchars($admission['first_name'] . ' ' . $admission['last_name']); ?>')" title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Import Students Modal -->
<div id="importModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="fas fa-download"></i> Import Students from Registration</h2>
            <button type="button" class="close" onclick="closeModal('importModal')">&times;</button>
        </div>
        <div class="modal-body">
            <?php if (empty($availableStudents)): ?>
                <div class="text-center py-4">
                    <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                    <h5>All Students Imported</h5>
                    <p>All students from your registration table have already been imported as admission records.</p>
                </div>
            <?php else: ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i>
                    <strong>Available for Import:</strong> <?php echo count($availableStudents); ?> student(s) from your registration table can be imported as admission records.
                </div>
                
                <form method="POST" id="importForm">
                    <input type="hidden" name="action" value="import_from_studrec">
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="selectAll" onchange="toggleAllStudents()">
                            <label class="form-check-label fw-bold" for="selectAll">
                                Select All Students (<?php echo count($availableStudents); ?>)
                            </label>
                        </div>
                    </div>
                    
                    <div style="max-height: 400px; overflow-y: auto;">
                        <?php foreach ($availableStudents as $student): ?>
                            <div class="student-card">
                                <div class="form-check">
                                    <input class="form-check-input student-checkbox" type="checkbox" 
                                           name="student_ids[]" value="<?php echo $student['ID']; ?>" 
                                           id="student_<?php echo $student['ID']; ?>">
                                    <label class="form-check-label w-100" for="student_<?php echo $student['ID']; ?>">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <strong><?php echo htmlspecialchars($student['FIRST_NAME'] . ' ' . $student['LAST_NAME']); ?></strong>
                                                <br><small class="text-muted"><?php echo htmlspecialchars($student['EMAIL']); ?></small>
                                            </div>
                                            <div class="col-md-6">
                                                <span class="badge bg-primary">
                                                    <?php echo htmlspecialchars($student['department_code'] ?? 'N/A'); ?>
                                                </span>
                                                <?php if (!empty($student['matric_number'])): ?>
                                                    <br><small class="badge bg-secondary"><?php echo htmlspecialchars($student['matric_number']); ?></small>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </label>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="d-flex gap-2 justify-content-end mt-4">
                        <button type="button" class="btn btn-secondary" onclick="closeModal('importModal')">Cancel</button>
                        <button type="submit" class="btn btn-info">
                            <i class="fas fa-download"></i> Import Selected Students
                        </button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Add Admission Modal -->
<div id="addModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="fas fa-plus"></i> Add New Application</h2>
            <button type="button" class="close" onclick="closeModal('addModal')">&times;</button>
        </div>
        <div class="modal-body">
            <form method="POST" id="addForm">
                <input type="hidden" name="action" value="add">
                
                <div class="row">
                    <div class="col-md-12">
                        <div class="form-group mb-3">
                            <label class="form-label">Application Number *</label>
                            <input type="text" class="form-control" name="application_number" required placeholder="e.g., APP2025001" style="text-transform: uppercase;">
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group mb-3">
                            <label class="form-label">First Name *</label>
                            <input type="text" class="form-control" name="first_name" required>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group mb-3">
                            <label class="form-label">Last Name *</label>
                            <input type="text" class="form-control" name="last_name" required>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group mb-3">
                            <label class="form-label">Middle Name</label>
                            <input type="text" class="form-control" name="middle_name">
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label class="form-label">Email *</label>
                            <input type="email" class="form-control" name="email" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label class="form-label">Phone *</label>
                            <input type="tel" class="form-control" name="phone" required>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group mb-3">
                            <label class="form-label">Date of Birth *</label>
                            <input type="date" class="form-control" name="date_of_birth" required>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group mb-3">
                            <label class="form-label">Gender *</label>
                            <select class="form-select" name="gender" required>
                                <option value="">Select Gender</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group mb-3">
                            <label class="form-label">State of Origin *</label>
                            <select class="form-select" name="state_of_origin" required>
                                <option value="">Select State</option>
                                <?php foreach ($nigerianStates as $state): ?>
                                    <option value="<?php echo $state; ?>"><?php echo $state; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label class="form-label">LGA *</label>
                            <input type="text" class="form-control" name="lga" required placeholder="Local Government Area">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label class="form-label">First Choice Department *</label>
                            <select class="form-select" name="first_choice_department" required>
                                <option value="">Select Department</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?php echo $dept['department_id']; ?>">
                                        <?php echo htmlspecialchars($dept['department_code'] . ' - ' . $dept['department_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="form-group mb-3">
                    <label class="form-label">Address *</label>
                    <textarea class="form-control" name="address" rows="3" required placeholder="Full residential address"></textarea>
                </div>
                
                <div class="d-flex gap-2 justify-content-end">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('addModal')">Cancel</button>
                    <button type="submit" class="btn btn-olive">Add Application</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Admission Modal -->
<div id="editModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="fas fa-edit"></i> Edit Application</h2>
            <button type="button" class="close" onclick="closeModal('editModal')">&times;</button>
        </div>
        <div class="modal-body">
            <form method="POST" id="editForm">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="admission_id" id="edit_admission_id">
                
                <div class="row">
                    <div class="col-md-12">
                        <div class="form-group mb-3">
                            <label class="form-label">Application Number *</label>
                            <input type="text" class="form-control" name="application_number" id="edit_application_number" required style="text-transform: uppercase;">
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group mb-3">
                            <label class="form-label">First Name *</label>
                            <input type="text" class="form-control" name="first_name" id="edit_first_name" required>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group mb-3">
                            <label class="form-label">Last Name *</label>
                            <input type="text" class="form-control" name="last_name" id="edit_last_name" required>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group mb-3">
                            <label class="form-label">Middle Name</label>
                            <input type="text" class="form-control" name="middle_name" id="edit_middle_name">
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label class="form-label">Email *</label>
                            <input type="email" class="form-control" name="email" id="edit_email" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label class="form-label">Phone *</label>
                            <input type="tel" class="form-control" name="phone" id="edit_phone" required>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group mb-3">
                            <label class="form-label">Date of Birth *</label>
                            <input type="date" class="form-control" name="date_of_birth" id="edit_date_of_birth" required>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group mb-3">
                            <label class="form-label">Gender *</label>
                            <select class="form-select" name="gender" id="edit_gender" required>
                                <option value="">Select Gender</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group mb-3">
                            <label class="form-label">State of Origin *</label>
                            <select class="form-select" name="state_of_origin" id="edit_state_of_origin" required>
                                <option value="">Select State</option>
                                <?php foreach ($nigerianStates as $state): ?>
                                    <option value="<?php echo $state; ?>"><?php echo $state; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label class="form-label">LGA *</label>
                            <input type="text" class="form-control" name="lga" id="edit_lga" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label class="form-label">First Choice Department *</label>
                            <select class="form-select" name="first_choice_department" id="edit_first_choice_department" required>
                                <option value="">Select Department</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?php echo $dept['department_id']; ?>">
                                        <?php echo htmlspecialchars($dept['department_code'] . ' - ' . $dept['department_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="form-group mb-3">
                    <label class="form-label">Address *</label>
                    <textarea class="form-control" name="address" id="edit_address" rows="3" required></textarea>
                </div>
                
                <div class="d-flex gap-2 justify-content-end">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('editModal')">Cancel</button>
                    <button type="submit" class="btn btn-success">Update Application</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Admission Modal -->
<div id="viewModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="fas fa-eye"></i> Application Details</h2>
            <button type="button" class="close" onclick="closeModal('viewModal')">&times;</button>
        </div>
        <div class="modal-body">
            <div id="admissionDetails"></div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function openModal(modalId) {
        document.getElementById(modalId).style.display = 'block';
        document.body.style.overflow = 'hidden';
    }

    function closeModal(modalId) {
        document.getElementById(modalId).style.display = 'none';
        document.body.style.overflow = 'auto';
    }

    function toggleAllStudents() {
        const selectAll = document.getElementById('selectAll');
        const checkboxes = document.querySelectorAll('.student-checkbox');
        
        checkboxes.forEach(checkbox => {
            checkbox.checked = selectAll.checked;
            const card = checkbox.closest('.student-card');
            if (selectAll.checked) {
                card.classList.add('selected');
            } else {
                card.classList.remove('selected');
            }
        });
    }

    function editAdmission(admission) {
        document.getElementById('edit_admission_id').value = admission.admission_id;
        document.getElementById('edit_application_number').value = admission.application_number;
        document.getElementById('edit_first_name').value = admission.first_name;
        document.getElementById('edit_last_name').value = admission.last_name;
        document.getElementById('edit_middle_name').value = admission.middle_name || '';
        document.getElementById('edit_email').value = admission.email;
        document.getElementById('edit_phone').value = admission.phone;
        document.getElementById('edit_date_of_birth').value = admission.date_of_birth;
        document.getElementById('edit_gender').value = admission.gender;
        document.getElementById('edit_state_of_origin').value = admission.state_of_origin;
        document.getElementById('edit_lga').value = admission.lga;
        document.getElementById('edit_first_choice_department').value = admission.first_choice_department;
        document.getElementById('edit_address').value = admission.address;
        openModal('editModal');
    }

    function viewAdmission(admission) {
        const age = admission.date_of_birth ? Math.floor((new Date() - new Date(admission.date_of_birth)) / (365.25 * 24 * 60 * 60 * 1000)) : 'N/A';
        const source = admission.student_id ? 'Imported from Student Records' : 'Added Manually';
        const sourceIcon = admission.student_id ? 'fas fa-download text-success' : 'fas fa-plus text-warning';
        
        const detailsHtml = `
            <div class="row">
                <div class="col-md-6">
                    <h5><i class="fas fa-user"></i> Personal Information</h5>
                    <table class="table table-borderless">
                        <tr><td><strong>Application Number:</strong></td><td><span class="badge bg-primary">${admission.application_number}</span></td></tr>
                        <tr><td><strong>Full Name:</strong></td><td>${admission.first_name} ${admission.middle_name || ''} ${admission.last_name}</td></tr>
                        <tr><td><strong>Email:</strong></td><td>${admission.email}</td></tr>
                        <tr><td><strong>Phone:</strong></td><td>${admission.phone}</td></tr>
                        <tr><td><strong>Date of Birth:</strong></td><td>${admission.date_of_birth} (${age} years)</td></tr>
                        <tr><td><strong>Gender:</strong></td><td><span class="badge bg-info">${admission.gender}</span></td></tr>
                    </table>
                </div>
                <div class="col-md-6">
                    <h5><i class="fas fa-map-marker-alt"></i> Location & Academic</h5>
                    <table class="table table-borderless">
                        <tr><td><strong>State of Origin:</strong></td><td>${admission.state_of_origin}</td></tr>
                        <tr><td><strong>LGA:</strong></td><td>${admission.lga}</td></tr>
                        <tr><td><strong>Department:</strong></td><td>${admission.department_name ? admission.department_code + ' - ' + admission.department_name : 'Not Selected'}</td></tr>
                        <tr><td><strong>Status:</strong></td><td><span class="status-badge status-${(admission.admission_status || 'pending').toLowerCase().replace(' ', '-')}">${admission.admission_status || 'Pending'}</span></td></tr>
                        <tr><td><strong>Source:</strong></td><td><i class="${sourceIcon}"></i> ${source}</td></tr>
                        <tr><td><strong>Applied:</strong></td><td>${admission.created_at ? new Date(admission.created_at).toLocaleDateString() : 'N/A'}</td></tr>
                    </table>
                </div>
            </div>
            
            ${admission.matric_number ? `
            <div class="row mt-3">
                <div class="col-12">
                    <h5><i class="fas fa-id-card"></i> Student Information</h5>
                    <div class="alert alert-success">
                        <strong>Matric Number:</strong> ${admission.matric_number}
                        <br><small>This applicant has been converted to a student record.</small>
                    </div>
                </div>
            </div>
            ` : ''}
            
            <div class="row mt-3">
                <div class="col-12">
                    <h5><i class="fas fa-home"></i> Address</h5>
                    <div class="alert alert-light">
                        ${admission.address}
                    </div>
                </div>
            </div>
        `;
        
        document.getElementById('admissionDetails').innerHTML = detailsHtml;
        openModal('viewModal');
    }

    function deleteAdmission(id, name) {
        if (confirm(`Are you sure you want to delete the application for "${name}"?\n\nThis action cannot be undone.`)) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="admission_id" value="${id}">
            `;
            document.body.appendChild(form);
            form.submit();
        }
    }

    function updateStatus(id, status) {
        if (confirm(`Are you sure you want to update the status to "${status.replace('-', ' ')}"?`)) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="admission_id" value="${id}">
                <input type="hidden" name="status" value="${status}">
            `;
            document.body.appendChild(form);
            form.submit();
        }
    }

    window.onclick = function(event) {
        const modals = document.querySelectorAll('.modal');
        modals.forEach(modal => {
            if (event.target === modal) {
                closeModal(modal.id);
            }
        });
    }
</script>
</body>
</html>