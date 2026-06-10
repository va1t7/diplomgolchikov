<?php
/**
 * admin.php — Универсальная панель управления.
 *
 * Роль пользователя определяет, какая вкладка отображается:
 *   role_id = 2 (Контент-менеджер) → управление товарами и складом
 *   role_id = 3 (Логист/Курьер)    → список заказов, смена статуса
 *   role_id = 4 (HR-Аналитик)      → отчёты по продажам и сотрудникам
 */

session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/helpers.php';

// Доступ только для персонала (роли 2, 3, 4)
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role_id'], [2, 3, 4])) {
    header('Location: login.php');
    exit;
}

$roleId   = (int) $_SESSION['role_id'];
$userName = e($_SESSION['user_name'] ?? '');

// ── ОБРАБОТКА POST-ЗАПРОСОВ ────────────────────────────────────

// Контент-менеджер: сохранение товара (добавление / редактирование)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $roleId === 2) {

    $action    = $_POST['action'] ?? '';
    $productId = (int) ($_POST['product_id'] ?? 0);

    // --- Добавить или обновить товар ---
    if ($action === 'save_product') {
        $name       = trim($_POST['name']        ?? '');
        $brandId    = (int)  $_POST['brand_id'];
        $categoryId = (int)  $_POST['category_id'];
        $price      = (float)$_POST['price'];
        $desc       = trim($_POST['description'] ?? '');
        $isActive   = isset($_POST['is_active']) ? 1 : 0;

        // Автогенерация slug
        $slug = mb_strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', transliterate($name))));
        $slug = rtrim($slug, '-') ?: 'product-' . time();

        // Обработка загруженного изображения
        $imageName = $_POST['current_image'] ?? 'default.jpg';
        if (!empty($_FILES['image']['name'])) {
            $ext       = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            $allowed   = ['jpg','jpeg','png','webp'];
            if (in_array($ext, $allowed)) {
                $imageName = uniqid('product_') . '.' . $ext;
                move_uploaded_file($_FILES['image']['tmp_name'], "assets/img/products/$imageName");
            }
        }

        if ($productId > 0) {
            // Обновление существующего товара
            $stmt = $pdo->prepare(
                "UPDATE products
                 SET brand_id=?, category_id=?, name=?, slug=?,
                     description=?, price=?, image=?, is_active=?
                 WHERE id=?"
            );
            $stmt->execute([$brandId, $categoryId, $name, $slug, $desc, $price, $imageName, $isActive, $productId]);
        } else {
            // Вставка нового товара
            $stmt = $pdo->prepare(
                "INSERT INTO products (brand_id, category_id, name, slug, description, price, image, is_active)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([$brandId, $categoryId, $name, $slug, $desc, $price, $imageName, $isActive]);
            $productId = (int) $pdo->lastInsertId();
        }

        // Обновление остатков на складе
        if (!empty($_POST['sizes']) && is_array($_POST['sizes'])) {
            foreach ($_POST['sizes'] as $size => $qty) {
                $size = (float) $size;
                $qty  = (int)   $qty;
                $pdo->prepare(
                    "INSERT INTO stock (product_id, size, quantity)
                     VALUES (?, ?, ?)
                     ON DUPLICATE KEY UPDATE quantity = ?"
                )->execute([$productId, $size, $qty, $qty]);
            }
        }

        $successMsg = 'Товар успешно сохранён.';
    }

    // --- Удалить товар ---
    if ($action === 'delete_product' && $productId > 0) {
        $pdo->prepare("UPDATE products SET is_active = 0 WHERE id = ?")->execute([$productId]);
        $successMsg = 'Товар деактивирован.';
    }
}

// Логист: смена статуса заказа
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $roleId === 3) {
    $orderId  = (int) ($_POST['order_id']  ?? 0);
    $statusId = (int) ($_POST['status_id'] ?? 0);

    if ($orderId > 0 && $statusId > 0) {
        $pdo->prepare(
            "UPDATE orders SET status_id = ?, logist_id = ? WHERE id = ?"
        )->execute([$statusId, $_SESSION['user_id'], $orderId]);
        $successMsg = "Статус заказа #$orderId обновлён.";
    }
}

// ── ДАННЫЕ ДЛЯ ОТОБРАЖЕНИЯ ─────────────────────────────────────

// Для контент-менеджера: список товаров, брендов, категорий
$products   = [];
$brands     = [];
$categories = [];

if ($roleId === 2) {
    $products = $pdo->query(
        "SELECT p.*, b.name AS brand_name, c.name AS cat_name
         FROM products p
         JOIN brands b ON b.id = p.brand_id
         JOIN categories c ON c.id = p.category_id
         ORDER BY p.created_at DESC"
    )->fetchAll();

    $brands     = $pdo->query("SELECT * FROM brands ORDER BY name")->fetchAll();
    $categories = $pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll();
}

// Для логиста: все заказы
$orders  = [];
$statuses = [];

