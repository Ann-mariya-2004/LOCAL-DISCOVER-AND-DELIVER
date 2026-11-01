-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 01, 2026 at 02:53 PM
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
-- Database: `local_discover_deliver`
--

-- --------------------------------------------------------

--
-- Table structure for table `delivery_partners`
--

CREATE TABLE `delivery_partners` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `vehicle_type` varchar(50) DEFAULT NULL,
  `vehicle_number` varchar(50) DEFAULT NULL,
  `availability_status` varchar(30) DEFAULT 'Available',
  `total_deliveries` int(11) DEFAULT 0,
  `total_earnings` decimal(10,2) DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `delivery_partners`
--

INSERT INTO `delivery_partners` (`id`, `user_id`, `vehicle_type`, `vehicle_number`, `availability_status`, `total_deliveries`, `total_earnings`, `created_at`) VALUES
(1, 16, 'Bike', 'KL 12 L 3456', 'Available', 0, 0.00, '2026-10-27 14:23:17');

-- --------------------------------------------------------

--
-- Table structure for table `feedback`
--

CREATE TABLE `feedback` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `rating` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `feedback`
--

INSERT INTO `feedback` (`id`, `user_id`, `message`, `rating`, `created_at`) VALUES
(1, 13, 'I like it.', 5, '2026-10-31 16:31:38'),
(2, 13, 'I like it.', 5, '2026-10-31 16:31:46'),
(3, 13, 'Its good', 5, '2026-11-01 12:55:47');

-- --------------------------------------------------------

--
-- Table structure for table `food_items`
--

