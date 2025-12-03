<?php
// admin/dashboard.php - Admin Dashboard UI (Fully Modern & Robust)

session_start();

// -------------------------------------------------------------------
// 1. CONFIGURATION / INITIALIZATION & ROBUST PATH CHECK
// -------------------------------------------------------------------

// Assuming db.php is in: C:\xampp\htdocs\teenpatti\config\db.php
require_once __DIR__ . '/../config/db.php'; 

// --- ROBUST functions.php PATH RESOLUTION ---
// Primary Check: As per your last input (in the 'user' folder)
$functions_file = realpath(__DIR__ . '/../user/functions.php');

if ($functions_file === false) {
    // Secondary Check: If the file is directly in the project root
    $functions_file = realpath(__DIR__ . '/../functions.php');
}

if ($functions_file === false) {
    // FATAL ERROR: If the file is not found in the expected locations
    die("
        <div style='background: #dc3545; color: white; padding: 20px; border-radius: 5px; font-family: sans-serif;'>
            <h3>❌ Fatal Error: functions.php Not Found</h3>
            <p>Could not locate <code>functions.php</code> in <code>/user/</code> or the root directory. Please verify file name and location.</p>
        </div>
    ");
}

require_once $functions_file;
// --- END PATH RESOLUTION ---

// LOGIN CHECK
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

$current_admin_id = $_SESSION['admin_id'];

// --- CSRF Token Generation ---
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); 
}
$csrf_token = $_SESSION['csrf_token'];


// -------------------------------------------------------------------
// 2. DASHBOARD STATS FETCH (Check for format_currency dependency here)
// -------------------------------------------------------------------
// Note: If format_currency is still undefined, the error will now point to this section.
if (!function_exists('format_currency')) {
    die("Error: The loaded functions.php file does not contain the required function 'format_currency()'.");
}

function fetch_dashboard_stats($conn) {
    $stats_query_sql = "
        SELECT 
            (SELECT COUNT(*) FROM users) AS total_users,
            (SELECT IFNULL(SUM(amount), 0) FROM transactions WHERE type='recharge' AND status='Completed') AS total_recharges,
            (SELECT IFNULL(SUM(commission_amount), 0) FROM transactions WHERE status='Completed') AS total_commission 
    "; 
    
    $stats_query = $conn->query($stats_query_sql);
    if (!$stats_query) {
        error_log("Stats Query Failed: " . $conn->error);
        return ['total_users' => 0, 'total_recharges' => 0.00, 'total_commission' => 0.00];
    }
    return $stats_query->fetch_assoc();
}

$stats = fetch_dashboard_stats($conn);


// -------------------------------------------------------------------
// 3. PENDING TRANSACTIONS FETCH 
// -------------------------------------------------------------------
$pending_transactions_sql = "
    SELECT 
        t.*, 
        u.name AS user_name, 
        u.email AS user_email,
        u.wallet_balance as current_user_balance
    FROM transactions t
    JOIN users u ON t.user_id = u.id
    WHERE t.status = 'Pending' 
    ORDER BY t.created_at ASC
";
$pending_transactions = $conn->query($pending_transactions_sql);

if (!$pending_transactions) {
    error_log("Pending Transactions Query Failed: " . $conn->error);
    $pending_transactions = (object)['num_rows' => 0];
    $pending_transactions_error = "Error fetching pending transactions. Database check failed.";
}


// -------------------------------------------------------------------
// 4. COMPLETED/REJECTED TRANSACTIONS FETCH (FIXED: processed_at removed)
// -------------------------------------------------------------------
$completed_transactions_sql = "
    SELECT 
        t.*, 
        u.name AS user_name 
    FROM transactions t
    JOIN users u ON t.user_id = u.id
    WHERE t.status IN ('Completed', 'Rejected')
    ORDER BY t.created_at DESC  
    LIMIT 10
"; 
$completed_transactions = $conn->query($completed_transactions_sql);

if (!$completed_transactions) {
    error_log("Completed Transactions Query Failed: " . $conn->error);
    $completed_transactions = (object)['num_rows' => 0]; 
}


