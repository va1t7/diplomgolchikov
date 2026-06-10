<?php
/**
 * catalog.php — Каталог товаров с фильтрами и пагинацией.
 */

session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/helpers.php';

// ── Параметры фильтрации ───────────────────────────────────────
$filterBrand    = trim($_GET['brand']     ?? '');
$filterCategory = trim($_GET['category']  ?? '');
$filterMin      = (float)($_GET['min_price'] ?? 0);
$filterMax      = (float)($_GET['max_price'] ?? 0);
$sortBy         = $_GET['sort'] ?? 'newest';
$page           = max(1, (int)($_GET['page'] ?? 1));
$perPage        = 12;
$offset         = ($page - 1) * $perPage;

$sortMap = [
    'newest'     => 'p.created_at DESC',
    'price_asc'  => 'p.price ASC',
    'price_desc' => 'p.price DESC',
    'name_asc'   => 'p.name ASC',
];
$orderSQL = $sortMap[$sortBy] ?? $sortMap['newest'];

// ── Динамический WHERE ─────────────────────────────────────────
$where  = ['p.is_active = 1'];
$params = [];

if ($filterBrand    !== '') { $where[] = 'b.slug = ?';   $params[] = $filterBrand; }
if ($filterCategory !== '') { $where[] = 'c.slug = ?';   $params[] = $filterCategory; }
if ($filterMin > 0)         { $where[] = 'p.price >= ?'; $params[] = $filterMin; }
if ($filterMax > 0)         { $where[] = 'p.price <= ?'; $params[] = $filterMax; }

$whereSQL = 'WHERE ' . implode(' AND ', $where);

// ── Пагинация: общее количество ────────────────────────────────
$countStmt = $pdo->prepare(
    "SELECT COUNT(*) FROM products p
     JOIN brands b     ON b.id = p.brand_id
     JOIN categories c ON c.id = p.category_id
     $whereSQL"
);
$countStmt->execute($params);
$totalItems = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalItems / $perPage));

// ── Товары текущей страницы ────────────────────────────────────
$stmt = $pdo->prepare(
    "SELECT p.id, p.name, p.slug, p.price, p.image,
            b.name AS brand_name, b.slug AS brand_slug,
            c.name AS cat_name,   c.slug AS cat_slug,
            COALESCE((SELECT SUM(s.quantity) FROM stock s WHERE s.product_id = p.id),0) AS total_stock
     FROM   products p
     JOIN   brands b     ON b.id = p.brand_id
     JOIN   categories c ON c.id = p.category_id
     $whereSQL
     ORDER  BY $orderSQL
     LIMIT  ? OFFSET ?"
);
$stmt->execute([...$params, $perPage, $offset]);
$products = $stmt->fetchAll();

// ── Данные для боковых фильтров ────────────────────────────────
$brands     = $pdo->query("SELECT * FROM brands ORDER BY name")->fetchAll();
$categories = $pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll();
$priceRange = $pdo->query("SELECT MIN(price) min_p, MAX(price) max_p FROM products WHERE is_active=1")->fetch();

$cartCount = array_sum(array_column($_SESSION['cart'] ?? [], 'quantity'));
$isLogged  = isset($_SESSION['user_id']);

// Текущие фильтры для сохранения в ссылках пагинации
$qFilters = array_filter([
    'brand'     => $filterBrand     ?: null,
    'category'  => $filterCategory  ?: null,
    'min_price' => $filterMin > 0 ? $filterMin : null,
    'max_price' => $filterMax > 0 ? $filterMax : null,
    'sort'      => $sortBy !== 'newest' ? $sortBy : null,
]);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Каталог — STEPUP</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/style.css">
  <style>
    .catalog-layout{display:grid;gap:28px}
    @media(min-width:768px){.catalog-layout{grid-template-columns:230px 1fr}}
    .f-label{display:flex;align-items:center;gap:8px;padding:5px 0;font-size:.88rem;cursor:pointer;transition:color .15s}
    .f-label:hover{color:var(--color-accent)}
    .f-label input{accent-color:var(--color-accent)}
  </style>
</head>
<body>

