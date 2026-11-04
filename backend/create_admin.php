<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('Not found');
}

require_once __DIR__ . '/bootstrap.php';

if ($argc < 2 || !filter_var($argv[1], FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Përdorimi: php backend/create_admin.php email@shembull.com\nRegjistro fillimisht llogarinë përmes faqes së regjistrimit.\n");
    exit(1);
}

try {
    $db = app_database();
    $statement = $db->prepare("UPDATE users SET role = 'admin' WHERE email = ?");
    $statement->execute([strtolower($argv[1])]);
    if ($statement->rowCount() === 0) {
        $check = $db->prepare('SELECT id FROM users WHERE email = ?');
        $check->execute([strtolower($argv[1])]);
        if (!$check->fetch()) {
            fwrite(STDERR, "Llogaria nuk u gjet. Regjistroje fillimisht në register.php.\n");
            exit(1);
        }
    }
    fwrite(STDOUT, "Llogaria {$argv[1]} tani është administrator.\n");
} catch (Throwable $error) {
    fwrite(STDERR, "Nuk u krijua administratori: {$error->getMessage()}\n");
    exit(1);
}
