/*
 Navicat Premium Data Transfer

 Source Server         : local
 Source Server Type    : MySQL
 Source Server Version : 100421 (10.4.21-MariaDB)
 Source Host           : localhost:3306
 Source Schema         : datnan

 Target Server Type    : MySQL
 Target Server Version : 100421 (10.4.21-MariaDB)
 File Encoding         : 65001

 Date: 05/09/2024 22:42:00
*/

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------
-- Table structure for cache
-- ----------------------------
DROP TABLE IF EXISTS `cache`;
CREATE TABLE `cache` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` int(11) NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Records of cache
-- ----------------------------
BEGIN;
COMMIT;

-- ----------------------------
-- Table structure for cache_locks
-- ----------------------------
DROP TABLE IF EXISTS `cache_locks`;
CREATE TABLE `cache_locks` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` int(11) NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Records of cache_locks
-- ----------------------------
BEGIN;
COMMIT;

-- ----------------------------
-- Table structure for categories
-- ----------------------------
DROP TABLE IF EXISTS `categories`;
CREATE TABLE `categories` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('active','inactive') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Records of categories
-- ----------------------------
BEGIN;
INSERT INTO `categories` (`id`, `name`, `description`, `status`, `deleted_at`, `created_at`, `updated_at`) VALUES (1, 'Category 1', 'Description 1', 'active', NULL, NULL, NULL);
INSERT INTO `categories` (`id`, `name`, `description`, `status`, `deleted_at`, `created_at`, `updated_at`) VALUES (3, 'Category 3', 'Description 8', 'active', NULL, NULL, '2024-07-17 09:01:50');
INSERT INTO `categories` (`id`, `name`, `description`, `status`, `deleted_at`, `created_at`, `updated_at`) VALUES (4, 'Category 4', 'Description 4', 'inactive', NULL, NULL, '2024-07-17 09:02:05');
INSERT INTO `categories` (`id`, `name`, `description`, `status`, `deleted_at`, `created_at`, `updated_at`) VALUES (5, 'Category 5', 'Description 5', 'inactive', NULL, NULL, NULL);
COMMIT;

-- ----------------------------
-- Table structure for failed_jobs
-- ----------------------------
DROP TABLE IF EXISTS `failed_jobs`;
CREATE TABLE `failed_jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Records of failed_jobs
-- ----------------------------
BEGIN;
COMMIT;

-- ----------------------------
-- Table structure for invoice_details
-- ----------------------------
DROP TABLE IF EXISTS `invoice_details`;
CREATE TABLE `invoice_details` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `invoice_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `order_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `product_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `product_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `product_price` decimal(15,2) NOT NULL,
  `product_quantity` int(11) NOT NULL,
  `product_discount` decimal(15,2) NOT NULL,
  `product_total` decimal(15,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Records of invoice_details
-- ----------------------------
BEGIN;
INSERT INTO `invoice_details` (`id`, `invoice_id`, `order_id`, `product_id`, `product_name`, `product_price`, `product_quantity`, `product_discount`, `product_total`, `created_at`, `updated_at`, `deleted_at`) VALUES ('4a17a781-4e86-48ca-a5a0-a5b2aef3548d', 'aa812e06-ece3-463b-931f-df5e437e5de0', '1', '1', 'Product 1', 1000000.00, 1, 0.00, 1000000.00, '2024-07-23 06:49:28', '2024-07-23 06:49:28', NULL);
COMMIT;

-- ----------------------------
-- Table structure for invoices
-- ----------------------------
DROP TABLE IF EXISTS `invoices`;
CREATE TABLE `invoices` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `number` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `total_price` decimal(15,2) NOT NULL,
  `issue_date` date NOT NULL,
  `due_date` date NOT NULL,
  `status` enum('unpaid','paid') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'unpaid',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `invoices_number_unique` (`number`),
  KEY `invoices_user_id_foreign` (`user_id`),
  CONSTRAINT `invoices_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Records of invoices
-- ----------------------------
BEGIN;
INSERT INTO `invoices` (`id`, `number`, `user_id`, `total_price`, `issue_date`, `due_date`, `status`, `created_at`, `updated_at`, `deleted_at`) VALUES ('aa812e06-ece3-463b-931f-df5e437e5de0', 'INV-2024-0001', 1, 1300000.00, '2024-07-22', '2024-07-29', 'unpaid', '2024-07-23 06:49:28', '2024-07-23 06:49:28', NULL);
COMMIT;

-- ----------------------------
-- Table structure for migrations
-- ----------------------------
DROP TABLE IF EXISTS `migrations`;
CREATE TABLE `migrations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=76 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Records of migrations
-- ----------------------------
BEGIN;
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1, '2014_10_12_100000_create_password_resets_table', 1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (2, '2019_08_19_000000_create_failed_jobs_table', 1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (3, '2019_12_14_000001_create_personal_access_tokens_table', 1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (18, '2022_04_28_211034_create_reservation_table', 2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (20, '2022_04_28_211035_create_service_table', 3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (21, '2024_06_15_051445_create_sessions_table', 4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (23, '2024_06_15_055612_create_expire_at_personal_access_token_table', 5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (26, '2024_07_02_031906_create_personal_access_tokens_table', 7);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (27, '2024_07_03_024459_change_name_column_users_table', 8);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (28, '2024_07_02_031403_create_user_table', 9);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (29, '2024_07_15_034425_create_cache_table', 10);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (30, '2024_07_16_034332_create_permission_tables', 10);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (31, '2024_07_17_071404_create_categories_table', 11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (32, '2024_07_17_072405_create_products_table', 12);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (34, '2024_07_17_100048_delete_category_id_in_product_table', 14);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (37, '2024_07_17_151448_create_column_price_in_product_by_days_table', 16);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (38, '2024_07_18_135706_remove_stock_column', 17);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (43, '2024_07_17_072804_create_product_by_days_table', 20);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (64, '2024_07_19_073449_create_orders_table', 27);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (65, '2024_07_22_075552_create_invoices_table', 27);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (67, '2024_07_19_075312_create_order_details_table', 28);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (69, '2024_07_22_033046_change_enum_of_payment_method_in_order_table', 29);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (70, '2024_07_22_033307_change_enum_of_payment_status_in_order_table', 29);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (71, '2024_07_22_043142_create_soft_deleted_in_order_table', 29);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (72, '2024_07_21_123610_create_type_column_in_order_table', 30);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (74, '2024_07_22_131602_create_invoice_details_table', 31);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (75, '2024_07_24_023914_create_column_regarding_bank_into_user_table', 32);
COMMIT;

-- ----------------------------
-- Table structure for model_has_permissions
-- ----------------------------
DROP TABLE IF EXISTS `model_has_permissions`;
CREATE TABLE `model_has_permissions` (
  `permission_id` bigint(20) unsigned NOT NULL,
  `model_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `model_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`permission_id`,`model_id`,`model_type`),
  KEY `model_has_permissions_model_id_model_type_index` (`model_id`,`model_type`),
  CONSTRAINT `model_has_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Records of model_has_permissions
-- ----------------------------
BEGIN;
COMMIT;

-- ----------------------------
-- Table structure for model_has_roles
-- ----------------------------
DROP TABLE IF EXISTS `model_has_roles`;
CREATE TABLE `model_has_roles` (
  `role_id` bigint(20) unsigned NOT NULL,
  `model_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `model_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`role_id`,`model_id`,`model_type`),
  KEY `model_has_roles_model_id_model_type_index` (`model_id`,`model_type`),
  CONSTRAINT `model_has_roles_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Records of model_has_roles
-- ----------------------------
BEGIN;
COMMIT;

-- ----------------------------
-- Table structure for order_details
-- ----------------------------
DROP TABLE IF EXISTS `order_details`;
CREATE TABLE `order_details` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `order_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `product_by_day_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `quantity` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `total_price` decimal(10,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Records of order_details
-- ----------------------------
BEGIN;
INSERT INTO `order_details` (`id`, `order_id`, `product_by_day_id`, `quantity`, `price`, `total_price`, `created_at`, `updated_at`, `deleted_at`) VALUES ('aa6e8c70-085b-4e84-ae0b-fc84e738def6', '8a5f4e5b-3f28-4127-8c80-86278dfd2590', '1', 2, 200000.00, 400000.00, NULL, NULL, NULL);
COMMIT;

-- ----------------------------
-- Table structure for orders
-- ----------------------------
DROP TABLE IF EXISTS `orders`;
CREATE TABLE `orders` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `order_number` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `order_date` date NOT NULL,
  `total_price` decimal(10,2) DEFAULT NULL,
  `payment_status` enum('unpaid','paid') COLLATE utf8mb4_unicode_ci NOT NULL,
  `payment_method` enum('cash','transfer') COLLATE utf8mb4_unicode_ci NOT NULL,
  `payment_date` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `type` enum('import','export') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'import',
  PRIMARY KEY (`id`),
  KEY `orders_user_id_foreign` (`user_id`),
  CONSTRAINT `orders_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Records of orders
