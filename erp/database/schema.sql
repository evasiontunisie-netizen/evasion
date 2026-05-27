-- ============================================================
-- ERP PRO - Complete Database Schema
-- Version: 1.0.0
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS `erp_pro` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `erp_pro`;

-- ============================================================
-- ROLES & PERMISSIONS
-- ============================================================
CREATE TABLE `roles` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(50) NOT NULL,
  `slug` VARCHAR(50) NOT NULL UNIQUE,
  `description` TEXT,
  `color` VARCHAR(20) DEFAULT '#6B7280',
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB;

CREATE TABLE `permissions` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `module` VARCHAR(50) NOT NULL,
  `action` VARCHAR(50) NOT NULL,
  `slug` VARCHAR(100) NOT NULL UNIQUE,
  `description` TEXT,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB;

CREATE TABLE `role_permissions` (
  `role_id` INT UNSIGNED NOT NULL,
  `permission_id` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`role_id`, `permission_id`),
  FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`permission_id`) REFERENCES `permissions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- USERS
-- ============================================================
CREATE TABLE `users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `role_id` INT UNSIGNED NOT NULL DEFAULT 10,
  `first_name` VARCHAR(100) NOT NULL,
  `last_name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(150) NOT NULL UNIQUE,
  `phone` VARCHAR(30),
  `password` VARCHAR(255) NOT NULL,
  `avatar` VARCHAR(255),
  `two_fa_secret` VARCHAR(100),
  `two_fa_enabled` TINYINT(1) DEFAULT 0,
  `language` ENUM('fr','en','ar') DEFAULT 'fr',
  `timezone` VARCHAR(60) DEFAULT 'Africa/Casablanca',
  `theme` ENUM('light','dark','system') DEFAULT 'system',
  `is_active` TINYINT(1) DEFAULT 1,
  `last_login` TIMESTAMP NULL,
  `login_attempts` INT DEFAULT 0,
  `locked_until` TIMESTAMP NULL,
  `password_reset_token` VARCHAR(100),
  `password_reset_expires` TIMESTAMP NULL,
  `email_verified_at` TIMESTAMP NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`)
) ENGINE=InnoDB;

CREATE TABLE `refresh_tokens` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `token` VARCHAR(255) NOT NULL UNIQUE,
  `ip_address` VARCHAR(45),
  `user_agent` VARCHAR(500),
  `expires_at` TIMESTAMP NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE `activity_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED,
  `action` VARCHAR(100) NOT NULL,
  `module` VARCHAR(50),
  `description` TEXT,
  `ip_address` VARCHAR(45),
  `user_agent` VARCHAR(500),
  `old_values` JSON,
  `new_values` JSON,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_user_id` (`user_id`),
  INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB;

-- ============================================================
-- WAREHOUSES / SHOWROOMS
-- ============================================================
CREATE TABLE `warehouses` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `code` VARCHAR(20) NOT NULL UNIQUE,
  `type` ENUM('warehouse','showroom','depot','web') DEFAULT 'warehouse',
  `address` TEXT,
  `city` VARCHAR(100),
  `phone` VARCHAR(30),
  `email` VARCHAR(150),
  `manager_id` INT UNSIGNED,
  `is_active` TINYINT(1) DEFAULT 1,
  `latitude` DECIMAL(10,8),
  `longitude` DECIMAL(11,8),
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB;

-- ============================================================
-- CATEGORIES & BRANDS
-- ============================================================
CREATE TABLE `categories` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `parent_id` INT UNSIGNED,
  `name` VARCHAR(100) NOT NULL,
  `slug` VARCHAR(120) NOT NULL UNIQUE,
  `description` TEXT,
  `image` VARCHAR(255),
  `sort_order` INT DEFAULT 0,
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`parent_id`) REFERENCES `categories`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE `brands` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `slug` VARCHAR(120) NOT NULL UNIQUE,
  `logo` VARCHAR(255),
  `website` VARCHAR(255),
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB;

CREATE TABLE `attributes` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `type` ENUM('select','color','size','text') DEFAULT 'select',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB;

CREATE TABLE `attribute_values` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `attribute_id` INT UNSIGNED NOT NULL,
  `value` VARCHAR(100) NOT NULL,
  `color_hex` VARCHAR(10),
  `sort_order` INT DEFAULT 0,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`attribute_id`) REFERENCES `attributes`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- SUPPLIERS
