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

// Handle search and filters
$search = $_GET['search'] ?? '';
$department_filter = $_GET['department'] ?? '';
$status_filter = $_GET['status'] ?? '';

// Build query with filters - FIXED: Proper JOIN with departments table
$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(s.FIRST_NAME LIKE ? OR s.LAST_NAME LIKE ? OR s.EMAIL LIKE ? OR s.MATRIC_NUMBER LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
}

if (!empty($department_filter)) {
    // FIXED: Filter by department_id instead of department name
    $where_conditions[] = "s.department_id = ?";
    $params[] = $department_filter;
}

if (!empty($status_filter)) {
    $where_conditions[] = "s.STATUS = ?";
    $params[] = $status_filter;
}

$where_sql = '';
if (!empty($where_conditions)) {
    $where_sql = "WHERE " . implode(" AND ", $where_conditions);
}

// Get all students with department information - FIXED: Proper JOIN
try {
    $sql = "SELECT s.*, d.department_name, d.department_code 
            FROM studrec s 
            LEFT JOIN departments d ON s.department_id = d.department_id 
            $where_sql 
            ORDER BY s.FIRST_NAME, s.LAST_NAME";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $students = $stmt->fetchAll();
} catch (PDOException $e) {
    $students = [];
    $error = "Error loading students: " . $e->getMessage();
}

// Get unique departments for filter
try {
    $dept_stmt = $pdo->query("SELECT department_id, department_name, department_code FROM departments ORDER BY department_name");
    $departments = $dept_stmt->fetchAll();
} catch (PDOException $e) {
    $departments = [];
}

// Delete student functionality
if (isset($_POST['delete_student']) && isset($_POST['student_id'])) {
    try {
        $delete_stmt = $pdo->prepare("DELETE FROM studrec WHERE ID = ?");
        $delete_stmt->execute([$_POST['student_id']]);
        $success_message = "Student deleted successfully!";
        // Refresh the page to update the list
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    } catch (PDOException $e) {
        $error_message = "Error deleting student: " . $e->getMessage();
    }
}

// Add Department functionality - NEW
if (isset($_POST['add_department'])) {
    try {
        $stmt = $pdo->prepare("INSERT INTO departments (department_name, department_code) VALUES (?, ?)");
        $stmt->execute([$_POST['dept_name'], $_POST['dept_code']]);
        $success_message = "Department added successfully!";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    } catch (PDOException $e) {
        $error_message = "Error adding department: " . $e->getMessage();
    }
}

