<?php
/**
 * api/cart_sync.php — Синхронизация корзины с PHP-сессией.
 *
 * Принимает POST с JSON-телом (массив позиций корзины).
 * Сохраняет в $_SESSION['cart'] для использования при оформлении заказа.
 *
 * Вызывается через navigator.sendBeacon() из cart.js.
 */

session_start();
header('Content-Type: application/json');

// Читаем тело запроса
$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid JSON']);
    exit;
}

// Сохраняем в сессию (санируем данные)
$cart = [];
foreach ($data as $item) {
    $id    = (int)($item['id']    ?? 0);
    $size  = isset($item['size']) ? (float)$item['size'] : null;
    $qty   = max(1, (int)($item['qty'] ?? 1));
    $price = (float)($item['price'] ?? 0);
    $name  = htmlspecialchars(strip_tags($item['name'] ?? ''), ENT_QUOTES, 'UTF-8');
    $image = basename($item['image'] ?? 'placeholder.svg'); // только имя файла

    if ($id > 0 && $price > 0) {
        $cart[] = compact('id', 'name', 'price', 'image', 'size', 'qty');
    }
}

$_SESSION['cart'] = $cart;

echo json_encode(['ok' => true, 'items' => count($cart)]);
