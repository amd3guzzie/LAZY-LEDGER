<?php
require 'db.php';
header("Content-Type: application/json");

$stmt = $pdo->prepare("SELECT email, full_name, role FROM users ORDER BY created_at DESC");
$stmt->execute();
echo json_encode($stmt->fetchAll());
exit;
?>