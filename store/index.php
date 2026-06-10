<?php
/**
 * index.php — Главная страница интернет-магазина.
 *
 * Показывает:
 *  - Hero-баннер
 *  - Новинки (последние 8 активных товаров)
 *  - Блок брендов
 */

session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/helpers.php'; // форматирование, экранирование

// ── Запрос последних 8 товаров ─────────────────────────────────
$stmt = $pdo->query(
    "SELECT p.id, p.name, p.slug, p.price, p.image,
            b.name AS brand_name,
            c.name AS category_name
     FROM   products  p
     JOIN   brands    b ON b.id = p.brand_id
     JOIN   categories c ON c.id = p.category_id
     WHERE  p.is_active = 1
     ORDER  BY p.created_at DESC
     LIMIT  8"
);
$featuredProducts = $stmt->fetchAll();

// ── Запрос всех брендов ────────────────────────────────────────
$brands = $pdo->query("SELECT * FROM brands ORDER BY name")->fetchAll();

// ── Данные для шапки ───────────────────────────────────────────
$cartCount = array_sum(array_column($_SESSION['cart'] ?? [], 'quantity'));
$isLogged  = isset($_SESSION['user_id']);
$userName  = htmlspecialchars($_SESSION['user_name'] ?? '');
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>STEPUP — Магазин кроссовок и обуви</title>
  <meta name="description" content="Интернет-магазин брендовой обуви: Nike, Adidas, Puma и другие.">

  <!-- Шрифты из Google Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<!-- ============================================================
     ШАПКА
     ============================================================ -->
<header class="header">
  <div class="header__inner">

    <!-- Логотип -->
    <a href="index.php" class="header__logo">STEP<span>UP</span></a>

    <!-- Основная навигация -->
    <nav class="header__nav" id="mainNav">
      <a href="index.php" class="active">Главная</a>
      <a href="catalog.php">Каталог</a>
      <?php if ($isLogged): ?>
        <a href="account.php">Мой кабинет</a>
        <?php if (in_array($_SESSION['role_id'], [2, 3, 4])): ?>
          <a href="admin.php">Панель управления</a>
        <?php endif; ?>
      <?php else: ?>
        <a href="login.php">Войти</a>
        <a href="login.php?tab=register">Регистрация</a>
      <?php endif; ?>
    </nav>

    <!-- Кнопки справа -->
    <div class="header__actions">

      <!-- Корзина -->
      <button class="cart-btn" onclick="cartDrawer.open()" aria-label="Корзина">
        🛒 Корзина
        <span class="cart-badge" id="cartBadge"
              style="display:<?= $cartCount > 0 ? 'flex' : 'none' ?>">
          <?= $cartCount ?>
        </span>
      </button>

      <?php if ($isLogged): ?>
        <span style="color: rgba(255,255,255,.65); font-size:.85rem;">
          <?= $userName ?>
        </span>
        <a href="auth.php" id="logoutForm" style="display:none"></a>
        <button
          onclick="document.getElementById('logoutForm').href='auth.php';
                   fetch('auth.php',{method:'POST',body:new URLSearchParams({action:'logout'})})
                   .then(()=>location.href='index.php')"
          style="background:none;border:none;color:rgba(255,255,255,.5);
                 cursor:pointer;font-size:.8rem;transition:color .2s"
          onmouseover="this.style.color='#fff'"
          onmouseout="this.style.color='rgba(255,255,255,.5)'"
        >Выйти</button>
      <?php endif; ?>

      <!-- Гамбургер для мобильных -->
      <button class="burger" id="burgerBtn" aria-label="Меню" onclick="toggleMobileMenu()">
        <span></span><span></span><span></span>
      </button>

    </div>
  </div>
</header>

<!-- ============================================================
     HERO-БАННЕР
     ============================================================ -->
<section class="hero">
  <div class="hero__inner">
    <div class="hero__tag">🔥 Новая коллекция 2026</div>
    <h1>Твой<br><em>следующий</em><br>шаг</h1>
    <p>Брендовые кроссовки, ботинки и кеды с доставкой по всей России</p>
    <a href="catalog.php" class="hero__cta">
      Смотреть каталог →
    </a>
  </div>
</section>

<!-- ============================================================
     НОВИНКИ
     ============================================================ -->
