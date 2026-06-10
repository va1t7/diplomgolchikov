<?php
/**
 * product.php — Страница отдельного товара.
 * GET: ?slug=nike-air-max-270
 */

session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/helpers.php';

$slug = trim($_GET['slug'] ?? '');
if ($slug === '') { header('Location: catalog.php'); exit; }

// ── Загружаем товар ────────────────────────────────────────────
$stmt = $pdo->prepare(
    "SELECT p.*, b.name AS brand_name, b.slug AS brand_slug,
            c.name AS cat_name, c.slug AS cat_slug
     FROM   products p
     JOIN   brands b     ON b.id = p.brand_id
     JOIN   categories c ON c.id = p.category_id
     WHERE  p.slug = ? AND p.is_active = 1
     LIMIT  1"
);
$stmt->execute([$slug]);
$product = $stmt->fetch();

if (!$product) {
    http_response_code(404);
    echo '<h2 style="text-align:center;margin-top:60px">Товар не найден</h2>';
    echo '<p style="text-align:center"><a href="catalog.php">← Вернуться в каталог</a></p>';
    exit;
}

// ── Остатки по размерам (только > 0) ──────────────────────────
$stockStmt = $pdo->prepare(
    "SELECT size, quantity FROM stock WHERE product_id = ? ORDER BY size"
);
$stockStmt->execute([$product['id']]);
$stock = $stockStmt->fetchAll(PDO::FETCH_KEY_PAIR); // [42 => 5, 43 => 3 ...]

// ── Похожие товары (тот же бренд) ─────────────────────────────
$relStmt = $pdo->prepare(
    "SELECT p.id, p.name, p.slug, p.price, p.image, b.name AS brand_name
     FROM   products p JOIN brands b ON b.id = p.brand_id
     WHERE  p.brand_id = ? AND p.id != ? AND p.is_active = 1
     ORDER  BY RAND() LIMIT 4"
);
$relStmt->execute([$product['brand_id'], $product['id']]);
$related = $relStmt->fetchAll();

$cartCount = array_sum(array_column($_SESSION['cart'] ?? [], 'quantity'));
$isLogged  = isset($_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($product['name']) ?> — STEPUP</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/style.css">
  <style>
    /* ── Страница товара ─────────────────────────────────────── */
    .product-page {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 52px;
      align-items: start;
    }
    @media(max-width:767px){ .product-page{grid-template-columns:1fr;gap:24px} }

    .product-photo {
      background: var(--color-surface);
      border-radius: var(--radius-xl);
      aspect-ratio: 1;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 40px;
      overflow: hidden;
    }
    .product-photo img {
      width: 100%;
      height: 100%;
      object-fit: contain;
      transition: transform .4s ease;
    }
    .product-photo:hover img { transform: scale(1.06); }

    /* Сетка размеров */
    .size-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(58px, 1fr));
      gap: 8px;
    }
    .size-btn {
      padding: 10px 4px;
      border: 1.5px solid var(--color-border);
      border-radius: var(--radius-md);
      background: var(--color-bg);
      font-family: var(--font-body);
      font-size: .9rem;
      font-weight: 600;
      cursor: pointer;
      text-align: center;
      transition: all .15s ease;
    }
    .size-btn:hover { border-color: var(--color-accent); color: var(--color-accent); }
    .size-btn.active {
      background: var(--color-accent);
      border-color: var(--color-accent);
      color: #fff;
    }
    .size-btn.low-stock { border-color: var(--color-warning); }
    .size-btn.out-stock {
      opacity: .4;
      cursor: not-allowed;
      text-decoration: line-through;
    }

    /* Инфо-блок */
    .product-features {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 12px;
      margin-top: 24px;
      padding-top: 24px;
      border-top: 1px solid var(--color-border);
    }
    .product-feature { font-size: .85rem; line-height: 1.5; }
    .product-feature strong { display: block; color: var(--color-dark); }
    .product-feature span   { color: var(--color-muted); }
  </style>
</head>
<body>

