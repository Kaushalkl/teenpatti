<?php
session_start();
require_once '../db.php';

$data = json_decode(file_get_contents("php://input"),true);
$wallet = floatval($data['wallet'] ?? 0);
$user_id = $_SESSION['user_id'] ?? 0;

$stmt = $conn->prepare("UPDATE users SET wallet_balance=? WHERE id=?");
$stmt->bind_param("di",$wallet,$user_id);
$stmt->execute();

echo json_encode(['success'=>true]);
?>