<main>
  <section class="section">
    <div class="section__header">
      <h2 class="section__title">Новинки</h2>
      <a href="catalog.php" class="btn btn--secondary btn--sm">Весь каталог →</a>
    </div>

    <?php if (empty($featuredProducts)): ?>
      <p class="text-muted text-center">Товары скоро появятся.</p>
    <?php else: ?>
      <div class="products-grid">
        <?php foreach ($featuredProducts as $i => $p): ?>
          <article class="product-card animate-in" style="animation-delay: <?= $i * 0.07 ?>s">

            <a href="product.php?slug=<?= htmlspecialchars($p['slug']) ?>">
              <div class="product-card__img-wrap">
                <img
                  src="assets/img/products/<?= htmlspecialchars($p['image']) ?>"
                  alt="<?= htmlspecialchars($p['name']) ?>"
                  onerror="this.src='assets/img/products/placeholder.svg'"
                  loading="lazy"
                >
              </div>
            </a>

            <div class="product-card__body">
              <div class="product-card__brand"><?= htmlspecialchars($p['brand_name']) ?></div>
              <div class="product-card__name"><?= htmlspecialchars($p['name']) ?></div>
              <div class="product-card__footer">
                <span class="product-card__price"><?= formatPrice($p['price']) ?> ₽</span>
                <button
                  class="btn-add"
                  onclick="quickAdd(<?= (int)$p['id'] ?>, '<?= htmlspecialchars($p['name'], ENT_QUOTES) ?>', <?= (float)$p['price'] ?>, '<?= htmlspecialchars($p['image'], ENT_QUOTES) ?>')"
                >
                  + В корзину
                </button>
              </div>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

  <!-- БРЕНДЫ -->
  <section style="background: var(--color-surface); padding: 48px 16px;">
    <div style="max-width: var(--max-width); margin: 0 auto; text-align: center;">
      <p style="font-size:.78rem;text-transform:uppercase;letter-spacing:.12em;color:var(--color-muted);margin-bottom:24px;">
        Официальные партнёры
      </p>
      <div style="display:flex;flex-wrap:wrap;justify-content:center;gap:32px;align-items:center;">
        <?php foreach ($brands as $brand): ?>
          <a href="catalog.php?brand=<?= htmlspecialchars($brand['slug']) ?>"
             style="font-family:var(--font-display);font-size:1.4rem;letter-spacing:.06em;
                    color:var(--color-muted);transition:color .2s;text-decoration:none;"
             onmouseover="this.style.color='var(--color-dark)'"
             onmouseout="this.style.color='var(--color-muted)'">
            <?= htmlspecialchars($brand['name']) ?>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  </section>
</main>

<!-- ============================================================
     КОРЗИНА (DRAWER-САЙДБАР)
     ============================================================ -->
<div class="cart-drawer" id="cartDrawer" role="dialog" aria-label="Корзина">
  <div class="cart-drawer__overlay" onclick="cartDrawer.close()"></div>
  <div class="cart-drawer__panel">

    <div class="cart-drawer__head">
      <span class="cart-drawer__title">Корзина</span>
      <button class="cart-drawer__close" onclick="cartDrawer.close()" aria-label="Закрыть">✕</button>
    </div>

    <div class="cart-drawer__body" id="cartBody">
      <p class="cart-drawer__empty" id="cartEmpty">Корзина пуста</p>
      <!-- Позиции заполняются через JS -->
    </div>

    <div class="cart-drawer__foot">
      <div class="cart-total">
        <span>Итого:</span>
        <span id="cartTotal">0 ₽</span>
      </div>
      <a href="checkout.php" class="btn btn--primary btn--block btn--lg">
        Оформить заказ
      </a>
    </div>

  </div>
</div>

<!-- ============================================================
     ПОДВАЛ
     ============================================================ -->
<footer class="footer">
  <p><strong>STEPUP</strong> &copy; <?= date('Y') ?> — Все права защищены.</p>
  <p style="margin-top:8px;">Интернет-магазин брендовой обуви</p>
</footer>

<!-- ============================================================
     JavaScript: корзина + UI
     ============================================================ -->
<script src="assets/js/cart.js"></script>
<script>
  // Открытие/закрытие мобильного меню
  function toggleMobileMenu() {
    document.getElementById('mainNav').classList.toggle('open');
  }

  // Быстрое добавление без выбора размера (добавляет размер 0 как "не выбран")
  function quickAdd(id, name, price, image) {
    cartDrawer.addItem({ id, name, price, image, size: null, qty: 1 });
    cartDrawer.open();
  }
</script>

</body>
</html>
