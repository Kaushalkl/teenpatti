<?php
// admin/users.php
require_once __DIR__ . '/../config/db.php';
session_start();
if(!isset($_SESSION['admin_id'])){ header('Location: login.php'); exit; }

// NOTE: You must ensure $conn and $res are properly initialized and defined
// For this example, I'll assume they are handled correctly by the includes.
// $res = $conn->query("SELECT * FROM users ORDER BY id DESC LIMIT 500"); 

// --- START: Mock Data for Demonstration if DB is not available ---
// Remove this block in your actual application
class MockUser {
    public function fetch_assoc() {
        static $users = [
            ['id' => 101, 'name' => 'Agent Smith', 'email' => 'asmith@neo.com', 'wallet_balance' => 45000.75, 'created_at' => '2025-11-01 10:30:00'],
            ['id' => 102, 'name' => 'Trinity', 'email' => 'trinity@matrix.org', 'wallet_balance' => 99999.00, 'created_at' => '2025-11-05 14:45:00'],
            ['id' => 103, 'name' => 'Morpheus', 'email' => 'leader@zion.net', 'wallet_balance' => 12500.50, 'created_at' => '2025-11-10 08:20:00'],
        ];
        return array_shift($users);
    }
}
// If $res is not set from DB, use mock data
if (!isset($res)) {
    $res = new MockUser();
}
// --- END: Mock Data ---

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Admin • Users // STATUS: ONLINE</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  
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
        letter-spacing: 0.5px;
    }

    /* Override Bootstrap Table Dark */
    .table-dark {
        --bs-table-bg: var(--color-background-secondary);
        --bs-table-color: var(--color-text-light);
        border: 1px solid var(--color-accent-primary);
        box-shadow: var(--shadow-elevation-1);
        border-radius: 4px;
    }
    .table-dark th {
        background-color: var(--color-background-tertiary) !important;
        color: var(--color-text-light);
        border-bottom: 1px dashed var(--color-accent-primary) !important; /* Dashed separator */
        text-transform: uppercase;
        font-weight: bold;
    }
    .table-dark td {
        border-color: var(--color-border);
    }

    /* Zebra striping with subtle glow */
    .table-dark > tbody > tr:nth-of-type(odd) > * {
        background-color: rgba(0, 255, 65, 0.03); 
    }
    
    /* Hover effect for table rows */
    .table-dark > tbody > tr:hover {
        background-color: var(--color-background-tertiary) !important;
        box-shadow: 0 0 10px rgba(0, 255, 65, 0.2);
        cursor: pointer;
        color: var(--color-accent-primary);
    }

    /* Styling for the Back Button (Bootstrap .btn-secondary) */
    .btn-secondary {
        --bs-btn-color: var(--color-text-light);
        --bs-btn-bg: var(--color-background-secondary);
        --bs-btn-border-color: var(--color-accent-primary);
        --bs-btn-hover-color: var(--color-background-primary); /* Black text on hover */
        --bs-btn-hover-bg: var(--color-accent-primary); /* Green background on hover */
        --bs-btn-hover-border-color: var(--color-text-light);
        --bs-btn-active-bg: var(--color-accent-hover);
        --bs-btn-active-border-color: var(--color-text-light);
        border-radius: 2px; /* Sharp edges */
        box-shadow: var(--shadow-elevation-1);
        transition: all 0.2s linear;
    }
    
    /* Stronger button glow on hover */
    .btn-secondary:hover {
        box-shadow: var(--shadow-elevation-2);
        color: var(--color-background-primary) !important;
    }
    
    /* Page Header */
    h3 {
        color: var(--color-accent-primary);
        text-shadow: 0 0 5px rgba(0, 255, 65, 0.5);
        margin-bottom: 25px;
    }

  </style>
  </head>
<body>
<div class="container py-5"> <h3>// ACCESS GRANTED: User Database Dump</h3>
  <div class="table-responsive">
    <table class="table table-dark table-striped">
      <thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Wallet</th><th>Joined</th></tr></thead>
      <tbody>
        <?php 
        // Resetting the mock data pointer if you use the mock class
        if (!isset($conn)) { $res = new MockUser(); } 
        while($u = $res->fetch_assoc()): 
        ?>
          <tr>
            <td><?php echo $u['id']; ?></td>
            <td><?php echo htmlspecialchars($u['name']); ?></td>
            <td><?php echo htmlspecialchars($u['email']); ?></td>
            <td style="color: <?php echo ($u['wallet_balance'] > 50000) ? '#00FF41' : '#00C740'; ?>;">
                ₹ <?php echo number_format($u['wallet_balance'],2); ?>
            </td>
            <td><?php echo $u['created_at']; ?></td>
          </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>
  <a class="btn btn-secondary" href="dashboard.php">cd ../dashboard</a>
</div>
</body>
</html>