-- ============================================================
CREATE TABLE `suppliers` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(150) NOT NULL,
  `email` VARCHAR(150),
  `phone` VARCHAR(30),
  `address` TEXT,
  `city` VARCHAR(100),
  `country` VARCHAR(100) DEFAULT 'Maroc',
  `ice` VARCHAR(50),
  `notes` TEXT,
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB;

-- ============================================================
-- PRODUCTS
-- ============================================================
CREATE TABLE `products` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `category_id` INT UNSIGNED,
  `brand_id` INT UNSIGNED,
  `supplier_id` INT UNSIGNED,
  `name` VARCHAR(255) NOT NULL,
  `slug` VARCHAR(280) NOT NULL UNIQUE,
  `sku` VARCHAR(100) UNIQUE,
  `barcode` VARCHAR(100),
  `qr_code` VARCHAR(255),
  `description` TEXT,
  `short_description` TEXT,
  `purchase_price` DECIMAL(10,2) DEFAULT 0.00,
  `sale_price` DECIMAL(10,2) DEFAULT 0.00,
  `promo_price` DECIMAL(10,2),
  `promo_start` DATE,
  `promo_end` DATE,
  `tax_rate` DECIMAL(5,2) DEFAULT 20.00,
  `weight` DECIMAL(8,2),
  `unit` VARCHAR(20) DEFAULT 'pcs',
  `min_stock` INT DEFAULT 5,
  `is_active` TINYINT(1) DEFAULT 1,
  `is_featured` TINYINT(1) DEFAULT 0,
  `has_variants` TINYINT(1) DEFAULT 0,
  `woo_product_id` INT,
  `meta_title` VARCHAR(255),
  `meta_description` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`brand_id`) REFERENCES `brands`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`supplier_id`) REFERENCES `suppliers`(`id`) ON DELETE SET NULL,
  INDEX `idx_sku` (`sku`),
  INDEX `idx_barcode` (`barcode`)
) ENGINE=InnoDB;

CREATE TABLE `product_images` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id` INT UNSIGNED NOT NULL,
  `image_path` VARCHAR(255) NOT NULL,
  `alt_text` VARCHAR(255),
  `is_primary` TINYINT(1) DEFAULT 0,
  `sort_order` INT DEFAULT 0,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE `product_variants` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id` INT UNSIGNED NOT NULL,
  `sku` VARCHAR(100) UNIQUE,
  `barcode` VARCHAR(100),
  `purchase_price` DECIMAL(10,2),
  `sale_price` DECIMAL(10,2),
  `weight` DECIMAL(8,2),
  `image` VARCHAR(255),
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE `variant_attributes` (
  `variant_id` INT UNSIGNED NOT NULL,
  `attribute_id` INT UNSIGNED NOT NULL,
  `attribute_value_id` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`variant_id`, `attribute_id`),
  FOREIGN KEY (`variant_id`) REFERENCES `product_variants`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`attribute_id`) REFERENCES `attributes`(`id`),
  FOREIGN KEY (`attribute_value_id`) REFERENCES `attribute_values`(`id`)
) ENGINE=InnoDB;

-- ============================================================
-- STOCK
-- ============================================================
CREATE TABLE `stock` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id` INT UNSIGNED NOT NULL,
  `variant_id` INT UNSIGNED,
  `warehouse_id` INT UNSIGNED NOT NULL,
  `quantity` INT DEFAULT 0,
  `reserved_qty` INT DEFAULT 0,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_product_variant_warehouse` (`product_id`, `variant_id`, `warehouse_id`),
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`variant_id`) REFERENCES `product_variants`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE `stock_movements` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id` INT UNSIGNED NOT NULL,
  `variant_id` INT UNSIGNED,
  `warehouse_id` INT UNSIGNED NOT NULL,
  `type` ENUM('in','out','adjustment','transfer_in','transfer_out','sale','return','purchase') NOT NULL,
  `quantity` INT NOT NULL,
  `qty_before` INT DEFAULT 0,
  `qty_after` INT DEFAULT 0,
  `reference_type` VARCHAR(50),
  `reference_id` INT UNSIGNED,
  `notes` TEXT,
  `user_id` INT UNSIGNED,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_product_warehouse` (`product_id`, `warehouse_id`),
  INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB;

