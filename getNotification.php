<?php
// File: getNotification.php
error_reporting(0);
ini_set('display_errors', 0);

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

// Handle OPTIONS request (preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// ==============================================
// INCLUDE DATABASE.PHP DARI FOLDER YANG SAMA
// ==============================================
$db_file = __DIR__ . '/database.php';

if (!file_exists($db_file)) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Database config file not found",
        "debug_info" => [
            "expected_path" => $db_file,
            "current_dir" => __DIR__
        ]
    ]);
    exit;
}

require_once $db_file;

// Cek koneksi database
if (!isset($conn) || !$conn) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Database connection failed"
    ]);
    exit;
}
// ==============================================

// Pastikan hanya metode GET yang diproses
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode([
        "success" => false,
        "message" => "Method not allowed. Use GET method"
    ]);
    exit;
}

try {
    // Cek apakah tabel notifications ada
    $table_check = $conn->query("SHOW TABLES LIKE 'notifications'");
    
    if ($table_check->num_rows === 0) {
        // Jika tabel tidak ada, buat tabel
        $create_table_sql = "CREATE TABLE IF NOT EXISTS notifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            message TEXT NOT NULL,
            type VARCHAR(50) DEFAULT 'info',
            is_read BOOLEAN DEFAULT FALSE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        
        if (!$conn->query($create_table_sql)) {
            throw new Exception("Failed to create table: " . $conn->error);
        }
        
        // Tambahkan data contoh jika tabel baru dibuat
        $sample_data = [
            ["title" => "Selamat Datang", "message" => "Sistem manajemen kapal pesiar telah siap digunakan", "type" => "info"],
            ["title" => "Pengingat", "message" => "Jangan lupa untuk melengkapi data crew", "type" => "warning"],
            ["title" => "Update Sistem", "message" => "Versi terbaru sistem telah tersedia", "type" => "info"]
        ];
        
        foreach ($sample_data as $data) {
            $stmt = $conn->prepare("INSERT INTO notifications (title, message, type) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $data['title'], $data['message'], $data['type']);
            $stmt->execute();
            $stmt->close();
        }
    }
    
    // Ambil data notifications
    $sql = "SELECT * FROM notifications ORDER BY created_at DESC";
    $result = $conn->query($sql);
    
    if (!$result) {
        throw new Exception("Query failed: " . $conn->error);
    }
    
    $notifications = [];
    while ($row = $result->fetch_assoc()) {
        $notifications[] = [
            "id" => (int)$row['id'],
            "title" => $row['title'],
            "message" => $row['message'],
            "type" => $row['type'],
            "is_read" => (bool)$row['is_read'],
            "created_at" => $row['created_at'],
            "updated_at" => $row['updated_at']
        ];
    }
    
    // Hitung notifikasi yang belum dibaca
    $unread_count = 0;
    foreach ($notifications as $notification) {
        if (!$notification['is_read']) {
            $unread_count++;
        }
    }
    
    echo json_encode([
        "success" => true,
        "data" => $notifications,
        "count" => count($notifications),
        "unread_count" => $unread_count
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Error: " . $e->getMessage()
    ], JSON_PRETTY_PRINT);
}

$conn->close();
// JANGAN PAKAI ?> 