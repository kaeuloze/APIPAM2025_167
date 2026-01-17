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
// Support 2 kemungkinan nama key dari Android:
// - camelCase: namaSertifikasi
// - snake_case: nama_sertifikasi
$namaSertifikasi = getv($input, "namaSertifikasi", "");
if ($namaSertifikasi === "") {
    $namaSertifikasi = getv($input, "namaSertifikasi", "");
}
$deskripsi = getv($input, "deskripsi", "");

// ====== Validasi required fields ======
if ($namaSertifikasi === "") {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Field namaSertifikasi (atau nama_sertifikasi) is required"]);
    exit;
}

try {
    // ⚠️ Sesuaikan nama tabel/kolom dengan DB kamu
    // Kalau kolom kamu pakai camelCase (namaSertifikasi), ubah query ini.
    $sql = "INSERT INTO certification (namaSertifikasi, deskripsi) VALUES (?, ?)";
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Prepare failed: " . $conn->error]);
        exit;
    }

    $stmt->bind_param("ss", $namaSertifikasi, $deskripsi);

    if ($stmt->execute()) {
        $insert_id = $conn->insert_id;

        // Optional: ambil data yang baru dimasukkan
        $select_sql = "SELECT * FROM certification WHERE sertif_id = ?";
        $select_stmt = $conn->prepare($select_sql);
        if ($select_stmt) {
            $select_stmt->bind_param("i", $insert_id);
            $select_stmt->execute();
            $result = $select_stmt->get_result();
            $new_data = $result ? $result->fetch_assoc() : null;
            $select_stmt->close();
        } else {
            $new_data = null;
        }

        echo json_encode([
            "success" => true,
            "message" => "Sertifikasi berhasil ditambahkan",
            "insert_id" => $insert_id,
            "data" => $new_data
        ]);
        $stmt->close();
        $conn->close();
        exit;

    } else {
        http_response_code(500);
        echo json_encode([
            "success" => false,
            "message" => "Gagal menambahkan data: " . $stmt->error
        ]);
        $stmt->close();
        $conn->close();
        exit;
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Error: " . $e->getMessage()
    ]);
    $conn->close();
    exit;
}
?>
