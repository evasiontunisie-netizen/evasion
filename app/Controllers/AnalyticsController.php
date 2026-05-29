<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Cache;
use App\Core\Controller;
use App\Core\Auth\AuthGuard;
use App\Core\Database;
use App\Core\Request;

final class AnalyticsController extends Controller
{
    public function dashboard(Request $request): void
    {
        if (!AuthGuard::can((int) ($request->user['sub'] ?? 0), ['analytics.view'])) {
            $this->error('Forbidden', 403);
            return;
        }

        $data = Cache::remember('dashboard:' . date('Y-m-d-H'), 300, static function (): array {
            $pdo = Database::pdo();

            return [
                'kpis' => [
                    'revenue_today' => (float) $pdo->query("SELECT COALESCE(SUM(grand_total),0) FROM orders WHERE DATE(created_at) = CURDATE()")->fetchColumn(),
                    'orders_month' => (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')")->fetchColumn(),
                    'open_tickets' => (int) $pdo->query("SELECT COUNT(*) FROM tickets WHERE status IN ('open','in_progress')")->fetchColumn(),
                    'low_stock' => (int) $pdo->query('SELECT COUNT(*) FROM stock s JOIN products p ON p.id = s.product_id WHERE s.quantity <= p.minimum_stock')->fetchColumn(),
                    'active_employees' => (int) $pdo->query("SELECT COUNT(*) FROM employees WHERE status = 'active'")->fetchColumn(),
                    'pending_deliveries' => (int) $pdo->query("SELECT COUNT(*) FROM deliveries WHERE status IN ('preparing','shipped','in_delivery')")->fetchColumn(),
                ],
                'sales_series' => $pdo->query("SELECT DATE(created_at) AS day, COALESCE(SUM(grand_total),0) AS revenue FROM orders WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) GROUP BY DATE(created_at) ORDER BY day")->fetchAll(),
                'sales_by_channel' => $pdo->query('SELECT channel, COALESCE(SUM(grand_total),0) AS revenue FROM orders GROUP BY channel ORDER BY revenue DESC')->fetchAll(),
                'top_products' => $pdo->query('SELECT name, SUM(quantity) AS units, SUM(total) AS revenue FROM order_items GROUP BY name ORDER BY revenue DESC LIMIT 10')->fetchAll(),
                'showroom_sales' => $pdo->query('SELECT w.name, COALESCE(SUM(o.grand_total),0) AS revenue FROM warehouses w LEFT JOIN orders o ON o.warehouse_id = w.id GROUP BY w.id, w.name ORDER BY revenue DESC')->fetchAll(),
                'product_categories' => $pdo->query('SELECT COALESCE(c.name, "Sans catégorie") AS name, COUNT(p.id) AS products FROM products p LEFT JOIN categories c ON c.id = p.category_id GROUP BY c.id, c.name ORDER BY products DESC LIMIT 12')->fetchAll(),
            ];
        });

        $this->ok($data);
    }

    public function accounting(Request $request): void
    {
        if (!AuthGuard::can((int) ($request->user['sub'] ?? 0), ['accounting.manage'])) {
            $this->error('Forbidden', 403);
            return;
        }

        $data = Cache::remember('accounting:' . date('Y-m-d-H-i'), 60, static function (): array {
            $pdo = Database::pdo();
            $revenue = (float) $pdo->query('SELECT COALESCE(SUM(grand_total),0) FROM invoices')->fetchColumn();
            $expenses = (float) $pdo->query('SELECT COALESCE(SUM(amount),0) FROM expenses')->fetchColumn();
            $tax = (float) $pdo->query('SELECT COALESCE(SUM(tax_total),0) FROM invoices')->fetchColumn();
            $paid = (float) $pdo->query("SELECT COALESCE(SUM(grand_total),0) FROM invoices WHERE status = 'paid'")->fetchColumn();
            $unpaid = (float) $pdo->query("SELECT COALESCE(SUM(grand_total),0) FROM invoices WHERE status IN ('draft','sent')")->fetchColumn();
            $monthly = $pdo->query("SELECT DATE_FORMAT(issue_date, '%Y-%m') AS month, COALESCE(SUM(grand_total),0) AS revenue, COALESCE(SUM(tax_total),0) AS tax FROM invoices GROUP BY DATE_FORMAT(issue_date, '%Y-%m') ORDER BY month DESC LIMIT 12")->fetchAll();
            $expenseCategories = $pdo->query('SELECT category, COALESCE(SUM(amount),0) AS amount FROM expenses GROUP BY category ORDER BY amount DESC LIMIT 10')->fetchAll();

            return [
            'revenue' => $revenue,
            'paid' => $paid,
            'unpaid' => $unpaid,
            'expenses' => $expenses,
            'profit' => $revenue - $expenses,
            'tax' => $tax,
            'margin_rate' => $revenue > 0 ? round((($revenue - $expenses) / $revenue) * 100, 2) : 0,
            'monthly' => $monthly,
            'expense_categories' => $expenseCategories,
            ];
        });

        $this->ok($data);
    }
}
