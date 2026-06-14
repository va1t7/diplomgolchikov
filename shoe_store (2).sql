-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Хост: 127.0.0.1
-- Время создания: Июн 14 2026 г., 13:10
-- Версия сервера: 10.4.32-MariaDB
-- Версия PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- База данных: `shoe_store`
--

-- --------------------------------------------------------

--
-- Структура таблицы `brands`
--

CREATE TABLE `brands` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `slug` varchar(110) NOT NULL COMMENT 'URL-совместимый идентификатор'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `brands`
--

INSERT INTO `brands` (`id`, `name`, `slug`) VALUES
(1, 'Nike', 'nike'),
(2, 'Adidas', 'adidas'),
(3, 'Puma', 'puma'),
(4, 'Reebok', 'reebok'),
(5, 'New Balance', 'new-balance'),
(6, 'Vans', 'vans'),
(7, 'Balenciaga', 'balenciaga'),
(8, 'Maison Margiela', 'maison margiela'),
(9, 'Loro Piano', 'loro piano'),
(10, 'Timberland', 'timberland'),
(11, 'BIRKINSTOCK', 'birkinstock');

-- --------------------------------------------------------

--
-- Структура таблицы `categories`
--

CREATE TABLE `categories` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `slug` varchar(110) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `categories`
--

INSERT INTO `categories` (`id`, `name`, `slug`) VALUES
(1, 'Кроссовки', 'sneakers'),
(2, 'Ботинки', 'boots'),
(3, 'Туфли', 'oxfords'),
(4, 'Сандалии', 'sandals'),
(5, 'Кеды', 'canvas');

-- --------------------------------------------------------

--
-- Структура таблицы `orders`
--

