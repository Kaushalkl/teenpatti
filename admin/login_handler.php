<?php
session_start();

// 1. डेटाबेस कनेक्शन फ़ाइल शामिल करें
// यह सुनिश्चित करता है कि ../config/db.php मौजूद है और $conn वेरिएबल उपलब्ध है।
// यदि फ़ाइल नहीं मिलती है, तो PHP एक गंभीर (Fatal) त्रुटि देगा।
// हमने इस फ़ाइल को इस धारणा पर फिक्स किया है कि यह पथ सही है।
if (!file_exists("../config/db.php")) {
    $_SESSION['admin_error'] = "सिस्टम त्रुटि: आवश्यक कॉन्फ़िगरेशन फ़ाइल (db.php) नहीं मिली। कृपया फ़ाइल पथ(../config/db.php) की जाँच करें।";
    header('Location: login.php');
    exit;
}
require_once "../config/db.php"; 

// 2. कनेक्शन जाँच
if (!isset($conn) || $conn->connect_error) {
    // यह जाँचता है कि db.php ने $conn वेरिएबल बनाया है और कनेक्शन सफल रहा है।
    $_SESSION['admin_error'] = "सिस्टम त्रुटि: डेटाबेस कनेक्शन विफल। कृपया db.php में credentials (user, pass, db) की जाँच करें।";
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit;
}

// इनपुट प्राप्त करें और साफ़ करें
$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

if (empty($username) || empty($password)) {
    $_SESSION['admin_error'] = "Username और Password आवश्यक हैं!";
    header('Location: login.php');
    exit;
}

$stmt = null;
try {
    // 3. SQL तैयारी और निष्पादन
    $stmt = $conn->prepare("SELECT id, username, password FROM admin WHERE username = ? LIMIT 1");
    
    if ($stmt === false) {
        // SQL सिंटैक्स या तालिका नाम त्रुटि को पकड़ें
        throw new Exception("SQL तैयारी विफल: " . $conn->error);
    }
    
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows !== 1) {
        // यूजरनेम मौजूद नहीं है
        $_SESSION['admin_error'] = "अवैध Username!";
        header('Location: login.php');
        exit;
    }

    $admin = $res->fetch_assoc();
    $dbpw = $admin['password'];
    
    // =========================================================
    // !!! उन्नत डीबगिंग लाइनें !!!
    // यह जानकारी login.php पर प्रदर्शित होगी
    // =========================================================
    $_SESSION['debug_hash'] = $dbpw;
    $_SESSION['debug_hash_length'] = strlen($dbpw);

    // 4. पासवर्ड सत्यापित करें
    if (password_verify($password, $dbpw)) {
        // लॉगिन सफल
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_id'] = $admin['id'];
        $_SESSION['admin_username'] = $admin['username'];
        
        // सफलता पर, डीबग वेरिएबल हटाएँ
        unset($_SESSION['debug_hash'], $_SESSION['debug_hash_length']);

        // डैशबोर्ड पर रीडायरेक्ट करें
        header('Location: dashboard.php'); 
        exit;
    } else {
        // पासवर्ड गलत है
        $_SESSION['admin_error'] = "Incorrect Password! (Debug info available on login page)";
        header('Location: login.php');
        exit;
    }

} catch (Exception $e) {
    // किसी भी अप्रत्याशित PHP या DB त्रुटि को पकड़ें
    error_log("Admin Login Exception: " . $e->getMessage());
    $_SESSION['admin_error'] = "लॉगिन प्रक्रिया में एक अप्रत्याशित त्रुटि हुई।";
    header('Location: login.php');
    exit;

} finally {
    // सुनिश्चित करें कि स्टेटमेंट बंद हो जाए, भले ही कोई त्रुटि हो
    if ($stmt !== null && $stmt instanceof mysqli_stmt) {
        $stmt->close();
    }
}
?>