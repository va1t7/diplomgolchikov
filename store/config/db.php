<?php
/**
 * db.php — Единственная точка подключения к MySQL через PDO.
 *
 * Как использовать в других файлах:
 *   require_once __DIR__ . '/../config/db.php';
 *   $stmt = $pdo->prepare("SELECT ...");
 */

// ── Параметры подключения ──────────────────────────────────────
define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_NAME', 'shoe_store');
define('DB_USER', 'root');       // замените на своего пользователя
define('DB_PASS', '');           // замените на свой пароль
define('DB_CHARSET', 'utf8mb4');

/**
 * Создаёт и возвращает единственный экземпляр PDO (Singleton-паттерн).
 * При ошибке подключения — бросает PDOException (перехватывается выше).
 *
 * @return PDO
 */
function getDB(): PDO
{
    static $pdo = null; // статическая переменная — создаётся один раз

    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            DB_HOST, DB_PORT, DB_NAME, DB_CHARSET
        );

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,  // бросать исключения
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,        // возвращать ассоциативные массивы
            PDO::ATTR_EMULATE_PREPARES   => false,                   // настоящие prepared statements
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // В продакшне — логировать, не показывать пароль
            error_log('[DB ERROR] ' . $e->getMessage());
            http_response_code(500);
            die(json_encode(['error' => 'Ошибка подключения к базе данных.']));
        }
    }

    return $pdo;
}

// Делаем $pdo доступным как переменную (удобно для include)
$pdo = getDB();
