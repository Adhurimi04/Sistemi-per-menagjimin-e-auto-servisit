<?php
declare(strict_types=1);

function app_database(): PDO
{
    static $connection = null;
    if ($connection instanceof PDO) {
        return $connection;
    }

    $host = getenv('AUTO_PRO_DB_HOST') ?: '127.0.0.1';
    $name = getenv('AUTO_PRO_DB_NAME') ?: 'auto pro';
    $user = getenv('AUTO_PRO_DB_USER') ?: 'root';
    $password = getenv('AUTO_PRO_DB_PASSWORD');
    $password = $password === false ? '' : $password;

    if (!preg_match('/\A[a-zA-Z0-9 _-]{1,64}\z/', $name)) {
        throw new RuntimeException('AUTO_PRO_DB_NAME has an invalid database name.');
    }

    $server = new PDO(
        "mysql:host={$host};charset=utf8mb4",
        $user,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $quotedName = '`' . str_replace('`', '``', $name) . '`';
    $server->exec("CREATE DATABASE IF NOT EXISTS {$quotedName} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

    $connection = new PDO(
        "mysql:host={$host};dbname={$name};charset=utf8mb4",
        $user,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );

    app_ensure_schema($connection);

    return $connection;
}

function app_ensure_schema(PDO $db): void
{
    $db->exec(
        "CREATE TABLE IF NOT EXISTS users (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(120) NOT NULL,
            email VARCHAR(190) NOT NULL UNIQUE,
            phone VARCHAR(40) NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            role ENUM('client', 'admin') NOT NULL DEFAULT 'client',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $db->exec(
        "CREATE TABLE IF NOT EXISTS services (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(120) NOT NULL UNIQUE,
            description VARCHAR(500) NOT NULL DEFAULT '',
            active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $db->exec(
        "CREATE TABLE IF NOT EXISTS bookings (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL,
            service_id BIGINT UNSIGNED NOT NULL,
            customer_name VARCHAR(120) NOT NULL,
            customer_email VARCHAR(190) NOT NULL,
            customer_phone VARCHAR(40) NOT NULL,
            booking_date DATE NOT NULL,
            vehicle VARCHAR(160) NOT NULL DEFAULT '',
            message TEXT NOT NULL,
            status ENUM('pending', 'confirmed', 'in_progress', 'completed', 'cancelled') NOT NULL DEFAULT 'pending',
            admin_note TEXT NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_bookings_user_date (user_id, booking_date),
            INDEX idx_bookings_status_date (status, booking_date),
            CONSTRAINT fk_bookings_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
            CONSTRAINT fk_bookings_service FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $count = (int) $db->query('SELECT COUNT(*) FROM services')->fetchColumn();
    if ($count === 0) {
        $insert = $db->prepare('INSERT INTO services (name, description) VALUES (?, ?)');
        $services = [
            ['Riparime mekanike', 'Kontroll dhe riparim profesional i motorit dhe pjesëve mekanike.'],
            ['Diagnostikim OBD', 'Identifikim i saktë i problemeve me pajisje diagnostikuese.'],
            ['Vaj dhe filtra', 'Ndërrim i vajit dhe filtrave për mirëmbajtje të rregullt.'],
            ['Sistemi i frenave', 'Kontroll dhe servis i frenave për siguri në rrugë.'],
            ['Goma dhe balancim', 'Montim gomash, balancim dhe kontroll i rrotave.'],
            ['Klimatizimi', 'Kontroll dhe mirëmbajtje e sistemit të klimatizimit.'],
            ['Kontroll i përgjithshëm', 'Kontroll i përgjithshëm i gjendjes së veturës.'],
            ['Tjetër', 'Shërbim tjetër i kërkuar nga klienti.'],
        ];
        foreach ($services as [$name, $description]) {
            $insert->execute([$name, $description]);
        }
    }
}

function app_start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    ini_set('session.use_strict_mode', '1');
    session_set_cookie_params([
        'httponly' => true,
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'samesite' => 'Lax',
        'path' => '/',
    ]);
    session_start();
}

function app_json_response(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function app_request_data(): array
{
    $data = json_decode(file_get_contents('php://input'), true);
    return is_array($data) ? $data : [];
}

function app_csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function app_require_csrf(): void
{
    $submitted = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!is_string($submitted) || !hash_equals(app_csrf_token(), $submitted)) {
        app_json_response(['error' => 'Sesioni ka skaduar. Rifresko faqen dhe provo përsëri.'], 419);
    }
}

function app_current_user(PDO $db): ?array
{
    $userId = $_SESSION['user_id'] ?? null;
    if (!is_int($userId) && !ctype_digit((string) $userId)) {
        return null;
    }

    $statement = $db->prepare('SELECT id, name, email, phone, role, created_at FROM users WHERE id = ?');
    $statement->execute([(int) $userId]);
    $user = $statement->fetch();
    return $user ?: null;
}

function app_require_user(PDO $db): array
{
    $user = app_current_user($db);
    if (!$user) {
        app_json_response(['error' => 'Duhet të kyçesh për të vazhduar.'], 401);
    }
    return $user;
}

function app_require_admin(array $user): void
{
    if ($user['role'] !== 'admin') {
        app_json_response(['error' => 'Nuk ke leje për këtë veprim.'], 403);
    }
}

function app_api_error(Throwable $error): void
{
    error_log('Auto Pro API error: ' . $error->getMessage());
    app_json_response(['error' => 'Ndodhi një gabim në server. Kontrollo konfigurimin e databazës.'], 500);
}
