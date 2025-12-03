<?php
require_once "../config/db.php";

$newPass = "123456";
$hash = password_hash($newPass, PASSWORD_BCRYPT);

echo "New Hash is: ".$hash."<br>";

$sql = "UPDATE admin SET password='$hash' WHERE username='kaushal'";

if ($conn->query($sql)) {
    echo "Password updated successfully!";
} else {
    echo "Error: ".$conn->error;
}
