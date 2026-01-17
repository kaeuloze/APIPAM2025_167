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

// ====== Ambil field ======
$crew_id          = (int) getv($input, "crew_id", "0");
$nama_lengkap     = getv($input, "nama_lengkap");
$jenis_kelamin    = strtoupper(getv($input, "jenis_kelamin"));
$tanggal_lahir    = getv($input, "tanggal_lahir");
$kewarganegaraan  = getv($input, "kewarganegaraan", "");
$posisi           = getv($input, "posisi");
$nomor_passport   = getv($input, "nomor_passport", "");
$kontak           = getv($input, "kontak", "");
$alamat           = getv($input, "alamat", "");

// ====== Validasi ======
if ($crew_id <= 0) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Field crew_id is required"]);
    exit;
}

$required = [
    "nama_lengkap"  => $nama_lengkap,
    "jenis_kelamin" => $jenis_kelamin,
    "tanggal_lahir" => $tanggal_lahir,
    "posisi"        => $posisi
];

foreach ($required as $field => $value) {
    if ($value === "") {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Field $field is required"]);
        exit;
    }
}

if ($jenis_kelamin !== "L" && $jenis_kelamin !== "P") {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "jenis_kelamin harus 'L' atau 'P'"]);
    exit;
}

if (!preg_match("/^\d{4}-\d{2}-\d{2}$/", $tanggal_lahir)) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "tanggal_lahir harus format YYYY-MM-DD"]);
    exit;
}

try {
    $sql = "UPDATE crew SET
            nama_lengkap = ?,
            jenis_kelamin = ?,
            tanggal_lahir = ?,
            kewarganegaraan = ?,
            posisi = ?,
            nomor_passport = ?,
            kontak = ?,
            alamat = ?
            WHERE crew_id = ?";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Prepare failed: " . $conn->error]);
        exit;
    }

    $stmt->bind_param(
        "ssssssssi",
        $nama_lengkap,
        $jenis_kelamin,
        $tanggal_lahir,
        $kewarganegaraan,
        $posisi,
        $nomor_passport,
        $kontak,
        $alamat,
        $crew_id
    );

    if ($stmt->execute()) {
        if ($stmt->affected_rows >= 0) {
            echo json_encode([
                "success" => true,
                "message" => "Data crew berhasil diupdate",
                "crew_id" => $crew_id
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
