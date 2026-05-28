USE evasion_erp;

INSERT INTO roles (name, slug) VALUES
('Super Admin', 'super-admin'),
('Admin', 'admin'),
('Manager', 'manager'),
('RH', 'rh'),
('Comptable', 'comptable'),
('Caissier', 'caissier'),
('Livreur', 'livreur'),
('Support Client', 'support-client'),
('Marketing', 'marketing'),
('Employé', 'employe')
ON DUPLICATE KEY UPDATE name = VALUES(name);

INSERT INTO permissions (name, slug) VALUES
('All permissions', '*'),
('Manage products', 'products.manage'),
('Manage stock', 'stock.manage'),
('Manage transfers', 'transfers.manage'),
('Use POS', 'pos.use'),
('Manage tickets', 'tickets.manage'),
('Manage HR', 'hr.manage'),
('Manage accounting', 'accounting.manage'),
('Manage marketing', 'marketing.manage'),
('Manage deliveries', 'deliveries.manage'),
('Manage WooCommerce', 'woocommerce.manage'),
('Manage customers', 'customers.manage'),
('Manage notifications', 'notifications.manage'),
('Manage users', 'users.manage'),
('View analytics', 'analytics.view')
ON DUPLICATE KEY UPDATE name = VALUES(name);

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p WHERE r.slug = 'super-admin' AND p.slug = '*';

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.slug <> '*'
WHERE r.slug = 'admin';

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.slug IN ('products.manage','stock.manage','transfers.manage','pos.use','tickets.manage','deliveries.manage','customers.manage','analytics.view','notifications.manage')
WHERE r.slug = 'manager';

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.slug IN ('hr.manage','analytics.view')
WHERE r.slug = 'rh';

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.slug IN ('accounting.manage','analytics.view')
WHERE r.slug = 'comptable';

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.slug IN ('pos.use','customers.manage','analytics.view')
WHERE r.slug = 'caissier';

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.slug IN ('deliveries.manage')
WHERE r.slug = 'livreur';

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.slug IN ('tickets.manage','customers.manage')
WHERE r.slug = 'support-client';

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.slug IN ('marketing.manage','customers.manage','analytics.view')
WHERE r.slug = 'marketing';

INSERT INTO users (role_id, name, email, password_hash, status)
SELECT r.id, 'Super Admin', 'admin@example.com', '$2y$10$T6TbaUC8EVByUh2HhDtHoeKWdZRQoMlcHqK6xCV6TsVeD8xQ.3PEm', 'active'
FROM roles r
WHERE r.slug = 'super-admin'
ON DUPLICATE KEY UPDATE role_id = VALUES(role_id), name = VALUES(name), password_hash = VALUES(password_hash), status = VALUES(status);

INSERT INTO users (role_id, name, email, password_hash, status)
SELECT r.id, 'Manager Demo', 'manager@example.com', '$2y$10$T6TbaUC8EVByUh2HhDtHoeKWdZRQoMlcHqK6xCV6TsVeD8xQ.3PEm', 'active'
FROM roles r
WHERE r.slug = 'manager'
ON DUPLICATE KEY UPDATE role_id = VALUES(role_id), name = VALUES(name), password_hash = VALUES(password_hash), status = VALUES(status);

INSERT INTO users (role_id, name, email, password_hash, status)
SELECT r.id, 'Caisse Demo', 'cashier@example.com', '$2y$10$T6TbaUC8EVByUh2HhDtHoeKWdZRQoMlcHqK6xCV6TsVeD8xQ.3PEm', 'active'
FROM roles r
WHERE r.slug = 'caissier'
ON DUPLICATE KEY UPDATE role_id = VALUES(role_id), name = VALUES(name), password_hash = VALUES(password_hash), status = VALUES(status);

