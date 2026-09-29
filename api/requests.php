<?php
require 'db.php';
header("Content-Type: application/json");
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $stmt = $pdo->prepare("SELECT * FROM category_requests WHERE status = 'pending' ORDER BY created_at ASC");
    $stmt->execute();
    echo json_encode($stmt->fetchAll());
    exit;
}

if ($method === 'POST') {
    $input = json_decode(file_get_contents("php://input"), true);
    $stmt = $pdo->prepare("UPDATE category_requests SET status = ? WHERE id = ?");
    $success = $stmt->execute([$input['status'], $input['id']]);
    echo json_encode(["success" => $success]);
    exit;
}
?>