CREATE TABLE `food_items` (
  `id` int(11) NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `category` varchar(50) DEFAULT NULL,
  `restaurant_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `food_items`
--

INSERT INTO `food_items` (`id`, `name`, `description`, `price`, `image`, `category`, `restaurant_id`) VALUES
(1, 'Chicken Biriyani', 'Kerala style special biriyani', 180.00, 'biriyani.jpg', 'Non Veg', 1),
(2, 'Veg Meals', 'Traditional Kerala meals', 120.00, 'meals.jpg', 'Veg', 2),
(3, 'Biriyani', '', 200.00, 'food_d070637271b7deb76daa124c.jpg', 'Non Veg', 6);

-- --------------------------------------------------------

--
-- Table structure for table `food_orders`
--

CREATE TABLE `food_orders` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `delivery_partner_id` int(11) DEFAULT NULL,
  `delivery_status` varchar(30) NOT NULL DEFAULT 'Available',
  `delivery_fee` decimal(10,2) NOT NULL DEFAULT 0.00,
  `restaurant_id` int(11) DEFAULT NULL,
  `food_name` varchar(100) DEFAULT NULL,
  `quantity` int(11) DEFAULT NULL,
  `price` decimal(10,2) DEFAULT NULL,
  `total` decimal(10,2) DEFAULT NULL,
  `order_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `payment_method` varchar(30) DEFAULT NULL,
  `payment_status` varchar(30) NOT NULL DEFAULT 'Pending',
  `payment_id` varchar(100) DEFAULT NULL,
  `customer_name` varchar(100) DEFAULT NULL,
  `phone` varchar(15) DEFAULT NULL,
  `delivery_address` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `food_orders`
--

INSERT INTO `food_orders` (`id`, `user_id`, `delivery_partner_id`, `delivery_status`, `delivery_fee`, `restaurant_id`, `food_name`, `quantity`, `price`, `total`, `order_date`, `payment_method`, `payment_status`, `payment_id`, `customer_name`, `phone`, `delivery_address`) VALUES
(1, 1, NULL, 'Available', 20.00, NULL, 'Chicken Biriyani', 1, 180.00, 180.00, '2026-07-25 17:27:03', NULL, 'Pending', NULL, NULL, NULL, NULL),
(2, 1, NULL, 'Available', 20.00, NULL, 'Chicken Biriyani', 1, 180.00, 180.00, '2026-07-25 17:39:54', NULL, 'Pending', NULL, NULL, NULL, NULL),
(3, 1, NULL, 'Available', 0.00, NULL, 'Chicken Biriyani', 0, 0.00, 0.00, '2026-07-27 09:31:37', NULL, 'Pending', NULL, NULL, NULL, NULL),
(4, 5, NULL, 'Available', 0.00, NULL, 'Chicken Biriyani', 3, 180.00, 540.00, '2026-07-27 10:34:30', NULL, 'Pending', NULL, NULL, NULL, NULL),
(5, 5, NULL, 'Available', 0.00, NULL, 'Chicken Biriyani', 2, 180.00, 360.00, '2026-07-29 07:47:30', NULL, 'Pending', NULL, NULL, NULL, NULL),
(7, 5, NULL, 'Available', 0.00, NULL, 'Chicken Biriyani', 2, 180.00, 360.00, '2026-07-29 10:14:08', NULL, 'Pending', NULL, NULL, NULL, NULL),
(8, 9, NULL, 'Available', 0.00, NULL, 'Chicken Biriyani', 3, 180.00, 540.00, '2026-09-16 06:01:46', NULL, 'Pending', NULL, NULL, NULL, NULL),
(9, 2, NULL, 'Available', 20.00, NULL, 'Chicken Biriyani', 1, 180.00, 180.00, '2026-10-03 14:04:40', NULL, 'Pending', NULL, NULL, NULL, NULL),
(10, 10, NULL, 'Available', 20.00, NULL, 'Chicken Biriyani', 1, 180.00, 180.00, '2026-10-04 15:24:26', NULL, 'Pending', NULL, NULL, NULL, NULL),
(11, 13, NULL, 'Available', 20.00, NULL, 'Veg Meals', 1, 120.00, 120.00, '2026-10-05 15:07:21', NULL, 'Pending', NULL, NULL, NULL, NULL),
(12, 13, 16, 'Delivered', 20.00, NULL, 'Veg Meals', 1, 120.00, 140.00, '2026-10-05 15:09:59', NULL, 'Pending', NULL, NULL, NULL, NULL),
(13, 13, 16, 'Picked Up', 20.00, NULL, 'Chicken Biriyani', 1, 180.00, 200.00, '2026-10-05 15:21:44', NULL, 'Pending', NULL, NULL, NULL, NULL),
(14, 13, NULL, 'Available', 20.00, NULL, 'Chicken Biriyani', 1, 180.00, 180.00, '2026-10-06 04:30:10', NULL, 'Pending', NULL, NULL, NULL, NULL),
(15, 13, NULL, 'Available', 0.00, 6, 'Biriyani', 2, 200.00, 400.00, '2026-11-01 12:29:57', 'COD', 'Pending', NULL, 'Bincy Saneesh', '9456321862', 'Vannappuram'),
(16, 13, NULL, 'Available', 0.00, 1, 'Chicken Biriyani', 1, 180.00, 180.00, '2026-11-01 12:52:49', 'COD', 'Pending', NULL, 'Bincy Saneesh', '9456321862', 'Vannappuram');

-- --------------------------------------------------------

--
-- Table structure for table `grocery_items`
--

CREATE TABLE `grocery_items` (
  `id` int(11) NOT NULL,
  `store_id` int(11) NOT NULL,
  `item_name` varchar(150) NOT NULL,
  `category` varchar(80) NOT NULL,
  `unit` varchar(50) NOT NULL DEFAULT '1 pc',
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `stock_qty` int(11) NOT NULL DEFAULT 0,
  `image` varchar(255) DEFAULT NULL,
  `description` varchar(500) DEFAULT NULL,
  `is_available` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `grocery_items`
--

INSERT INTO `grocery_items` (`id`, `store_id`, `item_name`, `category`, `unit`, `price`, `stock_qty`, `image`, `description`, `is_available`, `created_at`) VALUES
(1, 1, 'Milk', 'Dairy', '1 litre', 58.00, 40, NULL, 'Fresh toned milk.', 1, '2026-10-04 14:48:24'),
(2, 1, 'Rice', 'Staples', '5 kg', 320.00, 23, NULL, 'Everyday cooking rice.', 1, '2026-10-04 14:48:24'),
(3, 1, 'Tomato', 'Vegetables', '1 kg', 55.00, 34, NULL, 'Fresh red tomatoes.', 1, '2026-10-04 14:48:24'),
(4, 1, 'Banana', 'Fruits', '1 kg', 70.00, 24, NULL, 'Fresh bananas.', 1, '2026-10-04 14:48:24'),
(5, 1, 'Wheat Bread', 'Bakery', '400 g', 48.00, 17, NULL, 'Soft sliced bread.', 1, '2026-10-04 14:48:24'),
(6, 2, 'Eggs', 'Dairy & Eggs', '12 pcs', 92.00, 29, NULL, 'Farm fresh eggs.', 1, '2026-10-04 14:48:24'),
(7, 2, 'Potato', 'Vegetables', '1 kg', 45.00, 40, NULL, 'Fresh potatoes.', 1, '2026-10-04 14:48:24'),
(8, 2, 'Apple', 'Fruits', '1 kg', 180.00, 18, NULL, 'Fresh apples.', 1, '2026-10-04 14:48:24'),
(9, 2, 'Sunflower Oil', 'Cooking Essentials', '1 litre', 145.00, 28, NULL, 'Refined sunflower cooking oil.', 1, '2026-10-04 14:48:24'),
(10, 3, 'Tea Powder', 'Beverages', '250 g', 110.00, 22, NULL, 'Premium tea powder for daily use.', 1, '2026-10-04 14:48:24'),
(11, 3, 'Biscuits', 'Snacks', '1 pack', 35.00, 50, NULL, 'Assorted tea-time biscuits.', 1, '2026-10-04 14:48:24'),
(12, 3, 'Toor Dal', 'Staples', '1 kg', 165.00, 24, NULL, 'Everyday cooking dal.', 1, '2026-10-04 14:48:24'),
(13, 3, 'Detergent Powder', 'Household', '1 kg', 125.00, 20, NULL, 'Laundry detergent powder.', 1, '2026-10-04 14:48:24');

-- --------------------------------------------------------

--
-- Table structure for table `grocery_orders`
--

CREATE TABLE `grocery_orders` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `store_id` int(11) NOT NULL,
  `customer_name` varchar(120) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `delivery_address` text NOT NULL,
  `payment_method` varchar(30) NOT NULL DEFAULT 'Cash on Delivery',
  `total_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `delivery_fee` decimal(10,2) NOT NULL DEFAULT 0.00,
  `grand_total` decimal(10,2) NOT NULL DEFAULT 0.00,
  `order_status` varchar(40) NOT NULL DEFAULT 'Pending',
  `delivery_partner_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `payment_status` varchar(30) NOT NULL DEFAULT 'Pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `grocery_orders`
--

INSERT INTO `grocery_orders` (`id`, `customer_id`, `store_id`, `customer_name`, `phone`, `delivery_address`, `payment_method`, `total_amount`, `delivery_fee`, `grand_total`, `order_status`, `delivery_partner_id`, `created_at`, `payment_status`) VALUES
(1, 13, 1, 'Bincy Saneesh', '8281153526', 'Kizhakkedathu House Vannappuram p.o Orakanni ,Idukki,685607', 'Cash on Delivery', 55.00, 40.00, 95.00, 'Pending', NULL, '2026-10-05 15:13:14', 'Pending'),
(2, 13, 1, 'Bincy Saneesh', '8281153526', 'Kizhakkedathu House Vannappuram p.o Orakanni ,Idukki,685607', 'Cash on Delivery', 758.00, 0.00, 758.00, 'Pending', NULL, '2026-10-06 04:38:03', 'Pending'),
(3, 17, 1, 'Jithin Benny', '6754321890', 'Ambalappady', 'Cash on Delivery', 48.00, 40.00, 88.00, 'Pending', NULL, '2026-10-31 17:10:50', 'Pending'),
(4, 13, 1, 'Bincy Saneesh', '1234567890', 'Vannappuram', 'Cash on Delivery', 48.00, 40.00, 88.00, 'Pending', NULL, '2026-11-01 09:32:22', 'Pending'),
(5, 13, 2, 'Bincy Saneesh', '1234567890', 'Vannappuram', 'UPI', 92.00, 40.00, 132.00, 'Pending', NULL, '2026-11-01 09:33:51', 'Pending');

-- --------------------------------------------------------

--
-- Table structure for table `grocery_order_items`
--

CREATE TABLE `grocery_order_items` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `item_name` varchar(150) NOT NULL,
  `quantity` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `grocery_order_items`
--

INSERT INTO `grocery_order_items` (`id`, `order_id`, `item_id`, `item_name`, `quantity`, `price`, `subtotal`) VALUES
(1, 1, 3, 'Tomato', 1, 55.00, 55.00),
(2, 2, 2, 'Rice', 2, 320.00, 640.00),
(3, 2, 5, 'Wheat Bread', 1, 48.00, 48.00),
(4, 2, 4, 'Banana', 1, 70.00, 70.00),
(5, 3, 5, 'Wheat Bread', 1, 48.00, 48.00),
(6, 4, 5, 'Wheat Bread', 1, 48.00, 48.00),
(7, 5, 6, 'Eggs', 1, 92.00, 92.00);

-- --------------------------------------------------------

--
-- Table structure for table `grocery_stores`
--

CREATE TABLE `grocery_stores` (
  `id` int(11) NOT NULL,
  `vendor_id` int(11) DEFAULT NULL,
  `store_name` varchar(120) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `location` varchar(255) NOT NULL,
  `category` varchar(80) DEFAULT 'Grocery Store',
  `description` varchar(500) DEFAULT NULL,
  `delivery_time` varchar(50) DEFAULT '30-45 min',
  `rating` decimal(2,1) DEFAULT 4.5,
  `image` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `grocery_stores`
--

INSERT INTO `grocery_stores` (`id`, `vendor_id`, `store_name`, `phone`, `location`, `category`, `description`, `delivery_time`, `rating`, `image`, `is_active`, `created_at`) VALUES
(1, NULL, 'Fresh Basket Mart', '9876543210', 'Muvattupuzha', 'Supermarket', 'Daily groceries, vegetables, fruits, dairy and household essentials.', '25-35 min', 4.6, NULL, 1, '2026-10-04 14:47:05'),
(2, NULL, 'Green Valley Grocery', '9876543211', 'Muvattupuzha', 'Grocery Store', 'Fresh produce and everyday kitchen essentials from a local store.', '30-40 min', 4.5, NULL, 1, '2026-10-04 14:47:05'),
(3, NULL, 'Daily Needs Supermarket', '9876543212', 'Thodupuzha', 'Supermarket', 'One-stop shop for packaged food, beverages, grains and home essentials.', '35-45 min', 4.4, NULL, 1, '2026-10-04 14:47:05');

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `payment_method` varchar(30) DEFAULT NULL,
  `payment_status` varchar(30) DEFAULT NULL,
  `payment_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `order_type` varchar(20) NOT NULL DEFAULT 'food'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`id`, `order_id`, `user_id`, `amount`, `payment_method`, `payment_status`, `payment_date`, `order_type`) VALUES
(1, 3, 1, 0.00, 'cod', 'Paid', '2026-07-27 09:31:45', 'food'),
(2, 4, 5, 540.00, 'cod', 'Paid', '2026-07-27 10:34:38', 'food'),
(3, 5, 5, 360.00, 'cod', 'Paid', '2026-07-29 07:47:55', 'food'),
(4, 7, 5, 360.00, 'UPI', 'Paid', '2026-07-29 10:14:13', 'food'),
(5, 8, 9, 540.00, 'cod', 'Paid', '2026-09-16 06:02:00', 'food'),
(6, 9, 2, 180.00, 'cod', 'Paid', '2026-10-03 14:04:47', 'food'),
(7, 10, 10, 180.00, 'cod', 'Paid', '2026-10-04 15:24:31', 'food'),
(8, 12, 13, 120.00, 'cod', 'Paid', '2026-10-05 15:10:08', 'food'),
(9, 13, 13, 180.00, 'cod', 'Pending', '2026-10-05 15:21:52', 'food'),
(10, 15, 13, 400.00, 'COD', 'Pending', '2026-11-01 12:38:54', 'food'),
(11, 15, 13, 400.00, 'COD', 'Pending', '2026-11-01 12:38:59', 'food'),
(12, 15, 13, 400.00, 'COD', 'Pending', '2026-11-01 12:39:16', 'food'),
(13, 15, 13, 400.00, 'COD', 'Pending', '2026-11-01 12:49:14', 'food'),
(14, 16, 13, 180.00, 'COD', 'Pending', '2026-11-01 12:52:59', 'food'),
(15, 16, 13, 180.00, 'COD', 'Pending', '2026-11-01 12:53:28', 'food');

-- --------------------------------------------------------

--
-- Table structure for table `print_orders`
--

CREATE TABLE `print_orders` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `document_name` varchar(255) DEFAULT NULL,
  `copies` int(11) DEFAULT NULL,
  `color_type` varchar(50) DEFAULT NULL,
  `paper_size` varchar(20) DEFAULT NULL,
  `status` varchar(30) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `total_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `file_path` varchar(255) DEFAULT NULL,
  `delivery_status` varchar(50) NOT NULL DEFAULT 'Pending',
  `payment_method` varchar(30) DEFAULT NULL,
  `payment_status` varchar(30) NOT NULL DEFAULT 'Pending',
  `delivery_partner_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `print_orders`
--

INSERT INTO `print_orders` (`id`, `user_id`, `document_name`, `copies`, `color_type`, `paper_size`, `status`, `address`, `total_amount`, `created_at`, `file_path`, `delivery_status`, `payment_method`, `payment_status`, `delivery_partner_id`) VALUES
(1, 13, 'taylor-swift-american-singer-1080x2160-4705.jpg', 1, 'B&W', 'A4', 'Pending', 'Vannappuram ,685607', 22.00, '2026-10-31 10:32:51', NULL, 'Pending', NULL, 'Pending', NULL),
(2, 13, 'WhatsApp_Image_2025-12-30_at_7.00.12_PM-removebg-preview.png', 1, 'Colour', 'A4', 'Pending', 'Kaliyar,685697', 25.00, '2026-10-31 10:41:16', NULL, 'Pending', NULL, 'Pending', NULL),
(3, 17, 'restaurant.jpeg', 1, 'B&W', 'A4', 'Pending', 'Ambalappady', 22.00, '2026-10-31 12:17:49', NULL, 'Pending', NULL, 'Pending', NULL),
(4, 17, 'restaurant.jpeg', 1, 'Colour', 'A4', 'Pending', 'Ambalappady', 25.00, '2026-10-31 12:41:24', NULL, 'Pending', NULL, 'Pending', NULL),
(5, 13, 'Gemini_Generated_Image_j0nrtsj0nrtsj0nr.png', 1, 'B&W', 'A4', 'Pending', 'Orakanni,685607', 22.00, '2026-11-01 08:25:26', NULL, 'Pending', NULL, 'Pending', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `restaurants`
--

CREATE TABLE `restaurants` (
  `id` int(11) NOT NULL,
  `vendor_id` int(11) DEFAULT NULL,
  `restaurant_name` varchar(100) DEFAULT NULL,
  `location` varchar(100) DEFAULT NULL,
  `category` varchar(50) DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `restaurants`
--

INSERT INTO `restaurants` (`id`, `vendor_id`, `restaurant_name`, `location`, `category`, `image`, `latitude`, `longitude`, `phone`, `description`) VALUES
(1, NULL, 'Burger Hub', 'Muvattupuzha', 'Fast Food', 'burger.jpg', NULL, NULL, NULL, NULL),
(2, NULL, 'Hotel Aryas', 'Muvattupuzha', 'Vegetarian', 'aryas.jpg', NULL, NULL, NULL, NULL),
(3, NULL, 'Pizza Corner', 'Thodupuzha', 'Pizza', 'pizza.jpg', NULL, NULL, NULL, NULL),
(4, NULL, 'Cafe Brew', 'Muvattupuzha', 'Cafe', 'cafe.jpg', NULL, NULL, NULL, NULL),
(5, 10, 'ANNA\'S Restuarent', 'Vannappuram', 'Fast Food', 'restaurant_10_a3b4470275725768.jpg', 0.00000000, 0.00000000, '0987654321', ''),
(6, 14, 'Royal', 'Vannappuram', 'Other', '', 10.02800000, 76.30500000, '12378490039', '');

-- --------------------------------------------------------

--
-- Table structure for table `restaurant_menu`
--

CREATE TABLE `restaurant_menu` (
  `id` int(11) NOT NULL,
  `restaurant_id` int(11) NOT NULL,
  `item_name` varchar(150) NOT NULL,
  `category` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `image` varchar(255) DEFAULT NULL,
  `availability` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `restaurant_menu`
--

INSERT INTO `restaurant_menu` (`id`, `restaurant_id`, `item_name`, `category`, `description`, `price`, `image`, `availability`) VALUES
(1, 1, 'Chicken Burger', 'Burger', 'Chicken burger with fresh vegetables and sauce', 120.00, 'chicken_burger.jpg', 1),
(2, 1, 'Beef Burger', 'Burger', 'Juicy beef burger with vegetables and sauce', 150.00, 'beef_burger.jpg', 1),
(3, 1, 'French Fries', 'Sides', 'Crispy golden french fries', 80.00, 'french_fries.jpg', 1),
(4, 1, 'Chicken Shawarma', 'Shawarma', 'Chicken shawarma with vegetables and sauce', 110.00, 'chicken_shawarma.jpg', 1);

-- --------------------------------------------------------

--
-- Table structure for table `services`
--

CREATE TABLE `services` (
  `id` int(11) NOT NULL,
  `service_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `location` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `services`
--

INSERT INTO `services` (`id`, `service_name`, `description`, `image`, `created_at`, `location`) VALUES
(1, 'Grocery Delivery', 'Order groceries and daily household essentials for home delivery.', NULL, '2026-11-01 13:51:17', 'Muvattupuzha'),
(2, 'Food Delivery', 'Order food from nearby restaurants and get it delivered.', NULL, '2026-11-01 13:51:17', 'Muvattupuzha'),
(3, 'Print & Photocopy', 'Upload documents and order printing or photocopy services.', NULL, '2026-11-01 13:51:17', 'Muvattupuzha'),
(4, 'Local Shopping', 'Discover and order products from nearby shops', NULL, '2026-11-01 13:51:17', 'Muvattupuzha');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(15) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `role` varchar(50) NOT NULL,
  `location` varchar(100) DEFAULT NULL,
  `profile_image` varchar(255) DEFAULT NULL,
  `business_type` enum('Restaurant','Grocery','Printing') DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `phone`, `address`, `password`, `created_at`, `role`, `location`, `profile_image`, `business_type`) VALUES
(1, 'Densal', 'densal123456@gmail.com', '1234567890', 'Thodupuzha', '$2y$10$WbInN1s9CAzZcbY2STAIW.BsXHJNRMk5X46WcUjYC6ldEZE4Li4Ei', '2026-07-23 16:37:43', '', NULL, NULL, NULL),
(2, 'Albin Saneesh', 'albinsaneesh@gmail.com', '7472837453', 'Vannappuram', '$2y$10$9MJns8YJ.L3ax2auf0KZSO96ORT08uEt0v3Ooea6FpR2RfXlQAcOG', '2026-07-23 16:47:22', '', NULL, NULL, NULL),
(5, 'Anugraha', 'anugraha@gmail.com', '0987654321', 'Thiruvananthapuram', '$2y$10$EASmbfmVcolFTeC3hEvqm.FuIruU6Wu39DuMQkFYj5oCTU/EJxHLa', '2026-07-23 17:12:05', '', 'Vannappuram', '1785311512_luxury-letter-.jpg', NULL),
(7, 'Alan Gomas', 'gomas1234@gmail.com', '9234567831', 'Gomas Villa,Vietnam', '$2y$10$6J7L9N26D7.IuXphS7lBE.bKnzG1QF8qSLNsSICuGGptTuTjx4ZH2', '2026-07-29 07:45:33', 'Customer', NULL, NULL, NULL),
(8, 'Benjamin Joseph', 'benjaminjoseph@gmail.com', '7896463826', 'Mulampallil House,Palakkad', '$2y$10$.jcIOQ5fdteXi/BHYR4Ao.7VCyryupBPf2259U4M.xuO0qcPvLOry', '2026-07-30 08:38:16', 'Customer', 'Palakkadu', '1785401282_images.jpeg', NULL),
(9, 'Saneesh Mathew', 'saneeshmathew2026@gmail.com', '9961002175', 'Kizhakkedahu House Vannappuram', '$2y$10$Kvzon1XtkbS9iWfl.ZJokOGvhFGnShZONR/3cj9pSkfLRYlkA5Sde', '2026-08-06 06:30:28', 'Customer', NULL, NULL, NULL),
(10, 'Ann Mariya Saneesh', 'annmariyasaneesh2004@gmail.com', '9188152175', 'Kizhakkedahu House Vannappuram', '$2y$10$WYUSlSDshxpd7cZmjNSpK.WFjrToD5xjilLEoL3utYC8hj6JRzMbi', '2026-08-13 08:21:33', 'Admin', NULL, NULL, NULL),
(11, 'Haris Jaleel', 'harisjaleel1234@gmail.com', '9876543210', 'Thodupuzha', '$2y$10$.4StB98l5Jt9rhvUJRa5VuXUrzEoMIB1L6p3mgZZi1twup/zx8vCq', '2026-09-17 08:41:41', 'Delivery Partner', NULL, NULL, NULL),
(12, 'Muhammed Arshed', 'muhammedarshed@gmail.com', '4567832190', 'Thodupuzha', '$2y$10$t8BN2y/2jPsNAZC7tffXVedN.7/23CSQD9G5KT1T6F.nWcys2NlRy', '2026-09-19 13:21:24', 'Delivery Partner', NULL, NULL, NULL),
(13, 'Bincy Saneesh', 'bincysaneesh15@gmail.com', '8281153526', 'Kizhakkedahu House Vannappuram', '$2y$10$ZJCkxzGfaoXfdZ5E/9uz5ukFYz.6d7xxFiI1bi8BvH9tpVc2bJBlW', '2026-10-04 16:31:17', 'Customer', 'Vannappuram', '1793537773_Download free image of Book footwear smiling cartoon_ by Nardsucha about school girl animation, school kids cartoon, school girl cartoon, cartoon, and book 12159933.jpeg', NULL),
(14, 'Saneesh Mathew', 'saneeshmathew@gmail.com', '6754839201', 'Vannappuram', '$2y$10$n.r89OezMWyxgb1vGARKR.R4h1cmKpErosaSzY4bW2ZfMLrUhwp6O', '2026-10-25 16:22:18', 'Vendor', NULL, 'backend/uploads/vendor_14_c2da85984eaaad44.jpg', 'Restaurant'),
(16, 'Albin Saneesh', 'albinsaneesh2023@gmail.com', '9656492114', 'Vannappuram', '$2y$10$VKkqvmksw9zn3QSbg7/JwufBGe0hay75GGVCmiLKJXLGDaa6vhmFC', '2026-10-27 14:23:17', 'Delivery Partner', '', NULL, NULL),
(17, 'Jithin Benny', 'jithinbenny2026@gmail.com', '6754321890', 'Ambalappady', '$2y$10$VKr/UOwsGt3VDMZCpRK1qOiG9lMg9sS6bKMrVqQSG7JRzdPMMbwwO', '2026-10-31 16:40:57', 'Customer', NULL, NULL, NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `delivery_partners`
--
ALTER TABLE `delivery_partners`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user` (`user_id`);

--
-- Indexes for table `feedback`
--
ALTER TABLE `feedback`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `food_items`
--
ALTER TABLE `food_items`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `food_orders`
--
ALTER TABLE `food_orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `restaurant_id` (`restaurant_id`);

--
-- Indexes for table `grocery_items`
--
ALTER TABLE `grocery_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_grocery_items_store` (`store_id`),
  ADD KEY `idx_grocery_items_category` (`category`),
  ADD KEY `idx_grocery_items_available` (`is_available`);

--
-- Indexes for table `grocery_orders`
--
ALTER TABLE `grocery_orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_grocery_orders_customer` (`customer_id`),
  ADD KEY `idx_grocery_orders_store` (`store_id`),
  ADD KEY `idx_grocery_orders_status` (`order_status`),
  ADD KEY `idx_grocery_orders_delivery_partner` (`delivery_partner_id`);

--
-- Indexes for table `grocery_order_items`
--
ALTER TABLE `grocery_order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_grocery_order_items_order` (`order_id`),
  ADD KEY `idx_grocery_order_items_item` (`item_id`);

--
-- Indexes for table `grocery_stores`
--
ALTER TABLE `grocery_stores`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_grocery_store_active` (`is_active`),
  ADD KEY `idx_grocery_store_vendor` (`vendor_id`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `print_orders`
--
ALTER TABLE `print_orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `restaurants`
--
ALTER TABLE `restaurants`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `restaurant_menu`
--
ALTER TABLE `restaurant_menu`
  ADD PRIMARY KEY (`id`),
  ADD KEY `restaurant_id` (`restaurant_id`);

--
-- Indexes for table `services`
--
ALTER TABLE `services`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `delivery_partners`
--
ALTER TABLE `delivery_partners`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `feedback`
--
ALTER TABLE `feedback`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `food_items`
--
ALTER TABLE `food_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `food_orders`
--
ALTER TABLE `food_orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `grocery_items`
--
ALTER TABLE `grocery_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `grocery_orders`
--
ALTER TABLE `grocery_orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `grocery_order_items`
--
ALTER TABLE `grocery_order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `grocery_stores`
--
ALTER TABLE `grocery_stores`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `print_orders`
--
ALTER TABLE `print_orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `restaurants`
--
ALTER TABLE `restaurants`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `restaurant_menu`
--
ALTER TABLE `restaurant_menu`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `services`
--
ALTER TABLE `services`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `delivery_partners`
--
ALTER TABLE `delivery_partners`
  ADD CONSTRAINT `delivery_partners_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `feedback`
--
ALTER TABLE `feedback`
  ADD CONSTRAINT `feedback_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `food_orders`
--
ALTER TABLE `food_orders`
  ADD CONSTRAINT `food_orders_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `food_orders_ibfk_2` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`);

--
-- Constraints for table `grocery_items`
--
ALTER TABLE `grocery_items`
  ADD CONSTRAINT `fk_grocery_items_store` FOREIGN KEY (`store_id`) REFERENCES `grocery_stores` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `grocery_orders`
--
ALTER TABLE `grocery_orders`
  ADD CONSTRAINT `fk_grocery_orders_store` FOREIGN KEY (`store_id`) REFERENCES `grocery_stores` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `grocery_order_items`
--
ALTER TABLE `grocery_order_items`
  ADD CONSTRAINT `fk_grocery_order_items_item` FOREIGN KEY (`item_id`) REFERENCES `grocery_items` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_grocery_order_items_order` FOREIGN KEY (`order_id`) REFERENCES `grocery_orders` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `print_orders`
--
ALTER TABLE `print_orders`
  ADD CONSTRAINT `print_orders_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `restaurant_menu`
--
ALTER TABLE `restaurant_menu`
  ADD CONSTRAINT `restaurant_menu_ibfk_1` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
