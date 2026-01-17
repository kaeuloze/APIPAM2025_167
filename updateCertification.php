<?php
// File: updateCertification.php
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

if (!isset($conn) || !$conn) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Database connection failed"]);
    exit;
}

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

// Helper
function getv($arr, $key, $default = "") {
    return isset($arr[$key]) ? trim((string)$arr[$key]) : $default;
}

// ====== Ambil field ======
// Support 2 kemungkinan nama key dari Android:
// - sertif_id (sesuai model kamu)
// - id (fallback)
$sertif_id = (int) getv($input, "sertif_id", "0");
if ($sertif_id <= 0) {
    $sertif_id = (int) getv($input, "id", "0");
}

// Nama sertifikasi bisa datang dari:
// - namaSertifikasi (camelCase sesuai model)
// - nama_sertifikasi (snake_case jika backend pakai itu)
$namaSertifikasi = getv($input, "namaSertifikasi", "");
if ($namaSertifikasi === "") {
    $namaSertifikasi = getv($input, "namaSertifikasi", "");
}

$deskripsi = getv($input, "deskripsi", "");

// ====== Validasi ======
if ($sertif_id <= 0) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Field sertif_id is required"]);
    exit;
}

if ($namaSertifikasi === "") {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Field namaSertifikasi (atau namaSertifikasi) is required"]);
    exit;
}

try {
    
    $sql = "UPDATE certification SET
            namaSertifikasi = ?,
            deskripsi = ?
            WHERE sertif_id = ?";

   

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Prepare failed: " . $conn->error]);
        exit;
    }

    $stmt->bind_param(
        "ssi",
        $namaSertifikasi,
        $deskripsi,
        $sertif_id
    );

    if ($stmt->execute()) {
        // affected_rows bisa 0 kalau datanya sama (tetap dianggap sukses)
        echo json_encode([
            "success" => true,
            "message" => "Sertifikasi berhasil diupdate",
            "sertif_id" => $sertif_id,
            "affected_rows" => $stmt->affected_rows
        ]);
        $stmt->close();
        $conn->close();
        exit;
    } else {
        http_response_code(500);
        echo json_encode([
            "success" => false,
            "message" => "Gagal update data: " . $stmt->error
        ]);
        $stmt->close();
        $conn->close();
        exit;
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Error: " . $e->getMessage()]);
    $conn->close();
    exit;
}
?>
