<?php
error_reporting(0);
ini_set('display_errors', 0);

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

// Handle OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Include database dari folder yang sama
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

// Pastikan hanya metode POST yang diproses
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "Method not allowed. Use POST method"]);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents("php://input"), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Invalid input"]);
    exit;
}

// Validate required fields
$required = ['crew_id', 'shift_id', 'tanggal_shift', 'status'];
foreach ($required as $field) {
    if (!isset($input[$field]) || $input[$field] === '') {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Field $field is required"]);
        exit;
    }
}

try {
    $sql = "INSERT INTO crew_shift 
            (crew_id, shift_id, tanggal_shift, status)
            VALUES (?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        throw new Exception("Prepare statement failed: " . $conn->error);
    }
    
    $stmt->bind_param(
        "iiss",
        $input['crew_id'],
        $input['shift_id'],
        $input['tanggal_shift'],
        $input['status']
    );
    
    if ($stmt->execute()) {
        echo json_encode([
            "success" => true,
            "message" => "Crew shift berhasil ditambahkan",
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