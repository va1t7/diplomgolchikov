<?php
/**
 * helpers.php — Вспомогательные функции, используемые во всех шаблонах.
 */

/**
 * Форматирует цену: 8990.00 → "8 990"
 *
 * @param float|string $price
 * @return string
 */
function formatPrice($price): string
{
    return number_format((float) $price, 0, ',', ' ');
}

/**
 * Экранирует строку для вывода в HTML.
 *
 * @param string $str
 * @return string
 */
function e(string $str): string
{
    return htmlspecialchars($str, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/**
 * Возвращает одноразовый CSRF-токен и сохраняет его в сессии.
 *
 * @return string
 */
function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Проверяет CSRF-токен из POST-запроса.
 * При несовпадении завершает выполнение с ошибкой 403.
 */
function verifyCsrf(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(403);
        die('Ошибка безопасности: недействительный CSRF-токен.');
    }
}

/**
 * Возвращает HTML-бейдж для статуса заказа.
 *
 * @param string $label  Текст статуса
 * @param string $color  HEX-цвет
 * @return string
 */
function statusBadge(string $label, string $color): string
{
    // Осветляем цвет для фона (упрощённо — добавляем прозрачность)
    return sprintf(
        '<span class="badge" style="background:%s22;color:%s;border:1px solid %s55">%s</span>',
        $color, $color, $color, e($label)
    );
}
