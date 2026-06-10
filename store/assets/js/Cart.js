/**
 * cart.js — Управление корзиной на стороне клиента.
 *
 * Корзина хранится в sessionStorage как массив объектов:
 *   [{ id, name, price, image, size, qty }, ...]
 *
 * При каждом изменении — синхронизируется с PHP-сессией через /api/cart.php.
 */

const cartDrawer = (() => {
    // ── Ключ хранилища ─────────────────────────────────────────
    const STORAGE_KEY = 'su_cart';
  
    // ── Утилиты ────────────────────────────────────────────────
  
    /** Загружает корзину из sessionStorage */
    function load() {
      try { return JSON.parse(sessionStorage.getItem(STORAGE_KEY)) || []; }
      catch (e) { return []; }
    }
  
    /** Сохраняет корзину и обновляет UI */
    function save(items) {
      sessionStorage.setItem(STORAGE_KEY, JSON.stringify(items));
      sync(items); // отправляем на сервер
      render(items);
    }
  
    /** Отправляет корзину в PHP-сессию (fire-and-forget) */
    function sync(items) {
      navigator.sendBeacon('/api/cart_sync.php', JSON.stringify(items));
    }
  
    /** Форматирует число как цену "8 990 ₽" */
    function fmt(price) {
      return new Intl.NumberFormat('ru-RU').format(Math.round(price)) + ' ₽';
    }
  
    // ── Публичное API ───────────────────────────────────────────
  
    /** Добавляет товар в корзину или увеличивает количество */
    function addItem(item) {
      const items = load();
      const key   = `${item.id}_${item.size}`;
      const found = items.find(i => `${i.id}_${i.size}` === key);
  
      if (found) {
        found.qty += item.qty;
      } else {
        items.push({ ...item });
      }
  
      save(items);
      updateBadge(items);
    }
  
    /** Изменяет количество позиции */
    function changeQty(id, size, delta) {
      let items = load();
      const key = `${id}_${size}`;
      items = items.map(i => {
        if (`${i.id}_${i.size}` === key) {
          return { ...i, qty: Math.max(1, i.qty + delta) };
        }
        return i;
      });
      save(items);
      updateBadge(items);
    }
  
    /** Удаляет позицию из корзины */
    function removeItem(id, size) {
      let items = load().filter(i => !(i.id == id && i.size == size));
      save(items);
      updateBadge(items);
    }
  
    /** Очищает всю корзину */
    function clear() {
      sessionStorage.removeItem(STORAGE_KEY);
      sync([]);
      render([]);
      updateBadge([]);
    }
  
    /** Открывает сайдбар */
    function openDrawer() {
      document.getElementById('cartDrawer').classList.add('open');
      document.body.style.overflow = 'hidden';
      render(load());
    }
  
    /** Закрывает сайдбар */
    function closeDrawer() {
      document.getElementById('cartDrawer').classList.remove('open');
      document.body.style.overflow = '';
    }
  
    // ── Рендер ─────────────────────────────────────────────────
  
    /** Перерисовывает список товаров в корзине */
    function render(items) {
      const body  = document.getElementById('cartBody');
      const empty = document.getElementById('cartEmpty');
      const total = document.getElementById('cartTotal');
      if (!body) return;
  
      // Удаляем старые карточки (не #cartEmpty)
      body.querySelectorAll('.cart-item').forEach(el => el.remove());
  
      if (!items.length) {
        if (empty) empty.style.display = '';
        if (total) total.textContent = fmt(0);
        return;
      }
  
      if (empty) empty.style.display = 'none';
  
      let sum = 0;
  
      items.forEach(item => {
        sum += item.price * item.qty;
  
        const el = document.createElement('div');
        el.className = 'cart-item';
        el.innerHTML = `
          <img class="cart-item__img"
               src="assets/img/products/${escHtml(item.image || 'placeholder.svg')}"
               alt="${escHtml(item.name)}"
               onerror="this.src='assets/img/products/placeholder.svg'">
          <div class="cart-item__info">
            <div class="cart-item__name">${escHtml(item.name)}</div>
            <div class="cart-item__meta">
              ${item.size ? 'Размер: ' + item.size : '<span style="color:#f59e0b">Размер не выбран</span>'}
            </div>
            <div class="qty-control">
              <button class="qty-btn" onclick="cartDrawer.changeQty(${item.id},'${item.size}',-1)">−</button>
              <span class="qty-value">${item.qty}</span>
              <button class="qty-btn" onclick="cartDrawer.changeQty(${item.id},'${item.size}',+1)">+</button>
            </div>
          </div>
          <div style="display:flex;flex-direction:column;align-items:flex-end;gap:8px">
            <span class="cart-item__price">${fmt(item.price * item.qty)}</span>
            <button onclick="cartDrawer.removeItem(${item.id},'${item.size}')"
                    style="background:none;border:none;cursor:pointer;color:#9ca3af;font-size:.75rem;"
                    onmouseover="this.style.color='#ef4444'"
                    onmouseout="this.style.color='#9ca3af'">удалить</button>
          </div>
        `;
        body.appendChild(el);
      });
  
      if (total) total.textContent = fmt(sum);
    }
  
    /** Обновляет бейдж-счётчик на кнопке корзины */
    function updateBadge(items) {
      const badge = document.getElementById('cartBadge');
      if (!badge) return;
      const count = items.reduce((s, i) => s + i.qty, 0);
      badge.textContent = count;
      badge.style.display = count > 0 ? 'flex' : 'none';
    }
  
    /** Экранирует строку для вставки в HTML */
    function escHtml(str) {
      return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
    }
  
    // ── Инициализация при загрузке страницы ────────────────────
    document.addEventListener('DOMContentLoaded', () => {
      updateBadge(load());
  
      // Закрытие по Escape
      document.addEventListener('keydown', e => {
        if (e.key === 'Escape') closeDrawer();
      });
    });
  
    // ── Экспортируем публичный интерфейс ───────────────────────
    return {
      addItem,
      changeQty,
      removeItem,
      clear,
      open:  openDrawer,
      close: closeDrawer,
      getItems: load,
    };
  })();