<?php
// ============================================================
// ERP PRO - Transfer Controller
// ============================================================

class TransferController {

    public function index(): void {
        AuthMiddleware::authenticate();
        $page    = (int)($_GET['page']    ?? 1);
        $perPage = (int)($_GET['per_page']?? DEFAULT_PAGE_SIZE);
        $status  = $_GET['status'] ?? '';

        $where  = ['1=1'];
        $params = [];
        if ($status) { $where[] = 't.status = ?'; $params[] = $status; }

        $whereStr = implode(' AND ', $where);
        $sql = "SELECT t.*, 
                       fw.name as from_warehouse, fw.code as from_code,
                       tw.name as to_warehouse, tw.code as to_code,
                       u.first_name, u.last_name,
                       COUNT(ti.id) as item_count
                FROM transfers t
                JOIN warehouses fw ON fw.id = t.from_warehouse_id
                JOIN warehouses tw ON tw.id = t.to_warehouse_id
                LEFT JOIN users u ON u.id = t.created_by
                LEFT JOIN transfer_items ti ON ti.transfer_id = t.id
                WHERE $whereStr GROUP BY t.id ORDER BY t.created_at DESC";

        Response::paginated(Database::paginate($sql, $params, $page, $perPage));
    }

    public function show(int $id): void {
        AuthMiddleware::authenticate();
        $transfer = Database::fetch(
            "SELECT t.*, fw.name as from_warehouse, tw.name as to_warehouse,
                    u.first_name, u.last_name
             FROM transfers t
             JOIN warehouses fw ON fw.id = t.from_warehouse_id
             JOIN warehouses tw ON tw.id = t.to_warehouse_id
             LEFT JOIN users u ON u.id = t.created_by
             WHERE t.id = ?",
            [$id]
        );
        if (!$transfer) Response::notFound();

        $transfer['items'] = Database::fetchAll(
            "SELECT ti.*, p.name as product_name, p.sku, p.barcode
             FROM transfer_items ti JOIN products p ON p.id = ti.product_id WHERE ti.transfer_id = ?",
            [$id]
        );
        Response::success($transfer);
    }

    public function store(): void {
        $user = AuthMiddleware::authenticate();
        AuthMiddleware::requirePermission('transfers.create');

        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $v = Validator::make($input, [
            'from_warehouse_id' => 'required|integer',
            'to_warehouse_id'   => 'required|integer',
        ]);
        if ($v->fails()) Response::error('Données invalides', 422, $v->errors());
        if (empty($input['items'])) Response::error('Articles requis', 422);
        if ($input['from_warehouse_id'] === $input['to_warehouse_id']) Response::error('Les entrepôts doivent être différents', 422);

        $number = 'TRF-' . strtoupper(substr(md5(uniqid()), 0, 8));

        Database::beginTransaction();
        try {
            $transferId = Database::insert('transfers', [
                'transfer_number'   => $number,
                'from_warehouse_id' => (int)$input['from_warehouse_id'],
                'to_warehouse_id'   => (int)$input['to_warehouse_id'],
                'notes'             => $input['notes'] ?? null,
                'created_by'        => $user['id'],
            ]);

            foreach ($input['items'] as $item) {
                if (empty($item['product_id']) || empty($item['quantity'])) continue;
                Database::insert('transfer_items', [
                    'transfer_id'        => $transferId,
                    'product_id'         => (int)$item['product_id'],
                    'variant_id'         => !empty($item['variant_id']) ? (int)$item['variant_id'] : null,
                    'quantity_requested' => (int)$item['quantity'],
                ]);
            }
            Database::commit();
        } catch (\Throwable $e) {
            Database::rollback();
            Logger::error('Transfer create failed: ' . $e->getMessage());
            Response::error('Erreur lors de la création', 500);
        }

        Logger::activity($user['id'], 'create', 'transfers', "Transfert créé: $number");
        Response::success(['id' => $transferId, 'transfer_number' => $number], 'Transfert créé', 201);
    }