CREATE TABLE `orders` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL COMMENT 'Покупатель',
  `logist_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'Логист, взявший заказ',
  `status_id` int(10) UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Текущий статус',
  `total_price` decimal(12,2) NOT NULL COMMENT 'Итоговая сумма',
  `delivery_addr` text NOT NULL COMMENT 'Адрес доставки',
  `comment` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Заказы покупателей';

--
-- Дамп данных таблицы `orders`
--

INSERT INTO `orders` (`id`, `user_id`, `logist_id`, `status_id`, `total_price`, `delivery_addr`, `comment`, `created_at`, `updated_at`) VALUES
(1, 4, 2, 2, 10990.00, 'fd', 'fdf', '2026-04-25 23:37:23', '2026-05-26 19:52:44'),
(2, 5, 2, 3, 5990.00, 'rsrwer', 'rwerw', '2026-04-25 23:43:01', '2026-04-25 23:43:30'),
(3, 7, NULL, 1, 5990.00, '111', '11', '2026-05-18 15:35:44', '2026-05-18 15:35:44'),
(4, 4, 2, 3, 5990.00, 'Тарногский Городок, ул. Верхняя 14, 5', 'в 14:00 в любой день, кроме понедельника, свяжитесь со мной, чтобы уточнить', '2026-05-25 14:48:12', '2026-05-26 19:52:48'),
(5, 8, 2, 3, 11980.00, 'Ярославль, 1,1,1', '1234', '2026-05-26 19:51:21', '2026-06-14 13:49:03'),
(6, 9, 2, 2, 10990.00, '111', '111', '2026-06-14 13:47:50', '2026-06-14 13:48:59');

-- --------------------------------------------------------

--
-- Структура таблицы `order_items`
--

CREATE TABLE `order_items` (
  `id` int(10) UNSIGNED NOT NULL,
  `order_id` int(10) UNSIGNED NOT NULL,
  `product_id` int(10) UNSIGNED NOT NULL,
  `size` decimal(4,1) NOT NULL COMMENT 'Заказанный размер',
  `quantity` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `unit_price` decimal(10,2) NOT NULL COMMENT 'Цена на момент заказа (снэпшот)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Позиции внутри заказа';

--
-- Дамп данных таблицы `order_items`
--

INSERT INTO `order_items` (`id`, `order_id`, `product_id`, `size`, `quantity`, `unit_price`) VALUES
(1, 1, 3, 44.0, 1, 10990.00),
(2, 2, 8, 39.0, 1, 5990.00),
(3, 3, 8, 41.0, 1, 5990.00),
(4, 4, 8, 38.0, 1, 5990.00),
(5, 5, 8, 41.0, 2, 5990.00),
(6, 6, 3, 44.0, 1, 10990.00);

-- --------------------------------------------------------

--
-- Структура таблицы `products`
--

CREATE TABLE `products` (
  `id` int(10) UNSIGNED NOT NULL,
  `brand_id` int(10) UNSIGNED NOT NULL,
  `category_id` int(10) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL COMMENT 'Название модели',
  `slug` varchar(270) NOT NULL COMMENT 'ЧПУ для URL',
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL COMMENT 'Цена в рублях',
  `image` varchar(255) DEFAULT 'default.jpg' COMMENT 'Имя файла картинки',
  `is_active` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1 = товар виден на сайте',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Каталог обуви';

--
-- Дамп данных таблицы `products`
--

INSERT INTO `products` (`id`, `brand_id`, `category_id`, `name`, `slug`, `description`, `price`, `image`, `is_active`, `created_at`) VALUES
(1, 1, 1, 'Nike Air Max 270', 'nike-air-max-270', 'Культовые кроссовки с Air-подушкой.', 8990.00, '1.jfif', 1, '2026-04-24 23:14:44'),
(2, 1, 1, 'Nike React Infinity', 'nike-react-infinity', 'Максимальный комфорт для бега.', 9490.00, '2.jfif', 1, '2026-04-24 23:14:44'),
(3, 2, 1, 'Adidas Ultraboost 22', 'adidas-ultraboost-22', 'Технология Boost для возврата энергии.', 10990.00, '3.jpg', 1, '2026-04-24 23:14:44'),
(4, 2, 5, 'Adidas Stan Smith', 'adidas-stan-smith', 'Классика минимализма с 1965 года.', 6990.00, '4.jfif', 1, '2026-04-24 23:14:44'),
(5, 3, 1, 'Puma RS-X', 'puma-rs-x', 'Дерзкий дизайн в стиле 90-х.', 7490.00, '5.jfif', 1, '2026-04-24 23:14:44'),
(6, 4, 1, 'Reebok Classic Leather', 'reebok-classic-leather', 'Вечная классика из натуральной кожи.', 6490.00, '6.jfif', 1, '2026-04-24 23:14:44'),
(7, 5, 1, 'New Balance 574', 'new-balance-574', 'Легендарная модель с суэдом и сеткой.', 7990.00, '7.jfif', 1, '2026-04-24 23:14:44'),
(8, 6, 5, 'Vans Old Skool', 'vans-old-skool', 'Оригинальный скейтерский стиль.', 5990.00, '8.jfif', 1, '2026-04-24 23:14:44'),
(9, 5, 3, '1', '1', '111', 20000.00, 'product_6a15cfc0a34d8.jpg', 0, '2026-05-26 19:52:16'),
(10, 10, 2, 'Timberland 6 Inch', 'timberland-6-inch', 'Классика для любого мужчины', 14000.00, 't.jfif', 1, '2026-06-13 16:59:51'),
(11, 7, 1, 'Balenciaga Track 2', 'balenciaga-track-2', 'Лучшие кроссовки для модного парня', 50000.00, 'b.jfif', 1, '2026-06-13 17:01:31'),
(12, 9, 3, 'Loro Piano Tufles', 'loro-piano-tufles', 'Скромная роскошь', 79999.00, 'loro.jfif', 1, '2026-06-13 17:02:13'),
(13, 8, 5, 'Maison Margiela Tabi', 'maison-margiela-tabi', 'Необычные кеды для необычной личности', 34999.00, 'm.jfif', 1, '2026-06-13 17:03:02'),
(15, 11, 4, 'BIRKINSTOCK MILANO', 'birkinstock-milano', 'Летняя обувь для тех, кто ценит комфорт и роскошь', 49999.00, 'birkin.jfif', 1, '2026-06-13 17:05:37'),
(16, 7, 1, '11', '11', '111', 1111.00, 'product_6a2e870679067.png', 1, '2026-06-14 13:48:38');

-- --------------------------------------------------------

--
-- Структура таблицы `roles`
--

CREATE TABLE `roles` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(50) NOT NULL COMMENT 'Системное название роли',
  `label` varchar(100) NOT NULL COMMENT 'Отображаемое название'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Роли пользователей системы';

--
-- Дамп данных таблицы `roles`
--

INSERT INTO `roles` (`id`, `name`, `label`) VALUES
(1, 'customer', 'Клиент'),
(2, 'content_manager', 'Контент-менеджер'),
(3, 'logist', 'Логист / Курьер'),
(4, 'hr_analyst', 'HR-Аналитик');

-- --------------------------------------------------------

--
-- Структура таблицы `statuses`
--

CREATE TABLE `statuses` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(50) NOT NULL COMMENT 'Системное название',
  `label` varchar(100) NOT NULL COMMENT 'Отображаемый текст',
  `color` varchar(7) NOT NULL DEFAULT '#6c757d' COMMENT 'HEX-цвет бейджа'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `statuses`
--

INSERT INTO `statuses` (`id`, `name`, `label`, `color`) VALUES
(1, 'pending', 'Принят', '#f59e0b'),
(2, 'shipping', 'В пути', '#3b82f6'),
(3, 'delivered', 'Доставлен', '#10b981'),
(4, 'cancelled', 'Отменён', '#ef4444');

-- --------------------------------------------------------

--
-- Структура таблицы `stock`
--

CREATE TABLE `stock` (
  `id` int(10) UNSIGNED NOT NULL,
  `product_id` int(10) UNSIGNED NOT NULL,
  `size` decimal(4,1) NOT NULL COMMENT 'Размер обуви (EU), напр. 42.0',
  `quantity` int(10) UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Остаток на складе'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Складские остатки по размерам';

--
-- Дамп данных таблицы `stock`
--

INSERT INTO `stock` (`id`, `product_id`, `size`, `quantity`) VALUES
(1, 1, 40.0, 0),
(2, 1, 41.0, 0),
(3, 1, 42.0, 0),
(4, 1, 43.0, 0),
(5, 1, 44.0, 0),
(6, 2, 40.0, 3),
(7, 2, 41.0, 0),
(8, 2, 42.0, 0),
(9, 2, 43.0, 0),
(10, 2, 44.0, 0),
(11, 3, 41.0, 6),
(12, 3, 42.0, 4),
(13, 3, 43.0, 8),
(14, 3, 44.0, 1),
(15, 4, 39.0, 10),
(16, 4, 40.0, 8),
(17, 4, 41.0, 6),
(18, 4, 42.0, 5),
(19, 5, 40.0, 7),
(20, 5, 41.0, 9),
(21, 5, 42.0, 4),
(22, 5, 43.0, 2),
(23, 6, 40.0, 5),
(24, 6, 41.0, 6),
(25, 6, 42.0, 7),
(26, 6, 43.0, 4),
(27, 7, 40.0, 3),
(28, 7, 41.0, 8),
(29, 7, 42.0, 6),
(30, 7, 43.0, 5),
(31, 8, 38.0, 5),
(32, 8, 39.0, 7),
(33, 8, 40.0, 10),
(34, 8, 41.0, 4),
(35, 1, 36.0, 0),
(36, 1, 37.0, 0),
(37, 1, 38.0, 0),
(38, 1, 39.0, 0),
(44, 1, 45.0, 0),
(45, 1, 46.0, 0),
(57, 2, 36.0, 0),
(58, 2, 37.0, 0),
(59, 2, 38.0, 0),
(60, 2, 39.0, 0),
(66, 2, 45.0, 0),
(67, 2, 46.0, 0),
(68, 9, 36.0, 4),
(69, 9, 37.0, 0),
(70, 9, 38.0, 0),
(71, 9, 39.0, 0),
(72, 9, 40.0, 0),
(73, 9, 41.0, 0),
(74, 9, 42.0, 0),
(75, 9, 43.0, 0),
(76, 9, 44.0, 0),
(77, 9, 45.0, 2),
(78, 9, 46.0, 0),
(79, 10, 36.0, 4),
(80, 10, 37.0, 4),
(81, 10, 38.0, 3),
(82, 10, 39.0, 4),
(83, 10, 40.0, 10),
(84, 10, 41.0, 9),
(85, 10, 42.0, 13),
(86, 10, 43.0, 10),
(87, 10, 44.0, 15),
(88, 10, 45.0, 11),
(89, 10, 46.0, 12),
(90, 11, 36.0, 2),
(91, 11, 37.0, 2),
(92, 11, 38.0, 3),
(93, 11, 39.0, 4),
(94, 11, 40.0, 6),
(95, 11, 41.0, 6),
(96, 11, 42.0, 4),
(97, 11, 43.0, 5),
(98, 11, 44.0, 3),
(99, 11, 45.0, 1),
(100, 11, 46.0, 0),
(101, 12, 36.0, 1),
(102, 12, 37.0, 1),
(103, 12, 38.0, 1),
(104, 12, 39.0, 1),
(105, 12, 40.0, 1),
(106, 12, 41.0, 1),
(107, 12, 42.0, 1),
(108, 12, 43.0, 1),
(109, 12, 44.0, 1),
(110, 12, 45.0, 1),
(111, 12, 46.0, 1),
(112, 13, 36.0, 3),
(113, 13, 37.0, 3),
(114, 13, 38.0, 4),
(115, 13, 39.0, 5),
(116, 13, 40.0, 5),
(117, 13, 41.0, 5),
(118, 13, 42.0, 5),
(119, 13, 43.0, 5),
(120, 13, 44.0, 5),
(121, 13, 45.0, 5),
(122, 13, 46.0, 5),
(123, 15, 36.0, 0),
(124, 15, 37.0, 0),
(125, 15, 38.0, 0),
(126, 15, 39.0, 0),
(127, 15, 40.0, 0),
(128, 15, 41.0, 0),
(129, 15, 42.0, 0),
(130, 15, 43.0, 0),
(131, 15, 44.0, 0),
(132, 15, 45.0, 0),
(133, 15, 46.0, 0),
(145, 16, 36.0, 3),
(146, 16, 37.0, 0),
(147, 16, 38.0, 0),
(148, 16, 39.0, 0),
(149, 16, 40.0, 0),
(150, 16, 41.0, 0),
(151, 16, 42.0, 0),
(152, 16, 43.0, 0),
(153, 16, 44.0, 0),
(154, 16, 45.0, 0),
(155, 16, 46.0, 0);

-- --------------------------------------------------------

--
-- Структура таблицы `users`
--

CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,
  `role_id` int(10) UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Ссылка на роль',
  `name` varchar(150) NOT NULL COMMENT 'Полное имя',
  `email` varchar(255) NOT NULL COMMENT 'Email (логин)',
  `password` varchar(255) NOT NULL COMMENT 'Хэш пароля (password_hash)',
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL COMMENT 'Адрес доставки по умолчанию',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Пользователи системы';

--
-- Дамп данных таблицы `users`
--

INSERT INTO `users` (`id`, `role_id`, `name`, `email`, `password`, `phone`, `address`, `created_at`, `updated_at`) VALUES
(1, 2, 'Менеджер Иван', 'manager@store.ru', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NULL, NULL, '2026-04-24 23:14:44', '2026-04-25 23:33:05'),
(2, 3, 'Курьер Пётр', 'logist@store.ru', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NULL, NULL, '2026-04-24 23:14:44', '2026-04-25 23:33:05'),
(3, 4, 'Аналитик Анна', 'analyst@store.ru', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NULL, NULL, '2026-04-24 23:14:44', '2026-04-25 23:33:05'),
(4, 1, 'Дима', 'golchikovdmitriy@gmail.com', '$2y$10$DZXS7wQxiUHQoWPLqMLiqOEx.Gllv2DLLAQZ2B4I5z4K8HXn256H6', '89005524456', NULL, '2026-04-25 23:21:26', '2026-04-25 23:21:26'),
(5, 1, 'Миша', '1@g.ru', '$2y$10$pUPLHRRELr4m3lamsCuE9.QfsbKaDIAQeVeiCjMMWkLSluRCEJgXq', '89005524456', NULL, '2026-04-25 23:42:50', '2026-04-25 23:42:50'),
(6, 1, 'va1t', 'golchikovd7@gmail.com', '$2y$10$CvvDGPsunIIZyv6fAu5okeTDVuX5aXQG1.mxlluf8GJvlx08sejIS', '89005524456', NULL, '2026-05-18 14:32:00', '2026-05-18 14:32:00'),
(7, 1, '12', '1@GMAIL.COM', '$2y$10$fs.y5FCdm6epj4Yew9HoQe60817aatIwiluJ/AGljaNl5Q8fVx55S', '8935228358', NULL, '2026-05-18 15:35:09', '2026-05-18 15:35:09'),
(8, 1, 'dima', '2@h.ru', '$2y$10$4.Qdt/9/XGUuksOSvoYG/enP3CIP51z5ON09k71ndNnzj/D0tqnNe', '89539593963', NULL, '2026-05-26 19:50:28', '2026-05-26 19:50:28'),
(9, 1, '111', '111@gmail.com', '$2y$10$8WF9T51CwslVLz.icTVpo.c4OUwRe5YTJvPCdXMaFfhOmBXX.CEv.', '+79005524456', NULL, '2026-06-14 13:46:55', '2026-06-14 13:46:55');

--
-- Индексы сохранённых таблиц
--

--
-- Индексы таблицы `brands`
--
ALTER TABLE `brands`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_brands_slug` (`slug`);

