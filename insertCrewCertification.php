<?php
// ====== CONFIG DEBUG (matikan kalau sudah production) ======
error_reporting(E_ALL);
// PENTING: jangan tampilkan error ke output JSON
ini_set('display_errors', 0);
ini_set('log_errors', 1);

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Accept");

// Handle OPTIONS request (CORS preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Include database.php
$db_file = __DIR__ . '/database.php';
if (!file_exists($db_file)) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Database config file not found"]);
    exit;
}
require_once $db_file;

// Cek koneksi
if (!isset($conn) || !$conn) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Database connection failed"]);
    exit;
}

// Hanya POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "Method not allowed. Use POST method"]);
    exit;
}

// ====== Ambil input: JSON dulu, kalau gagal fallback ke $_POST ======
$raw = file_get_contents("php://input");
$input = json_decode($raw, true);

if (!is_array($input) || empty($input)) {
    $input = $_POST;
}

if (!is_array($input) || empty($input)) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Invalid input",
        "debug" => ["raw" => $raw, "post" => $_POST]
    ]);
    exit;
}

function getv($arr, $key, $default = "") {
    return isset($arr[$key]) ? trim((string)$arr[$key]) : $default;
}

// ====== Ambil field (TANPA crew_cert_id karena auto increment) ======
$crew_id            = (int) getv($input, "crew_id", "0");
$certification_id   = (int) getv($input, "certification_id", "0");
$nama_sertifikasi   = getv($input, "nama_sertifikasi");
$kru                = getv($input, "kru");
$tanggal_terbit     = getv($input, "tanggal_terbit");      // YYYY-MM-DD
$tanggal_kadaluarsa = getv($input, "tanggal_kadaluarsa");  // YYYY-MM-DD
$status             = getv($input, "status");

// ====== Validasi ======
if ($crew_id <= 0) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Field crew_id is required"]);
    exit;
}

if ($certification_id <= 0) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Field certification_id is required"]);
    exit;
}

$requiredStrings = [
    "nama_sertifikasi"   => $nama_sertifikasi,
    "kru"                => $kru,
    "tanggal_terbit"     => $tanggal_terbit,
    "tanggal_kadaluarsa" => $tanggal_kadaluarsa,
    "status"             => $status
];

foreach ($requiredStrings as $field => $value) {
    if ($value === "") {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Field $field is required"]);
        exit;
    }
}

// Validasi format tanggal
if (!preg_match("/^\d{4}-\d{2}-\d{2}$/", $tanggal_terbit)) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "tanggal_terbit harus format YYYY-MM-DD"]);
    exit;
}

if (!preg_match("/^\d{4}-\d{2}-\d{2}$/", $tanggal_kadaluarsa)) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "tanggal_kadaluarsa harus format YYYY-MM-DD"]);
    exit;
}

try {
    $sql = "INSERT INTO crew_certification
            (crew_id, certification_id, nama_sertifikasi, kru, tanggal_terbit, tanggal_kadaluarsa, status)
            VALUES (?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Prepare failed: " . $conn->error]);
        exit;
    }

    // 2 int + 5 string = "iisssss" (TOTAL 7 PARAMS)
    $stmt->bind_param(
        "iisssss",
        $crew_id,
        $certification_id,
        $nama_sertifikasi,
        $kru,
        $tanggal_terbit,
        $tanggal_kadaluarsa,
        $status
    );

    if ($stmt->execute()) {
        echo json_encode([
            "success" => true,
            "message" => "Data sertifikasi kru berhasil ditambahkan",
            "crew_cert_id" => $conn->insert_id
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            "success" => false,
            "message" => "Gagal menambahkan data: " . $stmt->error
        ]);
    }

    $stmt->close();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Error: " . $e->getMessage()
    ]);
}

$conn->close();
