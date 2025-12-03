<?php
// user/history.php
require_once __DIR__ . '/../config/db.php';
session_start();

if(!isset($_SESSION['user_id'])){ 
    header('Location: login.php'); 
    exit; 
}
$uid = (int)$_SESSION['user_id'];

// --- Utility Function to Format Transaction Data (UPDATED FOR HACKER THEME) ---
function formatTransaction($t) {
    // Note: 'withdraw' और 'bet' लोअरकेस होने चाहिए जैसा कि ENUM में परिभाषित है
    $is_debit = ($t['type'] == 'withdraw' || $t['type'] == 'bet');
    // Change to Hacker Theme classes
    $amount_class = $is_debit ? 'text-debit' : 'text-credit'; 
    $sign = $is_debit ? '-' : '+'; 

    // Status Badge Class (UPDATED FOR HACKER THEME)
    $status_class = 'bg-secondary-hacker';
    if (isset($t['status'])) {
        if ($t['status'] === 'Completed' || $t['status'] === 'Success') {
            $status_class = 'bg-success-hacker text-dark';
        } elseif ($t['status'] === 'Pending' || $t['status'] === 'Processing') {
            $status_class = 'bg-warning-hacker text-dark';
        } elseif ($t['status'] === 'Rejected' || $t['status'] === 'Failed') {
            $status_class = 'bg-danger-hacker text-light';
        }
    }

    return [
        'date' => date('Y-m-d H:i:s', strtotime($t['created_at'])), // Standard timestamp for terminal
        'type' => htmlspecialchars(strtoupper($t['type'])), 
        'amount_display' => $sign . ' ₹ ' . number_format($t['amount'], 2), 
        'amount_class' => $amount_class,
        // Using commission_amount (₹0.00 will show if NULL/missing)
        'commission' => '₹ ' . number_format($t['commission_amount'] ?? 0, 2), 
        'status' => isset($t['status']) ? htmlspecialchars(strtoupper($t['status'])) : 'N/A',
        'status_class' => $status_class
    ];
}

// ------------------------------------------------------------------
// --- Data Fetching Logic (Secure and Robust) ---
// ------------------------------------------------------------------

// Fetch user data (for navigation bar)
$stmt_user = $conn->prepare("SELECT name, wallet_balance FROM users WHERE id=? LIMIT 1");
if (!$stmt_user) {
    die("Error preparing user statement: " . $conn->error);
}
$stmt_user->bind_param("i", $uid);
$stmt_user->execute();
$user = $stmt_user->get_result()->fetch_assoc();
$stmt_user->close();


// --- Fetch Game Bets ---
$game_bets = [];
// FIX APPLIED: Using Prepared Statement (Prevents SQL Injection)
$stmt_games = $conn->prepare("SELECT * FROM game_bets WHERE user_id=? ORDER BY created_at DESC LIMIT 100");

if ($stmt_games) {
    $stmt_games->bind_param("i", $uid);
    if ($stmt_games->execute()) {
        $games_result = $stmt_games->get_result();

        if ($games_result) {
            while($g = $games_result->fetch_assoc()) {
                $win_loss_amount = $g['win_amount'] - $g['bet_amount'];
                $game_bets[] = [
                    'date' => date('Y-m-d H:i:s', strtotime($g['created_at'])), // Standard timestamp for terminal
                    'bet_amount' => '₹ ' . number_format($g['bet_amount'], 2),
                    'result' => htmlspecialchars(strtoupper($g['result'])),
                    'win_loss' => '₹ ' . number_format(abs($win_loss_amount), 2), 
                    'win_loss_class' => ($win_loss_amount >= 0) ? 'text-credit fw-bold' : 'text-debit',
                    'sign' => ($win_loss_amount >= 0) ? '+' : '-'
                ];
            }
             $games_result->free(); 
        }
    } else {
        error_log("SQL Error (Game Bets Execute): " . $stmt_games->error);
    }
    $stmt_games->close(); 
} else {
    error_log("SQL Error (Game Bets Prepare): " . $conn->error);
}


// --- Fetch Transactions and Process them ---
$formatted_transactions = [];
// FIX APPLIED: Using Prepared Statement (Prevents SQL Injection)
$stmt_tx = $conn->prepare("SELECT * FROM transactions WHERE user_id=? ORDER BY created_at DESC LIMIT 100");

