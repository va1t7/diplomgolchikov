<?php
/**
 * login.php — Страница входа и регистрации.
 * Активная вкладка управляется через ?tab=register
 */

session_start();
require_once __DIR__ . '/includes/helpers.php';

// Если уже вошёл — перенаправляем
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$activeTab = $_GET['tab'] ?? ($_SESSION['auth_form'] ?? 'login');
$errors    = $_SESSION['auth_errors'] ?? [];
unset($_SESSION['auth_errors'], $_SESSION['auth_form']);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Войти — STEPUP</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body style="background:var(--color-surface);min-height:100vh;display:flex;flex-direction:column;">

<header class="header">
  <div class="header__inner">
    <a href="index.php" class="header__logo">STEP<span>UP</span></a>
  </div>
</header>

<main style="flex:1;display:flex;align-items:center;justify-content:center;padding:32px 16px;">
  <div style="width:100%;max-width:440px;">

    <!-- Заголовок -->
    <h1 style="font-family:var(--font-display);font-size:2.4rem;letter-spacing:.04em;text-align:center;margin-bottom:8px;">
      STEP<span style="color:var(--color-accent)">UP</span>
    </h1>
    <p style="text-align:center;color:var(--color-muted);margin-bottom:28px;font-size:.9rem;">
      <?= $activeTab === 'register' ? 'Создайте аккаунт' : 'Войдите в аккаунт' ?>
    </p>

    <!-- Табы -->
    <div style="display:flex;background:var(--color-bg);border:1px solid var(--color-border);border-radius:var(--radius-md);padding:4px;margin-bottom:24px;">
      <a href="?tab=login"
         style="flex:1;text-align:center;padding:9px;border-radius:calc(var(--radius-md) - 2px);font-weight:600;font-size:.9rem;transition:all .2s;
                <?= $activeTab !== 'register' ? 'background:var(--color-accent);color:#fff;' : 'color:var(--color-muted);' ?>">
        Войти
      </a>
      <a href="?tab=register"
         style="flex:1;text-align:center;padding:9px;border-radius:calc(var(--radius-md) - 2px);font-weight:600;font-size:.9rem;transition:all .2s;
                <?= $activeTab === 'register' ? 'background:var(--color-accent);color:#fff;' : 'color:var(--color-muted);' ?>">
        Регистрация
      </a>
    </div>

    <!-- Ошибки -->
    <?php if (!empty($errors)): ?>
      <div class="alert alert--error">
        <?php foreach ($errors as $err): ?>
          <div><?= e($err) ?></div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <!-- Карточка с формой -->
    <div style="background:var(--color-bg);border:1px solid var(--color-border);border-radius:var(--radius-xl);padding:32px;box-shadow:var(--shadow-sm);">

      <?php if ($activeTab !== 'register'): ?>
        <!-- ФОРМА ВХОДА -->
        <form method="post" action="auth.php">
          <input type="hidden" name="action" value="login">

          <div class="form-group">
            <label>Email</label>
            <input type="email" name="email" class="form-control"
                   placeholder="you@example.com" required autocomplete="email">
          </div>

          <div class="form-group">
            <label>Пароль</label>
            <input type="password" name="password" class="form-control"
                   placeholder="••••••••" required autocomplete="current-password">
          </div>

          <button type="submit" class="btn btn--primary btn--block btn--lg" style="margin-top:8px;">
            Войти
          </button>
        </form>

        <!-- Демо-подсказка -->
        <div style="margin-top:20px;padding:12px;background:var(--color-surface);border-radius:var(--radius-md);font-size:.8rem;color:var(--color-muted);">
          <strong>Демо-аккаунты</strong> (пароль: <code>Password123!</code>)<br>
          manager@store.ru · logist@store.ru · analyst@store.ru
        </div>

      <?php else: ?>
        <!-- ФОРМА РЕГИСТРАЦИИ -->
        <form method="post" action="auth.php">
          <input type="hidden" name="action" value="register">

          <div class="form-group">
            <label>Ваше имя *</label>
            <input type="text" name="name" class="form-control"
                   placeholder="Иван Иванов" required minlength="2">
          </div>

          <div class="form-group">
            <label>Email *</label>
            <input type="email" name="email" class="form-control"
                   placeholder="you@example.com" required>
          </div>

          <div class="form-group">
            <label>Телефон</label>
            <input type="tel" name="phone" class="form-control" placeholder="+7 (999) 000-00-00">
          </div>

          <div class="form-group">
            <label>Пароль * (минимум 6 символов)</label>
            <input type="password" name="password" class="form-control"
                   placeholder="••••••••" required minlength="6">
          </div>

          <button type="submit" class="btn btn--primary btn--block btn--lg" style="margin-top:8px;">
            Зарегистрироваться
          </button>
        </form>
      <?php endif; ?>

    </div>
  </div>
</main>

</body>
</html>
