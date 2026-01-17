<?php
// GANTI BARIS INI (baris 2):
// include "../config/database.php";

// MENJADI SALAH SATU DARI OPSI BERIKUT:

// Opsi 1 (paling sederhana):
include "database.php";

// Opsi 2 (direkomendasikan untuk menghindari error path):
// require_once __DIR__ . "/database.php";

// Opsi 3 (jika tetap ingin menggunakan require):
// require "database.php";

// Cek koneksi untuk memastikan
if (!isset($conn)) {
    die("Error: Koneksi database gagal. Periksa file database.php");
}

$sql = "SELECT cc.*, c.nama_lengkap AS kru, cert.namaSertifikasi AS nama_sertifikasi
FROM crew_certification cc
JOIN crew c ON cc.crew_id = c.crew_id
JOIN certification cert ON cc.certification_id = cert.sertif_id";

$result = $conn->query($sql);

// Cek jika query gagal
if (!$result) {
    die("Error dalam query: " . $conn->error);
}

$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = $row;
}

echo json_encode($data);

// Tutup koneksi (opsional)
$conn->close();
?>