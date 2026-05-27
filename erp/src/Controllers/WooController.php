<?php
// ============================================================
// ERP PRO - WooCommerce Integration Controller
// ============================================================

class WooController {

    public function sites(): void {
        AuthMiddleware::authenticate();
        $sites = Database::fetchAll(
            "SELECT id, name, url, warehouse_id, is_active, last_sync, sync_status, created_at FROM woo_sites ORDER BY name"
        );
        Response::success($sites);
    }

    public function addSite(): void {
        $user = AuthMiddleware::authenticate();
        AuthMiddleware::requireRole(['super_admin','admin']);

        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $v = Validator::make($input, [
            'name'            => 'required|string|max:100',
            'url'             => 'required|url',
            'consumer_key'    => 'required|string',
            'consumer_secret' => 'required|string',
        ]);
        if ($v->fails()) Response::error('Données invalides', 422, $v->errors());

        // Test connection
        $test = $this->wooRequest($input['url'], $input['consumer_key'], $input['consumer_secret'], '/wp-json/wc/v3/products?per_page=1');
        if (!$test) Response::error('Impossible de se connecter au site WooCommerce', 422);

        $id = Database::insert('woo_sites', [
            'name'            => htmlspecialchars($input['name'], ENT_QUOTES),
            'url'             => rtrim($input['url'], '/'),
            'consumer_key'    => $input['consumer_key'],
            'consumer_secret' => $input['consumer_secret'],
            'webhook_secret'  => $input['webhook_secret'] ?? null,
            'warehouse_id'    => !empty($input['warehouse_id']) ? (int)$input['warehouse_id'] : null,
        ]);

        Logger::activity($user['id'], 'create', 'woo_sites', "Site WooCommerce ajouté: {$input['name']}");
        Response::success(['id' => $id], 'Site ajouté', 201);
    }

    public function syncOrders(int $siteId): void {
        $user = AuthMiddleware::authenticate();
        AuthMiddleware::requireRole(['super_admin','admin','manager']);

        $site = Database::fetch("SELECT * FROM woo_sites WHERE id = ? AND is_active = 1", [$siteId]);
        if (!$site) Response::notFound('Site introuvable');

        $page  = 1;
        $total = 0;
        $errors= 0;
        $since = $site['last_sync'] ? date('Y-m-d\TH:i:s', strtotime($site['last_sync'])) : null;

        do {
            $params = "per_page=100&page=$page&orderby=date&order=desc";
            if ($since) $params .= "&after=$since";

            $orders = $this->wooRequest($site['url'], $site['consumer_key'], $site['consumer_secret'],
                                        "/wp-json/wc/v3/orders?$params");
            if (!$orders || !is_array($orders)) break;

            foreach ($orders as $wooOrder) {
                try {
                    $this->importWooOrder($wooOrder, $site);
                    $total++;
                } catch (\Throwable $e) {
                    $errors++;
                    Logger::error('WooOrder import failed: ' . $e->getMessage(), ['woo_id' => $wooOrder['id']]);
                }
            }
            $page++;
        } while (count($orders) === 100);

        Database::update('woo_sites', ['last_sync' => date('Y-m-d H:i:s'), 'sync_status' => 'success'], 'id = ?', [$siteId]);
        Database::insert('woo_sync_logs', [
            'site_id' => $siteId, 'type' => 'order', 'direction' => 'pull',
            'status' => $errors > 0 ? 'partial' : 'success',
            'records_processed' => $total, 'records_failed' => $errors,
        ]);

        Response::success(['imported' => $total, 'errors' => $errors], "Sync terminé: $total commandes importées");
    }

    public function syncStock(int $siteId): void {
        $user = AuthMiddleware::authenticate();
        AuthMiddleware::requireRole(['super_admin','admin','manager']);

        $site = Database::fetch("SELECT * FROM woo_sites WHERE id = ? AND is_active = 1", [$siteId]);
        if (!$site) Response::notFound();

        $products = Database::fetchAll(
            "SELECT p.woo_product_id, COALESCE(SUM(s.quantity), 0) as total_stock
             FROM products p LEFT JOIN stock s ON s.product_id = p.id
             WHERE p.woo_product_id IS NOT NULL GROUP BY p.id"
        );

        $updated = 0;
        foreach ($products as $product) {
            $result = $this->wooRequest(
                $site['url'], $site['consumer_key'], $site['consumer_secret'],
                "/wp-json/wc/v3/products/{$product['woo_product_id']}",
                'PUT', ['stock_quantity' => (int)$product['total_stock'], 'manage_stock' => true]
            );
            if ($result) $updated++;
        }

        Response::success(['updated' => $updated], "Stock synchronisé: $updated produits mis à jour");
    }