-- ============================================================
-- TRANSFERS
-- ============================================================
CREATE TABLE `transfers` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `transfer_number` VARCHAR(30) NOT NULL UNIQUE,
  `from_warehouse_id` INT UNSIGNED NOT NULL,
  `to_warehouse_id` INT UNSIGNED NOT NULL,
  `status` ENUM('pending','validated','shipped','received','cancelled') DEFAULT 'pending',
  `notes` TEXT,
  `created_by` INT UNSIGNED,
  `validated_by` INT UNSIGNED,
  `shipped_at` TIMESTAMP NULL,
  `received_at` TIMESTAMP NULL,
  `signature` TEXT,
  `pdf_path` VARCHAR(255),
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`from_warehouse_id`) REFERENCES `warehouses`(`id`),
  FOREIGN KEY (`to_warehouse_id`) REFERENCES `warehouses`(`id`)
) ENGINE=InnoDB;

CREATE TABLE `transfer_items` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `transfer_id` INT UNSIGNED NOT NULL,
  `product_id` INT UNSIGNED NOT NULL,
  `variant_id` INT UNSIGNED,
  `quantity_requested` INT NOT NULL,
  `quantity_received` INT DEFAULT 0,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`transfer_id`) REFERENCES `transfers`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`)
) ENGINE=InnoDB;

-- ============================================================
-- CUSTOMERS
-- ============================================================
CREATE TABLE `customers` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `first_name` VARCHAR(100) NOT NULL,
  `last_name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(150),
  `phone` VARCHAR(30),
  `phone2` VARCHAR(30),
  `whatsapp` VARCHAR(30),
  `address` TEXT,
  `city` VARCHAR(100),
  `country` VARCHAR(100) DEFAULT 'Maroc',
  `birthday` DATE,
  `loyalty_points` INT DEFAULT 0,
  `total_spent` DECIMAL(12,2) DEFAULT 0.00,
  `notes` TEXT,
  `woo_customer_id` INT,
  `source` ENUM('pos','web','manual','woo') DEFAULT 'manual',
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_phone` (`phone`),
  INDEX `idx_email` (`email`)
) ENGINE=InnoDB;

CREATE TABLE `customer_notes` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `customer_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED,
  `note` TEXT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- ORDERS
-- ============================================================
CREATE TABLE `orders` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_number` VARCHAR(30) NOT NULL UNIQUE,
  `customer_id` INT UNSIGNED,
  `warehouse_id` INT UNSIGNED,
  `user_id` INT UNSIGNED,
  `source` ENUM('pos','web','woo','phone','manual') DEFAULT 'pos',
  `woo_order_id` INT,
  `woo_site_id` INT,
  `status` ENUM('pending','processing','completed','cancelled','refunded','on_hold') DEFAULT 'pending',
  `payment_status` ENUM('pending','paid','partial','refunded') DEFAULT 'pending',
  `payment_method` ENUM('cash','card','transfer','mixed','online') DEFAULT 'cash',
  `subtotal` DECIMAL(12,2) DEFAULT 0.00,
  `discount_amount` DECIMAL(10,2) DEFAULT 0.00,
  `discount_percent` DECIMAL(5,2) DEFAULT 0.00,
  `tax_amount` DECIMAL(10,2) DEFAULT 0.00,
  `shipping_amount` DECIMAL(10,2) DEFAULT 0.00,
  `total` DECIMAL(12,2) DEFAULT 0.00,
  `amount_paid` DECIMAL(12,2) DEFAULT 0.00,
  `notes` TEXT,
  `customer_note` TEXT,
  `shipping_address` JSON,
  `billing_address` JSON,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_customer` (`customer_id`),
  INDEX `idx_status` (`status`),
  INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB;

CREATE TABLE `order_items` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id` INT UNSIGNED NOT NULL,
  `product_id` INT UNSIGNED NOT NULL,
  `variant_id` INT UNSIGNED,
  `product_name` VARCHAR(255) NOT NULL,
  `sku` VARCHAR(100),
  `quantity` INT NOT NULL DEFAULT 1,
  `unit_price` DECIMAL(10,2) NOT NULL,
  `discount_amount` DECIMAL(10,2) DEFAULT 0.00,
  `tax_rate` DECIMAL(5,2) DEFAULT 20.00,
  `tax_amount` DECIMAL(10,2) DEFAULT 0.00,
  `total` DECIMAL(10,2) NOT NULL,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`)
) ENGINE=InnoDB;

