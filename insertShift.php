<?php
// ====== CONFIG DEBUG (matikan kalau sudah production) ======
error_reporting(E_ALL);
ini_set('display_errors', 1);

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Accept");

// Handle OPTIONS request (CORS preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Include database.php (di folder yang sama)
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
    $input = $_POST; // fallback form-data / x-www-form-urlencoded
}

if (!is_array($input) || empty($input)) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Invalid input",
        "debug" => [
            "raw" => $raw,
            "post" => $_POST
        ]
    ]);
    exit;
}

// Helper get value
function getv($arr, $key, $default = "") {
    return isset($arr[$key]) ? trim((string)$arr[$key]) : $default;
}

// ====== Ambil field SHIFT (sesuai tabel kamu) ======
$namaShift   = getv($input, "namaShift");
$jam_mulai    = getv($input, "jam_mulai");    // HH:mm
$jam_selesai  = getv($input, "jam_selesai");  // HH:mm
$deskripsi    = getv($input, "deskripsi", "");

// ====== Validasi required fields ======
$required = [
    "namaShift"  => $namaShift,
    "jam_mulai"   => $jam_mulai,
    "jam_selesai" => $jam_selesai
];

foreach ($required as $field => $value) {
    if ($value === "") {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Field $field is required"]);
        exit;
    }
}

// ====== Validasi format jam HH:mm ======
if (!preg_match("/^\d{2}:\d{2}$/", $jam_mulai)) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "jam_mulai harus format HH:mm"]);
    exit;
}
if (!preg_match("/^\d{2}:\d{2}$/", $jam_selesai)) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "jam_selesai harus format HH:mm"]);
    exit;
}

// (Opsional) Validasi logika jam: selesai harus > mulai (string compare aman untuk HH:mm)
if ($jam_selesai <= $jam_mulai) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "jam_selesai harus lebih besar dari jam_mulai"]);
    exit;
}

try {
    // ⚠️ Pastikan nama tabel & kolom sesuai database kamu:
    // - Jika tabel kamu "shift" dan kolomnya nama_shift, jam_mulai, jam_selesai, deskripsi -> sudah benar.
    $sql = "INSERT INTO shift (namaShift, jam_mulai, jam_selesai, deskripsi)
            VALUES (?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Prepare failed: " . $conn->error]);
        exit;
    }

    $stmt->bind_param("ssss", $namaShift, $jam_mulai, $jam_selesai, $deskripsi);

    if ($stmt->execute()) {
        echo json_encode([
            "success" => true,
            "message" => "Shift berhasil ditambahkan",
            "insert_id" => $conn->insert_id
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            "success" => false,
            "message" => "Gagal menambahkan shift: " . $stmt->error
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
// JANGAN PAKAI ?>