    public function updateStatus(int $id): void {
        $user   = AuthMiddleware::authenticate();
        $input  = json_decode(file_get_contents('php://input'), true) ?? [];
        $status = $input['status'] ?? '';

        $allowed = ['validated','shipped','received','cancelled'];
        if (!in_array($status, $allowed)) Response::error('Statut invalide', 422);

        $transfer = Database::fetch("SELECT * FROM transfers WHERE id = ?", [$id]);
        if (!$transfer) Response::notFound();

        $update = ['status' => $status];
        if ($status === 'shipped')  $update['shipped_at'] = date('Y-m-d H:i:s');
        if ($status === 'received') {
            $update['received_at']  = date('Y-m-d H:i:s');
            $update['validated_by'] = $user['id'];
            if (!empty($input['signature'])) $update['signature'] = $input['signature'];
            $this->processReceipt($id, $transfer, $input['items'] ?? [], $user['id']);
        }
        if ($status === 'validated') $update['validated_by'] = $user['id'];

        Database::update('transfers', $update, 'id = ?', [$id]);
        Logger::activity($user['id'], 'update_status', 'transfers', "Transfert {$transfer['transfer_number']} → $status");

        // Notify
        $this->notifyTransferStatus($transfer, $status, $user);

        Response::success(null, 'Statut mis à jour');
    }

    private function processReceipt(int $transferId, array $transfer, array $receivedItems, int $userId): void {
        $items = Database::fetchAll(
            "SELECT * FROM transfer_items WHERE transfer_id = ?", [$transferId]
        );

        foreach ($items as $item) {
            $received = 0;
            foreach ($receivedItems as $r) {
                if ((int)$r['item_id'] === $item['id']) { $received = (int)$r['quantity_received']; break; }
            }
            if ($received === 0) $received = $item['quantity_requested'];

            Database::update('transfer_items', ['quantity_received' => $received], 'id = ?', [$item['id']]);

            // Deduct from source
            $this->moveStock($item['product_id'], $item['variant_id'], $transfer['from_warehouse_id'],
                             'transfer_out', $received, $transferId, $userId);
            // Add to destination
            $this->moveStock($item['product_id'], $item['variant_id'], $transfer['to_warehouse_id'],
                             'transfer_in', $received, $transferId, $userId);
        }
    }

    private function moveStock(int $productId, ?int $variantId, int $warehouseId, string $type, int $qty, int $refId, int $userId): void {
        $current = Database::fetch(
            "SELECT * FROM stock WHERE product_id = ? AND warehouse_id = ? AND variant_id <=> ?",
            [$productId, $warehouseId, $variantId]
        );
        $qtyBefore = $current['quantity'] ?? 0;
        $qtyAfter  = $type === 'transfer_out' ? max(0, $qtyBefore - $qty) : $qtyBefore + $qty;

        if ($current) {
            Database::update('stock', ['quantity' => $qtyAfter], 'id = ?', [$current['id']]);
        } else {
            Database::insert('stock', [
                'product_id' => $productId, 'variant_id' => $variantId,
                'warehouse_id' => $warehouseId, 'quantity' => $qtyAfter,
            ]);
        }

        Database::insert('stock_movements', [
            'product_id' => $productId, 'variant_id' => $variantId,
            'warehouse_id' => $warehouseId, 'type' => $type,
            'quantity' => $qty, 'qty_before' => $qtyBefore, 'qty_after' => $qtyAfter,
            'reference_type' => 'transfer', 'reference_id' => $refId, 'user_id' => $userId,
        ]);
    }

    private function notifyTransferStatus(array $transfer, string $status, array $user): void {
        try {
            $labels = ['validated' => 'Validé', 'shipped' => 'Expédié', 'received' => 'Reçu', 'cancelled' => 'Annulé'];
            Database::insert('notifications', [
                'type'  => 'transfer_status',
                'title' => "Transfert {$transfer['transfer_number']}",
                'body'  => "Statut mis à jour: " . ($labels[$status] ?? $status),
                'data'  => json_encode(['transfer_id' => $transfer['id']]),
                'icon'  => 'truck',
                'color' => $status === 'received' ? 'green' : ($status === 'cancelled' ? 'red' : 'blue'),
            ]);
        } catch (\Throwable $e) {}
    }
}
