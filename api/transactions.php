<?php
session_start();
require 'db.php';
header("Content-Type: application/json");
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $stmt = $pdo->prepare("SELECT * FROM transactions ORDER BY transaction_date DESC LIMIT 50");
    $stmt->execute();
    echo json_encode($stmt->fetchAll());
    exit;
}

if ($method === 'POST') {
    $input = json_decode(file_get_contents("php://input"), true);
    if (empty($input['description']) || empty($input['amount']) || $input['amount'] < 0) {
        http_response_code(400);
        echo json_encode(["error" => "Invalid transaction data."]);
        exit;
    }

    $stmt = $pdo->prepare("INSERT INTO transactions (description, amount, type) VALUES (?, ?, ?)");
    $success = $stmt->execute([htmlspecialchars($input['description']), $input['amount'], $input['type']]);
    echo json_encode(["success" => $success]);
    exit;
}
?>