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
                case 'add':
                    // Check if course code already exists
                    $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM courses WHERE course_code = ?");
                    $checkStmt->execute([strtoupper(trim($_POST['course_code']))]);
                    if ($checkStmt->fetchColumn() > 0) {
                        $error = "Course code already exists!";
                        break;
                    }
                    
                    $stmt = $pdo->prepare("INSERT INTO courses (course_name, course_code, department_id, credit_units, semester, level, description, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
                    $stmt->execute([
                        trim($_POST['course_name']),
                        strtoupper(trim($_POST['course_code'])),
                        $_POST['department_id'],
                        $_POST['credit_units'],
                        $_POST['semester'],
                        $_POST['level'],
                        trim($_POST['description'])
                    ]);
                    $success = "Course added successfully!";
                    break;
                
                case 'edit':
                    // Check if course code already exists for other courses
                    $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM courses WHERE course_code = ? AND course_id != ?");
                    $checkStmt->execute([strtoupper(trim($_POST['course_code'])), $_POST['course_id']]);
                    if ($checkStmt->fetchColumn() > 0) {
                        $error = "Course code already exists!";
                        break;
                    }
                    
                    $stmt = $pdo->prepare("UPDATE courses SET course_name = ?, course_code = ?, department_id = ?, credit_units = ?, semester = ?, level = ?, description = ? WHERE course_id = ?");
                    $stmt->execute([
                        trim($_POST['course_name']),
                        strtoupper(trim($_POST['course_code'])),
                        $_POST['department_id'],
                        $_POST['credit_units'],
                        $_POST['semester'],
                        $_POST['level'],
                        trim($_POST['description']),
                        $_POST['course_id']
                    ]);
                    $success = "Course updated successfully!";
                    break;
                
                case 'delete':
                    $stmt = $pdo->prepare("DELETE FROM courses WHERE course_id = ?");
                    $stmt->execute([$_POST['course_id']]);
                    $success = "Course deleted successfully!";
                    break;
            }
        }
    } catch (PDOException $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Handle search and filters
$search = $_GET['search'] ?? '';
$department_filter = $_GET['department'] ?? '';
$semester_filter = $_GET['semester'] ?? '';
$level_filter = $_GET['level'] ?? '';

// Build query with filters
$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(c.course_name LIKE ? OR c.course_code LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param]);
}

if (!empty($department_filter)) {
    $where_conditions[] = "c.department_id = ?";
    $params[] = $department_filter;
}

if (!empty($semester_filter)) {
    $where_conditions[] = "c.semester = ?";
    $params[] = $semester_filter;
}

if (!empty($level_filter)) {
    $where_conditions[] = "c.level = ?";
    $params[] = $level_filter;
}

$where_sql = '';
if (!empty($where_conditions)) {
    $where_sql = "WHERE " . implode(" AND ", $where_conditions);
}

// Get all courses with department information
try {
    $sql = "SELECT c.*, d.department_name, d.department_code 
            FROM courses c 
            LEFT JOIN departments d ON c.department_id = d.department_id 
            $where_sql 
            ORDER BY c.course_code";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $courses = $stmt->fetchAll();
} catch (PDOException $e) {
    $courses = [];
    $error = "Error loading courses: " . $e->getMessage();
}

// Get departments for dropdown
try {
    $dept_stmt = $pdo->query("SELECT department_id, department_name, department_code FROM departments ORDER BY department_name");
    $departments = $dept_stmt->fetchAll();
} catch (PDOException $e) {
    $departments = [];
}

