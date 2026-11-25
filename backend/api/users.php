<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
app_start_session();

try {
    $db = app_database();
    $user = app_require_user($db);
    app_require_admin($user);

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $users = $db->query(
            "SELECT id, name, email, phone, role, vehicle_id, created_at
             FROM users
             ORDER BY created_at DESC"
        )->fetchAll();
        app_json_response(['users' => $users]);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'PATCH') {
        app_json_response(['error' => 'Metoda nuk lejohet.'], 405);
    }
    app_require_csrf();
    $data = app_request_data();
    $id = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);
    if (!$id) {
        app_json_response(['error' => 'Përdoruesi nuk është i vlefshëm.'], 422);
    }

    $updates = [];
    $parameters = [];
    if (array_key_exists('role', $data)) {
        $role = (string) $data['role'];
        if (!in_array($role, ['admin', 'client', 'mechanic'], true)) {
            app_json_response(['error' => 'Roli nuk është i vlefshëm.'], 422);
        }
        if ((int) $user['id'] === $id && $role !== 'admin') {
            $admins = (int) $db->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
            if ($admins <= 1) {
                app_json_response(['error' => 'Nuk mund ta heqësh rolin e administratorit të fundit.'], 409);
            }
        }
        $updates[] = 'role = ?';
        $parameters[] = $role;
    }

    if (array_key_exists('vehicle_id', $data)) {
        $vehicleId = $data['vehicle_id'] === null || $data['vehicle_id'] === ''
            ? null
            : filter_var($data['vehicle_id'], FILTER_VALIDATE_INT);
        if ($vehicleId !== null && (!$vehicleId || $vehicleId < 1)) {
            app_json_response(['error' => 'ID e veturës nuk është e vlefshme.'], 422);
        }
        $updates[] = 'vehicle_id = ?';
        $parameters[] = $vehicleId;
    }

    if (!$updates) {
        app_json_response(['error' => 'Nuk ka të dhëna për përditësim.'], 422);
    }

    $parameters[] = $id;
    $statement = $db->prepare('UPDATE users SET ' . implode(', ', $updates) . ' WHERE id = ?');
    $statement->execute($parameters);
    if ($statement->rowCount() === 0) {
        $check = $db->prepare('SELECT id FROM users WHERE id = ?');
        $check->execute([$id]);
        if (!$check->fetch()) {
            app_json_response(['error' => 'Klienti nuk u gjet.'], 404);
        }
    }
    app_json_response(['message' => 'Përdoruesi u përditësua.']);
} catch (Throwable $error) {
    app_api_error($error);
}
