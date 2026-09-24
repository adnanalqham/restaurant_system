-- ========================================================
-- Hotel Saba Restaurant Management System
-- Database Schema & Initial Setup
-- ========================================================

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';
SET time_zone = '+03:00';

-- Table structure for table `activity_log`
CREATE TABLE `activity_log` (
  `id` int NOT NULL,
  `user_id` int DEFAULT NULL,
  `action` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `details` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Table structure for table `categories`
CREATE TABLE `categories` (
  `id` int NOT NULL,
  `name_ar` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name_en` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `icon` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT '?️',
  `sort_order` int DEFAULT '0',
  `printer_id` int DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Table structure for table `daily_settlements`
CREATE TABLE `daily_settlements` (
  `id` int NOT NULL,
  `settlement_date` date NOT NULL,
  `cashier_id` int NOT NULL,
  `expected_cash` decimal(10,2) NOT NULL DEFAULT '0.00',
  `actual_cash` decimal(10,2) NOT NULL DEFAULT '0.00',
  `expected_card` decimal(10,2) NOT NULL DEFAULT '0.00',
  `actual_card` decimal(10,2) NOT NULL DEFAULT '0.00',
  `expected_wallet` decimal(10,2) NOT NULL DEFAULT '0.00',
  `actual_wallet` decimal(10,2) NOT NULL DEFAULT '0.00',
  `expected_other` decimal(10,2) NOT NULL DEFAULT '0.00',
  `actual_other` decimal(10,2) NOT NULL DEFAULT '0.00',
  `cash_diff` decimal(10,2) NOT NULL DEFAULT '0.00',
  `non_cash_diff` decimal(10,2) NOT NULL DEFAULT '0.00',
  `notes` text COLLATE utf8mb4_general_ci,
  `created_by` int NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- Table structure for table `direct_staff`
CREATE TABLE `direct_staff` (
  `id` int NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `sort_order` int NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- Table structure for table `discounts`
CREATE TABLE `discounts` (
  `id` int NOT NULL,
  `type` enum('item','category') COLLATE utf8mb4_unicode_ci NOT NULL,
  `target_id` int NOT NULL,
  `discount_type` enum('percent','fixed') COLLATE utf8mb4_unicode_ci NOT NULL,
  `discount_value` decimal(10,2) NOT NULL,
  `label` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Table structure for table `ingredients`
CREATE TABLE `ingredients` (
  `id` int NOT NULL,
  `ingredient_number` varchar(30) DEFAULT NULL,
  `name` varchar(200) NOT NULL,
  `unit` varchar(50) NOT NULL DEFAULT 'gram' COMMENT 'gram|kg|piece|liter|ml|cup|tablespoon|other',
  `notes` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Table structure for table `inventory_departments`
CREATE TABLE `inventory_departments` (
  `id` int NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `sort_order` int DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Table structure for table `inventory_transactions`
CREATE TABLE `inventory_transactions` (
  `id` int NOT NULL,
  `department_id` int NOT NULL,
  `ingredient_id` int NOT NULL,
  `quantity` decimal(10,3) NOT NULL DEFAULT '0.000',
  `transaction_date` date NOT NULL,
  `notes` varchar(500) DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Table structure for table `inv_items`
CREATE TABLE `inv_items` (
  `id` int NOT NULL,
  `item_number` varchar(50) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `unit` varchar(50) NOT NULL,
  `current_stock` decimal(10,2) DEFAULT '0.00',
  `min_stock` decimal(10,2) DEFAULT '0.00',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Table structure for table `inv_purchases`
CREATE TABLE `inv_purchases` (
  `id` int NOT NULL,
  `item_id` int NOT NULL,
  `quantity` decimal(10,2) NOT NULL,
  `price` decimal(10,2) DEFAULT '0.00',
  `notes` text,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Table structure for table `inv_requests`
CREATE TABLE `inv_requests` (
  `id` int NOT NULL,
  `requester_id` int NOT NULL,
  `status` enum('pending','approved','issued','rejected','cancelled') DEFAULT 'pending',
  `coordinator_id` int DEFAULT NULL,
  `warehouse_manager_id` int DEFAULT NULL,
  `notes` text,
  `rejection_reason` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Table structure for table `inv_request_items`
CREATE TABLE `inv_request_items` (
  `id` int NOT NULL,
  `request_id` int NOT NULL,
  `item_id` int NOT NULL,
  `requested_qty` decimal(10,2) NOT NULL,
  `approved_qty` decimal(10,2) DEFAULT NULL,
  `issued_qty` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Table structure for table `inv_sub_stock`
CREATE TABLE `inv_sub_stock` (
  `id` int NOT NULL,
  `warehouse_name` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `item_id` int DEFAULT NULL,
  `current_balance` decimal(10,2) DEFAULT '0.00',
  `last_received_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Table structure for table `inv_warehouses`
CREATE TABLE `inv_warehouses` (
  `id` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `type` enum('main','sub') COLLATE utf8mb4_unicode_ci DEFAULT 'sub'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Table structure for table `items`
CREATE TABLE `items` (
  `id` int NOT NULL,
  `category_id` int NOT NULL,
  `item_number` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name_ar` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name_en` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT '0.00',
  `description_ar` text COLLATE utf8mb4_unicode_ci COMMENT 'مكونات الصنف',
  `description_en` text COLLATE utf8mb4_unicode_ci,
  `image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_available` tinyint(1) DEFAULT '1',
  `sort_order` int DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `has_addons` tinyint(1) DEFAULT '0',
  `addons` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `has_sizes` tinyint(1) DEFAULT '0',
  `sizes` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin
) ;

--
-- Dumping data for table `items`
--

INSERT INTO `items` (`id`, `category_id`, `item_number`, `name_ar`, `name_en`, `price`, `description_ar`, `description_en`, `image`, `is_available`, `sort_order`, `created_at`, `updated_at`, `has_addons`, `addons`, `has_sizes`, `sizes`) VALUES
(32, 8, '504', 'أصابع دجاج كرسبي', 'Crispy Chicken Fingers', 2300.00, '', '', 'item_69bdcb2ac3e1f.jpg', 1, 0, '2026-03-20 20:30:29', '2026-06-03 16:50:01', 0, NULL, 0, NULL),
(33, 8, '4009', 'طبق فاهيتا دجاج', 'Chicken Fajita Plate', 4000.00, '', '', 'item_69bdcd3b682e6.jpg', 1, 0, '2026-03-20 20:30:29', '2026-06-03 16:49:38', 0, NULL, 0, NULL),
(34, 8, '4007', 'ستروجانوف لحم', 'Beef Stroganoff', 4400.00, '', '', 'item_69bdce2473ee5.jpg', 1, 0, '2026-03-20 20:30:29', '2026-06-03 16:49:06', 0, NULL, 0, NULL),
(35, 8, '749', 'دجاج بالزبدة', 'Butter Chicken', 3300.00, '', '', 'item_69bdcc1f48983.jpg', 1, 0, '2026-03-20 20:30:29', '2026-06-03 16:48:41', 0, NULL, 0, NULL),
(36, 8, '128', 'ستيك لحم بالفلفل والصوص الهندي', 'Beef Steak with Pepper & Indian Sauce', 4700.00, '', '', 'item_69bdcf095bb67.jpg', 1, 0, '2026-03-20 20:30:29', '2026-06-03 16:48:08', 0, NULL, 0, NULL),
(37, 8, '3010', 'دجاج ديناميت', 'Dynamite Chicken', 2500.00, '', '', 'item_69bdcd883f241.jpg', 1, 0, '2026-03-20 20:30:29', '2026-06-03 16:47:47', 0, NULL, 0, NULL),
(38, 8, '3012/', 'دجاج مقلي', 'Fried Chicken', 2700.00, '', '', 'item_69be135c517b1.jpg', 1, 0, '2026-03-20 20:30:29', '2026-06-03 16:44:02', 0, NULL, 0, NULL),
(39, 8, '3009', 'أجنحة دجاج باربيكيو حارة', 'Spicy BBQ Chicken Wings', 2200.00, '', '', 'item_69bdcaf608082.jpg', 1, 0, '2026-03-20 20:30:29', '2026-06-03 16:38:29', 0, NULL, 0, NULL),
(40, 8, '120', 'فرانش فرايز', 'French Fries', 700.00, '', '', 'item_69bdcd17e6939.jpg', 1, 0, '2026-03-20 20:30:29', '2026-06-03 16:38:03', 0, NULL, 0, NULL),
(41, 8, '724', 'تشيزي فرانش فرايز', 'Cheesy French Fries', 1200.00, '', '', 'item_69bdcb7935894.jpg', 1, 0, '2026-03-20 20:30:29', '2026-06-03 16:37:37', 0, NULL, 0, NULL),
(42, 8, '503', 'طبق ستيك دجاج', 'Chicken Steak Plate', 3300.00, '', '', 'item_69bdce628d728.jpg', 1, 0, '2026-03-20 20:30:29', '2026-06-03 16:31:26', 0, NULL, 0, NULL),
(43, 8, '518', 'دجاج برياني', 'Chicken Biryani', 2500.00, '', '', 'item_69bdcc3bb2809.jpg', 1, 0, '2026-03-20 20:30:29', '2026-06-03 16:36:48', 0, NULL, 0, NULL),
(44, 8, '1013', 'سويت أند ساور دجاج', 'Sweet and Sour Chicken', 4000.00, '', '', 'item_69bdceaec217c.jpg', 1, 0, '2026-03-20 20:30:29', '2026-03-20 22:48:14', 0, NULL, 0, NULL),
(45, 8, '751', 'دجاج منجولين', 'Mongolian Chicken', 4000.00, '', '', 'item_69bdcdf5a1f22.jpg', 1, 0, '2026-03-20 20:30:29', '2026-05-24 14:46:52', 0, NULL, 0, NULL),
(46, 8, '1015', 'دجاج ترياكي', 'Teriyaki Chicken', 3500.00, '', '', 'item_69bdcc5a3a583.jpg', 1, 0, '2026-03-20 20:30:29', '2026-03-20 22:38:18', 0, NULL, 0, NULL),
(47, 8, '5053', 'زهرة سولت أند بيبر', 'Salt & Pepper Cauliflower', 1500.00, '', '', 'item_69bdccf58bfdc.jpg', 1, 0, '2026-03-20 20:30:29', '2026-06-03 16:36:08', 0, NULL, 0, NULL),
(48, 8, '126', 'نودلز / رز مقلي بالخضار', 'Noodles / Vegetable Fried Rice', 1500.00, '', '', 'item_69bdcca444cd2.jpg', 1, 0, '2026-03-20 20:30:29', '2026-06-03 16:34:56', 0, NULL, 0, NULL),
(49, 8, '1018', 'سمك فيليه جريل', 'Grilled Fish Fillet', 4000.00, '', '', 'item_69bdcf95c1edf.jpg', 1, 0, '2026-03-20 20:30:29', '2026-03-20 22:52:05', 0, NULL, 0, NULL),
(50, 8, '1019', 'فيش & شبس', 'Fish and Chips', 3800.00, '', '', 'item_69bdcc89d9490.jpg', 1, 0, '2026-03-20 20:30:29', '2026-05-16 10:59:49', 0, NULL, 0, NULL),
(51, 8, '1046', 'سمك مشوي بصلصة الليمون', 'Grilled Fish with Lemon Sauce', 4000.00, '', '', 'item_69bdceea2c5e6.jpg', 1, 0, '2026-03-20 20:30:29', '2026-06-03 16:32:56', 0, NULL, 0, NULL),
(52, 8, '1011', 'سمك بالأعشاب مع صلصة الزبدة', 'Herb Fish with Butter Sauce', 4000.00, '', '', 'item_69bdcf501324d.jpg', 1, 0, '2026-03-20 20:30:29', '2026-06-03 16:31:44', 0, NULL, 0, NULL),
(53, 8, '4012', 'طبق جمبري بالصوص الحار', 'Spicy Shrimp', 4400.00, '', '', 'item_69bdcbd73b1cb.jpg', 1, 0, '2026-03-20 20:30:29', '2026-06-03 16:29:52', 0, NULL, 0, NULL),
(54, 11, '4001', 'سلطة الأناناس', 'Pineapple Salad', 2000.00, '', '', 'item_69bdd2a10f0ef.jpg', 1, 0, '2026-03-20 20:32:38', '2026-05-24 15:30:02', 0, NULL, 0, NULL),
(55, 11, '213', 'سلطة قيصر', 'Caesar Salad', 1500.00, '', '', 'item_69bdd31429a62.jpg', 1, 0, '2026-03-20 20:32:38', '2026-05-24 15:29:18', 0, NULL, 0, NULL),
(56, 11, '1065', 'سلطة يونانية', 'Greek Salad', 1500.00, '', '', 'item_69bdd333760a4.jpg', 1, 0, '2026-03-20 20:32:38', '2026-05-24 15:26:42', 0, NULL, 0, NULL),
(57, 11, '5054', 'سلطة ذرة', 'Corn Salad', 1600.00, '', '', 'item_69bdd2e5f3079.jpg', 1, 0, '2026-03-20 20:32:38', '2026-05-24 15:25:56', 0, NULL, 0, NULL),
(58, 11, '3008', 'سلطة بيت روت', 'Beetroot Salad', 1600.00, '', '', 'item_69bdd2cc886e0.jpg', 1, 0, '2026-03-20 20:32:38', '2026-05-24 15:25:28', 0, NULL, 0, NULL),
(59, 11, '551', 'تبولة الكينوا', 'Quinoa Tabbouleh', 1600.00, '', '', 'item_69bdd2825af9f.jpg', 1, 0, '2026-03-20 20:32:38', '2026-05-24 15:24:50', 0, NULL, 0, NULL),
(60, 11, '549', 'طبق حمص', 'Hummus Plate', 1200.00, '', '', 'item_69bdda342ada6.jpg', 1, 0, '2026-03-20 20:32:38', '2026-05-24 15:24:06', 0, NULL, 0, NULL),
(61, 11, '1030', 'كيلو سلطة', 'K.G Salad', 5000.00, '', '', 'item_69bdda65cce9a.jpg', 1, 0, '2026-03-20 20:32:38', '2026-05-24 16:29:29', 0, NULL, 0, NULL),
(62, 10, '511', 'برجر لحم مع الجبنة', 'Beef Burger with Cheese', 1800.00, '', '', 'item_69bdd00b5b957.jpg', 1, 0, '2026-03-20 20:34:16', '2026-06-03 16:52:54', 0, NULL, 0, NULL),
(63, 10, '1032', 'برجر دجاج', 'Chicken Burger', 1500.00, '', '', 'item_6a074c3541628.jpg', 1, 0, '2026-03-20 20:34:16', '2026-05-15 16:39:17', 0, NULL, 0, NULL),
(64, 10, '714', 'برجر ستربس', 'Strips Burger', 1800.00, '', '', 'item_6a074bd29ef32.jpg', 1, 0, '2026-03-20 20:34:16', '2026-06-03 16:52:37', 0, NULL, 0, NULL),
(65, 10, '720', 'ساندوتش زنجر', 'Zinger Sandwich', 2000.00, '', '', 'item_69bdd0d457289.jpg', 1, 0, '2026-03-20 20:34:16', '2026-06-03 16:52:13', 0, NULL, 0, NULL),
(66, 10, '3005', 'سبأ كلوب ساندوتش', 'Sheba Club Sandwich', 2000.00, '', '', 'item_69bdd153d71ad.jpg', 1, 0, '2026-03-20 20:34:16', '2026-06-03 16:51:37', 0, NULL, 0, NULL),
(67, 10, '716', 'ساندوتش فاهيتا', 'Fajita Sandwich', 2000.00, '', '', 'item_69bdd0fcc3d5a.jpg', 1, 0, '2026-03-20 20:34:16', '2026-06-03 16:51:16', 0, NULL, 0, NULL),
(68, 10, '514', 'ماكسيكان ساندوتش (دجاج)', 'Mexican Chicken Sandwich', 2200.00, '', '', 'item_69bdd20769d10.jpg', 1, 0, '2026-03-20 20:34:16', '2026-06-03 16:50:53', 0, NULL, 0, NULL),
(69, 10, '1038', 'كرسبي رول', 'Crispy Roll', 2500.00, '', '', 'item_69bdd1dcd4040.jpg', 1, 0, '2026-03-20 20:34:16', '2026-03-20 23:01:48', 0, NULL, 0, NULL),
(70, 10, '1039', 'هابي ميكس رول بالجبن', 'Happy Mix Cheese Roll', 1800.00, '', '', 'item_69bdd253b8952.jpg', 0, 0, '2026-03-20 20:34:16', '2026-05-16 10:52:57', 0, NULL, 0, NULL),
(71, 10, '3011', 'ساندوتش تونة', 'Tuna Sandwich', 1300.00, '', '', 'item_69bdd02cd7d72.jpg', 1, 0, '2026-03-20 20:34:16', '2026-06-03 16:50:30', 0, NULL, 0, NULL),
(76, 7, '1035////', 'شيشه خلطة سبأ 1', 'sisha Sheba Mix 1', 3500.00, '', '', 'item_69be006a3c36f.jpg', 1, 0, '2026-03-20 20:40:05', '2026-06-03 16:59:33', 0, NULL, 0, NULL),
(77, 7, '1035///', 'شيشه خلطة سبأ 2', 'sisha Sheba Mix 2', 3500.00, '', '', 'item_69be0119a521b.jpg', 1, 0, '2026-03-20 20:40:05', '2026-06-03 16:59:23', 0, NULL, 0, NULL),
(78, 7, '1035//', 'شيشه حسب الطلب أكثر من نوعين معسل', 'sisha Custom Mix (More than 2 flavors)', 3000.00, '', '', 'item_69be004235070.jpg', 1, 0, '2026-03-20 20:40:05', '2026-06-03 16:58:38', 0, NULL, 0, NULL),
(79, 7, '1035', 'شيشه حسب الطلب نوعين معسل', 'sisha Custom Mix (2 flavors)', 3000.00, '', '', 'item_69be0051c49da.jpg', 1, 0, '2026-03-20 20:40:05', '2026-06-03 16:58:29', 0, NULL, 0, NULL),
(80, 7, '1049', 'شيشه تفاحتين فاخر', 'sisha Double Apple Premium', 3000.00, '', '', 'item_69be0028efeb3.jpg', 1, 0, '2026-03-20 20:40:05', '2026-05-02 04:36:50', 0, NULL, 0, NULL),
(81, 7, '1050', 'شيشه عنب أحمر سعودي', 'sisha Saudi Red Grape', 3000.00, '', '', 'item_69be013b4e8a1.jpg', 1, 0, '2026-03-20 20:40:05', '2026-05-02 04:36:56', 0, NULL, 0, NULL),
(82, 7, '1051', 'شيشه عنب أبيض', 'sisha White Grape', 3000.00, '', '', 'item_69be01550f3c4.jpg', 1, 0, '2026-03-20 20:40:05', '2026-05-02 04:36:59', 0, NULL, 0, NULL),
(83, 7, '1052', 'شيشه فرنسي أبيض', 'sisha French White', 3000.00, '', '', 'item_69be00a993176.jpg', 1, 0, '2026-03-20 20:40:05', '2026-05-02 04:37:04', 0, NULL, 0, NULL),
(84, 7, '1053', 'شيشه فرنسي وردي', 'sisha French Pink', 3000.00, '', '', 'item_69be00f6cd28d.jpg', 1, 0, '2026-03-20 20:40:05', '2026-05-02 04:37:09', 0, NULL, 0, NULL),
(85, 7, '1054', 'شيشه خوخ', 'sisha Peach', 3000.00, '', '', 'item_69be0180c22f1.jpg', 1, 0, '2026-03-20 20:40:05', '2026-05-02 04:37:16', 0, NULL, 0, NULL),
(86, 7, '1055', 'شيشه بطيخ', 'sisha Watermelon', 3000.00, '', '', 'item_69be00050c9c3.jpg', 1, 0, '2026-03-20 20:40:05', '2026-05-02 04:37:13', 0, NULL, 0, NULL),
(87, 7, '1056', 'شيشه رمان أردني', 'sisha Jordanian Pomegranate', 3000.00, '', '', 'item_69be01746ba51.jpg', 1, 0, '2026-03-20 20:40:05', '2026-05-02 04:37:21', 0, NULL, 0, NULL),
(88, 7, '1035/', 'شيشه شهرزاد', 'sisha Shahrazad', 3000.00, '', '', 'item_69be016371aa7.jpg', 1, 0, '2026-03-20 20:40:05', '2026-06-03 16:59:02', 0, NULL, 0, NULL),
(89, 7, '1058', 'شيشه توت', 'sisha Berries', 3000.00, '', '', 'item_69be00345344b.jpg', 1, 0, '2026-03-20 20:40:05', '2026-05-02 04:37:29', 0, NULL, 0, NULL),
(90, 7, '616_', 'شيشه أفوكادو', 'sisha Avocado', 3000.00, '', '', 'item_69bdffc46dd89.jpg', 1, 0, '2026-03-20 20:40:05', '2026-06-03 16:57:40', 0, NULL, 0, NULL),
(91, 7, '616//////', 'شيشه ليمون', 'sisha Lemon', 3000.00, '', '', 'item_69be00e9b06da.jpg', 1, 0, '2026-03-20 20:40:05', '2026-06-03 16:56:54', 0, NULL, 0, NULL),
(92, 7, '616--', 'شيشه نعناع', 'sisha Mint', 3000.00, '', '', 'item_69be00b909ec7.jpg', 1, 0, '2026-03-20 20:40:05', '2026-06-03 16:57:26', 0, NULL, 0, NULL),
(93, 7, '616-', 'شيشه برتقال', 'sisha Orange', 3000.00, '', '', 'item_69bdfff23e670.jpg', 1, 0, '2026-03-20 20:40:05', '2026-06-03 16:57:09', 0, NULL, 0, NULL),
(94, 7, '616/////', 'شيشه نعماني', 'sisha Omani', 3000.00, '', '', 'item_69be00d864a14.jpg', 1, 0, '2026-03-20 20:40:05', '2026-06-03 16:56:31', 0, NULL, 0, NULL),
(95, 7, '616////', 'شيشه بلوبيري', 'sisha Blueberry', 3000.00, '', '', 'item_69be0017a2099.jpg', 1, 0, '2026-03-20 20:40:05', '2026-06-03 16:55:48', 0, NULL, 0, NULL),
(96, 7, '616///', 'شيشه كرز', 'sisha Cherry', 3000.00, '', '', 'item_69be010605d75.jpg', 1, 0, '2026-03-20 20:40:05', '2026-06-03 16:55:31', 0, NULL, 0, NULL),
(97, 7, '616//', 'شيشه آيس كريم', 'sisha Ice Cream', 3000.00, '', '', 'item_69bdffa10a4db.jpg', 1, 0, '2026-03-20 20:40:05', '2026-06-03 16:55:19', 0, NULL, 0, NULL),
(98, 7, '616/', 'شيشه فراولة أحمر', 'sisha Strawberry Red', 3000.00, '', '', 'item_69be0127c10cb.jpg', 1, 0, '2026-03-20 20:40:05', '2026-06-03 16:55:09', 0, NULL, 0, NULL),
(99, 7, '616', 'شيشه فراولة وردي', 'sisha Strawberry Pink', 3000.00, '', '', 'item_69be00c9d112b.jpg', 1, 0, '2026-03-20 20:40:05', '2026-06-03 16:54:52', 0, NULL, 0, NULL),
(100, 7, '617', 'إضافة رأس', 'sisha Extra Head', 700.00, '', '', 'item_69be0096ddbf2.jpg', 1, 0, '2026-03-20 20:40:05', '2026-06-12 10:14:04', 0, NULL, 0, NULL),
(101, 5, '1070', 'اسبريسو', 'Espresso', 800.00, '', '', 'item_69bdd03d7aaa9.jpg', 1, 0, '2026-03-20 20:43:51', '2026-03-20 22:54:53', 0, NULL, 0, NULL),
(102, 5, '1071', 'اسبريسو دبل', 'Double Espresso', 1200.00, '', '', 'item_69bde4fd73129.jpg', 1, 0, '2026-03-20 20:43:51', '2026-03-21 00:23:25', 0, NULL, 0, NULL),
(103, 5, '1072', 'اسبريسو ميكاتو', 'Espresso Macchiato', 1000.00, '', '', 'item_69bde2f3753b2.jpg', 1, 0, '2026-03-20 20:43:51', '2026-03-21 00:14:43', 0, NULL, 0, NULL),
(104, 5, '1073', 'أمريكانو', 'Americano', 800.00, '', '', 'item_69bde29e17ffa.jpg', 1, 0, '2026-03-20 20:43:51', '2026-05-25 15:53:31', 0, NULL, 0, NULL),
(105, 5, '1074', 'كابتشينو', 'Cappuccino', 1200.00, '', '', 'item_69bdf5bcafcae.jpg', 1, 0, '2026-03-20 20:43:51', '2026-03-21 01:34:52', 0, NULL, 0, NULL),
(106, 5, '1075', 'كورتادو', 'Cortado', 1200.00, '', '', 'item_69bdf6911704e.jpg', 1, 0, '2026-03-20 20:43:51', '2026-03-21 01:38:25', 0, NULL, 0, NULL),
(107, 5, '1076', 'كافيه لاتيه', 'Cafe Latte', 1200.00, '', '', 'item_69bdf5df66b22.jpg', 1, 0, '2026-03-20 20:43:51', '2026-03-21 01:35:27', 0, NULL, 0, NULL),
(108, 5, '1077', 'كراميل ميكاتو', 'Caramel Macchiato', 1500.00, '', '', 'item_69bdf640ea28b.jpg', 1, 0, '2026-03-20 20:43:51', '2026-03-21 01:37:04', 0, NULL, 0, NULL),
(109, 5, '1078', 'فلات وايت', 'Flat White', 1200.00, '', '', 'item_69bdf534c3298.jpg', 1, 0, '2026-03-20 20:43:51', '2026-05-25 15:53:08', 0, NULL, 0, NULL),
(110, 5, '1079', 'هوت شوكلت', 'Hot Chocolate', 1000.00, '', '', 'item_69bdf7f5140bc.jpg', 1, 0, '2026-03-20 20:43:51', '2026-05-25 15:52:49', 0, NULL, 0, NULL),
(111, 5, '1080', 'هوت كراميل', 'Hot Caramel', 1100.00, '', '', 'item_69bdf78131e1c.jpg', 1, 0, '2026-03-20 20:43:51', '2026-03-21 01:42:25', 0, NULL, 0, NULL),
(112, 5, '1081', 'هوت سنكرس', 'Hot Snickers', 1200.00, '', '', 'item_69bdf88c65a55.jpg', 1, 0, '2026-03-20 20:43:51', '2026-03-21 01:46:52', 0, NULL, 0, NULL),
(113, 5, '1082', 'هوت بيستاشيو', 'Hot Pistachio', 1000.00, '', '', 'item_69bdf86882ece.jpg', 1, 0, '2026-03-20 20:43:51', '2026-03-21 01:46:16', 0, NULL, 0, NULL),
(114, 5, '1083', 'نسكافيه عادي', 'Nescafe', 800.00, '', '', 'item_69bdf97039455.jpg', 1, 0, '2026-03-20 20:43:51', '2026-03-21 01:50:40', 0, NULL, 0, NULL),
(115, 5, '1084', 'نسكافيه حليب', 'Nescafe with Milk', 1000.00, '', '', 'item_69bdf80fcaab4.jpg', 1, 0, '2026-03-20 20:43:51', '2026-03-21 01:44:47', 0, NULL, 0, NULL),
(116, 5, '1085', 'قهوة تركي', 'Turkish Coffee', 800.00, '', '', 'item_69bdf56749419.jpg', 1, 0, '2026-03-20 20:43:51', '2026-03-21 01:33:27', 0, NULL, 0, NULL),
(117, 5, '1086', 'قهوة تركي دبل', 'Double Turkish Coffee', 1200.00, '', '', 'item_69bdf59160177.jpg', 1, 0, '2026-03-20 20:43:51', '2026-03-21 01:34:09', 0, NULL, 0, NULL),
(118, 5, '1087', 'شاي أحمر', 'Black Tea', 300.00, '', '', 'item_69bde24333270.jpg', 1, 0, '2026-03-20 20:43:51', '2026-03-21 00:11:47', 0, NULL, 0, NULL),
(119, 5, '1088', 'شاي أخضر', 'Green Tea', 500.00, '', '', 'item_69bde2553d536.jpg', 1, 0, '2026-03-20 20:43:51', '2026-05-25 15:52:14', 0, NULL, 0, NULL),
(120, 5, '1089', 'شاي عدني', 'Adeni Tea', 600.00, '', '', 'item_69bdf5134eedc.jpg', 1, 0, '2026-03-20 20:43:51', '2026-03-21 01:32:03', 0, NULL, 0, NULL),
(121, 5, '138/139', 'زنجبيل ساخن', 'Hot Ginger', 400.00, '', '', 'item_69bde7997c97d.jpg', 1, 0, '2026-03-20 20:43:51', '2026-05-24 20:06:08', 0, NULL, 0, NULL),
(122, 5, '1091', 'دله قهوة عربي مع التمر', 'Arabic Coffee Pot with Dates', 2500.00, '', '', 'item_69bddd078cad4.jpg', 0, 0, '2026-03-20 20:43:51', '2026-05-20 10:16:26', 0, NULL, 0, NULL),
(123, 5, '1092', 'لاتيه نكهات (توفي - فانيليا - بندق - لوتس - روز - بلو)', 'Flavored Latte (Toffee - Vanilla - Hazelnut - Lotus - Rose - Blue)', 1400.00, '', '', 'item_69bdf6e69a2a9.jpg', 1, 0, '2026-03-20 20:43:51', '2026-03-21 01:39:50', 0, NULL, 0, NULL),
(124, 5, '1093', 'كراميل لاتيه', 'Caramel Latte', 1200.00, '', '', 'item_69bdf614426e0.jpg', 1, 0, '2026-03-20 20:43:51', '2026-05-25 15:51:40', 0, NULL, 0, NULL),
(125, 5, '1094', 'سبانش لاتيه', 'Spanish Latte', 1400.00, '', '', 'item_69bddec5c5215.jpg', 1, 0, '2026-03-20 20:43:51', '2026-03-20 23:56:53', 0, NULL, 0, NULL),
(126, 5, '1095', 'بستاشيو لاتيه', 'Pistachio Latte', 1400.00, '', '', 'item_69bddc3fcf54a.jpg', 1, 0, '2026-03-20 20:43:51', '2026-03-20 23:46:07', 0, NULL, 0, NULL),
(127, 5, '1096', 'موكا', 'Mocha', 1400.00, '', '', 'item_69bdf71747211.jpg', 1, 0, '2026-03-20 20:43:51', '2026-03-21 01:40:39', 0, NULL, 0, NULL),
(128, 5, '1097', 'وايت موكا', 'White Mocha', 1400.00, '', '', 'item_69bdf74754e8e.jpg', 1, 0, '2026-03-20 20:43:51', '2026-03-21 01:41:27', 0, NULL, 0, NULL),
(129, 3, '112/514', 'بلاك فورست', 'Black Forest Cake 1kg', 10000.00, '', '', 'item_69bddd8b70c72.jpg', 1, 0, '2026-03-20 20:45:27', '2026-05-29 07:43:43', 0, NULL, 1, '[{\"name_ar\":\"كيلو\",\"name_en\":\"K.G\",\"price\":10000},{\"name_ar\":\"قطعه\",\"name_en\":\"piece\",\"price\":2000},{\"name_ar\":\"نصف كيلو\",\"name_en\":\"H.KG\",\"price\":5000}]'),
(130, 3, '175/143', 'وايت فورست', 'White Forest Cake 1kg', 10000.00, '', '', 'item_69bde1bd9fe2b.jpg', 1, 0, '2026-03-20 20:45:27', '2026-05-29 07:48:51', 0, NULL, 1, '[{\"name_ar\":\"كيلو\",\"name_en\":\"K.G\",\"price\":10000},{\"name_ar\":\"قطعه\",\"name_en\":\"piece\",\"price\":2000},{\"name_ar\":\"نصف كيلو\",\"name_en\":\"H.KG\",\"price\":5000}]'),
(131, 3, '139/173', 'كيك بالفراولة', 'Strawberry Cake 1kg', 9000.00, '', '', 'item_69bddfeb5d034.jpg', 1, 0, '2026-03-20 20:45:27', '2026-05-29 07:48:25', 0, NULL, 1, '[{\"name_ar\":\"كيلو\",\"name_en\":\"K.G\",\"price\":9000},{\"name_ar\":\"قطعه\",\"name_en\":\"piece\",\"price\":1600},{\"name_ar\":\"نصف كيلو\",\"name_en\":\"H.KG\",\"price\":4500}]'),
(132, 3, '141/174', 'كيك فانيليا', 'Vanilla Cake 1kg', 9000.00, '', '', 'item_69bde0768dfcc.jpg', 1, 0, '2026-03-20 20:45:27', '2026-05-29 07:48:01', 0, NULL, 1, '[{\"name_ar\":\"كيلو\",\"name_en\":\"K.G\",\"price\":9000},{\"name_ar\":\"قطعه\",\"name_en\":\"piece\",\"price\":1600},{\"name_ar\":\"نصف كيلو\",\"name_en\":\"H.KG\",\"price\":4500}]'),
(133, 3, '133/171', 'كيك بالأناناس', 'Pineapple Cake', 9000.00, '', '', 'item_69bddf99d0f3b.jpg', 1, 0, '2026-03-20 20:45:27', '2026-05-24 15:52:15', 0, NULL, 1, '[{\"name_ar\":\"كيلو\",\"name_en\":\"K.G\",\"price\":9000},{\"name_ar\":\"قطعه\",\"name_en\":\"piece\",\"price\":1500}]'),
(134, 3, '115', 'كيك ترافل بالشوكولاتة', 'Chocolate Truffle Cake', 12000.00, '', '', 'item_69bde00f6cfa9.jpg', 1, 0, '2026-03-20 20:45:27', '2026-05-29 07:47:20', 0, NULL, 1, '[{\"name_ar\":\"كيلو\",\"name_en\":\"K.G\",\"price\":12000},{\"name_ar\":\"قطعه\",\"name_en\":\"piece\",\"price\":2000},{\"name_ar\":\"نصف كيلو\",\"name_en\":\"H.KG\",\"price\":6000}]'),
(135, 3, '317/131', 'كيك موكا', 'Mocha Cake', 9000.00, '', '', 'item_69bde12e4c716.jpg', 1, 0, '2026-03-20 20:45:27', '2026-05-29 07:46:29', 0, NULL, 1, '[{\"name_ar\":\"كيلو\",\"name_en\":\"K.G\",\"price\":9000},{\"name_ar\":\"قطعه\",\"name_en\":\"piece\",\"price\":1800},{\"name_ar\":\"نصف كيلو\",\"name_en\":\"H.KG\",\"price\":4500}]'),
(136, 3, '121/320/', 'كيك فواكه', 'Fruit Cake', 4000.00, '', '', 'item_69bde1021c5e1.jpg', 1, 0, '2026-03-20 20:45:27', '2026-06-09 18:11:00', 0, NULL, 1, '[{\"name_ar\":\"كيلو\",\"name_en\":\"kg\",\"price\":4000},{\"name_ar\":\"قطعه\",\"name_en\":\"pice\",\"price\":400},{\"name_ar\":\"نصف كيلو\",\"name_en\":\"HKg\",\"price\":2000}]'),
(137, 3, '121/320', 'كيك فواكه مع زبيب', 'Fruit Cake with Raisins', 4000.00, '', '', 'item_69bde0dd84b44.jpg', 1, 0, '2026-03-20 20:45:27', '2026-06-09 18:11:29', 0, NULL, 1, '[{\"name_ar\":\"كيلو\",\"name_en\":\"kg\",\"price\":4000},{\"name_ar\":\"قطعه\",\"name_en\":\"pice\",\"price\":400},{\"name_ar\":\"نصف كيلو\",\"name_en\":\"HKg\",\"price\":2000}]'),
(138, 3, '192', 'كرواسون', 'Croissant', 500.00, '', '', 'item_69bddf1a3134a.jpg', 1, 0, '2026-03-20 20:45:27', '2026-05-24 15:42:56', 0, NULL, 0, NULL),
(139, 3, '1108', 'بسكويت زبدة 1 كجم', 'Butter Biscuits 1kg', 4000.00, '', '', 'item_69bddd3b11d67.jpg', 1, 0, '2026-03-20 20:45:27', '2026-03-20 23:50:19', 0, NULL, 0, NULL),
(140, 3, '1109', 'بسكويت شوكولاتة 1 كجم', 'Chocolate Biscuits 1kg', 3500.00, '', '', 'item_69bddd5cce88f.jpg', 1, 0, '2026-03-20 20:45:27', '2026-03-20 23:50:52', 0, NULL, 0, NULL),
(141, 3, '302', 'كنافة قطعه', 'Kunafa Piece', 300.00, '', '', 'item_69bddf3a84993.jpg', 1, 0, '2026-03-20 20:45:27', '2026-05-24 15:42:05', 0, NULL, 0, NULL),
(142, 3, '302', 'بسبوسة قطعه', 'Basbousa Piece', 300.00, '', '', 'item_69bddd2338554.jpg', 1, 0, '2026-03-20 20:45:27', '2026-05-24 15:42:25', 0, NULL, 0, NULL),
(143, 3, '101', 'خبز أسمر توست (قالب كامل)', 'Brown Toast Bread', 1500.00, '', '', 'item_69bddeac05312.jpg', 1, 0, '2026-03-20 20:45:27', '2026-05-24 15:41:42', 0, NULL, 0, NULL),
(144, 3, '103', 'خبز أبيض توست (قالب كامل)', 'White Toast Bread', 750.00, '', '', 'item_69bdde2b58ab0.jpg', 1, 0, '2026-03-20 20:45:27', '2026-05-24 15:41:26', 0, NULL, 0, NULL),
(145, 3, '1114', 'ترامسيو', 'Tiramisu', 1000.00, '', '', 'item_69bdddbcbe28e.jpg', 1, 0, '2026-03-20 20:45:27', '2026-03-20 23:52:28', 0, NULL, 0, NULL),
(146, 3, '1115', 'موس كيك قطعه', 'Mousse Cake Piece', 500.00, '', '', 'item_69bde1a166948.jpg', 1, 0, '2026-03-20 20:45:27', '2026-03-21 00:09:05', 0, NULL, 0, NULL),
(147, 3, '537', 'تشيز كيك بالفراولة', 'Strawberry Cheesecake', 10000.00, '', '', 'item_69bdde092df0b.jpg', 1, 0, '2026-03-20 20:45:27', '2026-05-25 15:54:09', 0, NULL, 1, '[{\"name_ar\":\"كيلو\",\"name_en\":\"K.G\",\"price\":10000},{\"name_ar\":\"قطعه\",\"name_en\":\"piece\",\"price\":1000}]'),
(148, 3, '306/5046', 'تشيز كيك بالشوكولاتة الداكنة', 'Dark Chocolate Cheesecake', 10000.00, '', '', 'item_69bddde0421f6.jpg', 1, 0, '2026-03-20 20:45:27', '2026-06-03 17:05:00', 0, NULL, 1, '[{\"name_ar\":\"كيلو\",\"name_en\":\"K.G\",\"price\":10000},{\"name_ar\":\"قطعه\",\"name_en\":\"piece\",\"price\":1000}]'),
(149, 3, '1066', 'قطعة تارت', 'Tart Piece', 300.00, '', '', 'item_69bddefa4edf7.jpg', 1, 0, '2026-03-20 20:45:27', '2026-06-03 17:04:18', 0, NULL, 0, NULL),
(172, 15, '1141', 'فطور شامي', 'Levant Breakfast', 3500.00, '', '', 'item_69f26e52392de.jpeg', 1, 0, '2026-03-20 21:14:47', '2026-04-29 20:47:14', 0, NULL, 0, NULL),
(173, 15, '1142', 'فطور تركي', 'Turkish Breakfast', 3500.00, '', '', 'item_69f26e3fa8f81.jpeg', 1, 0, '2026-03-20 21:14:47', '2026-04-29 20:46:55', 0, NULL, 0, NULL),
(174, 15, '199', 'فول مدمس', 'Foul Medames', 700.00, '', '', 'item_69bdc9beb26c9.jpg', 1, 0, '2026-03-20 21:14:47', '2026-05-26 21:46:39', 0, NULL, 0, NULL),
(175, 15, '1144', 'فاصوليا', 'Beans', 800.00, '', '', 'item_69bdc8a1cd1bc.jpg', 1, 0, '2026-03-20 21:14:47', '2026-05-16 10:45:57', 0, NULL, 0, NULL),
(176, 15, '1145', 'فاصوليا بالبيض', 'Beans with Eggs', 1000.00, '', '', 'item_69bdc8d83165a.jpg', 1, 0, '2026-03-20 21:14:47', '2026-04-02 09:17:37', 0, NULL, 0, NULL),
(177, 15, '1146', 'بازلاء', 'Peas', 800.00, '', '', 'item_69bdc751d887b.jpg', 1, 0, '2026-03-20 21:14:47', '2026-04-02 09:17:46', 0, NULL, 0, NULL),
(178, 15, '5006', 'بازلاء بالبيض', 'Peas with Eggs', 1000.00, '', '', 'item_69bdc786a5139.jpg', 1, 0, '2026-03-20 21:14:47', '2026-05-26 21:47:04', 0, NULL, 0, NULL),
(179, 15, '1148', 'كبدة', 'Liver', 2000.00, '', '', 'item_69bdca00b0d51.jpg', 1, 0, '2026-03-20 21:14:47', '2026-04-02 09:18:00', 0, NULL, 0, NULL),
(180, 15, '1149', 'لحم صغار', 'Lamb Cubes', 1600.00, '', '', 'item_69bdcac241e82.jpg', 1, 0, '2026-03-20 21:14:47', '2026-04-02 09:18:07', 0, NULL, 0, NULL),
(181, 15, '1150', 'لحم دقة', 'Minced Meat', 1500.00, '', '', 'item_69bdca46a5bbc.jpg', 1, 0, '2026-03-20 21:14:47', '2026-04-02 09:18:15', 0, NULL, 0, NULL),
(182, 15, '1151', 'شكشوكة', 'Shakshouka', 700.00, '', '', 'item_69bdc86c5e389.jpg', 0, 0, '2026-03-20 21:14:47', '2026-05-24 10:58:14', 0, NULL, 0, NULL),
(183, 15, '5056', 'بيض شكشوكة', 'Egg Shakshouka', 700.00, '', '', 'item_69bdc7fc2e63b.jpg', 1, 0, '2026-03-20 21:14:47', '2026-05-24 15:18:18', 0, NULL, 0, NULL),
(184, 15, '1153', 'أومليت إسباني', 'Spanish Omelette', 1200.00, '', '', 'item_69bdc35a4e129.jpeg', 1, 0, '2026-03-20 21:14:47', '2026-04-02 09:18:54', 0, NULL, 0, NULL),
(185, 15, '1154', 'أومليت بالجبن', 'Cheese Omelette', 1200.00, '', '', 'item_69bdc5a00c6eb.jpg', 1, 0, '2026-03-20 21:14:47', '2026-04-02 09:19:02', 0, NULL, 0, NULL),
(186, 15, '1155', 'أومليت بالخضار', 'Vegetable Omelette', 1000.00, '', '', 'item_69bdc6af6675a.jpg', 1, 0, '2026-03-20 21:14:47', '2026-04-02 09:19:08', 0, NULL, 0, NULL),
(187, 15, '1156', 'بيض أومليت', 'Omelette', 600.00, '', '', 'item_69bdc7b8d984c.jpg', 1, 0, '2026-03-20 21:14:47', '2026-03-20 22:18:32', 0, NULL, 0, NULL),
(188, 11, '191', 'شوربة دجاج بالكريمة', 'Creamy Chicken Soup', 1600.00, '', '', 'item_69bdd46e088f1.jpg', 1, 0, '2026-03-20 21:20:33', '2026-06-03 17:01:42', 0, NULL, 0, NULL),
(189, 11, '193', 'شوربة عدس', 'Lentil Soup', 1300.00, '', '', 'item_69bdd48d61b60.jpg', 1, 0, '2026-03-20 21:20:33', '2026-06-03 17:01:15', 0, NULL, 0, NULL),
(190, 11, '754', 'شوربة الجزر', 'Carrot Soup', 800.00, '', '', 'item_69bdd3a1dac53.jpg', 1, 0, '2026-03-20 21:20:33', '2026-06-03 17:01:00', 0, NULL, 0, NULL),
(191, 11, '1058', 'شوربة هوت آند ساور', 'Hot and Sour Soup', 1300.00, '', '', 'item_69bdda1ccf589.jpg', 1, 0, '2026-03-20 21:20:33', '2026-05-24 15:19:57', 0, NULL, 0, NULL),
(192, 17, '1161', 'ليمون', 'Lemon', 500.00, '', '', 'item_69bde528e2d35.jpg', 1, 0, '2026-03-20 21:26:02', '2026-04-01 08:02:16', 0, NULL, 0, NULL),
(193, 17, '1162', 'ليمون بالنعناع', 'Lemon Mint', 900.00, '', '', 'item_69bde4e888cf0.jpg', 1, 0, '2026-03-20 21:26:02', '2026-03-21 00:23:04', 0, NULL, 0, NULL),
(194, 17, '1163', 'برتقال كبس', 'Fresh Orange Juice', 1500.00, '', '', 'item_69bde2bed75e0.jpg', 1, 0, '2026-03-20 21:26:02', '2026-03-21 00:13:50', 0, NULL, 0, NULL),
(195, 17, '1164', 'برتقال عصير', 'Orange Juice', 1000.00, '', '', 'item_69bde2964561d.jpg', 1, 0, '2026-03-20 21:26:02', '2026-03-21 00:13:10', 0, NULL, 0, NULL),
(196, 17, '1165', 'جزر', 'Carrot Juice', 500.00, '', '', 'item_69bde3c534f6a.jpg', 0, 0, '2026-03-20 21:26:02', '2026-04-04 09:44:14', 0, NULL, 0, NULL),
(197, 17, '1166', 'برتقال مع جزر', 'Orange with Carrot', 1500.00, '', '', 'item_69bde3047c176.jpg', 0, 0, '2026-03-20 21:26:02', '2026-04-04 09:44:09', 0, NULL, 0, NULL),
(198, 17, '1167', 'حليب بالموز', 'Banana Milk', 800.00, '', '', 'item_69bde474eedda.jpg', 1, 0, '2026-03-20 21:26:02', '2026-05-25 15:45:12', 0, NULL, 0, NULL),
(199, 17, '156', 'مانجو', 'Mango Juice', 800.00, '', '', 'item_69bde24c105b0.jpg', 1, 0, '2026-03-20 21:26:02', '2026-05-25 15:44:49', 0, NULL, 0, NULL),
(200, 17, '1169', 'مانجو بالحليب', 'Mango Milk', 1000.00, '', '', 'item_69bde2275f77c.jpg', 1, 0, '2026-03-20 21:26:02', '2026-05-25 15:44:38', 0, NULL, 0, NULL),
(201, 17, '152', 'عصير فراولة', 'Strawberry Juice', 1000.00, '', '', 'item_69bde6876f747.jpg', 1, 0, '2026-03-20 21:26:02', '2026-05-24 20:08:01', 0, NULL, 0, NULL),
(202, 17, '1171', 'فراولة بالحليب', 'Strawberry Milk', 1200.00, '', '', 'item_69bde63e53655.jpg', 1, 0, '2026-03-20 21:26:02', '2026-03-21 00:28:46', 0, NULL, 0, NULL),
(203, 17, '1172', 'فراولة بالموز', 'Strawberry Banana', 1100.00, '', '', 'item_69bde5eb15310.jpg', 1, 0, '2026-03-20 21:26:02', '2026-03-21 00:27:23', 0, NULL, 0, NULL),
(204, 17, '1173', 'كيوي', 'Kiwi Juice', 1200.00, '', '', 'item_69bde57b0e439.jpg', 1, 0, '2026-03-20 21:26:02', '2026-03-21 00:25:31', 0, NULL, 0, NULL),
(205, 17, '1002', 'جوافة', 'Guava Juice', 1000.00, '', '', 'item_69bde4bd4c3b5.jpg', 1, 0, '2026-03-20 21:26:02', '2026-05-24 20:06:53', 0, NULL, 0, NULL),
(206, 17, '1175', 'بطيخ', 'Watermelon Juice', 1000.00, '', '', 'item_69bde32390b02.jpg', 1, 0, '2026-03-20 21:26:02', '2026-03-21 00:15:31', 0, NULL, 0, NULL),
(207, 17, '1176', 'شمام', 'Melon Juice', 1000.00, '', '', 'item_69bde5bb47165.jpg', 1, 0, '2026-03-20 21:26:02', '2026-03-21 00:26:35', 0, NULL, 0, NULL),
(208, 17, '1177', 'تفاح كبس', 'Fresh Apple Juice', 2000.00, '', '', 'item_69bde37b0ade0.jpg', 1, 0, '2026-03-20 21:26:02', '2026-03-21 00:16:59', 0, NULL, 0, NULL),
(209, 17, '139/138', 'زنجبيل', 'Ginger Juice', 800.00, '', '', 'item_69bde44d4482e.jpg', 1, 0, '2026-03-20 21:26:02', '2026-05-26 21:44:56', 0, NULL, 0, NULL),
(210, 17, '5057', 'أفوكادو', 'Avocado Juice', 2000.00, '', '', 'item_69bde2791e05a.jpg', 1, 0, '2026-03-20 21:26:02', '2026-05-25 15:44:05', 0, NULL, 0, NULL),
(211, 17, '1180', 'عوار قلب', 'Awar Juice', 2000.00, '', '', 'item_69bde7538e720.jpg', 1, 0, '2026-03-20 21:26:02', '2026-05-25 15:43:07', 0, NULL, 0, NULL),
(212, 17, '1181', 'سلطه فواكه عادي', 'Fruit Salad Regular', 1500.00, '', '', 'item_69bde59bcc0c4.jpg', 1, 0, '2026-03-20 21:26:02', '2026-03-21 00:26:03', 0, NULL, 0, NULL),
(213, 17, '1182', 'صحن فواكه سبيشل', 'Fruit Platter Special', 3000.00, '', '', 'item_69bde61c0d890.jpg', 1, 0, '2026-03-20 21:26:02', '2026-03-21 00:28:12', 0, NULL, 0, NULL),
(214, 4, '1183', 'مشكل عادي', 'Mixed Dish', 800.00, '', '', NULL, 1, 0, '2026-03-20 21:46:49', '2026-03-20 21:46:49', 0, NULL, 0, NULL),
(215, 4, '1184', 'مشكل فرن', 'Oven Mixed Dish', 800.00, '', '', 'item_69be115976cf5.jpg', 1, 0, '2026-03-20 21:46:49', '2026-03-21 03:32:41', 0, NULL, 0, NULL),
(216, 4, '5013', 'مسقعة', 'Moussaka', 800.00, '', '', 'item_69bddc48142c9.jpg', 1, 0, '2026-03-20 21:46:49', '2026-05-24 20:03:35', 0, NULL, 0, NULL),
(217, 4, '1186', 'بامية', 'Okra', 800.00, '', '', 'item_69bddabb03817.jpg', 0, 0, '2026-03-20 21:46:49', '2026-05-16 10:10:38', 0, NULL, 0, NULL),
(218, 4, '1187', 'ملوخية', 'Molokhia', 800.00, '', '', 'item_69bddce614bde.jpg', 0, 0, '2026-03-20 21:46:49', '2026-05-16 10:10:24', 0, NULL, 0, NULL),
(219, 4, '1188', 'أرز أبيض', 'White Rice', 500.00, '', '', 'item_69bdd3d50dabf.jpg', 1, 0, '2026-03-20 21:46:49', '2026-05-16 10:09:54', 0, NULL, 0, NULL),
(220, 4, '709', 'أرز زربيان', 'Zurbian Rice', 700.00, '', '', 'item_69bdd4450ac6a.jpg', 1, 0, '2026-03-20 21:46:49', '2026-05-24 20:01:14', 0, NULL, 0, NULL),
(221, 4, '709/', 'أرز برياني', 'Biryani Rice', 700.00, '', '', 'item_69bdd405a462b.jpg', 1, 0, '2026-03-20 21:46:49', '2026-05-26 21:44:04', 0, NULL, 0, NULL),
(222, 4, '1191', 'دجاج شواية بالفرن حبة', 'Whole Oven Grilled Chicken', 3000.00, '', '', 'item_69bddae4d21e3.jpg', 1, 0, '2026-03-20 21:46:49', '2026-03-20 23:40:20', 0, NULL, 0, NULL),
(223, 4, '1192', 'دجاج شواية بالفرن نصف', 'Half Oven Grilled Chicken', 1500.00, '', '', 'item_69bddb42650fa.jpg', 1, 0, '2026-03-20 21:46:49', '2026-03-20 23:41:54', 0, NULL, 0, NULL),
(224, 4, '1193', 'دجاج شواية بالفرن ربع', 'Quarter Oven Grilled Chicken', 800.00, '', '', 'item_69bddb18c39ec.jpg', 1, 0, '2026-03-20 21:46:49', '2026-03-20 23:41:12', 0, NULL, 0, NULL),
(225, 4, '5018', 'سحاوق عادي', 'Sahawiq (Regular)', 300.00, '', '', 'item_69bddbc424fd3.jpg', 1, 0, '2026-03-20 21:46:49', '2026-05-24 19:56:21', 0, NULL, 0, NULL),
(226, 4, '5019', 'سحاوق بالجبن', 'Sahawiq with Cheese', 500.00, '', '', 'item_69bddb8d7c132.jpg', 1, 0, '2026-03-20 21:46:49', '2026-05-24 19:56:06', 0, NULL, 0, NULL),
(227, 4, '1196', 'عشار', 'Assar', 400.00, '', '', 'item_69bddc18cea5a.jpg', 0, 0, '2026-03-20 21:46:49', '2026-05-16 10:08:11', 0, NULL, 0, NULL),
(228, 9, '400/4008/4009', 'بيتزا دجاج', 'Chicken Pizza', 2500.00, '', '', 'item_69bde00f21f47.jpg', 1, 0, '2026-03-21 00:02:23', '2026-05-24 19:55:25', 1, '[{\"name_ar\":\"أطراف جبن بيتزا كبير\",\"name_en\":\"Large pizza cheese edges\",\"price\":500},{\"name_ar\":\"أطراف جبن بيتزا صغير\",\"name_en\":\"Mini pizza cheese edges\",\"price\":250},{\"name_ar\":\"أطراف جبن بيتزا وسط\",\"name_en\":\"middle pizza cheese edges\",\"price\":500}]', 1, '[{\"name_ar\":\"صغير\",\"name_en\":\"small\",\"price\":2500},{\"name_ar\":\"متوسط\",\"name_en\":\"middle\",\"price\":3500},{\"name_ar\":\"كبير\",\"name_en\":\"Large\",\"price\":4500}]'),
(229, 9, '1198', 'بيتزا بيبروني', 'Pepperoni Pizza', 3000.00, '', '', 'item_69bde41e4ba47.jpg', 0, 0, '2026-03-21 00:19:42', '2026-05-16 10:07:50', 1, '[{\"name_ar\":\"أطراف جبن بيتزا كبير\",\"name_en\":\"Large pizza cheese edges\",\"price\":500},{\"name_ar\":\"أطراف جبن بيتزا صغير\",\"name_en\":\"Mini pizza cheese edges\",\"price\":250},{\"name_ar\":\"أطراف جبن بيتزا وسط\",\"name_en\":\"middle pizza cheese edges\",\"price\":500}]', 1, '[{\"name_ar\":\"صغير\",\"name_en\":\"small\",\"price\":3000},{\"name_ar\":\"وسط\",\"name_en\":\"middle\",\"price\":4000},{\"name_ar\":\"كبير\",\"name_en\":\"Large\",\"price\":5000}]'),
(230, 9, '4066/4067', 'بيتزا سبأ', 'Sheba Pizza', 3400.00, '', '', 'item_69bde63233522.jpg', 1, 0, '2026-03-21 00:21:39', '2026-05-24 19:54:01', 1, '[{\"name_ar\":\"أطراف جبن بيتزا كبير\",\"name_en\":\"Large pizza cheese edges\",\"price\":500},{\"name_ar\":\"أطراف جبن بيتزا صغير\",\"name_en\":\"Mini pizza cheese edges\",\"price\":250},{\"name_ar\":\"أطراف جبن بيتزا وسط\",\"name_en\":\"middle pizza cheese edges\",\"price\":500}]', 1, '[{\"name_ar\":\"صغير\",\"name_en\":\"small\",\"price\":3400},{\"name_ar\":\"متوسط\",\"name_en\":\"middle\",\"price\":4400},{\"name_ar\":\"كبير\",\"name_en\":\"Large\",\"price\":5400}]'),
(231, 9, '406', 'بيتزا ميكس تشيز', 'Mixed Cheese Pizza', 2600.00, '', '', 'item_69bdea8d6a3de.jpg', 1, 0, '2026-03-21 00:21:39', '2026-05-24 19:53:20', 1, '[{\"name_ar\":\"أطراف جبن بيتزا كبير\",\"name_en\":\"big pizza cheese edges\",\"price\":500},{\"name_ar\":\"أطراف جبن بيتزا وسط\",\"name_en\":\"middle pizza cheese edges\",\"price\":500},{\"name_ar\":\"أطراف جبن بيتزا صغير\",\"name_en\":\"Mini pizza cheese edges\",\"price\":250}]', 1, '[{\"name_ar\":\"صغير\",\"name_en\":\"small\",\"price\":2600},{\"name_ar\":\"متوسط\",\"name_en\":\"middle\",\"price\":3600},{\"name_ar\":\"كبير\",\"name_en\":\"Large\",\"price\":4600}]'),
(232, 9, '1201', 'بيتزا مارجريتا', 'Margherita Pizza', 2000.00, '', '', 'item_69bde95f4048a.jpg', 1, 0, '2026-03-21 00:21:39', '2026-05-15 16:31:47', 1, '[{\"name_ar\":\"أطراف جبن بيتزا كبير\",\"name_en\":\"big pizza cheese edges\",\"price\":500},{\"name_ar\":\"أطراف جبن بيتزا وسط\",\"name_en\":\"middle pizza cheese edges\",\"price\":500},{\"name_ar\":\"أطراف جبن بيتزا صغير\",\"name_en\":\"Mini pizza cheese edges\",\"price\":250}]', 1, '[{\"name_ar\":\"صغير\",\"name_en\":\"small\",\"price\":2000},{\"name_ar\":\"متوسط\",\"name_en\":\"middle\",\"price\":3000},{\"name_ar\":\"كبير\",\"name_en\":\"Large\",\"price\":4000}]'),
(233, 9, '4003/4078', 'بيتزا خضار', 'Vegetable Pizza', 2000.00, '', '', 'item_69bde54ec6546.jpg', 1, 0, '2026-03-21 00:21:39', '2026-05-24 19:52:30', 1, '[{\"name_ar\":\"أطراف جبن بيتزا كبير\",\"name_en\":\"Large pizza cheese edges\",\"price\":500},{\"name_ar\":\"أطراف جبن بيتزا صغير\",\"name_en\":\"Mini pizza cheese edges\",\"price\":250},{\"name_ar\":\"أطراف جبن بيتزا وسط\",\"name_en\":\"middle pizza cheese edges\",\"price\":500}]', 1, '[{\"name_ar\":\"صغير\",\"name_en\":\"small\",\"price\":2000},{\"name_ar\":\"متوسط\",\"name_en\":\"middle\",\"price\":3300},{\"name_ar\":\"كبير\",\"name_en\":\"Large\",\"price\":4000}]'),
(234, 13, '1203', 'آيس شوكليت', 'Ice Chocolate', 1000.00, '', '', 'item_69be0393586b2.jpg', 1, 0, '2026-03-21 02:23:23', '2026-05-25 15:50:46', 0, NULL, 0, NULL),
(235, 13, '1204', 'آيس موكا', 'Ice Mocha', 1300.00, '', '', 'item_69be0410aff90.jpg', 1, 0, '2026-03-21 02:23:23', '2026-05-25 15:50:25', 0, NULL, 0, NULL),
(236, 13, '1205', 'آيس كراميل لاتيه', 'Ice Caramel Latte', 1300.00, '', '', 'item_69be03c9eddf8.jpg', 1, 0, '2026-03-21 02:23:23', '2026-03-21 02:34:49', 0, NULL, 0, NULL),
(237, 13, '1206', 'آيس لاتيه', 'Ice Latte', 1000.00, '', '', 'item_69be03fe29705.jpg', 1, 0, '2026-03-21 02:23:23', '2026-03-21 02:35:42', 0, NULL, 0, NULL),
(238, 13, '1207', 'آيس سبانش لاتيه', 'Ice Spanish Latte', 1500.00, '', '', 'item_69be036712cc1.jpg', 1, 0, '2026-03-21 02:23:23', '2026-03-21 02:33:11', 0, NULL, 0, NULL),
(239, 13, '1208', 'آيس روز لاتيه', 'Ice Rose Latte', 1500.00, '', '', 'item_69be02f116b9c.jpg', 1, 0, '2026-03-21 02:23:23', '2026-03-21 02:31:13', 0, NULL, 0, NULL),
(240, 13, '1209', 'آيس بلو لاتيه', 'Ice Blue Latte', 1500.00, '', '', 'item_69be02d35847f.jpg', 1, 0, '2026-03-21 02:23:23', '2026-03-21 02:30:43', 0, NULL, 0, NULL),
(241, 13, '1210', 'آيس أمريكانو', 'Iced Americano', 800.00, '', '', 'item_69be02899608e.jpg', 1, 0, '2026-03-21 02:23:23', '2026-03-21 02:29:29', 0, NULL, 0, NULL),
(242, 13, '1022', 'ميلك شيك كوفي', 'Coffee Milkshake', 2000.00, '', '', 'item_69be050f608f8.jpg', 1, 0, '2026-03-21 02:23:23', '2026-05-24 19:50:34', 0, NULL, 0, NULL),
(243, 13, '1212', 'كركديه', 'Hibiscus', 800.00, '', '', 'item_69be04c209586.jpg', 0, 0, '2026-03-21 02:23:23', '2026-05-20 10:11:15', 0, NULL, 0, NULL),
(244, 13, '1213', 'أناناس مانجو موز سكريم', 'Pineapple Mango Banana Cream', 1500.00, '', '', 'item_69be044c7e9dc.jpg', 1, 0, '2026-03-21 02:23:23', '2026-03-21 02:37:00', 0, NULL, 0, NULL),
(245, 13, '1214', 'كيوي تفاح أفوكادو', 'Kiwi Apple Avocado', 1700.00, '', '', 'item_69be04e26443a.jpg', 1, 0, '2026-03-21 02:23:23', '2026-03-21 02:39:30', 0, NULL, 0, NULL),
(246, 13, '1215', 'أفوكادو مانجو فراولة حليب', 'Avocado Mango Strawberry Milk', 2000.00, '', '', 'item_69be043b5cd63.jpg', 1, 0, '2026-03-21 02:23:23', '2026-03-21 02:36:43', 0, NULL, 0, NULL),
(247, 13, '1216', 'عنب أفوكادو فراولة', 'Grape Avocado Strawberry', 2000.00, '', '', 'item_69be049b20ab9.jpg', 1, 0, '2026-03-21 02:23:23', '2026-03-21 02:38:19', 0, NULL, 0, NULL),
(248, 18, '1227', 'ايسكريم شوكولاتة', 'Ice cream Chocolate', 700.00, '', '', 'item_69be0ed6ed22c.jpg', 1, 0, '2026-03-21 02:56:07', '2026-05-02 04:39:37', 0, NULL, 0, NULL),
(249, 18, '311', 'ايسكريم فانيليا', 'Ice cream Vanilla', 700.00, '', '', 'item_69be0eca95531.jpg', 1, 0, '2026-03-21 02:56:07', '2026-05-24 15:31:13', 0, NULL, 0, NULL),
(250, 18, '311', 'ايسكريم فراولة', 'Ice cream Strawberry', 700.00, '', '', 'item_69be0ebc9e026.jpg', 1, 0, '2026-03-21 02:56:07', '2026-05-24 15:31:06', 0, NULL, 0, NULL),
(251, 18, '311', 'ايسكريم حمضيات', 'Ice cream Citrus', 700.00, '', '', 'item_69be0ee9ac362.jpg', 1, 0, '2026-03-21 02:56:07', '2026-05-24 15:30:59', 0, NULL, 0, NULL),
(252, 18, '311', 'ايسكريم بلو بيري', 'Ice cream Blueberry', 700.00, '', '', 'item_69be0f02139b8.jpg', 1, 0, '2026-03-21 02:56:07', '2026-05-24 15:30:51', 0, NULL, 0, NULL),
(253, 18, '311', 'ايسكريم مانجو', 'Ice cream Mango', 700.00, '', '', 'item_69be0f3fc8aed.jpg', 1, 0, '2026-03-21 02:56:07', '2026-05-24 15:30:44', 0, NULL, 0, NULL),
(254, 18, '311', 'ايسكريم لوتس', 'Ice cream Lotus', 700.00, '', '', 'item_69be0f28c6fd9.jpg', 1, 0, '2026-03-21 02:56:07', '2026-05-24 15:30:36', 0, NULL, 0, NULL),
(255, 13, '1217', 'فرابيه', 'Frappe', 1300.00, '', '', 'item_69be09e045aed.jpg', 1, 0, '2026-03-21 03:00:48', '2026-05-25 15:49:42', 0, NULL, 1, '[{\"name_ar\":\"شوكولاتة\",\"name_en\":\"Chocolate\",\"price\":1300},{\"name_ar\":\"كراميل\",\"name_en\":\"Caramel\",\"price\":1300},{\"name_ar\":\"فانيلا\",\"name_en\":\"Vanilla\",\"price\":1000},{\"name_ar\":\"توفي\",\"name_en\":\"Toffee\",\"price\":1300},{\"name_ar\":\"بندق\",\"name_en\":\"Hazelnut\",\"price\":1300}]'),
(256, 13, '1218', 'فربتشينو', 'Frappuccino', 1200.00, '', '', 'item_69be0abf2e538.jpg', 1, 0, '2026-03-21 03:04:31', '2026-05-25 15:49:08', 0, NULL, 1, '[{\"name_ar\":\"كراميل\",\"name_en\":\"Caramel\",\"price\":1200},{\"name_ar\":\"فانيلا\",\"name_en\":\"Vanilla\",\"price\":1200},{\"name_ar\":\"توفي\",\"name_en\":\"Toffee\",\"price\":1500},{\"name_ar\":\"بندق\",\"name_en\":\"Hazelnut\",\"price\":1500},{\"name_ar\":\"شوكولاتة\",\"name_en\":\"Chocolate\",\"price\":1500}]'),
(257, 13, '1018/1020', 'سموذي', 'Smoothie', 800.00, '', '', 'item_69be0b99df698.jpg', 1, 0, '2026-03-21 03:08:09', '2026-05-25 15:48:26', 0, NULL, 1, '[{\"name_ar\":\"فراوله\",\"name_en\":\"Strawberry\",\"price\":800},{\"name_ar\":\"كيوي\",\"name_en\":\"Kiwi\",\"price\":800},{\"name_ar\":\"بطيخ\",\"name_en\":\"Watermelon\",\"price\":800},{\"name_ar\":\"شمام\",\"name_en\":\"Melon\",\"price\":800},{\"name_ar\":\"ليمون\",\"name_en\":\"Lemon\",\"price\":600},{\"name_ar\":\"ليمون بالنعناع\",\"name_en\":\"Lemon with Mint\",\"price\":800}]'),
(258, 19, '532', 'باستا بولونيز', 'Pasta Bolognese', 3000.00, '', '', 'item_69be0f6e15abc.jpg', 1, 0, '2026-03-21 03:15:29', '2026-05-24 16:03:43', 0, NULL, 0, NULL),
(259, 19, '756', 'باستا فيتوتشيني', 'Fettuccine Pasta', 3800.00, '', '', 'item_69be0fb17a87b.jpg', 1, 0, '2026-03-21 03:15:29', '2026-05-24 16:03:22', 0, NULL, 0, NULL),
(260, 19, '757', 'باستا ماك آند تشيز', 'Mac and Cheese Pasta', 3300.00, '', '', 'item_69be0ff689477.jpg', 1, 0, '2026-03-21 03:15:29', '2026-05-24 16:02:45', 0, NULL, 0, NULL),
(261, 19, '531', 'معكرونة بالطماطم والزيتون', 'Pasta with Tomato and Olives', 1600.00, '', '', 'item_69be105422a12.jpg', 1, 0, '2026-03-21 03:15:29', '2026-05-24 16:02:14', 0, NULL, 0, NULL),
(262, 19, '1067', 'باستا بيني أرابياتا', 'Penne Arrabbiata', 1300.00, '', '', 'item_69be0f8c46f9e.jpg', 1, 0, '2026-03-21 03:15:29', '2026-05-24 16:01:46', 0, NULL, 0, NULL),
(263, 19, '1', 'فرايد رايس', 'Fried Rice', 1500.00, '', '', 'item_69be1035be626.jpg', 1, 0, '2026-03-21 03:15:29', '2026-06-03 16:34:41', 0, NULL, 0, NULL),
(264, 19, '5052', 'ريزو رايس', 'Rizo Rice', 2000.00, '', '', 'item_69be100d6985c.jpg', 1, 0, '2026-03-21 03:15:29', '2026-05-24 15:58:54', 0, NULL, 0, NULL),
(265, 19, '5051', 'ريزوتو', 'Risotto', 2000.00, '', '', 'item_69be10239b8cf.jpg', 1, 0, '2026-03-21 03:15:29', '2026-05-24 15:58:25', 0, NULL, 0, NULL),
(266, 13, '1022/1018', 'ميلك شيك', 'Milkshake', 1700.00, '', '', 'item_69be0f78738a9.jpg', 1, 0, '2026-03-21 03:24:40', '2026-05-25 15:47:45', 0, NULL, 1, '[{\"name_ar\":\"نوتيلا\",\"name_en\":\"Nutella\",\"price\":1700},{\"name_ar\":\"سنيكرس\",\"name_en\":\"Snickers\",\"price\":2000},{\"name_ar\":\"أوريو\",\"name_en\":\"Oreo\",\"price\":2000},{\"name_ar\":\"لوتس\",\"name_en\":\"Lotus\",\"price\":1700},{\"name_ar\":\"فراوله\",\"name_en\":\"Strawberry\",\"price\":1500},{\"name_ar\":\"بستاشيو\",\"name_en\":\"Pistachio\",\"price\":2000}]'),
(267, 13, '1221', 'سلاش', 'Slash', 1000.00, '', '', 'item_69be10be4c7b7.jpg', 1, 0, '2026-03-21 03:30:06', '2026-05-28 18:25:53', 0, NULL, 1, '[{\"name_ar\":\"بلوبيري\",\"name_en\":\"Blueberry\",\"price\":1000},{\"name_ar\":\"تفاح\",\"name_en\":\"Apple\",\"price\":1000},{\"name_ar\":\"خوخ\",\"name_en\":\"Peach\",\"price\":1000}]'),
(268, 13, '163/164/165', 'ايس تي', 'Iced Tea', 1200.00, '', '', 'item_69be114c05814.jpg', 1, 0, '2026-03-21 03:32:28', '2026-05-24 19:44:45', 0, NULL, 1, '[{\"name_ar\":\"خوخ\",\"name_en\":\"Peach\",\"price\":1200},{\"name_ar\":\"ريد بيري\",\"name_en\":\"Red Berry\",\"price\":1200},{\"name_ar\":\"بلاك بيري\",\"name_en\":\"Blackberry\",\"price\":1200}]'),
(269, 13, '1010/1009', 'موهيتو', 'Mojito', 1000.00, '', '', 'item_69be11f2d24fa.jpg', 1, 0, '2026-03-21 03:35:14', '2026-05-25 15:46:13', 0, NULL, 1, '[{\"name_ar\":\"كيوي\",\"name_en\":\"Kiwi\",\"price\":1000},{\"name_ar\":\"فراولة\",\"name_en\":\"Strawberry\",\"price\":1000},{\"name_ar\":\"تفاح\",\"name_en\":\"Apple\",\"price\":1000},{\"name_ar\":\"خوخ\",\"name_en\":\"Peach\",\"price\":1000},{\"name_ar\":\"كرز\",\"name_en\":\"Cherry\",\"price\":1000},{\"name_ar\":\"بلو بيري\",\"name_en\":\"Blueberry\",\"price\":1000}]'),
(292, 4, '5010', 'دجاج على الفحم حبة', 'Whole Grilled Chicken', 3000.00, '', '', '', 1, 0, '2026-03-21 03:49:31', '2026-05-24 19:42:56', 0, NULL, 0, NULL),
(293, 4, '5011', 'دجاج على الفحم نصف', 'Half Grilled Chicken', 1500.00, '', '', '', 1, 0, '2026-03-21 03:49:31', '2026-05-24 19:42:41', 0, NULL, 0, NULL),
(294, 21, '1266', 'لحم مندي ربع ذبيحة مع الأرز', 'Mandi Lamb (Quarter Carcass) with Rice', 15000.00, '', '', 'item_69be199f4d0fb.jpg', 1, 0, '2026-03-21 03:51:21', '2026-03-21 04:07:59', 0, NULL, 0, NULL),
(295, 21, '4091', 'نصف دجاج مندي مع الأرز', 'Half Mandi Chicken with Rice', 2000.00, '', '', 'item_69be180bad0fc.jpg', 1, 0, '2026-03-21 03:51:21', '2026-06-03 16:27:30', 0, NULL, 0, NULL),
(296, 21, '709//', 'رز مندي نفر', 'Mandi Rice (Single)', 700.00, '', '', 'item_69be193a40b08.jpg', 1, 0, '2026-03-21 03:51:21', '2026-06-03 16:27:54', 0, NULL, 0, NULL),
(297, 21, '4092', 'ربع دجاج مندي', 'Quarter Mandi Chicken', 800.00, '', '', 'item_69be189dbb5c1.jpg', 1, 0, '2026-03-21 03:51:21', '2026-06-03 16:24:02', 0, NULL, 0, NULL),
(298, 21, '708', 'لحم حنيذ نصف كيلو', 'Haneeth Lamb (Half Kilo)', 4000.00, '', '', 'item_69be19710705a.jpg', 1, 0, '2026-03-21 03:51:21', '2026-06-03 16:21:20', 0, NULL, 0, NULL),
(299, 21, '4093', 'ربع ذبيحة لحم حنيذ', 'Haneeth Lamb (Quarter Carcass)', 13000.00, '', '', 'item_69be18bc30580.jpg', 1, 0, '2026-03-21 03:51:21', '2026-06-03 16:23:52', 0, NULL, 0, NULL),
(300, 21, '4094', 'نصف دجاج حنيذ مع الرز', 'Half Haneeth Chicken', 2500.00, '', '', 'item_69be1ab05869a.jpg', 1, 2, '2026-03-21 03:51:21', '2026-05-23 18:10:26', 0, NULL, 0, NULL),
(301, 21, '4095', 'ربع دجاج مضغوط مع الأرز', 'Quarter Chicken with Rice', 1500.00, '', '', 'item_69be18140f502.jpg', 1, 0, '2026-03-21 03:51:21', '2026-06-03 16:18:22', 0, NULL, 0, NULL),
(302, 21, '4096', 'نصف دجاج مضغوط مع الأرز', 'Half Chicken with Rice', 2500.00, '', '', 'item_69be1ae9b1297.jpg', 1, 2, '2026-03-21 03:51:21', '2026-06-03 16:20:23', 0, NULL, 0, NULL),
(303, 21, '4097', 'حبة دجاج مضغوط مع الأرز', 'Whole Madghout Chicken with Rice', 5000.00, '', '', 'item_69be17f88a090.jpg', 1, 0, '2026-03-21 03:51:21', '2026-06-03 16:19:51', 0, NULL, 0, NULL),
(304, 21, '1276', 'رز مضغوط صافي', 'Plain Madghout Rice', 700.00, '', '', 'item_69be19085c390.jpg', 0, 0, '2026-03-21 03:51:21', '2026-05-16 10:01:23', 0, NULL, 0, NULL),
(305, 21, '5021', 'نفر سلته', 'nfar salta', 2500.00, '', '', 'item_69be1a074766e.webp', 1, 0, '2026-03-21 03:52:25', '2026-05-23 18:09:47', 0, NULL, 0, NULL),
(306, 21, '5022', 'نفر فحسه رضيع', 'fhsa nfr rdeh', 4000.00, '', '', 'item_69be1a70616fc.webp', 1, 0, '2026-03-21 03:52:25', '2026-05-23 18:09:24', 0, NULL, 0, NULL),
(307, 13, '1224', 'سن شاين طاقه', 'Sunshine', 1300.00, '', '', 'item_69be17ba8129a.jpg', 1, 0, '2026-03-21 03:59:54', '2026-03-21 04:00:16', 0, NULL, 1, '[{\"name_ar\":\"كيوي\",\"name_en\":\"Kiwi\",\"price\":1300},{\"name_ar\":\"فراولة\",\"name_en\":\"Strawberry\",\"price\":1300},{\"name_ar\":\"تفاح\",\"name_en\":\"Apple\",\"price\":1300},{\"name_ar\":\"خوخ\",\"name_en\":\"Peach\",\"price\":1300},{\"name_ar\":\"كرز\",\"name_en\":\"Cherry\",\"price\":1300},{\"name_ar\":\"بلو بيري\",\"name_en\":\"Blueberry\",\"price\":1300}]'),
(308, 13, '1012/168', 'سن شاين عادي', 'Sunshine', 1200.00, '', '', 'item_69be18ce1b206.jpg', 1, 0, '2026-03-21 04:04:30', '2026-05-25 15:45:42', 0, NULL, 1, '[{\"name_ar\":\"كيوي\",\"name_en\":\"Kiwi\",\"price\":1200},{\"name_ar\":\"فراولة\",\"name_en\":\"Strawberry\",\"price\":1000},{\"name_ar\":\"تفاح\",\"name_en\":\"Apple\",\"price\":1000},{\"name_ar\":\"خوخ\",\"name_en\":\"Peach\",\"price\":1000},{\"name_ar\":\"كرز\",\"name_en\":\"Cherry\",\"price\":1000},{\"name_ar\":\"بلو بيري\",\"name_en\":\"Blueberry\",\"price\":1000}]'),
(309, 13, '1000', 'ماء سام', 'Water', 200.00, '', '', 'item_69be6b22dc60a.jpg', 1, 0, '2026-03-21 09:55:46', '2026-03-21 09:55:46', 0, NULL, 1, '[{\"name_ar\":\"كبير 750\",\"name_en\":\"-\",\"price\":200},{\"name_ar\":\"متوسط 200\",\"name_en\":\"-\",\"price\":100}]'),
(310, 13, '201', 'مشروب غازي', 'mshrop kaze', 500.00, '', '', 'item_69be8a6c10b44.jpg', 1, 0, '2026-03-21 12:09:16', '2026-05-23 18:07:44', 0, NULL, 0, NULL),
(311, 4, '5007', 'شيش طاووق 2 اسياخ', 'Shish Tawook', 2500.00, '', '', 'item_69bff2131bbc1.jpg', 1, 0, '2026-03-22 13:43:47', '2026-05-23 18:07:24', 0, NULL, 0, NULL),
(316, 4, '1271', 'اوصلل لحم 2 اسياخ', 'Beef Kebab', 2800.00, '', '', 'item_69bff3ef523b3.jpg', 1, 0, '2026-03-22 13:51:43', '2026-06-06 10:29:22', 0, NULL, 0, NULL),
(317, 4, '5008', 'كباب لحم 3 اسياخ', '-', 2600.00, '', '', 'item_69bff44436688.jpg', 1, 0, '2026-03-22 13:53:08', '2026-06-06 10:30:14', 0, NULL, 0, NULL),
(318, 4, '5009', 'كباب دجاج 3 اسياخ', 'Chicken Kebab', 2300.00, '', '', 'item_69bff487e4b3c.jpg', 1, 0, '2026-03-22 13:54:15', '2026-06-06 10:30:30', 0, NULL, 0, NULL),
(319, 8, '4089/4099/5000', 'بروست', 'Broasted Chicken', 1500.00, '', '', 'item_69cbd436c45b8.jpg', 1, 0, '2026-03-31 14:03:34', '2026-06-01 18:26:05', 0, NULL, 1, '[{\"name_ar\":\"2 قطع\",\"name_en\":\"2 piece\",\"price\":1500},{\"name_ar\":\"4 قطع\",\"name_en\":\"4 piece\",\"price\":3000},{\"name_ar\":\"8 قطع\",\"name_en\":\"8 piece\",\"price\":6000}]'),
(320, 10, '712', 'شوارما عربي', 'Shawarma', 1200.00, '', '', 'item_69cd26f6b6b7d.jpg', 1, 0, '2026-04-01 14:08:54', '2026-05-23 18:04:00', 0, NULL, 0, NULL),
(321, 8, '3012', 'تشيكن فرايز', 'Chicken Fries', 2700.00, '', '', 'item_69cd293c8b085.webp', 1, 0, '2026-04-01 14:18:36', '2026-05-23 18:03:09', 0, NULL, 0, NULL),
(322, 13, '1380', 'هافانا', 'Havana', 2000.00, '', '', '', 0, 0, '2026-04-02 12:50:49', '2026-05-20 10:02:12', 0, NULL, 1, '[{\"name_ar\":\"أنانس\",\"name_en\":\"pineapple\",\"price\":2000},{\"name_ar\":\"تفاح\",\"name_en\":\"apple\",\"price\":2000},{\"name_ar\":\"برتقال\",\"name_en\":\"orange\",\"price\":2000}]'),
(323, 3, '302', 'دونات', 'Donat', 500.00, '', '', 'item_69ce7f8465615.jpeg', 1, 0, '2026-04-02 14:39:00', '2026-05-24 15:34:47', 0, NULL, 0, NULL),
(324, 22, '50275028', 'زيت زعتر', 'Zaatar with Olive Oil', 800.00, '', '', NULL, 1, 0, '2026-04-04 07:32:11', '2026-04-04 07:32:11', 0, NULL, 0, NULL),
(325, 22, '5028', 'زعتر مع الخضار', 'Zaatar with Vegetables', 1000.00, '', '', '', 1, 0, '2026-04-04 07:32:11', '2026-05-23 18:02:18', 0, NULL, 0, NULL),
(326, 22, '5029', 'زعتر مع طماط', 'Zaatar with Tomato', 900.00, '', '', '', 1, 0, '2026-04-04 07:32:11', '2026-05-23 18:01:36', 0, NULL, 0, NULL),
(327, 22, '5030', 'زعتر مع جبن', 'Zaatar with Cheese', 1300.00, '', '', '', 1, 0, '2026-04-04 07:32:11', '2026-05-23 18:01:12', 0, NULL, 0, NULL),
(328, 22, '5033', 'زعتر مع لبنه', 'Zaatar with Labneh', 1100.00, '', '', NULL, 1, 0, '2026-04-04 07:32:11', '2026-04-04 07:32:11', 0, NULL, 0, NULL),
(329, 22, '5030', 'لبنه مع عسل', 'Labneh with Honey', 1300.00, '', '', '', 1, 0, '2026-04-04 07:32:11', '2026-05-23 18:00:10', 0, NULL, 0, NULL),
(330, 22, '5033', 'لبنه صافي', 'Plain Labneh', 900.00, '', '', '', 1, 0, '2026-04-04 07:32:11', '2026-05-23 17:58:32', 0, NULL, 0, NULL),
(331, 22, '5034', 'بطاط مع لبنه', 'Potato with Labneh', 1300.00, '', '', '', 1, 0, '2026-04-04 07:32:11', '2026-05-23 17:53:34', 0, NULL, 0, NULL),
(332, 22, '5028', 'بطاط مع خضار', 'Potato with Vegetables', 1300.00, '', '', '', 1, 0, '2026-04-04 07:32:11', '2026-05-23 17:53:08', 0, NULL, 0, NULL),
(333, 22, '5040', 'بطاط', 'Potato', 1000.00, '', '', NULL, 1, 0, '2026-04-04 07:32:11', '2026-04-04 07:32:11', 0, NULL, 0, NULL),
(334, 22, '50455041', 'بطاط مع جبن', 'Potato with Cheese', 1300.00, '', '', NULL, 1, 0, '2026-04-04 07:32:11', '2026-04-04 07:32:11', 0, NULL, 0, NULL),
(335, 22, '5042', 'جبن مع الخضار', 'Cheese with Vegetables', 1500.00, '', '', '', 1, 0, '2026-04-04 07:32:11', '2026-05-23 17:52:46', 0, NULL, 0, NULL),
(336, 22, '5039', 'جبن مع طماط', 'Cheese with Tomato', 1300.00, '', '', '', 1, 0, '2026-04-04 07:32:11', '2026-05-23 17:52:17', 0, NULL, 0, NULL),
(337, 22, '5044', 'كرفت', 'Kraft Cheese', 1300.00, '', '', NULL, 1, 0, '2026-04-04 07:32:11', '2026-04-04 07:32:11', 0, NULL, 0, NULL),
(338, 22, NULL, 'شيدر', 'Cheddar Cheese', 1600.00, '', '', NULL, 1, 0, '2026-04-04 07:32:11', '2026-04-04 07:32:11', 0, NULL, 0, NULL);
INSERT INTO `items` (`id`, `category_id`, `item_number`, `name_ar`, `name_en`, `price`, `description_ar`, `description_en`, `image`, `is_available`, `sort_order`, `created_at`, `updated_at`, `has_addons`, `addons`, `has_sizes`, `sizes`) VALUES
(339, 22, '5042', 'قشقوان', 'Kashkaval Cheese', 1600.00, '', '', '', 1, 0, '2026-04-04 07:32:11', '2026-05-23 17:51:22', 0, NULL, 0, NULL),
(340, 22, '5043', 'محمرة مع قشقوان', 'Muhammara with Kashkaval', 1600.00, '', '', '', 1, 0, '2026-04-04 07:32:11', '2026-05-23 17:50:19', 0, NULL, 0, NULL),
(341, 22, '5044', 'شيدر مع قشقوان', 'Cheddar with Kashkaval', 1700.00, '', '', '', 1, 0, '2026-04-04 07:32:11', '2026-05-23 17:48:39', 0, NULL, 0, NULL),
(342, 22, NULL, 'محمرة مع شيدر', 'Muhammara with Cheddar', 1600.00, '', '', NULL, 1, 0, '2026-04-04 07:32:11', '2026-04-04 07:32:11', 0, NULL, 0, NULL),
(343, 13, '1389', 'مشروب طاقه', 'power drink', 800.00, '', '', 'item_69d28472acef4.png', 1, 0, '2026-04-05 15:49:06', '2026-05-04 21:51:33', 0, NULL, 0, NULL),
(344, 8, '751', 'مانجوليان سمك', 'Mangolen fish', 4000.00, '', '', '', 1, 0, '2026-04-06 11:13:13', '2026-05-24 14:47:18', 0, NULL, 0, NULL),
(345, 8, '5047', 'سولت اند بيبر تشيكن', 'Solt and pepr chicken', 2500.00, '', '', 'item_69d395e9354db.jpg', 1, 0, '2026-04-06 11:15:53', '2026-05-23 17:43:46', 0, NULL, 0, NULL),
(346, 4, '5048/5049', 'مشاوي مشكل', 'mshawe moshkl', 8000.00, '', '', 'item_69e0c413d0a1c.webp', 1, 0, '2026-04-16 11:12:19', '2026-05-23 17:40:58', 1, '[{\"name_ar\":\"فطيره كبير\",\"name_en\":\"fatera big\",\"price\":1000},{\"name_ar\":\"فطيره صغير\",\"name_en\":\"fatera small\",\"price\":500}]', 1, '[{\"name_ar\":\"كبير\",\"name_en\":\"big\",\"price\":8000},{\"name_ar\":\"صغير\",\"name_en\":\"small\",\"price\":4000}]'),
(347, 13, '5060', 'كوب ثلج', 'ice cup', 100.00, '', '', 'item_69f9eb99bae43.jpg', 1, 0, '2026-05-05 13:07:37', '2026-05-05 13:07:37', 0, NULL, 0, NULL),
(348, 3, '5050', 'جاتو صغير', 'mini cake', 800.00, '', '', 'item_69f9ec2230c7b.jpg', 1, 0, '2026-05-05 13:09:54', '2026-05-24 15:32:24', 0, NULL, 0, NULL),
(349, 11, '1060', 'سلطة خضراء عربي', 'SALAT', 1000.00, '', '', 'item_6a085354133ff.jpg', 1, 0, '2026-05-16 11:21:56', '2026-05-16 11:21:56', 0, NULL, 0, NULL),
(350, 11, '1061', 'سلطة زبادي', 'SALAT', 1200.00, '', '', 'item_6a0853d63460a.jpg', 1, 0, '2026-05-16 11:24:06', '2026-05-16 11:24:06', 0, NULL, 0, NULL),
(351, 17, '158', 'عصير كوكتيل', 'max', 2000.00, '', '', '', 1, 0, '2026-05-23 14:54:55', '2026-05-24 08:16:13', 0, NULL, 0, NULL),
(405, 5, '139', 'زنجبيل بالحليب ساخن', 'Ginger milk', 800.00, '', '', '', 1, 0, '2026-05-23 17:12:32', '2026-05-24 08:11:28', 0, NULL, 0, NULL),
(406, 3, '11112', 'كريم كراميل', 'Karamel', 500.00, '', '', '', 1, 0, '2026-05-28 18:52:32', '2026-05-28 18:52:32', 0, NULL, 0, NULL),
(407, 8, '11114', 'اوبن بوفيه غدا', 'Open bufe', 9000.00, '', '', 'item_6a18abe4ac831.jpg', 1, 0, '2026-05-28 20:46:09', '2026-05-28 20:56:04', 0, NULL, 1, '[{\"name_ar\":\"كبار\",\"name_en\":\"O\",\"price\":9000},{\"name_ar\":\"صغار\",\"name_en\":\"Y\",\"price\":4500}]'),
(408, 8, '1098', 'كيس خبز', 'Bread', 200.00, '', '', '', 1, 0, '2026-05-30 16:59:01', '2026-06-03 17:05:40', 0, NULL, 0, NULL),
(409, 13, '1099', 'ليفت اب', 'Left', 600.00, '', '', 'item_6a1c550e3293c.webp', 1, 0, '2026-05-31 15:34:38', '2026-06-03 17:05:28', 0, NULL, 0, NULL),
(410, 19, '66066', 'مكرونه نودلز صيني', 'Chinese', 2000.00, '', '', '', 1, 0, '2026-06-04 13:17:46', '2026-06-04 13:17:46', 0, NULL, 0, NULL),
(411, 5, '10159', 'بن يا الحليب', 'Bon withe millk', 1500.00, '', '', '', 1, 0, '2026-06-10 15:12:55', '2026-06-10 15:12:55', 0, NULL, 0, NULL),
(412, 10, '5001', 'ساندوتش بيض', 'Eggs sandwich', 600.00, '', '', '', 1, 0, '2026-06-18 20:52:10', '2026-06-18 20:52:10', 0, NULL, 0, NULL),
(413, 10, '5002', 'ساندوتش مشكل اجبان', 'Cheese sandwich', 1000.00, '', '', '', 1, 0, '2026-06-18 20:53:08', '2026-06-18 20:55:35', 0, NULL, 0, NULL),
(414, 3, '5003', 'سويس رول بستاشيو', 'Pistachio Swiss Roll', 1500.00, '', '', '', 1, 0, '2026-06-26 16:23:32', '2026-06-26 16:23:32', 0, NULL, 0, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `item_audit_log`
--

-- Table structure for table `item_audit_log`
CREATE TABLE `item_audit_log` (
  `id` int NOT NULL,
  `item_id` int NOT NULL,
  `user_id` int NOT NULL,
  `action_type` enum('create','update','delete') NOT NULL,
  `field_name` varchar(50) DEFAULT NULL,
  `old_value` text,
  `new_value` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Table structure for table `item_ingredients`
CREATE TABLE `item_ingredients` (
  `id` int NOT NULL,
  `item_id` int NOT NULL,
  `ingredient_id` int NOT NULL,
  `quantity_per_portion` decimal(10,3) NOT NULL DEFAULT '0.000',
  `notes` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Table structure for table `item_stock`
CREATE TABLE `item_stock` (
  `id` int NOT NULL,
  `item_id` int NOT NULL,
  `stock_qty` decimal(10,2) NOT NULL DEFAULT '0.00',
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- Table structure for table `item_stock_log`
CREATE TABLE `item_stock_log` (
  `id` int NOT NULL,
  `item_id` int NOT NULL,
  `item_name_ar` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `action_type` varchar(30) COLLATE utf8mb4_general_ci NOT NULL,
  `qty_before` decimal(10,2) DEFAULT NULL,
  `qty_change` decimal(10,2) DEFAULT NULL,
  `qty_after` decimal(10,2) DEFAULT NULL,
  `note` varchar(500) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `user_id` int DEFAULT NULL,
  `user_name` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- Table structure for table `manual_sales`
CREATE TABLE `manual_sales` (
  `id` int NOT NULL,
  `item_id` int NOT NULL,
  `quantity` decimal(10,2) NOT NULL DEFAULT '1.00',
  `sale_date` date NOT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Table structure for table `messages`
CREATE TABLE `messages` (
  `id` int NOT NULL,
  `sender_id` int NOT NULL,
  `receiver_id` int DEFAULT NULL COMMENT 'NULL = broadcast to all',
  `message` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_read` tinyint(1) DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Table structure for table `offers`
CREATE TABLE `offers` (
  `id` int NOT NULL,
  `name_ar` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name_en` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `price` decimal(10,2) NOT NULL,
  `image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Table structure for table `offer_items`
CREATE TABLE `offer_items` (
  `id` int NOT NULL,
  `offer_id` int NOT NULL,
  `item_id` int NOT NULL,
  `quantity` int DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Table structure for table `orders`
CREATE TABLE `orders` (
  `id` int NOT NULL,
  `order_number` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `table_number` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `waiter_id` int NOT NULL,
  `cashier_id` int DEFAULT NULL,
  `status` enum('pending','sent_to_cashier','confirmed','in_progress','ready','paid','cancelled','delivered','refunded','partially_refunded') COLLATE utf8mb4_unicode_ci DEFAULT 'pending',
  `is_paid_once` tinyint(1) DEFAULT '0',
  `print_count` int DEFAULT '0',
  `kitchen_print_count` int DEFAULT '0',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `direct_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `customer_type` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'normal',
  `customer_ref` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `guest_name` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `subtotal` decimal(10,2) DEFAULT '0.00',
  `discount` decimal(10,2) DEFAULT '0.00',
  `tax` decimal(10,2) DEFAULT '0.00',
  `service_charge` decimal(10,2) DEFAULT '0.00',
  `manual_discount` decimal(10,2) DEFAULT '0.00',
  `discount_reason` text COLLATE utf8mb4_unicode_ci,
  `total` decimal(10,2) DEFAULT '0.00',
  `refund_amount` decimal(10,2) DEFAULT '0.00',
  `payment_method` enum('cash','card','wallet','other') COLLATE utf8mb4_unicode_ci DEFAULT 'cash',
  `wallet_id` int DEFAULT NULL,
  `wallet_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payment_reference` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `paid_at` timestamp NULL DEFAULT NULL,
  `ready_at` timestamp NULL DEFAULT NULL,
  `delivered_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Table structure for table `order_items`
CREATE TABLE `order_items` (
  `id` int NOT NULL,
  `order_id` int NOT NULL,
  `item_id` int NOT NULL,
  `size_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `item_name_ar` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `item_name_en` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `category_id` int NOT NULL,
  `quantity` int NOT NULL DEFAULT '1',
  `unit_price` decimal(10,2) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `rejection_reason` text COLLATE utf8mb4_unicode_ci,
  `status` enum('pending','in_progress','ready','served','rejected') COLLATE utf8mb4_unicode_ci DEFAULT 'pending',
  `is_appended` tinyint(1) DEFAULT '0',
  `is_printed` tinyint(1) DEFAULT '0',
  `prep_start_time` datetime DEFAULT NULL,
  `prep_end_time` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Table structure for table `printers`
CREATE TABLE `printers` (
  `id` int NOT NULL,
  `name` varchar(100) NOT NULL,
  `ip` varchar(50) NOT NULL,
  `windows_name` varchar(255) DEFAULT NULL,
  `port` int DEFAULT '9100',
  `type` varchar(50) NOT NULL DEFAULT 'cashier',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Table structure for table `print_logs`
CREATE TABLE `print_logs` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `order_id` int NOT NULL,
  `printer_type` enum('ip','bluetooth') COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('success','failed','retrying') COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts` int DEFAULT '1',
  `error_message` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Table structure for table `print_queue`
CREATE TABLE `print_queue` (
  `id` int NOT NULL,
  `order_id` int NOT NULL,
  `station_user_id` int NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `printed_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Table structure for table `roles`
CREATE TABLE `roles` (
  `id` int NOT NULL,
  `name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'admin, waiter, cashier, chef, juice_bar, custom',
  `name_ar` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Table structure for table `settings`
CREATE TABLE `settings` (
  `id` int NOT NULL,
  `key` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` text COLLATE utf8mb4_unicode_ci,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Table structure for table `sse_events`
CREATE TABLE `sse_events` (
  `id` int NOT NULL,
  `event_type` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `target_roles` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'comma-separated role names, NULL=all',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Table structure for table `ticket_sales`
CREATE TABLE `ticket_sales` (
  `id` int NOT NULL,
  `ticket_type_id` int NOT NULL,
  `serial_number` int NOT NULL COMMENT 'رقم التذكرة المباعة',
  `sale_price` decimal(10,2) NOT NULL COMMENT 'سعر البيع الفعلي',
  `sold_by` int DEFAULT NULL COMMENT 'المستخدم الذي باع التذكرة',
  `cashier_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'اسم الكاشير',
  `phone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'رقم هاتف المشتري',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `sold_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='مبيعات التذاكر';


-- Table structure for table `ticket_types`
CREATE TABLE `ticket_types` (
  `id` int NOT NULL,
  `name` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'اسم التذكرة',
  `price` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT 'سعر التذكرة',
  `serial_from` int NOT NULL DEFAULT '1' COMMENT 'رقم البداية',
  `serial_to` int NOT NULL DEFAULT '100' COMMENT 'رقم النهاية',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_by` int DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='أنواع التذاكر';


-- Table structure for table `users`
CREATE TABLE `users` (
  `id` int NOT NULL,
  `name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name_en` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `username` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role_id` int NOT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `can_print` tinyint(1) DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `printer_mac` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `permissions` text COLLATE utf8mb4_unicode_ci,
  `warehouse_id` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `print_type` enum('network','bluetooth','chef') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'network'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Table structure for table `user_category_permissions`
CREATE TABLE `user_category_permissions` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `category_id` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Table structure for table `wallets`
CREATE TABLE `wallets` (
  `id` int NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `account_number` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `sort_order` int DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ========================================================
-- Indexes and Constraints for Dumped Tables
-- ========================================================

ALTER TABLE `activity_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `created_at` (`created_at`);

ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_category_printer` (`printer_id`);

ALTER TABLE `daily_settlements`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_cashier_date` (`settlement_date`,`cashier_id`);

ALTER TABLE `direct_staff`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `discounts`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `ingredients`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_name` (`name`);

ALTER TABLE `inventory_departments`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `inventory_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_date` (`transaction_date`),
  ADD KEY `idx_dept` (`department_id`),
  ADD KEY `idx_ingredient` (`ingredient_id`);

ALTER TABLE `inv_items`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `item_number` (`item_number`);

ALTER TABLE `inv_purchases`
  ADD PRIMARY KEY (`id`),
  ADD KEY `item_id` (`item_id`);

ALTER TABLE `inv_requests`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `inv_request_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `request_id` (`request_id`),
  ADD KEY `item_id` (`item_id`);

ALTER TABLE `inv_sub_stock`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `inv_warehouses`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_item_cat` (`category_id`),
  ADD KEY `idx_item_avail` (`is_available`),
  ADD KEY `idx_item_sort` (`sort_order`);

ALTER TABLE `item_audit_log`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `item_ingredients`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_item_ingredient` (`item_id`,`ingredient_id`),
  ADD KEY `idx_item_id` (`item_id`),
  ADD KEY `idx_ingredient_id` (`ingredient_id`);

ALTER TABLE `item_stock`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_item_stock` (`item_id`);

ALTER TABLE `item_stock_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_isl_item` (`item_id`),
  ADD KEY `idx_isl_created` (`created_at`);

ALTER TABLE `manual_sales`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_date` (`sale_date`),
  ADD KEY `idx_item` (`item_id`);

ALTER TABLE `messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sender_id` (`sender_id`);

ALTER TABLE `offers`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `offer_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `offer_id` (`offer_id`);

ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `order_number` (`order_number`),
  ADD KEY `waiter_id` (`waiter_id`),
  ADD KEY `cashier_id` (`cashier_id`),
  ADD KEY `idx_order_status` (`status`),
  ADD KEY `idx_order_created` (`created_at`),
  ADD KEY `idx_direct_name` (`direct_name`);

ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `item_id` (`item_id`),
  ADD KEY `category_id` (`category_id`);

ALTER TABLE `printers`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `print_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `user_id` (`user_id`);

ALTER TABLE `print_queue`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_station_pending` (`station_user_id`,`printed_at`);

ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `key` (`key`);

ALTER TABLE `sse_events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_created` (`created_at`);

ALTER TABLE `ticket_sales`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ticket_type` (`ticket_type_id`),
  ADD KEY `idx_sold_at` (`sold_at`),
  ADD KEY `idx_serial` (`ticket_type_id`,`serial_number`);

ALTER TABLE `ticket_types`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_active` (`is_active`);

ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `role_id` (`role_id`);

ALTER TABLE `user_category_permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_cat` (`user_id`,`category_id`),
  ADD KEY `category_id` (`category_id`);

ALTER TABLE `wallets`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `activity_log`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35290;

ALTER TABLE `categories`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

ALTER TABLE `daily_settlements`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

ALTER TABLE `direct_staff`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

ALTER TABLE `discounts`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

ALTER TABLE `ingredients`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

ALTER TABLE `inventory_departments`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

ALTER TABLE `inventory_transactions`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

ALTER TABLE `inv_items`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

ALTER TABLE `inv_purchases`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

ALTER TABLE `inv_requests`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

ALTER TABLE `inv_request_items`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

ALTER TABLE `inv_sub_stock`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

ALTER TABLE `items`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

ALTER TABLE `item_audit_log`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=196;

ALTER TABLE `item_ingredients`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

ALTER TABLE `item_stock`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

ALTER TABLE `item_stock_log`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

ALTER TABLE `manual_sales`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

ALTER TABLE `messages`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

ALTER TABLE `offers`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

ALTER TABLE `offer_items`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

ALTER TABLE `orders`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13393;

ALTER TABLE `order_items`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27641;

ALTER TABLE `printers`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

ALTER TABLE `print_logs`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37300;

ALTER TABLE `print_queue`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30905;

ALTER TABLE `roles`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

ALTER TABLE `settings`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=46;

ALTER TABLE `sse_events`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=117508;

ALTER TABLE `ticket_sales`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

ALTER TABLE `ticket_types`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

ALTER TABLE `users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=43;

ALTER TABLE `user_category_permissions`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=125;

ALTER TABLE `wallets`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

ALTER TABLE `categories`
  ADD CONSTRAINT `fk_category_printer` FOREIGN KEY (`printer_id`) REFERENCES `printers` (`id`) ON DELETE SET NULL;

ALTER TABLE `inventory_transactions`
  ADD CONSTRAINT `inventory_transactions_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `inventory_departments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `inventory_transactions_ibfk_2` FOREIGN KEY (`ingredient_id`) REFERENCES `ingredients` (`id`) ON DELETE CASCADE;

ALTER TABLE `inv_purchases`
  ADD CONSTRAINT `inv_purchases_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `inv_items` (`id`);

ALTER TABLE `inv_request_items`
  ADD CONSTRAINT `inv_request_items_ibfk_1` FOREIGN KEY (`request_id`) REFERENCES `inv_requests` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `inv_request_items_ibfk_2` FOREIGN KEY (`item_id`) REFERENCES `inv_items` (`id`);

ALTER TABLE `items`
  ADD CONSTRAINT `items_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`);

ALTER TABLE `item_ingredients`
  ADD CONSTRAINT `item_ingredients_ibfk_1` FOREIGN KEY (`ingredient_id`) REFERENCES `ingredients` (`id`) ON DELETE CASCADE;

ALTER TABLE `messages`
  ADD CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

ALTER TABLE `offer_items`
  ADD CONSTRAINT `offer_items_ibfk_1` FOREIGN KEY (`offer_id`) REFERENCES `offers` (`id`) ON DELETE CASCADE;

ALTER TABLE `orders`
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`waiter_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `orders_ibfk_2` FOREIGN KEY (`cashier_id`) REFERENCES `users` (`id`);

ALTER TABLE `order_items`
  ADD CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`),
  ADD CONSTRAINT `order_items_ibfk_3` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`);

ALTER TABLE `ticket_sales`
  ADD CONSTRAINT `ticket_sales_ibfk_1` FOREIGN KEY (`ticket_type_id`) REFERENCES `ticket_types` (`id`) ON DELETE RESTRICT;

ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`);

ALTER TABLE `user_category_permissions`
  ADD CONSTRAINT `user_category_permissions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_category_permissions_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE;


-- ========================================================
-- Default System Seed Data
-- ========================================================

-- Default Roles
INSERT INTO `roles` (`id`, `name`, `name_ar`) VALUES
(1, 'admin', 'مدير النظام'),
(2, 'waiter', 'ويتر / مباشر'),
(3, 'cashier', 'كاشير'),
(4, 'chef', 'شيف / مطبخ'),
(5, 'juice_bar', 'مسؤول العصائر'),
(6, 'accountant', 'محاسب مالي'),
(7, 'warehouse_manager', 'أمين المخزن')
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`), `name_ar`=VALUES(`name_ar`);

-- Default Administrator (User: admin / Password: password)
INSERT INTO `users` (`id`, `name`, `username`, `password`, `role_id`, `is_active`) VALUES
(1, 'مدير النظام', 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 1)
ON DUPLICATE KEY UPDATE `username`=VALUES(`username`);

-- Default System Settings
INSERT INTO `settings` (`key`, `value`) VALUES
('restaurant_name', 'مطعم فندق سبأ'),
('currency', 'ريال'),
('currency_position', 'after'),
('tax_rate', '0'),
('receipt_footer', 'شكراً لزيارتكم - فندق سبأ'),
('shift_closing_time', '03:00'),
('enable_department_printing', '1'),
('logo', 'images/Sheba Hotel (3) (3).png')
ON DUPLICATE KEY UPDATE `value`=VALUES(`value`);

-- Sample Categories
INSERT INTO `categories` (`id`, `name_ar`, `name_en`, `icon`, `sort_order`, `is_active`) VALUES
(1, 'وجبات رئيسية', 'Main Dishes', '🍲', 1, 1),
(2, 'مشويات', 'Grills', '🍖', 2, 1),
(3, 'مقبلات وشوربات', 'Appetizers & Soups', '🥗', 3, 1),
(4, 'عصائر ومشروبات باردة', 'Cold Drinks & Juices', '🥤', 4, 1),
(5, 'مشروبات ساخنة', 'Hot Drinks', '☕', 5, 1),
(6, 'حلويات', 'Desserts', '🍰', 6, 1)
ON DUPLICATE KEY UPDATE `name_ar`=VALUES(`name_ar`);

SET FOREIGN_KEY_CHECKS = 1;
