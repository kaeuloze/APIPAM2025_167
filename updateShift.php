<?php
// File: updateShift.php
// ====== CONFIG DEBUG (matikan kalau sudah production) ======
error_reporting(E_ALL);
ini_set('display_errors', 1);

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Accept");

// Handle OPTIONS request
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

// ====== Ambil field SHIFT ======
// Support key dari Kotlin model: shift_id, nama_shift, jam_mulai, jam_selesai, deskripsi
$shift_id    = (int) getv($input, "shift_id", "0");
$namaShift  = getv($input, "namaShift");
$jam_mulai   = getv($input, "jam_mulai");
$jam_selesai = getv($input, "jam_selesai");
$deskripsi   = getv($input, "deskripsi", "");

// ====== Validasi ======
if ($shift_id <= 0) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Field shift_id is required"]);
    exit;
}

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

// Validasi format jam HH:mm
if (!preg_match("/^\d{2}:\d{2}$/", $jam_mulai) || !preg_match("/^\d{2}:\d{2}$/", $jam_selesai)) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Format jam harus HH:mm"]);
    exit;
}

try {
    $sql = "UPDATE shift SET
            namaShift = ?,
            jam_mulai = ?,
            jam_selesai = ?,
            deskripsi = ?
            WHERE shift_id = ?";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Prepare failed: " . $conn->error]);
        exit;
    }

    $stmt->bind_param(
        "ssssi",
        $namaShift,
        $jam_mulai,
        $jam_selesai,
        $deskripsi,
        $shift_id
    );

    if ($stmt->execute()) {
        // affected_rows bisa 0 kalau datanya sama (tetap dianggap sukses)
        echo json_encode([
            "success" => true,
            "message" => "Data shift berhasil diupdate",
            "shift_id" => $shift_id,
            "affected_rows" => $stmt->affected_rows
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            "success" => false,
            "message" => "Gagal update data: " . $stmt->error
        ]);
    }

    $stmt->close();

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Error: " . $e->getMessage()]);
}

$conn->close();
?>