CREATE TABLE `payments` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id` INT UNSIGNED NOT NULL,
  `method` ENUM('cash','card','transfer','online') NOT NULL,
  `amount` DECIMAL(12,2) NOT NULL,
  `reference` VARCHAR(100),
  `notes` TEXT,
  `user_id` INT UNSIGNED,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- POS SESSIONS
-- ============================================================
CREATE TABLE `pos_sessions` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `warehouse_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `opening_cash` DECIMAL(12,2) DEFAULT 0.00,
  `closing_cash` DECIMAL(12,2),
  `expected_cash` DECIMAL(12,2) DEFAULT 0.00,
  `total_sales` DECIMAL(12,2) DEFAULT 0.00,
  `total_orders` INT DEFAULT 0,
  `status` ENUM('open','closed') DEFAULT 'open',
  `opened_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `closed_at` TIMESTAMP NULL,
  `notes` TEXT,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses`(`id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`)
) ENGINE=InnoDB;

-- ============================================================
-- TICKETS / SAV
-- ============================================================
CREATE TABLE `tickets` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ticket_number` VARCHAR(30) NOT NULL UNIQUE,
  `customer_id` INT UNSIGNED,
  `order_id` INT UNSIGNED,
  `assigned_to` INT UNSIGNED,
  `created_by` INT UNSIGNED,
  `category` ENUM('sav','delivery','defective','refund','complaint','technical','other') DEFAULT 'sav',
  `priority` ENUM('low','medium','high','urgent') DEFAULT 'medium',
  `status` ENUM('open','in_progress','resolved','closed') DEFAULT 'open',
  `subject` VARCHAR(255) NOT NULL,
  `description` TEXT,
  `resolved_at` TIMESTAMP NULL,
  `closed_at` TIMESTAMP NULL,
  `satisfaction_rating` TINYINT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_status` (`status`),
  INDEX `idx_priority` (`priority`)
) ENGINE=InnoDB;

CREATE TABLE `ticket_messages` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ticket_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED,
  `customer_id` INT UNSIGNED,
  `message` TEXT NOT NULL,
  `is_internal` TINYINT(1) DEFAULT 0,
  `attachments` JSON,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`ticket_id`) REFERENCES `tickets`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- EMPLOYEES & HR
-- ============================================================
CREATE TABLE `departments` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `manager_id` INT UNSIGNED,
  `description` TEXT,
  `is_active` TINYINT(1) DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB;

