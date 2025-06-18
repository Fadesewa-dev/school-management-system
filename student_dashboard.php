<?php
session_start();

// Check if student is logged in
if (!isset($_SESSION['student_logged_in']) || $_SESSION['student_logged_in'] !== true) {
    header("Location: student_login.php");
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

$student_id = $_SESSION['student_id'];

// Get student details
try {
    $stmt = $pdo->prepare("SELECT * FROM studrec WHERE ID = ?");
    $stmt->execute([$student_id]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error loading student details: " . $e->getMessage();
}

// Get student's results from lecturer posts
$my_results = [];
try {
    $stmt = $pdo->prepare("
        SELECT r.*, 
               c.course_name,
               c.course_code,
               CONCAT(l.first_name, ' ', l.last_name) as lecturer_name
        FROM results r
        JOIN courses c ON r.course_id = c.course_id
        JOIN lecturers l ON r.lecturer_id = l.lecturer_id
        WHERE r.student_id = ?
        ORDER BY r.created_at DESC
    ");
    $stmt->execute([$student_id]);
    $my_results = $stmt->fetchAll();
} catch (PDOException $e) {
    $my_results = [];
}

// Calculate GPA
$total_points = 0;
$total_courses = 0;
$grade_points = ['A' => 4, 'B' => 3, 'C' => 2, 'D' => 1, 'E' => 0, 'F' => 0];

foreach ($my_results as $result) {
    if (isset($grade_points[$result['grade']])) {
        $total_points += $grade_points[$result['grade']];
        $total_courses++;
    }
}

$gpa = $total_courses > 0 ? round($total_points / $total_courses, 2) : 0;

// Get available courses
$courses = [];
try {
    $stmt = $pdo->query("SELECT course_id, course_name, course_code FROM courses ORDER BY course_name");
    $courses = $stmt->fetchAll();
} catch (PDOException $e) {
    $courses = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - University Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #F5F8F0, #E8F0E0);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
        }

        .navbar {
            background: linear-gradient(135deg, #6B8E23, #5A7A1C);
            box-shadow: 0 2px 10px rgba(107, 142, 35, 0.3);
            border-bottom: 3px solid #556B1F;
        }

        .navbar-brand, .nav-link {
            color: white !important;
        }

        .navbar-brand:hover, .nav-link:hover {
            color: #E8F5E8 !important;
        }

        .dashboard {
            max-width: 1200px;
            margin: 30px auto;
            padding: 25px;
        }

        .profile-card {
            background: linear-gradient(135deg, #ffffff, #f8fdf8);
            border-radius: 15px;
            box-shadow: 0 8px 20px rgba(107, 142, 35, 0.1);
            text-align: center;
            padding: 30px;
            margin-bottom: 30px;
            border: 2px solid rgba(107, 142, 35, 0.1);
        }

        .profile-card .fa-user-graduate {
            color: #6B8E23 !important;
        }

        .profile-card h3 {
            color: #556B1F;
        }

        .stat-card {
            background: linear-gradient(135deg, #ffffff, #f8fdf8);
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 8px 20px rgba(107, 142, 35, 0.1);
            border-left: 4px solid #6B8E23;
            transition: all 0.3s ease;
            text-align: center;
            margin-bottom: 20px;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 30px rgba(107, 142, 35, 0.2);
        }

        .stat-icon {
            font-size: 2.5rem;
            color: #6B8E23;
            margin-bottom: 15px;
        }

        .results-card {
            background: linear-gradient(135deg, #ffffff, #f8fdf8);
            border-radius: 15px;
            box-shadow: 0 8px 20px rgba(107, 142, 35, 0.1);
            overflow: hidden;
            border: 2px solid rgba(107, 142, 35, 0.1);
        }

        .results-header {
            background: linear-gradient(135deg, #6B8E23, #5A7A1C);
            color: white;
            padding: 20px;
        }

        .table th {
            background: linear-gradient(135deg, #6B8E23, #5A7A1C);
            color: white;
            border: none;
            font-weight: 600;
        }

        .badge-grade {
            padding: 8px 12px;
            border-radius: 20px;
            font-weight: 600;
        }

        .grade-A { background: #6B8E23; color: white; }
        .grade-B { background: #8B9D3A; color: white; }
        .grade-C { background: #ffc107; color: black; }
        .grade-D { background: #fd7e14; color: white; }
        .grade-E { background: #6c757d; color: white; }
        .grade-F { background: #dc3545; color: white; }

        .status-submitted { color: #6B8E23; font-weight: 600; }
        .status-approved { color: #5A7A1C; font-weight: 600; }
        .status-draft { color: #ffc107; font-weight: 600; }

        .card {
            border: 1px solid rgba(107, 142, 35, 0.2);
            transition: all 0.3s ease;
        }

        .card:hover {
            box-shadow: 0 5px 15px rgba(107, 142, 35, 0.2);
            transform: translateY(-2px);
        }

        .card-title {
            color: #6B8E23 !important;
        }
        .profile-image-container {
    display: flex;
    justify-content: center;
    align-items: center;
}

.profile-image {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    object-fit: cover;
    border: 4px solid #007bff; /* Adjust color to match your theme */
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
    transition: transform 0.3s ease;
}

.profile-image:hover {
    transform: scale(1.05);
}

/* Alternative square image style */
.profile-image.square {
    border-radius: 15px;
    width: 100px;
    height: 100px;
}
    </style>
</head>
<body>

<!-- Navigation -->
<nav class="navbar navbar-expand-lg">
    <div class="container">
        <a class="navbar-brand" href="#">
            <i class="fas fa-user-graduate"></i> Student Portal
        </a>
        <div class="navbar-nav ms-auto">
            <span class="nav-link">Welcome, <?php echo htmlspecialchars($student['FIRST_NAME']); ?></span>
            <a class="nav-link" href="student_logout.php">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </div>
</nav>

<div class="dashboard">
    <div class="profile-card">
    <?php 
    // Check if student has a profile image, otherwise use default
    $profileImage = !empty($student['PROFILE_IMAGE']) ? $student['PROFILE_IMAGE'] : 'uploads/newme1.jpg';
    ?>
    
    <div class="profile-image-container mb-3">
        <img src="<?php echo htmlspecialchars($profileImage); ?>" 
             alt="Profile Picture" 
             class="profile-image"
             onerror="this.src='assets/images/default-avatar.png';">
    </div>
    
    <h3>Welcome, <?php echo htmlspecialchars($student['FIRST_NAME'] . ' ' . $student['LAST_NAME']); ?>! 👋</h3>
    <?php 
    $matric = $student['MATRIC_NUMBER'] ?? 'UNIOSUN/' . date('Y') . '/' . str_pad($student['ID'], 4, '0', STR_PAD_LEFT);
    ?>
    <p><strong>Matric Number:</strong> <?php echo htmlspecialchars($matric); ?></p>
    <p><strong>Email:</strong> <?php echo htmlspecialchars($student['EMAIL']); ?></p>
    <p><strong>Department:</strong> <?php echo htmlspecialchars($student['DEPARTMENT'] ?? 'Biology'); ?></p>
</div>

    <!-- Quick Stats -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="stat-card">
                <i class="fas fa-book stat-icon"></i>
                <h4><?php echo count($courses); ?></h4>
                <p class="text-muted mb-0">Available Courses</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <i class="fas fa-clipboard-list stat-icon"></i>
                <h4><?php echo count($my_results); ?></h4>
                <p class="text-muted mb-0">Results Posted</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <i class="fas fa-chart-line stat-icon"></i>
                <h4><?php echo $gpa; ?></h4>
                <p class="text-muted mb-0">Current GPA</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <i class="fas fa-calendar-semester stat-icon"></i>
                <h4>First</h4>
                <p class="text-muted mb-0">Current Semester</p>
            </div>
        </div>
    </div>

    <!-- My Results Section -->
    <div class="results-card">
        <div class="results-header">
            <h4><i class="fas fa-trophy"></i> My Academic Results</h4>
            <p class="mb-0">View your posted results from lecturers</p>
        </div>
        
        <div class="p-4">
            <?php if (empty($my_results)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">No Results Yet</h5>
                    <p class="text-muted">Your results will appear here when lecturers post them.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Course</th>
                                <th>Semester</th>
                                <th>Test</th>
                                <th>Exam</th>
                                <th>Total</th>
                                <th>Grade</th>
                                <th>Status</th>
                                <th>Lecturer</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($my_results as $result): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($result['course_code']); ?></strong><br>
                                        <small class="text-muted"><?php echo htmlspecialchars($result['course_name']); ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars($result['semester']); ?></td>
                                    <td><?php echo number_format($result['test_score'], 1); ?></td>
                                    <td><?php echo number_format($result['exam_score'], 1); ?></td>
                                    <td><strong><?php echo number_format($result['total_score'], 1); ?></strong></td>
                                    <td>
                                        <span class="badge badge-grade grade-<?php echo $result['grade']; ?>">
                                            <?php echo $result['grade']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="status-<?php echo $result['status']; ?>">
                                            <i class="fas fa-<?php echo $result['status'] == 'approved' ? 'check-circle' : ($result['status'] == 'submitted' ? 'clock' : 'edit'); ?>"></i>
                                            <?php echo ucfirst($result['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($result['lecturer_name']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Available Courses -->
    <div class="results-card mt-4">
        <div class="results-header">
            <h4><i class="fas fa-book-open"></i> Available Courses</h4>
            <p class="mb-0">Courses offered by the university</p>
        </div>
        
        <div class="p-4">
            <?php if (empty($courses)): ?>
                <p class="text-muted">No courses available at the moment.</p>
            <?php else: ?>
                <div class="row">
                    <?php foreach ($courses as $course): ?>
                        <div class="col-md-6 mb-3">
                            <div class="card h-100">
                                <div class="card-body">
                                    <h6 class="card-title text-primary"><?php echo htmlspecialchars($course['course_code']); ?></h6>
                                    <p class="card-text"><?php echo htmlspecialchars($course['course_name']); ?></p>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>