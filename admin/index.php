<?php
// admin/index.php (आपका डैशबोर्ड)
require_once '../config/db.php'; 
require_once '../functions.php'; 

// यह सुनिश्चित करता है कि केवल एडमिन ही इस पेज को एक्सेस कर सके
ensureAdmin(); 

// --- डेटाबेस से KPIs प्राप्त करें ---

// A. 24 घंटे में कुल जमा
$stmt_deposits = $conn->prepare("SELECT COALESCE(SUM(amount), 0) AS total_deposits FROM transactions WHERE type = 'recharge' AND status = 'Completed' AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)");
$stmt_deposits->execute();
$deposits_data = $stmt_deposits->get_result()->fetch_assoc();
$total_deposits_24h = number_format($deposits_data['total_deposits']); 
$stmt_deposits->close();

// B. आज के नए उपयोगकर्ता
$stmt_new_users = $conn->prepare("SELECT COUNT(id) AS new_users_today FROM users WHERE DATE(created_at) = CURDATE()");
$stmt_new_users->execute();
$users_data = $stmt_new_users->get_result()->fetch_assoc();
$new_users_today = $users_data['new_users_today'];
$stmt_new_users->close();

// C. लंबित निकासी
$stmt_pending_withdrawals = $conn->prepare("SELECT COUNT(id) AS pending_count FROM transactions WHERE type = 'withdraw' AND status = 'Pending'");
$stmt_pending_withdrawals->execute();
$withdrawals_data = $stmt_pending_withdrawals->get_result()->fetch_assoc();
$pending_withdrawals_count = $withdrawals_data['pending_count'];
$stmt_pending_withdrawals->close();

$conn->close();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>🏠 Admin Dashboard • Teen Patti</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .sidebar {
            width: 250px;
            height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            background-color: #212529; 
            padding-top: 56px; 
            z-index: 1000;
        }
        .main-content {
            margin-left: 250px;
            padding: 20px;
        }
        .sidebar .nav-link.active {
            background-color: #495057 !important; 
            border-left: 4px solid #ffc107; 
        }
    </style>
</head>
<body class="bg-dark text-light">

<nav class="navbar navbar-dark bg-dark sticky-top shadow-sm">
    <div class="container-fluid">
        <a class="navbar-brand ms-2" href="#">👑 Admin Panel</a>
        <div class="d-flex me-4">
            <span class="navbar-text me-3 text-warning">Welcome, Admin!</span>
            <a class="btn btn-outline-danger btn-sm" href="logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
        </div>
    </div>
</nav>

<div class="sidebar d-flex flex-column p-3">
    <h5 class="text-white mt-3 mb-4 border-bottom pb-2">Navigation</h5>
    <ul class="nav nav-pills flex-column mb-auto">
        <li class="nav-item">
            <a href="index.php" class="nav-link active text-white" aria-current="page">
                <i class="bi bi-speedometer2"></i> Dashboard
            </a>
        </li>
        <li>
            <a href="users.php" class="nav-link text-white">
                <i class="bi bi-people-fill"></i> Manage Users
            </a>
        </li>
        <li>
            <a href="recharge.php" class="nav-link text-white">
                <i class="bi bi-wallet-fill"></i> Recharge User
            </a>
        </li>
        <li>
            <a href="transactions.php" class="nav-link text-white">
                <i class="bi bi-receipt"></i> Transactions History
            </a>
        </li>
        <hr class="my-3 text-secondary">
        <li>
            <a href="logout.php" class="nav-link text-danger">
                <i class="bi bi-door-open"></i> Logout
            </a>
        </li>
    </ul>
</div>

<div class="main-content">
    
    <h2 class="text-warning mb-4">Dashboard Overview</h2>

    <div class="row g-4 mb-5">
        <div class="col-md-4">
            <div class="card bg-secondary text-light shadow-lg">
                <div class="card-body">
                    <h5 class="card-title"><i class="bi bi-currency-dollar me-2"></i> Total Deposits (24h)</h5>
                    <p class="display-4 fw-bold"><?php echo '₹ ' . $total_deposits_24h; ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-secondary text-light shadow-lg">
                <div class="card-body">
                    <h5 class="card-title"><i class="bi bi-person-plus me-2"></i> New Users (Today)</h5>
                    <p class="display-4 fw-bold"><?php echo $new_users_today; ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-secondary text-light shadow-lg">
                <div class="card-body">
                    <h5 class="card-title"><i class="bi bi-cash me-2"></i> Pending Withdrawals</h5>
                    <p class="display-4 fw-bold text-danger"><?php echo $pending_withdrawals_count; ?></p>
                </div>
            </div>
        </div>
    </div>
    
    <div class="card bg-dark border-warning shadow-sm">
        <div class="card-body">
            <h4 class="card-title text-warning">System Status</h4>
            <p class="card-text text-muted">This is where you can display recent activity, system logs, or key performance indicators (KPIs) relevant to the Teen Patti game platform.</p>
            <a href="transactions.php" class="btn btn-warning mt-3"><i class="bi bi-list-check me-2"></i> Review Transactions</a>
        </div>
    </div>
    
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>