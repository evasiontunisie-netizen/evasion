<?php
// ============================================================
// ERP PRO - API Entry Point
// ============================================================

declare(strict_types=1);

// Load config
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

// Autoload helpers & middleware
foreach (['Helpers/JWT', 'Helpers/Logger', 'Helpers/Response', 'Helpers/Validator',
          'Middleware/AuthMiddleware', 'Middleware/RateLimitMiddleware'] as $f) {
    require_once __DIR__ . "/../src/$f.php";
}

// CORS
$origin = $_SERVER['HTTP_ORIGIN'] ?? '*';
header("Access-Control-Allow-Origin: $origin");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Authorization, Content-Type, X-Requested-With, X-CSRF-Token");
header("Access-Control-Max-Age: 86400");
header("Content-Type: application/json; charset=utf-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

// Rate limiting
RateLimitMiddleware::check();

// Parse URI
$uri    = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$uri    = preg_replace('#^/erp/api#', '', $uri);
$uri    = trim($uri, '/');
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$parts  = explode('/', $uri);

// Route helper
$routes = [];

function route(string $m, string $pattern, callable $handler): void {
    global $routes;
    $routes[] = ['method' => $m, 'pattern' => $pattern, 'handler' => $handler];
}

function dispatch(string $method, string $uri): void {
    global $routes;
    foreach ($routes as $r) {
        if ($r['method'] !== $method && $r['method'] !== 'ANY') continue;
        $rx = '#^' . preg_replace('#\{[^/]+\}#', '([^/]+)', $r['pattern']) . '$#';
        if (preg_match($rx, $uri, $matches)) {
            array_shift($matches);
            ($r['handler'])(...array_map(fn($v) => is_numeric($v) ? (int)$v : $v, $matches));
            return;
        }
    }
    Response::notFound('Endpoint introuvable');
}

// ============================================================
// Load Controllers
// ============================================================
$controllers = ['Auth','Product','Stock','Transfer','Order','Ticket','Employee','Customer','Delivery','Woo','Analytics','Notification'];
foreach ($controllers as $c) {
    require_once __DIR__ . "/../src/Controllers/{$c}Controller.php";
}
$auth     = new AuthController();
$products = new ProductController();
$stock    = new StockController();
$transfers= new TransferController();
$orders   = new OrderController();
$tickets  = new TicketController();
$employees= new EmployeeController();
$customers= new CustomerController();
$delivery = new DeliveryController();
$woo      = new WooController();
$analytics= new AnalyticsController();
$notif    = new NotificationController();

// ============================================================
// AUTH ROUTES
// ============================================================
route('POST', 'auth/login',           fn() => $auth->login());
route('POST', 'auth/refresh',         fn() => $auth->refresh());
route('POST', 'auth/logout',          fn() => $auth->logout());
route('GET',  'auth/me',              fn() => $auth->me());
route('POST', 'auth/forgot-password', fn() => $auth->forgotPassword());
route('POST', 'auth/reset-password',  fn() => $auth->resetPassword());
route('POST', 'auth/change-password', fn() => $auth->changePassword());

// ============================================================
// PRODUCTS
// ============================================================
route('GET',    'products',             fn() => $products->index());
route('POST',   'products',             fn() => $products->store());
route('GET',    'products/low-stock',   fn() => $products->lowStock());
route('GET',    'products/{id}',        fn($id) => $products->show($id));
route('PUT',    'products/{id}',        fn($id) => $products->update($id));
route('DELETE', 'products/{id}',        fn($id) => $products->destroy($id));
route('GET',    'products/{id}/stock',  fn($id) => $products->stockByWarehouse($id));

// ============================================================
// STOCK
// ============================================================
route('GET',  'stock',            fn() => $stock->index());
route('POST', 'stock/adjust',     fn() => $stock->adjust());
route('GET',  'stock/movements',  fn() => $stock->movements());
route('GET',  'stock/inventory',  fn() => $stock->inventory());

// ============================================================
// TRANSFERS
// ============================================================
route('GET',   'transfers',              fn() => $transfers->index());
route('POST',  'transfers',              fn() => $transfers->store());
route('GET',   'transfers/{id}',         fn($id) => $transfers->show($id));
route('PATCH', 'transfers/{id}/status',  fn($id) => $transfers->updateStatus($id));

// ============================================================
// ORDERS / POS
// ============================================================
route('GET',   'orders',              fn() => $orders->index());
route('POST',  'orders',              fn() => $orders->store());
route('GET',   'orders/stats',        fn() => $orders->stats());
route('GET',   'orders/{id}',         fn($id) => $orders->show($id));
route('PATCH', 'orders/{id}/status',  fn($id) => $orders->updateStatus($id));

// ============================================================
// TICKETS / SAV
// ============================================================
route('GET',   'tickets',                       fn() => $tickets->index());
route('POST',  'tickets',                       fn() => $tickets->store());
route('GET',   'tickets/stats',                 fn() => $tickets->stats());
route('GET',   'tickets/{id}',                  fn($id) => $tickets->show($id));
route('POST',  'tickets/{id}/messages',         fn($id) => $tickets->addMessage($id));
route('PATCH', 'tickets/{id}/status',           fn($id) => $tickets->updateStatus($id));

// ============================================================
// EMPLOYEES / HR
// ============================================================
route('GET',  'employees',                        fn() => $employees->index());
route('POST', 'employees',                        fn() => $employees->store());
route('GET',  'employees/attendance',             fn() => $employees->attendance());
route('POST', 'employees/attendance/clock',       fn() => $employees->clockIn());
route('GET',  'employees/salaries',               fn() => $employees->salaries());
route('GET',  'employees/{id}',                   fn($id) => $employees->show($id));
route('POST', 'employees/{id}/salary',            fn($id) => $employees->generateSalary($id));

// ============================================================
// CUSTOMERS / CRM
// ============================================================
route('GET',  'customers',            fn() => $customers->index());
route('POST', 'customers',            fn() => $customers->store());
route('GET',  'customers/{id}',       fn($id) => $customers->show($id));
route('PUT',  'customers/{id}',       fn($id) => $customers->update($id));
route('POST', 'customers/{id}/notes', fn($id) => $customers->addNote($id));

// ============================================================
// DELIVERIES
// ============================================================
route('GET',   'deliveries',               fn() => $delivery->index());
route('POST',  'deliveries',               fn() => $delivery->store());
route('PATCH', 'deliveries/{id}/status',   fn($id) => $delivery->updateStatus($id));
route('GET',   'deliveries/track/{code}',  fn($code) => $delivery->track($code));

// ============================================================
// WOOCOMMERCE
// ============================================================
route('GET',  'woo/sites',                    fn() => $woo->sites());
route('POST', 'woo/sites',                    fn() => $woo->addSite());
route('POST', 'woo/sites/{id}/sync-orders',   fn($id) => $woo->syncOrders($id));
route('POST', 'woo/sites/{id}/sync-stock',    fn($id) => $woo->syncStock($id));
route('POST', 'woo/webhook/{id}',             fn($id) => $woo->webhook($id));

// ============================================================
// ANALYTICS / DASHBOARD
// ============================================================
route('GET', 'analytics/dashboard',     fn() => $analytics->dashboard());
route('GET', 'analytics/sales-report',  fn() => $analytics->salesReport());

// ============================================================
// NOTIFICATIONS
// ============================================================
route('GET',  'notifications',          fn() => $notif->index());
route('PATCH','notifications/{id}/read',fn($id) => $notif->markRead($id));
route('POST', 'notifications/read-all', fn() => $notif->markAllRead());
route('GET',  'notifications/stream',   fn() => $notif->stream());

// ============================================================
// Dispatch
// ============================================================
try {
    dispatch($method, $uri);
} catch (\Throwable $e) {
    Logger::error('Unhandled exception: ' . $e->getMessage(), [
        'file' => $e->getFile(), 'line' => $e->getLine()
    ]);
    if (APP_DEBUG) {
        Response::error($e->getMessage(), 500, ['file' => $e->getFile(), 'line' => $e->getLine()]);
    } else {
        Response::error('Erreur interne du serveur', 500);
    }
}
