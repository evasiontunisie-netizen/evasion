<?php
// ============================================================
// ERP PRO - Stock Controller
// ============================================================

class StockController {

    public function index(): void {
        AuthMiddleware::authenticate();
        $warehouseId = $_GET['warehouse_id'] ?? null;
        $search      = $_GET['search'] ?? '';

        $where  = ['p.is_active = 1'];
        $params = [];
        if ($warehouseId) { $where[] = 's.warehouse_id = ?'; $params[] = $warehouseId; }
        if ($search) {
            $where[] = "(p.name LIKE ? OR p.sku LIKE ? OR p.barcode LIKE ?)";
            $like    = "%$search%";
            $params  = array_merge($params, [$like, $like, $like]);
        }
        $whereStr = implode(' AND ', $where);

        $sql = "SELECT p.id, p.name, p.sku, p.barcode, p.min_stock,
                       w.id as warehouse_id, w.name as warehouse_name, w.code as warehouse_code,
                       COALESCE(s.quantity, 0) as quantity, COALESCE(s.reserved_qty, 0) as reserved_qty,
                       (COALESCE(s.quantity, 0) - COALESCE(s.reserved_qty, 0)) as available,
                       (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as image
                FROM products p
                CROSS JOIN warehouses w
                LEFT JOIN stock s ON s.product_id = p.id AND s.warehouse_id = w.id
                WHERE $whereStr
                ORDER BY w.name, p.name";

        $page    = (int)($_GET['page']    ?? 1);
        $perPage = (int)($_GET['per_page']?? DEFAULT_PAGE_SIZE);
        Response::paginated(Database::paginate($sql, $params, $page, $perPage));
    }

    public function adjust(): void {
        $user = AuthMiddleware::authenticate();
        AuthMiddleware::requirePermission('stock.adjust');

        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $v = Validator::make($input, [
            'product_id'   => 'required|integer',
            'warehouse_id' => 'required|integer',
            'quantity'     => 'required|integer',
            'type'         => 'required|in:in,out,adjustment',
            'notes'        => 'nullable|string',
        ]);
        if ($v->fails()) Response::error('Données invalides', 422, $v->errors());

        $productId   = (int)$input['product_id'];
        $warehouseId = (int)$input['warehouse_id'];
        $qty         = (int)$input['quantity'];
        $type        = $input['type'];
        $variantId   = !empty($input['variant_id']) ? (int)$input['variant_id'] : null;

        $current = Database::fetch(
            "SELECT * FROM stock WHERE product_id = ? AND warehouse_id = ? AND variant_id <=> ?",
            [$productId, $warehouseId, $variantId]
        );
        $qtyBefore = $current ? $current['quantity'] : 0;
        $qtyAfter  = match($type) {
            'in'         => $qtyBefore + $qty,
            'out'        => max(0, $qtyBefore - $qty),
            'adjustment' => $qty,
            default      => $qtyBefore,
        };

        if ($current) {
            Database::update('stock', ['quantity' => $qtyAfter], 'id = ?', [$current['id']]);
        } else {
            Database::insert('stock', [
                'product_id'   => $productId,
                'variant_id'   => $variantId,
                'warehouse_id' => $warehouseId,
                'quantity'     => $qtyAfter,
            ]);
        }

        Database::insert('stock_movements', [
            'product_id'   => $productId,
            'variant_id'   => $variantId,
            'warehouse_id' => $warehouseId,
            'type'         => $type,
            'quantity'     => $qty,
            'qty_before'   => $qtyBefore,
            'qty_after'    => $qtyAfter,
            'notes'        => $input['notes'] ?? null,
            'user_id'      => $user['id'],
        ]);

        Logger::activity($user['id'], 'stock_adjust', 'stock', "Ajustement stock: produit=$productId, entrepôt=$warehouseId, type=$type, qty=$qty");
        Response::success(['qty_before' => $qtyBefore, 'qty_after' => $qtyAfter]);
    }

    public function movements(): void {
        AuthMiddleware::authenticate();
        $page    = (int)($_GET['page']    ?? 1);
        $perPage = (int)($_GET['per_page']?? DEFAULT_PAGE_SIZE);

        $where  = ['1=1'];
        $params = [];
        if (!empty($_GET['product_id']))   { $where[] = 'sm.product_id = ?';   $params[] = (int)$_GET['product_id']; }
        if (!empty($_GET['warehouse_id'])) { $where[] = 'sm.warehouse_id = ?'; $params[] = (int)$_GET['warehouse_id']; }
        if (!empty($_GET['type']))         { $where[] = 'sm.type = ?';         $params[] = $_GET['type']; }
        if (!empty($_GET['date_from']))    { $where[] = 'DATE(sm.created_at) >= ?'; $params[] = $_GET['date_from']; }
        if (!empty($_GET['date_to']))      { $where[] = 'DATE(sm.created_at) <= ?'; $params[] = $_GET['date_to']; }

        $whereStr = implode(' AND ', $where);
        $sql = "SELECT sm.*, p.name as product_name, p.sku,
                       w.name as warehouse_name, u.first_name, u.last_name
                FROM stock_movements sm
                JOIN products p ON p.id = sm.product_id
                JOIN warehouses w ON w.id = sm.warehouse_id
                LEFT JOIN users u ON u.id = sm.user_id
                WHERE $whereStr ORDER BY sm.created_at DESC";

        Response::paginated(Database::paginate($sql, $params, $page, $perPage));
    }

    public function inventory(): void {
        AuthMiddleware::authenticate();
        $warehouseId = (int)($_GET['warehouse_id'] ?? 0);
        if (!$warehouseId) Response::error('warehouse_id requis', 422);

        $rows = Database::fetchAll(
            "SELECT p.id, p.name, p.sku, p.barcode, p.min_stock,
                    COALESCE(s.quantity, 0) as system_qty,
                    COALESCE(s.reserved_qty, 0) as reserved_qty,
                    p.purchase_price, p.sale_price,
                    c.name as category_name
             FROM products p
             LEFT JOIN stock s ON s.product_id = p.id AND s.warehouse_id = ?
             LEFT JOIN categories c ON c.id = p.category_id
             WHERE p.is_active = 1 ORDER BY c.name, p.name",
            [$warehouseId]
        );
        Response::success($rows);
    }
}