if ($roleId === 3) {
    $orders = $pdo->query(
        "SELECT o.id, o.total_price, o.delivery_addr, o.created_at,
                u.name AS customer_name,
                s.label AS status_label, s.color AS status_color,
                o.status_id, o.logist_id
         FROM   orders o
         JOIN   users    u ON u.id = o.user_id
         JOIN   statuses s ON s.id = o.status_id
         ORDER  BY o.created_at DESC"
    )->fetchAll();

    $statuses = $pdo->query("SELECT * FROM statuses")->fetchAll();
}

// Для аналитика: отчёты
$reportSales   = [];
$reportLogists = [];
$allBrandsForFilter = [];
$allCategoriesForFilter = [];

if ($roleId === 4) {

    // ── Параметры фильтра (GET) ────────────────────────────────
    $fDateFrom   = trim($_GET['date_from']  ?? '');
    $fDateTo     = trim($_GET['date_to']    ?? '');
    $fBrand      = (int)($_GET['brand_id']  ?? 0);
    $fCategory   = (int)($_GET['category_id'] ?? 0);
    $fPeriod     = $_GET['period']          ?? '30'; // 7, 30, 90, 365, custom
    $fMetric     = $_GET['metric']          ?? 'revenue'; // revenue, orders, items
    $fGroupBy    = $_GET['group_by']        ?? 'day'; // day, week, month

    // Если выбран быстрый период — вычисляем даты
    if ($fPeriod !== 'custom') {
        $fDateTo   = date('Y-m-d');
        $fDateFrom = date('Y-m-d', strtotime("-{$fPeriod} days"));
    }
    // Гарантируем, что даты заполнены
    if (empty($fDateFrom)) $fDateFrom = date('Y-m-d', strtotime('-30 days'));
    if (empty($fDateTo))   $fDateTo   = date('Y-m-d');

    // ── Данные для выпадающих списков фильтра ─────────────────
    $allBrandsForFilter     = $pdo->query("SELECT id, name FROM brands ORDER BY name")->fetchAll();
    $allCategoriesForFilter = $pdo->query("SELECT id, name FROM categories ORDER BY name")->fetchAll();

    // ── Формат группировки дат ────────────────────────────────
    $dateFormat = match($fGroupBy) {
        'week'  => "DATE_FORMAT(o.created_at, '%x-W%v')",
        'month' => "DATE_FORMAT(o.created_at, '%Y-%m')",
        default => "DATE(o.created_at)",
    };

    // ── Динамические WHERE-условия ────────────────────────────
    $salesWhere  = ["o.status_id != 4", "DATE(o.created_at) >= ?", "DATE(o.created_at) <= ?"];
    $salesParams = [$fDateFrom, $fDateTo];

    if ($fBrand > 0) {
        $salesWhere[]  = 'b.id = ?';
        $salesParams[] = $fBrand;
    }
    if ($fCategory > 0) {
        $salesWhere[]  = 'cat.id = ?';
        $salesParams[] = $fCategory;
    }

    $salesWhereSQL = 'WHERE ' . implode(' AND ', $salesWhere);

    // ── Отчёт 1: Продажи по брендам с фильтрами ──────────────
    $stmt = $pdo->prepare(
        "SELECT
            {$dateFormat}                            AS sale_date,
            b.name                                   AS brand_name,
            b.id                                     AS brand_id,
            COUNT(DISTINCT o.id)                     AS orders_count,
            SUM(oi.quantity)                         AS items_sold,
            ROUND(SUM(oi.quantity * oi.unit_price),2) AS revenue
         FROM   order_items oi
         JOIN   orders   o   ON o.id  = oi.order_id
         JOIN   products p   ON p.id  = oi.product_id
         JOIN   brands   b   ON b.id  = p.brand_id
         JOIN   categories cat ON cat.id = p.category_id
         {$salesWhereSQL}
         GROUP  BY sale_date, b.id
         ORDER  BY sale_date ASC, revenue DESC"
    );
    $stmt->execute($salesParams);
    $reportSales = $stmt->fetchAll();

    // ── KPI: итоговые цифры за период ────────────────────────
    $kpiStmt = $pdo->prepare(
        "SELECT
            COUNT(DISTINCT o.id)                      AS total_orders,
            SUM(oi.quantity)                          AS total_items,
            ROUND(SUM(oi.quantity * oi.unit_price),2) AS total_revenue,
            COUNT(DISTINCT o.user_id)                 AS unique_buyers
         FROM   order_items oi
         JOIN   orders   o   ON o.id  = oi.order_id
         JOIN   products p   ON p.id  = oi.product_id
         JOIN   brands   b   ON b.id  = p.brand_id
         JOIN   categories cat ON cat.id = p.category_id
         {$salesWhereSQL}"
    );
    $kpiStmt->execute($salesParams);
    $kpiTotals = $kpiStmt->fetch();

    // ── Отчёт 2: Эффективность логистов ──────────────────────
    $reportLogists = $pdo->query(
        "SELECT
            u.name                                   AS logist_name,
            u.email,
            COUNT(o.id)                              AS total_orders,
            SUM(o.total_price)                       AS total_revenue,
            SUM(o.status_id = 3)                     AS delivered,
            SUM(o.status_id = 2)                     AS in_transit,
            MIN(o.updated_at)                        AS first_action,
            MAX(o.updated_at)                        AS last_action
         FROM   orders o
         JOIN   users  u ON u.id = o.logist_id
         WHERE  o.logist_id IS NOT NULL
         GROUP  BY o.logist_id
         ORDER  BY total_orders DESC"
    )->fetchAll();
}

