-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jan 01, 2026 at 02:15 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `aaa`
--

-- --------------------------------------------------------

--
-- Table structure for table `cache`
--

CREATE TABLE `cache` (
  `key` varchar(255) NOT NULL,
  `value` mediumtext NOT NULL,
  `expiration` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cache_locks`
--

CREATE TABLE `cache_locks` (
  `key` varchar(255) NOT NULL,
  `owner` varchar(255) NOT NULL,
  `expiration` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `added_by_user` bigint(20) UNSIGNED DEFAULT NULL,
  `updated_by_user` bigint(20) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `name`, `created_at`, `updated_at`, `added_by_user`, `updated_by_user`) VALUES
(1, 'تجارة', '2025-12-27 19:05:26', '2025-12-27 19:05:26', NULL, NULL),
(2, 'صرافة', '2025-12-27 19:05:26', '2025-12-27 19:05:26', NULL, NULL),
(3, 'سفريات وسياحة', '2025-12-27 19:05:26', '2025-12-27 19:05:26', NULL, NULL),
(4, 'خدمات طبية', '2025-12-27 19:05:26', '2025-12-27 19:05:26', NULL, NULL),
(5, 'نقل وتخليص جمركي', '2025-12-27 19:05:26', '2025-12-27 19:05:26', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `category_client_need`
--

CREATE TABLE `category_client_need` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `client_need_id` bigint(20) UNSIGNED NOT NULL,
  `category_id` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `category_client_need`
--

INSERT INTO `category_client_need` (`id`, `client_need_id`, `category_id`, `created_at`, `updated_at`) VALUES
(1, 1, 1, NULL, NULL),
(2, 1, 4, NULL, NULL),
(3, 1, 3, NULL, NULL),
(4, 1, 2, NULL, NULL),
(5, 1, 5, NULL, NULL),
(6, 2, 1, NULL, NULL),
(7, 2, 4, NULL, NULL),
(8, 2, 3, NULL, NULL),
(9, 2, 2, NULL, NULL),
(10, 2, 5, NULL, NULL),
(11, 3, 1, NULL, NULL),
(12, 3, 4, NULL, NULL),
(13, 3, 3, NULL, NULL),
(14, 3, 2, NULL, NULL),
(15, 3, 5, NULL, NULL),
(16, 4, 2, NULL, NULL),
(17, 5, 2, NULL, NULL),
(18, 6, 1, NULL, NULL),
(19, 7, 3, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `category_designer`
--

CREATE TABLE `category_designer` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `designer_id` bigint(20) UNSIGNED NOT NULL,
  `category_id` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `category_designer`
--

INSERT INTO `category_designer` (`id`, `designer_id`, `category_id`, `created_at`, `updated_at`) VALUES
(1, 1, 1, NULL, NULL),
(2, 1, 4, NULL, NULL),
(3, 1, 3, NULL, NULL),
(4, 1, 2, NULL, NULL),
(5, 1, 5, NULL, NULL),
(6, 2, 1, NULL, NULL),
(7, 2, 4, NULL, NULL),
(8, 2, 3, NULL, NULL),
(9, 2, 2, NULL, NULL),
(10, 2, 5, NULL, NULL),
(11, 3, 2, NULL, NULL),
(12, 3, 3, NULL, NULL),
(13, 3, 1, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `category_tag`
--

CREATE TABLE `category_tag` (
  `category_id` bigint(20) UNSIGNED NOT NULL,
  `tag_id` bigint(20) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `category_tag`
--

INSERT INTO `category_tag` (`category_id`, `tag_id`) VALUES
(1, 1),
(1, 2),
(1, 6),
(2, 1),
(2, 2),
(2, 3),
(2, 4),
(3, 1),
(3, 2),
(3, 5),
(4, 1),
(4, 2),
(5, 1),
(5, 2);

-- --------------------------------------------------------

--
-- Table structure for table `clients`
--

CREATE TABLE `clients` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `company` varchar(255) NOT NULL,
  `client_name` varchar(255) DEFAULT NULL,
  `location_id` bigint(20) UNSIGNED DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `contact_number` varchar(255) DEFAULT NULL,
  `contact_job` varchar(255) DEFAULT NULL,
  `marketing_amount` decimal(10,2) DEFAULT NULL,
  `notified_at` timestamp NULL DEFAULT NULL,
  `suspension_days` int(11) DEFAULT NULL,
  `fixed_designer_id` bigint(20) UNSIGNED DEFAULT NULL,
  `is_credit_allowed` tinyint(1) NOT NULL DEFAULT 0,
  `suspended_at` timestamp NULL DEFAULT NULL,
  `category_id` bigint(20) UNSIGNED NOT NULL,
  `customer_rating_value` tinyint(3) UNSIGNED DEFAULT NULL,
  `rating` int(11) DEFAULT NULL,
  `change_cliche_threshold` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `added_by_user` bigint(20) UNSIGNED DEFAULT NULL,
  `updated_by_user` bigint(20) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `clients`
--

INSERT INTO `clients` (`id`, `status`, `company`, `client_name`, `location_id`, `address`, `contact_number`, `contact_job`, `marketing_amount`, `notified_at`, `suspension_days`, `fixed_designer_id`, `is_credit_allowed`, `suspended_at`, `category_id`, `customer_rating_value`, `rating`, `change_cliche_threshold`, `created_at`, `updated_at`, `added_by_user`, `updated_by_user`) VALUES
(1, 1, 'شركة سنان للصرافة والتحويلات', 'سنان', 5, 'سيئون', NULL, NULL, NULL, NULL, NULL, NULL, 1, NULL, 2, 7, NULL, 60, '2025-12-27 19:22:23', '2025-12-27 19:22:23', 1, 1),
(2, 1, 'شركة ابو عمر القعيطي', 'مدري', 4, 'مأرب', NULL, NULL, NULL, NULL, NULL, NULL, 1, NULL, 2, 8, NULL, 60, '2025-12-27 19:23:30', '2025-12-27 19:23:30', 1, 1),
(3, 1, 'شركة عدنان الحبشي', 'ششش', 4, 'صنعاء', NULL, NULL, NULL, NULL, NULL, NULL, 1, NULL, 2, 8, NULL, 60, '2025-12-27 19:24:07', '2025-12-27 19:24:07', 1, 1),
(4, 1, 'الملك للسفريات', NULL, 4, 'صنعاء', NULL, NULL, NULL, NULL, NULL, NULL, 1, NULL, 3, 7, NULL, 60, '2025-12-27 19:24:57', '2025-12-27 19:24:57', 1, 1),
(5, 1, 'داديه', NULL, 5, 'المكلا', NULL, NULL, NULL, NULL, NULL, NULL, 1, NULL, 1, 7, NULL, 60, '2025-12-27 19:27:57', '2025-12-27 19:27:57', 1, 1);

-- --------------------------------------------------------

--
-- Table structure for table `client_client_need`
--

CREATE TABLE `client_client_need` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `client_id` bigint(20) UNSIGNED NOT NULL,
  `client_need_id` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `client_client_need`
--

INSERT INTO `client_client_need` (`id`, `client_id`, `client_need_id`, `created_at`, `updated_at`) VALUES
(1, 1, 3, NULL, NULL),
(2, 1, 4, NULL, NULL),
(3, 2, 2, NULL, NULL),
(4, 2, 4, NULL, NULL),
(5, 2, 5, NULL, NULL),
(6, 3, 1, NULL, NULL),
(7, 3, 2, NULL, NULL),
(8, 3, 3, NULL, NULL),
(9, 3, 4, NULL, NULL),
(10, 4, 1, NULL, NULL),
(11, 4, 2, NULL, NULL),
(12, 4, 3, NULL, NULL),
(13, 4, 7, NULL, NULL),
(14, 5, 1, NULL, NULL),
(15, 5, 2, NULL, NULL),
(16, 5, 3, NULL, NULL),
(17, 5, 6, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `client_designer`
--

CREATE TABLE `client_designer` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `client_id` bigint(20) UNSIGNED NOT NULL,
  `subscription_id` bigint(20) UNSIGNED DEFAULT NULL,
  `designer_id` bigint(20) UNSIGNED NOT NULL,
  `week_start_date` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `client_designer`
--

INSERT INTO `client_designer` (`id`, `client_id`, `subscription_id`, `designer_id`, `week_start_date`, `created_at`, `updated_at`) VALUES
(1, 4, 1, 1, '2025-12-27', '2025-12-27 19:34:34', '2025-12-27 19:34:34'),
(2, 1, 2, 2, '2025-12-27', '2025-12-27 19:34:34', '2025-12-27 19:34:34'),
(3, 5, 5, 3, '2025-12-27', '2025-12-27 19:34:34', '2025-12-27 19:34:34'),
(4, 2, 3, 1, '2025-12-27', '2025-12-27 19:34:34', '2025-12-27 19:34:34');

-- --------------------------------------------------------

--
-- Table structure for table `client_idea`
--

CREATE TABLE `client_idea` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `client_id` bigint(20) UNSIGNED NOT NULL,
  `idea_id` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `added_by_user` bigint(20) UNSIGNED DEFAULT NULL,
  `updated_by_user` bigint(20) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `client_needs`
--

CREATE TABLE `client_needs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `importance_level` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`importance_level`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `added_by_user` bigint(20) UNSIGNED DEFAULT NULL,
  `updated_by_user` bigint(20) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `client_needs`
--

INSERT INTO `client_needs` (`id`, `name`, `importance_level`, `created_at`, `updated_at`, `added_by_user`, `updated_by_user`) VALUES
(1, 'يحتاج تصاميم جمعة مباركة', '[\"very_high\"]', '2025-12-27 19:18:33', '2025-12-27 19:18:33', 1, 1),
(2, 'يحتاج تصاميم جمعة طيبة', '[\"very_high\"]', '2025-12-27 19:18:56', '2025-12-27 19:18:56', 1, 1),
(3, 'يحتاج تصاميم جمع', '[\"very_high\"]', '2025-12-27 19:19:15', '2025-12-27 19:19:15', 1, 1),
(4, 'يحتاج اعلانات صرافة', '[\"medium\"]', '2025-12-27 19:19:49', '2025-12-27 19:19:49', 1, 1),
(5, 'يحتاج اعلانات تطبيقات صرافة', '[\"medium\"]', '2025-12-27 19:20:06', '2025-12-27 19:20:06', 1, 1),
(6, 'يحتاج تصاميم للمنتجات', '[\"medium\"]', '2025-12-27 19:20:36', '2025-12-27 19:20:36', 1, 1),
(7, 'يحتاج تصاميم سفريات', '[\"medium\"]', '2025-12-27 19:20:53', '2025-12-27 19:20:53', 1, 1);

-- --------------------------------------------------------

--
-- Table structure for table `client_need_tags_group`
--

CREATE TABLE `client_need_tags_group` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `client_need_id` bigint(20) UNSIGNED NOT NULL,
  `tag_id` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `client_need_tags_group`
--

INSERT INTO `client_need_tags_group` (`id`, `client_need_id`, `tag_id`, `created_at`, `updated_at`) VALUES
(1, 1, 1, NULL, NULL),
(2, 2, 2, NULL, NULL),
(3, 3, 1, NULL, NULL),
(4, 3, 2, NULL, NULL),
(5, 4, 3, NULL, NULL),
(6, 5, 4, NULL, NULL),
(7, 6, 6, NULL, NULL),
(8, 7, 5, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `client_tag_distributions`
--

CREATE TABLE `client_tag_distributions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `client_designer_id` bigint(20) UNSIGNED NOT NULL,
  `tag_id` bigint(20) UNSIGNED NOT NULL,
  `distribution_date` date NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `idea_id` bigint(20) UNSIGNED DEFAULT NULL,
  `custom_idea` text DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'pending',
  `designer_notes` text DEFAULT NULL,
  `attachment_path` varchar(255) DEFAULT NULL,
  `reviewer_feedback` text DEFAULT NULL,
  `reviewer_id` bigint(20) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `client_tag_distributions`
--

INSERT INTO `client_tag_distributions` (`id`, `client_designer_id`, `tag_id`, `distribution_date`, `created_at`, `updated_at`, `idea_id`, `custom_idea`, `status`, `designer_notes`, `attachment_path`, `reviewer_feedback`, `reviewer_id`) VALUES
(1, 1, 1, '2025-12-30', '2025-12-27 19:34:46', '2025-12-30 20:45:19', 3, NULL, 'reviewing', '111111111111111', 'designer-submissions/01KDRTDQ07M9Z9X1Q7HHY6WB39.jpeg', '123321', NULL),
(2, 4, 2, '2025-12-27', '2025-12-27 19:34:46', '2025-12-30 20:42:41', 4, NULL, 'sending', '111', 'designer-submissions/01KDGZB5A499X489K4T19F28A1.jpeg', NULL, 2),
(3, 1, 5, '2026-01-01', '2025-12-27 19:34:51', '2025-12-27 19:34:51', NULL, NULL, 'pending', NULL, NULL, NULL, NULL),
(4, 1, 5, '2025-12-29', '2025-12-27 19:34:51', '2025-12-27 19:34:51', NULL, NULL, 'pending', NULL, NULL, NULL, NULL),
(5, 4, 4, '2025-12-31', '2025-12-27 19:34:51', '2025-12-27 19:36:38', NULL, NULL, 'pending', NULL, NULL, NULL, NULL),
(6, 4, 3, '2025-12-27', '2025-12-27 19:34:51', '2025-12-27 19:34:53', 2, NULL, 'pending', NULL, NULL, NULL, NULL),
(7, 4, 3, '2025-12-29', '2025-12-27 19:34:51', '2025-12-27 19:34:53', 1, NULL, 'pending', NULL, NULL, NULL, NULL),
(8, 4, 3, '2025-12-28', '2025-12-27 19:34:51', '2025-12-27 19:34:51', NULL, NULL, 'pending', NULL, NULL, NULL, NULL),
(9, 4, 4, '2025-12-30', '2025-12-27 19:34:51', '2025-12-27 19:34:51', NULL, NULL, 'pending', NULL, NULL, NULL, NULL),
(10, 4, 4, '2026-01-01', '2025-12-27 19:34:51', '2025-12-27 19:34:51', NULL, NULL, 'pending', NULL, NULL, NULL, NULL),
(11, 2, 2, '2025-12-30', '2025-12-27 19:34:58', '2025-12-30 21:20:45', 4, NULL, 'reviewing', NULL, 'designer-submissions/01KDRTT732WPZWY55A0C523DE7.jpeg', NULL, 3),
(12, 2, 3, '2025-12-27', '2025-12-27 19:35:05', '2025-12-27 19:35:08', 1, NULL, 'pending', NULL, NULL, NULL, NULL),
(13, 2, 3, '2025-12-28', '2025-12-27 19:35:05', '2025-12-27 19:35:08', 2, NULL, 'pending', NULL, NULL, NULL, NULL),
(14, 2, 3, '2025-12-29', '2025-12-27 19:35:05', '2025-12-27 19:35:05', NULL, NULL, 'pending', NULL, NULL, NULL, NULL),
(15, 2, 3, '2026-01-01', '2025-12-27 19:35:05', '2025-12-27 19:35:05', NULL, NULL, 'pending', NULL, NULL, NULL, NULL),
(16, 3, 1, '2025-12-30', '2025-12-27 19:35:12', '2025-12-27 19:35:25', 3, NULL, 'pending', NULL, NULL, NULL, NULL),
(17, 3, 6, '2025-12-27', '2025-12-27 19:35:20', '2025-12-27 19:35:20', NULL, NULL, 'pending', NULL, NULL, NULL, NULL),
(18, 3, 6, '2025-12-29', '2025-12-27 19:35:20', '2025-12-27 19:35:20', NULL, NULL, 'pending', NULL, NULL, NULL, NULL),
(19, 3, 6, '2025-12-31', '2025-12-27 19:35:20', '2025-12-27 19:35:20', NULL, NULL, 'pending', NULL, NULL, NULL, NULL),
(20, 3, 6, '2026-01-01', '2025-12-27 19:35:20', '2025-12-27 19:35:20', NULL, NULL, 'pending', NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `currencies`
--

CREATE TABLE `currencies` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `currency` varchar(255) NOT NULL,
  `currency_name` varchar(255) NOT NULL,
  `value` decimal(15,4) NOT NULL,
  `added_by_user` bigint(20) UNSIGNED DEFAULT NULL,
  `updated_by_user` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `currencies`
--

INSERT INTO `currencies` (`id`, `currency`, `currency_name`, `value`, `added_by_user`, `updated_by_user`, `created_at`, `updated_at`) VALUES
(1, 'USD', 'دولار أمريكي', 530.0000, NULL, NULL, '2025-12-27 19:05:26', '2025-12-27 19:05:26');

-- --------------------------------------------------------

--
-- Table structure for table `custody`
--

CREATE TABLE `custody` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `added_by_user` bigint(20) UNSIGNED DEFAULT NULL,
  `updated_by_user` bigint(20) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `designers`
--

CREATE TABLE `designers` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `min_capacity` int(11) DEFAULT NULL,
  `max_capacity` int(11) DEFAULT NULL,
  `weekly_capacity` int(11) DEFAULT NULL,
  `rating` int(11) DEFAULT NULL,
  `rate` decimal(8,2) DEFAULT NULL,
  `shift_hours` int(11) DEFAULT NULL,
  `discipline_score` decimal(5,2) DEFAULT NULL,
  `amount_of_designs` int(11) DEFAULT NULL,
  `freepik_account` varchar(255) DEFAULT NULL,
  `pc_number` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `added_by_user` bigint(20) UNSIGNED DEFAULT NULL,
  `updated_by_user` bigint(20) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `designers`
--

INSERT INTO `designers` (`id`, `user_id`, `min_capacity`, `max_capacity`, `weekly_capacity`, `rating`, `rate`, `shift_hours`, `discipline_score`, `amount_of_designs`, `freepik_account`, `pc_number`, `created_at`, `updated_at`, `added_by_user`, `updated_by_user`) VALUES
(1, 2, 2, 10, NULL, NULL, 9.00, 7, 8.00, NULL, NULL, NULL, '2025-12-27 19:08:15', '2025-12-27 19:08:15', NULL, NULL),
(2, 3, 2, 10, NULL, NULL, 9.00, 8, 8.00, NULL, NULL, NULL, '2025-12-27 19:10:44', '2025-12-27 19:10:44', NULL, NULL),
(3, 4, 2, 10, NULL, NULL, 7.00, 7, 6.00, NULL, NULL, NULL, '2025-12-27 19:11:08', '2025-12-27 19:11:08', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `exports`
--

CREATE TABLE `exports` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `file_disk` varchar(255) NOT NULL,
  `file_name` varchar(255) DEFAULT NULL,
  `exporter` varchar(255) NOT NULL,
  `processed_rows` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `total_rows` int(10) UNSIGNED NOT NULL,
  `successful_rows` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `failed_import_rows`
--

CREATE TABLE `failed_import_rows` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`data`)),
  `import_id` bigint(20) UNSIGNED NOT NULL,
  `validation_error` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `failed_jobs`
--

CREATE TABLE `failed_jobs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `uuid` varchar(255) NOT NULL,
  `connection` text NOT NULL,
  `queue` text NOT NULL,
  `payload` longtext NOT NULL,
  `exception` longtext NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ideas`
--

CREATE TABLE `ideas` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `content` text DEFAULT NULL,
  `description` text DEFAULT NULL,
  `repeat_for_clients` tinyint(1) NOT NULL DEFAULT 0,
  `scheduled_at` datetime DEFAULT NULL,
  `idea_file` text DEFAULT NULL,
  `is_visible_in_generator` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `added_by_user` bigint(20) UNSIGNED DEFAULT NULL,
  `updated_by_user` bigint(20) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `ideas`
--

INSERT INTO `ideas` (`id`, `name`, `content`, `description`, `repeat_for_clients`, `scheduled_at`, `idea_file`, `is_visible_in_generator`, `created_at`, `updated_at`, `added_by_user`, `updated_by_user`) VALUES
(1, 'التحويلات الدولية', 'أرسل المال للخارج خطوة بخطوة', NULL, 0, NULL, NULL, 1, '2025-12-27 19:30:59', '2025-12-27 19:30:59', NULL, NULL),
(2, 'التحويلات المالية', 'توصيل فوري، أمان مطلق! 🚀 ', NULL, 0, NULL, NULL, 1, '2025-12-27 19:32:39', '2025-12-27 19:32:39', NULL, NULL),
(3, 'تصميم جمعة - غزة', 'دعاء عن غزة', NULL, 0, NULL, NULL, 1, '2025-12-27 19:33:45', '2025-12-27 19:33:45', NULL, NULL),
(4, 'تصميم جمع طيبة', 'جمع طيبة', NULL, 0, NULL, NULL, 1, '2025-12-27 19:34:12', '2025-12-27 19:34:12', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `idea_client_blocks`
--

CREATE TABLE `idea_client_blocks` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `client_id` bigint(20) UNSIGNED NOT NULL,
  `idea_id` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `added_by_user` bigint(20) UNSIGNED DEFAULT NULL,
  `updated_by_user` bigint(20) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `imports`
--

CREATE TABLE `imports` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `importer` varchar(255) NOT NULL,
  `processed_rows` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `total_rows` int(10) UNSIGNED NOT NULL,
  `successful_rows` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `jobs`
--

CREATE TABLE `jobs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `queue` varchar(255) NOT NULL,
  `payload` longtext NOT NULL,
  `attempts` tinyint(3) UNSIGNED NOT NULL,
  `reserved_at` int(10) UNSIGNED DEFAULT NULL,
  `available_at` int(10) UNSIGNED NOT NULL,
  `created_at` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `job_batches`
--

CREATE TABLE `job_batches` (
  `id` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `total_jobs` int(11) NOT NULL,
  `pending_jobs` int(11) NOT NULL,
  `failed_jobs` int(11) NOT NULL,
  `failed_job_ids` longtext NOT NULL,
  `options` mediumtext DEFAULT NULL,
  `cancelled_at` int(11) DEFAULT NULL,
  `created_at` int(11) NOT NULL,
  `finished_at` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `locations`
--

CREATE TABLE `locations` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `added_by_user` bigint(20) UNSIGNED DEFAULT NULL,
  `updated_by_user` bigint(20) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `locations`
--

INSERT INTO `locations` (`id`, `name`, `created_at`, `updated_at`, `added_by_user`, `updated_by_user`) VALUES
(1, 'جيبوتي', '2025-12-27 19:05:26', '2025-12-27 19:05:26', NULL, NULL),
(2, 'عمان', '2025-12-27 19:05:26', '2025-12-27 19:05:26', NULL, NULL),
(3, 'السعودية', '2025-12-27 19:05:26', '2025-12-27 19:05:26', NULL, NULL),
(4, 'شمالي', '2025-12-27 19:05:26', '2025-12-27 19:05:26', NULL, NULL),
(5, 'جنوبي', '2025-12-27 19:05:26', '2025-12-27 19:05:26', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `location_idea`
--

CREATE TABLE `location_idea` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `idea_id` bigint(20) UNSIGNED NOT NULL,
  `location_id` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `added_by_user` bigint(20) UNSIGNED DEFAULT NULL,
  `updated_by_user` bigint(20) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `location_idea`
--

INSERT INTO `location_idea` (`id`, `idea_id`, `location_id`, `created_at`, `updated_at`, `added_by_user`, `updated_by_user`) VALUES
(1, 1, 3, NULL, NULL, NULL, NULL),
(2, 1, 5, NULL, NULL, NULL, NULL),
(3, 1, 1, NULL, NULL, NULL, NULL),
(4, 1, 4, NULL, NULL, NULL, NULL),
(5, 1, 2, NULL, NULL, NULL, NULL),
(6, 2, 3, NULL, NULL, NULL, NULL),
(7, 2, 5, NULL, NULL, NULL, NULL),
(8, 2, 1, NULL, NULL, NULL, NULL),
(9, 2, 4, NULL, NULL, NULL, NULL),
(10, 2, 2, NULL, NULL, NULL, NULL),
(11, 3, 3, NULL, NULL, NULL, NULL),
(12, 3, 5, NULL, NULL, NULL, NULL),
(13, 3, 1, NULL, NULL, NULL, NULL),
(14, 3, 4, NULL, NULL, NULL, NULL),
(15, 3, 2, NULL, NULL, NULL, NULL),
(16, 4, 3, NULL, NULL, NULL, NULL),
(17, 4, 5, NULL, NULL, NULL, NULL),
(18, 4, 1, NULL, NULL, NULL, NULL),
(19, 4, 4, NULL, NULL, NULL, NULL),
(20, 4, 2, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `location_tag`
--

CREATE TABLE `location_tag` (
  `location_id` bigint(20) UNSIGNED NOT NULL,
  `tag_id` bigint(20) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `location_tag`
--

INSERT INTO `location_tag` (`location_id`, `tag_id`) VALUES
(1, 1),
(1, 2),
(1, 3),
(1, 4),
(1, 5),
(1, 6),
(2, 1),
(2, 2),
(2, 3),
(2, 4),
(2, 5),
(2, 6),
(3, 1),
(3, 2),
(3, 3),
(3, 4),
(3, 5),
(3, 6),
(4, 1),
(4, 2),
(4, 3),
(4, 4),
(4, 5),
(4, 6),
(5, 1),
(5, 2),
(5, 3),
(5, 4),
(5, 5),
(5, 6);

-- --------------------------------------------------------

--
-- Table structure for table `migrations`
--

CREATE TABLE `migrations` (
  `id` int(10) UNSIGNED NOT NULL,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `migrations`
--

INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES
(1, '0001_01_01_000000_create_users_table', 1),
(2, '0001_01_01_000001_create_cache_table', 1),
(3, '0001_01_01_000002_create_jobs_table', 1),
(4, '2025_05_21_232151_create_permission_tables', 1),
(5, '2025_05_22_004658_create_categories_table', 1),
(6, '2025_05_22_004728_create_designers_table', 1),
(7, '2025_05_22_004758_create_category_designer_table', 1),
(8, '2025_05_22_020859_create_locations_table', 1),
(9, '2025_05_22_021912_create_social_media_table', 1),
(10, '2025_05_22_022945_create_currencies_table', 1),
(11, '2025_05_25_175121_create_custody_table', 1),
(12, '2025_05_25_211051_create_client_needs_table', 1),
(13, '2025_05_25_212736_create_tags_groups_table', 1),
(14, '2025_05_25_213920_create_tags_table', 1),
(15, '2025_06_15_121620_create_category_client_need_table', 1),
(16, '2025_06_15_121717_create_client_need_tags_group_table', 1),
(17, '2025_06_16_212314_create_clients_table', 1),
(18, '2025_07_27_195809_create_client_designer_table', 1),
(19, '2025_07_29_212523_create_ideas_tables', 1),
(20, '2025_08_06_234845_create_imports_table', 1),
(21, '2025_08_06_234846_create_exports_table', 1),
(22, '2025_08_06_234847_create_failed_import_rows_table', 1),
(23, '2025_08_07_000923_create_notifications_table', 1),
(24, '2025_11_17_012430_create_category_tag_table', 1),
(25, '2025_11_17_020955_create_location_tag_table', 1),
(26, '2025_11_17_183432_create_client_client_need_table', 1),
(27, '2025_11_19_004707_create_subscriptions_table', 1),
(28, '2025_11_19_010039_create_subscription_tags_table', 1),
(29, '2025_11_22_181321_add_week_start_date_to_client_designer_table', 1),
(30, '2025_11_22_194012_fix_client_designer_unique_constraint', 1),
(31, '2025_11_22_221622_add_subscription_id_to_client_designer_table', 1),
(32, '2025_12_02_000000_create_client_tag_distributions_table', 1),
(33, '2025_12_09_003052_add_idea_id_to_client_tag_distributions_table', 1),
(34, '2025_12_09_184332_add_custom_idea_to_client_tag_distributions_table', 1),
(35, '2025_12_10_203845_add_fixed_designer_id_to_clients_table', 1),
(36, '2025_12_14_191047_change_weekly_day_to_json_in_tags_table', 1),
(37, '2025_12_21_211446_add_status_to_client_tag_distributions_table', 1),
(38, '2025_12_21_230105_add_workflow_columns_to_client_tag_distributions_table', 1),
(39, '2025_12_30_233730_add_reviewer_id_to_client_tag_distributions_table', 2);

-- --------------------------------------------------------

--
-- Table structure for table `model_has_permissions`
--

CREATE TABLE `model_has_permissions` (
  `permission_id` bigint(20) UNSIGNED NOT NULL,
  `model_type` varchar(255) NOT NULL,
  `model_id` bigint(20) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `model_has_roles`
--

CREATE TABLE `model_has_roles` (
  `role_id` bigint(20) UNSIGNED NOT NULL,
  `model_type` varchar(255) NOT NULL,
  `model_id` bigint(20) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `model_has_roles`
--

INSERT INTO `model_has_roles` (`role_id`, `model_type`, `model_id`) VALUES
(1, 'App\\Models\\User', 1),
(1, 'App\\Models\\User', 2);

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` char(36) NOT NULL,
  `type` varchar(255) NOT NULL,
  `notifiable_type` varchar(255) NOT NULL,
  `notifiable_id` bigint(20) UNSIGNED NOT NULL,
  `data` text NOT NULL,
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `password_reset_tokens`
--

CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `permissions`
--

CREATE TABLE `permissions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `guard_name` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `permissions`
--

INSERT INTO `permissions` (`id`, `name`, `guard_name`, `created_at`, `updated_at`) VALUES
(1, 'view_any_users', 'web', '2025-12-30 22:00:11', '2025-12-30 22:00:11'),
(2, 'view_users', 'web', '2025-12-30 22:00:11', '2025-12-30 22:00:11'),
(3, 'create_users', 'web', '2025-12-30 22:00:11', '2025-12-30 22:00:11'),
(4, 'update_users', 'web', '2025-12-30 22:00:11', '2025-12-30 22:00:11'),
(5, 'delete_users', 'web', '2025-12-30 22:00:11', '2025-12-30 22:00:11'),
(6, 'view_any_category', 'web', '2025-12-30 22:00:11', '2025-12-30 22:00:11'),
(7, 'view_category', 'web', '2025-12-30 22:00:11', '2025-12-30 22:00:11'),
(8, 'create_category', 'web', '2025-12-30 22:00:11', '2025-12-30 22:00:11'),
(9, 'update_category', 'web', '2025-12-30 22:00:11', '2025-12-30 22:00:11'),
(10, 'delete_category', 'web', '2025-12-30 22:00:11', '2025-12-30 22:00:11'),
(11, 'view_any_currency', 'web', '2025-12-30 22:00:11', '2025-12-30 22:00:11'),
(12, 'view_currency', 'web', '2025-12-30 22:00:11', '2025-12-30 22:00:11'),
(13, 'create_currency', 'web', '2025-12-30 22:00:11', '2025-12-30 22:00:11'),
(14, 'update_currency', 'web', '2025-12-30 22:00:11', '2025-12-30 22:00:11'),
(15, 'delete_currency', 'web', '2025-12-30 22:00:11', '2025-12-30 22:00:11'),
(16, 'view_any_location', 'web', '2025-12-30 22:00:11', '2025-12-30 22:00:11'),
(17, 'view_location', 'web', '2025-12-30 22:00:11', '2025-12-30 22:00:11'),
(18, 'create_location', 'web', '2025-12-30 22:00:11', '2025-12-30 22:00:11'),
(19, 'update_location', 'web', '2025-12-30 22:00:11', '2025-12-30 22:00:11'),
(20, 'delete_location', 'web', '2025-12-30 22:00:11', '2025-12-30 22:00:11'),
(21, 'view_any_tag', 'web', '2025-12-30 22:00:11', '2025-12-30 22:00:11'),
(22, 'view_tag', 'web', '2025-12-30 22:00:11', '2025-12-30 22:00:11'),
(23, 'create_tag', 'web', '2025-12-30 22:00:11', '2025-12-30 22:00:11'),
(24, 'update_tag', 'web', '2025-12-30 22:00:11', '2025-12-30 22:00:11'),
(25, 'delete_tag', 'web', '2025-12-30 22:00:11', '2025-12-30 22:00:11'),
(26, 'view_any_tag_group', 'web', '2025-12-30 22:00:11', '2025-12-30 22:00:11'),
(27, 'view_tag_group', 'web', '2025-12-30 22:00:11', '2025-12-30 22:00:11'),
(28, 'create_tag_group', 'web', '2025-12-30 22:00:11', '2025-12-30 22:00:11'),
(29, 'update_tag_group', 'web', '2025-12-30 22:00:11', '2025-12-30 22:00:11'),
(30, 'delete_tag_group', 'web', '2025-12-30 22:00:11', '2025-12-30 22:00:11'),
(31, 'view_any_client', 'web', '2025-12-30 22:00:11', '2025-12-30 22:00:11'),
(32, 'view_client', 'web', '2025-12-30 22:00:11', '2025-12-30 22:00:11'),
(33, 'create_client', 'web', '2025-12-30 22:00:11', '2025-12-30 22:00:11'),
(34, 'update_client', 'web', '2025-12-30 22:00:11', '2025-12-30 22:00:11'),
(35, 'delete_client', 'web', '2025-12-30 22:00:11', '2025-12-30 22:00:11'),
(36, 'view_any_client_need', 'web', '2025-12-30 22:00:11', '2025-12-30 22:00:11'),
(37, 'view_client_need', 'web', '2025-12-30 22:00:11', '2025-12-30 22:00:11'),
(38, 'create_client_need', 'web', '2025-12-30 22:00:11', '2025-12-30 22:00:11'),
(39, 'update_client_need', 'web', '2025-12-30 22:00:11', '2025-12-30 22:00:11'),
(40, 'delete_client_need', 'web', '2025-12-30 22:00:11', '2025-12-30 22:00:11'),
(41, 'view_any_designer', 'web', '2025-12-30 22:00:11', '2025-12-30 22:00:11'),
(42, 'view_designer', 'web', '2025-12-30 22:00:11', '2025-12-30 22:00:11'),
(43, 'create_designer', 'web', '2025-12-30 22:00:11', '2025-12-30 22:00:11'),
(44, 'update_designer', 'web', '2025-12-30 22:00:11', '2025-12-30 22:00:11'),
(45, 'delete_designer', 'web', '2025-12-30 22:00:11', '2025-12-30 22:00:11'),
(46, 'view_any_idea', 'web', '2025-12-30 22:00:11', '2025-12-30 22:00:11'),
(47, 'view_idea', 'web', '2025-12-30 22:00:11', '2025-12-30 22:00:11'),
(48, 'create_idea', 'web', '2025-12-30 22:00:11', '2025-12-30 22:00:11'),
(49, 'update_idea', 'web', '2025-12-30 22:00:11', '2025-12-30 22:00:11'),
(50, 'delete_idea', 'web', '2025-12-30 22:00:11', '2025-12-30 22:00:11'),
(51, 'view_any_custody', 'web', '2025-12-30 22:00:11', '2025-12-30 22:00:11'),
(52, 'view_custody', 'web', '2025-12-30 22:00:11', '2025-12-30 22:00:11'),
(53, 'create_custody', 'web', '2025-12-30 22:00:11', '2025-12-30 22:00:11'),
(54, 'update_custody', 'web', '2025-12-30 22:00:11', '2025-12-30 22:00:11'),
(55, 'delete_custody', 'web', '2025-12-30 22:00:11', '2025-12-30 22:00:11'),
(56, 'view_any_social_media', 'web', '2025-12-30 22:00:11', '2025-12-30 22:00:11'),
(57, 'view_social_media', 'web', '2025-12-30 22:00:11', '2025-12-30 22:00:11'),
(58, 'create_social_media', 'web', '2025-12-30 22:00:11', '2025-12-30 22:00:11'),
(59, 'update_social_media', 'web', '2025-12-30 22:00:11', '2025-12-30 22:00:11'),
(60, 'delete_social_media', 'web', '2025-12-30 22:00:11', '2025-12-30 22:00:11'),
(61, 'view_designer_dashboard', 'web', '2025-12-30 22:00:11', '2025-12-30 22:00:11'),
(62, 'view_reviewer_dashboard', 'web', '2025-12-30 22:00:11', '2025-12-30 22:00:11'),
(63, 'view_designer_distribution', 'web', '2025-12-30 22:00:11', '2025-12-30 22:00:11'),
(64, 'view_tag_distribution', 'web', '2025-12-30 22:00:11', '2025-12-30 22:00:11');

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `guard_name` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`id`, `name`, `guard_name`, `created_at`, `updated_at`) VALUES
(1, 'admin', 'web', '2025-12-30 21:36:14', '2025-12-30 21:36:14'),
(2, 'hr', 'web', '2025-12-30 21:36:14', '2025-12-30 21:36:14'),
(3, 'accountant', 'web', '2025-12-30 21:36:14', '2025-12-30 21:36:14'),
(4, 'designer', 'web', '2025-12-30 21:36:14', '2025-12-30 21:36:14'),
(5, 'reviewer', 'web', '2025-12-30 21:36:14', '2025-12-30 21:36:14'),
(6, 'supervisor', 'web', '2025-12-30 21:36:14', '2025-12-30 21:36:14'),
(7, 'social_media', 'web', '2025-12-30 21:36:14', '2025-12-30 21:36:14'),
(8, 'content_creator', 'web', '2025-12-30 21:36:14', '2025-12-30 21:36:14');

-- --------------------------------------------------------

--
-- Table structure for table `role_has_permissions`
--

CREATE TABLE `role_has_permissions` (
  `permission_id` bigint(20) UNSIGNED NOT NULL,
  `role_id` bigint(20) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `role_has_permissions`
--

INSERT INTO `role_has_permissions` (`permission_id`, `role_id`) VALUES
(1, 1),
(1, 2),
(1, 6),
(2, 1),
(2, 2),
(2, 6),
(3, 1),
(3, 2),
(4, 1),
(4, 2),
(5, 1),
(5, 2),
(6, 1),
(6, 8),
(7, 1),
(7, 8),
(8, 1),
(8, 8),
(9, 1),
(9, 8),
(10, 1),
(10, 8),
(11, 1),
(11, 8),
(12, 1),
(12, 8),
(13, 1),
(13, 8),
(14, 1),
(14, 8),
(15, 1),
(15, 8),
(16, 1),
(16, 8),
(17, 1),
(17, 8),
(18, 1),
(18, 8),
(19, 1),
(19, 8),
(20, 1),
(20, 8),
(21, 1),
(21, 8),
(22, 1),
(22, 8),
(23, 1),
(23, 8),
(24, 1),
(24, 8),
(25, 1),
(25, 8),
(26, 1),
(26, 8),
(27, 1),
(27, 8),
(28, 1),
(28, 8),
(29, 1),
(29, 8),
(30, 1),
(30, 8),
(31, 1),
(31, 5),
(31, 6),
(32, 1),
(32, 6),
(33, 1),
(33, 6),
(34, 1),
(34, 6),
(35, 1),
(36, 1),
(37, 1),
(38, 1),
(39, 1),
(40, 1),
(41, 1),
(41, 2),
(41, 6),
(42, 1),
(42, 2),
(42, 6),
(43, 1),
(43, 2),
(43, 6),
(44, 1),
(44, 2),
(44, 6),
(45, 1),
(45, 2),
(45, 6),
(46, 1),
(46, 8),
(47, 1),
(47, 8),
(48, 1),
(48, 8),
(49, 1),
(49, 8),
(50, 1),
(50, 8),
(51, 1),
(51, 2),
(52, 1),
(52, 2),
(53, 1),
(53, 2),
(54, 1),
(54, 2),
(55, 1),
(55, 2),
(56, 1),
(56, 6),
(56, 7),
(56, 8),
(57, 1),
(57, 6),
(57, 7),
(57, 8),
(58, 1),
(58, 6),
(58, 8),
(59, 1),
(59, 6),
(59, 8),
(60, 1),
(60, 6),
(60, 8),
(61, 1),
(61, 4),
(62, 1),
(62, 5),
(62, 6),
(63, 1),
(63, 2),
(63, 6),
(64, 1),
(64, 2),
(64, 6);

-- --------------------------------------------------------

--
-- Table structure for table `sessions`
--

CREATE TABLE `sessions` (
  `id` varchar(255) NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `payload` longtext NOT NULL,
  `last_activity` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `sessions`
--

INSERT INTO `sessions` (`id`, `user_id`, `ip_address`, `user_agent`, `payload`, `last_activity`) VALUES
('463syBodhgnwyPpXjfS9vmk5WUPmTzN84JIi04UQ', 2, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 'YTo2OntzOjY6Il90b2tlbiI7czo0MDoiRnN1SlFWR1BXbjE5WXA2SUpmMlluVVo4dDFmSW04MXpwQURxNVVLZyI7czozOiJ1cmwiO2E6MDp7fXM6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjQ2OiJodHRwOi8vbG9jYWxob3N0OjgwMDAvYWRtaW4vcmV2aWV3ZXItZGFzaGJvYXJkIjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319czo1MDoibG9naW5fd2ViXzU5YmEzNmFkZGMyYjJmOTQwMTU4MGYwMTRjN2Y1OGVhNGUzMDk4OWQiO2k6MjtzOjE3OiJwYXNzd29yZF9oYXNoX3dlYiI7czo2MDoiJDJ5JDEyJEl1by80MmRZR24zZzVPS3NZSmZPWHVVdDBKQnM4b3B3SUlKYWZOTVhqMFBwSDZWQ0xxWjFHIjt9', 1767230027),
('jjOizrd30SGc6ZYjxyDGn0VCVogCyhW3WDtoco2R', 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 'YTo2OntzOjY6Il90b2tlbiI7czo0MDoiQ0JRN3QxWkV6MDYxVVBnQ3dLWmZPbEtGeWtheDFPS0VlbnhzdE9BUiI7czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319czo5OiJfcHJldmlvdXMiO2E6MTp7czozOiJ1cmwiO3M6NDA6Imh0dHA6Ly9sb2NhbGhvc3Q6ODAwMC9hZG1pbi91c2Vycy8yL2VkaXQiO31zOjUwOiJsb2dpbl93ZWJfNTliYTM2YWRkYzJiMmY5NDAxNTgwZjAxNGM3ZjU4ZWE0ZTMwOTg5ZCI7aToxO3M6MTc6InBhc3N3b3JkX2hhc2hfd2ViIjtzOjYwOiIkMnkkMTIkMllIanNqUHFQdVZPNUUyVFNvT0tQTy9BaGVOVHQ0UjBISWliVkNmMXdUaDhZR2dEVjR1ajYiO3M6ODoiZmlsYW1lbnQiO2E6MDp7fX0=', 1767230032);

-- --------------------------------------------------------

--
-- Table structure for table `social_media`
--

CREATE TABLE `social_media` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `added_by_user` bigint(20) UNSIGNED DEFAULT NULL,
  `updated_by_user` bigint(20) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `subscriptions`
--

CREATE TABLE `subscriptions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `client_id` bigint(20) UNSIGNED NOT NULL,
  `is_main` tinyint(1) NOT NULL,
  `designs_count` int(11) NOT NULL,
  `start_date` date NOT NULL,
  `subscription_type` enum('weekly','monthly','yearly') NOT NULL,
  `next_renewal_date` date DEFAULT NULL,
  `payment_amount` decimal(10,2) NOT NULL,
  `currency_id` bigint(20) UNSIGNED NOT NULL,
  `payment_type` enum('advance','deferred') NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `subscriptions`
--

INSERT INTO `subscriptions` (`id`, `client_id`, `is_main`, `designs_count`, `start_date`, `subscription_type`, `next_renewal_date`, `payment_amount`, `currency_id`, `payment_type`, `created_at`, `updated_at`) VALUES
(1, 4, 1, 3, '2025-12-28', 'monthly', NULL, 100.00, 1, 'advance', '2025-12-27 19:25:29', '2025-12-27 19:25:29'),
(2, 1, 1, 5, '2025-12-28', 'monthly', NULL, 150.00, 1, 'advance', '2025-12-27 19:25:57', '2025-12-27 19:25:57'),
(3, 2, 1, 7, '2025-12-28', 'monthly', NULL, 300.00, 1, 'advance', '2025-12-27 19:26:26', '2025-12-27 19:26:26'),
(4, 3, 1, 7, '2025-12-28', 'monthly', NULL, 300.00, 1, 'advance', '2025-12-27 19:27:02', '2025-12-27 19:27:02'),
(5, 5, 1, 5, '2025-12-28', 'monthly', NULL, 150.00, 1, 'advance', '2025-12-27 19:28:21', '2025-12-27 19:28:21');

-- --------------------------------------------------------

--
-- Table structure for table `subscription_tags`
--

CREATE TABLE `subscription_tags` (
  `subscription_id` bigint(20) UNSIGNED NOT NULL,
  `tag_id` bigint(20) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tags`
--

CREATE TABLE `tags` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `importance` enum('veryhigh','high','medium','low') NOT NULL DEFAULT 'medium',
  `tag_group_id` bigint(20) UNSIGNED NOT NULL,
  `is_repetition` tinyint(1) NOT NULL DEFAULT 0,
  `repetition` enum('weekly','monthly','yearly') DEFAULT NULL,
  `weekly_times` int(11) DEFAULT NULL,
  `monthly_times` int(11) DEFAULT NULL,
  `yearly_times` int(11) DEFAULT NULL,
  `is_there_date_for_sending` tinyint(1) NOT NULL DEFAULT 0,
  `date_for_sending_yearly` date DEFAULT NULL,
  `weekly_day` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`weekly_day`)),
  `weekly_time` time DEFAULT NULL,
  `weekly_time_sm` time DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `added_by_user` bigint(20) UNSIGNED DEFAULT NULL,
  `updated_by_user` bigint(20) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tags`
--

INSERT INTO `tags` (`id`, `name`, `importance`, `tag_group_id`, `is_repetition`, `repetition`, `weekly_times`, `monthly_times`, `yearly_times`, `is_there_date_for_sending`, `date_for_sending_yearly`, `weekly_day`, `weekly_time`, `weekly_time_sm`, `created_at`, `updated_at`, `added_by_user`, `updated_by_user`) VALUES
(1, 'جمعة مباركة', 'veryhigh', 2, 0, NULL, NULL, NULL, NULL, 1, NULL, '[\"Thursday\"]', '15:00:00', '17:00:00', '2025-12-27 19:12:57', '2025-12-27 19:12:57', NULL, NULL),
(2, 'جمعة طيبة', 'veryhigh', 2, 1, 'weekly', 1, NULL, NULL, 1, NULL, '[\"Thursday\"]', '15:00:00', '17:00:00', '2025-12-27 19:13:43', '2025-12-27 19:13:43', NULL, NULL),
(3, 'تصاميم اعلانات صرافة', 'medium', 4, 1, 'weekly', 3, NULL, NULL, 1, NULL, '[\"Saturday\",\"Monday\",\"Wednesday\"]', '06:00:00', '04:00:00', '2025-12-27 19:15:06', '2025-12-27 19:15:06', NULL, NULL),
(4, 'تصاميم اعلانات تطبيقات الصرافة', 'medium', 4, 0, NULL, NULL, NULL, NULL, 1, NULL, '[]', '06:00:00', '16:00:00', '2025-12-27 19:16:12', '2025-12-27 19:16:12', NULL, NULL),
(5, 'تصاميم اعلانات سفريات', 'medium', 1, 0, NULL, NULL, NULL, NULL, 1, NULL, '[]', '06:00:00', '16:00:00', '2025-12-27 19:17:00', '2025-12-27 19:17:00', NULL, NULL),
(6, 'تصاميم اعلانات تجار المواد الغذائية', 'medium', 5, 0, NULL, NULL, NULL, NULL, 1, NULL, '[]', '06:00:00', '16:00:00', '2025-12-27 19:17:52', '2025-12-27 19:17:52', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `tags_groups`
--

CREATE TABLE `tags_groups` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `added_by_user` bigint(20) UNSIGNED DEFAULT NULL,
  `updated_by_user` bigint(20) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tags_groups`
--

INSERT INTO `tags_groups` (`id`, `name`, `created_at`, `updated_at`, `added_by_user`, `updated_by_user`) VALUES
(1, 'اعلانات سفريات وسياحة', '2025-12-27 19:05:26', '2025-12-27 19:05:26', NULL, NULL),
(2, 'جمعة', '2025-12-27 19:05:26', '2025-12-27 19:05:26', NULL, NULL),
(3, 'مناسبات', '2025-12-27 19:05:26', '2025-12-27 19:05:26', NULL, NULL),
(4, 'اعلانات صرافة', '2025-12-27 19:05:26', '2025-12-27 19:05:26', NULL, NULL),
(5, 'اعلانات منتجات', '2025-12-27 19:05:26', '2025-12-27 19:05:26', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `tag_idea`
--

CREATE TABLE `tag_idea` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `idea_id` bigint(20) UNSIGNED NOT NULL,
  `tag_id` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `added_by_user` bigint(20) UNSIGNED DEFAULT NULL,
  `updated_by_user` bigint(20) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tag_idea`
--

INSERT INTO `tag_idea` (`id`, `idea_id`, `tag_id`, `created_at`, `updated_at`, `added_by_user`, `updated_by_user`) VALUES
(1, 1, 3, NULL, NULL, NULL, NULL),
(2, 2, 3, NULL, NULL, NULL, NULL),
(3, 3, 1, NULL, NULL, NULL, NULL),
(4, 4, 2, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `username` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `work_phone_number` varchar(255) DEFAULT NULL,
  `personal_phone_number` varchar(255) DEFAULT NULL,
  `profile_image` varchar(255) DEFAULT NULL,
  `hire_date` date DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `username`, `email`, `email_verified_at`, `password`, `work_phone_number`, `personal_phone_number`, `profile_image`, `hire_date`, `status`, `remember_token`, `created_at`, `updated_at`) VALUES
(1, 'عبدالله الفقيه', 'admin', 'admin@admin.com', NULL, '$2y$12$2YHjsjPqPuVO5E2TSoOKPO/AheNTt4R0HIibVCf1wTh8YGgDV4uj6', '777777777', '777777777', 'profile_images/me.jpg', '2025-12-26', 1, NULL, '2025-12-27 19:05:26', '2025-12-27 19:07:09'),
(2, 'أحمد فرحان', 'ahmed', 'ahmed@test.com', NULL, '$2y$12$Iuo/42dYGn3g5OKsYJfOXuUt0JBs8opwIIJafNMXj0PpH6VCLqZ1G', NULL, NULL, 'profile_images/pngtree-man-in-business-suit-png-image_16142564.png', '2025-12-28', 1, NULL, '2025-12-27 19:06:30', '2025-12-31 21:06:05'),
(3, 'أسامة غراب', 'osama', 'osama@test.com', NULL, '$2y$12$DUI6r6LRRet4cjLFnRdby.cjGsI8GoYTaWFNPPtHy2aGyX9VBV2bm', NULL, NULL, NULL, '2025-12-28', 1, NULL, '2025-12-27 19:09:21', '2025-12-27 19:09:21'),
(4, 'عبدالله الحطوار', 'abood', 'abood@test.com', NULL, '$2y$12$AcueUIbFrLDjj0dSnTMc/O6ZWPWZkN2an5xQIh5tVk9LdLSknolO2', NULL, NULL, NULL, '2025-12-27', 1, NULL, '2025-12-27 19:10:06', '2025-12-27 19:10:06');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `cache`
--
ALTER TABLE `cache`
  ADD PRIMARY KEY (`key`);

--
-- Indexes for table `cache_locks`
--
ALTER TABLE `cache_locks`
  ADD PRIMARY KEY (`key`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD KEY `categories_added_by_user_foreign` (`added_by_user`),
  ADD KEY `categories_updated_by_user_foreign` (`updated_by_user`);

--
-- Indexes for table `category_client_need`
--
ALTER TABLE `category_client_need`
  ADD PRIMARY KEY (`id`),
  ADD KEY `category_client_need_client_need_id_foreign` (`client_need_id`),
  ADD KEY `category_client_need_category_id_foreign` (`category_id`);

--
-- Indexes for table `category_designer`
--
ALTER TABLE `category_designer`
  ADD PRIMARY KEY (`id`),
  ADD KEY `category_designer_designer_id_foreign` (`designer_id`),
  ADD KEY `category_designer_category_id_foreign` (`category_id`);

--
-- Indexes for table `category_tag`
--
ALTER TABLE `category_tag`
  ADD UNIQUE KEY `category_tag_category_id_tag_id_unique` (`category_id`,`tag_id`),
  ADD KEY `category_tag_tag_id_foreign` (`tag_id`);

--
-- Indexes for table `clients`
--
ALTER TABLE `clients`
  ADD PRIMARY KEY (`id`),
  ADD KEY `clients_location_id_foreign` (`location_id`),
  ADD KEY `clients_category_id_foreign` (`category_id`),
  ADD KEY `clients_added_by_user_foreign` (`added_by_user`),
  ADD KEY `clients_updated_by_user_foreign` (`updated_by_user`),
  ADD KEY `clients_fixed_designer_id_foreign` (`fixed_designer_id`);

--
-- Indexes for table `client_client_need`
--
ALTER TABLE `client_client_need`
  ADD PRIMARY KEY (`id`),
  ADD KEY `client_client_need_client_id_foreign` (`client_id`),
  ADD KEY `client_client_need_client_need_id_foreign` (`client_need_id`);

--
-- Indexes for table `client_designer`
--
ALTER TABLE `client_designer`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `sub_week_unique` (`subscription_id`,`week_start_date`),
  ADD KEY `client_designer_designer_id_foreign` (`designer_id`),
  ADD KEY `client_designer_client_id_foreign` (`client_id`);

--
-- Indexes for table `client_idea`
--
ALTER TABLE `client_idea`
  ADD PRIMARY KEY (`id`),
  ADD KEY `client_idea_client_id_foreign` (`client_id`),
  ADD KEY `client_idea_idea_id_foreign` (`idea_id`),
  ADD KEY `client_idea_added_by_user_foreign` (`added_by_user`),
  ADD KEY `client_idea_updated_by_user_foreign` (`updated_by_user`);

--
-- Indexes for table `client_needs`
--
ALTER TABLE `client_needs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `client_needs_added_by_user_foreign` (`added_by_user`),
  ADD KEY `client_needs_updated_by_user_foreign` (`updated_by_user`);

--
-- Indexes for table `client_need_tags_group`
--
ALTER TABLE `client_need_tags_group`
  ADD PRIMARY KEY (`id`),
  ADD KEY `client_need_tags_group_client_need_id_foreign` (`client_need_id`),
  ADD KEY `client_need_tags_group_tag_id_foreign` (`tag_id`);

--
-- Indexes for table `client_tag_distributions`
--
ALTER TABLE `client_tag_distributions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `client_tag_distributions_client_designer_id_foreign` (`client_designer_id`),
  ADD KEY `client_tag_distributions_tag_id_foreign` (`tag_id`),
  ADD KEY `client_tag_distributions_idea_id_foreign` (`idea_id`),
  ADD KEY `client_tag_distributions_reviewer_id_foreign` (`reviewer_id`);

--
-- Indexes for table `currencies`
--
ALTER TABLE `currencies`
  ADD PRIMARY KEY (`id`),
  ADD KEY `currencies_added_by_user_foreign` (`added_by_user`),
  ADD KEY `currencies_updated_by_user_foreign` (`updated_by_user`);

--
-- Indexes for table `custody`
--
ALTER TABLE `custody`
  ADD PRIMARY KEY (`id`),
  ADD KEY `custody_user_id_foreign` (`user_id`),
  ADD KEY `custody_added_by_user_foreign` (`added_by_user`),
  ADD KEY `custody_updated_by_user_foreign` (`updated_by_user`);

--
-- Indexes for table `designers`
--
ALTER TABLE `designers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `designers_user_id_foreign` (`user_id`),
  ADD KEY `designers_added_by_user_foreign` (`added_by_user`),
  ADD KEY `designers_updated_by_user_foreign` (`updated_by_user`);

--
-- Indexes for table `exports`
--
ALTER TABLE `exports`
  ADD PRIMARY KEY (`id`),
  ADD KEY `exports_user_id_foreign` (`user_id`);

--
-- Indexes for table `failed_import_rows`
--
ALTER TABLE `failed_import_rows`
  ADD PRIMARY KEY (`id`),
  ADD KEY `failed_import_rows_import_id_foreign` (`import_id`);

--
-- Indexes for table `failed_jobs`
--
ALTER TABLE `failed_jobs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`);

--
-- Indexes for table `ideas`
--
ALTER TABLE `ideas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `ideas_added_by_user_foreign` (`added_by_user`),
  ADD KEY `ideas_updated_by_user_foreign` (`updated_by_user`);

--
-- Indexes for table `idea_client_blocks`
--
ALTER TABLE `idea_client_blocks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idea_client_blocks_client_id_foreign` (`client_id`),
  ADD KEY `idea_client_blocks_idea_id_foreign` (`idea_id`),
  ADD KEY `idea_client_blocks_added_by_user_foreign` (`added_by_user`),
  ADD KEY `idea_client_blocks_updated_by_user_foreign` (`updated_by_user`);

--
-- Indexes for table `imports`
--
ALTER TABLE `imports`
  ADD PRIMARY KEY (`id`),
  ADD KEY `imports_user_id_foreign` (`user_id`);

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
-- Indexes for table `locations`
--
ALTER TABLE `locations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `locations_added_by_user_foreign` (`added_by_user`),
  ADD KEY `locations_updated_by_user_foreign` (`updated_by_user`);

--
-- Indexes for table `location_idea`
--
ALTER TABLE `location_idea`
  ADD PRIMARY KEY (`id`),
  ADD KEY `location_idea_idea_id_foreign` (`idea_id`),
  ADD KEY `location_idea_location_id_foreign` (`location_id`),
  ADD KEY `location_idea_added_by_user_foreign` (`added_by_user`),
  ADD KEY `location_idea_updated_by_user_foreign` (`updated_by_user`);

--
-- Indexes for table `location_tag`
--
ALTER TABLE `location_tag`
  ADD UNIQUE KEY `location_tag_location_id_tag_id_unique` (`location_id`,`tag_id`),
  ADD KEY `location_tag_tag_id_foreign` (`tag_id`);

--
-- Indexes for table `migrations`
--
ALTER TABLE `migrations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `model_has_permissions`
--
ALTER TABLE `model_has_permissions`
  ADD PRIMARY KEY (`permission_id`,`model_id`,`model_type`),
  ADD KEY `model_has_permissions_model_id_model_type_index` (`model_id`,`model_type`);

--
-- Indexes for table `model_has_roles`
--
ALTER TABLE `model_has_roles`
  ADD PRIMARY KEY (`role_id`,`model_id`,`model_type`),
  ADD KEY `model_has_roles_model_id_model_type_index` (`model_id`,`model_type`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `notifications_notifiable_type_notifiable_id_index` (`notifiable_type`,`notifiable_id`);

--
-- Indexes for table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  ADD PRIMARY KEY (`email`);

--
-- Indexes for table `permissions`
--
ALTER TABLE `permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `permissions_name_guard_name_unique` (`name`,`guard_name`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `roles_name_guard_name_unique` (`name`,`guard_name`);

--
-- Indexes for table `role_has_permissions`
--
ALTER TABLE `role_has_permissions`
  ADD PRIMARY KEY (`permission_id`,`role_id`),
  ADD KEY `role_has_permissions_role_id_foreign` (`role_id`);

--
-- Indexes for table `sessions`
--
ALTER TABLE `sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sessions_user_id_index` (`user_id`),
  ADD KEY `sessions_last_activity_index` (`last_activity`);

--
-- Indexes for table `social_media`
--
ALTER TABLE `social_media`
  ADD PRIMARY KEY (`id`),
  ADD KEY `social_media_added_by_user_foreign` (`added_by_user`),
  ADD KEY `social_media_updated_by_user_foreign` (`updated_by_user`);

--
-- Indexes for table `subscriptions`
--
ALTER TABLE `subscriptions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `subscriptions_client_id_foreign` (`client_id`),
  ADD KEY `subscriptions_currency_id_foreign` (`currency_id`);

--
-- Indexes for table `subscription_tags`
--
ALTER TABLE `subscription_tags`
  ADD UNIQUE KEY `subscription_tags_subscription_id_tag_id_unique` (`subscription_id`,`tag_id`),
  ADD KEY `subscription_tags_tag_id_foreign` (`tag_id`);

--
-- Indexes for table `tags`
--
ALTER TABLE `tags`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tags_tag_group_id_foreign` (`tag_group_id`),
  ADD KEY `tags_added_by_user_foreign` (`added_by_user`),
  ADD KEY `tags_updated_by_user_foreign` (`updated_by_user`);

--
-- Indexes for table `tags_groups`
--
ALTER TABLE `tags_groups`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tags_groups_added_by_user_foreign` (`added_by_user`),
  ADD KEY `tags_groups_updated_by_user_foreign` (`updated_by_user`);

--
-- Indexes for table `tag_idea`
--
ALTER TABLE `tag_idea`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tag_idea_idea_id_foreign` (`idea_id`),
  ADD KEY `tag_idea_tag_id_foreign` (`tag_id`),
  ADD KEY `tag_idea_added_by_user_foreign` (`added_by_user`),
  ADD KEY `tag_idea_updated_by_user_foreign` (`updated_by_user`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `users_username_unique` (`username`),
  ADD UNIQUE KEY `users_email_unique` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `category_client_need`
--
ALTER TABLE `category_client_need`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `category_designer`
--
ALTER TABLE `category_designer`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `clients`
--
ALTER TABLE `clients`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `client_client_need`
--
ALTER TABLE `client_client_need`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `client_designer`
--
ALTER TABLE `client_designer`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `client_idea`
--
ALTER TABLE `client_idea`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `client_needs`
--
ALTER TABLE `client_needs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `client_need_tags_group`
--
ALTER TABLE `client_need_tags_group`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `client_tag_distributions`
--
ALTER TABLE `client_tag_distributions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `currencies`
--
ALTER TABLE `currencies`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `custody`
--
ALTER TABLE `custody`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `designers`
--
ALTER TABLE `designers`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `exports`
--
ALTER TABLE `exports`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `failed_import_rows`
--
ALTER TABLE `failed_import_rows`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `failed_jobs`
--
ALTER TABLE `failed_jobs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ideas`
--
ALTER TABLE `ideas`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `idea_client_blocks`
--
ALTER TABLE `idea_client_blocks`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `imports`
--
ALTER TABLE `imports`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `jobs`
--
ALTER TABLE `jobs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `locations`
--
ALTER TABLE `locations`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `location_idea`
--
ALTER TABLE `location_idea`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `migrations`
--
ALTER TABLE `migrations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=40;

--
-- AUTO_INCREMENT for table `permissions`
--
ALTER TABLE `permissions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=65;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `social_media`
--
ALTER TABLE `social_media`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `subscriptions`
--
ALTER TABLE `subscriptions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `tags`
--
ALTER TABLE `tags`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `tags_groups`
--
ALTER TABLE `tags_groups`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `tag_idea`
--
ALTER TABLE `tag_idea`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `categories`
--
ALTER TABLE `categories`
  ADD CONSTRAINT `categories_added_by_user_foreign` FOREIGN KEY (`added_by_user`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `categories_updated_by_user_foreign` FOREIGN KEY (`updated_by_user`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `category_client_need`
--
ALTER TABLE `category_client_need`
  ADD CONSTRAINT `category_client_need_category_id_foreign` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `category_client_need_client_need_id_foreign` FOREIGN KEY (`client_need_id`) REFERENCES `client_needs` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `category_designer`
--
ALTER TABLE `category_designer`
  ADD CONSTRAINT `category_designer_category_id_foreign` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `category_designer_designer_id_foreign` FOREIGN KEY (`designer_id`) REFERENCES `designers` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `category_tag`
--
ALTER TABLE `category_tag`
  ADD CONSTRAINT `category_tag_category_id_foreign` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `category_tag_tag_id_foreign` FOREIGN KEY (`tag_id`) REFERENCES `tags` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `clients`
--
ALTER TABLE `clients`
  ADD CONSTRAINT `clients_added_by_user_foreign` FOREIGN KEY (`added_by_user`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `clients_category_id_foreign` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`),
  ADD CONSTRAINT `clients_fixed_designer_id_foreign` FOREIGN KEY (`fixed_designer_id`) REFERENCES `designers` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `clients_location_id_foreign` FOREIGN KEY (`location_id`) REFERENCES `locations` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `clients_updated_by_user_foreign` FOREIGN KEY (`updated_by_user`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `client_client_need`
--
ALTER TABLE `client_client_need`
  ADD CONSTRAINT `client_client_need_client_id_foreign` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `client_client_need_client_need_id_foreign` FOREIGN KEY (`client_need_id`) REFERENCES `client_needs` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `client_designer`
--
ALTER TABLE `client_designer`
  ADD CONSTRAINT `client_designer_client_id_foreign` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `client_designer_designer_id_foreign` FOREIGN KEY (`designer_id`) REFERENCES `designers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `client_designer_subscription_id_foreign` FOREIGN KEY (`subscription_id`) REFERENCES `subscriptions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `client_idea`
--
ALTER TABLE `client_idea`
  ADD CONSTRAINT `client_idea_added_by_user_foreign` FOREIGN KEY (`added_by_user`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `client_idea_client_id_foreign` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `client_idea_idea_id_foreign` FOREIGN KEY (`idea_id`) REFERENCES `ideas` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `client_idea_updated_by_user_foreign` FOREIGN KEY (`updated_by_user`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `client_needs`
--
ALTER TABLE `client_needs`
  ADD CONSTRAINT `client_needs_added_by_user_foreign` FOREIGN KEY (`added_by_user`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `client_needs_updated_by_user_foreign` FOREIGN KEY (`updated_by_user`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `client_need_tags_group`
--
ALTER TABLE `client_need_tags_group`
  ADD CONSTRAINT `client_need_tags_group_client_need_id_foreign` FOREIGN KEY (`client_need_id`) REFERENCES `client_needs` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `client_need_tags_group_tag_id_foreign` FOREIGN KEY (`tag_id`) REFERENCES `tags` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `client_tag_distributions`
--
ALTER TABLE `client_tag_distributions`
  ADD CONSTRAINT `client_tag_distributions_client_designer_id_foreign` FOREIGN KEY (`client_designer_id`) REFERENCES `client_designer` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `client_tag_distributions_idea_id_foreign` FOREIGN KEY (`idea_id`) REFERENCES `ideas` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `client_tag_distributions_reviewer_id_foreign` FOREIGN KEY (`reviewer_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `client_tag_distributions_tag_id_foreign` FOREIGN KEY (`tag_id`) REFERENCES `tags` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `currencies`
--
ALTER TABLE `currencies`
  ADD CONSTRAINT `currencies_added_by_user_foreign` FOREIGN KEY (`added_by_user`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `currencies_updated_by_user_foreign` FOREIGN KEY (`updated_by_user`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `custody`
--
ALTER TABLE `custody`
  ADD CONSTRAINT `custody_added_by_user_foreign` FOREIGN KEY (`added_by_user`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `custody_updated_by_user_foreign` FOREIGN KEY (`updated_by_user`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `custody_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `designers`
--
ALTER TABLE `designers`
  ADD CONSTRAINT `designers_added_by_user_foreign` FOREIGN KEY (`added_by_user`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `designers_updated_by_user_foreign` FOREIGN KEY (`updated_by_user`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `designers_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `exports`
--
ALTER TABLE `exports`
  ADD CONSTRAINT `exports_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `failed_import_rows`
--
ALTER TABLE `failed_import_rows`
  ADD CONSTRAINT `failed_import_rows_import_id_foreign` FOREIGN KEY (`import_id`) REFERENCES `imports` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `ideas`
--
ALTER TABLE `ideas`
  ADD CONSTRAINT `ideas_added_by_user_foreign` FOREIGN KEY (`added_by_user`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `ideas_updated_by_user_foreign` FOREIGN KEY (`updated_by_user`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `idea_client_blocks`
--
ALTER TABLE `idea_client_blocks`
  ADD CONSTRAINT `idea_client_blocks_added_by_user_foreign` FOREIGN KEY (`added_by_user`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `idea_client_blocks_client_id_foreign` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `idea_client_blocks_idea_id_foreign` FOREIGN KEY (`idea_id`) REFERENCES `ideas` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `idea_client_blocks_updated_by_user_foreign` FOREIGN KEY (`updated_by_user`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `imports`
--
ALTER TABLE `imports`
  ADD CONSTRAINT `imports_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `locations`
--
ALTER TABLE `locations`
  ADD CONSTRAINT `locations_added_by_user_foreign` FOREIGN KEY (`added_by_user`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `locations_updated_by_user_foreign` FOREIGN KEY (`updated_by_user`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `location_idea`
--
ALTER TABLE `location_idea`
  ADD CONSTRAINT `location_idea_added_by_user_foreign` FOREIGN KEY (`added_by_user`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `location_idea_idea_id_foreign` FOREIGN KEY (`idea_id`) REFERENCES `ideas` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `location_idea_location_id_foreign` FOREIGN KEY (`location_id`) REFERENCES `locations` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `location_idea_updated_by_user_foreign` FOREIGN KEY (`updated_by_user`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `location_tag`
--
ALTER TABLE `location_tag`
  ADD CONSTRAINT `location_tag_location_id_foreign` FOREIGN KEY (`location_id`) REFERENCES `locations` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `location_tag_tag_id_foreign` FOREIGN KEY (`tag_id`) REFERENCES `tags` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `model_has_permissions`
--
ALTER TABLE `model_has_permissions`
  ADD CONSTRAINT `model_has_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `model_has_roles`
--
ALTER TABLE `model_has_roles`
  ADD CONSTRAINT `model_has_roles_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `role_has_permissions`
--
ALTER TABLE `role_has_permissions`
  ADD CONSTRAINT `role_has_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `role_has_permissions_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `social_media`
--
ALTER TABLE `social_media`
  ADD CONSTRAINT `social_media_added_by_user_foreign` FOREIGN KEY (`added_by_user`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `social_media_updated_by_user_foreign` FOREIGN KEY (`updated_by_user`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `subscriptions`
--
ALTER TABLE `subscriptions`
  ADD CONSTRAINT `subscriptions_client_id_foreign` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `subscriptions_currency_id_foreign` FOREIGN KEY (`currency_id`) REFERENCES `currencies` (`id`);

--
-- Constraints for table `subscription_tags`
--
ALTER TABLE `subscription_tags`
  ADD CONSTRAINT `subscription_tags_subscription_id_foreign` FOREIGN KEY (`subscription_id`) REFERENCES `subscriptions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `subscription_tags_tag_id_foreign` FOREIGN KEY (`tag_id`) REFERENCES `tags` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `tags`
--
ALTER TABLE `tags`
  ADD CONSTRAINT `tags_added_by_user_foreign` FOREIGN KEY (`added_by_user`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `tags_tag_group_id_foreign` FOREIGN KEY (`tag_group_id`) REFERENCES `tags_groups` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `tags_updated_by_user_foreign` FOREIGN KEY (`updated_by_user`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `tags_groups`
--
ALTER TABLE `tags_groups`
  ADD CONSTRAINT `tags_groups_added_by_user_foreign` FOREIGN KEY (`added_by_user`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `tags_groups_updated_by_user_foreign` FOREIGN KEY (`updated_by_user`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `tag_idea`
--
ALTER TABLE `tag_idea`
  ADD CONSTRAINT `tag_idea_added_by_user_foreign` FOREIGN KEY (`added_by_user`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `tag_idea_idea_id_foreign` FOREIGN KEY (`idea_id`) REFERENCES `ideas` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `tag_idea_tag_id_foreign` FOREIGN KEY (`tag_id`) REFERENCES `tags` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `tag_idea_updated_by_user_foreign` FOREIGN KEY (`updated_by_user`) REFERENCES `users` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
