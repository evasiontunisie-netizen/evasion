<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Export\Exporter;
use App\Core\Logger;
use App\Core\Realtime\WebSocketNotifier;
use App\Core\Repository;
use App\Core\Request;
use App\Core\Response;
use App\Support\ModuleRegistry;

final class ModuleController extends Controller
{
    public function index(Request $request): void
    {
        $repo = $this->repo($request->params['resource'] ?? '');
        $this->ok($repo->paginate($request->query));
    }

    public function show(Request $request): void
    {
        $repo = $this->repo($request->params['resource'] ?? '');
        $item = $repo->find((int) $request->params['id']);
        $item ? $this->ok(['item' => $item]) : $this->error('Not found', 404);
    }

    public function store(Request $request): void
    {
        $resource = $request->params['resource'] ?? '';
        if ($resource === 'users') {
            $item = $this->storeUser($request);
            Logger::activity((int) ($request->user['sub'] ?? 0), 'users.created', ['id' => $item['id'] ?? null]);
            $this->ok(['item' => $item], 201);
            return;
        }

        $item = $this->repo($resource)->create($request->body);
        Logger::activity((int) ($request->user['sub'] ?? 0), $resource . '.created', ['id' => $item['id'] ?? null]);
        WebSocketNotifier::publish('erp', ['event' => $resource . '.created', 'item' => $item]);
        $this->ok(['item' => $item], 201);
    }

    public function update(Request $request): void
    {
        $resource = $request->params['resource'] ?? '';
        $item = $this->repo($resource)->update((int) $request->params['id'], $request->body);
        Logger::activity((int) ($request->user['sub'] ?? 0), $resource . '.updated', ['id' => $request->params['id']]);
        WebSocketNotifier::publish('erp', ['event' => $resource . '.updated', 'item' => $item]);
        $item ? $this->ok(['item' => $item]) : $this->error('Not found', 404);
    }

    public function destroy(Request $request): void
    {
        $resource = $request->params['resource'] ?? '';
        $this->repo($resource)->delete((int) $request->params['id']);
        Logger::activity((int) ($request->user['sub'] ?? 0), $resource . '.deleted', ['id' => $request->params['id']]);
        $this->ok(['deleted' => true]);
    }

    public function export(Request $request): void
    {
        $resource = $request->params['resource'] ?? '';
        $format = strtolower((string) ($request->query['format'] ?? 'csv'));
        $rows = $this->repo($resource)->exportRows($request->query);
        if ($format === 'pdf') {
            Response::download(Exporter::pdf(strtoupper($resource), $rows), $resource . '.pdf', 'application/pdf');
            return;
        }
        if ($format === 'xls' || $format === 'xlsx') {
            Response::download(Exporter::xls($rows), $resource . '.xls', 'application/vnd.ms-excel');
            return;
        }

        Response::download(Exporter::csv($rows), $resource . '.csv', 'text/csv; charset=utf-8');
    }

    public function importCsv(Request $request): void
    {
        $resource = $request->params['resource'] ?? '';
        if (!isset($_FILES['file']['tmp_name']) || !is_uploaded_file($_FILES['file']['tmp_name'])) {
            $this->error('CSV file is required');
            return;
        }

        $handle = fopen($_FILES['file']['tmp_name'], 'r');
        $headers = $handle ? fgetcsv($handle) : false;
        if ($resource === 'products' && $headers && in_array('Nom', $headers, true) && in_array('Images', $headers, true)) {
            $result = $this->importWooCommerceProducts($handle, $headers);
            if ($handle) {
                fclose($handle);
            }
            $this->ok($result);
            return;
        }

        $count = 0;
        while ($handle && $headers && ($row = fgetcsv($handle)) !== false) {
            $this->repo($resource)->create(array_combine($headers, $row) ?: []);
            $count++;
        }
        if ($handle) {
            fclose($handle);
        }

        $this->ok(['imported' => $count]);
    }

    public function transferReceive(Request $request): void
    {
        $id = (int) $request->params['id'];
        Database::pdo()->prepare('UPDATE transfers SET status = "received", received_at = NOW(), signature_path = COALESCE(:signature_path, signature_path) WHERE id = :id')
            ->execute(['id' => $id, 'signature_path' => $request->input('signature_path')]);
        WebSocketNotifier::publish('transfers', ['event' => 'transfer.received', 'id' => $id]);
        $this->ok(['transfer' => $this->repo('transfers')->find($id)]);
    }

