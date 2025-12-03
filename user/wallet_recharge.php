<?php
// user/wallet_recharge.php - Pending Recharge Request

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helper_functions.php';

session_start();

// --- 1. Authentication Check ---
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = (int)$_SESSION['user_id'];

// --- 2. Input Validation ---
$amount = filter_input(INPUT_POST, 'quick_amount', FILTER_VALIDATE_FLOAT);

if ($amount === false || $amount < 10) {
    $_SESSION['message'] = 'Invalid amount entered for recharge. Minimum recharge is ₹' . format_currency(10) . '.';
    $_SESSION['message_type'] = 'danger';
    header('Location: wallet.php');
    exit;
}

// --- 3. Commission Calculation ---
$commission_pct = 5.0; // 5% commission
$commission_amount = round($amount * ($commission_pct / 100), 2);

// --- 4. Insert Transaction (Pending) ---
$sql = "
    INSERT INTO transactions 
        (user_id, type, amount, status, commission_percent, commission_amount, remark)
    VALUES 
        (?, 'recharge', ?, 'Pending', ?, ?, ?)
";

if ($stmt = $conn->prepare($sql)) {
    $remark = 'User submitted recharge request (online payment).';
    
    // Bind parameters: i=integer, d=double, d=double, d=double, s=string
    $stmt->bind_param("iddds", $user_id, $amount, $commission_pct, $commission_amount, $remark);

    if ($stmt->execute()) {
        $_SESSION['message'] = "Recharge request of ₹" . format_currency($amount) . " submitted successfully. Waiting for Admin approval before funds are added to your wallet.";
        $_SESSION['message_type'] = 'warning';
    } else {
        $_SESSION['message'] = 'Error submitting recharge request. Database execution error: ' . $stmt->error;
        $_SESSION['message_type'] = 'danger';
    }

    $stmt->close();
} else {
    $_SESSION['message'] = 'Database preparation error. Please ensure the transactions table has the correct columns.';
    $_SESSION['message_type'] = 'danger';
}

// --- 5. Redirect Back to Wallet Page ---
header('Location: wallet.php');
exit;
