<?php
error_reporting(0);
ini_set('display_errors', 0);

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, DELETE, OPTIONS");
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

// Pastikan hanya metode POST atau DELETE yang diproses
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "Method not allowed. Use POST or DELETE method"]);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents("php://input"), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Invalid input"]);
    exit;
}

// Validate required field
if (!isset($input['crew_shift_id']) || $input['crew_shift_id'] === '') {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Field crew_shift_id is required"]);
    exit;
}

try {
    // Cek apakah data exist sebelum delete (opsional)
    $check_sql = "SELECT crew_shift_id FROM crew_shift WHERE crew_shift_id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("i", $input['crew_shift_id']);
    $check_stmt->execute();
    $check_stmt->store_result();
    
    if ($check_stmt->num_rows === 0) {
        http_response_code(404);
        echo json_encode([
            "success" => false,
            "message" => "Data not found with crew_shift_id: " . $input['crew_shift_id']
        ]);
        $check_stmt->close();
        exit;
    }
    $check_stmt->close();
    
    // Prepare DELETE statement
    $sql = "DELETE FROM crew_shift WHERE crew_shift_id = ?";
    
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        throw new Exception("Prepare statement failed: " . $conn->error);
    }
    
    $stmt->bind_param("i", $input['crew_shift_id']);
    
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            echo json_encode([
                "success" => true,
                "message" => "Crew shift berhasil dihapus",
                "affected_rows" => $stmt->affected_rows,
                "deleted_id" => $input['crew_shift_id']
            ]);
        } else {
            // Should not happen since we checked earlier, but just in case
            http_response_code(404);
            echo json_encode([
                "success" => false,
                "message" => "Data tidak ditemukan untuk dihapus"
            ]);
        }
    } else {
        http_response_code(500);
        echo json_encode([
            "success" => false,
            "message" => "Gagal menghapus data: " . $stmt->error
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