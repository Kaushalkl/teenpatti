<?php
// functions.php - Central Utility Functions

// -----------------------------------------------------------
// 1. SESSION MANAGEMENT AND ADMIN AUTHENTICATION
// -----------------------------------------------------------

// सत्र (Session) शुरू करें, यदि पहले से शुरू नहीं हुआ है
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * एडमिन लॉगिन की जाँच करता है और यदि लॉग इन नहीं है तो लॉगिन पेज पर रीडायरेक्ट करता है।
 */
function ensureAdmin() {
    // जांचें कि 'admin_id' सेशन में सेट है
    if (!isset($_SESSION['admin_id'])) {
        // यदि लॉग इन नहीं है, तो लॉगिन पेज पर भेजें
        header('Location: login.php');
        exit;
    }
}

/**
 * सुरक्षा के लिए एक CSRF टोकन उत्पन्न करता है और उसे सेशन में स्टोर करता है।
 * @return string CSRF टोकन
 */
function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        // 32-बाइट क्रिप्टोग्राफिक रूप से सुरक्षित यादृच्छिक डेटा उत्पन्न करें
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); 
    }
    return $_SESSION['csrf_token'];
}


// -----------------------------------------------------------
// 2. DATA FORMATTING (Fixes "format_currency() undefined" error)
// -----------------------------------------------------------

if (!function_exists('format_currency')) {
    /**
     * संख्या को भारतीय मुद्रा फॉर्मेट (₹ X,XXX.XX) में फॉर्मेट करता है।
     * * @param float|int $amount वह संख्या जिसे फॉर्मेट करना है।
     * @return string फॉर्मेट की गई मुद्रा स्ट्रिंग।
     */
    function format_currency($amount) {
        $amount = (float) $amount;
        // दो दशमलव स्थानों और कॉमा विभाजकों का उपयोग करता है
        return number_format($amount, 2, '.', ',');
    }
}

if (!function_exists('format_date_time')) {
    /**
     * डेटाबेस datetime स्ट्रिंग को पढ़ने योग्य फॉर्मेट में फॉर्मेट करता है।
     * * @param string $datetime डेटाबेस से datetime स्ट्रिंग।
     * @return string फॉर्मेट की गई तिथि और समय।
     */
    function format_date_time($datetime) {
        if (empty($datetime) || $datetime === '0000-00-00 00:00:00') {
            return 'N/A';
        }
        // उदाहरण फॉर्मेट: Dec 2, 2025 12:40 PM
        return date("M j, Y h:i A", strtotime($datetime));
    }
}

// -----------------------------------------------------------
// 3. DATABASE SANITIZATION (Security Helper)
// -----------------------------------------------------------

if (!function_exists('sanitize_input')) {
    /**
     * MySQLi का उपयोग करके एक स्ट्रिंग को सुरक्षित (Sanitize) करता है।
     * NOTE: $conn वेरिएबल को ग्लोबल स्कोप में उपलब्ध होना चाहिए।
     * * @param string $data इनपुट डेटा।
     * @return string सुरक्षित डेटा।
     */
    function sanitize_input($data) {
        global $conn; // यह माने कि आपके पास $conn (mysqli object) ग्लोबल स्तर पर है
        if (empty($conn)) {
             // यदि $conn उपलब्ध नहीं है, तो केवल ट्रिम और स्ट्रिप टैग करें (बुनियादी सुरक्षा)
             return htmlspecialchars(trim($data));
        }
        return mysqli_real_escape_string($conn, trim($data));
    }
}
?>