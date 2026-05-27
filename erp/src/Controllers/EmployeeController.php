<?php
// ============================================================
// ERP PRO - Employee / HR Controller
// ============================================================

class EmployeeController {

    public function index(): void {
        AuthMiddleware::authenticate();
        $page    = (int)($_GET['page']    ?? 1);
        $perPage = (int)($_GET['per_page']?? DEFAULT_PAGE_SIZE);
        $search  = $_GET['search'] ?? '';
        $dept    = $_GET['department_id'] ?? '';
        $status  = $_GET['status'] ?? '';

        $where  = ['1=1'];
        $params = [];
        if ($search) {
            $where[] = "(e.first_name LIKE ? OR e.last_name LIKE ? OR e.employee_code LIKE ? OR e.phone LIKE ?)";
            $like    = "%$search%";
            $params  = array_merge($params, [$like, $like, $like, $like]);
        }
        if ($dept)   { $where[] = 'e.department_id = ?'; $params[] = $dept; }
        if ($status) { $where[] = 'e.status = ?';        $params[] = $status; }

        $whereStr = implode(' AND ', $where);
        $sql = "SELECT e.*, d.name as department_name, jp.title as position_title, w.name as warehouse_name
                FROM employees e
                LEFT JOIN departments d ON d.id = e.department_id
                LEFT JOIN job_positions jp ON jp.id = e.position_id
                LEFT JOIN warehouses w ON w.id = e.warehouse_id
                WHERE $whereStr ORDER BY e.first_name, e.last_name";

        Response::paginated(Database::paginate($sql, $params, $page, $perPage));
    }

    public function show(int $id): void {
        AuthMiddleware::authenticate();
        $emp = Database::fetch(
            "SELECT e.*, d.name as department_name, jp.title as position_title
             FROM employees e
             LEFT JOIN departments d ON d.id = e.department_id
             LEFT JOIN job_positions jp ON jp.id = e.position_id
             WHERE e.id = ?",
            [$id]
        );
        if (!$emp) Response::notFound();

        $emp['salaries']   = Database::fetchAll("SELECT * FROM salaries WHERE employee_id = ? ORDER BY year DESC, month DESC LIMIT 12", [$id]);
        $emp['leaves']     = Database::fetchAll("SELECT * FROM leaves WHERE employee_id = ? ORDER BY start_date DESC LIMIT 10", [$id]);
        $emp['attendance_summary'] = Database::fetch(
            "SELECT 
                COUNT(*) as total_days,
                SUM(status = 'present') as present,
                SUM(status = 'absent') as absent,
                SUM(status = 'late') as late,
                SUM(late_minutes) as total_late_minutes
             FROM attendance WHERE employee_id = ? AND YEAR(date) = YEAR(NOW()) AND MONTH(date) = MONTH(NOW())",
            [$id]
        );

        Response::success($emp);
    }

    public function store(): void {
        $user = AuthMiddleware::authenticate();
        AuthMiddleware::requireRole(['super_admin','admin','hr']);

        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $v = Validator::make($input, [
            'first_name'    => 'required|string|max:100',
            'last_name'     => 'required|string|max:100',
            'hire_date'     => 'required',
            'base_salary'   => 'nullable|numeric',
            'department_id' => 'nullable|integer',
            'position_id'   => 'nullable|integer',
        ]);
        if ($v->fails()) Response::error('Données invalides', 422, $v->errors());

        $code = 'EMP-' . strtoupper(substr(md5(uniqid()), 0, 6));
        $id = Database::insert('employees', [
            'employee_code' => $code,
            'first_name'    => htmlspecialchars($input['first_name'], ENT_QUOTES),
            'last_name'     => htmlspecialchars($input['last_name'],  ENT_QUOTES),
            'email'         => $input['email'] ?? null,
            'phone'         => $input['phone'] ?? null,
            'cin'           => $input['cin'] ?? null,
            'birthday'      => $input['birthday'] ?? null,
            'hire_date'     => $input['hire_date'],
            'department_id' => !empty($input['department_id']) ? (int)$input['department_id'] : null,
            'position_id'   => !empty($input['position_id']) ? (int)$input['position_id'] : null,
            'warehouse_id'  => !empty($input['warehouse_id']) ? (int)$input['warehouse_id'] : null,
            'contract_type' => $input['contract_type'] ?? 'cdi',
            'base_salary'   => (float)($input['base_salary'] ?? 0),
            'address'       => $input['address'] ?? null,
        ]);

        Logger::activity($user['id'], 'create', 'employees', "Employé créé: {$input['first_name']} {$input['last_name']}");
        Response::success(['id' => $id, 'employee_code' => $code], 'Employé créé', 201);
    }

