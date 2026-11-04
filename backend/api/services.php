<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
app_start_session();

try {
    $db = app_database();
    $method = $_SERVER['REQUEST_METHOD'];
    $user = $method === 'GET' && !isset($_SESSION['user_id'])
        ? null
        : app_require_user($db);

    if ($method === 'GET') {
        if ($user && $user['role'] === 'admin') {
            $services = $db->query('SELECT id, name, description, active FROM services ORDER BY name')->fetchAll();
        } else {
            $services = $db->query('SELECT id, name, description, active FROM services WHERE active = 1 ORDER BY name')->fetchAll();
        }
        app_json_response(['services' => $services]);
    }

    app_require_csrf();
    app_require_admin($user);
    $data = app_request_data();

    if ($method === 'POST') {
        $name = trim((string) ($data['name'] ?? ''));
        $description = trim((string) ($data['description'] ?? ''));
        if (mb_strlen($name) < 2 || mb_strlen($name) > 120 || mb_strlen($description) > 500) {
            app_json_response(['error' => 'Plotëso emrin e shërbimit (2–120 karaktere) dhe përshkrimin (deri 500).'], 422);
        }
        try {
            $statement = $db->prepare('INSERT INTO services (name, description) VALUES (?, ?)');
            $statement->execute([$name, $description]);
        } catch (PDOException $error) {
            if ($error->getCode() === '23000') {
                app_json_response(['error' => 'Ekziston tashmë një shërbim me këtë emër.'], 409);
            }
            throw $error;
        }
        app_json_response(['message' => 'Shërbimi u shtua.'], 201);
    }

    if ($method === 'PATCH') {
        $id = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);
        $active = filter_var($data['active'] ?? null, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if (!$id || $active === null) {
            app_json_response(['error' => 'Të dhënat e shërbimit nuk janë të vlefshme.'], 422);
        }
        $statement = $db->prepare('UPDATE services SET active = ? WHERE id = ?');
        $statement->execute([$active ? 1 : 0, $id]);
        if ($statement->rowCount() === 0) {
            $check = $db->prepare('SELECT id FROM services WHERE id = ?');
            $check->execute([$id]);
            if (!$check->fetch()) {
                app_json_response(['error' => 'Shërbimi nuk u gjet.'], 404);
            }
        }
        app_json_response(['message' => 'Shërbimi u përditësua.']);
    }

    app_json_response(['error' => 'Metoda nuk lejohet.'], 405);
} catch (Throwable $error) {
    app_api_error($error);
}
