<?php
// backend/api/tasks.php

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit();
}

// 1. Lidhja me Databazën MySQL në XAMPP
$host = "localhost";
$db_user = "root";
$db_pass = "";
$db_name = "autopro_db";

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

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $result = $conn->query(
        "SELECT id, vehicle_id, assigned_to, title, status
         FROM service_tasks
         ORDER BY id DESC"
    );

    if ($result === false) {
        http_response_code(500);
        echo json_encode([
            "success" => false,
            "error" => "QUERY_ERROR",
            "message" => "Dështoi leximi i detyrave: " . $conn->error
        ]);
        $conn->close();
        exit();
    }

    $tasks = [];
    while ($task = $result->fetch_assoc()) {
        $tasks[] = $task;
    }

    echo json_encode([
        "success" => true,
        "data" => $tasks
    ]);
    $conn->close();
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    $data = json_decode(file_get_contents("php://input"), true);
    $id = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);
    $status = $data['status'] ?? null;
    $allowedStatuses = ['TODO', 'IN_PROGRESS', 'DONE'];

    if (!$id || $id < 1 || !is_string($status) || !in_array($status, $allowedStatuses, true)) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "message" => "ID dhe statusi i vlefshëm janë të detyrueshme."
        ]);
        $conn->close();
        exit();
    }

    $update = $conn->prepare("UPDATE service_tasks SET status = ? WHERE id = ?");
    if ($update === false) {
        http_response_code(500);
        echo json_encode([
            "success" => false,
            "message" => "Përgatitja e përditësimit dështoi: " . $conn->error
        ]);
        $conn->close();
        exit();
    }

    $update->bind_param("si", $status, $id);
    if (!$update->execute()) {
        http_response_code(500);
        echo json_encode([
            "success" => false,
            "message" => "Përditësimi i statusit dështoi: " . $update->error
        ]);
        $update->close();
        $conn->close();
        exit();
    }

    if ($update->affected_rows === 0) {
        $check = $conn->prepare("SELECT id FROM service_tasks WHERE id = ?");
        if ($check === false) {
            http_response_code(500);
            echo json_encode([
                "success" => false,
                "message" => "Verifikimi i detyrës dështoi: " . $conn->error
            ]);
            $update->close();
            $conn->close();
            exit();
        }

        $check->bind_param("i", $id);
        $check->execute();
        $check->store_result();
        $taskExists = $check->num_rows > 0;
        $check->close();

        if (!$taskExists) {
            http_response_code(404);
            echo json_encode([
                "success" => false,
                "message" => "Detyra nuk u gjet."
            ]);
            $update->close();
            $conn->close();
            exit();
        }
    }

    echo json_encode([
        "success" => true,
        "message" => "Statusi u përditësua!",
        "status" => $status
    ]);
    $update->close();
    $conn->close();
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $data = json_decode(file_get_contents("php://input"), true);
    $id = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);

    if (!$id || $id < 1) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "message" => "ID e detyrës nuk është e vlefshme."
        ]);
        $conn->close();
        exit();
    }

    $delete = $conn->prepare("DELETE FROM service_tasks WHERE id = ?");
    if ($delete === false) {
        http_response_code(500);
        echo json_encode([
            "success" => false,
            "message" => "Përgatitja e fshirjes dështoi: " . $conn->error
        ]);
        $conn->close();
        exit();
    }

    $delete->bind_param("i", $id);
    if (!$delete->execute()) {
        http_response_code(500);
        echo json_encode([
            "success" => false,
            "message" => "Fshirja e detyrës dështoi: " . $delete->error
        ]);
        $delete->close();
        $conn->close();
        exit();
    }

    if ($delete->affected_rows === 0) {
        http_response_code(404);
        echo json_encode([
            "success" => false,
            "message" => "Detyra nuk u gjet."
        ]);
        $delete->close();
        $conn->close();
        exit();
    }

    echo json_encode([
        "success" => true,
        "message" => "Detyra u fshi me sukses."
    ]);
    $delete->close();
    $conn->close();
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        "success" => false,
        "error" => "METHOD_NOT_ALLOWED",
        "message" => "Metoda e kërkesës nuk lejohet."
    ]);
    $conn->close();
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
        $resendApiKey = getenv("RESEND_API_KEY");

        $emailData = [
            "from" => "onboarding@resend.dev",
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

        $response = null;
        $curlError = null;
        $httpCode = 0;
        if ($resendApiKey) {
            $ch = curl_init("https://api.resend.com/emails");
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Authorization: Bearer " . $resendApiKey,
                "Content-Type: application/json"
            ]);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($emailData));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

            $response = curl_exec($ch);
            $curlError = curl_error($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
        } else {
            $curlError = "RESEND_API_KEY is not configured.";
        }

        $email_sent = ($httpCode >= 200 && $httpCode < 300);

        http_response_code(201);
        echo json_encode([
            "success" => true,
            "message" => $email_sent
                ? "Task-u u ruajt me sukses në databazë dhe email-i u dërgua!"
                : "Task-u u ruajt në databazë, por dërgimi i email-it dështoi.",
            "task_id" => $new_task_id,
            "resend_http_code" => $httpCode,
            "resend_details" => ($response !== null ? json_decode($response) : null) ?? $curlError
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