<header class="header">
  <div class="header__inner">
    <a href="index.php" class="header__logo">STEP<span>UP</span></a>
    <nav class="header__nav" id="mainNav">
      <a href="index.php">Главная</a>
      <a href="catalog.php">Каталог</a>
      <?php if ($isLogged): ?>
        <a href="account.php">Кабинет</a>
      <?php else: ?>
        <a href="login.php">Войти</a>
      <?php endif; ?>
    </nav>
    <div class="header__actions">
      <button class="cart-btn" onclick="cartDrawer.open()">
        🛒 Корзина
        <span class="cart-badge" id="cartBadge"
              style="display:<?= $cartCount>0?'flex':'none' ?>"><?= $cartCount ?></span>
      </button>
      <button class="burger" onclick="document.getElementById('mainNav').classList.toggle('open')">
        <span></span><span></span><span></span>
      </button>
    </div>
  </div>
</header>

<!-- Хлебные крошки -->
<div style="background:var(--color-surface);border-bottom:1px solid var(--color-border);padding:10px 16px;">
  <div style="max-width:var(--max-width);margin:0 auto;font-size:.82rem;color:var(--color-muted);">
    <a href="index.php"   style="color:var(--color-muted)">Главная</a> ›
    <a href="catalog.php" style="color:var(--color-muted)">Каталог</a> ›
    <a href="catalog.php?brand=<?= e($product['brand_slug']) ?>"
       style="color:var(--color-muted)"><?= e($product['brand_name']) ?></a> ›
    <span style="color:var(--color-dark)"><?= e($product['name']) ?></span>
  </div>
</div>

