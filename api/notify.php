<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Methods: POST");

$input = json_decode(file_get_contents("php://input"), true);

$to = $input['email'] ?? '';
$subject = $input['subject'] ?? 'LazyLedger Notification';
$message = $input['message'] ?? '';

if (empty($to) || empty($message)) {
    echo json_encode(["status" => "error", "message" => "Missing email data."]);
    exit;
}

$clean_message = strip_tags($message);
$headers = "From: noreply@lazyledger.com\r\nContent-Type: text/plain; charset=UTF-8\r\n";

if (mail($to, $subject, $clean_message, $headers)) {
    echo json_encode(["status" => "success"]);
} else {
    echo json_encode(["status" => "error", "message" => "Mail server error."]);
}
?>