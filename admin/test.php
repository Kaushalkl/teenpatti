<?php
$hash = '$2y$10$oBNbGkVjLSuWKT/AFoZKGe71x8hXAz5Dw1TtgnkcyPgMa6aXslF1.'; // वही hash जो DB में है

if (password_verify("123456", $hash)) {
    echo "OK — Password MATCHED ✔";
} else {
    echo "ERROR — Password NOT MATCHED ❌";
}
