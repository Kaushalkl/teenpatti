<?php
// admin/transactions.php (Advanced Version)
require_once __DIR__ . '/../config/db.php';
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

// --- CONFIGURATION (PHP logic remains unchanged) ---
$limit = 20; // Items per page
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'created_at';
$sort_order = isset($_GET['order']) && strtoupper($_GET['order']) == 'ASC' ? 'ASC' : 'DESC';
$allowed_sorts = ['created_at', 'amount', 'type', 'status', 'id'];
if (!in_array($sort_by, $allowed_sorts)) {
    $sort_by = 'created_at';
}

if (!function_exists('format_currency')) {
    function format_currency($amount) {
        return number_format((float)$amount, 2, '.', ','); 
    }
}
if (!function_exists('format_date_time')) {
    function format_date_time($timestamp) {
        if (!$timestamp || strtotime($timestamp) === false) {
             return 'N/A';
        }
        return date("M d, Y H:i:s", strtotime($timestamp)); 
    }
}

// --- MOCK DATA SECTION (For demonstrating the UI in isolation) ---
class MockResult {
    private $data;
    private $index = 0;
    public function __construct($data) { $this->data = $data; }
    public function fetch_assoc() { return $this->data[$this->index++] ?? null; }
    public function num_rows() { return count($this->data); }
}
$mock_transactions = [
    ['id' => 301, 'user_id' => 101, 'name' => 'John Doe', 'email' => 'john@ex.com', 'type' => 'recharge', 'status' => 'Completed', 'amount' => 1500.00, 'commission_amount' => 0.00, 'created_at' => '2025-12-01 10:30:00', 'processed_by' => 5, 'remark' => 'Top-up successful.'],
    ['id' => 302, 'user_id' => 102, 'name' => 'Jane Smith', 'email' => 'jane@ex.com', 'type' => 'withdrawal', 'status' => 'Pending', 'amount' => 500.50, 'commission_amount' => 10.01, 'created_at' => '2025-12-01 14:45:00', 'processed_by' => 0, 'remark' => 'Awaiting admin approval.'],
    ['id' => 303, 'user_id' => 101, 'name' => 'John Doe', 'email' => 'john@ex.com', 'type' => 'recharge', 'status' => 'Rejected', 'amount' => 200.00, 'commission_amount' => 0.00, 'created_at' => '2025-11-29 08:20:00', 'processed_by' => 3, 'remark' => 'Payment failed.'],
    ['id' => 304, 'user_id' => 103, 'name' => 'Admin Test', 'email' => 'admin@ex.com', 'type' => 'withdrawal', 'status' => 'Completed', 'amount' => 12000.00, 'commission_amount' => 240.00, 'created_at' => '2025-11-28 17:00:00', 'processed_by' => 5, 'remark' => 'Bank transfer done.'],
];
$total_records = 100; 
$total_pages = ceil($total_records / $limit);
$res = new MockResult(array_slice($mock_transactions, $offset, $limit)); 
// --- END MOCK DATA ---

if ($page < 1) $page = 1;
if ($page > $total_pages && $total_pages > 0) $page = $total_pages;
$offset = ($page - 1) * $limit;

function get_sort_link($column, $current_sort, $current_order, $current_page) {
    $new_order = ($current_sort == $column && $current_order == 'DESC') ? 'ASC' : 'DESC';
    $icon = '';
    // Using simple HTML entities for consistency with Hacker theme
    if ($current_sort == $column) {
        $icon = ($current_order == 'ASC') ? ' &#9650;' : ' &#9660;'; // ▲ or ▼
    }
    return '?page=' . $current_page . '&sort=' . $column . '&order=' . $new_order . $icon;
}

