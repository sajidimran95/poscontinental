-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Jul 28, 2026 at 02:50 PM
-- Server version: 8.4.3
-- PHP Version: 8.4.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `poscontinentalwholesale`
--

-- --------------------------------------------------------

--
-- Table structure for table `bulk_price_change_items`
--

CREATE TABLE `bulk_price_change_items` (
  `id` bigint UNSIGNED NOT NULL,
  `bulk_price_change_log_id` bigint UNSIGNED NOT NULL,
  `item_id` bigint UNSIGNED NOT NULL,
  `item_code` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `list_price_before` decimal(14,4) DEFAULT NULL,
  `list_price_after` decimal(14,4) DEFAULT NULL,
  `standard_cost_before` decimal(14,4) DEFAULT NULL,
  `standard_cost_after` decimal(14,4) DEFAULT NULL,
  `current_cost_before` decimal(14,4) DEFAULT NULL,
  `current_cost_after` decimal(14,4) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `bulk_price_change_items`
--

INSERT INTO `bulk_price_change_items` (`id`, `bulk_price_change_log_id`, `item_id`, `item_code`, `list_price_before`, `list_price_after`, `standard_cost_before`, `standard_cost_after`, `current_cost_before`, `current_cost_after`, `created_at`, `updated_at`) VALUES
(1, 1, 1, NULL, 9.9900, 9.9900, NULL, NULL, NULL, NULL, '2026-07-21 15:37:03', '2026-07-21 15:37:03'),
(2, 1, 2, NULL, 72.5000, 72.5000, NULL, NULL, NULL, NULL, '2026-07-21 15:37:03', '2026-07-21 15:37:03');

-- --------------------------------------------------------

--
-- Table structure for table `bulk_price_change_logs`
--

CREATE TABLE `bulk_price_change_logs` (
  `id` bigint UNSIGNED NOT NULL,
  `company_id` bigint UNSIGNED NOT NULL,
  `user_id` bigint UNSIGNED DEFAULT NULL,
  `filter_criteria` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `adjustment_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `adjustment_value` decimal(12,4) NOT NULL DEFAULT '0.0000',
  `targets` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `items_affected` int UNSIGNED NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ;

--
-- Dumping data for table `bulk_price_change_logs`
--

INSERT INTO `bulk_price_change_logs` (`id`, `company_id`, `user_id`, `filter_criteria`, `adjustment_type`, `adjustment_value`, `targets`, `items_affected`, `created_at`, `updated_at`) VALUES
(1, 1, 1, '{\"search\": \"\", \"target\": \"list_price\", \"category_id\": null, \"department_id\": null}', 'percent', 0.0000, '[\"list_price\"]', 2, '2026-07-21 15:37:03', '2026-07-21 15:37:03');

-- --------------------------------------------------------

--
-- Table structure for table `cache`
--

CREATE TABLE `cache` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` bigint NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cache_locks`
--

CREATE TABLE `cache_locks` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` bigint NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` bigint UNSIGNED NOT NULL,
  `company_id` bigint UNSIGNED NOT NULL,
  `department_id` bigint UNSIGNED NOT NULL,
  `code` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `company_id`, `department_id`, `code`, `name`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 'CIG', 'Cigarettes', 1, '2026-07-21 08:14:33', '2026-07-21 08:14:33'),
(2, 1, 1, 'OTP', 'Other Tobacco', 1, '2026-07-24 08:03:43', '2026-07-24 08:03:43'),
(3, 1, 2, 'SODA', 'Soda', 1, '2026-07-24 08:03:43', '2026-07-24 08:03:43'),
(4, 1, 2, 'WATER', 'Water', 1, '2026-07-24 08:03:43', '2026-07-24 08:03:43'),
(5, 1, 3, 'CHIP', 'Chips', 1, '2026-07-24 08:03:43', '2026-07-24 08:03:43'),
(6, 1, 3, 'CANDY', 'Candy', 1, '2026-07-24 08:03:43', '2026-07-24 08:03:43'),
(7, 1, 4, 'DRY', 'Dry Goods', 1, '2026-07-24 08:03:43', '2026-07-24 08:03:43');

-- --------------------------------------------------------

--
-- Table structure for table `cigarette_tax_classes`
--

CREATE TABLE `cigarette_tax_classes` (
  `id` bigint UNSIGNED NOT NULL,
  `company_id` bigint UNSIGNED NOT NULL,
  `code` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `cigarette_tax_classes`
--

INSERT INTO `cigarette_tax_classes` (`id`, `company_id`, `code`, `name`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 1, 'STD', 'Standard Cigarette Tax', 1, '2026-07-21 08:14:33', '2026-07-21 08:14:33');

-- --------------------------------------------------------

--
-- Table structure for table `companies`
--

CREATE TABLE `companies` (
  `id` bigint UNSIGNED NOT NULL,
  `code` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `fein_no` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `customer_app_api_active` tinyint(1) NOT NULL DEFAULT '1',
  `mail_mailer` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'log',
  `mail_host` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mail_port` int UNSIGNED DEFAULT NULL,
  `mail_username` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mail_password` text COLLATE utf8mb4_unicode_ci,
  `mail_encryption` varchar(16) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mail_from_address` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mail_from_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `companies`
--

INSERT INTO `companies` (`id`, `code`, `name`, `fein_no`, `is_active`, `customer_app_api_active`, `mail_mailer`, `mail_host`, `mail_port`, `mail_username`, `mail_password`, `mail_encryption`, `mail_from_address`, `mail_from_name`, `created_at`, `updated_at`) VALUES
(1, 'CWI', 'Continental Wholesale Inc', NULL, 1, 1, 'log', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-07-21 08:14:32', '2026-07-21 08:14:32');

-- --------------------------------------------------------

--
-- Table structure for table `credit_memos`
--

CREATE TABLE `credit_memos` (
  `id` bigint UNSIGNED NOT NULL,
  `company_id` bigint UNSIGNED NOT NULL,
  `memo_number` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `memo_date` date DEFAULT NULL,
  `reference_no` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `customer_id` bigint UNSIGNED DEFAULT NULL,
  `sales_order_id` bigint UNSIGNED DEFAULT NULL,
  `amount` decimal(14,4) NOT NULL DEFAULT '0.0000',
  `status` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Open',
  `comments` text COLLATE utf8mb4_unicode_ci,
  `restock_inventory` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `credit_memos`
--

INSERT INTO `credit_memos` (`id`, `company_id`, `memo_number`, `memo_date`, `reference_no`, `reason`, `customer_id`, `sales_order_id`, `amount`, `status`, `comments`, `restock_inventory`, `created_at`, `updated_at`) VALUES
(1, 1, '50001', '2026-07-27', '100001', 'Return', 3, 1, 9.9900, 'Open', '', 1, '2026-07-27 15:31:38', '2026-07-27 15:31:38');

-- --------------------------------------------------------

--
-- Table structure for table `credit_memo_lines`
--

CREATE TABLE `credit_memo_lines` (
  `id` bigint UNSIGNED NOT NULL,
  `credit_memo_id` bigint UNSIGNED NOT NULL,
  `item_id` bigint UNSIGNED DEFAULT NULL,
  `item_code` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `uom` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `qty` decimal(14,4) NOT NULL DEFAULT '0.0000',
  `price` decimal(14,4) NOT NULL DEFAULT '0.0000',
  `line_total` decimal(14,4) NOT NULL DEFAULT '0.0000',
  `line_no` int UNSIGNED NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `credit_memo_lines`
--

INSERT INTO `credit_memo_lines` (`id`, `credit_memo_id`, `item_id`, `item_code`, `description`, `uom`, `qty`, `price`, `line_total`, `line_no`, `created_at`, `updated_at`) VALUES
(1, 1, 1, '1229W', 'SOUR PATCH WATERMELON 240CT', 'BX', 1.0000, 9.9900, 9.9900, 1, '2026-07-27 15:31:38', '2026-07-27 15:31:38');

-- --------------------------------------------------------

--
-- Table structure for table `customers`
--

CREATE TABLE `customers` (
  `id` bigint UNSIGNED NOT NULL,
  `company_id` bigint UNSIGNED NOT NULL,
  `customer_id` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_inactive` tinyint(1) NOT NULL DEFAULT '0',
  `is_favorite` tinyint(1) NOT NULL DEFAULT '0',
  `contact` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `company_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `city` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `state` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `zip_code` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `country` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'US',
  `telephone` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telephone2` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mobile` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fax` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `portal_email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `portal_password` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `portal_active` tinyint(1) NOT NULL DEFAULT '0',
  `web_page` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `price_level_id` bigint UNSIGNED DEFAULT NULL,
  `cigarette_tax_class_id` bigint UNSIGNED DEFAULT NULL,
  `discount_schedule_id` bigint UNSIGNED DEFAULT NULL,
  `purchase_limit_schedule_id` bigint UNSIGNED DEFAULT NULL,
  `payment_term_id` bigint UNSIGNED DEFAULT NULL,
  `sales_rep_id` bigint UNSIGNED DEFAULT NULL,
  `lead_source` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `customer_category` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `opt_out_catalog` tinyint(1) NOT NULL DEFAULT '0',
  `delivery_route_id` bigint UNSIGNED DEFAULT NULL,
  `fein_no` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `account_type` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `credit_limit` decimal(14,2) NOT NULL DEFAULT '0.00',
  `balance` decimal(14,2) NOT NULL DEFAULT '0.00',
  `messages_alerts` text COLLATE utf8mb4_unicode_ci,
  `comments` text COLLATE utf8mb4_unicode_ci,
  `is_tax_exempt` tinyint(1) NOT NULL DEFAULT '0',
  `tax_certificate_no` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tax_certificate_exp` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `opt_out_email` tinyint(1) NOT NULL DEFAULT '0',
  `opt_out_telemarketing` tinyint(1) NOT NULL DEFAULT '0',
  `opt_out_mobile` tinyint(1) NOT NULL DEFAULT '0',
  `opt_out_all` tinyint(1) NOT NULL DEFAULT '0',
  `customer_since` date DEFAULT NULL,
  `last_order_on` date DEFAULT NULL,
  `number_of_orders` int UNSIGNED NOT NULL DEFAULT '0',
  `total_sales` decimal(14,2) NOT NULL DEFAULT '0.00',
  `bad_checks_count` int UNSIGNED NOT NULL DEFAULT '0',
  `replacements_count` int UNSIGNED NOT NULL DEFAULT '0',
  `returns_count` int UNSIGNED NOT NULL DEFAULT '0',
  `order_day` varchar(16) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `location_no` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `drivers_accept_returns` tinyint(1) NOT NULL DEFAULT '0',
  `certificate_on_file` tinyint(1) NOT NULL DEFAULT '0',
  `is_employee` tinyint(1) NOT NULL DEFAULT '0',
  `owner_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `owner_ssn` text COLLATE utf8mb4_unicode_ci,
  `owner_address` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `owner_city` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `owner_state` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `owner_zip` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `owner_country` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `owner_telephone` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `owner_fax` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `owner_email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `customers`
--

INSERT INTO `customers` (`id`, `company_id`, `customer_id`, `is_inactive`, `is_favorite`, `contact`, `company_name`, `address`, `city`, `state`, `zip_code`, `country`, `telephone`, `telephone2`, `mobile`, `fax`, `email`, `portal_email`, `portal_password`, `portal_active`, `web_page`, `price_level_id`, `cigarette_tax_class_id`, `discount_schedule_id`, `purchase_limit_schedule_id`, `payment_term_id`, `sales_rep_id`, `lead_source`, `customer_category`, `opt_out_catalog`, `delivery_route_id`, `fein_no`, `account_type`, `credit_limit`, `balance`, `messages_alerts`, `comments`, `is_tax_exempt`, `tax_certificate_no`, `tax_certificate_exp`, `created_at`, `updated_at`, `opt_out_email`, `opt_out_telemarketing`, `opt_out_mobile`, `opt_out_all`, `customer_since`, `last_order_on`, `number_of_orders`, `total_sales`, `bad_checks_count`, `replacements_count`, `returns_count`, `order_day`, `location_no`, `drivers_accept_returns`, `certificate_on_file`, `is_employee`, `owner_name`, `owner_ssn`, `owner_address`, `owner_city`, `owner_state`, `owner_zip`, `owner_country`, `owner_telephone`, `owner_fax`, `owner_email`) VALUES
(1, 1, '1001', 0, 0, 'Test Contact', 'SPK2 LLC', '3510 WILLIS RD', 'MILAN', 'MI', '48160', 'US', '7343519538', NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, 5000.00, 0.00, NULL, NULL, 0, NULL, NULL, '2026-07-21 08:15:10', '2026-07-22 08:15:08', 0, 0, 0, 0, NULL, NULL, 0, 0.00, 0, 0, 0, NULL, NULL, 0, 0, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(2, 1, 'Qui laborum autem ut', 0, 0, 'Voluptate modi sapie', 'Quia optio perferen', 'Consequuntur illo do', 'Deserunt deserunt ab', 'Omnis accusantium co', 'Praesentium rem plac', 'Nobis aute dolores h', 'Nemo sed dolore offi', 'Voluptatem eu volupt', 'Pariatur Reprehende', 'Fuga Aute at ut ill', 'lewo@mailinator.com', NULL, NULL, 0, 'Est voluptates illum', NULL, NULL, NULL, NULL, NULL, NULL, '', '', 0, NULL, '', '', 0.00, 0.00, '', '', 0, '', NULL, '2026-07-21 11:30:02', '2026-07-23 21:06:05', 0, 0, 0, 0, '2026-07-21', '2015-02-27', 1, 72.54, 0, 0, 0, '', '', 0, 0, 0, '', NULL, '', '', '', '', 'US', '', '', ''),
(3, 1, 'Ex beatae temporibus', 0, 0, 'Repellendus Volupta', 'Et doloribus odio do', 'Qui sed labore conse', 'Fugit non excepteur', 'Labore et nobis iure', 'Alias expedita volup', 'Molestias sunt adip', 'Nesciunt quia quod ', 'Officia sit exercit', 'Fugiat esse nobis ', 'Ipsum molestiae quia', 'hohizu@mailinator.com', NULL, NULL, 0, 'Quam deserunt adipis', NULL, NULL, NULL, NULL, NULL, NULL, '', '', 0, NULL, '', '', 0.00, 0.00, '', '', 0, '', NULL, '2026-07-21 12:06:17', '2026-07-27 14:46:49', 0, 0, 0, 0, '2026-07-21', '2026-07-27', 2, 60.27, 0, 0, 0, '', '', 0, 0, 0, '', NULL, '', '', '', '', 'US', '', '', ''),
(4, 1, 'Corrupti repellendu', 0, 1, 'Ad rerum ea minus si', 'Butler Melton LLC', 'Molestias porro ut c', 'Adipisicing totam ad', 'Ad non libero sit ma', '74803', 'Dolor et eos omnis ', '+1 (121) 216-9453', '+1 (899) 371-3421', 'Repudiandae eius con', '+1 (952) 654-1242', 'fahoteso@mailinator.com', NULL, NULL, 0, 'Ut nulla doloremque ', NULL, NULL, NULL, NULL, NULL, NULL, '', '', 0, NULL, '', '', 0.00, 18.76, '', '', 0, '', NULL, '2026-07-21 13:08:02', '2026-07-28 08:49:47', 0, 0, 0, 0, '2026-07-21', '2026-07-28', 5, 612.57, 0, 0, 0, '', '', 0, 0, 0, '', NULL, '', '', '', '', 'US', '', '', ''),
(5, 1, 'C1001', 0, 0, 'Raj Patel', 'Metro Convenience Mart', '450 Woodward Ave', 'Detroit', 'MI', '48226', 'US', '313-555-1001', NULL, NULL, NULL, 'metro@demo.local', NULL, NULL, 0, NULL, 1, NULL, NULL, NULL, 1, 2, 'Sales Call', 'Convenience', 0, 1, '38-1112233', 'Open Account', 15000.00, 1250.50, NULL, NULL, 0, NULL, NULL, '2026-07-24 08:03:43', '2026-07-24 08:03:43', 0, 0, 0, 0, '2025-01-24', NULL, 12, 18500.00, 0, 0, 0, NULL, NULL, 0, 0, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(6, 1, 'C1002', 0, 0, 'Lisa Nguyen', 'Quick Stop Fuels', '88 Telegraph Rd', 'Southfield', 'MI', '48033', 'US', '248-555-1002', NULL, NULL, NULL, 'quickstop@demo.local', NULL, NULL, 0, NULL, 2, NULL, NULL, NULL, 1, 2, 'Sales Call', 'Retail', 0, 1, '38-2223344', 'COD', 5000.00, 0.00, NULL, NULL, 0, NULL, NULL, '2026-07-24 08:03:43', '2026-07-24 08:03:43', 0, 0, 0, 0, '2025-01-24', NULL, 12, 18500.00, 0, 0, 0, NULL, NULL, 0, 0, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(7, 1, 'C1003', 0, 0, 'Tom Bradley', 'Great Lakes Chain Stores', '2100 Corporate Dr', 'Troy', 'MI', '48084', 'US', '248-555-1003', NULL, NULL, NULL, 'ap@glchain-demo.local', NULL, NULL, 0, NULL, 3, NULL, NULL, NULL, 1, 2, 'Sales Call', 'Chain', 0, 1, '38-3334455', 'Open Account', 75000.00, 8420.00, NULL, NULL, 0, NULL, NULL, '2026-07-24 08:03:43', '2026-07-24 08:03:43', 0, 0, 0, 0, '2025-01-24', NULL, 12, 18500.00, 0, 0, 0, NULL, NULL, 0, 0, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(8, 1, 'C1004', 0, 0, 'Nina Kowalski', 'Harbor Wholesale Dist.', '15 Dock St', 'Port Huron', 'MI', '48060', 'US', '810-555-1004', NULL, NULL, NULL, 'orders@harbor-demo.local', NULL, NULL, 0, NULL, 4, NULL, NULL, NULL, 1, 2, 'Sales Call', 'Distributor', 0, 1, '38-4445566', 'Open Account', 100000.00, 2200.00, NULL, NULL, 0, NULL, NULL, '2026-07-24 08:03:43', '2026-07-24 08:03:43', 0, 0, 0, 0, '2025-01-24', NULL, 12, 18500.00, 0, 0, 0, NULL, NULL, 0, 0, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(9, 1, 'C1005', 0, 0, 'Omar Hassan', 'Corner Smoke Shop', '301 Gratiot Ave', 'Detroit', 'MI', '48226', 'US', '313-555-1005', NULL, NULL, NULL, 'corner@demo.local', NULL, NULL, 0, NULL, 1, NULL, NULL, NULL, 1, 2, 'Sales Call', 'Wholesale', 0, 1, '38-5556677', 'Cash', 2500.00, 0.00, NULL, NULL, 0, NULL, NULL, '2026-07-24 08:03:43', '2026-07-24 08:03:43', 0, 0, 0, 0, '2025-01-24', NULL, 12, 18500.00, 0, 0, 0, NULL, NULL, 0, 0, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `customer_lookup_options`
--

CREATE TABLE `customer_lookup_options` (
  `id` bigint UNSIGNED NOT NULL,
  `company_id` bigint UNSIGNED NOT NULL,
  `type` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `customer_lookup_options`
--

INSERT INTO `customer_lookup_options` (`id`, `company_id`, `type`, `code`, `name`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 1, 'lead_source', 'WALKIN', 'Walk-in', 1, '2026-07-23 22:00:21', '2026-07-23 22:00:21'),
(2, 1, 'lead_source', 'REFERRAL', 'Referral', 1, '2026-07-23 22:00:21', '2026-07-23 22:00:21'),
(3, 1, 'lead_source', 'WEBSITE', 'Website', 1, '2026-07-23 22:00:21', '2026-07-23 22:00:21'),
(4, 1, 'lead_source', 'TRADESHOW', 'Trade Show', 1, '2026-07-23 22:00:21', '2026-07-23 22:00:21'),
(5, 1, 'lead_source', 'SALESCALL', 'Sales Call', 1, '2026-07-23 22:00:21', '2026-07-23 22:00:21'),
(6, 1, 'customer_category', 'WHOLESALE', 'Wholesale', 1, '2026-07-23 22:00:21', '2026-07-23 22:00:21'),
(7, 1, 'customer_category', 'RETAIL', 'Retail', 1, '2026-07-23 22:00:21', '2026-07-23 22:00:21'),
(8, 1, 'customer_category', 'CHAIN', 'Chain', 1, '2026-07-23 22:00:21', '2026-07-23 22:00:21'),
(9, 1, 'customer_category', 'CONVENIENCE', 'Convenience', 1, '2026-07-23 22:00:21', '2026-07-23 22:00:21'),
(10, 1, 'customer_category', 'DISTRIBUTOR', 'Distributor', 1, '2026-07-23 22:00:21', '2026-07-23 22:00:21'),
(11, 1, 'account_type', 'OPENACCOUNT', 'Open Account', 1, '2026-07-23 22:00:21', '2026-07-23 22:00:21'),
(12, 1, 'account_type', 'COD', 'COD', 1, '2026-07-23 22:00:21', '2026-07-23 22:00:21'),
(13, 1, 'account_type', 'CREDITCARD', 'Credit Card', 1, '2026-07-23 22:00:21', '2026-07-23 22:00:21'),
(14, 1, 'account_type', 'CASH', 'Cash', 1, '2026-07-23 22:00:21', '2026-07-23 22:00:21');

-- --------------------------------------------------------

--
-- Table structure for table `customer_shipping_addresses`
--

CREATE TABLE `customer_shipping_addresses` (
  `id` bigint UNSIGNED NOT NULL,
  `customer_id` bigint UNSIGNED NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `city` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `state` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `zip` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telephone` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fax` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `class` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_primary` tinyint(1) NOT NULL DEFAULT '0',
  `sort_order` smallint UNSIGNED NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `customer_shipping_addresses`
--

INSERT INTO `customer_shipping_addresses` (`id`, `customer_id`, `name`, `address`, `city`, `state`, `zip`, `telephone`, `fax`, `class`, `is_primary`, `sort_order`, `created_at`, `updated_at`) VALUES
(2, 3, 'Dolor in temporibus ', 'Anim labore architec', 'Pariatur Cum dolor ', 'Praesentium nostrum ', 'Eiusmod assumenda te', 'Est unde exercitatio', 'Quam nihil ullam ali', 'Enim voluptate neque', 1, 0, '2026-07-21 12:06:17', '2026-07-21 12:06:17'),
(3, 4, 'Proident et reprehe', 'Labore ad exercitati', 'Quisquam id sint qui', 'Autem vero temporibu', 'Facere esse a nisi ', 'Quaerat qui sit qui ', 'Et enim sequi volupt', 'Cillum aspernatur ev', 0, 0, '2026-07-21 13:08:02', '2026-07-21 13:08:02'),
(4, 4, 'Assumenda ut laboris', 'Autem rerum laudanti', 'Est officia pariatu', 'Repellendus Sed off', 'Ut aut amet impedit', 'Voluptate impedit c', 'Commodo magna amet ', 'Mollitia impedit do', 1, 1, '2026-07-21 13:08:02', '2026-07-21 13:08:02'),
(5, 2, 'Dolore rerum adipisi', 'Est in qui ipsum har', 'Maiores sit molesti', 'Neque et rerum tempo', 'Atque occaecat ipsum', 'Rerum sunt asperiore', 'Rerum qui neque aut ', 'Dolore dolore soluta', 1, 0, '2026-07-21 13:08:24', '2026-07-21 13:08:24');

-- --------------------------------------------------------

--
-- Table structure for table `delivery_routes`
--

CREATE TABLE `delivery_routes` (
  `id` bigint UNSIGNED NOT NULL,
  `company_id` bigint UNSIGNED NOT NULL,
  `code` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `delivery_routes`
--

INSERT INTO `delivery_routes` (`id`, `company_id`, `code`, `name`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 1, 'CITY', 'City', 1, '2026-07-21 08:14:33', '2026-07-21 08:14:33');

-- --------------------------------------------------------

--
-- Table structure for table `departments`
--

CREATE TABLE `departments` (
  `id` bigint UNSIGNED NOT NULL,
  `company_id` bigint UNSIGNED NOT NULL,
  `code` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `departments`
--

INSERT INTO `departments` (`id`, `company_id`, `code`, `name`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 1, 'TOB', 'Tobacco', 1, '2026-07-21 08:14:33', '2026-07-21 08:14:33'),
(2, 1, 'BEV', 'Beverages', 1, '2026-07-24 08:03:43', '2026-07-24 08:03:43'),
(3, 1, 'SNK', 'Snacks', 1, '2026-07-24 08:03:43', '2026-07-24 08:03:43'),
(4, 1, 'GRO', 'Grocery', 1, '2026-07-24 08:03:43', '2026-07-24 08:03:43');

-- --------------------------------------------------------

--
-- Table structure for table `discount_schedules`
--

CREATE TABLE `discount_schedules` (
  `id` bigint UNSIGNED NOT NULL,
  `company_id` bigint UNSIGNED NOT NULL,
  `code` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `percent` decimal(8,4) NOT NULL DEFAULT '0.0000',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `discount_schedules`
--

INSERT INTO `discount_schedules` (`id`, `company_id`, `code`, `name`, `percent`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 1, 'NONE', 'No Discount', 0.0000, 1, '2026-07-21 08:14:33', '2026-07-21 08:14:33');

-- --------------------------------------------------------

--
-- Table structure for table `document_email_logs`
--

CREATE TABLE `document_email_logs` (
  `id` bigint UNSIGNED NOT NULL,
  `company_id` bigint UNSIGNED NOT NULL,
  `document_type` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `document_id` bigint UNSIGNED DEFAULT NULL,
  `recipient` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `subject` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_id` bigint UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `document_email_logs`
--

INSERT INTO `document_email_logs` (`id`, `company_id`, `document_type`, `document_id`, `recipient`, `subject`, `user_id`, `created_at`, `updated_at`) VALUES
(1, 1, 'invoice', 1, 'hohizu@mailinator.com', 'Invoice 100001', 1, '2026-07-21 12:18:38', '2026-07-21 12:18:38'),
(2, 1, 'invoice', 1, 'hohizu@mailinator.com', 'Invoice 100001', 1, '2026-07-21 13:27:29', '2026-07-21 13:27:29'),
(3, 1, 'invoice', 3, 'fahoteso@mailinator.com', 'Invoice 100003', 1, '2026-07-23 21:06:08', '2026-07-23 21:06:08'),
(4, 1, 'price_list', NULL, 'fahoteso@mailinator.com', 'Price List', 1, '2026-07-23 15:51:23', '2026-07-23 15:51:23');

-- --------------------------------------------------------

--
-- Table structure for table `failed_jobs`
--

CREATE TABLE `failed_jobs` (
  `id` bigint UNSIGNED NOT NULL,
  `uuid` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `inventory_journal_entries`
--

CREATE TABLE `inventory_journal_entries` (
  `id` bigint UNSIGNED NOT NULL,
  `company_id` bigint UNSIGNED NOT NULL,
  `item_id` bigint UNSIGNED NOT NULL,
  `site_id` bigint UNSIGNED DEFAULT NULL,
  `source_type` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `source_id` bigint UNSIGNED DEFAULT NULL,
  `reference` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `qty_change` decimal(14,4) NOT NULL,
  `qty_after` decimal(14,4) DEFAULT NULL,
  `unit_cost` decimal(12,4) NOT NULL DEFAULT '0.0000',
  `user_id` bigint UNSIGNED DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `inventory_journal_entries`
--

INSERT INTO `inventory_journal_entries` (`id`, `company_id`, `item_id`, `site_id`, `source_type`, `source_id`, `reference`, `qty_change`, `qty_after`, `unit_cost`, `user_id`, `notes`, `created_at`, `updated_at`) VALUES
(1, 1, 2, 1, 'App\\Models\\InventoryReceiving', 2, '8000002', 1.0000, 121.0000, 2.0000, 1, 'Inventory Receiving', '2026-07-21 14:53:42', '2026-07-21 14:53:42'),
(2, 1, 2, 1, 'App\\Models\\ReturnToVendor', 1, '7000001', -1.0000, 120.0000, 2.0000, 1, 'Return to Vendor', '2026-07-21 15:00:44', '2026-07-21 15:00:44'),
(3, 1, 1, 1, 'App\\Models\\Invoice', 2, '100002', -1.0000, 4.0000, 0.0000, 1, 'Sales Invoice 100002 (SO 243075)', '2026-07-23 21:02:35', '2026-07-23 21:02:35'),
(4, 1, 1, 1, 'App\\Models\\Invoice', 2, '100002', -1.0000, 3.0000, 0.0000, 1, 'Sales Invoice 100002 (SO 243075)', '2026-07-23 21:02:35', '2026-07-23 21:02:35'),
(5, 1, 1, 1, 'App\\Models\\Invoice', 3, '100003', -1.0000, 2.0000, 0.0000, 1, 'Sales Invoice 100003 (SO 243076)', '2026-07-23 21:05:24', '2026-07-23 21:05:24'),
(6, 1, 2, 1, 'App\\Models\\Invoice', 3, '100003', -1.0000, 119.0000, 2.0000, 1, 'Sales Invoice 100003 (SO 243076)', '2026-07-23 21:05:24', '2026-07-23 21:05:24'),
(7, 1, 2, 1, 'App\\Models\\Invoice', 4, '100004', -1.0000, 118.0000, 2.0000, 1, 'Sales Invoice 100004 (SO 243077)', '2026-07-23 21:05:38', '2026-07-23 21:05:38'),
(8, 1, 3, 1, 'App\\Models\\InventoryReceiving', 5, '8000005', 20.0000, 20.0000, 1000.0000, 1, 'Inventory Receiving', '2026-07-23 21:41:44', '2026-07-23 21:41:44'),
(9, 1, 4, 1, 'App\\Models\\InventoryReceiving', 6, '8000006', 10.0000, 10.0000, 300.0000, 1, 'Inventory Receiving', '2026-07-23 23:22:02', '2026-07-23 23:22:02'),
(10, 1, 4, 1, 'App\\Models\\Invoice', 5, '100005', -1.0000, 9.0000, 300.0000, 1, 'Sales Invoice 100005 (SO 243078)', '2026-07-23 23:29:17', '2026-07-23 23:29:17'),
(11, 1, 10, 1, 'App\\Models\\InventoryReceiving', 9, 'DEMO-RCV-2002', 50.0000, 530.0000, 2.1000, 1, 'Inventory Receiving', '2026-07-24 08:07:31', '2026-07-24 08:07:31'),
(12, 1, 12, 1, 'App\\Models\\InventoryReceiving', 9, 'DEMO-RCV-2002', 10.0000, 100.0000, 18.5000, 1, 'Inventory Receiving', '2026-07-24 08:07:31', '2026-07-24 08:07:31'),
(13, 1, 13, 1, 'App\\Models\\InventoryReceiving', 10, 'DEMO-RCV-2003', 25.0000, 175.0000, 8.2000, 1, 'Inventory Receiving', '2026-07-24 08:07:31', '2026-07-24 08:07:31'),
(14, 1, 5, 1, 'App\\Models\\Invoice', 6, 'DEMO-INV-4001', -2.0000, 83.0000, 56.0000, 1, 'Sales Invoice DEMO-INV-4001 (SO DEMO-SO-3003)', '2026-07-24 08:07:32', '2026-07-24 08:07:32'),
(15, 1, 13, 1, 'App\\Models\\Invoice', 6, 'DEMO-INV-4001', -4.0000, 171.0000, 8.2000, 1, 'Sales Invoice DEMO-INV-4001 (SO DEMO-SO-3003)', '2026-07-24 08:07:32', '2026-07-24 08:07:32'),
(16, 1, 9, 1, 'App\\Models\\Invoice', 7, 'DEMO-INV-4002', -12.0000, 338.0000, 4.1000, 1, 'Sales Invoice DEMO-INV-4002 (SO DEMO-SO-3004)', '2026-07-24 08:07:32', '2026-07-24 08:07:32'),
(17, 1, 10, 1, 'App\\Models\\Invoice', 7, 'DEMO-INV-4002', -12.0000, 518.0000, 2.1000, 1, 'Sales Invoice DEMO-INV-4002 (SO DEMO-SO-3004)', '2026-07-24 08:07:32', '2026-07-24 08:07:32'),
(18, 1, 8, 1, 'App\\Models\\Invoice', 8, '4003', -3.0000, 197.0000, 12.4000, 1, 'Sales Invoice 4003 (SO 3006)', '2026-07-27 13:00:49', '2026-07-27 13:00:49'),
(19, 1, 1, NULL, 'App\\Models\\CreditMemo', 1, '50001', 1.0000, 3.0000, 0.0000, 1, 'Credit Memo restock', '2026-07-27 15:31:38', '2026-07-27 15:31:38'),
(20, 1, 8, 1, 'App\\Models\\Invoice', 9, '4004', -1.0000, 196.0000, 12.4000, 1, 'Sales Invoice 4004 (SO 3009)', '2026-07-28 08:01:39', '2026-07-28 08:01:39'),
(21, 1, 8, 1, 'App\\Models\\Invoice', 10, '4005', -1.0000, 195.0000, 12.4000, 1, 'Sales Invoice 4005 (SO 3010)', '2026-07-28 08:25:13', '2026-07-28 08:25:13'),
(22, 1, 2, 1, 'App\\Models\\Invoice', 10, '4005', -1.0000, 117.0000, 2.0000, 1, 'Sales Invoice 4005 (SO 3010)', '2026-07-28 08:25:13', '2026-07-28 08:25:13');

-- --------------------------------------------------------

--
-- Table structure for table `inventory_receivings`
--

CREATE TABLE `inventory_receivings` (
  `id` bigint UNSIGNED NOT NULL,
  `company_id` bigint UNSIGNED NOT NULL,
  `receipt_number` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `receipt_date` date DEFAULT NULL,
  `purchase_order_id` bigint UNSIGNED DEFAULT NULL,
  `reference_no` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'New',
  `supplier_id` bigint UNSIGNED DEFAULT NULL,
  `buyer_id` bigint UNSIGNED DEFAULT NULL,
  `site_id` bigint UNSIGNED DEFAULT NULL,
  `received_by` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `shipping_carrier` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `comments` text COLLATE utf8mb4_unicode_ci,
  `processed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `inventory_receivings`
--

INSERT INTO `inventory_receivings` (`id`, `company_id`, `receipt_number`, `receipt_date`, `purchase_order_id`, `reference_no`, `status`, `supplier_id`, `buyer_id`, `site_id`, `received_by`, `shipping_carrier`, `comments`, `processed_at`, `created_at`, `updated_at`) VALUES
(1, 1, '8000001', '2014-09-15', 1, 'Quibusdam maiores ne', 'Processed', 1, 2, 1, 'Nisi dolor deserunt ', 'Ut proident et natu', 'Ad ut labore do dese', '2026-07-21 12:02:38', '2026-07-21 12:02:25', '2026-07-21 12:02:38'),
(2, 1, '8000002', '2026-07-21', 2, '', 'Processed', 1, 1, 1, 'Yousef Imran', '', '', '2026-07-21 14:53:42', '2026-07-21 14:44:52', '2026-07-22 09:02:23'),
(3, 1, '8000003', '2026-07-22', 1, NULL, 'New', 1, 2, 1, 'Yousef Imran', NULL, NULL, NULL, '2026-07-22 09:05:08', '2026-07-22 09:05:08'),
(4, 1, '8000004', '2026-07-23', 3, NULL, 'New', 1, 1, 1, 'POS Admin', NULL, NULL, NULL, '2026-07-23 21:24:09', '2026-07-23 21:24:09'),
(5, 1, '8000005', '2026-07-23', 3, '', 'Processed', 1, 1, 1, 'POS Admin', 'cbhnhhjg', 'gtfjmu', '2026-07-23 21:41:44', '2026-07-23 21:35:53', '2026-07-23 21:41:44'),
(6, 1, '8000006', '2026-07-23', 4, '', 'Processed', 1, 1, 1, 'POS Admin', '', '', '2026-07-23 23:22:02', '2026-07-23 23:21:52', '2026-07-23 23:22:02'),
(7, 1, '8000007', '2026-07-23', 1, NULL, 'New', 1, 2, 1, 'POS Admin', NULL, NULL, NULL, '2026-07-23 15:49:54', '2026-07-23 15:49:54'),
(8, 1, 'DEMO-RCV-2001', '2026-07-24', 5, 'ASN-DEMO-1', 'New', 3, 1, 1, NULL, NULL, 'Demo receiving — not processed', NULL, '2026-07-24 08:07:31', '2026-07-24 08:07:31'),
(9, 1, 'DEMO-RCV-2002', '2026-07-22', 6, 'BOL-DEMO-2', 'Processed', 4, 1, 1, NULL, NULL, 'Demo processed receiving', '2026-07-24 08:07:31', '2026-07-24 08:07:31', '2026-07-24 08:07:31'),
(10, 1, 'DEMO-RCV-2003', '2026-07-20', 7, NULL, 'Processed', 5, 1, 1, NULL, NULL, 'Demo full receive', '2026-07-24 08:07:31', '2026-07-24 08:07:31', '2026-07-24 08:07:31');

-- --------------------------------------------------------

--
-- Table structure for table `inventory_receiving_lines`
--

CREATE TABLE `inventory_receiving_lines` (
  `id` bigint UNSIGNED NOT NULL,
  `inventory_receiving_id` bigint UNSIGNED NOT NULL,
  `purchase_order_line_id` bigint UNSIGNED DEFAULT NULL,
  `item_id` bigint UNSIGNED DEFAULT NULL,
  `item_code` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `uom` varchar(16) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `qty_ordered` decimal(14,4) NOT NULL DEFAULT '0.0000',
  `qty_received` decimal(14,4) NOT NULL DEFAULT '0.0000',
  `unit_cost` decimal(12,4) NOT NULL DEFAULT '0.0000',
  `line_no` smallint UNSIGNED NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `inventory_receiving_lines`
--

INSERT INTO `inventory_receiving_lines` (`id`, `inventory_receiving_id`, `purchase_order_line_id`, `item_id`, `item_code`, `description`, `uom`, `qty_ordered`, `qty_received`, `unit_cost`, `line_no`, `created_at`, `updated_at`) VALUES
(1, 2, 1, 2, 'MARL-RED-CTN', 'Marlboro Red Carton', 'CTN', 1.0000, 1.0000, 2.0000, 1, '2026-07-21 14:44:52', '2026-07-21 14:53:42'),
(2, 4, 2, 3, 'imran1', 'aaaaaaaaaaaaaaaaaa', 'BX', 20.0000, 20.0000, 1000.0000, 1, '2026-07-23 21:24:09', '2026-07-23 21:24:09'),
(3, 5, 2, 3, 'imran1', 'aaaaaaaaaaaaaaaaaa', 'BX', 20.0000, 20.0000, 1000.0000, 1, '2026-07-23 21:35:53', '2026-07-23 21:41:44'),
(4, 6, 3, 4, '111111111111111', '1111111111111111111', 'BX', 10.0000, 10.0000, 300.0000, 1, '2026-07-23 23:21:52', '2026-07-23 23:22:02'),
(5, 8, 4, 7, 'PEPSI-12PK-CS', 'Pepsi 12oz 12-Pack Case', 'CS', 40.0000, 40.0000, 12.2500, 1, '2026-07-24 08:07:31', '2026-07-24 08:07:31'),
(6, 8, 5, 8, 'COKE-12PK-CS', 'Coca-Cola 12oz 12-Pack Case', 'CS', 30.0000, 30.0000, 12.4000, 2, '2026-07-24 08:07:31', '2026-07-24 08:07:31'),
(7, 8, 6, 9, 'WATER-24PK', 'Purified Water 16.9oz 24-Pack', 'CS', 50.0000, 50.0000, 4.1000, 3, '2026-07-24 08:07:31', '2026-07-24 08:07:31'),
(8, 9, 7, 10, 'LAYS-CLASSIC', 'Lay\'s Classic Potato Chips 8oz', 'BAG', 100.0000, 50.0000, 2.1000, 1, '2026-07-24 08:07:31', '2026-07-24 08:07:31'),
(9, 9, 8, 12, 'SNICKERS-BX', 'Snickers Bar Box (24ct)', 'BX', 20.0000, 10.0000, 18.5000, 2, '2026-07-24 08:07:31', '2026-07-24 08:07:31'),
(10, 10, 9, 13, 'PAPER-TOWEL', 'Paper Towels 6-Roll Pack', 'EA', 25.0000, 25.0000, 8.2000, 1, '2026-07-24 08:07:31', '2026-07-24 08:07:31');

-- --------------------------------------------------------

--
-- Table structure for table `invoices`
--

CREATE TABLE `invoices` (
  `id` bigint UNSIGNED NOT NULL,
  `company_id` bigint UNSIGNED NOT NULL,
  `invoice_number` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `invoice_date` date DEFAULT NULL,
  `sales_order_id` bigint UNSIGNED DEFAULT NULL,
  `customer_id` bigint UNSIGNED DEFAULT NULL,
  `status` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'NOT PAID',
  `driver` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `subtotal` decimal(14,4) NOT NULL DEFAULT '0.0000',
  `total_discount` decimal(14,4) NOT NULL DEFAULT '0.0000',
  `trade_discount` decimal(14,4) NOT NULL DEFAULT '0.0000',
  `freight` decimal(14,4) NOT NULL DEFAULT '0.0000',
  `miscellaneous` decimal(14,4) NOT NULL DEFAULT '0.0000',
  `tax` decimal(14,4) NOT NULL DEFAULT '0.0000',
  `invoice_total` decimal(14,4) NOT NULL DEFAULT '0.0000',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `invoices`
--

INSERT INTO `invoices` (`id`, `company_id`, `invoice_number`, `invoice_date`, `sales_order_id`, `customer_id`, `status`, `driver`, `subtotal`, `total_discount`, `trade_discount`, `freight`, `miscellaneous`, `tax`, `invoice_total`, `created_at`, `updated_at`) VALUES
(1, 1, '100001', '2026-07-21', 1, 3, 'PAID', 'bvnvbnhgbvn', 9.9900, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 9.9900, '2026-07-21 12:18:03', '2026-07-21 13:20:01'),
(2, 1, '100002', '2026-07-23', 2, 4, 'PAID', NULL, 19.9800, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 19.9800, '2026-07-23 21:02:35', '2026-07-23 21:03:53'),
(3, 1, '100003', '2026-07-23', 3, 4, 'PAID', NULL, 82.4900, 0.0000, 0.0000, 0.0000, 0.0000, 0.0400, 82.5300, '2026-07-23 21:05:24', '2026-07-23 21:14:41'),
(4, 1, '100004', '2026-07-23', 4, 2, 'PAID', NULL, 72.5000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0400, 72.5400, '2026-07-23 21:05:38', '2026-07-23 21:06:05'),
(5, 1, '100005', '2026-07-23', 5, 4, 'PAID', 'aaaaaaaaaaa', 400.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 400.0000, '2026-07-23 23:29:17', '2026-07-24 00:22:36'),
(6, 1, 'DEMO-INV-4001', '2026-07-19', 8, 6, 'PAID', NULL, 183.0000, 0.0000, 0.0000, 0.0000, 0.0000, 10.9800, 193.9800, '2026-07-24 08:07:32', '2026-07-27 14:54:37'),
(7, 1, 'DEMO-INV-4002', '2026-07-12', 9, 8, 'PAID', NULL, 107.8800, 0.0000, 0.0000, 0.0000, 0.0000, 6.4700, 114.3500, '2026-07-24 08:07:32', '2026-07-24 08:07:32'),
(8, 1, '4003', '2026-07-27', 11, 3, 'PAID', 'RFET', 50.2500, 6.0000, 0.0000, 0.0000, 0.0000, 0.0300, 50.2800, '2026-07-27 13:00:49', '2026-07-27 14:46:49'),
(9, 1, '4004', '2026-07-28', 13, 4, 'NOT PAID', NULL, 18.7500, 0.0000, 0.0000, 0.0000, 0.0000, 0.0100, 18.7600, '2026-07-28 08:01:39', '2026-07-28 08:01:39'),
(10, 1, '4005', '2026-07-28', 14, 4, 'PAID', NULL, 91.2500, 0.0000, 0.0000, 0.0000, 0.0000, 0.0500, 91.3000, '2026-07-28 08:25:13', '2026-07-28 08:49:47');

-- --------------------------------------------------------

--
-- Table structure for table `invoice_credits`
--

CREATE TABLE `invoice_credits` (
  `id` bigint UNSIGNED NOT NULL,
  `invoice_id` bigint UNSIGNED NOT NULL,
  `credit_memo_id` bigint UNSIGNED DEFAULT NULL,
  `amount` decimal(14,4) NOT NULL DEFAULT '0.0000',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `invoice_payments`
--

CREATE TABLE `invoice_payments` (
  `id` bigint UNSIGNED NOT NULL,
  `invoice_id` bigint UNSIGNED NOT NULL,
  `payment_date` date DEFAULT NULL,
  `payment_method` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `amount` decimal(14,4) NOT NULL DEFAULT '0.0000',
  `comments` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_id` bigint UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `invoice_payments`
--

INSERT INTO `invoice_payments` (`id`, `invoice_id`, `payment_date`, `payment_method`, `amount`, `comments`, `user_id`, `created_at`, `updated_at`) VALUES
(1, 1, '2026-07-21', 'Credit Card', 9.9900, 'Customer-first payment', 1, '2026-07-21 12:19:21', '2026-07-21 12:19:21'),
(2, 2, '2026-07-23', 'Cash', 19.9800, '', 1, '2026-07-23 21:03:53', '2026-07-23 21:03:53'),
(3, 4, '2026-07-23', 'Credit Card', 72.5400, '', 1, '2026-07-23 21:06:05', '2026-07-23 21:06:05'),
(4, 3, '2026-07-23', 'Cash', 82.5300, 'vfbgvfg', 1, '2026-07-23 21:14:41', '2026-07-23 21:14:41'),
(5, 5, '2026-07-23', 'Cash', 400.0000, '', 1, '2026-07-23 23:32:18', '2026-07-23 23:32:18'),
(6, 6, '2026-07-21', 'Check', 96.9900, 'Demo partial payment', 1, '2026-07-24 08:07:32', '2026-07-24 08:07:32'),
(7, 7, '2026-07-14', 'ACH', 114.3500, 'Demo full payment', 1, '2026-07-24 08:07:32', '2026-07-24 08:07:32'),
(8, 8, '2026-07-27', 'Cash', 20.0000, NULL, 1, '2026-07-27 14:42:23', '2026-07-27 14:42:23'),
(9, 8, '2026-07-27', 'Cash', 10.0000, NULL, 1, '2026-07-27 14:46:28', '2026-07-27 14:46:28'),
(10, 8, '2026-07-27', 'Cash', 20.2800, NULL, 1, '2026-07-27 14:46:49', '2026-07-27 14:46:49'),
(11, 6, '2026-07-27', 'Cash', 96.9900, 'FGHYHY', 1, '2026-07-27 14:54:37', '2026-07-27 14:54:37'),
(12, 10, '2026-07-28', 'Cash', 91.3000, 'uko9lp0', 1, '2026-07-28 08:49:47', '2026-07-28 08:49:47');

-- --------------------------------------------------------

--
-- Table structure for table `items`
--

CREATE TABLE `items` (
  `id` bigint UNSIGNED NOT NULL,
  `company_id` bigint UNSIGNED NOT NULL,
  `item_code` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `item_type` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Standard Item',
  `class` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `extended_description` text COLLATE utf8mb4_unicode_ci,
  `product_highlights` text COLLATE utf8mb4_unicode_ci,
  `image_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `thumbnail_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `list_price` decimal(12,4) NOT NULL DEFAULT '0.0000',
  `msrp` decimal(12,4) NOT NULL DEFAULT '0.0000',
  `standard_cost` decimal(12,4) NOT NULL DEFAULT '0.0000',
  `current_cost` decimal(12,4) NOT NULL DEFAULT '0.0000',
  `last_cost` decimal(12,4) NOT NULL DEFAULT '0.0000',
  `average_cost` decimal(12,4) NOT NULL DEFAULT '0.0000',
  `quantity_in_stock` decimal(14,4) NOT NULL DEFAULT '0.0000',
  `allocated_qty` decimal(14,4) NOT NULL DEFAULT '0.0000',
  `on_order_qty` decimal(14,4) NOT NULL DEFAULT '0.0000',
  `back_order_qty` decimal(14,4) NOT NULL DEFAULT '0.0000',
  `reorder_point` decimal(14,4) NOT NULL DEFAULT '0.0000',
  `restock_level` decimal(14,4) NOT NULL DEFAULT '0.0000',
  `lead_time_days` int UNSIGNED NOT NULL DEFAULT '0',
  `last_received_at` date DEFAULT NULL,
  `last_ordered_at` date DEFAULT NULL,
  `last_sold_at` date DEFAULT NULL,
  `last_count_date` date DEFAULT NULL,
  `department_id` bigint UNSIGNED DEFAULT NULL,
  `category_id` bigint UNSIGNED DEFAULT NULL,
  `subcategory_id` bigint UNSIGNED DEFAULT NULL,
  `uom_schedule_id` bigint UNSIGNED DEFAULT NULL,
  `tax_schedule_id` bigint UNSIGNED DEFAULT NULL,
  `promotion_schedule_id` bigint UNSIGNED DEFAULT NULL,
  `pricing_method_id` bigint UNSIGNED DEFAULT NULL,
  `unit_of_measure` varchar(16) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_inactive` tinyint(1) NOT NULL DEFAULT '0',
  `can_order` tinyint(1) NOT NULL DEFAULT '1',
  `can_sell` tinyint(1) NOT NULL DEFAULT '1',
  `allow_back_order` tinyint(1) NOT NULL DEFAULT '1',
  `available_on_website` tinyint(1) NOT NULL DEFAULT '0',
  `item_tracking` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'None',
  `shipping_weight` decimal(12,4) NOT NULL DEFAULT '0.0000',
  `tare_weight` decimal(12,4) NOT NULL DEFAULT '0.0000',
  `manufacturer` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `item_line_message` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `manu_product_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `manu_promotion_item` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `manu_promotion_description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `manu_promotion_code` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `manu_base_count` decimal(14,4) NOT NULL DEFAULT '0.0000',
  `barcode_format` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `primary_upc` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `comments` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `items`
--

INSERT INTO `items` (`id`, `company_id`, `item_code`, `item_type`, `class`, `description`, `extended_description`, `product_highlights`, `image_path`, `thumbnail_path`, `list_price`, `msrp`, `standard_cost`, `current_cost`, `last_cost`, `average_cost`, `quantity_in_stock`, `allocated_qty`, `on_order_qty`, `back_order_qty`, `reorder_point`, `restock_level`, `lead_time_days`, `last_received_at`, `last_ordered_at`, `last_sold_at`, `last_count_date`, `department_id`, `category_id`, `subcategory_id`, `uom_schedule_id`, `tax_schedule_id`, `promotion_schedule_id`, `pricing_method_id`, `unit_of_measure`, `is_inactive`, `can_order`, `can_sell`, `allow_back_order`, `available_on_website`, `item_tracking`, `shipping_weight`, `tare_weight`, `manufacturer`, `item_line_message`, `manu_product_id`, `manu_promotion_item`, `manu_promotion_description`, `manu_promotion_code`, `manu_base_count`, `barcode_format`, `primary_upc`, `comments`, `created_at`, `updated_at`) VALUES
(1, 1, '1229W', 'Standard Item', NULL, 'SOUR PATCH WATERMELON 240CT', NULL, NULL, NULL, NULL, 9.9900, 0.0000, 8.9700, 0.0000, 0.0000, 0.0000, 3.0000, 3.0000, 0.0000, 0.0000, 10.0000, 0.0000, 0, NULL, NULL, '2026-07-23', NULL, 1, NULL, NULL, NULL, NULL, NULL, NULL, 'BX', 0, 1, 1, 1, 0, 'None', 0.0000, 0.0000, NULL, NULL, NULL, NULL, NULL, NULL, 0.0000, NULL, NULL, NULL, '2026-07-21 08:15:10', '2026-07-28 08:26:38'),
(2, 1, 'MARL-RED-CTN', 'Standard Item', 'CIG', 'Marlboro Red Carton', 'Premium carton', 'Full flavor', NULL, NULL, 72.5000, 89.9900, 58.0000, 2.0000, 2.0000, 57.3388, 117.0000, 5.0000, 0.0000, 0.0000, 24.0000, 96.0000, 3, '2026-07-21', '2026-07-21', '2026-07-28', NULL, 1, 1, 1, 1, 1, NULL, 1, 'CTN', 0, 1, 1, 1, 0, 'None', 0.0000, 0.0000, NULL, NULL, NULL, NULL, NULL, NULL, 0.0000, 'UPC-A', '028200003123', NULL, '2026-07-21 08:39:38', '2026-07-28 08:25:13'),
(3, 1, 'imran1', 'Standard Item', 'iiiiiiu', 'aaaaaaaaaaaaaaaaaa', '', '', NULL, NULL, 500.0000, 0.0000, 0.0000, 1000.0000, 1000.0000, 1000.0000, 20.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0, '2026-07-23', '2026-07-23', NULL, NULL, 1, 1, 1, NULL, NULL, NULL, NULL, 'BX', 0, 1, 1, 1, 0, 'None', 0.0000, 0.0000, '', '', '', '', '', '', 0.0000, 'UPC-A', 'ffffffffffffffffff', '', '2026-07-23 21:20:47', '2026-07-23 21:41:44'),
(4, 1, '111111111111111', 'Standard Item', '11111111111111', '1111111111111111111', 'vnhgjj', 'mhjmk', 'items/images/72e72065-3ed3-4f75-ad62-ec661080b941.png', 'items/thumbnails/6de324e6-cb81-4a95-8fec-1fac7d7b9aff.png', 400.0000, 400.0000, 20.0000, 300.0000, 300.0000, 300.0000, 9.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0, '2026-07-23', '2026-07-23', '2026-07-23', NULL, NULL, NULL, NULL, 3, NULL, NULL, NULL, 'BX', 0, 1, 1, 1, 0, 'None', 0.0000, 0.0000, '', 'kjh,kj', '', '', '', '', 0.0000, 'UPC-A', 'rthyujy', 'jk,jk', '2026-07-23 22:13:50', '2026-07-23 23:29:17'),
(5, 1, 'MARL-GOLD-CTN', 'Standard Item', NULL, 'Marlboro Gold Carton', NULL, NULL, NULL, NULL, 71.0000, 85.2000, 56.0000, 56.0000, 56.0000, 56.0000, 83.0000, 0.0000, 0.0000, 0.0000, 12.0000, 48.0000, 3, NULL, NULL, '2026-07-19', NULL, 1, 1, 1, 5, 1, NULL, 1, 'CTN', 0, 1, 1, 1, 0, 'None', 0.0000, 0.0000, NULL, NULL, NULL, NULL, NULL, NULL, 0.0000, 'UPC-A', '028200003456', NULL, '2026-07-24 08:03:43', '2026-07-24 08:07:32'),
(6, 1, 'NEWP-MENT-CTN', 'Standard Item', NULL, 'Newport Menthol Carton', NULL, NULL, NULL, NULL, 74.0000, 88.8000, 58.5000, 58.5000, 58.5000, 58.5000, 64.0000, 1.0000, 0.0000, 0.0000, 12.0000, 48.0000, 3, NULL, NULL, NULL, NULL, 1, 1, 1, 5, 1, NULL, 1, 'CTN', 0, 1, 1, 1, 0, 'None', 0.0000, 0.0000, NULL, NULL, NULL, NULL, NULL, NULL, 0.0000, 'UPC-A', '026200009988', NULL, '2026-07-24 08:03:43', '2026-07-27 13:01:48'),
(7, 1, 'PEPSI-12PK-CS', 'Standard Item', NULL, 'Pepsi 12oz 12-Pack Case', NULL, NULL, NULL, NULL, 18.5000, 22.2000, 12.2500, 12.2500, 12.2500, 12.2500, 240.0000, 10.0000, 0.0000, 0.0000, 12.0000, 48.0000, 3, NULL, NULL, NULL, NULL, 2, 3, 5, 4, 1, NULL, 1, 'CS', 0, 1, 1, 1, 0, 'None', 0.0000, 0.0000, NULL, NULL, NULL, NULL, NULL, NULL, 0.0000, 'UPC-A', '012000001111', NULL, '2026-07-24 08:03:43', '2026-07-27 12:00:14'),
(8, 1, 'COKE-12PK-CS', 'Standard Item', NULL, 'Coca-Cola 12oz 12-Pack Case', NULL, NULL, NULL, NULL, 18.7500, 22.5000, 12.4000, 12.4000, 12.4000, 12.4000, 195.0000, 20.0000, 0.0000, 0.0000, 12.0000, 48.0000, 3, NULL, NULL, '2026-07-28', NULL, 2, 3, 5, 4, 1, NULL, 1, 'CS', 0, 1, 1, 1, 0, 'None', 0.0000, 0.0000, NULL, NULL, NULL, NULL, NULL, NULL, 0.0000, 'UPC-A', '049000001122', NULL, '2026-07-24 08:03:43', '2026-07-28 08:25:13'),
(9, 1, 'WATER-24PK', 'Standard Item', NULL, 'Purified Water 16.9oz 24-Pack', NULL, NULL, NULL, NULL, 6.9900, 8.3900, 4.1000, 4.1000, 4.1000, 4.1000, 338.0000, 40.0000, 0.0000, 0.0000, 12.0000, 48.0000, 3, NULL, NULL, '2026-07-12', NULL, 2, 4, 7, 4, 1, NULL, 1, 'CS', 0, 1, 1, 1, 0, 'Lot', 0.0000, 0.0000, NULL, NULL, NULL, NULL, NULL, NULL, 0.0000, 'UPC-A', '078742001234', NULL, '2026-07-24 08:03:43', '2026-07-27 12:00:14'),
(10, 1, 'LAYS-CLASSIC', 'Standard Item', NULL, 'Lay\'s Classic Potato Chips 8oz', NULL, NULL, NULL, NULL, 3.4900, 4.1900, 2.1000, 2.1000, 2.1000, 2.1000, 518.0000, 24.0000, 0.0000, 0.0000, 12.0000, 48.0000, 3, '2026-07-22', NULL, '2026-07-12', NULL, 3, 5, 8, 11, 1, NULL, 1, 'BAG', 0, 1, 1, 1, 0, 'None', 0.0000, 0.0000, NULL, NULL, NULL, NULL, NULL, NULL, 0.0000, 'UPC-A', '028400001001', NULL, '2026-07-24 08:03:43', '2026-07-27 12:00:14'),
(11, 1, 'DORITOS-NACH', 'Standard Item', NULL, 'Doritos Nacho Cheese 9.25oz', NULL, NULL, NULL, NULL, 3.7900, 4.5500, 2.2500, 2.2500, 2.2500, 2.2500, 360.0000, 0.0000, 0.0000, 0.0000, 12.0000, 48.0000, 3, NULL, NULL, NULL, NULL, 3, 5, 8, 11, 1, NULL, 1, 'BAG', 0, 1, 1, 1, 0, 'None', 0.0000, 0.0000, NULL, NULL, NULL, NULL, NULL, NULL, 0.0000, 'UPC-A', '028400002002', NULL, '2026-07-24 08:03:43', '2026-07-24 08:03:43'),
(12, 1, 'SNICKERS-BX', 'Standard Item', NULL, 'Snickers Bar Box (24ct)', NULL, NULL, NULL, NULL, 28.0000, 33.6000, 18.5000, 18.5000, 18.5000, 18.5000, 100.0000, 6.0000, 0.0000, 0.0000, 12.0000, 48.0000, 3, '2026-07-22', NULL, NULL, NULL, 3, 6, 9, 2, 1, NULL, 1, 'BX', 0, 1, 1, 1, 0, 'None', 0.0000, 0.0000, NULL, NULL, NULL, NULL, NULL, NULL, 0.0000, 'UPC-A', '040000003003', NULL, '2026-07-24 08:03:43', '2026-07-27 12:00:14'),
(13, 1, 'PAPER-TOWEL', 'Standard Item', NULL, 'Paper Towels 6-Roll Pack', NULL, NULL, NULL, NULL, 12.9900, 15.5900, 8.2000, 8.2000, 8.2000, 8.2000, 171.0000, 0.0000, 0.0000, 0.0000, 12.0000, 48.0000, 3, '2026-07-20', NULL, '2026-07-19', NULL, 4, 7, 10, 2, 1, NULL, 1, 'EA', 0, 1, 1, 1, 0, 'Serial', 0.0000, 0.0000, NULL, NULL, NULL, NULL, NULL, NULL, 0.0000, 'UPC-A', '037000004004', NULL, '2026-07-24 08:03:43', '2026-07-27 12:00:14'),
(14, 1, 'LOW-STOCK-01', 'Standard Item', NULL, 'Demo Low Stock Item', NULL, NULL, NULL, NULL, 9.9900, 11.9900, 5.0000, 5.0000, 5.0000, 5.0000, 0.0000, 0.0000, 0.0000, 0.0000, 10.0000, 40.0000, 3, NULL, NULL, NULL, NULL, 4, 7, 10, 2, 1, NULL, 1, 'EA', 0, 1, 1, 1, 0, 'None', 0.0000, 0.0000, NULL, NULL, NULL, NULL, NULL, NULL, 0.0000, 'UPC-A', '999000000001', NULL, '2026-07-24 08:03:43', '2026-07-27 12:00:14'),
(15, 1, '1111', 'Standard Item', 'dfvbfggbhgf', 'fgbnhnb', '', '', 'items/images/3bb23049-b658-4b95-a82c-110f025b2da9.webp', 'items/thumbnails/409ced5e-184a-4668-85ea-69a6910862bc.webp', 700.0000, 600.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0, NULL, NULL, NULL, NULL, 2, 4, 7, 5, NULL, NULL, NULL, 'CTN', 0, 1, 1, 1, 0, 'None', 0.0000, 0.0000, '', '', '', '', '', '', 0.0000, 'UPC-A', '', '', '2026-07-24 09:01:13', '2026-07-24 09:01:13');

-- --------------------------------------------------------

--
-- Table structure for table `item_batches`
--

CREATE TABLE `item_batches` (
  `id` bigint UNSIGNED NOT NULL,
  `company_id` bigint UNSIGNED NOT NULL,
  `item_id` bigint UNSIGNED NOT NULL,
  `batch_number` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tracking_type` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Lot',
  `quantity` decimal(14,4) NOT NULL DEFAULT '0.0000',
  `expiry_date` date DEFAULT NULL,
  `received_at` date DEFAULT NULL,
  `notes` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sort_order` smallint UNSIGNED NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `item_batches`
--

INSERT INTO `item_batches` (`id`, `company_id`, `item_id`, `batch_number`, `tracking_type`, `quantity`, `expiry_date`, `received_at`, `notes`, `sort_order`, `created_at`, `updated_at`) VALUES
(1, 1, 9, 'LOT-WTR-2401', 'Lot', 180.0000, '2027-05-27', '2026-07-07', 'Spring warehouse receipt', 0, '2026-07-27 12:00:14', '2026-07-27 12:00:14'),
(2, 1, 9, 'LOT-WTR-2402', 'Lot', 170.0000, '2027-09-27', '2026-07-22', 'Latest receipt', 1, '2026-07-27 12:00:14', '2026-07-27 12:00:14'),
(3, 1, 13, 'SN-PT-10001', 'Serial', 1.0000, NULL, '2026-07-15', NULL, 0, '2026-07-27 12:00:14', '2026-07-27 12:00:14'),
(4, 1, 13, 'SN-PT-10002', 'Serial', 1.0000, NULL, '2026-07-15', NULL, 1, '2026-07-27 12:00:14', '2026-07-27 12:00:14'),
(5, 1, 13, 'SN-PT-10003', 'Serial', 1.0000, NULL, '2026-07-24', 'Display unit', 2, '2026-07-27 12:00:14', '2026-07-27 12:00:14');

-- --------------------------------------------------------

--
-- Table structure for table `item_prices`
--

CREATE TABLE `item_prices` (
  `id` bigint UNSIGNED NOT NULL,
  `item_id` bigint UNSIGNED NOT NULL,
  `price_level_id` bigint UNSIGNED DEFAULT NULL,
  `uom` varchar(16) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `price` decimal(12,4) NOT NULL DEFAULT '0.0000',
  `alias_code` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sort_order` smallint UNSIGNED NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `item_prices`
--

INSERT INTO `item_prices` (`id`, `item_id`, `price_level_id`, `uom`, `price`, `alias_code`, `sort_order`, `created_at`, `updated_at`) VALUES
(1, 2, NULL, 'CTN', 72.5000, 'MARL-RED', 0, '2026-07-21 08:39:39', '2026-07-21 08:39:39'),
(2, 3, NULL, 'BX', 0.0000, NULL, 0, '2026-07-23 21:20:47', '2026-07-23 21:20:47'),
(8, 4, NULL, 'BX', 400.0000, NULL, 0, '2026-07-23 23:27:41', '2026-07-23 23:27:41'),
(9, 5, NULL, 'CTN', 71.0000, 'MARL-GOLD-CTN', 0, '2026-07-24 08:03:43', '2026-07-24 08:03:43'),
(10, 5, 1, 'CTN', 68.5000, NULL, 1, '2026-07-24 08:03:43', '2026-07-24 08:03:43'),
(11, 5, 3, 'CTN', 67.0000, NULL, 2, '2026-07-24 08:03:43', '2026-07-24 08:03:43'),
(12, 6, NULL, 'CTN', 74.0000, 'NEWP-MENT-CTN', 0, '2026-07-24 08:03:43', '2026-07-24 08:03:43'),
(13, 6, 1, 'CTN', 71.5000, NULL, 1, '2026-07-24 08:03:43', '2026-07-24 08:03:43'),
(14, 7, NULL, 'CS', 18.5000, 'PEPSI-12PK-CS', 0, '2026-07-24 08:03:43', '2026-07-24 08:03:43'),
(15, 7, 1, 'CS', 17.2500, NULL, 1, '2026-07-24 08:03:43', '2026-07-24 08:03:43'),
(16, 7, 2, 'CS', 19.9900, NULL, 2, '2026-07-24 08:03:43', '2026-07-24 08:03:43'),
(17, 7, 3, 'CS', 16.5000, NULL, 3, '2026-07-24 08:03:43', '2026-07-24 08:03:43'),
(18, 8, NULL, 'CS', 18.7500, 'COKE-12PK-CS', 0, '2026-07-24 08:03:43', '2026-07-24 08:03:43'),
(19, 8, 1, 'CS', 17.5000, NULL, 1, '2026-07-24 08:03:43', '2026-07-24 08:03:43'),
(20, 8, 3, 'CS', 16.7500, NULL, 2, '2026-07-24 08:03:43', '2026-07-24 08:03:43'),
(21, 9, NULL, 'CS', 6.9900, 'WATER-24PK', 0, '2026-07-24 08:03:43', '2026-07-24 08:03:43'),
(22, 9, 1, 'CS', 6.2500, NULL, 1, '2026-07-24 08:03:43', '2026-07-24 08:03:43'),
(23, 9, 2, 'CS', 7.4900, NULL, 2, '2026-07-24 08:03:43', '2026-07-24 08:03:43'),
(24, 10, NULL, 'BAG', 3.4900, 'LAYS-CLASSIC', 0, '2026-07-24 08:03:43', '2026-07-24 08:03:43'),
(25, 10, 1, 'BAG', 3.1500, NULL, 1, '2026-07-24 08:03:43', '2026-07-24 08:03:43'),
(26, 10, 3, 'BAG', 2.9900, NULL, 2, '2026-07-24 08:03:43', '2026-07-24 08:03:43'),
(27, 11, NULL, 'BAG', 3.7900, 'DORITOS-NACH', 0, '2026-07-24 08:03:43', '2026-07-24 08:03:43'),
(28, 11, 1, 'BAG', 3.4000, NULL, 1, '2026-07-24 08:03:43', '2026-07-24 08:03:43'),
(29, 12, NULL, 'BX', 28.0000, 'SNICKERS-BX', 0, '2026-07-24 08:03:43', '2026-07-24 08:03:43'),
(30, 12, 1, 'BX', 26.0000, NULL, 1, '2026-07-24 08:03:43', '2026-07-24 08:03:43'),
(31, 12, 4, 'BX', 24.5000, NULL, 2, '2026-07-24 08:03:43', '2026-07-24 08:03:43'),
(32, 13, NULL, 'EA', 12.9900, 'PAPER-TOWEL', 0, '2026-07-24 08:03:43', '2026-07-24 08:03:43'),
(33, 13, 1, 'EA', 11.5000, NULL, 1, '2026-07-24 08:03:43', '2026-07-24 08:03:43'),
(34, 13, 2, 'EA', 13.9900, NULL, 2, '2026-07-24 08:03:43', '2026-07-24 08:03:43'),
(35, 14, NULL, 'EA', 9.9900, 'LOW-STOCK-01', 0, '2026-07-24 08:03:43', '2026-07-24 08:03:43'),
(36, 15, NULL, 'CTN', 500.0000, NULL, 0, '2026-07-24 09:01:13', '2026-07-24 09:01:13');

-- --------------------------------------------------------

--
-- Table structure for table `item_substitutes`
--

CREATE TABLE `item_substitutes` (
  `id` bigint UNSIGNED NOT NULL,
  `item_id` bigint UNSIGNED NOT NULL,
  `substitute_item_id` bigint UNSIGNED DEFAULT NULL,
  `quantity` decimal(14,4) NOT NULL DEFAULT '1.0000',
  `force_substitute` tinyint(1) NOT NULL DEFAULT '0',
  `sort_order` smallint UNSIGNED NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `item_substitutes`
--

INSERT INTO `item_substitutes` (`id`, `item_id`, `substitute_item_id`, `quantity`, `force_substitute`, `sort_order`, `created_at`, `updated_at`) VALUES
(1, 2, 5, 1.0000, 0, 0, '2026-07-27 12:00:14', '2026-07-27 12:00:14'),
(2, 7, 8, 1.0000, 0, 0, '2026-07-27 12:00:14', '2026-07-27 12:00:14'),
(3, 14, 13, 1.0000, 1, 0, '2026-07-27 12:00:14', '2026-07-27 12:00:14');

-- --------------------------------------------------------

--
-- Table structure for table `item_suppliers`
--

CREATE TABLE `item_suppliers` (
  `id` bigint UNSIGNED NOT NULL,
  `item_id` bigint UNSIGNED NOT NULL,
  `supplier_id` bigint UNSIGNED DEFAULT NULL,
  `supplier_item_code` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_received_at` date DEFAULT NULL,
  `last_cost` decimal(12,4) NOT NULL DEFAULT '0.0000',
  `avg_cost` decimal(12,4) NOT NULL DEFAULT '0.0000',
  `lead_time` int UNSIGNED NOT NULL DEFAULT '0',
  `is_default` tinyint(1) NOT NULL DEFAULT '0',
  `sort_order` smallint UNSIGNED NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `item_suppliers`
--

INSERT INTO `item_suppliers` (`id`, `item_id`, `supplier_id`, `supplier_item_code`, `last_received_at`, `last_cost`, `avg_cost`, `lead_time`, `is_default`, `sort_order`, `created_at`, `updated_at`) VALUES
(1, 2, 1, 'MR-RED', NULL, 0.0000, 0.0000, 3, 1, 0, '2026-07-21 08:39:39', '2026-07-21 08:39:39'),
(7, 4, 1, NULL, NULL, 0.0000, 0.0000, 0, 1, 0, '2026-07-23 23:27:41', '2026-07-23 23:27:41'),
(8, 15, 1, NULL, NULL, 0.0000, 0.0000, 0, 1, 0, '2026-07-24 09:01:13', '2026-07-24 09:01:13');

-- --------------------------------------------------------

--
-- Table structure for table `item_types`
--

CREATE TABLE `item_types` (
  `id` bigint UNSIGNED NOT NULL,
  `company_id` bigint UNSIGNED NOT NULL,
  `code` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `item_types`
--

INSERT INTO `item_types` (`id`, `company_id`, `code`, `name`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 1, 'STD', 'Standard Item', 1, '2026-07-21 14:19:58', '2026-07-21 14:19:58'),
(2, 1, 'KIT', 'Kit', 1, '2026-07-21 14:19:58', '2026-07-21 14:19:58'),
(3, 1, 'NONINV', 'Non-Inventory', 1, '2026-07-21 14:19:58', '2026-07-21 14:19:58'),
(4, 1, 'SVC', 'Service', 1, '2026-07-21 14:19:58', '2026-07-21 14:19:58');

-- --------------------------------------------------------

--
-- Table structure for table `item_upcs`
--

CREATE TABLE `item_upcs` (
  `id` bigint UNSIGNED NOT NULL,
  `item_id` bigint UNSIGNED NOT NULL,
  `upc` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_primary` tinyint(1) NOT NULL DEFAULT '0',
  `sort_order` smallint UNSIGNED NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `item_upcs`
--

INSERT INTO `item_upcs` (`id`, `item_id`, `upc`, `is_primary`, `sort_order`, `created_at`, `updated_at`) VALUES
(1, 2, '028200003123', 1, 0, '2026-07-21 08:39:39', '2026-07-21 08:39:39'),
(2, 3, 'ffffffffffffffffff', 1, 0, '2026-07-23 21:20:47', '2026-07-23 21:20:47'),
(8, 4, 'rthyujy', 1, 0, '2026-07-23 23:27:41', '2026-07-23 23:27:41'),
(9, 5, '028200003456', 1, 0, '2026-07-24 08:03:43', '2026-07-24 08:03:43'),
(10, 6, '026200009988', 1, 0, '2026-07-24 08:03:43', '2026-07-24 08:03:43'),
(11, 7, '012000001111', 1, 0, '2026-07-24 08:03:43', '2026-07-24 08:03:43'),
(12, 8, '049000001122', 1, 0, '2026-07-24 08:03:43', '2026-07-24 08:03:43'),
(13, 9, '078742001234', 1, 0, '2026-07-24 08:03:43', '2026-07-24 08:03:43'),
(14, 10, '028400001001', 1, 0, '2026-07-24 08:03:43', '2026-07-24 08:03:43'),
(15, 11, '028400002002', 1, 0, '2026-07-24 08:03:43', '2026-07-24 08:03:43'),
(16, 12, '040000003003', 1, 0, '2026-07-24 08:03:43', '2026-07-24 08:03:43'),
(17, 13, '037000004004', 1, 0, '2026-07-24 08:03:43', '2026-07-24 08:03:43'),
(18, 14, '999000000001', 1, 0, '2026-07-24 08:03:43', '2026-07-24 08:03:43');

-- --------------------------------------------------------

--
-- Table structure for table `jobs`
--

CREATE TABLE `jobs` (
  `id` bigint UNSIGNED NOT NULL,
  `queue` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts` smallint UNSIGNED NOT NULL,
  `reserved_at` int UNSIGNED DEFAULT NULL,
  `available_at` int UNSIGNED NOT NULL,
  `created_at` int UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `job_batches`
--

CREATE TABLE `job_batches` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_jobs` int NOT NULL,
  `pending_jobs` int NOT NULL,
  `failed_jobs` int NOT NULL,
  `failed_job_ids` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `options` mediumtext COLLATE utf8mb4_unicode_ci,
  `cancelled_at` int DEFAULT NULL,
  `created_at` int NOT NULL,
  `finished_at` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `migrations`
--

CREATE TABLE `migrations` (
  `id` int UNSIGNED NOT NULL,
  `migration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `migrations`
--

INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES
(1, '0001_01_01_000000_create_users_table', 1),
(2, '0001_01_01_000001_create_cache_table', 1),
(3, '0001_01_01_000002_create_jobs_table', 1),
(4, '2026_07_21_140049_create_companies_table', 1),
(5, '2026_07_21_140050_create_sites_table', 1),
(6, '2026_07_21_140051_create_roles_table', 1),
(7, '2026_07_21_140052_create_departments_table', 1),
(8, '2026_07_21_140053_create_categories_table', 1),
(9, '2026_07_21_140054_create_subcategories_table', 1),
(10, '2026_07_21_140055_create_uom_schedules_table', 1),
(11, '2026_07_21_140056_create_route_lookups_table', 1),
(12, '2026_07_21_140057_create_tax_schedules_table', 1),
(13, '2026_07_21_140058_create_pricing_methods_table', 1),
(14, '2026_07_21_140059_create_payment_terms_table', 1),
(15, '2026_07_21_140100_create_ship_vias_table', 1),
(16, '2026_07_21_140101_create_price_levels_table', 1),
(17, '2026_07_21_140102_create_discount_schedules_table', 1),
(18, '2026_07_21_140103_create_cigarette_tax_classes_table', 1),
(19, '2026_07_21_140104_create_purchase_limit_schedules_table', 1),
(20, '2026_07_21_140105_create_suppliers_table', 1),
(21, '2026_07_21_140106_create_supplier_contacts_table', 1),
(22, '2026_07_21_140200_create_items_and_customers_tables', 1),
(23, '2026_07_21_210000_expand_items_for_chief_parity', 2),
(24, '2026_07_21_220000_expand_customers_for_chief_parity', 3),
(25, '2026_07_21_221000_create_purchasing_module_tables', 3),
(26, '2026_07_21_222000_create_stock_counts_tables', 4),
(27, '2026_07_21_223000_create_sales_module_tables', 5),
(28, '2026_07_21_230000_add_city_state_zip_to_customer_shipping_addresses', 6),
(29, '2026_07_21_234000_phase_a_through_e_schema', 7),
(30, '2026_07_21_172755_create_personal_access_tokens_table', 8),
(31, '2026_07_21_235000_create_credit_memo_lines_table', 9),
(32, '2026_07_22_021800_create_item_types_table', 10),
(33, '2026_07_22_203500_add_processed_by_to_stock_counts', 11),
(34, '2026_07_22_220000_create_bulk_price_change_tables', 12),
(35, '2026_07_22_221000_add_portal_fields_to_customers', 13),
(36, '2026_07_22_223000_add_customer_app_api_active_to_companies', 14),
(37, '2026_07_22_224000_add_is_platform_admin_to_users', 15),
(38, '2026_07_23_120000_drop_is_platform_admin_from_users', 16),
(39, '2026_07_23_210000_add_restock_inventory_to_credit_memos', 17),
(40, '2026_07_23_233000_add_mail_settings_to_companies', 18),
(41, '2026_07_24_040000_role_permissions_price_levels_and_extras', 19),
(42, '2026_07_27_234800_add_line_message_and_instructions_to_sales_order_lines', 20),
(43, '2026_07_28_000100_create_item_batches_table', 21),
(44, '2026_07_28_004800_add_is_favorite_to_customers_table', 22);

-- --------------------------------------------------------

--
-- Table structure for table `password_reset_tokens`
--

CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payment_terms`
--

CREATE TABLE `payment_terms` (
  `id` bigint UNSIGNED NOT NULL,
  `company_id` bigint UNSIGNED NOT NULL,
  `code` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `days_due` int UNSIGNED NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `payment_terms`
--

INSERT INTO `payment_terms` (`id`, `company_id`, `code`, `name`, `days_due`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 1, 'N30', 'Net 30', 30, 1, '2026-07-21 08:14:33', '2026-07-21 08:14:33');

-- --------------------------------------------------------

--
-- Table structure for table `personal_access_tokens`
--

CREATE TABLE `personal_access_tokens` (
  `id` bigint UNSIGNED NOT NULL,
  `tokenable_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tokenable_id` bigint UNSIGNED NOT NULL,
  `name` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `abilities` text COLLATE utf8mb4_unicode_ci,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `price_levels`
--

CREATE TABLE `price_levels` (
  `id` bigint UNSIGNED NOT NULL,
  `company_id` bigint UNSIGNED NOT NULL,
  `code` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `price_levels`
--

INSERT INTO `price_levels` (`id`, `company_id`, `code`, `name`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 1, 'WS', 'Wholesale', 1, '2026-07-21 08:14:33', '2026-07-21 08:14:33'),
(2, 1, 'RET', 'Retail', 1, '2026-07-24 08:03:43', '2026-07-24 08:03:43'),
(3, 1, 'CHN', 'Chain', 1, '2026-07-24 08:03:43', '2026-07-24 08:03:43'),
(4, 1, 'VIP', 'VIP / Preferred', 1, '2026-07-24 08:03:43', '2026-07-24 08:03:43');

-- --------------------------------------------------------

--
-- Table structure for table `pricing_methods`
--

CREATE TABLE `pricing_methods` (
  `id` bigint UNSIGNED NOT NULL,
  `company_id` bigint UNSIGNED NOT NULL,
  `code` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `pricing_methods`
--

INSERT INTO `pricing_methods` (`id`, `company_id`, `code`, `name`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 1, 'FLAT', 'Flat Amount', 1, '2026-07-21 08:14:33', '2026-07-21 08:14:33');

-- --------------------------------------------------------

--
-- Table structure for table `purchase_limit_schedules`
--

CREATE TABLE `purchase_limit_schedules` (
  `id` bigint UNSIGNED NOT NULL,
  `company_id` bigint UNSIGNED NOT NULL,
  `code` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `purchase_limit_schedules`
--

INSERT INTO `purchase_limit_schedules` (`id`, `company_id`, `code`, `name`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 1, 'NONE', 'No Limit', 1, '2026-07-21 08:14:33', '2026-07-21 08:14:33');

-- --------------------------------------------------------

--
-- Table structure for table `purchase_orders`
--

CREATE TABLE `purchase_orders` (
  `id` bigint UNSIGNED NOT NULL,
  `company_id` bigint UNSIGNED NOT NULL,
  `po_number` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `order_type` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Standard',
  `reference_no` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `requisition_date` date DEFAULT NULL,
  `status` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'New',
  `buyer_id` bigint UNSIGNED DEFAULT NULL,
  `required_date` date DEFAULT NULL,
  `ship_to_site_id` bigint UNSIGNED DEFAULT NULL,
  `supplier_id` bigint UNSIGNED DEFAULT NULL,
  `ship_from` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payment_term_id` bigint UNSIGNED DEFAULT NULL,
  `ship_via_id` bigint UNSIGNED DEFAULT NULL,
  `comments` text COLLATE utf8mb4_unicode_ci,
  `subtotal` decimal(14,4) NOT NULL DEFAULT '0.0000',
  `trade_discount` decimal(14,4) NOT NULL DEFAULT '0.0000',
  `freight` decimal(14,4) NOT NULL DEFAULT '0.0000',
  `miscellaneous` decimal(14,4) NOT NULL DEFAULT '0.0000',
  `tax` decimal(14,4) NOT NULL DEFAULT '0.0000',
  `total` decimal(14,4) NOT NULL DEFAULT '0.0000',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `purchase_orders`
--

INSERT INTO `purchase_orders` (`id`, `company_id`, `po_number`, `order_type`, `reference_no`, `requisition_date`, `status`, `buyer_id`, `required_date`, `ship_to_site_id`, `supplier_id`, `ship_from`, `payment_term_id`, `ship_via_id`, `comments`, `subtotal`, `trade_discount`, `freight`, `miscellaneous`, `tax`, `total`, `created_at`, `updated_at`) VALUES
(1, 1, 'Suscipit dolore elit', 'Blanket', 'Omnis est magni ea c', '2004-05-19', 'New', 2, '1975-02-17', 1, 1, 'Aliquid alias evenie', 1, 1, 'Consectetur facilis ', 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, '2026-07-21 12:02:12', '2026-07-21 12:02:12'),
(2, 1, '1', 'Drop Ship', '', '2026-07-21', 'Received', 1, '2026-07-22', 1, 1, '', 1, 1, 'fghbfgnjh', 2.0000, 0.0000, 0.0000, 0.0000, 0.0000, 2.0000, '2026-07-21 14:41:31', '2026-07-21 14:53:42'),
(3, 1, '2', 'Standard', '111111111111111', '2026-07-23', 'Received', 1, '2026-07-23', 1, 1, 'adsfdffgv', 1, 1, ', j,m,', 20000.0000, 0.0000, 0.0000, 0.0000, 0.0000, 20000.0000, '2026-07-23 21:22:57', '2026-07-23 21:41:44'),
(4, 1, '3', 'Standard', 'vgfg', '2026-07-23', 'Received', 1, '2026-07-24', 1, 1, 'gvrfthb', 1, 1, 'fgbhgnb', 3000.0000, 0.0000, 0.0000, 0.0000, 0.0000, 3000.0000, '2026-07-23 23:21:35', '2026-07-23 23:22:02'),
(5, 1, 'DEMO-PO-1001', 'Purchase Order', NULL, '2026-07-19', 'New', 1, '2026-07-31', 1, 3, NULL, 1, 1, 'Demo open PO — not received yet', 1067.0000, 0.0000, 0.0000, 0.0000, 0.0000, 1067.0000, '2026-07-24 08:07:31', '2026-07-24 08:07:31'),
(6, 1, 'DEMO-PO-1002', 'Purchase Order', NULL, '2026-07-14', 'Partially Received', 1, '2026-07-26', 1, 4, NULL, 1, 1, 'Demo partial receive PO', 580.0000, 0.0000, 0.0000, 0.0000, 0.0000, 580.0000, '2026-07-24 08:07:31', '2026-07-24 08:07:31'),
(7, 1, 'DEMO-PO-1003', 'Purchase Order', NULL, '2026-07-04', 'Received', 1, '2026-07-19', 1, 5, NULL, 1, 1, 'Demo fully received PO', 205.0000, 0.0000, 0.0000, 0.0000, 0.0000, 205.0000, '2026-07-24 08:07:31', '2026-07-24 08:07:31');

-- --------------------------------------------------------

--
-- Table structure for table `purchase_order_lines`
--

CREATE TABLE `purchase_order_lines` (
  `id` bigint UNSIGNED NOT NULL,
  `purchase_order_id` bigint UNSIGNED NOT NULL,
  `item_id` bigint UNSIGNED DEFAULT NULL,
  `item_code` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `uom` varchar(16) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `qty_ordered` decimal(14,4) NOT NULL DEFAULT '0.0000',
  `qty_received` decimal(14,4) NOT NULL DEFAULT '0.0000',
  `unit_cost` decimal(12,4) NOT NULL DEFAULT '0.0000',
  `extended_cost` decimal(14,4) NOT NULL DEFAULT '0.0000',
  `line_no` smallint UNSIGNED NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `purchase_order_lines`
--

INSERT INTO `purchase_order_lines` (`id`, `purchase_order_id`, `item_id`, `item_code`, `description`, `uom`, `qty_ordered`, `qty_received`, `unit_cost`, `extended_cost`, `line_no`, `created_at`, `updated_at`) VALUES
(1, 2, 2, 'MARL-RED-CTN', 'Marlboro Red Carton', 'CTN', 1.0000, 1.0000, 2.0000, 2.0000, 1, '2026-07-21 14:43:58', '2026-07-21 14:53:42'),
(2, 3, 3, 'imran1', 'aaaaaaaaaaaaaaaaaa', 'BX', 20.0000, 20.0000, 1000.0000, 20000.0000, 1, '2026-07-23 21:22:57', '2026-07-23 21:41:44'),
(3, 4, 4, '111111111111111', '1111111111111111111', 'BX', 10.0000, 10.0000, 300.0000, 3000.0000, 1, '2026-07-23 23:21:35', '2026-07-23 23:22:02'),
(4, 5, 7, 'PEPSI-12PK-CS', 'Pepsi 12oz 12-Pack Case', 'CS', 40.0000, 0.0000, 12.2500, 490.0000, 1, '2026-07-24 08:07:31', '2026-07-24 08:07:31'),
(5, 5, 8, 'COKE-12PK-CS', 'Coca-Cola 12oz 12-Pack Case', 'CS', 30.0000, 0.0000, 12.4000, 372.0000, 2, '2026-07-24 08:07:31', '2026-07-24 08:07:31'),
(6, 5, 9, 'WATER-24PK', 'Purified Water 16.9oz 24-Pack', 'CS', 50.0000, 0.0000, 4.1000, 205.0000, 3, '2026-07-24 08:07:31', '2026-07-24 08:07:31'),
(7, 6, 10, 'LAYS-CLASSIC', 'Lay\'s Classic Potato Chips 8oz', 'BAG', 100.0000, 50.0000, 2.1000, 210.0000, 1, '2026-07-24 08:07:31', '2026-07-24 08:07:31'),
(8, 6, 12, 'SNICKERS-BX', 'Snickers Bar Box (24ct)', 'BX', 20.0000, 10.0000, 18.5000, 370.0000, 2, '2026-07-24 08:07:31', '2026-07-24 08:07:31'),
(9, 7, 13, 'PAPER-TOWEL', 'Paper Towels 6-Roll Pack', 'EA', 25.0000, 25.0000, 8.2000, 205.0000, 1, '2026-07-24 08:07:31', '2026-07-24 08:07:31');

-- --------------------------------------------------------

--
-- Table structure for table `return_to_vendors`
--

CREATE TABLE `return_to_vendors` (
  `id` bigint UNSIGNED NOT NULL,
  `company_id` bigint UNSIGNED NOT NULL,
  `rtv_number` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `rtv_date` date DEFAULT NULL,
  `status` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'New',
  `reference_no` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `supplier_id` bigint UNSIGNED DEFAULT NULL,
  `requested_by_id` bigint UNSIGNED DEFAULT NULL,
  `site_id` bigint UNSIGNED DEFAULT NULL,
  `comments` text COLLATE utf8mb4_unicode_ci,
  `subtotal` decimal(14,4) NOT NULL DEFAULT '0.0000',
  `discount` decimal(14,4) NOT NULL DEFAULT '0.0000',
  `freight` decimal(14,4) NOT NULL DEFAULT '0.0000',
  `total` decimal(14,4) NOT NULL DEFAULT '0.0000',
  `processed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `return_to_vendors`
--

INSERT INTO `return_to_vendors` (`id`, `company_id`, `rtv_number`, `rtv_date`, `status`, `reference_no`, `supplier_id`, `requested_by_id`, `site_id`, `comments`, `subtotal`, `discount`, `freight`, `total`, `processed_at`, `created_at`, `updated_at`) VALUES
(1, 1, '7000001', '2026-07-21', 'Returned', '435455', 1, 1, 1, '.l;;/o', 2.0000, 0.0000, 0.0000, 2.0000, '2026-07-21 15:00:44', '2026-07-21 15:00:38', '2026-07-21 15:00:44'),
(2, 1, '7000002', '2026-07-23', 'New', '', 1, 1, 1, '', 0.0000, 0.0000, 0.0000, 0.0000, NULL, '2026-07-24 00:13:54', '2026-07-24 00:13:54'),
(3, 1, 'DEMO-RTV-5001', '2026-07-23', 'New', 'DMG-DEMO', 4, 1, 1, 'Demo RTV — damaged bags (not processed)', 10.5000, 0.0000, 0.0000, 10.5000, NULL, '2026-07-24 08:07:32', '2026-07-24 08:07:32');

-- --------------------------------------------------------

--
-- Table structure for table `return_to_vendor_lines`
--

CREATE TABLE `return_to_vendor_lines` (
  `id` bigint UNSIGNED NOT NULL,
  `return_to_vendor_id` bigint UNSIGNED NOT NULL,
  `item_id` bigint UNSIGNED DEFAULT NULL,
  `item_code` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `uom` varchar(16) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `qty` decimal(14,4) NOT NULL DEFAULT '0.0000',
  `unit_cost` decimal(12,4) NOT NULL DEFAULT '0.0000',
  `extended_cost` decimal(14,4) NOT NULL DEFAULT '0.0000',
  `line_no` smallint UNSIGNED NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `return_to_vendor_lines`
--

INSERT INTO `return_to_vendor_lines` (`id`, `return_to_vendor_id`, `item_id`, `item_code`, `description`, `uom`, `qty`, `unit_cost`, `extended_cost`, `line_no`, `created_at`, `updated_at`) VALUES
(1, 1, 2, 'MARL-RED-CTN', 'Marlboro Red Carton', 'CTN', 1.0000, 2.0000, 2.0000, 1, '2026-07-21 15:00:38', '2026-07-21 15:00:38'),
(2, 2, 1, '1229W', 'SOUR PATCH WATERMELON 240CT', 'BX', 1.0000, 0.0000, 0.0000, 1, '2026-07-24 00:13:54', '2026-07-24 00:13:54'),
(3, 3, 10, 'LAYS-CLASSIC', 'Lay\'s Classic Potato Chips 8oz', 'BAG', 5.0000, 2.1000, 10.5000, 1, '2026-07-24 08:07:32', '2026-07-24 08:07:32');

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `id` bigint UNSIGNED NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `label` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `permissions` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`id`, `name`, `label`, `permissions`, `created_at`, `updated_at`) VALUES
(1, 'admin', 'Administrator', '[\"admin.users\", \"admin.email\", \"admin.terminal\", \"lookups\", \"sales.orders\", \"sales.customers\", \"sales.invoices\", \"sales.payments\", \"sales.credit_memos\", \"inventory.items\", \"inventory.stock_counts\", \"inventory.bulk_pricing\", \"purchasing.orders\", \"purchasing.receivings\", \"purchasing.rtv\", \"purchasing.suppliers\", \"inquiries\", \"reports\", \"tobacco\"]', '2026-07-21 08:14:32', '2026-07-21 08:14:32'),
(2, 'sales_rep', 'Sales Rep', '[\"admin.users\", \"admin.email\", \"admin.terminal\", \"lookups\", \"sales.orders\", \"sales.customers\", \"sales.invoices\", \"sales.payments\", \"sales.credit_memos\", \"inventory.items\", \"inventory.stock_counts\", \"inventory.bulk_pricing\", \"purchasing.orders\", \"purchasing.receivings\", \"purchasing.rtv\", \"purchasing.suppliers\", \"inquiries\", \"reports\", \"tobacco\"]', '2026-07-21 08:14:32', '2026-07-21 08:14:32'),
(3, 'buyer', 'Buyer', '[\"admin.users\", \"admin.email\", \"admin.terminal\", \"lookups\", \"sales.orders\", \"sales.customers\", \"sales.invoices\", \"sales.payments\", \"sales.credit_memos\", \"inventory.items\", \"inventory.stock_counts\", \"inventory.bulk_pricing\", \"purchasing.orders\", \"purchasing.receivings\", \"purchasing.rtv\", \"purchasing.suppliers\", \"inquiries\", \"reports\", \"tobacco\"]', '2026-07-21 08:14:32', '2026-07-21 08:14:32'),
(4, 'warehouse', 'Warehouse', '[\"admin.users\", \"admin.email\", \"admin.terminal\", \"lookups\", \"sales.orders\", \"sales.customers\", \"sales.invoices\", \"sales.payments\", \"sales.credit_memos\", \"inventory.items\", \"inventory.stock_counts\", \"inventory.bulk_pricing\", \"purchasing.orders\", \"purchasing.receivings\", \"purchasing.rtv\", \"purchasing.suppliers\", \"inquiries\", \"reports\", \"tobacco\"]', '2026-07-21 08:14:32', '2026-07-21 08:14:32');

-- --------------------------------------------------------

--
-- Table structure for table `sales_orders`
--

CREATE TABLE `sales_orders` (
  `id` bigint UNSIGNED NOT NULL,
  `company_id` bigint UNSIGNED NOT NULL,
  `order_number` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `order_type` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Sales Order',
  `status` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'New',
  `priority` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Normal',
  `customer_id` bigint UNSIGNED DEFAULT NULL,
  `ship_to_address_id` bigint UNSIGNED DEFAULT NULL,
  `bill_to_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bill_to_phone` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bill_to_address` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bill_to_city` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bill_to_state` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bill_to_zip` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ship_to_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ship_to_phone` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ship_to_address` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ship_to_city` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ship_to_state` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ship_to_zip` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `order_date` date DEFAULT NULL,
  `required_date` date DEFAULT NULL,
  `customer_po_no` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reference_no` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sales_rep_id` bigint UNSIGNED DEFAULT NULL,
  `payment_term_id` bigint UNSIGNED DEFAULT NULL,
  `route_id` bigint UNSIGNED DEFAULT NULL,
  `ship_via_id` bigint UNSIGNED DEFAULT NULL,
  `ship_from_site_id` bigint UNSIGNED DEFAULT NULL,
  `ship_date` date DEFAULT NULL,
  `no_of_boxes` int UNSIGNED NOT NULL DEFAULT '0',
  `no_of_pallets` int UNSIGNED NOT NULL DEFAULT '0',
  `custom_field_1` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `custom_field_2` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `custom_field_3` text COLLATE utf8mb4_unicode_ci,
  `custom_field_4` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `custom_field_5` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `comments` text COLLATE utf8mb4_unicode_ci,
  `subtotal` decimal(14,4) NOT NULL DEFAULT '0.0000',
  `trade_discount` decimal(14,4) NOT NULL DEFAULT '0.0000',
  `freight` decimal(14,4) NOT NULL DEFAULT '0.0000',
  `miscellaneous` decimal(14,4) NOT NULL DEFAULT '0.0000',
  `tax` decimal(14,4) NOT NULL DEFAULT '0.0000',
  `total` decimal(14,4) NOT NULL DEFAULT '0.0000',
  `created_by` bigint UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `sales_orders`
--

INSERT INTO `sales_orders` (`id`, `company_id`, `order_number`, `order_type`, `status`, `priority`, `customer_id`, `ship_to_address_id`, `bill_to_name`, `bill_to_phone`, `bill_to_address`, `bill_to_city`, `bill_to_state`, `bill_to_zip`, `ship_to_name`, `ship_to_phone`, `ship_to_address`, `ship_to_city`, `ship_to_state`, `ship_to_zip`, `order_date`, `required_date`, `customer_po_no`, `reference_no`, `sales_rep_id`, `payment_term_id`, `route_id`, `ship_via_id`, `ship_from_site_id`, `ship_date`, `no_of_boxes`, `no_of_pallets`, `custom_field_1`, `custom_field_2`, `custom_field_3`, `custom_field_4`, `custom_field_5`, `comments`, `subtotal`, `trade_discount`, `freight`, `miscellaneous`, `tax`, `total`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 1, '243074', 'Sales Order', 'Invoiced', 'Normal', 3, 2, 'Et doloribus odio do', 'Nesciunt quia quod ', 'Qui sed labore conse', 'Fugit non excepteur', 'Labore et nobis iure', 'Alias expedita volup', 'Dolor in temporibus ', 'Est unde exercitatio', 'Anim labore architec', 'Pariatur Cum dolor ', 'Praesentium nostrum ', 'Eiusmod assumenda te', '2026-07-21', '2026-07-21', '658u687i8786', '567y7u6', 1, 1, 1, 1, 1, '2026-07-22', 3, 1, 'grthyy6t', 'rtyhth', 'ythtyh', 'tyhtyjh', '45ghngjhnmjh', 'hnm hjm ', 9.9900, 0.0000, 0.0000, 0.0000, 0.0000, 9.9900, 1, '2026-07-21 12:17:58', '2026-07-21 12:18:03'),
(2, 1, '243075', 'Sales Order', 'Invoiced', 'Normal', 4, 4, 'Butler Melton LLC', '+1 (121) 216-9453', 'Molestias porro ut c', 'Adipisicing totam ad', 'Ad non libero sit ma', '74803', 'Assumenda ut laboris', 'Voluptate impedit c', 'Autem rerum laudanti', 'Est officia pariatu', 'Repellendus Sed off', 'Ut aut amet impedit', '2026-07-23', '2026-07-23', '', '', 1, 1, 1, 1, 1, '2026-07-23', 0, 0, '', '', '', '', '', '', 19.9800, 0.0000, 0.0000, 0.0000, 0.0000, 19.9800, 1, '2026-07-23 21:02:17', '2026-07-23 21:02:35'),
(3, 1, '243076', 'Sales Order', 'Invoiced', 'Normal', 4, 4, 'Butler Melton LLC', '+1 (121) 216-9453', 'Molestias porro ut c', 'Adipisicing totam ad', 'Ad non libero sit ma', '74803', 'Assumenda ut laboris', 'Voluptate impedit c', 'Autem rerum laudanti', 'Est officia pariatu', 'Repellendus Sed off', 'Ut aut amet impedit', '2026-07-23', '2026-07-23', '', '', 1, NULL, NULL, NULL, 1, '2026-07-23', 0, 0, '', '', '', '', '', '', 82.4900, 0.0000, 0.0000, 0.0000, 0.0400, 82.5300, 1, '2026-07-23 21:05:20', '2026-07-23 21:05:24'),
(4, 1, '243077', 'Return', 'Invoiced', 'Normal', 2, 5, 'Quia optio perferen', 'Nemo sed dolore offi', 'Consequuntur illo do', 'Deserunt deserunt ab', 'Omnis accusantium co', 'Praesentium rem plac', 'Dolore rerum adipisi', 'Rerum sunt asperiore', 'Est in qui ipsum har', 'Maiores sit molesti', 'Neque et rerum tempo', 'Atque occaecat ipsum', '2015-02-27', '1991-10-07', '215', '401', 1, 1, 1, 1, 1, '2006-01-10', 3, 9, 'Qui sapiente dicta a', 'Voluptatem qui eos t', 'Quia deserunt et non', 'Harum laboris verita', 'Ut reprehenderit est', 'Minus eaque at modi ', 72.5000, 0.0000, 0.0000, 0.0000, 0.0400, 72.5400, 1, '2026-07-23 21:05:26', '2026-07-23 21:05:38'),
(5, 1, '243078', 'Sales Order', 'Invoiced', 'Normal', 4, 4, 'Butler Melton LLC', '+1 (121) 216-9453', 'Molestias porro ut c', 'Adipisicing totam ad', 'Ad non libero sit ma', '74803', 'Assumenda ut laboris', 'Voluptate impedit c', 'Autem rerum laudanti', 'Est officia pariatu', 'Repellendus Sed off', 'Ut aut amet impedit', '2004-11-25', '1973-11-30', '899', '647', 1, NULL, NULL, NULL, 1, '2026-07-23', 0, 0, '', '', '', '', '', '', 400.0000, 0.0000, 0.0000, 0.0000, 0.0000, 400.0000, 1, '2026-07-23 23:29:04', '2026-07-23 23:29:17'),
(6, 1, 'DEMO-SO-3001', 'Sales Order', 'New', 'Normal', 5, NULL, 'Metro Convenience Mart', '313-555-1001', '450 Woodward Ave', 'Detroit', 'MI', '48226', 'Metro Convenience Mart', '313-555-1001', '450 Woodward Ave', 'Detroit', 'MI', '48226', '2026-07-23', '2026-07-27', 'PO-CUST-7781', NULL, 2, 1, 1, 1, 1, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 'Demo open sales order', 598.1000, 0.0000, 0.0000, 0.0000, 35.8900, 633.9900, 1, '2026-07-24 08:07:31', '2026-07-24 08:07:31'),
(7, 1, 'DEMO-SO-3002', 'Sales Order', 'Open', 'High', 7, NULL, 'Great Lakes Chain Stores', '248-555-1003', '2100 Corporate Dr', 'Troy', 'MI', '48084', 'Great Lakes Chain Stores', '248-555-1003', '2100 Corporate Dr', 'Troy', 'MI', '48084', '2026-07-24', '2026-07-25', 'GL-99201', NULL, 2, 1, 1, 1, 1, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 'Demo chain order — open', 741.0000, 0.0000, 0.0000, 0.0000, 44.4600, 785.4600, 1, '2026-07-24 08:07:31', '2026-07-24 08:07:31'),
(8, 1, 'DEMO-SO-3003', 'Sales Order', 'Invoiced', 'Normal', 6, NULL, 'Quick Stop Fuels', '248-555-1002', '88 Telegraph Rd', 'Southfield', 'MI', '48033', 'Quick Stop Fuels', '248-555-1002', '88 Telegraph Rd', 'Southfield', 'MI', '48033', '2026-07-17', '2026-07-19', 'QS-441', NULL, 2, 1, 1, 1, 1, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 'Demo invoiced order', 183.0000, 0.0000, 0.0000, 0.0000, 10.9800, 193.9800, 1, '2026-07-24 08:07:32', '2026-07-24 08:07:32'),
(9, 1, 'DEMO-SO-3004', 'Sales Order', 'Invoiced', 'Normal', 8, NULL, 'Harbor Wholesale Dist.', '810-555-1004', '15 Dock St', 'Port Huron', 'MI', '48060', 'Harbor Wholesale Dist.', '810-555-1004', '15 Dock St', 'Port Huron', 'MI', '48060', '2026-07-10', '2026-07-12', 'HW-100', NULL, 2, 1, 1, 1, 1, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 'Demo paid invoice', 107.8800, 0.0000, 0.0000, 0.0000, 6.4700, 114.3500, 1, '2026-07-24 08:07:32', '2026-07-24 08:07:32'),
(10, 1, '3005', 'Sales Order', 'New', 'Normal', 4, 4, 'Butler Melton LLC', '+1 (121) 216-9453', 'Molestias porro ut c', 'Adipisicing totam ad', 'Ad non libero sit ma', '74803', 'Assumenda ut laboris', 'Voluptate impedit c', 'Autem rerum laudanti', 'Est officia pariatu', 'Repellendus Sed off', 'Ut aut amet impedit', '2026-07-27', '2026-07-27', NULL, NULL, 1, 1, 1, 1, 1, '2026-07-27', 0, 0, NULL, NULL, NULL, NULL, NULL, NULL, 17.9800, 0.0000, 0.0000, 0.0000, 0.0000, 17.9800, 1, '2026-07-27 12:50:22', '2026-07-27 12:50:22'),
(11, 1, '3006', 'Sales Order', 'Invoiced', 'Normal', 3, 2, 'Et doloribus odio do', 'Nesciunt quia quod ', 'Qui sed labore conse', 'Fugit non excepteur', 'Labore et nobis iure', 'Alias expedita volup', 'Dolor in temporibus ', 'Est unde exercitatio', 'Anim labore architec', 'Pariatur Cum dolor ', 'Praesentium nostrum ', 'Eiusmod assumenda te', '2026-07-27', '2026-07-27', NULL, NULL, 1, 1, 1, 1, 1, '2026-07-27', 0, 0, NULL, NULL, NULL, NULL, NULL, NULL, 50.2500, 0.0000, 0.0000, 0.0000, 0.0300, 50.2800, 1, '2026-07-27 13:00:39', '2026-07-27 13:00:49'),
(12, 1, '3007', 'Sales Order', 'New', 'Normal', 4, 4, 'Butler Melton LLC', '+1 (121) 216-9453', 'Molestias porro ut c', 'Adipisicing totam ad', 'Ad non libero sit ma', '74803', 'Assumenda ut laboris', 'Voluptate impedit c', 'Autem rerum laudanti', 'Est officia pariatu', 'Repellendus Sed off', 'Ut aut amet impedit', '2026-07-27', '2026-07-27', NULL, NULL, 1, 1, 1, 1, 1, '2026-07-27', 0, 0, NULL, NULL, NULL, NULL, NULL, NULL, 74.0000, 0.0000, 0.0000, 0.0000, 0.0400, 74.0400, 1, '2026-07-27 13:01:48', '2026-07-27 13:01:48'),
(13, 1, '3009', 'Sales Order', 'Invoiced', 'Normal', 4, 4, 'Butler Melton LLC', '+1 (121) 216-9453', 'Molestias porro ut c', 'Adipisicing totam ad', 'Ad non libero sit ma', '74803', 'Assumenda ut laboris', 'Voluptate impedit c', 'Autem rerum laudanti', 'Est officia pariatu', 'Repellendus Sed off', 'Ut aut amet impedit', '2026-07-28', '2026-07-28', '111111111', NULL, 1, 1, 1, 1, 1, '2026-07-28', 0, 0, NULL, NULL, NULL, NULL, NULL, NULL, 18.7500, 0.0000, 0.0000, 0.0000, 0.0100, 18.7600, 1, '2026-07-28 08:01:29', '2026-07-28 08:01:39'),
(14, 1, '3010', 'Sales Order', 'Invoiced', 'Normal', 4, 4, 'Butler Melton LLC', '+1 (121) 216-9453', 'Molestias porro ut c', 'Adipisicing totam ad', 'Ad non libero sit ma', '74803', 'Assumenda ut laboris', 'Voluptate impedit c', 'Autem rerum laudanti', 'Est officia pariatu', 'Repellendus Sed off', 'Ut aut amet impedit', '2026-07-28', '2026-07-28', NULL, NULL, 1, 1, 1, 1, 1, '2026-07-28', 1, 0, NULL, NULL, NULL, NULL, NULL, NULL, 91.2500, 0.0000, 0.0000, 0.0000, 0.0500, 91.3000, 1, '2026-07-28 08:18:44', '2026-07-28 08:25:13'),
(15, 1, '3011', 'Sales Order', 'New', 'Normal', 4, 4, 'Butler Melton LLC', '+1 (121) 216-9453', 'Molestias porro ut c', 'Adipisicing totam ad', 'Ad non libero sit ma', '74803', 'Assumenda ut laboris', 'Voluptate impedit c', 'Autem rerum laudanti', 'Est officia pariatu', 'Repellendus Sed off', 'Ut aut amet impedit', '2026-07-28', '2026-07-28', NULL, NULL, 1, 1, 1, 1, 1, '2026-07-28', 0, 0, NULL, NULL, NULL, NULL, NULL, NULL, 9.9900, 0.0000, 0.0000, 0.0000, 0.0000, 9.9900, 1, '2026-07-28 08:26:38', '2026-07-28 08:26:38');

-- --------------------------------------------------------

--
-- Table structure for table `sales_order_boxes`
--

CREATE TABLE `sales_order_boxes` (
  `id` bigint UNSIGNED NOT NULL,
  `sales_order_id` bigint UNSIGNED NOT NULL,
  `box_number` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tracking_number` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sort_order` smallint UNSIGNED NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `sales_order_boxes`
--

INSERT INTO `sales_order_boxes` (`id`, `sales_order_id`, `box_number`, `tracking_number`, `sort_order`, `created_at`, `updated_at`) VALUES
(1, 1, 'grh45t546ty', '456t567y76u687', 0, '2026-07-21 12:17:58', '2026-07-21 12:17:58'),
(2, 2, 'e33', '333', 0, '2026-07-23 21:02:17', '2026-07-23 21:02:17'),
(3, 4, 'Rerum pariatur Quo ', 'Soluta non non sed d', 0, '2026-07-23 21:05:26', '2026-07-23 21:05:26');

-- --------------------------------------------------------

--
-- Table structure for table `sales_order_lines`
--

CREATE TABLE `sales_order_lines` (
  `id` bigint UNSIGNED NOT NULL,
  `sales_order_id` bigint UNSIGNED NOT NULL,
  `item_id` bigint UNSIGNED DEFAULT NULL,
  `item_code` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `uom` varchar(16) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `qty_ordered` decimal(14,4) NOT NULL DEFAULT '0.0000',
  `qty_shipped` decimal(14,4) NOT NULL DEFAULT '0.0000',
  `price` decimal(12,4) NOT NULL DEFAULT '0.0000',
  `discount` decimal(12,4) NOT NULL DEFAULT '0.0000',
  `line_message` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `instructions` text COLLATE utf8mb4_unicode_ci,
  `line_total` decimal(14,4) NOT NULL DEFAULT '0.0000',
  `line_no` smallint UNSIGNED NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `sales_order_lines`
--

INSERT INTO `sales_order_lines` (`id`, `sales_order_id`, `item_id`, `item_code`, `description`, `uom`, `qty_ordered`, `qty_shipped`, `price`, `discount`, `line_message`, `instructions`, `line_total`, `line_no`, `created_at`, `updated_at`) VALUES
(1, 1, 1, '1229W', 'SOUR PATCH WATERMELON 240CT', 'BX', 1.0000, 0.0000, 9.9900, 0.0000, NULL, NULL, 9.9900, 1, '2026-07-21 12:17:58', '2026-07-21 12:17:58'),
(2, 2, 1, '1229W', 'SOUR PATCH WATERMELON 240CT', 'BX', 1.0000, 1.0000, 9.9900, 0.0000, NULL, NULL, 9.9900, 1, '2026-07-23 21:02:17', '2026-07-23 21:02:35'),
(3, 2, 1, '1229W', 'SOUR PATCH WATERMELON 240CT', 'BX', 1.0000, 1.0000, 9.9900, 0.0000, NULL, NULL, 9.9900, 2, '2026-07-23 21:02:17', '2026-07-23 21:02:35'),
(4, 3, 1, '1229W', 'SOUR PATCH WATERMELON 240CT', 'BX', 1.0000, 1.0000, 9.9900, 0.0000, NULL, NULL, 9.9900, 1, '2026-07-23 21:05:20', '2026-07-23 21:05:24'),
(5, 3, 2, 'MARL-RED-CTN', 'Marlboro Red Carton', 'CTN', 1.0000, 1.0000, 72.5000, 0.0000, NULL, NULL, 72.5000, 2, '2026-07-23 21:05:20', '2026-07-23 21:05:24'),
(6, 4, 2, 'MARL-RED-CTN', 'Marlboro Red Carton', 'CTN', 1.0000, 1.0000, 72.5000, 0.0000, NULL, NULL, 72.5000, 1, '2026-07-23 21:05:26', '2026-07-23 21:05:38'),
(7, 5, 4, '111111111111111', '1111111111111111111 | kjh,kj', 'BX', 1.0000, 1.0000, 400.0000, 0.0000, NULL, NULL, 400.0000, 1, '2026-07-23 23:29:04', '2026-07-23 23:29:17'),
(8, 6, 2, 'MARL-RED-CTN', 'Marlboro Red Carton', 'CTN', 5.0000, 0.0000, 70.0000, 0.0000, NULL, NULL, 350.0000, 1, '2026-07-24 08:07:31', '2026-07-24 08:07:31'),
(9, 6, 7, 'PEPSI-12PK-CS', 'Pepsi 12oz 12-Pack Case', 'CS', 10.0000, 0.0000, 17.2500, 0.0000, NULL, NULL, 172.5000, 2, '2026-07-24 08:07:31', '2026-07-24 08:07:31'),
(10, 6, 10, 'LAYS-CLASSIC', 'Lay\'s Classic Potato Chips 8oz', 'BAG', 24.0000, 0.0000, 3.1500, 0.0000, NULL, NULL, 75.6000, 3, '2026-07-24 08:07:31', '2026-07-24 08:07:31'),
(11, 7, 8, 'COKE-12PK-CS', 'Coca-Cola 12oz 12-Pack Case', 'CS', 20.0000, 0.0000, 16.7500, 0.0000, NULL, NULL, 335.0000, 1, '2026-07-24 08:07:32', '2026-07-24 08:07:32'),
(12, 7, 9, 'WATER-24PK', 'Purified Water 16.9oz 24-Pack', 'CS', 40.0000, 0.0000, 6.2500, 0.0000, NULL, NULL, 250.0000, 2, '2026-07-24 08:07:32', '2026-07-24 08:07:32'),
(13, 7, 12, 'SNICKERS-BX', 'Snickers Bar Box (24ct)', 'BX', 6.0000, 0.0000, 26.0000, 0.0000, NULL, NULL, 156.0000, 3, '2026-07-24 08:07:32', '2026-07-24 08:07:32'),
(14, 8, 5, 'MARL-GOLD-CTN', 'Marlboro Gold Carton', 'CTN', 2.0000, 2.0000, 68.5000, 0.0000, NULL, NULL, 137.0000, 1, '2026-07-24 08:07:32', '2026-07-24 08:07:32'),
(15, 8, 13, 'PAPER-TOWEL', 'Paper Towels 6-Roll Pack', 'EA', 4.0000, 4.0000, 11.5000, 0.0000, NULL, NULL, 46.0000, 2, '2026-07-24 08:07:32', '2026-07-24 08:07:32'),
(16, 9, 9, 'WATER-24PK', 'Purified Water 16.9oz 24-Pack', 'CS', 12.0000, 12.0000, 6.0000, 0.0000, NULL, NULL, 72.0000, 1, '2026-07-24 08:07:32', '2026-07-24 08:07:32'),
(17, 9, 10, 'LAYS-CLASSIC', 'Lay\'s Classic Potato Chips 8oz', 'BAG', 12.0000, 12.0000, 2.9900, 0.0000, NULL, NULL, 35.8800, 2, '2026-07-24 08:07:32', '2026-07-24 08:07:32'),
(19, 10, 1, '1229W', 'SOUR PATCH WATERMELON 240CT', 'BX', 2.0000, 0.0000, 9.9900, 2.0000, 'ffffffffffffffffffffffffffffffffffffffffffffffff', 'ffffgggggggggggggggggggggggggggggggggggggggggggggggg', 17.9800, 1, '2026-07-27 12:55:55', '2026-07-27 12:55:55'),
(20, 11, 8, 'COKE-12PK-CS', 'Coca-Cola 12oz 12-Pack Case', 'CS', 3.0000, 3.0000, 18.7500, 6.0000, NULL, NULL, 50.2500, 1, '2026-07-27 13:00:39', '2026-07-27 13:00:49'),
(21, 12, 6, 'NEWP-MENT-CTN', 'Newport Menthol Carton', 'CTN', 1.0000, 0.0000, 74.0000, 0.0000, NULL, NULL, 74.0000, 1, '2026-07-27 13:01:48', '2026-07-27 13:01:48'),
(22, 13, 8, 'COKE-12PK-CS', 'Coca-Cola 12oz 12-Pack Case', 'CS', 1.0000, 1.0000, 18.7500, 0.0000, NULL, NULL, 18.7500, 1, '2026-07-28 08:01:29', '2026-07-28 08:01:29'),
(23, 14, 8, 'COKE-12PK-CS', 'Coca-Cola 12oz 12-Pack Case', 'CS', 1.0000, 1.0000, 18.7500, 0.0000, 'tjyuju', 'yjumkumui', 18.7500, 1, '2026-07-28 08:18:45', '2026-07-28 08:25:13'),
(24, 14, 2, 'MARL-RED-CTN', 'Marlboro Red Carton', 'CTN', 1.0000, 1.0000, 72.5000, 0.0000, NULL, NULL, 72.5000, 2, '2026-07-28 08:18:45', '2026-07-28 08:25:13'),
(25, 15, 1, '1229W', 'SOUR PATCH WATERMELON 240CT', 'BX', 1.0000, 0.0000, 9.9900, 0.0000, NULL, NULL, 9.9900, 1, '2026-07-28 08:26:38', '2026-07-28 08:41:31');

-- --------------------------------------------------------

--
-- Table structure for table `sessions`
--

CREATE TABLE `sessions` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint UNSIGNED DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_activity` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `sessions`
--

INSERT INTO `sessions` (`id`, `user_id`, `ip_address`, `user_agent`, `payload`, `last_activity`) VALUES
('38pLZPvl5MFRTm24dCGCqPduTcDLR2P9zSZUz7Ud', 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'eyJfdG9rZW4iOiJvdG5jdXFDUlJ5MjVNdjk1c1M3OVdJVkxJcUNzREhUUEUzdGdmMDdQIiwiX3ByZXZpb3VzIjp7InVybCI6Imh0dHBzOlwvXC93d3cucG9zY29udGluZW50YWx3aG9sZXNhbGUudGVzdFwvc2FsZXNcL2ludm9pY2VzIiwicm91dGUiOiJzYWxlcy5pbnZvaWNlcy5pbmRleCJ9LCJfZmxhc2giOnsib2xkIjpbXSwibmV3IjpbXX0sImxvZ2luX3dlYl81OWJhMzZhZGRjMmIyZjk0MDE1ODBmMDE0YzdmNThlYTRlMzA5ODlkIjoxLCJjb21wYW55X2lkIjoxLCJzaXRlX2lkIjoxLCJjb21wYW55X25hbWUiOiJDb250aW5lbnRhbCBXaG9sZXNhbGUgSW5jIiwic2l0ZV9jb2RlIjoiV1MifQ==', 1785250194);

-- --------------------------------------------------------

--
-- Table structure for table `ship_vias`
--

CREATE TABLE `ship_vias` (
  `id` bigint UNSIGNED NOT NULL,
  `company_id` bigint UNSIGNED NOT NULL,
  `code` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `ship_vias`
--

INSERT INTO `ship_vias` (`id`, `company_id`, `code`, `name`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 1, 'TRUCK', 'Truck', 1, '2026-07-21 08:14:33', '2026-07-21 08:14:33');

-- --------------------------------------------------------

--
-- Table structure for table `sites`
--

CREATE TABLE `sites` (
  `id` bigint UNSIGNED NOT NULL,
  `company_id` bigint UNSIGNED NOT NULL,
  `code` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `sites`
--

INSERT INTO `sites` (`id`, `company_id`, `code`, `name`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 1, 'WS', 'Wholesale Site', 1, '2026-07-21 08:14:32', '2026-07-21 08:14:32');

-- --------------------------------------------------------

--
-- Table structure for table `stock_counts`
--

CREATE TABLE `stock_counts` (
  `id` bigint UNSIGNED NOT NULL,
  `company_id` bigint UNSIGNED NOT NULL,
  `stock_count_no` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `date_created` date DEFAULT NULL,
  `status` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'New',
  `last_count_date` date DEFAULT NULL,
  `date_entered` timestamp NULL DEFAULT NULL,
  `date_processed` date DEFAULT NULL,
  `processed_by` bigint UNSIGNED DEFAULT NULL,
  `site_id` bigint UNSIGNED DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `shared_count` tinyint(1) NOT NULL DEFAULT '0',
  `comments` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `stock_counts`
--

INSERT INTO `stock_counts` (`id`, `company_id`, `stock_count_no`, `date_created`, `status`, `last_count_date`, `date_entered`, `date_processed`, `processed_by`, `site_id`, `description`, `shared_count`, `comments`, `created_at`, `updated_at`) VALUES
(1, 1, '300450481966', '2026-07-21', 'Processed', NULL, '2026-07-21 14:30:50', '2026-07-21', 1, 1, '', 1, '', '2026-07-21 14:30:50', '2026-07-22 08:46:02');

-- --------------------------------------------------------

--
-- Table structure for table `stock_count_lines`
--

CREATE TABLE `stock_count_lines` (
  `id` bigint UNSIGNED NOT NULL,
  `stock_count_id` bigint UNSIGNED NOT NULL,
  `item_id` bigint UNSIGNED DEFAULT NULL,
  `item_code` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `uom` varchar(16) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `in_stock` decimal(14,4) NOT NULL DEFAULT '0.0000',
  `allocated` decimal(14,4) NOT NULL DEFAULT '0.0000',
  `counted` decimal(14,4) DEFAULT NULL,
  `count_time` timestamp NULL DEFAULT NULL,
  `line_no` smallint UNSIGNED NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `subcategories`
--

CREATE TABLE `subcategories` (
  `id` bigint UNSIGNED NOT NULL,
  `company_id` bigint UNSIGNED NOT NULL,
  `category_id` bigint UNSIGNED NOT NULL,
  `code` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `subcategories`
--

INSERT INTO `subcategories` (`id`, `company_id`, `category_id`, `code`, `name`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 'CARTON', 'Cartons', 1, '2026-07-21 08:14:33', '2026-07-21 08:14:33'),
(2, 1, 1, 'PACK', 'Packs', 1, '2026-07-24 08:03:43', '2026-07-24 08:03:43'),
(3, 1, 2, 'CIGAR', 'Cigars', 1, '2026-07-24 08:03:43', '2026-07-24 08:03:43'),
(4, 1, 2, 'CHEW', 'Chew', 1, '2026-07-24 08:03:43', '2026-07-24 08:03:43'),
(5, 1, 3, 'CAN', 'Cans', 1, '2026-07-24 08:03:43', '2026-07-24 08:03:43'),
(6, 1, 3, 'BTL', 'Bottles', 1, '2026-07-24 08:03:43', '2026-07-24 08:03:43'),
(7, 1, 4, 'CASE', 'Cases', 1, '2026-07-24 08:03:43', '2026-07-24 08:03:43'),
(8, 1, 5, 'BAG', 'Bags', 1, '2026-07-24 08:03:43', '2026-07-24 08:03:43'),
(9, 1, 6, 'BX', 'Boxes', 1, '2026-07-24 08:03:43', '2026-07-24 08:03:43'),
(10, 1, 7, 'CS', 'Cases', 1, '2026-07-24 08:03:43', '2026-07-24 08:03:43');

-- --------------------------------------------------------

--
-- Table structure for table `suppliers`
--

CREATE TABLE `suppliers` (
  `id` bigint UNSIGNED NOT NULL,
  `company_id` bigint UNSIGNED NOT NULL,
  `supplier_id` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_inactive` tinyint(1) NOT NULL DEFAULT '0',
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `contact_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `city` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `state` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `zip_code` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `country` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'US',
  `fein_no` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone1` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone2` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fax` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `web_page` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_tobacco_supplier` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `suppliers`
--

INSERT INTO `suppliers` (`id`, `company_id`, `supplier_id`, `is_inactive`, `name`, `contact_name`, `address`, `city`, `state`, `zip_code`, `country`, `fein_no`, `phone1`, `phone2`, `fax`, `email`, `web_page`, `is_tobacco_supplier`, `created_at`, `updated_at`) VALUES
(1, 1, '888', 0, 'CITY FOOD & BEVERAGE', NULL, 'Detroit MI', NULL, NULL, NULL, 'US', '12-3456789', '(313) 555-0100', NULL, NULL, 'info@cityfood.example', 'WWW.CITYFOOD.COM', 0, '2026-07-21 08:15:10', '2026-07-21 08:15:10'),
(2, 1, 'SUP-ALTRIA', 0, 'Altria Distribution', 'Mike Chen', '6601 W Broad St', 'Richmond', 'VA', '23230', 'US', '54-1234567', '804-555-0101', NULL, NULL, 'orders@altria-demo.local', NULL, 1, '2026-07-24 08:03:43', '2026-07-24 08:03:43'),
(3, 1, 'SUP-PEPSI', 0, 'Pepsi Bottling Demo', 'Sara Lopez', '700 Anderson Hill Rd', 'Purchase', 'NY', '10577', 'US', '13-9876543', '914-555-0202', NULL, NULL, 'sales@pepsi-demo.local', NULL, 0, '2026-07-24 08:03:43', '2026-07-24 08:03:43'),
(4, 1, 'SUP-FRITO', 0, 'Frito-Lay Wholesale', 'James Okonkwo', '7701 Legacy Dr', 'Plano', 'TX', '75024', 'US', '75-5551212', '972-555-0303', NULL, NULL, 'wholesale@fritolay-demo.local', NULL, 0, '2026-07-24 08:03:43', '2026-07-24 08:03:43'),
(5, 1, 'SUP-GEN', 0, 'General Merchandise Co', 'Amy Park', '1200 Industrial Pkwy', 'Detroit', 'MI', '48201', 'US', '38-4443322', '313-555-0404', NULL, NULL, 'buy@gmc-demo.local', NULL, 0, '2026-07-24 08:03:43', '2026-07-24 08:03:43');

-- --------------------------------------------------------

--
-- Table structure for table `supplier_contacts`
--

CREATE TABLE `supplier_contacts` (
  `id` bigint UNSIGNED NOT NULL,
  `supplier_id` bigint UNSIGNED NOT NULL,
  `department` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contact_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ext` varchar(16) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tax_schedules`
--

CREATE TABLE `tax_schedules` (
  `id` bigint UNSIGNED NOT NULL,
  `company_id` bigint UNSIGNED NOT NULL,
  `code` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `rate` decimal(8,4) NOT NULL DEFAULT '0.0000',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tax_schedules`
--

INSERT INTO `tax_schedules` (`id`, `company_id`, `code`, `name`, `rate`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 1, 'STD', 'Standard Tax', 0.0600, 1, '2026-07-21 08:14:33', '2026-07-21 08:14:33');

-- --------------------------------------------------------

--
-- Table structure for table `tobacco_stamp_inventories`
--

CREATE TABLE `tobacco_stamp_inventories` (
  `id` bigint UNSIGNED NOT NULL,
  `company_id` bigint UNSIGNED NOT NULL,
  `period_start` date DEFAULT NULL,
  `period_end` date DEFAULT NULL,
  `r1_beginning_unaffixed` decimal(14,2) NOT NULL DEFAULT '0.00',
  `r2_beginning_affixed` decimal(14,2) NOT NULL DEFAULT '0.00',
  `r3_purchased` decimal(14,2) NOT NULL DEFAULT '0.00',
  `r4_affixed` decimal(14,2) NOT NULL DEFAULT '0.00',
  `r5_ending_unaffixed` decimal(14,2) NOT NULL DEFAULT '0.00',
  `r6_ending_affixed` decimal(14,2) NOT NULL DEFAULT '0.00',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_by` bigint UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tobacco_stamp_inventories`
--

INSERT INTO `tobacco_stamp_inventories` (`id`, `company_id`, `period_start`, `period_end`, `r1_beginning_unaffixed`, `r2_beginning_affixed`, `r3_purchased`, `r4_affixed`, `r5_ending_unaffixed`, `r6_ending_affixed`, `notes`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 1, '2026-07-21', '2026-07-22', 10.00, 20.00, 5.00, 3.00, 12.00, 22.00, 'cli test', 1, '2026-07-21 12:05:50', '2026-07-21 12:05:50');

-- --------------------------------------------------------

--
-- Table structure for table `uom_schedules`
--

CREATE TABLE `uom_schedules` (
  `id` bigint UNSIGNED NOT NULL,
  `company_id` bigint UNSIGNED NOT NULL,
  `code` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `base_uom` varchar(16) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `uom_schedules`
--

INSERT INTO `uom_schedules` (`id`, `company_id`, `code`, `name`, `base_uom`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 1, 'EA-BX', 'Each / Box', 'EA', 1, '2026-07-21 08:14:33', '2026-07-21 08:14:33'),
(2, 1, 'EA', 'Each', 'EA', 1, '2026-07-23 22:00:21', '2026-07-23 22:00:21'),
(3, 1, 'BX', 'Box', 'BX', 1, '2026-07-23 22:00:21', '2026-07-23 22:00:21'),
(4, 1, 'CS', 'Case', 'CS', 1, '2026-07-23 22:00:21', '2026-07-23 22:00:21'),
(5, 1, 'CTN', 'Carton', 'CTN', 1, '2026-07-23 22:00:21', '2026-07-23 22:00:21'),
(6, 1, 'PK', 'Pack', 'PK', 1, '2026-07-23 22:00:21', '2026-07-23 22:00:21'),
(7, 1, 'DZ', 'Dozen', 'DZ', 1, '2026-07-23 22:00:21', '2026-07-23 22:00:21'),
(8, 1, 'LB', 'Pound', 'LB', 1, '2026-07-23 22:00:21', '2026-07-23 22:00:21'),
(9, 1, 'KG', 'Kilogram', 'KG', 1, '2026-07-23 22:00:21', '2026-07-23 22:00:21'),
(10, 1, 'PLT', 'Pallet', 'PLT', 1, '2026-07-23 22:00:21', '2026-07-23 22:00:21'),
(11, 1, 'BAG', 'Bag', 'BAG', 1, '2026-07-23 22:00:21', '2026-07-23 22:00:21'),
(12, 1, 'BX-CS', 'Box / Case', 'BX', 1, '2026-07-23 22:00:21', '2026-07-23 22:00:21');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` bigint UNSIGNED NOT NULL,
  `company_id` bigint UNSIGNED DEFAULT NULL,
  `site_id` bigint UNSIGNED DEFAULT NULL,
  `role_id` bigint UNSIGNED DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `username` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `company_id`, `site_id`, `role_id`, `name`, `username`, `email`, `email_verified_at`, `password`, `is_active`, `remember_token`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 1, 'POS Admin', 'admin@gmail.com', 'admin@gmail.com', '2026-07-21 08:14:33', '$2y$12$LeHh6vx20V4XQ4qcusxMN.tnGMFxrUhWU2qk7j4w592nDaLWjiHFe', 1, NULL, '2026-07-21 08:14:33', '2026-07-23 07:20:14'),
(2, 1, 1, 2, 'Sales Rep', 'sales', 'sales@continental.local', '2026-07-21 08:14:33', '$2y$12$4SN8qoX1/pFjLvs.lYF2IOyZrW44dHl9GXqOpQK7oXk3XPpkscxX.', 1, NULL, '2026-07-21 08:14:33', '2026-07-21 08:14:33');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `bulk_price_change_items`
--
ALTER TABLE `bulk_price_change_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `bulk_price_change_items_bulk_price_change_log_id_foreign` (`bulk_price_change_log_id`),
  ADD KEY `bulk_price_change_items_item_id_foreign` (`item_id`);

--
-- Indexes for table `bulk_price_change_logs`
--
ALTER TABLE `bulk_price_change_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `bulk_price_change_logs_company_id_foreign` (`company_id`),
  ADD KEY `bulk_price_change_logs_user_id_foreign` (`user_id`);

--
-- Indexes for table `cache`
--
ALTER TABLE `cache`
  ADD PRIMARY KEY (`key`),
  ADD KEY `cache_expiration_index` (`expiration`);

--
-- Indexes for table `cache_locks`
--
ALTER TABLE `cache_locks`
  ADD PRIMARY KEY (`key`),
  ADD KEY `cache_locks_expiration_index` (`expiration`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `categories_company_id_code_unique` (`company_id`,`code`),
  ADD KEY `categories_department_id_foreign` (`department_id`);

--
-- Indexes for table `cigarette_tax_classes`
--
ALTER TABLE `cigarette_tax_classes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `cigarette_tax_classes_company_id_code_unique` (`company_id`,`code`);

--
-- Indexes for table `companies`
--
ALTER TABLE `companies`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `companies_code_unique` (`code`);

--
-- Indexes for table `credit_memos`
--
ALTER TABLE `credit_memos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `credit_memos_company_id_memo_number_unique` (`company_id`,`memo_number`),
  ADD KEY `credit_memos_customer_id_foreign` (`customer_id`),
  ADD KEY `credit_memos_sales_order_id_foreign` (`sales_order_id`);

--
-- Indexes for table `credit_memo_lines`
--
ALTER TABLE `credit_memo_lines`
  ADD PRIMARY KEY (`id`),
  ADD KEY `credit_memo_lines_credit_memo_id_foreign` (`credit_memo_id`),
  ADD KEY `credit_memo_lines_item_id_foreign` (`item_id`);

--
-- Indexes for table `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `customers_company_id_customer_id_unique` (`company_id`,`customer_id`),
  ADD KEY `customers_price_level_id_foreign` (`price_level_id`),
  ADD KEY `customers_cigarette_tax_class_id_foreign` (`cigarette_tax_class_id`),
  ADD KEY `customers_discount_schedule_id_foreign` (`discount_schedule_id`),
  ADD KEY `customers_purchase_limit_schedule_id_foreign` (`purchase_limit_schedule_id`),
  ADD KEY `customers_payment_term_id_foreign` (`payment_term_id`),
  ADD KEY `customers_sales_rep_id_foreign` (`sales_rep_id`),
  ADD KEY `customers_delivery_route_id_foreign` (`delivery_route_id`),
  ADD KEY `customers_company_portal_email_index` (`company_id`,`portal_email`),
  ADD KEY `customers_company_id_is_favorite_index` (`company_id`,`is_favorite`);

--
-- Indexes for table `customer_lookup_options`
--
ALTER TABLE `customer_lookup_options`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `customer_lookup_options_company_id_type_name_unique` (`company_id`,`type`,`name`);

--
-- Indexes for table `customer_shipping_addresses`
--
ALTER TABLE `customer_shipping_addresses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `customer_shipping_addresses_customer_id_foreign` (`customer_id`);

--
-- Indexes for table `delivery_routes`
--
ALTER TABLE `delivery_routes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `delivery_routes_company_id_code_unique` (`company_id`,`code`);

--
-- Indexes for table `departments`
--
ALTER TABLE `departments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `departments_company_id_code_unique` (`company_id`,`code`);

--
-- Indexes for table `discount_schedules`
--
ALTER TABLE `discount_schedules`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `discount_schedules_company_id_code_unique` (`company_id`,`code`);

--
-- Indexes for table `document_email_logs`
--
ALTER TABLE `document_email_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `document_email_logs_company_id_foreign` (`company_id`),
  ADD KEY `document_email_logs_user_id_foreign` (`user_id`);

--
-- Indexes for table `failed_jobs`
--
ALTER TABLE `failed_jobs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`),
  ADD KEY `failed_jobs_connection_queue_failed_at_index` (`connection`,`queue`,`failed_at`);

--
-- Indexes for table `inventory_journal_entries`
--
ALTER TABLE `inventory_journal_entries`
  ADD PRIMARY KEY (`id`),
  ADD KEY `inventory_journal_entries_item_id_foreign` (`item_id`),
  ADD KEY `inventory_journal_entries_site_id_foreign` (`site_id`),
  ADD KEY `inventory_journal_entries_user_id_foreign` (`user_id`),
  ADD KEY `inventory_journal_entries_company_id_item_id_created_at_index` (`company_id`,`item_id`,`created_at`),
  ADD KEY `inventory_journal_entries_source_type_source_id_index` (`source_type`,`source_id`);

--
-- Indexes for table `inventory_receivings`
--
ALTER TABLE `inventory_receivings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `inventory_receivings_company_id_receipt_number_unique` (`company_id`,`receipt_number`),
  ADD KEY `inventory_receivings_purchase_order_id_foreign` (`purchase_order_id`),
  ADD KEY `inventory_receivings_supplier_id_foreign` (`supplier_id`),
  ADD KEY `inventory_receivings_buyer_id_foreign` (`buyer_id`),
  ADD KEY `inventory_receivings_site_id_foreign` (`site_id`);

--
-- Indexes for table `inventory_receiving_lines`
--
ALTER TABLE `inventory_receiving_lines`
  ADD PRIMARY KEY (`id`),
  ADD KEY `inventory_receiving_lines_inventory_receiving_id_foreign` (`inventory_receiving_id`),
  ADD KEY `inventory_receiving_lines_purchase_order_line_id_foreign` (`purchase_order_line_id`),
  ADD KEY `inventory_receiving_lines_item_id_foreign` (`item_id`);

--
-- Indexes for table `invoices`
--
ALTER TABLE `invoices`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `invoices_company_id_invoice_number_unique` (`company_id`,`invoice_number`),
  ADD KEY `invoices_sales_order_id_foreign` (`sales_order_id`),
  ADD KEY `invoices_customer_id_foreign` (`customer_id`);

--
-- Indexes for table `invoice_credits`
--
ALTER TABLE `invoice_credits`
  ADD PRIMARY KEY (`id`),
  ADD KEY `invoice_credits_invoice_id_foreign` (`invoice_id`),
  ADD KEY `invoice_credits_credit_memo_id_foreign` (`credit_memo_id`);

--
-- Indexes for table `invoice_payments`
--
ALTER TABLE `invoice_payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `invoice_payments_invoice_id_foreign` (`invoice_id`),
  ADD KEY `invoice_payments_user_id_foreign` (`user_id`);

--
-- Indexes for table `items`
--
ALTER TABLE `items`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `items_company_id_item_code_unique` (`company_id`,`item_code`),
  ADD KEY `items_department_id_foreign` (`department_id`),
  ADD KEY `items_category_id_foreign` (`category_id`),
  ADD KEY `items_subcategory_id_foreign` (`subcategory_id`),
  ADD KEY `items_uom_schedule_id_foreign` (`uom_schedule_id`),
  ADD KEY `items_company_id_is_inactive_index` (`company_id`,`is_inactive`),
  ADD KEY `items_tax_schedule_id_foreign` (`tax_schedule_id`),
  ADD KEY `items_promotion_schedule_id_foreign` (`promotion_schedule_id`),
  ADD KEY `items_pricing_method_id_foreign` (`pricing_method_id`);

--
-- Indexes for table `item_batches`
--
ALTER TABLE `item_batches`
  ADD PRIMARY KEY (`id`),
  ADD KEY `item_batches_item_id_sort_order_index` (`item_id`,`sort_order`),
  ADD KEY `item_batches_company_id_batch_number_index` (`company_id`,`batch_number`);

--
-- Indexes for table `item_prices`
--
ALTER TABLE `item_prices`
  ADD PRIMARY KEY (`id`),
  ADD KEY `item_prices_item_id_foreign` (`item_id`),
  ADD KEY `item_prices_price_level_id_foreign` (`price_level_id`);

--
-- Indexes for table `item_substitutes`
--
ALTER TABLE `item_substitutes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `item_substitutes_item_id_foreign` (`item_id`),
  ADD KEY `item_substitutes_substitute_item_id_foreign` (`substitute_item_id`);

--
-- Indexes for table `item_suppliers`
--
ALTER TABLE `item_suppliers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `item_suppliers_item_id_foreign` (`item_id`),
  ADD KEY `item_suppliers_supplier_id_foreign` (`supplier_id`);

--
-- Indexes for table `item_types`
--
ALTER TABLE `item_types`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `item_types_company_id_code_unique` (`company_id`,`code`);

--
-- Indexes for table `item_upcs`
--
ALTER TABLE `item_upcs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `item_upcs_item_id_is_primary_index` (`item_id`,`is_primary`);

--
-- Indexes for table `jobs`
--
ALTER TABLE `jobs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `jobs_queue_index` (`queue`);

--
-- Indexes for table `job_batches`
--
ALTER TABLE `job_batches`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `migrations`
--
ALTER TABLE `migrations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  ADD PRIMARY KEY (`email`);

--
-- Indexes for table `payment_terms`
--
ALTER TABLE `payment_terms`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `payment_terms_company_id_code_unique` (`company_id`,`code`);

--
-- Indexes for table `personal_access_tokens`
--
ALTER TABLE `personal_access_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  ADD KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`),
  ADD KEY `personal_access_tokens_expires_at_index` (`expires_at`);

--
-- Indexes for table `price_levels`
--
ALTER TABLE `price_levels`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `price_levels_company_id_code_unique` (`company_id`,`code`);

--
-- Indexes for table `pricing_methods`
--
ALTER TABLE `pricing_methods`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `pricing_methods_company_id_code_unique` (`company_id`,`code`);

--
-- Indexes for table `purchase_limit_schedules`
--
ALTER TABLE `purchase_limit_schedules`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `purchase_limit_schedules_company_id_code_unique` (`company_id`,`code`);

--
-- Indexes for table `purchase_orders`
--
ALTER TABLE `purchase_orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `purchase_orders_company_id_po_number_unique` (`company_id`,`po_number`),
  ADD KEY `purchase_orders_buyer_id_foreign` (`buyer_id`),
  ADD KEY `purchase_orders_ship_to_site_id_foreign` (`ship_to_site_id`),
  ADD KEY `purchase_orders_supplier_id_foreign` (`supplier_id`),
  ADD KEY `purchase_orders_payment_term_id_foreign` (`payment_term_id`),
  ADD KEY `purchase_orders_ship_via_id_foreign` (`ship_via_id`),
  ADD KEY `purchase_orders_company_id_status_index` (`company_id`,`status`);

--
-- Indexes for table `purchase_order_lines`
--
ALTER TABLE `purchase_order_lines`
  ADD PRIMARY KEY (`id`),
  ADD KEY `purchase_order_lines_purchase_order_id_foreign` (`purchase_order_id`),
  ADD KEY `purchase_order_lines_item_id_foreign` (`item_id`);

--
-- Indexes for table `return_to_vendors`
--
ALTER TABLE `return_to_vendors`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `return_to_vendors_company_id_rtv_number_unique` (`company_id`,`rtv_number`),
  ADD KEY `return_to_vendors_supplier_id_foreign` (`supplier_id`),
  ADD KEY `return_to_vendors_requested_by_id_foreign` (`requested_by_id`),
  ADD KEY `return_to_vendors_site_id_foreign` (`site_id`);

--
-- Indexes for table `return_to_vendor_lines`
--
ALTER TABLE `return_to_vendor_lines`
  ADD PRIMARY KEY (`id`),
  ADD KEY `return_to_vendor_lines_return_to_vendor_id_foreign` (`return_to_vendor_id`),
  ADD KEY `return_to_vendor_lines_item_id_foreign` (`item_id`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `roles_name_unique` (`name`);

--
-- Indexes for table `sales_orders`
--
ALTER TABLE `sales_orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `sales_orders_company_id_order_number_unique` (`company_id`,`order_number`),
  ADD KEY `sales_orders_customer_id_foreign` (`customer_id`),
  ADD KEY `sales_orders_ship_to_address_id_foreign` (`ship_to_address_id`),
  ADD KEY `sales_orders_sales_rep_id_foreign` (`sales_rep_id`),
  ADD KEY `sales_orders_payment_term_id_foreign` (`payment_term_id`),
  ADD KEY `sales_orders_route_id_foreign` (`route_id`),
  ADD KEY `sales_orders_ship_via_id_foreign` (`ship_via_id`),
  ADD KEY `sales_orders_ship_from_site_id_foreign` (`ship_from_site_id`),
  ADD KEY `sales_orders_created_by_foreign` (`created_by`),
  ADD KEY `sales_orders_company_id_status_index` (`company_id`,`status`);

--
-- Indexes for table `sales_order_boxes`
--
ALTER TABLE `sales_order_boxes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sales_order_boxes_sales_order_id_foreign` (`sales_order_id`);

--
-- Indexes for table `sales_order_lines`
--
ALTER TABLE `sales_order_lines`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sales_order_lines_sales_order_id_foreign` (`sales_order_id`),
  ADD KEY `sales_order_lines_item_id_foreign` (`item_id`);

--
-- Indexes for table `sessions`
--
ALTER TABLE `sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sessions_user_id_index` (`user_id`),
  ADD KEY `sessions_last_activity_index` (`last_activity`);

--
-- Indexes for table `ship_vias`
--
ALTER TABLE `ship_vias`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ship_vias_company_id_code_unique` (`company_id`,`code`);

--
-- Indexes for table `sites`
--
ALTER TABLE `sites`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `sites_company_id_code_unique` (`company_id`,`code`);

--
-- Indexes for table `stock_counts`
--
ALTER TABLE `stock_counts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `stock_counts_company_id_stock_count_no_unique` (`company_id`,`stock_count_no`),
  ADD KEY `stock_counts_site_id_foreign` (`site_id`),
  ADD KEY `stock_counts_processed_by_foreign` (`processed_by`);

--
-- Indexes for table `stock_count_lines`
--
ALTER TABLE `stock_count_lines`
  ADD PRIMARY KEY (`id`),
  ADD KEY `stock_count_lines_stock_count_id_foreign` (`stock_count_id`),
  ADD KEY `stock_count_lines_item_id_foreign` (`item_id`);

--
-- Indexes for table `subcategories`
--
ALTER TABLE `subcategories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `subcategories_company_id_code_unique` (`company_id`,`code`),
  ADD KEY `subcategories_category_id_foreign` (`category_id`);

--
-- Indexes for table `suppliers`
--
ALTER TABLE `suppliers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `suppliers_company_id_supplier_id_unique` (`company_id`,`supplier_id`);

--
-- Indexes for table `supplier_contacts`
--
ALTER TABLE `supplier_contacts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `supplier_contacts_supplier_id_foreign` (`supplier_id`);

--
-- Indexes for table `tax_schedules`
--
ALTER TABLE `tax_schedules`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `tax_schedules_company_id_code_unique` (`company_id`,`code`);

--
-- Indexes for table `tobacco_stamp_inventories`
--
ALTER TABLE `tobacco_stamp_inventories`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tobacco_stamp_inventories_company_id_foreign` (`company_id`),
  ADD KEY `tobacco_stamp_inventories_created_by_foreign` (`created_by`);

--
-- Indexes for table `uom_schedules`
--
ALTER TABLE `uom_schedules`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uom_schedules_company_id_code_unique` (`company_id`,`code`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `users_email_unique` (`email`),
  ADD UNIQUE KEY `users_company_id_username_unique` (`company_id`,`username`),
  ADD KEY `users_site_id_foreign` (`site_id`),
  ADD KEY `users_role_id_foreign` (`role_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `bulk_price_change_items`
--
ALTER TABLE `bulk_price_change_items`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `bulk_price_change_logs`
--
ALTER TABLE `bulk_price_change_logs`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `cigarette_tax_classes`
--
ALTER TABLE `cigarette_tax_classes`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `companies`
--
ALTER TABLE `companies`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `credit_memos`
--
ALTER TABLE `credit_memos`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `credit_memo_lines`
--
ALTER TABLE `credit_memo_lines`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `customer_lookup_options`
--
ALTER TABLE `customer_lookup_options`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `customer_shipping_addresses`
--
ALTER TABLE `customer_shipping_addresses`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `delivery_routes`
--
ALTER TABLE `delivery_routes`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `departments`
--
ALTER TABLE `departments`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `discount_schedules`
--
ALTER TABLE `discount_schedules`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `document_email_logs`
--
ALTER TABLE `document_email_logs`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `failed_jobs`
--
ALTER TABLE `failed_jobs`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `inventory_journal_entries`
--
ALTER TABLE `inventory_journal_entries`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `inventory_receivings`
--
ALTER TABLE `inventory_receivings`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `inventory_receiving_lines`
--
ALTER TABLE `inventory_receiving_lines`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `invoices`
--
ALTER TABLE `invoices`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `invoice_credits`
--
ALTER TABLE `invoice_credits`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `invoice_payments`
--
ALTER TABLE `invoice_payments`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `items`
--
ALTER TABLE `items`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `item_batches`
--
ALTER TABLE `item_batches`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `item_prices`
--
ALTER TABLE `item_prices`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT for table `item_substitutes`
--
ALTER TABLE `item_substitutes`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `item_suppliers`
--
ALTER TABLE `item_suppliers`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `item_types`
--
ALTER TABLE `item_types`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `item_upcs`
--
ALTER TABLE `item_upcs`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `jobs`
--
ALTER TABLE `jobs`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `migrations`
--
ALTER TABLE `migrations`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=45;

--
-- AUTO_INCREMENT for table `payment_terms`
--
ALTER TABLE `payment_terms`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `personal_access_tokens`
--
ALTER TABLE `personal_access_tokens`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `price_levels`
--
ALTER TABLE `price_levels`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `pricing_methods`
--
ALTER TABLE `pricing_methods`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `purchase_limit_schedules`
--
ALTER TABLE `purchase_limit_schedules`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `purchase_orders`
--
ALTER TABLE `purchase_orders`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `purchase_order_lines`
--
ALTER TABLE `purchase_order_lines`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `return_to_vendors`
--
ALTER TABLE `return_to_vendors`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `return_to_vendor_lines`
--
ALTER TABLE `return_to_vendor_lines`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `sales_orders`
--
ALTER TABLE `sales_orders`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `sales_order_boxes`
--
ALTER TABLE `sales_order_boxes`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `sales_order_lines`
--
ALTER TABLE `sales_order_lines`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `ship_vias`
--
ALTER TABLE `ship_vias`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `sites`
--
ALTER TABLE `sites`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `stock_counts`
--
ALTER TABLE `stock_counts`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `stock_count_lines`
--
ALTER TABLE `stock_count_lines`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `subcategories`
--
ALTER TABLE `subcategories`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `suppliers`
--
ALTER TABLE `suppliers`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `supplier_contacts`
--
ALTER TABLE `supplier_contacts`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tax_schedules`
--
ALTER TABLE `tax_schedules`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `tobacco_stamp_inventories`
--
ALTER TABLE `tobacco_stamp_inventories`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `uom_schedules`
--
ALTER TABLE `uom_schedules`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `bulk_price_change_items`
--
ALTER TABLE `bulk_price_change_items`
  ADD CONSTRAINT `bulk_price_change_items_bulk_price_change_log_id_foreign` FOREIGN KEY (`bulk_price_change_log_id`) REFERENCES `bulk_price_change_logs` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `bulk_price_change_items_item_id_foreign` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `bulk_price_change_logs`
--
ALTER TABLE `bulk_price_change_logs`
  ADD CONSTRAINT `bulk_price_change_logs_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `bulk_price_change_logs_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `categories`
--
ALTER TABLE `categories`
  ADD CONSTRAINT `categories_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `categories_department_id_foreign` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `cigarette_tax_classes`
--
ALTER TABLE `cigarette_tax_classes`
  ADD CONSTRAINT `cigarette_tax_classes_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `credit_memos`
--
ALTER TABLE `credit_memos`
  ADD CONSTRAINT `credit_memos_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `credit_memos_customer_id_foreign` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `credit_memos_sales_order_id_foreign` FOREIGN KEY (`sales_order_id`) REFERENCES `sales_orders` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `credit_memo_lines`
--
ALTER TABLE `credit_memo_lines`
  ADD CONSTRAINT `credit_memo_lines_credit_memo_id_foreign` FOREIGN KEY (`credit_memo_id`) REFERENCES `credit_memos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `credit_memo_lines_item_id_foreign` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `customers`
--
ALTER TABLE `customers`
  ADD CONSTRAINT `customers_cigarette_tax_class_id_foreign` FOREIGN KEY (`cigarette_tax_class_id`) REFERENCES `cigarette_tax_classes` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `customers_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `customers_delivery_route_id_foreign` FOREIGN KEY (`delivery_route_id`) REFERENCES `delivery_routes` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `customers_discount_schedule_id_foreign` FOREIGN KEY (`discount_schedule_id`) REFERENCES `discount_schedules` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `customers_payment_term_id_foreign` FOREIGN KEY (`payment_term_id`) REFERENCES `payment_terms` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `customers_price_level_id_foreign` FOREIGN KEY (`price_level_id`) REFERENCES `price_levels` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `customers_purchase_limit_schedule_id_foreign` FOREIGN KEY (`purchase_limit_schedule_id`) REFERENCES `purchase_limit_schedules` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `customers_sales_rep_id_foreign` FOREIGN KEY (`sales_rep_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `customer_lookup_options`
--
ALTER TABLE `customer_lookup_options`
  ADD CONSTRAINT `customer_lookup_options_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `customer_shipping_addresses`
--
ALTER TABLE `customer_shipping_addresses`
  ADD CONSTRAINT `customer_shipping_addresses_customer_id_foreign` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `delivery_routes`
--
ALTER TABLE `delivery_routes`
  ADD CONSTRAINT `delivery_routes_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `departments`
--
ALTER TABLE `departments`
  ADD CONSTRAINT `departments_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `discount_schedules`
--
ALTER TABLE `discount_schedules`
  ADD CONSTRAINT `discount_schedules_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `document_email_logs`
--
ALTER TABLE `document_email_logs`
  ADD CONSTRAINT `document_email_logs_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `document_email_logs_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `inventory_journal_entries`
--
ALTER TABLE `inventory_journal_entries`
  ADD CONSTRAINT `inventory_journal_entries_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `inventory_journal_entries_item_id_foreign` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `inventory_journal_entries_site_id_foreign` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `inventory_journal_entries_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `inventory_receivings`
--
ALTER TABLE `inventory_receivings`
  ADD CONSTRAINT `inventory_receivings_buyer_id_foreign` FOREIGN KEY (`buyer_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `inventory_receivings_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `inventory_receivings_purchase_order_id_foreign` FOREIGN KEY (`purchase_order_id`) REFERENCES `purchase_orders` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `inventory_receivings_site_id_foreign` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `inventory_receivings_supplier_id_foreign` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `inventory_receiving_lines`
--
ALTER TABLE `inventory_receiving_lines`
  ADD CONSTRAINT `inventory_receiving_lines_inventory_receiving_id_foreign` FOREIGN KEY (`inventory_receiving_id`) REFERENCES `inventory_receivings` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `inventory_receiving_lines_item_id_foreign` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `inventory_receiving_lines_purchase_order_line_id_foreign` FOREIGN KEY (`purchase_order_line_id`) REFERENCES `purchase_order_lines` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `invoices`
--
ALTER TABLE `invoices`
  ADD CONSTRAINT `invoices_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `invoices_customer_id_foreign` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `invoices_sales_order_id_foreign` FOREIGN KEY (`sales_order_id`) REFERENCES `sales_orders` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `invoice_credits`
--
ALTER TABLE `invoice_credits`
  ADD CONSTRAINT `invoice_credits_credit_memo_id_foreign` FOREIGN KEY (`credit_memo_id`) REFERENCES `credit_memos` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `invoice_credits_invoice_id_foreign` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `invoice_payments`
--
ALTER TABLE `invoice_payments`
  ADD CONSTRAINT `invoice_payments_invoice_id_foreign` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `invoice_payments_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `items`
--
ALTER TABLE `items`
  ADD CONSTRAINT `items_category_id_foreign` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `items_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `items_department_id_foreign` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `items_pricing_method_id_foreign` FOREIGN KEY (`pricing_method_id`) REFERENCES `pricing_methods` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `items_promotion_schedule_id_foreign` FOREIGN KEY (`promotion_schedule_id`) REFERENCES `discount_schedules` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `items_subcategory_id_foreign` FOREIGN KEY (`subcategory_id`) REFERENCES `subcategories` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `items_tax_schedule_id_foreign` FOREIGN KEY (`tax_schedule_id`) REFERENCES `tax_schedules` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `items_uom_schedule_id_foreign` FOREIGN KEY (`uom_schedule_id`) REFERENCES `uom_schedules` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `item_batches`
--
ALTER TABLE `item_batches`
  ADD CONSTRAINT `item_batches_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `item_batches_item_id_foreign` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `item_prices`
--
ALTER TABLE `item_prices`
  ADD CONSTRAINT `item_prices_item_id_foreign` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `item_prices_price_level_id_foreign` FOREIGN KEY (`price_level_id`) REFERENCES `price_levels` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `item_substitutes`
--
ALTER TABLE `item_substitutes`
  ADD CONSTRAINT `item_substitutes_item_id_foreign` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `item_substitutes_substitute_item_id_foreign` FOREIGN KEY (`substitute_item_id`) REFERENCES `items` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `item_suppliers`
--
ALTER TABLE `item_suppliers`
  ADD CONSTRAINT `item_suppliers_item_id_foreign` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `item_suppliers_supplier_id_foreign` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `item_types`
--
ALTER TABLE `item_types`
  ADD CONSTRAINT `item_types_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `item_upcs`
--
ALTER TABLE `item_upcs`
  ADD CONSTRAINT `item_upcs_item_id_foreign` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `payment_terms`
--
ALTER TABLE `payment_terms`
  ADD CONSTRAINT `payment_terms_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `price_levels`
--
ALTER TABLE `price_levels`
  ADD CONSTRAINT `price_levels_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `pricing_methods`
--
ALTER TABLE `pricing_methods`
  ADD CONSTRAINT `pricing_methods_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `purchase_limit_schedules`
--
ALTER TABLE `purchase_limit_schedules`
  ADD CONSTRAINT `purchase_limit_schedules_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `purchase_orders`
--
ALTER TABLE `purchase_orders`
  ADD CONSTRAINT `purchase_orders_buyer_id_foreign` FOREIGN KEY (`buyer_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `purchase_orders_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `purchase_orders_payment_term_id_foreign` FOREIGN KEY (`payment_term_id`) REFERENCES `payment_terms` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `purchase_orders_ship_to_site_id_foreign` FOREIGN KEY (`ship_to_site_id`) REFERENCES `sites` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `purchase_orders_ship_via_id_foreign` FOREIGN KEY (`ship_via_id`) REFERENCES `ship_vias` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `purchase_orders_supplier_id_foreign` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `purchase_order_lines`
--
ALTER TABLE `purchase_order_lines`
  ADD CONSTRAINT `purchase_order_lines_item_id_foreign` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `purchase_order_lines_purchase_order_id_foreign` FOREIGN KEY (`purchase_order_id`) REFERENCES `purchase_orders` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `return_to_vendors`
--
ALTER TABLE `return_to_vendors`
  ADD CONSTRAINT `return_to_vendors_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `return_to_vendors_requested_by_id_foreign` FOREIGN KEY (`requested_by_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `return_to_vendors_site_id_foreign` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `return_to_vendors_supplier_id_foreign` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `return_to_vendor_lines`
--
ALTER TABLE `return_to_vendor_lines`
  ADD CONSTRAINT `return_to_vendor_lines_item_id_foreign` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `return_to_vendor_lines_return_to_vendor_id_foreign` FOREIGN KEY (`return_to_vendor_id`) REFERENCES `return_to_vendors` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `sales_orders`
--
ALTER TABLE `sales_orders`
  ADD CONSTRAINT `sales_orders_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `sales_orders_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `sales_orders_customer_id_foreign` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `sales_orders_payment_term_id_foreign` FOREIGN KEY (`payment_term_id`) REFERENCES `payment_terms` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `sales_orders_route_id_foreign` FOREIGN KEY (`route_id`) REFERENCES `delivery_routes` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `sales_orders_sales_rep_id_foreign` FOREIGN KEY (`sales_rep_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `sales_orders_ship_from_site_id_foreign` FOREIGN KEY (`ship_from_site_id`) REFERENCES `sites` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `sales_orders_ship_to_address_id_foreign` FOREIGN KEY (`ship_to_address_id`) REFERENCES `customer_shipping_addresses` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `sales_orders_ship_via_id_foreign` FOREIGN KEY (`ship_via_id`) REFERENCES `ship_vias` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `sales_order_boxes`
--
ALTER TABLE `sales_order_boxes`
  ADD CONSTRAINT `sales_order_boxes_sales_order_id_foreign` FOREIGN KEY (`sales_order_id`) REFERENCES `sales_orders` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `sales_order_lines`
--
ALTER TABLE `sales_order_lines`
  ADD CONSTRAINT `sales_order_lines_item_id_foreign` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `sales_order_lines_sales_order_id_foreign` FOREIGN KEY (`sales_order_id`) REFERENCES `sales_orders` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `ship_vias`
--
ALTER TABLE `ship_vias`
  ADD CONSTRAINT `ship_vias_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `sites`
--
ALTER TABLE `sites`
  ADD CONSTRAINT `sites_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `stock_counts`
--
ALTER TABLE `stock_counts`
  ADD CONSTRAINT `stock_counts_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `stock_counts_processed_by_foreign` FOREIGN KEY (`processed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `stock_counts_site_id_foreign` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `stock_count_lines`
--
ALTER TABLE `stock_count_lines`
  ADD CONSTRAINT `stock_count_lines_item_id_foreign` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `stock_count_lines_stock_count_id_foreign` FOREIGN KEY (`stock_count_id`) REFERENCES `stock_counts` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `subcategories`
--
ALTER TABLE `subcategories`
  ADD CONSTRAINT `subcategories_category_id_foreign` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `subcategories_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `suppliers`
--
ALTER TABLE `suppliers`
  ADD CONSTRAINT `suppliers_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `supplier_contacts`
--
ALTER TABLE `supplier_contacts`
  ADD CONSTRAINT `supplier_contacts_supplier_id_foreign` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `tax_schedules`
--
ALTER TABLE `tax_schedules`
  ADD CONSTRAINT `tax_schedules_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `tobacco_stamp_inventories`
--
ALTER TABLE `tobacco_stamp_inventories`
  ADD CONSTRAINT `tobacco_stamp_inventories_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `tobacco_stamp_inventories_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `uom_schedules`
--
ALTER TABLE `uom_schedules`
  ADD CONSTRAINT `uom_schedules_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `users_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `users_site_id_foreign` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
