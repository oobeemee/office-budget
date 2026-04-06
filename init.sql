-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Хост: 127.0.0.1
-- Время создания: Янв 30 2026 г., 12:32
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
-- База данных: `office_budget_local`
--

-- --------------------------------------------------------

--
-- Структура таблицы `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `type` enum('expense','income','universal') NOT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Дамп данных таблицы `categories`
--

INSERT INTO `categories` (`id`, `name`, `type`, `description`, `is_active`, `created_at`) VALUES
(1, 'Еда', 'expense', '', 1, '2026-01-23 20:00:02'),
(2, 'Подписки', 'expense', '', 1, '2026-01-23 20:01:41');

-- --------------------------------------------------------

--
-- Структура таблицы `transactions`
--

CREATE TABLE `transactions` (
  `id` int(11) NOT NULL,
  `date` datetime NOT NULL,
  `type` enum('expense','income') NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `category_id` int(11) NOT NULL,
  `comment` text DEFAULT NULL,
  `payment_method` varchar(255) DEFAULT NULL,
  `counterparty` varchar(255) DEFAULT NULL,
  `attachment_path` varchar(255) DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Дамп данных таблицы `transactions`
--

INSERT INTO `transactions` (`id`, `date`, `type`, `amount`, `category_id`, `comment`, `payment_method`, `counterparty`, `attachment_path`, `created_by`, `created_at`, `updated_at`) VALUES
(16, '2026-01-28 00:00:00', 'income', 500.00, 1, '', '', '', NULL, 1, '2026-01-28 09:55:07', NULL),
(17, '2026-01-28 00:00:00', 'expense', 250.00, 1, '', '', '', NULL, 1, '2026-01-28 10:01:38', NULL),
(18, '2026-01-28 00:00:00', 'expense', 500.00, 1, 'lorem', '', '', NULL, 4, '2026-01-28 10:54:19', NULL),
(19, '2026-01-28 00:00:00', 'expense', 500.00, 1, 'lorem', '', '', NULL, 4, '2026-01-28 10:54:21', NULL),
(20, '2026-01-28 00:00:00', 'expense', 500.00, 1, 'lorem', '', '', NULL, 4, '2026-01-28 10:54:23', NULL),
(21, '2026-01-28 00:00:00', 'expense', 500.00, 1, 'lorem', '', '', NULL, 4, '2026-01-28 10:54:26', NULL),
(22, '2026-01-28 00:00:00', 'expense', 500.00, 1, 'lorem', '', '', NULL, 4, '2026-01-28 10:54:28', NULL),
(23, '2026-01-28 00:00:00', 'income', 1000.00, 1, '', '', '', NULL, 1, '2026-01-28 10:54:39', NULL),
(24, '2026-01-28 00:00:00', 'income', 1500.00, 1, '', '', '', NULL, 1, '2026-01-28 10:54:50', NULL),
(25, '2026-01-28 00:00:00', 'expense', 1000.00, 2, '', '', '', NULL, 1, '2026-01-28 11:28:36', NULL),
(26, '2026-01-28 11:40:00', 'expense', 100.00, 1, '', '', '', NULL, 1, '2026-01-28 11:40:08', NULL);

-- --------------------------------------------------------

--
-- Структура таблицы `transaction_requests`
--

CREATE TABLE `transaction_requests` (
  `id` int(11) NOT NULL,
  `requester_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `description` text NOT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `approver_id` int(11) DEFAULT NULL,
  `rejection_reason` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `processed_at` datetime DEFAULT NULL,
  `transaction_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `transaction_requests`
--

INSERT INTO `transaction_requests` (`id`, `requester_id`, `amount`, `description`, `status`, `approver_id`, `rejection_reason`, `created_at`, `processed_at`, `transaction_id`) VALUES
(1, 1, 100.00, 'ававыаыа', 'rejected', 1, '', '2026-01-24 14:03:14', '2026-01-24 17:53:07', NULL),
(2, 4, 100.00, 'ттттттттттттт', 'rejected', 1, '', '2026-01-24 14:03:38', '2026-01-24 17:53:10', NULL),
(3, 4, 99999999.99, 'кккккккккк', 'approved', 1, NULL, '2026-01-24 14:52:50', '2026-01-24 17:56:57', NULL),
(4, 4, 400.00, 'хз', 'approved', 1, NULL, '2026-01-26 08:02:08', '2026-01-26 11:28:12', NULL),
(5, 4, 400.00, 'ыва', 'rejected', 1, 'ыавыв', '2026-01-26 08:28:57', '2026-01-26 11:30:10', NULL),
(6, 4, 300.00, 'ваывавы', 'approved', 1, NULL, '2026-01-26 08:30:20', '2026-01-26 11:55:43', NULL),
(7, 4, 100.00, 'пппп', 'approved', 1, NULL, '2026-01-26 09:40:32', '2026-01-26 15:53:43', NULL),
(8, 4, 100.00, 'ззззззззззззззззззззззз', 'approved', 1, NULL, '2026-01-26 12:55:59', '2026-01-26 15:56:16', NULL),
(9, 4, 150.00, 'пппппппп', 'approved', 1, NULL, '2026-01-28 06:22:46', '2026-01-28 09:23:09', NULL),
(10, 4, 200.00, 'ррррррррррррррррр', 'approved', 1, NULL, '2026-01-28 06:22:52', '2026-01-28 09:23:12', NULL),
(11, 4, 500.00, 'lorem', 'approved', 1, NULL, '2026-01-28 07:53:39', '2026-01-28 10:54:19', 18),
(12, 4, 500.00, 'lorem', 'approved', 1, NULL, '2026-01-28 07:53:44', '2026-01-28 10:54:22', 19),
(13, 4, 500.00, 'lorem', 'approved', 1, NULL, '2026-01-28 07:53:49', '2026-01-28 10:54:24', 20),
(14, 4, 500.00, 'lorem', 'approved', 1, NULL, '2026-01-28 07:53:53', '2026-01-28 10:54:27', 21),
(15, 4, 500.00, 'lorem', 'approved', 1, NULL, '2026-01-28 07:53:59', '2026-01-28 10:54:29', 22),
(16, 4, 100.00, 'lorem', 'pending', NULL, NULL, '2026-01-28 08:27:20', NULL, NULL);

-- --------------------------------------------------------

--
-- Структура таблицы `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `login` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('user','operator','admin') NOT NULL DEFAULT 'user',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Дамп данных таблицы `users`
--