// Delete Department functionality - NEW
if (isset($_POST['delete_department'])) {
    try {
        $stmt = $pdo->prepare("DELETE FROM departments WHERE department_id = ?");
        $stmt->execute([$_POST['delete_dept_id']]);
        $success_message = "Department deleted successfully!";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    } catch (PDOException $e) {
        $error_message = "Error deleting department: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Students - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
      <link rel="stylesheet" href="style.css">
    <style>
        body {
            background: linear-gradient(135deg, #F5F8F0, #E8F0E0);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
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

        .filters-card {
            background: linear-gradient(135deg, #ffffff, #f8fdf8);
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 8px 20px rgba(107, 142, 35, 0.1);
            border: 2px solid rgba(107, 142, 35, 0.1);
        }

        .students-table-card {
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
        }

        .table td {
            vertical-align: middle;
            border-color: rgba(107, 142, 35, 0.1);
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

        .student-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #6B8E23;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-right: 10px;
        }

        .status-badge {
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: 600;
        }

        .status-active { background: #d4edda; color: #155724; }
        .status-inactive { background: #f8d7da; color: #721c24; }
        .status-suspended { background: #fff3cd; color: #856404; }

        .action-buttons {
            display: flex;
            gap: 5px;
        }

        .stats-row {
            margin-bottom: 30px;
        }

        .stat-card {
            background: linear-gradient(135deg, #ffffff, #f8fdf8);
            border-radius: 15px;
            padding: 25px;
            text-align: center;
            box-shadow: 0 8px 20px rgba(107, 142, 35, 0.1);
            border-left: 4px solid #6B8E23;
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-icon {
            font-size: 2.5rem;
            color: #6B8E23;
            margin-bottom: 15px;
        }

        .no-students {
            text-align: center;
            padding: 60px 20px;
            color: #6c757d;
        }

        .form-control:focus {
            border-color: #6B8E23;
            box-shadow: 0 0 0 0.2rem rgba(107, 142, 35, 0.25);
        }

        .form-select:focus {
            border-color: #6B8E23;
            box-shadow: 0 0 0 0.2rem rgba(107, 142, 35, 0.25);
        }

        .alert {
            margin-bottom: 20px;
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
            <div class="col-md-6">
                <h1><i class="fas fa-users-cog"></i> Manage Students</h1>
                <p class="mb-0 text-muted">Complete student management system - view, add, edit, and organize students</p>
            </div>
            <div class="col-md-6 text-end">
                <button class="btn btn-dark" data-bs-toggle="modal" data-bs-target="#departmentModal">
                    <i class="fas fa-building"></i> Manage Departments
                </button>
                <a href="add_student.php" class="btn btn-olive">
                    <i class="fas fa-plus"></i> Add New Student
                </a>
                <div class="col-md-6 text-end" style="padding-top:10px;">
                     <a href="admin_dashboard.php" class="btn btn-danger">
                        <i class="fas fa-sign-out-alt"></i> Back
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Stats -->
    <div class="row stats-row">
        <div class="col-md-3">
            <div class="stat-card">
                <i class="fas fa-users stat-icon"></i>
                <h3><?php echo count($students); ?></h3>
                <p class="text-muted mb-0">Total Students</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <i class="fas fa-user-check stat-icon"></i>
                <h3><?php echo count(array_filter($students, fn($s) => ($s['STATUS'] ?? 'active') === 'active')); ?></h3>
                <p class="text-muted mb-0">Active Students</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <i class="fas fa-building stat-icon"></i>
                <h3><?php echo count($departments); ?></h3>
                <p class="text-muted mb-0">Active Departments</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <i class="fas fa-user-graduate stat-icon"></i>
                <h3><?php echo date('Y'); ?></h3>
                <p class="text-muted mb-0">Current Year</p>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="filters-card">
        <h5 class="mb-3"><i class="fas fa-filter"></i> Search & Filter Students</h5>
        <form method="GET" action="">
            <div class="row">
                <div class="col-md-4">
                    <label class="form-label">Search Students</label>
                    <input 
                        type="text" 
                        name="search" 
                        class="form-control" 
                        placeholder="Name, email, matric number..."
                        value="<?php echo htmlspecialchars($search); ?>"
                    >
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
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="">All Status</option>
                        <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        <option value="suspended" <?php echo $status_filter === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
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

    <!-- Students Table -->
    <div class="students-table-card">
        <div class="table-header">
            <h4><i class="fas fa-table"></i> Students List</h4>
            <p class="mb-0">
                Showing <?php echo count($students); ?> student(s)
                <?php if (!empty($search) || !empty($department_filter) || !empty($status_filter)): ?>
                    with current filters
                    <a href="<?php echo $_SERVER['PHP_SELF']; ?>" class="text-white text-decoration-underline ms-2">
                        <i class="fas fa-times"></i> Clear Filters
                    </a>
                <?php endif; ?>
            </p>
        </div>

        <div class="p-4">
            <?php if (empty($students)): ?>
                <div class="no-students">
                    <i class="fas fa-user-slash fa-3x mb-3"></i>
                    <h5>No Students Found</h5>
                    <p>No students match your current search criteria.</p>
                    <?php if (!empty($search) || !empty($department_filter) || !empty($status_filter)): ?>
                        <a href="<?php echo $_SERVER['PHP_SELF']; ?>" class="btn btn-olive">
                            <i class="fas fa-times"></i> Clear Filters
                        </a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Matric Number</th>
                                <th>Email</th>
                                <th>Department</th>
                                <th>Status</th>
                                <th>Joined</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($students as $student): ?>
                                <?php 
                                $matric = $student['MATRIC_NUMBER'] ?? 'UNIOSUN/' . date('Y') . '/' . str_pad($student['ID'], 4, '0', STR_PAD_LEFT);
                                $status = $student['STATUS'] ?? 'active';
                                $initials = strtoupper(substr($student['FIRST_NAME'], 0, 1) . substr($student['LAST_NAME'], 0, 1));
                                // FIXED: Now using department_name from JOIN
                                $department_display = $student['department_name'] ? 
                                    $student['department_code'] . ' - ' . $student['department_name'] : 
                                    'Not Assigned';
                                ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="student-avatar">
                                                <?php echo $initials; ?>
                                            </div>
                                            <div>
                                                <strong><?php echo htmlspecialchars($student['FIRST_NAME'] . ' ' . $student['LAST_NAME']); ?></strong>
                                                <br>
                                                <small class="text-muted">ID: <?php echo $student['ID']; ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td><code><?php echo htmlspecialchars($matric); ?></code></td>
                                    <td><?php echo htmlspecialchars($student['EMAIL']); ?></td>
                                    <td>
                                        <?php if ($student['department_name']): ?>
                                            <span class="badge bg-primary">
                                                <?php echo htmlspecialchars($department_display); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Not Assigned</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?php echo $status; ?>">
                                            <?php echo ucfirst($status); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M j, Y', strtotime($student['CREATED_AT'] ?? 'now')); ?></td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="edit_student.php?id=<?php echo $student['ID']; ?>" 
                                               class="btn btn-sm btn-outline-primary" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="view_student.php?id=<?php echo $student['ID']; ?>" 
                                               class="btn btn-sm btn-outline-info" title="View">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <form method="POST" style="display: inline;" 
                                                  onsubmit="return confirm('Are you sure you want to delete this student?');">
                                                <input type="hidden" name="student_id" value="<?php echo $student['ID']; ?>">
                                                <button type="submit" name="delete_student" 
                                                        class="btn btn-sm btn-outline-danger" title="Delete">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
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

<!-- Department Management Modal -->
<div class="modal fade" id="departmentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #6B8E23, #5A7A1C); color: white;">
                <h5 class="modal-title"><i class="fas fa-building"></i> Department Management</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <!-- Add New Department -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h6><i class="fas fa-plus"></i> Add New Department</h6>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <div class="row">
                                <div class="col-md-6">
                                    <input type="text" name="dept_name" class="form-control" placeholder="Department Name" required>
                                </div>
                                <div class="col-md-4">
                                    <input type="text" name="dept_code" class="form-control" placeholder="Code (e.g., CSC)" required>
                                </div>
                                <div class="col-md-2">
                                    <button type="submit" name="add_department" class="btn btn-olive w-100">
                                        <i class="fas fa-plus"></i> Add
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Current Departments -->
                <div class="card">
                    <div class="card-header">
                        <h6><i class="fas fa-list"></i> Current Departments</h6>
                    </div>
                    <div class="card-body">
                        <?php if (empty($departments)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-building fa-3x text-muted mb-3"></i>
                                <h6 class="text-muted">No Departments Yet</h6>
                                <p class="text-muted">Add departments to organize your students better!</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Code</th>
                                            <th>Department Name</th>
                                            <th>Students</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($departments as $dept): ?>
                                            <?php
                                            $student_count = count(array_filter($students, fn($s) => $s['department_id'] == $dept['department_id']));
                                            ?>
                                            <tr>
                                                <td><strong><?php echo htmlspecialchars($dept['department_code']); ?></strong></td>
                                                <td><?php echo htmlspecialchars($dept['department_name']); ?></td>
                                                <td>
                                                    <span class="badge bg-secondary"><?php echo $student_count; ?> students</span>
                                                </td>
                                                <td>
                                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this department? Students will be unassigned.');">
                                                        <input type="hidden" name="delete_dept_id" value="<?php echo $dept['department_id']; ?>">
                                                        <button type="submit" name="delete_department" class="btn btn-sm btn-outline-danger">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </form>
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
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Auto-submit search form on typing (with delay)
let searchTimeout;
document.querySelector('input[name="search"]')?.addEventListener('input', function() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        this.form.submit();
    }, 1000);
});

// Quick search in top header
document.querySelector('.search-box')?.addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        const searchInput = document.querySelector('input[name="search"]');
        if (searchInput) {
            searchInput.value = this.value;
            searchInput.form.submit();
        }
    }
});
</script>
</body>
</html>