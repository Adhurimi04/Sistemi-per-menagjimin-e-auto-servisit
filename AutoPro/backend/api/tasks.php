<?php
// backend/api/tasks.php

header("Content-Type: application/json");

// Marrja e të dhënave nga kërkesa HTTP (POST)
$data = json_decode(file_get_contents("php://input"), true);

if (isset($data['title']) && isset($data['vehicle_id'])) {
    $title = $data['title'];
    $vehicle_id = $data['vehicle_id'];

    // 1. Ruajtja e task-ut në MySQL të XAMPP...
    // (INSERT INTO service_tasks...)

    // 2. Dërgimi i njoftimit në email
    $to = "adhurimmehmetii2006@gmail.com";
    $subject = "Auto Pro - Detyrë e re Servisi";
    $message = "Përshëndetje,\n\nËshtë krijuar një detyrë e re për servisin: {$title} (Vetura ID: {$vehicle_id}).\n\nNumri i kontaktit: 045916042";
    $headers = "From: adhurimmehmetii2006@gmail.com";

    if (mail($to, $subject, $message, $headers)) {
        echo json_encode([
            "success" => true,
            "message" => "Task-u u ruajt në MySQL dhe email-i u dërgua me sukses!"
        ]);
    } else {
        echo json_encode([
            "success" => true,
            "message" => "Task-u u ruajt, por email-i dështoi të dërgohet."
        ]);
    }
} else {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "error" => "VALIDATION_ERROR",
        "message" => "Titulli dhe ID e veturës janë të detyrueshme."
    ]);
}
?>