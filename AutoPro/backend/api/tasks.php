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

        // 4. Dërgimi i Email-it përmes Resend API
        $resendApiKey = "re_XQALHH4A_KcGsBkHaFvTbLeXnjcu4EHdJ"; // Vendos këtu API Key nga Resend (fillon me re_)

        $emailData = [
            "from" => "Auto Pro <onboarding@resend.dev>",
            "to" => ["adhurimmehmetii2006@gmail.com"],
            "subject" => "Auto Pro - Detyrë e re Servisi #{$new_task_id}",
            "html" => "
                <h2>Përshëndetje Adhurim,</h2>
                <p>Është krijuar me sukses një detyrë e re servisi në sistemin <strong>Auto Pro</strong>:</p>
                <ul>
                    <li><strong>ID e Detyrës:</strong> #{$new_task_id}</li>
                    <li><strong>Titulli:</strong> {$title}</li>
                    <li><strong>ID e Veturës:</strong> {$vehicle_id}</li>
                    <li><strong>Statusi:</strong> {$status}</li>
                </ul>
                <p>Numri i kontaktit: 045916042</p>
            "
        ];

        $ch = curl_init("https://api.resend.com/emails");
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer " . $resendApiKey,
            "Content-Type: application/json"
        ]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($emailData));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $email_sent = ($httpCode >= 200 && $httpCode < 300);

        http_response_code(201);
        echo json_encode([
            "success" => true,
            "message" => $email_sent 
                ? "Task-u u ruajt me sukses në databazë dhe email-i u dërgua!" 
                : "Task-u u ruajt në databazë, por email-i dështoi (Kontrollo Resend Key).",
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
