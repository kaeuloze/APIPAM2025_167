<?php
// Aktifkan error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Cek lokasi error log
echo 'error_log: ' . ini_get('error_log') . '<br>';

// Test error
echo $undefined_variable; // Akan menghasilkan notice