// ── Функция транслитерации для slug ───────────────────────────
function transliterate(string $str): string
{
    $map = [
        'а'=>'a','б'=>'b','в'=>'v','г'=>'g','д'=>'d','е'=>'e','ё'=>'yo',
        'ж'=>'zh','з'=>'z','и'=>'i','й'=>'y','к'=>'k','л'=>'l','м'=>'m',
        'н'=>'n','о'=>'o','п'=>'p','р'=>'r','с'=>'s','т'=>'t','у'=>'u',
        'ф'=>'f','х'=>'kh','ц'=>'ts','ч'=>'ch','ш'=>'sh','щ'=>'shch',
        'ъ'=>'','ы'=>'y','ь'=>'','э'=>'e','ю'=>'yu','я'=>'ya',
    ];
    return strtr(mb_strtolower($str), $map);
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Панель управления — STEPUP</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/style.css">
  <link rel="stylesheet" href="assets/css/admin.css">
</head>
<body>

<!-- Шапка администратора -->
<header class="header">
  <div class="header__inner">
    <a href="index.php" class="header__logo">STEP<span>UP</span></a>
    <nav class="header__nav">
      <a href="index.php">← Сайт</a>
    </nav>
    <div class="header__actions" style="color:rgba(255,255,255,.6);font-size:.85rem;">
      <?= $userName ?> &nbsp;|&nbsp;
      <form method="post" action="auth.php" style="display:inline">
        <input type="hidden" name="action" value="logout">
        <button type="submit" style="background:none;border:none;color:rgba(255,255,255,.5);cursor:pointer;">Выйти</button>
      </form>
    </div>
  </div>
</header>

<div class="admin-layout">

  <!-- Боковое меню -->
  <aside class="admin-sidebar">
    <div class="admin-sidebar__role">
      <?php
        $roleLabels = [2 => 'Контент-менеджер', 3 => 'Логист', 4 => 'HR-Аналитик'];
        echo e($roleLabels[$roleId] ?? 'Администратор');
      ?>
    </div>

    <?php if ($roleId === 2): ?>
      <a href="?tab=products" class="admin-sidebar__link <?= ($_GET['tab']??'products')==='products'?'active':'' ?>">📦 Товары</a>
      <a href="?tab=add"      class="admin-sidebar__link <?= ($_GET['tab']??'')==='add'?'active':'' ?>">➕ Добавить товар</a>
    <?php elseif ($roleId === 3): ?>
      <a href="?tab=orders"   class="admin-sidebar__link active">📋 Заказы</a>
    <?php elseif ($roleId === 4): ?>
      <a href="?tab=sales"    class="admin-sidebar__link <?= ($_GET['tab']??'sales')==='sales'?'active':'' ?>">📊 Продажи по брендам</a>
      <a href="?tab=staff"    class="admin-sidebar__link <?= ($_GET['tab']??'')==='staff'?'active':'' ?>">👥 Эффективность логистов</a>
    <?php endif; ?>
  </aside>

  <!-- Основная область -->
  <main class="admin-main">

    <?php if (!empty($successMsg)): ?>
      <div class="alert alert--success"><?= e($successMsg) ?></div>
    <?php endif; ?>

    <?php
    $tab = $_GET['tab'] ?? (
        $roleId === 2 ? 'products' :
        ($roleId === 3 ? 'orders' : 'sales')
    );
    ?>

    <!-- ================================================
         КОНТЕНТ-МЕНЕДЖЕР: список товаров
         ================================================ -->
    <?php if ($roleId === 2 && $tab === 'products'): ?>
      <div class="admin-page-header">
        <h1 class="admin-title">Каталог товаров</h1>
        <a href="?tab=add" class="btn btn--primary btn--sm">+ Добавить</a>
      </div>

      <div class="table-wrap">
        <table class="data-table">
          <thead>
            <tr>
              <th>ID</th><th>Фото</th><th>Название</th><th>Бренд</th>
              <th>Цена</th><th>Статус</th><th>Действия</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($products as $p): ?>
            <tr>
              <td><?= (int)$p['id'] ?></td>
              <td>
                <img src="assets/img/products/<?= e($p['image']) ?>"
                     style="width:48px;height:48px;object-fit:contain;background:#f3f4f6;border-radius:6px;"
                     onerror="this.src='assets/img/products/placeholder.svg'">
              </td>
              <td><?= e($p['name']) ?></td>
              <td><?= e($p['brand_name']) ?></td>
              <td><?= formatPrice($p['price']) ?> ₽</td>
              <td>
                <span class="badge" style="background:<?= $p['is_active']?'#d1fae5':'#fee2e2' ?>;color:<?= $p['is_active']?'#065f46':'#991b1b' ?>">
                  <?= $p['is_active'] ? 'Активен' : 'Скрыт' ?>
                </span>
              </td>
              <td>
                <a href="?tab=edit&id=<?= (int)$p['id'] ?>" class="btn btn--secondary btn--sm">✏️ Изменить</a>
                <form method="post" style="display:inline"
                      onsubmit="return confirm('Деактивировать товар?')">
                  <input type="hidden" name="action"     value="delete_product">
                  <input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>">
                  <button type="submit" class="btn btn--danger btn--sm">🗑</button>
                </form>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

    <!-- ================================================
         КОНТЕНТ-МЕНЕДЖЕР: форма добавления/редактирования
         ================================================ -->
    <?php elseif ($roleId === 2 && in_array($tab, ['add', 'edit'])): ?>
      <?php
        // Если редактирование — загружаем существующий товар
        $editProduct = null;
        $editStock   = [];
        if ($tab === 'edit' && isset($_GET['id'])) {
            $s = $pdo->prepare("SELECT * FROM products WHERE id = ?");
            $s->execute([(int)$_GET['id']]);
            $editProduct = $s->fetch();

            $s2 = $pdo->prepare("SELECT size, quantity FROM stock WHERE product_id = ? ORDER BY size");
            $s2->execute([(int)$_GET['id']]);
            $editStock = $s2->fetchAll(PDO::FETCH_KEY_PAIR);
        }
        $formTitle = $editProduct ? 'Редактировать товар' : 'Новый товар';
        $stdSizes  = [36,37,38,39,40,41,42,43,44,45,46];
      ?>

      <h1 class="admin-title"><?= $formTitle ?></h1>

      <form method="post" enctype="multipart/form-data" class="admin-form">
        <input type="hidden" name="action"        value="save_product">
        <input type="hidden" name="product_id"    value="<?= (int)($editProduct['id'] ?? 0) ?>">
        <input type="hidden" name="current_image" value="<?= e($editProduct['image'] ?? 'default.jpg') ?>">

        <div class="admin-form__grid">
          <!-- Основные данные -->
          <div>
            <div class="form-group">
              <label>Название модели *</label>
              <input type="text" name="name" class="form-control"
                     value="<?= e($editProduct['name'] ?? '') ?>" required>
            </div>

            <div class="form-group">
              <label>Бренд *</label>
              <select name="brand_id" class="form-control" required>
                <?php foreach ($brands as $b): ?>
                  <option value="<?= (int)$b['id'] ?>"
                    <?= ($editProduct['brand_id'] ?? 0) == $b['id'] ? 'selected' : '' ?>>
                    <?= e($b['name']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="form-group">
              <label>Категория *</label>
              <select name="category_id" class="form-control" required>
                <?php foreach ($categories as $c): ?>
                  <option value="<?= (int)$c['id'] ?>"
                    <?= ($editProduct['category_id'] ?? 0) == $c['id'] ? 'selected' : '' ?>>
                    <?= e($c['name']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="form-group">
              <label>Цена (₽) *</label>
              <input type="number" name="price" class="form-control"
                     min="0" step="0.01"
                     value="<?= (float)($editProduct['price'] ?? 0) ?>" required>
            </div>

            <div class="form-group">
              <label>Описание</label>
              <textarea name="description" class="form-control" rows="4"><?= e($editProduct['description'] ?? '') ?></textarea>
            </div>

            <div class="form-group">
              <label style="display:flex;gap:8px;align-items:center;cursor:pointer;">
                <input type="checkbox" name="is_active" value="1"
                       <?= ($editProduct['is_active'] ?? 1) ? 'checked' : '' ?>>
                Показывать на сайте
              </label>
            </div>
          </div>

          <!-- Фото и склад -->
          <div>
            <div class="form-group">
              <label>Фото товара</label>
              <?php if (!empty($editProduct['image']) && $editProduct['image'] !== 'default.jpg'): ?>
                <img src="assets/img/products/<?= e($editProduct['image']) ?>"
                     style="width:120px;margin-bottom:8px;border-radius:8px;background:#f3f4f6;">
              <?php endif; ?>
              <input type="file" name="image" class="form-control" accept=".jpg,.jpeg,.png,.webp">
            </div>

            <div class="form-group">
              <label>Остатки на складе (EU размеры)</label>
              <div class="stock-grid">
                <?php foreach ($stdSizes as $sz): ?>
                  <div class="stock-item">
                    <span><?= $sz ?></span>
                    <input type="number" name="sizes[<?= $sz ?>]"
                           class="form-control"
                           value="<?= (int)($editStock[$sz] ?? 0) ?>"
                           min="0" style="width:64px;padding:6px 8px;">
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
          </div>
        </div><!-- /.admin-form__grid -->

        <button type="submit" class="btn btn--primary">💾 Сохранить товар</button>
        <a href="?tab=products" class="btn btn--secondary">Отмена</a>
      </form>

    <!-- ================================================
         ЛОГИСТ: список заказов
         ================================================ -->
    <?php elseif ($roleId === 3): ?>
      <h1 class="admin-title">Все заказы</h1>

      <div class="table-wrap">
        <table class="data-table">
          <thead>
            <tr>
              <th>№</th><th>Дата</th><th>Клиент</th><th>Адрес</th>
              <th>Сумма</th><th>Статус</th><th>Сменить статус</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($orders as $order): ?>
            <tr>
              <td><strong>#<?= (int)$order['id'] ?></strong></td>
              <td><?= date('d.m.Y H:i', strtotime($order['created_at'])) ?></td>
              <td><?= e($order['customer_name']) ?></td>
              <td style="max-width:180px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
                <?= e($order['delivery_addr']) ?></td>
              <td><?= formatPrice($order['total_price']) ?> ₽</td>
              <td><?= statusBadge($order['status_label'], $order['status_color']) ?></td>
              <td>
                <form method="post" style="display:flex;gap:6px;align-items:center;">
                  <input type="hidden" name="order_id" value="<?= (int)$order['id'] ?>">
                  <select name="status_id" class="form-control" style="width:auto;padding:5px 8px;font-size:.85rem;">
                    <?php foreach ($statuses as $st): ?>
                      <option value="<?= (int)$st['id'] ?>"
                        <?= $st['id'] == $order['status_id'] ? 'selected' : '' ?>>
                        <?= e($st['label']) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                  <button type="submit" class="btn btn--primary btn--sm">✓</button>
                </form>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

    <!-- ================================================
         HR-АНАЛИТИК: отчёт по продажам (с фильтрами и диаграммами)
         ================================================ -->
    <?php elseif ($roleId === 4 && $tab === 'sales'): ?>

      <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:24px;">
        <h1 class="admin-title" style="margin-bottom:0;">📊 Аналитика продаж</h1>
        <span style="font-size:.82rem;color:var(--color-muted);">
          Период: <?= date('d.m.Y', strtotime($fDateFrom)) ?> — <?= date('d.m.Y', strtotime($fDateTo)) ?>
        </span>
      </div>

      <!-- ── ПАНЕЛЬ ФИЛЬТРОВ ────────────────────────────────── -->
      <form method="get" action="admin.php" style="background:var(--color-bg);border:1px solid var(--color-border);
            border-radius:var(--radius-lg);padding:20px 24px;margin-bottom:28px;">
        <input type="hidden" name="tab" value="sales">

        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:16px;align-items:end;">

          <!-- Быстрый период -->
          <div>
            <label style="font-size:.78rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;
                           color:var(--color-muted);display:block;margin-bottom:6px;">Период</label>
            <select name="period" class="form-control" onchange="toggleCustomDates(this.value)">
              <option value="7"     <?= $fPeriod==='7'    ?'selected':'' ?>>7 дней</option>
              <option value="30"    <?= ($fPeriod==='30'||$fPeriod==='custom'&&$fDateFrom=='')?'selected':'' ?>>30 дней</option>
              <option value="90"    <?= $fPeriod==='90'   ?'selected':'' ?>>3 месяца</option>
              <option value="365"   <?= $fPeriod==='365'  ?'selected':'' ?>>Год</option>
              <option value="custom"<?= $fPeriod==='custom'?'selected':'' ?>>Свой период</option>
            </select>
          </div>

          <!-- Свои даты (скрыты если не custom) -->
          <div id="dateFromWrap" style="display:<?= $fPeriod==='custom'?'block':'none' ?>">
            <label style="font-size:.78rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;
                           color:var(--color-muted);display:block;margin-bottom:6px;">Дата с</label>
            <input type="date" name="date_from" class="form-control"
                   value="<?= e($fDateFrom) ?>" max="<?= date('Y-m-d') ?>">
          </div>

          <div id="dateToWrap" style="display:<?= $fPeriod==='custom'?'block':'none' ?>">
            <label style="font-size:.78rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;
                           color:var(--color-muted);display:block;margin-bottom:6px;">Дата по</label>
            <input type="date" name="date_to" class="form-control"
                   value="<?= e($fDateTo) ?>" max="<?= date('Y-m-d') ?>">
          </div>

          <!-- Бренд -->
          <div>
            <label style="font-size:.78rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;
                           color:var(--color-muted);display:block;margin-bottom:6px;">Бренд</label>
            <select name="brand_id" class="form-control">
              <option value="0">Все бренды</option>
              <?php foreach ($allBrandsForFilter as $b): ?>
                <option value="<?= (int)$b['id'] ?>" <?= $fBrand==$b['id']?'selected':'' ?>>
                  <?= e($b['name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- Категория -->
          <div>
            <label style="font-size:.78rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;
                           color:var(--color-muted);display:block;margin-bottom:6px;">Тип обуви</label>
            <select name="category_id" class="form-control">
              <option value="0">Все типы</option>
              <?php foreach ($allCategoriesForFilter as $c): ?>
                <option value="<?= (int)$c['id'] ?>" <?= $fCategory==$c['id']?'selected':'' ?>>
                  <?= e($c['name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- Группировка -->
          <div>
            <label style="font-size:.78rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;
                           color:var(--color-muted);display:block;margin-bottom:6px;">Группировка</label>
            <select name="group_by" class="form-control">
              <option value="day"   <?= $fGroupBy==='day'  ?'selected':'' ?>>По дням</option>
              <option value="week"  <?= $fGroupBy==='week' ?'selected':'' ?>>По неделям</option>
              <option value="month" <?= $fGroupBy==='month'?'selected':'' ?>>По месяцам</option>
            </select>
          </div>

          <!-- Метрика диаграммы -->
          <div>
            <label style="font-size:.78rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;
                           color:var(--color-muted);display:block;margin-bottom:6px;">Метрика</label>
            <select name="metric" class="form-control">
              <option value="revenue" <?= $fMetric==='revenue'?'selected':'' ?>>Выручка (₽)</option>
              <option value="orders"  <?= $fMetric==='orders' ?'selected':'' ?>>Заказы (шт.)</option>
              <option value="items"   <?= $fMetric==='items'  ?'selected':'' ?>>Продано пар</option>
            </select>
          </div>

          <!-- Кнопки -->
          <div style="display:flex;gap:8px;">
            <button type="submit" class="btn btn--primary btn--sm" style="flex:1;">Применить</button>
            <a href="?tab=sales" class="btn btn--secondary btn--sm">Сброс</a>
          </div>

        </div>
      </form>

      <?php if (empty($reportSales)): ?>
        <div style="text-align:center;padding:60px 0;color:var(--color-muted);">
          <div style="font-size:3rem;margin-bottom:12px;">📊</div>
          <p>Нет данных о продажах за выбранный период.</p>
          <a href="?tab=sales" class="btn btn--secondary" style="margin-top:16px;">Сбросить фильтры</a>
        </div>
      <?php else: ?>

        <!-- ── KPI-КАРТОЧКИ ИТОГОВ ─────────────────────────── -->
        <?php
          $byBrand  = [];
          foreach ($reportSales as $row) {
              $b = $row['brand_name'];
              if (!isset($byBrand[$b])) $byBrand[$b] = ['revenue'=>0,'orders'=>0,'items'=>0];
              $byBrand[$b]['revenue'] += $row['revenue'];
              $byBrand[$b]['orders']  += $row['orders_count'];
              $byBrand[$b]['items']   += $row['items_sold'];
          }
          arsort($byBrand);
          $totalRev = $kpiTotals['total_revenue'] ?? 0;

          $metricLabels = ['revenue'=>'Выручка','orders'=>'Заказов','items'=>'Продано пар'];
          $metricKey    = ['revenue'=>'revenue','orders'=>'orders','items'=>'items'][$fMetric];
        ?>

        <div class="kpi-row" style="margin-bottom:24px;">
          <div class="kpi-card" style="border-left:4px solid var(--color-accent);">
            <div class="kpi-card__label">Выручка за период</div>
            <div class="kpi-card__value" style="font-size:1.8rem;"><?= formatPrice($totalRev) ?> ₽</div>
          </div>
          <div class="kpi-card" style="border-left:4px solid #10b981;">
            <div class="kpi-card__label">Заказов</div>
            <div class="kpi-card__value" style="font-size:1.8rem;"><?= (int)($kpiTotals['total_orders'] ?? 0) ?></div>
          </div>
          <div class="kpi-card" style="border-left:4px solid #f59e0b;">
            <div class="kpi-card__label">Продано пар</div>
            <div class="kpi-card__value" style="font-size:1.8rem;"><?= (int)($kpiTotals['total_items'] ?? 0) ?></div>
          </div>
          <div class="kpi-card" style="border-left:4px solid #8b5cf6;">
            <div class="kpi-card__label">Уникальных покупателей</div>
            <div class="kpi-card__value" style="font-size:1.8rem;"><?= (int)($kpiTotals['unique_buyers'] ?? 0) ?></div>
          </div>
        </div>

        <!-- ── ДИАГРАММЫ ──────────────────────────────────── -->
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-bottom:32px;">

          <!-- График динамики по времени (линейный) -->
          <div style="background:var(--color-bg);border:1px solid var(--color-border);
                      border-radius:var(--radius-lg);padding:20px;">
            <div style="font-weight:700;font-size:.9rem;margin-bottom:16px;">
              📈 Динамика: <?= e($metricLabels[$fMetric]) ?> по периодам
            </div>
            <canvas id="chartLine" height="220"></canvas>
          </div>

          <!-- Круговая диаграмма по брендам -->
          <div style="background:var(--color-bg);border:1px solid var(--color-border);
                      border-radius:var(--radius-lg);padding:20px;">
            <div style="font-weight:700;font-size:.9rem;margin-bottom:16px;">
              🥧 Доля брендов: <?= e($metricLabels[$fMetric]) ?>
            </div>
            <canvas id="chartPie" height="220"></canvas>
          </div>

        </div>

        <!-- Горизонтальный бар-чарт по брендам -->
        <div style="background:var(--color-bg);border:1px solid var(--color-border);
                    border-radius:var(--radius-lg);padding:20px;margin-bottom:32px;">
          <div style="font-weight:700;font-size:.9rem;margin-bottom:16px;">
            📊 Сравнение брендов: <?= e($metricLabels[$fMetric]) ?>
          </div>
          <canvas id="chartBar" height="120"></canvas>
        </div>

        <!-- ── МИНИ-KPI ПО БРЕНДАМ ───────────────────────── -->
        <div class="kpi-row" style="margin-bottom:32px;">
          <?php foreach ($byBrand as $brand => $data): ?>
          <div class="kpi-card">
            <div class="kpi-card__label"><?= e($brand) ?></div>
            <div class="kpi-card__value"><?= formatPrice($data['revenue']) ?> ₽</div>
            <div style="font-size:.78rem;color:var(--color-muted);margin-top:4px;">
              <?= (int)$data['orders'] ?> заказ · <?= (int)$data['items'] ?> пар
            </div>
            <div class="kpi-card__bar" style="margin-top:8px;">
              <div class="kpi-card__bar-fill"
                   style="width:<?= $totalRev > 0 ? round($data['revenue'] / $totalRev * 100) : 0 ?>%"></div>
            </div>
            <div style="font-size:.72rem;color:var(--color-muted);margin-top:4px;">
              <?= $totalRev > 0 ? round($data['revenue'] / $totalRev * 100) : 0 ?>% от выручки
            </div>
          </div>
          <?php endforeach; ?>
        </div>

        <!-- ── ДЕТАЛЬНАЯ ТАБЛИЦА ──────────────────────────── -->
        <div style="font-weight:700;font-size:.9rem;margin-bottom:12px;">📋 Детализация</div>
        <div class="table-wrap">
          <table class="data-table">
            <thead>
              <tr>
                <th>Период</th>
                <th>Бренд</th>
                <th>Заказов</th>
                <th>Продано пар</th>
                <th>Выручка</th>
                <th>Ср. чек</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach (array_reverse($reportSales) as $row): ?>
              <tr>
                <td><?= e($row['sale_date']) ?></td>
                <td><strong><?= e($row['brand_name']) ?></strong></td>
                <td><?= (int)$row['orders_count'] ?></td>
                <td><?= (int)$row['items_sold'] ?></td>
                <td><?= formatPrice($row['revenue']) ?> ₽</td>
                <td><?= $row['orders_count'] > 0 ? formatPrice($row['revenue'] / $row['orders_count']) : '—' ?> ₽</td>
              </tr>
              <?php endforeach; ?>
            </tbody>
            <tfoot>
              <tr style="font-weight:700;background:var(--color-surface)">
                <td colspan="2">Итого</td>
                <td><?= (int)($kpiTotals['total_orders'] ?? 0) ?></td>
                <td><?= (int)($kpiTotals['total_items'] ?? 0) ?></td>
                <td><?= formatPrice($totalRev) ?> ₽</td>
                <td>—</td>
              </tr>
            </tfoot>
          </table>
        </div>

        <!-- ── CHART.JS СКРИПТЫ ───────────────────────────── -->
        <?php
          // Подготовка данных для JS
          // 1. Динамика по времени: уникальные периоды + суммы по всем брендам
          $timeData = [];
          foreach ($reportSales as $row) {
              $d = $row['sale_date'];
              if (!isset($timeData[$d])) $timeData[$d] = ['revenue'=>0,'orders'=>0,'items'=>0];
              $timeData[$d]['revenue'] += $row['revenue'];
              $timeData[$d]['orders']  += $row['orders_count'];
              $timeData[$d]['items']   += $row['items_sold'];
          }
          ksort($timeData);

          $timeLabels  = json_encode(array_keys($timeData));
          $timeValues  = json_encode(array_column(array_values($timeData), $metricKey));

          // 2. По брендам
          $brandNames  = json_encode(array_keys($byBrand));
          $brandValues = json_encode(array_map(fn($d) => round($d[$metricKey], 2), array_values($byBrand)));

          // Цвета для брендов
          $chartColors = ['#0056b3','#10b981','#f59e0b','#ef4444','#8b5cf6','#ec4899','#06b6d4','#84cc16'];
          $colorsJS    = json_encode(array_slice($chartColors, 0, count($byBrand)));

          $metricLabel = e($metricLabels[$fMetric]);
          $metricUnit  = $fMetric === 'revenue' ? ' ₽' : ' шт.';
        ?>

        <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
        <script>
        Chart.defaults.font.family = "'DM Sans', 'Segoe UI', sans-serif";
        Chart.defaults.color = '#6b7280';

        const metricUnit = '<?= $metricUnit ?>';
        const metricLabel = '<?= $metricLabel ?>';

        // Форматирование чисел
        function fmt(n) {
          if (n >= 1000000) return (n/1000000).toFixed(1) + ' млн';
          if (n >= 1000)    return (n/1000).toFixed(0) + ' тыс.';
          return String(Math.round(n));
        }

        // ── 1. Линейный график динамики ──
        new Chart(document.getElementById('chartLine'), {
          type: 'line',
          data: {
            labels: <?= $timeLabels ?>,
            datasets: [{
              label: metricLabel,
              data: <?= $timeValues ?>,
              borderColor: '#0056b3',
              backgroundColor: 'rgba(0,86,179,.08)',
              borderWidth: 2.5,
              pointRadius: 4,
              pointHoverRadius: 6,
              fill: true,
              tension: 0.35,
            }]
          },
          options: {
            responsive: true,
            plugins: {
              legend: { display: false },
              tooltip: {
                callbacks: {
                  label: ctx => ' ' + fmt(ctx.parsed.y) + metricUnit
                }
              }
            },
            scales: {
              y: {
                beginAtZero: true,
                ticks: { callback: v => fmt(v) + metricUnit },
                grid: { color: 'rgba(0,0,0,.05)' },
              },
              x: { grid: { display: false } }
            }
          }
        });

        // ── 2. Круговая диаграмма ──
        new Chart(document.getElementById('chartPie'), {
          type: 'doughnut',
          data: {
            labels: <?= $brandNames ?>,
            datasets: [{
              data: <?= $brandValues ?>,
              backgroundColor: <?= $colorsJS ?>,
              borderWidth: 2,
              borderColor: '#fff',
              hoverOffset: 8,
            }]
          },
          options: {
            responsive: true,
            cutout: '58%',
            plugins: {
              legend: {
                position: 'bottom',
                labels: { padding: 12, boxWidth: 12, font: { size: 12 } }
              },
              tooltip: {
                callbacks: {
                  label: ctx => ' ' + ctx.label + ': ' + fmt(ctx.parsed) + metricUnit
                }
              }
            }
          }
        });

        // ── 3. Горизонтальный бар ──
        new Chart(document.getElementById('chartBar'), {
          type: 'bar',
          data: {
            labels: <?= $brandNames ?>,
            datasets: [{
              label: metricLabel,
              data: <?= $brandValues ?>,
              backgroundColor: <?= $colorsJS ?>,
              borderRadius: 6,
              borderSkipped: false,
            }]
          },
          options: {
            indexAxis: 'y',
            responsive: true,
            plugins: {
              legend: { display: false },
              tooltip: {
                callbacks: {
                  label: ctx => ' ' + fmt(ctx.parsed.x) + metricUnit
                }
              }
            },
            scales: {
              x: {
                beginAtZero: true,
                ticks: { callback: v => fmt(v) },
                grid: { color: 'rgba(0,0,0,.05)' },
              },
              y: { grid: { display: false } }
            }
          }
        });
        </script>

        <script>
        // Показываем/скрываем поля своего периода
        function toggleCustomDates(val) {
          const show = val === 'custom';
          document.getElementById('dateFromWrap').style.display = show ? 'block' : 'none';
          document.getElementById('dateToWrap').style.display   = show ? 'block' : 'none';
        }
        </script>

      <?php endif; ?>

    <!-- ================================================
         HR-АНАЛИТИК: эффективность логистов
         ================================================ -->
    <?php elseif ($roleId === 4 && $tab === 'staff'): ?>
      <h1 class="admin-title">Эффективность логистов</h1>

      <?php if (empty($reportLogists)): ?>
        <p class="text-muted">Нет данных. Ни один заказ пока не назначен логисту.</p>
      <?php else: ?>
        <div class="kpi-row">
          <?php foreach ($reportLogists as $lg): ?>
          <div class="kpi-card">
            <div class="kpi-card__label"><?= e($lg['logist_name']) ?></div>
            <div class="kpi-card__value"><?= (int)$lg['total_orders'] ?> заказов</div>
            <div style="font-size:.8rem;color:var(--color-muted);margin-top:4px;">
              ✅ Доставлено: <?= (int)$lg['delivered'] ?> &nbsp;
              🚚 В пути: <?= (int)$lg['in_transit'] ?>
            </div>
          </div>
          <?php endforeach; ?>
        </div>

        <div class="table-wrap" style="margin-top:32px;">
          <table class="data-table">
            <thead>
              <tr>
                <th>Логист</th><th>Email</th><th>Всего заказов</th>
                <th>Доставлено</th><th>В пути</th><th>Оборот</th><th>Последнее действие</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($reportLogists as $lg): ?>
              <tr>
                <td><strong><?= e($lg['logist_name']) ?></strong></td>
                <td><?= e($lg['email']) ?></td>
                <td><?= (int)$lg['total_orders'] ?></td>
                <td><span class="badge" style="background:#d1fae5;color:#065f46"><?= (int)$lg['delivered'] ?></span></td>
                <td><span class="badge" style="background:#dbeafe;color:#1e40af"><?= (int)$lg['in_transit'] ?></span></td>
                <td><?= formatPrice($lg['total_revenue']) ?> ₽</td>
                <td><?= $lg['last_action'] ? date('d.m.Y H:i', strtotime($lg['last_action'])) : '—' ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>

    <?php endif; // конец переключения вкладок ?>

  </main>
</div><!-- /.admin-layout -->

</body>
</html>