// -------------------------------------------------------------------
// 5. QUICK RECHARGE USERS FETCH 
// -------------------------------------------------------------------
$users_query_sql = "SELECT id, name, email, wallet_balance FROM users ORDER BY name ASC LIMIT 500";
$users_query = $conn->query($users_query_sql);
if (!$users_query) {
    error_log("Users Query Failed: " . $conn->error);
    $users_query = (object)['num_rows' => 0]; 
}


// FLASH MESSAGE DISPLAY 
$message = $_SESSION['message'] ?? '';
$message_type = $_SESSION['message_type'] ?? 'success';
unset($_SESSION['message'], $_SESSION['message_type']);

?>
<!doctype html>
<html lang="hi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin • Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
  <style>
    /* 🎨 CSS Variables for the 'Hacker' Aesthetic */
    :root {
        --color-background-primary: #000000; /* Absolute black for deep darkness */
        --color-background-secondary: #0A0A0A; /* Dark panel background */
        --color-background-tertiary: #1A1A1A; /* Darker hover/header state */
        --color-text-light: #00FF41; /* Primary Neon Green text color */
        --color-accent-primary: #00C740; /* Slightly deeper green accent */
        --color-accent-hover: #00882C; /* Darker green for hover */
        --color-border: #006400; /* Dark green border */
        --shadow-color: rgba(0, 255, 65, 0.4); /* Neon green shadow/glow */
        --shadow-elevation-1: 0 0 5px var(--shadow-color); /* Subtle inner glow */
        --shadow-elevation-2: 0 0 15px var(--shadow-color); /* Stronger outer glow */
    }

    body {
        background-color: var(--color-background-primary);
        color: var(--color-text-light);
        /* Use a monospace font for a classic terminal feel */
        font-family: 'Consolas', 'Courier New', monospace;
        letter-spacing: 0.5px; /* Slight spacing for digital look */
    }

    /* 📜 Scrollable Table Container */
    .scroll-table {
        max-height: 500px;
        overflow-y: auto;
        border-radius: 2px; /* Sharper edges */
        border: 1px solid var(--color-border);
        box-shadow: var(--shadow-elevation-1);
        
        /* 💡 Modern Scrollbar Styling (Webkit only) */
        scrollbar-width: thin;
        scrollbar-color: var(--color-accent-primary) var(--color-background-secondary);
    }
    .scroll-table::-webkit-scrollbar {
        width: 6px; /* Thinner scrollbar */
    }
    .scroll-table::-webkit-scrollbar-thumb {
        background-color: var(--color-accent-primary);
        border-radius: 0; /* Sharp edges on thumb */
    }
    .scroll-table::-webkit-scrollbar-track {
        background: var(--color-background-secondary);
    }

    /* 💳 Card/Statistic Element Styling */
    .card-stat {
        transition: transform 0.2s linear, box-shadow 0.2s linear, background 0.2s linear;
        background: var(--color-background-secondary);
        border: 1px solid var(--color-accent-primary); /* Use accent for border */
        border-radius: 4px; /* Slightly sharp corners */
        padding: 20px;
        box-shadow: var(--shadow-elevation-1);
    }

    .card-stat:hover {
        /* Faster, sharper response on hover */
        transform: scale(1.02); 
        /* Intense glow effect */
        box-shadow: var(--shadow-elevation-2); 
        background: var(--color-background-tertiary);
        border-color: var(--color-text-light);
    }

    /* 📊 Table Styling (Bootstrap) */
    .table-dark {
        --bs-table-bg: var(--color-background-secondary);
        --bs-table-color: var(--color-text-light);
    }
    .table-dark th {
        background-color: var(--color-background-tertiary) !important;
        color: var(--color-text-light);
        border-bottom: 1px dashed var(--color-border) !important; /* Dashed separator */
        position: sticky;
        top: 0;
        z-index: 10;
        text-transform: uppercase; /* CAPITALIZE headers */
    }
    .table-dark td {
        border-color: var(--color-border);
    }
    /* Add a subtle zebra striping effect for readability */
    .table-dark > tbody > tr:nth-of-type(odd) > * {
        background-color: rgba(0, 255, 65, 0.03); 
    }
    
    /* 🏷️ Select2 Modern Styling */
    .select2-container .select2-selection--single {
        background-color: var(--color-background-secondary) !important;
        border: 1px solid var(--color-accent-primary) !important;
        color: var(--color-text-light) !important;
        border-radius: 2px !important;
        height: 40px !important;
        box-shadow: var(--shadow-elevation-1);
    }

    /* Adjust the text inside the selected option to be readable */
    .select2-container .select2-selection__rendered {
        color: var(--color-text-light) !important;
        line-height: 40px !important;
    }

    /* Styling for the dropdown results */
    .select2-dropdown {
        background-color: var(--color-background-secondary) !important;
        border: 1px solid var(--color-accent-primary) !important;
        box-shadow: var(--shadow-elevation-2);
        border-radius: 2px;
    }

    /* Highlighted option in dropdown */
    .select2-results__option--highlighted {
        background-color: var(--color-accent-primary) !important;
        color: var(--color-background-primary) !important; /* Black text on neon green */
        border-radius: 0;
        margin: 0;
    }

    /* Default option in dropdown */
    .select2-results__option {
        color: var(--color-text-light) !important;
        padding: 8px 12px;
    }

