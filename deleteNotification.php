<?php
include "../config/database.php";
$data = json_decode(file_get_contents("php://input"));

$sql = "DELETE FROM notification WHERE notif_id=$data->notif_id";
echo json_encode(["success" => $conn->query($sql)]);