    public function posCheckout(Request $request): void
    {
        $items = (array) $request->input('items', []);
        $customerId = $request->input('customer_id');
        $warehouseId = (int) $request->input('warehouse_id', 1);
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $subtotal = 0.0;
            foreach ($items as $item) {
                $subtotal += ((float) ($item['price'] ?? 0)) * ((int) ($item['quantity'] ?? 1));
            }
            $discount = (float) $request->input('discount_total', 0);
            $tax = (float) $request->input('tax_total', 0);
            $grandTotal = max(0, $subtotal - $discount + $tax);
            $orderNumber = 'POS-' . date('Ymd-His') . '-' . random_int(100, 999);
            $order = $this->repo('orders')->create([
                'customer_id' => $customerId,
                'warehouse_id' => $warehouseId,
                'user_id' => $request->user['sub'] ?? null,
                'order_number' => $orderNumber,
                'channel' => 'pos',
                'status' => 'paid',
                'payment_status' => 'paid',
                'subtotal' => $subtotal,
                'discount_total' => $discount,
                'tax_total' => $tax,
                'grand_total' => $grandTotal,
                'currency' => 'TND',
            ]);
            $line = $pdo->prepare('INSERT INTO order_items (order_id, product_id, sku, name, quantity, unit_price, tax_rate, total) VALUES (:order_id, :product_id, :sku, :name, :quantity, :unit_price, :tax_rate, :total)');
            $stock = $pdo->prepare('UPDATE stock SET quantity = quantity - :quantity WHERE product_id = :product_id AND warehouse_id = :warehouse_id');
            foreach ($items as $item) {
                $qty = (int) ($item['quantity'] ?? 1);
                $price = (float) ($item['price'] ?? 0);
                $line->execute([
                    'order_id' => $order['id'],
                    'product_id' => $item['product_id'] ?? null,
                    'sku' => $item['sku'] ?? null,
                    'name' => $item['name'] ?? 'Produit',
                    'quantity' => $qty,
                    'unit_price' => $price,
                    'tax_rate' => $item['tax_rate'] ?? 0,
                    'total' => $qty * $price,
                ]);
                if (!empty($item['product_id'])) {
                    $stock->execute(['quantity' => $qty, 'product_id' => $item['product_id'], 'warehouse_id' => $warehouseId]);
                }
            }
            $pdo->commit();
            WebSocketNotifier::publish('orders', ['event' => 'pos.sale', 'order' => $order]);
            $this->ok(['order' => $order, 'receipt_url' => '/api/orders/' . $order['id'] . '/export?format=pdf'], 201);
        } catch (\Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }
    }

    private function repo(string $resource): Repository
    {
        $resources = ModuleRegistry::resources();
        if (!isset($resources[$resource])) {
            Response::json(['success' => false, 'error' => 'Unknown resource'], 404);
            exit;
        }
        $config = $resources[$resource];

        return new Repository($config['table'], $config['fields'], $config['search']);
    }

    private function storeUser(Request $request): array
    {
        $password = (string) $request->input('password', 'ChangeMeSecure123!');
        $statement = Database::pdo()->prepare('INSERT INTO users (role_id, name, email, password_hash, avatar_path, status) VALUES (:role_id, :name, :email, :password_hash, :avatar_path, :status)');
        $statement->execute([
            'role_id' => (int) $request->input('role_id', 10),
            'name' => $request->input('name'),
            'email' => strtolower((string) $request->input('email')),
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'avatar_path' => $request->input('avatar_path'),
            'status' => $request->input('status', 'active'),
        ]);

        return $this->repo('users')->find((int) Database::pdo()->lastInsertId()) ?? [];
    }

    private function importWooCommerceProducts($handle, array $headers): array
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        $imported = 0;
        $images = 0;
        try {
            while (($row = fgetcsv($handle)) !== false) {
                $record = array_combine($headers, $row) ?: [];
                $name = trim((string) ($record['Nom'] ?? ''));
                if ($name === '') {
                    continue;
                }

                $categoryId = $this->ensureCategory((string) ($record['Catégories'] ?? 'WooCommerce'));
                $brandId = $this->ensureBrand((string) ($record['Marques'] ?? ''));
                $sku = trim((string) ($record['UGS'] ?? '')) ?: 'WC-' . (string) ($record['ID'] ?? uniqid());
                $productId = $this->upsertWooProduct($record, $categoryId, $brandId, $sku, $name);
                $images += $this->syncProductImages($productId, (string) ($record['Images'] ?? ''));
                $this->syncWooStock($productId, $sku, (string) ($record['Stock'] ?? ''));
                $imported++;
            }
            $pdo->commit();
        } catch (\Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }

        return ['imported' => $imported, 'images' => $images, 'format' => 'woocommerce_fr'];
    }

    private function upsertWooProduct(array $record, ?int $categoryId, ?int $brandId, string $sku, string $name): int
    {
        $pdo = Database::pdo();
        $existing = $pdo->prepare('SELECT id FROM products WHERE sku = :sku LIMIT 1');
        $existing->execute(['sku' => $sku]);
        $id = (int) $existing->fetchColumn();
        $payload = [
            'category_id' => $categoryId,
            'brand_id' => $brandId,
            'sku' => $sku,
            'barcode' => trim((string) ($record['GTIN, UPC, EAN ou ISBN'] ?? '')) ?: null,
            'name' => $name,
            'description' => (string) ($record['Description'] ?? $record['Description courte'] ?? ''),
            'sale_price' => $this->money((string) ($record['Tarif régulier'] ?? '0')),
            'promo_price' => $this->nullableMoney((string) ($record['Tarif promo'] ?? '')),
            'minimum_stock' => (int) $this->money((string) ($record['Montant de stock faible'] ?? '0')),
            'status' => ((string) ($record['Publié'] ?? '1')) === '1' ? 'active' : 'draft',
        ];

        if ($id > 0) {
            $sql = 'UPDATE products SET category_id = :category_id, brand_id = :brand_id, barcode = :barcode, name = :name, description = :description, sale_price = :sale_price, promo_price = :promo_price, minimum_stock = :minimum_stock, status = :status WHERE id = :id';
            $payload['id'] = $id;
            $pdo->prepare($sql)->execute($payload);
            return $id;
        }

        $sql = 'INSERT INTO products (category_id, brand_id, sku, barcode, name, description, sale_price, promo_price, minimum_stock, status) VALUES (:category_id, :brand_id, :sku, :barcode, :name, :description, :sale_price, :promo_price, :minimum_stock, :status)';
        $pdo->prepare($sql)->execute($payload);

        return (int) $pdo->lastInsertId();
    }

    private function syncProductImages(int $productId, string $images): int
    {
        $pdo = Database::pdo();
        $pdo->prepare('DELETE FROM product_images WHERE product_id = :product_id')->execute(['product_id' => $productId]);
        $urls = array_values(array_filter(array_map('trim', explode(',', $images))));
        $statement = $pdo->prepare('INSERT INTO product_images (product_id, path, sort_order) VALUES (:product_id, :path, :sort_order)');
        foreach ($urls as $index => $url) {
            $statement->execute(['product_id' => $productId, 'path' => $url, 'sort_order' => $index]);
        }

        return count($urls);
    }

    private function syncWooStock(int $productId, string $sku, string $stock): void
    {
        $quantity = (int) $this->money($stock);
        $warehouseId = (int) Database::pdo()->query("SELECT id FROM warehouses WHERE type = 'web' ORDER BY id LIMIT 1")->fetchColumn();
        if ($warehouseId <= 0) {
            $warehouseId = (int) Database::pdo()->query('SELECT id FROM warehouses ORDER BY id LIMIT 1')->fetchColumn();
        }
        if ($warehouseId <= 0) {
            return;
        }

        Database::pdo()->prepare('INSERT INTO stock (product_id, warehouse_id, quantity, reserved_quantity, sku_snapshot) VALUES (:product_id, :warehouse_id, :quantity, 0, :sku) ON DUPLICATE KEY UPDATE quantity = VALUES(quantity), sku_snapshot = VALUES(sku_snapshot)')
            ->execute(['product_id' => $productId, 'warehouse_id' => $warehouseId, 'quantity' => $quantity, 'sku' => $sku]);
    }

    private function ensureCategory(string $value): ?int
    {
        $name = trim(explode(',', $value)[0] ?? 'WooCommerce');
        $parts = array_map('trim', explode('>', $name));
        $name = end($parts) ?: 'WooCommerce';
        $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', iconv('UTF-8', 'ASCII//TRANSLIT', $name) ?: $name));
        $slug = trim($slug, '-') ?: 'woocommerce';
        Database::pdo()->prepare('INSERT INTO categories (name, slug, status) VALUES (:name, :slug, "active") ON DUPLICATE KEY UPDATE name = VALUES(name)')
            ->execute(['name' => $name, 'slug' => $slug]);

        return (int) Database::pdo()->query('SELECT id FROM categories WHERE slug = ' . Database::pdo()->quote($slug) . ' LIMIT 1')->fetchColumn();
    }

    private function ensureBrand(string $value): ?int
    {
        $name = trim($value);
        if ($name === '') {
            return null;
        }
        Database::pdo()->prepare('INSERT INTO brands (name, status) VALUES (:name, "active")')->execute(['name' => $name]);

        return (int) Database::pdo()->lastInsertId();
    }

    private function money(string $value): float
    {
        $normalized = str_replace([' ', ','], ['', '.'], trim($value));
        return is_numeric($normalized) ? (float) $normalized : 0.0;
    }

    private function nullableMoney(string $value): ?float
    {
        return trim($value) === '' ? null : $this->money($value);
    }
}
