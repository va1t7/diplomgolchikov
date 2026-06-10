<?php
/**
 * account.php — Личный кабинет клиента.
 * Показывает историю заказов и статусы.
 */

session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/helpers.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php?redirect=account');
    exit;
}

$userId = (int)$_SESSION['user_id'];

// ── Обновление профиля ─────────────────────────────────────────
$profileSuccess = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $name    = trim($_POST['name']    ?? '');
    $phone   = trim($_POST['phone']   ?? '');
    $address = trim($_POST['address'] ?? '');

    if (mb_strlen($name) >= 2) {
        $pdo->prepare("UPDATE users SET name=?, phone=?, address=? WHERE id=?")
            ->execute([$name, $phone, $address, $userId]);
        $_SESSION['user_name'] = $name;
        $profileSuccess = true;
    }
}

// ── Загружаем данные пользователя ─────────────────────────────
$userStmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$userStmt->execute([$userId]);
$user = $userStmt->fetch();

// ── История заказов ────────────────────────────────────────────
$ordersStmt = $pdo->prepare(
    "SELECT o.id, o.total_price, o.delivery_addr, o.created_at, o.updated_at,
            s.label AS status_label, s.color AS status_color
     FROM   orders   o
     JOIN   statuses s ON s.id = o.status_id
     WHERE  o.user_id = ?
     ORDER  BY o.created_at DESC"
);
$ordersStmt->execute([$userId]);
$orders = $ordersStmt->fetchAll();

// ── Активная вкладка ───────────────────────────────────────────
$tab = $_GET['tab'] ?? 'orders';

