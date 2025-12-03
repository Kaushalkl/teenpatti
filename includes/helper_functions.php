<?php
// C:\xampp\htdocs\teenpatti\includes\helper_functions.php
if (!function_exists('format_currency')) {
    function format_currency($amount) {
        $formatted = number_format((float)$amount, 2, '.', ',');
        return '₹' . $formatted;
    }
}
?>