</style>
</head>

<body class="bg-dark text-light">

<nav class="navbar navbar-expand-lg navbar-dark bg-dark border-bottom border-primary">
    <div class="container-fluid">
        <a class="navbar-brand text-primary fw-bold" href="dashboard.php">
            <i class="fas fa-chart-line me-2"></i> Admin Dashboard
        </a>
        <div class="d-flex">
            <a class="btn btn-outline-info btn-sm me-2" href="users.php"><i class="fas fa-users me-1"></i> Users</a>
            <a class="btn btn-outline-info btn-sm me-2" href="transactions.php"><i class="fas fa-list me-1"></i> All Transactions</a>
            <a class="btn btn-danger btn-sm" href="logout.php"><i class="fas fa-sign-out-alt me-1"></i> Logout</a>
        </div>
    </div>
</nav>

<div class="container py-4">
    
    <div id="flash-message-container">
        <?php if ($message): ?>
            <div class="alert alert-<?= $message_type ?> alert-dismissible fade show" role="alert">
                <i class="fas fa-info-circle me-2"></i> <?= htmlspecialchars($message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
    </div>
    
    <div class="row g-4">
        <div class="col-md-4">
            <div class="card card-stat p-3 text-center">
                <h6 class="text-info"><i class="fas fa-user-plus me-1"></i> Total Users</h6>
                <div class="display-5 fw-bold"><?= format_currency($stats['total_users']) ?></div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card card-stat p-3 text-center">
                <h6 class="text-success"><i class="fas fa-money-check-alt me-1"></i> Approved Recharge Value</h6>
                <div class="display-5 fw-bold text-success">₹ <span id="stat-recharges"><?= format_currency($stats['total_recharges']) ?></span></div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card card-stat p-3 text-center">
                <h6 class="text-warning"><i class="fas fa-piggy-bank me-1"></i> Total Commission Earned</h6>
                <div class="display-5 fw-bold text-warning">₹ <span id="stat-commission"><?= format_currency($stats['total_commission']) ?></span></div>
            </div>
        </div>
    </div>

    <hr class="my-5 border-secondary">

    <div class="mt-4">
        <h4 class="text-warning mb-3"><i class="fas fa-clock me-2"></i> Pending Transactions for Approval</h4>
        <p class="text-muted">कमीशन सेट करने के बाद **Approve** करें।</p>
        
        <?php if (isset($pending_transactions_error)): ?>
            <div class="alert alert-danger"><?= $pending_transactions_error ?></div>
        <?php endif; ?>

        <div class="scroll-table card bg-dark p-2 border border-secondary">
            <table class="table table-dark table-striped table-hover align-middle">
                <thead>
                    <tr class="text-primary">
                        <th>ID / Time</th>
                        <th>User Details</th>
                        <th>Type / Amount</th>
                        <th>Set Commission %</th>
                        <th>Commission Amt</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody id="pending-transactions-body">
                    <?php if ($pending_transactions->num_rows > 0): ?>
                        <?php while ($t = $pending_transactions->fetch_assoc()): ?>
                            <?php 
                                $badge_class = ($t['type'] == 'recharge') ? 'bg-success' : 'bg-danger';
                                $icon_class = ($t['type'] == 'recharge') ? 'fa-plus-circle' : 'fa-minus-circle';
                                
                                $commission_percent = $t['commission_percent'] ?? 0.00;
                                $commission_amount = $t['commission_amount'] ?? 0.00;
                                
                                // Calculate commission amount for display (robust check)
                                if ($commission_amount <= 0 && $commission_percent > 0) {
                                    $calculated_commission_amount = $t['amount'] * ($commission_percent / 100);
                                } else {
                                    $calculated_commission_amount = $commission_amount;
                                }

                                $display_commission_percent = number_format($commission_percent, 2, '.', '');
                                $display_commission_amount = format_currency($calculated_commission_amount);
                            ?>
                            <tr id="transaction-row-<?= $t['id'] ?>" data-user-id="<?= $t['user_id'] ?>" data-user-wallet-balance="<?= $t['current_user_balance'] ?>">
                                <td>
                                    <strong>#<?= $t['id'] ?></strong><br>
                                    <small class="text-muted"><?= format_date_time($t['created_at']) ?></small>
                                </td>
                                <td>
                                    <?= htmlspecialchars($t['user_name']) ?> <br>
                                    <small class="text-info user-email-<?= $t['user_id'] ?>"><i class="fas fa-envelope"></i> <?= htmlspecialchars($t['user_email']) ?></small>
                                    <span class="badge bg-secondary ms-1">Wallet: ₹<span class="user-wallet-<?= $t['user_id'] ?>"><?= format_currency($t['current_user_balance']) ?></span></span>
                                </td>
                                <td>
                                    <span class="badge <?= $badge_class ?> fs-6"><i class="fas <?= $icon_class ?>"></i> <?= ucfirst($t['type']) ?></span>
                                    <div class="fw-bold mt-1 text-light">₹ <span class="transaction-amount-<?= $t['id'] ?>"><?= format_currency($t['amount']) ?></span></div>
                                </td>
                                
                                <td data-transaction-id="<?= $t['id'] ?>">
                                    <form class="set-commission-form d-flex align-items-center">
                                        <input type="hidden" name="action" value="set_commission">
                                        <input type="hidden" name="transaction_id" value="<?= $t['id'] ?>">
                                        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                        <input type="number" 
                                                name="commission_percent" 
                                                value="<?= $display_commission_percent ?>"
                                                class="form-control form-control-sm w-75 me-1 bg-dark text-light border-secondary" 
                                                min="0" max="100" step="0.01" 
                                                required>
                                        <span class="text-muted">%</span>
                                        <button type="submit" class="btn btn-sm btn-info ms-2" title="Set Commission">
                                            <i class="fas fa-check"></i>
                                        </button>
                                    </form>
                                </td>
                                
                                <td class="commission-amount-cell-<?= $t['id'] ?>">
                                    <div class="fw-bold text-warning">₹ <span class="commission-amount-<?= $t['id'] ?>"><?= $display_commission_amount ?></span></div>
                                </td>
                                
                                <td>
                                    <form class="d-inline action-form" data-action-type="approve">
                                        <input type="hidden" name="action" value="approve">
                                        <input type="hidden" name="transaction_id" value="<?= $t['id'] ?>">
                                        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                        <button type="submit" class="btn btn-sm btn-success w-100 mb-1">
                                            <i class="fas fa-thumbs-up"></i> Approve
                                        </button>
                                    </form>
                                    
                                    <form class="d-inline action-form" data-action-type="reject">
                                        <input type="hidden" name="action" value="reject">
                                        <input type="hidden" name="transaction_id" value="<?= $t['id'] ?>">
                                        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                        <button type="submit" class="btn btn-sm btn-danger w-100">
                                            <i class="fas fa-ban"></i> Reject
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr id="no-pending-message">
                            <td colspan="6" class="text-center text-primary py-4">
                                <i class="fas fa-smile-beam me-2"></i> All clear! No pending transactions found.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <hr class="my-5 border-secondary">

    <div class="mt-4">
        <h4 class="text-success mb-3"><i class="fas fa-history me-2"></i> Recent Transaction History (Last 10)</h4>
        <p class="text-muted">हाल ही में पूरी हुई (**Completed/Rejected**) लेन-देन।</p>

        <div class="scroll-table card bg-dark p-2 border border-secondary">
            <table class="table table-dark table-striped table-hover align-middle">
                <thead>
                    <tr class="text-success">
                        <th>ID</th>
                        <th>Status</th>
                        <th>User Name</th>
                        <th>Type / Amount</th>
                        <th>Commission Amt</th>
                        <th>Completed At</th>
                        <th>Processed By</th>
                    </tr>
                </thead>
                <tbody id="completed-transactions-body">
                    <?php if ($completed_transactions->num_rows > 0): ?>
                        <?php while ($h = $completed_transactions->fetch_assoc()): ?>
                            <?php 
                                $status_class = ($h['status'] == 'Completed') ? 'bg-success' : 'bg-danger';
                                $type_class = ($h['type'] == 'recharge') ? 'text-success' : 'text-danger';
                                $type_icon = ($h['type'] == 'recharge') ? 'fa-plus' : 'fa-minus';
                                
                                // Fixed: using created_at as fallback for display time
                                $display_time = format_date_time($h['processed_at'] ?? $h['created_at']);
                            ?>
                            <tr>
                                <td><strong>#<?= $h['id'] ?></strong></td>
                                <td><span class="badge <?= $status_class ?>"><?= htmlspecialchars($h['status']) ?></span></td>
                                <td><?= htmlspecialchars($h['user_name']) ?></td>
                                <td class="<?= $type_class ?>">
                                    <i class="fas <?= $type_icon ?>"></i> 
                                    ₹ <?= format_currency($h['amount']) ?> (<?= ucfirst($h['type']) ?>)
                                </td>
                                <td>
                                    <div class="fw-bold text-warning">₹ <?= format_currency($h['commission_amount'] ?? 0.00) ?></div>
                                </td>
                                <td><?= $display_time ?></td> 
                                <td><small class="text-light">Admin ID: <?= htmlspecialchars($h['processed_by'] ?? 'N/A') ?></small></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr id="no-completed-message">
                            <td colspan="7" class="text-center text-secondary py-4">
                                <i class="fas fa-box-open me-2"></i> No completed transactions found recently.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <hr class="my-5 border-secondary">
    
    <div class="mt-4 card bg-secondary p-4">
        <h5 class="text-white mb-4"><i class="fas fa-wallet me-2"></i> Quick Recharge (Manual Credit)</h5>

        <form method="post" action="handle_action.php" class="row g-3">
            <input type="hidden" name="action" value="manual_recharge">
            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
            
            <div class="col-md-4">
                <label class="form-label">Select User (Searchable)</label>
                <select name="user_id" id="user-select" class="form-select" required>
                    <option value="">Select user...</option>
                    <?php $users_query->data_seek(0); // Reset pointer for user list ?>
                    <?php while ($u = $users_query->fetch_assoc()): ?>
                        <option value="<?= $u['id'] ?>" data-balance="<?= format_currency($u['wallet_balance']) ?>">
                            <?= htmlspecialchars($u['name']) ?> (ID: <?= $u['id'] ?> | Wallet: ₹<?= format_currency($u['wallet_balance']) ?>)
                        </option>
                    <?php endwhile; ?>
                </select>
                <small class="text-info mt-2 d-block">Current Balance: ₹<span id="manual-recharge-balance">0.00</span></small>
            </div>

            <div class="col-md-3">
                <label class="form-label">Amount (₹)</label>
                <input name="amount" type="number" min="1" step="1" class="form-control bg-dark text-light border-secondary" placeholder="Amount (Min 1)" required>
            </div>

            <div class="col-md-3">
                <label class="form-label">Commission to Apply</label>
                <select name="commission_percent_manual" class="form-select bg-dark text-light border-secondary">
                    <option value="0" selected>No Commission (0%)</option>
                    <option value="5">5% Commission</option>
                    <option value="10">10% Commission</option>
                    <option value="20">20% Commission</option>
                </select>
            </div>
            
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-warning w-100">
                    <i class="fas fa-bolt me-1"></i> Manual Recharge
                </button>
            </div>
        </form>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
// --- GLOBAL DATA MAPPING ---
// This is used for real-time wallet balance updates on the client side
const userBalances = {};
<?php 
$users_query->data_seek(0); 
while ($u = $users_query->fetch_assoc()) {
    echo "userBalances[{$u['id']}] = '{$u['wallet_balance']}';\n";
}
?>

// Helper function to format currency client-side
function formatCurrency(amount) {
    if (typeof amount === 'string') {
        amount = parseFloat(amount.replace(/[^0-9.-]+/g,"")); 
    }
    if (isNaN(amount) || amount === null) return '0.00';
    return amount.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ",");
}