    public function webhook(int $siteId): void {
        $site = Database::fetch("SELECT * FROM woo_sites WHERE id = ? AND is_active = 1", [$siteId]);
        if (!$site) { http_response_code(404); exit; }

        $payload = file_get_contents('php://input');
        $event   = $_SERVER['HTTP_X_WC_WEBHOOK_TOPIC'] ?? '';

        // Verify signature
        if ($site['webhook_secret']) {
            $sig = $_SERVER['HTTP_X_WC_WEBHOOK_SIGNATURE'] ?? '';
            $expected = base64_encode(hash_hmac('sha256', $payload, $site['webhook_secret'], true));
            if (!hash_equals($expected, $sig)) { http_response_code(401); exit; }
        }

        $data = json_decode($payload, true);
        if (!$data) { http_response_code(400); exit; }

        switch ($event) {
            case 'order.created':
            case 'order.updated':
                $this->importWooOrder($data, $site);
                break;
        }

        http_response_code(200);
        echo json_encode(['received' => true]);
        exit;
    }

    private function importWooOrder(array $wooOrder, array $site): void {
        $existing = Database::fetch("SELECT id FROM orders WHERE woo_order_id = ? AND woo_site_id = ?", [$wooOrder['id'], $site['id']]);
        if ($existing) return;

        // Find or create customer
        $billing    = $wooOrder['billing'] ?? [];
        $customerId = null;
        if (!empty($billing['phone'])) {
            $customer = Database::fetch("SELECT id FROM customers WHERE phone = ?", [$billing['phone']]);
            if (!$customer) {
                $customerId = Database::insert('customers', [
                    'first_name'     => $billing['first_name'] ?? 'Client',
                    'last_name'      => $billing['last_name']  ?? 'WooCommerce',
                    'email'          => $billing['email']  ?? null,
                    'phone'          => $billing['phone']  ?? null,
                    'address'        => ($billing['address_1'] ?? '') . ' ' . ($billing['address_2'] ?? ''),
                    'city'           => $billing['city']   ?? null,
                    'woo_customer_id'=> $wooOrder['customer_id'] ?? null,
                    'source'         => 'woo',
                ]);
            } else {
                $customerId = $customer['id'];
            }
        }

        $statusMap = ['pending' => 'pending', 'processing' => 'processing', 'completed' => 'completed', 'cancelled' => 'cancelled', 'refunded' => 'refunded', 'on-hold' => 'on_hold'];
        $payMap    = ['cod' => 'cash', 'bacs' => 'transfer', 'cheque' => 'transfer', 'stripe' => 'online', 'paypal' => 'online'];

        $orderId = Database::insert('orders', [
            'order_number'    => 'WOO-' . $wooOrder['id'],
            'customer_id'     => $customerId,
            'warehouse_id'    => $site['warehouse_id'],
            'source'          => 'woo',
            'woo_order_id'    => $wooOrder['id'],
            'woo_site_id'     => $site['id'],
            'status'          => $statusMap[$wooOrder['status']] ?? 'pending',
            'payment_status'  => in_array($wooOrder['status'], ['completed','processing']) ? 'paid' : 'pending',
            'payment_method'  => $payMap[$wooOrder['payment_method']] ?? 'online',
            'subtotal'        => (float)$wooOrder['subtotal'],
            'discount_amount' => (float)$wooOrder['discount_total'],
            'tax_amount'      => (float)$wooOrder['total_tax'],
            'shipping_amount' => (float)$wooOrder['shipping_total'],
            'total'           => (float)$wooOrder['total'],
            'amount_paid'     => in_array($wooOrder['status'], ['completed','processing']) ? (float)$wooOrder['total'] : 0,
            'billing_address' => json_encode($billing),
            'shipping_address'=> json_encode($wooOrder['shipping'] ?? []),
        ]);

        foreach ($wooOrder['line_items'] ?? [] as $item) {
            $product = Database::fetch("SELECT id FROM products WHERE woo_product_id = ?", [$item['product_id']]);
            if ($product) {
                Database::insert('order_items', [
                    'order_id'    => $orderId,
                    'product_id'  => $product['id'],
                    'product_name'=> $item['name'],
                    'quantity'    => $item['quantity'],
                    'unit_price'  => (float)$item['price'],
                    'tax_amount'  => (float)$item['total_tax'],
                    'total'       => (float)$item['total'],
                ]);
            }
        }
    }

    private function wooRequest(string $url, string $key, string $secret, string $endpoint, string $method = 'GET', array $body = []): mixed {
        $ch = curl_init(rtrim($url, '/') . $endpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_USERPWD        => "$key:$secret",
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Accept: application/json'],
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_CUSTOMREQUEST  => $method,
        ]);
        if ($body && in_array($method, ['PUT','POST','PATCH'])) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }
        $response = curl_exec($ch);
        $code     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (!$response || $code >= 400) return null;
        return json_decode($response, true);
    }
}
