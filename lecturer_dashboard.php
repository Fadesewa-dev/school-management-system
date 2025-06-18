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

// Get lecturer details with department
try {
    $stmt = $pdo->prepare("
        SELECT l.*, d.department_name 
        FROM lecturers l 
        LEFT JOIN departments d ON l.department_id = d.department_id 
        WHERE l.lecturer_id = ?
    ");
    $stmt->execute([$_SESSION['lecturer_id']]);
    $lecturer = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$lecturer) {
        // Lecturer not found, redirect to login
        header("Location: lecturer_login.php");
        exit;
    }
} catch (PDOException $e) {
    die("Error getting lecturer details: " . $e->getMessage());
}
// Get lecturer stats
// Get lecturer stats
$lecturer_id = $_SESSION['lecturer_id'];
$stats = [
    'my_courses' => 0,
    'results_uploaded' => 0,
    'pending_results' => 0,
    'my_students' => 0
];

try {
    // Count courses assigned to this lecturer (if courses table has lecturer_id)
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM courses WHERE lecturer_id = ?");
        $stmt->execute([$lecturer_id]);
        $stats['my_courses'] = $stmt->fetch()['total'];
    } catch (PDOException $e) {
        // If no lecturer_id column, show all courses
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM courses");
        $stats['my_courses'] = $stmt->fetch()['total'];
    }
    
    // Count students from studrec table (same as admin dashboard)
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM studrec WHERE role = 'student'");
    $stats['my_students'] = $stmt->fetch()['total'];
    
    // For now, set results to placeholder values
   // Count actual results uploaded by this lecturer
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM results WHERE lecturer_id = ?");
    $stmt->execute([$lecturer_id]);
    $stats['results_uploaded'] = $stmt->fetch()['total'];
} catch (PDOException $e) {
    // If results table doesn't exist or has issues, default to 0
    $stats['results_uploaded'] = 0;
}

// Count pending results (courses without results)
try {
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT c.course_id) as total 
        FROM courses c 
        LEFT JOIN results r ON c.course_id = r.course_id AND r.lecturer_id = ?
        WHERE c.lecturer_id = ? AND r.course_id IS NULL
    ");
    $stmt->execute([$lecturer_id, $lecturer_id]);
    $stats['pending_results'] = $stmt->fetch()['total'];
} catch (PDOException $e) {
    $stats['pending_results'] = $stats['my_courses'];
}
} catch (PDOException $e) {
    // Set default values if database query fails
}


