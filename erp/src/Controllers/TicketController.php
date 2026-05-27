<?php
// ============================================================
// ERP PRO - Ticket / SAV Controller
// ============================================================

class TicketController {

    public function index(): void {
        AuthMiddleware::authenticate();
        $page     = (int)($_GET['page']    ?? 1);
        $perPage  = (int)($_GET['per_page']?? DEFAULT_PAGE_SIZE);
        $status   = $_GET['status']   ?? '';
        $priority = $_GET['priority'] ?? '';
        $category = $_GET['category'] ?? '';
        $search   = $_GET['search']   ?? '';

        $where  = ['1=1'];
        $params = [];
        if ($status)   { $where[] = 't.status = ?';   $params[] = $status; }
        if ($priority) { $where[] = 't.priority = ?'; $params[] = $priority; }
        if ($category) { $where[] = 't.category = ?'; $params[] = $category; }
        if ($search)   {
            $where[] = "(t.subject LIKE ? OR t.ticket_number LIKE ? OR CONCAT(c.first_name,' ',c.last_name) LIKE ?)";
            $like    = "%$search%";
            $params  = array_merge($params, [$like, $like, $like]);
        }

        $whereStr = implode(' AND ', $where);
        $sql = "SELECT t.*,
                       CONCAT(c.first_name,' ',c.last_name) as customer_name, c.phone as customer_phone,
                       CONCAT(a.first_name,' ',a.last_name) as assigned_name,
                       (SELECT COUNT(*) FROM ticket_messages WHERE ticket_id = t.id) as message_count
                FROM tickets t
                LEFT JOIN customers c ON c.id = t.customer_id
                LEFT JOIN users a ON a.id = t.assigned_to
                WHERE $whereStr ORDER BY 
                    FIELD(t.priority, 'urgent','high','medium','low'),
                    t.created_at DESC";

        Response::paginated(Database::paginate($sql, $params, $page, $perPage));
    }

    public function show(int $id): void {
        AuthMiddleware::authenticate();
        $ticket = Database::fetch(
            "SELECT t.*, CONCAT(c.first_name,' ',c.last_name) as customer_name, c.phone, c.email,
                    CONCAT(a.first_name,' ',a.last_name) as assigned_name
             FROM tickets t
             LEFT JOIN customers c ON c.id = t.customer_id
             LEFT JOIN users a ON a.id = t.assigned_to
             WHERE t.id = ?",
            [$id]
        );
        if (!$ticket) Response::notFound();

        $ticket['messages'] = Database::fetchAll(
            "SELECT tm.*, 
                    CONCAT(u.first_name,' ',u.last_name) as user_name, u.avatar as user_avatar,
                    CONCAT(c.first_name,' ',c.last_name) as customer_name
             FROM ticket_messages tm
             LEFT JOIN users u ON u.id = tm.user_id
             LEFT JOIN customers c ON c.id = tm.customer_id
             WHERE tm.ticket_id = ? ORDER BY tm.created_at ASC",
            [$id]
        );

        Response::success($ticket);
    }

    public function store(): void {
        $user = AuthMiddleware::authenticate();

        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $v = Validator::make($input, [
            'subject'  => 'required|string|max:255',
            'category' => 'required|in:sav,delivery,defective,refund,complaint,technical,other',
            'priority' => 'required|in:low,medium,high,urgent',
        ]);
        if ($v->fails()) Response::error('Données invalides', 422, $v->errors());

        $number = 'TKT-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);

        $id = Database::insert('tickets', [
            'ticket_number' => $number,
            'customer_id'   => !empty($input['customer_id']) ? (int)$input['customer_id'] : null,
            'order_id'      => !empty($input['order_id']) ? (int)$input['order_id'] : null,
            'assigned_to'   => !empty($input['assigned_to']) ? (int)$input['assigned_to'] : null,
            'created_by'    => $user['id'],
            'category'      => $input['category'],
            'priority'      => $input['priority'],
            'subject'       => htmlspecialchars($input['subject'], ENT_QUOTES, 'UTF-8'),
            'description'   => !empty($input['description']) ? htmlspecialchars($input['description'], ENT_QUOTES, 'UTF-8') : null,
        ]);

        if (!empty($input['description'])) {
            Database::insert('ticket_messages', [
                'ticket_id'  => $id,
                'user_id'    => $user['id'],
                'message'    => htmlspecialchars($input['description'], ENT_QUOTES, 'UTF-8'),
                'is_internal'=> 0,
            ]);
        }

        Logger::activity($user['id'], 'create', 'tickets', "Ticket créé: $number");
        Response::success(['id' => $id, 'ticket_number' => $number], 'Ticket créé', 201);
    }

    public function addMessage(int $id): void {
        $user   = AuthMiddleware::authenticate();
        $input  = json_decode(file_get_contents('php://input'), true) ?? [];
        $msg    = trim($input['message'] ?? '');
        if (!$msg) Response::error('Message requis', 422);

        $ticket = Database::fetch("SELECT id,status FROM tickets WHERE id = ?", [$id]);
        if (!$ticket) Response::notFound();

        Database::insert('ticket_messages', [
            'ticket_id'  => $id,
            'user_id'    => $user['id'],
            'message'    => htmlspecialchars($msg, ENT_QUOTES, 'UTF-8'),
            'is_internal'=> (int)($input['is_internal'] ?? 0),
        ]);

        if ($ticket['status'] === 'open') {
            Database::update('tickets', ['status' => 'in_progress'], 'id = ?', [$id]);
        }

        Response::success(null, 'Message ajouté');
    }

    public function updateStatus(int $id): void {
        $user   = AuthMiddleware::authenticate();
        $input  = json_decode(file_get_contents('php://input'), true) ?? [];
        $status = $input['status'] ?? '';

        if (!in_array($status, ['open','in_progress','resolved','closed'])) {
            Response::error('Statut invalide', 422);
        }

        $update = ['status' => $status];
        if ($status === 'resolved') $update['resolved_at'] = date('Y-m-d H:i:s');
        if ($status === 'closed')   $update['closed_at']   = date('Y-m-d H:i:s');
        if (!empty($input['assigned_to'])) $update['assigned_to'] = (int)$input['assigned_to'];

        Database::update('tickets', $update, 'id = ?', [$id]);
        Logger::activity($user['id'], 'update', 'tickets', "Ticket #$id → $status");

        Response::success(null, 'Ticket mis à jour');
    }

    public function stats(): void {
        AuthMiddleware::authenticate();
        Response::success([
            'open'        => Database::fetch("SELECT COUNT(*) as c FROM tickets WHERE status = 'open'")['c'],
            'in_progress' => Database::fetch("SELECT COUNT(*) as c FROM tickets WHERE status = 'in_progress'")['c'],
            'resolved'    => Database::fetch("SELECT COUNT(*) as c FROM tickets WHERE status = 'resolved'")['c'],
            'urgent'      => Database::fetch("SELECT COUNT(*) as c FROM tickets WHERE priority = 'urgent' AND status NOT IN ('resolved','closed')")['c'],
        ]);
    }
}
