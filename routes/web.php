<?php

declare(strict_types=1);

use App\Controllers\AnalyticsController;
use App\Controllers\AiController;
use App\Controllers\AuthController;
use App\Controllers\InvoiceController;
use App\Controllers\ModuleController;
use App\Controllers\UploadController;
use App\Controllers\ViewController;
use App\Controllers\WooCommerceController;
use App\Core\Auth\AuthGuard;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Response;

$apiLimiter = static function (Request $request, callable $next): void {
    $key = ($_SERVER['REMOTE_ADDR'] ?? 'cli') . ':' . $request->path;
    if (!RateLimiter::check($key)) {
        Response::json(['success' => false, 'error' => 'Too many requests'], 429);
        return;
    }
    $next($request);
};

$auth = AuthGuard::api();

$router->get('/', [ViewController::class, 'app']);
$router->get('/api/health', static fn () => Response::json(['success' => true, 'data' => ['status' => 'ok', 'time' => date(DATE_ATOM)]]), [$apiLimiter]);

$router->post('/api/auth/login', [AuthController::class, 'login'], [$apiLimiter]);
$router->post('/api/auth/register-admin', [AuthController::class, 'registerAdmin'], [$apiLimiter]);
$router->post('/api/auth/forgot-password', [AuthController::class, 'forgotPassword'], [$apiLimiter]);
$router->post('/api/auth/reset-password', [AuthController::class, 'resetPassword'], [$apiLimiter]);
$router->get('/api/auth/me', [AuthController::class, 'me'], [$apiLimiter, $auth]);
$router->post('/api/auth/2fa/setup', [AuthController::class, 'twoFactorSetup'], [$apiLimiter, $auth]);
$router->post('/api/auth/2fa/confirm', [AuthController::class, 'twoFactorConfirm'], [$apiLimiter, $auth]);
$router->post('/api/auth/2fa/disable', [AuthController::class, 'twoFactorDisable'], [$apiLimiter, $auth]);

$router->get('/api/analytics/dashboard', [AnalyticsController::class, 'dashboard'], [$apiLimiter, $auth]);
$router->get('/api/analytics/accounting', [AnalyticsController::class, 'accounting'], [$apiLimiter, $auth]);
$router->get('/api/ai/insights', [AiController::class, 'insights'], [$apiLimiter, $auth]);
$router->post('/api/ai/ask', [AiController::class, 'ask'], [$apiLimiter, $auth]);

$router->post('/api/uploads', [UploadController::class, 'store'], [$apiLimiter, $auth]);
$router->post('/api/pos/checkout', [ModuleController::class, 'posCheckout'], [$apiLimiter, $auth]);
$router->get('/api/invoices/{id}/pdf', [InvoiceController::class, 'pdf'], [$apiLimiter, $auth]);
$router->post('/api/transfers/{id}/receive', [ModuleController::class, 'transferReceive'], [$apiLimiter, $auth]);
$router->post('/api/woocommerce-sites/{id}/sync', [WooCommerceController::class, 'sync'], [$apiLimiter, $auth]);
$router->post('/api/woocommerce/webhook', [WooCommerceController::class, 'webhook'], [$apiLimiter]);

$router->get('/api/{resource}', [ModuleController::class, 'index'], [$apiLimiter, $auth]);
$router->post('/api/{resource}', [ModuleController::class, 'store'], [$apiLimiter, $auth]);
$router->get('/api/{resource}/export', [ModuleController::class, 'export'], [$apiLimiter, $auth]);
$router->post('/api/{resource}/import', [ModuleController::class, 'importCsv'], [$apiLimiter, $auth]);
$router->get('/api/{resource}/{id}', [ModuleController::class, 'show'], [$apiLimiter, $auth]);
$router->put('/api/{resource}/{id}', [ModuleController::class, 'update'], [$apiLimiter, $auth]);
$router->patch('/api/{resource}/{id}', [ModuleController::class, 'update'], [$apiLimiter, $auth]);
$router->delete('/api/{resource}/{id}', [ModuleController::class, 'destroy'], [$apiLimiter, $auth]);