INSERT INTO users (role_id, name, email, password_hash, status)
SELECT r.id, 'Support Demo', 'support@example.com', '$2y$10$T6TbaUC8EVByUh2HhDtHoeKWdZRQoMlcHqK6xCV6TsVeD8xQ.3PEm', 'active'
FROM roles r
WHERE r.slug = 'support-client'
ON DUPLICATE KEY UPDATE role_id = VALUES(role_id), name = VALUES(name), password_hash = VALUES(password_hash), status = VALUES(status);

INSERT INTO warehouses (name, code, type, city, status) VALUES
('Stock principal', 'MAIN', 'main', 'Tunis', 'active'),
('Showroom 1', 'SHOW-01', 'showroom', 'Tunis', 'active'),
('Showroom 2', 'SHOW-02', 'showroom', 'Sousse', 'active'),
('Dépôt', 'DEPOT', 'depot', 'Tunis', 'active'),
('Stock web', 'WEB', 'web', 'Online', 'active')
ON DUPLICATE KEY UPDATE name = VALUES(name), type = VALUES(type);

INSERT INTO categories (name, slug, status) VALUES
('Sport', 'sport', 'active'),
('Accessoires', 'accessoires', 'active')
ON DUPLICATE KEY UPDATE name = VALUES(name);

INSERT INTO brands (name, status) VALUES
('Evasion Signature', 'active'),
('Performance Line', 'active');

INSERT INTO suppliers (name, email, phone, status) VALUES
('Fournisseur Premium', 'supplier@example.com', '+21600000000', 'active');

INSERT INTO products (category_id, brand_id, supplier_id, sku, barcode, name, purchase_price, sale_price, promo_price, tax_rate, minimum_stock, status)
SELECT c.id, b.id, s.id, 'EV-SHOE-001', '619000000001', 'Sneakers Performance', 120, 249, 219, 19, 5, 'active'
FROM categories c, brands b, suppliers s
WHERE c.slug = 'sport' AND b.name = 'Performance Line' AND s.name = 'Fournisseur Premium'
ON DUPLICATE KEY UPDATE name = VALUES(name), sale_price = VALUES(sale_price);

INSERT INTO products (category_id, brand_id, supplier_id, sku, barcode, name, purchase_price, sale_price, tax_rate, minimum_stock, status)
SELECT c.id, b.id, s.id, 'EV-BAG-002', '619000000002', 'Sac Sport Premium', 80, 189, 19, 4, 'active'
FROM categories c, brands b, suppliers s
WHERE c.slug = 'accessoires' AND b.name = 'Evasion Signature' AND s.name = 'Fournisseur Premium'
ON DUPLICATE KEY UPDATE name = VALUES(name), sale_price = VALUES(sale_price);

INSERT INTO product_images (product_id, path, alt_text, sort_order)
SELECT p.id, '/assets/product-shoe.svg', 'Sneakers Performance', 0
FROM products p
WHERE p.sku = 'EV-SHOE-001' AND NOT EXISTS (SELECT 1 FROM product_images pi WHERE pi.product_id = p.id AND pi.path = '/assets/product-shoe.svg');

INSERT INTO product_images (product_id, path, alt_text, sort_order)
SELECT p.id, '/assets/product-bag.svg', 'Sac Sport Premium', 0
FROM products p
WHERE p.sku = 'EV-BAG-002' AND NOT EXISTS (SELECT 1 FROM product_images pi WHERE pi.product_id = p.id AND pi.path = '/assets/product-bag.svg');

INSERT IGNORE INTO stock (product_id, warehouse_id, quantity, reserved_quantity, sku_snapshot)
SELECT p.id, w.id, 25, 0, p.sku FROM products p JOIN warehouses w WHERE w.code IN ('MAIN','SHOW-01','WEB');

INSERT INTO customers (name, email, phone, whatsapp, city, loyalty_points, status) VALUES
('Client Démo', 'client@example.com', '+21611111111', '+21611111111', 'Tunis', 120, 'active');

INSERT INTO marketing_campaigns (name, channel, budget, revenue, starts_at, status) VALUES
('Launch Sport Orange', 'Meta Ads', 1200, 5400, CURDATE(), 'active');