// Helper function to display temporary messages
function displayFlashMessage(message, type) {
    const container = $('#flash-message-container');
    container.html(`
        <div class="alert alert-${type} alert-dismissible fade show" role="alert">
            <i class="fas fa-info-circle me-2"></i> ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    `);
    setTimeout(() => {
        container.empty();
    }, 5000);
}

// Function to dynamically update the recent history section 
function updateRecentHistory(transactionId, status, type, amountText, userName, commissionAmountText) {
    const historyBody = $('#completed-transactions-body');
    const statusClass = status === 'Completed' ? 'bg-success' : 'bg-danger';
    const typeClass = type === 'recharge' ? 'text-success' : 'text-danger';
    const typeIcon = type === 'recharge' ? 'fa-plus' : 'fa-minus';
    
    const amount = formatCurrency(amountText);
    const commissionDisplay = formatCurrency(commissionAmountText);

    const now = new Date();
    // Format: Dec 2, 2025 12:40 PM
    const processedTime = now.toLocaleDateString('en-US', { 
        month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit', hour12: true
    }).replace(',', '').replace(' ', ', '); 
    

    const newRow = `
        <tr>
            <td><strong>#${transactionId}</strong></td>
            <td><span class="badge ${statusClass}">${status}</span></td>
            <td>${userName}</td>
            <td class="${typeClass}">
                <i class="fas ${typeIcon}"></i> 
                ₹ ${amount} (${type.charAt(0).toUpperCase() + type.slice(1)})
            </td>
            <td>
                <div class="fw-bold text-warning">₹ ${commissionDisplay}</div>
            </td>
            <td>${processedTime}</td> 
            <td><small class="text-light">Admin ID: <?= $current_admin_id ?></small></td>
        </tr>
    `;
    
    $('#no-completed-message').remove(); 
    historyBody.prepend(newRow);

    // Keep only the last 10 rows
    while (historyBody.children('tr').length > 10) {
        historyBody.children('tr:last').remove();
    }
}


