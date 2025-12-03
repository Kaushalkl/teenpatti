<?php
// C:\xampp\htdocs\teenpatti\user\wallet_recharge.php

// functions.php में डेटाबेस कनेक्शन (e.g., $conn) शामिल होना चाहिए
require_once '../functions.php';

// ensureAdmin() फ़ंक्शन एडमिन की जाँच करता है और $conn कनेक्शन ऑब्जेक्ट उपलब्ध कराता है
ensureAdmin(); // Assuming this function makes $conn (your mysqli object) available globally or through return/reference

$msg = '';

// POST अनुरोध को संभालने वाला लॉजिक
if($_SERVER['REQUEST_METHOD'] === 'POST'){
    
    // Ensure $conn is available (assuming it's available after ensureAdmin/functions.php)
    if (!isset($conn) || $conn->connect_error) {
        die("Database connection error.");
    }

    $user_id = intval($_POST['user_id']);
    $amount = floatval($_POST['amount']);
    
    if($amount > 0){
        $commission_pct = 0.05;
        $commission = round($amount * $commission_pct, 2);
        $credited = round($amount - $commission, 2);

        // --- 1. Update User Wallet Balance ---
        $stmt_update = $conn->prepare("UPDATE users SET wallet_balance = wallet_balance + ? WHERE id=?");
        $stmt_update->bind_param("di", $credited, $user_id);
        $stmt_update->execute();
        $stmt_update->close(); // Close statement

        // --- 2. Insert Transaction Record ---
        // FIX: 'admin_commission' को 'commission_amount' से बदला गया है।
        $sql_transaction = "INSERT INTO transactions (user_id, type, amount, commission_amount, remark) VALUES (?, ?, ?, ?, ?)";
        $stmt_trans = $conn->prepare($sql_transaction);
        
        $type = 'recharge';
        $remark = 'Admin recharge';
        
        $stmt_trans->bind_param("isdds", $user_id, $type, $amount, $commission, $remark);
        $stmt_trans->execute();
        $stmt_trans->close(); // Close statement

        // --- 3. Insert Admin Commission Record ---
        $stmt_commission = $conn->prepare("INSERT INTO admin_commission (source, amount) VALUES (?,?)");
        $src = 'recharge'; 
        
        $stmt_commission->bind_param("sd", $src, $commission); 
        $stmt_commission->execute();
        $stmt_commission->close(); // Close statement

        $msg = "Recharged user ID $user_id with ₹" . number_format($credited, 2) . " (commission ₹" . number_format($commission, 2) . ").";
    }
}

// --- Fetch Users for Dropdown ---
// NOTE: $conn->query() is used here, which is fine for simple SELECT.
$users = $conn->query("SELECT id, name, email, wallet_balance FROM users ORDER BY id DESC");

// Close connection after all database operations are complete (Optional, depending on when your application logic ends)
// $conn->close(); 
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Admin Recharge</title>
</head>
<body>
    <h2>Recharge User</h2>
    <?php if($msg) echo "<p style='color:green;'>$msg</p>"; ?>
    <form method="post">
        <select name="user_id" required>
            <?php 
            // Check if $users is valid before attempting to fetch
            if ($users && $users->num_rows > 0):
                while($u = $users->fetch_assoc()): 
            ?>
                <option value="<?php echo $u['id']; ?>">
                    <?php echo htmlspecialchars($u['name']) . " (" . htmlspecialchars($u['email']) . " | ₹" . number_format($u['wallet_balance'], 2) . ")"; ?>
                </option>
            <?php 
                endwhile; 
                $users->free(); // Free result set
            else:
            ?>
                <option value="" disabled>No users found</option>
            <?php endif; ?>
        </select>
        Amount: <input type="number" name="amount" min="10" step="1" required>
        <button type="submit">Recharge</button>
    </form>
    <p><a href="index.php">Back</a></p>
</body>
</html>