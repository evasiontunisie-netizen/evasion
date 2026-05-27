<?php
// ============================================================
// ERP PRO - Delivery Controller
// ============================================================

class DeliveryController {

    public function index(): void {
        AuthMiddleware::authenticate();
        $page    = (int)($_GET['page']    ?? 1);
        $perPage = (int)($_GET['per_page']?? DEFAULT_PAGE_SIZE);
        $status  = $_GET['status']    ?? '';
        $driver  = $_GET['driver_id'] ?? '';
        $search  = $_GET['search']    ?? '';

        $where  = ['1=1'];
        $params = [];
        if ($status) { $where[] = 'd.status = ?';    $params[] = $status; }
        if ($driver) { $where[] = 'd.driver_id = ?'; $params[] = $driver; }
        if ($search) {
            $where[] = "(d.delivery_number LIKE ? OR d.phone LIKE ? OR d.city LIKE ?)";
            $like    = "%$search%";
            $params  = array_merge($params, [$like, $like, $like]);
        }

        $whereStr = implode(' AND ', $where);
        $sql = "SELECT d.*, o.order_number, o.total as order_total,
                       CONCAT(c.first_name,' ',c.last_name) as customer_name,
                       CONCAT(e.first_name,' ',e.last_name) as driver_name,
                       dz.name as zone_name
                FROM deliveries d
                JOIN orders o ON o.id = d.order_id
                LEFT JOIN customers c ON c.id = o.customer_id
                LEFT JOIN employees e ON e.id = d.driver_id
                LEFT JOIN delivery_zones dz ON dz.id = d.zone_id
                WHERE $whereStr ORDER BY d.created_at DESC";

        Response::paginated(Database::paginate($sql, $params, $page, $perPage));
    }

    public function store(): void {
        $user = AuthMiddleware::authenticate();
        $input = json_decode(file_get_contents('php://input'), true) ?? [];

        $v = Validator::make($input, [
            'order_id' => 'required|integer',
            'address'  => 'required|string',
        ]);
        if ($v->fails()) Response::error('Données invalides', 422, $v->errors());

        $order = Database::fetch("SELECT * FROM orders WHERE id = ?", [(int)$input['order_id']]);
        if (!$order) Response::notFound('Commande introuvable');

        $number = 'DEL-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);

        $id = Database::insert('deliveries', [
            'delivery_number' => $number,
            'order_id'        => (int)$input['order_id'],
            'driver_id'       => !empty($input['driver_id']) ? (int)$input['driver_id'] : null,
            'zone_id'         => !empty($input['zone_id'])   ? (int)$input['zone_id']   : null,
            'address'         => htmlspecialchars($input['address'], ENT_QUOTES),
            'city'            => $input['city'] ?? null,
            'phone'           => $input['phone'] ?? null,
            'delivery_fee'    => (float)($input['delivery_fee'] ?? 0),
            'scheduled_at'    => $input['scheduled_at'] ?? null,
            'notes'           => $input['notes'] ?? null,
            'tracking_code'   => strtoupper(substr(md5(uniqid()), 0, 12)),
        ]);

        Logger::activity($user['id'], 'create', 'deliveries', "Livraison créée: $number");
        Response::success(['id' => $id, 'delivery_number' => $number], 'Livraison créée', 201);
    }

    public function updateStatus(int $id): void {
        $user   = AuthMiddleware::authenticate();
        $input  = json_decode(file_get_contents('php://input'), true) ?? [];
        $status = $input['status'] ?? '';

        if (!in_array($status, ['preparing','shipped','in_delivery','delivered','returned'])) {
            Response::error('Statut invalide', 422);
        }

        $update = ['status' => $status];
        if ($status === 'delivered') {
            $update['delivered_at'] = date('Y-m-d H:i:s');
            if (!empty($input['signature'])) $update['signature'] = $input['signature'];
        }

        Database::update('deliveries', $update, 'id = ?', [$id]);

        // Update order status
        $delivery = Database::fetch("SELECT order_id FROM deliveries WHERE id = ?", [$id]);
        if ($delivery && $status === 'delivered') {
            Database::update('orders', ['status' => 'completed'], 'id = ?', [$delivery['order_id']]);
        }

        Response::success(null, 'Statut mis à jour');
    }

    public function track(string $code): void {
        $delivery = Database::fetch(
            "SELECT d.*, o.order_number, o.total FROM deliveries d JOIN orders o ON o.id = d.order_id WHERE d.tracking_code = ?",
            [$code]
        );
        if (!$delivery) Response::notFound('Code de suivi invalide');

        Response::success([
            'tracking_code'   => $delivery['tracking_code'],
            'delivery_number' => $delivery['delivery_number'],
            'order_number'    => $delivery['order_number'],
            'status'          => $delivery['status'],
            'city'            => $delivery['city'],
            'scheduled_at'    => $delivery['scheduled_at'],
            'delivered_at'    => $delivery['delivered_at'],
        ]);
    }
}
