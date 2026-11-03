<?php
// backend/api/tasks.php

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");

// Përfshirja e klasave të PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/PHPMailer/src/Exception.php';
require __DIR__ . '/PHPMailer/src/PHPMailer.php';
require __DIR__ . '/PHPMailer/src/SMTP.php';

// 1. Lidhja me Databazën MySQL të XAMPP
$host = "localhost";
$db_user = "root";
$db_pass = "";
$db_name = "autopro_db"; // Emri i databazës tuaj

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

// 2. Marrja e të dhënave nga kërkesa HTTP (POST JSON)
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

        // 4. Dërgimi i Email-it me PHPMailer
        $mail = new PHPMailer(true);
        $email_sent = false;

        try {
            // Konfigurimi i Serverit SMTP të Gmail
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'adhurimmehmetii2006@gmail.com';
            
            // Këtu vendos kodin 16-shifror nga Google (pa hapësira)
            $mail->Password   = twkerzyzltgiurcp; 
            
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;

            // Marrësi dhe Dërguesi
            $mail->setFrom('adhurimmehmetii2006@gmail.com', 'Auto Pro System');
            $mail->addAddress('adhurimmehmetii2006@gmail.com');

            // Përmbajtja e Email-it
            $mail->isHTML(false);
            $mail->Subject = "Auto Pro - Detyre e re Servisi #{$new_task_id}";
            $mail->Body    = "Përshëndetje,\n\nËshtë krijuar një detyrë e re për servisin: {$title} (Vetura ID: {$vehicle_id}).\n\nNumri i kontaktit: 045916042";

            $mail->send();
            $email_sent = true;
        } catch (Exception $e) {
            $email_error_message = $mail->ErrorInfo;
        }

        http_response_code(201);
        echo json_encode([
            "success" => true,
            "message" => $email_sent 
                ? "Task-u u ruajt me sukses në databazë dhe njoftimi u dërgua në email!" 
                : "Task-u u ruajt, por email-i dështoi: " . ($email_error_message ?? 'Gabim SMTP'),
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
