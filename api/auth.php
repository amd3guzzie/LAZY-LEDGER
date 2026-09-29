<?php
session_start();
require 'db.php';
header("Content-Type: application/json");

$input = json_decode(file_get_contents("php://input"), true);
$action = $input['action'] ?? '';

if ($action === 'login') {
    $stmt = $pdo->prepare("SELECT id, full_name, password_hash, role FROM users WHERE email = ?");
    $stmt->execute([$input['email']]);
    $user = $stmt->fetch();

    if ($user && password_verify($input['password'], $user['password_hash'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role'] = $user['role'];
        echo json_encode(["success" => true, "role" => $user['role']]);
    } else {
        http_response_code(401);
        echo json_encode(["success" => false, "error" => "Invalid credentials"]);
    }
    exit;
}

if ($action === 'signup') {
    $password = password_hash($input['password'], PASSWORD_BCRYPT);
    try {
        $stmt = $pdo->prepare("INSERT INTO users (full_name, email, password_hash) VALUES (?, ?, ?)");
        $stmt->execute([$input['name'], $input['email'], $password]);
        echo json_encode(["success" => true]);
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(["success" => false, "error" => "Email may already exist."]);
    }
    exit;
}
?>