if ($stmt_tx) {
    $stmt_tx->bind_param("i", $uid);
    if ($stmt_tx->execute()) {
        $tx_result = $stmt_tx->get_result();
        if ($tx_result) {
            while($t = $tx_result->fetch_assoc()) {
                $formatted_transactions[] = formatTransaction($t);
            }
             $tx_result->free();
        }
    } else {
        error_log("SQL Error (Transactions Execute): " . $stmt_tx->error);
    }
    $stmt_tx->close();
} else {
    error_log("SQL Error (Transactions Prepare): " . $conn->error);
}

// ------------------------------------------------------------------
// --- HTML Display (UPDATED FOR HACKER THEME) ---
// ------------------------------------------------------------------
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Log File: History / User ID <?php echo htmlspecialchars($uid); ?></title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    
    <style>
        /* Custom Styles for Hacker Terminal UI - Keep consistent with wallet.php */
        :root {
            --color-background: #000000;
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
            background-color: #0A0A0A !important;
            border-bottom: 2px solid var(--color-secondary);
            box-shadow: var(--shadow-subtle);
        }
        .navbar-brand .neon-text {
            color: var(--color-primary);
            text-shadow: var(--shadow-glow);
        }

        /* Card Styling (Terminal Block) */
        .modern-card {
            background-color: #0A0A0A;
            border: 1px solid var(--color-secondary);
            border-radius: 4px; /* Boxy look */
            box-shadow: var(--shadow-subtle);
        }

        /* Table Styling */
        .table-hacker {
            --bs-table-bg: #000000;
            --bs-table-color: var(--color-primary);
            --bs-table-striped-bg: #050505; 
            --bs-table-hover-bg: #151515;
            border: 1px dashed var(--color-secondary);
        }
        .table-hacker thead th {
            border-bottom: 1px dashed var(--color-primary) !important;
            color: var(--color-terminal-yellow) !important; /* Yellow for column headers */
            text-transform: uppercase;
        }

        /* Text Colors */
        .text-credit { 
            color: var(--color-primary) !important; 
            text-shadow: var(--shadow-subtle);
        }
        .text-debit { 
            color: var(--color-terminal-red) !important; 
            text-shadow: 0 0 3px rgba(255, 64, 0, 0.6); 
        }
        .text-commission {
            color: var(--color-terminal-yellow) !important; /* Yellow for fees/commission */
        }
        .text-info-hacker {
            color: var(--color-terminal-yellow) !important;
        }

        /* Tabs (Pills) */
        .nav-pills .nav-link {
            border-radius: 0;
            color: var(--color-secondary) !important;
            border: 1px solid var(--color-secondary);
            margin-right: 5px;
            font-weight: bold;
        }
        .nav-pills .nav-link.active, .nav-pills .nav-link:hover {
            background-color: var(--color-primary) !important;
            border-color: var(--color-primary) !important;
            color: #000 !important;
            box-shadow: var(--shadow-subtle);
        }

        /* Badge Overrides (Consistent with wallet.php) */
        .bg-success-hacker { background-color: var(--color-primary) !important; color: #000 !important; }
        .bg-warning-hacker { background-color: var(--color-terminal-yellow) !important; color: #000 !important; }
        .bg-danger-hacker { background-color: var(--color-terminal-red) !important; color: #FFF !important; }
        .bg-secondary-hacker { background-color: #444 !important; color: #FFF !important; }
        .badge {
            border-radius: 0 !important; /* Make it square */
            font-family: 'Consolas', monospace;
        }

        /* Buttons */
        .btn-hacker {
            border-radius: 2px;
            font-weight: bold;
            text-transform: uppercase;
            transition: all 0.2s;
            border: 1px solid;
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
        .btn-primary-hacker {
             background-color: var(--color-primary) !important;
             border-color: var(--color-primary) !important;
             color: #000 !important;
        }
        .btn-primary-hacker:hover {
             box-shadow: 0 0 10px var(--color-primary);
        }
    </style>
</head>
<body>

<nav class="navbar navbar-dark px-3 shadow-sm">
    <span class="navbar-brand mb-0 h1">
        <span class="neon-text">DATA</span> <span class="neon-text" style="color: var(--color-terminal-yellow);">LOGS</span>
    </span>
    <div class="d-flex align-items-center">
        <?php if ($user): ?>
            <span class="me-3 text-muted-hacker">STATUS: <span style="color: var(--color-primary);">ONLINE</span></span>
            <span class="me-3 text-credit fw-bold">WALLET BALANCE: ₹<?php echo number_format($user['wallet_balance'], 2); ?></span>
        <?php endif; ?>
        <a class="btn btn-outline-light btn-sm btn-hacker me-2" href="bet.php">GAME PROTOCOL</a>
        <a class="btn btn-primary-hacker btn-sm btn-hacker ms-0 me-2" href="wallet.php">WALLET ACCESS</a>
        <a class="btn btn-danger btn-sm btn-hacker" href="logout.php">LOGOUT</a>
    </div>
</nav>

<div class="container py-4">
    <h2 class="mb-4" style="border-bottom: 2px dashed var(--color-secondary);">ACCESSING USER TRANSACTION HISTORY [UID: <?php echo htmlspecialchars($uid); ?>]</h2>
    
    <div class="card modern-card shadow">
        <div class="card-header border-bottom border-secondary">
            <ul class="nav nav-pills card-header-pills" id="historyTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="bets-tab" data-bs-toggle="tab" data-bs-target="#game-bets" type="button" role="tab" aria-controls="game-bets" aria-selected="true"><i class="fas fa-dice"></i> GAME BETS</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="tx-tab" data-bs-toggle="tab" data-bs-target="#transactions" type="button" role="tab" aria-controls="transactions" aria-selected="false"><i class="fas fa-wallet"></i> FINANCIAL TRANSFERS</button>
                </li>
            </ul>
        </div>
        
        <div class="card-body">
            <div class="tab-content" id="historyTabsContent">
                
                <div class="tab-pane fade show active" id="game-bets" role="tabpanel" aria-labelledby="bets-tab">
                    <h4 class="card-title mb-3" style="color: var(--color-terminal-yellow);">// LOG: GAME PLAY RECORDS (TOP 100)</h4>
                    <?php if(count($game_bets) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hacker table-striped table-hover table-sm">
                            <thead>
                                <tr>
                                    <th>TIMESTAMP</th>
                                    <th class="text-end">BET VALUE</th>
                                    <th>OUTCOME</th>
                                    <th class="text-end">NET +/- VALUE</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($game_bets as $g): ?>
                                    <tr>
                                        <td><?php echo $g['date']; ?></td>
                                        <td class="text-end text-muted-hacker"><?php echo $g['bet_amount']; ?></td>
                                        <td class="fw-bold <?php echo (strtolower($g['result']) == 'win') ? 'text-credit' : 'text-debit'; ?>"><?php echo $g['result']; ?></td>
                                        <td class="text-end <?php echo $g['win_loss_class']; ?>">
                                            <?php echo $g['sign']; ?> <?php echo $g['win_loss']; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                        <p class="text-center text-muted-hacker py-5 m-0">NO GAME LOGS FOUND. INITIATE GAMEPLAY TO GENERATE DATA.</p>
                    <?php endif; ?>
                </div>
                
                <div class="tab-pane fade" id="transactions" role="tabpanel" aria-labelledby="tx-tab">
                    <h4 class="card-title mb-3" style="color: var(--color-terminal-yellow);">// LOG: WALLET TRANSFER RECORDS (TOP 100)</h4>
                    <?php if(count($formatted_transactions) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hacker table-striped table-hover table-sm">
                            <thead>
                                <tr>
                                    <th>TIMESTAMP</th>
                                    <th>TRANSACTION TYPE</th>
                                    <th class="text-center">STATUS</th>
                                    <th class="text-end">AMOUNT (+/-)</th>
                                    <th class="text-end">FEE CHARGED</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($formatted_transactions as $t): ?>
                                    <tr>
                                        <td><?php echo $t['date']; ?></td>
                                        <td class="<?php echo $t['amount_class']; ?> fw-bold"><?php echo $t['type']; ?></td>
                                        <td class="text-center">
                                            <span class="badge <?php echo $t['status_class']; ?>"><?php echo $t['status']; ?></span>
                                        </td>
                                        <td class="text-end <?php echo $t['amount_class']; ?>">
                                            <?php echo $t['amount_display']; ?>
                                        </td>
                                        <td class="text-end text-commission">
                                            <?php echo $t['commission']; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                        <p class="text-center text-muted-hacker py-5 m-0">NO FINANCIAL LOGS FOUND. TRANSFER PROTOCOL IS IDLE.</p>
                    <?php endif; ?>
                </div>

            </div> 
        </div> 
    </div> 
    <div class="mt-4 text-center">
        <a class="btn btn-primary-hacker btn-lg btn-hacker shadow-sm" href="wallet.php"><i class="fas fa-arrow-left"></i> RETURN TO CONSOLE</a>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>