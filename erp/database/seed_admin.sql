-- ============================================================
-- ERP PRO - Create default admin user
-- Password: Admin@2024 (change immediately!)
-- ============================================================
USE `erp_pro`;

-- Super Admin user (password: Admin@2024)
INSERT INTO `users` (`role_id`, `first_name`, `last_name`, `email`, `phone`, `password`, `is_active`, `email_verified_at`)
VALUES (
  1,
  'Super',
  'Admin',
  'admin@erppro.ma',
  '+212600000000',
  '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
  1,
  NOW()
);

-- Demo users for each role
INSERT INTO `users` (`role_id`, `first_name`, `last_name`, `email`, `password`, `is_active`, `email_verified_at`) VALUES
(2, 'Admin', 'Test', 'admin2@erppro.ma', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, NOW()),
(6, 'Caissier', 'Demo', 'caissier@erppro.ma', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, NOW()),
(8, 'Support', 'Demo', 'support@erppro.ma', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, NOW());

-- Sample permissions
INSERT INTO `permissions` (`module`, `action`, `slug`, `description`) VALUES
('products', 'view',   'products.view',   'Voir les produits'),
('products', 'create', 'products.create', 'Créer des produits'),
('products', 'update', 'products.update', 'Modifier les produits'),
('products', 'delete', 'products.delete', 'Supprimer des produits'),
('products', 'import', 'products.import', 'Importer des produits'),
('orders',   'view',   'orders.view',     'Voir les commandes'),
('orders',   'create', 'orders.create',   'Créer des commandes'),
('orders',   'cancel', 'orders.cancel',   'Annuler des commandes'),
('stock',    'view',   'stock.view',      'Voir le stock'),
('stock',    'adjust', 'stock.adjust',    'Ajuster le stock'),
('transfers','view',   'transfers.view',  'Voir les transferts'),
('transfers','create', 'transfers.create','Créer des transferts'),
('employees','view',   'employees.view',  'Voir les employés'),
('employees','manage', 'employees.manage','Gérer les employés'),
('customers','view',   'customers.view',  'Voir les clients'),
('customers','manage', 'customers.manage','Gérer les clients'),
('reports',  'view',   'reports.view',    'Voir les rapports'),
('reports',  'export', 'reports.export',  'Exporter les rapports');

-- Assign all permissions to Super Admin role
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 1, id FROM `permissions`;

-- Assign relevant permissions to Admin role
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 2, id FROM `permissions` WHERE `slug` NOT IN ('employees.manage');

-- Caissier permissions
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 6, id FROM `permissions` WHERE `slug` IN ('products.view','orders.view','orders.create','customers.view','customers.manage','stock.view');

-- Support permissions
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 8, id FROM `permissions` WHERE `slug` IN ('orders.view','customers.view','customers.manage','reports.view');

-- Sample departments
INSERT INTO `departments` (`name`, `description`) VALUES
('Commercial', 'Équipe commerciale et ventes'),
('Entrepôt', 'Gestion du stock et logistique'),
('Service Client', 'SAV et support client'),
('Administration', 'Gestion administrative'),
('Livraison', 'Équipe de livraison');

-- Sample job positions
INSERT INTO `job_positions` (`department_id`, `title`) VALUES
(1, 'Responsable Commercial'),
(1, 'Vendeur'),
(2, 'Responsable Logistique'),
(2, 'Magasinier'),
(3, 'Responsable SAV'),
(3, 'Agent Support'),
(5, 'Livreur'),
(4, 'Comptable'),
(4, 'RH Manager');

-- Sample categories
INSERT INTO `categories` (`name`, `slug`, `is_active`) VALUES
('Chaussures', 'chaussures', 1),
('Vêtements', 'vetements', 1),
('Accessoires', 'accessoires', 1),
('Sport', 'sport', 1),
('Enfants', 'enfants', 1);

-- Sample brands
INSERT INTO `brands` (`name`, `slug`, `is_active`) VALUES
('Nike', 'nike', 1),
('Adidas', 'adidas', 1),
('Puma', 'puma', 1),
('New Balance', 'new-balance', 1),
('Jordan', 'jordan', 1);

