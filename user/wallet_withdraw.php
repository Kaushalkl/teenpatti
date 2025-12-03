<?php
// user/wallet_withdraw.php - Pending Withdrawal Request (User Payout Methods Table Integrated)

require_once __DIR__ . '/../config/db.php'; 
session_start();

// 1. Authentication Check
if (!isset($_SESSION['user_id'])) { 
    header('Location: login.php'); 
    exit; 
}

$user_id = (int)$_SESSION['user_id'];
// POST डेटा को साफ करें (Sanitize POST data)
$amount = floatval($_POST['withdraw_amount'] ?? 0);
$bank_account = trim($_POST['bank_account'] ?? '');
$bank_ifsc = trim($_POST['bank_ifsc'] ?? '');
$upi_id = trim($_POST['upi_id'] ?? '');
$save_details = isset($_POST['save_details']);

// Withdrawal Logic (Transaction begins here)
$conn->begin_transaction();

try {
    // --- Input Validation ---
    if ($amount < 100) { 
        throw new Exception("निकासी (Withdrawal) की न्यूनतम राशि ₹100 है।"); 
    }
    if (empty($bank_account) && empty($upi_id)) {
        throw new Exception("निकासी के लिए आपको बैंक विवरण या UPI ID प्रदान करना होगा।");
    }

    // Payout Method Data Preparation
    $payout_method_type = !empty($upi_id) ? 'upi' : 'bank';
    $payout_bank_account = ($payout_method_type === 'bank') ? $bank_account : NULL;
    $payout_bank_ifsc = ($payout_method_type === 'bank') ? $bank_ifsc : NULL;
    $payout_upi_id = ($payout_method_type === 'upi') ? $upi_id : NULL;
    $account_holder = $_POST['account_holder'] ?? 'N/A'; // Assuming you are sending account_holder name

    // 2. Fetch Current Wallet Balance and check eligibility (Lock row for update)
    $stmt_lock = $conn->prepare("SELECT wallet_balance FROM users WHERE id=? FOR UPDATE");
    if (!$stmt_lock) {
        throw new Exception("Database prepare error (Lock): " . $conn->error);
    }
    $stmt_lock->bind_param("i", $user_id);
    $stmt_lock->execute();
    $user_data = $stmt_lock->get_result()->fetch_assoc();
    $stmt_lock->close();

    if (!$user_data || $user_data['wallet_balance'] < $amount) {
        throw new Exception("निकासी के लिए अपर्याप्त बैलेंस (Insufficient balance)।");
    }

    // 3. IMMEDIATELY DEBIT Wallet Balance (फंड्स को लॉक करना)
    $stmt_debit = $conn->prepare("UPDATE users SET wallet_balance = wallet_balance - ? WHERE id=?");
    if (!$stmt_debit) {
        throw new Exception("Database prepare error (Debit): " . $conn->error);
    }
    $stmt_debit->bind_param("di", $amount, $user_id);
    if (!$stmt_debit->execute()) {
        throw new Exception("Wallet debit failed.");
    }
    $stmt_debit->close();

    // 4. Update/Insert User Payout Details using 'user_payout_methods' table
    if ($save_details) {
        // Step 4.1: मौजूदा (existing) विवरण की जाँच करें
        $check_stmt = $conn->prepare("SELECT id FROM user_payout_methods WHERE user_id = ? AND method_type = ?");
        $check_stmt->bind_param("is", $user_id, $payout_method_type);
        $check_stmt->execute();
        $existing_id = $check_stmt->get_result()->fetch_assoc()['id'] ?? null;
        $check_stmt->close();

        if ($existing_id) {
            // Step 4.2: यदि मौजूदा विवरण है, तो UPDATE करें
            $update_sql = "
                UPDATE user_payout_methods 
                SET account_holder=?, bank_account=?, bank_ifsc=?, upi_id=?, is_default=1 
                WHERE id=?
            ";
            $stmt_update_details = $conn->prepare($update_sql);
            // ध्यान दें: $payout_bank_account और $payout_upi_id NULL हो सकते हैं, 
            // लेकिन mysqli NULL को 's' (स्ट्रिंग) के रूप में बाइंड कर सकता है।
            $stmt_update_details->bind_param("ssssi", $account_holder, $payout_bank_account, $payout_bank_ifsc, $payout_upi_id, $existing_id);
        } else {
            // Step 4.3: यदि नया विवरण है, तो INSERT करें
            // सभी अन्य तरीकों को गैर-डिफ़ॉल्ट (non-default) पर सेट करें
            $conn->query("UPDATE user_payout_methods SET is_default = 0 WHERE user_id = {$user_id}");
            
            $insert_sql = "
                INSERT INTO user_payout_methods 
                (user_id, method_type, account_holder, bank_account, bank_ifsc, upi_id, is_default, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, 1, NOW())
            ";
            $stmt_update_details = $conn->prepare($insert_sql);
            $stmt_update_details->bind_param("isssss", $user_id, $payout_method_type, $account_holder, $payout_bank_account, $payout_bank_ifsc, $payout_upi_id);
        }

        if ($stmt_update_details) {
            if (!$stmt_update_details->execute()) {
                 error_log("Payout Details Save Failed: " . $stmt_update_details->error);
            }
            $stmt_update_details->close();
        }
    }


    // 5. Log Transaction with PENDING Status (Payout details logged in transactions table)
    $sql_transaction = "
        INSERT INTO transactions 
            (user_id, type, amount, status, payout_method, bank_account, bank_ifsc, upi_id, remark, created_at) 
        VALUES 
            (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW()) 
    ";
    
    $type = 'withdrawal'; 
    $status = 'pending';
    $remark = 'User requested withdrawal (funds debited, waiting admin payout).';

    $stmt_trans = $conn->prepare($sql_transaction);
    if (!$stmt_trans) {
        throw new Exception("Database prepare error (Transaction): " . $conn->error);
    }
    
    // FIX APPLIED HERE: Added one extra 's' to match the 9 bind variables.
    $stmt_trans->bind_param(
        "isdssssss", // अब 9 टाइप कैरेक्टर हैं: i, s, d, s, s, s, s, s, s
        $user_id, 
        $type, 
        $amount, 
        $status, 
        $payout_method_type,
        $payout_bank_account,
        $payout_bank_ifsc,
        $payout_upi_id,
        $remark // यह 9वां वेरिएबल था
    );
    // ---------------------------------------------------------------------------------
    
    if (!$stmt_trans->execute()) {
        throw new Exception("Transaction logging failed. MySQL Error: " . $stmt_trans->error);
    }
    $stmt_trans->close();

    $conn->commit(); // सफलता पर कमिट करें

    // --- 6. Redirect with Success Status ---
    $_SESSION['message'] = 'निकासी अनुरोध (Withdrawal request) ₹' . number_format($amount, 2) . ' सफलतापूर्वक सबमिट किया गया। वॉलेट से राशि काट ली गई है और एडमिन भुगतान (Admin payout) का इंतजार है।';
    $_SESSION['message_type'] = 'warning'; 

} catch (Exception $e) {
    $conn->rollback(); // विफलता पर रोलबैक करें
    
    // सुनिश्चित करें कि यूजर को केवल सुरक्षित त्रुटि संदेश मिले
    $display_message = strpos($e->getMessage(), 'Database') !== false || strpos($e->getMessage(), 'MySQL') !== false
                       ? 'निकासी प्रक्रिया विफल। कृपया बाद में प्रयास करें।' 
                       : $e->getMessage();
                       
    $_SESSION['message'] = 'निकासी विफल: ' . $display_message;
    $_SESSION['message_type'] = 'danger';
}

header('Location: wallet.php');
exit;
?>