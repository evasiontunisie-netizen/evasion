<?php
// ============================================================
// ERP PRO - Order Controller (POS + Web)
// ============================================================

class OrderController {

    public function index(): void {
        AuthMiddleware::authenticate();
        $page       = (int)($_GET['page']    ?? 1);
        $perPage    = (int)($_GET['per_page']?? DEFAULT_PAGE_SIZE);
        $search     = $_GET['search']   ?? '';
        $status     = $_GET['status']   ?? '';
        $source     = $_GET['source']   ?? '';
        $warehouseId= $_GET['warehouse_id'] ?? '';
        $dateFrom   = $_GET['date_from'] ?? '';
        $dateTo     = $_GET['date_to']   ?? '';

        $where  = ['1=1'];
        $params = [];

        if ($search) {
            $where[] = "(o.order_number LIKE ? OR CONCAT(c.first_name,' ',c.last_name) LIKE ? OR c.phone LIKE ?)";
            $like    = "%$search%";
            $params  = array_merge($params, [$like, $like, $like]);
        }
        if ($status)      { $where[] = 'o.status = ?';       $params[] = $status; }
        if ($source)      { $where[] = 'o.source = ?';       $params[] = $source; }
        if ($warehouseId) { $where[] = 'o.warehouse_id = ?'; $params[] = $warehouseId; }
        if ($dateFrom)    { $where[] = 'DATE(o.created_at) >= ?'; $params[] = $dateFrom; }
        if ($dateTo)      { $where[] = 'DATE(o.created_at) <= ?'; $params[] = $dateTo; }

        $whereStr = implode(' AND ', $where);
        $sql = "SELECT o.*, 
                       CONCAT(c.first_name,' ',c.last_name) as customer_name, c.phone as customer_phone,
                       w.name as warehouse_name,
                       CONCAT(u.first_name,' ',u.last_name) as created_by_name
                FROM orders o
                LEFT JOIN customers c ON c.id = o.customer_id
                LEFT JOIN warehouses w ON w.id = o.warehouse_id
                LEFT JOIN users u ON u.id = o.user_id
                WHERE $whereStr ORDER BY o.created_at DESC";

        Response::paginated(Database::paginate($sql, $params, $page, $perPage));
    }

    public function show(int $id): void {
        AuthMiddleware::authenticate();
        $order = Database::fetch(
            "SELECT o.*, CONCAT(c.first_name,' ',c.last_name) as customer_name, c.phone as customer_phone,
                    c.email as customer_email, w.name as warehouse_name
             FROM orders o
             LEFT JOIN customers c ON c.id = o.customer_id
             LEFT JOIN warehouses w ON w.id = o.warehouse_id WHERE o.id = ?",
            [$id]
        );
        if (!$order) Response::notFound();

        $order['items']    = Database::fetchAll(
            "SELECT oi.*, (SELECT image_path FROM product_images WHERE product_id = oi.product_id AND is_primary = 1 LIMIT 1) as image
             FROM order_items oi WHERE oi.order_id = ?",
            [$id]
        );
        $order['payments'] = Database::fetchAll("SELECT * FROM payments WHERE order_id = ?", [$id]);

        Response::success($order);
    }

