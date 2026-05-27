<?php
// ============================================================
// ERP PRO - Customer / CRM Controller
// ============================================================

class CustomerController {

    public function index(): void {
        AuthMiddleware::authenticate();
        $page    = (int)($_GET['page']    ?? 1);
        $perPage = (int)($_GET['per_page']?? DEFAULT_PAGE_SIZE);
        $search  = $_GET['search'] ?? '';

        $where  = ['1=1'];
        $params = [];
        if ($search) {
            $where[] = "(c.first_name LIKE ? OR c.last_name LIKE ? OR c.phone LIKE ? OR c.email LIKE ?)";
            $like    = "%$search%";
            $params  = array_merge($params, [$like, $like, $like, $like]);
        }

        $whereStr = implode(' AND ', $where);
        $sql = "SELECT c.*,
                       (SELECT COUNT(*) FROM orders WHERE customer_id = c.id) as order_count,
                       (SELECT COUNT(*) FROM tickets WHERE customer_id = c.id AND status NOT IN ('resolved','closed')) as open_tickets
                FROM customers c WHERE $whereStr ORDER BY c.total_spent DESC, c.created_at DESC";

        Response::paginated(Database::paginate($sql, $params, $page, $perPage));
    }

    public function show(int $id): void {
        AuthMiddleware::authenticate();
        $customer = Database::fetch("SELECT * FROM customers WHERE id = ?", [$id]);
        if (!$customer) Response::notFound();

        $customer['orders'] = Database::fetchAll(
            "SELECT id, order_number, total, status, payment_method, created_at FROM orders WHERE customer_id = ? ORDER BY created_at DESC LIMIT 20",
            [$id]
        );
        $customer['tickets'] = Database::fetchAll(
            "SELECT id, ticket_number, subject, status, priority, created_at FROM tickets WHERE customer_id = ? ORDER BY created_at DESC LIMIT 10",
            [$id]
        );
        $customer['notes'] = Database::fetchAll(
            "SELECT cn.*, CONCAT(u.first_name,' ',u.last_name) as user_name FROM customer_notes cn LEFT JOIN users u ON u.id = cn.user_id WHERE cn.customer_id = ? ORDER BY cn.created_at DESC",
            [$id]
        );

        Response::success($customer);
    }

    public function store(): void {
        $user = AuthMiddleware::authenticate();
        $input = json_decode(file_get_contents('php://input'), true) ?? [];

        $v = Validator::make($input, [
            'first_name' => 'required|string|max:100',
            'last_name'  => 'required|string|max:100',
            'phone'      => 'nullable|string|max:30',
            'email'      => 'nullable|email',
        ]);
        if ($v->fails()) Response::error('Données invalides', 422, $v->errors());

        // Check duplicate phone
        if (!empty($input['phone'])) {
            $exists = Database::fetch("SELECT id FROM customers WHERE phone = ?", [$input['phone']]);
            if ($exists) Response::error('Un client avec ce numéro existe déjà', 409);
        }

        $id = Database::insert('customers', [
            'first_name' => htmlspecialchars($input['first_name'], ENT_QUOTES),
            'last_name'  => htmlspecialchars($input['last_name'],  ENT_QUOTES),
            'email'      => $input['email'] ?? null,
            'phone'      => $input['phone'] ?? null,
            'phone2'     => $input['phone2'] ?? null,
            'whatsapp'   => $input['whatsapp'] ?? null,
            'address'    => $input['address'] ?? null,
            'city'       => $input['city'] ?? null,
            'birthday'   => $input['birthday'] ?? null,
            'notes'      => $input['notes'] ?? null,
            'source'     => $input['source'] ?? 'manual',
        ]);

        Logger::activity($user['id'], 'create', 'customers', "Client créé: {$input['first_name']} {$input['last_name']}");
        Response::success(['id' => $id], 'Client créé', 201);
    }

    public function update(int $id): void {
        $user = AuthMiddleware::authenticate();
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $customer = Database::fetch("SELECT id FROM customers WHERE id = ?", [$id]);
        if (!$customer) Response::notFound();

        $allowed = ['first_name','last_name','email','phone','phone2','whatsapp','address','city','country','birthday','notes'];
        $data = array_intersect_key($input, array_flip($allowed));
        if (empty($data)) Response::error('Aucune donnée', 422);

        Database::update('customers', $data, 'id = ?', [$id]);
        Response::success(null, 'Client mis à jour');
    }

    public function addNote(int $id): void {
        $user  = AuthMiddleware::authenticate();
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $note  = trim($input['note'] ?? '');
        if (!$note) Response::error('Note requise', 422);

        Database::insert('customer_notes', [
            'customer_id' => $id,
            'user_id'     => $user['id'],
            'note'        => htmlspecialchars($note, ENT_QUOTES),
        ]);
        Response::success(null, 'Note ajoutée');
    }
}