-- ----------------------------
BEGIN;
INSERT INTO `orders` (`id`, `user_id`, `order_number`, `order_date`, `total_price`, `payment_status`, `payment_method`, `payment_date`, `created_at`, `updated_at`, `deleted_at`, `type`) VALUES ('8a5f4e5b-3f28-4127-8c80-86278dfd2590', 5, 'ORD669E63CFBA1A3', '2024-07-22', 400000.00, 'paid', 'cash', '2024-07-22', '2024-07-22 13:51:11', '2024-07-22 13:51:11', NULL, 'export');
COMMIT;

-- ----------------------------
-- Table structure for password_resets
-- ----------------------------
DROP TABLE IF EXISTS `password_resets`;
CREATE TABLE `password_resets` (
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  KEY `password_resets_email_index` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Records of password_resets
-- ----------------------------
BEGIN;
COMMIT;

-- ----------------------------
-- Table structure for permissions
-- ----------------------------
DROP TABLE IF EXISTS `permissions`;
CREATE TABLE `permissions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `guard_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `permissions_name_guard_name_unique` (`name`,`guard_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Records of permissions
-- ----------------------------
BEGIN;
COMMIT;

-- ----------------------------
-- Table structure for personal_access_tokens
-- ----------------------------
DROP TABLE IF EXISTS `personal_access_tokens`;
CREATE TABLE `personal_access_tokens` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tokenable_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tokenable_id` bigint(20) unsigned NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `abilities` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`)
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Records of personal_access_tokens
-- ----------------------------
BEGIN;
INSERT INTO `personal_access_tokens` (`id`, `tokenable_type`, `tokenable_id`, `name`, `token`, `abilities`, `last_used_at`, `expires_at`, `created_at`, `updated_at`) VALUES (1, 'App\\Models\\User', 51, 'Personal Access Token', 'a8948460830da12fb95fed01ef2c059f677555f6154f981113ed5f5f5127ede8', '[\"*\"]', NULL, NULL, '2024-07-16 03:12:48', '2024-07-16 03:12:48');
INSERT INTO `personal_access_tokens` (`id`, `tokenable_type`, `tokenable_id`, `name`, `token`, `abilities`, `last_used_at`, `expires_at`, `created_at`, `updated_at`) VALUES (2, 'App\\Models\\User', 51, 'Personal Access Token', '54acb30b309484552e685794a71f8e62cd4474d9d93a110d3cfc6dfc2131c835', '[\"*\"]', NULL, NULL, '2024-07-16 03:28:47', '2024-07-16 03:28:47');
INSERT INTO `personal_access_tokens` (`id`, `tokenable_type`, `tokenable_id`, `name`, `token`, `abilities`, `last_used_at`, `expires_at`, `created_at`, `updated_at`) VALUES (3, 'App\\Models\\User', 51, 'Personal Access Token', '6a4d499a9908cb13c039b79d8bb65028e1b077781f5fc3eb59b559f742b34e59', '[\"*\"]', NULL, NULL, '2024-07-16 03:31:24', '2024-07-16 03:31:24');
INSERT INTO `personal_access_tokens` (`id`, `tokenable_type`, `tokenable_id`, `name`, `token`, `abilities`, `last_used_at`, `expires_at`, `created_at`, `updated_at`) VALUES (4, 'App\\Models\\User', 51, 'Personal Access Token', '2c71d54fd1b9219dc12ea0f64e501c355985d6f67427a7554205f90667aaf4d0', '[\"*\"]', NULL, NULL, '2024-07-16 03:35:20', '2024-07-16 03:35:20');
INSERT INTO `personal_access_tokens` (`id`, `tokenable_type`, `tokenable_id`, `name`, `token`, `abilities`, `last_used_at`, `expires_at`, `created_at`, `updated_at`) VALUES (5, 'App\\Models\\User', 51, 'Personal Access Token', '7ed0d8c64032c1f576cd0a492882352bdf548fdff7153c4bfb2307a8c225bfce', '[\"*\"]', NULL, NULL, '2024-07-16 03:36:39', '2024-07-16 03:36:39');
INSERT INTO `personal_access_tokens` (`id`, `tokenable_type`, `tokenable_id`, `name`, `token`, `abilities`, `last_used_at`, `expires_at`, `created_at`, `updated_at`) VALUES (6, 'App\\Models\\User', 51, 'Personal Access Token', '668ef9cea43264f072fed3e480cbbd90564e1e1bb843364e03ad1ea3e362eb51', '[\"*\"]', NULL, NULL, '2024-07-16 03:37:42', '2024-07-16 03:37:42');
INSERT INTO `personal_access_tokens` (`id`, `tokenable_type`, `tokenable_id`, `name`, `token`, `abilities`, `last_used_at`, `expires_at`, `created_at`, `updated_at`) VALUES (7, 'App\\Models\\User', 51, 'Personal Access Token', '961ca9c836edb45fafab76550c08a4063fa013854fd4603a299a25610a35ee62', '[\"*\"]', NULL, NULL, '2024-07-16 03:38:09', '2024-07-16 03:38:09');
INSERT INTO `personal_access_tokens` (`id`, `tokenable_type`, `tokenable_id`, `name`, `token`, `abilities`, `last_used_at`, `expires_at`, `created_at`, `updated_at`) VALUES (8, 'App\\Models\\User', 51, 'Personal Access Token', '6f5f57768ab2f0465b789a2004c201721a5b11491d484ce65b412a05703d0c59', '[\"*\"]', '2024-07-17 01:34:04', NULL, '2024-07-17 01:33:51', '2024-07-17 01:34:04');
INSERT INTO `personal_access_tokens` (`id`, `tokenable_type`, `tokenable_id`, `name`, `token`, `abilities`, `last_used_at`, `expires_at`, `created_at`, `updated_at`) VALUES (9, 'App\\Models\\User', 51, 'Personal Access Token', 'fb8d5de42d45e179caf07bcd49e9cccf74eaa7e0d4d412d79222a5eb417011f8', '[\"*\"]', NULL, NULL, '2024-07-17 01:37:12', '2024-07-17 01:37:12');
INSERT INTO `personal_access_tokens` (`id`, `tokenable_type`, `tokenable_id`, `name`, `token`, `abilities`, `last_used_at`, `expires_at`, `created_at`, `updated_at`) VALUES (10, 'App\\Models\\User', 51, 'Personal Access Token', '8b4deb656b6d40dd645da0a11c13e33345893e4307b6d7ff6a3fbf2d33075891', '[\"*\"]', '2024-07-17 02:05:22', NULL, '2024-07-17 01:37:52', '2024-07-17 02:05:22');
INSERT INTO `personal_access_tokens` (`id`, `tokenable_type`, `tokenable_id`, `name`, `token`, `abilities`, `last_used_at`, `expires_at`, `created_at`, `updated_at`) VALUES (11, 'App\\Models\\User', 51, 'Personal Access Token', '77973e4496456b4795c720b31158db5b378f4a705b9bff6d4a32bb55a2516fbc', '[\"*\"]', '2024-07-20 01:24:45', NULL, '2024-07-17 02:15:40', '2024-07-20 01:24:45');
INSERT INTO `personal_access_tokens` (`id`, `tokenable_type`, `tokenable_id`, `name`, `token`, `abilities`, `last_used_at`, `expires_at`, `created_at`, `updated_at`) VALUES (12, 'App\\Models\\User', 51, 'Personal Access Token', '209194f043e57ed6d7535b4f13a8cbcf7816119d5e6eea4614333080a8cc7bb5', '[\"*\"]', '2024-07-20 01:46:42', NULL, '2024-07-20 01:29:22', '2024-07-20 01:46:42');
INSERT INTO `personal_access_tokens` (`id`, `tokenable_type`, `tokenable_id`, `name`, `token`, `abilities`, `last_used_at`, `expires_at`, `created_at`, `updated_at`) VALUES (13, 'App\\Models\\User', 51, 'Personal Access Token', 'd9e06cc9f46e937c7e5b231c1a463347196ff9d626ea05b9da5f46cba6d78894', '[\"*\"]', '2024-07-24 02:33:22', NULL, '2024-07-20 02:09:37', '2024-07-24 02:33:22');
INSERT INTO `personal_access_tokens` (`id`, `tokenable_type`, `tokenable_id`, `name`, `token`, `abilities`, `last_used_at`, `expires_at`, `created_at`, `updated_at`) VALUES (14, 'App\\Models\\User', 51, 'Personal Access Token', '93301984e4690022f1819de4422036e4e23e3db230d30b2a15165143443cf3af', '[\"*\"]', NULL, NULL, '2024-07-24 02:44:26', '2024-07-24 02:44:26');
INSERT INTO `personal_access_tokens` (`id`, `tokenable_type`, `tokenable_id`, `name`, `token`, `abilities`, `last_used_at`, `expires_at`, `created_at`, `updated_at`) VALUES (15, 'App\\Models\\User', 51, 'Personal Access Token', '1912c83f32aeaf60215b9e74be2ad8e2a450a2577bd4eb72dcb4ac6f7c1301f6', '[\"*\"]', NULL, NULL, '2024-07-24 02:44:28', '2024-07-24 02:44:28');
INSERT INTO `personal_access_tokens` (`id`, `tokenable_type`, `tokenable_id`, `name`, `token`, `abilities`, `last_used_at`, `expires_at`, `created_at`, `updated_at`) VALUES (16, 'App\\Models\\User', 51, 'Personal Access Token', '36e879a36b6d4d474fea7821cd8eb141c46a902affa0849b46714815d38e080a', '[\"*\"]', NULL, NULL, '2024-07-24 02:44:45', '2024-07-24 02:44:45');
INSERT INTO `personal_access_tokens` (`id`, `tokenable_type`, `tokenable_id`, `name`, `token`, `abilities`, `last_used_at`, `expires_at`, `created_at`, `updated_at`) VALUES (17, 'App\\Models\\User', 51, 'Personal Access Token', '71f22bdb7780fab29e24bf9c6156014c854d1d4c3d03ce560c51c309d45bb002', '[\"*\"]', NULL, NULL, '2024-07-24 02:45:11', '2024-07-24 02:45:11');
INSERT INTO `personal_access_tokens` (`id`, `tokenable_type`, `tokenable_id`, `name`, `token`, `abilities`, `last_used_at`, `expires_at`, `created_at`, `updated_at`) VALUES (18, 'App\\Models\\User', 51, 'Personal Access Token', '3026d94477cf441d79c03e11c53a6ad7d57adb9ec0d4a1f3699c1d272aa57a04', '[\"*\"]', NULL, NULL, '2024-07-24 02:45:56', '2024-07-24 02:45:56');
INSERT INTO `personal_access_tokens` (`id`, `tokenable_type`, `tokenable_id`, `name`, `token`, `abilities`, `last_used_at`, `expires_at`, `created_at`, `updated_at`) VALUES (19, 'App\\Models\\User', 51, 'Personal Access Token', '74f604e76dfcb0ad453bf6b0f8fa4de98cf693775a7358508472b039803f2863', '[\"*\"]', '2024-07-24 02:52:53', NULL, '2024-07-24 02:48:37', '2024-07-24 02:52:53');
INSERT INTO `personal_access_tokens` (`id`, `tokenable_type`, `tokenable_id`, `name`, `token`, `abilities`, `last_used_at`, `expires_at`, `created_at`, `updated_at`) VALUES (20, 'App\\Models\\User', 51, 'Personal Access Token', '50800bfe0da789ab6668f891b63642208978fc0110e78a7e1b8d72e3e8bb790d', '[\"*\"]', '2024-07-24 02:55:13', NULL, '2024-07-24 02:53:33', '2024-07-24 02:55:13');
INSERT INTO `personal_access_tokens` (`id`, `tokenable_type`, `tokenable_id`, `name`, `token`, `abilities`, `last_used_at`, `expires_at`, `created_at`, `updated_at`) VALUES (21, 'App\\Models\\User', 51, 'Personal Access Token', '2e57a6aa89335d52373fe036cc06bfeff92bd96f2cda433bf7b7cb1c6c348753', '[\"*\"]', '2024-07-24 02:55:49', NULL, '2024-07-24 02:55:41', '2024-07-24 02:55:49');
INSERT INTO `personal_access_tokens` (`id`, `tokenable_type`, `tokenable_id`, `name`, `token`, `abilities`, `last_used_at`, `expires_at`, `created_at`, `updated_at`) VALUES (22, 'App\\Models\\User', 51, 'Personal Access Token', 'a4661f78fde301c15973f024619bcdd8fa408788d6793ffea51811cd061e9863', '[\"*\"]', '2024-08-17 04:38:49', NULL, '2024-07-24 02:57:16', '2024-08-17 04:38:49');
INSERT INTO `personal_access_tokens` (`id`, `tokenable_type`, `tokenable_id`, `name`, `token`, `abilities`, `last_used_at`, `expires_at`, `created_at`, `updated_at`) VALUES (23, 'App\\Models\\User', 53, 'Personal Access Token', '66fd433c4dc24174dd2fa39d97e7228facfa54f90b3e3dca8a8e0d2be70f6f7a', '[\"*\"]', NULL, NULL, '2024-09-05 14:37:16', '2024-09-05 14:37:16');
INSERT INTO `personal_access_tokens` (`id`, `tokenable_type`, `tokenable_id`, `name`, `token`, `abilities`, `last_used_at`, `expires_at`, `created_at`, `updated_at`) VALUES (24, 'App\\Models\\User', 53, 'Personal Access Token', '4cc0f2874c1e8f3acb958484cc72376b6cd48fdfe5df1e09aa118ba398173dd8', '[\"*\"]', '2024-09-05 14:37:45', NULL, '2024-09-05 14:37:30', '2024-09-05 14:37:45');
INSERT INTO `personal_access_tokens` (`id`, `tokenable_type`, `tokenable_id`, `name`, `token`, `abilities`, `last_used_at`, `expires_at`, `created_at`, `updated_at`) VALUES (25, 'App\\Models\\User', 53, 'Personal Access Token', '3248dc6237a61c90748cf7d1441cf52e628608b692ea7ba85f317e1797f4b96e', '[\"*\"]', '2024-09-05 15:26:43', NULL, '2024-09-05 15:26:12', '2024-09-05 15:26:43');
COMMIT;

-- ----------------------------
-- Table structure for product_by_days
-- ----------------------------
DROP TABLE IF EXISTS `product_by_days`;
CREATE TABLE `product_by_days` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `date` date NOT NULL,
  `product_id` bigint(20) unsigned NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `status` enum('active','inactive') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `product_by_days_product_id_foreign` (`product_id`),
  CONSTRAINT `product_by_days_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Records of product_by_days
-- ----------------------------
BEGIN;
INSERT INTO `product_by_days` (`id`, `date`, `product_id`, `price`, `status`, `deleted_at`, `created_at`, `updated_at`) VALUES ('434bf423-061e-475e-b786-bf75e1487f4b', '2024-07-19', 2, 300000.00, 'active', NULL, NULL, NULL);
INSERT INTO `product_by_days` (`id`, `date`, `product_id`, `price`, `status`, `deleted_at`, `created_at`, `updated_at`) VALUES ('6ccb8039-9210-4abe-8327-9aa082baf1d7', '2021-01-01', 4, 10000.00, 'active', NULL, NULL, NULL);
INSERT INTO `product_by_days` (`id`, `date`, `product_id`, `price`, `status`, `deleted_at`, `created_at`, `updated_at`) VALUES ('a8e62f46-9b09-4c6c-9eed-7f0cc9e23cc5', '2021-01-01', 3, 100000.00, 'active', NULL, NULL, NULL);
INSERT INTO `product_by_days` (`id`, `date`, `product_id`, `price`, `status`, `deleted_at`, `created_at`, `updated_at`) VALUES ('d0605aed-55ea-4254-a17d-eb8c8b76a672', '2024-07-19', 1, 200000.00, 'active', NULL, NULL, NULL);
COMMIT;

-- ----------------------------
-- Table structure for products
-- ----------------------------
DROP TABLE IF EXISTS `products`;
CREATE TABLE `products` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('active','inactive') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Records of products
-- ----------------------------
BEGIN;
INSERT INTO `products` (`id`, `name`, `description`, `status`, `deleted_at`, `created_at`, `updated_at`) VALUES (1, 'Product 1', 'Description 1', 'active', NULL, NULL, NULL);
INSERT INTO `products` (`id`, `name`, `description`, `status`, `deleted_at`, `created_at`, `updated_at`) VALUES (2, 'Product 2', 'Description 10', 'active', NULL, NULL, '2024-07-17 14:57:11');
INSERT INTO `products` (`id`, `name`, `description`, `status`, `deleted_at`, `created_at`, `updated_at`) VALUES (3, 'Product 3', 'Description 3', 'active', NULL, NULL, '2024-07-18 22:10:00');
INSERT INTO `products` (`id`, `name`, `description`, `status`, `deleted_at`, `created_at`, `updated_at`) VALUES (4, 'Product 4', 'Description 4', 'active', NULL, NULL, NULL);
COMMIT;

-- ----------------------------
-- Table structure for role_has_permissions
-- ----------------------------
DROP TABLE IF EXISTS `role_has_permissions`;
CREATE TABLE `role_has_permissions` (
  `permission_id` bigint(20) unsigned NOT NULL,
  `role_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`permission_id`,`role_id`),
  KEY `role_has_permissions_role_id_foreign` (`role_id`),
  CONSTRAINT `role_has_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `role_has_permissions_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Records of role_has_permissions
-- ----------------------------
BEGIN;
COMMIT;

-- ----------------------------
-- Table structure for roles
-- ----------------------------
DROP TABLE IF EXISTS `roles`;
CREATE TABLE `roles` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `guard_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `roles_name_guard_name_unique` (`name`,`guard_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Records of roles
-- ----------------------------
BEGIN;
COMMIT;

-- ----------------------------
-- Table structure for sessions
-- ----------------------------
DROP TABLE IF EXISTS `sessions`;
CREATE TABLE `sessions` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_activity` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Records of sessions
-- ----------------------------
BEGIN;
INSERT INTO `sessions` (`id`, `user_id`, `ip_address`, `user_agent`, `payload`, `last_activity`) VALUES ('ed8ljiFxdlFfGwm7AeWYoonApI0xzfarzfOeOjb4', NULL, '127.0.0.1', 'PostmanRuntime/7.40.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiZHV3S0RSckNwakxlVE15S2h6SHJ4TnRKbXF6QzR6QVROVnlRYVllcCI7czo5OiJfcHJldmlvdXMiO2E6MTp7czozOiJ1cmwiO3M6MjE6Imh0dHA6Ly8xMjcuMC4wLjE6ODAwMCI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fX0=', 1725546995);
INSERT INTO `sessions` (`id`, `user_id`, `ip_address`, `user_agent`, `payload`, `last_activity`) VALUES ('GhymAviBdQgAUesSPkSH8pCGOHaXbDX0PM7OkH7T', NULL, '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/127.0.0.0 Safari/537.36', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoienpXYnlGVVNweHA0TnNVb3QwVEI4UGlaQW5lNlByaG1sTXdSaDROaiI7czo5OiJfcHJldmlvdXMiO2E6MTp7czozOiJ1cmwiO3M6MjE6Imh0dHA6Ly9sb2NhbGhvc3Q6ODAwMCI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fX0=', 1723868909);
INSERT INTO `sessions` (`id`, `user_id`, `ip_address`, `user_agent`, `payload`, `last_activity`) VALUES ('q1ZLeR4zjbOdK23Q24ChZ0AlFpAbSiYLL9CxRepU', NULL, '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36', 'YToyOntzOjY6Il90b2tlbiI7czo0MDoiV3pDMENyWjkyNThHam9xT21IQ3dRd2tBS1hJS01qNDdnQXBCcXZrSCI7czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==', 1721446777);
INSERT INTO `sessions` (`id`, `user_id`, `ip_address`, `user_agent`, `payload`, `last_activity`) VALUES ('qr79PsG55lzdQVTphuOAiLmoCetVcbsU7R8JVS9T', NULL, '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiZWpIUTFualE4UnFsMmRxd0k4RnpOR0tuSXF6ZGs4V2ZpckNwb2F0SCI7czo5OiJfcHJldmlvdXMiO2E6MTp7czozOiJ1cmwiO3M6MjE6Imh0dHA6Ly9sb2NhbGhvc3Q6ODAwMCI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fX0=', 1721097290);
INSERT INTO `sessions` (`id`, `user_id`, `ip_address`, `user_agent`, `payload`, `last_activity`) VALUES ('vvFV54gsFWtx5IwmfOq2Q0JZuQeKZhPUIldZ4RBA', NULL, '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiM3RNd3dXbXVKazRlTng3TW0zS3ZlTVJuRmZyZndtSk5FRU51MXZSNiI7czo5OiJfcHJldmlvdXMiO2E6MTp7czozOiJ1cmwiO3M6MjE6Imh0dHA6Ly9sb2NhbGhvc3Q6ODAwMCI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fX0=', 1725545229);
INSERT INTO `sessions` (`id`, `user_id`, `ip_address`, `user_agent`, `payload`, `last_activity`) VALUES ('WmtJDkcEdhNSqVqV7sZErkqdN01JbV9U9dcULqJp', NULL, '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/127.0.0.0 Safari/537.36', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiZzN4RDg3bU84dzV3ZzlCanVHNHBlVlZEVVZ2dkpqcW5KOHR4UDdOZyI7czo5OiJfcHJldmlvdXMiO2E6MTp7czozOiJ1cmwiO3M6MjE6Imh0dHA6Ly9sb2NhbGhvc3Q6ODAwMSI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fX0=', 1723129781);
INSERT INTO `sessions` (`id`, `user_id`, `ip_address`, `user_agent`, `payload`, `last_activity`) VALUES ('zpWPD680E3qyBWvnatwDdO6BkybxqyGXAM1Z8tnv', NULL, '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36', 'YToyOntzOjY6Il90b2tlbiI7czo0MDoiMVR1TDRVSnY1OUREa0ZXSlo1T3FGd213QjB4RDk4dmhaYnFQVUZaZiI7czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==', 1721198169);
COMMIT;

-- ----------------------------
-- Table structure for users
-- ----------------------------
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `full_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `company` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `role` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `username` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `country` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contact` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `bank_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bank_account` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `current_plan` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `avatar` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `billing` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=54 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Records of users
-- ----------------------------
BEGIN;
INSERT INTO `users` (`id`, `full_name`, `company`, `role`, `username`, `country`, `contact`, `email`, `bank_name`, `bank_account`, `current_plan`, `password`, `status`, `avatar`, `billing`, `email_verified_at`, `remember_token`, `deleted_at`, `created_at`, `updated_at`) VALUES (1, 'Galasasen Slixby', 'Yotz PVT LTD', 'editor', 'gslixby0', 'El Salvador', '(479) 232-9151', 'gslixby0@abc.net.au', NULL, NULL, 'enterprise', NULL, 'inactive', '/images/avatars/avatar-1.png', 'Manual-Credit Card', NULL, NULL, NULL, NULL, NULL);
INSERT INTO `users` (`id`, `full_name`, `company`, `role`, `username`, `country`, `contact`, `email`, `bank_name`, `bank_account`, `current_plan`, `password`, `status`, `avatar`, `billing`, `email_verified_at`, `remember_token`, `deleted_at`, `created_at`, `updated_at`) VALUES (3, 'Marjory Sicely', 'Oozz PVT LTD', 'maintainer', 'msicely2', 'Russia', '(321) 264-4599', 'msicely2@who.int', NULL, NULL, 'enterprise', NULL, 'active', '/images/avatars/avatar-3.png', 'Manual-Credit Card', NULL, NULL, NULL, NULL, NULL);
INSERT INTO `users` (`id`, `full_name`, `company`, `role`, `username`, `country`, `contact`, `email`, `bank_name`, `bank_account`, `current_plan`, `password`, `status`, `avatar`, `billing`, `email_verified_at`, `remember_token`, `deleted_at`, `created_at`, `updated_at`) VALUES (5, 'Maggy Hurran', 'Aimbo PVT LTD', 'subscriber', 'mhurran4', 'Pakistan', '(669) 914-1078', 'mhurran4@yahoo.co.jp', NULL, NULL, 'enterprise', NULL, 'pending', '/images/avatars/avatar-5.png', 'Manual-Cash', NULL, NULL, NULL, NULL, NULL);
INSERT INTO `users` (`id`, `full_name`, `company`, `role`, `username`, `country`, `contact`, `email`, `bank_name`, `bank_account`, `current_plan`, `password`, `status`, `avatar`, `billing`, `email_verified_at`, `remember_token`, `deleted_at`, `created_at`, `updated_at`) VALUES (6, 'Silvain Halstead', 'Jaxbean PVT LTD', 'author', 'shalstead5', 'China', '(958) 973-3093', 'shalstead5@shinystat.com', NULL, NULL, '\"company\"', NULL, 'active', '/images/avatars/avatar-6.png', 'Manual-Credit Card', NULL, NULL, NULL, NULL, NULL);
INSERT INTO `users` (`id`, `full_name`, `company`, `role`, `username`, `country`, `contact`, `email`, `bank_name`, `bank_account`, `current_plan`, `password`, `status`, `avatar`, `billing`, `email_verified_at`, `remember_token`, `deleted_at`, `created_at`, `updated_at`) VALUES (7, 'Breena Gallemore', 'Jazzy PVT LTD', 'subscriber', 'bgallemore6', 'Canada', '(825) 977-8152', 'bgallemore6@boston.com', NULL, NULL, '\"company\"', NULL, 'pending', '/images/avatars/avatar-1.png', 'Manual-PayPal', NULL, NULL, NULL, NULL, NULL);
INSERT INTO `users` (`id`, `full_name`, `company`, `role`, `username`, `country`, `contact`, `email`, `bank_name`, `bank_account`, `current_plan`, `password`, `status`, `avatar`, `billing`, `email_verified_at`, `remember_token`, `deleted_at`, `created_at`, `updated_at`) VALUES (9, 'Franz Scotfurth', 'Tekfly PVT LTD', 'subscriber', 'fscotfurth8', 'China', '(978) 146-5443', 'fscotfurth8@dailymotion.com', NULL, NULL, 'team', NULL, 'pending', '/images/avatars/avatar-3.png', 'Manual-Credit Card', NULL, NULL, NULL, NULL, NULL);
INSERT INTO `users` (`id`, `full_name`, `company`, `role`, `username`, `country`, `contact`, `email`, `bank_name`, `bank_account`, `current_plan`, `password`, `status`, `avatar`, `billing`, `email_verified_at`, `remember_token`, `deleted_at`, `created_at`, `updated_at`) VALUES (10, 'Jillene Bellany', 'Gigashots PVT LTD', 'maintainer', 'jbellany9', 'Jamaica', '(589) 284-6732', 'jbellany9@kickstarter.com', NULL, NULL, '\"company\"', NULL, 'inactive', '/images/avatars/avatar-4.png', 'Manual-Credit Card', NULL, NULL, NULL, NULL, NULL);
INSERT INTO `users` (`id`, `full_name`, `company`, `role`, `username`, `country`, `contact`, `email`, `bank_name`, `bank_account`, `current_plan`, `password`, `status`, `avatar`, `billing`, `email_verified_at`, `remember_token`, `deleted_at`, `created_at`, `updated_at`) VALUES (11, 'Jonah Wharlton', 'Eare PVT LTD', 'subscriber', 'jwharltona', 'United States', '(176) 532-6824', 'jwharltona@oakley.com', NULL, NULL, 'team', NULL, 'inactive', '/images/avatars/avatar-5.png', 'Manual-Cash', NULL, NULL, NULL, NULL, NULL);
INSERT INTO `users` (`id`, `full_name`, `company`, `role`, `username`, `country`, `contact`, `email`, `bank_name`, `bank_account`, `current_plan`, `password`, `status`, `avatar`, `billing`, `email_verified_at`, `remember_token`, `deleted_at`, `created_at`, `updated_at`) VALUES (12, 'Seth Hallam', 'Yakitri PVT LTD', 'subscriber', 'shallamb', 'Peru', '(234) 464-0600', 'shallamb@hugedomains.com', NULL, NULL, 'team', NULL, 'pending', '/images/avatars/avatar-6.png', 'Manual-PayPal', NULL, NULL, NULL, NULL, NULL);
INSERT INTO `users` (`id`, `full_name`, `company`, `role`, `username`, `country`, `contact`, `email`, `bank_name`, `bank_account`, `current_plan`, `password`, `status`, `avatar`, `billing`, `email_verified_at`, `remember_token`, `deleted_at`, `created_at`, `updated_at`) VALUES (13, 'Yoko Pottie', 'Leenti PVT LTD', 'subscriber', 'ypottiec', 'Philippines', '(907) 284-5083', 'ypottiec@privacy.gov.au', NULL, NULL, 'basic', NULL, 'inactive', '/images/avatars/avatar-1.png', 'Manual-PayPal', NULL, NULL, NULL, NULL, NULL);
INSERT INTO `users` (`id`, `full_name`, `company`, `role`, `username`, `country`, `contact`, `email`, `bank_name`, `bank_account`, `current_plan`, `password`, `status`, `avatar`, `billing`, `email_verified_at`, `remember_token`, `deleted_at`, `created_at`, `updated_at`) VALUES (14, 'Maximilianus Krause', 'Digitube PVT LTD', 'author', 'mkraused', 'Democratic Republic of the Congo', '(167) 135-7392', 'mkraused@stanford.edu', NULL, NULL, 'team', NULL, 'active', '/images/avatars/avatar-2.png', 'Manual-PayPal', NULL, NULL, NULL, NULL, NULL);
INSERT INTO `users` (`id`, `full_name`, `company`, `role`, `username`, `country`, `contact`, `email`, `bank_name`, `bank_account`, `current_plan`, `password`, `status`, `avatar`, `billing`, `email_verified_at`, `remember_token`, `deleted_at`, `created_at`, `updated_at`) VALUES (15, 'Zsazsa McCleverty', 'Kaymbo PVT LTD', 'maintainer', 'zmcclevertye', 'France', '(317) 409-6565', 'zmcclevertye@soundcloud.com', NULL, NULL, 'enterprise', NULL, 'active', '/images/avatars/avatar-3.png', 'Manual-PayPal', NULL, NULL, NULL, NULL, NULL);
INSERT INTO `users` (`id`, `full_name`, `company`, `role`, `username`, `country`, `contact`, `email`, `bank_name`, `bank_account`, `current_plan`, `password`, `status`, `avatar`, `billing`, `email_verified_at`, `remember_token`, `deleted_at`, `created_at`, `updated_at`) VALUES (16, 'Bentlee Emblin', 'Yambee PVT LTD', 'author', 'bemblinf', 'Spain', '(590) 606-1056', 'bemblinf@wired.com', NULL, NULL, '\"company\"', NULL, 'active', '/images/avatars/avatar-4.png', 'Manual-PayPal', NULL, NULL, NULL, NULL, NULL);
INSERT INTO `users` (`id`, `full_name`, `company`, `role`, `username`, `country`, `contact`, `email`, `bank_name`, `bank_account`, `current_plan`, `password`, `status`, `avatar`, `billing`, `email_verified_at`, `remember_token`, `deleted_at`, `created_at`, `updated_at`) VALUES (17, 'Brockie Myles', 'Wikivu PVT LTD', 'maintainer', 'bmylesg', 'Poland', '(553) 225-9905', 'bmylesg@amazon.com', NULL, NULL, 'basic', NULL, 'active', '/images/avatars/avatar-5.png', 'Manual-Credit Card', NULL, NULL, NULL, NULL, NULL);
INSERT INTO `users` (`id`, `full_name`, `company`, `role`, `username`, `country`, `contact`, `email`, `bank_name`, `bank_account`, `current_plan`, `password`, `status`, `avatar`, `billing`, `email_verified_at`, `remember_token`, `deleted_at`, `created_at`, `updated_at`) VALUES (18, 'Bertha Biner', 'Twinte PVT LTD', 'editor', 'bbinerh', 'Yemen', '(901) 916-9287', 'bbinerh@mozilla.com', NULL, NULL, 'team', NULL, 'active', '/images/avatars/avatar-6.png', 'Manual-Credit Card', NULL, NULL, NULL, NULL, NULL);
INSERT INTO `users` (`id`, `full_name`, `company`, `role`, `username`, `country`, `contact`, `email`, `bank_name`, `bank_account`, `current_plan`, `password`, `status`, `avatar`, `billing`, `email_verified_at`, `remember_token`, `deleted_at`, `created_at`, `updated_at`) VALUES (19, 'Travus Bruntjen', 'Cog\"idoo PVT LTD', 'admin', 'tbruntjeni', 'France', '(524) 586-6057', 'tbruntjeni@sitemeter.com', NULL, NULL, 'enterprise', NULL, 'active', '/images/avatars/avatar-1.png', 'Manual-Cash', NULL, NULL, NULL, NULL, NULL);
INSERT INTO `users` (`id`, `full_name`, `company`, `role`, `username`, `country`, `contact`, `email`, `bank_name`, `bank_account`, `current_plan`, `password`, `status`, `avatar`, `billing`, `email_verified_at`, `remember_token`, `deleted_at`, `created_at`, `updated_at`) VALUES (20, 'Wesley Burland', 'Bubblemix PVT LTD', 'editor', 'wburlandj', 'Honduras', '(569) 683-1292', 'wburlandj@uiuc.edu', NULL, NULL, 'team', NULL, 'inactive', '/images/avatars/avatar-2.png', 'Manual-Cash', NULL, NULL, NULL, NULL, NULL);
INSERT INTO `users` (`id`, `full_name`, `company`, `role`, `username`, `country`, `contact`, `email`, `bank_name`, `bank_account`, `current_plan`, `password`, `status`, `avatar`, `billing`, `email_verified_at`, `remember_token`, `deleted_at`, `created_at`, `updated_at`) VALUES (21, 'Selina Kyle', 'Wayne Enterprises', 'admin', 'catwomen1940', 'USA', '(829) 537-0057', 'irena.dubrovna@wayne.com', NULL, NULL, 'team', NULL, 'active', '/images/avatars/avatar-3.png', 'Manual-Cash', NULL, NULL, NULL, NULL, NULL);
INSERT INTO `users` (`id`, `full_name`, `company`, `role`, `username`, `country`, `contact`, `email`, `bank_name`, `bank_account`, `current_plan`, `password`, `status`, `avatar`, `billing`, `email_verified_at`, `remember_token`, `deleted_at`, `created_at`, `updated_at`) VALUES (22, 'Jameson Lyster', 'Quaxo PVT LTD', 'editor', 'jlysterl', 'Ukraine', '(593) 624-0222', 'jlysterl@guardian.co.uk', NULL, NULL, '\"company\"', NULL, 'inactive', '/images/avatars/avatar-4.png', 'Manual-Cash', NULL, NULL, NULL, NULL, NULL);
INSERT INTO `users` (`id`, `full_name`, `company`, `role`, `username`, `country`, `contact`, `email`, `bank_name`, `bank_account`, `current_plan`, `password`, `status`, `avatar`, `billing`, `email_verified_at`, `remember_token`, `deleted_at`, `created_at`, `updated_at`) VALUES (23, 'Kare Skitterel', 'Ainyx PVT LTD', 'maintainer', 'kskitterelm', 'Poland', '(254) 845-4107', 'kskitterelm@ainyx.com', NULL, NULL, 'basic', NULL, 'pending', '/images/avatars/avatar-5.png', 'Manual-Credit Card', NULL, NULL, NULL, NULL, NULL);
INSERT INTO `users` (`id`, `full_name`, `company`, `role`, `username`, `country`, `contact`, `email`, `bank_name`, `bank_account`, `current_plan`, `password`, `status`, `avatar`, `billing`, `email_verified_at`, `remember_token`, `deleted_at`, `created_at`, `updated_at`) VALUES (24, 'Cleavland Hatherleigh', 'Flipopia PVT LTD', 'admin', 'chatherleighn', 'Brazil', '(700) 783-7498', 'chatherleighn@washington.edu', NULL, NULL, 'team', NULL, 'pending', '/images/avatars/avatar-6.png', 'Manual-Credit Card', NULL, NULL, NULL, NULL, NULL);
INSERT INTO `users` (`id`, `full_name`, `company`, `role`, `username`, `country`, `contact`, `email`, `bank_name`, `bank_account`, `current_plan`, `password`, `status`, `avatar`, `billing`, `email_verified_at`, `remember_token`, `deleted_at`, `created_at`, `updated_at`) VALUES (25, 'Adeline Micco', 'Topicware PVT LTD', 'admin', 'amiccoo', 'France', '(227) 598-1841', 'amiccoo@whitehouse.gov', NULL, NULL, 'enterprise', NULL, 'pending', '/images/avatars/avatar-1.png', 'Auto Debit', NULL, NULL, NULL, NULL, NULL);
INSERT INTO `users` (`id`, `full_name`, `company`, `role`, `username`, `country`, `contact`, `email`, `bank_name`, `bank_account`, `current_plan`, `password`, `status`, `avatar`, `billing`, `email_verified_at`, `remember_token`, `deleted_at`, `created_at`, `updated_at`) VALUES (26, 'Hugh Hasson', 'Skinix PVT LTD', 'admin', 'hhassonp', 'China', '(582) 516-1324', 'hhassonp@bizjournals.com', NULL, NULL, 'basic', NULL, 'inactive', 'avatar6', 'Auto Debit', NULL, NULL, NULL, NULL, NULL);
INSERT INTO `users` (`id`, `full_name`, `company`, `role`, `username`, `country`, `contact`, `email`, `bank_name`, `bank_account`, `current_plan`, `password`, `status`, `avatar`, `billing`, `email_verified_at`, `remember_token`, `deleted_at`, `created_at`, `updated_at`) VALUES (27, 'Germain Jacombs', 'Youopia PVT LTD', 'editor', 'gjacombsq', 'Zambia', '(137) 467-5393', 'gjacombsq@jigsy.com', NULL, NULL, 'enterprise', NULL, 'active', '/images/avatars/avatar-1.png', 'Auto Debit', NULL, NULL, NULL, NULL, NULL);
INSERT INTO `users` (`id`, `full_name`, `company`, `role`, `username`, `country`, `contact`, `email`, `bank_name`, `bank_account`, `current_plan`, `password`, `status`, `avatar`, `billing`, `email_verified_at`, `remember_token`, `deleted_at`, `created_at`, `updated_at`) VALUES (28, 'Bree Kilday', 'Jetpulse PVT LTD', 'maintainer', 'bkildayr', 'Portugal', '(412) 476-0854', 'bkildayr@mashable.com', NULL, NULL, 'team', NULL, 'active', '/images/avatars/avatar-2.png', 'Auto Debit', NULL, NULL, NULL, NULL, NULL);
INSERT INTO `users` (`id`, `full_name`, `company`, `role`, `username`, `country`, `contact`, `email`, `bank_name`, `bank_account`, `current_plan`, `password`, `status`, `avatar`, `billing`, `email_verified_at`, `remember_token`, `deleted_at`, `created_at`, `updated_at`) VALUES (29, 'Candice Pinyon', 'Kare PVT LTD', 'maintainer', 'cpinyons', 'Sweden', '(170) 683-1520', 'cpinyons@behance.net', NULL, NULL, 'team', NULL, 'active', '/images/avatars/avatar-3.png', 'Manual-Cash', NULL, NULL, NULL, NULL, NULL);
INSERT INTO `users` (`id`, `full_name`, `company`, `role`, `username`, `country`, `contact`, `email`, `bank_name`, `bank_account`, `current_plan`, `password`, `status`, `avatar`, `billing`, `email_verified_at`, `remember_token`, `deleted_at`, `created_at`, `updated_at`) VALUES (30, 'Isabel Mallindine', 'Voomm PVT LTD', 'subscriber', 'imallindinet', 'Slovenia', '(332) 803-1983', 'imallindinet@shinystat.com', NULL, NULL, 'team', NULL, 'pending', '/images/avatars/avatar-4.png', 'Manual-Cash', NULL, NULL, NULL, NULL, NULL);
INSERT INTO `users` (`id`, `full_name`, `company`, `role`, `username`, `country`, `contact`, `email`, `bank_name`, `bank_account`, `current_plan`, `password`, `status`, `avatar`, `billing`, `email_verified_at`, `remember_token`, `deleted_at`, `created_at`, `updated_at`) VALUES (31, 'Gwendolyn Meineken', 'Oyondu PVT LTD', 'admin', 'gmeinekenu', 'Moldova', '(551) 379-7460', 'gmeinekenu@hc360.com', NULL, NULL, 'basic', NULL, 'pending', '/images/avatars/avatar-5.png', 'Manual-Cash', NULL, NULL, NULL, NULL, NULL);
INSERT INTO `users` (`id`, `full_name`, `company`, `role`, `username`, `country`, `contact`, `email`, `bank_name`, `bank_account`, `current_plan`, `password`, `status`, `avatar`, `billing`, `email_verified_at`, `remember_token`, `deleted_at`, `created_at`, `updated_at`) VALUES (32, 'Rafaellle Snowball', 'Fivespan PVT LTD', 'editor', 'rsnowballv', 'Philippines', '(974) 829-0911', 'rsnowballv@indiegogo.com', NULL, NULL, 'basic', NULL, 'pending', '/images/avatars/avatar-6.png', 'Manual-Cash', NULL, NULL, NULL, NULL, NULL);
INSERT INTO `users` (`id`, `full_name`, `company`, `role`, `username`, `country`, `contact`, `email`, `bank_name`, `bank_account`, `current_plan`, `password`, `status`, `avatar`, `billing`, `email_verified_at`, `remember_token`, `deleted_at`, `created_at`, `updated_at`) VALUES (33, 'Rochette Emer', 'Thoughtworks PVT LTD', 'admin', 'remerw', 'North Korea', '(841) 889-3339', 'remerw@blogtalkradio.com', NULL, NULL, 'basic', NULL, 'active', '/images/avatars/avatar-1.png', 'Manual-Cash', NULL, NULL, NULL, NULL, NULL);
INSERT INTO `users` (`id`, `full_name`, `company`, `role`, `username`, `country`, `contact`, `email`, `bank_name`, `bank_account`, `current_plan`, `password`, `status`, `avatar`, `billing`, `email_verified_at`, `remember_token`, `deleted_at`, `created_at`, `updated_at`) VALUES (34, 'Ophelie Fibbens', 'Jaxbean PVT LTD', 'subscriber', 'ofibbensx', 'Indonesia', '(764) 885-7351', 'ofibbensx@booking.com', NULL, NULL, '\"company\"', NULL, 'active', '/images/avatars/avatar-2.png', 'Manual-Cash', NULL, NULL, NULL, NULL, NULL);
INSERT INTO `users` (`id`, `full_name`, `company`, `role`, `username`, `country`, `contact`, `email`, `bank_name`, `bank_account`, `current_plan`, `password`, `status`, `avatar`, `billing`, `email_verified_at`, `remember_token`, `deleted_at`, `created_at`, `updated_at`) VALUES (35, 'Stephen MacGilfoyle', 'Browseblab PVT LTD', 'maintainer', 'smacgilfoyley', 'Japan', '(350) 589-8520', 'smacgilfoyley@bigcartel.com', NULL, NULL, '\"company\"', NULL, 'pending', '/images/avatars/avatar-3.png', 'Manual-Cash', NULL, NULL, NULL, NULL, NULL);
INSERT INTO `users` (`id`, `full_name`, `company`, `role`, `username`, `country`, `contact`, `email`, `bank_name`, `bank_account`, `current_plan`, `password`, `status`, `avatar`, `billing`, `email_verified_at`, `remember_token`, `deleted_at`, `created_at`, `updated_at`) VALUES (36, 'Bradan Rosebotham', 'Agivu PVT LTD', 'subscriber', 'brosebothamz', 'Belarus', '(882) 933-2180', 'brosebothamz@tripadvisor.com', NULL, NULL, 'team', NULL, 'inactive', '/images/avatars/avatar-4.png', 'Manual-Credit Card', NULL, NULL, NULL, NULL, NULL);
INSERT INTO `users` (`id`, `full_name`, `company`, `role`, `username`, `country`, `contact`, `email`, `bank_name`, `bank_account`, `current_plan`, `password`, `status`, `avatar`, `billing`, `email_verified_at`, `remember_token`, `deleted_at`, `created_at`, `updated_at`) VALUES (37, 'Skip Hebblethwaite', 'Katz PVT LTD', 'admin', 'shebblethwaite10', 'Canada', '(610) 343-1024', 'shebblethwaite10@arizona.edu', NULL, NULL, '\"company\"', NULL, 'inactive', '/images/avatars/avatar-5.png', 'Manual-Credit Card', NULL, NULL, NULL, NULL, NULL);
INSERT INTO `users` (`id`, `full_name`, `company`, `role`, `username`, `country`, `contact`, `email`, `bank_name`, `bank_account`, `current_plan`, `password`, `status`, `avatar`, `billing`, `email_verified_at`, `remember_token`, `deleted_at`, `created_at`, `updated_at`) VALUES (38, 'Moritz Piccard', 'Twitternation PVT LTD', 'maintainer', 'mpiccard11', 'Croatia', '(365) 277-2986', 'mpiccard11@vimeo.com', NULL, NULL, 'enterprise', NULL, 'inactive', '/images/avatars/avatar-6.png', 'Manual-Credit Card', NULL, NULL, NULL, NULL, NULL);
INSERT INTO `users` (`id`, `full_name`, `company`, `role`, `username`, `country`, `contact`, `email`, `bank_name`, `bank_account`, `current_plan`, `password`, `status`, `avatar`, `billing`, `email_verified_at`, `remember_token`, `deleted_at`, `created_at`, `updated_at`) VALUES (39, 'Tyne W\"idmore', 'Yombu PVT LTD', 'subscriber', 'tw\"idmore12', 'Finland', '(531) 731-0928', 'tw\"idmore12@bravesites.com', NULL, NULL, 'team', NULL, 'pending', '/images/avatars/avatar-1.png', 'Auto Debit', NULL, NULL, NULL, NULL, NULL);
INSERT INTO `users` (`id`, `full_name`, `company`, `role`, `username`, `country`, `contact`, `email`, `bank_name`, `bank_account`, `current_plan`, `password`, `status`, `avatar`, `billing`, `email_verified_at`, `remember_token`, `deleted_at`, `created_at`, `updated_at`) VALUES (40, 'Florenza Desporte', 'Kamba PVT LTD', 'author', 'fdesporte13', 'Ukraine', '(312) 104-2638', 'fdesporte13@omniture.com', NULL, NULL, '\"company\"', NULL, 'active', '/images/avatars/avatar-2.png', 'Auto Debit', NULL, NULL, NULL, NULL, NULL);
INSERT INTO `users` (`id`, `full_name`, `company`, `role`, `username`, `country`, `contact`, `email`, `bank_name`, `bank_account`, `current_plan`, `password`, `status`, `avatar`, `billing`, `email_verified_at`, `remember_token`, `deleted_at`, `created_at`, `updated_at`) VALUES (41, 'Edwina Baldetti', 'Dazzlesphere PVT LTD', 'maintainer', 'ebaldetti14', 'Haiti', '(315) 329-3578', 'ebaldetti14@theguardian.com', NULL, NULL, 'team', NULL, 'pending', '/images/avatars/avatar-3.png', 'Auto Debit', NULL, NULL, NULL, NULL, NULL);
INSERT INTO `users` (`id`, `full_name`, `company`, `role`, `username`, `country`, `contact`, `email`, `bank_name`, `bank_account`, `current_plan`, `password`, `status`, `avatar`, `billing`, `email_verified_at`, `remember_token`, `deleted_at`, `created_at`, `updated_at`) VALUES (42, 'Benedetto Rossiter', 'Mybuzz PVT LTD', 'editor', 'brossiter15', 'Indonesia', '(323) 175-6741', 'brossiter15@craigslist.org', NULL, NULL, 'team', NULL, 'inactive', '/images/avatars/avatar-4.png', 'Auto Debit', NULL, NULL, NULL, NULL, NULL);
INSERT INTO `users` (`id`, `full_name`, `company`, `role`, `username`, `country`, `contact`, `email`, `bank_name`, `bank_account`, `current_plan`, `password`, `status`, `avatar`, `billing`, `email_verified_at`, `remember_token`, `deleted_at`, `created_at`, `updated_at`) VALUES (43, 'Micaela McNirlan', 'Tambee PVT LTD', 'admin', 'mmcnirlan16', 'Indonesia', '(242) 952-0916', 'mmcnirlan16@hc360.com', NULL, NULL, 'basic', NULL, 'inactive', '/images/avatars/avatar-5.png', 'Auto Debit', NULL, NULL, NULL, NULL, NULL);
INSERT INTO `users` (`id`, `full_name`, `company`, `role`, `username`, `country`, `contact`, `email`, `bank_name`, `bank_account`, `current_plan`, `password`, `status`, `avatar`, `billing`, `email_verified_at`, `remember_token`, `deleted_at`, `created_at`, `updated_at`) VALUES (44, 'Vladamir Koschek', 'Centimia PVT LTD', 'author', 'vkoschek17', 'Guatemala', '(531) 758-8335', 'vkoschek17@abc.net.au', NULL, NULL, 'team', NULL, 'active', '/images/avatars/avatar-6.png', 'Manual-Credit Card', NULL, NULL, NULL, NULL, NULL);
INSERT INTO `users` (`id`, `full_name`, `company`, `role`, `username`, `country`, `contact`, `email`, `bank_name`, `bank_account`, `current_plan`, `password`, `status`, `avatar`, `billing`, `email_verified_at`, `remember_token`, `deleted_at`, `created_at`, `updated_at`) VALUES (45, 'Corrie Perot', 'Flipopia PVT LTD', 'subscriber', 'cperot18', 'China', '(659) 385-6808', 'cperot18@goo.ne.jp', NULL, NULL, 'team', NULL, 'pending', '/images/avatars/avatar-1.png', 'Manual-Credit Card', NULL, NULL, NULL, NULL, NULL);
INSERT INTO `users` (`id`, `full_name`, `company`, `role`, `username`, `country`, `contact`, `email`, `bank_name`, `bank_account`, `current_plan`, `password`, `status`, `avatar`, `billing`, `email_verified_at`, `remember_token`, `deleted_at`, `created_at`, `updated_at`) VALUES (46, 'Saunder Offner', 'Skalith PVT LTD', 'maintainer', 'soffner19', 'Poland', '(200) 586-2264', 'soffner19@mac.com', NULL, NULL, 'enterprise', NULL, 'pending', '/images/avatars/avatar-2.png', 'Manual-Credit Card', NULL, NULL, NULL, NULL, NULL);
INSERT INTO `users` (`id`, `full_name`, `company`, `role`, `username`, `country`, `contact`, `email`, `bank_name`, `bank_account`, `current_plan`, `password`, `status`, `avatar`, `billing`, `email_verified_at`, `remember_token`, `deleted_at`, `created_at`, `updated_at`) VALUES (47, 'Karena Courtliff', 'Feedfire PVT LTD', 'admin', 'kcourtliff1a', 'China', '(478) 199-0020', 'kcourtliff1a@bbc.co.uk', NULL, NULL, 'basic', NULL, 'active', '/images/avatars/avatar-3.png', 'Manual-Credit Card', NULL, NULL, NULL, NULL, NULL);
INSERT INTO `users` (`id`, `full_name`, `company`, `role`, `username`, `country`, `contact`, `email`, `bank_name`, `bank_account`, `current_plan`, `password`, `status`, `avatar`, `billing`, `email_verified_at`, `remember_token`, `deleted_at`, `created_at`, `updated_at`) VALUES (48, 'Onfre Wind', 'Thoughtmix PVT LTD', 'admin', 'owind1b', 'Ukraine', '(344) 262-7270', 'owind1b@yandex.ru', NULL, NULL, 'basic', NULL, 'pending', '/images/avatars/avatar-4.png', 'Manual-PayPal', NULL, NULL, NULL, NULL, NULL);
INSERT INTO `users` (`id`, `full_name`, `company`, `role`, `username`, `country`, `contact`, `email`, `bank_name`, `bank_account`, `current_plan`, `password`, `status`, `avatar`, `billing`, `email_verified_at`, `remember_token`, `deleted_at`, `created_at`, `updated_at`) VALUES (49, 'Paulie Durber', 'Babbleblab PVT LTD', 'subscriber', 'pdurber1c', 'Sweden', '(694) 676-1275', 'pdurber1c@gov.uk', NULL, NULL, 'team', NULL, 'inactive', '/images/avatars/avatar-5.png', 'Manual-PayPal', NULL, NULL, NULL, NULL, NULL);
INSERT INTO `users` (`id`, `full_name`, `company`, `role`, `username`, `country`, `contact`, `email`, `bank_name`, `bank_account`, `current_plan`, `password`, `status`, `avatar`, `billing`, `email_verified_at`, `remember_token`, `deleted_at`, `created_at`, `updated_at`) VALUES (50, 'Beverlie Krabbe', 'Kaymbo PVT LTD', 'editor', 'bkrabbe1d', 'China', '(397) 294-5153', 'bkrabbe1d@home.pl', NULL, NULL, '\"company\"', NULL, 'active', '/images/avatars/avatar-6.png', 'Manual-Cash', NULL, NULL, NULL, NULL, NULL);
INSERT INTO `users` (`id`, `full_name`, `company`, `role`, `username`, `country`, `contact`, `email`, `bank_name`, `bank_account`, `current_plan`, `password`, `status`, `avatar`, `billing`, `email_verified_at`, `remember_token`, `deleted_at`, `created_at`, `updated_at`) VALUES (51, 'Beverlie Krabbe', 'Kaymbo PVT LTD', 'editor', 'bkrabbe1d', 'China', '(397) 294-5153', 'admin@demo.com', 'vietcombank', '984294225223', 'team', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'active', '/images/avatars/avatar-6.png', 'Manual-Cash', NULL, NULL, NULL, '2024-07-16 03:12:48', '2024-07-16 03:12:48');
INSERT INTO `users` (`id`, `full_name`, `company`, `role`, `username`, `country`, `contact`, `email`, `bank_name`, `bank_account`, `current_plan`, `password`, `status`, `avatar`, `billing`, `email_verified_at`, `remember_token`, `deleted_at`, `created_at`, `updated_at`) VALUES (52, NULL, 'thinh company', 'Admin', NULL, 'UK', '89452335342', 'thinh@gmail.com', 'vietcombank', '082324232323', NULL, NULL, 'active', NULL, 'Auto Debit', NULL, NULL, NULL, '2024-07-17 02:02:37', '2024-07-17 02:02:37');
INSERT INTO `users` (`id`, `full_name`, `company`, `role`, `username`, `country`, `contact`, `email`, `bank_name`, `bank_account`, `current_plan`, `password`, `status`, `avatar`, `billing`, `email_verified_at`, `remember_token`, `deleted_at`, `created_at`, `updated_at`) VALUES (53, NULL, NULL, NULL, NULL, NULL, NULL, 'phong@demo.com', NULL, NULL, NULL, '$2y$12$Xnt/Xb/TxD2eys2aNPLiSu8puSgxSszlPamZxVxCn0jEvm667.R8q', NULL, NULL, NULL, NULL, NULL, NULL, '2024-09-05 14:37:16', '2024-09-05 14:37:16');
COMMIT;

SET FOREIGN_KEY_CHECKS = 1;
