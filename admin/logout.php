<?php
// admin/logout.php
session_start();

// सभी सेशन वेरिएबल्स को हटा दें
$_SESSION = array();

// सेशन कुकी को समाप्त करें (यदि मौजूद हो)
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// सेशन को नष्ट करें
session_destroy();

// लॉगिन पेज पर रीडायरेक्ट करें
header('Location: login.php');
exit;
?>