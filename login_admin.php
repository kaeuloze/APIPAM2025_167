<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

error_reporting(0);
ini_set('display_errors', 0);

require_once __DIR__ . '/database.php';

$input = json_decode(file_get_contents("php://input"), true);

$username = $input['username'] ?? '';
$password = $input['password'] ?? '';

if (empty($username) || empty($password)) {
    echo json_encode([
        "success" => false,
        "message" => "Username atau password kosong"
    ]);
    exit;
}

$sql = "SELECT * FROM admin WHERE username=? AND password=?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $username, $password);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $admin = $result->fetch_assoc();
    echo json_encode([
        "success" => true,
        "message" => "Login berhasil",
        "data" => [
            "id" => $admin['id'],
            "username" => $admin['username']
        ]
    ]);
} else {
    echo json_encode([
        "success" => false,
        "message" => "Username atau password salah"
    ]);
}

?> 