<main>
<div class="section">

  <div class="product-page">

    <!-- ФОТО -->
    <div>
      <div class="product-photo">
        <img src="assets/img/products/<?= e($product['image']) ?>"
             alt="<?= e($product['name']) ?>"
             onerror="this.src='assets/img/products/placeholder.svg'">
      </div>
    </div>

    <!-- ИНФОРМАЦИЯ -->
    <div>
      <div style="font-size:.75rem;font-weight:700;text-transform:uppercase;
                  letter-spacing:.12em;color:var(--color-accent);margin-bottom:8px;">
        <?= e($product['brand_name']) ?> · <?= e($product['cat_name']) ?>
      </div>

      <h1 style="font-family:var(--font-display);font-size:clamp(1.8rem,4vw,2.8rem);
                 letter-spacing:.03em;text-transform:uppercase;line-height:1;margin-bottom:16px;">
        <?= e($product['name']) ?>
      </h1>

      <!-- Цена -->
      <div style="font-size:2.2rem;font-weight:800;color:var(--color-dark);margin-bottom:24px;">
        <?= formatPrice($product['price']) ?> ₽
      </div>

      <!-- Описание -->
      <?php if (!empty($product['description'])): ?>
        <p style="color:var(--color-muted);line-height:1.75;margin-bottom:28px;font-size:.95rem;">
          <?= nl2br(e($product['description'])) ?>
        </p>
      <?php endif; ?>

      <!-- Выбор размера -->
      <div style="margin-bottom:24px;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
          <strong style="font-size:.9rem;">Размер (EU):</strong>
          <span id="sizeLabel" style="color:var(--color-accent);font-weight:700;font-size:.9rem;"></span>
        </div>

        <?php if (empty($stock)): ?>
          <div class="alert alert--error" style="margin:0;">Нет в наличии</div>
        <?php else: ?>
          <div class="size-grid">
            <?php foreach ($stock as $sz => $qty): ?>
              <button type="button"
                      class="size-btn <?= $qty == 0 ? 'out-stock' : ($qty <= 2 ? 'low-stock' : '') ?>"
                      data-size="<?= (float)$sz ?>"
                      data-qty="<?= (int)$qty ?>"
                      <?= $qty == 0 ? 'disabled' : '' ?>
                      onclick="pickSize(this)">
                <?= (float)$sz ?>
              </button>
            <?php endforeach; ?>
          </div>
          <div id="stockHint" style="font-size:.8rem;color:var(--color-muted);margin-top:8px;min-height:18px;"></div>
        <?php endif; ?>
      </div>

      <!-- Кнопка добавления в корзину -->
      <?php if (!empty($stock)): ?>
        <button id="btnAdd" class="btn btn--primary btn--lg btn--block" disabled onclick="addToCart()">
          Выберите размер
        </button>
      <?php else: ?>
        <button class="btn btn--block btn--lg" disabled
                style="background:var(--color-surface);color:var(--color-muted);cursor:default;border:1px solid var(--color-border);">
          Нет в наличии
        </button>
      <?php endif; ?>

      <!-- Характеристики -->
      <div class="product-features">
        <div class="product-feature"><strong>🚚 Доставка</strong><span>1–3 рабочих дня</span></div>
        <div class="product-feature"><strong>↩️ Возврат</strong><span>14 дней бесплатно</span></div>
        <div class="product-feature"><strong>✅ Гарантия</strong><span>Оригинальный товар</span></div>
        <div class="product-feature"><strong>💳 Оплата</strong><span>При получении или онлайн</span></div>
      </div>

    </div>
  </div><!-- /.product-page -->

  <!-- Похожие товары -->
  <?php if (!empty($related)): ?>
    <div style="margin-top:64px;">
      <h2 style="font-family:var(--font-display);font-size:1.8rem;letter-spacing:.04em;
                 text-transform:uppercase;margin-bottom:24px;">
        Ещё от <?= e($product['brand_name']) ?>
      </h2>
      <div class="products-grid">
        <?php foreach ($related as $r): ?>
          <article class="product-card animate-in">
            <a href="product.php?slug=<?= e($r['slug']) ?>">
              <div class="product-card__img-wrap">
                <img src="assets/img/products/<?= e($r['image']) ?>"
                     alt="<?= e($r['name']) ?>"
                     onerror="this.src='assets/img/products/placeholder.svg'" loading="lazy">
              </div>
            </a>
            <div class="product-card__body">
              <div class="product-card__brand"><?= e($r['brand_name']) ?></div>
              <a href="product.php?slug=<?= e($r['slug']) ?>" style="color:inherit">
                <div class="product-card__name"><?= e($r['name']) ?></div>
              </a>
              <div class="product-card__footer">
                <span class="product-card__price"><?= formatPrice($r['price']) ?> ₽</span>
                <a href="product.php?slug=<?= e($r['slug']) ?>" class="btn-add">Смотреть</a>
              </div>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>

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
<script>
  // Данные товара из PHP
  const PRODUCT = {
    id:    <?= (int)$product['id'] ?>,
    name:  <?= json_encode($product['name'], JSON_UNESCAPED_UNICODE) ?>,
    price: <?= (float)$product['price'] ?>,
    image: <?= json_encode($product['image']) ?>,
  };

  let selectedSize = null;

  /** Выбор размера */
  function pickSize(btn) {
    document.querySelectorAll('.size-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    selectedSize = parseFloat(btn.dataset.size);
    const qty    = parseInt(btn.dataset.qty);

    document.getElementById('sizeLabel').textContent = 'Размер ' + selectedSize;

    const hint = document.getElementById('stockHint');
    if (qty <= 2) {
      hint.innerHTML = `<span style="color:var(--color-warning)">⚠️ Осталось: ${qty} пары — спешите!</span>`;
    } else {
      hint.innerHTML = `<span style="color:var(--color-success)">✓ Есть в наличии (${qty} шт.)</span>`;
    }

    const btn2 = document.getElementById('btnAdd');
    btn2.disabled    = false;
    btn2.textContent = '🛒 Добавить в корзину';
  }

  /** Добавление в корзину */
  function addToCart() {
    if (!selectedSize) return;

    cartDrawer.addItem({
      id:    PRODUCT.id,
      name:  PRODUCT.name,
      price: PRODUCT.price,
      image: PRODUCT.image,
      size:  selectedSize,
      qty:   1,
    });

    // Визуальная обратная связь
    const btn = document.getElementById('btnAdd');
    const orig = btn.textContent;
    btn.textContent       = '✓ Добавлено в корзину!';
    btn.style.background  = 'var(--color-success)';
    btn.disabled          = true;

    setTimeout(() => {
      btn.textContent      = orig;
      btn.style.background = '';
      btn.disabled         = false;
    }, 2000);

    cartDrawer.open();
  }
</script>
</body>
</html>
