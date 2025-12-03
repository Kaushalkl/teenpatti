<?php
// user/wallet.php
// PHP लॉजिक अपरिवर्तित (Unchanged PHP Logic) है

require_once __DIR__ . '/../config/db.php';
session_start();

// 1. Authentication Check
if(!isset($_SESSION['user_id'])){ 
    header('Location: login.php'); 
    exit; 
}

$user_id = (int)$_SESSION['user_id'];
$user = null;
$payout_details = [
    'bank_account' => '',
    'bank_ifsc' => '',
    'upi_id' => ''
]; // Default empty values

// 2. Fetch User Data (Prepared statement - ONLY from users table)
$stmt_user = $conn->prepare("SELECT id, name, email, wallet_balance FROM users WHERE id=? LIMIT 1");
if (!$stmt_user) {
    die("Error preparing user statement: " . $conn->error);
}
$stmt_user->bind_param("i", $user_id);
$stmt_user->execute();
$user = $stmt_user->get_result()->fetch_assoc();
$stmt_user->close();

if (!$user) {
    session_destroy();
    header('Location: login.php?error=user_not_found');
    exit;
}

// 3. Fetch User's Default Payout Details
$stmt_payout = $conn->prepare("
    SELECT account_holder, bank_account, bank_ifsc, upi_id 
    FROM user_payout_methods 
    WHERE user_id = ? AND is_default = TRUE 
    LIMIT 1
");

if ($stmt_payout) {
    $stmt_payout->bind_param("i", $user_id);
    $stmt_payout->execute();
    $result_payout = $stmt_payout->get_result()->fetch_assoc();
    $stmt_payout->close();

    if ($result_payout) {
        $payout_details = array_merge($payout_details, $result_payout);
    }
} else {
    error_log("Failed to prepare payout statement: " . $conn->error);
}


// 4. Fetch Recent Transactions (USING PREPARED STATEMENT)
$transactions_result = null;

$stmt_trans = $conn->prepare("SELECT id, type, amount, status, remark, created_at FROM transactions WHERE user_id=? ORDER BY created_at DESC LIMIT 10");

if (!$stmt_trans) {
    error_log("Failed to prepare transaction statement: " . $conn->error);
} else {
    $stmt_trans->bind_param("i", $user_id);
    $stmt_trans->execute();
    $transactions_result = $stmt_trans->get_result();
    $stmt_trans->close();
}


// 5. Handle Session Messages
$message = '';
$message_type = '';
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $message_type = $_SESSION['message_type'] ?? 'info';
    unset($_SESSION['message']);
    unset($_SESSION['message_type']);
}

/**
 * Status के आधार पर Bootstrap क्लास लौटाता है (Hacker Theme friendly)
 */