?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Admin • Transactions // PROCESSING LOGS</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    
    <style>
        /* 🎨 CSS Variables for the 'Hacker' Aesthetic */
        :root {
            --color-background-primary: #000000; /* Absolute black */
            --color-background-secondary: #0A0A0A; /* Dark panel background */
            --color-background-tertiary: #1A1A1A; /* Darker hover/header state */
            --color-text-light: #00FF41; /* Primary Neon Green text color */
            --color-accent-primary: #00C740; /* Slightly deeper green accent */
            --color-accent-hover: #00882C; /* Darker green for hover */
            --color-border: #006400; /* Dark green border */
            --shadow-color: rgba(0, 255, 65, 0.4); /* Neon green shadow/glow */
            --shadow-elevation-1: 0 0 5px var(--shadow-color); /* Subtle glow */
            --shadow-elevation-2: 0 0 15px var(--shadow-color); /* Stronger glow */
        }

        body {
            background-color: var(--color-background-primary);
            color: var(--color-text-light);
            font-family: 'Consolas', 'Courier New', monospace; /* Terminal Font */
            letter-spacing: 0.5px;
        }

        /* Override Bootstrap Header */
        h3 {
            color: var(--color-text-light) !important;
            text-shadow: 0 0 8px var(--shadow-color);
            border-bottom: 2px dashed var(--color-accent-primary) !important;
            padding-bottom: 10px !important;
            margin-bottom: 30px;
        }

        /* Table Container Styling */
        .table-container { 
            max-height: 80vh; 
            overflow-y: auto; 
            border-radius: 2px;
            box-shadow: var(--shadow-elevation-1);
            border: 1px solid var(--color-accent-primary);
        }
        
        /* Override Bootstrap Table Dark */
        .table-dark {
            --bs-table-bg: var(--color-background-secondary);
            --bs-table-color: var(--color-text-light);
            --bs-table-striped-bg: rgba(0, 255, 65, 0.05); /* Very light green stripe */
            margin-bottom: 0;
        }
        
        /* Sticky Header */
        .table-dark thead {
            background-color: var(--color-background-tertiary);
            position: sticky;
            top: 0;
            z-index: 10;
        }
        
        /* Table Header Styling */
        .table-dark th {
            border-bottom: 1px solid var(--color-border) !important;
            text-transform: uppercase;
            color: var(--color-accent-primary) !important;
            font-weight: bold;
        }
        
        /* Sorting Link Styling */
        .sort-link { 
            text-decoration: none; 
            color: inherit; 
            white-space: nowrap;
        }
        .sort-link:hover { 
            color: var(--color-text-light);
        }

        /* Table Row Hover */
        .table-dark tbody tr:hover {
            background-color: #202020 !important;
            box-shadow: 0 0 8px rgba(0, 255, 65, 0.2);
            cursor: pointer;
        }
        
        /* Transaction Types (Recharge/Withdrawal) */
        .text-recharge { color: #00FF41; } /* Primary Neon Green */
        .text-withdrawal { color: #FF4000; } /* Strong Orange/Red for warning */
        .text-commission { color: #FFFF00; } /* Yellow for alerts */
        
        /* Status Badges - MUST be overridden for hacker look */
        .badge {
            padding: 0.5em 0.8em;
            font-weight: bold;
            border-radius: 2px;
        }
        .bg-success { background-color: var(--color-accent-primary) !important; color: var(--color-background-primary) !important; }
        .bg-warning { background-color: #FFA500 !important; color: var(--color-background-primary) !important; }
        .bg-danger { background-color: #FF0000 !important; color: var(--color-text-light) !important; }
        .bg-secondary { background-color: #333333 !important; color: var(--color-text-light) !important; }
        .text-info { color: var(--color-accent-primary) !important; }

        /* Pagination Styling */
        .pagination .page-link {
            background-color: var(--color-background-secondary);
            border: 1px solid var(--color-accent-primary);
            color: var(--color-text-light);
            border-radius: 2px;
            margin: 0 2px;
        }
        .pagination .page-link:hover {
            background-color: var(--color-accent-primary);
            color: var(--color-background-primary);
        }
        .pagination .page-item.active .page-link {
            background-color: var(--color-accent-primary);
            border-color: var(--color-text-light);
            color: var(--color-background-primary);
            box-shadow: var(--shadow-elevation-1);
        }
        .pagination .page-item.disabled .page-link {
            background-color: #111;
            border-color: #222;
            color: #444;
        }
        
        /* Back Button Styling */
        .btn-outline-info {
            --bs-btn-color: var(--color-text-light);
            --bs-btn-border-color: var(--color-accent-primary);
            --bs-btn-hover-color: var(--color-background-primary); 
            --bs-btn-hover-bg: var(--color-accent-primary); 
            --bs-btn-hover-border-color: var(--color-text-light);
            border-radius: 2px;
            transition: all 0.2s linear;
        }
        .btn-outline-info:hover {
            box-shadow: var(--shadow-elevation-2);
        }

    </style>
    </head>
<body class="bg-dark text-light">
<div class="container py-4">
    <h3 class="text-info pb-2">// SYSTEM LOG: Transactions (COUNT: <?= $total_records ?>)</h3>
    <a class="btn btn-sm btn-outline-info mb-3" href="dashboard.php">← cd ../dashboard</a>

    <nav aria-label="Transaction Pagination" class="mb-3">
        <ul class="pagination pagination-sm justify-content-center">
            <?php 
            $sort_params = '&sort=' . $sort_by . '&order=' . $sort_order;
            
            if ($page > 1): ?>
                <li class="page-item"><a class="page-link" href="?page=<?= $page - 1 ?><?= $sort_params ?>">Prev</a></li>
            <?php else: ?>
                <li class="page-item disabled"><span class="page-link">Prev</span></li>
            <?php endif; ?>
            
            <li class="page-item disabled"><span class="page-link text-warning">PAGE: <?= $page ?> / <?= $total_pages ?></span></li>

            <?php 
            $start = max(1, $page - 2);
            $end = min($total_pages, $page + 2);

            if ($start > 1) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
            
            for ($i = $start; $i <= $end; $i++):
                $active_class = ($i == $page) ? 'active' : '';
            ?>
                <li class="page-item <?= $active_class ?>"><a class="page-link" href="?page=<?= $i ?><?= $sort_params ?>"><?= $i ?></a></li>
            <?php endfor; ?>

            <?php if ($end < $total_pages) echo '<li class="page-item disabled"><span class="page-link">...</span></li>'; ?>


            <?php if ($page < $total_pages): ?>
                <li class="page-item"><a class="page-link" href="?page=<?= $page + 1 ?><?= $sort_params ?>">Next</a></li>
            <?php else: ?>
                <li class="page-item disabled"><span class="page-link">Next</span></li>
            <?php endif; ?>
        </ul>
    </nav>
    
    <div class="table-container">
        <table class="table table-dark table-striped table-hover align-middle">
            <thead>
                <tr>
                    <th><a class="sort-link" href="<?= get_sort_link('id', $sort_by, $sort_order, $page) ?>">ID</a></th>
                    <th><a class="sort-link" href="<?= get_sort_link('created_at', $sort_by, $sort_order, $page) ?>">DATE/TIME</a></th>
                    <th>USER (EMAIL)</th>
                    <th><a class="sort-link" href="<?= get_sort_link('type', $sort_by, $sort_order, $page) ?>">TYPE</a></th>
                    <th><a class="sort-link" href="<?= get_sort_link('status', $sort_by, $sort_order, $page) ?>">STATUS</a></th>
                    <th><a class="sort-link" href="<?= get_sort_link('amount', $sort_by, $sort_order, $page) ?>">AMOUNT</a></th>
                    <th>COMMISSION</th>
                    <th>PROCESSED BY</th> 
                    <th>REMARK</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($res->num_rows() > 0): ?>
                    <?php while($r = $res->fetch_assoc()): ?>
                        <?php
                            $type_class = ($r['type'] == 'recharge') ? 'text-recharge' : 'text-withdrawal';
                            
                            $status_badge = 'badge bg-secondary';
                            if ($r['status'] == 'Completed') $status_badge = 'badge bg-success';
                            else if ($r['status'] == 'Pending') $status_badge = 'badge bg-warning text-dark';
                            else if ($r['status'] == 'Rejected') $status_badge = 'badge bg-danger';
                        ?>
                        <tr>
                            <td><?= $r['id'] ?></td>
                            <td class="text-secondary"><?php echo format_date_time($r['created_at']); ?></td>
                            <td>
                                <span class="text-recharge"><?php echo htmlspecialchars($r['name']); ?></span><br>
                                <small class="text-info">(<?php echo htmlspecialchars($r['email']); ?>)</small>
                            </td>
                            <td class="<?= $type_class ?> fw-bold"><?php echo strtoupper($r['type']); ?></td>
                            <td><span class="<?= $status_badge; ?>"><?php echo htmlspecialchars($r['status'] ?? 'N/A'); ?></span></td>
                            <td><span class="text-light">₹ <?php echo format_currency($r['amount']); ?></span></td>
                            
                            <td class="text-commission fw-bold">₹ <?php echo format_currency($r['commission_amount'] ?? 0.00); ?></td>
                            
                            <td><small class="text-info">ID: <?= htmlspecialchars($r['processed_by'] ?? 'N/A') ?></small></td>
                            <td><small class="text-white-50 text-truncate" title="<?php echo htmlspecialchars($r['remark']); ?>"><?php echo htmlspecialchars(substr($r['remark'], 0, 30)) . (strlen($r['remark']) > 30 ? '...' : ''); ?></small></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="9" class="text-center text-danger py-3">!! ERROR: NO LOGS FOUND !!</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <nav aria-label="Transaction Pagination" class="mt-3">
        <ul class="pagination pagination-sm justify-content-center">
            <?php 
            // Reuse logic from top pagination
            if ($page > 1): ?>
                <li class="page-item"><a class="page-link" href="?page=<?= $page - 1 ?><?= $sort_params ?>">Prev</a></li>
            <?php else: ?>
                <li class="page-item disabled"><span class="page-link">Prev</span></li>
            <?php endif; ?>
            
            <?php 
            $start = max(1, $page - 2);
            $end = min($total_pages, $page + 2);

            if ($start > 1) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
            
            for ($i = $start; $i <= $end; $i++):
                $active_class = ($i == $page) ? 'active' : '';
            ?>
                <li class="page-item <?= $active_class ?>"><a class="page-link" href="?page=<?= $i ?><?= $sort_params ?>"><?= $i ?></a></li>
            <?php endfor; ?>

            <?php if ($end < $total_pages) echo '<li class="page-item disabled"><span class="page-link">...</span></li>'; ?>

            <?php if ($page < $total_pages): ?>
                <li class="page-item"><a class="page-link" href="?page=<?= $page + 1 ?><?= $sort_params ?>">Next</a></li>
            <?php else: ?>
                <li class="page-item disabled"><span class="page-link">Next</span></li>
            <?php endif; ?>
        </ul>
    </nav>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>