CREATE TABLE `job_positions` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `department_id` INT UNSIGNED,
  `title` VARCHAR(100) NOT NULL,
  `description` TEXT,
  `is_active` TINYINT(1) DEFAULT 1,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE `employees` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED,
  `employee_code` VARCHAR(30) NOT NULL UNIQUE,
  `first_name` VARCHAR(100) NOT NULL,
  `last_name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(150),
  `phone` VARCHAR(30),
  `cin` VARCHAR(30),
  `birthday` DATE,
  `hire_date` DATE NOT NULL,
  `end_date` DATE,
  `department_id` INT UNSIGNED,
  `position_id` INT UNSIGNED,
  `warehouse_id` INT UNSIGNED,
  `contract_type` ENUM('cdi','cdd','interim','freelance') DEFAULT 'cdi',
  `base_salary` DECIMAL(10,2) DEFAULT 0.00,
  `avatar` VARCHAR(255),
  `address` TEXT,
  `emergency_contact` JSON,
  `documents` JSON,
  `status` ENUM('active','inactive','on_leave','terminated') DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`position_id`) REFERENCES `job_positions`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE `attendance` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `employee_id` INT UNSIGNED NOT NULL,
  `date` DATE NOT NULL,
  `check_in` TIME,
  `check_out` TIME,
  `status` ENUM('present','absent','late','half_day','holiday','on_leave') DEFAULT 'present',
  `late_minutes` INT DEFAULT 0,
  `overtime_minutes` INT DEFAULT 0,
  `notes` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_employee_date` (`employee_id`, `date`),
  FOREIGN KEY (`employee_id`) REFERENCES `employees`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE `leaves` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `employee_id` INT UNSIGNED NOT NULL,
  `type` ENUM('annual','sick','maternity','paternity','unpaid','other') DEFAULT 'annual',
  `start_date` DATE NOT NULL,
  `end_date` DATE NOT NULL,
  `days` INT NOT NULL,
  `reason` TEXT,
  `status` ENUM('pending','approved','rejected','cancelled') DEFAULT 'pending',
  `approved_by` INT UNSIGNED,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`employee_id`) REFERENCES `employees`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE `salaries` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `employee_id` INT UNSIGNED NOT NULL,
  `month` TINYINT NOT NULL,
  `year` SMALLINT NOT NULL,
  `base_salary` DECIMAL(10,2) DEFAULT 0.00,
  `overtime_amount` DECIMAL(10,2) DEFAULT 0.00,
  `bonuses` DECIMAL(10,2) DEFAULT 0.00,
  `commissions` DECIMAL(10,2) DEFAULT 0.00,
  `advances` DECIMAL(10,2) DEFAULT 0.00,
  `deductions` DECIMAL(10,2) DEFAULT 0.00,
  `net_salary` DECIMAL(10,2) DEFAULT 0.00,
  `status` ENUM('draft','approved','paid') DEFAULT 'draft',
  `paid_at` TIMESTAMP NULL,
  `notes` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_employee_month_year` (`employee_id`, `month`, `year`),
  FOREIGN KEY (`employee_id`) REFERENCES `employees`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- DELIVERIES
-- ============================================================
CREATE TABLE `delivery_zones` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `cities` JSON,
  `delivery_fee` DECIMAL(8,2) DEFAULT 0.00,
  `is_active` TINYINT(1) DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB;

CREATE TABLE `deliveries` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `delivery_number` VARCHAR(30) NOT NULL UNIQUE,
  `order_id` INT UNSIGNED NOT NULL,
  `driver_id` INT UNSIGNED,
  `zone_id` INT UNSIGNED,
  `status` ENUM('preparing','shipped','in_delivery','delivered','returned') DEFAULT 'preparing',
  `address` TEXT NOT NULL,
  `city` VARCHAR(100),
  `phone` VARCHAR(30),
  `notes` TEXT,
  `tracking_code` VARCHAR(100),
  `signature` TEXT,
  `delivery_fee` DECIMAL(8,2) DEFAULT 0.00,
  `scheduled_at` TIMESTAMP NULL,
  `delivered_at` TIMESTAMP NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`),
  FOREIGN KEY (`driver_id`) REFERENCES `employees`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`zone_id`) REFERENCES `delivery_zones`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================================
