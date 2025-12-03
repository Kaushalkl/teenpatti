<?php
session_start();
// सुनिश्चित करें कि db.php में $conn वैरिएबल मौजूद है
require_once "../config/db.php";

// ---------------------------
// अस्थायी डीबगिंग शुरू (Debugging Start)
// ---------------------------
// यदि आप $conn ऑब्जेक्ट को देखना चाहते हैं कि वह ठीक से जुड़ा है या नहीं:
// echo "<pre>"; var_dump($conn); echo "</pre>";
// यदि आप पासवर्ड हैश देखना चाहते हैं, तो DBG_MODE को true करें
define('DBG_MODE', true); 
// ---------------------------
// अस्थायी डीबगिंग समाप्त (Debugging End)
// ---------------------------


if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit;
}

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

if ($username === '' || $password === '') {
    $_SESSION['admin_error'] = "Username और Password आवश्यक हैं!";
    header('Location: login.php');
    exit;
}

// 1. डेटाबेस से यूजरनेम द्वारा एडमिन रिकॉर्ड फ़ेच करें
$stmt = $conn->prepare("SELECT id, username, password FROM admin WHERE username = ? LIMIT 1");
if (!$stmt) {
    // डेटाबेस स्टेटमेंट तैयारी में विफलता
    error_log("Admin login prepare failed: " . $conn->error);
    $_SESSION['admin_error'] = "Database error (101).";
    header('Location: login.php');
    exit;
}
$stmt->bind_param("s", $username);
$stmt->execute();
$res = $stmt->get_result();
$stmt->close();

if ($res->num_rows !== 1) {
    $_SESSION['admin_error'] = "Admin Not Found! (उपयोगकर्ता नाम गलत है या डेटाबेस से जुड़ा नहीं है)";
    header('Location: login.php');
    exit;
}

$admin = $res->fetch_assoc();
$dbpw = $admin['password'];

// ---------------------------
// अस्थायी डीबगिंग ब्लॉक: पासवर्ड हैश चेक करें
// ---------------------------
if (defined('DBG_MODE') && DBG_MODE === true) {
    echo "<h1>DEBUG MODE ACTIVE</h1>";
    echo "Admin record found. Username: " . htmlspecialchars($admin['username']) . "<br>";
    echo "Input Password (Plain): " . htmlspecialchars($password) . "<br>";
    echo "DB Hashed Password: " . htmlspecialchars($dbpw) . "<br>";
    echo "If the hashed password is empty or not starting with '$2y$', the password in the database is not hashed correctly.<br>";
    // exit; // यदि आप इसे आगे नहीं चलाना चाहते हैं तो इसे अन-कमेंट करें
}
// ---------------------------
// डीबगिंग ब्लॉक समाप्त
// ---------------------------


// 2. पासवर्ड सत्यापित करें (Hashing के कारण)
if (password_verify($password, $dbpw)) {
    // login ok
    $_SESSION['admin_logged_in'] = true;
    $_SESSION['admin_id'] = $admin['id'];
    $_SESSION['admin_username'] = $admin['username'];
    
    // डीबग मोड से बाहर निकलें
    if (defined('DBG_MODE') && DBG_MODE === true) {
        header('Location: dashboard.php?debug_success=1');
    } else {
        header('Location: dashboard.php');
    }
    exit;
} else {
    // Incorrect Password!
    $_SESSION['admin_error'] = "Incorrect Password! (आपका इनपुट पासवर्ड डेटाबेस से मेल नहीं खाता)";
    header('Location: login.php');
    exit;
}

?>