<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
app_start_session();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit();
}

try {
    $user = app_require_user(app_database());
    $role = $user['role'];
    $method = $_SERVER['REQUEST_METHOD'];

    $conn = new mysqli('localhost', 'root', '', 'autopro_db');
    $conn->set_charset('utf8mb4');

    $priceColumn = $conn->query("SHOW COLUMNS FROM service_tasks LIKE 'price'");
    if ($priceColumn->num_rows === 0) {
        $conn->query(
            'ALTER TABLE service_tasks
             ADD COLUMN price DECIMAL(10, 2) NOT NULL DEFAULT 0.00 AFTER status'
        );
    }

    $conn->query(
        "CREATE TABLE IF NOT EXISTS service_catalog (
            id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            service_name VARCHAR(150) NOT NULL,
            default_price DECIMAL(10, 2) NOT NULL,
            UNIQUE KEY uq_service_catalog_name (service_name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    $catalogCount = (int) $conn->query('SELECT COUNT(*) AS total FROM service_catalog')->fetch_assoc()['total'];
    if ($catalogCount === 0) {
        $catalogInsert = $conn->prepare(
            'INSERT INTO service_catalog (service_name, default_price) VALUES (?, ?)'
        );
        $catalogServices = [
            ['Ndërrimi i 4 gomave + Balancimi', '20.00'],
            ['Vetëm Balancimi i gomave (4 copë)', '10.00'],
            ['Ndërrim vaji dhe filtra (Punë dore + Vaj)', '55.00'],
            ['Ndërrim diskave dhe pllakave të frenave', '85.00'],
            ['Diagnostikë kompjuterike (OBD2)', '15.00'],
            ['Regjistrim/Gjeometri e rrotave (Optika)', '25.00'],
            ['Mbushje e klimës (Freon R134a)', '30.00'],
        ];
        foreach ($catalogServices as [$serviceName, $defaultPrice]) {
            $catalogInsert->bind_param('ss', $serviceName, $defaultPrice);
            $catalogInsert->execute();
        }
    }

    if ($method === 'GET') {
        if ($role === 'client' && $user['vehicle_id'] === null) {
            app_json_response(['success' => true, 'data' => [], 'catalog' => []]);
        }

        if ($role === 'client') {
            $statement = $conn->prepare(
                'SELECT id, vehicle_id, assigned_to, title, status, price
                 FROM service_tasks WHERE vehicle_id = ? ORDER BY created_at DESC'
            );
            $vehicleId = (int) $user['vehicle_id'];
            $statement->bind_param('i', $vehicleId);
            $statement->execute();
            $result = $statement->get_result();
        } else {
            $result = $conn->query(
                'SELECT id, vehicle_id, assigned_to, title, status, price
                 FROM service_tasks ORDER BY created_at DESC'
            );
        }

        $tasks = [];
        while ($task = $result->fetch_assoc()) {
            $tasks[] = $task;
        }
        $catalog = $conn->query(
            'SELECT id, service_name, default_price FROM service_catalog ORDER BY service_name'
        )->fetch_all(MYSQLI_ASSOC);
        app_json_response(['success' => true, 'data' => $tasks, 'catalog' => $catalog]);
    }

    if ($method === 'POST' || $method === 'PUT') {
        if (!in_array($role, ['admin', 'mechanic'], true)) {
            app_json_response(['success' => false, 'message' => 'Nuk keni leje për këtë veprim.'], 403);
        }
    } elseif ($method === 'DELETE') {
        if ($role !== 'admin') {
            app_json_response(['success' => false, 'message' => 'Vetëm Administratori mund të fshijë detyra.'], 403);
        }
    } else {
        app_json_response(['success' => false, 'message' => 'Metoda e kërkesës nuk lejohet.'], 405);
    }

    app_require_csrf();
    $data = app_request_data();

    if ($method === 'PUT') {
        $id = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);
        $status = $data['status'] ?? null;
        $allowedStatuses = ['TODO', 'IN_PROGRESS', 'DONE'];
        if (!$id || $id < 1 || !is_string($status) || !in_array($status, $allowedStatuses, true)) {
            app_json_response(['success' => false, 'message' => 'ID dhe statusi i vlefshëm janë të detyrueshme.'], 400);
        }

        $update = $conn->prepare('UPDATE service_tasks SET status = ? WHERE id = ?');
        $update->bind_param('si', $status, $id);
        $update->execute();
        if ($update->affected_rows === 0) {
            $check = $conn->prepare('SELECT id FROM service_tasks WHERE id = ?');
            $check->bind_param('i', $id);
            $check->execute();
            $check->store_result();
            if ($check->num_rows === 0) {
                app_json_response(['success' => false, 'message' => 'Detyra nuk u gjet.'], 404);
            }
        }
        app_json_response(['success' => true, 'message' => 'Statusi u përditësua!', 'status' => $status]);
    }

    if ($method === 'DELETE') {
        $id = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);
        if (!$id || $id < 1) {
            app_json_response(['success' => false, 'message' => 'ID e detyrës nuk është e vlefshme.'], 400);
        }
        $delete = $conn->prepare('DELETE FROM service_tasks WHERE id = ?');
        $delete->bind_param('i', $id);
        $delete->execute();
        if ($delete->affected_rows === 0) {
            app_json_response(['success' => false, 'message' => 'Detyra nuk u gjet.'], 404);
        }
        app_json_response(['success' => true, 'message' => 'Detyra u fshi me sukses.']);
    }

    $title = trim((string) ($data['title'] ?? ''));
    $vehicleId = filter_var($data['vehicle_id'] ?? null, FILTER_VALIDATE_INT);
    $assignedTo = isset($data['assigned_to'])
        ? filter_var($data['assigned_to'], FILTER_VALIDATE_INT)
        : null;
    $status = (string) ($data['status'] ?? 'TODO');
    $price = $data['price'] ?? '0.00';
    if (is_float($price) || is_int($price)) {
        $price = number_format((float) $price, 2, '.', '');
    }
    if ($title === '' || mb_strlen($title) > 255 || !$vehicleId || $vehicleId < 1) {
        app_json_response(['success' => false, 'message' => 'Titulli dhe ID e vlefshme e veturës janë të detyrueshme.'], 400);
    }
    if ($assignedTo !== null && (!$assignedTo || $assignedTo < 1)) {
        app_json_response(['success' => false, 'message' => 'ID e mekanikut nuk është e vlefshme.'], 400);
    }
    if (!in_array($status, ['TODO', 'IN_PROGRESS', 'DONE'], true)) {
        app_json_response(['success' => false, 'message' => 'Statusi nuk është i vlefshëm.'], 400);
    }
    if (!is_string($price) || !preg_match('/\A(?:0|[1-9][0-9]{0,7})(?:\.[0-9]{1,2})?\z/', $price)) {
        app_json_response(['success' => false, 'message' => 'Çmimi duhet të jetë një vlerë e vlefshme deri në dy shifra dhjetore.'], 400);
    }

    if ($assignedTo === null) {
        $insert = $conn->prepare(
            'INSERT INTO service_tasks (vehicle_id, assigned_to, title, status, price)
             VALUES (?, NULL, ?, ?, ?)'
        );
        $insert->bind_param('isss', $vehicleId, $title, $status, $price);
    } else {
        $insert = $conn->prepare(
            'INSERT INTO service_tasks (vehicle_id, assigned_to, title, status, price)
             VALUES (?, ?, ?, ?, ?)'
        );
        $insert->bind_param('iisss', $vehicleId, $assignedTo, $title, $status, $price);
    }
    $insert->execute();
    $newTaskId = $conn->insert_id;

    $resendApiKey = getenv('RESEND_API_KEY');
    $safeTitle = htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $emailData = [
        'from' => 'onboarding@resend.dev',
        'to' => ['adhurimmehmetii2006@gmail.com'],
        'subject' => "Auto Pro - Detyrë e re Servisi #{$newTaskId}",
        'html' => "
            <h2>Përshëndetje Adhurim,</h2>
            <p>Është krijuar me sukses një detyrë e re servisi në sistemin <strong>Auto Pro</strong>:</p>
            <ul>
                <li><strong>ID e Detyrës:</strong> #{$newTaskId}</li>
                <li><strong>Titulli:</strong> {$safeTitle}</li>
                <li><strong>ID e Vetures:</strong> {$vehicleId}</li>
                <li><strong>Statusi:</strong> {$status}</li>
                <li><strong>Çmimi total:</strong> {$price} € (përfshirë TVSH-në)</li>
            </ul>
            <p>Numri i kontaktit: 045916042</p>
        ",
    ];

    $response = null;
    $curlError = null;
    $httpCode = 0;
    if ($resendApiKey) {
        $ch = curl_init('https://api.resend.com/emails');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $resendApiKey,
            'Content-Type: application/json',
        ]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($emailData));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        $curlError = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
    } else {
        $curlError = 'RESEND_API_KEY is not configured.';
    }

    $emailSent = $httpCode >= 200 && $httpCode < 300;
    http_response_code(201);
    echo json_encode([
        'success' => true,
        'message' => $emailSent
            ? 'Detyra u ruajt me sukses në databazë dhe email-i u dërgua!'
            : 'Detyra u ruajt në databazë, por dërgimi i email-it dështoi.',
        'task_id' => $newTaskId,
        'price' => $price,
        'resend_http_code' => $httpCode,
        'resend_details' => (is_string($response) ? json_decode($response) : null) ?? $curlError,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $error) {
    app_api_error($error);
}
