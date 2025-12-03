<?php
require "../config/db.php";

$username = "kaushal";
$plain = "123456";   // apna real password yaha daalo

$hashed = password_hash($plain, PASSWORD_BCRYPT);

$conn->query("UPDATE admin SET password='$hashed' WHERE username='$username'");

echo "Password updated successfully!";
