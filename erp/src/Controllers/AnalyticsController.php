<?php
// ============================================================
// ERP PRO - Analytics & Dashboard Controller
// ============================================================

class AnalyticsController {

    public function dashboard(): void {
        AuthMiddleware::authenticate();

        $today  = date('Y-m-d');
        $month  = date('m');
        $year   = date('Y');
        $prevMonth = date('m', strtotime('-1 month'));
        $prevYear  = date('Y', strtotime('-1 month'));

        // KPI Cards
        $kpis = [
            'revenue_today'   => (float)Database::fetch("SELECT COALESCE(SUM(total),0) as v FROM orders WHERE DATE(created_at) = ? AND status != 'cancelled'", [$today])['v'],
            'revenue_month'   => (float)Database::fetch("SELECT COALESCE(SUM(total),0) as v FROM orders WHERE MONTH(created_at) = ? AND YEAR(created_at) = ? AND status != 'cancelled'", [$month, $year])['v'],
            'orders_today'    => (int)Database::fetch("SELECT COUNT(*) as v FROM orders WHERE DATE(created_at) = ?", [$today])['v'],
            'orders_month'    => (int)Database::fetch("SELECT COUNT(*) as v FROM orders WHERE MONTH(created_at) = ? AND YEAR(created_at) = ?", [$month, $year])['v'],
            'new_customers'   => (int)Database::fetch("SELECT COUNT(*) as v FROM customers WHERE MONTH(created_at) = ? AND YEAR(created_at) = ?", [$month, $year])['v'],
            'open_tickets'    => (int)Database::fetch("SELECT COUNT(*) as v FROM tickets WHERE status IN ('open','in_progress')")['v'],
            'low_stock_items' => (int)Database::fetch("SELECT COUNT(*) as v FROM (SELECT p.id FROM products p LEFT JOIN stock s ON s.product_id = p.id GROUP BY p.id HAVING COALESCE(SUM(s.quantity),0) <= p.min_stock) as t")['v'],
            'pending_deliveries' => (int)Database::fetch("SELECT COUNT(*) as v FROM deliveries WHERE status IN ('preparing','shipped','in_delivery')")['v'],
            'active_employees'   => (int)Database::fetch("SELECT COUNT(*) as v FROM employees WHERE status = 'active'")['v'],
        ];

        // Revenue last 7 days
        $revenueDays = Database::fetchAll(
            "SELECT DATE(created_at) as date, COALESCE(SUM(total),0) as revenue, COUNT(*) as orders
             FROM orders WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) AND status != 'cancelled'
             GROUP BY DATE(created_at) ORDER BY date ASC"
        );

        // Revenue by warehouse
        $revenueByWarehouse = Database::fetchAll(
            "SELECT w.name, w.code, COALESCE(SUM(o.total),0) as revenue, COUNT(o.id) as orders
             FROM warehouses w LEFT JOIN orders o ON o.warehouse_id = w.id
             AND MONTH(o.created_at) = ? AND YEAR(o.created_at) = ? AND o.status != 'cancelled'
             GROUP BY w.id ORDER BY revenue DESC",
            [$month, $year]
        );

        // Top products
        $topProducts = Database::fetchAll(
            "SELECT p.name, p.sku, SUM(oi.quantity) as sold_qty, SUM(oi.total) as revenue
             FROM order_items oi JOIN products p ON p.id = oi.product_id
             JOIN orders o ON o.id = oi.order_id
             WHERE MONTH(o.created_at) = ? AND YEAR(o.created_at) = ? AND o.status != 'cancelled'
             GROUP BY p.id ORDER BY sold_qty DESC LIMIT 10",
            [$month, $year]
        );

        // Top customers
        $topCustomers = Database::fetchAll(
            "SELECT CONCAT(c.first_name,' ',c.last_name) as name, c.phone,
                    COUNT(o.id) as orders, SUM(o.total) as spent
             FROM customers c JOIN orders o ON o.customer_id = c.id
             WHERE MONTH(o.created_at) = ? AND YEAR(o.created_at) = ? AND o.status != 'cancelled'
             GROUP BY c.id ORDER BY spent DESC LIMIT 10",
            [$month, $year]
        );

        // Revenue by category
        $revenueByCategory = Database::fetchAll(
            "SELECT c.name, SUM(oi.total) as revenue
             FROM order_items oi JOIN products p ON p.id = oi.product_id
             JOIN categories c ON c.id = p.category_id
             JOIN orders o ON o.id = oi.order_id
             WHERE MONTH(o.created_at) = ? AND YEAR(o.created_at) = ? AND o.status != 'cancelled'
             GROUP BY c.id ORDER BY revenue DESC LIMIT 8",
            [$month, $year]
        );

        // Sales by payment method
        $paymentMethods = Database::fetchAll(
            "SELECT payment_method, COUNT(*) as count, SUM(total) as revenue
             FROM orders WHERE MONTH(created_at) = ? AND YEAR(created_at) = ? AND status != 'cancelled'
             GROUP BY payment_method",
            [$month, $year]
        );

        // Monthly comparison (12 months)
        $monthlyRevenue = Database::fetchAll(
            "SELECT YEAR(created_at) as year, MONTH(created_at) as month,
                    SUM(total) as revenue, COUNT(*) as orders
             FROM orders WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH) AND status != 'cancelled'
             GROUP BY YEAR(created_at), MONTH(created_at) ORDER BY year, month"
        );

        Response::success([
            'kpis'                => $kpis,
            'revenue_days'        => $revenueDays,
            'revenue_by_warehouse'=> $revenueByWarehouse,
            'top_products'        => $topProducts,
            'top_customers'       => $topCustomers,
            'revenue_by_category' => $revenueByCategory,
            'payment_methods'     => $paymentMethods,
            'monthly_revenue'     => $monthlyRevenue,
        ]);
    }

    public function salesReport(): void {
        AuthMiddleware::authenticate();

        $from = $_GET['from'] ?? date('Y-m-01');
        $to   = $_GET['to']   ?? date('Y-m-d');
        $warehouseId = $_GET['warehouse_id'] ?? null;

        $wFilter = $warehouseId ? " AND o.warehouse_id = $warehouseId" : '';

        $daily = Database::fetchAll(
            "SELECT DATE(o.created_at) as date, COUNT(o.id) as orders,
                    SUM(o.total) as revenue, SUM(o.discount_amount) as discounts,
                    SUM(o.tax_amount) as taxes, AVG(o.total) as avg_order
             FROM orders o
             WHERE DATE(o.created_at) BETWEEN ? AND ? AND o.status != 'cancelled'$wFilter
             GROUP BY DATE(o.created_at) ORDER BY date ASC",
            [$from, $to]
        );

        Response::success(['from' => $from, 'to' => $to, 'daily' => $daily]);
    }
}