-- WOOCOMMERCE INTEGRATION
-- ============================================================
CREATE TABLE `woo_sites` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `url` VARCHAR(255) NOT NULL,
  `consumer_key` VARCHAR(255) NOT NULL,
  `consumer_secret` VARCHAR(255) NOT NULL,
  `webhook_secret` VARCHAR(255),
  `warehouse_id` INT UNSIGNED,
  `is_active` TINYINT(1) DEFAULT 1,
  `last_sync` TIMESTAMP NULL,
  `sync_status` VARCHAR(50),
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE `woo_sync_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `site_id` INT UNSIGNED NOT NULL,
  `type` ENUM('product','order','customer','stock') NOT NULL,
  `direction` ENUM('push','pull') NOT NULL,
  `status` ENUM('success','error','partial') NOT NULL,
  `records_processed` INT DEFAULT 0,
  `records_failed` INT DEFAULT 0,
  `message` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`site_id`) REFERENCES `woo_sites`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- INVOICES
-- ============================================================
CREATE TABLE `invoices` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `invoice_number` VARCHAR(30) NOT NULL UNIQUE,
  `order_id` INT UNSIGNED,
  `customer_id` INT UNSIGNED,
  `type` ENUM('invoice','credit_note','quote','receipt') DEFAULT 'invoice',
  `status` ENUM('draft','sent','paid','overdue','cancelled') DEFAULT 'draft',
  `subtotal` DECIMAL(12,2) DEFAULT 0.00,
  `tax_amount` DECIMAL(10,2) DEFAULT 0.00,
  `discount_amount` DECIMAL(10,2) DEFAULT 0.00,
  `total` DECIMAL(12,2) DEFAULT 0.00,
  `due_date` DATE,
  `paid_at` TIMESTAMP NULL,
  `notes` TEXT,
  `pdf_path` VARCHAR(255),
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB;

-- ============================================================
-- EXPENSES / ACCOUNTING
-- ============================================================
CREATE TABLE `expense_categories` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `parent_id` INT UNSIGNED,
  `type` ENUM('expense','revenue') DEFAULT 'expense',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB;

CREATE TABLE `expenses` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `category_id` INT UNSIGNED,
  `warehouse_id` INT UNSIGNED,
  `user_id` INT UNSIGNED,
  `title` VARCHAR(255) NOT NULL,
  `amount` DECIMAL(12,2) NOT NULL,
  `date` DATE NOT NULL,
  `payment_method` ENUM('cash','card','transfer') DEFAULT 'cash',
  `reference` VARCHAR(100),
  `attachment` VARCHAR(255),
  `notes` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`category_id`) REFERENCES `expense_categories`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================================
-- NOTIFICATIONS
-- ============================================================
CREATE TABLE `notifications` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED,
  `type` VARCHAR(50) NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `body` TEXT,
  `data` JSON,
  `icon` VARCHAR(50) DEFAULT 'bell',
  `color` VARCHAR(20) DEFAULT 'blue',
  `is_read` TINYINT(1) DEFAULT 0,
  `read_at` TIMESTAMP NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_user_read` (`user_id`, `is_read`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- PURCHASE ORDERS
-- ============================================================
CREATE TABLE `purchase_orders` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `po_number` VARCHAR(30) NOT NULL UNIQUE,
  `supplier_id` INT UNSIGNED,
  `warehouse_id` INT UNSIGNED,
  `user_id` INT UNSIGNED,
  `status` ENUM('draft','ordered','partial','received','cancelled') DEFAULT 'draft',
  `subtotal` DECIMAL(12,2) DEFAULT 0.00,
  `tax_amount` DECIMAL(10,2) DEFAULT 0.00,
  `total` DECIMAL(12,2) DEFAULT 0.00,
  `expected_date` DATE,
  `notes` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB;

CREATE TABLE `purchase_order_items` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `po_id` INT UNSIGNED NOT NULL,
  `product_id` INT UNSIGNED NOT NULL,
  `variant_id` INT UNSIGNED,
  `quantity_ordered` INT NOT NULL,
  `quantity_received` INT DEFAULT 0,
  `unit_price` DECIMAL(10,2) NOT NULL,
  `total` DECIMAL(10,2) NOT NULL,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`po_id`) REFERENCES `purchase_orders`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`)
) ENGINE=InnoDB;

-- ============================================================
-- SEED DATA
-- ============================================================

INSERT INTO `roles` (`name`, `slug`, `description`, `color`) VALUES
('Super Admin', 'super_admin', 'Accès total au système', '#EF4444'),
('Admin', 'admin', 'Administration générale', '#F97316'),
('Manager', 'manager', 'Gestion des opérations', '#8B5CF6'),
('RH', 'hr', 'Ressources Humaines', '#06B6D4'),
('Comptable', 'accountant', 'Comptabilité et finances', '#10B981'),
('Caissier', 'cashier', 'Point de vente', '#F59E0B'),
('Livreur', 'driver', 'Livraisons', '#6366F1'),
('Support Client', 'support', 'Service client et SAV', '#EC4899'),
('Marketing', 'marketing', 'Marketing et analytics', '#14B8A6'),
('Employé', 'employee', 'Employé standard', '#6B7280');

INSERT INTO `warehouses` (`name`, `code`, `type`, `address`, `city`, `is_active`) VALUES
('Dépôt Principal', 'DEP-MAIN', 'depot', 'Zone Industrielle', 'Casablanca', 1),
('Showroom Casablanca', 'SW-CASA', 'showroom', 'Boulevard Mohammed V', 'Casablanca', 1),
('Showroom Rabat', 'SW-RABAT', 'showroom', 'Avenue Hassan II', 'Rabat', 1),
('Stock Web', 'WEB-STOCK', 'web', NULL, NULL, 1);

INSERT INTO `expense_categories` (`name`, `type`) VALUES
('Loyer', 'expense'), ('Électricité', 'expense'), ('Salaires', 'expense'),
('Fournisseurs', 'expense'), ('Marketing', 'expense'), ('Transport', 'expense'),
('Ventes Produits', 'revenue'), ('Services', 'revenue'), ('Autres Revenus', 'revenue');

SET FOREIGN_KEY_CHECKS = 1;
