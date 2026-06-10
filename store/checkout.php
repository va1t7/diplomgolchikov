<?php
/**
 * checkout.php — Оформление заказа.
 *
 * GET  — показывает форму с товарами из корзины.
 * POST — сохраняет заказ в БД, очищает корзину, редиректит на order_success.php.
 */

session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/helpers.php';

// Только авторизованные пользователи могут оформлять заказ
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php?redirect=checkout');
    exit;
}

$userId = (int)$_SESSION['user_id'];
$errors = [];

// ── Загружаем данные пользователя (для автозаполнения) ─────────
$userStmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$userStmt->execute([$userId]);
$user = $userStmt->fetch();

// ── Корзина приходит из POST (JS передаёт JSON) ────────────────
// При GET — просто показываем форму, корзина отрисовывается через JS.

// ── Обработка оформления заказа ────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $cartJson = $_POST['cart_data'] ?? '[]';
    $cartItems = json_decode($cartJson, true);

    $deliveryAddr = trim($_POST['address']  ?? '');
    $phone        = trim($_POST['phone']    ?? '');
    $comment      = trim($_POST['comment']  ?? '');

    // Валидация
    if (empty($cartItems))   $errors[] = 'Корзина пуста. Добавьте товары перед оформлением.';
    if (empty($deliveryAddr)) $errors[] = 'Укажите адрес доставки.';
    if (empty($phone))        $errors[] = 'Укажите номер телефона.';

    if (empty($errors)) {
        // Считаем итоговую сумму и проверяем остатки
        $totalPrice = 0;
        $validItems = [];

        foreach ($cartItems as $item) {
            $itemId   = (int)($item['id']    ?? 0);
            $itemSize = (float)($item['size'] ?? 0);
            $itemQty  = (int)($item['qty']   ?? 1);

            if ($itemId <= 0 || $itemSize <= 0) continue;

            // Актуальная цена из БД (не доверяем клиенту)
            $pStmt = $pdo->prepare("SELECT id, name, price FROM products WHERE id = ? AND is_active = 1");
            $pStmt->execute([$itemId]);
            $dbProduct = $pStmt->fetch();

            if (!$dbProduct) continue;

            // Проверяем остаток
            $sStmt = $pdo->prepare(
                "SELECT quantity FROM stock WHERE product_id = ? AND size = ?"
            );
            $sStmt->execute([$itemId, $itemSize]);
            $stockQty = (int)($sStmt->fetchColumn() ?? 0);

            if ($stockQty < $itemQty) {
                $errors[] = "Недостаточно товара «{$dbProduct['name']}» (размер {$itemSize}) на складе.";
                continue;
            }

            $validItems[] = [
                'product_id' => $itemId,
                'size'       => $itemSize,
                'quantity'   => $itemQty,
                'unit_price' => (float)$dbProduct['price'],
            ];
            $totalPrice += $dbProduct['price'] * $itemQty;
        }

        if (empty($validItems)) {
            $errors[] = 'Ни один товар не прошёл проверку наличия.';
        }
    }

    // Если ошибок нет — создаём заказ
    if (empty($errors)) {
        $pdo->beginTransaction();

        try {
            // Вставляем заказ
            $orderStmt = $pdo->prepare(
                "INSERT INTO orders (user_id, status_id, total_price, delivery_addr, comment)
                 VALUES (?, 1, ?, ?, ?)"
            );
            $orderStmt->execute([$userId, $totalPrice, $deliveryAddr, $comment]);
            $orderId = (int)$pdo->lastInsertId();

            // Вставляем позиции и списываем со склада
            $itemStmt  = $pdo->prepare(
                "INSERT INTO order_items (order_id, product_id, size, quantity, unit_price)
                 VALUES (?, ?, ?, ?, ?)"
            );
            $stockStmt = $pdo->prepare(
                "UPDATE stock SET quantity = quantity - ? WHERE product_id = ? AND size = ?"
            );

            foreach ($validItems as $vi) {
                $itemStmt->execute([
                    $orderId, $vi['product_id'], $vi['size'], $vi['quantity'], $vi['unit_price']
                ]);
                $stockStmt->execute([$vi['quantity'], $vi['product_id'], $vi['size']]);
            }

            $pdo->commit();

            // Очищаем корзину в сессии
            unset($_SESSION['cart']);

            // Редиректим на страницу успеха
            header("Location: order_success.php?order_id=$orderId");
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = 'Ошибка при создании заказа. Попробуйте ещё раз.';
            error_log('[ORDER ERROR] ' . $e->getMessage());
        }
    }
}

