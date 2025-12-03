<?php
// user/recharge_action.php
require_once 'functions.php';
// ensureLoggedIn() and $conn (database connection) are assumed to be provided by functions.php
ensureLoggedIn();
$user_id = $_SESSION['user_id'];

// Check if the request method and action are correct for recharge
if($_SERVER['REQUEST_METHOD'] === 'POST'){

    $amount = filter_input(INPUT_POST, 'amount', FILTER_VALIDATE_FLOAT);
    
    // Basic validation
    if($amount === false || $amount <= 0 || !is_numeric($_POST['amount'])){
        $_SESSION['message'] = 'Invalid recharge amount.';
        $_SESSION['message_type'] = 'danger';
        header('Location: wallet.php'); // Redirect to wallet.php
        exit;
    }

    $amount = round($amount, 2);
    $commission_pct = 0.05; // 5% admin commission
    $commission = round($amount * $commission_pct, 2);
    $credited = round($amount - $commission, 2);

    // --- START ATOMIC TRANSACTION ---
    $conn->begin_transaction();

    try {
        // 1. Update User Wallet
        $stmt_user = $conn->prepare("UPDATE users SET wallet_balance = wallet_balance + ? WHERE id=?");
        if (!$stmt_user) {
             throw new Exception("Prepare statement failed: " . $conn->error);
        }
        $stmt_user->bind_param("di", $credited, $user_id);
        if (!$stmt_user->execute()) {
            throw new Exception("User wallet update failed: " . $stmt_user->error);
        }
        $stmt_user->close();

        // 2. Log Transaction (Full details)
        $stmt_trans = $conn->prepare("INSERT INTO transactions (user_id, type, amount, admin_commission, remark) VALUES (?, ?, ?, ?, ?)");
        if (!$stmt_trans) {
             throw new Exception("Prepare statement failed: " . $conn->error);
        }
        $type = 'Recharge';
        $remark = 'User recharge via dashboard (₹' . number_format($credited, 2) . ' credited)';
        $stmt_trans->bind_param("isdds", $user_id, $type, $amount, $commission, $remark);
        if (!$stmt_trans->execute()) {
            throw new Exception("Transaction log failed: " . $stmt_trans->error);
        }
        $stmt_trans->close();

        // 3. Log Admin Commission
        $stmt_admin = $conn->prepare("INSERT INTO admin_commission (source, amount) VALUES (?, ?)");
        if (!$stmt_admin) {
             throw new Exception("Prepare statement failed: " . $conn->error);
        }
        $src = 'Recharge Commission';
        $stmt_admin->bind_param("sd", $src, $commission);
        if (!$stmt_admin->execute()) {
            throw new Exception("Admin commission log failed: " . $stmt_admin->error);
        }
        $stmt_admin->close();

        // If all steps succeeded, commit the transaction
        $conn->commit();
        $_SESSION['message'] = 'Recharge successful! ₹' . number_format($amount, 2) . ' processed. ₹' . number_format($credited, 2) . ' credited to your wallet.';
        $_SESSION['message_type'] = 'success';

    } catch (Exception $e) {
        // If any step failed, rollback all changes
        $conn->rollback();
        error_log("Atomic Recharge Failed for User ID {$user_id}: " . $e->getMessage());
        $_SESSION['message'] = 'Recharge failed due to a system error. Please try again.';
        $_SESSION['message_type'] = 'danger';
    } finally {
        // Close the connection
        $conn->close();
    }
}

// Redirect back to the user's dashboard (or wallet page)
header('Location: wallet.php'); // Redirect to wallet.php
exit;