INSERT INTO `users` (`id`, `login`, `password_hash`, `role`, `is_active`, `created_at`) VALUES
(1, 'admin', '$2y$10$TKh8H1.PfQx37YgCzwiKb.KjNyWgaHb9cbcoQgdIVFlYg7B77UdFm', 'admin', 1, '2026-01-23 16:12:54'),
(4, 'Тест', '$2y$10$07bkZTouJABsTGishuBBAOBzYp9cYzB3als/Zwt9ypcF1RzHqahRS', 'user', 1, '2026-01-24 16:20:37'),
(7, 'Оператор', '$2y$10$gtdWCFRM4XOY0pz9H8Yu6etfawfkSIuyK1.PGhQcrXFbIkxDQ3Zxu', 'operator', 1, '2026-01-28 09:16:32');

--
-- Индексы сохранённых таблиц
--

--
-- Индексы таблицы `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `transactions`
--
ALTER TABLE `transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `category_id` (`category_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Индексы таблицы `transaction_requests`
--
ALTER TABLE `transaction_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `requester_id` (`requester_id`),
  ADD KEY `approver_id` (`approver_id`),
  ADD KEY `transaction_id` (`transaction_id`);

--
-- Индексы таблицы `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `login` (`login`);

--
-- AUTO_INCREMENT для сохранённых таблиц
--

--
-- AUTO_INCREMENT для таблицы `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT для таблицы `transactions`
--
ALTER TABLE `transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT для таблицы `transaction_requests`
--
ALTER TABLE `transaction_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT для таблицы `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- Ограничения внешнего ключа сохраненных таблиц
--

--
-- Ограничения внешнего ключа таблицы `transactions`
--
ALTER TABLE `transactions`
  ADD CONSTRAINT `transactions_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`),
  ADD CONSTRAINT `transactions_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Ограничения внешнего ключа таблицы `transaction_requests`
--
ALTER TABLE `transaction_requests`
  ADD CONSTRAINT `transaction_requests_ibfk_1` FOREIGN KEY (`requester_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `transaction_requests_ibfk_2` FOREIGN KEY (`approver_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `transaction_requests_ibfk_3` FOREIGN KEY (`transaction_id`) REFERENCES `transactions` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