--
-- Индексы таблицы `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_categories_slug` (`slug`);

--
-- Индексы таблицы `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_orders_user` (`user_id`),
  ADD KEY `fk_orders_logist` (`logist_id`),
  ADD KEY `fk_orders_status` (`status_id`);

--
-- Индексы таблицы `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_items_order` (`order_id`),
  ADD KEY `fk_items_product` (`product_id`);

--
-- Индексы таблицы `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_products_slug` (`slug`),
  ADD KEY `fk_products_brand` (`brand_id`),
  ADD KEY `fk_products_category` (`category_id`);

--
-- Индексы таблицы `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_roles_name` (`name`);

--
-- Индексы таблицы `statuses`
--
ALTER TABLE `statuses`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_statuses_name` (`name`);

--
-- Индексы таблицы `stock`
--
ALTER TABLE `stock`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_stock_product_size` (`product_id`,`size`);

--
-- Индексы таблицы `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_users_email` (`email`),
  ADD KEY `fk_users_role` (`role_id`);

--
-- AUTO_INCREMENT для сохранённых таблиц
--

--
-- AUTO_INCREMENT для таблицы `brands`
--
ALTER TABLE `brands`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT для таблицы `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT для таблицы `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT для таблицы `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT для таблицы `products`
--
ALTER TABLE `products`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT для таблицы `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT для таблицы `statuses`
--
ALTER TABLE `statuses`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT для таблицы `stock`
--
ALTER TABLE `stock`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=156;

--
-- AUTO_INCREMENT для таблицы `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- Ограничения внешнего ключа сохраненных таблиц
--

--
-- Ограничения внешнего ключа таблицы `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `fk_orders_logist` FOREIGN KEY (`logist_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_orders_status` FOREIGN KEY (`status_id`) REFERENCES `statuses` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_orders_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE;

--
-- Ограничения внешнего ключа таблицы `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `fk_items_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_items_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON UPDATE CASCADE;

--
-- Ограничения внешнего ключа таблицы `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `fk_products_brand` FOREIGN KEY (`brand_id`) REFERENCES `brands` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_products_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON UPDATE CASCADE;

--
-- Ограничения внешнего ключа таблицы `stock`
--
ALTER TABLE `stock`
  ADD CONSTRAINT `fk_stock_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ограничения внешнего ключа таблицы `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_users_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