// Recent activities for this lecturer
// Recent activities for this lecturer - simplified for now
$recentActivities = [
    [
        'course_name' => 'Course Management',
        'type' => 'system',
        'activity' => 'Lecturer dashboard accessed',
        'ref_id' => 1,
        'created_at' => date('Y-m-d H:i:s')
    ]
];
$currentPage = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lecturer Dashboard - UNIOSUN</title>
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

        .university-name h1 {
            font-size: 32px;
            font-weight: 700;
            margin: 0;
            background: linear-gradient(45deg, #ffffff, #f0f8ff);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .university-tagline {
            font-size: 14px;
            opacity: 0.9;
            margin: 0;
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

        .lecturer-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.3);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            font-size: 24px;
        }

        .lecturer-info h4 {
            margin: 0;
            font-size: 18px;
            font-weight: 600;
        }

        .lecturer-info p {
            margin: 5px 0 0 0;
            opacity: 0.9;
            font-size: 14px;
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

        .dashboard-header {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #e9ecef;
        }

        .dashboard-title {
            color: #333;
            font-size: 28px;
            font-weight: 600;
            margin: 0;
        }

        .dashboard-subtitle {
            color: #666;
            font-size: 14px;
            margin: 5px 0 0 0;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
            border-left: 4px solid var(--accent-color);
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
        }

        .stat-card.blue { --accent-color: #3498db; }
        .stat-card.green { --accent-color: #2ecc71; }
        .stat-card.orange { --accent-color: #f39c12; }
        .stat-card.purple { --accent-color: #9b59b6; }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .stat-title {
            font-size: 12px;
            color: #666;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .stat-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--accent-color);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
        }

        .stat-value {
            font-size: 32px;
            font-weight: 700;
            color: #333;
            margin-bottom: 5px;
        }

        .stat-description {
            font-size: 13px;
            color: #666;
        }

        .welcome-card {
            background: linear-gradient(135deg, #6B8E23, #8FBC8F);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            text-align: center;
        }

        .welcome-card h3 {
            margin: 0 0 10px 0;
            font-size: 24px;
        }

        .welcome-card p {
            margin: 0;
            opacity: 0.9;
        }

        .recent-activity {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 15px;
            margin-top: 30px;
        }

        .activity-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .activity-title {
            font-size: 20px;
            font-weight: 600;
            color: #333;
            margin: 0;
        }

        .activity-item {
            display: flex;
            align-items: center;
            padding: 15px 0;
            border-bottom: 1px solid #dee2e6;
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #6B8E23;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            font-size: 14px;
        }

        .activity-content {
            flex: 1;
        }

        .activity-text {
            font-size: 14px;
            color: #333;
            margin: 0 0 5px 0;
        }

        .activity-time {
            font-size: 12px;
            color: #666;
            margin: 0;
        }

        @media (max-width: 768px) {
            .dashboard-container {
                flex-direction: column;
            }
            
            .sidebar {
                width: 100%;
            }
            
            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
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
                    <i class="fas fa-chalkboard-teacher"></i> Lecturer Portal
                </div>
                <div class="col-md-6 text-end">
                    <span class="me-3">
                        <i class="fas fa-user"></i> 
                        Welcome, <?php echo htmlspecialchars($_SESSION['lecturer_name']); ?>
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
                    <h1>OSUN STATE UNIVERSITY</h1>
                    <p class="university-tagline">Lecturer Portal - Academic Management System</p>
                </div>
            </div>
        </div>
    </div>

    <div class="dashboard-container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="lecturer-profile">
                <div class="lecturer-avatar">
                    <i class="fas fa-user-tie"></i>
                </div>
                <div class="lecturer-info">
                    <h4><?php echo htmlspecialchars($_SESSION['lecturer_name']); ?></h4>
                    <?php echo htmlspecialchars($lecturer['department_name'] ?? 'Not Assigned'); ?>
                </div>
            </div>
            
            <ul class="nav-menu">
                <li><a href="lecturer_dashboard.php" class="<?php echo ($currentPage == 'lecturer_dashboard.php') ? 'active' : ''; ?>">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a></li>
                <li><a href="lecturer_courses.php">
                    <i class="fas fa-book"></i> My Courses
                </a></li>
                <li><a href="lecturer_results.php">
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
            <div class="welcome-card">
                <h3><i class="fas fa-hand-wave"></i> Welcome back, <?php echo htmlspecialchars($_SESSION['lecturer_name']); ?>!</h3>
                <p>Ready to manage your courses and upload student results</p>
            </div>
            
            <div class="dashboard-header">
                <h1 class="dashboard-title"><i class="fas fa-chart-line"></i> My Dashboard</h1>
                <p class="dashboard-subtitle"><?php echo date('l, F j, Y g:i A'); ?></p>
            </div>
            
            <!-- Stats Grid -->
            <div class="stats-grid">
                <div class="stat-card blue">
                    <div class="stat-header">
                        <span class="stat-title">My Courses</span>
                        <div class="stat-icon">
                            <i class="fas fa-book"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?php echo $stats['my_courses']; ?></div>
                    <div class="stat-description">Courses assigned to me</div>
                </div>
                
                <div class="stat-card green">
                    <div class="stat-header">
                        <span class="stat-title">Results Uploaded</span>
                        <div class="stat-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?php echo $stats['results_uploaded']; ?></div>
                    <div class="stat-description">Total results submitted</div>
                </div>
                
                <div class="stat-card orange">
                    <div class="stat-header">
                        <span class="stat-title">My Students</span>
                        <div class="stat-icon">
                            <i class="fas fa-users"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?php echo $stats['my_students']; ?></div>
                    <div class="stat-description">Students in my courses</div>
                </div>
                
                <div class="stat-card purple">
                    <div class="stat-header">
                        <span class="stat-title">This Semester</span>
                        <div class="stat-icon">
                            <i class="fas fa-calendar"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?php echo date('Y'); ?></div>
                    <div class="stat-description">Academic Year</div>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div class="row">
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body text-center p-4">
                            <div class="mb-3">
                                <i class="fas fa-upload fa-3x text-primary"></i>
                            </div>
                            <h5 class="card-title">Upload Results</h5>
                            <p class="card-text text-muted">Upload student results for your courses</p>
                            <a href="lecturer_results.php" class="btn btn-primary">
                                <i class="fas fa-plus"></i> Upload Now
                            </a>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body text-center p-4">
                            <div class="mb-3">
                                <i class="fas fa-eye fa-3x text-success"></i>
                            </div>
                            <h5 class="card-title">View My Courses</h5>
                            <p class="card-text text-muted">See all courses assigned to you</p>
                            <a href="lecturer_courses.php" class="btn btn-success">
                                <i class="fas fa-book"></i> View Courses
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Recent Activity -->
            <?php if (!empty($recentActivities)): ?>
            <div class="recent-activity">
                <div class="activity-header">
                    <h3 class="activity-title"><i class="fas fa-history"></i> Recent Activity</h3>
                </div>
                
                <?php foreach ($recentActivities as $activity): ?>
                <div class="activity-item">
                    <div class="activity-icon">
                        <i class="fas fa-clipboard-check"></i>
                    </div>
                    <div class="activity-content">
                        <p class="activity-text"><?php echo htmlspecialchars($activity['activity'] . ': ' . $activity['course_name']); ?></p>
                        <p class="activity-time">
                            <?php echo $activity['created_at'] ? date('M j, Y g:i A', strtotime($activity['created_at'])) : 'Recently'; ?>
                        </p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>