<header class="header">
  <div class="header__inner">
    <a href="index.php" class="header__logo">STEP<span>UP</span></a>
    <nav class="header__nav" id="mainNav">
      <a href="index.php">Главная</a>
      <a href="catalog.php" class="active">Каталог</a>
      <?php if ($isLogged): ?>
        <a href="account.php">Кабинет</a>
        <?php if (in_array($_SESSION['role_id'] ?? 0, [2,3,4])): ?>
          <a href="admin.php">Управление</a>
        <?php endif; ?>
      <?php else: ?>
        <a href="login.php">Войти</a>
        <a href="login.php?tab=register">Регистрация</a>
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

<div style="background:var(--color-surface);border-bottom:1px solid var(--color-border);padding:10px 16px;">
  <div style="max-width:var(--max-width);margin:0 auto;font-size:.82rem;color:var(--color-muted);">
    <a href="index.php" style="color:var(--color-muted)">Главная</a> ›
    <span style="color:var(--color-dark)">Каталог</span>
  </div>
</div>

<main>
<div class="section">
<div class="catalog-layout">

  <!-- ────── ФИЛЬТРЫ ────── -->
  <aside>
    <form method="get" action="catalog.php">
      <div class="filters">

        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
          <strong style="font-size:.95rem;">Фильтры</strong>
          <a href="catalog.php" style="font-size:.78rem;color:var(--color-muted);">✕ Сбросить</a>
        </div>

        <!-- Бренд -->
        <div class="filter-group">
          <span class="filter-group__label">Бренд</span>
          <label class="f-label">
            <input type="radio" name="brand" value=""
                   <?= $filterBrand==='' ? 'checked' : '' ?> onchange="this.form.submit()"> Все
          </label>
          <?php foreach ($brands as $b): ?>
          <label class="f-label">
            <input type="radio" name="brand" value="<?= e($b['slug']) ?>"
                   <?= $filterBrand===$b['slug'] ? 'checked' : '' ?> onchange="this.form.submit()">
            <?= e($b['name']) ?>
          </label>
          <?php endforeach; ?>
        </div>

        <!-- Категория -->
        <div class="filter-group">
          <span class="filter-group__label">Тип обуви</span>
          <label class="f-label">
            <input type="radio" name="category" value=""
                   <?= $filterCategory==='' ? 'checked' : '' ?> onchange="this.form.submit()"> Все
          </label>
          <?php foreach ($categories as $c): ?>
          <label class="f-label">
            <input type="radio" name="category" value="<?= e($c['slug']) ?>"
                   <?= $filterCategory===$c['slug'] ? 'checked' : '' ?> onchange="this.form.submit()">
            <?= e($c['name']) ?>
          </label>
          <?php endforeach; ?>
        </div>

        <!-- Цена -->
        <div class="filter-group">
          <span class="filter-group__label">Цена, ₽</span>
          <div style="display:flex;gap:8px;align-items:center;">
            <input type="number" name="min_price" class="form-control"
                   placeholder="от <?= (int)$priceRange['min_p'] ?>"
                   value="<?= $filterMin>0 ? (int)$filterMin : '' ?>"
                   min="0" style="width:88px;">
            <span>—</span>
            <input type="number" name="max_price" class="form-control"
                   placeholder="до <?= (int)$priceRange['max_p'] ?>"
                   value="<?= $filterMax>0 ? (int)$filterMax : '' ?>"
                   min="0" style="width:88px;">
          </div>
        </div>

        <!-- Сортировка -->
        <div class="filter-group">
          <span class="filter-group__label">Сортировка</span>
          <select name="sort" class="form-control" onchange="this.form.submit()">
            <option value="newest"     <?= $sortBy==='newest'    ?'selected':'' ?>>Сначала новые</option>
            <option value="price_asc"  <?= $sortBy==='price_asc' ?'selected':'' ?>>Дешевле</option>
            <option value="price_desc" <?= $sortBy==='price_desc'?'selected':'' ?>>Дороже</option>
            <option value="name_asc"   <?= $sortBy==='name_asc'  ?'selected':'' ?>>По названию</option>
          </select>
        </div>

        <button type="submit" class="btn btn--primary btn--block">Применить</button>
      </div>
    </form>
  </aside>

  <!-- ────── ТОВАРЫ ────── -->
  <div>
    <div style="display:flex;justify-content:space-between;align-items:center;
                margin-bottom:24px;flex-wrap:wrap;gap:8px;">
      <h1 style="font-family:var(--font-display);font-size:2rem;
                 letter-spacing:.04em;text-transform:uppercase;">
        <?php
          if ($filterBrand) {
              echo e(array_column($brands,'name','slug')[$filterBrand] ?? $filterBrand);
          } elseif ($filterCategory) {
              echo e(array_column($categories,'name','slug')[$filterCategory] ?? $filterCategory);
          } else {
              echo 'Весь каталог';
          }
        ?>
      </h1>
      <span style="color:var(--color-muted);font-size:.88rem;">Найдено: <?= $totalItems ?></span>
    </div>

    <?php if (empty($products)): ?>
      <div style="text-align:center;padding:60px 0;">
        <div style="font-size:3rem;margin-bottom:12px;">👟</div>
        <p style="color:var(--color-muted);margin-bottom:20px;">По выбранным фильтрам ничего нет</p>
        <a href="catalog.php" class="btn btn--secondary">Сбросить фильтры</a>
      </div>
    <?php else: ?>

      <div class="products-grid">
        <?php foreach ($products as $i => $p): ?>
          <article class="product-card animate-in" style="animation-delay:<?= $i*0.05 ?>s">
            <a href="product.php?slug=<?= e($p['slug']) ?>">
              <div class="product-card__img-wrap">
                <img src="assets/img/products/<?= e($p['image']) ?>"
                     alt="<?= e($p['name']) ?>"
                     onerror="this.src='assets/img/products/placeholder.svg'"
                     loading="lazy">
                <?php if ($p['total_stock'] == 0): ?>
                  <div style="position:absolute;inset:0;background:rgba(255,255,255,.7);
                              display:flex;align-items:center;justify-content:center;
                              font-size:.72rem;font-weight:700;color:var(--color-muted);letter-spacing:.1em">
                    НЕТ В НАЛИЧИИ
                  </div>
                <?php endif; ?>
              </div>
            </a>
            <div class="product-card__body">
              <div class="product-card__brand"><?= e($p['brand_name']) ?></div>
              <a href="product.php?slug=<?= e($p['slug']) ?>" style="color:inherit">
                <div class="product-card__name"><?= e($p['name']) ?></div>
              </a>
              <div class="product-card__footer">
                <span class="product-card__price"><?= formatPrice($p['price']) ?> ₽</span>
                <?php if ($p['total_stock'] > 0): ?>
                  <a href="product.php?slug=<?= e($p['slug']) ?>" class="btn-add">Выбрать →</a>
                <?php endif; ?>
              </div>
            </div>
          </article>
        <?php endforeach; ?>
      </div>

      <!-- Пагинация -->
      <?php if ($totalPages > 1): ?>
        <nav style="display:flex;justify-content:center;gap:8px;margin-top:40px;flex-wrap:wrap;">
          <?php if ($page > 1): ?>
            <a href="?<?= http_build_query(array_merge($qFilters,['page'=>$page-1])) ?>" class="page-btn">← Назад</a>
          <?php endif; ?>
          <?php for ($pg = max(1,$page-2); $pg <= min($totalPages,$page+2); $pg++): ?>
            <a href="?<?= http_build_query(array_merge($qFilters,['page'=>$pg])) ?>"
               class="page-btn <?= $pg===$page?'active':'' ?>"><?= $pg ?></a>
          <?php endfor; ?>
          <?php if ($page < $totalPages): ?>
            <a href="?<?= http_build_query(array_merge($qFilters,['page'=>$page+1])) ?>" class="page-btn">Вперёд →</a>
          <?php endif; ?>
        </nav>
      <?php endif; ?>

    <?php endif; ?>
  </div>

</div><!-- /.catalog-layout -->
</div><!-- /.section -->
</main>

<style>
.page-btn{padding:8px 14px;border-radius:var(--radius-md);background:var(--color-surface);
          border:1px solid var(--color-border);font-weight:600;font-size:.88rem;
          color:var(--color-dark);text-decoration:none;transition:all .15s}
.page-btn:hover{border-color:var(--color-accent);color:var(--color-accent)}
.page-btn.active{background:var(--color-accent);color:#fff;border-color:var(--color-accent)}
</style>

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
