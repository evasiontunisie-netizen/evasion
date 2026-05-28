<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Export\Exporter;
use App\Core\Logger;
use App\Core\Realtime\WebSocketNotifier;
use App\Core\Repository;
use App\Core\Request;
use App\Core\Response;
use App\Support\ModuleRegistry;

final class ModuleController extends Controller
{
    public function index(Request $request): void
    {
        $repo = $this->repo($request->params['resource'] ?? '');
        $this->ok($repo->paginate($request->query));
    }

    public function show(Request $request): void
    {
        $repo = $this->repo($request->params['resource'] ?? '');
        $item = $repo->find((int) $request->params['id']);
        $item ? $this->ok(['item' => $item]) : $this->error('Not found', 404);
    }

    public function store(Request $request): void
    {
        $resource = $request->params['resource'] ?? '';
        $item = $this->repo($resource)->create($request->body);
        Logger::activity((int) ($request->user['sub'] ?? 0), $resource . '.created', ['id' => $item['id'] ?? null]);
        WebSocketNotifier::publish('erp', ['event' => $resource . '.created', 'item' => $item]);
        $this->ok(['item' => $item], 201);
    }

    public function update(Request $request): void
    {
        $resource = $request->params['resource'] ?? '';
        $item = $this->repo($resource)->update((int) $request->params['id'], $request->body);
        Logger::activity((int) ($request->user['sub'] ?? 0), $resource . '.updated', ['id' => $request->params['id']]);
        WebSocketNotifier::publish('erp', ['event' => $resource . '.updated', 'item' => $item]);
        $item ? $this->ok(['item' => $item]) : $this->error('Not found', 404);
    }

    public function destroy(Request $request): void
    {
        $resource = $request->params['resource'] ?? '';
        $this->repo($resource)->delete((int) $request->params['id']);
        Logger::activity((int) ($request->user['sub'] ?? 0), $resource . '.deleted', ['id' => $request->params['id']]);
        $this->ok(['deleted' => true]);
    }

    public function export(Request $request): void
    {
        $resource = $request->params['resource'] ?? '';
        $format = strtolower((string) ($request->query['format'] ?? 'csv'));
        $rows = $this->repo($resource)->exportRows($request->query);
        if ($format === 'pdf') {
            Response::download(Exporter::pdf(strtoupper($resource), $rows), $resource . '.pdf', 'application/pdf');
            return;
        }
        if ($format === 'xls' || $format === 'xlsx') {
            Response::download(Exporter::xls($rows), $resource . '.xls', 'application/vnd.ms-excel');
            return;
        }

        Response::download(Exporter::csv($rows), $resource . '.csv', 'text/csv; charset=utf-8');
    }

    public function importCsv(Request $request): void
    {
        $resource = $request->params['resource'] ?? '';
        if (!isset($_FILES['file']['tmp_name']) || !is_uploaded_file($_FILES['file']['tmp_name'])) {
            $this->error('CSV file is required');
            return;
        }

        $handle = fopen($_FILES['file']['tmp_name'], 'r');
        $headers = $handle ? fgetcsv($handle) : false;
        $count = 0;
        while ($handle && $headers && ($row = fgetcsv($handle)) !== false) {
            $this->repo($resource)->create(array_combine($headers, $row) ?: []);
            $count++;
        }
        if ($handle) {
            fclose($handle);
        }

        $this->ok(['imported' => $count]);
    }

    public function transferReceive(Request $request): void
    {
        $id = (int) $request->params['id'];
        Database::pdo()->prepare('UPDATE transfers SET status = "received", received_at = NOW(), signature_path = COALESCE(:signature_path, signature_path) WHERE id = :id')
            ->execute(['id' => $id, 'signature_path' => $request->input('signature_path')]);
        WebSocketNotifier::publish('transfers', ['event' => 'transfer.received', 'id' => $id]);
        $this->ok(['transfer' => $this->repo('transfers')->find($id)]);
    }

    public function posCheckout(Request $request): void
    {
        $items = (array) $request->input('items', []);
        $customerId = $request->input('customer_id');
        $warehouseId = (int) $request->input('warehouse_id', 1);
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $subtotal = 0.0;
            foreach ($items as $item) {
                $subtotal += ((float) ($item['price'] ?? 0)) * ((int) ($item['quantity'] ?? 1));
            }
            $discount = (float) $request->input('discount_total', 0);
            $tax = (float) $request->input('tax_total', 0);
            $grandTotal = max(0, $subtotal - $discount + $tax);
            $orderNumber = 'POS-' . date('Ymd-His') . '-' . random_int(100, 999);
            $order = $this->repo('orders')->create([
                'customer_id' => $customerId,
                'warehouse_id' => $warehouseId,
                'user_id' => $request->user['sub'] ?? null,
                'order_number' => $orderNumber,
                'channel' => 'pos',
                'status' => 'paid',
                'payment_status' => 'paid',
                'subtotal' => $subtotal,
                'discount_total' => $discount,
                'tax_total' => $tax,
                'grand_total' => $grandTotal,
                'currency' => 'TND',
            ]);
            $line = $pdo->prepare('INSERT INTO order_items (order_id, product_id, sku, name, quantity, unit_price, tax_rate, total) VALUES (:order_id, :product_id, :sku, :name, :quantity, :unit_price, :tax_rate, :total)');
            $stock = $pdo->prepare('UPDATE stock SET quantity = quantity - :quantity WHERE product_id = :product_id AND warehouse_id = :warehouse_id');
            foreach ($items as $item) {
                $qty = (int) ($item['quantity'] ?? 1);
                $price = (float) ($item['price'] ?? 0);
                $line->execute([
                    'order_id' => $order['id'],
                    'product_id' => $item['product_id'] ?? null,
                    'sku' => $item['sku'] ?? null,
                    'name' => $item['name'] ?? 'Produit',
                    'quantity' => $qty,
                    'unit_price' => $price,
                    'tax_rate' => $item['tax_rate'] ?? 0,
                    'total' => $qty * $price,
                ]);
                if (!empty($item['product_id'])) {
                    $stock->execute(['quantity' => $qty, 'product_id' => $item['product_id'], 'warehouse_id' => $warehouseId]);
                }
            }
            $pdo->commit();
            WebSocketNotifier::publish('orders', ['event' => 'pos.sale', 'order' => $order]);
            $this->ok(['order' => $order, 'receipt_url' => '/api/orders/' . $order['id'] . '/export?format=pdf'], 201);
        } catch (\Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }
    }

    private function repo(string $resource): Repository
    {
        $resources = ModuleRegistry::resources();
        if (!isset($resources[$resource])) {
            Response::json(['success' => false, 'error' => 'Unknown resource'], 404);
            exit;
        }
        $config = $resources[$resource];

        return new Repository($config['table'], $config['fields'], $config['search']);
    }
}