$cartCount = array_sum(array_column($_SESSION['cart'] ?? [], 'quantity'));
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Личный кабинет — STEPUP</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/style.css">
  <style>
    .account-layout {
      display: grid;
      gap: 28px;
    }
    @media(min-width:768px){
      .account-layout { grid-template-columns: 220px 1fr; }
    }
    .account-nav a {
      display: block;
      padding: 10px 16px;
      border-radius: var(--radius-md);
      font-weight: 600;
      font-size: .9rem;
      color: var(--color-muted);
      transition: all .15s;
      margin-bottom: 4px;
    }
    .account-nav a:hover,
    .account-nav a.active {
      background: var(--color-accent-lt);
      color: var(--color-accent);
    }

    /* Карточка заказа */
    .order-card {
      background: var(--color-bg);
      border: 1px solid var(--color-border);
      border-radius: var(--radius-lg);
      margin-bottom: 16px;
      overflow: hidden;
      transition: box-shadow .2s;
    }
    .order-card:hover { box-shadow: var(--shadow-md); }
    .order-card__head {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 16px 20px;
      border-bottom: 1px solid var(--color-border);
      flex-wrap: wrap;
      gap: 8px;
    }
    .order-card__body { padding: 16px 20px; }
    .order-card__items {
      display: flex;
      gap: 8px;
      flex-wrap: wrap;
      margin-bottom: 12px;
    }

    /* Трекер статусов */
    .status-track {
      display: flex;
      align-items: center;
      gap: 0;
      margin-top: 8px;
    }
    .status-step {
      display: flex;
      flex-direction: column;
      align-items: center;
      flex: 1;
      position: relative;
    }
    .status-step::before {
      content: '';
      position: absolute;
      top: 12px;
      left: -50%;
      right: 50%;
      height: 2px;
      background: var(--color-border);
    }
    .status-step:first-child::before { display: none; }
    .status-step.done::before { background: var(--color-accent); }

    .status-dot {
      width: 24px; height: 24px;
      border-radius: 50%;
      background: var(--color-border);
      border: 2px solid var(--color-border);
      z-index: 1;
      position: relative;
      display: flex; align-items: center; justify-content: center;
      font-size: .65rem; font-weight: 700;
    }
    .status-step.done .status-dot { background: var(--color-accent); border-color: var(--color-accent); color: #fff; }
    .status-step.current .status-dot { background: #fff; border-color: var(--color-accent); box-shadow: 0 0 0 3px var(--color-accent-lt); }

    .status-step span { font-size: .7rem; color: var(--color-muted); margin-top: 4px; text-align: center; }
    .status-step.done span, .status-step.current span { color: var(--color-dark); font-weight: 600; }
  </style>
</head>
<body>

<header class="header">
  <div class="header__inner">
    <a href="index.php" class="header__logo">STEP<span>UP</span></a>
    <nav class="header__nav" id="mainNav">
      <a href="index.php">Главная</a>
      <a href="catalog.php">Каталог</a>
      <a href="account.php" class="active">Кабинет</a>
    </nav>
    <div class="header__actions">
      <button class="cart-btn" onclick="cartDrawer.open()">
        🛒 Корзина
        <span class="cart-badge" id="cartBadge"
              style="display:<?= $cartCount>0?'flex':'none' ?>"><?= $cartCount ?></span>
      </button>
      <form method="post" action="auth.php" style="display:inline">
        <input type="hidden" name="action" value="logout">
        <button type="submit" class="btn btn--secondary btn--sm">Выйти</button>
      </form>
    </div>
  </div>
</header>

<main>
<div class="section">

  <!-- Приветствие -->
  <div style="margin-bottom:28px;">
    <h1 style="font-family:var(--font-display);font-size:clamp(1.6rem,3vw,2.4rem);
               letter-spacing:.04em;text-transform:uppercase;">
      Привет, <?= e($user['name']) ?>!
    </h1>
    <p style="color:var(--color-muted);font-size:.88rem;margin-top:4px;">
      <?= e($user['email']) ?> · Клиент с <?= date('d.m.Y', strtotime($user['created_at'])) ?>
    </p>
  </div>

  <div class="account-layout">

    <!-- ── БОКОВОЕ МЕНЮ ─────────────────────────────────────── -->
    <aside>
      <div class="account-nav">
        <a href="?tab=orders"  class="<?= $tab==='orders'  ? 'active' : '' ?>">📦 Мои заказы</a>
        <a href="?tab=profile" class="<?= $tab==='profile' ? 'active' : '' ?>">👤 Профиль</a>
        <a href="catalog.php" style="color:var(--color-accent);">🛍 В каталог</a>
      </div>

      <!-- Статистика -->
      <div style="background:var(--color-surface);border-radius:var(--radius-lg);
                  padding:16px;margin-top:16px;">
        <div style="font-size:.72rem;text-transform:uppercase;letter-spacing:.08em;
                    color:var(--color-muted);margin-bottom:12px;">Статистика</div>
        <div style="font-size:1.8rem;font-weight:800;font-family:var(--font-display);letter-spacing:.03em;">
          <?= count($orders) ?>
        </div>
        <div style="font-size:.8rem;color:var(--color-muted);">всего заказов</div>

        <?php
          $totalSpent = array_sum(array_column($orders, 'total_price'));
          $delivered  = count(array_filter($orders, fn($o) => str_contains(strtolower($o['status_label']), 'доставл')));
        ?>
        <div style="margin-top:12px;font-size:1.4rem;font-weight:800;font-family:var(--font-display);">
          <?= formatPrice($totalSpent) ?> ₽
        </div>
        <div style="font-size:.8rem;color:var(--color-muted);">потрачено всего</div>
      </div>
    </aside>

    <!-- ── ОСНОВНОЙ КОНТЕНТ ──────────────────────────────────── -->
    <div>

      <?php if ($tab === 'orders'): ?>
      <!-- ИСТОРИЯ ЗАКАЗОВ -->

        <?php if (empty($orders)): ?>
          <div style="text-align:center;padding:60px 0;">
            <div style="font-size:3rem;margin-bottom:12px;">📦</div>
            <p style="color:var(--color-muted);margin-bottom:20px;">Заказов пока нет</p>
            <a href="catalog.php" class="btn btn--primary">Перейти в каталог</a>
          </div>
        <?php else: ?>

          <?php foreach ($orders as $order):
            // Определяем текущий шаг трекера
            $statusSteps = [
              1 => ['Принят', '✓'],
              2 => ['В пути', '🚚'],
              3 => ['Доставлен', '✓'],
              4 => ['Отменён', '✕'],
            ];
            $currentStatusId = null;

            // Получаем status_id через повторный запрос
            $sid = $pdo->prepare("SELECT status_id FROM orders WHERE id = ?");
            $sid->execute([$order['id']]);
            $currentStatusId = (int)$sid->fetchColumn();
          ?>

          <div class="order-card">
            <div class="order-card__head">
              <div>
                <strong style="font-size:1rem;">Заказ #<?= (int)$order['id'] ?></strong>
                <div style="font-size:.78rem;color:var(--color-muted);margin-top:2px;">
                  <?= date('d.m.Y в H:i', strtotime($order['created_at'])) ?>
                </div>
              </div>
              <div style="text-align:right;">
                <?= statusBadge($order['status_label'], $order['status_color']) ?>
                <div style="font-weight:700;margin-top:6px;"><?= formatPrice($order['total_price']) ?> ₽</div>
              </div>
            </div>

            <div class="order-card__body">

              <!-- Миниатюры товаров -->
              <?php
                $orderItemsStmt = $pdo->prepare(
                  "SELECT oi.size, oi.quantity, oi.unit_price, p.name, p.image, b.name AS brand
                   FROM order_items oi
                   JOIN products p ON p.id = oi.product_id
                   JOIN brands   b ON b.id = p.brand_id
                   WHERE oi.order_id = ?"
                );
                $orderItemsStmt->execute([$order['id']]);
                $oItems = $orderItemsStmt->fetchAll();
              ?>
              <div class="order-card__items">
                <?php foreach ($oItems as $oi): ?>
                  <div style="display:flex;align-items:center;gap:8px;
                              background:var(--color-surface);border-radius:var(--radius-md);
                              padding:8px 10px;font-size:.82rem;">
                    <img src="assets/img/products/<?= e($oi['image']) ?>"
                         onerror="this.src='assets/img/products/placeholder.svg'"
                         style="width:36px;height:36px;object-fit:contain;">
                    <div>
                      <div style="font-weight:600;"><?= e($oi['brand']) ?> <?= e($oi['name']) ?></div>
                      <div style="color:var(--color-muted);">р. <?= (float)$oi['size'] ?> · <?= (int)$oi['quantity'] ?> шт.</div>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>

              <!-- Трекер статуса (только для не-отменённых) -->
              <?php if ($currentStatusId !== 4): ?>
              <div class="status-track">
                <?php
                  $steps = [1=>'Принят', 2=>'В пути', 3=>'Доставлен'];
                  foreach ($steps as $sid => $sLabel):
                    $isDone    = $currentStatusId > $sid;
                    $isCurrent = $currentStatusId === $sid;
                    $cls = $isDone ? 'done' : ($isCurrent ? 'current' : '');
                ?>
                  <div class="status-step <?= $cls ?>">
                    <div class="status-dot"><?= $isDone ? '✓' : '' ?></div>
                    <span><?= $sLabel ?></span>
                  </div>
                <?php endforeach; ?>
              </div>
              <?php else: ?>
                <div class="alert alert--error" style="margin:0;padding:8px 12px;font-size:.82rem;">
                  Заказ отменён
                </div>
              <?php endif; ?>

              <!-- Адрес -->
              <div style="margin-top:10px;font-size:.8rem;color:var(--color-muted);">
                📍 <?= e(mb_substr($order['delivery_addr'], 0, 80)) ?><?= mb_strlen($order['delivery_addr'])>80 ? '...' : '' ?>
              </div>

            </div>
          </div>

          <?php endforeach; ?>
        <?php endif; ?>

      <?php elseif ($tab === 'profile'): ?>
      <!-- РЕДАКТИРОВАНИЕ ПРОФИЛЯ -->

        <?php if ($profileSuccess): ?>
          <div class="alert alert--success">Профиль успешно обновлён!</div>
        <?php endif; ?>

        <div style="background:var(--color-bg);border:1px solid var(--color-border);
                    border-radius:var(--radius-xl);padding:28px;max-width:480px;">
          <h2 style="font-family:var(--font-display);font-size:1.4rem;letter-spacing:.04em;
                     text-transform:uppercase;margin-bottom:24px;">Мои данные</h2>

          <form method="post">
            <input type="hidden" name="update_profile" value="1">

            <div class="form-group">
              <label>Имя *</label>
              <input type="text" name="name" class="form-control"
                     value="<?= e($user['name']) ?>" required minlength="2">
            </div>

            <div class="form-group">
              <label>Email (нельзя изменить)</label>
              <input type="email" class="form-control" value="<?= e($user['email']) ?>" disabled
                     style="background:var(--color-surface);color:var(--color-muted);cursor:not-allowed;">
            </div>

            <div class="form-group">
              <label>Телефон</label>
              <input type="tel" name="phone" class="form-control"
                     value="<?= e($user['phone'] ?? '') ?>" placeholder="+7 (999) 000-00-00">
            </div>

            <div class="form-group">
              <label>Адрес доставки по умолчанию</label>
              <textarea name="address" class="form-control" rows="3"
                        placeholder="Город, улица, дом, квартира"><?= e($user['address'] ?? '') ?></textarea>
            </div>

            <button type="submit" class="btn btn--primary">💾 Сохранить</button>
          </form>
        </div>

      <?php endif; ?>

    </div><!-- /.основной контент -->

  </div><!-- /.account-layout -->
</div><!-- /.section -->
</main>

<!-- Корзина-drawer -->
<div class="cart-drawer" id="cartDrawer">
  <div class="cart-drawer__overlay" onclick="cartDrawer.close()"></div>
  <div class="cart-drawer__panel">
    <div class="cart-drawer__head">
      <span class="cart-drawer__title">Корзина</span>
      <button class="cart-drawer__close" onclick="cartDrawer.close()">✕</button>
    </div>
    <div class="cart-drawer__body" id="cartBody">
      <p class="cart-drawer__empty" id="cartEmpty">Корзина пуста</p>
    </div>
    <div class="cart-drawer__foot">
      <div class="cart-total"><span>Итого:</span><span id="cartTotal">0 ₽</span></div>
      <a href="checkout.php" class="btn btn--primary btn--block btn--lg">Оформить заказ</a>
    </div>
  </div>
</div>

<footer class="footer"><p><strong>STEPUP</strong> &copy; <?= date('Y') ?></p></footer>
<script src="assets/js/cart.js"></script>
</body>
</html>
