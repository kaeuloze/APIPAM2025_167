<?php
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

function getv($arr, $key, $default = "") {
    return isset($arr[$key]) ? trim((string)$arr[$key]) : $default;
}

// ====== Ambil field CREW_CERTIFICATION ======
$crew_cert_id       = (int) getv($input, "crew_cert_id", "0");
$crew_id            = (int) getv($input, "crew_id", "0");
$certification_id   = (int) getv($input, "certification_id", "0");
$nama_sertifikasi   = getv($input, "nama_sertifikasi");
$kru                = getv($input, "kru");
$tanggal_terbit     = getv($input, "tanggal_terbit");      // YYYY-MM-DD
$tanggal_kadaluarsa = getv($input, "tanggal_kadaluarsa");  // YYYY-MM-DD
$status             = getv($input, "status");

// ====== Validasi ======
if ($crew_cert_id <= 0) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Field crew_cert_id is required"]);
    exit;
}

$required = [
    "crew_id"            => $crew_id,
    "certification_id"   => $certification_id,
    "nama_sertifikasi"   => $nama_sertifikasi,
    "kru"                => $kru,
    "tanggal_terbit"     => $tanggal_terbit,
    "tanggal_kadaluarsa" => $tanggal_kadaluarsa,
    "status"             => $status
];

foreach ($required as $field => $value) {
    if ($value === "" || $value === 0) {
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

// Validasi tanggal kadaluarsa > tanggal terbit
try {
    $dtTerbit = new DateTime($tanggal_terbit);
    $dtExp    = new DateTime($tanggal_kadaluarsa);
    if ($dtExp <= $dtTerbit) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Tanggal kadaluarsa harus setelah tanggal terbit"]);
        exit;
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Tanggal tidak valid"]);
    exit;
}

try {
    // Pastikan data ada
    $check = $conn->prepare("SELECT crew_cert_id FROM crew_certification WHERE crew_cert_id = ?");
    if (!$check) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Prepare failed: " . $conn->error]);
        exit;
    }
    $check->bind_param("i", $crew_cert_id);
    $check->execute();
    $check->store_result();

    if ($check->num_rows === 0) {
        http_response_code(404);
        echo json_encode([
            "success" => false,
            "message" => "Data tidak ditemukan / sudah terhapus",
            "crew_cert_id" => $crew_cert_id
        ]);
        $check->close();
        $conn->close();
        exit;
    }
    $check->close();

    // UPDATE (full update style seperti crew)
    $sql = "UPDATE crew_certification SET
            crew_id = ?,
            certification_id = ?,
            nama_sertifikasi = ?,
            kru = ?,
            tanggal_terbit = ?,
            tanggal_kadaluarsa = ?,
            status = ?,
            updated_at = NOW()
            WHERE crew_cert_id = ?";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Prepare failed: " . $conn->error]);
        exit;
    }

    $stmt->bind_param(
        "iisssssi",
        $crew_id,
        $certification_id,
        $nama_sertifikasi,
        $kru,
        $tanggal_terbit,
        $tanggal_kadaluarsa,
        $status,
        $crew_cert_id
    );

    if ($stmt->execute()) {
        if ($stmt->affected_rows >= 0) {
            echo json_encode([
                "success" => true,
                "message" => "Data sertifikasi kru berhasil diupdate",
                "crew_cert_id" => $crew_cert_id
            ]);
        } else {
            http_response_code(500);
            echo json_encode(["success" => false, "message" => "Update gagal"]);
        }
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
