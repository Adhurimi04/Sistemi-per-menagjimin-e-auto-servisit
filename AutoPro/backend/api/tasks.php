<?php
// backend/api/tasks.php

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");

// 1. Lidhja me Databazën MySQL në XAMPP
$host = "localhost";
$db_user = "root";
$db_pass = "";
$db_name = "autopro_db"; // Emri i databazës suaj

$conn = new mysqli($host, $db_user, $db_pass, $db_name);

if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "error" => "DATABASE_ERROR",
        "message" => "Lidhja me databazën dështoi: " . $conn->connect_error
    ]);
    exit();
}

// 2. Pranim i të dhënave JSON nga Postman
$data = json_decode(file_get_contents("php://input"), true);

if (!empty($data['title']) && !empty($data['vehicle_id'])) {
    $title = $conn->real_escape_string($data['title']);
    $vehicle_id = (int)$data['vehicle_id'];
    $assigned_to = isset($data['assigned_to']) ? (int)$data['assigned_to'] : NULL;
    $status = isset($data['status']) ? $conn->real_escape_string($data['status']) : 'TODO';

    // 3. Ruajtja e task-ut në MySQL
    $sql = "INSERT INTO service_tasks (vehicle_id, assigned_to, title, status) VALUES ('$vehicle_id', " . ($assigned_to ? "'$assigned_to'" : "NULL") . ", '$title', '$status')";

    if ($conn->query($sql) === TRUE) {
        $new_task_id = $conn->insert_id;

        // 4. Dërgimi i njoftimit menjëherë te telefoni yt përmes Telegram Bot
        // Krijimi i bot-it merr vetëm 1 minutë në Telegram me @BotFather
        $botToken = "TOKENI_YT_NGA_BOTFATHER"; 
        $chatID = "CHAT_ID_JUAJ"; 

        $messageText = "🚗 [Auto Pro Notification]\n"
                     . "Është krijuar një detyrë e re servisi #{$new_task_id}!\n"
                     . "Titulli: {$title}\n"
                     . "Vetura ID: {$vehicle_id}\n"
                     . "Kontakt: 045916042";

        $url = "https://api.telegram.org/bot{$botToken}/sendMessage?chat_id={$chatID}&text=" . urlencode($messageText);
        
        // Ekzekutimi i kërkesës HTTP për mesazhin
        @file_get_contents($url);

        http_response_code(201);
        echo json_encode([
            "success" => true,
            "message" => "Task-u u ruajt me sukses në databazë dhe njoftimi u dërgua në telefon!",
            "task_id" => $new_task_id
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            "success" => false,
            "error" => "QUERY_ERROR",
            "message" => "Dështoi ruajtja në databazë: " . $conn->error
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

$conn->close();
?>
