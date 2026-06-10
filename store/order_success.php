<?php
/**
 * order_success.php — Страница подтверждения заказа.
 */

session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/helpers.php';

if (!isset($_SESSION['user_id'])) { header('Location: index.php'); exit; }

$orderId = (int)($_GET['order_id'] ?? 0);
if ($orderId <= 0) { header('Location: account.php'); exit; }

// Загружаем заказ (только свой)
$stmt = $pdo->prepare(
    "SELECT o.*, s.label AS status_label, s.color AS status_color
     FROM orders o JOIN statuses s ON s.id = o.status_id
     WHERE o.id = ? AND o.user_id = ?"
);
$stmt->execute([$orderId, $_SESSION['user_id']]);
$order = $stmt->fetch();

if (!$order) { header('Location: account.php'); exit; }

// Позиции заказа
$items = $pdo->prepare(
    "SELECT oi.*, p.name, p.image, b.name AS brand_name
     FROM order_items oi
     JOIN products p ON p.id = oi.product_id
     JOIN brands   b ON b.id  = p.brand_id
     WHERE oi.order_id = ?"
);
$items->execute([$orderId]);
$orderItems = $items->fetchAll();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Заказ #<?= $orderId ?> оформлен — STEPUP</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<header class="header">
  <div class="header__inner">
    <a href="index.php" class="header__logo">STEP<span>UP</span></a>
  </div>
</header>

<main>
<div class="section" style="max-width:640px;margin:0 auto;text-align:center;">

  <!-- Иконка успеха -->
  <div style="width:80px;height:80px;background:var(--color-success);border-radius:50%;
              display:flex;align-items:center;justify-content:center;
              font-size:2rem;margin:0 auto 24px;box-shadow:0 8px 24px rgba(16,185,129,.3);">
    ✓
  </div>

  <h1 style="font-family:var(--font-display);font-size:2.4rem;letter-spacing:.04em;margin-bottom:8px;">
    ЗАКАЗ ПРИНЯТ!
  </h1>
  <p style="color:var(--color-muted);margin-bottom:32px;font-size:1.05rem;">
    Номер заказа: <strong style="color:var(--color-dark)">#<?= $orderId ?></strong>
  </p>

  <!-- Детали заказа -->
  <div style="background:var(--color-bg);border:1px solid var(--color-border);
              border-radius:var(--radius-xl);padding:28px;text-align:left;margin-bottom:24px;">

    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
      <strong style="font-size:1rem;">Состав заказа</strong>
      <?= statusBadge($order['status_label'], $order['status_color']) ?>
    </div>

    <?php foreach ($orderItems as $item): ?>
    <div style="display:flex;gap:12px;align-items:center;padding:10px 0;
                border-bottom:1px solid var(--color-border);">
      <img src="assets/img/products/<?= e($item['image']) ?>"
           onerror="this.src='assets/img/products/placeholder.svg'"
           style="width:52px;height:52px;object-fit:contain;background:var(--color-surface);
                  border-radius:var(--radius-sm);">
      <div style="flex:1">
        <div style="font-weight:600;font-size:.9rem;"><?= e($item['brand_name']) ?> <?= e($item['name']) ?></div>
        <div style="font-size:.78rem;color:var(--color-muted);">
          Размер: <?= (float)$item['size'] ?> · <?= (int)$item['quantity'] ?> шт.
        </div>
      </div>
      <strong><?= formatPrice($item['unit_price'] * $item['quantity']) ?> ₽</strong>
    </div>
    <?php endforeach; ?>

    <div style="display:flex;justify-content:space-between;margin-top:16px;
                font-size:1.1rem;font-weight:800;">
      <span>Итого:</span>
      <span><?= formatPrice($order['total_price']) ?> ₽</span>
    </div>
  </div>

  <!-- Адрес -->
  <div style="background:var(--color-surface);border-radius:var(--radius-lg);padding:16px;
              text-align:left;margin-bottom:32px;font-size:.88rem;">
    <strong>📍 Адрес доставки:</strong><br>
    <span style="color:var(--color-muted)"><?= nl2br(e($order['delivery_addr'])) ?></span>
  </div>

  <div style="display:flex;gap:12px;justify-content:center;flex-wrap:wrap;">
    <a href="account.php" class="btn btn--secondary">Мои заказы</a>
    <a href="catalog.php" class="btn btn--primary">Продолжить покупки</a>
  </div>

</div>
</main>

<footer class="footer"><p><strong>STEPUP</strong> &copy; <?= date('Y') ?></p></footer>

<!-- Очищаем корзину после успешного заказа -->
<script>
  sessionStorage.removeItem('su_cart');
</script>
</body>
</html>
