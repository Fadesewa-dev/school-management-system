<?php
session_start();

// Temporarily disable session check to test the page
// Comment out the session check for now
/*
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}
*/

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

// First, let's create the results table if it doesn't exist
try {
    $createResultsTable = "
    CREATE TABLE IF NOT EXISTS results (
        result_id INT PRIMARY KEY AUTO_INCREMENT,
        student_id INT,
        course_id INT,
        lecturer_id INT,
        session_id INT,
        semester ENUM('First', 'Second') DEFAULT 'First',
        test_score DECIMAL(5,2) DEFAULT 0,
        exam_score DECIMAL(5,2) DEFAULT 0,
        total_score DECIMAL(5,2) DEFAULT 0,
        grade VARCHAR(2),
        status ENUM('draft', 'submitted', 'approved') DEFAULT 'draft',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        
        INDEX idx_student (student_id),
        INDEX idx_course (course_id),
        INDEX idx_lecturer (lecturer_id),
        INDEX idx_session (session_id),
        UNIQUE KEY unique_result (student_id, course_id, session_id, semester)
    )";
    $pdo->exec($createResultsTable);
} catch(PDOException $e) {
    // Table might already exist or there's an issue
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'add_result':
                    // Calculate total score and grade
                    $test_score = floatval($_POST['test_score']);
                    $exam_score = floatval($_POST['exam_score']);
                    $total_score = $test_score + $exam_score;
                    
                    // Calculate grade based on total score
                    $grade = 'F';
                    if ($total_score >= 70) $grade = 'A';
                    elseif ($total_score >= 60) $grade = 'B';
                    elseif ($total_score >= 50) $grade = 'C';
                    elseif ($total_score >= 45) $grade = 'D';
                    elseif ($total_score >= 40) $grade = 'E';
                    
                    $stmt = $pdo->prepare("
                        INSERT INTO results (student_id, course_id, lecturer_id, session_id, semester, test_score, exam_score, total_score, grade, status) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                        ON DUPLICATE KEY UPDATE
                        test_score = VALUES(test_score),
                        exam_score = VALUES(exam_score),
                        total_score = VALUES(total_score),
                        grade = VALUES(grade),
                        status = VALUES(status),
                        updated_at = CURRENT_TIMESTAMP
                    ");
                    
                    $stmt->execute([
                        $_POST['student_id'],
                        $_POST['course_id'],
                        $_POST['lecturer_id'],
                        $_POST['session_id'],
                        $_POST['semester'],
                        $test_score,
                        $exam_score,
                        $total_score,
                        $grade,
                        $_POST['status'] ?? 'draft'
                    ]);
                    
                    $success = "Result added/updated successfully!";
                    break;
                
                case 'bulk_upload':
                    $bulk_data = $_POST['bulk_results'];
                    $course_id = $_POST['bulk_course_id'];
                    $lecturer_id = $_POST['bulk_lecturer_id'];
                    $session_id = $_POST['bulk_session_id'];
                    $semester = $_POST['bulk_semester'];
                    
                    $lines = explode("\n", trim($bulk_data));
                    $success_count = 0;
                    $error_count = 0;
                    
                    foreach ($lines as $line) {
                        $line = trim($line);
                        if (empty($line)) continue;
                        
                        // Expected format: student_id,test_score,exam_score
                        // Or: matric_number,test_score,exam_score
                        $parts = explode(',', $line);
                        if (count($parts) >= 3) {
                            $student_identifier = trim($parts[0]);
                            $test_score = floatval(trim($parts[1]));
                            $exam_score = floatval(trim($parts[2]));
                            $total_score = $test_score + $exam_score;
                            
                            // Calculate grade
                            $grade = 'F';
                            if ($total_score >= 70) $grade = 'A';
                            elseif ($total_score >= 60) $grade = 'B';
                            elseif ($total_score >= 50) $grade = 'C';
                            elseif ($total_score >= 45) $grade = 'D';
                            elseif ($total_score >= 40) $grade = 'E';
                            
                            // Find student by ID or matric number
                            $studentStmt = $pdo->prepare("SELECT id FROM studrec WHERE id = ? OR matric_number = ? LIMIT 1");
                            $studentStmt->execute([$student_identifier, $student_identifier]);
                            $student = $studentStmt->fetch();
                            
                            if ($student) {
                                $stmt = $pdo->prepare("
                                    INSERT INTO results (student_id, course_id, lecturer_id, session_id, semester, test_score, exam_score, total_score, grade, status) 
                                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'draft')
                                    ON DUPLICATE KEY UPDATE
                                    test_score = VALUES(test_score),
                                    exam_score = VALUES(exam_score),
                                    total_score = VALUES(total_score),
                                    grade = VALUES(grade),
                                    updated_at = CURRENT_TIMESTAMP
                                ");
                                
                                $stmt->execute([
                                    $student['id'],
                                    $course_id,
                                    $lecturer_id,
                                    $session_id,
                                    $semester,
                                    $test_score,
                                    $exam_score,
                                    $total_score,
                                    $grade
                                ]);
                                $success_count++;
                            } else {
                                $error_count++;
                            }
                        }
                    }
                    
                    $success = "Bulk upload completed! {$success_count} results uploaded, {$error_count} errors.";
                    break;
                
                case 'delete_result':
                    $stmt = $pdo->prepare("DELETE FROM results WHERE result_id = ?");
                    $stmt->execute([$_POST['result_id']]);
                    $success = "Result deleted successfully!";
                    break;
            }
        }
    } catch (PDOException $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Get filter parameters
$selected_course = $_GET['course_id'] ?? '';
$selected_session = $_GET['session_id'] ?? '';
$selected_semester = $_GET['semester'] ?? '';
$selected_lecturer = $_GET['lecturer_id'] ?? '';

// Fetch results with filters
$whereConditions = [];
$params = [];

if ($selected_course) {
    $whereConditions[] = "r.course_id = ?";
    $params[] = $selected_course;
}
if ($selected_session) {
    $whereConditions[] = "r.session_id = ?";
    $params[] = $selected_session;
}
if ($selected_semester) {
    $whereConditions[] = "r.semester = ?";
    $params[] = $selected_semester;
}
if ($selected_lecturer) {
    $whereConditions[] = "r.lecturer_id = ?";
    $params[] = $selected_lecturer;
}

$whereClause = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : "";

try {
    $resultsQuery = "
        SELECT 
            r.*,
            s.first_name as student_first_name,
            s.last_name as student_last_name,
            s.matric_number,
            c.course_name,
            c.course_code,
            l.first_name as lecturer_first_name,
            l.last_name as lecturer_last_name,
            acs.session_name
        FROM results r
        LEFT JOIN studrec s ON r.student_id = s.id
        LEFT JOIN courses c ON r.course_id = c.course_id
        LEFT JOIN lecturers l ON r.lecturer_id = l.lecturer_id
        LEFT JOIN academic_sessions acs ON r.session_id = acs.session_id
        {$whereClause}
        ORDER BY r.created_at DESC
    ";
    
    $stmt = $pdo->prepare($resultsQuery);
    $stmt->execute($params);
    $results = $stmt->fetchAll();
    
    // Get dropdown data
    $coursesStmt = $pdo->query("SELECT course_id, course_name, course_code FROM courses ORDER BY course_name");
    $courses = $coursesStmt->fetchAll();
    
    $sessionsStmt = $pdo->query("SELECT session_id, session_name FROM academic_sessions ORDER BY session_name DESC");
    $sessions = $sessionsStmt->fetchAll();
    
    $lecturersStmt = $pdo->query("SELECT lecturer_id, first_name, last_name FROM lecturers ORDER BY first_name");
    $lecturers = $lecturersStmt->fetchAll();
    
    $studentsStmt = $pdo->query("SELECT id, first_name, last_name, matric_number FROM studrec WHERE role = 'student' ORDER BY first_name");
    $students = $studentsStmt->fetchAll();
    
} catch (PDOException $e) {
    $results = [];
    $courses = [];
    $sessions = [];
    $lecturers = [];
    $students = [];
    $error = "Error fetching data: " . $e->getMessage();
}

$currentPage = 'manage_results.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Results Management - UNIOSUN</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        .content-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .page-header {
            background: #6B8E23;
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
        
        .btn-primary { background: #6B8E23; color: white; }
        .btn-success { background: #28a745; color: white; }
        .btn-warning { background: #ffc107; color: #212529; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-info { background: #17a2b8; color: white; }
        .btn-secondary { background: #6c757d; color: white; }
        .btn-sm { padding: 6px 12px; font-size: 12px; }
        
        .btn:hover { transform: translateY(-2px); opacity: 0.9; }
        
        .filters-section {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }
        
        .form-control {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #556B2F;
            box-shadow: 0 0 0 0.2rem rgba(107, 142, 35, 0.25);
        }
        
        .results-table {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow-x: auto;
        }
        
        .table {
            width: 100%;
            margin-bottom: 0;
            border-collapse: collapse;
            min-width: 1000px;
        }
        
        .table th,
        .table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #dee2e6;
            white-space: nowrap;
        }
        
        .table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #495057;
        }
        
        .table tbody tr:hover {
            background: #f8f9fa;
        }
        
        .grade-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-weight: bold;
            font-size: 12px;
        }
        
        .grade-A { background: #d4edda; color: #155724; }
        .grade-B { background: #cce5ff; color: #004085; }
        .grade-C { background: #fff3cd; color: #856404; }
        .grade-D { background: #f8d7da; color: #721c24; }
        .grade-E { background: #f8d7da; color: #721c24; }
        .grade-F { background: #f5c6cb; color: #721c24; }
        
        .status-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .status-draft { background: #e2e3e5; color: #6c757d; }
        .status-submitted { background: #cce5ff; color: #004085; }
        .status-approved { background: #d4edda; color: #155724; }
        
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
            max-width: 600px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
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
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
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
        
        .bulk-upload-area {
            border: 2px dashed #6B8E23;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            margin: 15px 0;
        }
        
        .bulk-textarea {
            width: 100%;
            min-height: 150px;
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 10px;
            font-family: monospace;
            font-size: 13px;
        }
        
        .format-help {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin: 10px 0;
            font-size: 13px;
        }
    </style>
</head>
<body>
   
   <!-- Top Header -->
    <div class="top-header" style="padding-left:70px; margin-top:20px;">
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
                <h1><i class="fas fa-clipboard-list"></i> Results Management</h1>
                <p>Manage student results and grades</p>
            </div>
            <div style="display: flex; gap: 10px;">
                <a href="admin_dashboard.php" class="btn btn-danger" title="Back to Dashboard">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
                <button class="btn btn-dark" onclick="openModal('addModal')">
                    <i class="fas fa-plus"></i> Add Result
                </button>
                <button class="btn btn-success" onclick="openModal('bulkModal')">
                    <i class="fas fa-upload"></i> Bulk Upload
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
                <div class="stat-value"><?php echo count($results); ?></div>
                <div class="stat-label">Total Results</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo count(array_filter($results, function($r) { return $r['grade'] == 'A'; })); ?></div>
                <div class="stat-label">Grade A</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo count(array_filter($results, function($r) { return in_array($r['grade'], ['A', 'B', 'C', 'D', 'E']); })); ?></div>
                <div class="stat-label">Passed</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo count(array_filter($results, function($r) { return $r['grade'] == 'F'; })); ?></div>
                <div class="stat-label">Failed</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo count(array_filter($results, function($r) { return $r['status'] == 'approved'; })); ?></div>
                <div class="stat-label">Approved</div>
            </div>
        </div>

        <!-- Filters Section -->
        <div class="filters-section">
            <h3><i class="fas fa-filter"></i> Filter Results</h3>
            <form method="GET" class="filters-form">
                <div class="filters-grid">
                    <div class="form-group">
                        <label>Course</label>
                        <select name="course_id" class="form-control">
                            <option value="">All Courses</option>
                            <?php foreach ($courses as $course): ?>
                                <option value="<?php echo $course['course_id']; ?>" <?php echo $selected_course == $course['course_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($course['course_code'] . ' - ' . $course['course_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Academic Session</label>
                        <select name="session_id" class="form-control">
                            <option value="">All Sessions</option>
                            <?php foreach ($sessions as $session): ?>
                                <option value="<?php echo $session['session_id']; ?>" <?php echo $selected_session == $session['session_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($session['session_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Semester</label>
                        <select name="semester" class="form-control">
                            <option value="">All Semesters</option>
                            <option value="First" <?php echo $selected_semester == 'First' ? 'selected' : ''; ?>>First Semester</option>
                            <option value="Second" <?php echo $selected_semester == 'Second' ? 'selected' : ''; ?>>Second Semester</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Lecturer</label>
                        <select name="lecturer_id" class="form-control">
                            <option value="">All Lecturers</option>
                            <?php foreach ($lecturers as $lecturer): ?>
                                <option value="<?php echo $lecturer['lecturer_id']; ?>" <?php echo $selected_lecturer == $lecturer['lecturer_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($lecturer['first_name'] . ' ' . $lecturer['last_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-search"></i> Apply Filters
                </button>
                <a href="manage_results.php" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Clear Filters
                </a>
            </form>
        </div>

        <!-- Results Table -->
        <div class="results-table">
            <table class="table">
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Matric Number</th>
                        <th>Course</th>
                        <th>Lecturer</th>
                        <th>Session</th>
                        <th>Semester</th>
                        <th>Test</th>
                        <th>Exam</th>
                        <th>Total</th>
                        <th>Grade</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($results)): ?>
                        <tr>
                            <td colspan="12" style="text-align: center; padding: 40px;">
                                <i class="fas fa-clipboard-list fa-3x" style="color: #ddd; margin-bottom: 15px;"></i>
                                <p style="color: #666; margin: 0;">No results found. Add some results to get started!</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($results as $result): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($result['student_first_name'] . ' ' . $result['student_last_name']); ?></td>
                                <td><?php echo htmlspecialchars($result['matric_number']); ?></td>
                                <td><?php echo htmlspecialchars($result['course_code']); ?></td>
                                <td><?php echo htmlspecialchars($result['lecturer_first_name'] . ' ' . $result['lecturer_last_name']); ?></td>
                                <td><?php echo htmlspecialchars($result['session_name']); ?></td>
                                <td><?php echo htmlspecialchars($result['semester']); ?></td>
                                <td><?php echo number_format($result['test_score'], 1); ?></td>
                                <td><?php echo number_format($result['exam_score'], 1); ?></td>
                                <td><?php echo number_format($result['total_score'], 1); ?></td>
                                <td>
                                    <span class="grade-badge grade-<?php echo $result['grade']; ?>">
                                        <?php echo $result['grade']; ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="status-badge status-<?php echo $result['status']; ?>">
                                        <?php echo ucfirst($result['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <button class="btn btn-warning btn-sm" onclick="editResult(<?php echo htmlspecialchars(json_encode($result)); ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-danger btn-sm" onclick="deleteResult(<?php echo $result['result_id']; ?>)">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Add Result Modal -->
    <div id="addModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('addModal')">&times;</span>
            <h2><i class="fas fa-plus"></i> Add New Result</h2>
            <form method="POST">
                <input type="hidden" name="action" value="add_result">
                
                <div class="form-group">
                    <label for="student_id">Student *</label>
                    <select class="form-control" name="student_id" required>
                        <option value="">Select Student</option>
                        <?php foreach ($students as $student): ?>
                            <option value="<?php echo $student['id']; ?>">
                                <?php echo htmlspecialchars($student['matric_number'] . ' - ' . $student['first_name'] . ' ' . $student['last_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="course_id">Course *</label>
                    <select class="form-control" name="course_id" required>
                        <option value="">Select Course</option>
                        <?php foreach ($courses as $course): ?>
                            <option value="<?php echo $course['course_id']; ?>">
                                <?php echo htmlspecialchars($course['course_code'] . ' - ' . $course['course_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="lecturer_id">Lecturer *</label>
                    <select class="form-control" name="lecturer_id" required>
                        <option value="">Select Lecturer</option>
                        <?php foreach ($lecturers as $lecturer): ?>
                            <option value="<?php echo $lecturer['lecturer_id']; ?>">
                                <?php echo htmlspecialchars($lecturer['first_name'] . ' ' . $lecturer['last_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="session_id">Academic Session *</label>
                    <select class="form-control" name="session_id" required>
                        <option value="">Select Session</option>
                        <?php foreach ($sessions as $session): ?>
                            <option value="<?php echo $session['session_id']; ?>">
                                <?php echo htmlspecialchars($session['session_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="semester">Semester *</label>
                    <select class="form-control" name="semester" required>
                        <option value="">Select Semester</option>
                        <option value="First">First Semester</option>
                        <option value="Second">Second Semester</option>
                    </select>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label for="test_score">Test Score (0-30) *</label>
                        <input type="number" class="form-control" name="test_score" min="0" max="30" step="0.1" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="exam_score">Exam Score (0-70) *</label>
                        <input type="number" class="form-control" name="exam_score" min="0" max="70" step="0.1" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="status">Status</label>
                    <select class="form-control" name="status">
                        <option value="draft">Draft</option>
                        <option value="submitted">Submitted</option>
                        <option value="approved">Approved</option>
                    </select>
                </div>
                
                <div style="display: flex; gap: 10px; justify-content: flex-end;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('addModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Result</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Result Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('editModal')">&times;</span>
            <h2><i class="fas fa-edit"></i> Edit Result</h2>
            <form method="POST" id="editForm">
                <input type="hidden" name="action" value="add_result">
                <input type="hidden" name="result_id" id="edit_result_id">
                
                <div class="form-group">
                    <label for="edit_student_id">Student *</label>
                    <select class="form-control" name="student_id" id="edit_student_id" required>
                        <option value="">Select Student</option>
                        <?php foreach ($students as $student): ?>
                            <option value="<?php echo $student['id']; ?>">
                                <?php echo htmlspecialchars($student['matric_number'] . ' - ' . $student['first_name'] . ' ' . $student['last_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="edit_course_id">Course *</label>
                    <select class="form-control" name="course_id" id="edit_course_id" required>
                        <option value="">Select Course</option>
                        <?php foreach ($courses as $course): ?>
                            <option value="<?php echo $course['course_id']; ?>">
                                <?php echo htmlspecialchars($course['course_code'] . ' - ' . $course['course_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="edit_lecturer_id">Lecturer *</label>
                    <select class="form-control" name="lecturer_id" id="edit_lecturer_id" required>
                        <option value="">Select Lecturer</option>
                        <?php foreach ($lecturers as $lecturer): ?>
                            <option value="<?php echo $lecturer['lecturer_id']; ?>">
                                <?php echo htmlspecialchars($lecturer['first_name'] . ' ' . $lecturer['last_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="edit_session_id">Academic Session *</label>
                    <select class="form-control" name="session_id" id="edit_session_id" required>
                        <option value="">Select Session</option>
                        <?php foreach ($sessions as $session): ?>
                            <option value="<?php echo $session['session_id']; ?>">
                                <?php echo htmlspecialchars($session['session_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="edit_semester">Semester *</label>
                    <select class="form-control" name="semester" id="edit_semester" required>
                        <option value="">Select Semester</option>
                        <option value="First">First Semester</option>
                        <option value="Second">Second Semester</option>
                    </select>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label for="edit_test_score">Test Score (0-30) *</label>
                        <input type="number" class="form-control" name="test_score" id="edit_test_score" min="0" max="30" step="0.1" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_exam_score">Exam Score (0-70) *</label>
                        <input type="number" class="form-control" name="exam_score" id="edit_exam_score" min="0" max="70" step="0.1" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="edit_status">Status</label>
                    <select class="form-control" name="status" id="edit_status">
                        <option value="draft">Draft</option>
                        <option value="submitted">Submitted</option>
                        <option value="approved">Approved</option>
                    </select>
                </div>
                
                <div style="display: flex; gap: 10px; justify-content: flex-end;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('editModal')">Cancel</button>
                    <button type="submit" class="btn btn-success">Update Result</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Bulk Upload Modal -->
    <div id="bulkModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('bulkModal')">&times;</span>
            <h2><i class="fas fa-upload"></i> Bulk Upload Results</h2>
            
            <div class="format-help">
                <h4><i class="fas fa-info-circle"></i> Format Instructions</h4>
                <p><strong>Format:</strong> student_id_or_matric,test_score,exam_score</p>
                <p><strong>Example:</strong></p>
                <code>
                    123,25,65<br>
                    UNI/SCI/18/001,28,70<br>
                    124,20,55
                </code>
                <p><em>Each line represents one student result. Use comma to separate values.</em></p>
            </div>
            
            <form method="POST">
                <input type="hidden" name="action" value="bulk_upload">
                
                <div class="form-group">
                    <label for="bulk_course_id">Course *</label>
                    <select class="form-control" name="bulk_course_id" required>
                        <option value="">Select Course</option>
                        <?php foreach ($courses as $course): ?>
                            <option value="<?php echo $course['course_id']; ?>">
                                <?php echo htmlspecialchars($course['course_code'] . ' - ' . $course['course_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="bulk_lecturer_id">Lecturer *</label>
                    <select class="form-control" name="bulk_lecturer_id" required>
                        <option value="">Select Lecturer</option>
                        <?php foreach ($lecturers as $lecturer): ?>
                            <option value="<?php echo $lecturer['lecturer_id']; ?>">
                                <?php echo htmlspecialchars($lecturer['first_name'] . ' ' . $lecturer['last_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label for="bulk_session_id">Academic Session *</label>
                        <select class="form-control" name="bulk_session_id" required>
                            <option value="">Select Session</option>
                            <?php foreach ($sessions as $session): ?>
                                <option value="<?php echo $session['session_id']; ?>">
                                    <?php echo htmlspecialchars($session['session_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="bulk_semester">Semester *</label>
                        <select class="form-control" name="bulk_semester" required>
                            <option value="">Select Semester</option>
                            <option value="First">First Semester</option>
                            <option value="Second">Second Semester</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="bulk_results">Results Data *</label>
                    <div class="bulk-upload-area">
                        <textarea class="bulk-textarea" name="bulk_results" placeholder="Paste your results here in the format: student_id,test_score,exam_score&#10;Example:&#10;123,25,65&#10;UNI/SCI/18/001,28,70&#10;124,20,55" required></textarea>
                    </div>
                </div>
                
                <div style="display: flex; gap: 10px; justify-content: flex-end;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('bulkModal')">Cancel</button>
                    <button type="submit" class="btn btn-success">Upload Results</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openModal(modalId) {
            document.getElementById(modalId).style.display = 'block';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        function editResult(result) {
            document.getElementById('edit_result_id').value = result.result_id;
            document.getElementById('edit_student_id').value = result.student_id;
            document.getElementById('edit_course_id').value = result.course_id;
            document.getElementById('edit_lecturer_id').value = result.lecturer_id;
            document.getElementById('edit_session_id').value = result.session_id;
            document.getElementById('edit_semester').value = result.semester;
            document.getElementById('edit_test_score').value = result.test_score;
            document.getElementById('edit_exam_score').value = result.exam_score;
            document.getElementById('edit_status').value = result.status;
            openModal('editModal');
        }

        function deleteResult(id) {
            if (confirm('Are you sure you want to delete this result?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_result">
                    <input type="hidden" name="result_id" value="${id}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
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

        // Auto-calculate total score and grade preview
        function setupScoreCalculation() {
            const testInputs = document.querySelectorAll('input[name="test_score"]');
            const examInputs = document.querySelectorAll('input[name="exam_score"]');
            
            function calculateGrade(total) {
                if (total >= 70) return 'A';
                if (total >= 60) return 'B';
                if (total >= 50) return 'C';
                if (total >= 45) return 'D';
                if (total >= 40) return 'E';
                return 'F';
            }
            
            function updatePreview(testInput, examInput) {
                const test = parseFloat(testInput.value) || 0;
                const exam = parseFloat(examInput.value) || 0;
                const total = test + exam;
                const grade = calculateGrade(total);
                
                // Find or create preview element
                let preview = testInput.parentNode.parentNode.querySelector('.score-preview');
                if (!preview) {
                    preview = document.createElement('div');
                    preview.className = 'score-preview';
                    preview.style.cssText = 'margin-top: 10px; padding: 8px; background: #f8f9fa; border-radius: 4px; font-size: 14px;';
                    testInput.parentNode.parentNode.appendChild(preview);
                }
                
                preview.innerHTML = `<strong>Total: ${total.toFixed(1)} | Grade: <span class="grade-badge grade-${grade}">${grade}</span></strong>`;
            }
            
            // Setup for all test/exam input pairs
            testInputs.forEach((testInput, index) => {
                const examInput = examInputs[index];
                if (examInput) {
                    testInput.addEventListener('input', () => updatePreview(testInput, examInput));
                    examInput.addEventListener('input', () => updatePreview(testInput, examInput));
                }
            });
        }

        // Initialize when page loads
        document.addEventListener('DOMContentLoaded', function() {
            setupScoreCalculation();
        });

        // Re-initialize when modals open
        function openModal(modalId) {
            document.getElementById(modalId).style.display = 'block';
            setTimeout(setupScoreCalculation, 100);
        }

        // Bulk upload helper
        function validateBulkData() {
            const textarea = document.querySelector('textarea[name="bulk_results"]');
            if (textarea) {
                textarea.addEventListener('input', function() {
                    const lines = this.value.trim().split('\n').filter(line => line.trim());
                    const validLines = lines.filter(line => {
                        const parts = line.split(',');
                        return parts.length >= 3 && !isNaN(parts[1]) && !isNaN(parts[2]);
                    });
                    
                    // Show validation feedback
                    let feedback = textarea.parentNode.querySelector('.validation-feedback');
                    if (!feedback) {
                        feedback = document.createElement('div');
                        feedback.className = 'validation-feedback';
                        feedback.style.cssText = 'margin-top: 5px; font-size: 12px;';
                        textarea.parentNode.appendChild(feedback);
                    }
                    
                    if (lines.length > 0) {
                        feedback.innerHTML = `<span style="color: green;">✓ ${validLines.length} valid entries</span> ${lines.length !== validLines.length ? `<span style="color: red;">| ${lines.length - validLines.length} invalid entries</span>` : ''}`;
                    } else {
                        feedback.innerHTML = '';
                    }
                });
            }
        }

        // Initialize bulk upload validation
        document.addEventListener('DOMContentLoaded', validateBulkData);
    </script>
</body>
</html>