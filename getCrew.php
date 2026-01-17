<?php
// File: getCrew.php
error_reporting(0);
ini_set('display_errors', 0);

// Set headers
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header("Access-Control-Allow-Methods: GET, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type");
    http_response_code(200);
    exit();
}

// Set allowed methods
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "Method not allowed. Use GET"]);
    exit();
}

// Include database dari folder yang sama
$db_path = dirname(__FILE__) . '/database.php';

if (!file_exists($db_path)) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Database config not found: " . $db_path]);
    exit();
}

require_once $db_path;

// Check connection
if (!isset($conn) || $conn->connect_error) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Database connection failed"]);
    exit();
}

// Get data from database
try {
    $query = "SELECT * FROM crew ORDER BY crew_id DESC";
    $result = $conn->query($query);
    
    if (!$result) {
        throw new Exception("Query failed: " . $conn->error);
    }
    
    $crew_data = [];
    while ($row = $result->fetch_assoc()) {
        $crew_data[] = $row;
    }
    
    $response = [
        "success" => true,
        "count" => count($crew_data),
        "data" => $crew_data
    ];
    
    echo json_encode($response, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Database error: " . $e->getMessage()]);
}

// Close connection
if (isset($conn)) {
    $conn->close();
}
// JANGAN PAKAI ?> 