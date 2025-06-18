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

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'generate_fees':
                    // Generate fees for students based on their department and level
                    $academic_session = $_POST['academic_session'];
                    $semester = $_POST['semester'];
                    $department = $_POST['department'];
                    $level = $_POST['level'];
                    
                    // Get fee structure
                    $stmt = $pdo->prepare("SELECT * FROM fee_structure WHERE department = ? AND level_year = ? AND academic_session = ? AND is_active = 1");
                    $stmt->execute([$department, $level, $academic_session]);
                    $feeStructure = $stmt->fetch();
                    
                    if ($feeStructure) {
                        // Get students from the specified department and level
                        $stmt = $pdo->prepare("SELECT * FROM studrec WHERE DEPARTMENT = ? AND LEVEL = ? AND role = 'student'");
                        $stmt->execute([$department, $level]);
                        $students = $stmt->fetchAll();
                        
                        $generated = 0;
                        foreach ($students as $student) {
                            // Check if fee already exists
                            $checkStmt = $pdo->prepare("SELECT fee_id FROM fees WHERE student_id = ? AND academic_session = ? AND semester = ?");
                            $checkStmt->execute([$student['STUDENT_ID'], $academic_session, $semester]);
                            
                            if (!$checkStmt->fetch()) {
                                // Calculate due date (3 months from now)
                                $due_date = date('Y-m-d', strtotime('+3 months'));
                                
                                // Insert fee record
                                $insertStmt = $pdo->prepare("
                                    INSERT INTO fees (
                                        student_id, student_name, department, level_year, semester, academic_session,
                                        tuition_fee, library_fee, lab_fee, sports_fee, medical_fee, accommodation_fee,
                                        examination_fee, development_fee, total_amount, balance, due_date, created_by
                                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                                ");
                                
                                $total = $feeStructure['total_fee'];
                                $insertStmt->execute([
                                    $student['STUDENT_ID'],
                                    $student['FIRST_NAME'] . ' ' . $student['LAST_NAME'],
                                    $department,
                                    $level,
                                    $semester,
                                    $academic_session,
                                    $feeStructure['tuition_fee'],
                                    $feeStructure['library_fee'],
                                    $feeStructure['lab_fee'],
                                    $feeStructure['sports_fee'],
                                    $feeStructure['medical_fee'],
                                    $feeStructure['accommodation_fee'],
                                    $feeStructure['examination_fee'],
                                    $feeStructure['development_fee'],
                                    $total,
                                    $total,
                                    $due_date,
                                    $_SESSION['admin_name']
                                ]);
                                $generated++;
                            }
                        }
                        $success = "Successfully generated fees for $generated students!";
                    } else {
                        $error = "No fee structure found for the selected criteria!";
                    }
                    break;
                    
                case 'record_payment':
                    $fee_id = $_POST['fee_id'];
                    $amount_paid = $_POST['amount_paid'];
                    $payment_method = $_POST['payment_method'];
                    $transaction_ref = $_POST['transaction_ref'];
                    $receipt_number = $_POST['receipt_number'];
                    $notes = $_POST['notes'];
                    
                    // Get current fee details
                    $stmt = $pdo->prepare("SELECT * FROM fees WHERE fee_id = ?");
                    $stmt->execute([$fee_id]);
                    $fee = $stmt->fetch();
                    
                    if ($fee) {
                        // Record payment
                        $stmt = $pdo->prepare("
                            INSERT INTO fee_payments (
                                fee_id, student_id, amount_paid, payment_method, transaction_ref,
                                payment_date, receipt_number, processed_by, notes
                            ) VALUES (?, ?, ?, ?, ?, NOW(), ?, ?, ?)
                        ");
                        $stmt->execute([
                            $fee_id, $fee['student_id'], $amount_paid, $payment_method,
                            $transaction_ref, $receipt_number, $_SESSION['admin_name'], $notes
                        ]);
                        
                        // Update fee record
                        $new_amount_paid = $fee['amount_paid'] + $amount_paid;
                        $new_balance = $fee['total_amount'] - $new_amount_paid;
                        
                        $payment_status = 'pending';
                        if ($new_balance == 0) {
                            $payment_status = 'paid';
                        } elseif ($new_amount_paid > 0) {
                            $payment_status = 'partial';
                        }
                        
                        $stmt = $pdo->prepare("
                            UPDATE fees SET 
                                amount_paid = ?, 
                                balance = ?, 
                                payment_status = ?,
                                payment_date = NOW()
                            WHERE fee_id = ?
                        ");
                        $stmt->execute([$new_amount_paid, $new_balance, $payment_status, $fee_id]);
                        
                        $success = "Payment recorded successfully! Receipt #: $receipt_number";
                    }
                    break;
            }
        }
    } catch (PDOException $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Fetch statistics
try {
    // Total fees
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM fees");
    $totalFees = $stmt->fetch()['total'];
    
    // Total collected
    $stmt = $pdo->query("SELECT SUM(amount_paid) as total FROM fees");
    $totalCollected = $stmt->fetch()['total'] ?? 0;
    
    // Outstanding balance
    $stmt = $pdo->query("SELECT SUM(balance) as total FROM fees WHERE balance > 0");
    $totalOutstanding = $stmt->fetch()['total'] ?? 0;
    
    // Overdue payments
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM fees WHERE due_date < CURDATE() AND balance > 0");
    $overduePayments = $stmt->fetch()['total'];
    
} catch (PDOException $e) {
    $totalFees = $totalCollected = $totalOutstanding = $overduePayments = 0;
}

// Fetch fees with filters
$whereClause = "WHERE 1=1";
$params = [];

if (isset($_GET['status']) && $_GET['status'] !== '') {
    $whereClause .= " AND payment_status = ?";
    $params[] = $_GET['status'];
}

if (isset($_GET['department']) && $_GET['department'] !== '') {
    $whereClause .= " AND department = ?";
    $params[] = $_GET['department'];
}

if (isset($_GET['session']) && $_GET['session'] !== '') {
    $whereClause .= " AND academic_session = ?";
    $params[] = $_GET['session'];
}

$stmt = $pdo->prepare("SELECT * FROM fees $whereClause ORDER BY created_at DESC LIMIT 50");
$stmt->execute($params);
$fees = $stmt->fetchAll();

// Get departments for filter
$stmt = $pdo->query("SELECT DISTINCT department_id FROM studrec WHERE role = 'student' ORDER BY department_id");
$departments = $stmt->fetchAll();

// Get current page
$currentPage = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fees Management - School Management System</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    
    <style>
        body {
            background: #6B8E23;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
        }

        .container-fluid {
            padding: 20px;
        }

        .page-header {
            background: rgba(255, 255, 255, 0.95);
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
            backdrop-filter: blur(10px);
        }

        .stats-row {
            margin-bottom: 30px;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(10px);
            border-left: 4px solid;
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-card.blue { border-left-color: #3498db; }
        .stat-card.green { border-left-color: #2ecc71; }
        .stat-card.orange { border-left-color: #f39c12; }
        .stat-card.red { border-left-color: #e74c3c; }

        .content-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(10px);
            margin-bottom: 30px;
        }

        .action-buttons {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .btn-custom {
            background: #6B8E23;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .btn-custom:hover {
            background: #556B2F;
            color: white;
            transform: translateY(-2px);
        }

        .table {
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }

        .table thead {
            background: #6B8E23;
            color: white;
        }

        .badge {
            font-size: 0.8em;
            padding: 5px 10px;
        }

        .filter-section {
            background: rgba(255, 255, 255, 0.1);
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        .modal-content {
            border-radius: 15px;
            border: none;
        }

        .modal-header {
            background: #6B8E23;
            color: white;
            border-radius: 15px 15px 0 0;
        }

        .form-control, .form-select {
            border-radius: 8px;
            border: 1px solid #ddd;
            padding: 10px;
        }

        .amount-display {
            font-size: 1.2em;
            font-weight: bold;
        }

        .payment-status {
            text-transform: capitalize;
        }

        .alert {
            border-radius: 10px;
            border: none;
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
                            <p class="university-tagline">School Management System - Department Management</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="container-fluid">
        <!-- Page Header -->
        <div class="page-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    
                    <h1 class="d-inline"><i class="fas fa-money-bill-wave"></i> Fees Management</h1>
                    <p class="mb-0 mt-2">Manage student fees, payments, and financial records</p>
                </div>
                <div>
                    <a href="admin_dashboard.php" class="btn btn-secondary me-3">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                    <button class="btn btn-custom" data-bs-toggle="modal" data-bs-target="#generateFeesModal">
                        <i class="fas fa-plus"></i> Generate Fees
                    </button>
                </div>
            </div>
        </div>

        <!-- Success/Error Messages -->
        <?php if (isset($success)): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle"></i> <?php echo $success; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <?php if (isset($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="row stats-row">
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="stat-card blue">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1">Total Fee Records</h6>
                            <h3 class="mb-0"><?php echo number_format($totalFees); ?></h3>
                        </div>
                        <div class="text-primary">
                            <i class="fas fa-file-invoice fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="stat-card green">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1">Total Collected</h6>
                            <h3 class="mb-0">₦<?php echo number_format($totalCollected); ?></h3>
                        </div>
                        <div class="text-success">
                            <i class="fas fa-money-bill-wave fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="stat-card orange">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1">Outstanding Balance</h6>
                            <h3 class="mb-0">₦<?php echo number_format($totalOutstanding); ?></h3>
                        </div>
                        <div class="text-warning">
                            <i class="fas fa-clock fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="stat-card red">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1">Overdue Payments</h6>
                            <h3 class="mb-0"><?php echo number_format($overduePayments); ?></h3>
                        </div>
                        <div class="text-danger">
                            <i class="fas fa-exclamation-triangle fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Fees List -->
        <div class="content-card">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h4><i class="fas fa-list"></i> Fee Records</h4>
                <div class="action-buttons">
                    <button class="btn btn-outline-success" data-bs-toggle="modal" data-bs-target="#paymentModal">
                        <i class="fas fa-credit-card"></i> Record Payment
                    </button>
                    <button class="btn btn-outline-info" onclick="window.print()">
                        <i class="fas fa-print"></i> Print Report
                    </button>
                </div>
            </div>

            <!-- Filters -->
            <div class="filter-section">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <select name="status" class="form-select">
                            <option value="">All Status</option>
                            <option value="pending" <?php echo (isset($_GET['status']) && $_GET['status'] == 'pending') ? 'selected' : ''; ?>>Pending</option>
                            <option value="partial" <?php echo (isset($_GET['status']) && $_GET['status'] == 'partial') ? 'selected' : ''; ?>>Partial</option>
                            <option value="paid" <?php echo (isset($_GET['status']) && $_GET['status'] == 'paid') ? 'selected' : ''; ?>>Paid</option>
                            <option value="overdue" <?php echo (isset($_GET['status']) && $_GET['status'] == 'overdue') ? 'selected' : ''; ?>>Overdue</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select name="department" class="form-select">
                            <option value="">All Departments</option>
                            <?php foreach ($departments as $dept): ?>
                            <option value="<?php echo $dept['DEPARTMENT']; ?>" <?php echo (isset($_GET['department']) && $_GET['department'] == $dept['DEPARTMENT']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($dept['DEPARTMENT']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select name="session" class="form-select">
                            <option value="">All Sessions</option>
                            <option value="2024/2025" <?php echo (isset($_GET['session']) && $_GET['session'] == '2024/2025') ? 'selected' : ''; ?>>2024/2025</option>
                            <option value="2023/2024" <?php echo (isset($_GET['session']) && $_GET['session'] == '2023/2024') ? 'selected' : ''; ?>>2023/2024</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-custom w-100">
                            <i class="fas fa-filter"></i> Filter
                        </button>
                    </div>
                </form>
            </div>

            <!-- Fees Table -->
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Student ID</th>
                            <th>Student Name</th>
                            <th>Department</th>
                            <th>Level</th>
                            <th>Session</th>
                            <th>Total Amount</th>
                            <th>Amount Paid</th>
                            <th>Balance</th>
                            <th>Status</th>
                            <th>Due Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($fees)): ?>
                        <?php foreach ($fees as $fee): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($fee['student_id']); ?></strong></td>
                            <td><?php echo htmlspecialchars($fee['student_name']); ?></td>
                            <td><?php echo htmlspecialchars($fee['department']); ?></td>
                            <td><?php echo htmlspecialchars($fee['level_year']); ?></td>
                            <td><?php echo htmlspecialchars($fee['academic_session']); ?></td>
                            <td class="amount-display">₦<?php echo number_format($fee['total_amount']); ?></td>
                            <td class="amount-display text-success">₦<?php echo number_format($fee['amount_paid']); ?></td>
                            <td class="amount-display text-danger">₦<?php echo number_format($fee['balance']); ?></td>
                            <td>
                                <span class="badge 
                                    <?php 
                                    switch($fee['payment_status']) {
                                        case 'paid': echo 'bg-success'; break;
                                        case 'partial': echo 'bg-warning'; break;
                                        case 'overdue': echo 'bg-danger'; break;
                                        default: echo 'bg-secondary';
                                    }
                                    ?> payment-status">
                                    <?php echo ucfirst($fee['payment_status']); ?>
                                </span>
                            </td>
                            <td>
                                <?php 
                                $due_date = new DateTime($fee['due_date']);
                                $now = new DateTime();
                                $is_overdue = ($due_date < $now && $fee['balance'] > 0);
                                ?>
                                <span class="<?php echo $is_overdue ? 'text-danger fw-bold' : ''; ?>">
                                    <?php echo $due_date->format('M d, Y'); ?>
                                    <?php if ($is_overdue): ?>
                                        <i class="fas fa-exclamation-triangle ms-1"></i>
                                    <?php endif; ?>
                                </span>
                            </td>
                            <td>
                                <div class="btn-group" role="group">
                                    <button class="btn btn-sm btn-outline-primary" 
                                            onclick="viewFeeDetails(<?php echo $fee['fee_id']; ?>)"
                                            title="View Details">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <?php if ($fee['balance'] > 0): ?>
                                    <button class="btn btn-sm btn-outline-success" 
                                            onclick="recordPayment(<?php echo $fee['fee_id']; ?>, '<?php echo htmlspecialchars($fee['student_name']); ?>', <?php echo $fee['balance']; ?>)"
                                            title="Record Payment">
                                        <i class="fas fa-credit-card"></i>
                                    </button>
                                    <?php endif; ?>
                                    <button class="btn btn-sm btn-outline-info" 
                                            onclick="printReceipt(<?php echo $fee['fee_id']; ?>)"
                                            title="Print Receipt">
                                        <i class="fas fa-print"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php else: ?>
                        <tr>
                            <td colspan="11" class="text-center py-4">
                                <div class="text-muted">
                                    <i class="fas fa-inbox fa-3x mb-3"></i>
                                    <br>No fee records found. Generate fees for students to get started.
                                </div>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Generate Fees Modal -->
    <div class="modal fade" id="generateFeesModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-plus"></i> Generate Student Fees</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="generate_fees">
                        
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Academic Session</label>
                                <select name="academic_session" class="form-select" required>
                                    <option value="">Select Session</option>
                                    <option value="2024/2025">2024/2025</option>
                                    <option value="2023/2024">2023/2024</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Semester</label>
                                <select name="semester" class="form-select" required>
                                    <option value="">Select Semester</option>
                                    <option value="First Semester">First Semester</option>
                                    <option value="Second Semester">Second Semester</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Department</label>
                                <select name="department" class="form-select" required>
                                    <option value="">Select Department</option>
                                    <?php foreach ($departments as $dept): ?>
                                    <option value="<?php echo $dept['DEPARTMENT']; ?>">
                                        <?php echo htmlspecialchars($dept['DEPARTMENT']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Level</label>
                                <select name="level" class="form-select" required>
                                    <option value="">Select Level</option>
                                    <option value="100 Level">100 Level</option>
                                    <option value="200 Level">200 Level</option>
                                    <option value="300 Level">300 Level</option>
                                    <option value="400 Level">400 Level</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="alert alert-info mt-3">
                            <i class="fas fa-info-circle"></i>
                            <strong>Note:</strong> This will generate fee records for all students in the selected department and level who don't already have fees for this session.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-custom">
                            <i class="fas fa-plus"></i> Generate Fees
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Record Payment Modal -->
    <div class="modal fade" id="paymentModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-credit-card"></i> Record Payment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="record_payment">
                        <input type="hidden" name="fee_id" id="payment_fee_id">
                        
                        <div class="alert alert-primary">
                            <strong>Student:</strong> <span id="payment_student_name"></span><br>
                            <strong>Outstanding Balance:</strong> ₦<span id="payment_balance"></span>
                        </div>
                        
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Amount Paid *</label>
                                <input type="number" name="amount_paid" class="form-control" 
                                       step="0.01" min="0" required placeholder="0.00">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Payment Method *</label>
                                <select name="payment_method" class="form-select" required>
                                    <option value="">Select Method</option>
                                    <option value="Cash">Cash</option>
                                    <option value="Bank Transfer">Bank Transfer</option>
                                    <option value="Online Payment">Online Payment</option>
                                    <option value="POS">POS</option>
                                    <option value="Cheque">Cheque</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Transaction Reference</label>
                                <input type="text" name="transaction_ref" class="form-control" 
                                       placeholder="Bank reference or transaction ID">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Receipt Number *</label>
                                <input type="text" name="receipt_number" class="form-control" 
                                       value="RCP-<?php echo date('Ymd') . rand(1000, 9999); ?>" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Notes</label>
                                <textarea name="notes" class="form-control" rows="3" 
                                          placeholder="Additional payment notes or remarks..."></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save"></i> Record Payment
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Fee Details Modal -->
    <div class="modal fade" id="feeDetailsModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-eye"></i> Fee Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="feeDetailsContent">
                    <!-- Content will be loaded dynamically -->
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
        function recordPayment(feeId, studentName, balance) {
            document.getElementById('payment_fee_id').value = feeId;
            document.getElementById('payment_student_name').textContent = studentName;
            document.getElementById('payment_balance').textContent = new Intl.NumberFormat().format(balance);
            
            // Set max amount to balance
            document.querySelector('input[name="amount_paid"]').setAttribute('max', balance);
            
            var modal = new bootstrap.Modal(document.getElementById('paymentModal'));
            modal.show();
        }

        function viewFeeDetails(feeId) {
            // Load fee details via AJAX
            fetch(`get_fee_details.php?fee_id=${feeId}`)
                .then(response => response.text())
                .then(data => {
                    document.getElementById('feeDetailsContent').innerHTML = data;
                    var modal = new bootstrap.Modal(document.getElementById('feeDetailsModal'));
                    modal.show();
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error loading fee details');
                });
        }

        function printReceipt(feeId) {
            window.open(`print_receipt.php?fee_id=${feeId}`, '_blank');
        }

        // Auto-generate receipt number
        document.addEventListener('DOMContentLoaded', function() {
            const receiptInput = document.querySelector('input[name="receipt_number"]');
            if (receiptInput && !receiptInput.value) {
                const today = new Date();
                const dateStr = today.getFullYear() + 
                               String(today.getMonth() + 1).padStart(2, '0') + 
                               String(today.getDate()).padStart(2, '0');
                const randomNum = Math.floor(Math.random() * 9000) + 1000;
                receiptInput.value = `RCP-${dateStr}${randomNum}`;
            }
        });
    </script>
</body>
</html>