$(document).ready(function() {
    // -------------------------------------------------------------------
    // SELECT2 Initialization and Manual Recharge Logic
    // -------------------------------------------------------------------
    $('#user-select').select2({
        theme: "bootstrap", 
        placeholder: "Search user by name, ID or Email...",
        allowClear: true,
        dropdownParent: $('body'),
        templateResult: function (data) {
            if (!data.id) { return data.text; }
            const originalOption = $(data.element);
            const balance = originalOption.data('balance');
            return $('<span>' + data.text.replace(' | Wallet: ₹' + balance, '') + '</span><span class="float-end text-warning">Wallet: ₹' + balance + '</span>');
        }
    }).on('select2:select', function (e) {
        const balance = $(e.currentTarget).find(':selected').data('balance') || '0.00';
        $('#manual-recharge-balance').text(balance);
    }).on('select2:clear', function (e) {
        $('#manual-recharge-balance').text('0.00');
    });

    // -------------------------------------------------------------------
    // AJAX ACTIONS (Requires handle_action.php)
    // -------------------------------------------------------------------
    const ajaxUrl = "handle_action.php";

    function updateUserWalletDisplay(userId, newBalance) {
        const formattedBalance = formatCurrency(newBalance);
        
        userBalances[userId] = newBalance;
        $(`.user-wallet-${userId}`).text(formattedBalance);

        const selectOption = $(`#user-select option[value="${userId}"]`);
        selectOption.data('balance', formattedBalance).attr('data-balance', formattedBalance); 
        
        const oldText = selectOption.text();
        const newText = oldText.replace(/Wallet: ₹.*?\)/, `Wallet: ₹${formattedBalance})`);
        selectOption.text(newText);
        
        if ($('#user-select').val() == userId) {
            $('#manual-recharge-balance').text(formattedBalance);
        }
    }


    // 1. Commission Setter (Set Commission % action)
    $('.set-commission-form').on('submit', function(e) {
        e.preventDefault();
        const form = $(this);
        const transactionId = form.find('input[name="transaction_id"]').val();
        
        const setButton = form.find('button[type="submit"]');
        const originalHtml = setButton.html();
        setButton.html('<span class="spinner-border spinner-border-sm"></span>');
        setButton.prop('disabled', true);
        
        $.ajax({
            type: "POST",
            url: ajaxUrl,
            data: form.serialize(),
            dataType: 'json',
            success: function(response) {
                setButton.html(originalHtml);
                setButton.prop('disabled', false);
                
                if (response.status === 'success') {
                    const cleanAmount = formatCurrency(response.new_commission_amount); 
                    $(`.commission-amount-${transactionId}`).text(cleanAmount); 
                    displayFlashMessage(response.message, 'success');
                } else {
                    displayFlashMessage(response.message || "Failed to set commission.", 'danger');
                }
            },
            error: function(xhr) {
                displayFlashMessage(`Error setting commission. Status: ${xhr.status}.`, 'danger');
                setButton.html(originalHtml);
                setButton.prop('disabled', false);
            }
        });
    });

    // 2. Approve/Reject Action
    $('.action-form').on('submit', function(e) {
        e.preventDefault();
        const form = $(this);
        const actionType = form.data('action-type');
        const transactionId = form.find('input[name="transaction_id"]').val();
        
        const confirmMessage = actionType === 'approve' 
            ? `APPROVE Transaction #${transactionId}?` 
            : `REJECT Transaction #${transactionId}?`;
            
        if (!confirm(confirmMessage)) {
            return;
        }

        const transactionRow = $(`#transaction-row-${transactionId}`);
        const userId = transactionRow.data('userId');
        const type = transactionRow.find('td:nth-child(3) .badge').text().trim().toLowerCase().includes('recharge') ? 'recharge' : 'withdraw'; 
        const amountText = transactionRow.find(`.transaction-amount-${transactionId}`).text(); 
        const commissionAmountText = transactionRow.find(`.commission-amount-${transactionId}`).text(); 
        
        const userNameNode = transactionRow.find('td:nth-child(2)').contents().filter(function() { 
            return this.nodeType === 3; 
        }).eq(0);
        const userName = userNameNode.length ? userNameNode.text().trim() : 'N/A';


        const allButtons = $(`#transaction-row-${transactionId}`).find('.action-form button');
        const originalButtonHtml = {};
        allButtons.each(function() {
            const btn = $(this);
            originalButtonHtml[btn.closest('.action-form').data('action-type')] = btn.html();
            btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span>');
        });
        
        $.ajax({
            type: "POST",
            url: ajaxUrl,
            data: form.serialize(),
            dataType: 'json', 
            success: function(response) {
                
                if (response.status === 'success') {
                    
                    if (response.stats) {
                        $('#stat-recharges').text(response.stats.total_recharges);
                        $('#stat-commission').text(response.stats.total_commission);
                    }

                    if (response.new_user_balance !== undefined && userId) {
                        updateUserWalletDisplay(userId, response.new_user_balance);
                    }
                    
                    const statusText = actionType === 'approve' ? 'Completed' : 'Rejected';
                    updateRecentHistory(transactionId, statusText, type, amountText, userName, commissionAmountText);

                    transactionRow.fadeOut(500, function() {
                        $(this).remove();
                        
                        const pendingTableBody = $('#pending-transactions-body');
                        if (pendingTableBody.children('tr').length === 0) {
                            const emptyMessageRow = `
                                    <tr id="no-pending-message">
                                        <td colspan="6" class="text-center text-primary py-4">
                                            <i class="fas fa-smile-beam me-2"></i> All clear! No pending transactions found.
                                        </td>
                                    </tr>`;
                            pendingTableBody.append(emptyMessageRow);
                        }
                    });
                    
                    displayFlashMessage(response.message, 'success');
                    
                } else {
                    displayFlashMessage(response.message || "Action failed due to server error.", 'danger');
                    allButtons.prop('disabled', false).each(function() {
                        $(this).html(originalButtonHtml[$(this).closest('.action-form').data('action-type')]);
                    });
                }
            },
            error: function(xhr) {
                displayFlashMessage(`Action failed! Server Status: ${xhr.status}. Check console for details.`, 'danger');
                console.error("AJAX Error:", xhr.responseText);

                allButtons.prop('disabled', false).each(function() {
                    $(this).html(originalButtonHtml[$(this).closest('.action-form').data('action-type')]);
                });
            }
        });
    });

});
</script>
</body>
</html>