// Calculate statistics
$totalCourses = count($courses);
$totalCredits = array_sum(array_column($courses, 'credit_units'));
$uniqueDepartments = count(array_unique(array_column($courses, 'department_id')));
$courseLevels = array_count_values(array_column($courses, 'level'));
$courseSemesters = array_count_values(array_column($courses, 'semester'));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Courses - UNIOSUN Admin</title>
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

        .filters-card {
            background: linear-gradient(135deg, #ffffff, #f8fdf8);
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 8px 20px rgba(107, 142, 35, 0.1);
            border: 2px solid rgba(107, 142, 35, 0.1);
        }

        .courses-table-card {
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

        .course-code {
            background: #6B8E23;
            color: white;
            padding: 5px 10px;
            border-radius: 15px;
            font-weight: bold;
            font-size: 0.9em;
        }

        .credit-badge {
            background: #17a2b8;
            color: white;
            padding: 4px 8px;
            border-radius: 10px;
            font-size: 0.8em;
        }

        .semester-badge {
            background: #28a745;
            color: white;
            padding: 4px 8px;
            border-radius: 10px;
            font-size: 0.8em;
        }

        .level-badge {
            background: #ffc107;
            color: #212529;
            padding: 4px 8px;
            border-radius: 10px;
            font-size: 0.8em;
            font-weight: bold;
        }

        .action-buttons {
            display: flex;
            gap: 5px;
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
            margin: 50px auto;
            padding: 0;
            border-radius: 15px;
            max-width: 600px;
            width: 90%;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }

        .modal-header {
            background: linear-gradient(135deg, #6B8E23, #5A7A1C);
            color: white;
            padding: 25px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
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

        .no-courses {
            text-align: center;
            padding: 60px 20px;
            color: #6c757d;
        }

        @media (max-width: 768px) {
            .stats-row .row {
                margin: 0 -5px;
            }
            
            .stats-row .col-md-3 {
                padding: 0 5px;
                margin-bottom: 10px;
            }
        }
    </style>
</head>
<body>

<!-- Top Header -->

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
                <h1><i class="fas fa-book"></i> Manage Courses</h1>
                <p class="mb-0 text-muted">Complete course management system - view, add, edit, and organize courses</p>
            </div>
            <div class="col-md-4 text-end">
              
                <button class="btn btn-olive" onclick="openModal('addModal')">
                    <i class="fas fa-plus"></i> Add New Course
                </button>
            </div>
            <div class="col-md-4 text-end">
              
                 <a href="admin_dashboard.php" class="btn btn-danger" title="Back to Dashboard" style="margin-top:10px;">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>
            
        </div>
    </div>

    <!-- Quick Stats -->
    <div class="stats-row">
        <div class="row">
            <div class="col-md-3">
                <div class="stat-card">
                    <i class="fas fa-book stat-icon"></i>
                    <h3><?php echo $totalCourses; ?></h3>
                    <p class="text-muted mb-0">Total Courses</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <i class="fas fa-credit-card stat-icon"></i>
                    <h3><?php echo $totalCredits; ?></h3>
                    <p class="text-muted mb-0">Total Credit Units</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <i class="fas fa-building stat-icon"></i>
                    <h3><?php echo $uniqueDepartments; ?></h3>
                    <p class="text-muted mb-0">Departments</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <i class="fas fa-layer-group stat-icon"></i>
                    <h3><?php echo count($courseLevels); ?></h3>
                    <p class="text-muted mb-0">Course Levels</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="filters-card">
        <h5 class="mb-3"><i class="fas fa-filter"></i> Search & Filter Courses</h5>
        <form method="GET" action="">
            <div class="row">
                <div class="col-md-3">
                    <label class="form-label">Search Courses</label>
                    <input 
                        type="text" 
                        name="search" 
                        class="form-control" 
                        placeholder="Course name or code..."
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
                <div class="col-md-2">
                    <label class="form-label">Semester</label>
                    <select name="semester" class="form-select">
                        <option value="">All Semesters</option>
                        <option value="1st" <?php echo $semester_filter === '1st' ? 'selected' : ''; ?>>1st Semester</option>
                        <option value="2nd" <?php echo $semester_filter === '2nd' ? 'selected' : ''; ?>>2nd Semester</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Level</label>
                    <select name="level" class="form-select">
                        <option value="">All Levels</option>
                        <option value="100" <?php echo $level_filter === '100' ? 'selected' : ''; ?>>100 Level</option>
                        <option value="200" <?php echo $level_filter === '200' ? 'selected' : ''; ?>>200 Level</option>
                        <option value="300" <?php echo $level_filter === '300' ? 'selected' : ''; ?>>300 Level</option>
                        <option value="400" <?php echo $level_filter === '400' ? 'selected' : ''; ?>>400 Level</option>
                        <option value="500" <?php echo $level_filter === '500' ? 'selected' : ''; ?>>500 Level</option>
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

    <!-- Courses Table -->
    <div class="courses-table-card">
        <div class="table-header">
            <h4><i class="fas fa-table"></i> Courses List</h4>
            <p class="mb-0">
                Showing <?php echo count($courses); ?> course(s)
                <?php if (!empty($search) || !empty($department_filter) || !empty($semester_filter) || !empty($level_filter)): ?>
                    with current filters
                    <a href="<?php echo $_SERVER['PHP_SELF']; ?>" class="text-white text-decoration-underline ms-2">
                        <i class="fas fa-times"></i> Clear Filters
                    </a>
                <?php endif; ?>
            </p>
        </div>

        <div class="p-4">
            <?php if (empty($courses)): ?>
                <div class="no-courses">
                    <i class="fas fa-book-open fa-3x mb-3"></i>
                    <h5>No Courses Found</h5>
                    <p>No courses match your current search criteria.</p>
                    <?php if (!empty($search) || !empty($department_filter) || !empty($semester_filter) || !empty($level_filter)): ?>
                        <a href="<?php echo $_SERVER['PHP_SELF']; ?>" class="btn btn-olive">
                            <i class="fas fa-times"></i> Clear Filters
                        </a>
                    <?php else: ?>
                        <button class="btn btn-olive" onclick="openModal('addModal')">
                            <i class="fas fa-plus"></i> Add First Course
                        </button>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Course Code</th>
                                <th>Course Name</th>
                                <th>Department</th>
                                <th>Credits</th>
                                <th>Semester</th>
                                <th>Level</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($courses as $course): ?>
                                <tr>
                                    <td>
                                        <span class="course-code"><?php echo htmlspecialchars($course['course_code']); ?></span>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($course['course_name']); ?></strong>
                                        <?php if (!empty($course['description'])): ?>
                                            <br><small class="text-muted"><?php echo htmlspecialchars(substr($course['description'], 0, 50)) . (strlen($course['description']) > 50 ? '...' : ''); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($course['department_name']): ?>
                                            <span class="badge bg-primary">
                                                <?php echo htmlspecialchars($course['department_code']); ?>
                                            </span>
                                            <br><small><?php echo htmlspecialchars($course['department_name']); ?></small>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Not Assigned</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="credit-badge"><?php echo $course['credit_units']; ?> Units</span>
                                    </td>
                                    <td>
                                        <span class="semester-badge"><?php echo htmlspecialchars($course['semester']); ?></span>
                                    </td>
                                    <td>
                                        <span class="level-badge"><?php echo $course['level']; ?> Level</span>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <button class="btn btn-sm btn-outline-primary" onclick="editCourse(<?php echo htmlspecialchars(json_encode($course)); ?>)" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-info" onclick="viewCourse(<?php echo htmlspecialchars(json_encode($course)); ?>)" title="View">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-danger" onclick="deleteCourse(<?php echo $course['course_id']; ?>, '<?php echo htmlspecialchars($course['course_name']); ?>')" title="Delete">
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

<!-- Add Course Modal -->
<div id="addModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="fas fa-plus"></i> Add New Course</h2>
            <button type="button" class="close" onclick="closeModal('addModal')">&times;</button>
        </div>
        <div class="modal-body">
            <form method="POST" id="addForm">
                <input type="hidden" name="action" value="add">
                
                <div class="row">
                    <div class="col-md-8">
                        <div class="form-group mb-3">
                            <label class="form-label">Course Name *</label>
                            <input type="text" class="form-control" name="course_name" required placeholder="e.g., Introduction to Computer Science">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group mb-3">
                            <label class="form-label">Course Code *</label>
                            <input type="text" class="form-control" name="course_code" required placeholder="e.g., CSC101" style="text-transform: uppercase;">
                        </div>
                    </div>
                </div>
                
                <div class="form-group mb-3">
                    <label class="form-label">Description</label>
                    <textarea class="form-control" name="description" rows="3" placeholder="Brief description of the course..."></textarea>
                </div>
                
                <div class="d-flex gap-2 justify-content-end">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('addModal')">Cancel</button>
                    <button type="submit" class="btn btn-olive">Add Course</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Course Modal -->
<div id="editModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="fas fa-edit"></i> Edit Course</h2>
            <button type="button" class="close" onclick="closeModal('editModal')">&times;</button>
        </div>
        <div class="modal-body">
            <form method="POST" id="editForm">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="course_id" id="edit_course_id">
                
                <div class="row">
                    <div class="col-md-8">
                        <div class="form-group mb-3">
                            <label class="form-label">Course Name *</label>
                            <input type="text" class="form-control" name="course_name" id="edit_course_name" required>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group mb-3">
                            <label class="form-label">Course Code *</label>
                            <input type="text" class="form-control" name="course_code" id="edit_course_code" required style="text-transform: uppercase;">
                        </div>
                    </div>
                </div>
                
                <div class="form-group mb-3">
                    <label class="form-label">Department *</label>
                    <select class="form-select" name="department_id" id="edit_department_id" required>
                        <option value="">Select Department</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?php echo $dept['department_id']; ?>">
                                <?php echo htmlspecialchars($dept['department_code'] . ' - ' . $dept['department_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group mb-3">
                            <label class="form-label">Credit Units *</label>
                            <select class="form-select" name="credit_units" id="edit_credit_units" required>
                                <option value="">Select Credits</option>
                                <option value="1">1 Unit</option>
                                <option value="2">2 Units</option>
                                <option value="3">3 Units</option>
                                <option value="4">4 Units</option>
                                <option value="5">5 Units</option>
                                <option value="6">6 Units</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group mb-3">
                            <label class="form-label">Semester *</label>
                            <select class="form-select" name="semester" id="edit_semester" required>
                                <option value="">Select Semester</option>
                                <option value="1st">1st Semester</option>
                                <option value="2nd">2nd Semester</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group mb-3">
                            <label class="form-label">Level *</label>
                            <select class="form-select" name="level" id="edit_level" required>
                                <option value="">Select Level</option>
                                <option value="100">100 Level</option>
                                <option value="200">200 Level</option>
                                <option value="300">300 Level</option>
                                <option value="400">400 Level</option>
                                <option value="500">500 Level</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="form-group mb-3">
                    <label class="form-label">Description</label>
                    <textarea class="form-control" name="description" id="edit_description" rows="3"></textarea>
                </div>
                
                <div class="d-flex gap-2 justify-content-end">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('editModal')">Cancel</button>
                    <button type="submit" class="btn btn-success">Update Course</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Course Modal -->
<div id="viewModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="fas fa-eye"></i> Course Details</h2>
            <button type="button" class="close" onclick="closeModal('viewModal')">&times;</button>
        </div>
        <div class="modal-body">
            <div id="courseDetails">
                <!-- Content will be loaded here -->
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Modal Functions
    function openModal(modalId) {
        document.getElementById(modalId).style.display = 'block';
        document.body.style.overflow = 'hidden';
    }

    function closeModal(modalId) {
        document.getElementById(modalId).style.display = 'none';
        document.body.style.overflow = 'auto';
    }

    // Edit Course Function
    function editCourse(course) {
        document.getElementById('edit_course_id').value = course.course_id;
        document.getElementById('edit_course_name').value = course.course_name;
        document.getElementById('edit_course_code').value = course.course_code;
        document.getElementById('edit_department_id').value = course.department_id;
        document.getElementById('edit_credit_units').value = course.credit_units;
        document.getElementById('edit_semester').value = course.semester;
        document.getElementById('edit_level').value = course.level;
        document.getElementById('edit_description').value = course.description || '';
        openModal('editModal');
    }

    // View Course Function
    function viewCourse(course) {
        const detailsHtml = `
            <div class="row">
                <div class="col-md-6">
                    <h5><i class="fas fa-info-circle"></i> Course Information</h5>
                    <table class="table table-borderless">
                        <tr><td><strong>Course Name:</strong></td><td>${course.course_name}</td></tr>
                        <tr><td><strong>Course Code:</strong></td><td><span class="badge bg-primary">${course.course_code}</span></td></tr>
                        <tr><td><strong>Department:</strong></td><td>${course.department_name ? course.department_code + ' - ' + course.department_name : 'Not Assigned'}</td></tr>
                    </table>
                </div>
                <div class="col-md-6">
                    <h5><i class="fas fa-graduation-cap"></i> Academic Details</h5>
                    <table class="table table-borderless">
                        <tr><td><strong>Credit Units:</strong></td><td><span class="badge bg-info">${course.credit_units} Units</span></td></tr>
                        <tr><td><strong>Semester:</strong></td><td><span class="badge bg-success">${course.semester}</span></td></tr>
                        <tr><td><strong>Level:</strong></td><td><span class="badge bg-warning text-dark">${course.level} Level</span></td></tr>
                    </table>
                </div>
            </div>
            
            ${course.description ? `
            <div class="row mt-3">
                <div class="col-12">
                    <h5><i class="fas fa-align-left"></i> Description</h5>
                    <div class="alert alert-light">
                        ${course.description}
                    </div>
                </div>
            </div>
            ` : ''}
            
            <div class="row mt-3">
                <div class="col-12">
                    <h5><i class="fas fa-calendar"></i> Course Timeline</h5>
                    <div class="alert alert-info">
                        <strong>Created:</strong> ${course.created_at ? new Date(course.created_at).toLocaleDateString() : 'N/A'}
                    </div>
                </div>
            </div>
        `;
        
        document.getElementById('courseDetails').innerHTML = detailsHtml;
        openModal('viewModal');
    }

    // Delete Course Function
    function deleteCourse(id, name) {
        if (confirm(`Are you sure you want to delete "${name}"?\n\nThis action cannot be undone.`)) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="course_id" value="${id}">
            `;
            document.body.appendChild(form);
            form.submit();
        }
    }

    // Auto-submit search form on typing (with delay)
    let searchTimeout;
    document.querySelector('input[name="search"]')?.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            this.form.submit();
        }, 1000);
    });

    // Close modal when clicking outside
    window.onclick = function(event) {
        const modals = document.querySelectorAll('.modal');
        modals.forEach(modal => {
            if (event.target === modal) {
                closeModal(modal.id);
            }
        });
    }

    // Auto-uppercase course codes
    document.querySelectorAll('input[name="course_code"]').forEach(input => {
        input.addEventListener('input', function() {
            this.value = this.value.toUpperCase();
        });
    });

    // Form validation
    document.getElementById('addForm').addEventListener('submit', function(e) {
        const code = this.querySelector('input[name="course_code"]').value;
        if (code.length < 3) {
            e.preventDefault();
            alert('Course code must be at least 3 characters long.');
            return false;
        }
    });

    document.getElementById('editForm').addEventListener('submit', function(e) {
        const code = this.querySelector('input[name="course_code"]').value;
        if (code.length < 3) {
            e.preventDefault();
            alert('Course code must be at least 3 characters long.');
            return false;
        }
    });

    // Auto-dismiss alerts after 5 seconds
    setTimeout(function() {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            if (alert.classList.contains('alert-success') || alert.classList.contains('alert-danger')) {
                alert.style.transition = 'opacity 0.5s';
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 500);
            }
        });
    }, 5000);

    // Enhanced search functionality
    function performAdvancedSearch() {
        const searchTerm = document.querySelector('input[name="search"]').value.toLowerCase();
        const rows = document.querySelectorAll('tbody tr');
        
        if (!searchTerm) {
            rows.forEach(row => row.style.display = '');
            return;
        }
        
        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            if (text.includes(searchTerm)) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    }

    // Add keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        // Ctrl+N to add new course
        if (e.ctrlKey && e.key === 'n') {
            e.preventDefault();
            openModal('addModal');
        }
        
        // Escape to close any open modal
        if (e.key === 'Escape') {
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                if (modal.style.display === 'block') {
                    closeModal(modal.id);
                }
            });
        }
    });

    // Add tooltips for better UX
    document.addEventListener('DOMContentLoaded', function() {
        // Add title attributes for accessibility
        document.querySelectorAll('.btn').forEach(btn => {
            if (!btn.hasAttribute('title') && btn.querySelector('i')) {
                const icon = btn.querySelector('i').className;
                if (icon.includes('fa-edit')) btn.title = 'Edit Course';
                if (icon.includes('fa-eye')) btn.title = 'View Details';
                if (icon.includes('fa-trash')) btn.title = 'Delete Course';
                if (icon.includes('fa-plus')) btn.title = 'Add New Course';
            }
        });
    });
</script>
</body>
</html>
