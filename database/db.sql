-- phpMyAdmin SQL Dump
-- version 5.2.1-1.el8
-- https://www.phpmyadmin.net/
--
-- Хост: localhost
-- Время создания: Июл 14 2026 г., 06:38
-- Версия сервера: 8.0.36-cll-lve
-- Версия PHP: 7.2.24

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- База данных: `yago`
--

DELIMITER $$
--
-- Процедуры
--
CREATE DEFINER=`kws_bg`@`localhost` PROCEDURE `yg_add_column` (IN `p_table` VARCHAR(64), IN `p_column` VARCHAR(64), IN `p_definition` TEXT)   BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = p_table AND COLUMN_NAME = p_column
  ) THEN
    SET @yg_sql = CONCAT('ALTER TABLE `', p_table, '` ADD COLUMN ', p_definition);
    PREPARE yg_stmt FROM @yg_sql; EXECUTE yg_stmt; DEALLOCATE PREPARE yg_stmt;
  END IF;
END$$

CREATE DEFINER=`kws_bg`@`localhost` PROCEDURE `yg_add_column_if_missing` (IN `p_table_name` VARCHAR(64), IN `p_column_definition` TEXT)   BEGIN
  DECLARE v_column_name VARCHAR(64);
  DECLARE v_exists INT DEFAULT 0;
  DECLARE v_sql TEXT;

  SET v_column_name = TRIM(p_column_definition);

  IF LEFT(v_column_name, 1) = '`' THEN
    SET v_column_name = SUBSTRING_INDEX(SUBSTRING(v_column_name, 2), '`', 1);
  ELSE
    SET v_column_name = SUBSTRING_INDEX(v_column_name, ' ', 1);
  END IF;

  SELECT COUNT(*)
  INTO v_exists
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = p_table_name
    AND COLUMN_NAME = v_column_name;

  IF v_exists = 0 THEN
    SET v_sql = CONCAT(
      'ALTER TABLE `',
      REPLACE(p_table_name, '`', '``'),
      '` ADD COLUMN ',
      p_column_definition
    );

    SET @yg_sql = v_sql;
    PREPARE stmt FROM @yg_sql;
    EXECUTE stmt;
    DEALLOCATE PREPARE stmt;
  END IF;
END$$

CREATE DEFINER=`kws_bg`@`localhost` PROCEDURE `yg_add_index` (IN `p_table` VARCHAR(64), IN `p_index` VARCHAR(64), IN `p_definition` TEXT)   BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = p_table AND INDEX_NAME = p_index
  ) THEN
    SET @yg_sql = CONCAT('ALTER TABLE `', p_table, '` ', p_definition);
    PREPARE yg_stmt FROM @yg_sql; EXECUTE yg_stmt; DEALLOCATE PREPARE yg_stmt;
  END IF;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Структура таблицы `addresses`
--

CREATE TABLE `addresses` (
  `id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `street` varchar(255) NOT NULL,
  `apartment` varchar(50) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `recipient_name` varchar(100) NOT NULL DEFAULT '',
  `recipient_phone` varchar(20) NOT NULL DEFAULT '',
  `is_primary` tinyint(1) NOT NULL DEFAULT '0',
  `delivery_distance_km` decimal(8,3) DEFAULT NULL,
  `delivery_distance_m` int UNSIGNED DEFAULT NULL,
  `delivery_lat` decimal(10,7) DEFAULT NULL,
  `delivery_lng` decimal(10,7) DEFAULT NULL,
  `delivery_normalized_address` varchar(255) DEFAULT NULL,
  `delivery_distance_provider` varchar(50) DEFAULT NULL,
  `delivery_distance_calculated_at` datetime DEFAULT NULL,
  `delivery_distance_error` text,
  `last_checkout_comment` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Дамп данных таблицы `addresses`
--

INSERT INTO `addresses` (`id`, `user_id`, `street`, `apartment`, `created_at`, `recipient_name`, `recipient_phone`, `is_primary`, `delivery_distance_km`, `delivery_distance_m`, `delivery_lat`, `delivery_lng`, `delivery_normalized_address`, `delivery_distance_provider`, `delivery_distance_calculated_at`, `delivery_distance_error`, `last_checkout_comment`) VALUES
(2, 6, '9мая 75', NULL, '2025-05-22 18:56:33', '', '', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(12, 16, 'Кутузова 20 , 4 подъезд , кв 76', NULL, '2025-06-16 19:42:20', '', '', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(15, 17, 'BerryGO', NULL, '2025-06-22 14:14:16', 'Вика', '79535829980', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(16, 18, 'г Москва, Щёлковское шоссе, д 90 к 1', NULL, '2025-06-25 19:01:01', 'Анна', '79954846102', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(24, 25, 'Самовывоз', NULL, '2025-07-23 09:51:32', 'Татьяна', '79233295055', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(32, 1, 'Самовывоз: 9 мая, 73', NULL, '2025-07-25 10:04:09', 'Администратор', '', 0, NULL, NULL, NULL, NULL, 'Самовывоз: 9 мая, 73', 'pending_review', '2026-07-14 06:21:10', 'DaData не смогла определить координаты адреса. Уточните адрес и попробуйте ещё раз. Ошибка clean/address: DaData clean/address вернул HTTP 403. Forbidden.', ''),
(36, 36, 'Гладкова 25, 67', NULL, '2025-07-25 22:44:58', 'Ольга', '79509736046', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(37, 37, 'Дубенского 2, 25', NULL, '2025-07-25 22:47:32', 'Андрей', '79831556117', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(38, 38, 'Дубровинского 106', NULL, '2025-07-25 22:50:11', 'Евгения', '79607691702', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(41, 41, 'berryGo', NULL, '2025-07-26 08:15:29', 'Вячеслав', '79230195349', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(42, 42, 'Киренского 75', NULL, '2025-07-26 08:33:36', 'Светлана', '79620847993', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(43, 43, 'Самовывоз: 9 мая, 73', NULL, '2025-07-26 08:38:32', 'Ефим', '79333172557', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(44, 44, 'Самовывоз: 9 мая, 73', NULL, '2025-07-26 08:41:33', 'Александра', '79233170979', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(45, 45, 'Лесников 43', NULL, '2025-07-26 08:44:38', 'Наталья', '79830777206', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(46, 46, 'Самовывоз: 9 мая, 73', NULL, '2025-07-26 08:48:28', 'Ирина', '79233364624', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(47, 47, 'Авиаторов 3, 158', NULL, '2025-07-27 09:26:27', 'Марина', '79994426487', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(48, 48, 'Самовывоз: 9 мая, 73', NULL, '2025-07-27 09:28:31', 'Наталья', '79082134737', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(49, 49, 'Самовывоз: 9 мая, 73', NULL, '2025-07-27 09:31:10', 'Марина', '79179875542', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(51, 51, 'Устиновича 36, 23', NULL, '2025-07-29 16:16:02', 'Светлана', '79607656831', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(52, 52, '60 лет Октября 86а', NULL, '2025-07-29 16:18:17', 'Дмитрий', '79230626949', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(53, 53, 'Вильского 24', NULL, '2025-08-02 08:20:50', 'Арина', '79509651124', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(54, 54, 'Волгоградкая 37', NULL, '2025-08-02 10:18:17', 'Руслан', '79832986050', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(55, 55, 'Самовывоз', NULL, '2025-08-02 10:28:14', 'Юлия', '79135258594', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(56, 56, '60 лет Образования', NULL, '2025-08-02 10:54:22', 'Ирина', '79535807417', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(57, 57, 'Самовывоз', NULL, '2025-08-02 11:57:46', 'Светлана', '79080213253', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(58, 58, 'Самовывоз', NULL, '2025-08-03 09:55:05', 'Светлана', '79332001299', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(59, 59, 'Самовывоз', NULL, '2025-08-03 09:57:47', 'Ксения', '79059998100', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(60, 60, 'Самовывоз: 9 мая, 73', NULL, '2025-08-03 10:03:13', 'Клиент', '79233774413', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(61, 61, 'Самовывоз: 9 мая, 73', NULL, '2025-08-03 10:05:27', 'Владимир', '79509959852', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(62, 62, 'Субито, Заводская', NULL, '2025-08-03 10:46:47', 'Татьяна', '79029411930', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(63, 63, 'Матезалки 6', NULL, '2025-08-03 14:40:45', 'Ольга', '79509885558', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(64, 64, 'Самовывоз: 9 мая, 73', NULL, '2025-08-03 15:25:21', 'Клиент', '79964300303', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(65, 65, 'Чкалова 42,19', NULL, '2025-08-03 17:14:35', 'Клиент', '79029788482', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(66, 66, 'Волгоградская улица, 17А, кв 27', NULL, '2025-08-03 21:48:16', 'Александра', '79026864174', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(67, 67, 'Самовывоз: 9 мая, 73', NULL, '2025-08-04 08:03:19', 'Залина', '79135129595', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(68, 68, 'Самовывоз: 9 мая, 73', NULL, '2025-08-04 08:17:13', 'Клиент', '79631918160', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(69, 69, 'Саянская 245', NULL, '2025-08-04 09:57:13', 'Клиент', '79135392645', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(70, 70, 'Самовывоз: 9 мая, 73', NULL, '2025-08-04 10:22:39', 'Кристина', '79131754069', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(71, 71, 'Светлогорская 7', NULL, '2025-08-04 11:18:48', 'Екатерина', '79039225233', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(72, 72, '60 лет образования СССР 35-45', NULL, '2025-08-04 16:40:25', 'Светлана', '79333379547', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(73, 73, '2я Краснофлотская 18', NULL, '2025-08-04 16:47:13', 'Евгений', '79039241950', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(74, 74, 'Самовывоз: 9 мая, 73', NULL, '2025-08-05 08:03:25', 'Клиент', '79135204651', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(75, 75, 'Снт Химик', NULL, '2025-08-05 08:24:35', 'Елена', '79233692449', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(76, 76, 'Самовывоз: 9 мая, 73', NULL, '2025-08-05 09:50:16', 'Татьяна', '79509935445', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(77, 77, 'Самовывоз: 9 мая, 73', NULL, '2025-08-05 10:14:03', 'Елена', '79836168751', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(78, 78, 'Самовывоз', NULL, '2025-08-05 12:38:15', 'Юлия', '79256610460', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(79, 79, 'Парашютная 70а', NULL, '2025-08-05 20:00:13', 'Анна', '79831436880', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(80, 80, 'Свободный 46', NULL, '2025-08-06 09:54:27', 'Екатерина', '79130514209', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(81, 81, 'Пж 59 357', NULL, '2025-08-06 10:54:17', 'Гоар', '79831572234', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(82, 82, 'Самовывоз: 9 мая, 73', NULL, '2025-08-06 10:57:50', 'Вадим', '79069170626', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(83, 83, 'Самовывоз: 9 мая, 73', NULL, '2025-08-06 11:31:03', 'Мария', '79233368885', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(84, 84, 'Красная площадь 9а \"Арника\"', NULL, '2025-08-06 11:35:54', 'Елена', '79504331949', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(85, 85, 'Вильского 6а', NULL, '2025-08-06 15:22:21', 'Клиент', '79233242337', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(86, 86, 'Бограда 15', NULL, '2025-08-06 18:36:10', 'Евгений', '79233008808', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(87, 87, 'Адрес', NULL, '2025-08-06 19:30:45', 'Наталья', '79333303749', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(88, 88, 'Самовывоз: 9 мая, 73', NULL, '2025-08-07 05:47:10', 'Равиль', '79135070008', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(89, 89, 'Лесников 37б', NULL, '2025-08-07 09:04:38', 'Клиент', '79069692927', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(90, 90, 'Самовывоз: 9 мая, 73', NULL, '2025-08-07 11:21:28', 'Надежда', '79293080447', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(91, 91, 'Борисова 10-203', NULL, '2025-08-07 12:03:03', 'Марина', '79232776782', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(92, 92, 'Самовывоз: 9 мая, 73', NULL, '2025-08-08 05:56:49', 'Полина', '79069120828', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(93, 93, 'Металлургов 34', NULL, '2025-08-08 06:42:30', 'Александр', '79135942097', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(94, 94, 'Мясокомбинатская 3-1', NULL, '2025-08-08 07:39:16', 'Клиент', '79964302528', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(95, 95, 'Самовывоз: 9 мая, 73', NULL, '2025-08-08 08:34:22', 'Клиент', '79131700328', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(96, 96, 'Комсомольский 22', NULL, '2025-08-08 08:40:20', 'Ксения', '79232780764', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(97, 97, 'Молодёжный 4', NULL, '2025-08-08 08:50:51', 'Ольга', '79620711423', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(98, 98, 'Самовывоз: 9 мая, 73', NULL, '2025-08-08 11:21:28', 'Коиент', '79509708812', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(99, 99, 'Самовывоз: 9 мая, 73', NULL, '2025-08-08 11:31:05', 'Владимир', '79333313110', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(100, 100, 'Самовывоз: 9 мая, 73', NULL, '2025-08-08 12:05:51', 'Олеся', '79135170799', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(101, 101, 'Алексеева 3-135', NULL, '2025-08-08 13:30:13', 'Клиент', '79241629757', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(102, 102, 'Светлогорская 9-100', NULL, '2025-08-08 15:12:29', 'Екатерина', '79832689108', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(103, 103, 'Самовывоз: 9 мая, 73', NULL, '2025-08-09 10:18:07', 'Ольга', '79617423305', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(104, 104, 'Рейдовая 74-32', NULL, '2025-08-09 10:57:01', 'Екатерина', '79237846153', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(105, 105, 'Самовывоз: 9 мая, 73', NULL, '2025-08-09 19:17:22', 'Павел', '79131898747', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(106, 106, 'Самовывоз: 9 мая, 73', NULL, '2025-08-09 19:18:08', 'Елена', '79135187295', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(107, 107, 'Елены Стасовой 48Е', NULL, '2025-08-12 16:25:18', 'Людмила', '79025505385', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(108, 108, 'berryGo', NULL, '2025-08-20 03:59:50', 'Seller', '79535880614', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(109, 109, '9 мая 73, красрозы', NULL, '2025-08-20 09:49:07', 'Юлия', '79135735517', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(110, 59, 'Самовывоз: 9 мая, 73', NULL, '2025-08-20 10:28:36', 'Ксения', '79059998100', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(112, 111, 'г Красноярск, ул Шахтеров, д 38', NULL, '2025-08-20 11:30:10', 'Наталья', '79959336599', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(114, 112, 'Щорса', NULL, '2025-08-23 15:48:10', 'Клиент', '79082196397', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(115, 113, 'Самовывоз: 9 мая, 73', NULL, '2025-08-23 15:49:06', 'Клиент', '79233034299', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(116, 114, 'Самовывоз: 9 мая, 73', NULL, '2025-08-23 15:51:39', 'Ирина', '79233387774', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(117, 115, 'Чернышевского 98', NULL, '2025-08-24 14:05:12', 'Анастасия', '79504277353', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(118, 116, '.', NULL, '2025-08-25 15:33:25', 'Юрий', '79853315903', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(119, 117, 'Перенсона 2а', NULL, '2025-08-26 18:32:54', 'Анна', '79535916848', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(120, 118, 'Калинина 70б', NULL, '2025-08-26 18:36:59', 'Елена', '79293207718', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(121, 119, 'Самовывоз', NULL, '2025-08-26 18:48:24', 'Светлана', '79831653630', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(122, 119, 'Самовывоз: 9 мая, 73', NULL, '2025-08-26 18:49:47', 'Светлана', '79831653630', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(123, 109, 'Самовывоз: 9 мая, 73', NULL, '2025-08-26 18:55:16', 'Юлия', '79135735517', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(124, 120, 'Кутузова', NULL, '2025-08-27 17:37:25', 'Андрей', '79538509450', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(125, 121, 'Солнечный', NULL, '2025-08-28 06:42:25', 'Марина', '79048987883', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(126, 122, 'Самовывоз: 9 мая, 73', NULL, '2025-08-28 18:52:10', 'Анастасия', '79293392654', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(127, 123, 'Самовывоз: 9 мая, 73', NULL, '2025-08-29 21:26:20', 'Клиент', '79509733747', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(128, 124, 'Самовывоз: 9 мая, 73', NULL, '2025-08-30 09:14:27', 'Клиент', '79509780754', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(129, 125, 'Самовывоз: 9 мая, 73', NULL, '2025-08-30 09:15:22', 'Клиент', '79504243113', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(130, 126, 'Самовывоз: 9 мая, 73', NULL, '2025-08-30 09:16:19', 'Ольга', '79232851479', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(131, 127, 'Самовывоз: 9 мая, 73', NULL, '2025-08-30 09:22:05', 'Сузанна', '79137597006', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(132, 128, 'Комсомольский проспект 4, 6 подъезд', NULL, '2025-08-30 17:12:55', 'Ирина', '79501319190', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(133, 129, 'Самовывоз: 9 мая, 73', NULL, '2025-09-02 18:54:15', 'Клиент', '79233052497', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(134, 130, 'Самовывоз: 9 мая, 73', NULL, '2025-09-04 09:41:01', 'Раиса', '79082040786', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(135, 131, 'Судостроительная 139', NULL, '2025-09-04 11:28:18', 'Юлия', '79233271743', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(136, 132, 'Караульная 42,245', NULL, '2025-09-04 13:14:10', 'Елизавета', '79950734860', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(137, 133, 'Самовывоз: 9 мая, 73', NULL, '2025-09-04 14:46:09', 'Евгения', '79509899603', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(138, 134, 'Самовывоз: 9 мая, 73', NULL, '2025-09-04 16:12:02', 'Клиент', '79994477005', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(139, 135, '.', NULL, '2025-09-05 10:52:04', 'Клиент', '79774675773', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(140, 136, 'Ленина 221А', NULL, '2025-09-05 10:54:51', 'Наталья', '79029215917', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(141, 137, '78 добровольческой бригады 2', NULL, '2025-09-05 17:46:29', 'Татьяна', '79835087340', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(142, 136, 'Самовывоз: 9 мая, 73', NULL, '2025-09-06 10:49:48', 'Наталья', '79029215917', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(143, 138, 'Самовывоз: 9 мая, 73', NULL, '2025-09-06 20:49:22', 'Клиент', '79509821931', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(144, 139, 'Калинина 47м', NULL, '2025-09-07 07:40:47', 'Ксения', '79135214474', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(145, 140, 'Самовывоз', NULL, '2025-09-07 11:43:51', 'Дмитрий', '79835026699', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(146, 140, 'Самовывоз: 9 мая, 73', NULL, '2025-09-07 11:44:16', 'Дмитрий', '79835026699', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(147, 141, 'Матросов 25, кв 61', NULL, '2025-09-07 11:45:48', 'Евгения', '79029275992', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(148, 142, 'Самовывоз: 9 мая, 73', NULL, '2025-09-07 11:53:16', 'Клиент', '79535970033', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(149, 143, 'Самовывоз', NULL, '2025-09-07 12:38:53', 'Клиент', '79509752711', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(150, 143, 'Самовывоз: 9 мая, 73', NULL, '2025-09-07 12:39:54', 'Клиент', '79509752711', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(151, 144, 'Октябрьская 8-159', NULL, '2025-09-07 13:32:16', 'Сергей', '79659006007', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(152, 145, 'Самовывоз', NULL, '2025-09-07 14:38:52', 'Клиент', '79029407256', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(153, 145, 'Самовывоз: 9 мая, 73', NULL, '2025-09-07 14:39:52', 'Клиент', '79029407256', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(154, 146, 'Воронова 24', NULL, '2025-09-08 11:42:52', 'Клиент', '79232953032', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(155, 147, 'Самовывоз: 9 мая, 73', NULL, '2025-09-08 11:43:39', 'Клиент', '79135344068', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(156, 148, '2я огородная 22а', NULL, '2025-09-08 11:52:51', 'Клиент', '79029911999', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(157, 149, 'Алексеева 23, кв 35', NULL, '2025-09-08 12:00:37', 'Татьяна', '79082119151', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(158, 150, 'Калинина 183а, кв 131', NULL, '2025-09-08 13:17:47', 'Евгений', '79607895999', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(159, 151, 'Самовывоз', NULL, '2025-09-08 17:07:44', 'Марк', '79293077566', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(160, 151, 'Самовывоз: 9 мая, 73', NULL, '2025-09-08 17:08:46', 'Марк', '79293077566', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(161, 152, 'Железнодорожников 14, кв 71', NULL, '2025-09-09 07:10:30', 'Клиент', '79232942460', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(162, 153, 'Самовывоз: 9 мая, 73', NULL, '2025-09-09 10:17:57', 'Клиент', '79500801616', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(163, 154, 'Соколовская 70', NULL, '2025-09-09 12:55:58', 'Виктория', '79509681921', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(164, 155, 'Чернышевского 67, кв 136', NULL, '2025-09-09 12:58:31', 'Леонид', '79131709771', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(165, 156, 'Самовывоз: 9 мая, 73', NULL, '2025-09-11 12:46:20', 'Клиент', '79676043126', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(166, 157, 'Мира 34', NULL, '2025-09-11 12:47:20', 'Клиент', '79230175886', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(167, 158, 'Самовывоз', NULL, '2025-09-13 11:32:41', 'Клиент', '79293317775', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(168, 158, 'Самовывоз: 9 мая, 73', NULL, '2025-09-13 11:38:37', 'Клиент', '79293317775', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(169, 159, 'Свердловская 28, 25', NULL, '2025-09-13 11:42:56', 'Клиент', '79039880825', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(170, 160, 'Самовывоз: 9 мая, 73', NULL, '2025-09-13 11:55:11', 'Клиент', '79293065416', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(171, 161, 'Светлогорский переулок 10, кв124', NULL, '2025-09-13 13:44:25', 'Илья', '79174387520', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(172, 162, 'Подзолкова 26', NULL, '2025-09-13 15:58:19', 'Клиент', '79964283797', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(173, 163, 'Забобонова 4, 59', NULL, '2025-09-13 18:17:30', 'Светлана', '79994488130', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(174, 164, '60 лет образования СССР 30, 152', NULL, '2025-09-18 21:06:46', 'Елена', '79996431647', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(175, 165, 'Самовывоз', NULL, '2025-09-19 07:34:24', 'Катерина', '79134487698', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(176, 165, 'Самовывоз: 9 мая, 73', NULL, '2025-09-19 07:35:01', 'Катерина', '79134487698', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(177, 166, 'Самовывоз', NULL, '2025-09-19 08:34:31', 'Дарья', '79504250195', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(178, 166, 'Самовывоз: 9 мая, 73', NULL, '2025-09-19 08:35:37', 'Дарья', '79504250195', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(179, 167, 'Самовывоз: 9 мая, 73', NULL, '2025-09-19 09:20:12', 'Клиент', '79993191845', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(180, 169, 'Самовывоз: 9 мая, 73', NULL, '2025-09-19 13:03:06', 'Лина', '79648116283', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(182, 171, 'Самовывоз: 9 мая, 73', NULL, '2025-09-20 07:49:46', 'Клиент', '79994424727', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(183, 172, 'Взлётная 26а,', NULL, '2025-09-20 14:26:51', 'Евгения', '79082181545', 1, NULL, NULL, NULL, NULL, NULL, 'pending_review', '2026-06-18 17:02:21', 'Выберите адрес из подсказок DaData, чтобы не подставить случайный город/улицу. Найдено вариантов: 8.', ''),
(187, 122, 'Гусарова 25', NULL, '2025-09-22 07:17:54', 'Анастасия', '79293392654', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(188, 175, 'Самовывоз: 9 мая, 73', NULL, '2025-09-22 15:21:53', 'Ананда', '79633143865', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(189, 176, 'Самовывоз', NULL, '2025-09-23 08:23:33', 'Александр', '79509934058', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(190, 176, 'Самовывоз: 9 мая, 73', NULL, '2025-09-23 08:23:58', 'Александр', '79509934058', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(191, 177, 'Судостроительная 113, 2 подъезд', NULL, '2025-09-24 06:57:49', 'Олеся', '79131852859', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(192, 178, 'Свердловская 45, 5 подъезд', NULL, '2025-09-26 10:44:35', 'Алина', '79080167813', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(193, 179, 'Самовывоз', NULL, '2025-09-27 11:03:40', 'Клиент', '79607644573', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(194, 179, 'Самовывоз: 9 мая, 73', NULL, '2025-09-27 11:04:05', 'Клиент', '79607644573', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(195, 180, 'Самовывоз: 9 мая, 73', NULL, '2025-09-28 11:48:26', 'Анастасия', '79039221129', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(196, 181, 'Самовывоз', NULL, '2025-09-30 09:18:20', 'Вадим', '79135724451', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(198, 182, 'Самовывоз: 9 мая, 73', NULL, '2025-09-30 17:31:38', 'Клиент', '79135771792', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(199, 183, 'Самовывоз', NULL, '2025-09-30 18:39:56', 'Ирина', '79029424655', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(200, 183, 'Самовывоз: 9 мая, 73', NULL, '2025-09-30 18:41:46', 'Ирина', '79029424655', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(201, 184, 'Самовывоз', NULL, '2025-09-30 18:42:50', 'Алексей', '79029779555', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(202, 184, 'Самовывоз: 9 мая, 73', NULL, '2025-09-30 18:44:54', 'Алексей', '79029779555', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(203, 116, 'Взлётная 16, 4 подъезд, 124', NULL, '2025-10-01 19:07:55', 'Юрий', '79853315903', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(204, 186, 'Самовывоз: 9 мая, 73', NULL, '2025-10-01 19:37:33', 'Андрей', '79131978364', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(205, 187, 'Самовывоз: 9 мая, 73', NULL, '2025-10-02 08:10:10', 'Владимир', '79235774441', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(206, 188, 'Свободный 28а', NULL, '2025-10-02 10:39:28', 'Маргарита', '79082043587', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(207, 189, 'Самовывоз: 9 мая, 73', NULL, '2025-10-02 12:18:52', 'Елена', '79504018755', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(208, 190, 'Самовывоз: 9 мая, 73', NULL, '2025-10-02 12:19:43', 'Наталья', '79135389278', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(209, 78, 'Самовывоз: 9 мая, 73', NULL, '2025-10-05 17:20:33', 'Юлия', '79256610460', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(210, 193, 'Самовывоз: 9 мая, 73', NULL, '2025-10-06 10:25:00', 'Клиент', '79632561522', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(211, 194, 'Самовывоз: 9 мая, 73', NULL, '2025-10-06 10:26:21', 'Клиент', '79232716088', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(212, 195, 'Самовывоз', NULL, '2025-10-07 18:25:35', 'Александр', '79831666737', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(213, 195, 'Самовывоз: 9 мая, 73', NULL, '2025-10-07 18:25:56', 'Александр', '79831666737', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(214, 196, 'Пограничников 42/1', NULL, '2025-10-07 18:28:18', 'Иван', '79059999501', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(215, 197, 'Самовывоз', NULL, '2025-10-09 05:42:46', 'Анастасия', '79233137376', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(216, 198, 'Самовывоз', NULL, '2025-10-09 05:47:24', 'Ольга', '79135692746', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(217, 198, 'Самовывоз: 9 мая, 73', NULL, '2025-10-09 05:48:14', 'Ольга', '79135692746', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(218, 199, 'Самовывоз', NULL, '2025-10-09 07:31:37', 'Лариса', '79233292900', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(219, 199, 'Самовывоз: 9 мая, 73', NULL, '2025-10-09 07:32:21', 'Лариса', '79233292900', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(220, 199, 'Самовывоз: 9 мая, 73', NULL, '2025-10-09 07:40:50', 'Юлия', '79233292900', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(221, 200, 'Самовывоз', NULL, '2025-10-09 07:42:46', 'Лариса', '79232642859', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(222, 200, 'Самовывоз: 9 мая, 73', NULL, '2025-10-09 07:43:13', 'Лариса', '79232642859', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(223, 202, 'Республики 19', NULL, '2025-10-11 18:23:17', 'Ольга', '79131893930', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(225, 204, 'Самовывоз', NULL, '2025-10-12 17:12:10', 'Павел', '79233324167', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(226, 205, 'Самовывоз', NULL, '2025-10-12 17:17:28', 'Павел', '79069165489', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(227, 206, 'Самовывоз: 9 мая, 73', NULL, '2025-10-13 18:25:08', 'Клиент', '79676169080', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(228, 203, 'Комсомольский 3д 3 подьезд', NULL, '2025-10-14 10:12:24', '', '', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(229, 207, 'Лесникова 23', NULL, '2025-10-15 18:57:24', 'Клиент', '79135629334', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(230, 208, 'Северный проезд 6  1 подъезд  25 квартира', NULL, '2025-10-16 09:01:06', 'Виктория', '79135822173', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(231, 209, 'Самовывоз: 9 мая, 73', NULL, '2025-10-17 08:29:59', 'Андрей', '79607629696', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(232, 210, 'Самовывоз: 9 мая, 73', NULL, '2025-10-17 08:54:12', 'Кристина', '79233572317', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(233, 203, 'Самовывоз: 9 мая, 73', NULL, '2025-10-17 09:16:16', 'Эльмира', '79233017137', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(234, 211, 'Крайняя 14А, 6п', NULL, '2025-10-18 10:29:05', 'Татьяна', '79135869573', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(235, 212, 'Белинского 3д', NULL, '2025-10-18 11:15:37', 'Студия сигма', '79631919361', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(236, 213, 'Самовывоз', NULL, '2025-10-19 07:50:10', 'Любовь', '79535970426', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(237, 214, 'Самовывоз: 9 мая, 73', NULL, '2025-10-20 19:46:59', 'Ксения', '79620788036', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(238, 215, 'Самовывоз: 9 мая, 73', NULL, '2025-10-21 08:01:01', 'Сергей', '79293337080', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(239, 216, 'Киренского 67', NULL, '2025-10-21 10:23:45', 'Галина', '79831695666', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(240, 217, 'Самовывоз: 9 мая, 73', NULL, '2025-10-21 12:52:01', 'Клиент', '79230005556', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(241, 218, 'Самовывоз: 9 мая, 73', NULL, '2025-10-22 14:08:18', 'Move', '79080199619', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(242, 219, 'Самовывоз: 9 мая, 73', NULL, '2025-10-22 20:21:25', 'Юлия', '79135655155', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(243, 220, 'Самовывоз: 9 мая, 73', NULL, '2025-10-23 10:41:40', 'Татьяна', '79607708140', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(244, 221, 'Самовывоз: 9 мая, 73', NULL, '2025-10-23 14:32:37', 'Клиент', '79235702858', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(245, 222, 'Самовывоз: 9 мая, 73', NULL, '2025-10-23 17:36:09', 'Александр', '79233060547', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(246, 223, 'Самовывоз: 9 мая, 73', NULL, '2025-10-23 18:08:11', 'Руслан', '79137144115', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(247, 224, 'Лесникова 55', NULL, '2025-10-24 19:07:21', 'Максим', '79143591006', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(248, 225, 'Самовывоз: 9 мая, 73', NULL, '2025-10-25 14:27:31', 'Аркадий', '79535880611', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(249, 226, 'Самовывоз: 9 мая, 73', NULL, '2025-10-26 14:18:34', 'Денис', '79039237465', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(250, 227, 'Воронова 35', NULL, '2025-12-12 08:01:42', 'Екатерина', '79080193180', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(251, 227, 'Партизана Железняка 1', NULL, '2025-12-12 08:10:05', 'Екатерина', '79080193180', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(252, 228, 'Чернышевского 98,14', NULL, '2026-01-08 19:06:45', 'Людмила', '79135372581', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(253, 229, 'ул. Кольцевая, д. 6 кв. 30', NULL, '2026-01-23 13:45:59', 'Вадим', '79135937602', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(254, 108, 'Самовывоз: 9 мая, 73', NULL, '2026-03-24 19:09:00', 'Юрий', '79535880614', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(255, 230, 'Бограда, 111-18', NULL, '2026-04-10 23:18:12', 'Екатерина', '79535851533', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(256, 231, 'Народная 2-2', NULL, '2026-05-06 14:32:54', 'София Евгеньевна Григорьева', '79620777163', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(257, 232, 'Шевченко 86, кв 64', NULL, '2026-05-08 14:23:36', 'Клиент', '79835000629', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(258, 233, 'Самовывоз: 9 мая, 73', NULL, '2026-05-08 14:25:38', 'Виктория', '79833708548', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(259, 234, 'Самовывоз: 9 мая, 73', NULL, '2026-05-08 14:27:07', 'Марина', '79059743133', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(260, 235, 'Самовывоз: 9 мая, 73', NULL, '2026-05-08 14:28:57', 'Дмитрий', '79029135286', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(261, 236, 'Самовывоз: 9 мая, 73', NULL, '2026-05-08 16:29:36', 'Клиент', '79832091133', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(262, 237, 'Киренского 2А', NULL, '2026-05-11 06:54:57', 'Елена', '79607608228', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(263, 238, 'Матросова 40', NULL, '2026-05-11 07:00:48', 'Надежда', '79233067611', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(264, 239, 'Самовывоз: 9 мая, 73', NULL, '2026-05-11 07:06:18', 'Арина', '79025391775', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(265, 240, 'Самовывоз: 9 мая, 73', NULL, '2026-05-11 07:07:19', 'Евгений', '79135322858', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(266, 241, 'Мартынова 11, кв 6', NULL, '2026-05-11 16:55:15', 'Ляйсан', '79134765190', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(267, 242, 'Самовывоз: 9 мая, 73', NULL, '2026-05-13 18:54:04', 'Полина', '79048935836', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(268, 243, 'Новосибирскся 31, 3 подъезд, 7 этаж , квартира 78', NULL, '2026-05-14 09:24:34', 'Юлия', '79232807787', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(269, 244, 'Проспект молодёжный 7, кв.97', NULL, '2026-05-14 09:31:36', 'Светлана', '79135860727', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(270, 245, '6я полярная 2а, 2п', NULL, '2026-05-14 13:06:26', 'Мария', '79641269888', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(271, 246, 'Самовывоз: 9 мая, 73', NULL, '2026-05-14 13:09:33', 'Илья', '79659044557', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(272, 247, 'Самовывоз: 9 мая, 73', NULL, '2026-05-14 18:32:04', 'Сергей', '79029409325', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(273, 248, 'Самовывоз: 9 мая, 73', NULL, '2026-05-14 18:34:19', 'Дарья', '79135127471', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(274, 249, 'Самовывоз: 9 мая, 73', NULL, '2026-05-15 10:10:01', 'Энергопоток', '79029208094', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(275, 250, 'Самовывоз: 9 мая, 73', NULL, '2026-05-15 11:41:25', 'Игорь', '79059762593', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(276, 251, 'Самовывоз: 9 мая, 73', NULL, '2026-05-15 13:48:00', 'Федя', '79048905534', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(277, 252, 'Батурина 30к1, кв 29', NULL, '2026-05-15 19:15:07', 'Яна', '79964303402', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(278, 253, 'Самовывоз: 9 мая, 73', NULL, '2026-05-15 19:34:27', 'Клиент', '79082062131', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(279, 254, 'Самовывоз: 9 мая, 73', NULL, '2026-05-17 09:32:45', 'Екатерина', '79029912006', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(280, 255, 'Весны 5, кв 309', NULL, '2026-05-17 10:33:00', 'Сергей', '79994482127', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(281, 256, 'Самовывоз: 9 мая, 73', NULL, '2026-05-19 18:23:06', 'Сладко', '79339972322', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(282, 257, 'Калинина 41в, кв 10', NULL, '2026-05-19 18:24:36', 'Илья', '79509791039', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(283, 258, 'Самовывоз: 9 мая, 73', NULL, '2026-05-20 17:30:21', 'Алена', '79874839938', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(284, 259, 'Калинина 41в, кв 219', NULL, '2026-05-20 17:44:37', 'Анна', '79233398374', 1, NULL, NULL, NULL, NULL, NULL, 'pending_review', '2026-06-06 09:31:27', 'Выберите адрес из подсказок DaData, чтобы не подставить случайный город/улицу. Найдено вариантов: 1.', ''),
(285, 260, 'Самовывоз: 9 мая, 73', NULL, '2026-05-24 10:47:21', 'Агния', '79509874119', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(286, 261, 'Менжинского 9, 6п, 88кв', NULL, '2026-05-25 14:21:36', 'Владимир', '79994483743', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(287, 262, 'Самовывоз: 9 мая, 73', NULL, '2026-05-25 16:16:41', 'Клиент', '79535863713', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(288, 263, 'Академика Вавилова 94', NULL, '2026-05-27 17:30:55', 'Александр', '79501202782', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(289, 264, 'Самовывоз: 9 мая, 73', NULL, '2026-05-28 10:22:39', 'Строительный Холдинг', '79620717871', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(290, 265, 'Ады Лебедевой 31, кв 139, 5 п', NULL, '2026-05-28 17:32:51', 'Анатолий', '79333310408', 1, NULL, NULL, NULL, NULL, NULL, 'pending_review', '2026-06-12 20:27:06', 'DaData не смогла определить координаты адреса. Уточните адрес и попробуйте ещё раз. Ошибка clean/address: DaData clean/address вернул HTTP 403. Forbidden.', ''),
(291, 172, 'Взлетная 26а, кв 73, п3', NULL, '2026-05-28 17:35:31', 'Евгения', '79082181545', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(292, 266, 'Самовывоз: 9 мая, 73', NULL, '2026-06-02 11:01:29', 'Светлана Зыкова', '79135093677', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(294, 267, 'Красноярск, ул. Лесников, д. 25, кВ. 906', NULL, '2026-06-02 14:26:24', 'Михаил', '79041399912', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(295, 116, 'Самовывоз: 9 мая, 73', NULL, '2026-06-03 10:08:40', 'Юрий', '79853315903', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(296, 117, 'Самовывоз: 9 мая, 73', NULL, '2026-06-03 10:11:24', 'Анна', '79535916848', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(297, 1, 'Самовывоз: 9 мая, 73', NULL, '2026-06-03 10:20:30', 'Юрий', '', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(298, 1, 'г Красноярск, ул Карла Маркса, д 96', NULL, '2026-06-05 17:46:38', 'Юрий', '', 0, 16.000, 16000, NULL, NULL, 'г Красноярск, ул Карла Маркса, д 96', 'manual', '2026-06-05 17:49:05', NULL, ''),
(299, 268, 'Апрельская 1', NULL, '2026-06-06 09:35:20', 'Ксения', '79080141979', 1, NULL, NULL, NULL, NULL, NULL, 'pending_review', '2026-06-06 09:35:21', 'Выберите адрес из подсказок DaData, чтобы не подставить случайный город/улицу. Найдено вариантов: 8.', 'Подъезд 9'),
(300, 269, 'г Красноярск, ул 9 Мая, д 49', NULL, '2026-06-10 07:00:13', 'Валерия', '79509935885', 1, 1.237, 1237, 56.0610000, 92.9210000, 'г Красноярск, ул 9 Мая, д 49', 'tariff_zone', '2026-06-10 07:00:14', NULL, 'Доставка в 10:00. Ящик с крупной ягодой'),
(301, 270, 'Самовывоз: 9 мая, 73', NULL, '2026-06-12 20:23:43', 'Ольга', '79082072188', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(302, 271, 'Самовывоз: 9 мая, 73', NULL, '2026-06-12 20:33:57', 'Сам', '79131826668', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(303, 272, 'Металлургов 28 Б кВ 9', NULL, '2026-06-13 10:28:19', 'Ангелина', '79080104768', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(304, 273, 'г Красноярск, ул Щорса, д 87', NULL, '2026-06-16 15:50:48', 'Виктория', '79233751300', 1, 10.542, 10542, 55.9870000, 92.9370000, 'г Красноярск, ул Щорса, д 87', 'tariff_zone', '2026-06-16 15:50:48', NULL, ''),
(305, 274, 'Самовывоз: 9 мая, 73', NULL, '2026-06-17 15:37:07', 'Клиент', '79333172366', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(306, 275, 'г Красноярск, ул Батурина, д 30 к 26', NULL, '2026-06-17 17:09:39', 'Клиент', '79131950833', 1, 2.227, 2227, 56.0420000, 92.9010000, 'г Красноярск, ул Батурина, д 30 к 26', 'tariff_zone', '2026-06-17 17:09:39', NULL, ''),
(307, 276, 'Самовывоз: 9 мая, 73', NULL, '2026-06-17 17:11:08', 'Клиент', '79080228190', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(308, 277, 'г Красноярск, Комсомольский пр-кт, д 3Ж', NULL, '2026-06-18 17:03:55', 'Андрей', '79011596565', 1, 2.086, 2086, 56.0600000, 92.9310000, 'г Красноярск, Комсомольский пр-кт, д 3Ж', 'tariff_zone', '2026-06-18 17:03:55', NULL, ''),
(309, 278, 'Самовывоз: 9 мая, 73', NULL, '2026-06-18 17:06:05', 'Владимир', '79233486829', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(310, 279, 'Кутузова 50', NULL, '2026-06-22 23:31:35', 'Ирина', '79504212156', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(311, 280, 'Петра Ломако, 4', NULL, '2026-06-23 15:58:59', 'Софья', '79130597194', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(312, 281, 'Судостроительная 90, подьезд 4,кв 165,9 этаж.', NULL, '2026-06-23 16:10:03', 'Егор', '79509727775', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(313, 282, 'Ул. Красномосковская, 19', NULL, '2026-06-26 05:55:48', 'Коптев Андрей', '79039208061', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(314, 283, 'Семафорная', NULL, '2026-06-26 15:00:46', 'Дарья', '79504175724', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(315, 287, '60 лет октября 49', NULL, '2026-06-26 15:03:25', 'Дарья Владимировна Пономарева', '79330234983', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(316, 288, 'Лесников 25а 150', NULL, '2026-06-30 14:34:05', 'Никита', '79333297169', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(317, 289, 'Воронова 18в', NULL, '2026-06-30 17:42:13', 'Диана', '79232747164', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(318, 290, 'Балахтинская, 108 кв 1', NULL, '2026-06-30 20:35:34', 'Оксана Васильевна Воротникова', '79029433322', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(319, 291, 'Красноярск, Ярыгинская Набережная, д.3, кв. 107', NULL, '2026-07-01 00:58:36', 'Эля', '79004122012', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(320, 292, 'Микуцкого дом 2', NULL, '2026-07-01 13:39:42', 'Ирина Сергеевна Боброва', '79029781670', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(321, 293, 'Пр. Ульяновский дом 8 квартира 66', NULL, '2026-07-01 15:21:28', 'Наталья', '79135729280', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(322, 294, 'Ангарская д.2 кв.14', NULL, '2026-07-03 13:34:06', 'Аникеева Наталья Александровна', '79233003110', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(323, 295, 'Сергея Лазо 28, квартира 125', NULL, '2026-07-04 11:04:09', 'Варвара', '79509782638', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(324, 296, '9 мая 73', NULL, '2026-07-06 13:45:52', 'Berry Me Please', '79994405042', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(325, 297, 'Красноярский край, Красноярск г, 60 лет Октября ул, 40', NULL, '2026-07-06 18:07:58', 'Юлия', '79233741556', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Структура таблицы `cart_items`
--

CREATE TABLE `cart_items` (
  `id` bigint UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `product_id` int UNSIGNED NOT NULL,
  `quantity` decimal(8,2) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `purchase_batch_id` int UNSIGNED DEFAULT NULL,
  `purchase_batch_key` int UNSIGNED GENERATED ALWAYS AS (ifnull(`purchase_batch_id`,0)) STORED,
  `stock_mode` enum('preorder','instant','discount_stock') NOT NULL DEFAULT 'instant',
  `boxes` decimal(10,2) NOT NULL DEFAULT '0.00',
  `sale_price_per_box` decimal(10,2) NOT NULL DEFAULT '0.00'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Дамп данных таблицы `cart_items`
--

INSERT INTO `cart_items` (`id`, `user_id`, `product_id`, `quantity`, `unit_price`, `purchase_batch_id`, `stock_mode`, `boxes`, `sale_price_per_box`) VALUES
(1, 25, 6, 1.00, 1624.00, NULL, 'instant', 1.00, 1624.00),
(2, 66, 24, 1.00, 1374.00, NULL, 'instant', 1.00, 1374.00),
(3, 89, 19, 1.00, 1200.00, NULL, 'instant', 1.00, 1200.00),
(4, 89, 20, 1.00, 1200.00, NULL, 'instant', 1.00, 1200.00),
(5, 116, 9, 2.00, 1500.00, NULL, 'instant', 2.00, 1500.00),
(6, 126, 9, 1.00, 1500.00, NULL, 'instant', 1.00, 1500.00),
(7, 228, 21, 1.00, 1500.00, NULL, 'instant', 1.00, 1500.00),
(8, 229, 20, 1.00, 1500.00, NULL, 'instant', 1.00, 1500.00),
(9, 231, 7, 1.00, 1100.00, NULL, 'instant', 1.00, 1100.00),
(10, 233, 7, 1.00, 1100.00, NULL, 'instant', 1.00, 1100.00),
(11, 273, 7, 1.00, 1500.00, 34, 'instant', 1.00, 1500.00),
(12, 281, 7, 1.00, 1500.00, 40, 'instant', 1.00, 1500.00),
(13, 294, 6, 1.00, 1800.00, 28, 'instant', 1.00, 1800.00),
(14, 294, 11, 1.00, 750.00, 41, 'instant', 1.00, 750.00),
(15, 297, 7, 1.00, 1500.00, 39, 'instant', 1.00, 1500.00);

-- --------------------------------------------------------

--
-- Структура таблицы `content_categories`
--

CREATE TABLE `content_categories` (
  `id` int NOT NULL,
  `name` varchar(255) NOT NULL,
  `alias` varchar(255) NOT NULL,
  `meta_title` varchar(255) DEFAULT NULL,
  `meta_description` varchar(255) DEFAULT NULL,
  `meta_keywords` varchar(255) DEFAULT NULL,
  `in_sitemap` tinyint(1) NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Дамп данных таблицы `content_categories`
--

INSERT INTO `content_categories` (`id`, `name`, `alias`, `meta_title`, `meta_description`, `meta_keywords`, `in_sitemap`) VALUES
(1, 'О клубнике', 'clubnika', 'О клубнике — сорта, вкус, доставка и выбор | berryGo', 'Полезные материалы о клубнике: сорта Клери, Черный Принц, Альбион, клубника из Киргизии, хранение и выбор свежей ягоды.', 'клубника, сорта клубники, клубника Красноярск, клубника Клери, купить клубнику', 1),
(2, 'Покупателю', 'pokupatelyu', NULL, NULL, NULL, 1);

-- --------------------------------------------------------

--
-- Структура таблицы `coupons`
--

CREATE TABLE `coupons` (
  `id` int UNSIGNED NOT NULL,
  `code` varchar(50) NOT NULL,
  `discount` decimal(5,2) NOT NULL COMMENT 'Сумма или % скидки',
  `expires_at` datetime DEFAULT NULL COMMENT 'Дата окончания действия',
  `is_active` tinyint(1) NOT NULL DEFAULT '1' COMMENT '1 — активен, 0 — не активен',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `points` int UNSIGNED NOT NULL DEFAULT '0' COMMENT 'Количество баллов',
  `type` varchar(20) NOT NULL DEFAULT 'discount' COMMENT 'Тип купона'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Промокоды и акции';

--
-- Дамп данных таблицы `coupons`
--

INSERT INTO `coupons` (`id`, `code`, `discount`, `expires_at`, `is_active`, `created_at`, `points`, `type`) VALUES
(1, '89ED7900', 10.00, '2025-06-30 00:00:00', 1, '2025-06-08 18:09:08', 0, 'discount'),
(2, '8DBF8152', 15.00, '2025-06-17 00:00:00', 1, '2025-06-16 12:02:10', 0, 'discount');

-- --------------------------------------------------------

--
-- Структура таблицы `delivery_slots`
--

CREATE TABLE `delivery_slots` (
  `id` int UNSIGNED NOT NULL,
  `time_from` time NOT NULL,
  `time_to` time NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Дамп данных таблицы `delivery_slots`
--

INSERT INTO `delivery_slots` (`id`, `time_from`, `time_to`) VALUES
(3, '08:00:00', '12:00:00'),
(4, '12:00:00', '15:00:00'),
(5, '15:00:00', '18:00:00'),
(6, '18:00:00', '22:00:00');

-- --------------------------------------------------------

--
-- Структура таблицы `delivery_tariff_zones`
--

CREATE TABLE `delivery_tariff_zones` (
  `id` int UNSIGNED NOT NULL,
  `min_km` decimal(8,3) NOT NULL,
  `max_km` decimal(8,3) DEFAULT NULL,
  `price_rub` int UNSIGNED NOT NULL,
  `sort_order` int NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Дамп данных таблицы `delivery_tariff_zones`
--

INSERT INTO `delivery_tariff_zones` (`id`, `min_km`, `max_km`, `price_rub`, `sort_order`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 0.000, 2.000, 300, 1, 1, '2026-06-04 08:40:15', '2026-06-05 16:43:36'),
(2, 2.000, 5.000, 350, 2, 1, '2026-06-04 08:40:15', '2026-06-05 16:43:36'),
(3, 5.000, 8.000, 400, 3, 1, '2026-06-04 08:40:15', '2026-06-05 16:43:36'),
(4, 8.000, 12.000, 450, 4, 1, '2026-06-04 08:40:15', '2026-06-05 16:43:36'),
(5, 12.000, 16.000, 500, 5, 1, '2026-06-05 16:43:36', '2026-06-05 16:43:36'),
(6, 16.000, 18.000, 600, 6, 1, '2026-06-05 16:43:36', '2026-06-05 16:43:36'),
(7, 18.000, 20.000, 700, 7, 1, '2026-06-05 16:43:36', '2026-06-05 16:43:36'),
(8, 20.000, 22.000, 800, 8, 1, '2026-06-05 16:43:36', '2026-06-05 16:43:36'),
(9, 22.000, 24.000, 950, 9, 1, '2026-06-05 16:43:36', '2026-06-05 16:43:36'),
(10, 24.000, 27.000, 1050, 10, 1, '2026-06-05 16:43:36', '2026-06-05 16:43:36');

-- --------------------------------------------------------

--
-- Структура таблицы `favorites`
--

CREATE TABLE `favorites` (
  `user_id` int UNSIGNED NOT NULL,
  `product_id` int UNSIGNED NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `mailing_clients`
--

CREATE TABLE `mailing_clients` (
  `id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `nalet_number` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT '',
  `allow_mailing` tinyint(1) NOT NULL DEFAULT '0',
  `comment` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `mailing_clients`
--

INSERT INTO `mailing_clients` (`id`, `user_id`, `nalet_number`, `allow_mailing`, `comment`, `created_at`, `updated_at`) VALUES
(1, 137, '', 1, NULL, '2025-10-19 05:46:08', '2025-10-19 06:00:11'),
(6, 93, '', 1, NULL, '2025-10-19 05:55:28', '2025-10-19 05:55:28'),
(9, 72, '', 1, NULL, '2025-10-19 06:04:57', '2025-10-19 06:05:03'),
(11, 148, '', 0, 'взяла ягоду, поела, потом решила заменить, и все-равно осталась недовольной', '2025-10-19 06:08:35', '2025-10-19 09:53:58');

-- --------------------------------------------------------

--
-- Структура таблицы `materials`
--

CREATE TABLE `materials` (
  `id` int NOT NULL,
  `category_id` int NOT NULL,
  `alias` varchar(255) NOT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `short_desc` text,
  `text` text,
  `meta_title` varchar(255) DEFAULT NULL,
  `meta_description` varchar(255) DEFAULT NULL,
  `meta_keywords` varchar(255) DEFAULT NULL,
  `product1_id` int DEFAULT NULL,
  `product2_id` int DEFAULT NULL,
  `product3_id` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `in_sitemap` tinyint(1) NOT NULL DEFAULT '1',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `show_on_home` tinyint(1) NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Дамп данных таблицы `materials`
--

INSERT INTO `materials` (`id`, `category_id`, `alias`, `image_path`, `title`, `short_desc`, `text`, `meta_title`, `meta_description`, `meta_keywords`, `product1_id`, `product2_id`, `product3_id`, `created_at`, `updated_at`, `in_sitemap`, `is_active`, `show_on_home`) VALUES
(1, 1, 'gde-kupit-klubniku', '/uploads/mat_6859320c571690.26048822.webp', 'Где купить клубнику в Красноярске: Клери, Черный Принц и Альбион', 'Подсказываем, где заказать свежую клубнику в Красноярске, чтобы она была сладкой, красивой и приехала в хорошем состоянии.', 'Когда хочется вкусной клубники, важно не только найти, где её купить, но и быть уверенным, что ягода приедет свежей, ароматной и аккуратной. Хорошая клубника должна радовать сразу: открываешь коробку и понимаешь, что это именно тот вкус лета, ради которого её и ждут.\r\n\r\nВ berryGo можно заказать клубнику с доставкой по Красноярску в удобных фасовках 2 и 3 кг. У нас есть разные сорта, чтобы каждый мог выбрать ягоду под свой вкус: кому-то нравится особенно сладкая, кому-то — более ароматная, кому-то хочется взять побольше для семьи или гостей.\r\n\r\nМы работаем с прямыми поставками из Киргизии, а после поступления храним ягоду в помещении с холодильным температурным режимом. Это помогает сохранить свежесть, вкус и красивый внешний вид до доставки.\r\n\r\nЗаказывать у нас удобно и выгодно: действуют скидки, акции и кешбэк баллами за свои покупки и приглашённых друзей. Если хочется купить свежую клубнику в Красноярске и просто с удовольствием поесть по-настоящему вкусную ягоду, berryGo — очень подходящее место.', 'Где купить клубнику в Красноярске | Клери из Киргизии | berryGo', 'Где купить свежую клубнику в Красноярске? Клери, Черный Принц и Альбион с доставкой по городу. Ящики 2 кг и 3 кг.', 'купить клубнику Красноярск, клубника Красноярск, клубника Клери Красноярск, доставка клубники Красноярск', 10, 6, 14, '2025-06-23 09:38:59', '2026-06-14 08:13:56', 1, 1, 1),
(2, 1, 'sezon-chereshni', '/uploads/mat_685d6da159cf00.56061952.webp', 'Сезон черешни на финишной прямой!', 'Успейте насладиться сочной черешней от BerryGO — до конца июня осталось совсем немного, а вместе с ним заканчивается и сезон этих летних ягод', 'Успейте насладиться сочной черешней от berryGo — до конца июня осталось совсем немного, а вместе с ним заканчивается и сезон этих летних ягод. Мы доставляем в Красноярске три изысканных сорта черешни, каждый со своим неповторимым вкусом:\r\n\r\n🍒 Белая черешня — нежная, сладкая и практически без кислоты. Её янтарно-жёлтые плоды — идеальный выбор для детского рациона и тех, кто любит мягкий вкус.\r\n\r\n🌸 Розовая черешня — сочная и ароматная, с тонкой кожицей и сбалансированным вкусом. Настоящее летнее лакомство, которое тает во рту.\r\n\r\n🍷 Тёмно-бордовая черешня — самая насыщенная по вкусу. Её плотная мякоть и глубокий винный оттенок — выбор для настоящих ценителей.\r\n\r\nЗаказывайте свежую черешню с доставкой по Красноярску прямо сейчас — пока сезон не закончился!', 'Черешня в Красноярске – белая, розовая и тёмно-бордовая | Доставка от berryGo', 'Успей купить свежую черешню в Красноярске – белую, розовую и тёмно-бордовую. Сочный вкус лета с доставкой на дом от berryGo. Сезон скоро закончится!', 'черешня Красноярск, купить черешню, белая черешня, розовая черешня, тёмная черешня, свежие фрукты доставка, berryGo доставка фруктов, сезон черешни', 6, 15, 16, '2025-06-26 15:34:28', '2026-05-08 02:31:11', 0, 1, 1),
(33, 1, 'clubnika-kleri-opisanie-vkus-otzyvy', NULL, 'Клубника Клери: описание сорта, вкус и отзывы покупателей', 'Клери — один из самых популярных сортов клубники для еды: сладкая, ароматная, плотная и красивая ягода.', 'Клубника Клери ценится за яркий вкус, красивую форму и плотную мякоть. Это сорт, который хорошо подходит для доставки: ягода не превращается в кашу, сохраняет внешний вид и аромат. У Клери обычно выраженная сладость, легкая кислинка и насыщенный клубничный запах. В berryGo Клери часто выбирают для семьи, детям, к чаю и для подарочного ящика. Если вы хотите купить клубнику Клери в Красноярске, обращайте внимание на свежесть поставки, цвет ягоды и условия хранения.', 'Клубника Клери — описание сорта, вкус, отзывы | Купить в Красноярске', 'Клубника Клери: описание сорта, вкус, отзывы покупателей. Свежая клубника Клери с доставкой по Красноярску от berryGo.', 'клубника Клери, клубника Клери описание, клубника Клери отзывы, купить клубнику Клери Красноярск', NULL, NULL, NULL, '2026-06-14 08:38:08', '2026-06-14 08:38:08', 1, 1, 1),
(34, 1, 'clubnika-cherniy-princ-otlichiya-ot-kleri', NULL, 'Клубника Черный Принц: чем отличается от Клери', 'Черный Принц — темная, насыщенная и ароматная клубника для тех, кто любит глубокий вкус.', 'Клубника Черный Принц отличается более темным цветом, насыщенным ароматом и плотной ягодой. По вкусу она часто кажется глубже и ярче, чем классическая светло-красная клубника. Клери обычно выбирают за сладость и красоту, а Черный Принц — за выраженный ягодный вкус. Для доставки по Красноярску этот сорт удобен за счет плотности и хорошей сохранности. В berryGo Черный Принц можно использовать как самостоятельный ящик или добавить в MIX-набор.', 'Клубника Черный Принц — описание сорта и отличия от Клери', 'Чем клубника Черный Принц отличается от Клери: вкус, цвет, плотность и выбор для доставки по Красноярску.', 'клубника Черный Принц, клубника принц, клубника черный принц описание, клубника Клери', NULL, NULL, NULL, '2026-06-14 08:38:08', '2026-06-14 08:38:08', 1, 1, 1),
(35, 1, 'luchshie-sorta-klubniki-dlya-edy', NULL, 'Лучшие сорта клубники для еды: Клери, Черный Принц и Альбион', 'Разбираем сорта клубники, которые лучше всего подходят именно для еды, доставки и свежего ящика домой.', 'Для еды лучше выбирать сорта с хорошим балансом сладости, аромата и плотности. Клери подходит тем, кто любит сладкую красивую ягоду. Черный Принц понравится тем, кто ищет насыщенный вкус. Альбион часто выбирают за крупный размер и плотную структуру. При покупке клубники в Красноярске важно смотреть не только на сорт, но и на свежесть поставки, температуру хранения и состояние ягоды в ящике.', 'Лучшие сорта клубники для еды | Клери, Черный Принц, Альбион', 'Какие сорта клубники самые вкусные для еды: Клери, Черный Принц, Альбион. Советы berryGo для покупателей в Красноярске.', 'сорта клубники, лучшие сорта клубники, какая клубника лучше, клубника Клери, клубника Альбион', NULL, NULL, NULL, '2026-06-14 08:38:08', '2026-06-14 08:38:08', 1, 1, 1),
(36, 1, 'kak-vybrat-sladkuyu-klubniku', NULL, 'Как выбрать сладкую клубнику и не купить кислую', 'Простая инструкция для покупателя: как понять, что клубника свежая, сладкая и не залежалась.', 'Сладкая клубника обычно имеет ровный насыщенный цвет, выраженный аромат и сухую поверхность. Слишком водянистая ягода может быстро испортиться, а ягода без запаха часто оказывается менее вкусной. При выборе ящика смотрите на нижний слой: там не должно быть сока, плесени и размятых ягод. В berryGo клубнику проверяют перед продажей, поэтому покупатель получает свежую ягоду, подходящую для еды, детей, десертов и подарка.', 'Как выбрать сладкую клубнику | Советы перед покупкой', 'Как выбрать сладкую свежую клубнику и не купить кислую: цвет, аромат, плотность, состояние ящика. Советы berryGo.', 'как выбрать клубнику, сладкая клубника, свежая клубника, вкус клубники, купить клубнику Красноярск', NULL, NULL, NULL, '2026-06-14 08:38:08', '2026-06-14 08:38:08', 1, 1, 1),
(37, 1, 'clubnika-iz-kirgizii-pochemu-sladkaya', NULL, 'Клубника из Киргизии: почему она такая сладкая', 'Киргизская клубника ценится за солнце, аромат и насыщенный вкус. Объясняем, чем она отличается.', 'Клубника из Киргизии стала популярной в Красноярске из-за яркого вкуса, аромата и хорошего соотношения цены и качества. Южное солнце, короткий сезон и быстрая поставка делают такую ягоду особенно привлекательной для покупателей. В berryGo мы делаем акцент на свежих партиях, понятной фасовке и доставке по Красноярску. Киргизская клубника хорошо подходит для еды, семейного ящика и предзаказа на следующую поставку.', 'Клубника из Киргизии — купить в Красноярске с доставкой', 'Почему клубника из Киргизии сладкая и ароматная. Свежая киргизская клубника с доставкой по Красноярску от berryGo.', 'клубника из Киргизии, киргизская клубника, клубника Киргизия Красноярск, купить клубнику из Киргизии', NULL, NULL, NULL, '2026-06-14 08:38:08', '2026-06-14 08:38:08', 1, 1, 1),
(38, 1, 'gde-kupit-svezhuyu-klubniku-v-krasnoyarske', NULL, 'Где купить свежую клубнику в Красноярске', 'Гид для покупателей: где искать свежую клубнику, как не переплатить и почему удобнее заказать доставку.', 'Свежую клубнику в Красноярске можно купить на рынке, у сезонных продавцов или заказать с доставкой. Главное отличие berryGo — понятная фасовка, свежие поставки и возможность выбрать клубнику онлайн. Мы продаем клубнику ящиками 2 кг и 3 кг, а также сезонные ягоды и фрукты. Если вы ищете, где купить клубнику в Красноярске, выбирайте не только цену, но и свежесть, сорт, условия хранения и скорость доставки.', 'Где купить клубнику в Красноярске | Свежая клубника berryGo', 'Где купить свежую клубнику в Красноярске: Клери, Черный Принц, Альбион, ящики 2 кг и 3 кг с доставкой.', 'где купить клубнику Красноярск, купить клубнику в Красноярске, клубника Красноярск, доставка клубники', NULL, NULL, NULL, '2026-06-14 08:38:08', '2026-06-14 08:38:08', 1, 1, 1),
(39, 1, 'kak-hranit-klubniku-2-3-kg', NULL, 'Как хранить клубнику 2 и 3 кг, чтобы она не испортилась', 'Купили ящик клубники? Рассказываем, как сохранить ягоду свежей дольше.', 'Клубнику лучше не мыть заранее. Если вы купили ящик 2 кг или 3 кг, переберите ягоду, уберите поврежденные плоды и храните клубнику в холодильнике в открытой или слегка прикрытой таре. Мыть ягоду лучше прямо перед едой. Не ставьте клубнику рядом с продуктами с сильным запахом. Если ягода очень спелая, лучше съесть ее в ближайшие сутки или использовать для десертов.', 'Как хранить клубнику 2 кг и 3 кг | Советы berryGo', 'Как хранить свежую клубнику в ящике 2 кг и 3 кг, чтобы ягода не испортилась. Простые советы после покупки.', 'как хранить клубнику, клубника 2 кг, клубника 3 кг, ящик клубники, свежая клубника', NULL, NULL, NULL, '2026-06-14 08:38:08', '2026-06-14 08:38:08', 1, 1, 1),
(40, 1, 'kupit-klubniku-2-kg-krasnoyarsk', NULL, 'Купить клубнику 2 кг в Красноярске: кому подходит такой ящик', 'Ящик 2 кг — удобный формат для семьи, гостей и свежей ягоды к столу.', 'Клубника 2 кг — самый удобный формат для первой покупки. Такого объема хватает для семьи, десерта, завтраков и угощения гостей. В отличие от маленькой фасовки, ящик выгоднее по цене за килограмм. В berryGo можно купить клубнику 2 кг в Красноярске с доставкой на дом или оформить предзаказ на свежую поставку.', 'Купить клубнику 2 кг в Красноярске | berryGo', 'Клубника 2 кг в Красноярске с доставкой. Свежие ящики клубники Клери, Черный Принц и MIX от berryGo.', 'купить клубнику 2 кг Красноярск, клубника 2 кг, ящик клубники 2 кг, доставка клубники Красноярск', NULL, NULL, NULL, '2026-06-14 08:38:08', '2026-06-14 08:38:08', 1, 1, 1),
(41, 1, 'kupit-klubniku-3-kg-krasnoyarsk', NULL, 'Купить клубнику 3 кг в Красноярске: выгодный формат для семьи', 'Ящик 3 кг выгоден, если клубнику берут домой, на праздник или для большой семьи.', 'Клубника 3 кг — формат для тех, кто хочет взять ягоду выгоднее и не возвращаться за новой коробкой на следующий день. Такой ящик подходит для семьи, офиса, гостей, дачи и десертов. В berryGo можно заказать клубнику 3 кг с доставкой по Красноярску. Мы делаем ставку на свежие поставки, понятное описание сорта и честный вес.', 'Купить клубнику 3 кг в Красноярске | Ящик клубники berryGo', 'Клубника 3 кг в Красноярске с доставкой. Свежая сладкая клубника ящиками для семьи, офиса и праздника.', 'купить клубнику 3 кг Красноярск, клубника 3 кг, ящик клубники 3 кг, клубника Красноярск', NULL, NULL, NULL, '2026-06-14 08:38:08', '2026-06-14 08:38:08', 1, 1, 1),
(42, 1, 'dostavka-klubniki-na-dom-krasnoyarsk', NULL, 'Доставка клубники на дом в Красноярске', 'Как работает доставка клубники berryGo и почему это удобнее покупки на улице.', 'Доставка клубники на дом экономит время и снижает риск купить залежавшуюся ягоду случайно. В berryGo можно выбрать сорт, вес и удобный формат заказа. Мы доставляем клубнику по Красноярску, а также предлагаем предзаказ на свежую поставку. Для покупателя это удобно: не нужно искать ягоду по рынкам, сравнивать коробки и переживать за качество.', 'Доставка клубники на дом в Красноярске | berryGo', 'Закажите свежую клубнику с доставкой на дом в Красноярске. Ящики 2 кг и 3 кг, сорта Клери, Черный Принц, Альбион.', 'доставка клубники на дом Красноярск, доставка клубники Красноярск, заказать клубнику с доставкой', NULL, NULL, NULL, '2026-06-14 08:38:08', '2026-06-14 08:38:08', 1, 1, 1),
(43, 1, 'svezhaya-klubnika-krasnoyarsk', NULL, 'Свежая клубника в Красноярске: как понять, что ягода не залежалась', 'Свежесть клубники видно по запаху, цвету, плотности и состоянию ящика.', 'Свежая клубника отличается ароматом, сухой поверхностью и плотной ягодой. Если в ящике много сока, размятых плодов или кислого запаха, ягода уже потеряла качество. В berryGo мы делаем акцент на свежих партиях и быстрой продаже, потому что клубника — продукт короткого срока. Заказывать свежую клубнику в Красноярске лучше у продавца, который показывает сорт, вес и дату поставки.', 'Свежая клубника в Красноярске | Как выбрать и заказать', 'Свежая клубника в Красноярске с доставкой. Как определить качество ягоды и заказать свежий ящик в berryGo.', 'свежая клубника Красноярск, свежая клубника, купить свежую клубнику, клубника Красноярск', NULL, NULL, NULL, '2026-06-14 08:38:08', '2026-06-14 08:38:08', 1, 1, 1),
(44, 1, 'clubnika-krasnoyarsk-kupit-s-dostavkoy', NULL, 'Клубника в Красноярске: купить с доставкой или на рынке', 'Сравниваем покупку клубники на рынке и заказ свежего ящика с доставкой.', 'Купить клубнику в Красноярске можно разными способами, но доставка удобнее, если важны время, понятный вес и стабильное качество. На рынке покупатель часто выбирает визуально, не зная сорт и дату поставки. В berryGo можно заранее посмотреть ассортимент, выбрать фасовку и оформить заказ онлайн. Это особенно удобно в сезон, когда хорошую клубнику быстро разбирают.', 'Клубника Красноярск — купить с доставкой | berryGo', 'Клубника в Красноярске с доставкой: свежие ящики 2 кг и 3 кг, сорта Клери, Черный Принц и Альбион.', 'клубника Красноярск купить, купить клубнику Красноярск, клубника с доставкой Красноярск', NULL, NULL, NULL, '2026-06-14 08:38:08', '2026-06-14 08:38:08', 1, 1, 1),
(45, 1, 'clubnika-albion-opisanie-vkus', NULL, 'Клубника Альбион: описание сорта и вкус', 'Альбион — крупная плотная клубника с хорошей транспортабельностью.', 'Клубника Альбион известна крупной ягодой, плотной мякотью и аккуратным внешним видом. Этот сорт хорошо переносит доставку, поэтому его часто выбирают для ящиков и продажи в свежем виде. По вкусу Альбион может быть сладким с легкой кислинкой, особенно если ягода спелая. В berryGo Альбион подходит тем, кто хочет красивую крупную клубнику для семьи или подарка.', 'Клубника Альбион — описание сорта, вкус, фото | berryGo', 'Описание сорта клубники Альбион: вкус, размер, плотность и кому подойдет этот сорт для покупки в Красноярске.', 'клубника Альбион, клубника Альбион описание, сорт клубники Альбион, купить клубнику Альбион', NULL, NULL, NULL, '2026-06-14 08:38:08', '2026-06-14 08:38:08', 1, 1, 1),
(46, 1, 'clubnika-aziya-opisanie-vkus', NULL, 'Клубника Азия: описание сорта и вкус', 'Азия — крупная красивая клубника с насыщенным вкусом и хорошим ароматом.', 'Клубника Азия часто привлекает покупателей крупным размером и ровной формой. Это сорт, который хорошо смотрится в ящике и подходит для свежей еды. Вкус зависит от спелости партии: хорошая Азия сладкая, ароматная и мясистая. При покупке важно смотреть на цвет и запах. В berryGo такие сорта используются для расширения ассортимента и сравнения с Клери и Альбионом.', 'Клубника Азия — описание сорта и вкус | berryGo', 'Клубника Азия: описание сорта, вкус, внешний вид и отличие от Клери и Альбиона.', 'клубника Азия, клубника Азия описание, сорт клубники Азия, клубника из Киргизии', NULL, NULL, NULL, '2026-06-14 08:38:08', '2026-06-14 08:38:08', 1, 1, 1),
(47, 1, 'clubnika-murano-opisanie-vkus', NULL, 'Клубника Мурано: описание сорта и особенности', 'Мурано — сорт клубники с аккуратной ягодой и приятным вкусом.', 'Клубника Мурано интересна покупателям, которые ищут не только привычную Клери, но и другие сорта. У Мурано аккуратная форма, приятный аромат и универсальный вкус. Такая ягода подходит для еды, десертов и свежего ящика домой. При выборе Мурано важно учитывать свежесть партии: именно она сильнее всего влияет на вкус.', 'Клубника Мурано — описание сорта и вкус | berryGo', 'Описание сорта клубники Мурано: вкус, внешний вид, особенности и кому подойдет этот сорт.', 'клубника Мурано, клубника Мурано описание, сорт клубники Мурано', NULL, NULL, NULL, '2026-06-14 08:38:08', '2026-06-14 08:38:08', 1, 1, 1),
(48, 1, 'clubnika-viktoriya-opisanie', NULL, 'Клубника Виктория: почему так называют почти любую садовую клубнику', 'Виктория — народное название клубники, но покупателю важно смотреть на сорт и свежесть.', 'Многие покупатели называют клубнику Викторией, хотя чаще это общее народное название садовой клубники. На практике вкус зависит не от слова Виктория, а от конкретного сорта, спелости и свежести партии. Если вы ищете клубнику Виктория в Красноярске, лучше уточнять сорт: Клери, Альбион, Черный Принц или MIX. В berryGo мы стараемся указывать сорт и формат ящика понятно.', 'Клубника Виктория — что это за сорт и как выбрать', 'Что такое клубника Виктория, почему так называют разные сорта и как выбрать вкусную ягоду в Красноярске.', 'клубника Виктория, виктория клубника описание, клубника садовая, купить клубнику Красноярск', NULL, NULL, NULL, '2026-06-14 08:38:08', '2026-06-14 08:38:08', 1, 1, 1),
(49, 1, 'clubnika-alba-opisanie', NULL, 'Клубника Альба: описание сорта и вкус', 'Альба — ранний сорт клубники с красивой ягодой и плотной структурой.', 'Клубника Альба известна как ранний сорт с ровной ягодой и хорошей плотностью. Для покупателя это значит, что ягода обычно хорошо выглядит и подходит для транспортировки. По вкусу Альба может быть спокойнее, чем Клери, но при хорошей спелости остается приятной и ароматной. Такой сорт интересен в начале сезона и при свежих поставках.', 'Клубника Альба — описание сорта, вкус и особенности', 'Клубника Альба: описание сорта, вкус, внешний вид и кому подойдет этот сорт.', 'клубника Альба, клубника Альба описание, сорт клубники Альба', NULL, NULL, NULL, '2026-06-14 08:38:08', '2026-06-14 08:38:08', 1, 1, 1),
(50, 1, 'clubnika-elizaveta-opisanie', NULL, 'Клубника Елизавета: описание сорта и вкус', 'Елизавета — известный сорт с крупной ягодой и выраженным клубничным вкусом.', 'Клубника Елизавета популярна благодаря крупной ягоде и узнаваемому названию. Покупатели часто ищут этот сорт, когда хотят большую и красивую клубнику. Как и у других сортов, вкус зависит от партии, спелости и хранения. При покупке Елизаветы стоит выбирать сухую, плотную, ароматную ягоду без потемнений и сока в ящике.', 'Клубника Елизавета — описание сорта и вкус | berryGo', 'Описание сорта клубники Елизавета: вкус, размер, особенности и советы покупателю.', 'клубника Елизавета, Елизавета клубника описание, клубника Елизавета 2', NULL, NULL, NULL, '2026-06-14 08:38:08', '2026-06-14 08:38:08', 1, 1, 1),
(51, 1, 'clubnika-festivalnaya-opisanie', NULL, 'Клубника Фестивальная: описание сорта', 'Фестивальная — знакомый многим сорт клубники с классическим ягодным вкусом.', 'Клубника Фестивальная хорошо знакома покупателям старшего поколения. Ее часто ассоциируют с дачной ягодой и классическим вкусом. В коммерческой продаже этот сорт встречается не всегда, но спрос по названию сохраняется. Для berryGo такая статья помогает объяснить покупателю разницу между привычными сортами и свежей клубникой, которую можно заказать в сезон.', 'Клубника Фестивальная — описание сорта и вкус', 'Клубника Фестивальная: описание сорта, вкус и сравнение с современными сортами клубники.', 'клубника Фестивальная, Фестивальная клубника описание, сорт клубники Фестивальная', NULL, NULL, NULL, '2026-06-14 08:38:08', '2026-06-14 08:38:08', 1, 1, 1),
(52, 1, 'clubnika-kupchikha-opisanie', NULL, 'Клубника Купчиха: описание и чем она отличается', 'Купчиха — необычная ягода с выраженным ароматом, которую часто сравнивают с клубникой.', 'Купчиха — это не совсем обычная клубника, а гибридный тип ягоды с ярким ароматом и плотной структурой. Покупатели интересуются ею из-за необычного вкуса и названия. В продаже Купчиха встречается реже, чем Клери или Альбион, но запросы по ней полезны для SEO и расширения доверия к сайту berryGo как к эксперту по ягодам.', 'Клубника Купчиха — описание, вкус и особенности', 'Что такое клубника Купчиха, какой у нее вкус и чем она отличается от обычной клубники.', 'клубника Купчиха, Купчиха клубника описание, ягода Купчиха', NULL, NULL, NULL, '2026-06-14 08:38:08', '2026-06-14 08:38:08', 1, 1, 1),
(53, 1, 'clubnika-zenga-opisanie', NULL, 'Клубника Зенга: описание сорта и вкус', 'Зенга — сорт с насыщенным вкусом, который часто вспоминают любители ароматной клубники.', 'Клубника Зенга известна насыщенным вкусом и ароматом. Ее часто ищут покупатели, которым важна не только крупная ягода, но и настоящий клубничный запах. В свежей продаже такой сорт встречается не всегда, поэтому статья помогает собрать поисковый спрос и перевести покупателя на доступные сорта berryGo: Клери, Черный Принц, Альбион и сезонные MIX-ящики.', 'Клубника Зенга — описание сорта, вкус и аромат', 'Клубника Зенга: описание сорта, вкус, аромат и современные альтернативы для покупки.', 'клубника Зенга, Зенга клубника, клубника зенга описание', NULL, NULL, NULL, '2026-06-14 08:38:08', '2026-06-14 08:38:08', 1, 1, 1),
(54, 1, 'belaya-klubnika-chto-eto', NULL, 'Белая клубника: что это такое и бывает ли она сладкой', 'Белая клубника — редкий и необычный вариант, который чаще интересен как экзотика.', 'Белая клубника существует, но в обычной продаже встречается редко. Покупатели часто ищут ее из любопытства или ради необычного подарка. По вкусу белая клубника может отличаться от привычной красной ягоды, но для регулярной покупки чаще выбирают Клери, Альбион или Черный Принц. В berryGo основной акцент сделан на свежую красную клубнику с понятным вкусом и доставкой.', 'Белая клубника — что это такое, вкус и особенности', 'Белая клубника: бывает ли такой сорт, какой у нее вкус и чем она отличается от обычной клубники.', 'белая клубника, белая клубника описание, необычная клубника', NULL, NULL, NULL, '2026-06-14 08:38:08', '2026-06-14 08:38:08', 1, 1, 1),
(55, 1, 'chernaya-klubnika-mif-ili-sort', NULL, 'Черная клубника: миф, сорт или маркетинг', 'Разбираем, существует ли черная клубника и что обычно имеют в виду покупатели.', 'Запрос “черная клубника” часто связан с интересом к темным сортам. Полностью черной клубники в привычной продаже почти не бывает, но есть сорта с темно-красной или бордовой ягодой. Один из понятных вариантов для покупателя — Черный Принц. Он выглядит темнее обычной клубники и имеет насыщенный вкус. Поэтому тем, кто ищет черную клубнику, чаще стоит смотреть именно темные сорта.', 'Черная клубника — существует ли такой сорт | berryGo', 'Что такое черная клубника, существует ли она и какие темные сорта можно выбрать вместо нее.', 'черная клубника, клубника черный принц, темная клубника, черная клубника описание', NULL, NULL, NULL, '2026-06-14 08:38:08', '2026-06-14 08:38:08', 1, 1, 1),
(56, 1, 'klubnika-foto-kak-vybrat-po-vneshnemu-vidu', NULL, 'Клубника по фото: как понять качество ягоды до покупки', 'Фото клубники помогает оценить свежесть, размер, цвет и состояние ящика.', 'При заказе клубники онлайн фото имеет большое значение. По нему можно оценить цвет, размер, сухость ягоды и общее состояние ящика. Хорошая клубника на фото выглядит ровной, свежей, без большого количества сока и темных пятен. Но важно помнить: фото должно быть актуальным, а не прошлогодним. В berryGo визуальная подача товара помогает покупателю выбрать ягоду до оформления заказа.', 'Клубника фото — как выбрать свежую ягоду по внешнему виду', 'Как по фото клубники понять свежесть и качество ягоды перед покупкой онлайн.', 'клубника фото, клубника фото отзыв, клубника описание фото, свежая клубника фото', NULL, NULL, NULL, '2026-06-14 08:38:08', '2026-06-14 08:38:08', 1, 1, 1),
(57, 1, 'klubnika-otzyvy-pokupateley', NULL, 'Отзывы о клубнике: на что смотреть перед покупкой', 'Отзывы помогают понять вкус, свежесть, доставку и честность продавца.', 'Перед покупкой клубники полезно смотреть отзывы не только о вкусе, но и о доставке, свежести и совпадении фото с реальным ящиком. Хороший отзыв обычно содержит конкретику: была ли ягода сладкой, целой, свежей, не текла ли коробка. В berryGo отзывы покупателей помогают улучшать поставки и подбирать сорта, которые чаще всего нравятся клиентам.', 'Отзывы о клубнике — как выбрать продавца и свежую ягоду', 'На что смотреть в отзывах о клубнике перед покупкой: вкус, свежесть, доставка, качество ящика.', 'клубника отзывы, клубника отзывы покупателей, клубника описание отзывы, клубника Красноярск отзывы', NULL, NULL, NULL, '2026-06-14 08:38:08', '2026-06-14 08:38:08', 1, 1, 1),
(58, 1, 'pochemu-klubnika-byvaet-kisloy', NULL, 'Почему клубника бывает кислой и как выбрать сладкую', 'Кислая клубника — не всегда плохая ягода. Часто дело в сорте, спелости и поставке.', 'Клубника может быть кислой по нескольким причинам: сорт, ранний сбор, недостаток солнца, неправильное хранение или слишком долгая дорога. Даже красивая ягода не всегда бывает сладкой, если ее собрали рано. Поэтому при покупке стоит выбирать проверенные партии и сорта с понятным вкусом. В berryGo мы описываем ягоду честно: если сорт более сладкий или с кислинкой, это лучше указать сразу.', 'Почему клубника кислая | Как выбрать сладкую клубнику', 'Почему клубника бывает кислой и как выбрать сладкую свежую ягоду в Красноярске.', 'почему клубника кислая, как выбрать сладкую клубнику, вкус клубники, сладкая клубника Красноярск', NULL, NULL, NULL, '2026-06-14 08:38:08', '2026-06-14 08:38:08', 1, 1, 1),
(59, 1, 'krupnaya-klubnika-ili-melkaya-chto-vkusnee', NULL, 'Крупная или мелкая клубника: какая вкуснее', 'Размер ягоды важен, но вкус зависит от сорта, спелости и свежести.', 'Многие покупатели думают, что крупная клубника всегда вкуснее, но это не совсем так. Крупная ягода выглядит эффектно и удобна для подарка, но мелкая и средняя клубника иногда бывает более ароматной. Главное — сорт, спелость и свежесть. Для семьи лучше выбирать не только по размеру, а по вкусу и состоянию ящика.', 'Крупная или мелкая клубника — какая вкуснее', 'Какая клубника вкуснее: крупная или мелкая. Как выбрать свежую сладкую ягоду для семьи.', 'крупная клубника, какая клубника вкуснее, сладкая клубника, свежая клубника', NULL, NULL, NULL, '2026-06-14 08:38:08', '2026-06-14 08:38:08', 1, 1, 1),
(60, 1, 'kakaya-klubnika-luchshe-dlya-detey', NULL, 'Какая клубника лучше для детей', 'Для детей лучше выбирать свежую, сладкую, чистую по качеству ягоду без признаков порчи.', 'Для детей чаще выбирают сладкую клубнику без выраженной кислоты, с плотной и свежей ягодой. Важно, чтобы клубника не была размятой, не текла и не имела постороннего запаха. Перед едой ягоду нужно хорошо промыть, а новую клубнику лучше давать небольшими порциями. В berryGo для семьи часто выбирают Клери и другие сладкие сорта.', 'Какая клубника лучше для детей | Советы berryGo', 'Как выбрать клубнику для детей: свежесть, сладость, сорт, хранение и доставка.', 'клубника для детей, какая клубника лучше, сладкая клубника, свежая клубника', NULL, NULL, NULL, '2026-06-14 08:38:08', '2026-06-14 08:38:08', 1, 1, 1),
(61, 1, 'sezon-klubniki-v-krasnoyarske', NULL, 'Сезон клубники в Красноярске: когда лучше покупать ягоду', 'Сезон влияет на цену, вкус и доступность свежих партий клубники.', 'В Красноярске клубнику покупают весь теплый сезон, но вкус, цена и происхождение ягоды меняются. В начале сезона чаще ценится ранняя клубника, летом появляется больше поставок, а ближе к осени растет роль предзаказа и свежего привоза. В berryGo удобно следить за актуальным наличием и заказывать клубнику тогда, когда приходит свежая партия.', 'Сезон клубники в Красноярске | Когда покупать свежую ягоду', 'Когда начинается сезон клубники в Красноярске и когда выгоднее покупать свежую ягоду с доставкой.', 'сезон клубники Красноярск, когда будет клубника, клубника май Красноярск, свежий привоз клубники', NULL, NULL, NULL, '2026-06-14 08:38:08', '2026-06-14 08:38:08', 1, 1, 1),
(62, 1, 'predzakaz-klubniki-krasnoyarsk', NULL, 'Предзаказ клубники в Красноярске: как купить свежую партию выгоднее', 'Предзаказ помогает получить свежую ягоду из новой поставки и часто купить дешевле.', 'Предзаказ клубники удобен, когда свежая партия еще в пути. Покупатель заранее бронирует ящик и получает ягоду после привоза. Для berryGo это важный формат: он помогает точнее планировать поставку, а покупателю — получить свежую клубнику по более выгодной цене. Особенно хорошо предзаказ работает для ящиков 2 кг и 3 кг.', 'Предзаказ клубники в Красноярске | berryGo', 'Оформите предзаказ клубники в Красноярске и получите свежую ягоду из новой поставки. Ящики 2 кг и 3 кг.', 'предзаказ клубники Красноярск, заказать клубнику заранее, клубника новый привоз, свежая клубника Красноярск', NULL, NULL, NULL, '2026-06-14 08:38:08', '2026-06-14 08:38:08', 1, 1, 1);

-- --------------------------------------------------------

--
-- Структура таблицы `metadata`
--

CREATE TABLE `metadata` (
  `id` int NOT NULL,
  `page` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `keywords` text COLLATE utf8mb4_unicode_ci,
  `h1` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `text` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `metadata`
--

INSERT INTO `metadata` (`id`, `page`, `title`, `description`, `keywords`, `h1`, `text`) VALUES
(1, 'home', 'Купить клубнику в Красноярске с доставкой | Клери из Киргизии | berryGo', 'Свежая клубника Клери из Киргизии в Красноярске. Ящики 2 кг и 3 кг. Доставка по Красноярску. Сладкая крупная клубника напрямую от поставщика.', 'купить клубнику Красноярск, клубника Красноярск, доставка клубники Красноярск, клубника Клери Красноярск, клубника из Киргизии', 'Купить свежую клубнику в Красноярске с доставкой', 'В berryGo можно заказать свежие ягоды и сезонные фрукты с доставкой по Красноярску. У нас собраны самые любимые летние вкусы: сладкая клубника, сочная малина, ароматная ежевика, черешня и другие свежие позиции сезона.\r\n\r\nМы работаем с прямыми поставками, поэтому ягоды приезжают свежими, красивыми и вкусными. После поступления они хранятся в помещении с холодильным температурным режимом, чтобы сохранить аромат, плотность и аккуратный внешний вид до момента доставки.\r\n\r\nОсобое внимание мы уделяем клубнике. В сезон у нас можно выбрать разные сорта и фасовки, заказать ягоду домой, к столу, для десертов или просто чтобы порадовать себя и близких вкусом настоящего лета.\r\n\r\nВ berryGo приятно заказывать снова: для покупателей действуют скидки и акции, а ещё кешбэк баллами за свои покупки и приглашённых друзей. Нас выбирают за вкусную ягоду, удобный сервис и безупречную репутацию.'),
(2, 'catalog', 'Каталог клубники, черешни и ягод в Красноярске | berryGo', 'Клубника Клери, Черный Принц, Альбион, черешня и другие ягоды с доставкой по Красноярску. Свежие поставки из Киргизии.', 'клубника Красноярск, купить клубнику Красноярск, черешня Красноярск, ягоды Красноярск', 'Каталог ягод и сезонных фруктов', 'В каталоге berryGo собраны свежие ягоды и сезонные фрукты с доставкой по Красноярску. Здесь можно выбрать клубнику, малину, ежевику, черешню и другие вкусные позиции, которые доступны в продаже прямо сейчас.\r\n\r\nЕсли хочется сладкой ягоды к столу, чего-то свежего для семьи, десертов или сезонных покупок, в каталоге легко найти подходящий вариант. Мы регулярно обновляем ассортимент и стараемся предлагать только то, что действительно приятно есть.\r\n\r\nВсе ягоды и фрукты после поступления хранятся в помещении с холодильным температурным режимом. Это помогает сохранить свежесть, сочность и вкус до доставки.\r\n\r\nЕсли вам нужна именно клубника, для неё у нас есть отдельная страница, где удобно выбрать сорт и фасовку. А каталог — это общая страница для тех, кто хочет посмотреть весь сезонный ассортимент berryGo.'),
(3, 'about_app', 'Как заказать ягоды и клубнику в berryGo — сайт, Telegram и бонусы', 'Заказывайте ягоды и клубнику в berryGo через сайт и Telegram. Удобный выбор, доставка по Красноярску, скидки, акции и кешбэк баллами за свои покупки и приглашённых друзей.', 'как заказать клубнику Красноярск, заказать ягоды Красноярск, berryGo Telegram, berryGo сайт, кешбэк berryGo, бонусы berryGo', 'Как заказать ягоды и клубнику в berryGo', 'Заказать ягоды и фрукты в berryGo просто. Выберите нужный товар на сайте или в Telegram, укажите адрес и удобное время, а мы аккуратно подготовим заказ и доставим его по Красноярску.\r\n\r\nОсобенно удобно заказывать клубнику: можно быстро выбрать сорт, фасовку и получить свежую ягоду домой или в офис без лишней суеты. Мы стараемся сделать покупку понятной и приятной с первого клика до вручения заказа.\r\n\r\nДля покупателей действуют скидки и акции, а ещё кешбэк баллами за свои покупки и приглашённых друзей. Это значит, что любимую ягоду можно заказывать не только вкусно, но и выгодно.\r\n\r\nBerryGo выбирают за свежесть, удобство и хорошую репутацию. Если хочется порадовать себя сладкой клубникой или выбрать что-то ещё из сезонных ягод и фруктов, всё уже готово к заказу.'),
(4, 'contacts', 'Контакты berryGo — заказать ягоды и клубнику в Красноярске', 'Свяжитесь с berryGo, чтобы заказать клубнику, ягоды и сезонные фрукты в Красноярске. Поможем с выбором, доставкой, акциями и бонусами.', 'контакты berryGo, заказать клубнику Красноярск, заказать ягоды Красноярск, доставка ягод Красноярск, berryGo Красноярск', 'Контакты berryGo', 'Если хотите заказать клубнику, ягоды или сезонные фрукты, свяжитесь с нами удобным способом. Подскажем по наличию, расскажем о доставке, акциях, скидках и бонусах.\r\n\r\nЕсли не знаете, что выбрать, мы поможем сориентироваться по ассортименту и подобрать вариант под ваш вкус и количество. Кто-то берёт немного к столу, кто-то — побольше для семьи, гостей или десертов.\r\n\r\nВ berryGo ценят вежливую связь, аккуратный сервис и честный подход. Нам важно, чтобы заказ был не просто оформлен, а действительно порадовал.'),
(5, 'register', 'Регистрация – BerryGo', 'Создайте аккаунт для заказа свежих ягод', '', 'Регистрация', ''),
(6, 'reset-pin', 'Сброс PIN – BerryGo', 'Восстановите код доступа к приложению', '', 'Сброс PIN', ''),
(7, 'login', 'Вход – BerryGo', 'Авторизуйтесь для доступа к личному кабинету', '', 'Вход', '');

-- --------------------------------------------------------

--
-- Структура таблицы `notifications`
--

CREATE TABLE `notifications` (
  `id` int NOT NULL,
  `code` varchar(100) NOT NULL,
  `description` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Дамп данных таблицы `notifications`
--

INSERT INTO `notifications` (`id`, `code`, `description`) VALUES
(1, 'batch_ready_for_pickup', 'Партия #21 по товару #20 готова к выдаче.'),
(2, 'batch_ready_for_pickup', 'Партия #22 по товару #7 готова к выдаче.'),
(3, 'batch_ready_for_pickup', 'Партия #23 по товару #20 готова к выдаче.'),
(4, 'batch_ready_for_pickup', 'Партия #27 по товару #6 готова к выдаче.'),
(5, 'batch_ready_for_pickup', 'Партия #25 по товару #7 готова к выдаче.'),
(6, 'preorder_price_confirmation_requested', 'Поставка пришла. Предзаказам по товару #31 отправлено подтверждение цены: 1'),
(7, 'batch_ready_for_pickup', 'Партия #29 по товару #31 готова к выдаче.'),
(8, 'batch_ready_for_pickup', 'Партия #28 по товару #6 готова к выдаче.'),
(9, 'batch_ready_for_pickup', 'Партия #30 по товару #7 готова к выдаче.'),
(10, 'batch_ready_for_pickup', 'Партия #30 по товару #7 готова к выдаче.'),
(11, 'batch_ready_for_pickup', 'Партия #34 по товару #7 готова к выдаче.'),
(12, 'batch_ready_for_pickup', 'Партия #41 по товару #11 готова к выдаче.');

-- --------------------------------------------------------

--
-- Структура таблицы `notification_channel_settings`
--

CREATE TABLE `notification_channel_settings` (
  `id` tinyint UNSIGNED NOT NULL DEFAULT '1',
  `in_app_enabled` tinyint(1) NOT NULL DEFAULT '1',
  `telegram_enabled` tinyint(1) NOT NULL DEFAULT '0',
  `sms_enabled` tinyint(1) NOT NULL DEFAULT '0',
  `sms_template` varchar(255) NOT NULL DEFAULT 'berrygo.ru: у вас новое уведомление по заказу {order_id}',
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Дамп данных таблицы `notification_channel_settings`
--

INSERT INTO `notification_channel_settings` (`id`, `in_app_enabled`, `telegram_enabled`, `sms_enabled`, `sms_template`, `updated_at`) VALUES
(1, 1, 0, 0, 'berrygo.ru: у вас новое уведомление по заказу {order_id}', NULL);

-- --------------------------------------------------------

--
-- Структура таблицы `orders`
--

CREATE TABLE `orders` (
  `id` int UNSIGNED NOT NULL,
  `order_group_id` bigint UNSIGNED DEFAULT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `address_id` int UNSIGNED NOT NULL,
  `slot_id` int UNSIGNED DEFAULT NULL,
  `status` enum('reserved','new','confirmed','shipped','completed','cancelled','returned') NOT NULL DEFAULT 'new',
  `total_amount` decimal(10,0) NOT NULL,
  `delivery_date` date NOT NULL,
  `created_by_user_id` int DEFAULT NULL,
  `discount_applied` int NOT NULL DEFAULT '0',
  `points_used` int NOT NULL DEFAULT '0',
  `points_accrued` int NOT NULL DEFAULT '0',
  `manager_points_accrued` int NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `assigned_to` int UNSIGNED DEFAULT NULL,
  `coupon_code` varchar(50) DEFAULT NULL,
  `comment` text,
  `order_mode` enum('preorder','instant','discount_stock') NOT NULL DEFAULT 'instant',
  `purchase_batch_id` int UNSIGNED DEFAULT NULL,
  `reserved_at` datetime DEFAULT NULL,
  `fulfilled_from_stock_at` datetime DEFAULT NULL,
  `bonuses_allowed` tinyint(1) NOT NULL DEFAULT '1',
  `coupons_allowed` tinyint(1) NOT NULL DEFAULT '1',
  `payment_status` varchar(32) NOT NULL DEFAULT 'unpaid',
  `delivery_fee` int NOT NULL DEFAULT '0',
  `delivery_distance_km` decimal(8,3) DEFAULT NULL,
  `delivery_tariff_zone_id` int UNSIGNED DEFAULT NULL,
  `delivery_pricing_source` varchar(50) DEFAULT NULL,
  `delivery_comment` text,
  `payment_method` varchar(32) DEFAULT NULL,
  `payment_provider` varchar(32) DEFAULT NULL,
  `payment_invoice_id` bigint UNSIGNED DEFAULT NULL,
  `payment_amount` decimal(10,2) DEFAULT NULL,
  `paid_at` datetime DEFAULT NULL,
  `refunded_at` datetime DEFAULT NULL,
  `refund_comment` text,
  `payment_raw_response` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Дамп данных таблицы `orders`
--

INSERT INTO `orders` (`id`, `order_group_id`, `user_id`, `address_id`, `slot_id`, `status`, `total_amount`, `delivery_date`, `created_by_user_id`, `discount_applied`, `points_used`, `points_accrued`, `manager_points_accrued`, `created_at`, `assigned_to`, `coupon_code`, `comment`, `order_mode`, `purchase_batch_id`, `reserved_at`, `fulfilled_from_stock_at`, `bonuses_allowed`, `coupons_allowed`, `payment_status`, `delivery_fee`, `delivery_distance_km`, `delivery_tariff_zone_id`, `delivery_pricing_source`, `delivery_comment`, `payment_method`, `payment_provider`, `payment_invoice_id`, `payment_amount`, `paid_at`, `refunded_at`, `refund_comment`, `payment_raw_response`) VALUES
(131, NULL, 36, 36, 3, 'completed', 3936, '2025-07-26', NULL, 0, 0, 0, 0, '2025-07-25 22:44:58', NULL, '', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(132, NULL, 37, 37, 3, 'completed', 4273, '2025-07-26', NULL, 0, 0, 0, 0, '2025-07-25 22:47:32', NULL, '', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(133, NULL, 38, 38, 3, 'completed', 2473, '2025-07-26', NULL, 0, 0, 0, 0, '2025-07-25 22:50:11', NULL, '', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(134, NULL, 42, 42, 4, 'completed', 2473, '2025-07-26', NULL, 0, 0, 74, 0, '2025-07-26 08:33:36', NULL, '', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(135, NULL, 43, 43, 3, 'completed', 1080, '2025-07-26', NULL, 0, 0, 32, 0, '2025-07-26 08:38:32', NULL, '', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(136, NULL, 44, 44, 3, 'completed', 1080, '2025-07-26', NULL, 0, 0, 32, 0, '2025-07-26 08:41:33', NULL, '', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(137, NULL, 45, 45, 3, 'completed', 2700, '2025-07-26', NULL, 0, 0, 81, 0, '2025-07-26 08:44:38', NULL, '', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(138, NULL, 46, 46, 6, 'completed', 989, '2025-07-26', NULL, 0, 0, 29, 0, '2025-07-26 08:48:28', NULL, '', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(139, NULL, 25, 24, 5, 'completed', 4400, '2025-07-26', NULL, 0, 0, 132, 0, '2025-07-26 12:05:07', NULL, '', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(140, NULL, 47, 47, 3, 'completed', 1236, '2025-07-27', NULL, 0, 0, 37, 0, '2025-07-27 09:26:27', NULL, '', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(141, NULL, 48, 48, 3, 'completed', 3956, '2025-07-27', NULL, 0, 0, 118, 0, '2025-07-27 09:28:31', NULL, '', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(142, NULL, 49, 49, 3, 'completed', 1978, '2025-07-27', NULL, 0, 0, 59, 0, '2025-07-27 09:31:10', NULL, '', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(146, NULL, 51, 51, 6, 'completed', 2280, '2025-07-29', NULL, 0, 0, 68, 0, '2025-07-29 16:16:02', NULL, '', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(147, NULL, 52, 52, 6, 'completed', 2280, '2025-07-29', NULL, 0, 0, 68, 0, '2025-07-29 16:18:17', NULL, '', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(148, NULL, 53, 53, 3, 'completed', 1290, '2025-08-02', NULL, 0, 0, 38, 0, '2025-08-02 08:20:50', NULL, '', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(149, NULL, 58, 58, 4, 'completed', 6600, '2025-08-03', NULL, 0, 0, 198, 198, '2025-08-03 09:55:06', NULL, '', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(150, NULL, 59, 59, 5, 'completed', 1290, '2025-08-02', NULL, 0, 0, 38, 38, '2025-08-03 09:57:47', NULL, '', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(151, NULL, 60, 60, 4, 'completed', 990, '2025-08-03', NULL, 0, 0, 29, 29, '2025-08-03 10:03:13', NULL, '', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(152, NULL, 61, 61, 5, 'completed', 6600, '2025-08-03', NULL, 0, 0, 118, 118, '2025-08-03 10:05:27', NULL, '', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(153, NULL, 62, 62, 6, 'completed', 11190, '2025-08-03', NULL, 0, 0, 335, 335, '2025-08-03 10:46:47', NULL, '', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(154, NULL, 63, 63, 6, 'completed', 1290, '2025-08-03', NULL, 0, 0, 38, 38, '2025-08-03 14:40:45', NULL, '', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(155, NULL, 64, 64, 6, 'completed', 2970, '2025-08-04', NULL, 0, 0, 89, 0, '2025-08-03 15:25:21', NULL, '', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(156, NULL, 65, 65, 6, 'completed', 1100, '2025-08-03', NULL, 0, 0, 33, 33, '2025-08-03 17:14:35', NULL, '', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(157, NULL, 67, 67, 3, 'completed', 2970, '2025-08-04', NULL, 0, 0, 89, 0, '2025-08-04 08:03:19', NULL, '', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(158, NULL, 68, 68, 4, 'completed', 1980, '2025-08-04', NULL, 0, 0, 198, 59, '2025-08-04 08:17:13', NULL, '', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(159, NULL, 69, 69, 4, 'completed', 3300, '2025-08-04', NULL, 0, 0, 330, 99, '2025-08-04 09:57:13', NULL, '', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(160, NULL, 70, 70, 5, 'completed', 7700, '2025-08-04', NULL, 0, 0, 770, 231, '2025-08-04 10:22:39', NULL, '', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(161, NULL, 71, 71, 5, 'completed', 3270, '2025-08-04', NULL, 0, 0, 327, 98, '2025-08-04 11:18:48', NULL, '', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(162, NULL, 72, 72, 3, 'completed', 5500, '2025-08-04', NULL, 0, 0, 550, 165, '2025-08-04 16:40:25', NULL, '', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(163, NULL, 73, 73, 6, 'completed', 1290, '2025-08-04', NULL, 0, 0, 129, 38, '2025-08-04 16:47:13', NULL, '', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(164, NULL, 74, 74, 4, 'completed', 2200, '2025-08-05', NULL, 0, 0, 220, 66, '2025-08-05 08:03:25', NULL, '', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(165, NULL, 75, 75, 5, 'completed', 2280, '2025-08-05', NULL, 0, 0, 228, 68, '2025-08-05 08:24:35', NULL, '', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(166, NULL, 76, 76, 6, 'completed', 990, '2025-08-05', NULL, 0, 0, 99, 29, '2025-08-05 09:50:16', NULL, '', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(167, NULL, 77, 77, 5, 'completed', 3960, '2025-08-05', NULL, 0, 0, 396, 118, '2025-08-05 10:14:03', NULL, '', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(168, NULL, 79, 79, 3, 'completed', 2280, '2025-08-06', NULL, 0, 0, 228, 68, '2025-08-05 20:00:13', NULL, '', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(169, NULL, 80, 80, 4, 'completed', 7230, '2025-08-06', NULL, 0, 0, 723, 216, '2025-08-06 09:54:27', NULL, '', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(170, NULL, 81, 81, 5, 'completed', 2500, '2025-08-06', NULL, 0, 0, 250, 75, '2025-08-06 10:54:17', NULL, 'NQVVMV5E', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(171, NULL, 82, 82, 5, 'completed', 1980, '2025-08-06', NULL, 0, 0, 198, 59, '2025-08-06 10:57:50', NULL, '', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(172, NULL, 83, 83, 5, 'completed', 1980, '2025-08-06', NULL, 0, 0, 198, 59, '2025-08-06 11:31:03', NULL, '', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(173, NULL, 84, 84, 4, 'completed', 5250, '2025-08-07', NULL, 0, 0, 525, 157, '2025-08-06 11:35:54', NULL, 'NQVVMV5E', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(174, NULL, 85, 85, 6, 'completed', 1400, '2025-08-06', NULL, 0, 0, 140, 42, '2025-08-06 15:22:21', NULL, 'NQVVMV5E', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(175, NULL, 86, 86, 3, 'completed', 2500, '2025-08-07', NULL, 0, 0, 75, 0, '2025-08-06 18:36:10', NULL, 'H7K2M9P1', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(176, NULL, 87, 87, 3, 'cancelled', 4600, '2025-08-06', NULL, 0, 0, 0, 0, '2025-08-06 19:30:45', NULL, 'NQVVMV5E', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(177, NULL, 88, 88, 3, 'completed', 2200, '2025-08-07', NULL, 0, 0, 220, 66, '2025-08-07 05:47:10', NULL, 'NQVVMV5E', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(178, NULL, 89, 89, 4, 'completed', 1400, '2025-08-07', NULL, 0, 0, 140, 42, '2025-08-07 09:04:38', NULL, 'NQVVMV5E', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(179, NULL, 90, 90, 3, 'completed', 2115, '2025-08-08', NULL, 235, 0, 211, 63, '2025-08-07 11:21:28', NULL, 'NQVVMV5E', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(180, NULL, 91, 91, 5, 'completed', 2600, '2025-08-07', NULL, 0, 0, 260, 78, '2025-08-07 12:03:03', NULL, '', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(184, NULL, 93, 93, 5, 'completed', 2070, '2025-08-08', NULL, 230, 0, 207, 62, '2025-08-08 06:42:30', NULL, 'NQVVMV5E', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(185, NULL, 94, 94, 4, 'completed', 4900, '2025-08-08', NULL, 0, 0, 490, 147, '2025-08-08 07:39:16', NULL, 'NQVVMV5E', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(186, NULL, 95, 95, 4, 'completed', 3240, '2025-08-08', NULL, 360, 0, 324, 97, '2025-08-08 08:34:22', NULL, 'NQVVMV5E', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(187, NULL, 96, 96, 4, 'completed', 1080, '2025-08-08', NULL, 120, 0, 108, 32, '2025-08-08 08:40:20', NULL, 'NQVVMV5E', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(189, NULL, 98, 98, 5, 'completed', 6900, '2025-08-08', NULL, 0, 0, 690, 207, '2025-08-08 11:21:28', NULL, 'NQVVMV5E', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(190, NULL, 99, 99, 5, 'cancelled', 1200, '2025-08-08', NULL, 0, 0, 0, 0, '2025-08-08 11:31:05', NULL, 'NQVVMV5E', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(191, NULL, 100, 100, 5, 'completed', 2070, '2025-08-08', NULL, 230, 0, 207, 62, '2025-08-08 12:05:51', NULL, 'NQVVMV5E', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(192, NULL, 97, 97, 3, 'completed', 3900, '2025-08-10', NULL, 0, 0, 390, 117, '2025-08-08 13:07:02', NULL, '', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(193, NULL, 101, 101, 5, 'completed', 2600, '2025-08-08', NULL, 0, 0, 260, 78, '2025-08-08 13:30:13', NULL, 'NQVVMV5E', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(194, NULL, 102, 102, 6, 'completed', 2700, '2025-08-08', NULL, 0, 0, 270, 81, '2025-08-08 15:12:29', NULL, 'NQVVMV5E', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(195, NULL, 103, 103, 3, 'completed', 3300, '2025-08-10', NULL, 0, 0, 330, 99, '2025-08-09 10:18:07', NULL, 'NQVVMV5E', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(196, NULL, 104, 104, 3, 'completed', 2700, '2025-08-10', NULL, 0, 0, 270, 81, '2025-08-09 10:57:01', NULL, 'NQVVMV5E', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(197, NULL, 105, 105, 4, 'cancelled', 1100, '2025-08-10', NULL, 0, 0, 0, 0, '2025-08-09 19:17:22', NULL, 'NQVVMV5E', '', 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(198, NULL, 106, 106, 3, 'completed', 1100, '2025-08-10', NULL, 0, 0, 110, 33, '2025-08-09 19:18:08', NULL, 'NQVVMV5E', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(202, NULL, 108, 108, 3, 'completed', 1380, '2025-08-20', NULL, 120, 0, 41, 0, '2025-08-20 18:45:21', NULL, 'H7K2M9P1', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(206, NULL, 112, 114, 3, 'completed', 2700, '2025-08-23', NULL, 0, 0, 270, 81, '2025-08-23 15:48:10', NULL, 'NQVVMV5E', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(207, NULL, 113, 115, 5, 'completed', 1100, '2025-08-23', NULL, 0, 0, 110, 33, '2025-08-23 15:49:06', NULL, 'NQVVMV5E', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(210, NULL, 115, 117, 3, 'completed', 1400, '2025-08-24', NULL, 0, 0, 140, 42, '2025-08-24 14:05:34', NULL, '', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(211, NULL, 116, 118, 6, 'completed', 2700, '2025-08-25', NULL, 0, 0, 270, 81, '2025-08-25 15:33:25', NULL, 'NQVVMV5E', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(212, NULL, 117, 119, 5, 'completed', 12000, '2025-08-27', NULL, 0, 0, 360, 0, '2025-08-26 18:34:29', NULL, '', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(213, NULL, 118, 120, 5, 'completed', 1500, '2025-08-21', NULL, 0, 0, 45, 0, '2025-08-26 18:38:07', NULL, '', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(214, NULL, 118, 120, 5, 'completed', 1500, '2025-08-21', NULL, 0, 0, 45, 0, '2025-08-26 18:38:07', NULL, '', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(215, NULL, 107, 107, 6, 'completed', 4800, '2025-08-12', NULL, 0, 0, 144, 0, '2025-08-26 18:45:02', NULL, '', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(216, NULL, 119, 122, 4, 'completed', 1980, '2025-08-21', NULL, 220, 0, 59, 0, '2025-08-26 18:49:47', NULL, '7WLNVMT8', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(217, NULL, 109, 123, 4, 'completed', 2160, '2025-08-21', NULL, 240, 0, 64, 0, '2025-08-26 18:55:16', NULL, '7WLNVMT8', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(218, NULL, 120, 124, 6, 'completed', 2700, '2025-08-27', NULL, 0, 0, 270, 81, '2025-08-27 17:37:25', NULL, 'NQVVMV5E', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(219, NULL, 95, 95, 4, 'completed', 7560, '2025-08-28', NULL, 840, 0, 756, 226, '2025-08-27 17:50:13', NULL, '7WLNVMT8', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(220, NULL, 121, 125, 3, 'completed', 1500, '2025-08-28', NULL, 0, 0, 150, 45, '2025-08-28 06:42:25', NULL, 'NQVVMV5E', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(221, NULL, 122, 126, 6, 'completed', 2400, '2025-08-28', NULL, 0, 0, 240, 72, '2025-08-28 18:52:10', NULL, 'NQVVMV5E', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(222, NULL, 109, 109, 6, 'completed', 2300, '2025-08-28', NULL, 0, 0, 69, 0, '2025-08-29 07:10:55', NULL, '', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(223, NULL, 123, 127, 3, 'cancelled', 1200, '2025-08-31', NULL, 0, 0, 0, 0, '2025-08-29 21:26:20', NULL, 'NQVVMV5E', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(224, NULL, 112, 114, 3, 'completed', 3900, '2025-08-29', NULL, 0, 0, 390, 117, '2025-08-29 21:27:13', NULL, '', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(225, NULL, 124, 128, 3, 'completed', 3600, '2025-08-31', NULL, 0, 0, 360, 108, '2025-08-30 09:14:27', NULL, 'NQVVMV5E', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(226, NULL, 125, 129, 3, 'completed', 2400, '2025-08-31', NULL, 0, 0, 240, 72, '2025-08-30 09:15:22', NULL, 'NQVVMV5E', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(227, NULL, 126, 130, 3, 'completed', 2400, '2025-08-31', NULL, 0, 0, 240, 72, '2025-08-30 09:16:19', NULL, 'NQVVMV5E', '', 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(228, NULL, 127, 131, 3, 'cancelled', 3500, '2025-08-31', NULL, 0, 0, 0, 0, '2025-08-30 09:22:05', NULL, 'NQVVMV5E', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(229, NULL, 128, 132, 5, 'completed', 1100, '2025-08-31', NULL, 0, 0, 150, 45, '2025-08-30 17:12:55', NULL, 'NQVVMV5E', '', 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(230, NULL, 129, 133, 3, 'completed', 2100, '2025-09-03', NULL, 0, 0, 210, 63, '2025-09-02 18:54:15', NULL, 'NQVVMV5E', '', 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(231, NULL, 130, 134, 3, 'completed', 2200, '2025-09-04', NULL, 0, 0, 220, 66, '2025-09-04 09:41:01', NULL, 'NQVVMV5E', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(232, NULL, 131, 135, 5, 'completed', 2500, '2025-09-04', NULL, 0, 0, 250, 75, '2025-09-04 11:28:18', NULL, 'NQVVMV5E', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(233, NULL, 132, 136, 5, 'completed', 1400, '2025-09-04', NULL, 0, 0, 140, 42, '2025-09-04 13:14:10', NULL, 'NQVVMV5E', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(234, NULL, 133, 137, 4, 'completed', 3300, '2025-09-05', NULL, 0, 0, 330, 99, '2025-09-04 14:46:09', NULL, 'NQVVMV5E', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(235, NULL, 134, 138, 6, 'cancelled', 1100, '2025-09-04', NULL, 0, 0, 0, 0, '2025-09-04 16:12:02', NULL, 'NQVVMV5E', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(236, NULL, 135, 139, 5, 'completed', 4700, '2025-09-05', NULL, 0, 0, 470, 141, '2025-09-05 10:52:04', NULL, 'NQVVMV5E', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(237, NULL, 136, 140, 3, 'completed', 1800, '2025-09-06', NULL, 0, 0, 180, 54, '2025-09-05 10:54:51', NULL, 'NQVVMV5E', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(238, NULL, 137, 141, 5, 'completed', 4700, '2025-09-05', NULL, 0, 0, 470, 141, '2025-09-05 17:46:29', NULL, 'NQVVMV5E', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(239, NULL, 136, 142, 4, 'completed', 990, '2025-09-06', NULL, 110, 0, 99, 29, '2025-09-06 10:49:48', NULL, '7WLNVMT8', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(240, NULL, 138, 143, 3, 'completed', 1100, '2025-09-07', NULL, 0, 0, 110, 33, '2025-09-06 20:49:22', NULL, 'NQVVMV5E', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(241, NULL, 139, 144, 4, 'completed', 2500, '2025-09-07', NULL, 0, 0, 250, 75, '2025-09-07 07:40:47', NULL, 'NQVVMV5E', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(242, NULL, 112, 114, 5, 'completed', 3600, '2025-09-07', NULL, 0, 0, 360, 108, '2025-09-07 10:32:54', NULL, '', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(243, NULL, 140, 146, 3, 'completed', 1100, '2025-09-07', NULL, 0, 0, 33, 0, '2025-09-07 11:44:16', NULL, '', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(244, NULL, 141, 147, 6, 'completed', 2500, '2025-09-03', NULL, 0, 0, 75, 0, '2025-09-07 11:46:40', NULL, '', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(245, NULL, 142, 148, 5, 'completed', 1100, '2025-09-07', NULL, 0, 0, 110, 33, '2025-09-07 11:53:16', NULL, 'NQVVMV5E', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(246, NULL, 143, 150, 5, 'completed', 2200, '2025-09-07', NULL, 0, 0, 66, 0, '2025-09-07 12:39:54', NULL, '', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(247, NULL, 144, 151, 6, 'completed', 1400, '2025-09-07', NULL, 0, 0, 42, 0, '2025-09-07 13:32:51', NULL, '', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(248, NULL, 145, 153, 6, 'completed', 1100, '2025-09-07', NULL, 0, 0, 33, 0, '2025-09-07 14:39:52', NULL, '', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(249, NULL, 146, 154, 5, 'completed', 1400, '2025-09-08', NULL, 0, 0, 140, 42, '2025-09-08 11:42:52', NULL, 'NQVVMV5E', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(250, NULL, 147, 155, 5, 'completed', 1100, '2025-09-08', NULL, 0, 0, 110, 33, '2025-09-08 11:43:39', NULL, 'NQVVMV5E', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(251, NULL, 148, 156, 5, 'completed', 3600, '2025-09-08', NULL, 0, 0, 360, 108, '2025-09-08 11:52:51', NULL, 'NQVVMV5E', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(252, NULL, 149, 157, 3, 'completed', 2500, '2025-09-08', NULL, 0, 0, 75, 0, '2025-09-08 12:01:05', NULL, '', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(253, NULL, 150, 158, 5, 'completed', 1400, '2025-09-08', NULL, 0, 0, 42, 0, '2025-09-08 13:18:11', NULL, '', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(254, NULL, 142, 148, 6, 'completed', 2500, '2025-09-08', NULL, 0, 0, 250, 75, '2025-09-08 14:09:53', NULL, '', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(255, NULL, 151, 160, 6, 'completed', 1100, '2025-09-08', NULL, 0, 0, 33, 0, '2025-09-08 17:08:46', NULL, '', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(256, NULL, 152, 161, 4, 'completed', 3700, '2025-09-09', NULL, 0, 0, 370, 111, '2025-09-09 07:10:30', NULL, 'NQVVMV5E', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(257, NULL, 153, 162, 4, 'completed', 6600, '2025-09-09', NULL, 0, 0, 660, 198, '2025-09-09 10:17:57', NULL, 'NQVVMV5E', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(258, NULL, 154, 163, 6, 'completed', 2500, '2025-09-09', NULL, 0, 0, 75, 0, '2025-09-09 12:56:42', NULL, '', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(259, NULL, 155, 164, 3, 'cancelled', 2500, '2025-09-10', NULL, 0, 0, 0, 0, '2025-09-09 12:59:10', NULL, '', 'Привезти до 14', 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(260, NULL, 156, 165, 6, 'completed', 3300, '2025-09-11', NULL, 0, 0, 330, 99, '2025-09-11 12:46:20', NULL, 'NQVVMV5E', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(261, NULL, 157, 166, 5, 'completed', 6900, '2025-09-11', NULL, 0, 0, 690, 207, '2025-09-11 12:47:20', NULL, 'NQVVMV5E', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(262, NULL, 158, 168, 3, 'completed', 2200, '2025-09-13', NULL, 0, 0, 66, 0, '2025-09-13 11:38:37', NULL, '', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(263, NULL, 159, 169, 3, 'completed', 2500, '2025-09-13', NULL, 0, 0, 75, 0, '2025-09-13 11:43:48', NULL, '', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(264, NULL, 160, 170, 4, 'completed', 1100, '2025-09-12', NULL, 0, 0, 110, 33, '2025-09-13 11:55:11', NULL, 'NQVVMV5E', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(265, NULL, 161, 171, 6, 'completed', 2500, '2025-09-13', NULL, 0, 0, 75, 0, '2025-09-13 13:44:57', NULL, '', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(266, NULL, 162, 172, 6, 'completed', 1400, '2025-09-13', NULL, 0, 0, 140, 42, '2025-09-13 15:58:19', NULL, 'NQVVMV5E', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(267, NULL, 163, 173, 6, 'completed', 2500, '2025-09-13', NULL, 0, 0, 250, 75, '2025-09-13 18:17:30', NULL, 'NQVVMV5E', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(268, NULL, 118, 120, 6, 'completed', 1400, '2025-09-13', NULL, 0, 0, 42, 0, '2025-09-13 18:18:18', NULL, '', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(269, NULL, 164, 174, 5, 'completed', 1400, '2025-09-18', NULL, 0, 0, 140, 42, '2025-09-18 21:06:46', NULL, 'NQVVMV5E', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(270, NULL, 165, 176, 6, 'completed', 1100, '2025-09-18', NULL, 0, 0, 33, 0, '2025-09-19 07:35:01', NULL, '', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(271, NULL, 166, 178, 4, 'completed', 1100, '2025-09-19', NULL, 0, 0, 33, 0, '2025-09-19 08:35:37', NULL, '', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(272, NULL, 167, 179, 6, 'completed', 1100, '2025-09-19', NULL, 0, 0, 110, 33, '2025-09-19 09:20:12', NULL, 'NQVVMV5E', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(273, NULL, 169, 180, 5, 'completed', 1100, '2025-09-19', NULL, 0, 0, 110, 33, '2025-09-19 13:03:06', NULL, 'NQVVMV5E', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(278, NULL, 171, 182, 4, 'completed', 1100, '2025-09-20', NULL, 0, 0, 110, 33, '2025-09-20 07:49:46', NULL, 'NQVVMV5E', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(279, NULL, 172, 183, 4, 'completed', 4700, '2025-09-20', NULL, 0, 0, 141, 0, '2025-09-20 14:28:05', NULL, '', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(284, NULL, 122, 187, 5, 'completed', 15400, '2025-09-19', NULL, 0, 0, 1540, 462, '2025-09-22 07:17:54', NULL, '', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(285, NULL, 175, 188, 5, 'completed', 2100, '2025-09-18', NULL, 0, 0, 66, 0, '2025-09-22 15:21:53', NULL, '7WLNVMT8', '', 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(287, NULL, 176, 190, 3, 'completed', 1100, '2025-09-23', NULL, 0, 0, 33, 0, '2025-09-23 08:23:58', NULL, '', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(288, NULL, 177, 191, 3, 'completed', 3600, '2025-09-24', NULL, 0, 0, 360, 108, '2025-09-24 06:57:49', NULL, 'NQVVMV5E', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(289, NULL, 149, 157, 5, 'completed', 2375, '2025-09-24', NULL, 0, 125, 71, 0, '2025-09-24 12:52:02', NULL, '', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(290, NULL, 178, 192, 5, 'completed', 2500, '2025-09-26', NULL, 0, 0, 75, 0, '2025-09-26 10:44:35', NULL, '7WLNVMT8', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(291, NULL, 152, 161, 6, 'completed', 2500, '2025-09-26', NULL, 0, 0, 250, 75, '2025-09-26 15:36:27', NULL, '', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(292, NULL, 179, 194, 3, 'completed', 1100, '2025-09-27', NULL, 0, 0, 33, 0, '2025-09-27 11:04:05', NULL, '', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(293, NULL, 149, 157, 3, 'completed', 1282, '2025-09-28', NULL, 0, 118, 38, 0, '2025-09-28 06:45:06', NULL, '', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(294, NULL, 149, 157, 3, 'completed', 1400, '2025-09-28', NULL, 0, 0, 42, 0, '2025-09-28 06:46:09', NULL, '', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(295, NULL, 180, 195, 5, 'completed', 2200, '2025-09-28', NULL, 0, 0, 220, 66, '2025-09-28 11:48:26', NULL, 'NQVVMV5E', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(296, NULL, 181, 196, 5, 'completed', 3300, '2025-09-30', NULL, 0, 0, 99, 0, '2025-09-30 09:21:16', NULL, '', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(299, NULL, 183, 200, 5, 'completed', 1100, '2025-09-30', NULL, 0, 0, 33, 0, '2025-09-30 18:41:46', NULL, '', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(300, NULL, 184, 202, 4, 'completed', 1100, '2025-10-01', NULL, 0, 0, 33, 0, '2025-09-30 18:44:54', NULL, '', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(301, NULL, 182, 198, 3, 'completed', 3600, '2025-09-30', NULL, 0, 0, 360, 108, '2025-09-30 18:46:26', NULL, '', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(302, NULL, 149, 157, 3, 'completed', 2366, '2025-10-01', NULL, 0, 134, 70, 0, '2025-10-01 07:54:35', NULL, '', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(303, NULL, 172, 183, 6, 'completed', 2500, '2025-10-02', NULL, 0, 0, 75, 0, '2025-10-01 16:26:20', NULL, '', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(304, NULL, 116, 203, 4, 'completed', 2500, '2025-10-02', NULL, 0, 0, 250, 75, '2025-10-01 19:07:55', NULL, '', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(331, NULL, 112, 114, 3, 'completed', 3600, '2025-10-02', NULL, 0, 0, 360, 108, '2025-10-01 19:36:36', NULL, '', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(332, NULL, 186, 204, 3, 'completed', 1100, '2025-10-02', NULL, 0, 0, 110, 33, '2025-10-01 19:37:33', NULL, 'NQVVMV5E', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(333, NULL, 118, 120, 6, 'completed', 1400, '2025-10-02', NULL, 0, 0, 42, 0, '2025-10-01 19:50:57', NULL, '', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(334, NULL, 187, 205, 4, 'completed', 2200, '2025-10-02', NULL, 0, 0, 220, 66, '2025-10-02 08:10:10', NULL, 'NQVVMV5E', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(335, NULL, 188, 206, 5, 'completed', 3600, '2025-10-02', NULL, 0, 0, 108, 0, '2025-10-02 10:39:28', NULL, '7WLNVMT8', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(336, NULL, 189, 207, 5, 'completed', 1100, '2025-10-02', NULL, 0, 0, 110, 33, '2025-10-02 12:18:52', NULL, 'NQVVMV5E', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(337, NULL, 190, 208, 5, 'completed', 1100, '2025-10-02', NULL, 0, 0, 110, 33, '2025-10-02 12:19:43', NULL, 'NQVVMV5E', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(338, NULL, 78, 209, 6, 'completed', 1100, '2025-10-05', NULL, 0, 0, 33, 0, '2025-10-05 17:20:33', NULL, '', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(339, NULL, 193, 210, 5, 'completed', 1100, '2025-10-05', NULL, 0, 0, 110, 33, '2025-10-06 10:25:00', NULL, 'NQVVMV5E', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(340, NULL, 194, 211, 4, 'completed', 2200, '2025-10-06', NULL, 0, 0, 220, 66, '2025-10-06 10:26:21', NULL, 'NQVVMV5E', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(341, NULL, 195, 213, 4, 'completed', 2600, '2025-10-08', NULL, 0, 0, 78, 0, '2025-10-07 18:25:56', NULL, '', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(342, NULL, 196, 214, 6, 'cancelled', 1600, '2025-10-08', NULL, 0, 0, 48, 0, '2025-10-07 18:28:18', NULL, '7WLNVMT8', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(343, NULL, 118, 120, 4, 'cancelled', 1600, '2025-10-08', NULL, 0, 0, 0, 0, '2025-10-07 18:29:03', NULL, '', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(344, NULL, 116, 203, 6, 'completed', 1600, '2025-10-08', NULL, 0, 0, 160, 48, '2025-10-08 15:19:52', NULL, '', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(345, NULL, 197, 215, 3, 'completed', 2900, '2025-10-14', NULL, 0, 0, 87, 0, '2025-10-09 05:43:22', NULL, '', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(349, NULL, 198, 217, 3, 'completed', 2600, '2025-10-14', NULL, 0, 0, 78, 0, '2025-10-09 05:48:21', NULL, '', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(351, NULL, 199, 220, 3, 'cancelled', 2600, '2025-10-14', NULL, 0, 0, 0, 0, '2025-10-09 07:40:50', NULL, '', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(352, NULL, 200, 222, 3, 'cancelled', 1300, '2025-10-14', NULL, 0, 0, 0, 0, '2025-10-09 07:43:13', NULL, '', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(353, NULL, 202, 223, 3, 'completed', 2900, '2025-10-14', NULL, 0, 0, 87, 0, '2025-10-11 18:23:17', NULL, '7WLNVMT8', '', 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(355, NULL, 204, 225, 3, 'completed', 1600, '2025-10-14', NULL, 0, 0, 48, 0, '2025-10-12 17:12:10', NULL, '7WLNVMT8', '', 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(356, NULL, 205, 226, 3, 'completed', 7800, '2025-10-14', NULL, 0, 0, 234, 0, '2025-10-12 17:17:28', NULL, '7WLNVMT8', '', 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(357, NULL, 206, 227, 3, 'cancelled', 2600, '2025-10-14', NULL, 0, 0, 0, 0, '2025-10-13 18:25:08', NULL, 'NQVVMV5E', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(358, NULL, 203, 228, 3, 'completed', 5900, '2025-10-15', NULL, 0, 0, 177, 0, '2025-10-14 10:13:57', NULL, '', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(359, NULL, 118, 120, 5, 'completed', 1700, '2025-10-14', NULL, 0, 0, 51, 0, '2025-10-14 10:15:16', NULL, '', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(360, NULL, 116, 203, 6, 'completed', 3100, '2025-10-14', NULL, 0, 0, 310, 93, '2025-10-14 10:16:52', NULL, '', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(361, NULL, 207, 229, 5, 'completed', 1700, '2025-10-15', NULL, 0, 0, 51, 0, '2025-10-15 18:57:25', NULL, '7WLNVMT8', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(362, NULL, 208, 230, 4, 'completed', 1700, '2025-10-16', NULL, 0, 0, 51, 0, '2025-10-16 09:01:06', NULL, '7WLNVMT8', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(363, NULL, 149, 157, 6, 'completed', 1582, '2025-10-16', NULL, 0, 118, 47, 0, '2025-10-16 14:02:44', NULL, '', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(364, NULL, 209, 231, 4, 'completed', 1400, '2025-10-17', NULL, 0, 0, 140, 42, '2025-10-17 08:29:59', NULL, 'NQVVMV5E', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(365, NULL, 210, 232, 4, 'completed', 1400, '2025-10-17', NULL, 0, 0, 140, 42, '2025-10-17 08:54:13', NULL, 'NQVVMV5E', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(366, NULL, 203, 233, 3, 'completed', 1400, '2025-10-17', NULL, 0, 0, 42, 0, '2025-10-17 09:16:16', NULL, '', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(367, NULL, 211, 234, 4, 'completed', 1700, '2025-10-18', NULL, 0, 0, 51, 0, '2025-10-18 10:29:05', NULL, '7WLNVMT8', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(368, NULL, 208, 230, 3, 'completed', 1700, '2025-10-18', NULL, 0, 0, 51, 0, '2025-10-18 10:31:05', NULL, '', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(369, NULL, 212, 235, 5, 'completed', 3100, '2025-10-18', NULL, 0, 0, 93, 0, '2025-10-18 11:15:37', NULL, '7WLNVMT8', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(371, NULL, 116, 203, 3, 'completed', 3300, '2025-10-20', NULL, 0, 0, 330, 99, '2025-10-19 17:50:17', NULL, '', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(372, NULL, 195, 213, 3, 'cancelled', 3300, '2025-10-22', NULL, 0, 0, 0, 0, '2025-10-20 17:00:47', NULL, '', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(373, NULL, 214, 237, 3, 'completed', 1500, '2025-10-22', NULL, 0, 0, 45, 0, '2025-10-20 19:46:59', NULL, '7WLNVMT8', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(374, NULL, 215, 238, 3, 'cancelled', 1500, '2025-10-22', NULL, 0, 0, 0, 0, '2025-10-21 08:01:01', NULL, '7WLNVMT8', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(375, NULL, 216, 239, 4, 'completed', 1800, '2025-10-21', NULL, 0, 0, 0, 0, '2025-10-21 10:25:51', NULL, '', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(376, NULL, 217, 240, 3, 'cancelled', 1500, '2025-10-22', NULL, 0, 0, 0, 0, '2025-10-21 12:52:01', NULL, 'NQVVMV5E', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(377, NULL, 218, 241, 3, 'completed', 1500, '2025-10-25', NULL, 0, 0, 45, 0, '2025-10-22 14:08:18', NULL, '7WLNVMT8', '', 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(378, NULL, 219, 242, 3, 'completed', 4100, '2025-10-22', NULL, 0, 0, 123, 0, '2025-10-22 20:21:25', NULL, '7WLNVMT8', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(379, NULL, 220, 243, 6, 'completed', 1500, '2025-10-23', NULL, 0, 0, 45, 0, '2025-10-23 10:41:40', NULL, '7WLNVMT8', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(383, NULL, 223, 246, 6, 'cancelled', 3000, '2025-10-23', NULL, 0, 0, 0, 0, '2025-10-23 18:08:11', NULL, '7WLNVMT8', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(397, NULL, 232, 257, 5, 'completed', 1400, '2026-05-08', NULL, 0, 0, 42, 0, '2026-05-08 14:23:36', NULL, '7WLNVMT8', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(398, NULL, 233, 258, 6, 'completed', 1100, '2026-05-08', NULL, 0, 0, 33, 0, '2026-05-08 14:25:38', NULL, '7WLNVMT8', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(399, NULL, 234, 259, 3, 'completed', 1100, '2026-05-08', NULL, 0, 0, 33, 0, '2026-05-08 14:27:07', NULL, '7WLNVMT8', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(400, NULL, 235, 260, 3, 'cancelled', 1100, '2026-05-10', NULL, 0, 0, 0, 0, '2026-05-08 14:28:57', NULL, '7WLNVMT8', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(401, NULL, 116, 203, 4, 'completed', 1400, '2026-05-08', NULL, 0, 0, 140, 42, '2026-05-08 14:38:50', NULL, '', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(402, NULL, 236, 261, 6, 'completed', 1100, '2026-05-08', NULL, 0, 0, 33, 0, '2026-05-08 16:29:36', NULL, '7WLNVMT8', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(404, NULL, 237, 262, 5, 'completed', 1500, '2026-05-10', NULL, 0, 0, 45, 0, '2026-05-11 06:54:57', NULL, '7WLNVMT8', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(405, NULL, 238, 263, 3, 'completed', 1600, '2026-05-11', NULL, 0, 0, 48, 0, '2026-05-11 07:00:48', NULL, '7WLNVMT8', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(406, NULL, 239, 264, 5, 'completed', 1200, '2026-05-09', NULL, 0, 0, 36, 0, '2026-05-11 07:06:18', NULL, '7WLNVMT8', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(407, NULL, 240, 265, 3, 'completed', 2400, '2026-05-09', NULL, 0, 0, 72, 0, '2026-05-11 07:07:19', NULL, '7WLNVMT8', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(408, NULL, 117, 119, 3, 'completed', 10800, '2026-05-08', NULL, 0, 0, 324, 0, '2026-05-11 07:08:46', NULL, '', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(410, NULL, 241, 266, 6, 'completed', 2600, '2026-05-11', NULL, 0, 0, 78, 0, '2026-05-11 16:55:15', NULL, '7WLNVMT8', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(412, NULL, 243, 268, 4, 'completed', 1500, '2026-05-14', NULL, 0, 0, 45, 0, '2026-05-14 09:24:34', NULL, '7WLNVMT8', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL);
INSERT INTO `orders` (`id`, `order_group_id`, `user_id`, `address_id`, `slot_id`, `status`, `total_amount`, `delivery_date`, `created_by_user_id`, `discount_applied`, `points_used`, `points_accrued`, `manager_points_accrued`, `created_at`, `assigned_to`, `coupon_code`, `comment`, `order_mode`, `purchase_batch_id`, `reserved_at`, `fulfilled_from_stock_at`, `bonuses_allowed`, `coupons_allowed`, `payment_status`, `delivery_fee`, `delivery_distance_km`, `delivery_tariff_zone_id`, `delivery_pricing_source`, `delivery_comment`, `payment_method`, `payment_provider`, `payment_invoice_id`, `payment_amount`, `paid_at`, `refunded_at`, `refund_comment`, `payment_raw_response`) VALUES
(413, NULL, 244, 269, 3, 'completed', 1400, '2026-05-14', NULL, 0, 0, 42, 0, '2026-05-14 09:31:36', NULL, '7WLNVMT8', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(415, NULL, 246, 271, 3, 'cancelled', 1100, '2026-05-17', NULL, 0, 0, 33, 0, '2026-05-14 13:09:33', NULL, '7WLNVMT8', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(427, NULL, 245, 270, 6, 'cancelled', 1420, '2026-05-15', NULL, 0, 0, 0, 0, '2026-05-14 18:30:41', NULL, '', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(429, NULL, 247, 272, 6, 'completed', 1120, '2026-05-14', NULL, 0, 0, 33, 0, '2026-05-14 18:32:04', NULL, '7WLNVMT8', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(430, NULL, 248, 273, 3, 'completed', 1120, '2026-05-15', NULL, 0, 0, 33, 0, '2026-05-14 18:34:19', NULL, '7WLNVMT8', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(432, NULL, 249, 274, 3, 'completed', 1120, '2026-05-15', NULL, 0, 0, 33, 0, '2026-05-15 10:10:01', NULL, '7WLNVMT8', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(433, NULL, 250, 275, 5, 'completed', 1120, '2026-05-15', NULL, 0, 0, 33, 0, '2026-05-15 11:41:25', NULL, '7WLNVMT8', 'Заберет в 17:30', 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(434, NULL, 116, 203, 4, 'completed', 1420, '2026-05-15', NULL, 0, 0, 42, 42, '2026-05-15 11:58:23', NULL, '', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(435, NULL, 251, 276, 6, 'cancelled', 1120, '2026-05-15', NULL, 0, 0, 0, 0, '2026-05-15 13:48:00', NULL, '7WLNVMT8', 'Написать, когда придет Клери', 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(436, NULL, 252, 277, 5, 'completed', 1420, '2026-05-15', NULL, 0, 0, 42, 0, '2026-05-15 19:15:07', NULL, '7WLNVMT8', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(437, NULL, 253, 278, 6, 'completed', 1120, '2026-05-15', NULL, 0, 0, 33, 0, '2026-05-15 19:34:27', NULL, '7WLNVMT8', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(438, NULL, 254, 279, 4, 'cancelled', 1500, '2026-05-17', NULL, 0, 0, 0, 0, '2026-05-17 09:32:45', NULL, '7WLNVMT8', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(439, NULL, 255, 280, 3, 'completed', 1800, '2026-05-18', NULL, 0, 0, 54, 0, '2026-05-17 10:33:00', NULL, '7WLNVMT8', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(441, NULL, 116, 118, 5, 'completed', 1900, '2026-05-19', NULL, 0, 0, 57, 57, '2026-05-19 18:19:42', NULL, '', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(442, NULL, 256, 281, 5, 'completed', 1120, '2026-05-19', NULL, 0, 0, 33, 0, '2026-05-19 18:23:06', NULL, '7WLNVMT8', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(443, NULL, 257, 282, 5, 'completed', 1900, '2026-05-19', NULL, 0, 0, 57, 0, '2026-05-19 18:24:36', NULL, '7WLNVMT8', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(446, NULL, 258, 283, 3, 'completed', 4500, '2026-05-22', NULL, 0, 0, 135, 0, '2026-05-20 17:30:21', NULL, '7WLNVMT8', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(447, NULL, 259, 284, 5, 'completed', 1800, '2026-05-20', NULL, 0, 0, 54, 0, '2026-05-20 17:44:38', NULL, '7WLNVMT8', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(454, NULL, 117, 119, 5, 'completed', 21810, '2026-05-22', 17, 0, 1140, 654, 0, '2026-05-22 08:59:08', NULL, '', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(455, NULL, 243, 268, 3, 'completed', 1800, '2026-05-22', 17, 0, 0, 54, 0, '2026-05-22 09:00:22', NULL, '', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(456, NULL, 260, 285, 5, 'cancelled', 0, '2026-05-24', 17, 0, 0, 0, 0, '2026-05-24 10:47:21', NULL, '7WLNVMT8', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(457, NULL, 116, 203, 5, 'completed', 1200, '2026-05-25', 17, 0, 0, 36, 36, '2026-05-25 13:12:51', NULL, '', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(458, NULL, 261, 286, 6, 'completed', 1200, '2026-05-25', 17, 0, 0, 36, 0, '2026-05-25 14:21:36', NULL, '7WLNVMT8', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(459, NULL, 262, 287, 5, 'completed', 1200, '2026-05-25', 17, 0, 0, 36, 0, '2026-05-25 16:16:41', NULL, '7WLNVMT8', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(461, NULL, 264, 289, 6, 'completed', 1200, '2026-05-28', 17, 0, 0, 36, 0, '2026-05-28 10:22:39', NULL, '7WLNVMT8', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(462, NULL, 265, 290, 4, 'completed', 3400, '2026-05-28', 17, 0, 0, 102, 0, '2026-05-28 17:32:52', NULL, '7WLNVMT8', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(463, NULL, 172, 291, 6, 'completed', 1200, '2026-05-28', 17, 0, 0, 36, 0, '2026-05-28 17:35:32', NULL, '', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(466, NULL, 266, 292, 5, 'completed', 1350, '2026-06-01', 17, 0, 0, 40, 0, '2026-06-02 11:01:29', NULL, '', NULL, 'instant', 24, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(467, NULL, 116, 203, 5, 'cancelled', 1800, '2026-06-02', 17, 0, 0, 54, 54, '2026-06-02 13:35:29', NULL, '', NULL, 'instant', 24, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(468, NULL, 267, 294, 6, 'completed', 1800, '2026-06-02', NULL, 0, 0, 0, 0, '2026-06-02 14:26:24', NULL, '', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(469, NULL, 172, 183, 3, 'completed', 1800, '2026-06-02', 17, 0, 0, 54, 0, '2026-06-02 17:00:06', NULL, '', NULL, 'instant', 24, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(472, NULL, 116, 295, 3, 'completed', 500, '2026-06-02', 17, 0, 1000, 15, 15, '2026-06-03 10:08:40', NULL, '', NULL, 'instant', 24, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(473, NULL, 117, 296, 3, 'completed', 500, '2026-06-02', 17, 0, 1000, 15, 0, '2026-06-03 10:11:24', NULL, '', NULL, 'instant', 24, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(474, NULL, 1, 297, 3, 'completed', 926, '2026-06-03', NULL, 0, 74, 0, 0, '2026-06-03 10:20:30', NULL, '', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(477, NULL, 259, 284, 4, 'completed', 1800, '2026-06-05', 17, 0, 0, 45, 0, '2026-06-06 09:31:27', NULL, '', NULL, 'instant', 25, NULL, NULL, 1, 1, 'unpaid', 300, NULL, NULL, 'pending_review', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(478, NULL, 268, 299, 3, 'completed', 1800, '2026-06-05', 17, 0, 0, 45, 0, '2026-06-06 09:35:21', NULL, '', NULL, 'instant', 25, NULL, NULL, 1, 1, 'unpaid', 300, NULL, NULL, 'pending_review', 'Подъезд 9', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(481, NULL, 269, 300, 3, 'completed', 1650, '2026-06-11', 17, 0, 0, 0, 40, '2026-06-10 07:00:14', NULL, '', NULL, 'instant', 25, NULL, NULL, 1, 1, 'unpaid', 300, 1.237, 1, 'tariff_zone', 'Доставка в 10:00. Ящик с крупной ягодой', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(482, NULL, 270, 301, 3, 'completed', 1200, '2026-06-11', 17, 0, 0, 0, 42, '2026-06-12 20:23:43', NULL, '', NULL, 'instant', 30, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, 'pickup', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(483, NULL, 265, 290, 3, 'completed', 2100, '2026-06-11', 17, 0, 0, 0, 54, '2026-06-12 20:26:15', NULL, '', NULL, 'instant', 28, NULL, NULL, 1, 1, 'unpaid', 300, NULL, NULL, 'pending_review', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(484, NULL, 265, 290, 3, 'completed', 3000, '2026-06-11', 17, 0, 0, 0, 81, '2026-06-12 20:27:06', NULL, '', NULL, 'instant', 30, NULL, NULL, 1, 1, 'unpaid', 300, NULL, NULL, 'pending_review', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(485, NULL, 271, 302, 3, 'completed', 6650, '2026-06-11', 17, 0, 0, 0, 199, '2026-06-12 20:33:57', NULL, '', NULL, 'instant', 30, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, 'pickup', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(486, NULL, 1, 32, 3, 'cancelled', 2054, '2026-06-15', NULL, 0, 46, 0, 0, '2026-06-14 11:09:52', NULL, '', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 300, NULL, NULL, 'pending_review', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(487, NULL, 273, 304, 3, 'completed', 1950, '2026-06-15', 17, 0, 0, 0, 45, '2026-06-16 15:50:48', NULL, '', NULL, 'instant', 34, NULL, NULL, 1, 1, 'unpaid', 450, 10.542, 4, 'tariff_zone', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(488, NULL, 274, 305, 3, 'completed', 1500, '2026-06-17', 17, 0, 0, 0, 45, '2026-06-17 15:37:07', NULL, '', NULL, 'instant', 34, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, 'pickup', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(489, NULL, 275, 306, 6, 'completed', 1850, '2026-06-17', 17, 0, 0, 0, 45, '2026-06-17 17:09:39', NULL, '', NULL, 'instant', 40, NULL, NULL, 1, 1, 'unpaid', 350, 2.227, 2, 'tariff_zone', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(490, NULL, 276, 307, 4, 'completed', 1500, '2026-06-18', 17, 0, 0, 0, 45, '2026-06-17 17:11:08', NULL, '', NULL, 'instant', 40, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, 'pickup', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(491, NULL, 172, 183, 6, 'completed', 1800, '2026-06-18', 17, 0, 0, 0, 45, '2026-06-18 17:02:21', NULL, '', NULL, 'instant', 40, NULL, NULL, 1, 1, 'unpaid', 300, NULL, NULL, 'pending_review', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(492, NULL, 277, 308, 6, 'completed', 3650, '2026-06-18', 17, 0, 0, 0, 99, '2026-06-18 17:03:55', NULL, '', NULL, 'instant', NULL, NULL, NULL, 1, 1, 'unpaid', 350, 2.086, 2, 'tariff_zone', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(493, NULL, 278, 309, 3, 'completed', 1500, '2026-06-18', 17, 0, 0, 0, 45, '2026-06-18 17:06:05', NULL, '', NULL, 'instant', 40, NULL, NULL, 1, 1, 'unpaid', 0, NULL, NULL, 'pickup', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Структура таблицы `order_groups`
--

CREATE TABLE `order_groups` (
  `id` bigint UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `created_by_user_id` int UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `comment` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Дамп данных таблицы `order_groups`
--

INSERT INTO `order_groups` (`id`, `user_id`, `created_by_user_id`, `created_at`, `comment`) VALUES
(1, 1, 1, '2026-07-14 05:23:49', NULL),
(2, 1, NULL, '2026-07-14 06:21:10', 'client checkout');

-- --------------------------------------------------------

--
-- Структура таблицы `order_items`
--

CREATE TABLE `order_items` (
  `id` bigint UNSIGNED NOT NULL,
  `order_id` int UNSIGNED NOT NULL,
  `product_id` int UNSIGNED NOT NULL,
  `quantity` decimal(6,1) NOT NULL,
  `unit_price` decimal(8,0) NOT NULL,
  `boxes` decimal(10,0) NOT NULL DEFAULT '0',
  `purchase_batch_id` int UNSIGNED DEFAULT NULL,
  `stock_mode` enum('preorder','instant','discount_stock') NOT NULL DEFAULT 'instant',
  `cost_unit_price` decimal(10,2) NOT NULL DEFAULT '0.00',
  `cost_price_per_box` decimal(10,2) NOT NULL DEFAULT '0.00',
  `sale_price_per_box` decimal(10,2) NOT NULL DEFAULT '0.00',
  `margin_amount` decimal(10,2) NOT NULL DEFAULT '0.00'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Дамп данных таблицы `order_items`
--

INSERT INTO `order_items` (`id`, `order_id`, `product_id`, `quantity`, `unit_price`, `boxes`, `purchase_batch_id`, `stock_mode`, `cost_unit_price`, `cost_price_per_box`, `sale_price_per_box`, `margin_amount`) VALUES
(1, 131, 19, 4.0, 750, 2, NULL, 'instant', 0.00, 0.00, 1500.00, 0.00),
(2, 131, 20, 2.0, 687, 1, NULL, 'instant', 0.00, 0.00, 1374.00, 0.00),
(3, 132, 6, 2.0, 1000, 1, NULL, 'instant', 0.00, 0.00, 2000.00, 0.00),
(4, 132, 18, 2.0, 687, 1, NULL, 'instant', 0.00, 0.00, 1374.00, 0.00),
(5, 132, 20, 2.0, 687, 1, NULL, 'instant', 0.00, 0.00, 1374.00, 0.00),
(6, 133, 18, 2.0, 687, 1, NULL, 'instant', 0.00, 0.00, 1374.00, 0.00),
(7, 133, 24, 2.0, 687, 1, NULL, 'instant', 0.00, 0.00, 1374.00, 0.00),
(8, 134, 18, 4.0, 687, 2, NULL, 'instant', 0.00, 0.00, 1374.00, 0.00),
(9, 135, 19, 2.0, 750, 1, NULL, 'instant', 0.00, 0.00, 1500.00, 0.00),
(10, 136, 19, 2.0, 750, 1, NULL, 'instant', 0.00, 0.00, 1500.00, 0.00),
(11, 137, 19, 4.0, 750, 2, NULL, 'instant', 0.00, 0.00, 1500.00, 0.00),
(12, 138, 18, 2.0, 687, 1, NULL, 'instant', 0.00, 0.00, 1374.00, 0.00),
(13, 139, 6, 4.0, 1000, 2, NULL, 'instant', 0.00, 0.00, 2000.00, 0.00),
(14, 139, 19, 2.0, 750, 1, NULL, 'instant', 0.00, 0.00, 1500.00, 0.00),
(15, 140, 7, 2.0, 687, 1, NULL, 'instant', 0.00, 0.00, 1374.00, 0.00),
(16, 141, 18, 8.0, 687, 4, NULL, 'instant', 0.00, 0.00, 1374.00, 0.00),
(17, 142, 18, 2.0, 687, 1, NULL, 'instant', 0.00, 0.00, 1374.00, 0.00),
(18, 142, 20, 2.0, 687, 1, NULL, 'instant', 0.00, 0.00, 1374.00, 0.00),
(19, 146, 7, 4.0, 550, 2, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(20, 147, 18, 2.0, 550, 1, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(21, 147, 20, 2.0, 550, 1, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(22, 148, 20, 2.0, 550, 1, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(23, 149, 18, 6.0, 550, 3, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(24, 149, 20, 6.0, 550, 3, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(25, 150, 18, 2.0, 550, 1, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(26, 151, 18, 2.0, 550, 1, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(27, 152, 18, 6.0, 550, 3, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(28, 152, 20, 6.0, 550, 3, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(29, 153, 18, 8.0, 550, 4, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(30, 153, 20, 14.0, 550, 7, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(31, 154, 20, 2.0, 550, 1, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(32, 155, 18, 6.0, 550, 3, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(33, 156, 20, 2.0, 550, 1, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(34, 157, 18, 6.0, 550, 3, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(35, 158, 18, 2.0, 550, 1, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(36, 158, 20, 2.0, 550, 1, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(37, 159, 19, 2.0, 550, 1, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(38, 159, 20, 4.0, 550, 2, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(39, 160, 18, 14.0, 550, 7, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(40, 161, 18, 4.0, 550, 2, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(41, 161, 20, 2.0, 550, 1, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(42, 162, 18, 4.0, 550, 2, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(43, 162, 19, 3.0, 550, 2, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(44, 162, 20, 3.0, 550, 2, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(45, 163, 19, 2.0, 550, 1, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(46, 164, 18, 2.0, 550, 1, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(47, 164, 19, 1.0, 550, 1, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(48, 164, 20, 1.0, 550, 1, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(49, 165, 18, 4.0, 550, 2, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(50, 166, 18, 2.0, 550, 1, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(51, 167, 18, 8.0, 550, 4, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(52, 168, 20, 4.0, 550, 2, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(53, 169, 18, 6.0, 550, 3, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(54, 169, 19, 8.0, 550, 4, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(55, 170, 20, 4.0, 550, 2, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(56, 171, 18, 4.0, 550, 2, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(57, 172, 18, 4.0, 550, 2, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(58, 173, 18, 10.0, 550, 5, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(59, 174, 20, 2.0, 550, 1, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(60, 175, 18, 4.0, 550, 2, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(61, 176, 6, 2.0, 1600, 1, NULL, 'instant', 0.00, 0.00, 3200.00, 0.00),
(62, 176, 20, 2.0, 550, 1, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(63, 177, 18, 2.0, 550, 1, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(64, 177, 20, 2.0, 550, 1, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(65, 178, 18, 2.0, 550, 1, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(66, 179, 18, 1.0, 550, 1, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(67, 179, 19, 1.0, 600, 1, NULL, 'instant', 0.00, 0.00, 1200.00, 0.00),
(68, 179, 20, 2.0, 600, 1, NULL, 'instant', 0.00, 0.00, 1200.00, 0.00),
(69, 180, 18, 2.0, 550, 1, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(70, 180, 20, 2.0, 600, 1, NULL, 'instant', 0.00, 0.00, 1200.00, 0.00),
(71, 184, 18, 2.0, 550, 1, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(72, 184, 20, 2.0, 600, 1, NULL, 'instant', 0.00, 0.00, 1200.00, 0.00),
(73, 185, 18, 4.0, 550, 2, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(74, 185, 20, 4.0, 600, 2, NULL, 'instant', 0.00, 0.00, 1200.00, 0.00),
(75, 186, 7, 6.0, 600, 3, NULL, 'instant', 0.00, 0.00, 1200.00, 0.00),
(76, 187, 19, 1.0, 600, 1, NULL, 'instant', 0.00, 0.00, 1200.00, 0.00),
(77, 187, 20, 1.0, 600, 1, NULL, 'instant', 0.00, 0.00, 1200.00, 0.00),
(78, 189, 18, 6.0, 550, 3, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(79, 189, 20, 6.0, 600, 3, NULL, 'instant', 0.00, 0.00, 1200.00, 0.00),
(80, 190, 19, 2.0, 600, 1, NULL, 'instant', 0.00, 0.00, 1200.00, 0.00),
(81, 191, 18, 2.0, 550, 1, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(82, 191, 19, 1.0, 600, 1, NULL, 'instant', 0.00, 0.00, 1200.00, 0.00),
(83, 191, 20, 1.0, 600, 1, NULL, 'instant', 0.00, 0.00, 1200.00, 0.00),
(84, 192, 20, 6.0, 600, 3, NULL, 'instant', 0.00, 0.00, 1200.00, 0.00),
(85, 193, 18, 2.0, 550, 1, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(86, 193, 20, 2.0, 600, 1, NULL, 'instant', 0.00, 0.00, 1200.00, 0.00),
(87, 194, 19, 2.0, 600, 1, NULL, 'instant', 0.00, 0.00, 1200.00, 0.00),
(88, 194, 20, 2.0, 600, 1, NULL, 'instant', 0.00, 0.00, 1200.00, 0.00),
(89, 195, 7, 4.0, 550, 2, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(90, 195, 19, 2.0, 550, 1, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(91, 196, 20, 4.0, 600, 2, NULL, 'instant', 0.00, 0.00, 1200.00, 0.00),
(92, 197, 7, 2.0, 550, 1, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(93, 198, 7, 2.0, 550, 1, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(94, 202, 20, 2.0, 600, 1, NULL, 'instant', 0.00, 0.00, 1200.00, 0.00),
(95, 206, 20, 4.0, 600, 2, NULL, 'instant', 0.00, 0.00, 1200.00, 0.00),
(96, 207, 18, 2.0, 550, 1, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(97, 210, 18, 2.0, 550, 1, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(98, 211, 7, 2.0, 600, 1, NULL, 'instant', 0.00, 0.00, 1200.00, 0.00),
(99, 211, 20, 2.0, 600, 1, NULL, 'instant', 0.00, 0.00, 1200.00, 0.00),
(100, 212, 20, 20.0, 600, 10, NULL, 'instant', 0.00, 0.00, 1200.00, 0.00),
(101, 213, 7, 2.0, 600, 1, NULL, 'instant', 0.00, 0.00, 1200.00, 0.00),
(102, 214, 7, 2.0, 600, 1, NULL, 'instant', 0.00, 0.00, 1200.00, 0.00),
(103, 215, 7, 2.0, 600, 1, NULL, 'instant', 0.00, 0.00, 1200.00, 0.00),
(104, 215, 18, 6.0, 550, 3, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(105, 216, 18, 4.0, 550, 2, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(106, 217, 20, 4.0, 600, 2, NULL, 'instant', 0.00, 0.00, 1200.00, 0.00),
(107, 218, 20, 4.0, 600, 2, NULL, 'instant', 0.00, 0.00, 1200.00, 0.00),
(108, 219, 7, 14.0, 600, 7, NULL, 'instant', 0.00, 0.00, 1200.00, 0.00),
(109, 220, 7, 2.0, 600, 1, NULL, 'instant', 0.00, 0.00, 1200.00, 0.00),
(110, 221, 7, 4.0, 600, 2, NULL, 'instant', 0.00, 0.00, 1200.00, 0.00),
(111, 222, 18, 2.0, 550, 1, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(112, 222, 20, 2.0, 600, 1, NULL, 'instant', 0.00, 0.00, 1200.00, 0.00),
(113, 223, 7, 2.0, 600, 1, NULL, 'instant', 0.00, 0.00, 1200.00, 0.00),
(114, 224, 20, 6.0, 600, 3, NULL, 'instant', 0.00, 0.00, 1200.00, 0.00),
(115, 225, 7, 6.0, 600, 3, NULL, 'instant', 0.00, 0.00, 1200.00, 0.00),
(116, 226, 7, 4.0, 600, 2, NULL, 'instant', 0.00, 0.00, 1200.00, 0.00),
(117, 227, 7, 4.0, 600, 2, NULL, 'instant', 0.00, 0.00, 1200.00, 0.00),
(118, 228, 7, 4.0, 600, 2, NULL, 'instant', 0.00, 0.00, 1200.00, 0.00),
(119, 228, 18, 2.0, 550, 1, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(120, 229, 7, 2.0, 550, 1, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(121, 230, 7, 2.0, 550, 1, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(122, 230, 20, 2.0, 500, 1, NULL, 'instant', 0.00, 0.00, 1000.00, 0.00),
(123, 231, 20, 4.0, 550, 2, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(124, 232, 20, 4.0, 550, 2, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(125, 233, 20, 2.0, 550, 1, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(126, 234, 20, 6.0, 550, 3, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(127, 235, 20, 2.0, 550, 1, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(128, 236, 18, 2.0, 550, 1, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(129, 236, 20, 6.0, 550, 3, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(130, 237, 20, 2.0, 900, 1, NULL, 'instant', 0.00, 0.00, 1800.00, 0.00),
(131, 238, 9, 2.0, 550, 1, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(132, 238, 18, 2.0, 550, 1, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(133, 238, 20, 4.0, 550, 2, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(134, 239, 20, 2.0, 550, 1, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(135, 240, 9, 2.0, 550, 1, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(136, 241, 18, 2.0, 550, 1, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(137, 241, 20, 2.0, 550, 1, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(138, 242, 20, 6.0, 550, 3, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(139, 243, 9, 2.0, 550, 1, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(140, 244, 18, 2.0, 550, 1, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(141, 244, 20, 2.0, 550, 1, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(142, 245, 20, 2.0, 550, 1, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(143, 246, 18, 2.0, 550, 1, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(144, 246, 20, 2.0, 550, 1, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(145, 247, 9, 2.0, 550, 1, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(146, 248, 18, 2.0, 550, 1, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(147, 249, 9, 2.0, 550, 1, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(148, 250, 9, 2.0, 550, 1, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(149, 251, 18, 4.0, 550, 2, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(150, 251, 20, 2.0, 550, 1, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(151, 252, 18, 4.0, 550, 2, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(152, 253, 20, 2.0, 550, 1, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(153, 254, 20, 4.0, 550, 2, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(154, 255, 9, 2.0, 550, 1, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(155, 256, 18, 2.0, 550, 1, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(156, 256, 19, 2.0, 600, 1, NULL, 'instant', 0.00, 0.00, 1200.00, 0.00),
(157, 256, 20, 2.0, 550, 1, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(158, 257, 9, 6.0, 550, 3, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(159, 257, 18, 2.0, 550, 1, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(160, 257, 20, 4.0, 550, 2, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(161, 258, 9, 2.0, 550, 1, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(162, 258, 18, 2.0, 550, 1, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(163, 259, 9, 2.0, 550, 1, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(164, 259, 20, 2.0, 550, 1, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(165, 260, 18, 6.0, 550, 3, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(166, 261, 18, 2.0, 550, 1, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(167, 261, 20, 10.0, 550, 5, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(168, 262, 18, 4.0, 550, 2, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(169, 263, 18, 4.0, 550, 2, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(170, 264, 9, 2.0, 550, 1, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(171, 265, 18, 2.0, 550, 1, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(172, 265, 20, 2.0, 550, 1, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(173, 266, 20, 2.0, 550, 1, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(174, 267, 18, 2.0, 550, 1, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(175, 267, 20, 2.0, 550, 1, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(176, 268, 9, 2.0, 550, 1, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(177, 269, 20, 2.0, 550, 1, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(178, 270, 9, 2.0, 550, 1, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(179, 271, 9, 2.0, 550, 1, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(180, 272, 9, 2.0, 550, 1, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(181, 273, 9, 2.0, 550, 1, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(182, 278, 9, 2.0, 550, 1, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(183, 279, 9, 4.0, 550, 2, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(184, 279, 18, 4.0, 550, 2, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(185, 284, 9, 28.0, 550, 14, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(186, 285, 18, 2.0, 525, 1, NULL, 'instant', 0.00, 0.00, 1050.00, 0.00),
(187, 285, 20, 2.0, 525, 1, NULL, 'instant', 0.00, 0.00, 1050.00, 0.00),
(188, 287, 20, 2.0, 550, 1, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(189, 288, 20, 6.0, 550, 3, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(190, 289, 9, 2.0, 550, 1, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(191, 289, 18, 2.0, 550, 1, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(192, 290, 18, 2.0, 550, 1, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(193, 290, 20, 2.0, 550, 1, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(194, 291, 18, 2.0, 550, 1, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(195, 291, 20, 2.0, 550, 1, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(196, 292, 18, 2.0, 550, 1, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(197, 293, 18, 2.0, 550, 1, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(198, 294, 9, 2.0, 550, 1, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(199, 295, 9, 4.0, 550, 2, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(200, 296, 20, 6.0, 550, 3, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(201, 299, 20, 2.0, 550, 1, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(202, 300, 20, 2.0, 550, 1, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(203, 301, 9, 2.0, 550, 1, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(204, 301, 18, 2.0, 550, 1, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(205, 301, 20, 2.0, 550, 1, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(206, 302, 9, 2.0, 550, 1, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(207, 302, 18, 2.0, 550, 1, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(208, 303, 9, 4.0, 550, 2, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(209, 304, 9, 4.0, 550, 2, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(210, 331, 20, 6.0, 550, 3, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(211, 332, 18, 2.0, 550, 1, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(212, 333, 20, 2.0, 550, 1, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(213, 334, 20, 4.0, 550, 2, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(214, 335, 18, 2.0, 550, 1, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(215, 335, 20, 4.0, 550, 2, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(216, 336, 9, 2.0, 550, 1, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(217, 337, 20, 2.0, 550, 1, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(218, 338, 9, 2.0, 550, 1, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(219, 339, 9, 2.0, 550, 1, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(220, 340, 18, 2.0, 550, 1, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(221, 340, 20, 2.0, 550, 1, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(222, 341, 9, 4.0, 650, 2, NULL, 'instant', 0.00, 0.00, 1300.00, 0.00),
(223, 342, 20, 2.0, 650, 1, NULL, 'instant', 0.00, 0.00, 1300.00, 0.00),
(224, 343, 9, 2.0, 650, 1, NULL, 'instant', 0.00, 0.00, 1300.00, 0.00),
(225, 344, 20, 2.0, 650, 1, NULL, 'instant', 0.00, 0.00, 1300.00, 0.00),
(226, 345, 9, 2.0, 650, 1, NULL, 'instant', 0.00, 0.00, 1300.00, 0.00),
(227, 345, 20, 2.0, 650, 1, NULL, 'instant', 0.00, 0.00, 1300.00, 0.00),
(228, 349, 20, 4.0, 650, 2, NULL, 'instant', 0.00, 0.00, 1300.00, 0.00),
(229, 351, 9, 2.0, 650, 1, NULL, 'instant', 0.00, 0.00, 1300.00, 0.00),
(230, 351, 18, 2.0, 650, 1, NULL, 'instant', 0.00, 0.00, 1300.00, 0.00),
(231, 352, 9, 2.0, 650, 1, NULL, 'instant', 0.00, 0.00, 1300.00, 0.00),
(232, 353, 20, 4.0, 650, 2, NULL, 'instant', 0.00, 0.00, 1300.00, 0.00),
(233, 355, 9, 2.0, 650, 1, NULL, 'instant', 0.00, 0.00, 1300.00, 0.00),
(234, 356, 9, 4.0, 650, 2, NULL, 'instant', 0.00, 0.00, 1300.00, 0.00),
(235, 356, 20, 8.0, 650, 4, NULL, 'instant', 0.00, 0.00, 1300.00, 0.00),
(236, 357, 9, 4.0, 650, 2, NULL, 'instant', 0.00, 0.00, 1300.00, 0.00),
(237, 358, 18, 6.0, 700, 3, NULL, 'instant', 0.00, 0.00, 1400.00, 0.00),
(238, 358, 20, 2.0, 700, 1, NULL, 'instant', 0.00, 0.00, 1400.00, 0.00),
(239, 359, 9, 2.0, 700, 1, NULL, 'instant', 0.00, 0.00, 1400.00, 0.00),
(240, 360, 9, 4.0, 700, 2, NULL, 'instant', 0.00, 0.00, 1400.00, 0.00),
(241, 361, 9, 2.0, 700, 1, NULL, 'instant', 0.00, 0.00, 1400.00, 0.00),
(242, 362, 20, 2.0, 700, 1, NULL, 'instant', 0.00, 0.00, 1400.00, 0.00),
(243, 363, 18, 2.0, 700, 1, NULL, 'instant', 0.00, 0.00, 1400.00, 0.00),
(244, 364, 9, 2.0, 700, 1, NULL, 'instant', 0.00, 0.00, 1400.00, 0.00),
(245, 365, 9, 2.0, 700, 1, NULL, 'instant', 0.00, 0.00, 1400.00, 0.00),
(246, 366, 20, 2.0, 700, 1, NULL, 'instant', 0.00, 0.00, 1400.00, 0.00),
(247, 367, 20, 2.0, 700, 1, NULL, 'instant', 0.00, 0.00, 1400.00, 0.00),
(248, 368, 9, 2.0, 700, 1, NULL, 'instant', 0.00, 0.00, 1400.00, 0.00),
(249, 369, 20, 4.0, 700, 2, NULL, 'instant', 0.00, 0.00, 1400.00, 0.00),
(250, 371, 9, 4.0, 750, 2, NULL, 'instant', 0.00, 0.00, 1500.00, 0.00),
(251, 372, 9, 4.0, 750, 2, NULL, 'instant', 0.00, 0.00, 1500.00, 0.00),
(252, 373, 18, 2.0, 750, 1, NULL, 'instant', 0.00, 0.00, 1500.00, 0.00),
(253, 374, 9, 2.0, 750, 1, NULL, 'instant', 0.00, 0.00, 1500.00, 0.00),
(254, 375, 20, 2.0, 750, 1, NULL, 'instant', 0.00, 0.00, 1500.00, 0.00),
(255, 376, 9, 2.0, 750, 1, NULL, 'instant', 0.00, 0.00, 1500.00, 0.00),
(256, 377, 20, 2.0, 750, 1, NULL, 'instant', 0.00, 0.00, 1500.00, 0.00),
(257, 378, 9, 4.0, 650, 2, NULL, 'instant', 0.00, 0.00, 1300.00, 0.00),
(258, 378, 18, 2.0, 750, 1, NULL, 'instant', 0.00, 0.00, 1500.00, 0.00),
(259, 379, 9, 2.0, 750, 1, NULL, 'instant', 0.00, 0.00, 1500.00, 0.00),
(260, 383, 9, 2.0, 750, 1, NULL, 'instant', 0.00, 0.00, 1500.00, 0.00),
(261, 383, 18, 2.0, 750, 1, NULL, 'instant', 0.00, 0.00, 1500.00, 0.00),
(262, 397, 7, 2.0, 550, 1, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(263, 398, 7, 2.0, 550, 1, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(264, 399, 7, 2.0, 550, 1, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(265, 400, 7, 2.0, 550, 1, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(266, 401, 7, 2.0, 550, 1, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(267, 402, 7, 2.0, 550, 1, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(268, 404, 7, 2.0, 600, 1, NULL, 'instant', 0.00, 0.00, 1200.00, 0.00),
(269, 405, 20, 2.0, 650, 1, NULL, 'instant', 0.00, 0.00, 1300.00, 0.00),
(270, 406, 7, 2.0, 600, 1, NULL, 'instant', 0.00, 0.00, 1200.00, 0.00),
(271, 407, 7, 4.0, 600, 2, NULL, 'instant', 0.00, 0.00, 1200.00, 0.00),
(272, 408, 7, 18.0, 600, 9, NULL, 'instant', 0.00, 0.00, 1200.00, 0.00),
(273, 410, 7, 2.0, 550, 1, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(274, 410, 20, 2.0, 600, 1, NULL, 'instant', 0.00, 0.00, 1200.00, 0.00),
(275, 412, 20, 2.0, 600, 1, NULL, 'instant', 0.00, 0.00, 1200.00, 0.00),
(276, 413, 20, 2.0, 550, 1, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(277, 415, 7, 2.0, 550, 1, NULL, 'instant', 0.00, 0.00, 1100.00, 0.00),
(278, 427, 20, 2.0, 560, 1, NULL, 'instant', 0.00, 0.00, 0.00, 0.00),
(279, 429, 20, 2.0, 560, 1, NULL, 'instant', 0.00, 0.00, 0.00, 0.00),
(280, 430, 20, 2.0, 560, 1, NULL, 'instant', 0.00, 0.00, 0.00, 0.00),
(281, 432, 20, 2.0, 560, 1, NULL, 'instant', 0.00, 0.00, 0.00, 0.00),
(282, 433, 20, 2.0, 560, 1, NULL, 'instant', 0.00, 0.00, 0.00, 0.00),
(283, 434, 20, 2.0, 560, 1, NULL, 'instant', 0.00, 0.00, 0.00, 0.00),
(284, 435, 20, 2.0, 560, 1, NULL, 'instant', 0.00, 0.00, 0.00, 0.00),
(285, 436, 20, 2.0, 560, 1, NULL, 'instant', 0.00, 0.00, 0.00, 0.00),
(286, 437, 20, 2.0, 560, 1, NULL, 'instant', 0.00, 0.00, 0.00, 0.00),
(287, 438, 7, 2.0, 750, 1, NULL, 'instant', 0.00, 0.00, 0.00, 0.00),
(288, 439, 7, 2.0, 750, 1, NULL, 'instant', 0.00, 0.00, 0.00, 0.00),
(289, 441, 7, 2.0, 800, 1, NULL, 'instant', 0.00, 0.00, 0.00, 0.00),
(290, 442, 20, 2.0, 560, 1, NULL, 'instant', 0.00, 0.00, 0.00, 0.00),
(291, 443, 7, 2.0, 800, 1, NULL, 'instant', 0.00, 0.00, 0.00, 0.00),
(292, 446, 20, 6.0, 750, 3, NULL, 'instant', 0.00, 0.00, 0.00, 0.00),
(293, 447, 7, 2.0, 750, 1, NULL, 'instant', 0.00, 0.00, 0.00, 0.00),
(294, 454, 7, 34.0, 675, 17, NULL, 'instant', 0.00, 0.00, 0.00, 0.00),
(295, 455, 7, 2.0, 750, 1, NULL, 'instant', 0.00, 0.00, 0.00, 0.00),
(296, 457, 7, 2.0, 600, 1, NULL, 'instant', 0.00, 0.00, 0.00, 0.00),
(297, 458, 7, 2.0, 600, 1, NULL, 'instant', 0.00, 0.00, 0.00, 0.00),
(298, 459, 7, 2.0, 600, 1, NULL, 'instant', 0.00, 0.00, 0.00, 0.00),
(299, 461, 20, 2.0, 600, 1, NULL, 'instant', 0.00, 0.00, 0.00, 0.00),
(300, 462, 6, 2.0, 500, 1, NULL, 'instant', 0.00, 0.00, 0.00, 0.00),
(301, 462, 7, 2.0, 600, 1, NULL, 'instant', 0.00, 0.00, 0.00, 0.00),
(302, 462, 20, 2.0, 600, 1, NULL, 'instant', 0.00, 0.00, 0.00, 0.00),
(303, 463, 7, 2.0, 600, 1, NULL, 'instant', 0.00, 0.00, 0.00, 0.00),
(304, 466, 7, 2.0, 750, 1, 24, 'instant', 0.00, 0.00, 0.00, 0.00),
(305, 467, 7, 2.0, 750, 1, 24, 'instant', 0.00, 0.00, 0.00, 0.00),
(306, 468, 7, 2.0, 750, 1, 24, 'instant', 0.00, 0.00, 0.00, 0.00),
(307, 469, 7, 2.0, 750, 1, 24, 'instant', 0.00, 0.00, 0.00, 0.00),
(308, 472, 7, 2.0, 750, 1, 24, 'instant', 0.00, 0.00, 0.00, 0.00),
(309, 473, 7, 2.0, 750, 1, 24, 'instant', 0.00, 0.00, 0.00, 0.00),
(310, 474, 7, 2.0, 500, 1, 24, 'instant', 0.00, 0.00, 0.00, 0.00),
(311, 477, 7, 2.0, 750, 1, 25, 'instant', 0.00, 0.00, 0.00, 0.00),
(312, 478, 7, 2.0, 750, 1, 25, 'instant', 0.00, 0.00, 0.00, 0.00),
(313, 481, 7, 2.0, 675, 1, 25, 'instant', 0.00, 0.00, 0.00, 0.00),
(314, 482, 7, 2.0, 600, 1, 30, 'instant', 0.00, 0.00, 0.00, 0.00),
(315, 483, 6, 2.0, 900, 1, 28, 'instant', 0.00, 0.00, 0.00, 0.00),
(316, 484, 7, 4.0, 675, 2, 30, 'instant', 0.00, 0.00, 0.00, 0.00),
(317, 485, 7, 14.0, 475, 7, 30, 'instant', 0.00, 0.00, 0.00, 0.00),
(318, 486, 31, 2.0, 900, 1, 29, 'instant', 0.00, 0.00, 0.00, 0.00),
(319, 487, 7, 2.0, 750, 1, 34, 'instant', 0.00, 0.00, 0.00, 0.00),
(320, 488, 7, 2.0, 750, 1, 34, 'instant', 0.00, 0.00, 0.00, 0.00),
(321, 489, 7, 2.0, 750, 1, 40, 'instant', 0.00, 0.00, 0.00, 0.00),
(322, 490, 7, 2.0, 750, 1, 40, 'instant', 0.00, 0.00, 0.00, 0.00),
(323, 491, 7, 2.0, 750, 1, 40, 'instant', 0.00, 0.00, 0.00, 0.00),
(324, 492, 7, 2.0, 750, 1, 40, 'instant', 0.00, 0.00, 0.00, 0.00),
(325, 492, 31, 2.0, 900, 1, 35, 'instant', 0.00, 0.00, 0.00, 0.00),
(326, 493, 7, 2.0, 750, 1, 40, 'instant', 0.00, 0.00, 0.00, 0.00);

-- --------------------------------------------------------

--
-- Структура таблицы `order_status_history`
--

CREATE TABLE `order_status_history` (
  `id` int UNSIGNED NOT NULL,
  `order_id` int UNSIGNED NOT NULL,
  `from_status` varchar(32) DEFAULT NULL,
  `to_status` varchar(32) NOT NULL,
  `changed_by_user_id` int UNSIGNED DEFAULT NULL,
  `changed_by_role` varchar(32) DEFAULT NULL,
  `comment` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Дамп данных таблицы `order_status_history`
--

INSERT INTO `order_status_history` (`id`, `order_id`, `from_status`, `to_status`, `changed_by_user_id`, `changed_by_role`, `comment`, `created_at`) VALUES
(1, 481, 'new', 'confirmed', 17, 'manager', NULL, '2026-06-10 07:00:29'),
(2, 481, 'confirmed', 'completed', 1, 'admin', NULL, '2026-06-11 19:59:33'),
(3, 456, 'new', 'cancelled', 1, 'admin', NULL, '2026-06-11 20:01:59'),
(4, 482, 'new', 'completed', 17, 'manager', NULL, '2026-06-12 20:24:24'),
(5, 484, 'new', 'completed', 17, 'manager', NULL, '2026-06-12 20:27:54'),
(6, 483, 'new', 'completed', 17, 'manager', NULL, '2026-06-12 20:28:09'),
(7, 485, 'new', 'completed', 17, 'manager', NULL, '2026-06-12 20:34:14'),
(8, 487, 'new', 'completed', 17, 'manager', NULL, '2026-06-16 15:50:59'),
(9, 488, 'new', 'completed', 17, 'manager', NULL, '2026-06-17 15:37:19'),
(10, 489, 'new', 'completed', 17, 'manager', NULL, '2026-06-17 17:09:53'),
(11, 490, 'new', 'completed', 17, 'manager', NULL, '2026-06-18 17:01:27'),
(12, 492, 'new', 'completed', 17, 'manager', NULL, '2026-06-18 17:06:12'),
(13, 491, 'new', 'completed', 17, 'manager', NULL, '2026-06-18 17:06:21'),
(14, 493, 'new', 'completed', 17, 'manager', NULL, '2026-06-18 17:06:30'),
(15, 494, 'new', 'confirmed', 1, 'admin', NULL, '2026-07-14 05:25:31'),
(16, 486, 'new', 'cancelled', 1, 'admin', NULL, '2026-07-14 06:19:03');

-- --------------------------------------------------------

--
-- Структура таблицы `partner_profiles`
--

CREATE TABLE `partner_profiles` (
  `user_id` int UNSIGNED NOT NULL,
  `partner_type` enum('internal_staff','production_partner','marketplace_seller','brand_partner') NOT NULL DEFAULT 'production_partner',
  `status` enum('draft','active','paused','blocked') NOT NULL DEFAULT 'draft',
  `default_fulfillment_model` enum('by_berrygo_on_site','by_berrygo_remote','by_partner_under_berrygo_brand','by_seller','by_berrygo_from_seller_stock') NOT NULL DEFAULT 'by_partner_under_berrygo_brand',
  `monetization_model` enum('salary','internal_bonus','fixed_payout','commission','subscription','commission_plus_subscription','fixed_fee_per_order') NOT NULL DEFAULT 'commission',
  `client_visibility` enum('berrygo_only','partner_visible','seller_visible') NOT NULL DEFAULT 'berrygo_only',
  `commission_rate` decimal(5,2) NOT NULL DEFAULT '30.00',
  `subscription_fee` decimal(10,2) NOT NULL DEFAULT '0.00',
  `fixed_fee_per_order` decimal(10,2) NOT NULL DEFAULT '0.00',
  `default_bonus_percent` decimal(5,2) NOT NULL DEFAULT '10.00',
  `max_active_jobs` int UNSIGNED NOT NULL DEFAULT '1',
  `notes` text,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Дамп данных таблицы `partner_profiles`
--

INSERT INTO `partner_profiles` (`user_id`, `partner_type`, `status`, `default_fulfillment_model`, `monetization_model`, `client_visibility`, `commission_rate`, `subscription_fee`, `fixed_fee_per_order`, `default_bonus_percent`, `max_active_jobs`, `notes`, `created_at`, `updated_at`) VALUES
(296, 'brand_partner', 'active', 'by_partner_under_berrygo_brand', 'commission', 'partner_visible', 30.00, 0.00, 0.00, 10.00, 10, 'Berry Me Please — мастерская клубники в шоколаде и подарочных berry-наборов.', '2026-07-06 13:47:46', '2026-07-07 05:35:10');

-- --------------------------------------------------------

--
-- Структура таблицы `points_transactions`
--

CREATE TABLE `points_transactions` (
  `id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `order_id` int UNSIGNED DEFAULT NULL,
  `amount` int NOT NULL COMMENT 'положительное = начисление, отрицательное = списание',
  `transaction_type` enum('accrual','usage','payout') NOT NULL,
  `description` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Дамп данных таблицы `points_transactions`
--

INSERT INTO `points_transactions` (`id`, `user_id`, `order_id`, `amount`, `transaction_type`, `description`, `created_at`) VALUES
(51, 6, NULL, -283, 'usage', 'Скидка за заказ', '2025-06-16 17:23:45'),
(57, 16, NULL, -112, 'usage', 'Скидка за заказ', '2025-06-17 03:47:05'),
(112, 16, NULL, 112, 'accrual', 'Возврат 112 за удаление заказа №82', '2025-07-20 16:09:27'),
(124, 1, NULL, -118, 'usage', 'Скидка за заказ', '2025-07-25 07:04:09'),
(125, 1, NULL, 118, 'accrual', 'Возврат 118 за удаление заказа №123', '2025-07-25 16:35:12'),
(127, 1, NULL, -118, 'usage', 'Скидка за заказ', '2025-07-25 16:50:12'),
(128, 1, NULL, 118, 'accrual', 'Возврат 118 за удаление заказа №126', '2025-07-25 16:52:42'),
(131, 37, 132, 213, 'accrual', 'Начисление 213 за заказ №132', '2025-07-26 00:37:58'),
(132, 38, 133, 123, 'accrual', 'Начисление 123 за заказ №133', '2025-07-26 05:38:43'),
(133, 36, 131, 196, 'accrual', 'Начисление 196 за заказ №131', '2025-07-26 05:38:54'),
(134, 43, 135, 54, 'accrual', 'Начисление 54 за заказ №135', '2025-07-26 05:39:40'),
(135, 41, 135, 32, 'accrual', 'Бонус за заказ №135', '2025-07-26 05:39:40'),
(136, 44, 136, 54, 'accrual', 'Начисление 54 за заказ №136', '2025-07-26 05:41:42'),
(137, 41, 136, 32, 'accrual', 'Бонус за заказ №136', '2025-07-26 05:41:42'),
(138, 45, 137, 135, 'accrual', 'Начисление 135 за заказ №137', '2025-07-26 06:02:05'),
(139, 41, 137, 81, 'accrual', 'Бонус за заказ №137', '2025-07-26 06:02:05'),
(140, 42, 134, 123, 'accrual', 'Начисление 123 за заказ №134', '2025-07-26 08:28:55'),
(141, 41, 134, 74, 'accrual', 'Бонус за заказ №134', '2025-07-26 08:28:55'),
(142, 46, 138, 49, 'accrual', 'Начисление 49 за заказ №138', '2025-07-26 09:05:12'),
(143, 41, 138, 29, 'accrual', 'Бонус за заказ №138', '2025-07-26 09:05:12'),
(144, 25, 139, 220, 'accrual', 'Начисление 220 за заказ №139', '2025-07-26 09:06:06'),
(145, 17, 139, 132, 'accrual', 'Бонус за заказ №139', '2025-07-26 09:06:06'),
(146, 46, 138, 49, 'accrual', 'Начисление 49 за заказ №138', '2025-07-27 06:22:12'),
(147, 49, 142, 98, 'accrual', 'Начисление 98 за заказ №142', '2025-07-27 06:31:28'),
(148, 6, 142, 59, 'accrual', 'Бонус за заказ №142', '2025-07-27 06:31:28'),
(149, 47, 140, 61, 'accrual', 'Начисление 61 за заказ №140', '2025-07-29 05:38:50'),
(150, 6, 140, 37, 'accrual', 'Бонус за заказ №140', '2025-07-29 05:38:50'),
(151, 48, 141, 197, 'accrual', 'Начисление 197 за заказ №141', '2025-07-29 05:38:59'),
(152, 6, 141, 118, 'accrual', 'Бонус за заказ №141', '2025-07-29 05:38:59'),
(155, 52, 147, 114, 'accrual', 'Начисление 114 за заказ №147', '2025-07-29 15:24:54'),
(156, 1, 147, 68, 'accrual', 'Бонус за заказ №147', '2025-07-29 15:24:54'),
(157, 51, 146, 114, 'accrual', 'Начисление 114 за заказ №146', '2025-07-29 15:24:58'),
(158, 1, 146, 68, 'accrual', 'Бонус за заказ №146', '2025-07-29 15:24:58'),
(159, 53, 148, 64, 'accrual', 'Начисление 64 за заказ №148', '2025-08-02 07:07:26'),
(160, 17, 148, 38, 'accrual', 'Бонус за заказ №148', '2025-08-02 07:07:26'),
(161, 59, 150, 64, 'accrual', 'Начисление 64 за заказ №150', '2025-08-03 06:58:04'),
(162, 41, 150, 38, 'accrual', 'Бонус за заказ №150', '2025-08-03 06:58:04'),
(163, 17, 150, 38, 'accrual', 'Менеджерский бонус за заказ №150', '2025-08-03 06:58:04'),
(164, 61, 152, 198, 'accrual', 'Начисление 198 за заказ №152', '2025-08-03 07:06:13'),
(165, 41, 152, 118, 'accrual', 'Бонус за заказ №152', '2025-08-03 07:06:13'),
(166, 17, 152, 118, 'accrual', 'Менеджерский бонус за заказ №152', '2025-08-03 07:06:13'),
(167, 58, 149, 330, 'accrual', 'Начисление 330 за заказ №149', '2025-08-03 10:27:59'),
(168, 41, 149, 198, 'accrual', 'Бонус за заказ №149', '2025-08-03 10:27:59'),
(169, 17, 149, 198, 'accrual', 'Менеджерский бонус за заказ №149', '2025-08-03 10:27:59'),
(170, 60, 151, 49, 'accrual', 'Начисление 49 за заказ №151', '2025-08-03 10:28:19'),
(171, 41, 151, 29, 'accrual', 'Бонус за заказ №151', '2025-08-03 10:28:19'),
(172, 17, 151, 29, 'accrual', 'Менеджерский бонус за заказ №151', '2025-08-03 10:28:19'),
(173, 63, 154, 64, 'accrual', 'Начисление 64 за заказ №154', '2025-08-03 12:23:44'),
(174, 41, 154, 38, 'accrual', 'Бонус за заказ №154', '2025-08-03 12:23:44'),
(175, 17, 154, 38, 'accrual', 'Менеджерский бонус за заказ №154', '2025-08-03 12:23:44'),
(176, 62, 153, 559, 'accrual', 'Начисление 559 за заказ №153', '2025-08-03 12:58:22'),
(177, 41, 153, 335, 'accrual', 'Бонус за заказ №153', '2025-08-03 12:58:22'),
(178, 17, 153, 335, 'accrual', 'Менеджерский бонус за заказ №153', '2025-08-03 12:58:22'),
(179, 65, 156, 55, 'accrual', 'Начисление 55 за заказ №156', '2025-08-03 14:46:38'),
(180, 41, 156, 33, 'accrual', 'Бонус за заказ №156', '2025-08-03 14:46:38'),
(181, 17, 156, 33, 'accrual', 'Менеджерский бонус за заказ №156', '2025-08-03 14:46:38'),
(182, 68, 158, 99, 'accrual', 'Начисление 99 за заказ №158', '2025-08-04 05:56:37'),
(183, 41, 158, 198, 'accrual', 'Бонус за заказ №158', '2025-08-04 05:56:37'),
(184, 17, 158, 59, 'accrual', 'Менеджерский бонус за заказ №158', '2025-08-04 05:56:37'),
(185, 41, NULL, -198, 'payout', 'Запрос выплаты на 198 ₽', '2025-08-04 06:38:00'),
(186, 71, 161, 163, 'accrual', 'Начисление 163 за заказ №161', '2025-08-04 08:45:04'),
(187, 41, 161, 327, 'accrual', 'Бонус за заказ №161', '2025-08-04 08:45:04'),
(188, 17, 161, 98, 'accrual', 'Менеджерский бонус за заказ №161', '2025-08-04 08:45:05'),
(189, 70, 160, 385, 'accrual', 'Начисление 385 за заказ №160', '2025-08-04 09:42:43'),
(190, 41, 160, 770, 'accrual', 'Бонус за заказ №160', '2025-08-04 09:42:43'),
(191, 17, 160, 231, 'accrual', 'Менеджерский бонус за заказ №160', '2025-08-04 09:42:43'),
(192, 69, 159, 165, 'accrual', 'Начисление 165 за заказ №159', '2025-08-04 09:58:04'),
(193, 41, 159, 330, 'accrual', 'Бонус за заказ №159', '2025-08-04 09:58:04'),
(194, 17, 159, 99, 'accrual', 'Менеджерский бонус за заказ №159', '2025-08-04 09:58:04'),
(195, 64, 155, 148, 'accrual', 'Начисление 148 за заказ №155', '2025-08-04 12:52:59'),
(196, 1, 155, 89, 'accrual', 'Бонус за заказ №155', '2025-08-04 12:52:59'),
(197, 67, 157, 148, 'accrual', 'Начисление 148 за заказ №157', '2025-08-04 12:53:04'),
(198, 1, 157, 89, 'accrual', 'Бонус за заказ №157', '2025-08-04 12:53:04'),
(199, 73, 163, 64, 'accrual', 'Начисление 64 за заказ №163', '2025-08-04 14:40:17'),
(200, 41, 163, 129, 'accrual', 'Бонус за заказ №163', '2025-08-04 14:40:17'),
(201, 17, 163, 38, 'accrual', 'Менеджерский бонус за заказ №163', '2025-08-04 14:40:17'),
(202, 41, NULL, -1556, 'payout', 'Запрос выплаты на 1556 ₽', '2025-08-04 16:12:29'),
(203, 72, 162, 275, 'accrual', 'Начисление 275 за заказ №162', '2025-08-05 04:01:45'),
(204, 41, 162, 550, 'accrual', 'Бонус за заказ №162', '2025-08-05 04:01:45'),
(205, 17, 162, 165, 'accrual', 'Менеджерский бонус за заказ №162', '2025-08-05 04:01:45'),
(206, 74, 164, 110, 'accrual', 'Начисление 110 за заказ №164', '2025-08-05 07:40:42'),
(207, 41, 164, 220, 'accrual', 'Бонус за заказ №164', '2025-08-05 07:40:42'),
(208, 17, 164, 66, 'accrual', 'Менеджерский бонус за заказ №164', '2025-08-05 07:40:42'),
(209, 77, 167, 198, 'accrual', 'Начисление 198 за заказ №167', '2025-08-05 09:25:41'),
(210, 41, 167, 396, 'accrual', 'Бонус за заказ №167', '2025-08-05 09:25:41'),
(211, 17, 167, 118, 'accrual', 'Менеджерский бонус за заказ №167', '2025-08-05 09:25:41'),
(212, 75, 165, 114, 'accrual', 'Начисление 114 за заказ №165', '2025-08-05 09:58:25'),
(213, 41, 165, 228, 'accrual', 'Бонус за заказ №165', '2025-08-05 09:58:25'),
(214, 17, 165, 68, 'accrual', 'Менеджерский бонус за заказ №165', '2025-08-05 09:58:25'),
(215, 76, 166, 49, 'accrual', 'Начисление 49 за заказ №166', '2025-08-05 14:33:57'),
(216, 41, 166, 99, 'accrual', 'Бонус за заказ №166', '2025-08-05 14:33:57'),
(217, 17, 166, 29, 'accrual', 'Менеджерский бонус за заказ №166', '2025-08-05 14:33:57'),
(218, 79, 168, 114, 'accrual', 'Начисление 114 за заказ №168', '2025-08-06 06:55:14'),
(219, 41, 168, 228, 'accrual', 'Бонус за заказ №168', '2025-08-06 06:55:14'),
(220, 17, 168, 68, 'accrual', 'Менеджерский бонус за заказ №168', '2025-08-06 06:55:14'),
(221, 81, 170, 125, 'accrual', 'Начисление 125 за заказ №170', '2025-08-06 09:15:25'),
(222, 41, 170, 250, 'accrual', 'Бонус за заказ №170', '2025-08-06 09:15:25'),
(223, 17, 170, 75, 'accrual', 'Менеджерский бонус за заказ №170', '2025-08-06 09:15:25'),
(224, 80, 169, 361, 'accrual', 'Начисление 361 за заказ №169', '2025-08-06 11:41:00'),
(225, 41, 169, 723, 'accrual', 'Бонус за заказ №169', '2025-08-06 11:41:00'),
(226, 17, 169, 216, 'accrual', 'Менеджерский бонус за заказ №169', '2025-08-06 11:41:00'),
(227, 85, 174, 70, 'accrual', 'Начисление 70 за заказ №174', '2025-08-06 15:27:41'),
(228, 41, 174, 140, 'accrual', 'Бонус за заказ №174', '2025-08-06 15:27:41'),
(229, 17, 174, 42, 'accrual', 'Менеджерский бонус за заказ №174', '2025-08-06 15:27:41'),
(230, 83, 172, 99, 'accrual', 'Начисление 99 за заказ №172', '2025-08-06 15:27:51'),
(231, 41, 172, 198, 'accrual', 'Бонус за заказ №172', '2025-08-06 15:27:51'),
(232, 17, 172, 59, 'accrual', 'Менеджерский бонус за заказ №172', '2025-08-06 15:27:51'),
(233, 82, 171, 99, 'accrual', 'Начисление 99 за заказ №171', '2025-08-06 15:27:58'),
(234, 41, 171, 198, 'accrual', 'Бонус за заказ №171', '2025-08-06 15:27:58'),
(235, 17, 171, 59, 'accrual', 'Менеджерский бонус за заказ №171', '2025-08-06 15:27:58'),
(236, 41, NULL, -3230, 'payout', 'Запрос выплаты на 3230 ₽', '2025-08-06 15:48:17'),
(237, 86, 175, 125, 'accrual', 'Начисление 125 за заказ №175', '2025-08-07 04:05:00'),
(238, 1, 175, 75, 'accrual', 'Бонус за заказ №175', '2025-08-07 04:05:00'),
(239, 88, 177, 110, 'accrual', 'Начисление 110 за заказ №177', '2025-08-07 05:34:53'),
(240, 41, 177, 220, 'accrual', 'Бонус за заказ №177', '2025-08-07 05:34:53'),
(241, 17, 177, 66, 'accrual', 'Менеджерский бонус за заказ №177', '2025-08-07 05:34:53'),
(242, 89, 178, 70, 'accrual', 'Начисление 70 за заказ №178', '2025-08-07 07:08:41'),
(243, 41, 178, 140, 'accrual', 'Бонус за заказ №178', '2025-08-07 07:08:41'),
(244, 17, 178, 42, 'accrual', 'Менеджерский бонус за заказ №178', '2025-08-07 07:08:41'),
(245, 84, 173, 262, 'accrual', 'Начисление 262 за заказ №173', '2025-08-07 07:25:26'),
(246, 41, 173, 525, 'accrual', 'Бонус за заказ №173', '2025-08-07 07:25:26'),
(247, 17, 173, 157, 'accrual', 'Менеджерский бонус за заказ №173', '2025-08-07 07:25:26'),
(248, 91, 180, 130, 'accrual', 'Начисление 130 за заказ №180', '2025-08-07 10:33:11'),
(249, 41, 180, 260, 'accrual', 'Бонус за заказ №180', '2025-08-07 10:33:11'),
(250, 17, 180, 78, 'accrual', 'Менеджерский бонус за заказ №180', '2025-08-07 10:33:11'),
(251, 41, NULL, -1145, 'payout', 'Запрос выплаты на 1145 ₽', '2025-08-08 03:38:40'),
(252, 90, 179, 105, 'accrual', 'Начисление 105 за заказ №179', '2025-08-08 05:00:35'),
(253, 41, 179, 211, 'accrual', 'Бонус за заказ №179', '2025-08-08 05:00:35'),
(254, 17, 179, 63, 'accrual', 'Менеджерский бонус за заказ №179', '2025-08-08 05:00:35'),
(255, 95, 186, 162, 'accrual', 'Начисление 162 за заказ №186', '2025-08-08 07:18:11'),
(256, 41, 186, 324, 'accrual', 'Бонус за заказ №186', '2025-08-08 07:18:11'),
(257, 17, 186, 97, 'accrual', 'Менеджерский бонус за заказ №186', '2025-08-08 07:18:11'),
(258, 96, 187, 54, 'accrual', 'Начисление 54 за заказ №187', '2025-08-08 07:52:38'),
(259, 41, 187, 108, 'accrual', 'Бонус за заказ №187', '2025-08-08 07:52:38'),
(260, 17, 187, 32, 'accrual', 'Менеджерский бонус за заказ №187', '2025-08-08 07:52:38'),
(261, 94, 185, 245, 'accrual', 'Начисление 245 за заказ №185', '2025-08-08 07:54:23'),
(262, 41, 185, 490, 'accrual', 'Бонус за заказ №185', '2025-08-08 07:54:23'),
(263, 17, 185, 147, 'accrual', 'Менеджерский бонус за заказ №185', '2025-08-08 07:54:23'),
(264, 98, 189, 345, 'accrual', 'Начисление 345 за заказ №189', '2025-08-08 08:32:22'),
(265, 41, 189, 690, 'accrual', 'Бонус за заказ №189', '2025-08-08 08:32:22'),
(266, 17, 189, 207, 'accrual', 'Менеджерский бонус за заказ №189', '2025-08-08 08:32:22'),
(267, 93, 184, 103, 'accrual', 'Начисление 103 за заказ №184', '2025-08-08 10:07:23'),
(268, 41, 184, 207, 'accrual', 'Бонус за заказ №184', '2025-08-08 10:07:23'),
(269, 17, 184, 62, 'accrual', 'Менеджерский бонус за заказ №184', '2025-08-08 10:07:23'),
(270, 100, 191, 103, 'accrual', 'Начисление 103 за заказ №191', '2025-08-08 10:27:33'),
(271, 41, 191, 207, 'accrual', 'Бонус за заказ №191', '2025-08-08 10:27:33'),
(272, 17, 191, 62, 'accrual', 'Менеджерский бонус за заказ №191', '2025-08-08 10:27:33'),
(273, 101, 193, 130, 'accrual', 'Начисление 130 за заказ №193', '2025-08-08 10:53:55'),
(274, 41, 193, 260, 'accrual', 'Бонус за заказ №193', '2025-08-08 10:53:55'),
(275, 17, 193, 78, 'accrual', 'Менеджерский бонус за заказ №193', '2025-08-08 10:53:55'),
(276, 102, 194, 135, 'accrual', 'Начисление 135 за заказ №194', '2025-08-08 12:54:06'),
(277, 41, 194, 270, 'accrual', 'Бонус за заказ №194', '2025-08-08 12:54:06'),
(278, 17, 194, 81, 'accrual', 'Менеджерский бонус за заказ №194', '2025-08-08 12:54:06'),
(279, 97, 192, 195, 'accrual', 'Начисление 195 за заказ №192', '2025-08-08 15:10:10'),
(280, 41, 192, 390, 'accrual', 'Бонус за заказ №192', '2025-08-08 15:10:10'),
(281, 17, 192, 117, 'accrual', 'Менеджерский бонус за заказ №192', '2025-08-08 15:10:10'),
(282, 41, NULL, -3157, 'payout', 'Запрос выплаты на 3157 ₽', '2025-08-09 07:15:21'),
(283, 104, 196, 135, 'accrual', 'Начисление 135 за заказ №196', '2025-08-09 23:56:44'),
(284, 41, 196, 270, 'accrual', 'Бонус за заказ №196', '2025-08-09 23:56:44'),
(285, 17, 196, 81, 'accrual', 'Менеджерский бонус за заказ №196', '2025-08-09 23:56:44'),
(286, 106, 198, 55, 'accrual', 'Начисление 55 за заказ №198', '2025-08-10 16:23:45'),
(287, 41, 198, 110, 'accrual', 'Бонус за заказ №198', '2025-08-10 16:23:45'),
(288, 17, 198, 33, 'accrual', 'Менеджерский бонус за заказ №198', '2025-08-10 16:23:45'),
(289, 103, 195, 165, 'accrual', 'Начисление 165 за заказ №195', '2025-08-10 16:24:01'),
(290, 41, 195, 330, 'accrual', 'Бонус за заказ №195', '2025-08-10 16:24:01'),
(291, 17, 195, 99, 'accrual', 'Менеджерский бонус за заказ №195', '2025-08-10 16:24:01'),
(292, 108, 202, 69, 'accrual', 'Начисление 69 за заказ №202', '2025-08-20 15:46:04'),
(293, 1, 202, 41, 'accrual', 'Бонус за заказ №202', '2025-08-20 15:46:04'),
(298, 17, NULL, -2933, 'payout', 'Запрос выплаты на 2933 ₽', '2025-08-20 16:22:20'),
(300, 41, NULL, -710, 'payout', 'Запрос выплаты на 710 ₽', '2025-08-23 12:46:38'),
(301, 113, 207, 55, 'accrual', 'Начисление 55 за заказ №207', '2025-08-23 12:49:13'),
(302, 41, 207, 110, 'accrual', 'Бонус за заказ №207', '2025-08-23 12:49:13'),
(303, 17, 207, 33, 'accrual', 'Менеджерский бонус за заказ №207', '2025-08-23 12:49:13'),
(304, 112, 206, 135, 'accrual', 'Начисление 135 за заказ №206', '2025-08-23 12:49:31'),
(305, 41, 206, 270, 'accrual', 'Бонус за заказ №206', '2025-08-23 12:49:31'),
(306, 17, 206, 81, 'accrual', 'Менеджерский бонус за заказ №206', '2025-08-23 12:49:31'),
(307, 115, 210, 70, 'accrual', 'Начисление 70 за заказ №210', '2025-08-24 14:03:09'),
(308, 41, 210, 140, 'accrual', 'Бонус за заказ №210', '2025-08-24 14:03:09'),
(309, 17, 210, 42, 'accrual', 'Менеджерский бонус за заказ №210', '2025-08-24 14:03:09'),
(310, 116, 211, 135, 'accrual', 'Начисление 135 за заказ №211', '2025-08-25 16:40:01'),
(311, 41, 211, 270, 'accrual', 'Бонус за заказ №211', '2025-08-25 16:40:01'),
(312, 17, 211, 81, 'accrual', 'Менеджерский бонус за заказ №211', '2025-08-25 16:40:01'),
(316, 118, 214, 75, 'accrual', 'Начисление 75 за заказ №214', '2025-08-26 15:40:27'),
(317, 17, 214, 45, 'accrual', 'Бонус за заказ №214', '2025-08-26 15:40:27'),
(318, 107, 215, 240, 'accrual', 'Начисление 240 за заказ №215', '2025-08-26 15:45:34'),
(319, 17, 215, 144, 'accrual', 'Бонус за заказ №215', '2025-08-26 15:45:34'),
(320, 119, 216, 99, 'accrual', 'Начисление 99 за заказ №216', '2025-08-26 15:50:35'),
(321, 17, 216, 59, 'accrual', 'Бонус за заказ №216', '2025-08-26 15:50:35'),
(322, 109, 217, 108, 'accrual', 'Начисление 108 за заказ №217', '2025-08-26 15:55:39'),
(323, 17, 217, 64, 'accrual', 'Бонус за заказ №217', '2025-08-26 15:55:39'),
(324, 117, 212, 600, 'accrual', 'Начисление 600 за заказ №212', '2025-08-27 14:36:40'),
(325, 17, 212, 360, 'accrual', 'Бонус за заказ №212', '2025-08-27 14:36:40'),
(326, 120, 218, 135, 'accrual', 'Начисление 135 за заказ №218', '2025-08-27 14:38:13'),
(327, 41, 218, 270, 'accrual', 'Бонус за заказ №218', '2025-08-27 14:38:13'),
(328, 17, 218, 81, 'accrual', 'Менеджерский бонус за заказ №218', '2025-08-27 14:38:14'),
(329, 121, 220, 75, 'accrual', 'Начисление 75 за заказ №220', '2025-08-28 03:42:39'),
(330, 41, 220, 150, 'accrual', 'Бонус за заказ №220', '2025-08-28 03:42:40'),
(331, 17, 220, 45, 'accrual', 'Менеджерский бонус за заказ №220', '2025-08-28 03:42:40'),
(332, 122, 221, 120, 'accrual', 'Начисление 120 за заказ №221', '2025-08-28 15:52:19'),
(333, 41, 221, 240, 'accrual', 'Бонус за заказ №221', '2025-08-28 15:52:19'),
(334, 17, 221, 72, 'accrual', 'Менеджерский бонус за заказ №221', '2025-08-28 15:52:19'),
(335, 95, 219, 378, 'accrual', 'Начисление 378 за заказ №219', '2025-08-29 02:39:33'),
(336, 41, 219, 756, 'accrual', 'Бонус за заказ №219', '2025-08-29 02:39:33'),
(337, 17, 219, 226, 'accrual', 'Менеджерский бонус за заказ №219', '2025-08-29 02:39:33'),
(338, 109, 222, 115, 'accrual', 'Начисление 115 за заказ №222', '2025-08-29 04:11:02'),
(339, 17, 222, 69, 'accrual', 'Бонус за заказ №222', '2025-08-29 04:11:02'),
(340, 112, 224, 195, 'accrual', 'Начисление 195 за заказ №224', '2025-08-30 15:01:03'),
(341, 41, 224, 390, 'accrual', 'Бонус за заказ №224', '2025-08-30 15:01:03'),
(342, 17, 224, 117, 'accrual', 'Менеджерский бонус за заказ №224', '2025-08-30 15:01:03'),
(343, 118, 213, 75, 'accrual', 'Начисление 75 за заказ №213', '2025-09-01 09:46:15'),
(344, 17, 213, 45, 'accrual', 'Бонус за заказ №213', '2025-09-01 09:46:15'),
(345, 128, 229, 75, 'accrual', 'Начисление 75 за заказ №229', '2025-09-01 09:48:00'),
(346, 41, 229, 150, 'accrual', 'Бонус за заказ №229', '2025-09-01 09:48:00'),
(347, 17, 229, 45, 'accrual', 'Менеджерский бонус за заказ №229', '2025-09-01 09:48:00'),
(348, 126, 227, 120, 'accrual', 'Начисление 120 за заказ №227', '2025-09-01 09:51:58'),
(349, 41, 227, 240, 'accrual', 'Бонус за заказ №227', '2025-09-01 09:51:58'),
(350, 17, 227, 72, 'accrual', 'Менеджерский бонус за заказ №227', '2025-09-01 09:51:58'),
(351, 125, 226, 120, 'accrual', 'Начисление 120 за заказ №226', '2025-09-01 09:52:08'),
(352, 41, 226, 240, 'accrual', 'Бонус за заказ №226', '2025-09-01 09:52:08'),
(353, 17, 226, 72, 'accrual', 'Менеджерский бонус за заказ №226', '2025-09-01 09:52:08'),
(354, 124, 225, 180, 'accrual', 'Начисление 180 за заказ №225', '2025-09-01 09:52:15'),
(355, 41, 225, 360, 'accrual', 'Бонус за заказ №225', '2025-09-01 09:52:15'),
(356, 17, 225, 108, 'accrual', 'Менеджерский бонус за заказ №225', '2025-09-01 09:52:15'),
(357, 41, NULL, -3706, 'payout', 'Запрос выплаты на 3706 ₽', '2025-09-04 06:39:26'),
(358, 17, NULL, -1897, 'payout', 'Запрос выплаты на 1897 ₽', '2025-09-05 10:35:17'),
(359, 136, 237, 90, 'accrual', 'Начисление 90 за заказ №237', '2025-09-06 07:47:31'),
(360, 41, 237, 180, 'accrual', 'Бонус за заказ №237', '2025-09-06 07:47:31'),
(361, 17, 237, 54, 'accrual', 'Менеджерский бонус за заказ №237', '2025-09-06 07:47:31'),
(362, 137, 238, 235, 'accrual', 'Начисление 235 за заказ №238', '2025-09-06 07:47:42'),
(363, 41, 238, 470, 'accrual', 'Бонус за заказ №238', '2025-09-06 07:47:42'),
(364, 17, 238, 141, 'accrual', 'Менеджерский бонус за заказ №238', '2025-09-06 07:47:42'),
(365, 135, 236, 235, 'accrual', 'Начисление 235 за заказ №236', '2025-09-06 07:47:54'),
(366, 41, 236, 470, 'accrual', 'Бонус за заказ №236', '2025-09-06 07:47:54'),
(367, 17, 236, 141, 'accrual', 'Менеджерский бонус за заказ №236', '2025-09-06 07:47:54'),
(368, 133, 234, 165, 'accrual', 'Начисление 165 за заказ №234', '2025-09-06 07:48:02'),
(369, 41, 234, 330, 'accrual', 'Бонус за заказ №234', '2025-09-06 07:48:02'),
(370, 17, 234, 99, 'accrual', 'Менеджерский бонус за заказ №234', '2025-09-06 07:48:02'),
(371, 132, 233, 70, 'accrual', 'Начисление 70 за заказ №233', '2025-09-06 07:48:10'),
(372, 41, 233, 140, 'accrual', 'Бонус за заказ №233', '2025-09-06 07:48:10'),
(373, 17, 233, 42, 'accrual', 'Менеджерский бонус за заказ №233', '2025-09-06 07:48:10'),
(374, 131, 232, 125, 'accrual', 'Начисление 125 за заказ №232', '2025-09-06 07:48:18'),
(375, 41, 232, 250, 'accrual', 'Бонус за заказ №232', '2025-09-06 07:48:18'),
(376, 17, 232, 75, 'accrual', 'Менеджерский бонус за заказ №232', '2025-09-06 07:48:18'),
(377, 130, 231, 110, 'accrual', 'Начисление 110 за заказ №231', '2025-09-06 07:48:27'),
(378, 41, 231, 220, 'accrual', 'Бонус за заказ №231', '2025-09-06 07:48:27'),
(379, 17, 231, 66, 'accrual', 'Менеджерский бонус за заказ №231', '2025-09-06 07:48:27'),
(380, 129, 230, 105, 'accrual', 'Начисление 105 за заказ №230', '2025-09-06 07:48:34'),
(381, 41, 230, 210, 'accrual', 'Бонус за заказ №230', '2025-09-06 07:48:34'),
(382, 17, 230, 63, 'accrual', 'Менеджерский бонус за заказ №230', '2025-09-06 07:48:34'),
(383, 136, 239, 49, 'accrual', 'Начисление 49 за заказ №239', '2025-09-06 07:50:00'),
(384, 41, 239, 99, 'accrual', 'Бонус за заказ №239', '2025-09-06 07:50:00'),
(385, 17, 239, 29, 'accrual', 'Менеджерский бонус за заказ №239', '2025-09-06 07:50:00'),
(386, 140, 243, 55, 'accrual', 'Начисление 55 за заказ №243', '2025-09-07 08:44:39'),
(387, 17, 243, 33, 'accrual', 'Бонус за заказ №243', '2025-09-07 08:44:39'),
(388, 141, 244, 125, 'accrual', 'Начисление 125 за заказ №244', '2025-09-07 08:46:52'),
(389, 17, 244, 75, 'accrual', 'Бонус за заказ №244', '2025-09-07 08:46:52'),
(390, 139, 241, 125, 'accrual', 'Начисление 125 за заказ №241', '2025-09-07 09:13:21'),
(391, 41, 241, 250, 'accrual', 'Бонус за заказ №241', '2025-09-07 09:13:21'),
(392, 17, 241, 75, 'accrual', 'Менеджерский бонус за заказ №241', '2025-09-07 09:13:21'),
(393, 112, 242, 180, 'accrual', 'Начисление 180 за заказ №242', '2025-09-07 09:13:31'),
(394, 41, 242, 360, 'accrual', 'Бонус за заказ №242', '2025-09-07 09:13:31'),
(395, 17, 242, 108, 'accrual', 'Менеджерский бонус за заказ №242', '2025-09-07 09:13:31'),
(396, 138, 240, 55, 'accrual', 'Начисление 55 за заказ №240', '2025-09-07 09:22:53'),
(397, 41, 240, 110, 'accrual', 'Бонус за заказ №240', '2025-09-07 09:22:53'),
(398, 17, 240, 33, 'accrual', 'Менеджерский бонус за заказ №240', '2025-09-07 09:22:53'),
(399, 143, 246, 110, 'accrual', 'Начисление 110 за заказ №246', '2025-09-07 09:40:11'),
(400, 17, 246, 66, 'accrual', 'Бонус за заказ №246', '2025-09-07 09:40:11'),
(401, 144, 247, 70, 'accrual', 'Начисление 70 за заказ №247', '2025-09-07 11:38:15'),
(402, 17, 247, 42, 'accrual', 'Бонус за заказ №247', '2025-09-07 11:38:15'),
(403, 142, 245, 55, 'accrual', 'Начисление 55 за заказ №245', '2025-09-07 11:38:29'),
(404, 41, 245, 110, 'accrual', 'Бонус за заказ №245', '2025-09-07 11:38:29'),
(405, 17, 245, 33, 'accrual', 'Менеджерский бонус за заказ №245', '2025-09-07 11:38:29'),
(406, 145, 248, 55, 'accrual', 'Начисление 55 за заказ №248', '2025-09-07 11:39:57'),
(407, 17, 248, 33, 'accrual', 'Бонус за заказ №248', '2025-09-07 11:39:57'),
(408, 149, 252, 125, 'accrual', 'Начисление 125 за заказ №252', '2025-09-08 09:01:11'),
(409, 17, 252, 75, 'accrual', 'Бонус за заказ №252', '2025-09-08 09:01:11'),
(410, 147, 250, 55, 'accrual', 'Начисление 55 за заказ №250', '2025-09-08 09:01:43'),
(411, 41, 250, 110, 'accrual', 'Бонус за заказ №250', '2025-09-08 09:01:43'),
(412, 17, 250, 33, 'accrual', 'Менеджерский бонус за заказ №250', '2025-09-08 09:01:43'),
(413, 146, 249, 70, 'accrual', 'Начисление 70 за заказ №249', '2025-09-08 09:26:05'),
(414, 41, 249, 140, 'accrual', 'Бонус за заказ №249', '2025-09-08 09:26:05'),
(415, 17, 249, 42, 'accrual', 'Менеджерский бонус за заказ №249', '2025-09-08 09:26:05'),
(416, 150, 253, 70, 'accrual', 'Начисление 70 за заказ №253', '2025-09-08 10:18:18'),
(417, 17, 253, 42, 'accrual', 'Бонус за заказ №253', '2025-09-08 10:18:18'),
(418, 148, 251, 180, 'accrual', 'Начисление 180 за заказ №251', '2025-09-08 10:18:43'),
(419, 41, 251, 360, 'accrual', 'Бонус за заказ №251', '2025-09-08 10:18:43'),
(420, 17, 251, 108, 'accrual', 'Менеджерский бонус за заказ №251', '2025-09-08 10:18:43'),
(421, 151, 255, 55, 'accrual', 'Начисление 55 за заказ №255', '2025-09-08 14:08:51'),
(422, 17, 255, 33, 'accrual', 'Бонус за заказ №255', '2025-09-08 14:08:51'),
(423, 17, NULL, -1541, 'payout', 'Запрос выплаты на 1541 ₽', '2025-09-08 14:08:57'),
(424, 153, 257, 330, 'accrual', 'Начисление 330 за заказ №257', '2025-09-09 07:18:11'),
(425, 41, 257, 660, 'accrual', 'Бонус за заказ №257', '2025-09-09 07:18:11'),
(426, 17, 257, 198, 'accrual', 'Менеджерский бонус за заказ №257', '2025-09-09 07:18:11'),
(427, 152, 256, 185, 'accrual', 'Начисление 185 за заказ №256', '2025-09-09 14:52:47'),
(428, 41, 256, 370, 'accrual', 'Бонус за заказ №256', '2025-09-09 14:52:47'),
(429, 17, 256, 111, 'accrual', 'Менеджерский бонус за заказ №256', '2025-09-09 14:52:47'),
(430, 154, 258, 125, 'accrual', 'Начисление 125 за заказ №258', '2025-09-09 14:53:02'),
(431, 17, 258, 75, 'accrual', 'Бонус за заказ №258', '2025-09-09 14:53:02'),
(432, 156, 260, 165, 'accrual', 'Начисление 165 за заказ №260', '2025-09-12 05:18:44'),
(433, 41, 260, 330, 'accrual', 'Бонус за заказ №260', '2025-09-12 05:18:44'),
(434, 17, 260, 99, 'accrual', 'Менеджерский бонус за заказ №260', '2025-09-12 05:18:44'),
(435, 157, 261, 345, 'accrual', 'Начисление 345 за заказ №261', '2025-09-12 05:18:55'),
(436, 41, 261, 690, 'accrual', 'Бонус за заказ №261', '2025-09-12 05:18:55'),
(437, 17, 261, 207, 'accrual', 'Менеджерский бонус за заказ №261', '2025-09-12 05:18:55'),
(438, 142, 254, 125, 'accrual', 'Начисление 125 за заказ №254', '2025-09-12 05:19:14'),
(439, 41, 254, 250, 'accrual', 'Бонус за заказ №254', '2025-09-12 05:19:14'),
(440, 17, 254, 75, 'accrual', 'Менеджерский бонус за заказ №254', '2025-09-12 05:19:14'),
(441, 159, 263, 125, 'accrual', 'Начисление 125 за заказ №263', '2025-09-13 08:44:20'),
(442, 17, 263, 75, 'accrual', 'Бонус за заказ №263', '2025-09-13 08:44:20'),
(443, 158, 262, 110, 'accrual', 'Начисление 110 за заказ №262', '2025-09-13 08:44:52'),
(444, 17, 262, 66, 'accrual', 'Бонус за заказ №262', '2025-09-13 08:44:52'),
(445, 160, 264, 55, 'accrual', 'Начисление 55 за заказ №264', '2025-09-13 08:55:18'),
(446, 41, 264, 110, 'accrual', 'Бонус за заказ №264', '2025-09-13 08:55:18'),
(447, 17, 264, 33, 'accrual', 'Менеджерский бонус за заказ №264', '2025-09-13 08:55:18'),
(448, 163, 267, 125, 'accrual', 'Начисление 125 за заказ №267', '2025-09-14 07:42:41'),
(449, 41, 267, 250, 'accrual', 'Бонус за заказ №267', '2025-09-14 07:42:41'),
(450, 17, 267, 75, 'accrual', 'Менеджерский бонус за заказ №267', '2025-09-14 07:42:41'),
(451, 118, 268, 70, 'accrual', 'Начисление 70 за заказ №268', '2025-09-14 07:57:42'),
(452, 17, 268, 42, 'accrual', 'Бонус за заказ №268', '2025-09-14 07:57:42'),
(453, 162, 266, 70, 'accrual', 'Начисление 70 за заказ №266', '2025-09-14 07:57:51'),
(454, 41, 266, 140, 'accrual', 'Бонус за заказ №266', '2025-09-14 07:57:52'),
(455, 17, 266, 42, 'accrual', 'Менеджерский бонус за заказ №266', '2025-09-14 07:57:52'),
(456, 161, 265, 125, 'accrual', 'Начисление 125 за заказ №265', '2025-09-14 07:58:00'),
(457, 17, 265, 75, 'accrual', 'Бонус за заказ №265', '2025-09-14 07:58:00'),
(458, 17, NULL, -1173, 'payout', 'Запрос выплаты на 1173 ₽', '2025-09-17 11:13:16'),
(459, 164, 269, 70, 'accrual', 'Начисление 70 за заказ №269', '2025-09-18 18:06:54'),
(460, 41, 269, 140, 'accrual', 'Бонус за заказ №269', '2025-09-18 18:06:54'),
(461, 17, 269, 42, 'accrual', 'Менеджерский бонус за заказ №269', '2025-09-18 18:06:54'),
(462, 165, 270, 55, 'accrual', 'Начисление 55 за заказ №270', '2025-09-19 04:35:07'),
(463, 17, 270, 33, 'accrual', 'Бонус за заказ №270', '2025-09-19 04:35:07'),
(464, 166, 271, 55, 'accrual', 'Начисление 55 за заказ №271', '2025-09-19 05:35:42'),
(465, 17, 271, 33, 'accrual', 'Бонус за заказ №271', '2025-09-19 05:35:42'),
(466, 169, 273, 55, 'accrual', 'Начисление 55 за заказ №273', '2025-09-19 14:36:06'),
(467, 41, 273, 110, 'accrual', 'Бонус за заказ №273', '2025-09-19 14:36:06'),
(468, 17, 273, 33, 'accrual', 'Менеджерский бонус за заказ №273', '2025-09-19 14:36:06'),
(469, 167, 272, 55, 'accrual', 'Начисление 55 за заказ №272', '2025-09-19 14:36:12'),
(470, 41, 272, 110, 'accrual', 'Бонус за заказ №272', '2025-09-19 14:36:12'),
(471, 17, 272, 33, 'accrual', 'Менеджерский бонус за заказ №272', '2025-09-19 14:36:12'),
(473, 122, NULL, 120, 'accrual', 'Возврат 120 за удаление заказа №276', '2025-09-20 03:58:01'),
(475, 122, NULL, 120, 'accrual', 'Возврат 120 за удаление заказа №277', '2025-09-20 11:27:11'),
(476, 172, 279, 235, 'accrual', 'Начисление 235 за заказ №279', '2025-09-20 11:28:16'),
(477, 17, 279, 141, 'accrual', 'Бонус за заказ №279', '2025-09-20 11:28:16'),
(479, 171, 278, 55, 'accrual', 'Начисление 55 за заказ №278', '2025-09-22 04:17:03'),
(480, 41, 278, 110, 'accrual', 'Бонус за заказ №278', '2025-09-22 04:17:03'),
(481, 17, 278, 33, 'accrual', 'Менеджерский бонус за заказ №278', '2025-09-22 04:17:03'),
(482, 122, NULL, 120, 'accrual', 'Возврат 120 за удаление заказа №280', '2025-09-22 04:18:07'),
(483, 122, 284, 770, 'accrual', 'Начисление 770 за заказ №284', '2025-09-22 04:18:47'),
(484, 41, 284, 1540, 'accrual', 'Бонус за заказ №284', '2025-09-22 04:18:47'),
(485, 17, 284, 462, 'accrual', 'Менеджерский бонус за заказ №284', '2025-09-22 04:18:47'),
(486, 175, 285, 110, 'accrual', 'Начисление 110 за заказ №285', '2025-09-22 12:24:09'),
(487, 17, 285, 66, 'accrual', 'Бонус за заказ №285', '2025-09-22 12:24:09'),
(488, 176, 287, 55, 'accrual', 'Начисление 55 за заказ №287', '2025-09-23 05:24:05'),
(489, 17, 287, 33, 'accrual', 'Бонус за заказ №287', '2025-09-23 05:24:05'),
(490, 149, NULL, -125, 'usage', 'Скидка за заказ', '2025-09-24 09:52:02'),
(491, 177, 288, 180, 'accrual', 'Начисление 180 за заказ №288', '2025-09-24 10:07:40'),
(492, 41, 288, 360, 'accrual', 'Бонус за заказ №288', '2025-09-24 10:07:40'),
(493, 17, 288, 108, 'accrual', 'Менеджерский бонус за заказ №288', '2025-09-24 10:07:40'),
(494, 149, 289, 118, 'accrual', 'Начисление 118 за заказ №289', '2025-09-24 13:48:33'),
(495, 17, 289, 71, 'accrual', 'Бонус за заказ №289', '2025-09-24 13:48:33'),
(496, 178, 290, 125, 'accrual', 'Начисление 125 за заказ №290', '2025-09-26 08:07:08'),
(497, 17, 290, 75, 'accrual', 'Бонус за заказ №290', '2025-09-26 08:07:08'),
(498, 152, 291, 125, 'accrual', 'Начисление 125 за заказ №291', '2025-09-26 13:16:10'),
(499, 41, 291, 250, 'accrual', 'Бонус за заказ №291', '2025-09-26 13:16:11'),
(500, 17, 291, 75, 'accrual', 'Менеджерский бонус за заказ №291', '2025-09-26 13:16:11'),
(501, 17, NULL, -1238, 'payout', 'Запрос выплаты на 1238 ₽', '2025-09-26 13:16:17'),
(502, 179, 292, 55, 'accrual', 'Начисление 55 за заказ №292', '2025-09-27 08:04:10'),
(503, 17, 292, 33, 'accrual', 'Бонус за заказ №292', '2025-09-27 08:04:10'),
(504, 149, NULL, -118, 'usage', 'Скидка за заказ', '2025-09-28 03:45:06'),
(505, 149, 294, 70, 'accrual', 'Начисление 70 за заказ №294', '2025-09-29 07:13:18'),
(506, 17, 294, 42, 'accrual', 'Бонус за заказ №294', '2025-09-29 07:13:18'),
(507, 149, 293, 64, 'accrual', 'Начисление 64 за заказ №293', '2025-09-29 07:13:24'),
(508, 17, 293, 38, 'accrual', 'Бонус за заказ №293', '2025-09-29 07:13:24'),
(509, 180, 295, 110, 'accrual', 'Начисление 110 за заказ №295', '2025-09-29 07:13:29'),
(510, 41, 295, 220, 'accrual', 'Бонус за заказ №295', '2025-09-29 07:13:29'),
(511, 17, 295, 66, 'accrual', 'Менеджерский бонус за заказ №295', '2025-09-29 07:13:29'),
(512, 181, 296, 165, 'accrual', 'Начисление 165 за заказ №296', '2025-09-30 15:38:57'),
(513, 17, 296, 99, 'accrual', 'Бонус за заказ №296', '2025-09-30 15:38:57'),
(514, 183, 299, 55, 'accrual', 'Начисление 55 за заказ №299', '2025-09-30 15:44:58'),
(515, 17, 299, 33, 'accrual', 'Бонус за заказ №299', '2025-09-30 15:44:58'),
(516, 149, NULL, -134, 'usage', 'Скидка за заказ', '2025-10-01 04:54:35'),
(517, 149, 302, 118, 'accrual', 'Начисление 118 за заказ №302', '2025-10-01 13:26:27'),
(518, 17, 302, 70, 'accrual', 'Бонус за заказ №302', '2025-10-01 13:26:27'),
(519, 184, 300, 55, 'accrual', 'Начисление 55 за заказ №300', '2025-10-01 13:26:35'),
(520, 17, 300, 33, 'accrual', 'Бонус за заказ №300', '2025-10-01 13:26:35'),
(521, 182, 301, 180, 'accrual', 'Начисление 180 за заказ №301', '2025-10-01 13:26:48'),
(522, 41, 301, 360, 'accrual', 'Бонус за заказ №301', '2025-10-01 13:26:48'),
(523, 17, 301, 108, 'accrual', 'Менеджерский бонус за заказ №301', '2025-10-01 13:26:48'),
(524, 116, 304, 125, 'accrual', 'Начисление 125 за заказ №304', '2025-10-02 07:40:38'),
(525, 41, 304, 250, 'accrual', 'Бонус за заказ №304', '2025-10-02 07:40:38'),
(526, 17, 304, 75, 'accrual', 'Менеджерский бонус за заказ №304', '2025-10-02 07:40:38'),
(527, 186, 332, 55, 'accrual', 'Начисление 55 за заказ №332', '2025-10-02 07:41:19'),
(528, 41, 332, 110, 'accrual', 'Бонус за заказ №332', '2025-10-02 07:41:19'),
(529, 17, 332, 33, 'accrual', 'Менеджерский бонус за заказ №332', '2025-10-02 07:41:19'),
(530, 112, 331, 180, 'accrual', 'Начисление 180 за заказ №331', '2025-10-02 07:43:43'),
(531, 41, 331, 360, 'accrual', 'Бонус за заказ №331', '2025-10-02 07:43:43'),
(532, 17, 331, 108, 'accrual', 'Менеджерский бонус за заказ №331', '2025-10-02 07:43:43'),
(533, 187, 334, 110, 'accrual', 'Начисление 110 за заказ №334', '2025-10-02 07:49:17'),
(534, 41, 334, 220, 'accrual', 'Бонус за заказ №334', '2025-10-02 07:49:17'),
(535, 17, 334, 66, 'accrual', 'Менеджерский бонус за заказ №334', '2025-10-02 07:49:17'),
(536, 172, 303, 125, 'accrual', 'Начисление 125 за заказ №303', '2025-10-02 13:57:52'),
(537, 17, 303, 75, 'accrual', 'Бонус за заказ №303', '2025-10-02 13:57:52'),
(538, 118, 333, 70, 'accrual', 'Начисление 70 за заказ №333', '2025-10-02 13:57:58'),
(539, 17, 333, 42, 'accrual', 'Бонус за заказ №333', '2025-10-02 13:57:58'),
(540, 188, 335, 180, 'accrual', 'Начисление 180 за заказ №335', '2025-10-02 13:58:04'),
(541, 17, 335, 108, 'accrual', 'Бонус за заказ №335', '2025-10-02 13:58:04'),
(542, 189, 336, 55, 'accrual', 'Начисление 55 за заказ №336', '2025-10-02 13:58:16'),
(543, 41, 336, 110, 'accrual', 'Бонус за заказ №336', '2025-10-02 13:58:16'),
(544, 17, 336, 33, 'accrual', 'Менеджерский бонус за заказ №336', '2025-10-02 13:58:16'),
(545, 190, 337, 55, 'accrual', 'Начисление 55 за заказ №337', '2025-10-02 13:58:27'),
(546, 41, 337, 110, 'accrual', 'Бонус за заказ №337', '2025-10-02 13:58:27'),
(547, 17, 337, 33, 'accrual', 'Менеджерский бонус за заказ №337', '2025-10-02 13:58:27'),
(548, 78, 338, 55, 'accrual', 'Начисление 55 за заказ №338', '2025-10-05 14:20:38'),
(549, 1, 338, 33, 'accrual', 'Бонус за заказ №338', '2025-10-05 14:20:38'),
(551, 194, 340, 110, 'accrual', 'Начисление 110 за заказ №340', '2025-10-07 15:23:44'),
(552, 41, 340, 220, 'accrual', 'Бонус за заказ №340', '2025-10-07 15:23:44'),
(553, 17, 340, 66, 'accrual', 'Менеджерский бонус за заказ №340', '2025-10-07 15:23:45'),
(554, 193, 339, 55, 'accrual', 'Начисление 55 за заказ №339', '2025-10-07 15:23:51'),
(555, 41, 339, 110, 'accrual', 'Бонус за заказ №339', '2025-10-07 15:23:51'),
(556, 17, 339, 33, 'accrual', 'Менеджерский бонус за заказ №339', '2025-10-07 15:23:51'),
(557, 195, 341, 130, 'accrual', 'Начисление 130 за заказ №341', '2025-10-08 12:18:28'),
(558, 17, 341, 78, 'accrual', 'Бонус за заказ №341', '2025-10-08 12:18:28'),
(559, 196, 342, 80, 'accrual', 'Начисление 80 за заказ №342', '2025-10-08 12:18:35'),
(560, 17, 342, 48, 'accrual', 'Бонус за заказ №342', '2025-10-08 12:18:35'),
(561, 116, 344, 80, 'accrual', 'Начисление 80 за заказ №344', '2025-10-08 12:19:57'),
(562, 41, 344, 160, 'accrual', 'Бонус за заказ №344', '2025-10-08 12:19:57'),
(563, 17, 344, 48, 'accrual', 'Менеджерский бонус за заказ №344', '2025-10-08 12:19:57'),
(564, 17, NULL, -1368, 'payout', 'Запрос выплаты на 1368 ₽', '2025-10-08 12:20:20'),
(565, 205, 356, 390, 'accrual', 'Начисление 390 за заказ №356', '2025-10-14 07:05:25'),
(566, 17, 356, 234, 'accrual', 'Бонус за заказ №356', '2025-10-14 07:05:25'),
(567, 118, 359, 85, 'accrual', 'Начисление 85 за заказ №359', '2025-10-15 03:31:03'),
(568, 17, 359, 51, 'accrual', 'Бонус за заказ №359', '2025-10-15 03:31:03'),
(569, 116, 360, 155, 'accrual', 'Начисление 155 за заказ №360', '2025-10-15 03:31:45'),
(570, 41, 360, 310, 'accrual', 'Бонус за заказ №360', '2025-10-15 03:31:45'),
(571, 17, 360, 93, 'accrual', 'Менеджерский бонус за заказ №360', '2025-10-15 03:31:45'),
(572, 203, 358, 295, 'accrual', 'Начисление 295 за заказ №358', '2025-10-15 15:57:37'),
(573, 17, 358, 177, 'accrual', 'Бонус за заказ №358', '2025-10-15 15:57:37'),
(574, 197, 345, 145, 'accrual', 'Начисление 145 за заказ №345', '2025-10-15 15:57:45'),
(575, 17, 345, 87, 'accrual', 'Бонус за заказ №345', '2025-10-15 15:57:45'),
(576, 198, 349, 130, 'accrual', 'Начисление 130 за заказ №349', '2025-10-15 15:57:57'),
(577, 17, 349, 78, 'accrual', 'Бонус за заказ №349', '2025-10-15 15:57:57'),
(578, 204, 355, 80, 'accrual', 'Начисление 80 за заказ №355', '2025-10-15 15:58:44'),
(579, 17, 355, 48, 'accrual', 'Бонус за заказ №355', '2025-10-15 15:58:44'),
(580, 202, 353, 145, 'accrual', 'Начисление 145 за заказ №353', '2025-10-15 15:58:53'),
(581, 17, 353, 87, 'accrual', 'Бонус за заказ №353', '2025-10-15 15:58:53'),
(582, 149, NULL, -118, 'usage', 'Скидка за заказ', '2025-10-16 11:02:44'),
(583, 149, 363, 79, 'accrual', 'Начисление 79 за заказ №363', '2025-10-16 15:33:13'),
(584, 17, 363, 47, 'accrual', 'Бонус за заказ №363', '2025-10-16 15:33:13'),
(585, 208, 362, 85, 'accrual', 'Начисление 85 за заказ №362', '2025-10-16 15:33:31'),
(586, 17, 362, 51, 'accrual', 'Бонус за заказ №362', '2025-10-16 15:33:31'),
(587, 207, 361, 85, 'accrual', 'Начисление 85 за заказ №361', '2025-10-16 15:33:43'),
(588, 17, 361, 51, 'accrual', 'Бонус за заказ №361', '2025-10-16 15:33:43'),
(589, 211, 367, 85, 'accrual', 'Начисление 85 за заказ №367', '2025-10-18 07:49:54'),
(590, 17, 367, 51, 'accrual', 'Бонус за заказ №367', '2025-10-18 07:49:54'),
(591, 208, 368, 85, 'accrual', 'Начисление 85 за заказ №368', '2025-10-18 07:49:59'),
(592, 17, 368, 51, 'accrual', 'Бонус за заказ №368', '2025-10-18 07:49:59'),
(593, 212, 369, 155, 'accrual', 'Начисление 155 за заказ №369', '2025-10-18 08:15:40'),
(594, 17, 369, 93, 'accrual', 'Бонус за заказ №369', '2025-10-18 08:15:40'),
(595, 209, 364, 70, 'accrual', 'Начисление 70 за заказ №364', '2025-10-19 04:48:45'),
(596, 41, 364, 140, 'accrual', 'Бонус за заказ №364', '2025-10-19 04:48:45'),
(597, 17, 364, 42, 'accrual', 'Менеджерский бонус за заказ №364', '2025-10-19 04:48:45'),
(598, 210, 365, 70, 'accrual', 'Начисление 70 за заказ №365', '2025-10-19 04:48:53'),
(599, 41, 365, 140, 'accrual', 'Бонус за заказ №365', '2025-10-19 04:48:53'),
(600, 17, 365, 42, 'accrual', 'Менеджерский бонус за заказ №365', '2025-10-19 04:48:53'),
(601, 203, 366, 70, 'accrual', 'Начисление 70 за заказ №366', '2025-10-19 04:49:01'),
(602, 17, 366, 42, 'accrual', 'Бонус за заказ №366', '2025-10-19 04:49:01'),
(603, 17, NULL, -1325, 'payout', 'Запрос выплаты на 1325 ₽', '2025-10-20 05:16:18'),
(604, 116, 371, 165, 'accrual', 'Начисление 165 за заказ №371', '2025-10-20 16:45:49'),
(605, 41, 371, 330, 'accrual', 'Бонус за заказ №371', '2025-10-20 16:45:50'),
(606, 17, 371, 99, 'accrual', 'Менеджерский бонус за заказ №371', '2025-10-20 16:45:50'),
(607, 219, 378, 205, 'accrual', 'Начисление 205 за заказ №378', '2025-10-23 06:48:05'),
(608, 17, 378, 123, 'accrual', 'Бонус за заказ №378', '2025-10-23 06:48:05'),
(609, 216, 375, 90, 'accrual', 'Начисление 90 за заказ №375', '2025-10-23 06:52:21'),
(610, 220, 379, 75, 'accrual', 'Начисление 75 за заказ №379', '2025-10-23 14:37:05'),
(611, 17, 379, 45, 'accrual', 'Бонус за заказ №379', '2025-10-23 14:37:05'),
(614, 214, 373, 75, 'accrual', 'Начисление 75 за заказ №373', '2025-10-23 14:47:26'),
(615, 17, 373, 45, 'accrual', 'Бонус за заказ №373', '2025-10-23 14:47:26'),
(616, 218, 377, 75, 'accrual', 'Начисление 75 за заказ №377', '2025-10-27 06:23:48'),
(617, 17, 377, 45, 'accrual', 'Бонус за заказ №377', '2025-10-27 06:23:48'),
(618, 108, NULL, -69, 'usage', 'Скидка за заказ', '2026-03-24 16:09:00'),
(619, 17, NULL, -411, 'payout', 'Запрос выплаты на 411 ₽', '2026-03-24 19:19:10'),
(620, 149, 363, 79, 'accrual', 'Начисление 79 за заказ №363', '2026-05-04 18:16:18'),
(621, 232, 397, 70, 'accrual', 'Начисление 70 за заказ №397', '2026-05-08 11:23:55'),
(622, 17, 397, 42, 'accrual', 'Бонус за заказ №397', '2026-05-08 11:23:55'),
(623, 234, 399, 55, 'accrual', 'Начисление 55 за заказ №399', '2026-05-08 11:27:28'),
(624, 17, 399, 33, 'accrual', 'Бонус за заказ №399', '2026-05-08 11:27:28'),
(625, 116, 401, 70, 'accrual', 'Начисление 70 за заказ №401', '2026-05-08 11:39:07'),
(626, 41, 401, 140, 'accrual', 'Бонус за заказ №401', '2026-05-08 11:39:07'),
(627, 17, 401, 42, 'accrual', 'Менеджерский бонус за заказ №401', '2026-05-08 11:39:07'),
(628, 233, 398, 55, 'accrual', 'Начисление 55 за заказ №398', '2026-05-08 13:28:24'),
(629, 17, 398, 33, 'accrual', 'Бонус за заказ №398', '2026-05-08 13:28:24'),
(630, 236, 402, 55, 'accrual', 'Начисление 55 за заказ №402', '2026-05-08 13:37:48'),
(631, 17, 402, 33, 'accrual', 'Бонус за заказ №402', '2026-05-08 13:37:48'),
(632, 238, 405, 80, 'accrual', 'Начисление 80 за заказ №405', '2026-05-11 04:07:26'),
(633, 17, 405, 48, 'accrual', 'Бонус за заказ №405', '2026-05-11 04:07:26'),
(634, 237, 404, 75, 'accrual', 'Начисление 75 за заказ №404', '2026-05-11 04:07:33'),
(635, 17, 404, 45, 'accrual', 'Бонус за заказ №404', '2026-05-11 04:07:33'),
(636, 239, 406, 60, 'accrual', 'Начисление 60 за заказ №406', '2026-05-11 04:07:42'),
(637, 17, 406, 36, 'accrual', 'Бонус за заказ №406', '2026-05-11 04:07:42'),
(638, 240, 407, 120, 'accrual', 'Начисление 120 за заказ №407', '2026-05-11 04:07:49'),
(639, 17, 407, 72, 'accrual', 'Бонус за заказ №407', '2026-05-11 04:07:50'),
(640, 117, 408, 540, 'accrual', 'Начисление 540 за заказ №408', '2026-05-11 04:09:05'),
(641, 17, 408, 324, 'accrual', 'Бонус за заказ №408', '2026-05-11 04:09:05'),
(642, 241, 410, 130, 'accrual', 'Начисление 130 за заказ №410', '2026-05-11 14:06:01'),
(643, 17, 410, 78, 'accrual', 'Бонус за заказ №410', '2026-05-11 14:06:01'),
(644, 244, 413, 70, 'accrual', 'Начисление 70 за заказ №413', '2026-05-14 10:03:16'),
(645, 17, 413, 42, 'accrual', 'Бонус за заказ №413', '2026-05-14 10:03:16'),
(646, 243, 412, 75, 'accrual', 'Начисление 75 за заказ №412', '2026-05-14 10:03:44'),
(647, 17, 412, 45, 'accrual', 'Бонус за заказ №412', '2026-05-14 10:03:44'),
(648, 1, NULL, -74, 'usage', 'Скидка за заказ', '2026-05-14 12:32:16'),
(649, 247, 429, 56, 'accrual', 'Начисление 56 за заказ №429', '2026-05-14 15:32:12'),
(650, 17, 429, 33, 'accrual', 'Бонус за заказ №429', '2026-05-14 15:32:12'),
(651, 1, NULL, 74, 'accrual', 'Возврат 74 за удаление заказа №426', '2026-05-14 19:54:10'),
(652, 249, 432, 56, 'accrual', 'Начисление 56 за заказ №432', '2026-05-15 07:10:13'),
(653, 17, 432, 33, 'accrual', 'Бонус за заказ №432', '2026-05-15 07:10:13'),
(654, 248, 430, 56, 'accrual', 'Начисление 56 за заказ №430', '2026-05-15 07:10:44'),
(655, 17, 430, 33, 'accrual', 'Бонус за заказ №430', '2026-05-15 07:10:44'),
(656, 246, 415, 55, 'accrual', 'Начисление 55 за заказ №415', '2026-05-15 07:11:33'),
(657, 17, 415, 33, 'accrual', 'Бонус за заказ №415', '2026-05-15 07:11:33'),
(658, 116, 434, 71, 'accrual', 'Начисление 71 за заказ №434', '2026-05-15 08:58:35'),
(659, 41, 434, 42, 'accrual', 'Бонус за заказ №434', '2026-05-15 08:58:35'),
(660, 17, 434, 42, 'accrual', 'Менеджерский бонус за заказ №434', '2026-05-15 08:58:35'),
(661, 250, 433, 56, 'accrual', 'Начисление 56 за заказ №433', '2026-05-15 10:38:00'),
(662, 17, 433, 33, 'accrual', 'Бонус за заказ №433', '2026-05-15 10:38:00'),
(663, 252, 436, 71, 'accrual', 'Начисление 71 за заказ №436', '2026-05-15 16:15:20'),
(664, 17, 436, 42, 'accrual', 'Бонус за заказ №436', '2026-05-15 16:15:20'),
(665, 253, 437, 56, 'accrual', 'Начисление 56 за заказ №437', '2026-05-15 16:34:35'),
(666, 17, 437, 33, 'accrual', 'Бонус за заказ №437', '2026-05-15 16:34:35'),
(667, 255, 439, 90, 'accrual', 'Начисление 90 за заказ №439', '2026-05-18 12:24:26'),
(668, 17, 439, 54, 'accrual', 'Бонус за заказ №439', '2026-05-18 12:24:26'),
(669, 116, 441, 95, 'accrual', 'Начисление 95 за заказ №441', '2026-05-19 15:19:49'),
(670, 41, 441, 57, 'accrual', 'Бонус за заказ №441', '2026-05-19 15:19:49'),
(671, 17, 441, 57, 'accrual', 'Менеджерский бонус за заказ №441', '2026-05-19 15:19:49'),
(672, 256, 442, 56, 'accrual', 'Начисление 56 за заказ №442', '2026-05-19 15:23:12'),
(673, 17, 442, 33, 'accrual', 'Бонус за заказ №442', '2026-05-19 15:23:12'),
(674, 257, 443, 95, 'accrual', 'Начисление 95 за заказ №443', '2026-05-19 15:25:26'),
(675, 17, 443, 57, 'accrual', 'Бонус за заказ №443', '2026-05-19 15:25:26'),
(676, 1, NULL, -74, 'usage', 'Скидка за заказ', '2026-05-20 07:33:31'),
(677, 1, NULL, 74, 'accrual', 'Возврат 74 за удаление заказа №444', '2026-05-20 10:37:42'),
(678, 1, NULL, -74, 'usage', 'Скидка за заказ', '2026-05-20 10:38:15'),
(679, 1, NULL, 74, 'accrual', 'Возврат 74 за удаление заказа №445', '2026-05-20 10:38:39'),
(680, 259, 447, 90, 'accrual', 'Начисление 90 за заказ №447', '2026-05-20 14:44:49'),
(681, 17, 447, 54, 'accrual', 'Бонус за заказ №447', '2026-05-20 14:44:49'),
(683, 1, NULL, 74, 'accrual', 'Возврат 74 за удаление заказа №448', '2026-05-21 07:42:03'),
(685, 117, 454, -1140, 'usage', 'Списание 1140 клубничек за заказ #454', '2026-05-22 05:59:08'),
(686, 243, 455, 90, 'accrual', 'Начисление 90 за заказ №455', '2026-05-22 06:00:31'),
(687, 17, 455, 54, 'accrual', 'Бонус за заказ №455', '2026-05-22 06:00:31'),
(688, 1, NULL, 74, 'accrual', 'Возврат 74 за удаление заказа №453', '2026-05-22 13:42:35'),
(689, 117, 454, 1090, 'accrual', 'Начисление 1090 за заказ №454', '2026-05-22 14:02:17'),
(690, 17, 454, 654, 'accrual', 'Бонус за заказ №454', '2026-05-22 14:02:17'),
(691, 258, 446, 225, 'accrual', 'Начисление 225 за заказ №446', '2026-05-24 13:31:33'),
(692, 17, 446, 135, 'accrual', 'Бонус за заказ №446', '2026-05-24 13:31:33'),
(693, 116, 457, 60, 'accrual', 'Начисление 60 за заказ №457', '2026-05-25 10:13:42'),
(694, 41, 457, 36, 'accrual', 'Бонус за заказ №457', '2026-05-25 10:13:42'),
(695, 17, 457, 36, 'accrual', 'Менеджерский бонус за заказ №457', '2026-05-25 10:13:42'),
(696, 261, 458, 60, 'accrual', 'Начисление 60 за заказ №458', '2026-05-25 13:10:46'),
(697, 17, 458, 36, 'accrual', 'Бонус за заказ №458', '2026-05-25 13:10:46'),
(698, 262, 459, 60, 'accrual', 'Начисление 60 за заказ №459', '2026-05-25 13:16:54'),
(699, 17, 459, 36, 'accrual', 'Бонус за заказ №459', '2026-05-25 13:16:54'),
(700, 17, NULL, -2361, 'payout', 'Запрос выплаты на 2361 ₽', '2026-05-25 14:03:50'),
(701, 1, NULL, -74, 'usage', 'Скидка за заказ', '2026-05-28 05:05:44'),
(702, 264, 461, 60, 'accrual', 'Начисление 60 за заказ №461', '2026-05-28 14:30:29'),
(703, 17, 461, 36, 'accrual', 'Бонус за заказ №461', '2026-05-28 14:30:29'),
(704, 265, 462, 170, 'accrual', 'Начисление 170 за заказ №462', '2026-05-28 14:33:40'),
(705, 17, 462, 102, 'accrual', 'Бонус за заказ №462', '2026-05-28 14:33:40'),
(706, 172, 463, 60, 'accrual', 'Начисление 60 за заказ №463', '2026-05-28 14:35:48'),
(707, 17, 463, 36, 'accrual', 'Бонус за заказ №463', '2026-05-28 14:35:48'),
(708, 1, NULL, 74, 'accrual', 'Возврат 74 за удаление заказа №460', '2026-05-29 09:24:26'),
(709, 1, NULL, -74, 'usage', 'Скидка за заказ', '2026-06-01 13:40:08'),
(710, 1, NULL, 74, 'accrual', 'Возврат 74 за удаление заказа №465', '2026-06-01 14:13:58'),
(711, 266, 466, 67, 'accrual', 'Начисление 67 за заказ №466', '2026-06-02 08:01:41'),
(712, 17, 466, 40, 'accrual', 'Бонус за заказ №466', '2026-06-02 08:01:41'),
(713, 116, 467, 90, 'accrual', 'Начисление 90 за заказ №467', '2026-06-02 10:35:39'),
(714, 41, 467, 54, 'accrual', 'Бонус за заказ №467', '2026-06-02 10:35:39'),
(715, 17, 467, 54, 'accrual', 'Менеджерский бонус за заказ №467', '2026-06-02 10:35:39'),
(716, 172, 469, 90, 'accrual', 'Начисление 90 за заказ №469', '2026-06-02 14:00:18'),
(717, 17, 469, 54, 'accrual', 'Бонус за заказ №469', '2026-06-02 14:00:18'),
(718, 1, NULL, -74, 'usage', 'Скидка за заказ', '2026-06-02 16:13:59'),
(719, 1, NULL, 74, 'accrual', 'Возврат 74 за удаление заказа №470', '2026-06-02 16:19:46'),
(720, 267, 468, 90, 'accrual', 'Начисление 90 за заказ №468', '2026-06-02 16:19:57'),
(721, 116, 472, -1000, 'usage', 'Списание 1000 клубничек за заказ #472', '2026-06-03 07:08:40'),
(722, 116, 472, 25, 'accrual', 'Начисление 25 за заказ №472', '2026-06-03 07:08:56'),
(723, 41, 472, 15, 'accrual', 'Бонус за заказ №472', '2026-06-03 07:08:56'),
(724, 17, 472, 15, 'accrual', 'Менеджерский бонус за заказ №472', '2026-06-03 07:08:56'),
(725, 117, 473, -1000, 'usage', 'Списание 1000 клубничек за заказ #473', '2026-06-03 07:11:24'),
(726, 117, 473, 25, 'accrual', 'Начисление 25 за заказ №473', '2026-06-03 07:11:33'),
(727, 17, 473, 15, 'accrual', 'Бонус за заказ №473', '2026-06-03 07:11:33'),
(728, 1, NULL, -74, 'usage', 'Скидка за заказ', '2026-06-03 07:20:30'),
(729, 1, 474, 46, 'accrual', 'Начисление 46 за заказ №474', '2026-06-03 17:20:25'),
(730, 1, NULL, -46, 'usage', 'Скидка за заказ', '2026-06-03 19:23:45'),
(731, 1, NULL, 46, 'accrual', 'Возврат 46 за удаление заказа №475', '2026-06-03 19:24:55'),
(732, 1, NULL, -46, 'usage', 'Скидка за заказ', '2026-06-05 14:46:38'),
(733, 268, 478, 75, 'accrual', 'Начисление 75 за заказ №478', '2026-06-06 06:35:56'),
(734, 17, 478, 45, 'accrual', 'Бонус за заказ №478', '2026-06-06 06:35:56'),
(735, 259, 477, 75, 'accrual', 'Начисление 75 за заказ №477', '2026-06-06 06:36:17'),
(736, 17, 477, 45, 'accrual', 'Бонус за заказ №477', '2026-06-06 06:36:17'),
(737, 1, NULL, 46, 'accrual', 'Возврат 46 за удаление заказа №476', '2026-06-07 13:57:20'),
(738, 1, NULL, -46, 'usage', 'Скидка за заказ', '2026-06-08 03:05:47'),
(739, 1, NULL, 46, 'accrual', 'Возврат 46 за удаление заказа №479', '2026-06-08 17:33:40'),
(740, 269, 481, 75, 'accrual', 'Начисление 75 за заказ №481', '2026-06-11 05:06:17'),
(741, 269, 481, 75, 'accrual', 'Начисление 75 за заказ №481', '2026-06-11 12:11:36'),
(742, 269, 481, 67, 'accrual', 'Начисление 67 за заказ №481', '2026-06-11 16:47:35'),
(743, 269, 481, 67, 'accrual', 'Начисление 67 за заказ №481', '2026-06-11 16:59:33'),
(744, 17, 481, 40, 'accrual', 'Базовые 3% менеджера за заказ №481', '2026-06-11 16:59:33'),
(745, 270, 482, 70, 'accrual', 'Начисление 70 за заказ №482', '2026-06-12 17:24:24'),
(746, 17, 482, 42, 'accrual', 'Базовые 3% менеджера за заказ №482', '2026-06-12 17:24:24'),
(747, 265, 484, 135, 'accrual', 'Начисление 135 за заказ №484', '2026-06-12 17:27:54'),
(748, 17, 484, 81, 'accrual', 'Базовые 3% менеджера за заказ №484', '2026-06-12 17:27:54'),
(749, 265, 483, 90, 'accrual', 'Начисление 90 за заказ №483', '2026-06-12 17:28:09'),
(750, 17, 483, 54, 'accrual', 'Базовые 3% менеджера за заказ №483', '2026-06-12 17:28:09');
INSERT INTO `points_transactions` (`id`, `user_id`, `order_id`, `amount`, `transaction_type`, `description`, `created_at`) VALUES
(751, 271, 485, 332, 'accrual', 'Начисление 332 за заказ №485', '2026-06-12 17:34:14'),
(752, 17, 485, 199, 'accrual', 'Базовые 3% менеджера за заказ №485', '2026-06-12 17:34:14'),
(753, 1, NULL, -46, 'usage', 'Скидка за заказ', '2026-06-14 08:09:52'),
(754, 273, 487, 75, 'accrual', 'Начисление 75 за заказ №487', '2026-06-16 12:50:59'),
(755, 17, 487, 45, 'accrual', 'Базовые 3% менеджера за заказ №487', '2026-06-16 12:50:59'),
(756, 274, 488, 75, 'accrual', 'Начисление 75 за заказ №488', '2026-06-17 12:37:19'),
(757, 17, 488, 45, 'accrual', 'Базовые 3% менеджера за заказ №488', '2026-06-17 12:37:19'),
(758, 275, 489, 75, 'accrual', 'Начисление 75 за заказ №489', '2026-06-17 14:09:53'),
(759, 17, 489, 45, 'accrual', 'Базовые 3% менеджера за заказ №489', '2026-06-17 14:09:53'),
(760, 276, 490, 75, 'accrual', 'Начисление 75 за заказ №490', '2026-06-18 14:01:27'),
(761, 17, 490, 45, 'accrual', 'Базовые 3% менеджера за заказ №490', '2026-06-18 14:01:27'),
(762, 277, 492, 165, 'accrual', 'Начисление 165 за заказ №492', '2026-06-18 14:06:12'),
(763, 17, 492, 99, 'accrual', 'Базовые 3% менеджера за заказ №492', '2026-06-18 14:06:12'),
(764, 172, 491, 75, 'accrual', 'Начисление 75 за заказ №491', '2026-06-18 14:06:21'),
(765, 17, 491, 45, 'accrual', 'Базовые 3% менеджера за заказ №491', '2026-06-18 14:06:21'),
(766, 278, 493, 75, 'accrual', 'Начисление 75 за заказ №493', '2026-06-18 14:06:30'),
(767, 17, 493, 45, 'accrual', 'Базовые 3% менеджера за заказ №493', '2026-06-18 14:06:30');

-- --------------------------------------------------------

--
-- Структура таблицы `preorder_intents`
--

CREATE TABLE `preorder_intents` (
  `id` bigint UNSIGNED NOT NULL,
  `user_id` bigint UNSIGNED NOT NULL,
  `product_id` bigint UNSIGNED NOT NULL,
  `purchase_batch_id` bigint UNSIGNED DEFAULT NULL,
  `requested_boxes` decimal(10,2) NOT NULL,
  `desired_delivery_date` date DEFAULT NULL,
  `status` enum('waiting_batch','linked_to_batch','awaiting_price_confirmation','confirmed','declined','expired','moved_to_cart','completed','intent_created','offer_sent') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'waiting_batch',
  `offered_price_per_box` decimal(10,2) DEFAULT NULL,
  `expected_price_per_box` decimal(10,2) DEFAULT NULL,
  `discount_percent_snapshot` decimal(5,2) DEFAULT NULL,
  `offer_expires_at` datetime DEFAULT NULL,
  `checkout_token` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ;

--
-- Дамп данных таблицы `preorder_intents`
--

INSERT INTO `preorder_intents` (`id`, `user_id`, `product_id`, `purchase_batch_id`, `requested_boxes`, `desired_delivery_date`, `status`, `offered_price_per_box`, `expected_price_per_box`, `discount_percent_snapshot`, `offer_expires_at`, `checkout_token`, `created_at`, `updated_at`) VALUES
(1, 108, 20, NULL, 1.00, NULL, 'declined', NULL, NULL, NULL, NULL, NULL, '2026-05-14 22:45:09', '2026-05-18 15:23:42'),
(2, 108, 7, NULL, 1.00, NULL, 'declined', NULL, NULL, NULL, NULL, NULL, '2026-05-15 06:11:35', '2026-05-18 15:23:42'),
(3, 233, 7, NULL, 1.00, NULL, 'declined', NULL, NULL, NULL, NULL, NULL, '2026-05-16 15:58:17', '2026-05-20 05:32:51'),
(4, 1, 6, NULL, 1.00, NULL, 'declined', NULL, NULL, NULL, NULL, NULL, '2026-05-17 19:31:20', '2026-05-20 05:32:51'),
(5, 1, 7, NULL, 1.00, NULL, 'declined', NULL, NULL, NULL, NULL, NULL, '2026-05-18 15:19:30', '2026-05-22 08:48:38'),
(6, 1, 20, NULL, 1.00, NULL, 'declined', NULL, NULL, NULL, NULL, NULL, '2026-05-18 15:20:16', '2026-05-22 08:48:38'),
(7, 1, 6, NULL, 1.00, NULL, 'declined', NULL, NULL, NULL, NULL, NULL, '2026-05-22 16:37:02', '2026-05-24 17:11:58'),
(8, 1, 7, NULL, 1.00, '2026-05-25', 'declined', NULL, 1080.00, 10.00, NULL, NULL, '2026-05-22 21:06:36', '2026-05-29 13:02:50'),
(9, 1, 20, NULL, 1.00, '2026-05-27', 'declined', 1040.00, 1040.00, 10.00, '2026-05-29 11:22:55', NULL, '2026-05-27 11:22:55', '2026-05-29 13:02:46'),
(10, 1, 20, 23, 1.00, '2026-05-30', 'declined', 1080.00, 1080.00, 10.00, NULL, NULL, '2026-05-28 13:44:33', '2026-05-29 13:02:53'),
(11, 267, 7, 25, 1.00, NULL, 'declined', 1350.00, NULL, 0.00, NULL, NULL, '2026-06-02 14:32:36', '2026-06-04 15:47:41'),
(12, 1, 7, 25, 17.00, '2026-06-05', 'declined', 1350.00, 1350.00, 10.00, NULL, NULL, '2026-06-03 10:16:32', '2026-06-04 15:47:35'),
(13, 1, 6, 27, 1.00, '2026-06-05', 'declined', 1620.00, 1620.00, 10.00, NULL, NULL, '2026-06-03 10:53:44', '2026-06-04 15:47:45'),
(14, 17, 7, 30, 1.00, NULL, 'declined', 1210.00, 1210.00, 10.00, NULL, NULL, '2026-06-06 09:28:11', '2026-06-07 16:57:27'),
(15, 1, 6, 28, 1.00, '2026-06-07', 'declined', 1620.00, 1620.00, 10.00, NULL, NULL, '2026-06-07 16:56:41', '2026-06-07 16:57:32'),
(16, 1, 20, 26, 1.00, '2026-06-07', 'declined', 1350.00, 1350.00, 10.00, NULL, NULL, '2026-06-08 06:05:05', '2026-06-08 20:33:11'),
(17, 1, 7, 30, 1.00, '2026-06-19', 'declined', 1350.00, 1350.00, 10.00, NULL, NULL, '2026-06-08 20:30:12', '2026-06-08 20:33:05'),
(18, 1, 31, 29, 1.00, '2026-06-11', 'declined', 1620.00, 2020.00, 10.00, '2026-06-12 13:57:27', NULL, '2026-06-09 11:27:47', '2026-06-11 15:10:49'),
(19, 294, 11, NULL, 1.00, '2026-07-05', 'declined', NULL, NULL, 0.00, NULL, NULL, '2026-07-03 13:35:41', '2026-07-06 13:50:25'),
(20, 294, 6, 37, 1.00, '2026-07-05', 'declined', 2430.00, NULL, 0.00, NULL, NULL, '2026-07-03 13:40:17', '2026-07-06 13:50:29'),
(21, 297, 7, 36, 1.00, '2026-07-10', 'linked_to_batch', 1350.00, NULL, 0.00, NULL, NULL, '2026-07-06 18:09:43', '2026-07-06 18:09:43');

-- --------------------------------------------------------

--
-- Структура таблицы `preorder_intent_events`
--

CREATE TABLE `preorder_intent_events` (
  `id` bigint UNSIGNED NOT NULL,
  `preorder_intent_id` bigint UNSIGNED NOT NULL,
  `event_type` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `from_status` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `to_status` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `meta_json` json DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `preorder_intent_events`
--

INSERT INTO `preorder_intent_events` (`id`, `preorder_intent_id`, `event_type`, `from_status`, `to_status`, `meta_json`, `created_at`) VALUES
(1, 1, 'intent_created', NULL, 'intent_created', '{\"requested_boxes\": 2}', '2026-05-14 22:45:09'),
(2, 2, 'intent_created', NULL, 'intent_created', '{\"requested_boxes\": 1}', '2026-05-15 06:11:35'),
(3, 1, 'intent_updated', NULL, 'intent_created', '{\"requested_boxes\": 1}', '2026-05-15 16:30:04'),
(4, 2, 'intent_updated', NULL, 'intent_created', '{\"requested_boxes\": 1}', '2026-05-15 16:30:15'),
(5, 3, 'intent_created', NULL, 'intent_created', '{\"requested_boxes\": 1}', '2026-05-16 15:58:17'),
(6, 3, 'intent_updated', NULL, 'intent_created', '{\"requested_boxes\": 1}', '2026-05-16 15:58:22'),
(7, 2, 'intent_updated', NULL, 'intent_created', '{\"requested_boxes\": 1}', '2026-05-16 22:00:43'),
(8, 2, 'intent_updated', NULL, 'intent_created', '{\"requested_boxes\": 1}', '2026-05-16 22:24:48'),
(9, 4, 'intent_created', NULL, 'intent_created', '{\"requested_boxes\": 1}', '2026-05-17 19:31:20'),
(10, 5, 'intent_created', NULL, 'intent_created', '{\"source_section\": \"in_stock\", \"requested_boxes\": 1, \"eta_delivery_date\": \"2026-05-09\", \"source_delivery_date\": \"2026-05-07\"}', '2026-05-18 15:19:30'),
(11, 6, 'intent_created', NULL, 'intent_created', '{\"source_section\": \"\", \"requested_boxes\": 1, \"eta_delivery_date\": null, \"source_delivery_date\": \"2026-05-10\"}', '2026-05-18 15:20:16'),
(12, 2, 'auto_cancel_unconfirmed', 'intent_created', 'declined', NULL, '2026-05-18 15:23:42'),
(13, 1, 'auto_cancel_unconfirmed', 'intent_created', 'declined', NULL, '2026-05-18 15:23:42'),
(14, 5, 'intent_updated', NULL, 'intent_created', '{\"source_section\": \"\", \"requested_boxes\": 1, \"eta_delivery_date\": null, \"source_delivery_date\": \"2026-05-07\"}', '2026-05-18 15:54:00'),
(15, 5, 'intent_updated', NULL, 'intent_created', '{\"source_section\": \"in_stock\", \"requested_boxes\": 1, \"eta_delivery_date\": \"2026-05-09\", \"source_delivery_date\": \"2026-05-07\"}', '2026-05-19 05:02:52'),
(16, 5, 'intent_updated', NULL, 'intent_created', '{\"source_section\": \"in_stock\", \"requested_boxes\": 1, \"eta_delivery_date\": \"2026-05-24\", \"source_delivery_date\": \"2026-05-22\"}', '2026-05-19 08:13:01'),
(17, 5, 'intent_updated', NULL, 'intent_created', '{\"source_section\": \"other\", \"requested_boxes\": 1, \"eta_delivery_date\": null, \"source_delivery_date\": \"2026-05-19\"}', '2026-05-19 14:46:04'),
(18, 4, 'intent_updated', NULL, 'intent_created', '{\"source_section\": \"other\", \"requested_boxes\": 1, \"eta_delivery_date\": null, \"source_delivery_date\": \"2026-05-18\"}', '2026-05-19 14:46:08'),
(19, 6, 'intent_updated', NULL, 'intent_created', '{\"source_section\": \"other\", \"requested_boxes\": 1, \"eta_delivery_date\": null, \"source_delivery_date\": \"2026-05-14\"}', '2026-05-19 14:46:10'),
(20, 6, 'intent_updated', NULL, 'intent_created', '{\"source_section\": \"other\", \"requested_boxes\": 1, \"eta_delivery_date\": null, \"source_delivery_date\": \"2026-05-14\"}', '2026-05-20 05:24:12'),
(21, 4, 'intent_updated', NULL, 'intent_created', '{\"source_section\": \"other\", \"requested_boxes\": 1, \"eta_delivery_date\": null, \"source_delivery_date\": \"2026-05-18\"}', '2026-05-20 05:24:20'),
(22, 5, 'intent_updated', NULL, 'intent_created', '{\"source_section\": \"other\", \"requested_boxes\": 1, \"eta_delivery_date\": null, \"source_delivery_date\": \"2026-05-19\"}', '2026-05-20 05:24:28'),
(23, 4, 'auto_cancel_unconfirmed', 'intent_created', 'declined', NULL, '2026-05-20 05:32:51'),
(24, 3, 'auto_cancel_unconfirmed', 'intent_created', 'declined', NULL, '2026-05-20 05:32:51'),
(25, 5, 'intent_updated', NULL, 'intent_created', '{\"source_section\": \"in_stock\", \"requested_boxes\": 1, \"eta_delivery_date\": \"2026-05-21\", \"source_delivery_date\": \"2026-05-19\"}', '2026-05-20 10:32:08'),
(26, 5, 'auto_cancel_unconfirmed', 'intent_created', 'declined', NULL, '2026-05-22 08:48:38'),
(27, 6, 'auto_cancel_unconfirmed', 'intent_created', 'declined', NULL, '2026-05-22 08:48:38'),
(28, 7, 'intent_created', NULL, 'intent_created', '{\"source_section\": \"other\", \"requested_boxes\": 1, \"eta_delivery_date\": null, \"source_delivery_date\": \"2026-05-18\"}', '2026-05-22 16:37:02'),
(29, 8, 'intent_created', NULL, 'intent_created', '{\"source_section\": \"preorder\", \"requested_boxes\": 1, \"eta_delivery_date\": null, \"source_delivery_date\": \"2026-05-24\"}', '2026-05-22 21:06:36'),
(30, 8, 'intent_updated', NULL, 'intent_created', '{\"source_section\": \"preorder\", \"requested_boxes\": 2, \"eta_delivery_date\": null, \"source_delivery_date\": \"2026-05-24\"}', '2026-05-24 17:10:34'),
(31, 7, 'auto_cancel_unconfirmed', 'intent_created', 'declined', NULL, '2026-05-24 17:11:58'),
(32, 8, 'intent_updated', NULL, 'intent_created', '{\"source_section\": \"in_stock\", \"requested_boxes\": 1, \"eta_delivery_date\": \"2026-05-20\", \"source_delivery_date\": \"2026-05-18\", \"desired_delivery_date\": \"2026-05-25\", \"expected_price_per_box\": 1620, \"discount_percent_snapshot\": 10}', '2026-05-25 07:11:44'),
(33, 8, 'intent_updated', NULL, 'intent_created', '{\"source_section\": \"in_stock\", \"requested_boxes\": 1, \"eta_delivery_date\": \"2026-05-20\", \"source_delivery_date\": \"2026-05-18\", \"desired_delivery_date\": \"2026-05-25\", \"expected_price_per_box\": 1080, \"discount_percent_snapshot\": 10}', '2026-05-25 18:08:32'),
(34, 9, 'intent_created', NULL, 'intent_created', '{\"status\": \"offer_sent\", \"source_section\": \"in_stock\", \"requested_boxes\": 1, \"planned_batch_id\": 15, \"eta_delivery_date\": \"2026-05-28\", \"source_delivery_date\": \"2026-05-26\", \"desired_delivery_date\": \"2026-05-27\", \"expected_price_per_box\": 1040, \"discount_percent_snapshot\": 10}', '2026-05-27 11:22:56'),
(35, 9, 'offer_confirmed', 'offer_sent', 'confirmed', NULL, '2026-05-27 17:10:27'),
(36, 10, 'intent_created', NULL, 'intent_created', '{\"status\": \"linked_to_batch\", \"source_section\": \"in_stock\", \"requested_boxes\": 1, \"planned_batch_id\": 23, \"eta_delivery_date\": \"2026-05-30\", \"source_delivery_date\": \"2026-05-28\", \"desired_delivery_date\": \"2026-05-30\", \"expected_price_per_box\": 1080, \"discount_percent_snapshot\": 10}', '2026-05-28 13:44:33'),
(37, 9, 'manager_declined', 'confirmed', 'declined', NULL, '2026-05-29 13:02:46'),
(38, 8, 'manager_declined', 'confirmed', 'declined', NULL, '2026-05-29 13:02:50'),
(39, 10, 'manager_declined', 'linked_to_batch', 'declined', NULL, '2026-05-29 13:02:53'),
(40, 11, 'intent_created', NULL, 'intent_created', '{\"status\": \"linked_to_batch\", \"source_section\": \"\", \"requested_boxes\": 1, \"planned_batch_id\": 25, \"eta_delivery_date\": null, \"source_delivery_date\": \"\", \"desired_delivery_date\": \"any\", \"expected_price_per_box\": null, \"discount_percent_snapshot\": 0}', '2026-06-02 14:32:36'),
(41, 12, 'intent_created', NULL, 'intent_created', '{\"status\": \"linked_to_batch\", \"source_section\": \"in_stock\", \"requested_boxes\": 1, \"planned_batch_id\": 25, \"eta_delivery_date\": \"2026-06-02\", \"source_delivery_date\": \"2026-05-31\", \"desired_delivery_date\": \"any\", \"expected_price_per_box\": 1350, \"discount_percent_snapshot\": 10}', '2026-06-03 10:16:32'),
(42, 12, 'intent_updated', NULL, 'intent_created', '{\"status\": \"linked_to_batch\", \"source_section\": \"in_stock\", \"requested_boxes\": 1, \"planned_batch_id\": 25, \"eta_delivery_date\": \"2026-06-02\", \"source_delivery_date\": \"2026-05-31\", \"desired_delivery_date\": \"any\", \"expected_price_per_box\": 1350, \"discount_percent_snapshot\": 10}', '2026-06-03 10:16:32'),
(43, 12, 'intent_updated', NULL, 'intent_created', '{\"status\": \"linked_to_batch\", \"source_section\": \"preorder\", \"requested_boxes\": 1, \"planned_batch_id\": 25, \"eta_delivery_date\": null, \"source_delivery_date\": \"\", \"desired_delivery_date\": \"2026-06-04\", \"expected_price_per_box\": 1350, \"discount_percent_snapshot\": 10}', '2026-06-03 10:19:29'),
(44, 12, 'intent_updated', NULL, 'intent_created', '{\"status\": \"linked_to_batch\", \"source_section\": \"in_stock\", \"requested_boxes\": 1, \"planned_batch_id\": 25, \"eta_delivery_date\": \"2026-06-02\", \"source_delivery_date\": \"2026-05-31\", \"desired_delivery_date\": \"2026-06-02\", \"expected_price_per_box\": 1350, \"discount_percent_snapshot\": 10}', '2026-06-03 10:40:59'),
(45, 13, 'intent_created', NULL, 'intent_created', '{\"status\": \"linked_to_batch\", \"source_section\": \"preorder\", \"requested_boxes\": 1, \"planned_batch_id\": 27, \"eta_delivery_date\": null, \"source_delivery_date\": \"\", \"desired_delivery_date\": \"2026-06-05\", \"expected_price_per_box\": 1620, \"discount_percent_snapshot\": 10}', '2026-06-03 10:53:44'),
(46, 13, 'manager_confirmed', 'linked_to_batch', 'confirmed', NULL, '2026-06-03 10:54:45'),
(47, 12, 'intent_updated', NULL, 'intent_created', '{\"status\": \"linked_to_batch\", \"source_section\": \"preorder\", \"requested_boxes\": 17, \"planned_batch_id\": 25, \"eta_delivery_date\": null, \"source_delivery_date\": \"\", \"desired_delivery_date\": \"2026-06-05\", \"expected_price_per_box\": 1350, \"discount_percent_snapshot\": 10}', '2026-06-03 11:02:21'),
(48, 12, 'manager_declined', 'linked_to_batch', 'declined', NULL, '2026-06-04 15:47:35'),
(49, 11, 'manager_declined', 'linked_to_batch', 'declined', NULL, '2026-06-04 15:47:41'),
(50, 13, 'manager_declined', 'confirmed', 'declined', NULL, '2026-06-04 15:47:45'),
(51, 14, 'intent_created', NULL, 'intent_created', '{\"status\": \"linked_to_batch\", \"source_section\": \"preorder\", \"requested_boxes\": 1, \"planned_batch_id\": 30, \"eta_delivery_date\": null, \"source_delivery_date\": \"\", \"desired_delivery_date\": \"any\", \"expected_price_per_box\": 1210, \"discount_percent_snapshot\": 10}', '2026-06-06 09:28:11'),
(52, 15, 'intent_created', NULL, 'intent_created', '{\"status\": \"linked_to_batch\", \"source_section\": \"preorder\", \"requested_boxes\": 1, \"planned_batch_id\": 28, \"eta_delivery_date\": null, \"source_delivery_date\": \"\", \"desired_delivery_date\": \"2026-06-07\", \"expected_price_per_box\": 1620, \"discount_percent_snapshot\": 10}', '2026-06-07 16:56:41'),
(53, 14, 'manager_declined', 'linked_to_batch', 'declined', NULL, '2026-06-07 16:57:27'),
(54, 15, 'manager_declined', 'linked_to_batch', 'declined', NULL, '2026-06-07 16:57:32'),
(55, 16, 'intent_created', NULL, 'intent_created', '{\"status\": \"linked_to_batch\", \"source_section\": \"preorder\", \"requested_boxes\": 1, \"planned_batch_id\": 26, \"eta_delivery_date\": null, \"source_delivery_date\": \"2026-06-06\", \"desired_delivery_date\": \"2026-06-07\", \"expected_price_per_box\": 1350, \"discount_percent_snapshot\": 10}', '2026-06-08 06:05:05'),
(56, 17, 'intent_created', NULL, 'intent_created', '{\"status\": \"linked_to_batch\", \"source_section\": \"in_stock\", \"requested_boxes\": 1, \"planned_batch_id\": 30, \"eta_delivery_date\": \"2026-06-08\", \"source_delivery_date\": \"2026-06-06\", \"desired_delivery_date\": \"2026-06-19\", \"expected_price_per_box\": 1350, \"discount_percent_snapshot\": 10}', '2026-06-08 20:30:12'),
(57, 17, 'manager_declined', 'linked_to_batch', 'declined', NULL, '2026-06-08 20:33:05'),
(58, 16, 'manager_declined', 'linked_to_batch', 'declined', NULL, '2026-06-08 20:33:11'),
(59, 18, 'intent_created', NULL, 'intent_created', '{\"status\": \"linked_to_batch\", \"source_section\": \"preorder\", \"requested_boxes\": 1, \"planned_batch_id\": 29, \"eta_delivery_date\": null, \"source_delivery_date\": \"\", \"desired_delivery_date\": \"2026-06-11\", \"expected_price_per_box\": 2020, \"discount_percent_snapshot\": 10}', '2026-06-09 11:27:47'),
(60, 18, 'price_confirmation_requested', 'linked_to_batch', 'awaiting_price_confirmation', '{\"offer_expires_at\": \"2026-06-12 13:57:27\", \"purchase_batch_id\": 29, \"price_delta_per_box\": -400, \"desired_delivery_date\": \"2026-06-11\", \"offered_price_per_box\": 1620, \"covered_delivery_dates\": [\"2026-06-10\", \"2026-06-11\", \"2026-06-12\"], \"expected_price_per_box\": 2020, \"discount_percent_snapshot\": 10}', '2026-06-10 13:57:27'),
(61, 18, 'manager_declined', 'awaiting_price_confirmation', 'declined', NULL, '2026-06-11 15:10:49'),
(62, 19, 'intent_created', NULL, 'intent_created', '{\"status\": \"waiting_batch\", \"source_section\": \"\", \"requested_boxes\": 1, \"planned_batch_id\": null, \"eta_delivery_date\": null, \"source_delivery_date\": \"\", \"desired_delivery_date\": \"2026-07-05\", \"expected_price_per_box\": null, \"discount_percent_snapshot\": 0}', '2026-07-03 13:35:41'),
(63, 19, 'intent_updated', NULL, 'intent_created', '{\"status\": \"waiting_batch\", \"source_section\": \"\", \"requested_boxes\": 1, \"planned_batch_id\": null, \"eta_delivery_date\": null, \"source_delivery_date\": \"\", \"desired_delivery_date\": \"2026-07-05\", \"expected_price_per_box\": null, \"discount_percent_snapshot\": 0}', '2026-07-03 13:35:43'),
(64, 20, 'intent_created', NULL, 'intent_created', '{\"status\": \"linked_to_batch\", \"source_section\": \"\", \"requested_boxes\": 1, \"planned_batch_id\": 37, \"eta_delivery_date\": null, \"source_delivery_date\": \"\", \"desired_delivery_date\": \"2026-07-05\", \"expected_price_per_box\": null, \"discount_percent_snapshot\": 0}', '2026-07-03 13:40:17'),
(65, 20, 'intent_updated', NULL, 'intent_created', '{\"status\": \"linked_to_batch\", \"source_section\": \"\", \"requested_boxes\": 1, \"planned_batch_id\": 37, \"eta_delivery_date\": null, \"source_delivery_date\": \"\", \"desired_delivery_date\": \"2026-07-05\", \"expected_price_per_box\": null, \"discount_percent_snapshot\": 0}', '2026-07-03 13:40:19'),
(66, 19, 'manager_confirmed', 'waiting_batch', 'confirmed', NULL, '2026-07-04 15:18:53'),
(67, 20, 'manager_confirmed', 'linked_to_batch', 'confirmed', NULL, '2026-07-04 15:19:05'),
(68, 19, 'manager_declined', 'confirmed', 'declined', NULL, '2026-07-06 13:50:25'),
(69, 20, 'manager_declined', 'confirmed', 'declined', NULL, '2026-07-06 13:50:29'),
(70, 21, 'intent_created', NULL, 'intent_created', '{\"status\": \"linked_to_batch\", \"source_section\": \"\", \"requested_boxes\": 1, \"planned_batch_id\": 36, \"eta_delivery_date\": null, \"source_delivery_date\": \"\", \"desired_delivery_date\": \"2026-07-10\", \"expected_price_per_box\": null, \"discount_percent_snapshot\": 0}', '2026-07-06 18:09:43');

-- --------------------------------------------------------

--
-- Структура таблицы `production_executor_settings`
--

CREATE TABLE `production_executor_settings` (
  `user_id` int UNSIGNED NOT NULL,
  `executor_type` enum('internal_staff','production_partner','marketplace_seller','brand_partner') NOT NULL DEFAULT 'internal_staff',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `can_work_on_site` tinyint(1) NOT NULL DEFAULT '1',
  `can_work_remote` tinyint(1) NOT NULL DEFAULT '0',
  `current_mode` enum('on_shift','remote_available','offline','paused') NOT NULL DEFAULT 'offline',
  `default_fulfillment_model` enum('by_berrygo_on_site','by_berrygo_remote','by_partner_under_berrygo_brand','by_seller','by_berrygo_from_seller_stock') NOT NULL DEFAULT 'by_berrygo_on_site',
  `default_bonus_percent` decimal(5,2) NOT NULL DEFAULT '10.00',
  `default_bonus_amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `max_active_jobs` int UNSIGNED NOT NULL DEFAULT '1',
  `notes` text,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Дамп данных таблицы `production_executor_settings`
--

INSERT INTO `production_executor_settings` (`user_id`, `executor_type`, `is_active`, `can_work_on_site`, `can_work_remote`, `current_mode`, `default_fulfillment_model`, `default_bonus_percent`, `default_bonus_amount`, `max_active_jobs`, `notes`, `created_at`, `updated_at`) VALUES
(296, 'brand_partner', 1, 1, 1, 'remote_available', 'by_partner_under_berrygo_brand', 10.00, 0.00, 10, 'Производство клубники в шоколаде Berry Me Please.', '2026-07-07 05:35:10', '2026-07-07 05:35:10');

-- --------------------------------------------------------

--
-- Структура таблицы `production_jobs`
--

CREATE TABLE `production_jobs` (
  `id` int UNSIGNED NOT NULL,
  `order_id` int UNSIGNED NOT NULL,
  `product_id` int UNSIGNED DEFAULT NULL,
  `executor_type` enum('internal_staff','production_partner','marketplace_seller','brand_partner') DEFAULT NULL,
  `executor_id` int UNSIGNED DEFAULT NULL,
  `fulfillment_model` enum('by_berrygo_on_site','by_berrygo_remote','by_partner_under_berrygo_brand','by_seller','by_berrygo_from_seller_stock') NOT NULL DEFAULT 'by_berrygo_on_site',
  `production_location` enum('shop','remote','partner','seller') NOT NULL DEFAULT 'shop',
  `status` enum('new','assigned','materials_pending','materials_sent','materials_received','in_progress','photo_uploaded','approved','ready_for_handover','handed_over','completed','cancelled','problem') NOT NULL DEFAULT 'new',
  `production_deadline` datetime DEFAULT NULL,
  `handover_deadline` datetime DEFAULT NULL,
  `bonus_type` enum('salary','internal_bonus','fixed_payout','commission','subscription','commission_plus_subscription','fixed_fee_per_order') NOT NULL DEFAULT 'internal_bonus',
  `bonus_value` decimal(10,2) NOT NULL DEFAULT '0.00',
  `bonus_amount_locked` decimal(10,2) NOT NULL DEFAULT '0.00',
  `materials_required` json DEFAULT NULL,
  `materials_delivery_required` tinyint(1) NOT NULL DEFAULT '0',
  `materials_delivery_cost` decimal(10,2) NOT NULL DEFAULT '0.00',
  `result_delivery_required` tinyint(1) NOT NULL DEFAULT '0',
  `result_delivery_cost` decimal(10,2) NOT NULL DEFAULT '0.00',
  `manager_comment` text,
  `assigned_at` datetime DEFAULT NULL,
  `started_at` datetime DEFAULT NULL,
  `photo_uploaded_at` datetime DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `handed_over_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `estimated_materials_cost` decimal(10,2) NOT NULL DEFAULT '0.00',
  `estimated_acquiring_cost` decimal(10,2) NOT NULL DEFAULT '0.00',
  `estimated_margin_amount` decimal(10,2) DEFAULT NULL,
  `minimum_margin_amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `margin_status` varchar(32) NOT NULL DEFAULT 'unknown'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `production_job_events`
--

CREATE TABLE `production_job_events` (
  `id` int UNSIGNED NOT NULL,
  `job_id` int UNSIGNED NOT NULL,
  `order_id` int UNSIGNED NOT NULL,
  `from_status` varchar(32) DEFAULT NULL,
  `to_status` varchar(32) NOT NULL,
  `changed_by_user_id` int UNSIGNED DEFAULT NULL,
  `changed_by_role` varchar(32) DEFAULT NULL,
  `comment` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `production_job_photos`
--

CREATE TABLE `production_job_photos` (
  `id` int UNSIGNED NOT NULL,
  `job_id` int UNSIGNED NOT NULL,
  `order_id` int UNSIGNED NOT NULL,
  `image_path` varchar(255) NOT NULL,
  `photo_type` enum('ready','packaging','handover') NOT NULL DEFAULT 'ready',
  `review_status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `reviewed_by_user_id` int UNSIGNED DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `review_comment` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `production_specs`
--

CREATE TABLE `production_specs` (
  `id` int UNSIGNED NOT NULL,
  `product_id` int UNSIGNED DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `reference_image_path` varchar(255) DEFAULT NULL,
  `berry_count` int UNSIGNED DEFAULT NULL,
  `berry_size` varchar(100) DEFAULT NULL,
  `chocolate_type` varchar(100) DEFAULT NULL,
  `decor` text,
  `packaging` text,
  `ribbon_color` varchar(100) DEFAULT NULL,
  `postcard_rules` text,
  `production_minutes` int UNSIGNED NOT NULL DEFAULT '120',
  `storage_conditions` text,
  `photo_instruction` text,
  `handover_instruction` text,
  `allowed_replacements` text,
  `forbidden_replacements` text,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Дамп данных таблицы `production_specs`
--

INSERT INTO `production_specs` (`id`, `product_id`, `title`, `reference_image_path`, `berry_count`, `berry_size`, `chocolate_type`, `decor`, `packaging`, `ribbon_color`, `postcard_rules`, `production_minutes`, `storage_conditions`, `photo_instruction`, `handover_instruction`, `allowed_replacements`, `forbidden_replacements`, `created_at`, `updated_at`) VALUES
(1, 32, 'Berry Me Please · Мини-комплимент 9 ягод', '/assets/img/chocolate-strawberry-placeholder.svg', 9, 'средняя/крупная ягода без повреждений', 'молочный/белый шоколад по стандарту карточки', 'шоколадный декор по карточке товара', 'компактная подарочная коробка Berry Me Please', 'по стандарту карточки товара', 'текст открытки строго из заказа, без исправлений от исполнителя', 120, 'хранить при +2…+6 °C, не перегревать, передавать курьеру сразу после готовности', 'фото сверху, крупный план ягод, фото упаковки, фото открытки при наличии', 'передать курьеру в подарочной упаковке, без повреждения декора и цветов', 'замены только после согласования с менеджером', 'нельзя менять количество ягод, упаковку, цветовую концепцию и текст открытки без согласования', '2026-06-22 09:21:15', '2026-07-06 14:44:49'),
(2, 33, 'Berry Me Please · Нежный бокс 12 ягод', '/assets/img/chocolate-strawberry-placeholder.svg', 12, 'средняя/крупная ягода без повреждений', 'молочный/белый шоколад по стандарту карточки', 'шоколадный декор по карточке товара', 'подарочный бокс Berry Me Please', 'по стандарту карточки товара', 'текст открытки строго из заказа, без исправлений от исполнителя', 120, 'хранить при +2…+6 °C, не перегревать, передавать курьеру сразу после готовности', 'фото сверху, крупный план ягод, фото упаковки, фото открытки при наличии', 'передать курьеру в подарочной упаковке, без повреждения декора и цветов', 'замены только после согласования с менеджером', 'нельзя менять количество ягод, упаковку, цветовую концепцию и текст открытки без согласования', '2026-06-22 09:21:15', '2026-07-06 14:44:49'),
(3, 34, 'Berry Me Please · Signature 16 ягод', '/assets/img/chocolate-strawberry-placeholder.svg', 16, 'средняя/крупная ягода без повреждений', 'молочный/белый шоколад по стандарту карточки', 'шоколадный декор по карточке товара', 'фирменная подарочная упаковка Berry Me Please', 'по стандарту карточки товара', 'текст открытки строго из заказа, без исправлений от исполнителя', 150, 'хранить при +2…+6 °C, не перегревать, передавать курьеру сразу после готовности', 'фото сверху, крупный план ягод, фото упаковки, фото открытки при наличии', 'передать курьеру в подарочной упаковке, без повреждения декора и цветов', 'замены только после согласования с менеджером', 'нельзя менять количество ягод, упаковку, цветовую концепцию и текст открытки без согласования', '2026-06-22 09:21:15', '2026-07-06 14:44:49'),
(4, 35, 'Berry Me Please · Шоколадный букет 20 ягод', '/assets/img/chocolate-strawberry-placeholder.svg', 20, 'средняя/крупная ягода без повреждений', 'молочный/белый шоколад по стандарту карточки', 'шоколадный декор по карточке товара', 'букетная подарочная упаковка Berry Me Please', 'по стандарту карточки товара', 'текст открытки строго из заказа, без исправлений от исполнителя', 180, 'хранить при +2…+6 °C, не перегревать, передавать курьеру сразу после готовности', 'фото сверху, крупный план ягод, фото упаковки, фото открытки при наличии', 'передать курьеру в подарочной упаковке, без повреждения декора и цветов', 'замены только после согласования с менеджером', 'нельзя менять количество ягод, упаковку, цветовую концепцию и текст открытки без согласования', '2026-06-22 09:21:15', '2026-07-06 14:44:49'),
(5, 36, 'Berry Me Please · 20 клубник + 5 роз', '/assets/img/chocolate-strawberry-placeholder.svg', 20, 'средняя/крупная ягода без повреждений', 'молочный/белый шоколад по стандарту карточки', 'шоколадный декор, 5 роз, оформление по стандарту Berry Me Please', 'подарочная упаковка для клубники и роз Berry Me Please', 'по стандарту карточки товара', 'текст открытки строго из заказа, без исправлений от исполнителя', 180, 'хранить при +2…+6 °C, не перегревать, передавать курьеру сразу после готовности', 'фото сверху, крупный план ягод, фото упаковки, фото открытки при наличии', 'передать курьеру в подарочной упаковке, без повреждения декора и цветов', 'замены только после согласования с менеджером', 'нельзя менять количество ягод, упаковку, цветовую концепцию и текст открытки без согласования', '2026-06-22 09:21:15', '2026-07-06 14:44:49'),
(6, 37, 'Berry Me Please · Luxe Box', '/assets/img/chocolate-strawberry-placeholder.svg', 25, 'средняя/крупная ягода без повреждений', 'молочный/белый шоколад по стандарту карточки', 'премиальный декор Luxe Box по стандарту Berry Me Please', 'премиальная коробка Luxe Box Berry Me Please', 'по стандарту карточки товара', 'текст открытки строго из заказа, без исправлений от исполнителя', 150, 'хранить при +2…+6 °C, не перегревать, передавать курьеру сразу после готовности', 'фото сверху, крупный план ягод, фото упаковки, фото открытки при наличии', 'передать курьеру в подарочной упаковке, без повреждения декора и цветов', 'замены только после согласования с менеджером', 'нельзя менять количество ягод, упаковку, цветовую концепцию и текст открытки без согласования', '2026-06-22 09:21:15', '2026-07-06 14:44:49');

-- --------------------------------------------------------

--
-- Структура таблицы `products`
--

CREATE TABLE `products` (
  `id` int UNSIGNED NOT NULL,
  `variety` varchar(100) NOT NULL,
  `origin_country` varchar(100) NOT NULL,
  `unit` enum('л','кг','шт','набор') NOT NULL DEFAULT 'кг',
  `price` decimal(8,0) NOT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `product_type_id` int UNSIGNED NOT NULL COMMENT 'Тип продукта',
  `seller_id` int UNSIGNED DEFAULT NULL,
  `alias` varchar(255) NOT NULL,
  `box_size` decimal(8,0) NOT NULL DEFAULT '0' COMMENT 'Размер одного ящика',
  `box_unit` enum('кг','л','шт','набор') NOT NULL DEFAULT 'кг' COMMENT 'Единица размера ящика',
  `description` text COMMENT 'Краткое описание',
  `full_description` text,
  `composition` text,
  `meta_title` varchar(255) DEFAULT NULL,
  `meta_description` varchar(255) DEFAULT NULL,
  `meta_keywords` varchar(255) DEFAULT NULL,
  `manufacturer` varchar(100) DEFAULT NULL COMMENT 'Производитель',
  `delivery_date` date DEFAULT NULL COMMENT 'Дата следующей поставки',
  `sale_price` decimal(10,0) NOT NULL DEFAULT '0' COMMENT 'Акционная цена (0 = без акции)',
  `is_active` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'Флаг активности товара (1 = активно, 0 = скрыто/непродаётся)',
  `in_sitemap` tinyint(1) NOT NULL DEFAULT '1',
  `current_purchase_batch_id` int UNSIGNED DEFAULT NULL,
  `free_stock_boxes` decimal(10,2) NOT NULL DEFAULT '0.00',
  `reserved_stock_boxes` decimal(10,2) NOT NULL DEFAULT '0.00',
  `discount_stock_boxes` decimal(10,2) NOT NULL DEFAULT '0.00',
  `sold_stock_boxes` decimal(10,2) NOT NULL DEFAULT '0.00',
  `written_off_stock_boxes` decimal(10,2) NOT NULL DEFAULT '0.00',
  `preorder_price_per_box` decimal(10,2) NOT NULL DEFAULT '0.00',
  `instant_price_per_box` decimal(10,2) NOT NULL DEFAULT '0.00',
  `discount_price_per_box` decimal(10,2) NOT NULL DEFAULT '0.00',
  `preorder_unit_price` decimal(10,2) NOT NULL DEFAULT '0.00',
  `instant_unit_price` decimal(10,2) NOT NULL DEFAULT '0.00',
  `discount_unit_price` decimal(10,2) NOT NULL DEFAULT '0.00',
  `stock_status` enum('in_stock','preorder','arriving_today','sold_out','hidden') NOT NULL DEFAULT 'sold_out',
  `requires_production` tinyint(1) NOT NULL DEFAULT '0',
  `production_spec_id` int UNSIGNED DEFAULT NULL,
  `default_fulfillment_model` varchar(64) NOT NULL DEFAULT 'by_berrygo_on_site',
  `default_production_minutes` int UNSIGNED NOT NULL DEFAULT '120',
  `default_executor_bonus_percent` decimal(5,2) NOT NULL DEFAULT '10.00',
  `default_executor_bonus_amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `default_materials_cost` decimal(10,2) NOT NULL DEFAULT '0.00',
  `minimum_production_margin` decimal(10,2) NOT NULL DEFAULT '500.00'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Дамп данных таблицы `products`
--

INSERT INTO `products` (`id`, `variety`, `origin_country`, `unit`, `price`, `image_path`, `product_type_id`, `seller_id`, `alias`, `box_size`, `box_unit`, `description`, `full_description`, `composition`, `meta_title`, `meta_description`, `meta_keywords`, `manufacturer`, `delivery_date`, `sale_price`, `is_active`, `in_sitemap`, `current_purchase_batch_id`, `free_stock_boxes`, `reserved_stock_boxes`, `discount_stock_boxes`, `sold_stock_boxes`, `written_off_stock_boxes`, `preorder_price_per_box`, `instant_price_per_box`, `discount_price_per_box`, `preorder_unit_price`, `instant_unit_price`, `discount_unit_price`, `stock_status`, `requires_production`, `production_spec_id`, `default_fulfillment_model`, `default_production_minutes`, `default_executor_bonus_percent`, `default_executor_bonus_amount`, `default_materials_cost`, `minimum_production_margin`) VALUES
(6, 'Teмно-бордовая (premium)', '', 'кг', 900, '/uploads/prod_6a22e79b737979.20644318.webp', 2, NULL, 'bordo-premium', 2, 'кг', 'Черешня Premium из Киргизии – высший сорт для истинных ценителей', 'Черешня Premium из Киргизии – высший сорт для истинных ценителей!\r\nBerryGO предлагает черешню премиум-класса в ящиках по 2 кг с прямыми поставками из Киргизии. Это отборные ягоды крупного калибра, одинаково зрелые, плотные и невероятно сладкие. Идеальная форма, насыщенный тёмно-красный цвет и характерный аромат делают эту черешню настоящим лакомством для гурманов.\r\n\r\nPremium-сегмент — это особый стандарт: ягоды проходят ручную сортировку и доставляются в максимально короткие сроки, чтобы сохранить свежесть, вкус и внешний вид. Подходит для праздничного стола, подарков или просто — чтобы порадовать себя лучшим.\r\n\r\nЗаказывайте черешню премиум 2 кг с доставкой по Красноярску — BerryGO привозит только лучшее прямо с южных плантаций Киргизии.', '[\"Черешня (premium) 2кг\"]', 'Черешня Premium из Киргизии 2 кг – элитная ягода | Доставка в Красноярске', 'Премиальная черешня из Киргизии в ящиках по 2 кг. Крупные, сладкие ягоды отборного качества. Доставка по Красноярску от BerryGO. Успейте в сезон!', 'черешня премиум, черешня из Киргизии, купить черешню Красноярск, черешня 2 кг, элитные ягоды, свежая черешня доставка, BerryGO фрукты', 'Киргизия', NULL, 0, 1, 1, 28, 1.00, 0.00, 0.00, 1.00, 1.00, 1620.00, 1800.00, 1300.00, 810.00, 900.00, 650.00, 'in_stock', 0, NULL, 'by_berrygo_on_site', 120, 10.00, 0.00, 0.00, 500.00),
(7, 'Клери', '', 'кг', 1400, '/uploads/prod_6a22e7173e8b58.18396861.webp', 1, NULL, 'kleri-2kg', 2, 'кг', 'Сладкая клубника Клери 2 кг с доставкой по Красноярску. Яркая, ароматная, свежая и очень вкусная.', 'Клубника Клери 2 кг — отличный выбор для тех, кто хочет сладкой, красивой и ароматной ягоды к столу. У этого сорта яркий вкус, приятная сладость и аккуратные плотные ягоды, которые приятно есть просто так, добавлять в десерты или подавать гостям.\r\n\r\nКлери любят за сочность, красивый внешний вид и тот самый летний аромат, из-за которого хочется открыть коробку сразу после доставки. Такая фасовка удобна для дома: клубники достаточно, чтобы наесться в удовольствие, угостить близких и оставить немного на завтрак, десерт или заморозку.\r\n\r\nМы получаем клубнику по прямым поставкам из Киргизии, а после поступления храним её в помещении с холодильным температурным режимом. Благодаря этому ягода приезжает свежей, аккуратной и по-настоящему вкусной.\r\n\r\nВ berryGo приятно заказывать снова: для покупателей действуют скидки, акции и кешбэк баллами за свои покупки и приглашённых друзей. Если хочется купить клубнику Клери 2 кг в Красноярске с доставкой, это очень удачный вариант.', '[\"Клубника Клери 2 кг\"]', 'Клубника Клери 2 кг в Красноярске с доставкой — купить свежую клубнику | berryGo', 'Купить клубнику Клери 2 кг в Красноярске с доставкой. Сладкая, свежая и ароматная ягода из Киргизии, холодильное хранение, акции и кешбэк баллами.', 'клубника Клери 2 кг Красноярск, купить клубнику Клери Красноярск, свежая клубника Красноярск, клубника с доставкой Красноярск, клубника из Киргизии', 'Киргизия', NULL, 0, 1, 1, 39, 7.00, 0.00, 0.00, 15.00, 10.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'in_stock', 0, NULL, 'by_berrygo_on_site', 120, 10.00, 0.00, 0.00, 500.00),
(9, 'Альбион', '', 'кг', 750, '/uploads/prod_684eb83d537462.75819782.webp', 1, NULL, 'albion', 2, 'кг', 'Свежая клубника Альбион 2 кг с доставкой по Красноярску. Вкусная ягода для дома, десертов и летнего настроения.', 'Клубника Альбион 2 кг — хороший вариант для тех, кто любит свежую ягоду к столу и хочет заказать клубнику в Красноярске с доставкой без лишней суеты. Эта фасовка удобна для дома, семьи и десертов.\r\n\r\nЯгода поступает по прямым поставкам и после получения хранится в помещении с холодильным температурным режимом. Так клубника дольше сохраняет свежесть, вкус и аккуратный вид.\r\n\r\nВ berryGo можно не только купить вкусную клубнику, но и получить дополнительные преимущества: скидки, акции и кешбэк баллами за свои покупки и приглашённых друзей. Альбион — приятный вариант для тех, кто хочет просто вкусной клубники к столу.', '[\"Клубника Альбион 2 кг\"]', 'Клубника Альбион 2 кг в Красноярске с доставкой | berryGo', 'Купить клубнику Альбион 2 кг в Красноярске с доставкой. Свежая ягода, прямые поставки, хранение в холодильном режиме, акции и кешбэк.', 'клубника Альбион 2 кг Красноярск, купить клубнику Альбион Красноярск, свежая клубника Красноярск', 'Киргизия', '2026-03-29', 0, 0, 1, NULL, 0.00, 0.00, 0.00, 0.00, 0.00, 1500.00, 1500.00, 1500.00, 750.00, 750.00, 750.00, 'sold_out', 0, NULL, 'by_berrygo_on_site', 120, 10.00, 0.00, 0.00, 500.00),
(10, 'Виктория 10л', '', 'кг', 660, '/uploads/prod_6859296b0088d4.73853727.webp', 1, NULL, 'victoria-10l', 5, 'кг', 'Саяногорская ягода, собираем заказы, ящики 10л', 'Клубника Виктория – свежий сбор под заказ из Субботино!\r\nBerryGO принимает предварительные заказы на ароматную клубнику сорта Виктория с местных хозяйств Субботино. Мы собираем ягоду под ваш заказ — без хранения и лишних перекладок. Только что с грядки – в ящиках объёмом 10 литров.\r\n\r\nКлубника Виктория отличается ярким насыщенным вкусом, сочной мякотью и характерным летним ароматом. Идеальна для свежего употребления, приготовления варенья, пирогов или заморозки. Ягоды собираются вручную утром перед отгрузкой, что гарантирует максимальную свежесть.\r\n\r\nСбор по заявкам — успейте оформить заказ!', '[\"Клубника Виктория 10л (5кг)\"]', 'Клубника Виктория под заказ из Субботино – ящик 10 л | BerryGO Красноярск', 'Собираем предварительные заказы на клубнику Виктория из Субботино. Свежий сбор под заказ, ящики по 10 л, доставка по Красноярску от BerryGO.', 'клубника Виктория, клубника под заказ, клубника Субботино, свежая клубника Красноярск, клубника 10 л, купить клубнику, BerryGO доставка', 'Субботино', NULL, 0, 0, 0, NULL, 0.00, 0.00, 0.00, 0.00, 0.00, 3300.00, 3300.00, 3300.00, 660.00, 660.00, 660.00, 'sold_out', 0, NULL, 'by_berrygo_on_site', 120, 10.00, 0.00, 0.00, 500.00),
(11, 'Шалах', '', 'кг', 750, '/uploads/prod_687c9ec2607f91.61695381.webp', 3, NULL, 'shalah-3kg', 2, 'кг', 'Абрикос Шалах – сладкий привет из солнечной Киргизии!', 'Абрикос Шалах – сладкий привет из солнечной Киргизии!\r\nBerryGO доставляет свежие абрикосы сорта Шалах в ящиках по 2 кг прямо к вам домой в Красноярске. Эти крупные, медовые плоды с нежной мякотью и ярким ароматом — настоящее летнее лакомство. Шалах отличается натуральной сладостью, тонкой кожицей и минимумом кислоты. Идеален для свежего употребления, варенья, сушки или десертов.\r\n\r\nПоставки напрямую из Киргизии — без лишних посредников. Мы отбираем только зрелые и целые плоды, чтобы вы получали максимум вкуса и пользы.\r\n\r\nЗаказывайте абрикосы Шалах в ящиках 3 кг с быстрой доставкой от BerryGO — почувствуйте вкус настоящего лета!', '[\"Абрикос Шалах 2кг\"]', 'Абрикос Шалах из Киргизии – ящик 3 кг | Доставка в Красноярске – BerryGO', 'Сладкий абрикос Шалах из Киргизии в ящиках по 3 кг. Свежая поставка, отборные плоды. Закажите с доставкой по Красноярску от BerryGO!', 'абрикос Шалах, абрикос Киргизия, купить абрикос Красноярск, доставка фруктов, абрикос 3 кг, BerryGO фрукты, свежие абрикосы', 'Киргизия', NULL, 0, 1, 1, 41, 10.00, 0.00, 0.00, 0.00, 0.00, 680.00, 860.00, 860.00, 430.00, 430.00, 430.00, 'in_stock', 0, NULL, 'by_berrygo_on_site', 120, 10.00, 0.00, 0.00, 500.00),
(12, 'Килич', '', 'кг', 300, '/uploads/prod_68575bcd8afd67.79590416.webp', 3, NULL, 'kilich', 4, 'кг', 'Абрикос Килич – сочный вкус лета в каждом плоде!', 'Абрикос Килич – сочный вкус лета в каждом плоде!\r\nBerryGO доставляет свежие и ароматные абрикосы сорта Килич по Красноярску — прямо с плантаций на ваш стол. Крупные, сладкие и мясистые фрукты с бархатистой кожицей идеально подойдут для свежего перекуса, варенья или десертов. Килич отличается насыщенным вкусом и высокой сахаристостью — это один из лучших сортов для гурманов и ценителей натуральных продуктов.\r\n\r\nМы отбираем только спелые и качественные плоды, чтобы вы получали максимум пользы и вкуса. Заказывайте абрикосы с быстрой доставкой по Красноярску — BerryGO гарантирует свежесть и заботу в каждом заказе.\r\n\r\nBerryGO – ваш источник лучших ягод и фруктов в Красноярске!', '[\"Абрикос Килич 4кг\"]', 'Абрикос Килич 4кг – купить с доставкой в Красноярске | BerryGO', 'Свежий абрикос Килич 4кг с доставкой по Красноярску. Крупные, сочные плоды из Киргизии. Заказывайте отборные фрукты в BerryGO – вкус лета у вас дома!', 'абрикос Килич, абрикос 4кг, купить абрикос Красноярск, абрикос Киргизия, доставка фруктов, BerryGO фрукты, свежие абрикосы', 'Узбекистан', '2025-06-21', 0, 0, 1, NULL, 0.00, 0.00, 0.00, 0.00, 0.00, 1200.00, 1200.00, 1200.00, 300.00, 300.00, 300.00, 'sold_out', 0, NULL, 'by_berrygo_on_site', 120, 10.00, 0.00, 0.00, 500.00),
(13, 'Шалах', '', 'кг', 210, '/uploads/prod_68575bc395ce30.22656232.webp', 3, NULL, 'shalah-8kg', 8, 'кг', 'Ящики 8кг — больше объём, выгоднее цена!', 'Абрикос Шалах 8 кг — больше объём, выгоднее цена!\r\nСвежий и ароматный абрикос сорта Шалах из солнечной Киргизии теперь в больших ящиках по 8 кг — идеально для семьи, заготовок и тех, кто любит вкусное и полезное. Плоды крупные, сладкие, с тонкой кожицей и сочной мякотью. Шалах известен своей медовой мягкостью и почти полным отсутствием кислоты.\r\n\r\nЭтот объём — отличная возможность сэкономить: цена за килограмм ниже, а качество остаётся на высоте. Абрикосы отлично подходят для еды в свежем виде, варенья, компотов и сушки.\r\n\r\nДоставка по Красноярску от BerryGO — быстро, удобно и с гарантией свежести. Успейте заказать, пока сезон в разгаре!', '[\"Абрикос Шалах 8кг\"]', 'Абрикос Шалах 8 кг из Киргизии – оптом дешевле | BerryGO Красноярск', 'Крупный ящик абрикосов Шалах 8 кг из Киргизии — выгодно и вкусно! Свежие плоды с доставкой по Красноярску от BerryGO. Сезонные фрукты оптом.', 'абрикос Шалах 8 кг, купить абрикос оптом, абрикос из Киргизии, дешево абрикос Красноярск, BerryGO доставка, сезонные фрукты, свежие абрикосы', 'Киргизия', '2025-06-25', 0, 1, 1, 33, 0.00, 0.00, 0.00, 0.00, 0.00, 1680.00, 1680.00, 1680.00, 210.00, 210.00, 210.00, 'preorder', 0, NULL, 'by_berrygo_on_site', 120, 10.00, 0.00, 0.00, 500.00),
(14, 'Мурано', '', 'кг', 720, NULL, 1, NULL, 'murano-2kg', 2, 'кг', 'Свежая клубника Мурано 2 кг с доставкой по Красноярску. Вкусная сезонная ягода для дома и любимых десертов.', 'Клубника Мурано 2 кг — удобный вариант, когда хочется заказать свежую ягоду домой, к столу или для десертов. Такая фасовка подходит для семьи и для тех, кто любит покупать клубнику в меру, чтобы съесть её свежей и с удовольствием.\r\n\r\nПосле поступления ягода хранится в помещении с холодильным температурным режимом, поэтому до доставки сохраняет свежесть и аккуратный внешний вид.\r\n\r\nВ berryGo действуют скидки, акции и кешбэк баллами за свои покупки и приглашённых друзей. Если хочется вкусной сезонной клубники в Красноярске с доставкой, Мурано тоже можно смело рассматривать.', '[\"Клубника Мурано 2 кг\"]', 'Клубника Мурано 2 кг в Красноярске с доставкой | berryGo', 'Купить клубнику Мурано 2 кг в Красноярске с доставкой. Свежая ягода, хранение в холодильном режиме, акции, скидки и кешбэк баллами.', 'клубника Мурано 2 кг Красноярск, купить клубнику Мурано Красноярск, клубника с доставкой Красноярск', 'Азербайджан', NULL, 0, 0, 1, NULL, 0.00, 0.00, 0.00, 0.00, 0.00, 1440.00, 1440.00, 1440.00, 720.00, 720.00, 720.00, 'sold_out', 0, NULL, 'by_berrygo_on_site', 120, 10.00, 0.00, 0.00, 500.00),
(15, 'Белая', '', 'кг', 750, '/uploads/prod_685bb8159c2244.86063307.webp', 2, NULL, 'beaya', 2, 'кг', 'Белая черешня – нежный вкус лета!', 'Белая черешня – нежный вкус лета!\r\nBerryGO доставляет свежую белую черешню в ящиках по 2 кг по Красноярску. Это особенный сорт с янтарно-жёлтыми ягодами, тонкой кожицей и выраженной сладостью без кислоты. Белая черешня особенно ценится за мягкий вкус и высокое содержание сахаров — идеальна для детей, диетического питания и тех, кто предпочитает деликатные фрукты.\r\n\r\nЯгоды отбираются вручную, доставляются быстро и аккуратно, чтобы вы могли насладиться натуральной свежестью. Отлично подходит для употребления в свежем виде, а также для варенья, сушки или заморозки.\r\n\r\nЗаказывайте белую черешню 2 кг с доставкой по Красноярску — сезонное удовольствие от BerryGO, пока не закончилось!', '[\"Черешня белая 2кг\"]', 'Белая черешня 2 кг – сладкая и нежная | Доставка по Красноярску – BerryGO', 'Свежая белая черешня в ящиках по 2 кг. Сладкий вкус без кислоты, быстрая доставка по Красноярску от BerryGO. Успей купить в сезон!', 'белая черешня, черешня купить Красноярск, сладкая черешня, черешня 2 кг, доставка ягод, BerryGO фрукты, сезонная черешня', 'Азербайджан', '2025-06-25', 0, 0, 1, NULL, 0.00, 0.00, 0.00, 0.00, 0.00, 1500.00, 1500.00, 1500.00, 750.00, 750.00, 750.00, 'sold_out', 0, NULL, 'by_berrygo_on_site', 120, 10.00, 0.00, 0.00, 500.00),
(16, 'Розовая', '', 'кг', 750, '/uploads/prod_685bb8406929e6.52300705.webp', 2, NULL, 'rozovaya', 2, 'кг', 'Розовая черешня – нежность, сладость и аромат в каждой ягоде!', 'Розовая черешня – нежность, сладость и аромат в каждой ягоде!\r\nBerryGO предлагает свежую розовую черешню в ящиках по 2 кг с доставкой по Красноярску. Этот сорт отличается особым медовым вкусом, мягкой мякотью и тонкой кожицей. Ягоды светло-розового цвета с лёгким румянцем выглядят особенно аппетитно и подойдут как для свежего употребления, так и для десертов, варенья и компотов.\r\n\r\nРозовая черешня — идеальный выбор для тех, кто любит мягкий, сбалансированный вкус без излишней кислоты. Благодаря быстрой доставке от BerryGO, вы получаете максимально свежую продукцию — прямо с южных садов на ваш стол.\r\n\r\nЗаказывайте розовую черешню 2 кг с доставкой по Красноярску — насладитесь настоящим вкусом лета!', '[\"Черешня розовая 2кг\"]', 'Розовая черешня 2 кг – свежие ягоды | Доставка по Красноярску – BerryGO', 'Сочная розовая черешня в ящиках по 2 кг. Нежный вкус, отборные ягоды и быстрая доставка по Красноярску от BerryGO. Успей заказать в сезон!', 'розовая черешня, черешня купить Красноярск, свежая черешня, черешня 2 кг, доставка ягод, BerryGO фрукты, сезонная черешня', 'Азербайджан', '2025-06-25', 0, 0, 1, NULL, 0.00, 0.00, 0.00, 0.00, 0.00, 1500.00, 1500.00, 1500.00, 750.00, 750.00, 750.00, 'sold_out', 0, NULL, 'by_berrygo_on_site', 120, 10.00, 0.00, 0.00, 500.00),
(17, 'Сладкий', '', 'кг', 450, '/uploads/prod_685da442e1f625.35942460.webp', 5, NULL, 'sladkiy', 6, 'кг', 'Спелые, сочные. Ящик 5-7кг', 'Нектарин из Азербайджана – сочный и спелый, как лето!\r\nBerryGO предлагает свежие, ароматные нектарины в больших ящиках по 6 кг с прямыми поставками из Азербайджана. Эти фрукты отличаются насыщенным сладким вкусом, плотной и сочной мякотью, а также гладкой кожицей без пуха — идеальны для свежего перекуса, фруктовых салатов, десертов и заготовок.\r\n\r\nАзербайджанские нектарины славятся своим качеством: плоды крупные, зрелые, с ярким ароматом и ярко выраженным вкусом. Упаковка 6 кг — удобный формат для семьи, праздника или домашней переработки.\r\n\r\nОформляйте заказ на нектарины 6 кг с доставкой по Красноярску от BerryGO — сезонные фрукты на вашем столе уже сегодня!', '[\"Нектарин 6кг\"]', 'Нектарин 6 кг из Азербайджана – доставка по Красноярску | BerryGO', 'Сочные нектарины из Азербайджана в ящиках по 6 кг. Прямые поставки, отличный вкус и доставка по Красноярску от BerryGO. Заказывайте в сезон!', 'нектарин Азербайджан, купить нектарин Красноярск, нектарин 6 кг, свежие фрукты доставка, BerryGO фрукты, сезонный нектарин, крупные нектарины', 'Азербайджан', NULL, 0, 0, 1, NULL, 0.00, 0.00, 0.00, 0.00, 0.00, 2700.00, 2700.00, 2700.00, 450.00, 450.00, 450.00, 'sold_out', 0, NULL, 'by_berrygo_on_site', 120, 10.00, 0.00, 0.00, 500.00),
(18, '', '', 'кг', 750, '/uploads/prod_687fa6122a63c6.55400521.webp', 7, NULL, 'malina-2kg', 2, 'кг', 'berryGo предлагает отборную малина из Киргизии в ящиках по 2 кг — ягоды насыщенного рубинового цвета, с ярким ароматом и сладко-кислым вкусом. Доставка по Красноярску за 1–2 ч.', 'Откройте для себя вкус настоящей малины из Киргизии от berryGo! Наши ягоды выращены на южных плантациях с идеальным климатом, что обеспечивает яркий аромат и сбалансированную сладость. Каждая малина проходит строгий отбор на стадии сбора: выбираются крупные, упругие ягоды без повреждений. Удобная фасовка в прочные ящики по 2 кг сохраняет форму и сочность даже при транспортировке.', '[\"Малина 2кг\"]', 'Малина из Киргизии в ящиках 2 кг — купить в Красноярске | berryGo', 'Отборная малина из Киргизии в ящиках 2 кг: сладкая, ароматная, свежая. Доставка за 1–2 ч по Красноярску. Закажите онлайн в Telegram-боте или на сайте berryGo', 'малина из Киргизии, малина 2 кг, купить малину в Красноярске, малина в ящиках, свежая малина, доставка малины, berryGo', 'Киргизия', NULL, 0, 0, 1, NULL, 0.00, 0.00, 0.00, 0.00, 0.00, 1500.00, 1500.00, 1500.00, 750.00, 750.00, 750.00, 'sold_out', 0, NULL, 'by_berrygo_on_site', 120, 10.00, 0.00, 0.00, 500.00),
(19, '', '', 'кг', 1800, '/uploads/prod_687c9fb83d9183.48949594.webp', 6, NULL, 'ejevika-2kg', 2, 'кг', 'кусная ежевика из Киргизии в ящиках по 2 кг — крупные ягоды с тонкой кожицей и насыщенным вкусом. Закажите доставку по Красноярску за 1–2 ч через Telegram-бот или веб-приложение berryGo', 'Погрузитесь в мир истинной свежести с ежевикой из Киргизии от berryGo! Наши ягоды растут под тёплым солнцем, что придаёт им насыщенный, слегка кислый вкус и плотную структуру. Мы тщательно отбираем каждую ежевинку на этапе сбора: в ящики по 2 кг попадают только целые, упругие ягоды без признаков повреждений или переспелости.', '[\"Ежевика 2кг\"]', 'Ежевика из Киргизии в ящиках 2 кг — купить в Красноярске | berryGo', 'Сочная ежевика из Киргизии в ящиках по 2 кг: плотная, ароматная, витаминизированная. Доставка за 1–2 ч по Красноярску. Закажите онлайн через berryGo', 'ежевика из Киргизии, ежевика 2 кг, купить ежевику в Красноярске, ежевика в ящиках, свежая ежевика, доставка ежевики, berryGo', 'Киргизия', NULL, 0, 1, 1, 32, 2.00, 0.00, 0.00, 0.00, 0.00, 1500.00, 1200.00, 1200.00, 600.00, 600.00, 600.00, 'in_stock', 0, NULL, 'by_berrygo_on_site', 120, 10.00, 0.00, 0.00, 500.00),
(20, 'Черный принц', '', 'кг', 1450, '/uploads/prod_6a22e77de557f2.70254073.webp', 1, NULL, 'cherniy-princ', 2, 'кг', 'Сладкая и ароматная клубника Черный принц 2 кг с доставкой по Красноярску. Ягода для тех, кто любит насыщенный вкус.', 'Клубника Черный принц 2 кг понравится тем, кто любит ягоду с ярким ароматом и насыщенным сладким вкусом. Это тот вариант, который хочется есть сразу из коробки: клубника сочная, аппетитная и очень летняя по настроению.\r\n\r\nФасовка 2 кг удобна для дома, семьи и уютного стола. Можно заказать клубнику к чаю, на завтрак, для десертов или просто чтобы порадовать себя любимой ягодой без лишнего объёма.\r\n\r\nМы получаем клубнику по прямым поставкам из Киргизии и храним её в помещении с холодильным температурным режимом. Это помогает сохранить вкус, свежесть и аккуратный внешний вид до момента доставки.\r\n\r\nВ berryGo можно не только купить вкусную клубнику в Красноярске, но и получить приятные бонусы: скидки, акции и кешбэк баллами за свои покупки и приглашённых друзей. Если хочется ароматной клубники с доставкой, Черный принц 2 кг — очень хороший выбор.', '[\"Клубника Черный принц 2 кг\"]', 'Клубника Черный принц 2 кг в Красноярске с доставкой | berryGo', 'Купить клубнику Черный принц 2 кг в Красноярске с доставкой. Сладкая, ароматная и свежая ягода, прямые поставки из Киргизии, акции и кешбэк.', 'клубника Черный принц 2 кг Красноярск, купить клубнику Черный принц Красноярск, сладкая клубника Красноярск, ароматная клубника Красноярск', 'Киргизия', NULL, 0, 1, 1, 38, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'preorder', 0, NULL, 'by_berrygo_on_site', 120, 10.00, 0.00, 0.00, 500.00),
(21, 'для кондитеров', '', 'кг', 2000, '/uploads/prod_687fa73fe20ab0.92713518.webp', 1, NULL, 'klubnika-dlya-konditerov', 1, 'кг', '', '', '[\"Клубника Клери 1кг\"]', '', '', '', '', NULL, 1500, 0, 1, NULL, 0.00, 0.00, 0.00, 0.00, 0.00, 1500.00, 1500.00, 1500.00, 1500.00, 1500.00, 1500.00, 'sold_out', 0, NULL, 'by_berrygo_on_site', 120, 10.00, 0.00, 0.00, 500.00),
(22, 'Черная', '', 'кг', 550, '/uploads/prod_688b266440e6e1.81144444.webp', 8, NULL, 'smorodina-chernaya', 2, 'кг', '', '', '[\"Черная смородина - 2кг\"]', 'Купить черную смородину (Киргизия) с доставкой в Красноярске', 'Свежая черная смородина из Киргизии для заготовок или не еду.', '', 'Киргизия', NULL, 0, 0, 1, NULL, 0.00, 0.00, 0.00, 0.00, 0.00, 1100.00, 1100.00, 1100.00, 550.00, 550.00, 550.00, 'sold_out', 0, NULL, 'by_berrygo_on_site', 120, 10.00, 0.00, 0.00, 500.00),
(23, 'Красная', '', 'кг', 550, '/uploads/prod_68857b22a0c5a7.51489789.webp', 8, NULL, 'smorodina-krasnaya', 2, 'кг', '', '', '[\"Красная смородина - 2кг\"]', 'Купить красную смородину (Киргизия) в Красноярске', 'Свежая красная смородина из киргизии для заготовок и на еду', '', 'Киргизия', NULL, 0, 0, 1, NULL, 0.00, 0.00, 0.00, 0.00, 0.00, 1100.00, 1100.00, 1100.00, 550.00, 550.00, 550.00, 'sold_out', 0, NULL, 'by_berrygo_on_site', 120, 10.00, 0.00, 0.00, 500.00),
(24, 'свежий', '', 'кг', 687, '/uploads/prod_688b2788666304.18934904.webp', 10, NULL, 'svezhiy', 2, 'кг', '', '', '[\"Крыжовник - 2кг\"]', 'Купить крыжовник (Киргизия) с доставкой в Красноярске', 'Свежий крыжовник из Киргизии для заготовок или не еду.', '', '', NULL, 0, 0, 1, NULL, 0.00, 0.00, 0.00, 0.00, 0.00, 1374.00, 1374.00, 1374.00, 687.00, 687.00, 687.00, 'sold_out', 0, NULL, 'by_berrygo_on_site', 120, 10.00, 0.00, 0.00, 500.00),
(26, 'Красная', '', 'кг', 550, '/uploads/prod_688b270fbcfed8.94811669.webp', 11, NULL, 'vishnya-krasnaya', 2, 'кг', '', '', '[\"Вишня 2кг\"]', '', '', '', '', NULL, 0, 0, 1, NULL, 0.00, 0.00, 0.00, 0.00, 0.00, 1100.00, 1100.00, 1100.00, 550.00, 550.00, 550.00, 'sold_out', 0, NULL, 'by_berrygo_on_site', 120, 10.00, 0.00, 0.00, 500.00),
(27, 'йцуйцу', '', 'кг', 500, NULL, 11, 108, 'vishnya', 1, 'кг', '', '', NULL, 'asdasda', 'asdasd', '', 'Узбекистан', '2025-08-21', 0, 0, 0, NULL, 0.00, 0.00, 0.00, 0.00, 0.00, 500.00, 500.00, 500.00, 500.00, 500.00, 500.00, 'sold_out', 0, NULL, 'by_berrygo_on_site', 120, 10.00, 0.00, 0.00, 500.00),
(28, 'Розы Rhodos 50см Кения', '', 'кг', 150, '/uploads/prod_68f45cf0dfce51.10219607.webp', 12, 108, 'red-roses', 1, 'кг', '', 'Плотные бутоны насыщенно-красного оттенка Rhodos — премиальные кенийские розы длиной 50 см. Соберём монобукет или композицию под ваш бюджет, открытка бесплатно. Доставим по Красноярску в день заказа.', '[\"роза 50см\"]', 'Розы Rhodos 50 см (Кения) — купить букет с доставкой по Красноярску | партнеры berryGo', 'Кенийские розы Rhodos, длина стебля 50 см: плотный бутон, насыщенно-красный цвет. Соберём букет под ваш бюджет, открытка бесплатно. Доставка по Красноярску в день заказа', 'розы Rhodos 50 см, кенийские розы, красные розы Красноярск, купить букет роз, доставка цветов Красноярск', 'Кения', NULL, 99, 0, 1, NULL, 0.00, 0.00, 0.00, 0.00, 0.00, 99.00, 99.00, 99.00, 99.00, 99.00, 99.00, 'sold_out', 0, NULL, 'by_berrygo_on_site', 120, 10.00, 0.00, 0.00, 500.00),
(29, 'Клери', '', 'кг', 550, NULL, 1, NULL, 'kleri-3kg', 3, 'кг', 'Клубника Клери 3 кг с доставкой по Красноярску. Сладкая, яркая и свежая ягода, которой хватит всей семье.', 'Клубника Клери 3 кг — отличный вариант, когда хочется побольше сладкой и красивой ягоды. Такая фасовка хорошо подходит для семьи, гостей, праздничного стола, десертов и всех случаев, когда одной небольшой коробки уже мало.\r\n\r\nУ Клери приятный сладкий вкус, яркий аромат и аппетитный внешний вид. Эту клубнику приятно подать к столу, добавить в десерты или просто есть в удовольствие, пока длится сезон.\r\n\r\nМы получаем клубнику по прямым поставкам из Киргизии и храним её в помещении с холодильным температурным режимом. Поэтому ягода приезжает свежей, аккуратной и вкусной.\r\n\r\nВ berryGo действуют скидки, акции и кешбэк баллами за свои покупки и приглашённых друзей. Если хотите купить клубнику 3 кг в Красноярске с доставкой и получить действительно вкусную ягоду, Клери — очень удачный выбор.', '[\"Клубника Клери 3 кг\"]', 'Клубника Клери 3 кг в Красноярске с доставкой — купить свежую клубнику | berryGo', 'Купить клубнику Клери 3 кг в Красноярске с доставкой. Сладкая свежая ягода из Киргизии, хранение в холодильном режиме, акции и кешбэк баллами.', 'клубника Клери 3 кг Красноярск, купить клубнику 3 кг Красноярск, клубника Клери с доставкой Красноярск, свежая клубника Красноярск', 'Киргизия', NULL, 0, 0, 1, NULL, 0.00, 0.00, 0.00, 0.00, 0.00, 1650.00, 1650.00, 1650.00, 550.00, 550.00, 550.00, 'sold_out', 0, NULL, 'by_berrygo_on_site', 120, 10.00, 0.00, 0.00, 500.00),
(30, 'Черный принц', '', 'кг', 600, NULL, 1, NULL, 'cherniy-princ-3kg', 3, 'кг', 'Клубника Черный принц 3 кг с доставкой по Красноярску. Сладкая, ароматная и сочная ягода для дома, гостей и удовольствия.', 'Клубника Черный принц 3 кг — это вариант для тех, кто хочет вдоволь насладиться вкусной ягодой. Её удобно брать для семьи, гостей, десертов, завтраков и просто на те дни, когда дома хочется побольше сладкой клубники.\r\n\r\nУ этого сорта яркий аромат, насыщенный вкус и приятная сладость. Такая клубника хорошо подходит и для красивой подачи, и для домашнего удовольствия без всякой спешки.\r\n\r\nМы получаем ягоду по прямым поставкам из Киргизии, а после поступления храним её в помещении с холодильным температурным режимом. Благодаря этому клубника сохраняет свежесть и хороший вкус до доставки.\r\n\r\nВ berryGo приятно покупать снова: у нас бывают скидки и акции, а ещё действует кешбэк баллами за свои покупки и приглашённых друзей. Если хочется заказать ароматную клубнику 3 кг в Красноярске с доставкой, Черный принц — очень вкусный вариант.', '[\"Клубника Черный принц 3 кг\"]', 'Клубника Черный принц 3 кг в Красноярске с доставкой | berryGo', 'Купить клубнику Черный принц 3 кг в Красноярске с доставкой. Сладкая ароматная ягода из Киргизии, холодильное хранение, скидки, акции и кешбэк.', 'клубника Черный принц 3 кг Красноярск, купить клубнику 3 кг Красноярск, сладкая клубника Красноярск, ароматная клубника Красноярск', 'Киргизия', NULL, 0, 0, 1, NULL, 0.00, 0.00, 0.00, 0.00, 0.00, 1800.00, 1800.00, 1800.00, 600.00, 600.00, 600.00, 'sold_out', 0, NULL, 'by_berrygo_on_site', 120, 10.00, 0.00, 0.00, 500.00),
(31, 'Полька', '', 'кг', 1500, '/uploads/prod_6a27eb056ad8e0.88498341.webp', 7, NULL, 'polka', 2, 'кг', '', '', '[\"Малина Полька 2кг\"]', 'Малина Полька из Киргизии 2кг  купить в Красноярске | berryGo', 'Вскусная спелая сочная малина с доставкой в Красноярска. Малина из Киргизии, в ящиках по 2кг.', '', '', NULL, 0, 1, 1, 35, 0.00, 1.00, 0.00, 1.00, 6.00, 1350.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'preorder', 0, NULL, 'by_berrygo_on_site', 120, 10.00, 0.00, 0.00, 500.00),
(32, 'Мини-комплимент 9 ягод', 'RU', 'набор', 1590, '/assets/img/chocolate-strawberry-placeholder.svg', 13, 296, 'shokoladnaya-klubnika-9-yagod', 1, 'набор', 'Мини-набор из 9 ягод клубники в шоколаде. Небольшой сладкий подарок без повода.', 'Мини-комплимент из свежей клубники в шоколаде от Berry Me Please. Готовим под заказ, аккуратно упаковываем и перед отправкой можем прислать фото.', '9 ягод клубники, шоколад, декор, подарочная упаковка.', 'Клубника в шоколаде 9 ягод — Berry Me Please', 'Мини-набор из 9 ягод клубники в шоколаде от Berry Me Please с доставкой по Красноярску.', 'клубника в шоколаде 9 ягод, мини набор клубники в шоколаде', 'Berry Me Please', NULL, 0, 1, 1, NULL, 0.00, 0.00, 0.00, 0.00, 0.00, 1590.00, 1590.00, 0.00, 1590.00, 1590.00, 0.00, 'sold_out', 1, 1, 'by_partner_under_berrygo_brand', 120, 10.00, 300.00, 700.00, 450.00),
(33, 'Нежный бокс 12 ягод', 'RU', 'набор', 2190, '/assets/img/chocolate-strawberry-placeholder.svg', 13, 296, 'shokoladnaya-klubnika-12-yagod', 1, 'набор', 'Подарочный бокс из 12 ягод клубники в шоколаде. Аккуратный формат для комплимента, свидания или благодарности.', 'Нежный бокс из 12 ягод клубники в шоколаде. Хороший формат для небольшого подарка, поздравления, свидания или комплимента.', '12 ягод клубники, шоколад, декор, подарочная упаковка.', 'Клубника в шоколаде 12 ягод — Berry Me Please', 'Подарочный бокс из 12 ягод клубники в шоколаде от Berry Me Please.', 'клубника в шоколаде 12 ягод, бокс клубники в шоколаде', 'Berry Me Please', NULL, 0, 1, 1, NULL, 0.00, 0.00, 0.00, 0.00, 0.00, 2190.00, 2190.00, 0.00, 2190.00, 2190.00, 0.00, 'sold_out', 1, 2, 'by_partner_under_berrygo_brand', 120, 10.00, 400.00, 950.00, 600.00),
(34, 'Berry Me Signature 16 ягод', 'RU', 'набор', 2590, '/assets/img/chocolate-strawberry-placeholder.svg', 13, 296, 'shokoladnaya-klubnika-16-yagod', 1, 'набор', 'Фирменный набор Berry Me Please из 16 ягод клубники в шоколаде.', 'Berry Me Signature — фирменный набор из 16 ягод клубники в шоколаде. Свежая ягода, красивый шоколадный декор, подарочная упаковка и фото перед отправкой.', '16 ягод клубники, шоколад, декор, подарочная упаковка.', 'Клубника в шоколаде 16 ягод — Berry Me Please', 'Фирменный набор из 16 ягод клубники в шоколаде от Berry Me Please.', 'клубника в шоколаде 16 ягод, Berry Me Please', 'Berry Me Please', NULL, 0, 1, 1, NULL, 0.00, 0.00, 0.00, 0.00, 0.00, 2590.00, 2590.00, 0.00, 2590.00, 2590.00, 0.00, 'sold_out', 1, 3, 'by_partner_under_berrygo_brand', 150, 10.00, 500.00, 1150.00, 700.00),
(35, 'Шоколадный букет 20 ягод', 'RU', 'набор', 2890, '/assets/img/chocolate-strawberry-placeholder.svg', 13, 296, 'shokoladnaya-klubnika-20-yagod', 1, 'набор', 'Шоколадный букет из 20 ягод клубники в шоколаде в подарочной упаковке.', 'Шоколадный букет из 20 ягод клубники в шоколаде. Эффектный подарок с доставкой по Красноярску.', '20 ягод клубники, шоколад, декор, подарочная упаковка.', 'Клубника в шоколаде 20 ягод — Berry Me Please', 'Шоколадный букет из 20 ягод клубники в шоколаде с доставкой по Красноярску.', 'клубника в шоколаде 20 ягод, шоколадный букет', 'Berry Me Please', NULL, 0, 1, 1, NULL, 0.00, 0.00, 0.00, 0.00, 0.00, 2890.00, 2890.00, 0.00, 2890.00, 2890.00, 0.00, 'sold_out', 1, 4, 'by_partner_under_berrygo_brand', 180, 10.00, 700.00, 1350.00, 800.00),
(36, '20 клубник в шоколаде + 5 роз', 'RU', 'набор', 3990, '/assets/img/chocolate-strawberry-placeholder.svg', 13, 296, 'klubnika-v-shokolade-20-yagod-5-roz', 1, 'набор', 'Подарочный набор: 20 ягод клубники в шоколаде и 5 роз.', 'Подарочный набор Berry Me Please: 20 клубник в шоколаде и 5 роз. Подходит для романтического подарка, дня рождения или красивого сюрприза.', '20 ягод клубники в шоколаде, 5 роз, декор, подарочная упаковка.', 'Клубника в шоколаде с розами — Berry Me Please', 'Подарочный набор: 20 клубник в шоколаде и 5 роз от Berry Me Please.', 'клубника в шоколаде с розами, подарок девушке Красноярск', 'Berry Me Please', NULL, 0, 1, 1, NULL, 0.00, 0.00, 0.00, 0.00, 0.00, 3990.00, 3990.00, 0.00, 3990.00, 3990.00, 0.00, 'sold_out', 1, 5, 'by_partner_under_berrygo_brand', 180, 10.00, 700.00, 2200.00, 1000.00),
(37, 'Luxe Box', 'RU', 'набор', 5490, '/assets/img/chocolate-strawberry-placeholder.svg', 13, 296, 'luxe-box-klubnika-v-shokolade', 1, 'набор', 'Премиальный Luxe Box с клубникой в шоколаде, декором и подарочной упаковкой.', 'Luxe Box — премиальный подарочный набор клубники в шоколаде с расширенным декором и красивой упаковкой.', 'Клубника в шоколаде, премиальный декор, подарочная упаковка Luxe Box.', 'Luxe Box клубники в шоколаде — Berry Me Please', 'Премиальный Luxe Box клубники в шоколаде от Berry Me Please.', 'luxe box, премиальная клубника в шоколаде', 'Berry Me Please', NULL, 0, 1, 1, NULL, 0.00, 0.00, 0.00, 0.00, 0.00, 5490.00, 5490.00, 0.00, 5490.00, 5490.00, 0.00, 'sold_out', 1, 6, 'by_partner_under_berrygo_brand', 150, 10.00, 600.00, 2900.00, 1200.00);

-- --------------------------------------------------------

--
-- Структура таблицы `product_types`
--

CREATE TABLE `product_types` (
  `id` int UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `alias` varchar(255) NOT NULL,
  `meta_title` varchar(255) DEFAULT NULL,
  `meta_description` varchar(255) DEFAULT NULL,
  `meta_keywords` varchar(255) DEFAULT NULL,
  `h1` varchar(255) DEFAULT NULL,
  `short_description` text,
  `text` text,
  `seller_id` int UNSIGNED DEFAULT NULL,
  `in_sitemap` tinyint(1) NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Дамп данных таблицы `product_types`
--

INSERT INTO `product_types` (`id`, `name`, `alias`, `meta_title`, `meta_description`, `meta_keywords`, `h1`, `short_description`, `text`, `seller_id`, `in_sitemap`) VALUES
(1, 'Клубника', 'klubnika', 'Клубника в Красноярске с доставкой — Клери, Черный принц и другие сорта | berryGo', 'Купить свежую клубнику в Красноярске с доставкой: Клери, Черный принц и другие сорта в фасовках 2 и 3 кг. Прямые поставки из Киргизии, холодильное хранение, акции и кешбэк баллами.', 'клубника Красноярск, купить клубнику Красноярск, клубника с доставкой Красноярск, клубника 2 кг Красноярск, клубника 3 кг Красноярск, клубника Клери Красноярск, клубника Черный принц Красноярск', 'Свежая клубника с доставкой по Красноярску', 'Сладкая и свежая клубника в Красноярске: Клери, Черный принц и другие сорта в удобных фасовках 2 и 3 кг. Прямые поставки из Киргизии, хранение в холодильном режиме, скидки, акции и кешбэк баллами.', 'Если хочется вкусной, сладкой и свежей клубники, вы попали куда нужно. На этой странице собрана именно клубника: разные сорта и удобные фасовки, чтобы можно было выбрать ягоду под свой вкус и количество.\r\n\r\nУ нас можно заказать клубнику для дома, для семьи, к столу, для десертов и просто чтобы вдоволь наесться любимой ягодой в сезон. Есть варианты поменьше и побольше — удобно взять столько, сколько действительно нужно.\r\n\r\nОснову ассортимента составляет клубника с прямыми поставками из Киргизии. После поступления ягода хранится в помещении с холодильным температурным режимом, поэтому до доставки сохраняет свежесть, аромат и красивый вид.\r\n\r\nВ berryGo любят не только вкусную клубнику, но и честный сервис: у нас действуют скидки, акции и кешбэк баллами за свои покупки и приглашённых друзей. Если хотите заказать клубнику в Красноярске с доставкой, здесь собраны самые удобные варианты.', NULL, 1),
(2, 'Черешня', 'chereshnya', 'Черешня в Красноярске – белая, розовая, тёмная | Доставка от BerryGO', 'Купить черешню с доставкой в Красноярске: белая, розовая, тёмно-бордовая и премиум. Прямые поставки, отборные ягоды по 2 кг. Заказывайте в BerryGO!', 'черешня Красноярск, купить черешню, белая черешня, розовая черешня, тёмная черешня, премиум черешня, доставка ягод, BerryGO фрукты', 'Купить черешню с доставкой по Красноярску', 'Свежая черешня с поставками из Киргизии и местных хозяйств. Белая, розовая, тёмно-бордовая, премиум – в ящиках по 2 кг. Заказывайте черешню с быстрой доставкой по Красноярску от BerryGO', 'В каталоге BerryGO — свежая черешня с доставкой по Красноярску. Мы предлагаем отборные ягоды в ящиках по 2 кг: белую, розовую, насыщенную тёмно-бордовую и премиум-сорт. Поставки идут напрямую из Киргизии и проверенных хозяйств, чтобы вы получали свежий и сочный продукт без посредников.\r\n\r\nКаждая партия проходит ручной отбор — только спелые, плотные и сладкие ягоды. Черешня отлично подходит для свежего употребления, десертов, заготовок и праздничного стола. Белая — нежная и сладкая, розовая — с медовым послевкусием, тёмная — насыщенная и мясистая.\r\n\r\nСезон короткий — заказывайте черешню онлайн в BerryGO и получайте быструю доставку по Красноярску.', NULL, 1),
(3, 'Абрикос', 'abrikos', 'Абрикосы в Красноярске – купить Шалах и Килич | Доставка от BerryGO', 'Купить свежие абрикосы в Красноярске: сорта Шалах и Килич с прямыми поставками из Киргизии. Удобные ящики 3 и 8 кг. Доставка на дом от BerryGO.', 'абрикос Красноярск, купить абрикосы, абрикос Шалах, абрикос Килич, свежие абрикосы доставка, абрикос Киргизия, BerryGO фрукты', 'Купить абрикосы с доставкой по Красноярску', 'Сочные и сладкие абрикосы из Киргизии — сорта Шалах, Килич и другие. Прямые поставки, отличное качество, ящики 3 и 8 кг. Быстрая доставка по Красноярску от BerryGO.', 'Свежие абрикосы с доставкой по Красноярску от BerryGO — это вкус настоящего лета! В каталоге представлены популярные сорта, такие как Шалах и Килич, с прямыми поставками из Киргизии. Отборные плоды отличаются сладким вкусом, насыщенным ароматом и нежной мякотью.\r\n\r\nМы предлагаем абрикосы в ящиках по 3 и 8 кг — удобно как для домашнего потребления, так и для заготовок. Сорт Шалах идеально подойдёт для сушки и варенья, а Килич — для свежих перекусов и десертов. Все фрукты проходят ручную сортировку и доставляются в кратчайшие сроки, чтобы вы получили только самое свежее.\r\n\r\nЗаказывайте абрикосы онлайн с доставкой по Красноярску — BerryGO привозит фрукты, в которых действительно есть вкус!', NULL, 1),
(5, 'Нектарин', 'nektarin', 'Нектарины купить в Красноярске – доставка свежих фруктов | BerryGO', 'Сочные нектарины с доставкой по Красноярску. Прямые поставки, отличное качество и быстрый заказ на сайте BerryGO. Вкусное лето – у вас дома!', 'нектарины Красноярск, купить нектарин, доставка фруктов, свежие нектарины, сезонные фрукты, BerryGO фрукты, нектарин оптом', 'Купить нектарины с доставкой по Красноярску', 'Сочные и сладкие нектарины с прямыми поставками из Азербайджана и других регионов. Только отборные плоды в сезон. Удобные ящики, быстрый заказ и доставка по Красноярску от BerryGO.', 'Нектарины с доставкой по Красноярску – спелые, сочные, отборные!\r\nВ интернет-магазине BerryGO вы можете купить свежие нектарины с прямой доставкой на дом в Красноярске. Мы предлагаем отборные фрукты из надёжных хозяйств Азербайджана, Киргизии и других тёплых регионов. Нектарины поступают в продажу только в сезон — спелые, ароматные и сладкие.\r\n\r\nГладкая кожура, сочная мякоть и насыщенный вкус делают нектарин идеальным фруктом для перекуса, приготовления десертов, смузи и заготовок. Ящики разного объёма (от 2 до 6 кг) подойдут как для семьи, так и для оптовых закупок.\r\n\r\nBerryGO гарантирует свежесть каждого плода: быстрая логистика, аккуратная упаковка и ручной отбор перед отправкой.\r\n\r\nЗаказывайте нектарины онлайн с доставкой по Красноярску — почувствуйте вкус настоящего лета вместе с BerryGO!', NULL, 1),
(6, 'Ежевика', 'ejevika', 'Каталог ежевики: купить свежую ежевику в Красноярске — berryGo', 'Свежая ежевика из Субботино и Азербайджана фасовкой 0,5–2 кг с доставкой за 1–2 ч по Красноярску. Заказывайте онлайн через Telegram-бот и веб-приложение berryGo', 'ежевика, ежевика купить, ежевика Красноярск, купить ежевику в Красноярске, ежевика доставка, свежая ежевика, каталожная ежевика,', 'Каталог свежей ежевики', 'В каталоге berryGo — отборная ежевика из Киргизии. Быстрая доставка по Красноярску за 1–2 ч. Выбирайте онлайн и наслаждайтесь сочными ягодами премиум-качества!', 'Добро пожаловать в каталог свежей ежевики от berryGo! Мы предлагаем только лучшие ягоды: сочная ежевика из Киргизии с ярко-кислым вкусом. Каждая партия проходит строгий отбор и упаковывается в экологичные контейнеры, сохраняющие форму и сочность ягод. Премиум-качество, оперативность и вкус, который объединяет лето и пользу витаминов круглый год!', NULL, 1),
(7, 'Малина', 'malina', 'Каталог малины из Киргизии: купить свежую малину в Красноярске — berryGo', 'Свежая малина из Киргизии фасовкой 0,5–2 кг с доставкой за 1–2 ч по Красноярску. Заказывайте онлайн через Telegram-бот и веб-приложение berryGo', 'малина, малина купить, малина Красноярск, малина из Киргизии, купить малину в Красноярске, доставка малины, свежая малина', 'Каталог свежей малины из Киргизии', 'В каталоге berryGo — сладкая и ароматная малина прямой поставки из Киргизии, фасовка от 0,5 до 2 кг. Доставка за 1–2 ч по Красноярску или самовывоз с ул. 9 Мая, 73', 'Добро пожаловать в каталог малины из Киргизии от berryGo! Мы сотрудничаем с надёжными фермерами, которые выращивают лучшие сорта малины под южным солнцем — ягоды насыщенного рубинового цвета, с выраженным сладко-кислым вкусом и ярким ароматом. Внимательный отбор на стадии сбора и сушка на ветру обеспечивают идеальную плотность и минимальную ломкость ягод.', NULL, 1),
(8, 'Смородина', 'smorodina', '', '', '', '', '', '', NULL, 1),
(10, 'Крыжовник', 'krizhovnik', 'Свежий крыжовник в Красноярске', '', '', '', '', '', NULL, 1),
(11, 'Вишня', 'vishnya', '', '', '', '', '', '', NULL, 1),
(12, 'Цветы', 'flowers', 'Цветы с доставкой по Красноярску — свежие букеты | партнеры berryGo', 'Каталог цветов YagodGO: свежие букеты и композиции с доставкой по Красноярску в день заказа. Соберём по вашему бюджету, открытка в подарок. Заказывайте онлайн или в Telegram', 'цветы Красноярск, купить букет, доставка цветов, розы, пионы, тюльпаны, цветы с открыткой, цветы день рождения', 'Цветы с доставкой по Красноярску', '', 'Свежие букеты и цветочные композиции от YagodGO — собираем под ваш бюджет и повод, добавим открытку бесплатно. Доставляем по Красноярску в день заказа и аккуратно привозим к удобному времени.', 108, 1),
(13, 'Клубника в шоколаде', 'klubnika-v-shokolade', 'Клубника в шоколаде в Красноярске — Berry Me Please', 'Клубника в шоколаде Berry Me Please: подарочные боксы, букеты, наборы с розами и доставка по Красноярску.', 'клубника в шоколаде Красноярск, купить клубнику в шоколаде, Berry Me Please', 'Клубника в шоколаде Berry Me Please', 'Подарочные наборы клубники в шоколаде с доставкой по Красноярску.', 'Berry Me Please — мастерская клубники в шоколаде: свежая ягода, подарочные боксы, букеты, наборы с розами, фото перед отправкой и доставка по Красноярску.', 296, 1);

-- --------------------------------------------------------

--
-- Структура таблицы `purchase_batches`
--

CREATE TABLE `purchase_batches` (
  `id` int UNSIGNED NOT NULL,
  `product_id` int UNSIGNED NOT NULL,
  `buyer_user_id` int UNSIGNED DEFAULT NULL,
  `purchased_at` datetime DEFAULT NULL,
  `arrived_at` datetime DEFAULT NULL,
  `box_size_snapshot` decimal(10,2) NOT NULL DEFAULT '0.00',
  `box_unit_snapshot` enum('кг','л') NOT NULL DEFAULT 'кг',
  `boxes_total` decimal(10,2) NOT NULL DEFAULT '0.00',
  `boxes_reserved` decimal(10,2) NOT NULL DEFAULT '0.00',
  `boxes_free` decimal(10,2) NOT NULL DEFAULT '0.00',
  `boxes_sold` decimal(10,2) NOT NULL DEFAULT '0.00',
  `boxes_discount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `boxes_written_off` decimal(10,2) NOT NULL DEFAULT '0.00',
  `boxes_remaining` decimal(10,2) NOT NULL DEFAULT '0.00',
  `purchase_price_per_box` decimal(10,2) NOT NULL DEFAULT '0.00',
  `extra_cost_per_box` decimal(10,2) NOT NULL DEFAULT '0.00',
  `cost_price_per_box` decimal(10,2) NOT NULL DEFAULT '0.00',
  `preorder_margin_percent` decimal(5,2) NOT NULL DEFAULT '30.00',
  `preorder_discount_percent` decimal(5,2) NOT NULL DEFAULT '10.00',
  `instant_margin_percent` decimal(5,2) NOT NULL DEFAULT '50.00',
  `discount_markup_fixed` decimal(10,2) NOT NULL DEFAULT '100.00',
  `preorder_price_per_box` decimal(10,2) NOT NULL DEFAULT '0.00',
  `instant_price_per_box` decimal(10,2) NOT NULL DEFAULT '0.00',
  `discount_price_per_box` decimal(10,2) NOT NULL DEFAULT '0.00',
  `preorder_unit_price` decimal(10,2) NOT NULL DEFAULT '0.00',
  `instant_unit_price` decimal(10,2) NOT NULL DEFAULT '0.00',
  `discount_unit_price` decimal(10,2) NOT NULL DEFAULT '0.00',
  `status` enum('planned','purchased','arrived','closed') NOT NULL DEFAULT 'planned',
  `closed_at` datetime DEFAULT NULL,
  `close_reason` varchar(255) DEFAULT NULL,
  `comment` text,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ;

--
-- Дамп данных таблицы `purchase_batches`
--

INSERT INTO `purchase_batches` (`id`, `product_id`, `buyer_user_id`, `purchased_at`, `arrived_at`, `box_size_snapshot`, `box_unit_snapshot`, `boxes_total`, `boxes_reserved`, `boxes_free`, `boxes_sold`, `boxes_discount`, `boxes_written_off`, `boxes_remaining`, `purchase_price_per_box`, `extra_cost_per_box`, `cost_price_per_box`, `preorder_margin_percent`, `preorder_discount_percent`, `instant_margin_percent`, `discount_markup_fixed`, `preorder_price_per_box`, `instant_price_per_box`, `discount_price_per_box`, `preorder_unit_price`, `instant_unit_price`, `discount_unit_price`, `status`, `closed_at`, `close_reason`, `comment`, `created_at`, `updated_at`) VALUES
(3, 20, 1, '2026-05-14 14:55:20', NULL, 2.00, 'кг', 11.00, 0.00, 0.00, 2.00, 0.00, 0.00, 9.00, 750.00, 0.00, 750.00, 35.00, 10.00, 50.00, 100.00, 1010.00, 1120.00, 850.00, 505.00, 560.00, 425.00, 'closed', NULL, NULL, '[CLOSED]', '2026-05-14 14:55:20', '2026-05-28 07:03:03'),
(4, 20, 1, '2026-05-14 22:47:46', NULL, 2.00, 'кг', 10.00, 0.00, 0.00, 0.00, 0.00, 0.00, 10.00, 750.00, 0.00, 750.00, 35.00, 10.00, 50.00, 100.00, 1010.00, 1120.00, 850.00, 505.00, 560.00, 425.00, 'closed', NULL, NULL, '[CLOSED]', '2026-05-14 22:47:46', '2026-05-28 07:03:03'),
(5, 7, 1, '2026-05-16 21:58:59', NULL, 2.00, 'кг', 6.00, 0.00, 0.00, 0.00, 0.00, 0.00, 6.00, 1000.00, 0.00, 1000.00, 35.00, 10.00, 50.00, 100.00, 1350.00, 1500.00, 1100.00, 675.00, 750.00, 550.00, 'closed', NULL, NULL, '[CLOSED]', '2026-05-16 21:58:59', '2026-05-28 07:03:03'),
(6, 7, 1, '2026-05-19 00:00:00', NULL, 2.00, 'кг', 10.00, 0.00, 0.00, 0.00, 0.00, 10.00, 0.00, 1000.00, 0.00, 850.00, 35.00, 10.00, 50.00, 100.00, 1350.00, 1500.00, 1100.00, 675.00, 750.00, 550.00, 'closed', '2026-05-28 07:04:30', 'Ручное закрытие', '[CLOSED] 1', '2026-05-16 22:23:42', '2026-05-28 07:04:30'),
(7, 6, 1, '2026-05-18 00:00:00', NULL, 2.00, 'кг', 2.00, 0.00, 0.00, 0.00, 0.00, 2.00, 0.00, 1200.00, 0.00, 1200.00, 35.00, 10.00, 50.00, 100.00, 1620.00, 1800.00, 1300.00, 810.00, 900.00, 650.00, 'closed', '2026-05-28 07:04:46', 'Ручное закрытие', '[CLOSED] 1', '2026-05-18 15:21:51', '2026-05-28 07:04:46'),
(8, 7, 1, '2026-05-19 00:00:00', NULL, 2.00, 'кг', 3.00, 0.00, 0.00, 2.00, 0.00, 1.00, 0.00, 1000.00, 0.00, 1000.00, 35.00, 10.00, 50.00, 100.00, 1350.00, 1500.00, 1100.00, 675.00, 750.00, 550.00, 'closed', '2026-05-28 07:05:02', 'Ручное закрытие', 'Без причины', '2026-05-19 21:05:14', '2026-05-28 07:05:02'),
(9, 20, 1, '2026-05-20 00:00:00', NULL, 2.00, 'кг', 2.00, 0.00, 0.00, 0.00, 0.00, 2.00, 0.00, 1000.00, 0.00, 1000.00, 35.00, 10.00, 50.00, 100.00, 1350.00, 1500.00, 1100.00, 675.00, 750.00, 550.00, 'closed', '2026-05-28 07:05:23', 'Ручное закрытие', 'Без причины', '2026-05-20 05:33:21', '2026-05-28 07:05:23'),
(10, 7, 6, '2026-05-24 00:00:00', NULL, 2.00, 'кг', 10.00, 0.00, 0.00, 0.00, 0.00, 0.00, 10.00, 1000.00, 0.00, 1000.00, 35.00, 10.00, 50.00, 100.00, 1350.00, 1500.00, 1100.00, 675.00, 750.00, 550.00, 'closed', '2026-05-28 07:05:35', 'Ручное закрытие', '[CLOSED]', '2026-05-22 20:47:47', '2026-05-28 07:05:35'),
(11, 7, 6, '2026-05-24 00:00:00', NULL, 2.00, 'кг', 10.00, 0.00, 0.00, 0.00, 0.00, 0.00, 10.00, 1000.00, 0.00, 1000.00, 35.00, 10.00, 50.00, 100.00, 1350.00, 1500.00, 1100.00, 675.00, 750.00, 550.00, 'closed', '2026-05-28 07:05:53', 'Ручное закрытие', '[CLOSED]', '2026-05-22 20:48:15', '2026-05-28 07:05:53'),
(12, 7, 1, '2026-05-24 00:00:00', NULL, 2.00, 'кг', 5.00, 0.00, 0.00, 0.00, 0.00, 5.00, 0.00, 800.00, 0.00, 8000.00, 35.00, 10.00, 50.00, 100.00, 1080.00, 1200.00, 900.00, 540.00, 600.00, 450.00, 'closed', '2026-05-28 07:06:06', 'Ручное закрытие', '[CLOSED] Без причины', '2026-05-24 08:37:47', '2026-05-28 07:06:06'),
(13, 20, 1, '2026-05-24 00:00:00', NULL, 2.00, 'кг', 5.00, 0.00, 0.00, 0.00, 0.00, 5.00, 0.00, 800.00, 0.00, 800.00, 35.00, 10.00, 50.00, 100.00, 1080.00, 1200.00, 900.00, 540.00, 600.00, 450.00, 'closed', '2026-05-28 07:06:16', 'Ручное закрытие', 'Без причины', '2026-05-24 08:38:28', '2026-05-28 07:06:16'),
(14, 7, 1, '2026-05-28 00:00:00', NULL, 2.00, 'кг', 1.00, 0.00, 0.00, 0.00, 0.00, 0.00, 1.00, 800.00, 0.00, 800.00, 35.00, 10.00, 50.00, 100.00, 1080.00, 1200.00, 900.00, 540.00, 600.00, 450.00, 'closed', '2026-05-28 07:06:29', 'Ручное закрытие', '[CLOSED]', '2026-05-25 18:20:57', '2026-05-28 07:06:29'),
(15, 20, 1, '2026-05-26 00:00:00', NULL, 2.00, 'кг', 1.00, 0.00, 0.00, 0.00, 0.00, 0.00, 1.00, 800.00, 0.00, 800.00, 35.00, 10.00, 50.00, 100.00, 1080.00, 1200.00, 900.00, 540.00, 600.00, 450.00, 'closed', '2026-05-28 07:06:36', 'Ручное закрытие', '[CLOSED]', '2026-05-26 06:09:25', '2026-05-28 07:06:36'),
(16, 7, 1, '2026-05-28 00:00:00', NULL, 2.00, 'кг', 2.00, 0.00, 0.00, 0.00, 0.00, 2.00, 0.00, 1000.00, 0.00, 1000.00, 35.00, 10.00, 50.00, 100.00, 1350.00, 1500.00, 1100.00, 675.00, 750.00, 550.00, 'closed', '2026-05-28 07:06:49', 'Ручное закрытие', 'Без причины', '2026-05-27 17:12:50', '2026-06-08 06:12:15'),
(17, 20, 1, '2026-05-28 00:00:00', NULL, 2.00, 'кг', 2.00, 0.00, 0.00, 0.00, 0.00, 0.00, 2.00, 1000.00, 0.00, 1000.00, 35.00, 10.00, 50.00, 100.00, 1350.00, 1500.00, 1100.00, 675.00, 750.00, 550.00, 'closed', '2026-05-28 07:06:57', 'Ручное закрытие', '[CLOSED]', '2026-05-27 17:14:04', '2026-05-28 07:06:57'),
(18, 20, 1, '2026-05-28 00:00:00', NULL, 2.00, 'кг', 2.00, 0.00, 0.00, 0.00, 0.00, 0.00, 2.00, 1000.00, 0.00, 1000.00, 35.00, 10.00, 50.00, 100.00, 1350.00, 1500.00, 1100.00, 675.00, 750.00, 550.00, 'closed', '2026-05-28 08:05:25', 'Ручное закрытие', '[CLOSED]', '2026-05-28 04:52:07', '2026-05-28 08:05:25'),
(19, 20, 1, '2026-05-28 00:00:00', NULL, 2.00, 'кг', 2.00, 0.00, 0.00, 0.00, 0.00, 2.00, 0.00, 1100.00, 0.00, 1000.00, 35.00, 10.00, 50.00, 100.00, 1480.00, 1650.00, 1200.00, 740.00, 825.00, 600.00, 'closed', '2026-05-28 08:05:20', 'Ручное закрытие', 'Без причины', '2026-05-28 04:54:49', '2026-06-08 06:10:06'),
(20, 7, 1, '2026-05-28 00:00:00', NULL, 2.00, 'кг', 2.00, 0.00, 0.00, 0.00, 0.00, 2.00, 0.00, 750.00, 0.00, 1000.00, 35.00, 10.00, 50.00, 100.00, 1010.00, 1120.00, 1100.00, 505.00, 560.00, 550.00, 'closed', '2026-05-29 13:03:49', 'Автозакрытие: нет активных остатков и обязательств', 'Без причины', '2026-05-28 08:07:10', '2026-05-29 13:03:49'),
(21, 20, 1, '2026-05-28 00:00:00', NULL, 2.00, 'кг', 5.00, 0.00, 0.00, 0.00, 0.00, 5.00, 0.00, 800.00, 0.00, 1000.00, 35.00, 10.00, 50.00, 100.00, 1080.00, 1200.00, 1100.00, 540.00, 600.00, 550.00, 'closed', '2026-05-29 13:03:57', 'Автозакрытие: нет активных остатков и обязательств', 'Без причины', '2026-05-28 08:07:27', '2026-05-29 13:03:57'),
(22, 7, 1, '2026-05-29 00:00:00', NULL, 2.00, 'кг', 5.00, 0.00, 0.00, 0.00, 0.00, 5.00, 0.00, 750.00, 0.00, 1000.00, 35.00, 10.00, 50.00, 100.00, 1010.00, 1130.00, 1100.00, 505.00, 560.00, 550.00, 'closed', '2026-05-31 13:58:58', 'Автозакрытие: нет активных остатков и обязательств', 'Без причины', '2026-05-28 08:36:04', '2026-05-31 13:58:58'),
(23, 20, 1, '2026-05-29 00:00:00', NULL, 2.00, 'кг', 3.00, 0.00, 0.00, 0.00, 0.00, 3.00, 0.00, 800.00, 0.00, 800.00, 35.00, 10.00, 50.00, 100.00, 1080.00, 1200.00, 900.00, 1080.00, 1200.00, 450.00, 'closed', '2026-05-31 13:58:49', 'Автозакрытие: нет активных остатков и обязательств', 'Без причины', '2026-05-28 12:58:10', '2026-05-31 13:58:49'),
(24, 7, 1, '2026-05-31 00:00:00', NULL, 2.00, 'кг', 8.00, 0.00, 0.00, 7.00, 0.00, 1.00, 0.00, 1000.00, 0.00, 0.00, 35.00, 10.00, 50.00, 100.00, 1350.00, 1500.00, 100.00, 675.00, 750.00, 50.00, 'closed', '2026-06-08 07:01:56', 'Автозакрытие: нет активных остатков и обязательств', '1', '2026-05-31 13:59:10', '2026-06-08 07:01:56'),
(25, 7, 1, '2026-06-06 00:00:00', NULL, 2.00, 'кг', 8.00, 0.00, 0.00, 3.00, 0.00, 5.00, 0.00, 650.00, 0.00, 0.00, 35.00, 10.00, 50.00, 100.00, 1480.00, 1500.00, 100.00, 435.00, 485.00, 50.00, 'arrived', NULL, NULL, 'Без причины', '2026-06-01 14:19:56', '2026-06-11 15:10:29'),
(26, 20, 1, '2026-06-06 00:00:00', NULL, 2.00, 'кг', 1.00, 0.00, 1.00, 0.00, 0.00, 1.00, 0.00, 1000.00, 0.00, 0.00, 35.00, 10.00, 50.00, 100.00, 1350.00, 1500.00, 100.00, 675.00, 750.00, 50.00, 'closed', '2026-06-08 06:14:13', 'Ручное закрытие', 'Без причины', '2026-06-01 14:22:03', '2026-06-08 20:33:40'),
(27, 6, 17, '2026-06-05 00:00:00', NULL, 2.00, 'кг', 2.00, 1.00, 0.00, 0.00, 0.00, 1.00, 1.00, 1250.00, 0.00, 1200.00, 35.00, 10.00, 50.00, 100.00, 1680.00, 1870.00, 1300.00, 1680.00, 1870.00, 650.00, 'closed', '2026-06-08 07:00:11', 'Ручное закрытие', 'Без причины', '2026-06-03 10:53:04', '2026-06-08 07:00:11'),
(28, 6, 1, '2026-07-04 00:00:00', NULL, 2.00, 'кг', 3.00, 0.00, 1.00, 1.00, 0.00, 1.00, 1.00, 1200.00, 0.00, 1200.00, 35.00, 10.00, 50.00, 100.00, 1620.00, 1800.00, 1300.00, 810.00, 900.00, 650.00, 'arrived', NULL, NULL, 'Без причины', '2026-06-05 18:13:58', '2026-07-14 06:16:15'),
(29, 31, 1, '2026-06-10 00:00:00', NULL, 2.00, 'кг', 3.00, 1.00, 0.00, 0.00, 0.00, 2.00, 1.00, 1200.00, 0.00, 1100.00, 35.00, 10.00, 50.00, 100.00, 1620.00, 1800.00, 1200.00, 810.00, 900.00, 600.00, 'arrived', NULL, NULL, 'Без причины', '2026-06-05 18:17:38', '2026-06-16 15:43:12'),
(30, 7, 17, '2026-06-12 00:00:00', NULL, 2.00, 'кг', 17.00, 0.00, 0.00, 10.00, 0.00, 0.00, 7.00, 1000.00, 0.00, 900.00, 35.00, 10.00, 50.00, 100.00, 1350.00, 1500.00, 1000.00, 675.00, 750.00, 500.00, 'arrived', NULL, NULL, '', '2026-06-06 09:27:05', '2026-06-12 20:34:14'),
(31, 19, 1, NULL, NULL, 2.00, 'кг', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 1200.00, 0.00, 1200.00, 35.00, 10.00, 50.00, 100.00, 1620.00, 1800.00, 1300.00, 810.00, 900.00, 650.00, 'planned', NULL, NULL, '', '2026-06-12 08:21:14', NULL),
(32, 19, 1, '2026-07-04 00:00:00', NULL, 2.00, 'кг', 2.00, 0.00, 2.00, 0.00, 0.00, 0.00, 2.00, 1200.00, 0.00, 1200.00, 35.00, 10.00, 50.00, 100.00, 1620.00, 1800.00, 1300.00, 810.00, 900.00, 650.00, 'purchased', NULL, NULL, '', '2026-06-12 08:21:32', '2026-07-14 06:21:31'),
(33, 13, 1, NULL, NULL, 8.00, 'кг', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 1500.00, 0.00, 1500.00, 35.00, 10.00, 50.00, 100.00, 2020.00, 2250.00, 1600.00, 253.00, 281.00, 200.00, 'planned', NULL, NULL, '', '2026-06-12 08:21:50', NULL),
(34, 7, 17, '2026-06-13 00:00:00', NULL, 2.00, 'кг', 7.00, 0.00, 0.00, 2.00, 0.00, 5.00, 0.00, 1000.00, 0.00, 1000.00, 35.00, 10.00, 50.00, 100.00, 1350.00, 1500.00, 1100.00, 1350.00, 1500.00, 550.00, 'arrived', NULL, NULL, 'Без причины', '2026-06-12 20:35:37', '2026-06-17 15:37:19'),
(35, 31, 1, '2026-06-18 00:00:00', NULL, 2.00, 'кг', 5.00, 0.00, 0.00, 1.00, 0.00, 4.00, 0.00, 1200.00, 0.00, 1000.00, 35.00, 10.00, 50.00, 100.00, 1620.00, 1800.00, 1100.00, 1620.00, 1800.00, 550.00, 'closed', '2026-06-27 16:48:05', 'Автозакрытие: нет активных остатков и обязательств', 'Без причины', '2026-06-14 09:45:05', '2026-06-27 16:48:05'),
(36, 7, 1, NULL, NULL, 2.00, 'кг', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 1000.00, 0.00, 1000.00, 35.00, 10.00, 50.00, 100.00, 1350.00, 1500.00, 1100.00, 675.00, 750.00, 550.00, 'planned', NULL, NULL, '', '2026-06-14 09:45:46', NULL),
(37, 6, 1, NULL, NULL, 2.00, 'кг', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 1800.00, 0.00, 1800.00, 35.00, 10.00, 50.00, 100.00, 2430.00, 2700.00, 1900.00, 1215.00, 1350.00, 950.00, 'planned', NULL, NULL, '', '2026-06-14 09:46:39', NULL),
(38, 20, 1, NULL, NULL, 2.00, 'кг', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 1000.00, 0.00, 1000.00, 35.00, 10.00, 50.00, 100.00, 1350.00, 1500.00, 1100.00, 675.00, 750.00, 550.00, 'planned', NULL, NULL, '', '2026-06-16 15:42:25', NULL),
(39, 7, 17, '2026-07-04 00:00:00', NULL, 2.00, 'кг', 7.00, 0.00, 7.00, 0.00, 0.00, 0.00, 7.00, 1100.00, 0.00, 1000.00, 35.00, 10.00, 50.00, 100.00, 1480.00, 1500.00, 1100.00, 1480.00, 1500.00, 550.00, 'purchased', NULL, NULL, '', '2026-06-17 16:59:16', '2026-07-14 06:16:15'),
(40, 7, 17, '2026-06-18 00:00:00', NULL, 2.00, 'кг', 10.00, 0.00, 0.00, 5.00, 0.00, 5.00, 0.00, 1000.00, 0.00, 1000.00, 35.00, 10.00, 50.00, 100.00, 1350.00, 1500.00, 1100.00, 1350.00, 1500.00, 550.00, 'closed', '2026-06-27 16:47:58', 'Автозакрытие: нет активных остатков и обязательств', 'Без причины', '2026-06-17 17:01:00', '2026-06-27 16:47:58'),
(41, 11, 1, '2026-07-04 00:00:00', NULL, 2.00, 'кг', 10.00, 0.00, 10.00, 0.00, 0.00, 0.00, 10.00, 500.00, 0.00, 400.00, 35.00, 10.00, 50.00, 100.00, 670.00, 750.00, 500.00, 335.00, 375.00, 250.00, 'arrived', NULL, NULL, '', '2026-06-27 16:51:38', '2026-07-14 06:21:38');

-- --------------------------------------------------------

--
-- Структура таблицы `purchase_batch_photos`
--

CREATE TABLE `purchase_batch_photos` (
  `id` int UNSIGNED NOT NULL,
  `purchase_batch_id` int UNSIGNED NOT NULL,
  `image_path` varchar(255) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Дамп данных таблицы `purchase_batch_photos`
--

INSERT INTO `purchase_batch_photos` (`id`, `purchase_batch_id`, `image_path`, `created_at`) VALUES
(2, 21, '/uploads/prod_687c9f2b2b2603.76182003.webp', '2026-05-28 13:40:41'),
(6, 23, '/uploads/batch_68565_6a1965a14f7e30.33698516.webp', '2026-05-29 13:08:33'),
(8, 24, '/uploads/batch_69019_6a1c1560513491.88242126.webp', '2026-05-31 14:02:56'),
(10, 27, '/uploads/prod_687c9f17b722a2.80298175.webp', '2026-06-03 10:58:54'),
(11, 25, '/uploads/batch_69819_6a235f6997f3e4.03516105.webp', '2026-06-06 02:44:42'),
(12, 25, '/uploads/prod_6a22e7173e8b58.18396861.webp', '2026-06-06 02:44:55'),
(13, 29, '/uploads/batch_6a26b790b47db1.84916438.webp', '2026-06-08 15:37:36'),
(14, 28, '/uploads/batch_6a2781b64dbb48.40906561.webp', '2026-06-09 06:00:06'),
(15, 29, '/uploads/batch_6a27eafa2f7f84.09950229.webp', '2026-06-09 13:29:14'),
(16, 28, '/uploads/prod_6a22e79b737979.20644318.webp', '2026-06-10 13:59:12'),
(17, 30, '/uploads/batch_71524_6a2b94333163e6.08223852.webp', '2026-06-12 08:08:04'),
(18, 30, '/uploads/prod_6a22e7173e8b58.18396861.webp', '2026-06-12 08:08:08'),
(19, 34, '/uploads/prod_6a22e7173e8b58.18396861.webp', '2026-06-14 09:44:14'),
(20, 34, '/uploads/prod_6a22e7173e8b58.18396861.webp', '2026-06-14 09:44:14'),
(21, 41, '/uploads/prod_687c9ec2607f91.61695381.webp', '2026-06-27 16:52:20'),
(22, 41, '/uploads/prod_687c9ec2607f91.61695381.webp', '2026-06-27 16:52:20');

-- --------------------------------------------------------

--
-- Структура таблицы `referrals`
--

CREATE TABLE `referrals` (
  `id` int UNSIGNED NOT NULL,
  `referrer_id` int UNSIGNED NOT NULL,
  `referred_id` int UNSIGNED NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Дамп данных таблицы `referrals`
--

INSERT INTO `referrals` (`id`, `referrer_id`, `referred_id`, `created_at`) VALUES
(10, 6, 16, '2025-06-16 16:42:20'),
(27, 17, 25, '2025-07-23 06:51:32'),
(42, 41, 42, '2025-07-26 05:33:36'),
(43, 41, 43, '2025-07-26 05:38:32'),
(44, 41, 44, '2025-07-26 05:41:33'),
(45, 41, 45, '2025-07-26 05:44:38'),
(46, 41, 46, '2025-07-26 05:48:28'),
(47, 6, 47, '2025-07-27 06:26:27'),
(48, 6, 48, '2025-07-27 06:28:31'),
(49, 6, 49, '2025-07-27 06:31:10'),
(52, 1, 51, '2025-07-29 13:16:02'),
(53, 1, 52, '2025-07-29 13:18:17'),
(54, 17, 53, '2025-08-02 05:20:50'),
(55, 41, 58, '2025-08-03 06:55:05'),
(56, 41, 59, '2025-08-03 06:57:47'),
(57, 41, 60, '2025-08-03 07:03:13'),
(58, 41, 61, '2025-08-03 07:05:27'),
(59, 41, 62, '2025-08-03 07:46:47'),
(60, 41, 63, '2025-08-03 11:40:45'),
(61, 1, 64, '2025-08-03 12:25:21'),
(62, 41, 65, '2025-08-03 14:14:35'),
(63, 1, 67, '2025-08-04 05:03:19'),
(64, 41, 68, '2025-08-04 05:17:13'),
(65, 41, 69, '2025-08-04 06:57:13'),
(66, 41, 70, '2025-08-04 07:22:39'),
(67, 41, 71, '2025-08-04 08:18:48'),
(68, 41, 72, '2025-08-04 13:40:25'),
(69, 41, 73, '2025-08-04 13:47:13'),
(70, 41, 74, '2025-08-05 05:03:25'),
(71, 41, 75, '2025-08-05 05:24:35'),
(72, 41, 76, '2025-08-05 06:50:16'),
(73, 41, 77, '2025-08-05 07:14:03'),
(74, 1, 78, '2025-08-05 09:38:15'),
(75, 41, 79, '2025-08-05 17:00:13'),
(76, 41, 80, '2025-08-06 06:54:27'),
(77, 41, 81, '2025-08-06 07:54:17'),
(78, 41, 82, '2025-08-06 07:57:50'),
(79, 41, 83, '2025-08-06 08:31:03'),
(80, 41, 84, '2025-08-06 08:35:54'),
(81, 41, 85, '2025-08-06 12:22:21'),
(82, 1, 86, '2025-08-06 15:36:10'),
(83, 41, 87, '2025-08-06 16:30:45'),
(84, 41, 88, '2025-08-07 02:47:10'),
(85, 41, 89, '2025-08-07 06:04:38'),
(86, 41, 90, '2025-08-07 08:21:28'),
(87, 41, 91, '2025-08-07 09:03:03'),
(88, 41, 92, '2025-08-08 02:56:49'),
(89, 41, 93, '2025-08-08 03:42:30'),
(90, 41, 94, '2025-08-08 04:39:16'),
(91, 41, 95, '2025-08-08 05:34:22'),
(92, 41, 96, '2025-08-08 05:40:20'),
(93, 41, 97, '2025-08-08 05:50:51'),
(94, 41, 98, '2025-08-08 08:21:28'),
(95, 41, 99, '2025-08-08 08:31:05'),
(96, 41, 100, '2025-08-08 09:05:51'),
(97, 41, 101, '2025-08-08 10:30:13'),
(98, 41, 102, '2025-08-08 12:12:29'),
(99, 41, 103, '2025-08-09 07:18:07'),
(100, 41, 104, '2025-08-09 07:57:01'),
(101, 41, 105, '2025-08-09 16:17:22'),
(102, 41, 106, '2025-08-09 16:18:08'),
(103, 1, 108, '2025-08-20 00:59:51'),
(107, 41, 112, '2025-08-23 12:48:10'),
(108, 41, 113, '2025-08-23 12:49:06'),
(109, 41, 114, '2025-08-23 12:51:39'),
(110, 41, 115, '2025-08-24 11:05:12'),
(111, 41, 116, '2025-08-25 12:33:25'),
(112, 17, 117, '2025-08-26 15:32:54'),
(113, 17, 118, '2025-08-26 15:36:59'),
(114, 17, 119, '2025-08-26 15:48:24'),
(115, 41, 120, '2025-08-27 14:37:25'),
(116, 41, 121, '2025-08-28 03:42:25'),
(117, 41, 122, '2025-08-28 15:52:10'),
(118, 41, 123, '2025-08-29 18:26:20'),
(119, 41, 124, '2025-08-30 06:14:27'),
(120, 41, 125, '2025-08-30 06:15:22'),
(121, 41, 126, '2025-08-30 06:16:19'),
(122, 41, 127, '2025-08-30 06:22:05'),
(123, 41, 128, '2025-08-30 14:12:55'),
(124, 41, 129, '2025-09-02 15:54:15'),
(125, 41, 130, '2025-09-04 06:41:01'),
(126, 41, 131, '2025-09-04 08:28:18'),
(127, 41, 132, '2025-09-04 10:14:10'),
(128, 41, 133, '2025-09-04 11:46:09'),
(129, 41, 134, '2025-09-04 13:12:02'),
(130, 41, 135, '2025-09-05 07:52:04'),
(131, 41, 136, '2025-09-05 07:54:51'),
(132, 41, 137, '2025-09-05 14:46:29'),
(133, 41, 138, '2025-09-06 17:49:22'),
(134, 41, 139, '2025-09-07 04:40:47'),
(135, 17, 140, '2025-09-07 08:43:51'),
(136, 17, 141, '2025-09-07 08:45:48'),
(137, 41, 142, '2025-09-07 08:53:16'),
(138, 17, 143, '2025-09-07 09:38:53'),
(139, 17, 144, '2025-09-07 10:32:16'),
(140, 17, 145, '2025-09-07 11:38:52'),
(141, 41, 146, '2025-09-08 08:42:52'),
(142, 41, 147, '2025-09-08 08:43:39'),
(143, 41, 148, '2025-09-08 08:52:51'),
(144, 17, 149, '2025-09-08 09:00:37'),
(145, 17, 150, '2025-09-08 10:17:47'),
(146, 17, 151, '2025-09-08 14:07:44'),
(147, 41, 152, '2025-09-09 04:10:30'),
(148, 41, 153, '2025-09-09 07:17:57'),
(149, 17, 154, '2025-09-09 09:55:58'),
(150, 17, 155, '2025-09-09 09:58:31'),
(151, 41, 156, '2025-09-11 09:46:20'),
(152, 41, 157, '2025-09-11 09:47:20'),
(153, 17, 158, '2025-09-13 08:32:41'),
(154, 17, 159, '2025-09-13 08:42:56'),
(155, 41, 160, '2025-09-13 08:55:11'),
(156, 17, 161, '2025-09-13 10:44:25'),
(157, 41, 162, '2025-09-13 12:58:19'),
(158, 41, 163, '2025-09-13 15:17:30'),
(159, 41, 164, '2025-09-18 18:06:46'),
(160, 17, 165, '2025-09-19 04:34:24'),
(161, 17, 166, '2025-09-19 05:34:31'),
(162, 41, 167, '2025-09-19 06:20:12'),
(163, 41, 169, '2025-09-19 10:03:06'),
(165, 41, 171, '2025-09-20 04:49:46'),
(166, 17, 172, '2025-09-20 11:26:51'),
(169, 17, 175, '2025-09-22 12:21:53'),
(170, 17, 176, '2025-09-23 05:23:33'),
(171, 41, 177, '2025-09-24 03:57:49'),
(172, 17, 178, '2025-09-26 07:44:35'),
(173, 17, 179, '2025-09-27 08:03:40'),
(174, 41, 180, '2025-09-28 08:48:26'),
(175, 17, 181, '2025-09-30 06:18:20'),
(176, 41, 182, '2025-09-30 14:31:38'),
(177, 17, 183, '2025-09-30 15:39:56'),
(178, 17, 184, '2025-09-30 15:42:50'),
(179, 41, 186, '2025-10-01 16:37:33'),
(180, 41, 187, '2025-10-02 05:10:10'),
(181, 17, 188, '2025-10-02 07:39:28'),
(182, 41, 189, '2025-10-02 09:18:52'),
(183, 41, 190, '2025-10-02 09:19:43'),
(184, 41, 193, '2025-10-06 07:25:00'),
(185, 41, 194, '2025-10-06 07:26:21'),
(186, 17, 195, '2025-10-07 15:25:35'),
(187, 17, 196, '2025-10-07 15:28:18'),
(188, 17, 197, '2025-10-09 02:42:46'),
(189, 17, 198, '2025-10-09 02:47:24'),
(190, 17, 199, '2025-10-09 04:31:37'),
(191, 17, 200, '2025-10-09 04:42:46'),
(192, 17, 202, '2025-10-11 15:23:17'),
(193, 17, 203, '2025-10-12 13:54:48'),
(194, 17, 204, '2025-10-12 14:12:10'),
(195, 17, 205, '2025-10-12 14:17:28'),
(196, 41, 206, '2025-10-13 15:25:08'),
(197, 17, 207, '2025-10-15 15:57:24'),
(198, 17, 208, '2025-10-16 06:01:06'),
(199, 41, 209, '2025-10-17 05:29:59'),
(200, 41, 210, '2025-10-17 05:54:12'),
(201, 17, 211, '2025-10-18 07:29:05'),
(202, 17, 212, '2025-10-18 08:15:37'),
(203, 17, 213, '2025-10-19 04:50:10'),
(204, 17, 214, '2025-10-20 16:46:59'),
(205, 17, 215, '2025-10-21 05:01:01'),
(206, 41, 217, '2025-10-21 09:52:01'),
(207, 17, 218, '2025-10-22 11:08:18'),
(208, 17, 219, '2025-10-22 17:21:25'),
(209, 17, 220, '2025-10-23 07:41:40'),
(210, 41, 221, '2025-10-23 11:32:37'),
(211, 17, 222, '2025-10-23 14:36:09'),
(212, 17, 223, '2025-10-23 15:08:11'),
(213, 17, 224, '2025-10-24 16:07:21'),
(214, 41, 225, '2025-10-25 11:27:31'),
(215, 41, 226, '2025-10-26 11:18:34'),
(216, 17, 232, '2026-05-08 11:23:36'),
(217, 17, 233, '2026-05-08 11:25:38'),
(218, 17, 234, '2026-05-08 11:27:07'),
(219, 17, 235, '2026-05-08 11:28:57'),
(220, 17, 236, '2026-05-08 13:29:36'),
(221, 17, 237, '2026-05-11 03:54:57'),
(222, 17, 238, '2026-05-11 04:00:48'),
(223, 17, 239, '2026-05-11 04:06:18'),
(224, 17, 240, '2026-05-11 04:07:19'),
(225, 17, 241, '2026-05-11 13:55:15'),
(226, 17, 242, '2026-05-13 15:54:04'),
(227, 17, 243, '2026-05-14 06:24:34'),
(228, 17, 244, '2026-05-14 06:31:36'),
(229, 17, 245, '2026-05-14 10:06:26'),
(230, 17, 246, '2026-05-14 10:09:33'),
(231, 17, 247, '2026-05-14 15:32:04'),
(232, 17, 248, '2026-05-14 15:34:19'),
(233, 17, 249, '2026-05-15 07:10:01'),
(234, 17, 250, '2026-05-15 08:41:25'),
(235, 17, 251, '2026-05-15 10:48:00'),
(236, 17, 252, '2026-05-15 16:15:07'),
(237, 17, 253, '2026-05-15 16:34:27'),
(238, 17, 254, '2026-05-17 06:32:45'),
(239, 17, 255, '2026-05-17 07:33:00'),
(240, 17, 256, '2026-05-19 15:23:06'),
(241, 17, 257, '2026-05-19 15:24:36'),
(242, 17, 258, '2026-05-20 14:30:21'),
(243, 17, 259, '2026-05-20 14:44:37'),
(244, 17, 260, '2026-05-24 07:47:21'),
(245, 17, 261, '2026-05-25 11:21:36'),
(246, 17, 262, '2026-05-25 13:16:41'),
(247, 17, 264, '2026-05-28 07:22:39'),
(248, 17, 265, '2026-05-28 14:32:51'),
(249, 17, 266, '2026-06-02 08:01:29'),
(250, 17, 268, '2026-06-06 06:35:20'),
(251, 17, 269, '2026-06-10 04:00:13'),
(252, 17, 270, '2026-06-12 17:23:43'),
(253, 17, 271, '2026-06-12 17:33:57'),
(254, 17, 273, '2026-06-16 12:50:48'),
(255, 17, 274, '2026-06-17 12:37:07'),
(256, 17, 275, '2026-06-17 14:09:39'),
(257, 17, 276, '2026-06-17 14:11:08'),
(258, 17, 277, '2026-06-18 14:03:55'),
(259, 17, 278, '2026-06-18 14:06:05');

-- --------------------------------------------------------

--
-- Структура таблицы `seller_payouts`
--

CREATE TABLE `seller_payouts` (
  `id` int UNSIGNED NOT NULL,
  `seller_id` int UNSIGNED NOT NULL,
  `order_id` int UNSIGNED NOT NULL,
  `gross_amount` decimal(10,2) NOT NULL,
  `commission_rate` decimal(5,2) NOT NULL DEFAULT '30.00',
  `commission_amount` decimal(10,2) NOT NULL,
  `payout_amount` decimal(10,2) NOT NULL,
  `status` enum('pending','scheduled','accrued','paid','cancelled') NOT NULL DEFAULT 'pending',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `paid_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `settings`
--

CREATE TABLE `settings` (
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text NOT NULL,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Ключ-значение для хранения настроек приложения';

--
-- Дамп данных таблицы `settings`
--

INSERT INTO `settings` (`setting_key`, `setting_value`, `updated_at`) VALUES
('company_name', 'berryGo', '2026-06-04 07:45:12'),
('contact_phone', '+79029237794', '2026-06-04 07:45:12'),
('dadata_api_key', 'd32d23e2087d406928a38947855d3179f03dcff2', '2026-06-04 08:14:44'),
('dadata_secret_key', 'b11fccf3100a8666f0cb5382071f2e935c449df9', '2026-06-04 08:14:44'),
('delivery_dadata_center_lat', '56.233717', '2026-06-05 16:44:07'),
('delivery_dadata_center_lng', '92.8426', '2026-06-05 16:44:07'),
('delivery_dadata_radius_m', '60000', '2026-06-05 16:44:07'),
('delivery_dadata_suggestion_count', '8', '2026-06-05 16:44:07'),
('delivery_default_fee', '300', '2026-06-05 16:44:07'),
('delivery_per_km_from_km', '27', '2026-06-05 16:44:07'),
('delivery_per_km_price', '50', '2026-06-05 16:44:07'),
('delivery_store_address', 'Самовывоз: 9 мая, 73', '2026-06-05 16:44:07'),
('delivery_store_lat', '56.055', '2026-06-05 16:44:07'),
('delivery_store_lng', '92.909', '2026-06-05 16:44:07'),
('delivery_taxi_courier_button_text', 'Вызову такси-курьера', '2026-06-05 16:44:07'),
('delivery_taxi_courier_enabled', '1', '2026-06-05 16:44:07'),
('delivery_taxi_courier_instructions', '', '2026-06-05 16:44:07'),
('openrouteservice_api_key', 'eyJvcmciOiI1YjNjZTM1OTc4NTExMTAwMDFjZjYyNDgiLCJpZCI6IjI0MDdmODZmY2Q2NzRkN2ZiYjc5YjA0ODA3M2QwMzJhIiwiaCI6Im11cm11cjY0In0=', '2026-06-04 08:32:04'),
('openrouteservice_snap_radius_m', '2000', '2026-06-05 16:44:07'),
('payment_method_card_on_delivery_enabled', '0', '2026-06-08 06:57:12'),
('payment_method_card_pickup_enabled', '0', '2026-06-08 06:57:12'),
('payment_method_cash_on_delivery_enabled', '1', '2026-06-08 06:57:12'),
('payment_method_cash_pickup_enabled', '1', '2026-06-08 06:57:12'),
('payment_method_online_robokassa_enabled', '1', '2026-06-08 06:57:12'),
('pricing_discount_stock_bonuses_allowed', '0', '2026-05-11 07:04:21'),
('pricing_discount_stock_coupons_allowed', '0', '2026-05-11 07:04:21'),
('pricing_discount_stock_markup_fixed', '100', '2026-05-11 07:04:21'),
('pricing_free_boxes_default', '10', '2026-05-11 07:04:21'),
('pricing_instant_margin_percent', '50', '2026-06-04 07:45:12'),
('pricing_preorder_margin_percent', '35', '2026-05-28 07:03:00'),
('pricing_rounding_step', '10', '2026-06-04 07:45:12'),
('registration_email_verification_ttl_minutes', '60', '2026-06-22 09:31:38'),
('registration_phone_verification_enabled', '0', '2026-06-22 09:31:38'),
('robokassa_culture', 'ru', '2026-06-04 07:45:12'),
('robokassa_default_description', 'Оплата заказа berryGo', '2026-06-04 07:45:12'),
('robokassa_enabled', '0', '2026-06-04 07:45:12'),
('robokassa_encoding', 'UTF-8', '2026-06-04 07:45:12'),
('robokassa_expiration_minutes', '60', '2026-06-04 07:45:12'),
('robokassa_fail_url', 'https://berrygo.ru/payments/robokassa/fail', '2026-06-04 07:45:12'),
('robokassa_hash_algorithm', 'MD5', '2026-06-04 07:45:12'),
('robokassa_inc_curr_label', 'BankCard', '2026-06-04 07:45:12'),
('robokassa_is_test', '1', '2026-06-04 07:45:12'),
('robokassa_merchant_login', 'berrygo', '2026-06-04 07:45:12'),
('robokassa_password1', 'FbbQ3ceUdM7lMnn94N3T', '2026-06-03 22:21:56'),
('robokassa_password2', 'KcDSFUvQ1UB28lSIal38', '2026-06-03 22:21:56'),
('robokassa_payment_url', 'https://auth.robokassa.ru/Merchant/Index.aspx', '2026-06-04 07:45:12'),
('robokassa_result_url', 'https://berrygo.ru/payments/robokassa/result', '2026-06-04 07:45:12'),
('robokassa_success_url', 'https://berrygo.ru/payments/robokassa/success', '2026-06-04 07:45:12'),
('sitemap_page_catalog', '0', '2026-07-01 15:48:42'),
('sitemap_page_login', '0', '2026-07-01 15:47:24'),
('sitemap_page_register', '0', '2026-07-01 15:47:22'),
('sitemap_page_reset_pin', '0', '2026-07-01 15:48:10'),
('stock_deficit_last_notification_signature', '', '2026-07-14 06:21:12'),
('theme_dark_primary', 'raspberry', '2026-06-04 07:45:12'),
('theme_light_primary', 'raspberry', '2026-06-04 07:45:12'),
('ui_home_no_stock_message', 'На данный момент ягод нет в наличии. Воспользуйтесь нашим предложением предварительного заказа со скидкой 10% — это дополнительная скидка за оформление предварительного бронирования.', '2026-06-04 07:45:12'),
('ui_preorder_discount_percent', '10', '2026-06-04 07:45:12'),
('ui_preorder_price_hint', 'Цена ориентировочная, точная цена будет после поступления', '2026-06-04 07:45:12');

-- --------------------------------------------------------

--
-- Структура таблицы `sitemap_settings`
--

CREATE TABLE `sitemap_settings` (
  `id` int NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '0',
  `last_generated` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Дамп данных таблицы `sitemap_settings`
--

INSERT INTO `sitemap_settings` (`id`, `is_active`, `last_generated`) VALUES
(1, 1, '2026-07-14 05:27:24');

-- --------------------------------------------------------

--
-- Структура таблицы `stock_movements`
--

CREATE TABLE `stock_movements` (
  `id` int UNSIGNED NOT NULL,
  `purchase_batch_id` int UNSIGNED NOT NULL,
  `product_id` int UNSIGNED NOT NULL,
  `order_id` int UNSIGNED DEFAULT NULL,
  `user_id` int UNSIGNED DEFAULT NULL,
  `movement_type` enum('purchase','reserve','unreserve','sale','return_to_stock','move_to_discount','writeoff','correction') NOT NULL,
  `stock_mode` enum('preorder','instant','discount_stock','internal') NOT NULL DEFAULT 'internal',
  `boxes_delta` decimal(10,2) NOT NULL,
  `boxes_balance_after` decimal(10,2) DEFAULT NULL,
  `comment` text,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Дамп данных таблицы `stock_movements`
--

INSERT INTO `stock_movements` (`id`, `purchase_batch_id`, `product_id`, `order_id`, `user_id`, `movement_type`, `stock_mode`, `boxes_delta`, `boxes_balance_after`, `comment`, `created_at`) VALUES
(5, 3, 20, NULL, NULL, 'reserve', 'instant', -1.00, NULL, NULL, '2026-05-14 15:11:22'),
(6, 3, 20, NULL, NULL, 'sale', 'internal', -1.00, NULL, NULL, '2026-05-14 15:11:22'),
(7, 3, 20, NULL, NULL, 'reserve', 'instant', -1.00, NULL, NULL, '2026-05-14 15:32:16'),
(8, 3, 20, NULL, NULL, 'sale', 'internal', -1.00, NULL, NULL, '2026-05-14 15:32:16'),
(9, 8, 7, NULL, NULL, 'reserve', 'instant', -1.00, NULL, NULL, '2026-05-20 10:33:31'),
(10, 8, 7, NULL, NULL, 'sale', 'internal', -1.00, NULL, NULL, '2026-05-20 10:33:31'),
(11, 8, 7, NULL, NULL, 'reserve', 'instant', -1.00, NULL, NULL, '2026-05-20 13:38:15'),
(12, 8, 7, NULL, NULL, 'sale', 'internal', -1.00, NULL, NULL, '2026-05-20 13:38:15'),
(13, 16, 7, NULL, NULL, 'reserve', 'instant', -1.00, NULL, NULL, '2026-05-28 08:05:44'),
(14, 16, 7, NULL, NULL, 'sale', 'internal', -1.00, NULL, NULL, '2026-05-28 08:05:44'),
(16, 16, 7, NULL, NULL, 'return_to_stock', 'instant', 1.00, NULL, NULL, '2026-05-29 12:24:26'),
(17, 23, 20, NULL, NULL, 'reserve', 'preorder', -1.00, NULL, NULL, '2026-05-29 12:27:53'),
(18, 24, 7, NULL, NULL, 'sale', 'instant', -1.00, NULL, NULL, '2026-06-01 16:40:08'),
(19, 24, 7, NULL, NULL, 'return_to_stock', 'instant', 1.00, NULL, NULL, '2026-06-01 17:13:58'),
(20, 24, 7, 466, NULL, 'sale', 'instant', -1.00, NULL, NULL, '2026-06-02 11:01:29'),
(21, 24, 7, 467, NULL, 'sale', 'instant', -1.00, NULL, NULL, '2026-06-02 13:35:29'),
(22, 24, 7, 468, NULL, 'sale', 'instant', -1.00, NULL, NULL, '2026-06-02 14:26:24'),
(23, 24, 7, 469, NULL, 'sale', 'instant', -1.00, NULL, NULL, '2026-06-02 17:00:06'),
(24, 24, 7, 467, NULL, 'return_to_stock', 'instant', 1.00, NULL, NULL, '2026-06-02 18:01:07'),
(25, 24, 7, NULL, NULL, 'sale', 'instant', -1.00, NULL, NULL, '2026-06-02 19:13:59'),
(26, 24, 7, NULL, NULL, 'sale', 'instant', -1.00, NULL, NULL, '2026-06-02 19:18:15'),
(27, 24, 7, NULL, NULL, 'return_to_stock', 'instant', 1.00, NULL, NULL, '2026-06-02 19:19:34'),
(28, 24, 7, NULL, NULL, 'return_to_stock', 'instant', 1.00, NULL, NULL, '2026-06-02 19:19:46'),
(29, 24, 7, 472, NULL, 'sale', 'instant', -1.00, NULL, NULL, '2026-06-03 10:08:40'),
(30, 24, 7, 473, NULL, 'sale', 'instant', -1.00, NULL, NULL, '2026-06-03 10:11:24'),
(31, 24, 7, 474, NULL, 'sale', 'instant', -1.00, NULL, NULL, '2026-06-03 10:20:30'),
(32, 24, 7, NULL, NULL, 'sale', 'instant', -2.00, NULL, NULL, '2026-06-03 22:23:45'),
(33, 24, 7, NULL, NULL, 'return_to_stock', 'instant', 2.00, NULL, NULL, '2026-06-03 22:24:54'),
(34, 24, 7, NULL, NULL, 'sale', 'instant', -2.00, NULL, NULL, '2026-06-05 17:46:38'),
(35, 25, 7, 477, NULL, 'sale', 'instant', -1.00, NULL, NULL, '2026-06-06 09:31:27'),
(36, 25, 7, 478, NULL, 'sale', 'instant', -1.00, NULL, NULL, '2026-06-06 09:35:21'),
(37, 24, 7, NULL, NULL, 'return_to_stock', 'instant', 1.00, NULL, NULL, '2026-06-07 16:57:19'),
(38, 26, 20, NULL, NULL, 'reserve', 'preorder', -1.00, NULL, NULL, '2026-06-08 06:05:47'),
(39, 30, 7, NULL, NULL, 'reserve', 'preorder', -1.00, NULL, NULL, '2026-06-08 20:31:42'),
(40, 26, 20, NULL, NULL, 'unreserve', 'preorder', 1.00, NULL, NULL, '2026-06-08 20:33:40'),
(41, 25, 7, 481, NULL, 'reserve', 'instant', -1.00, NULL, NULL, '2026-06-10 07:00:29'),
(42, 25, 7, 481, NULL, 'sale', 'internal', -1.00, NULL, NULL, '2026-06-11 08:06:16'),
(43, 30, 7, 482, NULL, 'reserve', 'instant', -1.00, NULL, NULL, '2026-06-12 20:24:24'),
(44, 30, 7, 482, NULL, 'sale', 'internal', -1.00, NULL, NULL, '2026-06-12 20:24:24'),
(45, 30, 7, 484, NULL, 'reserve', 'instant', -2.00, NULL, NULL, '2026-06-12 20:27:54'),
(46, 30, 7, 484, NULL, 'sale', 'internal', -2.00, NULL, NULL, '2026-06-12 20:27:54'),
(47, 28, 6, 483, NULL, 'reserve', 'instant', -1.00, NULL, NULL, '2026-06-12 20:28:09'),
(48, 28, 6, 483, NULL, 'sale', 'internal', -1.00, NULL, NULL, '2026-06-12 20:28:09'),
(49, 30, 7, 485, NULL, 'reserve', 'instant', -7.00, NULL, NULL, '2026-06-12 20:34:14'),
(50, 30, 7, 485, NULL, 'sale', 'internal', -7.00, NULL, NULL, '2026-06-12 20:34:14'),
(51, 34, 7, 487, NULL, 'reserve', 'instant', -1.00, NULL, NULL, '2026-06-16 15:50:59'),
(52, 34, 7, 487, NULL, 'sale', 'internal', -1.00, NULL, NULL, '2026-06-16 15:50:59'),
(53, 34, 7, 488, NULL, 'reserve', 'instant', -1.00, NULL, NULL, '2026-06-17 15:37:19'),
(54, 34, 7, 488, NULL, 'sale', 'internal', -1.00, NULL, NULL, '2026-06-17 15:37:19'),
(55, 40, 7, 489, NULL, 'reserve', 'instant', -1.00, NULL, NULL, '2026-06-17 17:09:53'),
(56, 40, 7, 489, NULL, 'sale', 'internal', -1.00, NULL, NULL, '2026-06-17 17:09:53'),
(57, 40, 7, 490, NULL, 'reserve', 'instant', -1.00, NULL, NULL, '2026-06-18 17:01:27'),
(58, 40, 7, 490, NULL, 'sale', 'internal', -1.00, NULL, NULL, '2026-06-18 17:01:27'),
(59, 40, 7, 492, NULL, 'reserve', 'instant', -1.00, NULL, NULL, '2026-06-18 17:06:12'),
(60, 35, 31, 492, NULL, 'reserve', 'instant', -1.00, NULL, NULL, '2026-06-18 17:06:12'),
(61, 40, 7, 492, NULL, 'sale', 'internal', -1.00, NULL, NULL, '2026-06-18 17:06:12'),
(62, 35, 31, 492, NULL, 'sale', 'internal', -1.00, NULL, NULL, '2026-06-18 17:06:12'),
(63, 40, 7, 491, NULL, 'reserve', 'instant', -1.00, NULL, NULL, '2026-06-18 17:06:20'),
(64, 40, 7, 491, NULL, 'sale', 'internal', -1.00, NULL, NULL, '2026-06-18 17:06:21'),
(65, 40, 7, 493, NULL, 'reserve', 'instant', -1.00, NULL, NULL, '2026-06-18 17:06:30'),
(66, 40, 7, 493, NULL, 'sale', 'internal', -1.00, NULL, NULL, '2026-06-18 17:06:30'),
(67, 28, 6, NULL, NULL, 'sale', 'instant', -1.00, NULL, NULL, '2026-07-14 05:23:49'),
(68, 32, 19, NULL, NULL, 'sale', 'instant', -1.00, NULL, NULL, '2026-07-14 05:23:49'),
(69, 39, 7, NULL, NULL, 'sale', 'instant', -1.00, NULL, NULL, '2026-07-14 05:23:49'),
(70, 41, 11, NULL, NULL, 'sale', 'instant', -1.00, NULL, NULL, '2026-07-14 05:23:49'),
(71, 28, 6, NULL, NULL, 'return_to_stock', 'instant', 1.00, NULL, NULL, '2026-07-14 06:16:15'),
(72, 39, 7, NULL, NULL, 'return_to_stock', 'instant', 1.00, NULL, NULL, '2026-07-14 06:16:15'),
(73, 41, 11, NULL, NULL, 'return_to_stock', 'instant', 1.00, NULL, NULL, '2026-07-14 06:16:15'),
(74, 32, 19, NULL, NULL, 'return_to_stock', 'instant', 1.00, NULL, NULL, '2026-07-14 06:16:16'),
(75, 41, 11, NULL, NULL, 'sale', 'instant', -1.00, NULL, NULL, '2026-07-14 06:21:11'),
(76, 32, 19, NULL, NULL, 'sale', 'instant', -1.00, NULL, NULL, '2026-07-14 06:21:11'),
(77, 32, 19, NULL, NULL, 'return_to_stock', 'instant', 1.00, NULL, NULL, '2026-07-14 06:21:31'),
(78, 41, 11, NULL, NULL, 'return_to_stock', 'instant', 1.00, NULL, NULL, '2026-07-14 06:21:38');

-- --------------------------------------------------------

--
-- Структура таблицы `support_chats`
--

CREATE TABLE `support_chats` (
  `id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `order_id` int UNSIGNED DEFAULT NULL,
  `internal_note` text COLLATE utf8mb4_unicode_ci,
  `internal_note_updated_by` int UNSIGNED DEFAULT NULL,
  `internal_note_updated_at` datetime DEFAULT NULL,
  `client_unread_count` int UNSIGNED NOT NULL DEFAULT '0',
  `staff_unread_count` int UNSIGNED NOT NULL DEFAULT '0',
  `last_message_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `support_chats`
--

INSERT INTO `support_chats` (`id`, `user_id`, `order_id`, `internal_note`, `internal_note_updated_by`, `internal_note_updated_at`, `client_unread_count`, `staff_unread_count`, `last_message_at`, `created_at`, `updated_at`) VALUES
(1, 6, NULL, NULL, NULL, NULL, 0, 0, '2026-06-02 22:59:03', '2026-06-02 22:57:19', '2026-06-03 20:57:30'),
(2, 1, 474, NULL, NULL, NULL, 0, 0, '2026-06-03 11:55:40', '2026-06-03 10:24:42', '2026-06-03 12:01:07'),
(3, 1, NULL, NULL, NULL, NULL, 0, 0, '2026-06-03 12:00:47', '2026-06-03 10:25:19', '2026-06-03 12:01:04');

-- --------------------------------------------------------

--
-- Структура таблицы `support_messages`
--

CREATE TABLE `support_messages` (
  `id` int UNSIGNED NOT NULL,
  `chat_id` int UNSIGNED NOT NULL,
  `sender_user_id` int UNSIGNED DEFAULT NULL,
  `sender_name_snapshot` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `body` text COLLATE utf8mb4_unicode_ci,
  `hidden_from_client_at` datetime DEFAULT NULL,
  `hidden_from_client_by` int UNSIGNED DEFAULT NULL,
  `edited_at` datetime DEFAULT NULL,
  `edited_by` int UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `support_messages`
--

INSERT INTO `support_messages` (`id`, `chat_id`, `sender_user_id`, `sender_name_snapshot`, `body`, `hidden_from_client_at`, `hidden_from_client_by`, `edited_at`, `edited_by`, `created_at`) VALUES
(1, 1, 6, 'Анна', '654', NULL, NULL, NULL, NULL, '2026-06-02 22:57:19'),
(2, 1, 6, 'Анна', 'лдороролор', NULL, NULL, NULL, NULL, '2026-06-02 22:59:03'),
(3, 2, 1, 'Юрий', 'пупупу бубубу бебебе', NULL, NULL, NULL, NULL, '2026-06-03 10:24:42'),
(4, 2, 1, 'Юрий', 'пупупу бубубу бебебе', NULL, NULL, NULL, NULL, '2026-06-03 10:24:44'),
(5, 3, 1, 'Юрий', 'бубуб ббебе', NULL, NULL, NULL, NULL, '2026-06-03 10:25:19'),
(6, 2, 1, 'Юрий', 'ререре', NULL, NULL, NULL, NULL, '2026-06-03 10:31:37'),
(7, 2, 17, 'Виктория', 'гугугу', NULL, NULL, NULL, NULL, '2026-06-03 11:55:40'),
(8, 3, 17, 'Виктория', 'Скря', '2026-06-03 19:23:02', 1, '2026-06-09 06:02:36', 1, '2026-06-03 12:00:47');

-- --------------------------------------------------------

--
-- Структура таблицы `support_message_attachments`
--

CREATE TABLE `support_message_attachments` (
  `id` int UNSIGNED NOT NULL,
  `message_id` int UNSIGNED NOT NULL,
  `file_path` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `original_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mime_type` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_size` int UNSIGNED NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `users`
--

CREATE TABLE `users` (
  `id` int UNSIGNED NOT NULL,
  `role` enum('client','admin','courier','manager','partner','seller','buyer') NOT NULL DEFAULT 'client',
  `name` varchar(100) NOT NULL,
  `company_name` varchar(255) DEFAULT NULL,
  `pickup_address` varchar(255) DEFAULT NULL,
  `delivery_cost` decimal(10,2) DEFAULT '0.00',
  `work_mode` enum('berrygo_store','own_store','warehouse_delivery') NOT NULL DEFAULT 'berrygo_store',
  `phone` varchar(20) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `email_verified_at` datetime DEFAULT NULL,
  `email_verification_token_hash` varchar(255) DEFAULT NULL,
  `email_verification_expires_at` datetime DEFAULT NULL,
  `telegram_id` bigint DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL DEFAULT '',
  `referral_code` varchar(20) DEFAULT NULL,
  `referred_by` int UNSIGNED DEFAULT NULL,
  `has_used_referral_coupon` tinyint(1) NOT NULL DEFAULT '0',
  `points_balance` int NOT NULL DEFAULT '0',
  `rub_balance` int NOT NULL DEFAULT '0',
  `is_blocked` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `chat_id` bigint DEFAULT NULL,
  `subscribed_notifications` varchar(255) DEFAULT '',
  `address_id` int UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Дамп данных таблицы `users`
--

INSERT INTO `users` (`id`, `role`, `name`, `company_name`, `pickup_address`, `delivery_cost`, `work_mode`, `phone`, `email`, `email_verified_at`, `email_verification_token_hash`, `email_verification_expires_at`, `telegram_id`, `password_hash`, `referral_code`, `referred_by`, `has_used_referral_coupon`, `points_balance`, `rub_balance`, `is_blocked`, `created_at`, `chat_id`, `subscribed_notifications`, `address_id`) VALUES
(1, 'admin', 'Юрий', NULL, NULL, 0.00, 'berrygo_store', '79029237794', NULL, NULL, NULL, NULL, NULL, '$2y$10$jT2s5aQO.CUrusOJHh.BPeMmJnxzH7wBvcZgk.h7MUYtKkff1Zibu', 'H7K2M9P1', NULL, 0, 0, 0, 0, '2025-05-17 22:37:01', NULL, '', NULL),
(6, 'partner', 'Анна', NULL, NULL, 0.00, 'berrygo_store', '79233159564', NULL, NULL, NULL, NULL, NULL, '$2y$10$/XnwoKA0VuCjIi/.AR66kO0.IWX5nfEAECEgrfimBRwOCm7fAr7Rm', 'N3X5R2B7', 1, 0, 0, 0, 0, '2025-05-22 18:56:33', NULL, '', NULL),
(16, 'client', 'Светлана', NULL, NULL, 0.00, 'berrygo_store', '79832853929', NULL, NULL, NULL, NULL, NULL, '$2y$10$2fFRK0h3wPIvCo/Nfv7iyOztI/TMR5ZeaDVtWIfRP2OR/BOVLEkwu', 'JSFG9FVG', 17, 0, 0, 0, 0, '2025-06-16 19:42:20', NULL, '', NULL),
(17, 'manager', 'Виктория', '', '', 0.00, 'berrygo_store', '79535829980', NULL, NULL, NULL, NULL, NULL, '$2y$10$BD65/G5GJiARK/hhCsPRJuB/TYR89tHd9Pd8D2FuGwEXMaL07yedS', '7WLNVMT8', NULL, 0, 1227, 0, 0, '2025-06-22 14:14:16', NULL, '', NULL),
(18, 'client', 'Анна', NULL, NULL, 0.00, 'berrygo_store', '79954846102', NULL, NULL, NULL, NULL, NULL, '$2y$10$52kFiHQVNN0aWTIS/unDo./xopBHoti2erKZnbJh9CdLvB33JIOam', 'LBMVMFF6', 17, 0, 0, 0, 0, '2025-06-25 19:01:01', NULL, '', NULL),
(25, 'client', 'Татьяна', NULL, NULL, 0.00, 'berrygo_store', '79233295055', NULL, NULL, NULL, NULL, NULL, '$2y$10$VRXz4oCG.cIRczirtoK7/OG.UbolPOH3oXAqBAcFEebcxGCvdCmgu', 'B7DKBGV3', 17, 0, 0, 0, 0, '2025-07-23 09:51:32', NULL, '', NULL),
(36, 'client', 'Ольга', NULL, NULL, 0.00, 'berrygo_store', '79509736046', NULL, NULL, NULL, NULL, NULL, '$2y$10$VuJg9YVEYt4B2264dyOJBu2E4XyxxTZdLUMF1V6UMLnNKk1UeVlfq', 'E2Y7347Y', 17, 0, 0, 0, 0, '2025-07-25 22:44:58', NULL, '', NULL),
(37, 'client', 'Андрей', NULL, NULL, 0.00, 'berrygo_store', '79831556117', NULL, NULL, NULL, NULL, NULL, '$2y$10$N7.dzJ6HPSMmvD.kueJ8Q.6y9ByEBCl6l1piHRWgg5lDsHwn7dIkK', 'EKCMPSKQ', 17, 0, 0, 0, 0, '2025-07-25 22:47:32', NULL, '', NULL),
(38, 'client', 'Евгения', NULL, NULL, 0.00, 'berrygo_store', '79607691702', NULL, NULL, NULL, NULL, NULL, '$2y$10$uM6QQgT1Nn978Yx/Ef6bTeBzTHpMMRqv90LgMFZotaUO0DUgrJ4By', 'NWBKWYWJ', 17, 0, 0, 0, 0, '2025-07-25 22:50:11', NULL, '', NULL),
(41, 'partner', 'Вячеслав', NULL, NULL, 0.00, 'berrygo_store', '79230195349', NULL, NULL, NULL, NULL, NULL, '$2y$10$1rmX2r/FOWd3wV1G2snxEuU34x0QSFq4uU7ev2dCWN209RDv3FNHK', 'NQVVMV5E', 17, 0, 12723, 0, 0, '2025-07-26 08:15:29', NULL, '', NULL),
(42, 'client', 'Светлана', NULL, NULL, 0.00, 'berrygo_store', '79620847993', NULL, NULL, NULL, NULL, NULL, '$2y$10$pqyj7vYKDr4yL59PSuQSvu3XJABDkJhDYJgX0h8GAxTY0kU6HAJtu', 'V9FCMF97', 17, 0, 0, 0, 0, '2025-07-26 08:33:36', NULL, '', NULL),
(43, 'client', 'Ефим', NULL, NULL, 0.00, 'berrygo_store', '79333172557', NULL, NULL, NULL, NULL, NULL, '$2y$10$.BCDpzaB6PtaV2Yn0Ai9Qe6jkalBul2uYMi1/vK3OqmoyYIbODcH.', 'H9UHMRZU', 41, 0, 0, 0, 0, '2025-07-26 08:38:32', NULL, '', NULL),
(44, 'client', 'Александра', NULL, NULL, 0.00, 'berrygo_store', '79233170979', NULL, NULL, NULL, NULL, NULL, '$2y$10$MEeTREgNQDBs5Li9cLPr7ezsoc5IhS9G7UxNEjGeMEI1Xc0TZ.56S', 'PXMN3XRF', 41, 0, 0, 0, 0, '2025-07-26 08:41:33', NULL, '', NULL),
(45, 'client', 'Наталья', NULL, NULL, 0.00, 'berrygo_store', '79830777206', NULL, NULL, NULL, NULL, NULL, '$2y$10$bR9IvOqWXTmz5puOFN9xwOgmA6QnenXUKjc9.fakJbdURkV3eQ17.', '6SJMM27D', 41, 0, 0, 0, 0, '2025-07-26 08:44:38', NULL, '', NULL),
(46, 'client', 'Ирина', NULL, NULL, 0.00, 'berrygo_store', '79233364624', NULL, NULL, NULL, NULL, NULL, '$2y$10$RVAZoB6nT1nLUs1WnztVB.NAZqXI.2pZFAR2BGG54yuaMZE1Vrcc2', 'UBLX286V', 41, 0, 0, 0, 0, '2025-07-26 08:48:28', NULL, '', NULL),
(47, 'client', 'Марина', NULL, NULL, 0.00, 'berrygo_store', '79994426487', NULL, NULL, NULL, NULL, NULL, '$2y$10$rzOjnALHFs1UojCjcNwqn.6dZaTgbMy2QSyaVTP.QWXkGfKW5ZUY.', 'W7TU54PV', 6, 0, 0, 0, 0, '2025-07-27 09:26:27', NULL, '', NULL),
(48, 'client', 'Наталья', NULL, NULL, 0.00, 'berrygo_store', '79082134737', NULL, NULL, NULL, NULL, NULL, '$2y$10$OhsxcXQYToDBdVoNeX.VPemD88z.mJs.kGhbcO5eDrReZlNDwJnC6', 'XY9427S5', 6, 0, 0, 0, 0, '2025-07-27 09:28:31', NULL, '', NULL),
(49, 'client', 'Марина', NULL, NULL, 0.00, 'berrygo_store', '79179875542', NULL, NULL, NULL, NULL, NULL, '$2y$10$ZxR0A9TSGwjH6p89ZfKy/exMwDrzWdfvfm9YkfTj8p9sU59I3PPSS', 'GHE4CWMD', 6, 0, 0, 0, 0, '2025-07-27 09:31:10', NULL, '', NULL),
(51, 'client', 'Светлана', NULL, NULL, 0.00, 'berrygo_store', '79607656831', NULL, NULL, NULL, NULL, NULL, '$2y$10$znPzlVeDBNcZ4kce4iozVebE3ewarQrQmAoLJpyb9ZZBl4dy6xMPe', 'LND6FQP3', 17, 0, 0, 0, 0, '2025-07-29 16:16:02', NULL, '', NULL),
(52, 'client', 'Дмитрий', NULL, NULL, 0.00, 'berrygo_store', '79230626949', NULL, NULL, NULL, NULL, NULL, '$2y$10$I1Ol5fnB1DKpTLT3aA2OEOKBl9h/Mx6wggnHdFa0jhoz0bZ.Z.dhW', 'SM7AQL7V', 17, 0, 0, 0, 0, '2025-07-29 16:18:17', NULL, '', NULL),
(53, 'client', 'Арина', NULL, NULL, 0.00, 'berrygo_store', '79509651124', NULL, NULL, NULL, NULL, NULL, '$2y$10$UPN1KApsrxEbqfxI3L/eeOvC9wCQeDdIq17ndbGDJckTVdDMmGiES', 'MAMLL5GK', 17, 0, 0, 0, 0, '2025-08-02 08:20:50', NULL, '', NULL),
(54, 'client', 'Руслан', NULL, NULL, 0.00, 'berrygo_store', '79832986050', NULL, NULL, NULL, NULL, NULL, '$2y$10$r7RSC.01M42SOE.rCViELedREE9ipKCWZEdMuatyNHiudO9c50J1O', 'ZYGJFDMP', 17, 0, 0, 0, 0, '2025-08-02 10:18:17', NULL, '', NULL),
(55, 'client', 'Юлия', NULL, NULL, 0.00, 'berrygo_store', '79135258594', NULL, NULL, NULL, NULL, NULL, '$2y$10$ojMue3oZaezKZfUZwk6qmuLL2kmHY/9QTWtT5e5.hM4A2HNkD2BiG', 'HC6TPPCY', 17, 0, 0, 0, 0, '2025-08-02 10:28:14', NULL, '', NULL),
(56, 'client', 'Ирина', NULL, NULL, 0.00, 'berrygo_store', '79535807417', NULL, NULL, NULL, NULL, NULL, '$2y$10$LqOFsmF1V5dSWE4LLAwaqe0irshzP./NCiPUgTP5jvPhG7GN3t7Ou', 'TU6NZRBR', 17, 0, 0, 0, 0, '2025-08-02 10:54:22', NULL, '', NULL),
(57, 'client', 'Светлана', NULL, NULL, 0.00, 'berrygo_store', '79080213253', NULL, NULL, NULL, NULL, NULL, '$2y$10$2niCu9ujrQVix8SeelZHV.KqoYfproEVlIqNJI2CmyekuBJosZCSK', 'YAD6ZCH6', 17, 0, 0, 0, 0, '2025-08-02 11:57:46', NULL, '', NULL),
(58, 'client', 'Светлана', NULL, NULL, 0.00, 'berrygo_store', '79332001299', NULL, NULL, NULL, NULL, NULL, '$2y$10$jPrxsEIMldjtLUicgd2GsedvZhVPZ5HbofJ0I2U2k58.2YOseUf46', '76AVAJNL', 41, 0, 0, 0, 0, '2025-08-03 09:55:05', NULL, '', NULL),
(59, 'client', 'Ксения', NULL, NULL, 0.00, 'berrygo_store', '79059998100', NULL, NULL, NULL, NULL, NULL, '$2y$10$AU7gdI1o8qsSTnblKHhYquv0pibN223HgoHJ2RV24CwUndfuOzjVm', 'U8URS2JX', 41, 0, 0, 0, 0, '2025-08-03 09:57:47', NULL, '', NULL),
(60, 'client', 'Наталья', NULL, NULL, 0.00, 'berrygo_store', '79233774413', NULL, NULL, NULL, NULL, NULL, '$2y$10$dfQ7gYesIW9tahwKqtzNfurM1G3wGJAx8lCOF7GZqKVz5HwJJMqfq', 'HFSRTQDR', 41, 0, 0, 0, 0, '2025-08-03 10:03:13', NULL, '', NULL),
(61, 'client', 'Владимир', NULL, NULL, 0.00, 'berrygo_store', '79599959852', NULL, NULL, NULL, NULL, NULL, '$2y$10$8BuYA/ZXnN1H.hUakicO2uT0Oq2BWbYgl9WdODZ55CeCkowykdDoO', '7AQ3LUC9', 41, 0, 0, 0, 0, '2025-08-03 10:05:27', NULL, '', NULL),
(62, 'client', 'Татьяна', NULL, NULL, 0.00, 'berrygo_store', '79029411930', NULL, NULL, NULL, NULL, NULL, '$2y$10$H4TlxELrVr3GtdNPSeQzru7UHs.3cu5oT3JDMhPC8Ujg4/YLgZjfS', '55CU7LHQ', 41, 0, 0, 0, 0, '2025-08-03 10:46:47', NULL, '', NULL),
(63, 'client', 'Ольга', NULL, NULL, 0.00, 'berrygo_store', '79509885558', NULL, NULL, NULL, NULL, NULL, '$2y$10$35k7ZTXYHfsue61PVjD1NOfijNKp.GMrnqMSLn2OUIoitExvQ0x.G', 'VD8ND3WN', 41, 0, 0, 0, 0, '2025-08-03 14:40:45', NULL, '', NULL),
(64, 'client', 'Иннокентий', NULL, NULL, 0.00, 'berrygo_store', '79964300303', NULL, NULL, NULL, NULL, NULL, '$2y$10$xQTUWJ2DPjuM9H17ba3d9u8Ni99tdaFDuJ0F9O4uTwtmlQhdyEJ8.', 'CNRFT99R', 1, 0, 0, 0, 0, '2025-08-03 15:25:21', NULL, '', NULL),
(65, 'client', 'Юлиана', NULL, NULL, 0.00, 'berrygo_store', '79029788482', NULL, NULL, NULL, NULL, NULL, '$2y$10$4XMAECIg7kFA2q9N9RQ2Ruy7qp906EVs7qKvL8nIC2Xegw6Rt2Wry', '5BX397RH', 41, 0, 0, 0, 0, '2025-08-03 17:14:35', NULL, '', NULL),
(66, 'client', 'Александра', NULL, NULL, 0.00, 'berrygo_store', '79026864174', NULL, NULL, NULL, NULL, NULL, '$2y$10$gZKDCoJTcZ5mbGP/x/ooMO6RiuxS/m.Mkeai/9na3TF.5ytKamzHG', 'VZDYANFW', NULL, 0, 0, 0, 0, '2025-08-03 21:48:16', NULL, '', NULL),
(67, 'client', 'Залина', NULL, NULL, 0.00, 'berrygo_store', '79135129595', NULL, NULL, NULL, NULL, NULL, '$2y$10$I5hM1JJ6ZPsh33LhQjxGDudJnpLDBqRWJtkkd2PkT/q7oDG2k/w2e', 'LAHTM7SK', 1, 0, 0, 0, 0, '2025-08-04 08:03:19', NULL, '', NULL),
(68, 'client', 'Клиент', NULL, NULL, 0.00, 'berrygo_store', '79631918160', NULL, NULL, NULL, NULL, NULL, '$2y$10$DMUYoyH/g7/SJ3pnd/ggYOj3zCq6yb4z9gOlxwKox62VnBuD/rECG', 'CA83W8MD', 41, 0, 0, 0, 0, '2025-08-04 08:17:13', NULL, '', NULL),
(69, 'client', 'Клиент', NULL, NULL, 0.00, 'berrygo_store', '79135392645', NULL, NULL, NULL, NULL, NULL, '$2y$10$vI.8jZ070BB.zMqaFi5bROEvGdDm5v290aLP1ate2.nBJ1ki5kkHu', 'UDHNSJXH', 41, 0, 0, 0, 0, '2025-08-04 09:57:13', NULL, '', NULL),
(70, 'client', 'Кристина', NULL, NULL, 0.00, 'berrygo_store', '79131754069', NULL, NULL, NULL, NULL, NULL, '$2y$10$yvYFA2EUlhPgAyVhf14B6.VKFQ6ZYInm6Q6b1FSNpxt8JEpdGzAOO', '3MAVDK6B', 41, 0, 0, 0, 0, '2025-08-04 10:22:39', NULL, '', NULL),
(71, 'client', 'Екатерина', NULL, NULL, 0.00, 'berrygo_store', '79039225233', NULL, NULL, NULL, NULL, NULL, '$2y$10$TnEg2QcdnT8sBV.U4qWxquuvg5Y3DnW.oD45AyPcudIS6oD8X3GrW', 'M236XR86', 41, 0, 0, 0, 0, '2025-08-04 11:18:48', NULL, '', NULL),
(72, 'client', 'Светлана', NULL, NULL, 0.00, 'berrygo_store', '79333379547', NULL, NULL, NULL, NULL, NULL, '$2y$10$vO2z40I0svZb.BYm01vZOunOe.HRxOfS7UaZI75BFtxSW9nhnOzZu', '9GNFQGY2', 41, 0, 0, 0, 0, '2025-08-04 16:40:25', NULL, '', NULL),
(73, 'client', 'Евгений', NULL, NULL, 0.00, 'berrygo_store', '79039241950', NULL, NULL, NULL, NULL, NULL, '$2y$10$os/EslxmDU4pJFSLK0VwPOgqnWrTi62XlIA2l.vnJPUGnaAki6bzi', 'BP4HT7P3', 41, 0, 0, 0, 0, '2025-08-04 16:47:13', NULL, '', NULL),
(74, 'client', 'Клиент', NULL, NULL, 0.00, 'berrygo_store', '79135204651', NULL, NULL, NULL, NULL, NULL, '$2y$10$5m4V/mM838OKYgFmXgTgb.IOE5mMQijSd3Ai0B4jutR0B4LI0zpJa', 'SUAYV27W', 41, 0, 0, 0, 0, '2025-08-05 08:03:25', NULL, '', NULL),
(75, 'client', 'Елена', NULL, NULL, 0.00, 'berrygo_store', '79233692449', NULL, NULL, NULL, NULL, NULL, '$2y$10$Jv8PVpwWcFZFHS2CnYf8JeDIjgCpXbt8HCwgqmhK7C6kkCwUhi9Gi', 'NE9LCN3A', 41, 0, 0, 0, 0, '2025-08-05 08:24:35', NULL, '', NULL),
(76, 'client', 'Татьяна', NULL, NULL, 0.00, 'berrygo_store', '79509935445', NULL, NULL, NULL, NULL, NULL, '$2y$10$VnJMR6X/RfQWP.XS74e9E.5p2fLr8T2D8k12pQ6fsYCm7M8mN8r/u', 'H6PHJWU2', 41, 0, 0, 0, 0, '2025-08-05 09:50:16', NULL, '', NULL),
(77, 'client', 'Елена', NULL, NULL, 0.00, 'berrygo_store', '79836168751', NULL, NULL, NULL, NULL, NULL, '$2y$10$PgCWSOQoFSJW1ulURV8JNeJqkH3qmnmRRxvW4W3Ng91XSSRfPYNZG', 'TRB98LS6', 41, 0, 0, 0, 0, '2025-08-05 10:14:03', NULL, '', NULL),
(78, 'client', 'Юлия', NULL, NULL, 0.00, 'berrygo_store', '79256610460', NULL, NULL, NULL, NULL, NULL, '$2y$10$Kz18l3Cah7FEuXBHsoe0K.LE6/OaJDQSRstJFsw5hgc0O7THKx4y6', 'LCFQBD9G', 1, 0, 55, 0, 0, '2025-08-05 12:38:15', NULL, '', NULL),
(79, 'client', 'Анна', NULL, NULL, 0.00, 'berrygo_store', '79831436880', NULL, NULL, NULL, NULL, NULL, '$2y$10$JRRCU03Lpjmdt/Dv3PhU8OF84yeS5e2E8vUm/gcqRIzXW1vUh/2I.', 'NZ46PYDN', 41, 0, 0, 0, 0, '2025-08-05 20:00:13', NULL, '', NULL),
(80, 'client', 'Екатерина', NULL, NULL, 0.00, 'berrygo_store', '79130514209', NULL, NULL, NULL, NULL, NULL, '$2y$10$RLHARe8xfzO45WQjUwGdpe7tgoK/e.sfvkl9vk8m.UAutNVF993kG', 'GQZHGLRV', 41, 0, 0, 0, 0, '2025-08-06 09:54:27', NULL, '', NULL),
(81, 'client', 'Гоар', NULL, NULL, 0.00, 'berrygo_store', '79831572234', NULL, NULL, NULL, NULL, NULL, '$2y$10$/n41jfu0a8BiESmMPHQvPOPDWUnA.LyvX3XWr1elV7wGHKcljzvsi', '47CZTQWU', 41, 0, 0, 0, 0, '2025-08-06 10:54:17', NULL, '', NULL),
(82, 'client', 'Вадим', NULL, NULL, 0.00, 'berrygo_store', '79069170626', NULL, NULL, NULL, NULL, NULL, '$2y$10$GuIYZ7TujGGehmDruN1EW.OAmINwWgrlfGpLo4itTYacaPmZYjhl2', 'PUQEC7NS', 41, 0, 0, 0, 0, '2025-08-06 10:57:50', NULL, '', NULL),
(83, 'client', 'Мария', NULL, NULL, 0.00, 'berrygo_store', '79233368885', NULL, NULL, NULL, NULL, NULL, '$2y$10$qO1ckKNP0NC5gqiY3P9n9O9Mditb9UmNBzdSmIxoNgNF88iKMFqeS', 'CVXT6JQQ', 41, 0, 0, 0, 0, '2025-08-06 11:31:03', NULL, '', NULL),
(84, 'client', 'Елена', NULL, NULL, 0.00, 'berrygo_store', '79504331949', NULL, NULL, NULL, NULL, NULL, '$2y$10$6lYoLpL6AtDjv8RRNO1MiusBGliriXOKU2DW0diEL4CCAqG9u0nti', 'DQJFMT5G', 41, 0, 0, 0, 0, '2025-08-06 11:35:54', NULL, '', NULL),
(85, 'client', 'Клиент', NULL, NULL, 0.00, 'berrygo_store', '79233242337', NULL, NULL, NULL, NULL, NULL, '$2y$10$PbeOBkMpRzbrPqr0gWnNuO60HZFlrF156iMBC61vPMKENveLFfCPG', 'GYZDP5QH', 41, 0, 0, 0, 0, '2025-08-06 15:22:21', NULL, '', NULL),
(86, 'client', 'Евгений', NULL, NULL, 0.00, 'berrygo_store', '79233008808', NULL, NULL, NULL, NULL, NULL, '$2y$10$XfcgUHrEDpJ48gaBBEKWQ.aeBcgznis0sHXCyTgUdIkeQ0mTNvmAa', 'CM9THHP4', 1, 0, 0, 0, 0, '2025-08-06 18:36:10', NULL, '', NULL),
(87, 'client', 'Наталья', NULL, NULL, 0.00, 'berrygo_store', '79333303749', NULL, NULL, NULL, NULL, NULL, '$2y$10$4HxdeMW6bUC42JQh5bplr.yC/b3LfcQHZix82EILcB1xLQI1MNCp.', 'FXV65YDV', 41, 0, 0, 0, 0, '2025-08-06 19:30:45', NULL, '', NULL),
(88, 'client', 'Равиль', NULL, NULL, 0.00, 'berrygo_store', '79135070008', NULL, NULL, NULL, NULL, NULL, '$2y$10$dmu2YqBG6dYgGY1w/6cgYOFRycCEXc4f2KAzlIfc05KfP3pIbW27C', 'YN3YMJ22', 41, 0, 0, 0, 0, '2025-08-07 05:47:10', NULL, '', NULL),
(89, 'client', 'Клиент', NULL, NULL, 0.00, 'berrygo_store', '79069692927', NULL, NULL, NULL, NULL, NULL, '$2y$10$KPdQTh.TMfGc1MCKG4BQU.0nccWwECTXwhOtwqHHgZb6kwY48vIQW', 'E8NXPYE4', 41, 0, 0, 0, 0, '2025-08-07 09:04:38', NULL, '', NULL),
(90, 'client', 'Надежда', NULL, NULL, 0.00, 'berrygo_store', '79293080447', NULL, NULL, NULL, NULL, NULL, '$2y$10$xI7GXs6qBX3BP.mq6CPS0uWt70XS4QB3n/K2IlDXKQBmlDUkkCtQm', 'VNPEKNEX', 41, 0, 0, 0, 0, '2025-08-07 11:21:28', NULL, '', NULL),
(91, 'client', 'Марина', NULL, NULL, 0.00, 'berrygo_store', '79232776782', NULL, NULL, NULL, NULL, NULL, '$2y$10$00MeHpR0XUPZOEnrPCUz5ezXZXmk26qYhvukrZduA0bIxqvGEMJYq', '6N5T6F75', 41, 0, 0, 0, 0, '2025-08-07 12:03:03', NULL, '', NULL),
(92, 'client', 'Полина', NULL, NULL, 0.00, 'berrygo_store', '79069120828', NULL, NULL, NULL, NULL, NULL, '$2y$10$O8o2oOURt4shr69ObEOX.eiFCpuyoztk3QTjGPBaS/1ccarxEoLRm', 'ZEWLGFFG', 41, 0, 0, 0, 0, '2025-08-08 05:56:49', NULL, '', NULL),
(93, 'client', 'Александр', NULL, NULL, 0.00, 'berrygo_store', '79135942097', NULL, NULL, NULL, NULL, NULL, '$2y$10$05.YobFTw8RiEhk./3bfEObzlcM2cN1FhdnYAmUzxIoeEeM0aGMea', 'AJ5CFSE8', 41, 0, 0, 0, 0, '2025-08-08 06:42:30', NULL, '', NULL),
(94, 'client', 'Клиент', NULL, NULL, 0.00, 'berrygo_store', '79964302528', NULL, NULL, NULL, NULL, NULL, '$2y$10$IqdcvWCBx0l85ox34eZ.BeauuTXUdGdGKO/3pBojpk5zYxXy2CVoC', 'JV5ELCPG', 41, 0, 0, 0, 0, '2025-08-08 07:39:16', NULL, '', NULL),
(95, 'client', 'Клиент', NULL, NULL, 0.00, 'berrygo_store', '79131700328', NULL, NULL, NULL, NULL, NULL, '$2y$10$q9SEBO7dXDzOin62lEqjd.CEaxoC7AvFDH9CrNbiavFsBx4PwsPeS', 'NK32AXBD', NULL, 1, 378, 0, 0, '2025-08-08 08:34:22', NULL, '', NULL),
(96, 'client', 'Ксения', NULL, NULL, 0.00, 'berrygo_store', '79232780764', NULL, NULL, NULL, NULL, NULL, '$2y$10$LDuL4qX.VYCOIGnndvkkHuiXbmIZ52LEGY1vJzzHgFZaDgw7tvMVa', 'WQV8WS64', 41, 0, 0, 0, 0, '2025-08-08 08:40:20', NULL, '', NULL),
(97, 'client', 'Ольга', NULL, NULL, 0.00, 'berrygo_store', '79620711423', NULL, NULL, NULL, NULL, NULL, '$2y$10$GEhCSIc3J4vEUWLqaJbC0O87lE/0ywvVAUZMdY4A6DX9JRoYT.Aim', '3Z35XUQS', 41, 0, 0, 0, 0, '2025-08-08 08:50:51', NULL, '', NULL),
(98, 'client', 'Коиент', NULL, NULL, 0.00, 'berrygo_store', '79509708812', NULL, NULL, NULL, NULL, NULL, '$2y$10$6MZxyD2FS8pI8yEw517VO.FplG/X1p07CASGZLQM1jhnGWszzUNKW', 'PNQFW8YM', 41, 0, 0, 0, 0, '2025-08-08 11:21:28', NULL, '', NULL),
(99, 'client', 'Владимир', NULL, NULL, 0.00, 'berrygo_store', '79333313110', NULL, NULL, NULL, NULL, NULL, '$2y$10$hQNg2gcgZbkQLq52deARu.tWRupNnBKmQOirGz/nY4Z5P0Nu.h9CS', 'GP6YCXC6', 41, 0, 0, 0, 0, '2025-08-08 11:31:05', NULL, '', NULL),
(100, 'client', 'Олеся', NULL, NULL, 0.00, 'berrygo_store', '79135170799', NULL, NULL, NULL, NULL, NULL, '$2y$10$5fbXJVv/2qHSkqD6IAI/BeuxtOT/Eq.Dcp6CP32GoZ6VgfihXFOYa', 'LQBYKWZ3', 41, 0, 0, 0, 0, '2025-08-08 12:05:51', NULL, '', NULL),
(101, 'client', 'Клиент', NULL, NULL, 0.00, 'berrygo_store', '79241629757', NULL, NULL, NULL, NULL, NULL, '$2y$10$RZ6ICnyW4ZjkLRU39uf6RescRlpDoX.15yKgMU9mfH6rXglw7Iche', '9AMUXUTP', 41, 0, 0, 0, 0, '2025-08-08 13:30:13', NULL, '', NULL),
(102, 'client', 'Екатерина', NULL, NULL, 0.00, 'berrygo_store', '79832689108', NULL, NULL, NULL, NULL, NULL, '$2y$10$PInZCVccWtNjA6G1RKAZoumk8QX9jUBTIic.ryxB1K4lidMX7AcV2', '357AQQGA', 41, 0, 0, 0, 0, '2025-08-08 15:12:29', NULL, '', NULL),
(103, 'client', 'Ольга', NULL, NULL, 0.00, 'berrygo_store', '79617423305', NULL, NULL, NULL, NULL, NULL, '$2y$10$YsWGFKyQrqwXY9I2jqhsMe9XeAFpXO2TjGCTkp8SYiVy7wMHAQqVG', 'ZH4J6HFX', 41, 0, 165, 0, 0, '2025-08-09 10:18:07', NULL, '', NULL),
(104, 'client', 'Екатерина', NULL, NULL, 0.00, 'berrygo_store', '79237846153', NULL, NULL, NULL, NULL, NULL, '$2y$10$t517TMwtYysNFi6OVShyCelUM5cwO41YNXOgUuHDh37vVJTEE2EYG', '9UJSDMTV', 41, 0, 135, 0, 0, '2025-08-09 10:57:01', NULL, '', NULL),
(105, 'client', 'Павел', NULL, NULL, 0.00, 'berrygo_store', '79131898747', NULL, NULL, NULL, NULL, NULL, '$2y$10$9zH4B6Z46mos6f/PQISgTeVrHF/ngg4Rai4NKsMq1ej.REG8MHgVC', '37LRDKTN', 41, 0, 0, 0, 0, '2025-08-09 19:17:22', NULL, '', NULL),
(106, 'client', 'Елена', NULL, NULL, 0.00, 'berrygo_store', '79135187295', NULL, NULL, NULL, NULL, NULL, '$2y$10$pDFlpbInbKtoIaGDNYh3HujBLI5Q1wOR91lTs49iXiEC0EXC/vQb.', 'V85EUQBH', 41, 0, 55, 0, 0, '2025-08-09 19:18:08', NULL, '', NULL),
(107, 'client', 'Людмила', NULL, NULL, 0.00, 'berrygo_store', '79025505385', NULL, NULL, NULL, NULL, NULL, '$2y$10$GU3P51wiTD.EH/RjlSWMG.P0rBkkhqreVB6bQoMaTukVdok4TYIMy', '3U9A957V', 17, 0, 240, 0, 0, '2025-08-12 16:25:18', NULL, '', NULL),
(108, 'seller', 'Юрий', 'krasflowers', '9 мая 73', 0.00, 'berrygo_store', '79535880614', NULL, NULL, NULL, NULL, NULL, '$2y$10$dQxt9gyPWBk5bA7pV7Q7JuihtRtYIFIDbNFnvnwUJOAA9coPbC/cK', 'MHC8TGNJ', 1, 1, 0, 1050, 0, '2025-08-20 03:59:50', NULL, '', NULL),
(109, 'client', 'Юлия', '', '', 0.00, 'berrygo_store', '79135735517', NULL, NULL, NULL, NULL, NULL, '$2y$10$58UKnTT5oHRCl/6FV.OG9O5RD8cszZjWfOQqRZ9ODblkp9k4q0ctS', '535M97SR', 17, 1, 223, 0, 0, '2025-08-20 09:49:07', NULL, '', NULL),
(111, 'client', 'Наталья', '', '', 0.00, 'berrygo_store', '79959336599', NULL, NULL, NULL, NULL, NULL, '$2y$10$0ybJeoOyoCUAI2Rl2wT8DeZqwWYLi7xKu0fSUfhwOJ9poe3FJZH46', 'HDLJ2HVJ', 17, 0, 0, 0, 0, '2025-08-20 11:30:10', NULL, '', NULL),
(112, 'client', 'Клиент', NULL, NULL, 0.00, 'berrygo_store', '79082196397', NULL, NULL, NULL, NULL, NULL, '$2y$10$Xho5kyt89e0UPF8Ui2SmdeuhCW2qd9vQeLm8Nqc.9tdWuwjXta5zi', 'AM6HBVZU', 41, 0, 690, 0, 0, '2025-08-23 15:48:10', NULL, '', NULL),
(113, 'client', 'Клиент', NULL, NULL, 0.00, 'berrygo_store', '79233034299', NULL, NULL, NULL, NULL, NULL, '$2y$10$8VzbLlrcZWjZFOR/Jrp20.unIZ356BSCV49tDRUVApNe0r2N5fCea', 'VNQT4BYV', 41, 0, 55, 0, 0, '2025-08-23 15:49:06', NULL, '', NULL),
(114, 'client', 'Ирина', NULL, NULL, 0.00, 'berrygo_store', '79233387774', NULL, NULL, NULL, NULL, NULL, '$2y$10$o3ZnZUQacq1epN..lUBQ/er66NlTMDvLX0Ez72TH6pN4cMbXWVoyy', '4JTFGDAM', 41, 0, 60, 0, 0, '2025-08-23 15:51:39', NULL, '', NULL),
(115, 'client', 'Анастасия', NULL, NULL, 0.00, 'berrygo_store', '79504277353', NULL, NULL, NULL, NULL, NULL, '$2y$10$GxCmDaO0a.2u3PNxovOgvespZHXPIlGwf.ZSvzVVOH4Jto3EmwPcW', 'AHHPAEY6', 41, 0, 70, 0, 0, '2025-08-24 14:05:12', NULL, '', NULL),
(116, 'client', 'Юрий', NULL, NULL, 0.00, 'berrygo_store', '79853315903', NULL, NULL, NULL, NULL, NULL, '$2y$10$orlnL/VdoRrhj3GKCg.lROauiO.w4h/9Hcgf4yr1lAMT.jdr15zxe', 'JHX9RP9T', 41, 0, 71, 0, 0, '2025-08-25 15:33:25', NULL, '', NULL),
(117, 'client', 'Анна', NULL, NULL, 0.00, 'berrygo_store', '79535916848', NULL, NULL, NULL, NULL, NULL, '$2y$10$IOvfL.soNjpfsrlUXl1wK.5uRb02Hz.VjhX8bGubVlmvu6pg03Hey', '8RTHRW43', 17, 0, 115, 0, 0, '2025-08-26 18:32:54', NULL, '', NULL),
(118, 'client', 'Елена', NULL, NULL, 0.00, 'berrygo_store', '79293207718', NULL, NULL, NULL, NULL, NULL, '$2y$10$p6gQOLoUcKShYT4LPPQ0vuqg2JYPmAklNZx5rG4U5/HE4bcJx4KaO', 'YJ8LXX3X', 17, 0, 375, 0, 0, '2025-08-26 18:36:59', NULL, '', NULL),
(119, 'client', 'Светлана', NULL, NULL, 0.00, 'berrygo_store', '79831653630', NULL, NULL, NULL, NULL, NULL, '$2y$10$AVlX9lhvI8gSfsHucxMZIeTjlvUfUevMVqukmRlPGhwmJ9ws.pf4m', 'ZBFRNV32', 17, 1, 99, 0, 0, '2025-08-26 18:48:24', NULL, '', NULL),
(120, 'client', 'Андрей', NULL, NULL, 0.00, 'berrygo_store', '79538509450', NULL, NULL, NULL, NULL, NULL, '$2y$10$2lugFRBvTpy3HhQbIZQ7wOz1jdyQOyF2i4zNLBvoE2ONnmYHSA9Ba', 'ZT5U4WST', NULL, 0, 135, 0, 0, '2025-08-27 17:37:25', NULL, '', NULL),
(121, 'client', 'Марина', NULL, NULL, 0.00, 'berrygo_store', '79048987883', NULL, NULL, NULL, NULL, NULL, '$2y$10$qeYnAABaqghAlwT2y4f6nOYHvhk9HXQNCkN2M/42mdEmP1pk0Byke', '5U3JUQ3B', NULL, 0, 75, 0, 0, '2025-08-28 06:42:25', NULL, '', NULL),
(122, 'client', 'Анастасия', NULL, NULL, 0.00, 'berrygo_store', '79293392654', NULL, NULL, NULL, NULL, NULL, '$2y$10$HcQvKVdCyHBPX.A4JxN5GeFezghMsahniVD33TSodEMQyFB6BI.vm', 'CNEE9XRU', NULL, 1, 890, 0, 0, '2025-08-28 18:52:10', NULL, '', NULL),
(123, 'client', 'Клиент', NULL, NULL, 0.00, 'berrygo_store', '79509733747', NULL, NULL, NULL, NULL, NULL, '$2y$10$.XX.uFAFDSNMddond6brlucgFrJnSSDfnEtzsL3K9fbO6QrY8qD3m', '67BM8W9W', NULL, 0, 0, 0, 0, '2025-08-29 21:26:20', NULL, '', NULL),
(124, 'client', 'Клиент', NULL, NULL, 0.00, 'berrygo_store', '79509780754', NULL, NULL, NULL, NULL, NULL, '$2y$10$Go/VofTTfvTZH4XTIj64S.7YvXfeWEd5CeYAajzLlicLnu5x0u9oW', 'WGPCJPQY', NULL, 0, 180, 0, 0, '2025-08-30 09:14:27', NULL, '', NULL),
(125, 'client', 'Клиент', NULL, NULL, 0.00, 'berrygo_store', '79504243113', NULL, NULL, NULL, NULL, NULL, '$2y$10$mmNCPGQc.G9Mm6Xo2RxqzO7/h9D.dXPsukHRmEnu.24jhTWmOGeuq', 'PT97JK9Z', NULL, 0, 120, 0, 0, '2025-08-30 09:15:22', NULL, '', NULL),
(126, 'client', 'Ольга', NULL, NULL, 0.00, 'berrygo_store', '79232851479', NULL, NULL, NULL, NULL, NULL, '$2y$10$YjZjtn4HSk2783GP3MEXC.NLD/n/2iEXRR2mogAyv2Kl2tyiRRcVW', 'HDVMFE6D', NULL, 0, 120, 0, 0, '2025-08-30 09:16:19', NULL, '', NULL),
(127, 'client', 'Сузанна', NULL, NULL, 0.00, 'berrygo_store', '79137597006', NULL, NULL, NULL, NULL, NULL, '$2y$10$UFqCFlKTBP4rdtMgtOfDLejc6j8woEAjvx16Mm9fmOF6ol6yMDNba', 'SP6YJA4P', NULL, 0, 0, 0, 0, '2025-08-30 09:22:05', NULL, '', NULL),
(128, 'client', 'Ирина', NULL, NULL, 0.00, 'berrygo_store', '79501319190', NULL, NULL, NULL, NULL, NULL, '$2y$10$ICVJTq8/krleoN.wTN3ocuQN3Lvux81wARwTuW/nmL5iMjtXpNqXy', '8LEMV63L', NULL, 0, 75, 0, 0, '2025-08-30 17:12:55', NULL, '', NULL),
(129, 'client', 'Клиент', NULL, NULL, 0.00, 'berrygo_store', '79233052497', NULL, NULL, NULL, NULL, NULL, '$2y$10$Ne0kffUowOUDQwqGN46GzuJ8qTroUiHTtXCGzFfV22VK46xmoE0qC', 'H6QXBKY7', NULL, 0, 105, 0, 0, '2025-09-02 18:54:15', NULL, '', NULL),
(130, 'client', 'Раиса', NULL, NULL, 0.00, 'berrygo_store', '79082040786', NULL, NULL, NULL, NULL, NULL, '$2y$10$ZN.ld7xm5me6EyuXxmoDv.AEeVWvSYG8eQsqk5uCExTo6QOXpyL4i', 'HFTUFGCQ', NULL, 0, 110, 0, 0, '2025-09-04 09:41:01', NULL, '', NULL),
(131, 'client', 'Юлия', NULL, NULL, 0.00, 'berrygo_store', '79233271743', NULL, NULL, NULL, NULL, NULL, '$2y$10$A3AgNAkQTrT1QREOpR.Biu80bvQbD3.SX5plTOsBBrfGwyi83.M0O', 'VUZ63VSG', NULL, 0, 125, 0, 0, '2025-09-04 11:28:18', NULL, '', NULL),
(132, 'client', 'Елизавета', NULL, NULL, 0.00, 'berrygo_store', '79950734860', NULL, NULL, NULL, NULL, NULL, '$2y$10$SBow1.v93r84ZU6CkKSEjO0qBNZ.ZOsEahHf2I0RW60GRab9Sr2FK', 'WWQ2AR7R', NULL, 0, 70, 0, 0, '2025-09-04 13:14:10', NULL, '', NULL),
(133, 'client', 'Евгения', NULL, NULL, 0.00, 'berrygo_store', '79509899603', NULL, NULL, NULL, NULL, NULL, '$2y$10$5QORB.UE0XsYqirlRM10K.F0dMs2ZhoJi0wiL6dmdlfD.DOLLQO6.', 'STTCNTYW', NULL, 0, 165, 0, 0, '2025-09-04 14:46:09', NULL, '', NULL),
(134, 'client', 'Клиент', NULL, NULL, 0.00, 'berrygo_store', '79994477005', NULL, NULL, NULL, NULL, NULL, '$2y$10$Q3mNSdZkxSkZ8/ZE9BwVUeuKQQ6AsuzPiVHMf/I6AijIaNJO6kEEm', 'QFEBRLZA', NULL, 0, 0, 0, 0, '2025-09-04 16:12:02', NULL, '', NULL),
(135, 'client', 'Клиент', NULL, NULL, 0.00, 'berrygo_store', '79774675773', NULL, NULL, NULL, NULL, NULL, '$2y$10$VGPc4qX895SOOiTVnGi4COQDnTQ2/CKRnwMk/0/SEe.IymbE1iOJa', 'ZZB765JH', NULL, 0, 235, 0, 0, '2025-09-05 10:52:04', NULL, '', NULL),
(136, 'client', 'Наталья', NULL, NULL, 0.00, 'berrygo_store', '79029215917', NULL, NULL, NULL, NULL, NULL, '$2y$10$ecLaSACIyH3tvCC6YYrRE.3XOLjsIvbbPAO8ehhHO4vBVoGCd6cX2', 'Y494NT64', NULL, 1, 139, 0, 0, '2025-09-05 10:54:51', NULL, '', NULL),
(137, 'client', 'Татьяна', NULL, NULL, 0.00, 'berrygo_store', '79835087340', NULL, NULL, NULL, NULL, NULL, '$2y$10$UTh0WQvCTQ1muloaC.lh2e9A8ocGmiX107nLkKthkbLE6C/YnSPFK', 'F5VU4HA7', NULL, 0, 235, 0, 0, '2025-09-05 17:46:29', NULL, '', NULL),
(138, 'client', 'Клиент', NULL, NULL, 0.00, 'berrygo_store', '79509821931', NULL, NULL, NULL, NULL, NULL, '$2y$10$w4zETgLoqNs54l0ulZJOUOdQYF0hf.o3J2Ox.//OfJlRPDWpQ3.ru', 'NYLF53FB', NULL, 0, 55, 0, 0, '2025-09-06 20:49:22', NULL, '', NULL),
(139, 'client', 'Ксения', NULL, NULL, 0.00, 'berrygo_store', '79135214474', NULL, NULL, NULL, NULL, NULL, '$2y$10$1t0pHQVdm3ZIdEy4Sy7jvO036Xddr/aZZENYhqlkTJwJFS/UI6Iv2', 'J3HW46HM', NULL, 0, 125, 0, 0, '2025-09-07 07:40:47', NULL, '', NULL),
(140, 'client', 'Дмитрий', NULL, NULL, 0.00, 'berrygo_store', '79835026699', NULL, NULL, NULL, NULL, NULL, '$2y$10$1g7kdWitNushwLjt7NtayO1/RJEa.pi07WMBj9jVuigle9MTlBpo.', 'VEVLXL55', 17, 0, 55, 0, 0, '2025-09-07 11:43:51', NULL, '', NULL),
(141, 'client', 'Евгения', NULL, NULL, 0.00, 'berrygo_store', '79029275992', NULL, NULL, NULL, NULL, NULL, '$2y$10$0lubJrUUbv/zRqO49FtcUuYjsba8NsHAqoFNjsyGT3tJb871uJijS', 'NGUQSSH6', 17, 0, 125, 0, 0, '2025-09-07 11:45:48', NULL, '', NULL),
(142, 'client', 'Клиент', NULL, NULL, 0.00, 'berrygo_store', '79535970033', NULL, NULL, NULL, NULL, NULL, '$2y$10$stnlVM/TaMGFy0AWoNKyDOaEvIxdhZPA6x.cHWoRYtYT4Uz/0DRs.', '7S445BXJ', NULL, 0, 180, 0, 0, '2025-09-07 11:53:16', NULL, '', NULL),
(143, 'client', 'Клиент', NULL, NULL, 0.00, 'berrygo_store', '79509752711', NULL, NULL, NULL, NULL, NULL, '$2y$10$zFp3AnWBtfh37.hNlgOcx.Lycjnl0zzZmdS7XUvb1Uz4yJtaGOfKW', 'F96C9GF5', 17, 0, 110, 0, 0, '2025-09-07 12:38:53', NULL, '', NULL),
(144, 'client', 'Сергей', NULL, NULL, 0.00, 'berrygo_store', '79659006007', NULL, NULL, NULL, NULL, NULL, '$2y$10$IX3FJBWlMM7aOitk5wpTreKXtUOf9VK7KaeOUUsJvF2LIrx9rZsEC', 'YSAA9KXC', 17, 0, 70, 0, 0, '2025-09-07 13:32:16', NULL, '', NULL),
(145, 'client', 'Клиент', NULL, NULL, 0.00, 'berrygo_store', '79029407256', NULL, NULL, NULL, NULL, NULL, '$2y$10$hrJUvU4qyI.LseyajT/TIuPjJrhVsGdgMm4SKO5tk2Ov4tYAMzom6', '7DHTTYDT', 17, 0, 55, 0, 0, '2025-09-07 14:38:52', NULL, '', NULL),
(146, 'client', 'Клиент', NULL, NULL, 0.00, 'berrygo_store', '79232953032', NULL, NULL, NULL, NULL, NULL, '$2y$10$IlCPZT.TJWoB2doT5WmFROZyeZ/pqcgnLBBKwC0zIKI3r15kj9BDS', 'CYFVSS8B', NULL, 0, 70, 0, 0, '2025-09-08 11:42:52', NULL, '', NULL),
(147, 'client', 'Клиент', NULL, NULL, 0.00, 'berrygo_store', '79135344068', NULL, NULL, NULL, NULL, NULL, '$2y$10$H8d1jSb0opuzivwL0bBkke5QyX.ThWapAgkDBvtbkVT5ZDIlcTk5y', 'L7KM6H7A', NULL, 0, 55, 0, 0, '2025-09-08 11:43:39', NULL, '', NULL),
(148, 'client', 'Клиент', NULL, NULL, 0.00, 'berrygo_store', '79029911999', NULL, NULL, NULL, NULL, NULL, '$2y$10$dKrxz2rMW7AAkhLdW/jxYueoUQ.d1T4D.QAVWNDcgUS8L3BDwtQkO', 'L4A5DZWH', NULL, 0, 180, 0, 0, '2025-09-08 11:52:51', NULL, '', NULL),
(149, 'client', 'Татьяна', NULL, NULL, 0.00, 'berrygo_store', '79082119151', NULL, NULL, NULL, NULL, NULL, '$2y$10$tCxu6kdLkQ4YZhbEXPohnu4OsyZGf2irl6MsBf6WpISZmA9lpk0d.', 'S9NAS953', 17, 0, 158, 0, 0, '2025-09-08 12:00:37', NULL, '', NULL),
(150, 'client', 'Евгений', NULL, NULL, 0.00, 'berrygo_store', '79607895999', NULL, NULL, NULL, NULL, NULL, '$2y$10$qEJpswd.Q5e5jDdyee9LoeWe6RndtNHc6YLCIX150DTRcLOW/3flm', 'KVLJNNB6', 17, 0, 70, 0, 0, '2025-09-08 13:17:47', NULL, '', NULL),
(151, 'client', 'Марк', NULL, NULL, 0.00, 'berrygo_store', '79293077566', NULL, NULL, NULL, NULL, NULL, '$2y$10$L.uHBKHdAr2DntpYMn.k3O4k/tYm5s31tN75FSO0/bhqRjZkMj1VG', 'XHCZQK6D', 17, 0, 55, 0, 0, '2025-09-08 17:07:44', NULL, '', NULL),
(152, 'client', 'Клиент', NULL, NULL, 0.00, 'berrygo_store', '79232942460', NULL, NULL, NULL, NULL, NULL, '$2y$10$dRsOKh5xSsMW5ccspZB5rO0LVhCmh6oz7TqoEQ9MdkZrpPucpxkMi', 'PG3VR7B7', NULL, 0, 310, 0, 0, '2025-09-09 07:10:30', NULL, '', NULL),
(153, 'client', 'Клиент', NULL, NULL, 0.00, 'berrygo_store', '79500801616', NULL, NULL, NULL, NULL, NULL, '$2y$10$34RGg/kUkp6HxozvNUIf/e5mlFqRfp7IG2p4/SqEJ.pcVc9b8Koai', 'FQYG7QLP', NULL, 0, 330, 0, 0, '2025-09-09 10:17:57', NULL, '', NULL),
(154, 'client', 'Виктория', NULL, NULL, 0.00, 'berrygo_store', '79509681921', NULL, NULL, NULL, NULL, NULL, '$2y$10$Gl8.bFLmTR6hk2z1aOkcWuMG.jDMyIbQFzbCjLxtSZCj4efywRrxW', 'Y48R8ZG7', 17, 0, 125, 0, 0, '2025-09-09 12:55:58', NULL, '', NULL),
(155, 'client', 'Леонид', NULL, NULL, 0.00, 'berrygo_store', '79131709771', NULL, NULL, NULL, NULL, NULL, '$2y$10$Nihr/1P0VUdueQPhXsrI5etRxtfDOwlhv1EEIKKKLzoQWHx3HPXG6', '9U4SAT4X', 17, 0, 0, 0, 0, '2025-09-09 12:58:31', NULL, '', NULL),
(156, 'client', 'Клиент', NULL, NULL, 0.00, 'berrygo_store', '79676043126', NULL, NULL, NULL, NULL, NULL, '$2y$10$PBqYFIZb11RV445toXdvwuxFxxD.2ZNRUgAzIOBuThxWlaL31R.xq', 'FAW4EE24', NULL, 0, 165, 0, 0, '2025-09-11 12:46:20', NULL, '', NULL),
(157, 'client', 'Клиент', NULL, NULL, 0.00, 'berrygo_store', '79230175886', NULL, NULL, NULL, NULL, NULL, '$2y$10$IexKQHC.FZeMwIlG1v5Q2eW8.N/cchzC6oR49I4lVyHMD8UIDkd1a', 'EEU9VJJE', NULL, 0, 345, 0, 0, '2025-09-11 12:47:20', NULL, '', NULL),
(158, 'client', 'Клиент', NULL, NULL, 0.00, 'berrygo_store', '79293317775', NULL, NULL, NULL, NULL, NULL, '$2y$10$HSaahPL3UPxeaqyFf5AnmOT.HJZajzyhaI600hUpZCz58zwL42c6e', 'HNFYAPLE', 17, 0, 110, 0, 0, '2025-09-13 11:32:41', NULL, '', NULL),
(159, 'client', 'Клиент', NULL, NULL, 0.00, 'berrygo_store', '79039880825', NULL, NULL, NULL, NULL, NULL, '$2y$10$6SQg8v/VMIuGjeOPwigY4./ga.LhGqYowcOUJViHdQSeMYTOdXPIi', 'PLDTLFAR', 17, 0, 125, 0, 0, '2025-09-13 11:42:56', NULL, '', NULL),
(160, 'client', 'Клиент', NULL, NULL, 0.00, 'berrygo_store', '79293065416', NULL, NULL, NULL, NULL, NULL, '$2y$10$GVY8ZXFSX9piu84SkXPrIOa/B4h5c3eOkBe8CzN7LlvfpVmmSAdf.', '4FXNFMNR', NULL, 0, 55, 0, 0, '2025-09-13 11:55:11', NULL, '', NULL),
(161, 'client', 'Илья', NULL, NULL, 0.00, 'berrygo_store', '79174387520', NULL, NULL, NULL, NULL, NULL, '$2y$10$cbuwQp.KgKPOVosO9SlK3Oy2uTBxg1VSA/ugjy3sMZGDnmV6oa0Ty', 'MMEKEMVJ', 17, 0, 125, 0, 0, '2025-09-13 13:44:25', NULL, '', NULL),
(162, 'client', 'Клиент', NULL, NULL, 0.00, 'berrygo_store', '79964283797', NULL, NULL, NULL, NULL, NULL, '$2y$10$4QtZnACsXiLYnKj8.mXFSuEbLwBB8X7FHj8WBC99xq/3rFmgNFtq.', 'QRAXWP2Y', NULL, 0, 70, 0, 0, '2025-09-13 15:58:19', NULL, '', NULL),
(163, 'client', 'Светлана', NULL, NULL, 0.00, 'berrygo_store', '79994488130', NULL, NULL, NULL, NULL, NULL, '$2y$10$jo4iyx.0LnUknfMYbQQSFOgAHemsjTWZLVrmeo86PXoiRh9826CKm', 'XKJ4V43Q', NULL, 0, 125, 0, 0, '2025-09-13 18:17:30', NULL, '', NULL),
(164, 'client', 'Елена', NULL, NULL, 0.00, 'berrygo_store', '79996431647', NULL, NULL, NULL, NULL, NULL, '$2y$10$J9ydk2RU3Cbpjk.I2X8HYuluC3N4KBw/LSGImxinU/ZsxcZMFu7rq', 'XYGYX2Z4', NULL, 0, 70, 0, 0, '2025-09-18 21:06:46', NULL, '', NULL),
(165, 'client', 'Катерина', NULL, NULL, 0.00, 'berrygo_store', '79134487698', NULL, NULL, NULL, NULL, NULL, '$2y$10$NHo4IqvTviNghuhAfZ9sF.uIgPrZiETtPlsVz6VbVeQnNSReqLKD6', '4XKX7QGE', 17, 0, 55, 0, 0, '2025-09-19 07:34:24', NULL, '', NULL),
(166, 'client', 'Дарья', NULL, NULL, 0.00, 'berrygo_store', '79504250195', NULL, NULL, NULL, NULL, NULL, '$2y$10$RsP7KVgOzxOYe/G57w8NlOaRqIpebL70zlUUOPxIFsSDLrt8J23C.', 'AQ3JVQHL', 17, 0, 55, 0, 0, '2025-09-19 08:34:31', NULL, '', NULL),
(167, 'client', 'Клиент', NULL, NULL, 0.00, 'berrygo_store', '79993191845', NULL, NULL, NULL, NULL, NULL, '$2y$10$0suH.Nyx.HhSvYWbVfCU5OgwyYrBtswWmfCwzy/Eb8hOm0N8VY6hG', '3523R8KX', NULL, 0, 55, 0, 0, '2025-09-19 09:20:12', NULL, '', NULL),
(169, 'client', 'Лина', NULL, NULL, 0.00, 'berrygo_store', '79648116283', NULL, NULL, NULL, NULL, NULL, '$2y$10$gVL8CjVpbUxiYqBqgN/Nku5Rhr3MIk3XtbkgwcShc/1Z1OKlnVgsW', 'U9WFE7VN', NULL, 0, 55, 0, 0, '2025-09-19 13:03:06', NULL, '', NULL),
(171, 'client', 'Клиент', NULL, NULL, 0.00, 'berrygo_store', '79994424727', NULL, NULL, NULL, NULL, NULL, '$2y$10$TuU9dm448dm0fAFQPfUy/uRlQM5d602NebkDzmAx63/acNdPM9b1e', 'EREVW3R8', NULL, 0, 55, 0, 0, '2025-09-20 07:49:46', NULL, '', NULL),
(172, 'client', 'Евгения', NULL, NULL, 0.00, 'berrygo_store', '79082181545', NULL, NULL, NULL, NULL, NULL, '$2y$10$SOcfEZ4uoJi04A2oV3ZIluXM8AcIjqp5o.GafDC5Luhj2.VXPeQQu', 'XW83JTWU', 17, 0, 585, 0, 0, '2025-09-20 14:26:51', NULL, '', NULL),
(175, 'client', 'Ананда', NULL, NULL, 0.00, 'berrygo_store', '79633143865', NULL, NULL, NULL, NULL, NULL, '$2y$10$BvX1S2PTR3sCn8Y9R3xsB.0NQy22vHzSqnldhH2GZ54KvKlQvR2Da', 'UFEGJTJT', 17, 0, 110, 0, 0, '2025-09-22 15:21:53', NULL, '', NULL),
(176, 'client', 'Александр', NULL, NULL, 0.00, 'berrygo_store', '79509934058', NULL, NULL, NULL, NULL, NULL, '$2y$10$pfaRuSrSydMYMBgO8PAacehrAhllXDPXU6hdgUPLHqc2DgLZIidMK', 'QQ6YW478', 17, 0, 55, 0, 0, '2025-09-23 08:23:33', NULL, '', NULL),
(177, 'client', 'Олеся', NULL, NULL, 0.00, 'berrygo_store', '79131852859', NULL, NULL, NULL, NULL, NULL, '$2y$10$EbZAJcOsDvEg5MDH9lEPDOmV.H5fuh8SSIiTSHprWz9lgnDv8UwjO', 'RSY8HT5F', NULL, 0, 180, 0, 0, '2025-09-24 06:57:49', NULL, '', NULL),
(178, 'client', 'Алина', NULL, NULL, 0.00, 'berrygo_store', '79080167813', NULL, NULL, NULL, NULL, NULL, '$2y$10$FDEz9RxZrQMVoDQHKYI/Q.z56OOPEArBgHcFGpFM./403acLSvRYa', 'WJJTLQ9R', 17, 0, 125, 0, 0, '2025-09-26 10:44:35', NULL, '', NULL),
(179, 'client', 'Клиент', NULL, NULL, 0.00, 'berrygo_store', '79607644573', NULL, NULL, NULL, NULL, NULL, '$2y$10$h0yeSV.vYH0DwX/8HmV6Ju8sgmfSTD6wUdXc7nYW1iu1dtAYWOxTi', '7EZ5VJCZ', 17, 0, 55, 0, 0, '2025-09-27 11:03:40', NULL, '', NULL),
(180, 'client', 'Анастасия', NULL, NULL, 0.00, 'berrygo_store', '79039221129', NULL, NULL, NULL, NULL, NULL, '$2y$10$jNrmEDJ5Rdt.FQOybtLWJuBcWW/sDuVYeTyLcQeTCYA3RaSFE7MyK', 'W2P5UM7N', NULL, 0, 110, 0, 0, '2025-09-28 11:48:26', NULL, '', NULL),
(181, 'client', 'Вадим', NULL, NULL, 0.00, 'berrygo_store', '79135724451', NULL, NULL, NULL, NULL, NULL, '$2y$10$8T94YRycG3cN2T3H/eOxeeartIteHJYz8obr/EZhakZHGSDzOPn0O', '2GL2QXDJ', 17, 0, 165, 0, 0, '2025-09-30 09:18:20', NULL, '', NULL),
(182, 'client', 'Клиент', NULL, NULL, 0.00, 'berrygo_store', '79135771792', NULL, NULL, NULL, NULL, NULL, '$2y$10$3qKmxiXnkN5cLqU1dqFeYeWDBummq30GdkngtqHH1tkDAKyJJnoOe', 'QU4W86GU', NULL, 0, 180, 0, 0, '2025-09-30 17:31:38', NULL, '', NULL),
(183, 'client', 'Ирина', NULL, NULL, 0.00, 'berrygo_store', '79029424655', NULL, NULL, NULL, NULL, NULL, '$2y$10$FnPfGj5vwAQbPy./Vl1t9uPZIhJsRYt2HFnyE7pNm1GfvDlY8F1bS', 'X4AC4PSZ', 17, 0, 55, 0, 0, '2025-09-30 18:39:56', NULL, '', NULL),
(184, 'client', 'Алексей', NULL, NULL, 0.00, 'berrygo_store', '79029779555', NULL, NULL, NULL, NULL, NULL, '$2y$10$p6NULjL/t.WODYRbzu1NB.ck95EpZNKgd/Cf9Xf33sztDt2zJ00aG', 'Y3HFUC2C', 17, 0, 55, 0, 0, '2025-09-30 18:42:50', NULL, '', NULL),
(186, 'client', 'Андрей', NULL, NULL, 0.00, 'berrygo_store', '79131978364', NULL, NULL, NULL, NULL, NULL, '$2y$10$oIj.sVa46S31G6dm6kK.1.hOxyKBO39AYWQtoO.7XVl8Gs5TKyctq', '9X39AXR9', NULL, 0, 55, 0, 0, '2025-10-01 19:37:33', NULL, '', NULL),
(187, 'client', 'Владимир', NULL, NULL, 0.00, 'berrygo_store', '79235774441', NULL, NULL, NULL, NULL, NULL, '$2y$10$jQvJS2l5G0uAmGyC17fNCOIwIPktUYaxv13y0d34ZsQG4vqwhnTEe', 'FZVR33F7', NULL, 0, 110, 0, 0, '2025-10-02 08:10:10', NULL, '', NULL),
(188, 'client', 'Маргарита', NULL, NULL, 0.00, 'berrygo_store', '79082043587', NULL, NULL, NULL, NULL, NULL, '$2y$10$5dUvfONqbnIKJYvszR9SnOjbpQMaxaTkeXdGeg3rM1Qq3asa/1ENa', 'Z93599ZN', 17, 0, 180, 0, 0, '2025-10-02 10:39:27', NULL, '', NULL),
(189, 'client', 'Елена', NULL, NULL, 0.00, 'berrygo_store', '79504018755', NULL, NULL, NULL, NULL, NULL, '$2y$10$nX70OVG.Ui976oGzZ.BMVuQs/lU.WdevTpJ.Y9yU0c66e8q3asbXu', 'FNNT669J', NULL, 0, 55, 0, 0, '2025-10-02 12:18:52', NULL, '', NULL),
(190, 'client', 'Наталья', NULL, NULL, 0.00, 'berrygo_store', '79135389278', NULL, NULL, NULL, NULL, NULL, '$2y$10$qnRX6Qvo0VG/YudZ5YsM2Owefs9GH8/CVACv2oz67UwS2cPHQebtO', '578T5G4L', NULL, 0, 55, 0, 0, '2025-10-02 12:19:43', NULL, '', NULL),
(193, 'client', 'Клиент', NULL, NULL, 0.00, 'berrygo_store', '79632561522', NULL, NULL, NULL, NULL, NULL, '$2y$10$pLxfhIuJLIkRbtOA6BxihOsxDNMOqruQ.5lGDqt94KYJ0pfgUKL.e', 'RLSMQW7J', NULL, 0, 55, 0, 0, '2025-10-06 10:25:00', NULL, '', NULL),
(194, 'client', 'Клиент', NULL, NULL, 0.00, 'berrygo_store', '79232716088', NULL, NULL, NULL, NULL, NULL, '$2y$10$lMJ8zzRHp.m9m73JbRWBvOWOonukVpqonEtaClq3DtI6rKooyhyNu', 'TQ9FC3YQ', NULL, 0, 110, 0, 0, '2025-10-06 10:26:21', NULL, '', NULL),
(195, 'client', 'Александр', NULL, NULL, 0.00, 'berrygo_store', '79831666737', NULL, NULL, NULL, NULL, NULL, '$2y$10$MnD41LwixwyZsPaap5rWC.jXj/n4BwCH5Mow/rFZOw4BCRv1yL536', 'BCU4J2YE', 17, 0, 130, 0, 0, '2025-10-07 18:25:35', NULL, '', NULL),
(196, 'client', 'Иван', NULL, NULL, 0.00, 'berrygo_store', '79059999501', NULL, NULL, NULL, NULL, NULL, '$2y$10$oput9E90B6DRevwoiuFB0OHbuIdEG4nVmfkV57UFopn7jYjE890mS', 'NQTTF4JB', 17, 0, 80, 0, 0, '2025-10-07 18:28:18', NULL, '', NULL),
(197, 'client', 'Анастасия', NULL, NULL, 0.00, 'berrygo_store', '79233137376', NULL, NULL, NULL, NULL, NULL, '$2y$10$SZIE3dyDGHzrYITQqlw11.JjiYwZavDlB86JY66MgXz52CYZSUFwi', '7YWG989C', 17, 0, 145, 0, 0, '2025-10-09 05:42:46', NULL, '', NULL),
(198, 'client', 'Ольга', NULL, NULL, 0.00, 'berrygo_store', '79135692746', NULL, NULL, NULL, NULL, NULL, '$2y$10$yH3S/6MWRjTuozFrnP5lluBkyW3am5.IYyaPg76IYGT.nY/inK.2q', 'NMR68FNJ', 17, 0, 130, 0, 0, '2025-10-09 05:47:24', NULL, '', NULL),
(199, 'client', 'Юлия', NULL, NULL, 0.00, 'berrygo_store', '79233292900', NULL, NULL, NULL, NULL, NULL, '$2y$10$0E.1evUS0WSOvHaVt4nZzOKdkLAkR/YgmcDiYV9hYN3fXhYjVdxXe', '4GQDTKJS', 17, 0, 0, 0, 0, '2025-10-09 07:31:37', NULL, '', NULL),
(200, 'client', 'Лариса', NULL, NULL, 0.00, 'berrygo_store', '79232642859', NULL, NULL, NULL, NULL, NULL, '$2y$10$amdZvODZs183BRbzs6BnfeBZtoic.9qsM0r7DwrxxE1s6vPjYWqJK', 'KF742KVM', 17, 0, 0, 0, 0, '2025-10-09 07:42:46', NULL, '', NULL),
(202, 'client', 'Ольга', NULL, NULL, 0.00, 'berrygo_store', '79131893930', NULL, NULL, NULL, NULL, NULL, '$2y$10$HBsng4kdx8OuC/wPHUYJXenk3ndIT6OZVJksI0oRFsQ50sgtKfzw2', 'P36X7VB6', 17, 0, 145, 0, 0, '2025-10-11 18:23:17', NULL, '', NULL),
(203, 'client', 'Эльмира', NULL, NULL, 0.00, 'berrygo_store', '79233017137', NULL, NULL, NULL, NULL, NULL, '$2y$10$eRSV0xTbotn/3g1C78GtTuYsuizdUAd9QkLG5PnKDZxEamCSKvGhG', 'NL6WMC8F', 17, 0, 365, 0, 0, '2025-10-12 16:54:48', NULL, '', NULL),
(204, 'client', 'Евгений', NULL, NULL, 0.00, 'berrygo_store', '79233324167', NULL, NULL, NULL, NULL, NULL, '$2y$10$gFyMDhNrMO8j46fiIO7kj.hKdSD/1e3FUrc/WbGDRFQdv.kCtjFyi', 'ZU7KVR3U', 17, 0, 80, 0, 0, '2025-10-12 17:12:10', NULL, '', NULL),
(205, 'client', 'Павел', NULL, NULL, 0.00, 'berrygo_store', '79069165489', NULL, NULL, NULL, NULL, NULL, '$2y$10$qlpB6l4INEada.T8qziOAeZcOlpItltkmcswasB9G81F4AFH39mji', 'HJXYRCGR', 17, 0, 390, 0, 0, '2025-10-12 17:17:28', NULL, '', NULL),
(206, 'client', 'Клиент', NULL, NULL, 0.00, 'berrygo_store', '79676169080', NULL, NULL, NULL, NULL, NULL, '$2y$10$19FRLGqynGw8YKiVmKvHSOirNsZY05.PzKrUQbc31ll/P2RACI.Yy', 'WBG5TRLS', NULL, 0, 0, 0, 0, '2025-10-13 18:25:08', NULL, '', NULL),
(207, 'client', 'Клиент', NULL, NULL, 0.00, 'berrygo_store', '79135629334', NULL, NULL, NULL, NULL, NULL, '$2y$10$rsu0qoQiymf2fcAhZgRDHei2y71p5FmEg8pQ6gCB2/whFut4U2q8u', 'EMXLQLG2', 17, 0, 85, 0, 0, '2025-10-15 18:57:24', NULL, '', NULL),
(208, 'client', 'Виктория', NULL, NULL, 0.00, 'berrygo_store', '79135822173', NULL, NULL, NULL, NULL, NULL, '$2y$10$2WKr9DBILNf8UvsMza92R.i3tkbRF6CGeK6NHGxX5QX/U9avYK87m', 'Q8HQPLAE', 17, 0, 170, 0, 0, '2025-10-16 09:01:06', NULL, '', NULL),
(209, 'client', 'Андрей', NULL, NULL, 0.00, 'berrygo_store', '79607629696', NULL, NULL, NULL, NULL, NULL, '$2y$10$aWfir/UaUoQ.6nWIgoYabeyHZmBgD3GNRwqNuCpo16fb0OGNHTlQi', 'W75TC7AH', 41, 0, 70, 0, 0, '2025-10-17 08:29:59', NULL, '', NULL),
(210, 'client', 'Кристина', NULL, NULL, 0.00, 'berrygo_store', '79233572317', NULL, NULL, NULL, NULL, NULL, '$2y$10$l3Rq3KZZleamxRn1o8iAN.YEPNV35Wx1gURqjRbyU1.U5YcXBjNy.', 'MFH7SAHE', NULL, 0, 70, 0, 0, '2025-10-17 08:54:12', NULL, '', NULL),
(211, 'client', 'Татьяна', NULL, NULL, 0.00, 'berrygo_store', '79135869573', NULL, NULL, NULL, NULL, NULL, '$2y$10$sMi/vn2Kk5RIzrTjwCfwkOfFsF7XF9AkoFBBWEKcPpF5TtSnzVf62', 'S9ZE8FU5', 17, 0, 85, 0, 0, '2025-10-18 10:29:05', NULL, '', NULL),
(212, 'client', 'Студия сигма', NULL, NULL, 0.00, 'berrygo_store', '79631919361', NULL, NULL, NULL, NULL, NULL, '$2y$10$lNo6CJ.pqqMCwkHSP9L9y.R.RFHMd48TcSnFO2tBXvNJMY/eDb1Mq', 'VCL65GLY', 17, 0, 155, 0, 0, '2025-10-18 11:15:37', NULL, '', NULL),
(213, 'client', 'Любовь', NULL, NULL, 0.00, 'berrygo_store', '79535970426', NULL, NULL, NULL, NULL, NULL, '$2y$10$Rg49sqXrymLPlApXV4vxIOLrwBTBnVNpvDwgS6DHMB6aPUuXR2x3a', 'UEP3EZQT', 17, 0, 0, 0, 0, '2025-10-19 07:50:10', NULL, '', NULL),
(214, 'client', 'Ксения', NULL, NULL, 0.00, 'berrygo_store', '79620788036', NULL, NULL, NULL, NULL, NULL, '$2y$10$mkHxNgKMWThVEhI4IVRThOZ4XtuWceYf6fnf4cTlnnHi1tEdy8fda', '8FL7X46A', 17, 0, 75, 0, 0, '2025-10-20 19:46:59', NULL, '', NULL),
(215, 'client', 'Сергей', NULL, NULL, 0.00, 'berrygo_store', '79293337080', NULL, NULL, NULL, NULL, NULL, '$2y$10$uyKv5S2ijx6SNuCxs2cnw.LLUOBbgGdKnIGbVoTkOrveUPduQxdjy', 'RXMBTX35', 17, 0, 0, 0, 0, '2025-10-21 08:01:01', NULL, '', NULL),
(216, 'client', 'Галина', NULL, NULL, 0.00, 'berrygo_store', '79831695666', NULL, NULL, NULL, NULL, NULL, '$2y$10$o16dtzFVczYGCgVtrgUzpugvp0lZAxqca4wlcfOCzvpqHW5alwCx.', 'APJTJ4PW', NULL, 0, 90, 0, 0, '2025-10-21 10:23:45', NULL, '', NULL),
(217, 'client', 'Клиент', NULL, NULL, 0.00, 'berrygo_store', '79230005556', NULL, NULL, NULL, NULL, NULL, '$2y$10$kUfY9gNq./nkNeAVGCWwoe0hL/kMI0oRDnJ5bIDWq6pSoxkjprAsS', '6EAUTNNF', NULL, 0, 0, 0, 0, '2025-10-21 12:52:01', NULL, '', NULL),
(218, 'client', 'Move', NULL, NULL, 0.00, 'berrygo_store', '79080199619', NULL, NULL, NULL, NULL, NULL, '$2y$10$z5j8sNgaCtbHncvanUss0O7XfT3Ot.hhpWz7nsdrNhbFRwuLKa0Fu', '6AXKRB29', 17, 0, 75, 0, 0, '2025-10-22 14:08:18', NULL, '', NULL),
(219, 'client', 'Юлия', NULL, NULL, 0.00, 'berrygo_store', '79135655155', NULL, NULL, NULL, NULL, NULL, '$2y$10$5huqcUMVoJzu4OpSqDvPkO/a59dudLWXjdypIVwHGp0IClDyVqXsO', '8XEJZYSE', 17, 0, 205, 0, 0, '2025-10-22 20:21:25', NULL, '', NULL),
(220, 'client', 'Татьяна', NULL, NULL, 0.00, 'berrygo_store', '79607708140', NULL, NULL, NULL, NULL, NULL, '$2y$10$dk8lMcEfPCDz10q952eySeHk2AEhHPT6Qgr1ShQcHtlE.Vavm7QjS', '5JPZP2FS', 17, 0, 165, 0, 0, '2025-10-23 10:41:40', NULL, '', NULL),
(221, 'client', 'Юрий', NULL, NULL, 0.00, 'berrygo_store', '79235702858', NULL, NULL, NULL, NULL, NULL, '$2y$10$DKuTXxYUcHSDuWzUOKCcJOySdVM3bdRS8FEibbeOfBtxxZ0A0YRYm', '4EHKG6SF', NULL, 0, 0, 0, 0, '2025-10-23 14:32:37', NULL, '', NULL),
(222, 'client', 'Александр', NULL, NULL, 0.00, 'berrygo_store', '79233060547', NULL, NULL, NULL, NULL, NULL, '$2y$10$QBJTEk4XCrP7jbTPrA7S5elFc22POx/G1a7AEx8peYoPHWJHe4FtC', 'STYNMSWZ', 17, 0, 0, 0, 0, '2025-10-23 17:36:09', NULL, '', NULL),
(223, 'client', 'Руслан', NULL, NULL, 0.00, 'berrygo_store', '79137144115', NULL, NULL, NULL, NULL, NULL, '$2y$10$5jfpY9OFY2HZoqQRUu6vCO5IO4Zr1rfJFCzslGm/ydMLz9LFgOjE6', 'UDCPMXWD', 17, 0, 0, 0, 0, '2025-10-23 18:08:11', NULL, '', NULL),
(224, 'client', 'Максим', NULL, NULL, 0.00, 'berrygo_store', '79143591006', NULL, NULL, NULL, NULL, NULL, '$2y$10$Ad8LwjwO/VyTqpODMNInOeFHt2JnKByN0gEbRMpujVi1bTkgtK2AW', 'VA9UG3DP', 17, 0, 0, 0, 0, '2025-10-24 19:07:21', NULL, '', NULL),
(225, 'client', 'Аркадий', NULL, NULL, 0.00, 'berrygo_store', '79535880611', NULL, NULL, NULL, NULL, NULL, '$2y$10$qoA7LiLGtLJ8uAP7Feoa3ue.B6O2XM6Ccjuk5mUkp53uVaysn9v42', 'CSXEV4KD', NULL, 0, 0, 0, 0, '2025-10-25 14:27:31', NULL, '', NULL),
(226, 'client', 'Денис', NULL, NULL, 0.00, 'berrygo_store', '79039237465', NULL, NULL, NULL, NULL, NULL, '$2y$10$tgErcE8sZgYW0AauTY9PW.xbnThUnw/UfuxkVubMVNu17/HJ.5BXq', 'Q3RBC3ET', NULL, 0, 0, 0, 0, '2025-10-26 14:18:34', NULL, '', NULL),
(227, 'client', 'Екатерина', NULL, NULL, 0.00, 'berrygo_store', '79080193180', NULL, NULL, NULL, NULL, NULL, '$2y$10$6ACRCbVXpbKCS95f9SXDe.Fas9/reF314J/Goc0rJT4HYIQbeZ9i2', 'XRXM4BB9', NULL, 0, 0, 0, 0, '2025-12-12 08:01:42', NULL, '', NULL),
(228, 'client', 'Людмила', NULL, NULL, 0.00, 'berrygo_store', '79135372581', NULL, NULL, NULL, NULL, NULL, '$2y$10$B6Al/uRgm0OBZBYzLaqJVuwGOERFVRJfNROxS4lu8ig4HxaJ0UoOS', 'D978MK8J', NULL, 0, 0, 0, 0, '2026-01-08 19:06:45', NULL, '', NULL),
(229, 'client', 'Вадим', NULL, NULL, 0.00, 'berrygo_store', '79135937602', NULL, NULL, NULL, NULL, NULL, '$2y$10$ODt1TOPt6TojExQamcNG1e3dMiyPUbACPO0HHKEaIRmllMdmkG6iG', 'MC4BJM2W', NULL, 0, 0, 0, 0, '2026-01-23 13:45:59', NULL, '', NULL),
(230, 'client', 'Екатерина', NULL, NULL, 0.00, 'berrygo_store', '79535851533', NULL, NULL, NULL, NULL, NULL, '$2y$10$85Et47L/jpAKa9r9Op3g0.yFuGPBzeCitbDoWF0q.iCNxfxpH9veu', 'LG3YUJUU', NULL, 0, 0, 0, 0, '2026-04-10 23:18:12', NULL, '', NULL),
(231, 'client', 'София Евгеньевна Григорьева', NULL, NULL, 0.00, 'berrygo_store', '79620777163', NULL, NULL, NULL, NULL, NULL, '$2y$10$V/TRboR8Z8bazKHSv6Jf5.lwQ9PMO.PWmfPXL.16CxMueWtDxwm2m', 'YZBBQ6X8', NULL, 0, 0, 0, 0, '2026-05-06 14:32:54', NULL, '', NULL),
(232, 'client', 'Клиент', NULL, NULL, 0.00, 'berrygo_store', '79835000629', NULL, NULL, NULL, NULL, NULL, '$2y$10$MO9h.cIL0wJr6b/3xIGnLuijx4RgZFDZGUFMbEa36YGl1rN4/KT5.', 'F9AB3GGV', 17, 0, 70, 0, 0, '2026-05-08 14:23:36', NULL, '', NULL),
(233, 'client', 'Виктория', NULL, NULL, 0.00, 'berrygo_store', '79833708548', NULL, NULL, NULL, NULL, NULL, '$2y$10$WquaZ3Qr/wcnp9d5ATQ6/eGamqlm5xanxdFbJf.dq7L3cBGzM1Tge', 'PFRNQP43', 17, 0, 55, 0, 0, '2026-05-08 14:25:38', NULL, '', NULL),
(234, 'client', 'Марина', NULL, NULL, 0.00, 'berrygo_store', '79059743133', NULL, NULL, NULL, NULL, NULL, '$2y$10$/AIxf3jxE9ztvgVA32GEPeFGEu6YZxfTepSlU9myAC.q3E7A2xvbu', 'QH44FA2V', 17, 0, 55, 0, 0, '2026-05-08 14:27:07', NULL, '', NULL),
(235, 'client', 'Дмитрий', NULL, NULL, 0.00, 'berrygo_store', '79029135286', NULL, NULL, NULL, NULL, NULL, '$2y$10$aylC5ilW9GNwAL8evwzDZexBjI8vtf0BkvqId2YqsMiFzwmyqjdy.', '2HCKF2EP', 17, 0, 0, 0, 0, '2026-05-08 14:28:57', NULL, '', NULL),
(236, 'client', 'Клиент', NULL, NULL, 0.00, 'berrygo_store', '79832091133', NULL, NULL, NULL, NULL, NULL, '$2y$10$C.TwiJGewwDhLKZxBz6UhusK/38SfzEwA9Lw6wXeZNTbUCeKMm1NC', 'WXWJJQ75', 17, 0, 55, 0, 0, '2026-05-08 16:29:36', NULL, '', NULL),
(237, 'client', 'Елена', NULL, NULL, 0.00, 'berrygo_store', '79607608228', NULL, NULL, NULL, NULL, NULL, '$2y$10$roQOZniAYIdXuEDteuJfBeSJUm3gLhTAlVHYpb2gVBTr5JA162Sze', 'NF8AQUPS', 17, 0, 75, 0, 0, '2026-05-11 06:54:57', NULL, '', NULL),
(238, 'client', 'Надежда', NULL, NULL, 0.00, 'berrygo_store', '79233067611', NULL, NULL, NULL, NULL, NULL, '$2y$10$57j9WxkaTvg4WbZHTYNB4e7O9fBO6Z3l4lR/nT6vI1SzztXJpKi4e', 'MP98BX6X', 17, 0, 80, 0, 0, '2026-05-11 07:00:48', NULL, '', NULL),
(239, 'client', 'Арина', NULL, NULL, 0.00, 'berrygo_store', '79025391775', NULL, NULL, NULL, NULL, NULL, '$2y$10$oUmTpitpj5VxYFU8rsQLAeGwExUn2hGbC9i3kPN5lpBO9JOOh3afm', 'F2G8KLZQ', 17, 0, 60, 0, 0, '2026-05-11 07:06:18', NULL, '', NULL),
(240, 'client', 'Евгений', NULL, NULL, 0.00, 'berrygo_store', '79135322858', NULL, NULL, NULL, NULL, NULL, '$2y$10$/Ujg56NM.QSg3vhTQvWwQukTP5g5wkGgmwIUHiikxquMZWn5tmE7q', 'XDS5SATH', 17, 0, 120, 0, 0, '2026-05-11 07:07:19', NULL, '', NULL),
(241, 'client', 'Ляйсан', NULL, NULL, 0.00, 'berrygo_store', '79134765190', NULL, NULL, NULL, NULL, NULL, '$2y$10$KbPtmg4IUx/xzpffvfOkAuC0veQCl4kO7ySY69uk663/kxnX3MucO', '8UNHFDFX', 17, 0, 130, 0, 0, '2026-05-11 16:55:15', NULL, '', NULL),
(242, 'client', 'Полина', NULL, NULL, 0.00, 'berrygo_store', '79048935836', NULL, NULL, NULL, NULL, NULL, '$2y$10$RS5tQY3M1uZ2DWrmAUyx3.2awy5zAKz4/jGz6Vzmg4zmDk1K089Li', 'E84Y4FA3', 17, 0, 0, 0, 0, '2026-05-13 18:54:04', NULL, '', NULL),
(243, 'client', 'Юлия', NULL, NULL, 0.00, 'berrygo_store', '79232807787', NULL, NULL, NULL, NULL, NULL, '$2y$10$2wBBJciwQsoo96B5HrL2kuS5j0CxYIhGwZjVrHqT3DozaCx8F80JO', 'Z4KMBW92', 17, 0, 165, 0, 0, '2026-05-14 09:24:34', NULL, '', NULL),
(244, 'client', 'Светлана', NULL, NULL, 0.00, 'berrygo_store', '79135860727', NULL, NULL, NULL, NULL, NULL, '$2y$10$/jyhq6Z63pHD98hPk2ngn.xzONPDNdoMJ4yr28z9ViQm8diMsRRee', '4XJRHF7Q', 17, 0, 70, 0, 0, '2026-05-14 09:31:36', NULL, '', NULL),
(245, 'client', 'Мария', NULL, NULL, 0.00, 'berrygo_store', '79641269888', NULL, NULL, NULL, NULL, NULL, '$2y$10$y2fu/WrllI2ZNzwwXRbuwezm5NUsAYzo3XaHzNZo5Cmwlk4uOCdY.', 'KJYJV6NR', 17, 0, 0, 0, 0, '2026-05-14 13:06:26', NULL, '', NULL),
(246, 'client', 'Илья', NULL, NULL, 0.00, 'berrygo_store', '79659044557', NULL, NULL, NULL, NULL, NULL, '$2y$10$z2FdoBSrW2PBmYUQlGehde/gcFFQw5qHkenJi.DDNPwZ4aVnJPAFa', 'F6GF445B', 17, 0, 55, 0, 0, '2026-05-14 13:09:33', NULL, '', NULL),
(247, 'client', 'Сергей', NULL, NULL, 0.00, 'berrygo_store', '79029409325', NULL, NULL, NULL, NULL, NULL, '$2y$10$lzoGq/R9HUyk5hXPewodJewPcnjRFu81w.Dj/8sG6bDJmHSeeTtnu', 'ECUCMN99', 17, 0, 56, 0, 0, '2026-05-14 18:32:04', NULL, '', NULL),
(248, 'client', 'Дарья', NULL, NULL, 0.00, 'berrygo_store', '79135127471', NULL, NULL, NULL, NULL, NULL, '$2y$10$SbINGX1IVETWjQaRMNPZ1eOIdOp8xH8uwTJVvv1yz9PdA25qH571G', 'GTFDDY7A', 17, 0, 56, 0, 0, '2026-05-14 18:34:19', NULL, '', NULL),
(249, 'client', 'Энергопоток', NULL, NULL, 0.00, 'berrygo_store', '79029208094', NULL, NULL, NULL, NULL, NULL, '$2y$10$F/.73H9BEAG3TZNrwBSI/eB3dot6tC/HZBe06AQ0iBprtfqwAZEKO', 'R9JULTLB', 17, 0, 56, 0, 0, '2026-05-15 10:10:01', NULL, '', NULL);
INSERT INTO `users` (`id`, `role`, `name`, `company_name`, `pickup_address`, `delivery_cost`, `work_mode`, `phone`, `email`, `email_verified_at`, `email_verification_token_hash`, `email_verification_expires_at`, `telegram_id`, `password_hash`, `referral_code`, `referred_by`, `has_used_referral_coupon`, `points_balance`, `rub_balance`, `is_blocked`, `created_at`, `chat_id`, `subscribed_notifications`, `address_id`) VALUES
(250, 'client', 'Игорь', NULL, NULL, 0.00, 'berrygo_store', '79059762593', NULL, NULL, NULL, NULL, NULL, '$2y$10$LOB90.xY33wfpBVfRVTVmeX3vij7Fw.iFREauF2q3tqYvzIrRNfUu', 'V9L72TBW', 17, 0, 56, 0, 0, '2026-05-15 11:41:25', NULL, '', NULL),
(251, 'client', 'Федя', NULL, NULL, 0.00, 'berrygo_store', '79048905534', NULL, NULL, NULL, NULL, NULL, '$2y$10$ofnQd2E3aCc2n0eVPSBFd.tbwFMdgYDC6b7CZKFXb4.ReTDJPQnTW', '5QKHKEAG', 17, 0, 0, 0, 0, '2026-05-15 13:48:00', NULL, '', NULL),
(252, 'client', 'Яна', NULL, NULL, 0.00, 'berrygo_store', '79964303402', NULL, NULL, NULL, NULL, NULL, '$2y$10$XpA/KmgRacndypokokWGjusB.PEU9SOAtuu7B/m9nRM9jv4ogfO.q', '6PCMZCK7', 17, 0, 71, 0, 0, '2026-05-15 19:15:07', NULL, '', NULL),
(253, 'client', 'Клиент', NULL, NULL, 0.00, 'berrygo_store', '79082062131', NULL, NULL, NULL, NULL, NULL, '$2y$10$.APXMc8/ouoMaUf9FO1OWur8plgxnkYkfBw8lFg7fhYNzIwEicdNi', 'YC6KSHPV', 17, 0, 56, 0, 0, '2026-05-15 19:34:27', NULL, '', NULL),
(254, 'client', 'Екатерина', NULL, NULL, 0.00, 'berrygo_store', '79029912006', NULL, NULL, NULL, NULL, NULL, '$2y$10$BETbTcYsIvFdzUH0Z7FLVeyod4X/2ZhxIleN2zbCYv1NKy6rP6XJu', '74VE4HGR', 17, 0, 0, 0, 0, '2026-05-17 09:32:45', NULL, '', NULL),
(255, 'client', 'Сергей', NULL, NULL, 0.00, 'berrygo_store', '79994482127', NULL, NULL, NULL, NULL, NULL, '$2y$10$09jKoli/TLYqnABMr5yB1ubOJ9j1RfVDHoRVSRw6ot47MwcBKl2ra', 'SF426QJ9', 17, 0, 90, 0, 0, '2026-05-17 10:33:00', NULL, '', NULL),
(256, 'client', 'Сладко', NULL, NULL, 0.00, 'berrygo_store', '79339972322', NULL, NULL, NULL, NULL, NULL, '$2y$10$PFvDDVKLbNTpFIzvdPMnP.DbSLPLDziqHsZ401Le2FCfh37l9wSpe', 'TE2KSJDR', 17, 0, 56, 0, 0, '2026-05-19 18:23:06', NULL, '', NULL),
(257, 'client', 'Илья', NULL, NULL, 0.00, 'berrygo_store', '79509791039', NULL, NULL, NULL, NULL, NULL, '$2y$10$q.UXvSeb8Qyf8nl7idUfu.rupYh5vU6hhBeYozMV308RgtDKmc9OO', 'HTD59U6B', 17, 0, 95, 0, 0, '2026-05-19 18:24:36', NULL, '', NULL),
(258, 'client', 'Алена', NULL, NULL, 0.00, 'berrygo_store', '79874839938', NULL, NULL, NULL, NULL, NULL, '$2y$10$x6QHPsbjXWtgdSNJG06E3O.s/xCvR/fzbGk6MWAcK.OblfRn3NZIW', 'H35Y9JSR', 17, 0, 225, 0, 0, '2026-05-20 17:30:21', NULL, '', NULL),
(259, 'client', 'Анна', NULL, NULL, 0.00, 'berrygo_store', '79233398374', NULL, NULL, NULL, NULL, NULL, '$2y$10$FEahP268g3R9i.3Q9ObwDO8U20bLF5.zSkqxBJq9QB2yMEW8ScYsW', 'QCLYS5PH', 17, 0, 165, 0, 0, '2026-05-20 17:44:37', NULL, '', NULL),
(260, 'client', 'Агния', NULL, NULL, 0.00, 'berrygo_store', '79509874119', NULL, NULL, NULL, NULL, NULL, '$2y$10$/magfoz6hdxXI8iFY/UNw.6Oj8J/E4/sKX21G7PDfOHTVEYDl6cd6', 'LJFDHH2W', 17, 0, 0, 0, 0, '2026-05-24 10:47:21', NULL, '', NULL),
(261, 'client', 'Владимир', NULL, NULL, 0.00, 'berrygo_store', '79994483743', NULL, NULL, NULL, NULL, NULL, '$2y$10$ZbtUVbubE82mFtiigfDyJOC.IXCMGVLCOQ6rEHoJMqE/wVs1wKChK', 'K7MEEMCD', 17, 0, 60, 0, 0, '2026-05-25 14:21:36', NULL, '', NULL),
(262, 'client', 'Клиент', NULL, NULL, 0.00, 'berrygo_store', '79535863713', NULL, NULL, NULL, NULL, NULL, '$2y$10$tSzZJKrIuc87zKuQbspcd.afo0ej3V3/c2rnY/h6ZwgA3GCilxF3y', 'QCRAYBGH', 17, 0, 60, 0, 0, '2026-05-25 16:16:41', NULL, '', NULL),
(263, 'client', 'Александр', NULL, NULL, 0.00, 'berrygo_store', '79501202782', NULL, NULL, NULL, NULL, NULL, '$2y$10$ytNVQ7JpQ4N7vXfJIOWs/.oNm5yJEwdBw2tNosyIomyMIKxu1kMw6', 'WPDG895D', NULL, 0, 0, 0, 0, '2026-05-27 17:30:55', NULL, '', NULL),
(264, 'client', 'Строительный Холдинг', NULL, NULL, 0.00, 'berrygo_store', '79620717871', NULL, NULL, NULL, NULL, NULL, '$2y$10$VC6jAKzZTrzbJiFeFPlm7.yZMuBkkec.kq7fFUynxFL7JQ8b5HNgG', 'R5L9A5HT', 17, 0, 60, 0, 0, '2026-05-28 10:22:39', NULL, '', NULL),
(265, 'client', 'Анатолий', NULL, NULL, 0.00, 'berrygo_store', '79333310408', NULL, NULL, NULL, NULL, NULL, '$2y$10$bh90DUrI/43WKP90tMyGZ.8vU8COl5N4gKet4Eqtlb.OVGReRk2g6', 'JDVBDYH2', 17, 0, 395, 0, 0, '2026-05-28 17:32:51', NULL, '', NULL),
(266, 'client', 'Светлана Зыкова', NULL, NULL, 0.00, 'berrygo_store', '79135093677', NULL, NULL, NULL, NULL, NULL, '$2y$10$Fa5sQc3FiJN5mCcAz63qHe3cUy5jtparJkW6Ebs9mze4Nks/7dD4e', 'CDTFTV4S', 17, 1, 67, 0, 0, '2026-06-02 11:01:29', NULL, '', NULL),
(267, 'client', 'Михаил', NULL, NULL, 0.00, 'berrygo_store', '79041399912', NULL, NULL, NULL, NULL, NULL, '$2y$10$UQ1S0EJqaW5OkUUvveAWN.2Qek7W/c5hMPUJijME1Oo0vaEvWUAAC', 'Y8DNYJQ9', NULL, 0, 90, 0, 0, '2026-06-02 14:24:05', NULL, '', NULL),
(268, 'client', 'Ксения', NULL, NULL, 0.00, 'berrygo_store', '79080141979', NULL, NULL, NULL, NULL, NULL, '$2y$10$myIBLjVKNr.9oanUz7fsfue0yyOw4pvquw/SDw9vuYmQUSaoaMBZa', 'D4JDJ8G2', 17, 0, 75, 0, 0, '2026-06-06 09:35:20', NULL, '', NULL),
(269, 'client', 'Валерия', NULL, NULL, 0.00, 'berrygo_store', '79509935885', NULL, NULL, NULL, NULL, NULL, '$2y$10$AXVpOBz7VdOvrS5G6jIGPuKiGVWl38a.rFGaHzyC811H6hcWF7i9.', 'XC4DZAG5', 17, 0, 284, 0, 0, '2026-06-10 07:00:13', NULL, '', NULL),
(270, 'client', 'Ольга', NULL, NULL, 0.00, 'berrygo_store', '79082072188', NULL, NULL, NULL, NULL, NULL, '$2y$10$bU3OfAuJZgV43ZTRgDPbqe4mURcjm46u0PYoP1CRxNohUS.C1BKUu', '9ZSFEBTL', 17, 1, 70, 0, 0, '2026-06-12 20:23:43', NULL, '', NULL),
(271, 'client', 'Сам', NULL, NULL, 0.00, 'berrygo_store', '79131826668', NULL, NULL, NULL, NULL, NULL, '$2y$10$4UJtG9TFmDSYl5k40mSZtuznpPtc2K6VPX1KIV9yfX60wTUC7l/ZC', '3FKKSNDR', 17, 0, 332, 0, 0, '2026-06-12 20:33:57', NULL, '', NULL),
(272, 'client', 'Ангелина', NULL, NULL, 0.00, 'berrygo_store', '79080104768', NULL, NULL, NULL, NULL, NULL, '$2y$10$Yp6f.uneoWlPe5QgbL3GXupycIjLr1/zgaNoS8lIKyhd7cASXGrBu', 'PUTFG22S', NULL, 0, 0, 0, 0, '2026-06-13 10:28:19', NULL, '', NULL),
(273, 'client', 'Виктория', NULL, NULL, 0.00, 'berrygo_store', '79233751300', NULL, NULL, NULL, NULL, NULL, '$2y$10$7nz8U9zgpBG9eVdpR1kxQuqiZ6L/dMvn6Xz0YfrncDyX7rGaLjqBy', 'PDJR3QCV', 17, 0, 75, 0, 0, '2026-06-16 15:50:48', NULL, '', NULL),
(274, 'client', 'Клиент', NULL, NULL, 0.00, 'berrygo_store', '79333172366', NULL, NULL, NULL, NULL, NULL, '$2y$10$xgq1lP7bG0addgYNxcHq1O7c/Tmu5IlhFROcnJj8M0elyG1mHpRgy', 'YGNXZWBJ', 17, 0, 75, 0, 0, '2026-06-17 15:37:07', NULL, '', NULL),
(275, 'client', 'Клиент', NULL, NULL, 0.00, 'berrygo_store', '79131950833', NULL, NULL, NULL, NULL, NULL, '$2y$10$7BuWCh/G3AERM0sDRvvrWOTlMr.e1kujuDrT4Acfl4/WKR49AwRpe', 'HVGS8FVL', 17, 0, 75, 0, 0, '2026-06-17 17:09:39', NULL, '', NULL),
(276, 'client', 'Клиент', NULL, NULL, 0.00, 'berrygo_store', '79080228190', NULL, NULL, NULL, NULL, NULL, '$2y$10$ltMg3xelfLUVu85Z.WNlvugKcNj6nOJYdv2EYwZXBWcCpQxmWHu7S', '68VA4F62', 17, 0, 75, 0, 0, '2026-06-17 17:11:08', NULL, '', NULL),
(277, 'client', 'Андрей', NULL, NULL, 0.00, 'berrygo_store', '79011596565', NULL, NULL, NULL, NULL, NULL, '$2y$10$P3bLaANCWHBfAzmaMjFsbud2ZG4fI616zsamYymvkEK4CJdEMB1JK', 'QHM3TSLX', 17, 0, 165, 0, 0, '2026-06-18 17:03:55', NULL, '', NULL),
(278, 'client', 'Владимир', NULL, NULL, 0.00, 'berrygo_store', '79233486829', NULL, NULL, NULL, NULL, NULL, '$2y$10$Xn08r6FwmVEsgVWd0UQ9sOi1//JbYTBdgdybKvWu8.jVm96IYAQNu', 'D9MBA43M', 17, 0, 75, 0, 0, '2026-06-18 17:06:05', NULL, '', NULL),
(279, 'client', 'Ирина', NULL, NULL, 0.00, 'berrygo_store', '79504212156', 'snegovichok96@mail.ru', '2026-06-22 23:31:47', NULL, NULL, NULL, '$2y$10$KDwpCjGdpxu1.cePFQFzdeAB96G8fmkRs/nUOrXg865AbUIhc.u/a', 'WKV9ZVMY', NULL, 0, 0, 0, 0, '2026-06-22 23:31:35', NULL, '', NULL),
(280, 'client', 'Софья', NULL, NULL, 0.00, 'berrygo_store', '79130597194', 'sonechka.sofishka@yandex.ru', NULL, 'bd573895a0aa713e040aa7d0ea0bbe8755f864997bf8e838fb56789931a84ef8', '2026-06-23 16:59:00', NULL, '$2y$10$wMqpuOkDMLtmgFT/xltheuPvTkNEPGyivmIHSuikd94k4RuMRAc1G', 'CXBMJE72', NULL, 0, 0, 0, 0, '2026-06-23 15:58:59', NULL, '', NULL),
(281, 'client', 'Егор', NULL, NULL, 0.00, 'berrygo_store', '79509727775', 'floksq27@gmail.com', '2026-06-23 16:10:24', NULL, NULL, NULL, '$2y$10$zCxfZwDVrRDf/JTz4B/H1ejpiyoC0O0OR6kqhF6sZCsyr1eyzpLMG', 'H5QBPE6P', NULL, 0, 0, 0, 0, '2026-06-23 16:10:03', NULL, '', NULL),
(282, 'client', 'Коптев Андрей', NULL, NULL, 0.00, 'berrygo_store', '79039208061', 'kopteff@mail.ru', '2026-06-26 05:56:14', NULL, NULL, NULL, '$2y$10$wAG40VJ4PSMEp3ENkiKltOGc.UFSz8dao9i.b3x.TO7XShqCHHNv.', 'LTJ6XGJR', NULL, 0, 0, 0, 0, '2026-06-26 05:55:48', NULL, '', NULL),
(283, 'client', 'Дарья', NULL, NULL, 0.00, 'berrygo_store', '79504175724', 'darakasumova8@gmai.com', NULL, '598626ee2e6ec7278756e2cb49b7231d1b9a0e356c8feba6b6c0d2abe0e67589', '2026-06-26 16:00:46', NULL, '$2y$10$R9n2R9677b7zcnM1kn3hgu0PBwNAKrW.qP0V48fdJplX.KIIb3rBm', '85H6JT2H', NULL, 0, 0, 0, 0, '2026-06-26 15:00:46', NULL, '', NULL),
(287, 'client', 'Дарья Владимировна Пономарева', NULL, NULL, 0.00, 'berrygo_store', '79330234983', 'darakasumova8@gmail.com', '2026-06-26 15:05:28', NULL, NULL, NULL, '$2y$10$rn2/S6u27JsxgiGkrryp2.qotRcJARz7BNuL8s4OvSjyLVo6GI5sG', 'KPESHRQ9', NULL, 0, 0, 0, 0, '2026-06-26 15:03:25', NULL, '', NULL),
(288, 'client', 'Никита', NULL, NULL, 0.00, 'berrygo_store', '79333297169', 'npetrov0496@gmail.com', '2026-06-30 14:34:42', NULL, NULL, NULL, '$2y$10$hf4wvJn0reveepBio95bkuHiaN2RwoXgOGsNxN62JUv1P/xYcIFXK', 'MKZAKBWZ', NULL, 0, 0, 0, 0, '2026-06-30 14:34:05', NULL, '', NULL),
(289, 'client', 'Диана', NULL, NULL, 0.00, 'berrygo_store', '79232747164', 'diana.khakhaleva.96@mail.ru', '2026-06-30 17:42:30', NULL, NULL, NULL, '$2y$10$L/e73o3yRZS5LqFxzA2hiulWyFuZuKCHye8/7szpHxiT4KXdhSk/u', 'Z7TMG3U7', NULL, 0, 0, 0, 0, '2026-06-30 17:42:13', NULL, '', NULL),
(290, 'client', 'Оксана Васильевна Воротникова', NULL, NULL, 0.00, 'berrygo_store', '79029433322', 'vorotnikova17@indox.ru', NULL, 'd3eab1c192dc86711b04be3c11ac0f8184178b33e4bf14427805bcb1dc8c5aa8', '2026-06-30 21:35:34', NULL, '$2y$10$L3aHGYlrUo8iy0j5pzxSTejEZsUKSoPfjsERXGKyc2aJr5EUXX9Z.', 'NJ7RFC5W', NULL, 0, 0, 0, 0, '2026-06-30 20:35:34', NULL, '', NULL),
(291, 'client', 'Эля', NULL, NULL, 0.00, 'berrygo_store', '79004122012', 'ela89004122012@yandex.ru', '2026-07-01 01:52:15', NULL, NULL, NULL, '$2y$10$J3kXxKDmkNOT8AhyGuyqMuhVUX2R/rJ3yyYC5pCB1VFbOxpTXnvDC', 'GLR7V9Q7', NULL, 0, 0, 0, 0, '2026-07-01 00:58:36', NULL, '', NULL),
(292, 'client', 'Ирина Сергеевна Боброва', NULL, NULL, 0.00, 'berrygo_store', '79029781670', 'mamzinai@mail.ru', NULL, '4169d6c8fbd3c009df190e1f6b6daca9a2c8ad8fbaf7c8c9659f66faa0d2f6d7', '2026-07-01 14:39:42', NULL, '$2y$10$WCnaz43neMq3DY3hcGIPK.OBjJ4.z/slPkdP9mL2LBHdK0OZi2RV6', '46C9DGN7', NULL, 0, 0, 0, 0, '2026-07-01 13:39:42', NULL, '', NULL),
(293, 'client', 'Наталья', NULL, NULL, 0.00, 'berrygo_store', '79135729280', '63nata@bk.ru', '2026-07-01 15:22:35', NULL, NULL, NULL, '$2y$10$9Gf88hBAfEOohB0pLyaT0uPhUHhvp0Jb6SkfdIpob8w4882bLMiNS', 'EJPBEXM9', NULL, 0, 0, 0, 0, '2026-07-01 15:21:28', NULL, '', NULL),
(294, 'client', 'Аникеева Наталья Александровна', NULL, NULL, 0.00, 'berrygo_store', '79233003110', 'anikeeva_natasha@mail.ru', '2026-07-03 13:34:18', NULL, NULL, NULL, '$2y$10$FWYhMd0HRlfSNYBR.YhYu.NxasueIrs6M6ltKyYEK9KcVpLwYUdSS', 'EDC5SEP3', NULL, 0, 0, 0, 0, '2026-07-03 13:34:06', NULL, '', NULL),
(295, 'client', 'Варвара', NULL, NULL, 0.00, 'berrygo_store', '79509782638', 'varkaut@mail.ru', '2026-07-04 11:04:41', NULL, NULL, NULL, '$2y$10$d/IUezN/LgFNkNH66M5P7uj5VXD/F2emjGVVczqgwjg.CcPPim0qi', 'FKXMX2RJ', NULL, 0, 0, 0, 0, '2026-07-04 11:04:09', NULL, '', NULL),
(296, 'seller', 'Berry Me Please', 'Berry Me Please', '9 мая 73', 0.00, 'berrygo_store', '79994405042', NULL, NULL, NULL, NULL, NULL, '$2y$10$SpBsobsUgyoQoAFQkEMD6uwLMjhKpsmobiu9PYakF1eRqdzJzDk/y', '83CG4QEE', NULL, 0, 0, 0, 0, '2026-07-06 13:45:52', NULL, '', NULL),
(297, 'client', 'Юлия', NULL, NULL, 0.00, 'berrygo_store', '79233741556', 'uliavolanskaa@gmail.com', '2026-07-06 18:08:39', NULL, NULL, NULL, '$2y$10$CgX2BGlSYC6LOgh0eehc4Os0W7M1SYrBIDIOFwFv9MAbNhtQnMi0q', 'NA7JL84Q', NULL, 0, 0, 0, 0, '2026-07-06 18:07:58', NULL, '', NULL);

--
-- Триггеры `users`
--
DELIMITER $$
CREATE TRIGGER `trg_users_bi_defaults` BEFORE INSERT ON `users` FOR EACH ROW BEGIN
  IF NEW.password_hash IS NULL OR NEW.password_hash = '' THEN
    SET NEW.password_hash = CONCAT('bot-only:', SHA2(UUID(), 256));
  END IF;
END
$$
DELIMITER ;

--
-- Индексы сохранённых таблиц
--

--
-- Индексы таблицы `addresses`
--
ALTER TABLE `addresses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Индексы таблицы `cart_items`
--
ALTER TABLE `cart_items`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_cart_items_user_product_mode_batch_key` (`user_id`,`product_id`,`stock_mode`,`purchase_batch_key`),
  ADD KEY `idx_cart_items_purchase_batch` (`purchase_batch_id`),
  ADD KEY `idx_cart_items_stock_mode` (`stock_mode`),
  ADD KEY `idx_cart_items_user_id` (`user_id`),
  ADD KEY `idx_cart_items_product_id` (`product_id`);

--
-- Индексы таблицы `content_categories`
--
ALTER TABLE `content_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `alias` (`alias`);

--
-- Индексы таблицы `coupons`
--
ALTER TABLE `coupons`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Индексы таблицы `delivery_slots`
--
ALTER TABLE `delivery_slots`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_slot` (`time_from`,`time_to`);

--
-- Индексы таблицы `delivery_tariff_zones`
--
ALTER TABLE `delivery_tariff_zones`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_delivery_tariff_zones_active_range` (`is_active`,`min_km`,`max_km`),
  ADD KEY `idx_delivery_tariff_zones_sort` (`sort_order`,`min_km`);

--
-- Индексы таблицы `favorites`
--
ALTER TABLE `favorites`
  ADD PRIMARY KEY (`user_id`,`product_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Индексы таблицы `mailing_clients`
--
ALTER TABLE `mailing_clients`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_user` (`user_id`);

--
-- Индексы таблицы `materials`
--
ALTER TABLE `materials`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `alias` (`alias`),
  ADD KEY `category_id` (`category_id`);

--
-- Индексы таблицы `metadata`
--
ALTER TABLE `metadata`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `page` (`page`);

--
-- Индексы таблицы `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `notification_channel_settings`
--
ALTER TABLE `notification_channel_settings`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `address_id` (`address_id`),
  ADD KEY `slot_id` (`slot_id`),
  ADD KEY `assigned_to` (`assigned_to`),
  ADD KEY `idx_orders_order_mode` (`order_mode`),
  ADD KEY `idx_orders_purchase_batch` (`purchase_batch_id`),
  ADD KEY `idx_orders_delivery_mode` (`delivery_date`,`order_mode`),
  ADD KEY `idx_orders_order_group_id` (`order_group_id`);

--
-- Индексы таблицы `order_groups`
--
ALTER TABLE `order_groups`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_order_groups_user` (`user_id`),
  ADD KEY `idx_order_groups_created_by` (`created_by_user_id`);

--
-- Индексы таблицы `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_order_items_order_product_batch_mode` (`order_id`,`product_id`,`purchase_batch_id`,`stock_mode`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `idx_order_items_purchase_batch` (`purchase_batch_id`),
  ADD KEY `idx_order_items_stock_mode` (`stock_mode`),
  ADD KEY `idx_order_items_order_id` (`order_id`);

--
-- Индексы таблицы `order_status_history`
--
ALTER TABLE `order_status_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_order_status_history_order_id` (`order_id`),
  ADD KEY `idx_order_status_history_created_at` (`created_at`);

--
-- Индексы таблицы `partner_profiles`
--
ALTER TABLE `partner_profiles`
  ADD PRIMARY KEY (`user_id`),
  ADD KEY `idx_partner_profiles_type_status` (`partner_type`,`status`),
  ADD KEY `idx_partner_profiles_visibility` (`client_visibility`);

--
-- Индексы таблицы `points_transactions`
--
ALTER TABLE `points_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_points_tx_user` (`user_id`),
  ADD KEY `fk_points_tx_order` (`order_id`);

--
-- Индексы таблицы `preorder_intents`
--
ALTER TABLE `preorder_intents`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_preorder_intents_checkout_token` (`checkout_token`),
  ADD KEY `idx_preorder_intents_product_status_created` (`product_id`,`status`,`created_at`),
  ADD KEY `idx_preorder_intents_user_product_status` (`user_id`,`product_id`,`status`),
  ADD KEY `idx_preorder_intents_expires_status` (`offer_expires_at`,`status`),
  ADD KEY `idx_preorder_intents_batch_status` (`purchase_batch_id`,`status`);

--
-- Индексы таблицы `preorder_intent_events`
--
ALTER TABLE `preorder_intent_events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_preorder_intent_events_intent` (`preorder_intent_id`,`created_at`),
  ADD KEY `idx_preorder_intent_events_event` (`event_type`,`created_at`);

--
-- Индексы таблицы `production_executor_settings`
--
ALTER TABLE `production_executor_settings`
  ADD PRIMARY KEY (`user_id`),
  ADD KEY `idx_production_executor_settings_available` (`executor_type`,`is_active`,`current_mode`),
  ADD KEY `idx_production_executor_settings_mode` (`current_mode`);

--
-- Индексы таблицы `production_jobs`
--
ALTER TABLE `production_jobs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_production_jobs_order_id` (`order_id`),
  ADD KEY `idx_production_jobs_status_deadline` (`status`,`production_deadline`),
  ADD KEY `idx_production_jobs_executor_status` (`executor_type`,`executor_id`,`status`),
  ADD KEY `idx_production_jobs_fulfillment_status` (`fulfillment_model`,`status`);

--
-- Индексы таблицы `production_job_events`
--
ALTER TABLE `production_job_events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_production_job_events_job_id` (`job_id`),
  ADD KEY `idx_production_job_events_order_id` (`order_id`),
  ADD KEY `idx_production_job_events_created_at` (`created_at`);

--
-- Индексы таблицы `production_job_photos`
--
ALTER TABLE `production_job_photos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_production_job_photos_job_id` (`job_id`),
  ADD KEY `idx_production_job_photos_order_id` (`order_id`),
  ADD KEY `idx_production_job_photos_review_status` (`review_status`);

--
-- Индексы таблицы `production_specs`
--
ALTER TABLE `production_specs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_production_specs_product_id` (`product_id`);

--
-- Индексы таблицы `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `alias` (`alias`),
  ADD KEY `fk_products_type` (`product_type_id`),
  ADD KEY `fk_products_seller` (`seller_id`),
  ADD KEY `idx_products_current_batch` (`current_purchase_batch_id`),
  ADD KEY `idx_products_stock_status` (`stock_status`);

--
-- Индексы таблицы `product_types`
--
ALTER TABLE `product_types`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`),
  ADD UNIQUE KEY `alias` (`alias`),
  ADD KEY `fk_product_types_seller` (`seller_id`);

--
-- Индексы таблицы `purchase_batches`
--
ALTER TABLE `purchase_batches`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_purchase_batches_product` (`product_id`),
  ADD KEY `idx_purchase_batches_buyer` (`buyer_user_id`),
  ADD KEY `idx_purchase_batches_status` (`status`),
  ADD KEY `idx_purchase_batches_purchased_at` (`purchased_at`),
  ADD KEY `idx_purchase_batches_product_status_free` (`product_id`,`status`,`boxes_free`);

--
-- Индексы таблицы `purchase_batch_photos`
--
ALTER TABLE `purchase_batch_photos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_purchase_batch_photos_batch` (`purchase_batch_id`);

--
-- Индексы таблицы `referrals`
--
ALTER TABLE `referrals`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ux_referrals_pair` (`referrer_id`,`referred_id`),
  ADD KEY `fk_referrals_referred` (`referred_id`);

--
-- Индексы таблицы `seller_payouts`
--
ALTER TABLE `seller_payouts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_sp_seller` (`seller_id`),
  ADD KEY `fk_sp_order` (`order_id`);

--
-- Индексы таблицы `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`setting_key`);

--
-- Индексы таблицы `sitemap_settings`
--
ALTER TABLE `sitemap_settings`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `stock_movements`
--
ALTER TABLE `stock_movements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_stock_movements_batch` (`purchase_batch_id`),
  ADD KEY `idx_stock_movements_product` (`product_id`),
  ADD KEY `idx_stock_movements_order` (`order_id`),
  ADD KEY `idx_stock_movements_type` (`movement_type`),
  ADD KEY `fk_stock_movements_user` (`user_id`);

--
-- Индексы таблицы `support_chats`
--
ALTER TABLE `support_chats`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_support_chats_user_order` (`user_id`,`order_id`),
  ADD KEY `idx_support_chats_last_message` (`last_message_at`),
  ADD KEY `idx_support_chats_staff_unread` (`staff_unread_count`),
  ADD KEY `idx_support_chats_user_last` (`user_id`,`last_message_at`),
  ADD KEY `fk_support_chats_order` (`order_id`);

--
-- Индексы таблицы `support_messages`
--
ALTER TABLE `support_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_support_messages_chat_id` (`chat_id`,`id`),
  ADD KEY `idx_support_messages_sender` (`sender_user_id`);

--
-- Индексы таблицы `support_message_attachments`
--
ALTER TABLE `support_message_attachments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_support_attachments_message` (`message_id`);

--
-- Индексы таблицы `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `phone` (`phone`),
  ADD UNIQUE KEY `referral_code` (`referral_code`),
  ADD UNIQUE KEY `users_email_unique` (`email`),
  ADD KEY `fk_users_referred_by` (`referred_by`),
  ADD KEY `telegram_id` (`telegram_id`),
  ADD KEY `idx_users_address_id` (`address_id`);

--
-- AUTO_INCREMENT для сохранённых таблиц
--

--
-- AUTO_INCREMENT для таблицы `addresses`
--
ALTER TABLE `addresses`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=326;

--
-- AUTO_INCREMENT для таблицы `cart_items`
--
ALTER TABLE `cart_items`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT для таблицы `content_categories`
--
ALTER TABLE `content_categories`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT для таблицы `coupons`
--
ALTER TABLE `coupons`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT для таблицы `delivery_slots`
--
ALTER TABLE `delivery_slots`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT для таблицы `delivery_tariff_zones`
--
ALTER TABLE `delivery_tariff_zones`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT для таблицы `mailing_clients`
--
ALTER TABLE `mailing_clients`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT для таблицы `materials`
--
ALTER TABLE `materials`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=63;

--
-- AUTO_INCREMENT для таблицы `metadata`
--
ALTER TABLE `metadata`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT для таблицы `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT для таблицы `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=497;

--
-- AUTO_INCREMENT для таблицы `order_groups`
--
ALTER TABLE `order_groups`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT для таблицы `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=333;

--
-- AUTO_INCREMENT для таблицы `order_status_history`
--
ALTER TABLE `order_status_history`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT для таблицы `points_transactions`
--
ALTER TABLE `points_transactions`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=768;

--
-- AUTO_INCREMENT для таблицы `preorder_intents`
--
ALTER TABLE `preorder_intents`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `preorder_intent_events`
--
ALTER TABLE `preorder_intent_events`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=71;

--
-- AUTO_INCREMENT для таблицы `production_jobs`
--
ALTER TABLE `production_jobs`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `production_job_events`
--
ALTER TABLE `production_job_events`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `production_job_photos`
--
ALTER TABLE `production_job_photos`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `production_specs`
--
ALTER TABLE `production_specs`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT для таблицы `products`
--
ALTER TABLE `products`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=38;

--
-- AUTO_INCREMENT для таблицы `product_types`
--
ALTER TABLE `product_types`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT для таблицы `purchase_batches`
--
ALTER TABLE `purchase_batches`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `purchase_batch_photos`
--
ALTER TABLE `purchase_batch_photos`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT для таблицы `referrals`
--
ALTER TABLE `referrals`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=260;

--
-- AUTO_INCREMENT для таблицы `seller_payouts`
--
ALTER TABLE `seller_payouts`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT для таблицы `sitemap_settings`
--
ALTER TABLE `sitemap_settings`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT для таблицы `stock_movements`
--
ALTER TABLE `stock_movements`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=79;

--
-- AUTO_INCREMENT для таблицы `support_chats`
--
ALTER TABLE `support_chats`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT для таблицы `support_messages`
--
ALTER TABLE `support_messages`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT для таблицы `support_message_attachments`
--
ALTER TABLE `support_message_attachments`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `users`
--
ALTER TABLE `users`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=300;

--
-- Ограничения внешнего ключа сохраненных таблиц
--

--
-- Ограничения внешнего ключа таблицы `addresses`
--
ALTER TABLE `addresses`
  ADD CONSTRAINT `fk_addresses_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `cart_items`
--
ALTER TABLE `cart_items`
  ADD CONSTRAINT `cart_items_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `cart_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  ADD CONSTRAINT `fk_cart_items_purchase_batch` FOREIGN KEY (`purchase_batch_id`) REFERENCES `purchase_batches` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT;

--
-- Ограничения внешнего ключа таблицы `favorites`
--
ALTER TABLE `favorites`
  ADD CONSTRAINT `fk_favorites_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_favorites_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `mailing_clients`
--
ALTER TABLE `mailing_clients`
  ADD CONSTRAINT `fk_mailing_clients_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `materials`
--
ALTER TABLE `materials`
  ADD CONSTRAINT `materials_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `content_categories` (`id`);

--
-- Ограничения внешнего ключа таблицы `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `fk_orders_address` FOREIGN KEY (`address_id`) REFERENCES `addresses` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_orders_courier` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_orders_order_group` FOREIGN KEY (`order_group_id`) REFERENCES `order_groups` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_orders_purchase_batch` FOREIGN KEY (`purchase_batch_id`) REFERENCES `purchase_batches` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_orders_slot` FOREIGN KEY (`slot_id`) REFERENCES `delivery_slots` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_orders_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `order_groups`
--
ALTER TABLE `order_groups`
  ADD CONSTRAINT `fk_order_groups_created_by` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_order_groups_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `fk_order_items_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_order_items_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_order_items_purchase_batch` FOREIGN KEY (`purchase_batch_id`) REFERENCES `purchase_batches` (`id`) ON DELETE SET NULL;

--
-- Ограничения внешнего ключа таблицы `points_transactions`
--
ALTER TABLE `points_transactions`
  ADD CONSTRAINT `fk_points_tx_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_points_tx_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `fk_products_current_batch` FOREIGN KEY (`current_purchase_batch_id`) REFERENCES `purchase_batches` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_products_seller` FOREIGN KEY (`seller_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_products_type` FOREIGN KEY (`product_type_id`) REFERENCES `product_types` (`id`) ON DELETE RESTRICT;

--
-- Ограничения внешнего ключа таблицы `product_types`
--
ALTER TABLE `product_types`
  ADD CONSTRAINT `fk_product_types_seller` FOREIGN KEY (`seller_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Ограничения внешнего ключа таблицы `purchase_batches`
--
ALTER TABLE `purchase_batches`
  ADD CONSTRAINT `fk_purchase_batches_buyer` FOREIGN KEY (`buyer_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_purchase_batches_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE RESTRICT;

--
-- Ограничения внешнего ключа таблицы `purchase_batch_photos`
--
ALTER TABLE `purchase_batch_photos`
  ADD CONSTRAINT `fk_purchase_batch_photos_batch` FOREIGN KEY (`purchase_batch_id`) REFERENCES `purchase_batches` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `referrals`
--
ALTER TABLE `referrals`
  ADD CONSTRAINT `fk_referrals_referred` FOREIGN KEY (`referred_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_referrals_referrer` FOREIGN KEY (`referrer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `seller_payouts`
--
ALTER TABLE `seller_payouts`
  ADD CONSTRAINT `fk_sp_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_sp_seller` FOREIGN KEY (`seller_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `stock_movements`
--
ALTER TABLE `stock_movements`
  ADD CONSTRAINT `fk_stock_movements_batch` FOREIGN KEY (`purchase_batch_id`) REFERENCES `purchase_batches` (`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_stock_movements_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_stock_movements_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_stock_movements_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Ограничения внешнего ключа таблицы `support_chats`
--
ALTER TABLE `support_chats`
  ADD CONSTRAINT `fk_support_chats_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_support_chats_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `support_messages`
--
ALTER TABLE `support_messages`
  ADD CONSTRAINT `fk_support_messages_chat` FOREIGN KEY (`chat_id`) REFERENCES `support_chats` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_support_messages_sender` FOREIGN KEY (`sender_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Ограничения внешнего ключа таблицы `support_message_attachments`
--
ALTER TABLE `support_message_attachments`
  ADD CONSTRAINT `fk_support_attachments_message` FOREIGN KEY (`message_id`) REFERENCES `support_messages` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_users_referred_by` FOREIGN KEY (`referred_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;
-- Florix24 inbound integration schema (kept here for clean installations).
CREATE TABLE IF NOT EXISTS `integration_clients` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL, `source` varchar(50) NOT NULL,
  `token_hash` varchar(255) NOT NULL, `token_prefix` varchar(32) NOT NULL DEFAULT '',
  `permissions` json DEFAULT NULL, `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `allowed_ips` text, `ip_check_enabled` tinyint(1) NOT NULL DEFAULT '0', `trusted_proxy_mode` tinyint(1) NOT NULL DEFAULT '0',
  `rate_limit_per_minute` int unsigned NOT NULL DEFAULT '60', `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `last_used_at` datetime DEFAULT NULL, `expires_at` datetime DEFAULT NULL, `revoked_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`), UNIQUE KEY `uq_integration_clients_source` (`source`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `integration_request_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT, `integration_client_id` bigint unsigned DEFAULT NULL,
  `source` varchar(50) NOT NULL, `endpoint` varchar(255) NOT NULL, `request_payload` json DEFAULT NULL, `response_payload` json DEFAULT NULL,
  `http_status` smallint unsigned NOT NULL, `external_order_id` varchar(128) DEFAULT NULL, `partner_user_id` int unsigned DEFAULT NULL,
  `points_used` int NOT NULL DEFAULT '0', `error_code` varchar(64) DEFAULT NULL, `correlation_id` varchar(64) NOT NULL,
  `processing_ms` int unsigned NOT NULL DEFAULT '0', `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`), KEY `idx_integration_request_logs_source_created` (`source`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `integration_rate_limit_windows` (`integration_client_id` bigint unsigned NOT NULL,`window_started_at` datetime NOT NULL,`request_count` int unsigned NOT NULL DEFAULT '0',PRIMARY KEY (`integration_client_id`,`window_started_at`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `catalog_feed_state` (`id` tinyint unsigned NOT NULL,`is_dirty` tinyint(1) NOT NULL DEFAULT '1',`generated_at` datetime DEFAULT NULL,`last_error` text,`updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,PRIMARY KEY (`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT IGNORE INTO `catalog_feed_state` (`id`,`is_dirty`) VALUES (1,1);
ALTER TABLE `orders` ADD COLUMN `integration_source` varchar(50) DEFAULT NULL, ADD COLUMN `external_order_id` varchar(128) DEFAULT NULL, ADD COLUMN `partner_user_id` int unsigned DEFAULT NULL, ADD COLUMN `partner_source` varchar(50) DEFAULT NULL, ADD COLUMN `external_partner_id` varchar(128) DEFAULT NULL, ADD COLUMN `external_partner_name` varchar(255) DEFAULT NULL, ADD COLUMN `subtotal_before_points` decimal(10,2) DEFAULT NULL, ADD COLUMN `points_discount_amount` decimal(10,2) NOT NULL DEFAULT '0', ADD COLUMN `total_after_points` decimal(10,2) DEFAULT NULL, ADD UNIQUE KEY `uq_orders_integration_external` (`integration_source`,`external_order_id`);
ALTER TABLE `products` ADD COLUMN `external_catalog_enabled` tinyint(1) NOT NULL DEFAULT '0', ADD COLUMN `external_name` varchar(255) DEFAULT NULL, ADD COLUMN `external_description` text, ADD COLUMN `external_sku` varchar(128) DEFAULT NULL, ADD COLUMN `external_image_path` varchar(255) DEFAULT NULL, ADD COLUMN `external_updated_at` datetime DEFAULT NULL;
ALTER TABLE `users` ADD COLUMN `integration_partner_enabled` tinyint(1) NOT NULL DEFAULT '0';
ALTER TABLE `points_transactions` MODIFY COLUMN `transaction_type` enum('accrual','usage','payout','refund','partner_reward','partner_reward_reversal') NOT NULL, ADD COLUMN `source` varchar(64) DEFAULT NULL, ADD COLUMN `external_order_id` varchar(128) DEFAULT NULL, ADD COLUMN `related_transaction_id` int unsigned DEFAULT NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
