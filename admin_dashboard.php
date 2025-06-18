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

// Initialize all variables with default values
$totalStudents = 0;
$totalLecturers = 0;
$totalDepartments = 0;
$totalCourses = 0;
$totalFeesCollected = 0;
$totalFeesExpected = 0;
$totalOutstanding = 0;
$pendingFeeRecords = 0;
$partialFeeRecords = 0;
$paidFeeRecords = 0;
$pendingAdmissions = 0;
$recentActivities = [];

// Fetch all dashboard statistics
try {
    // Total Students
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM studrec WHERE role = 'student'");
    $totalStudents = $stmt->fetch()['total'];
    
    // Total Lecturers
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM lecturers WHERE status = 'active'");
        $totalLecturers = $stmt->fetch()['total'];
    } catch (PDOException $e) {
        $totalLecturers = 0;
    }
    
    // Total Departments
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM departments");
    $totalDepartments = $stmt->fetch()['total'];
    
    // Total Courses
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM courses");
    $totalCourses = $stmt->fetch()['total'];
    
    // Fee Statistics
    try {
        // Total fees collected
        $stmt = $pdo->query("SELECT SUM(amount_paid) as total FROM fees WHERE payment_status IN ('paid', 'partial')");
        $totalFeesCollected = $stmt->fetch()['total'] ?? 0;
        
        // Total fees expected
        $stmt = $pdo->query("SELECT SUM(total_amount) as total FROM fees");
        $totalFeesExpected = $stmt->fetch()['total'] ?? 0;
        
        // Outstanding balance
        $stmt = $pdo->query("SELECT SUM(balance) as total FROM fees WHERE payment_status != 'paid'");
        $totalOutstanding = $stmt->fetch()['total'] ?? 0;
        
        // Fee record counts
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM fees WHERE payment_status = 'pending'");
        $pendingFeeRecords = $stmt->fetch()['total'] ?? 0;
        
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM fees WHERE payment_status = 'partial'");
        $partialFeeRecords = $stmt->fetch()['total'] ?? 0;
        
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM fees WHERE payment_status = 'paid'");
        $paidFeeRecords = $stmt->fetch()['total'] ?? 0;
        
    } catch (PDOException $e) {
        // Fees table doesn't exist or has different structure
        error_log("Fee table error: " . $e->getMessage());
    }
    
    // Pending Admissions
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM admissions WHERE status = 'pending'");
        $pendingAdmissions = $stmt->fetch()['total'];
    } catch (PDOException $e) {
        $pendingAdmissions = 0;
    }
    
    // Recent Activities
    $recentActivities = [];
    
    // Recent student registrations
    try {
        $stmt = $pdo->query("
            SELECT 
                CONCAT(FIRST_NAME, ' ', LAST_NAME) as name, 
                'student_registration' as type,
                'New student enrolled' as activity,
                ID as ref_id
            FROM studrec 
            WHERE role = 'student'
            ORDER BY ID DESC 
            LIMIT 3
        ");
        $studentActivities = $stmt->fetchAll();
        $recentActivities = array_merge($recentActivities, $studentActivities);
    } catch (PDOException $e) {
        error_log("Student activities error: " . $e->getMessage());
    }
    
    // Recent admissions
    try {
        $stmt = $pdo->query("
            SELECT 
                CONCAT(first_name, ' ', last_name) as name, 
                'admission' as type,
                CASE 
                    WHEN status = 'pending' THEN 'New admission application'
                    WHEN status = 'admitted' THEN 'Student admitted'
                    ELSE 'Application updated'
                END as activity,
                admission_id as ref_id
            FROM admissions 
            ORDER BY admission_id DESC 
            LIMIT 2
        ");
        $admissionActivities = $stmt->fetchAll();
        $recentActivities = array_merge($recentActivities, $admissionActivities);
    } catch (PDOException $e) {
        error_log("Admission activities error: " . $e->getMessage());
    }
    
    // Recent lecturer additions
    try {
        $stmt = $pdo->query("
            SELECT 
                CONCAT(first_name, ' ', last_name) as name, 
                'lecturer_added' as type,
                'New lecturer added' as activity,
                lecturer_id as ref_id
            FROM lecturers 
            ORDER BY lecturer_id DESC 
            LIMIT 2
        ");
        $lecturerActivities = $stmt->fetchAll();
        $recentActivities = array_merge($recentActivities, $lecturerActivities);
    } catch (PDOException $e) {
        error_log("Lecturer activities error: " . $e->getMessage());
    }
    
    // Recent fee activities
    try {
        $stmt = $pdo->query("
            SELECT 
                student_name as name, 
                'fee_record' as type,
                CASE 
                    WHEN payment_status = 'pending' THEN 'Fee record generated'
                    WHEN payment_status = 'partial' THEN 'Partial payment received'
                    WHEN payment_status = 'paid' THEN 'Fee fully paid'
                    ELSE 'Fee record updated'
                END as activity,
                fee_id as ref_id
            FROM fees 
            ORDER BY fee_id DESC 
            LIMIT 3
        ");
        $feeActivities = $stmt->fetchAll();
        $recentActivities = array_merge($recentActivities, $feeActivities);
    } catch (PDOException $e) {
        error_log("Fee activities error: " . $e->getMessage());
    }
    
    // Shuffle and limit activities
    if (!empty($recentActivities)) {
        shuffle($recentActivities);
        $recentActivities = array_slice($recentActivities, 0, 6);
    }
    
} catch (PDOException $e) {
    error_log("Dashboard query error: " . $e->getMessage());
    // Variables are already initialized with default values above
}

// Get current page for active menu
$currentPage = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>School Management System - Admin Dashboard</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #6B8E23;
            min-height: 100vh;
        }

        .dashboard-container {
            display: flex;
            min-height: calc(100vh - 200px);
            margin-top: 20px;
        }

        /* Sidebar */
        .sidebar {
            width: 250px;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            padding: 20px;
            border-right: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 15px 0 0 15px;
            margin-left: 20px;
        }

        .sidebar-logo {
            text-align: center;
            margin-bottom: 20px;
            color: white;
        }

        .sidebar-logo h3 {
            font-size: 20px;
            margin-bottom: 5px;
        }

        .sidebar-logo p {
            font-size: 11px;
            opacity: 0.8;
        }

        .admin-profile {
            display: flex;
            align-items: center;
            margin-bottom: 30px;
            padding: 15px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
        }

       
        .admin-info h4 {
            color: white;
            font-size: 14px;
        }

        .admin-info p {
            color: rgba(255, 255, 255, 0.7);
            font-size: 12px;
        }

        .nav-menu {
            list-style: none;
        }

        .nav-menu li {
            margin-bottom: 5px;
        }

        .nav-menu a {
            display: flex;
            align-items: center;
            padding: 12px 15px;
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .nav-menu a:hover, .nav-menu a.active {
            background: rgba(255, 255, 255, 0.2);
            color: white;
        }

        .nav-menu i {
            margin-right: 10px;
            width: 20px;
        }

        /* Main Content */
        .main-content {
            flex: 1;
            padding: 30px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 0 15px 15px 0;
            margin-right: 20px;
        }

        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            background: rgba(255, 255, 255, 0.1);
            padding: 20px;
            border-radius: 10px;
            backdrop-filter: blur(10px);
        }

        .dashboard-title {
            color: white;
            font-size: 28px;
            font-weight: 600;
        }

        .dashboard-date {
            color: rgba(255, 255, 255, 0.8);
            font-size: 14px;
        }

        /* Enhanced Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.95);
            padding: 20px;
            border-radius: 15px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(10px);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            position: relative;
            overflow: hidden;
            cursor: pointer;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.2);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--card-color), var(--card-color-light));
        }

        .stat-card.blue { --card-color: #3498db; --card-color-light: #5dade2; }
        .stat-card.green { --card-color: #2ecc71; --card-color-light: #58d68d; }
        .stat-card.orange { --card-color: #f39c12; --card-color-light: #f8c471; }
        .stat-card.red { --card-color: #e74c3c; --card-color-light: #ec7063; }
        .stat-card.purple { --card-color: #9b59b6; --card-color-light: #bb8fce; }
        .stat-card.teal { --card-color: #1abc9c; --card-color-light: #5dccb4; }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .stat-title {
            font-size: 12px;
            color: #666;
            font-weight: 500;
            text-transform: uppercase;
        }

        .stat-icon {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 16px;
        }

        .stat-value {
            font-size: 28px;
            font-weight: 700;
            color: #333;
            margin-bottom: 5px;
        }

        .stat-change {
            font-size: 11px;
            color: #666;
        }

        .stat-change.positive {
            color: #2ecc71;
        }

        .stat-change.negative {
            color: #e74c3c;
        }

        /* Recent Activity */
        .recent-activity {
            background: rgba(255, 255, 255, 0.95);
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(10px);
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
        }

        .activity-item {
            display: flex;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #eee;
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        .activity-icon {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 12px;
            font-size: 12px;
            color: white;
        }

        .activity-content {
            flex: 1;
        }

        .activity-text {
            font-size: 13px;
            color: #333;
            margin-bottom: 2px;
        }

        .activity-time {
            font-size: 11px;
            color: #666;
        }

        /* Custom button styles */
        .view-all-btn {
            background: #6B8E23; 
            color: white; 
            border: none; 
            padding: 8px 15px; 
            border-radius: 5px; 
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 12px;
        }

        .view-all-btn:hover {
            background: #556B2F;
            transform: translateY(-1px);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .dashboard-container {
                flex-direction: column;
                margin: 10px;
            }
            
            .sidebar {
                width: 100%;
                margin-left: 0;
                border-radius: 15px;
                margin-bottom: 20px;
            }
            
            .main-content {
                margin-right: 0;
                border-radius: 15px;
            }
            
            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            }
        }
        .admin-avatar {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    overflow: hidden;
    background: #f0f0f0;
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
}

.admin-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    border-radius: 50%;
    position: absolute;
    top: 0;
    left: 0;
}

/* Remove any text inside the avatar */
.admin-avatar::before,
.admin-avatar::after {
    display: none;
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
                <div class="col-md-6 text-end d-flex align-items-center justify-content-end">
                    <input type="text" class="search-box" placeholder="Search...">
                    <div class="auth-toggle">
                        <span class="text-white me-3">
                            <i class="fas fa-user-shield"></i> 
                            Welcome, <?php echo htmlspecialchars($_SESSION['admin_name']); ?>
                        </span>
                        <a href="admin_logout.php" class="auth-btn text-danger">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Header -->
    <div class="main-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-12">
                    <div class="logo-section">
                        <div class="logo">
                            <i class="fas fa-university"></i>
                        </div>
                        <div class="university-name">
                            <h1>OSUN STATE UNIVERSITY</h1>
                            <p class="university-tagline">School Management System - Admin Dashboard</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="dashboard-container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-logo">
                <h3><i class="fas fa-cogs"></i> SMS Admin</h3>
                <p>Management Console</p>
            </div>
            
            <div class="admin-profile">
                <div class="admin-avatar">
                    <img src="uploads/newme1.jpg" alt="Admin Photo">
                </div>
                <div class="admin-info">
                    <h4><?php echo htmlspecialchars($_SESSION['admin_name']); ?></h4>
                    <p>Administrator</p>
                </div>
            </div>
            
            <ul class="nav-menu">
                <li><a href="admin_dashboard.php" class="<?php echo ($currentPage == 'admin_dashboard.php') ? 'active' : ''; ?>">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a></li>
                <li><a href="manage_students.php" class="<?php echo ($currentPage == 'manage_students.php') ? 'active' : ''; ?>">
                    <i class="fas fa-user-graduate"></i> Students
                </a></li>
                <li><a href="manage_lecturers.php" class="<?php echo ($currentPage == 'manage_lecturers.php') ? 'active' : ''; ?>">
                    <i class="fas fa-chalkboard-teacher"></i> Lecturers
                </a></li>
                <li><a href="manage_results.php" class="<?php echo ($currentPage == 'manage_results.php') ? 'active' : ''; ?>">
                    <i class="fas fa-clipboard-list"></i>  Results Management</a>
                </a></li>
                <li><a href="manage_departments.php" class="<?php echo ($currentPage == 'manage_departments.php') ? 'active' : ''; ?>">
                    <i class="fas fa-building"></i> Departments
                </a></li>
                <li><a href="manage_courses.php" class="<?php echo ($currentPage == 'manage_courses.php') ? 'active' : ''; ?>">
                    <i class="fas fa-graduation-cap"></i> Courses
                </a></li>
                <li><a href="manage_fees.php" class="<?php echo ($currentPage == 'manage_fees.php') ? 'active' : ''; ?>">
                    <i class="fas fa-money-bill-wave"></i> Fees
                </a></li>
                <li><a href="manage_admissions.php" class="<?php echo ($currentPage == 'manage_admissions.php') ? 'active' : ''; ?>">
                    <i class="fas fa-clipboard-list"></i> Admissions
                </a></li>
                <li><a href="admission_reports.php" class="<?php echo ($currentPage == 'admission_reports.php') ? 'active' : ''; ?>">
                    <i class="fas fa-chart-bar"></i>Admission Reports
                </a></li>
                
                <li><a  class="<?php echo ($currentPage == 'settings.php') ? 'active' : ''; ?>">
                    <i class="fas fa-cog"></i> Settings
                </a></li>
                <li><a href="admin_logout.php">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a></li>
            </ul>
        </div>
        
        <!-- Main Content -->
        <div class="main-content">
            <div class="dashboard-header">
                <div>
                    <h1 class="dashboard-title"><i class="fas fa-chart-line"></i> University Overview</h1>
                    <p class="dashboard-date"><?php echo date('l, F j, Y g:i A'); ?></p>
                </div>
            </div>
            
            <!-- Enhanced Stats Grid -->
            <div class="stats-grid">
                <div class="stat-card blue" onclick="location.href='manage_students.php'" title="View all students">
                    <div class="stat-header">
                        <span class="stat-title">Total Students</span>
                        <div class="stat-icon" style="background: #3498db;">
                            <i class="fas fa-user-graduate"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?php echo number_format($totalStudents); ?></div>
                    <div class="stat-change positive">Currently enrolled</div>
                </div>
                
                <div class="stat-card green" onclick="location.href='manage_lecturers.php'" title="View all lecturers">
                    <div class="stat-header">
                        <span class="stat-title">Total Lecturers</span>
                        <div class="stat-icon" style="background: #2ecc71;">
                            <i class="fas fa-chalkboard-teacher"></i>
                        </div>
                    </div>
                     <div class="stat-value"><?php echo number_format($totalLecturers); ?></div>
                    <div class="stat-change positive"><?php echo $totalLecturers > 0 ? 'Active faculty' : 'Setup required'; ?></div>
                </div>
                
                <div class="stat-card purple" onclick="location.href='manage_departments.php'" title="View all departments">
                    <div class="stat-header">
                        <span class="stat-title">Departments</span>
                        <div class="stat-icon" style="background: #9b59b6;">
                            <i class="fas fa-building"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?php echo number_format($totalDepartments); ?></div>
                    <div class="stat-change positive">Academic units</div>
                </div>
                
                <div class="stat-card teal" onclick="location.href='manage_courses.php'" title="View all courses">
                    <div class="stat-header">
                        <span class="stat-title">Total Courses</span>
                        <div class="stat-icon" style="background: #1abc9c;">
                            <i class="fas fa-book"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?php echo number_format($totalCourses); ?></div>
                    <div class="stat-change positive"><?php echo $totalCourses > 0 ? 'Available courses' : 'Setup required'; ?></div>
                </div>
                
                
                <!-- Total Fees Expected Card -->
                <div class="stat-card blue" onclick="location.href='manage_fees.php'" title="View total fees expected">
                    <div class="stat-header">
                        <span class="stat-title">Total Fees Expected</span>
                        <div class="stat-icon" style="background: #3498db;">
                            <i class="fas fa-chart-line"></i>
                        </div>
                    </div>
                    <div class="stat-value">₦<?php echo number_format($totalFeesExpected, 2); ?></div>
                    <div class="stat-change neutral">From <?php echo number_format($totalStudents); ?> students</div>
                </div>

                <!-- Outstanding Balance Card -->
                <div class="stat-card red" onclick="location.href='manage_fees.php?status=pending'" title="View pending payments">
                    <div class="stat-header">
                        <span class="stat-title">Outstanding Balance</span>
                        <div class="stat-icon" style="background: #e74c3c;">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                    </div>
                    <div class="stat-value">₦<?php echo number_format($totalOutstanding, 2); ?></div>
                    <div class="stat-change negative">
                        <?php echo $pendingFeeRecords; ?> pending payments
                    </div>
                </div>

                <!-- Collection Rate Card -->
                <div class="stat-card green" onclick="location.href='reports/fee_reports.php'" title="View fee collection reports">
                    <div class="stat-header">
                        <span class="stat-title">Collection Rate</span>
                    <div class="stat-icon" style="background: #27ae60;">
                    <i class="fas fa-percentage"></i>
                </div>
            </div>
            <div class="stat-value">
                <?php 
                    $collectionRate = $totalFeesExpected > 0 ? ($totalFeesCollected / $totalFeesExpected) * 100 : 0;
                    echo number_format($collectionRate, 1) . '%'; 
                ?>
            </div>
            <div class="stat-change <?php echo $collectionRate > 50 ? 'positive' : ($collectionRate > 0 ? 'neutral' : 'negative'); ?>">
                <?php 
                    if ($collectionRate == 0) {
                    echo 'No collections yet';} elseif ($collectionRate < 25) {echo 'Low collection rate';} elseif ($collectionRate < 75) {echo 'Moderate collection';
                    } else {
                    echo 'Good collection rate';
                    }
                ?>
            </div>
        </div>
        <div class="stat-card red" onclick="location.href='manage_admissions.php'" title="View pending admissions">
                    <div class="stat-header">
                        <span class="stat-title">Pending Admissions</span>
                        <div class="stat-icon" style="background: #e74c3c;">
                            <i class="fas fa-clipboard-list"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?php echo number_format($pendingAdmissions); ?></div>
                    <div class="stat-change"><?php echo $pendingAdmissions > 0 ? 'Awaiting review' : 'All processed'; ?></div>
                </div>
            </div>
            
            <!-- Recent Activity -->
            <div class="recent-activity">
                <div class="activity-header">
                    <h3 class="activity-title"><i class="fas fa-history"></i> Recent University Activity</h3>
                    <button class="view-all-btn" onclick="location.href='activity_log.php'">
                        View All
                    </button>
                </div>
                
                <div id="activityList">
                    <?php if (!empty($recentActivities)): ?>
                        <?php foreach ($recentActivities as $activity): ?>
                            <div class="activity-item">
                                <div class="activity-icon" style="background: 
                                    <?php 
                                    switch($activity['type']) {
                                        case 'student_registration': echo '#2ecc71'; break;
                                        case 'admission': echo '#3498db'; break;
                                        case 'lecturer_added': echo '#9b59b6'; break;
                                        default: echo '#95a5a6';
                                    }
                                    ?>;">
                                    <i class="fas 
                                        <?php 
                                        switch($activity['type']) {
                                            case 'student_registration': echo 'fa-user-graduate'; break;
                                            case 'admission': echo 'fa-clipboard-list'; break;
                                            case 'lecturer_added': echo 'fa-chalkboard-teacher'; break;
                                            default: echo 'fa-info';
                                        }
                                        ?>"></i>
                                </div>
                                <div class="activity-content">
                                    <div class="activity-text"><?php echo htmlspecialchars($activity['activity'] . ': ' . $activity['name']); ?></div>
                                    <div class="activity-time">Reference ID: #<?php echo $activity['ref_id']; ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="activity-item">
                            <div class="activity-icon" style="background: #95a5a6;">
                                <i class="fas fa-info"></i>
                            </div>
                            <div class="activity-content">
                                <div class="activity-text">No recent activity found</div>
                                <div class="activity-time">System is ready for use</div>
                            </div>
                        </div>
                    <?php endif; ?>
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
        // Update current date and time
        function updateDateTime() {
            const now = new Date();
            const options = { 
                weekday: 'long', 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            };
            document.querySelector('.dashboard-date').textContent = now.toLocaleDateString('en-US', options);
        }

        // Update time every minute
        updateDateTime();
        setInterval(updateDateTime, 60000);
    </script>
</body>
</html>