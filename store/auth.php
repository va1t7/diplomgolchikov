<?php
/**
 * auth.php — Логика регистрации, авторизации и управления сессией.
 *
 * Обрабатывает POST-запросы:
 *   action=register  — регистрация нового клиента
 *   action=login     — вход в систему
 *   action=logout    — выход
 */

session_start();
require_once __DIR__ . '/config/db.php';

// ── Вспомогательные функции ────────────────────────────────────

/**
 * Перенаправляет пользователя и завершает скрипт.
 *
 * @param string $url  Целевой URL
 */
function redirect(string $url): void
{
    header("Location: $url");
    exit;
}

/**
 * Проверяет, вошёл ли пользователь в систему.
 *
 * @return bool
 */
function isLoggedIn(): bool
{
    return isset($_SESSION['user_id']);
}

/**
 * Возвращает role_id текущего пользователя или null.
 *
 * @return int|null
 */
function getCurrentRole(): ?int
{
    return $_SESSION['role_id'] ?? null;
}

/**
 * Проверяет, имеет ли текущий пользователь нужную роль.
 * Завершает выполнение с ошибкой 403, если нет.
 *
 * @param int[] $allowedRoles  Массив допустимых role_id
 */
function requireRole(array $allowedRoles): void
{
    if (!isLoggedIn() || !in_array(getCurrentRole(), $allowedRoles, true)) {
        http_response_code(403);
        die('<h1>403 — Доступ запрещён</h1>');
    }
}

// ── Обработка POST-запросов ────────────────────────────────────

$action = $_POST['action'] ?? '';
$errors = [];

// ---- РЕГИСТРАЦИЯ ----
if ($action === 'register') {
    $name     = trim($_POST['name']     ?? '');
    $email    = trim($_POST['email']    ?? '');
    $password = trim($_POST['password'] ?? '');
    $phone    = trim($_POST['phone']    ?? '');

    // Валидация
    if (mb_strlen($name) < 2)          $errors[] = 'Введите имя (минимум 2 символа).';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Некорректный email.';
    if (mb_strlen($password) < 6)      $errors[] = 'Пароль должен быть не менее 6 символов.';

    if (empty($errors)) {
        // Проверяем, не занят ли email
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);

        if ($stmt->fetch()) {
            $errors[] = 'Этот email уже зарегистрирован.';
        } else {
            // Хэшируем пароль и сохраняем пользователя (role_id=1 — клиент)
            $hash = password_hash($password, PASSWORD_BCRYPT);

            $ins = $pdo->prepare(
                "INSERT INTO users (role_id, name, email, password, phone)
                 VALUES (1, ?, ?, ?, ?)"
            );
            $ins->execute([$name, $email, $hash, $phone]);

            // Автоматически логиним после регистрации
            $_SESSION['user_id']   = (int) $pdo->lastInsertId();
            $_SESSION['user_name'] = $name;
            $_SESSION['role_id']   = 1;

            redirect('index.php');
        }
    }

    // Если есть ошибки — возвращаем на страницу с сообщением
    $_SESSION['auth_errors'] = $errors;
    $_SESSION['auth_form']   = 'register';
    redirect('login.php');
}

// ---- ВХОД ----
if ($action === 'login') {
    $email    = trim($_POST['email']    ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($email) || empty($password)) {
        $errors[] = 'Заполните все поля.';
    } else {
        // Ищем пользователя по email
        $stmt = $pdo->prepare(
            "SELECT u.id, u.name, u.password, u.role_id
             FROM   users u
             WHERE  u.email = ?
             LIMIT  1"
        );
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password'])) {
            $errors[] = 'Неверный email или пароль.';
        } else {
            // Сохраняем данные в сессии
            session_regenerate_id(true); // защита от session fixation
            $_SESSION['user_id']   = (int) $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['role_id']   = (int) $user['role_id'];

            // Перенаправляем в зависимости от роли
            $roleRedirects = [
                1 => 'index.php',        // клиент → главная
                2 => 'admin.php',        // контент-менеджер → админка
                3 => 'admin.php',        // логист → админка
                4 => 'admin.php',        // аналитик → админка
            ];

            redirect($roleRedirects[$user['role_id']] ?? 'index.php');
        }
    }

    $_SESSION['auth_errors'] = $errors;
    $_SESSION['auth_form']   = 'login';
    redirect('login.php');
}

// ---- ВЫХОД ----
if ($action === 'logout') {
    // Полностью уничтожаем сессию
    $_SESSION = [];
    session_destroy();
    redirect('index.php');
}