    public function store(): void {
        $user = AuthMiddleware::authenticate();
        AuthMiddleware::requirePermission('orders.create');

        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        if (empty($input['items'])) Response::error('Articles requis', 422);

        $warehouseId = (int)($input['warehouse_id'] ?? 0);
        if (!$warehouseId) Response::error('Entrepôt requis', 422);

        Database::beginTransaction();
        try {
            $orderNumber = 'ORD-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);

            $subtotal = 0;
            $taxAmt   = 0;
            $items    = [];

            foreach ($input['items'] as $item) {
                $product = Database::fetch("SELECT * FROM products WHERE id = ?", [(int)$item['product_id']]);
                if (!$product) continue;

                $qty       = max(1, (int)$item['quantity']);
                $unitPrice = (float)($item['unit_price'] ?? $product['sale_price']);
                $discount  = (float)($item['discount_amount'] ?? 0);
                $taxRate   = (float)($item['tax_rate'] ?? $product['tax_rate'] ?? 20);
                $lineTotal = ($unitPrice * $qty) - $discount;
                $lineTax   = $lineTotal * ($taxRate / 100);

                $subtotal += $lineTotal;
                $taxAmt   += $lineTax;

                $items[] = [
                    'product_id'      => $product['id'],
                    'variant_id'      => !empty($item['variant_id']) ? (int)$item['variant_id'] : null,
                    'product_name'    => $product['name'],
                    'sku'             => $product['sku'],
                    'quantity'        => $qty,
                    'unit_price'      => $unitPrice,
                    'discount_amount' => $discount,
                    'tax_rate'        => $taxRate,
                    'tax_amount'      => $lineTax,
                    'total'           => $lineTotal,
                ];
            }

            $discountPercent = (float)($input['discount_percent'] ?? 0);
            $discountAmount  = $subtotal * ($discountPercent / 100);
            $shippingAmt     = (float)($input['shipping_amount'] ?? 0);
            $total           = $subtotal - $discountAmount + $taxAmt + $shippingAmt;
            $amountPaid      = (float)($input['amount_paid'] ?? $total);

            $orderId = Database::insert('orders', [
                'order_number'    => $orderNumber,
                'customer_id'     => !empty($input['customer_id']) ? (int)$input['customer_id'] : null,
                'warehouse_id'    => $warehouseId,
                'user_id'         => $user['id'],
                'source'          => $input['source'] ?? 'pos',
                'status'          => 'completed',
                'payment_status'  => $amountPaid >= $total ? 'paid' : 'partial',
                'payment_method'  => $input['payment_method'] ?? 'cash',
                'subtotal'        => $subtotal,
                'discount_amount' => $discountAmount,
                'discount_percent'=> $discountPercent,
                'tax_amount'      => $taxAmt,
                'shipping_amount' => $shippingAmt,
                'total'           => $total,
                'amount_paid'     => $amountPaid,
                'notes'           => $input['notes'] ?? null,
            ]);

            foreach ($items as $item) {
                $item['order_id'] = $orderId;
                Database::insert('order_items', $item);

                // Deduct stock
                $this->deductStock($item['product_id'], $item['variant_id'], $warehouseId, $item['quantity'], $orderId, $user['id']);
            }

            // Payment record
            if ($amountPaid > 0) {
                Database::insert('payments', [
                    'order_id' => $orderId,
                    'method'   => $input['payment_method'] ?? 'cash',
                    'amount'   => $amountPaid,
                    'user_id'  => $user['id'],
                ]);
            }

            // Update customer stats
            if (!empty($input['customer_id'])) {
                Database::update('customers', [
                    'total_spent'   => Database::fetch("SELECT COALESCE(SUM(total),0) as t FROM orders WHERE customer_id = ?", [(int)$input['customer_id']])['t'],
                ], 'id = ?', [(int)$input['customer_id']]);
            }

            Database::commit();
        } catch (\Throwable $e) {
            Database::rollback();
            Logger::error('Order create failed: ' . $e->getMessage());
            Response::error('Erreur lors de la création', 500);
        }

        Logger::activity($user['id'], 'create', 'orders', "Commande créée: $orderNumber");
        Response::success(['id' => $orderId, 'order_number' => $orderNumber, 'total' => $total], 'Commande créée', 201);
    }

    public function updateStatus(int $id): void {
        $user   = AuthMiddleware::authenticate();
        $input  = json_decode(file_get_contents('php://input'), true) ?? [];
        $status = $input['status'] ?? '';
        if (!in_array($status, ['pending','processing','completed','cancelled','refunded','on_hold'])) {
            Response::error('Statut invalide', 422);
        }
        Database::update('orders', ['status' => $status], 'id = ?', [$id]);
        Logger::activity($user['id'], 'update_status', 'orders', "Commande #$id → $status");
        Response::success(null, 'Statut mis à jour');
    }

    public function stats(): void {
        AuthMiddleware::authenticate();
        $period      = $_GET['period']      ?? 'today';
        $warehouseId = $_GET['warehouse_id']?? null;

        $dateFilter = match($period) {
            'today'   => "DATE(created_at) = CURDATE()",
            'week'    => "YEARWEEK(created_at, 1) = YEARWEEK(NOW(), 1)",
            'month'   => "YEAR(created_at) = YEAR(NOW()) AND MONTH(created_at) = MONTH(NOW())",
            'year'    => "YEAR(created_at) = YEAR(NOW())",
            default   => "DATE(created_at) = CURDATE()",
        };

        $wFilter = $warehouseId ? " AND warehouse_id = $warehouseId" : '';

        $stats = [
            'revenue'    => Database::fetch("SELECT COALESCE(SUM(total),0) as v FROM orders WHERE $dateFilter AND status != 'cancelled'$wFilter")['v'],
            'orders'     => Database::fetch("SELECT COUNT(*) as v FROM orders WHERE $dateFilter$wFilter")['v'],
            'avg_order'  => Database::fetch("SELECT COALESCE(AVG(total),0) as v FROM orders WHERE $dateFilter AND status != 'cancelled'$wFilter")['v'],
            'new_customers'=> Database::fetch("SELECT COUNT(*) as v FROM customers WHERE $dateFilter")['v'],
        ];

        Response::success($stats);
    }

    private function deductStock(int $productId, ?int $variantId, int $warehouseId, int $qty, int $orderId, int $userId): void {
        $current = Database::fetch(
            "SELECT * FROM stock WHERE product_id = ? AND warehouse_id = ? AND variant_id <=> ?",
            [$productId, $warehouseId, $variantId]
        );
        $qtyBefore = $current['quantity'] ?? 0;
        $qtyAfter  = max(0, $qtyBefore - $qty);

        if ($current) {
            Database::update('stock', ['quantity' => $qtyAfter], 'id = ?', [$current['id']]);
        } else {
            Database::insert('stock', ['product_id' => $productId, 'variant_id' => $variantId, 'warehouse_id' => $warehouseId, 'quantity' => 0]);
        }

        Database::insert('stock_movements', [
            'product_id' => $productId, 'variant_id' => $variantId, 'warehouse_id' => $warehouseId,
            'type' => 'sale', 'quantity' => $qty, 'qty_before' => $qtyBefore, 'qty_after' => $qtyAfter,
            'reference_type' => 'order', 'reference_id' => $orderId, 'user_id' => $userId,
        ]);
    }
}
