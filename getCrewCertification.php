<?php
// File: getCrewCertification.php
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
    // Ambil parameter filter jika ada
    $crew_id = isset($_GET['crew_id']) ? (int)$_GET['crew_id'] : null;
    $certification_id = isset($_GET['certification_id']) ? (int)$_GET['certification_id'] : null;
    $status = isset($_GET['status']) ? trim((string)$_GET['status']) : null;

    // Query dasar
    $sql = "SELECT
                cc.crew_cert_id,
                cc.crew_id,
                cc.certification_id,
                cc.nama_sertifikasi,
                cc.kru,
                cc.tanggal_terbit,
                cc.tanggal_kadaluarsa,
                cc.status,
                cc.created_at,
                cc.updated_at
            FROM crew_certification cc";

    $conditions = [];
    $params = [];
    $types = "";

    if ($crew_id !== null && $crew_id > 0) {
        $conditions[] = "cc.crew_id = ?";
        $params[] = $crew_id;
        $types .= "i";
    }

    if ($certification_id !== null && $certification_id > 0) {
        $conditions[] = "cc.certification_id = ?";
        $params[] = $certification_id;
        $types .= "i";
    }

    if ($status !== null && $status !== "") {
        $conditions[] = "cc.status = ?";
        $params[] = $status;
        $types .= "s";
    }

    if (!empty($conditions)) {
        $sql .= " WHERE " . implode(" AND ", $conditions);
    }

    $sql .= " ORDER BY cc.tanggal_kadaluarsa ASC, cc.created_at DESC";

    // Eksekusi query (prepared jika ada params)
    if (!empty($params)) {
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }

        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();
    } else {
        $result = $conn->query($sql);
    }

    if (!$result) {
        throw new Exception("Query failed: " . $conn->error);
    }

    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
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
