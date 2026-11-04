<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
app_start_session();

try {
    $db = app_database();
    $user = app_require_user($db);
    $method = $_SERVER['REQUEST_METHOD'];

    if ($method === 'GET') {
        $baseQuery = "SELECT b.id, b.customer_name, b.customer_email, b.customer_phone,
                             b.booking_date, b.vehicle, b.message, b.status, b.admin_note,
                             b.created_at, b.updated_at, s.name AS service_name, u.id AS user_id
                      FROM bookings b
                      JOIN services s ON s.id = b.service_id
                      JOIN users u ON u.id = b.user_id";
        if ($user['role'] === 'admin') {
            $bookings = $db->query($baseQuery . ' ORDER BY b.created_at DESC')->fetchAll();
        } else {
            $statement = $db->prepare($baseQuery . ' WHERE b.user_id = ? ORDER BY b.created_at DESC');
            $statement->execute([$user['id']]);
            $bookings = $statement->fetchAll();
        }
        app_json_response(['bookings' => $bookings]);
    }

    app_require_csrf();
    $data = app_request_data();

    if ($method === 'POST') {
        $serviceId = filter_var($data['service_id'] ?? null, FILTER_VALIDATE_INT);
        $bookingDate = trim((string) ($data['booking_date'] ?? ''));
        $vehicle = trim((string) ($data['vehicle'] ?? ''));
        $message = trim((string) ($data['message'] ?? ''));
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $bookingDate);
        $dateErrors = DateTimeImmutable::getLastErrors();

        if (!$serviceId || !$date || ($dateErrors !== false && ($dateErrors['warning_count'] || $dateErrors['error_count'])) || $date->format('Y-m-d') !== $bookingDate || $bookingDate < date('Y-m-d')) {
            app_json_response(['error' => 'Zgjedh një shërbim dhe datë të vlefshme, jo në të kaluarën.'], 422);
        }
        if (mb_strlen($vehicle) > 160 || mb_strlen($message) > 3000) {
            app_json_response(['error' => 'Përshkrimi i veturës ose mesazhi është tepër i gjatë.'], 422);
        }

        $service = $db->prepare('SELECT name FROM services WHERE id = ? AND active = 1');
        $service->execute([$serviceId]);
        $serviceName = $service->fetchColumn();
        if (!$serviceName) {
            app_json_response(['error' => 'Shërbimi nuk është më i disponueshëm.'], 422);
        }

        $statement = $db->prepare(
            "INSERT INTO bookings
                (user_id, service_id, customer_name, customer_email, customer_phone, booking_date, vehicle, message, admin_note)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, '')"
        );
        $statement->execute([
            $user['id'],
            $serviceId,
            $user['name'],
            $user['email'],
            $user['phone'],
            $bookingDate,
            $vehicle,
            $message,
        ]);
        app_json_response([
            'message' => 'Rezervimi u ruajt dhe është në pritje të konfirmimit.',
            'booking_id' => (int) $db->lastInsertId(),
            'booking' => [
                'name' => $user['name'],
                'email' => $user['email'],
                'phone' => $user['phone'],
                'service' => $serviceName,
                'date' => $bookingDate,
                'car' => $vehicle !== '' ? $vehicle : 'Nuk është specifikuar',
                'message' => $message !== '' ? $message : 'Pa mesazh shtesë',
            ],
        ], 201);
    }

    if ($method === 'PATCH') {
        app_require_admin($user);
        $id = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);
        $status = (string) ($data['status'] ?? '');
        $adminNote = trim((string) ($data['admin_note'] ?? ''));
        $allowedStatuses = ['pending', 'confirmed', 'in_progress', 'completed', 'cancelled'];
        if (!$id || !in_array($status, $allowedStatuses, true) || mb_strlen($adminNote) > 3000) {
            app_json_response(['error' => 'Statusi ose shënimi nuk është i vlefshëm.'], 422);
        }
        $statement = $db->prepare('UPDATE bookings SET status = ?, admin_note = ? WHERE id = ?');
        $statement->execute([$status, $adminNote, $id]);
        if ($statement->rowCount() === 0) {
            $check = $db->prepare('SELECT id FROM bookings WHERE id = ?');
            $check->execute([$id]);
            if (!$check->fetch()) {
                app_json_response(['error' => 'Rezervimi nuk u gjet.'], 404);
            }
        }
        app_json_response(['message' => 'Rezervimi u përditësua.']);
    }

    app_json_response(['error' => 'Metoda nuk lejohet.'], 405);
} catch (Throwable $error) {
    app_api_error($error);
}
