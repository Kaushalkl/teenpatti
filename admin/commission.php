<?php
// admin/commission.php
require_once __DIR__ . '/../config/db.php';
session_start();
if(!isset($_SESSION['admin_id'])){ header('Location: login.php'); exit; }

if($_SERVER['REQUEST_METHOD'] === 'POST'){
    $user_id = intval($_POST['user_id'] ?? 0);
    $amount = floatval($_POST['amount'] ?? 0);
    $apply_commission = ($_POST['apply_commission'] ?? '1') === '1';

    if($user_id <= 0 || $amount <= 0){
        $_SESSION['msg'] = "Invalid input.";
        header('Location: dashboard.php');
        exit;
    }

    $commission_pct = 0.05;
    $commission = $apply_commission ? round($amount * $commission_pct,2) : 0.00;
    $credited = round($amount - $commission,2);

    // update wallet
    $stmt = $conn->prepare("UPDATE users SET wallet_balance = wallet_balance + ? WHERE id = ?");
    $stmt->bind_param("di",$credited,$user_id);
    $stmt->execute();

    // log transaction
    $stmt = $conn->prepare("INSERT INTO transactions (user_id,type,amount,admin_commission,remark) VALUES (?,?,?,?,?)");
    $type='recharge'; $remark = 'Admin recharge';
    $stmt->bind_param("isdds",$user_id,$type,$amount,$commission,$remark);
    $stmt->execute();

    // log commission
    if($commission > 0){
        $stmt = $conn->prepare("INSERT INTO admin_commission (source,amount) VALUES (?,?)");
        $src = 'recharge';
        $stmt->bind_param("sd",$src,$commission);
        $stmt->execute();
    }

    $_SESSION['msg'] = "User credited ₹{$credited} (commission ₹{$commission}).";
    header('Location: dashboard.php');
    exit;
}
header('Location: dashboard.php');
exit;
