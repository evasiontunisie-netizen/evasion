<?php

declare(strict_types=1);

namespace App\Support;

final class ModuleRegistry
{
    public static function resources(): array
    {
        return [
            'products' => ['table' => 'products', 'search' => ['sku', 'name', 'barcode'], 'fields' => ['category_id', 'brand_id', 'supplier_id', 'sku', 'barcode', 'qr_code', 'name', 'description', 'purchase_price', 'sale_price', 'promo_price', 'tax_rate', 'minimum_stock', 'status']],
            'product-variants' => ['table' => 'product_variants', 'search' => ['sku', 'barcode', 'size', 'color'], 'fields' => ['product_id', 'sku', 'barcode', 'size', 'color', 'attributes', 'price_delta', 'status']],
            'product-images' => ['table' => 'product_images', 'search' => ['path', 'alt_text'], 'fields' => ['product_id', 'path', 'alt_text', 'sort_order']],
            'categories' => ['table' => 'categories', 'search' => ['name', 'slug'], 'fields' => ['parent_id', 'name', 'slug', 'description', 'status']],
            'brands' => ['table' => 'brands', 'search' => ['name'], 'fields' => ['name', 'description', 'status']],
            'suppliers' => ['table' => 'suppliers', 'search' => ['name', 'email', 'phone'], 'fields' => ['name', 'email', 'phone', 'address', 'status']],
            'warehouses' => ['table' => 'warehouses', 'search' => ['name', 'code', 'type'], 'fields' => ['name', 'code', 'type', 'address', 'city', 'phone', 'status']],
            'stock' => ['table' => 'stock', 'search' => ['sku_snapshot'], 'fields' => ['product_id', 'warehouse_id', 'quantity', 'reserved_quantity', 'sku_snapshot']],
            'stock-movements' => ['table' => 'stock_movements', 'search' => ['type', 'reference'], 'fields' => ['product_id', 'warehouse_id', 'user_id', 'type', 'quantity', 'reference', 'notes']],
            'transfers' => ['table' => 'transfers', 'search' => ['reference', 'status'], 'fields' => ['reference', 'from_warehouse_id', 'to_warehouse_id', 'requested_by', 'validated_by', 'status', 'notes', 'signature_path']],
            'transfer-items' => ['table' => 'transfer_items', 'search' => ['scanned_code'], 'fields' => ['transfer_id', 'product_id', 'quantity', 'scanned_code']],
            'tickets' => ['table' => 'tickets', 'search' => ['subject', 'priority', 'status', 'category'], 'fields' => ['customer_id', 'assigned_to', 'subject', 'category', 'priority', 'status', 'description']],
            'ticket-messages' => ['table' => 'ticket_messages', 'search' => ['message'], 'fields' => ['ticket_id', 'user_id', 'customer_id', 'message', 'attachment_path', 'internal']],
            'departments' => ['table' => 'departments', 'search' => ['name'], 'fields' => ['name']],
            'employees' => ['table' => 'employees', 'search' => ['employee_code', 'first_name', 'last_name', 'email'], 'fields' => ['user_id', 'department_id', 'employee_code', 'first_name', 'last_name', 'email', 'phone', 'position', 'contract_type', 'hire_date', 'salary_base', 'status']],
            'employee-documents' => ['table' => 'employee_documents', 'search' => ['label', 'path'], 'fields' => ['employee_id', 'label', 'path']],
            'attendance' => ['table' => 'attendance', 'search' => ['status'], 'fields' => ['employee_id', 'work_date', 'clock_in', 'clock_out', 'status', 'notes']],
            'leaves' => ['table' => 'leaves', 'search' => ['type', 'status'], 'fields' => ['employee_id', 'starts_at', 'ends_at', 'type', 'status']],
            'salaries' => ['table' => 'salaries', 'search' => ['period', 'status'], 'fields' => ['employee_id', 'period', 'base_salary', 'bonuses', 'commissions', 'advances', 'deductions', 'net_salary', 'status']],
            'deliveries' => ['table' => 'deliveries', 'search' => ['tracking_number', 'status', 'zone'], 'fields' => ['order_id', 'driver_id', 'tracking_number', 'zone', 'delivery_fee', 'status', 'signature_path', 'delivered_at']],
            'orders' => ['table' => 'orders', 'search' => ['order_number', 'channel', 'status'], 'fields' => ['customer_id', 'warehouse_id', 'user_id', 'order_number', 'channel', 'source_site_id', 'status', 'payment_status', 'subtotal', 'discount_total', 'tax_total', 'shipping_total', 'grand_total', 'currency']],
            'order-items' => ['table' => 'order_items', 'search' => ['sku', 'name'], 'fields' => ['order_id', 'product_id', 'sku', 'name', 'quantity', 'unit_price', 'tax_rate', 'total']],
            'payments' => ['table' => 'payments', 'search' => ['method', 'reference'], 'fields' => ['order_id', 'method', 'amount', 'reference']],
            'customers' => ['table' => 'customers', 'search' => ['name', 'email', 'phone'], 'fields' => ['name', 'email', 'phone', 'whatsapp', 'address', 'city', 'loyalty_points', 'internal_notes', 'status']],
            'invoices' => ['table' => 'invoices', 'search' => ['invoice_number', 'status'], 'fields' => ['order_id', 'customer_id', 'invoice_number', 'issue_date', 'due_date', 'subtotal', 'tax_total', 'grand_total', 'status']],
            'expenses' => ['table' => 'expenses', 'search' => ['label', 'category'], 'fields' => ['label', 'category', 'amount', 'tax_amount', 'expense_date', 'payment_method', 'notes']],
            'notifications' => ['table' => 'notifications', 'search' => ['title', 'channel', 'status'], 'fields' => ['user_id', 'title', 'body', 'channel', 'status', 'payload']],
            'woocommerce-sites' => ['table' => 'woocommerce_sites', 'search' => ['name', 'url'], 'fields' => ['name', 'url', 'consumer_key', 'consumer_secret', 'status', 'last_sync_at']],
            'marketing-campaigns' => ['table' => 'marketing_campaigns', 'search' => ['name', 'channel'], 'fields' => ['name', 'channel', 'budget', 'revenue', 'starts_at', 'ends_at', 'status']],
        ];
    }

    public static function navigation(): array
    {
        return [
            ['key' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'grid'],
            ['key' => 'products', 'label' => 'Produits', 'icon' => 'box'],
            ['key' => 'stock', 'label' => 'Stocks', 'icon' => 'layers'],
            ['key' => 'transfers', 'label' => 'Transferts', 'icon' => 'shuffle'],
            ['key' => 'pos', 'label' => 'POS Caisse', 'icon' => 'terminal'],
            ['key' => 'tickets', 'label' => 'Tickets SAV', 'icon' => 'message'],
            ['key' => 'employees', 'label' => 'RH', 'icon' => 'users'],
            ['key' => 'deliveries', 'label' => 'Livraison', 'icon' => 'truck'],
            ['key' => 'woocommerce-sites', 'label' => 'WooCommerce', 'icon' => 'globe'],
            ['key' => 'customers', 'label' => 'CRM', 'icon' => 'heart'],
            ['key' => 'marketing-campaigns', 'label' => 'Marketing', 'icon' => 'chart'],
            ['key' => 'invoices', 'label' => 'Comptabilité', 'icon' => 'receipt'],
            ['key' => 'notifications', 'label' => 'Notifications', 'icon' => 'bell'],
        ];
    }
}
