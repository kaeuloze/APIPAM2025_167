<?php
// Mulai output buffering di LINE PALING ATAS
ob_start();

// Nonaktifkan error display (biar tidak muncul di output JSON)
ini_set('display_errors', 0);
error_reporting(0);

// Set header JSON
header("Content-Type: application/json");

// Include koneksi database
require_once "../koneksi.php";

// Pastikan request method POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_end_clean();
    echo json_encode([
        "success" => false,
        "message" => "Method tidak diizinkan"
    ]);
    exit;
}

// Ambil data dari POST
$username = $_POST['username'] ?? 'admin';
$password = $_POST['password'] ?? 'admin123';

// Validasi input
if (empty($username) || empty($password)) {
    ob_end_clean();
    echo json_encode([
        "success" => false,
        "message" => "Username dan password wajib diisi"
    ]);
    exit;
}

// Bersihkan input dan hindari SQL Injection
$username = mysqli_real_escape_string($conn, $username);
$password = mysqli_real_escape_string($conn, $password);

// Query dengan prepared statement (lebih aman)
$stmt = mysqli_prepare($conn, "SELECT * FROM admin WHERE username = ? AND password = ?");
mysqli_stmt_bind_param($stmt, "ss", $username, $password);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

// Periksa hasil query
if (mysqli_num_rows($result) > 0) {
    $admin = mysqli_fetch_assoc($result);
    
    ob_end_clean();
    echo json_encode([
        "success" => true,
        "message" => "Login berhasil",
        "data" => [
            "id" => $admin['id'],
            "username" => $admin['username']
        ]
    ]);
} else {
    ob_end_clean();
    echo json_encode([
        "success" => false,
        "message" => "Username atau password salah"
    ]);
}

// Tutup koneksi
mysqli_stmt_close($stmt);
mysqli_close($conn);
exit;