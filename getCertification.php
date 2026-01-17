<?php
// File: getCertification.php
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
    // ⚠️ Pastikan tabel/kolom sesuai DB kamu:
    // - tabel: certification
    // - kolom: sertif_id, nama_sertifikasi, deskripsi
    // Kalau kolom kamu berbeda (mis. namaSertifikasi), sesuaikan di SELECT.
    $query = "SELECT * FROM certification ORDER BY sertif_id DESC";
    $result = $conn->query($query);

    if (!$result) {
        throw new Exception("Query failed: " . $conn->error);
    }

    $data = [];
    while ($row = $result->fetch_assoc()) {
        // Transform agar konsisten dengan model Kotlin (namaSertifikasi, deskripsi)
        $data[] = [
            "sertif_id" => isset($row["sertif_id"]) ? (int)$row["sertif_id"] : 0,
            "namaSertifikasi" => $row["namaSertifikasi"] ?? ($row["nama_sertifikasi"] ?? ""),
            "deskripsi" => $row["deskripsi"] ?? ""
        ];
    }

    $response = [
        "success" => true,
        "count" => count($data),
        "data" => $data
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