-- Sample products
INSERT INTO `products` (`category_id`, `brand_id`, `name`, `slug`, `sku`, `barcode`, `purchase_price`, `sale_price`, `tax_rate`, `min_stock`, `is_active`)
VALUES
(1, 1, 'Nike Air Max 270', 'nike-air-max-270', 'NAM-270-BLK', '1234567890001', 450, 899, 20, 5, 1),
(1, 1, 'Nike React Infinity', 'nike-react-infinity', 'NRI-001-WHT', '1234567890002', 500, 1099, 20, 3, 1),
(1, 2, 'Adidas Ultraboost 22', 'adidas-ultraboost-22', 'AUB-22-BLK', '1234567890003', 550, 1199, 20, 3, 1),
(1, 2, 'Adidas Stan Smith', 'adidas-stan-smith', 'ASS-001-WHT', '1234567890004', 200, 499, 20, 10, 1),
(2, 1, 'Nike Dri-FIT T-Shirt', 'nike-dri-fit-t-shirt', 'NDT-001-BLK', '1234567890005', 80, 199, 20, 20, 1),
(2, 2, 'Adidas Track Jacket', 'adidas-track-jacket', 'ATJ-001-RED', '1234567890006', 150, 349, 20, 10, 1),
(3, 1, 'Nike Cap Swoosh', 'nike-cap-swoosh', 'NCS-001-BLK', '1234567890007', 60, 149, 20, 15, 1),
(3, 1, 'Nike Sac Gym', 'nike-sac-gym', 'NSG-001-BLK', '1234567890008', 120, 279, 20, 8, 1);

-- Sample stock
INSERT INTO `stock` (`product_id`, `warehouse_id`, `quantity`) VALUES
(1, 1, 45), (1, 2, 20), (1, 3, 100), (1, 4, 30),
(2, 1, 15), (2, 2, 8),  (2, 3, 60),  (2, 4, 25),
(3, 1, 20), (3, 2, 12), (3, 3, 80),  (3, 4, 40),
(4, 1, 60), (4, 2, 35), (4, 3, 150), (4, 4, 50),
(5, 1, 100),(5, 2, 80), (5, 3, 200),
(6, 1, 40), (6, 2, 25), (6, 3, 100),
(7, 1, 75), (7, 2, 50), (7, 3, 200),
(8, 1, 30), (8, 2, 15), (8, 3, 80);

-- Sample customers
INSERT INTO `customers` (`first_name`, `last_name`, `phone`, `email`, `city`, `total_spent`, `loyalty_points`, `source`) VALUES
('Ahmed', 'Benali', '+212661234567', 'ahmed@example.com', 'Casablanca', 2450, 245, 'pos'),
('Fatima', 'Ouali', '+212669876543', 'fatima@example.com', 'Rabat', 1890, 189, 'woo'),
('Mohamed', 'Khaldi', '+212678901234', NULL, 'Casablanca', 3200, 320, 'pos'),
('Sara', 'Amrani', '+212655432109', 'sara@example.com', 'Marrakech', 750, 75, 'web'),
('Youssef', 'Berrada', '+212644567890', NULL, 'Casablanca', 5100, 510, 'pos');

-- Sample notifications
INSERT INTO `notifications` (`type`, `title`, `body`, `icon`, `color`, `is_read`) VALUES
('low_stock', 'Stock faible', 'Nike React Infinity - Seulement 3 unités restantes', 'alert-triangle', 'yellow', 0),
('new_order', 'Nouvelle commande', 'Commande #ORD-20240115-0042 reçue (WooCommerce)', 'shopping-bag', 'blue', 0),
('ticket_open', 'Nouveau ticket SAV', 'TKT-20240115-0012 - Problème de taille chaussure', 'ticket', 'red', 0),
('transfer_received', 'Transfert reçu', 'TRF-ABC12345 reçu au Showroom Rabat', 'truck', 'green', 1);