function get_status_badge(string $status): string {
    switch (strtolower($status)) {
        case 'completed': 
        case 'success':
            return 'badge rounded-0 bg-success-hacker text-dark fw-bold'; // Neon Green
        case 'pending':
        case 'processing':
            return 'badge rounded-0 bg-warning-hacker text-dark fw-bold'; // Yellow
        case 'rejected':
        case 'failed':
            return 'badge rounded-0 bg-danger-hacker text-light fw-bold'; // Red
        case 'cancelled':
            return 'badge rounded-0 bg-secondary-hacker text-light';
        default:
            return 'badge rounded-0 bg-dark-hacker text-light';
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Wallet • ACCESS / USER-ID: <?php echo htmlspecialchars($user_id); ?></title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
  
  <style>
    /* Custom Styles for Hacker Terminal UI */
    :root {
        --color-background:white;
        --color-primary: #00FF41; /* Neon Green */
        --color-secondary: #00C740; /* Darker Green */
        --color-text-dim: #777;
        --color-terminal-red: #FF4000; /* Reddish Orange for errors */
        --color-terminal-yellow: #FFFF00; /* Yellow for pending */
        --shadow-glow: 0 0 8px rgba(0, 255, 65, 0.6);
        --shadow-subtle: 0 0 3px rgba(0, 255, 65, 0.3);
    }
    body {
        background-color: var(--color-background) !important;
        color: var(--color-primary) !important;
        font-family: 'Consolas', 'Courier New', monospace; 
        line-height: 1.4;
    }
    
    /* Global Overrides */
    h1, h2, h3, h4, h5, h6 {
        color: var(--color-primary) !important;
        text-shadow: var(--shadow-subtle);
    }
    .text-muted-hacker {
        color: var(--color-text-dim) !important;
        font-size: 0.85rem;
    }

    /* Navbar/Header */
    .navbar {
        background-color: red!important;
        border-bottom: 2px solid var(--color-secondary);
        box-shadow: var(--shadow-subtle);
    }
    .navbar-brand .neon-text {
        color: var(--color-primary);
        text-shadow: var(--shadow-glow);
    }

    /* Card Styling (Terminal Block) */
    .modern-card {
        background-color:black;
        border: 1px solid var(--color-secondary);
        border-radius: 4px; /* Boxy look */
        box-shadow: var(--shadow-subtle);
    }
    .card-balance {
        background: #320505ff;
        border: 3px double var(--color-primary);
        box-shadow: 0 0 25px rgba(0, 255, 65, 0.8);
        padding: 20px !important;
    }
    .text-balance-value {
        font-size: 4rem;
        font-weight: bold;
        text-shadow: var(--shadow-glow);
    }
    
    /* Form Elements */
    .form-control-dark {
        background-color: #180707ff;
        color: var(--color-primary);
        border: 1px solid var(--color-secondary);
        border-radius: 2px;
    }
    .form-control-dark::placeholder {
        color: #555;
    }
    .form-control-dark:focus {
        background-color: #300101ff;
        border-color: var(--color-primary);
        box-shadow: 0 0 5px var(--color-primary);
    }
    .input-group-text {
        background-color: #2f0404ff !important;
        border: 1px solid var(--color-secondary);
        color: #AAA !important;
        font-family: 'Consolas', monospace;
    }
    .form-check-input {
        background-color: #333 !important; 
        border-color: #555 !important;
    }
    .form-check-input:checked {
        background-color: var(--color-primary) !important; 
        border-color: var(--color-primary) !important;
    }
    
    /* Neon Button Styles (Recolor for pure Green/Red) */
    .btn-hacker {
        border-radius: 2px;
        font-weight: bold;
        text-transform: uppercase;
        transition: all 0.2s;
        border: 1px solid;
    }
    .btn-neon-green, .btn-neon-red {
        border: 2px solid; /* Make border explicit */
        box-shadow: 0 0 10px;
        color: #300303ff; /* Dark text on bright button */
    }
    .btn-neon-green {
        background-color: var(--color-primary);
        border-color: var(--color-primary);
        box-shadow: 0 0 10px var(--color-primary);
    }
    .btn-neon-green:hover {
        background-color: var(--color-secondary);
        box-shadow: 0 0 15px var(--color-primary);
        color: #000;
    }
    .btn-neon-red {
        background-color: var(--color-terminal-red);
        border-color: var(--color-terminal-red);
        box-shadow: 0 0 10px var(--color-terminal-red);
    }
    .btn-neon-red:hover {
        background-color: #CC3300;
        box-shadow: 0 0 15px var(--color-terminal-red);
        color: #000;
    }
    .btn-outline-light {
        color: var(--color-secondary) !important;
        border-color: var(--color-secondary) !important;
    }
    .btn-outline-light:hover {
        background-color: var(--color-secondary) !important;
        color: #000 !important;
    }
    .btn-danger {
        background-color: var(--color-terminal-red) !important;
        border-color: var(--color-terminal-red) !important;
        color: #000 !important;
    }
    .btn-danger:hover {
        box-shadow: 0 0 8px var(--color-terminal-red);
    }
    
    /* Transaction Table */
    .transaction-table {
        --bs-table-bg: #000000;
        --bs-table-color: var(--color-primary);
        --bs-table-striped-bg: #050505; 
        --bs-table-hover-bg: #151515;
    }
    .transaction-table thead th {
        border-bottom: 1px dashed var(--color-secondary) !important;
        color: var(--color-secondary) !important;
    }
    .text-transaction-type {
        text-transform: uppercase;
        letter-spacing: 2px;
        color: var(--color-primary) !important;
    }
    .text-debit { 
        color: var(--color-terminal-red) !important; 
        text-shadow: 0 0 3px rgba(255, 64, 0, 0.6); 
    }
    .text-credit { 
        color: var(--color-primary) !important; 
        text-shadow: var(--shadow-subtle);
    }

    /* Badge Overrides */
    .bg-success-hacker { background-color: var(--color-primary) !important; color: #000 !important; }
    .bg-warning-hacker { background-color: var(--color-terminal-yellow) !important; color: #000 !important; }
    .bg-danger-hacker { background-color: var(--color-terminal-red) !important; color: #FFF !important; }
    .bg-secondary-hacker { background-color: #444 !important; color: #FFF !important; }
    .badge {
        border-radius: 0 !important; /* Make it square */
        font-family: 'Consolas', monospace;
    }

    /* Alert Boxes (Terminal Output Style) */
    .alert {
        border-radius: 2px;
        font-family: 'Consolas', monospace;
        padding: 0.75rem 1.25rem;
        color: #000;
        font-weight: bold;
    }
    .alert-success { background-color: var(--color-primary); border: 1px solid var(--color-primary); }
    .alert-danger { background-color: var(--color-terminal-red); border: 1px solid var(--color-terminal-red); }
    .alert-info { background-color: var(--color-secondary); border: 1px solid var(--color-secondary); }
    
  </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark">
  <div class="container-fluid px-4">
    <span class="navbar-brand mb-0 h1 fw-bold">
        <span class="neon-text">SYSTEM</span> <span class="neon-text" style="color: var(--color-terminal-yellow);">ACCESS</span> 
    </span>
    <div class="d-flex">
      <span class="me-3 align-self-center text-muted-hacker d-none d-md-inline">USER LOGGED: <span class="fw-bold" style="color: var(--color-primary);"><?php echo htmlspecialchars(strtoupper($user['name'])); ?></span></span>
      <a class="btn btn-sm me-2 btn-neon-green btn-hacker" href="bet.php">START GAME PROTOCOL</a>
      <a class="btn btn-outline-light btn-sm me-2 btn-hacker" href="history.php">VIEW LOGS</a>
      <a class="btn btn-danger btn-sm btn-hacker" href="logout.php">LOGOUT</a>
    </div>
  </div>
</nav>

<div class="container py-5">
    
    <?php if($message): ?>
    <div class="alert alert-<?php echo htmlspecialchars($message_type); ?> alert-dismissible fade show" role="alert">
        <strong class="me-2"><?php echo $message_type === 'success' ? 'STATUS: OK' : 'STATUS: WARNING'; ?>:</strong>
        <?php echo htmlspecialchars($message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>

  <div class="row g-4">
    
    <div class="col-lg-7">
      
      <div class="card modern-card card-balance text-light shadow-sm mb-4">
        <div class="card-body p-4">
          <div class="d-flex justify-content-between align-items-center">
             <div>
               <p class="text-uppercase fw-bold mb-1" style="color: var(--color-primary);">[WALLET_REGISTER: ONLINE]</p>
               <h2 class="fw-bolder text-balance-value">₹ <span id="walletBalance"><?php echo number_format($user['wallet_balance'], 2); ?></span></h2>
             </div>
             <div class="text-end">
               <p class="text-muted-hacker mb-0"><small>USER ID: <span style="color: var(--color-terminal-yellow);">#<?php echo htmlspecialchars($user_id); ?></span></small></p>
               <p class="text-muted-hacker mb-0"><small>EMAIL: <?php echo htmlspecialchars(substr($user['email'], 0, 4)) . '***@***.com'; ?></small></p>
             </div>
          </div>
        </div>
      </div>

      <div class="row g-4">
        
        <div class="col-md-6">
          <div class="card modern-card shadow-sm h-100">
            <div class="card-body">
              <h5 class="card-title mb-3" style="color: var(--color-primary);">🟢 RECHARGE PROTOCOL <span class="badge bg-success-hacker">I/O</span></h5>
              <form method="post" action="wallet_recharge.php" class="d-grid gap-3">
                <input class="form-control form-control-dark" name="quick_amount" type="number" min="10" placeholder="ENTER AMOUNT (MIN 10)" required />
                <button class="btn btn-lg btn-neon-green btn-hacker">⚡ ACTIVATE FUNDS</button>
              </form>
            </div>
          </div>
        </div>

        <div class="col-md-6">
          <div class="card modern-card shadow-sm h-100">
            <div class="card-body">
              <h5 class="card-title mb-3" style="color: var(--color-terminal-red);">🔴 WITHDRAWAL PROTOCOL</h5>
              <form method="post" action="wallet_withdraw.php" class="d-grid gap-2">
                <input class="form-control form-control-dark" style="border-color: var(--color-terminal-red);" name="withdraw_amount" type="number" min="100" placeholder="AMOUNT (MIN. ₹100)" required />

                <h6 class="mt-3 text-start text-muted-hacker">PAYOUT ADDRESS:</h6>
                <div class="input-group mb-2">
                    <span class="input-group-text">A/C NO.</span>
                    <input class="form-control form-control-dark" name="bank_account" type="text" placeholder="BANK ACCOUNT NO." value="<?php echo htmlspecialchars($payout_details['bank_account'] ?? ''); ?>" />
                </div>
                <div class="input-group">
                    <span class="input-group-text">IFSC</span>
                    <input class="form-control form-control-dark" name="bank_ifsc" type="text" placeholder="IFSC CODE" value="<?php echo htmlspecialchars($payout_details['bank_ifsc'] ?? ''); ?>" />
                </div>
                
                <div class="text-center text-muted-hacker my-2"><small>--- OR UPI HASH ---</small></div>
                <input class="form-control form-control-dark" name="upi_id" type="text" placeholder="UPI ID (E.G., USER@BANK)" value="<?php echo htmlspecialchars($payout_details['upi_id'] ?? ''); ?>" />

                <div class="form-check text-start mt-3">
                    <input class="form-check-input" type="checkbox" name="save_details" value="1" id="saveDetailsCheck" checked>
                    <label class="form-check-label text-muted-hacker" for="saveDetailsCheck">
                        PERSIST DATA
                    </label>
                </div>

                <button type="submit" class="btn btn-lg btn-neon-red btn-hacker mt-3">INITIATE WITHDRAWAL</button>
              </form>
            </div>
          </div>
        </div>
      </div>
      
    </div>

    <div class="col-lg-5">
      
      <div class="card modern-card shadow-sm h-100">
        <div class="card-body p-4">
            <h5 class="card-title mb-4 border-bottom pb-2" style="border-color: var(--color-secondary) !important;">▶ ACTIVITY LOG: [TOP 10]</h5>
            
            <?php if($transactions_result && $transactions_result->num_rows > 0): ?>
            <div class="table-responsive">
                <table class="table transaction-table table-striped table-hover table-sm">
                    <thead>
                        <tr>
                            <th scope="col" style="color: var(--color-terminal-yellow);">LOG TYPE</th>
                            <th scope="col" class="text-center" style="color: var(--color-terminal-yellow);">STATUS</th> 
                            <th scope="col" class="text-end" style="color: var(--color-terminal-yellow);">VALUE (₹)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($t = $transactions_result->fetch_assoc()): 
                            $is_debit = (strtolower($t['type']) == 'withdraw' || strtolower($t['type']) == 'bet' || strtolower($t['type']) == 'loss');
                            $is_credit = (strtolower($t['type']) == 'recharge' || strtolower($t['type']) == 'win');
                            
                            $amount_class = $is_debit ? 'text-debit' : 'text-credit';
                            $sign = $is_debit ? '-' : ($is_credit ? '+' : '');
                            
                            $status_badge_class = get_status_badge($t['status']);
                        ?>
                            <tr>
                                <td class="py-2">
                                    <strong class="d-block text-transaction-type text-white"><?php echo htmlspecialchars(strtoupper($t['type'])); ?></strong>
                                    <small class="text-muted-hacker d-block"><?php echo htmlspecialchars($t['remark']); ?></small>
                                    <small class="text-muted-hacker d-block" style="font-size: 0.7rem;">TS: <?php echo date('Y-m-d H:i:s', strtotime($t['created_at'])); ?></small>
                                </td>
                                
                                <td class="text-center align-middle">
                                    <span class="<?php echo $status_badge_class; ?>">
                                        <?php echo strtoupper($t['status']); ?>
                                    </span>
                                </td>
                                
                                <td class="text-end align-middle fw-bold <?php echo $amount_class; ?>">
                                    <?php echo $sign; ?> <?php echo number_format($t['amount'], 2); ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            <div class="text-center mt-3">
               <a href="history.php" class="btn btn-outline-light btn-sm btn-hacker">ACCESS FULL HISTORY LOG</a>
            </div>
            <?php else: ?>
                <p class="text-center text-muted-hacker py-5 m-0">NO RECENT ACTIVITY DETECTED. START TRANSACTION PROTOCOL.</p>
                <div class="d-grid gap-2">
                    <a href="bet.php" class="btn btn-lg btn-neon-green btn-hacker">ACCESS GAME GRID</a>
                </div>
            <?php endif; ?>
            
        </div>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>