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
            "SELECT id, name, email, phone, role, created_at
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
    $role = (string) ($data['role'] ?? '');
    if (!$id || !in_array($role, ['admin', 'client'], true)) {
        app_json_response(['error' => 'Përdoruesi ose roli nuk është i vlefshëm.'], 422);
    }

    if ((int) $user['id'] === $id && $role !== 'admin') {
        $admins = (int) $db->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
        if ($admins <= 1) {
            app_json_response(['error' => 'Nuk mund ta heqësh rolin e administratorit të fundit.'], 409);
        }
    }

    $statement = $db->prepare('UPDATE users SET role = ? WHERE id = ?');
    $statement->execute([$role, $id]);
    if ($statement->rowCount() === 0) {
        $check = $db->prepare('SELECT id FROM users WHERE id = ?');
        $check->execute([$id]);
        if (!$check->fetch()) {
            app_json_response(['error' => 'Klienti nuk u gjet.'], 404);
        }
    }
    app_json_response(['message' => 'Roli u përditësua.']);
} catch (Throwable $error) {
    app_api_error($error);
}
