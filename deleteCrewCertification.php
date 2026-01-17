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
$crew_cert_id = (int) getv($input, "crew_cert_id", "0");

if ($crew_cert_id <= 0) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Field crew_cert_id is required"]);
    exit;
}

try {
    // (Optional) cek exist dulu supaya bisa kasih 404 yang jelas
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

    // DELETE
    $sql = "DELETE FROM crew_certification WHERE crew_cert_id = ?";
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Prepare failed: " . $conn->error]);
        exit;
    }

    $stmt->bind_param("i", $crew_cert_id);

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            echo json_encode([
                "success" => true,
                "message" => "Data sertifikasi kru berhasil dihapus",
                "crew_cert_id" => $crew_cert_id
            ]);
        } else {
            http_response_code(404);
            echo json_encode([
                "success" => false,
                "message" => "Data tidak ditemukan / sudah terhapus",
                "crew_cert_id" => $crew_cert_id
            ]);
        }
    } else {
        http_response_code(500);
        echo json_encode([
            "success" => false,
            "message" => "Gagal hapus data: " . $stmt->error
        ]);
    }

    $stmt->close();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Error: " . $e->getMessage()]);
}

$conn->close();
?>
