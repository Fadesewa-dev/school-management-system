<?php
session_start();

// Check if lecturer is logged in
if (!isset($_SESSION['lecturer_logged_in']) || $_SESSION['lecturer_logged_in'] !== true) {
    header("Location: lecturer_login.php");
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

$lecturer_id = $_SESSION['lecturer_id'];
$message = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['submit_result'])) {
        $student_id = $_POST['student_id'];
        $course_id = $_POST['course_id'];
        $semester = $_POST['semester'];
        $test_score = floatval($_POST['test_score']);
        $exam_score = floatval($_POST['exam_score']);
        $total_score = $test_score + $exam_score;
        
        // Calculate grade based on total score
        $grade = '';
        if ($total_score >= 70) $grade = 'A';
        elseif ($total_score >= 60) $grade = 'B';
        elseif ($total_score >= 50) $grade = 'C';
        elseif ($total_score >= 45) $grade = 'D';
        elseif ($total_score >= 40) $grade = 'E';
        else $grade = 'F';
        
        try {
            // Check if result already exists
            $stmt = $pdo->prepare("SELECT result_id FROM results WHERE student_id = ? AND course_id = ? AND lecturer_id = ? AND semester = ?");
            $stmt->execute([$student_id, $course_id, $lecturer_id, $semester]);
            
            if ($stmt->fetch()) {
                $error = "Result already exists for this student in this course and semester!";
            } else {
                // Insert new result
                $stmt = $pdo->prepare("
                    INSERT INTO results (student_id, course_id, lecturer_id, semester, test_score, exam_score, total_score, grade, status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'draft')
                ");
                $stmt->execute([$student_id, $course_id, $lecturer_id, $semester, $test_score, $exam_score, $total_score, $grade]);
                $message = "Result saved as draft successfully!";
            }
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
    
    if (isset($_POST['submit_final'])) {
        $result_id = $_POST['result_id'];
        try {
            $stmt = $pdo->prepare("UPDATE results SET status = 'submitted' WHERE result_id = ? AND lecturer_id = ?");
            $stmt->execute([$result_id, $lecturer_id]);
            $message = "Result submitted for approval!";
        } catch (PDOException $e) {
            $error = "Error submitting result: " . $e->getMessage();
        }
    }
}

// Get courses assigned to this lecturer
$courses = [];
try {
    $stmt = $pdo->prepare("SELECT course_id, course_name, course_code FROM courses WHERE lecturer_id = ?");
    $stmt->execute([$lecturer_id]);
    $courses = $stmt->fetchAll();
} catch (PDOException $e) {
    // If no courses assigned, get all courses
    $stmt = $pdo->query("SELECT course_id, course_name, course_code FROM courses");
    $courses = $stmt->fetchAll();
}

// Get students
$students = [];
try {
    $stmt = $pdo->query("SELECT ID as student_id, CONCAT(FIRST_NAME, ' ', LAST_NAME) as full_name, MATRIC_NUMBER FROM studrec WHERE role = 'student' ORDER BY FIRST_NAME");
    $students = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = "Error loading students: " . $e->getMessage();
}

// Get lecturer's results
$my_results = [];
try {
    $stmt = $pdo->prepare("
        SELECT r.*, 
               CONCAT(s.FIRST_NAME, ' ', s.LAST_NAME) as student_name,
               s.MATRIC_NUMBER,
               c.course_name,
               c.course_code
        FROM results r
        JOIN studrec s ON r.student_id = s.ID
        JOIN courses c ON r.course_id = c.course_id
        WHERE r.lecturer_id = ?
        ORDER BY r.created_at DESC
    ");
    $stmt->execute([$lecturer_id]);
    $my_results = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = "Error loading results: " . $e->getMessage();
}

$currentPage = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Results - Lecturer Portal</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #6B8E23 0%, #556B2F 100%);
            min-height: 100vh;
        }

        .top-header {
            background: rgba(0, 0, 0, 0.2);
            color: white;
            padding: 10px 0;
            font-size: 14px;
        }

        .main-header {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            padding: 20px 0;
            margin-bottom: 20px;
        }

        .logo-section {
            display: flex;
            align-items: center;
            color: white;
        }

        .logo {
            background: linear-gradient(45deg, #ff6b6b, #4ecdc4);
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 20px;
            font-size: 24px;
        }

        .dashboard-container {
            display: flex;
            margin: 0 20px 20px 20px;
            gap: 20px;
            min-height: 70vh;
        }

        .sidebar {
            width: 280px;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            height: fit-content;
        }

        .lecturer-profile {
            text-align: center;
            margin-bottom: 30px;
            padding: 20px;
            background: linear-gradient(135deg, #6B8E23, #8FBC8F);
            border-radius: 15px;
            color: white;
        }

        .nav-menu {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .nav-menu li {
            margin-bottom: 8px;
        }

        .nav-menu a {
            display: flex;
            align-items: center;
            padding: 15px 20px;
            color: #333;
            text-decoration: none;
            border-radius: 10px;
            transition: all 0.3s ease;
            font-weight: 500;
        }

        .nav-menu a:hover, .nav-menu a.active {
            background: #6B8E23;
            color: white;
            transform: translateX(5px);
        }

        .nav-menu i {
            margin-right: 12px;
            width: 20px;
            text-align: center;
        }

        .main-content {
            flex: 1;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        }

        .page-header {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #e9ecef;
        }

        .page-title {
            color: #333;
            font-size: 28px;
            font-weight: 600;
            margin: 0;
        }

        .form-card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
        }

        .form-card h4 {
            color: #6B8E23;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e9ecef;
        }

        .form-control {
            border-radius: 8px;
            border: 2px solid #e9ecef;
            padding: 12px 15px;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: #6B8E23;
            box-shadow: 0 0 0 0.2rem rgba(107, 142, 35, 0.25);
        }

        .btn-primary {
            background: #6B8E23;
            border-color: #6B8E23;
            border-radius: 8px;
            padding: 12px 25px;
            font-weight: 600;
        }

        .btn-primary:hover {
            background: #556B2F;
            border-color: #556B2F;
        }

        .results-table {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
        }

        .table {
            margin: 0;
        }

        .table th {
            background: #6B8E23;
            color: white;
            border: none;
            padding: 15px;
            font-weight: 600;
        }

        .table td {
            padding: 15px;
            vertical-align: middle;
            border-color: #e9ecef;
        }

        .badge {
            padding: 8px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .badge.bg-warning {
            background-color: #f39c12 !important;
        }

        .badge.bg-info {
            background-color: #3498db !important;
        }

        .badge.bg-success {
            background-color: #2ecc71 !important;
        }

        .grade-a { color: #2ecc71; font-weight: bold; }
        .grade-b { color: #3498db; font-weight: bold; }
        .grade-c { color: #f39c12; font-weight: bold; }
        .grade-d { color: #e67e22; font-weight: bold; }
        .grade-e { color: #e74c3c; font-weight: bold; }
        .grade-f { color: #c0392b; font-weight: bold; }

        @media (max-width: 768px) {
            .dashboard-container {
                flex-direction: column;
            }
            
            .sidebar {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <!-- Top Header -->
    <div class="top-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <i class="fas fa-clipboard-list"></i> Results Management
                </div>
                <div class="col-md-6 text-end">
                    <span class="me-3">
                        <i class="fas fa-user"></i> 
                        <?php echo htmlspecialchars($_SESSION['lecturer_name']); ?>
                    </span>
                    <a href="lecturer_logout.php" class="text-light">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Header -->
    <div class="main-header">
        <div class="container">
            <div class="logo-section">
                <div class="logo">
                    <i class="fas fa-university"></i>
                </div>
                <div class="university-name">
                    <h1 style="font-size: 32px; font-weight: 700; margin: 0; color: white;">OSUN STATE UNIVERSITY</h1>
                    <p style="font-size: 14px; opacity: 0.9; margin: 0; color: white;">Lecturer Portal - Results Upload System</p>
                </div>
            </div>
        </div>
    </div>

    <div class="dashboard-container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="lecturer-profile">
                <div style="width: 60px; height: 60px; border-radius: 50%; background: rgba(255, 255, 255, 0.3); display: flex; align-items: center; justify-content: center; margin: 0 auto 15px; font-size: 24px;">
                    <i class="fas fa-user-tie"></i>
                </div>
                <div>
                    <h4 style="margin: 0; font-size: 18px; font-weight: 600;"><?php echo htmlspecialchars($_SESSION['lecturer_name']); ?></h4>
                    <p style="margin: 5px 0 0 0; opacity: 0.9; font-size: 14px;">Department Lecturer</p>
                </div>
            </div>
            
            <ul class="nav-menu">
                <li><a href="lecturer_dashboard.php">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a></li>
                <li><a href="lecturer_courses.php">
                    <i class="fas fa-book"></i> My Courses
                </a></li>
                <li><a href="lecturer_results.php" class="active">
                    <i class="fas fa-clipboard-list"></i> Upload Results
                </a></li>
                <li><a href="lecturer_students.php">
                    <i class="fas fa-users"></i> My Students
                </a></li>
                <li><a href="lecturer_profile.php">
                    <i class="fas fa-user-cog"></i> My Profile
                </a></li>
                <li><a href="lecturer_logout.php">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a></li>
            </ul>
        </div>
        
        <!-- Main Content -->
        <div class="main-content">
            <div class="page-header">
                <h1 class="page-title"><i class="fas fa-upload"></i> Upload Student Results</h1>
            </div>
            
            <?php if ($message): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <!-- Upload Form -->
            <div class="form-card">
                <h4><i class="fas fa-plus-circle"></i> Add New Result</h4>
                <form method="POST">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="student_id" class="form-label">Student</label>
                                <select class="form-control" id="student_id" name="student_id" required>
                                    <option value="">Select Student</option>
                                    <?php foreach ($students as $student): ?>
                                        <option value="<?php echo $student['student_id']; ?>">
                                            <?php echo htmlspecialchars($student['full_name'] . ' - ' . $student['MATRIC_NUMBER']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="course_id" class="form-label">Course</label>
                                <select class="form-control" id="course_id" name="course_id" required>
                                    <option value="">Select Course</option>
                                    <?php foreach ($courses as $course): ?>
                                        <option value="<?php echo $course['course_id']; ?>">
                                            <?php echo htmlspecialchars($course['course_code'] . ' - ' . $course['course_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="semester" class="form-label">Semester</label>
                                <select class="form-control" id="semester" name="semester" required>
                                    <option value="">Select Semester</option>
                                    <option value="First">First Semester</option>
                                    <option value="Second">Second Semester</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="test_score" class="form-label">Test Score (30)</label>
                                <input type="number" class="form-control" id="test_score" name="test_score" 
                                       min="0" max="30" step="0.01" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="exam_score" class="form-label">Exam Score (70)</label>
                                <input type="number" class="form-control" id="exam_score" name="exam_score" 
                                       min="0" max="70" step="0.01" required>
                            </div>
                        </div>
                    </div>
                    
                    <button type="submit" name="submit_result" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save as Draft
                    </button>
                </form>
            </div>
            
            <!-- My Results -->
            <div class="results-table">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Course</th>
                                <th>Semester</th>
                                <th>Test</th>
                                <th>Exam</th>
                                <th>Total</th>
                                <th>Grade</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($my_results)): ?>
                                <tr>
                                    <td colspan="9" class="text-center py-4">
                                        <i class="fas fa-clipboard text-muted fa-3x mb-3"></i>
                                        <p class="text-muted">No results uploaded yet. Start by adding your first result above.</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($my_results as $result): ?>
                                    <tr>
                                        <td>
                                            <div>
                                                <strong><?php echo htmlspecialchars($result['student_name']); ?></strong><br>
                                                <small class="text-muted"><?php echo htmlspecialchars($result['MATRIC_NUMBER']); ?></small>
                                            </div>
                                        </td>
                                        <td>
                                            <div>
                                                <strong><?php echo htmlspecialchars($result['course_code']); ?></strong><br>
                                                <small class="text-muted"><?php echo htmlspecialchars($result['course_name']); ?></small>
                                            </div>
                                        </td>
                                        <td><?php echo htmlspecialchars($result['semester']); ?></td>
                                        <td><?php echo number_format($result['test_score'], 1); ?></td>
                                        <td><?php echo number_format($result['exam_score'], 1); ?></td>
                                        <td><strong><?php echo number_format($result['total_score'], 1); ?></strong></td>
                                        <td><span class="grade-<?php echo strtolower($result['grade']); ?>"><?php echo $result['grade']; ?></span></td>
                                        <td>
                                            <?php
                                            $status_class = '';
                                            switch($result['status']) {
                                                case 'draft': $status_class = 'bg-warning'; break;
                                                case 'submitted': $status_class = 'bg-info'; break;
                                                case 'approved': $status_class = 'bg-success'; break;
                                            }
                                            ?>
                                            <span class="badge <?php echo $status_class; ?>">
                                                <?php echo ucfirst($result['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($result['status'] == 'draft'): ?>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="result_id" value="<?php echo $result['result_id']; ?>">
                                                    <button type="submit" name="submit_final" class="btn btn-sm btn-success">
                                                        <i class="fas fa-paper-plane"></i> Submit
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <span class="text-muted"><i class="fas fa-check"></i> Submitted</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-calculate total score
        document.getElementById('test_score').addEventListener('input', calculateTotal);
        document.getElementById('exam_score').addEventListener('input', calculateTotal);
        
        function calculateTotal() {
            const testScore = parseFloat(document.getElementById('test_score').value) || 0;
            const examScore = parseFloat(document.getElementById('exam_score').value) || 0;
            const total = testScore + examScore;
            
            // Show preview of total and grade
            let grade = '';
            if (total >= 70) grade = 'A';
            else if (total >= 60) grade = 'B';
            else if (total >= 50) grade = 'C';
            else if (total >= 45) grade = 'D';
            else if (total >= 40) grade = 'E';
            else grade = 'F';
            
            // You can add a preview display here if needed
        }
    </script>
</body>
</html>