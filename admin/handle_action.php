<?php
// admin/handle_action.php - Recharge + Withdrawal Processing (Full Version)

session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../config/db.php';

// CURRENCY FORMAT
if (!function_exists('format_currency')) {
    function format_currency($amount) {
        return number_format((float)$amount, 2, '.', ',');
    }
}

// LOGIN CHECK
if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access.']);
    exit;
}

$current_admin_id = $_SESSION['admin_id'];

// INPUTS
$action = $_POST['action'] ?? '';
$transaction_id = $_POST['transaction_id'] ?? null;
$user_id = $_POST['user_id'] ?? null;
$amount = $_POST['amount'] ?? null;
$commission_percent = $_POST['commission_percent'] ?? null;
$commission_percent_manual = $_POST['commission_percent_manual'] ?? null;


// 🔥 DASHBOARD STATS
function get_dashboard_stats($conn) {
    $query = "
        SELECT 
        (SELECT IFNULL(SUM(amount), 0) FROM transactions WHERE type='recharge' AND status='Completed') AS total_recharges,
        (SELECT IFNULL(SUM(amount), 0) FROM transactions WHERE type='withdrawal' AND status='Completed') AS total_withdrawals,
        (SELECT IFNULL(SUM(amount), 0) FROM admin_commission) AS total_commission
    ";
    $res = $conn->query($query);
    $stats = $res->fetch_assoc();

    return [
        'total_recharges' => format_currency($stats['total_recharges']),
        'total_withdrawals' => format_currency($stats['total_withdrawals']),
        'total_commission' => format_currency($stats['total_commission'])
    ];
}


/***************************************************
 * 1. SET COMMISSION (Recharge + Withdrawal Both)
 ***************************************************/
if ($action === 'set_commission') {

    $transaction_id = (int)$transaction_id;
    $commission_percent = (float)$commission_percent;

    $stmt = $conn->prepare("SELECT amount FROM transactions WHERE id=? AND status='Pending'");
    $stmt->bind_param("i", $transaction_id);
    $stmt->execute();
    $data = $stmt->get_result();

    if ($data->num_rows === 0) {
        echo json_encode(['status'=>'error','message'=>'Transaction not found']);
        exit;
    }

    $amountRow = $data->fetch_assoc();
    $original_amount = $amountRow['amount'];

    $commission_amount = round($original_amount * ($commission_percent / 100), 2);

    $update = $conn->prepare("UPDATE transactions SET commission_percent=?, commission_amount=? WHERE id=?");
    $update->bind_param("ddi", $commission_percent, $commission_amount, $transaction_id);

    if ($update->execute()) {
        echo json_encode([
            'status'=>'success',
            'message'=>'Commission updated',
            'new_commission_amount'=>format_currency($commission_amount)
        ]);
    } else {
        echo json_encode(['status'=>'error','message'=>'Update failed']);
    }
    exit;
}


/***************************************************
 * 2. APPROVE / REJECT (Recharge + Withdrawal)
 ***************************************************/
if ($action === 'approve' || $action === 'reject') {

    $transaction_id = (int)$transaction_id;
    $new_status = ($action === 'approve') ? 'Completed' : 'Rejected';

    $conn->begin_transaction();

    $stmt = $conn->prepare("
        SELECT t.*, u.wallet_balance 
        FROM transactions t
        JOIN users u ON t.user_id = u.id
        WHERE t.id = ? AND t.status='Pending'
        FOR UPDATE
    ");
    $stmt->bind_param("i", $transaction_id);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows === 0) {
        $conn->rollback();
        echo json_encode(['status'=>'error','message'=>'Invalid transaction']);
        exit;
    }

    $t = $res->fetch_assoc();

    $user_id = $t['user_id'];
    $amount = (float)$t['amount'];
    $type = trim($t['type']);
    $commission_amount = (float)$t['commission_amount'];

    try {

        /********* APPROVE *********/
        if ($action === 'approve') {

            if ($type === 'recharge') {
                // Add money to wallet
                $wallet = $conn->prepare("UPDATE users SET wallet_balance = wallet_balance + ? WHERE id=?");
                $wallet->bind_param("di", $amount, $user_id);
                $wallet->execute();

                // Admin commission
                if ($commission_amount > 0) {
                    $c = $conn->prepare("
                        INSERT INTO admin_commission (transaction_id, amount, admin_id, created_at)
                        VALUES (?, ?, ?, NOW())
                    ");
                    $c->bind_param("idi", $transaction_id, $commission_amount, $current_admin_id);
                    $c->execute();
                }

            } elseif ($type === 'withdrawal') {
                // Withdrawal approve → No wallet change (already deducted earlier)
            }

            $msg = "Transaction #$transaction_id ($type) approved.";

        }

        /********* REJECT *********/
        else if ($action === 'reject') {

            if ($type === 'withdrawal') {
                // Add back amount to wallet
                $wallet = $conn->prepare("UPDATE users SET wallet_balance = wallet_balance + ? WHERE id=?");
                $wallet->bind_param("di", $amount, $user_id);
                $wallet->execute();
            }

            $msg = "Transaction #$transaction_id ($type) rejected.";
        }


        // Update transaction status
        $update = $conn->prepare("UPDATE transactions SET status=?, processed_by=? WHERE id=?");
        $update->bind_param("sii", $new_status, $current_admin_id, $transaction_id);
        $update->execute();

        $conn->commit();

        echo json_encode([
            'status'=>'success',
            'message'=>$msg,
            'stats'=>get_dashboard_stats($conn)
        ]);
        exit;

    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['status'=>'error','message'=>$e->getMessage()]);
        exit;
    }
}


/***************************************************
 * 3. MANUAL RECHARGE (Admin Adds Money)
 ***************************************************/
if ($action === 'manual_recharge') {

    $user_id = (int)$user_id;
    $amount = (float)$amount;
    $commission_percent = (float)$commission_percent_manual;

    $commission_amt = round($amount * ($commission_percent / 100), 2);

    $conn->begin_transaction();

    try {
        // Wallet update
        $wallet = $conn->prepare("UPDATE users SET wallet_balance = wallet_balance + ? WHERE id=?");
        $wallet->bind_param("di", $amount, $user_id);
        $wallet->execute();

        // Insert transaction
        $trx = $conn->prepare("
            INSERT INTO transactions
            (user_id, type, amount, status, commission_percent, commission_amount, processed_by, created_at)
            VALUES (?, 'recharge', ?, 'Completed', ?, ?, ?, NOW())
        ");
        $trx->bind_param("idddi", $user_id, $amount, $commission_percent, $commission_amt, $current_admin_id);
        $trx->execute();
        $new_id = $conn->insert_id;

        // Insert commission
        if ($commission_amt > 0) {
            $c = $conn->prepare("
                INSERT INTO admin_commission (transaction_id, amount, admin_id, created_at)
                VALUES (?, ?, ?, NOW())
            ");
            $c->bind_param("idi", $new_id, $commission_amt, $current_admin_id);
            $c->execute();
        }

        $conn->commit();
        echo json_encode(['status'=>'success','message'=>'Manual recharge done.']);
        exit;

    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['status'=>'error','message'=>$e->getMessage()]);
        exit;
    }
}


echo json_encode(['status'=>'error','message'=>'Invalid Action']);
exit;
