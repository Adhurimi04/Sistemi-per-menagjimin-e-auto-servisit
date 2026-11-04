<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
app_start_session();

try {
    $db = app_database();
    $action = $_GET['action'] ?? '';

    if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'csrf') {
        app_json_response(['csrfToken' => app_csrf_token()]);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'me') {
        app_json_response(['user' => app_current_user($db)]);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        app_json_response(['error' => 'Metoda nuk lejohet.'], 405);
    }
    app_require_csrf();
    $data = app_request_data();

    if ($action === 'register') {
        $name = trim((string) ($data['name'] ?? ''));
        $email = strtolower(trim((string) ($data['email'] ?? '')));
        $phone = trim((string) ($data['phone'] ?? ''));
        $password = (string) ($data['password'] ?? '');

        if (mb_strlen($name) < 2 || mb_strlen($name) > 120) {
            app_json_response(['error' => 'Emri duhet të ketë 2 deri në 120 karaktere.'], 422);
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
            app_json_response(['error' => 'Shkruaj një adresë emaili të vlefshme.'], 422);
        }
        if (mb_strlen($phone) < 5 || mb_strlen($phone) > 40) {
            app_json_response(['error' => 'Shkruaj numrin e telefonit.'], 422);
        }
        if (strlen($password) < 10 || strlen($password) > 72) {
            app_json_response(['error' => 'Fjalëkalimi duhet të ketë 10 deri në 72 karaktere.'], 422);
        }

        $statement = $db->prepare('INSERT INTO users (name, email, phone, password_hash) VALUES (?, ?, ?, ?)');
        try {
            $statement->execute([$name, $email, $phone, password_hash($password, PASSWORD_DEFAULT)]);
        } catch (PDOException $error) {
            if ($error->getCode() === '23000') {
                app_json_response(['error' => 'Ky email është regjistruar tashmë.'], 409);
            }
            throw $error;
        }

        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $db->lastInsertId();
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        app_json_response(['message' => 'Llogaria u krijua me sukses.'], 201);
    }

    if ($action === 'login') {
        $email = strtolower(trim((string) ($data['email'] ?? '')));
        $password = (string) ($data['password'] ?? '');
        $statement = $db->prepare('SELECT id, password_hash FROM users WHERE email = ?');
        $statement->execute([$email]);
        $user = $statement->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            app_json_response(['error' => 'Emaili ose fjalëkalimi nuk është i saktë.'], 401);
        }

        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        app_json_response(['message' => 'U kyçe me sukses.']);
    }

    if ($action === 'logout') {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $parameters = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $parameters['path'], '', $parameters['secure'], $parameters['httponly']);
        }
        session_destroy();
        app_json_response(['message' => 'Dole nga llogaria.']);
    }

    app_json_response(['error' => 'Veprimi nuk u gjet.'], 404);
} catch (Throwable $error) {
    app_api_error($error);
}