$cartCount = 0; // На checkout badge не нужен
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Оформление заказа — STEPUP</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/style.css">
  <style>
    .checkout-layout {
      display: grid;
      gap: 32px;
    }
    @media(min-width:768px){
      .checkout-layout { grid-template-columns: 1fr 380px; }
    }

    .checkout-box {
      background:    var(--color-bg);
      border:        1px solid var(--color-border);
      border-radius: var(--radius-xl);
      padding:       28px;
    }
    .checkout-box h2 {
      font-family:   var(--font-display);
      font-size:     1.4rem;
      letter-spacing: .04em;
      text-transform: uppercase;
      margin-bottom: 20px;
      padding-bottom: 12px;
      border-bottom: 1px solid var(--color-border);
    }

    /* Строка товара в итогах */
    .order-line {
      display: flex;
      gap: 12px;
      align-items: center;
      padding: 12px 0;
      border-bottom: 1px solid var(--color-border);
    }
    .order-line:last-of-type { border-bottom: none; }
    .order-line__img {
      width: 56px; height: 56px;
      object-fit: contain;
      background: var(--color-surface);
      border-radius: var(--radius-sm);
      flex-shrink: 0;
    }
    .order-line__info { flex: 1; min-width: 0; }
    .order-line__name { font-weight: 600; font-size: .9rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .order-line__meta { font-size: .78rem; color: var(--color-muted); }
    .order-line__price { font-weight: 700; white-space: nowrap; }
  </style>
</head>
<body>

<header class="header">
  <div class="header__inner">
    <a href="index.php" class="header__logo">STEP<span>UP</span></a>
    <nav class="header__nav">
      <a href="catalog.php">← Каталог</a>
    </nav>
  </div>
</header>

<main>
<div class="section">

  <h1 style="font-family:var(--font-display);font-size:clamp(1.8rem,4vw,2.8rem);
             letter-spacing:.04em;text-transform:uppercase;margin-bottom:32px;">
    Оформление заказа
  </h1>

  <!-- Ошибки с сервера -->
  <?php foreach ($errors as $err): ?>
    <div class="alert alert--error"><?= e($err) ?></div>
  <?php endforeach; ?>

  <!-- Пустая корзина (показывается через JS) -->
  <div id="emptyCartMsg" style="display:none;text-align:center;padding:60px 0;">
    <div style="font-size:3rem;margin-bottom:12px;">🛒</div>
    <p style="color:var(--color-muted);margin-bottom:20px;">Ваша корзина пуста</p>
    <a href="catalog.php" class="btn btn--primary">Перейти в каталог</a>
  </div>

  <div id="checkoutContent">
    <div class="checkout-layout">

      <!-- ── ФОРМА ДАННЫХ ────────────────────────────────────── -->
      <form id="orderForm" method="post" action="checkout.php">

        <!-- Скрытое поле с JSON корзины (заполняется JS) -->
        <input type="hidden" name="cart_data" id="cartDataInput">

        <div class="checkout-box" style="margin-bottom:24px;">
          <h2>Данные доставки</h2>

          <div class="form-group">
            <label>Имя получателя *</label>
            <input type="text" class="form-control" value="<?= e($user['name'] ?? '') ?>"
                   name="recipient" required placeholder="Иван Иванов">
          </div>

          <div class="form-group">
            <label>Телефон *</label>
            <input type="tel" name="phone" class="form-control"
                   value="<?= e($user['phone'] ?? '') ?>"
                   placeholder="+7 (999) 000-00-00" required>
          </div>

          <div class="form-group">
            <label>Адрес доставки *</label>
            <textarea name="address" class="form-control" rows="3" required
                      placeholder="Город, улица, дом, квартира"><?= e($user['address'] ?? '') ?></textarea>
          </div>

          <div class="form-group">
            <label>Комментарий к заказу</label>
            <textarea name="comment" class="form-control" rows="2"
                      placeholder="Код домофона, время доставки и т.д."></textarea>
          </div>
        </div>

        <div class="checkout-box">
          <h2>Способ оплаты</h2>

          <?php
          $payOptions = [
            'cash'   => ['💵', 'Наличными при получении'],
            'card'   => ['💳', 'Картой при получении'],
            'online' => ['📱', 'Онлайн (имитация)'],
          ];
          foreach ($payOptions as $val => [$icon, $label]):
          ?>
          <label style="display:flex;align-items:center;gap:12px;padding:12px;
                         border:1.5px solid var(--color-border);border-radius:var(--radius-md);
                         margin-bottom:8px;cursor:pointer;transition:border-color .15s"
                 onclick="this.style.borderColor='var(--color-accent)'">
            <input type="radio" name="payment" value="<?= $val ?>"
                   <?= $val==='cash'?'checked':'' ?> style="accent-color:var(--color-accent);">
            <span style="font-size:1.3rem;"><?= $icon ?></span>
            <span style="font-weight:600;font-size:.95rem;"><?= $label ?></span>
          </label>
          <?php endforeach; ?>
        </div>

      </form>

      <!-- ── ИТОГО ───────────────────────────────────────────── -->
      <div>
        <div class="checkout-box" style="position:sticky;top:80px;">
          <h2>Ваш заказ</h2>

          <!-- Список товаров (заполняется JS) -->
          <div id="orderLines">
            <p style="color:var(--color-muted);text-align:center;padding:20px 0;">
              Загрузка корзины...
            </p>
          </div>

          <div style="padding-top:16px;border-top:2px solid var(--color-border);margin-top:8px;">
            <div style="display:flex;justify-content:space-between;margin-bottom:8px;font-size:.9rem;">
              <span style="color:var(--color-muted)">Товары:</span>
              <span id="subtotal">0 ₽</span>
            </div>
            <div style="display:flex;justify-content:space-between;margin-bottom:8px;font-size:.9rem;">
              <span style="color:var(--color-muted)">Доставка:</span>
              <span style="color:var(--color-success)">Бесплатно</span>
            </div>
            <div style="display:flex;justify-content:space-between;font-size:1.2rem;
                        font-weight:800;margin-top:12px;">
              <span>Итого:</span>
              <span id="totalPrice">0 ₽</span>
            </div>
          </div>

          <button type="submit" form="orderForm"
                  id="submitBtn"
                  class="btn btn--primary btn--block btn--lg"
                  style="margin-top:20px;"
                  onclick="prepareSubmit()">
            Подтвердить заказ →
          </button>

          <p style="font-size:.75rem;color:var(--color-muted);text-align:center;margin-top:12px;line-height:1.5;">
            Нажимая кнопку, вы соглашаетесь с условиями<br>доставки и возврата
          </p>
        </div>
      </div>

    </div><!-- /.checkout-layout -->
  </div><!-- /#checkoutContent -->

</div><!-- /.section -->
</main>

<footer class="footer"><p><strong>STEPUP</strong> &copy; <?= date('Y') ?></p></footer>

<script src="assets/js/cart.js"></script>
<script>
  /**
   * Форматирует число как "8 990 ₽"
   */
  function fmt(n) {
    return new Intl.NumberFormat('ru-RU').format(Math.round(n)) + ' ₽';
  }

  /**
   * Отрисовывает список товаров из корзины в блоке "Ваш заказ"
   */
  function renderOrderLines() {
    const items    = cartDrawer.getItems();
    const linesEl  = document.getElementById('orderLines');
    const emptyMsg = document.getElementById('emptyCartMsg');
    const content  = document.getElementById('checkoutContent');

    if (!items.length) {
      emptyMsg.style.display  = 'block';
      content.style.display   = 'none';
      return;
    }

    let html  = '';
    let total = 0;

    items.forEach(item => {
      const lineTotal = item.price * item.qty;
      total += lineTotal;
      html += `
        <div class="order-line">
          <img class="order-line__img"
               src="assets/img/products/${item.image || 'placeholder.svg'}"
               onerror="this.src='assets/img/products/placeholder.svg'">
          <div class="order-line__info">
            <div class="order-line__name">${item.name}</div>
            <div class="order-line__meta">
              ${item.size ? 'Размер: ' + item.size : '<span style="color:var(--color-warning)">Размер не выбран!</span>'}
              · Кол-во: ${item.qty}
            </div>
          </div>
          <div class="order-line__price">${fmt(lineTotal)}</div>
        </div>`;
    });

    linesEl.innerHTML = html;
    document.getElementById('subtotal').textContent  = fmt(total);
    document.getElementById('totalPrice').textContent = fmt(total);
  }

  /**
   * Перед отправкой формы — кладёт JSON корзины в hidden-поле
   */
  function prepareSubmit() {
    const items = cartDrawer.getItems();
    document.getElementById('cartDataInput').value = JSON.stringify(items);
  }

  // Инициализация при загрузке
  document.addEventListener('DOMContentLoaded', renderOrderLines);
</script>
</body>
</html>
