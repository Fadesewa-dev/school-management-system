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

// Get filter parameters
$date_from = $_GET['date_from'] ?? date('Y-01-01');
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$department_filter = $_GET['department'] ?? '';
$status_filter = $_GET['status'] ?? '';

// Build WHERE clause for filters
$where_conditions = ["DATE(a.created_at) BETWEEN ? AND ?"];
$params = [$date_from, $date_to];

if (!empty($department_filter)) {
    $where_conditions[] = "a.first_choice_department = ?";
    $params[] = $department_filter;
}

if (!empty($status_filter)) {
    $where_conditions[] = "a.admission_status = ?";
    $params[] = $status_filter;
}

$where_sql = "WHERE " . implode(" AND ", $where_conditions);

// Get departments for filters
try {
    $dept_stmt = $pdo->query("SELECT department_id, department_name, department_code FROM departments ORDER BY department_name");
    $departments = $dept_stmt->fetchAll();
} catch (PDOException $e) {
    $departments = [];
}

// 1. OVERVIEW STATISTICS
try {
    // Total applications in period
    $total_stmt = $pdo->prepare("SELECT COUNT(*) as total FROM admissions a $where_sql");
    $total_stmt->execute($params);
    $total_applications = $total_stmt->fetchColumn();

    // Status breakdown
    $status_stmt = $pdo->prepare("
        SELECT admission_status, COUNT(*) as count 
        FROM admissions a $where_sql 
        GROUP BY admission_status
    ");
    $status_stmt->execute($params);
    $status_breakdown = $status_stmt->fetchAll();

    // Applications by department
    $dept_stmt = $pdo->prepare("
        SELECT d.department_name, d.department_code, COUNT(*) as count 
        FROM admissions a 
        LEFT JOIN departments d ON a.first_choice_department = d.department_id 
        $where_sql 
        GROUP BY a.first_choice_department, d.department_name, d.department_code 
        ORDER BY count DESC
    ");
    $dept_stmt->execute($params);
    $department_breakdown = $dept_stmt->fetchAll();

    // Gender distribution
    $gender_stmt = $pdo->prepare("
        SELECT gender, COUNT(*) as count 
        FROM admissions a $where_sql 
        GROUP BY gender
    ");
    $gender_stmt->execute($params);
    $gender_breakdown = $gender_stmt->fetchAll();

    // State distribution (top 10)
    $state_stmt = $pdo->prepare("
        SELECT state_of_origin, COUNT(*) as count 
        FROM admissions a $where_sql 
        GROUP BY state_of_origin 
        ORDER BY count DESC 
        LIMIT 10
    ");
    $state_stmt->execute($params);
    $state_breakdown = $state_stmt->fetchAll();

    // Monthly trends (last 12 months)
    $trends_stmt = $pdo->query("
        SELECT 
            DATE_FORMAT(created_at, '%Y-%m') as month,
            COUNT(*) as applications,
            SUM(CASE WHEN admission_status = 'approved' THEN 1 ELSE 0 END) as approved,
            SUM(CASE WHEN admission_status = 'rejected' THEN 1 ELSE 0 END) as rejected,
            SUM(CASE WHEN admission_status = 'pending' THEN 1 ELSE 0 END) as pending
        FROM admissions 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY month ASC
    ");
    $monthly_trends = $trends_stmt->fetchAll();

    // Source analysis
    $source_stmt = $pdo->prepare("
        SELECT 
            CASE WHEN student_id IS NOT NULL THEN 'Imported' ELSE 'Manual' END as source,
            COUNT(*) as count
        FROM admissions a $where_sql 
        GROUP BY CASE WHEN student_id IS NOT NULL THEN 'Imported' ELSE 'Manual' END
    ");
    $source_stmt->execute($params);
    $source_breakdown = $source_stmt->fetchAll();

    // Recent applications
    $recent_stmt = $pdo->prepare("
        SELECT a.*, d.department_name, d.department_code 
        FROM admissions a 
        LEFT JOIN departments d ON a.first_choice_department = d.department_id 
        $where_sql 
        ORDER BY a.created_at DESC 
        LIMIT 10
    ");
    $recent_stmt->execute($params);
    $recent_applications = $recent_stmt->fetchAll();

} catch (PDOException $e) {
    $error = "Error loading report data: " . $e->getMessage();
}

// Calculate percentages and rates
$status_data = [];
$status_colors = [
    'pending' => '#ffc107',
    'approved' => '#28a745', 
    'rejected' => '#dc3545',
    'under-review' => '#17a2b8'
];

foreach ($status_breakdown as $status) {
    $percentage = $total_applications > 0 ? round(($status['count'] / $total_applications) * 100, 1) : 0;
    $status_data[] = [
        'status' => $status['admission_status'] ?: 'pending',
        'count' => $status['count'],
        'percentage' => $percentage,
        'color' => $status_colors[$status['admission_status']] ?? '#6c757d'
    ];
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admission Reports - UNIOSUN Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="style.css">
    <style>
        body {
            background: linear-gradient(135deg, #F5F8F0, #E8F0E0);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
        }

      
        .main-content {
            max-width: 1600px;
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

        .report-card {
            background: linear-gradient(135deg, #ffffff, #f8fdf8);
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 8px 20px rgba(107, 142, 35, 0.1);
            border: 2px solid rgba(107, 142, 35, 0.1);
            transition: transform 0.3s ease;
        }

        .report-card:hover {
            transform: translateY(-5px);
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

        .stat-card.success { border-left-color: #28a745; }
        .stat-card.warning { border-left-color: #ffc107; }
        .stat-card.danger { border-left-color: #dc3545; }
        .stat-card.info { border-left-color: #17a2b8; }

        .stat-icon {
            font-size: 2.5rem;
            margin-bottom: 15px;
        }

        .stat-icon.success { color: #28a745; }
        .stat-icon.warning { color: #ffc107; }
        .stat-icon.danger { color: #dc3545; }
        .stat-icon.info { color: #17a2b8; }
        .stat-icon.primary { color: #6B8E23; }

        .chart-container {
            position: relative;
            height: 400px;
            margin-bottom: 20px;
        }

        .chart-container.small {
            height: 250px;
        }

        .filters-card {
            background: linear-gradient(135deg, #ffffff, #f8fdf8);
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 8px 20px rgba(107, 142, 35, 0.1);
            border: 2px solid rgba(107, 142, 35, 0.1);
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

        .table th {
            background: #6B8E23;
            color: white;
            border: none;
            font-weight: 600;
        }

        .table td {
            vertical-align: middle;
            border-color: rgba(107, 142, 35, 0.1);
        }

        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8em;
            font-weight: bold;
            text-transform: uppercase;
        }

        .status-pending { background: #ffc107; color: #212529; }
        .status-approved { background: #28a745; color: white; }
        .status-rejected { background: #dc3545; color: white; }
        .status-under-review { background: #17a2b8; color: white; }

        .export-buttons {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }

        @media print {
            .no-print { display: none !important; }
            .page-header, .report-card { box-shadow: none; border: 1px solid #ddd; }
        }

        .trend-indicator {
            font-size: 0.9em;
            margin-left: 10px;
        }

        .trend-up { color: #28a745; }
        .trend-down { color: #dc3545; }
        .trend-neutral { color: #6c757d; }
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

      <!-- Page Header -->
    <div class="page-header">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h1><i class="fas fa-chart-bar"></i> Admission Reports</h1>
                <p class="mb-0 text-muted">Comprehensive analysis of admission applications and trends</p>
            </div>
            <div class="col-md-4 text-end no-print">
                <div class="export-buttons">
                    <button class="btn btn-success btn-sm" onclick="exportToExcel()">
                        <i class="fas fa-file-excel"></i> Excel
                    </button>
                    <button class="btn btn-danger btn-sm" onclick="exportToPDF()">
                        <i class="fas fa-file-pdf"></i> PDF
                    </button>
                    <button class="btn btn-olive btn-sm" onclick="window.print()">
                        <i class="fas fa-print"></i> Print
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="filters-card no-print">
        <h5 class="mb-3"><i class="fas fa-filter"></i> Report Filters</h5>
        <form method="GET" action="">
            <div class="row">
                <div class="col-md-3">
                    <label class="form-label">Date From</label>
                    <input type="date" name="date_from" class="form-control" value="<?php echo $date_from; ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Date To</label>
                    <input type="date" name="date_to" class="form-control" value="<?php echo $date_to; ?>">
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
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="">All Status</option>
                        <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="approved" <?php echo $status_filter === 'approved' ? 'selected' : ''; ?>>Approved</option>
                        <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                        <option value="under-review" <?php echo $status_filter === 'under-review' ? 'selected' : ''; ?>>Under Review</option>
                    </select>
                </div>
                <div class="col-md-1">
                    <label class="form-label">&nbsp;</label>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-olive">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <!-- Overview Statistics -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="stat-card">
                <i class="fas fa-file-alt stat-icon primary"></i>
                <h3><?php echo number_format($total_applications); ?></h3>
                <p class="text-muted mb-0">Total Applications</p>
                <small class="text-muted">Period: <?php echo date('M j', strtotime($date_from)) . ' - ' . date('M j, Y', strtotime($date_to)); ?></small>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card success">
                <i class="fas fa-check-circle stat-icon success"></i>
                <h3><?php echo number_format(array_sum(array_column(array_filter($status_data, function($s) { return $s['status'] === 'approved'; }), 'count'))); ?></h3>
                <p class="text-muted mb-0">Approved</p>
                <small class="text-success">
                    <?php 
                    $approved_pct = 0;
                    foreach($status_data as $s) {
                        if($s['status'] === 'approved') $approved_pct = $s['percentage'];
                    }
                    echo $approved_pct . '% of total';
                    ?>
                </small>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card warning">
                <i class="fas fa-clock stat-icon warning"></i>
                <h3><?php echo number_format(array_sum(array_column(array_filter($status_data, function($s) { return $s['status'] === 'pending'; }), 'count'))); ?></h3>
                <p class="text-muted mb-0">Pending</p>
                <small class="text-warning">Awaiting Review</small>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card danger">
                <i class="fas fa-times-circle stat-icon danger"></i>
                <h3><?php echo number_format(array_sum(array_column(array_filter($status_data, function($s) { return $s['status'] === 'rejected'; }), 'count'))); ?></h3>
                <p class="text-muted mb-0">Rejected</p>
                <small class="text-danger">
                    <?php 
                    $rejected_pct = 0;
                    foreach($status_data as $s) {
                        if($s['status'] === 'rejected') $rejected_pct = $s['percentage'];
                    }
                    echo $rejected_pct . '% of total';
                    ?>
                </small>
            </div>
        </div>
    </div>

    <!-- Charts Row 1 -->
    <div class="row">
        <div class="col-md-6">
            <div class="report-card">
                <h5><i class="fas fa-chart-pie"></i> Application Status Distribution</h5>
                <div class="chart-container small">
                    <canvas id="statusChart"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="report-card">
                <h5><i class="fas fa-chart-bar"></i> Applications by Department</h5>
                <div class="chart-container small">
                    <canvas id="departmentChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Row 2 -->
    <div class="row">
        <div class="col-md-8">
            <div class="report-card">
                <h5><i class="fas fa-chart-line"></i> Application Trends (Last 12 Months)</h5>
                <div class="chart-container">
                    <canvas id="trendsChart"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="report-card">
                <h5><i class="fas fa-users"></i> Gender Distribution</h5>
                <div class="chart-container small">
                    <canvas id="genderChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Demographics and Source Analysis -->
    <div class="row">
        <div class="col-md-6">
            <div class="report-card">
                <h5><i class="fas fa-map-marker-alt"></i> Top States of Origin</h5>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>State</th>
                                <th>Applications</th>
                                <th>Percentage</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($state_breakdown as $state): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($state['state_of_origin']); ?></td>
                                    <td><?php echo number_format($state['count']); ?></td>
                                    <td>
                                        <div class="progress" style="height: 20px;">
                                            <div class="progress-bar bg-olive" style="width: <?php echo ($state['count'] / $total_applications) * 100; ?>%">
                                                <?php echo round(($state['count'] / $total_applications) * 100, 1); ?>%
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="report-card">
                <h5><i class="fas fa-source-fork"></i> Application Sources</h5>
                <div class="chart-container small">
                    <canvas id="sourceChart"></canvas>
                </div>
                <div class="mt-3">
                    <?php foreach ($source_breakdown as $source): ?>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="fw-bold"><?php echo $source['source']; ?></span>
                            <span class="badge bg-<?php echo $source['source'] === 'Imported' ? 'success' : 'warning'; ?>">
                                <?php echo number_format($source['count']); ?> applications
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Applications -->
    <div class="report-card">
        <h5><i class="fas fa-clock"></i> Recent Applications</h5>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>App Number</th>
                        <th>Name</th>
                        <th>Department</th>
                        <th>Status</th>
                        <th>Applied Date</th>
                        <th>Source</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_applications as $app): ?>
                        <tr>
                            <td><span class="badge bg-primary"><?php echo htmlspecialchars($app['application_number']); ?></span></td>
                            <td><?php echo htmlspecialchars($app['first_name'] . ' ' . $app['last_name']); ?></td>
                            <td>
                                <?php if ($app['department_name']): ?>
                                    <small><?php echo htmlspecialchars($app['department_code']); ?></small><br>
                                    <?php echo htmlspecialchars($app['department_name']); ?>
                                <?php else: ?>
                                    <span class="text-muted">Not Selected</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="status-badge status-<?php echo str_replace(' ', '-', strtolower($app['admission_status'] ?? 'pending')); ?>">
                                    <?php echo htmlspecialchars($app['admission_status'] ?? 'Pending'); ?>
                                </span>
                            </td>
                            <td><?php echo date('M j, Y', strtotime($app['created_at'])); ?></td>
                            <td>
                                <?php if (!empty($app['student_id'])): ?>
                                    <span class="badge bg-success"><i class="fas fa-download"></i> Imported</span>
                                <?php else: ?>
                                    <span class="badge bg-warning"><i class="fas fa-plus"></i> Manual</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Chart configurations
Chart.defaults.font.family = "'Segoe UI', Tahoma, Geneva, Verdana, sans-serif";
Chart.defaults.font.size = 12;

// Status Distribution Pie Chart
const statusCtx = document.getElementById('statusChart').getContext('2d');
new Chart(statusCtx, {
    type: 'doughnut',
    data: {
        labels: <?php echo json_encode(array_column($status_data, 'status')); ?>,
        datasets: [{
            data: <?php echo json_encode(array_column($status_data, 'count')); ?>,
            backgroundColor: <?php echo json_encode(array_column($status_data, 'color')); ?>,
            borderWidth: 2,
            borderColor: '#fff'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom'
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        const label = context.label || '';
                        const value = context.parsed;
                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                        const percentage = ((value / total) * 100).toFixed(1);
                        return `${label}: ${value} (${percentage}%)`;
                    }
                }
            }
        }
    }
});

// Department Bar Chart
const deptCtx = document.getElementById('departmentChart').getContext('2d');
new Chart(deptCtx, {
    type: 'horizontalBar',
    data: {
        labels: <?php echo json_encode(array_map(function($d) { return $d['department_code']; }, array_slice($department_breakdown, 0, 8))); ?>,
        datasets: [{
            label: 'Applications',
            data: <?php echo json_encode(array_column(array_slice($department_breakdown, 0, 8), 'count')); ?>,
            backgroundColor: 'rgba(107, 142, 35, 0.8)',
            borderColor: 'rgba(107, 142, 35, 1)',
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        indexAxis: 'y',
        plugins: {
            legend: {
                display: false
            }
        },
        scales: {
            x: {
                beginAtZero: true
            }
        }
    }
});

// Monthly Trends Line Chart
const trendsCtx = document.getElementById('trendsChart').getContext('2d');
new Chart(trendsCtx, {
    type: 'line',
    data: {
        labels: <?php echo json_encode(array_map(function($t) { return date('M Y', strtotime($t['month'] . '-01')); }, $monthly_trends)); ?>,
        datasets: [
            {
                label: 'Total Applications',
                data: <?php echo json_encode(array_column($monthly_trends, 'applications')); ?>,
                borderColor: '#6B8E23',
                backgroundColor: 'rgba(107, 142, 35, 0.1)',
                tension: 0.4,
                fill: true
            },
            {
                label: 'Approved',
                data: <?php echo json_encode(array_column($monthly_trends, 'approved')); ?>,
                borderColor: '#28a745',
                backgroundColor: 'rgba(40, 167, 69, 0.1)',
                tension: 0.4
            },
            {
                label: 'Rejected',
                data: <?php echo json_encode(array_column($monthly_trends, 'rejected')); ?>,
                borderColor: '#dc3545',
                backgroundColor: 'rgba(220, 53, 69, 0.1)',
                tension: 0.4
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'top'
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    stepSize: 1
                }
            }
        },
        interaction: {
            intersect: false,
            mode: 'index'
        }
    }
});

// Gender Distribution Pie Chart
const genderCtx = document.getElementById('genderChart').getContext('2d');
new Chart(genderCtx, {
    type: 'pie',
    data: {
        labels: <?php echo json_encode(array_column($gender_breakdown, 'gender')); ?>,
        datasets: [{
            data: <?php echo json_encode(array_column($gender_breakdown, 'count')); ?>,
            backgroundColor: ['#4e73df', '#e74a3b', '#36b9cc'],
            borderWidth: 2,
            borderColor: '#fff'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom'
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        const label = context.label || '';
                        const value = context.parsed;
                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                        const percentage = ((value / total) * 100).toFixed(1);
                        return `${label}: ${value} (${percentage}%)`;
                    }
                }
            }
        }
    }
});

// Source Distribution Pie Chart
const sourceCtx = document.getElementById('sourceChart').getContext('2d');
new Chart(sourceCtx, {
    type: 'doughnut',
    data: {
        labels: <?php echo json_encode(array_column($source_breakdown, 'source')); ?>,
        datasets: [{
            data: <?php echo json_encode(array_column($source_breakdown, 'count')); ?>,
            backgroundColor: ['#28a745', '#ffc107'],
            borderWidth: 2,
            borderColor: '#fff'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom'
            }
        }
    }
});

// Export Functions
function exportToExcel() {
    // Create a simple CSV export
    let csvContent = "data:text/csv;charset=utf-8,";
    
    // Add headers
    csvContent += "Application Number,Name,Email,Department,Status,Gender,State,Applied Date,Source\n";
    
    // Add data (you would need to pass the recent applications data to JavaScript)
    <?php foreach ($recent_applications as $app): ?>
    csvContent += "<?php echo $app['application_number']; ?>," +
                  "<?php echo addslashes($app['first_name'] . ' ' . $app['last_name']); ?>," +
                  "<?php echo $app['email']; ?>," +
                  "<?php echo addslashes($app['department_code'] ?? 'N/A'); ?>," +
                  "<?php echo $app['admission_status'] ?? 'pending'; ?>," +
                  "<?php echo $app['gender']; ?>," +
                  "<?php echo $app['state_of_origin']; ?>," +
                  "<?php echo date('Y-m-d', strtotime($app['created_at'])); ?>," +
                  "<?php echo !empty($app['student_id']) ? 'Imported' : 'Manual'; ?>\n";
    <?php endforeach; ?>
    
    const encodedUri = encodeURI(csvContent);
    const link = document.createElement("a");
    link.setAttribute("href", encodedUri);
    link.setAttribute("download", "admission_report_<?php echo date('Y-m-d'); ?>.csv");
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

function exportToPDF() {
    // Simple PDF export using print functionality
    window.print();
}

// Auto-refresh data every 5 minutes
setInterval(function() {
    // You can add AJAX call here to refresh statistics without page reload
    console.log('Auto-refresh triggered');
}, 300000);

// Add smooth scrolling for internal links
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
        e.preventDefault();
        const target = document.querySelector(this.getAttribute('href'));
        if (target) {
            target.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        }
    });
});

// Tooltip initialization for better UX
document.addEventListener('DOMContentLoaded', function() {
    // Initialize Bootstrap tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});

// Print styles optimization
window.addEventListener('beforeprint', function() {
    // Hide charts during print and show data tables instead
    document.querySelectorAll('.chart-container').forEach(container => {
        container.style.display = 'none';
    });
});

window.addEventListener('afterprint', function() {
    // Show charts again after print
    document.querySelectorAll('.chart-container').forEach(container => {
        container.style.display = 'block';
    });
});

// Add dynamic date validation
document.querySelector('input[name="date_from"]').addEventListener('change', function() {
    const dateTo = document.querySelector('input[name="date_to"]');
    if (dateTo.value && this.value > dateTo.value) {
        dateTo.value = this.value;
    }
});

document.querySelector('input[name="date_to"]').addEventListener('change', function() {
    const dateFrom = document.querySelector('input[name="date_from"]');
    if (dateFrom.value && this.value < dateFrom.value) {
        dateFrom.value = this.value;
    }
});
</script>

<!-- Custom CSS for print -->
<style media="print">
    @page {
        margin: 0.5in;
        size: A4;
    }
    
    body { 
        font-size: 12px; 
        line-height: 1.3;
    }
    
    .main-content {
        max-width: none;
        margin: 0;
        padding: 0;
    }
    
    .report-card {
        page-break-inside: avoid;
        margin-bottom: 20px;
    }
    
    .chart-container {
        height: 200px !important;
    }
    
    .table {
        font-size: 10px;
    }
    
    .stat-card {
        border: 1px solid #ddd;
        margin-bottom: 15px;
    }
    
    .page-header {
        border-bottom: 2px solid #6B8E23;
        margin-bottom: 20px;
    }
    
    .no-print {
        display: none !important;
    }
    
    /* Ensure proper page breaks */
    .row {
        page-break-inside: avoid;
    }
    
    h1, h2, h3, h4, h5 {
        page-break-after: avoid;
    }
</style>

</body>
</html>