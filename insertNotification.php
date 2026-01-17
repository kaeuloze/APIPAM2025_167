<?php
include "../config/database.php";
$data = json_decode(file_get_contents("php://input"));

$sql = "INSERT INTO notification
(crew_id, tanggal_notif, jenis, pesan)
VALUES (
$data->crew_id,
'$data->tanggal_notif',
'$data->jenis',
'$data->pesan'
)";

echo json_encode(["success" => $conn->query($sql)]);