    public function attendance(): void {
        AuthMiddleware::authenticate();
        $empId = $_GET['employee_id'] ?? null;
        $month = $_GET['month'] ?? date('m');
        $year  = $_GET['year']  ?? date('Y');

        $where  = ['MONTH(a.date) = ? AND YEAR(a.date) = ?'];
        $params = [$month, $year];
        if ($empId) { $where[] = 'a.employee_id = ?'; $params[] = $empId; }

        $whereStr = implode(' AND ', $where);
        $rows = Database::fetchAll(
            "SELECT a.*, e.first_name, e.last_name, e.employee_code
             FROM attendance a JOIN employees e ON e.id = a.employee_id
             WHERE $whereStr ORDER BY a.date DESC, e.first_name",
            $params
        );
        Response::success($rows);
    }

    public function clockIn(): void {
        $user = AuthMiddleware::authenticate();
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $empId = (int)($input['employee_id'] ?? 0);
        if (!$empId) Response::error('employee_id requis', 422);

        $today = date('Y-m-d');
        $existing = Database::fetch("SELECT * FROM attendance WHERE employee_id = ? AND date = ?", [$empId, $today]);

        if ($existing) {
            if ($existing['check_out']) Response::error('Déjà pointé aujourd\'hui', 409);
            Database::update('attendance', ['check_out' => date('H:i:s')], 'id = ?', [$existing['id']]);
            Response::success(null, 'Départ enregistré');
        } else {
            $checkIn = date('H:i:s');
            $late    = 0;
            $startHour = strtotime($today . ' 09:00:00');
            if (time() > $startHour) $late = (int)floor((time() - $startHour) / 60);

            Database::insert('attendance', [
                'employee_id'  => $empId,
                'date'         => $today,
                'check_in'     => $checkIn,
                'status'       => $late > 15 ? 'late' : 'present',
                'late_minutes' => $late,
            ]);
            Response::success(['check_in' => $checkIn, 'late_minutes' => $late], 'Arrivée enregistrée');
        }
    }

    public function salaries(): void {
        AuthMiddleware::authenticate();
        $page   = (int)($_GET['page']    ?? 1);
        $perPage= (int)($_GET['per_page']?? DEFAULT_PAGE_SIZE);
        $month  = $_GET['month'] ?? date('m');
        $year   = $_GET['year']  ?? date('Y');

        $sql = "SELECT s.*, e.first_name, e.last_name, e.employee_code, d.name as department_name
                FROM salaries s
                JOIN employees e ON e.id = s.employee_id
                LEFT JOIN departments d ON d.id = e.department_id
                WHERE s.month = ? AND s.year = ?
                ORDER BY e.first_name";

        Response::paginated(Database::paginate($sql, [$month, $year], $page, $perPage));
    }

    public function generateSalary(int $empId): void {
        $user = AuthMiddleware::authenticate();
        AuthMiddleware::requireRole(['super_admin','admin','hr','accountant']);

        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $month = (int)($input['month'] ?? date('m'));
        $year  = (int)($input['year']  ?? date('Y'));

        $emp = Database::fetch("SELECT * FROM employees WHERE id = ?", [$empId]);
        if (!$emp) Response::notFound('Employé introuvable');

        $existing = Database::fetch("SELECT id FROM salaries WHERE employee_id = ? AND month = ? AND year = ?", [$empId, $month, $year]);
        if ($existing) Response::error('Fiche de paie déjà générée', 409);

        $bonuses    = (float)($input['bonuses']    ?? 0);
        $commissions= (float)($input['commissions']?? 0);
        $advances   = (float)($input['advances']   ?? 0);
        $deductions = (float)($input['deductions'] ?? 0);

        $attendance = Database::fetch(
            "SELECT SUM(overtime_minutes) as ot FROM attendance WHERE employee_id = ? AND MONTH(date) = ? AND YEAR(date) = ?",
            [$empId, $month, $year]
        );
        $overtimeAmt = (float)($attendance['ot'] ?? 0) / 60 * ($emp['base_salary'] / 26 / 8) * 1.25;
        $net = $emp['base_salary'] + $overtimeAmt + $bonuses + $commissions - $advances - $deductions;

        $id = Database::insert('salaries', [
            'employee_id'     => $empId,
            'month'           => $month,
            'year'            => $year,
            'base_salary'     => $emp['base_salary'],
            'overtime_amount' => $overtimeAmt,
            'bonuses'         => $bonuses,
            'commissions'     => $commissions,
            'advances'        => $advances,
            'deductions'      => $deductions,
            'net_salary'      => $net,
            'notes'           => $input['notes'] ?? null,
        ]);

        Response::success(['id' => $id, 'net_salary' => $net], 'Fiche de paie générée', 201);
    }
}
