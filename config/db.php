<?php
// config/db.php
$host = 'localhost';
$user = 'root';
$pass = ''; // Local XAMPP/WAMP के लिए खाली
$db   = 'teen_patti';

// MySQLi कनेक्शन
$conn = new mysqli($host, $user, $pass, $db);

// कनेक्शन त्रुटि की जाँच
if ($conn->connect_error) {
    // यदि यह त्रुटि दिखाई देती है, तो सुनिश्चित करें कि आपका MySQL/MariaDB सर्वर चल रहा है।
    die("DB Connection failed: " . $conn->connect_error);
}

// कैरेक्टर सेट सेट करें
$conn->set_charset("utf8mb4");
?>