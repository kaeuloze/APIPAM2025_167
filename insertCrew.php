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

// ====== Ambil field ======
$nama_lengkap     = getv($input, "nama_lengkap");
$jenis_kelamin    = strtoupper(getv($input, "jenis_kelamin")); // L / P
$tanggal_lahir    = getv($input, "tanggal_lahir");            // YYYY-MM-DD
$kewarganegaraan  = getv($input, "kewarganegaraan", "");
$posisi           = getv($input, "posisi");
$nomor_passport   = getv($input, "nomor_passport", "");
$kontak           = getv($input, "kontak", "");
$alamat           = getv($input, "alamat", "");

// ====== Validasi required fields ======
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

// ====== Validasi jenis kelamin ======
if ($jenis_kelamin !== "L" && $jenis_kelamin !== "P") {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "jenis_kelamin harus 'L' atau 'P'"]);
    exit;
}

// ====== Validasi format tanggal YYYY-MM-DD ======
if (!preg_match("/^\d{4}-\d{2}-\d{2}$/", $tanggal_lahir)) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "tanggal_lahir harus format YYYY-MM-DD"]);
    exit;
}

try {
    $sql = "INSERT INTO crew
            (nama_lengkap, jenis_kelamin, tanggal_lahir, kewarganegaraan, posisi, nomor_passport, kontak, alamat)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Prepare failed: " . $conn->error]);
        exit;
    }

    $stmt->bind_param(
        "ssssssss",
        $nama_lengkap,
        $jenis_kelamin,
        $tanggal_lahir,
        $kewarganegaraan,
        $posisi,
        $nomor_passport,
        $kontak,
        $alamat
    );

    if ($stmt->execute()) {
        echo json_encode([
            "success" => true,
            "message" => "Data crew berhasil ditambahkan",
            "insert_id" => $conn->insert_id
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
?>
