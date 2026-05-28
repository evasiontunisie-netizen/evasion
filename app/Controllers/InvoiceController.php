<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth\AuthGuard;
use App\Core\Database;
use App\Core\Pdf\DocumentPdf;
use App\Core\Request;
use App\Core\Response;

final class InvoiceController extends Controller
{
    public function pdf(Request $request): void
    {
        if (!AuthGuard::can((int) ($request->user['sub'] ?? 0), ['accounting.manage'])) {
            $this->error('Forbidden', 403);
            return;
        }

        $id = (int) $request->params['id'];
        $statement = Database::pdo()->prepare('SELECT i.*, c.name AS customer_name, c.email AS customer_email, c.phone AS customer_phone, c.address AS customer_address, o.order_number FROM invoices i LEFT JOIN customers c ON c.id = i.customer_id LEFT JOIN orders o ON o.id = i.order_id WHERE i.id = :id LIMIT 1');
        $statement->execute(['id' => $id]);
        $invoice = $statement->fetch();
        if (!$invoice) {
            $this->error('Invoice not found', 404);
            return;
        }

        $items = [];
        if (!empty($invoice['order_id'])) {
            $lines = Database::pdo()->prepare('SELECT name, quantity, unit_price, tax_rate, total FROM order_items WHERE order_id = :id');
            $lines->execute(['id' => $invoice['order_id']]);
            foreach ($lines->fetchAll() as $line) {
                $items[] = [
                    $line['name'],
                    'Qte: ' . $line['quantity'],
                    'PU: ' . number_format((float) $line['unit_price'], 3, ',', ' ') . ' TND',
                    'Total: ' . number_format((float) $line['total'], 3, ',', ' ') . ' TND',
                ];
            }
        }
        if ($items === []) {
            $items[] = ['Prestation / vente', 'Qte: 1', 'PU: ' . number_format((float) $invoice['subtotal'], 3, ',', ' ') . ' TND', 'Total: ' . number_format((float) $invoice['subtotal'], 3, ',', ' ') . ' TND'];
        }

        $pdf = DocumentPdf::make('Facture ' . $invoice['invoice_number'], [
            'Entreprise' => [
                'Evasion ERP',
                'Showrooms physiques + WooCommerce',
                'Document genere automatiquement',
            ],
            'Client' => [
                'Nom: ' . ($invoice['customer_name'] ?: 'Client comptoir'),
                'Email: ' . ($invoice['customer_email'] ?: '-'),
                'Telephone: ' . ($invoice['customer_phone'] ?: '-'),
                'Adresse: ' . ($invoice['customer_address'] ?: '-'),
            ],
            'Facture' => [
                'Numero: ' . $invoice['invoice_number'],
                'Commande: ' . ($invoice['order_number'] ?: '-'),
                'Date emission: ' . $invoice['issue_date'],
                'Date echeance: ' . ($invoice['due_date'] ?: '-'),
                'Statut: ' . $invoice['status'],
            ],
            'Articles' => $items,
            'Totaux' => [
                'Sous-total: ' . number_format((float) $invoice['subtotal'], 3, ',', ' ') . ' TND',
                'TVA: ' . number_format((float) $invoice['tax_total'], 3, ',', ' ') . ' TND',
                'Total TTC: ' . number_format((float) $invoice['grand_total'], 3, ',', ' ') . ' TND',
            ],
            'Conditions' => [
                'Merci pour votre confiance.',
                'Cette facture est generee par Evasion ERP.',
            ],
        ]);

        Response::download($pdf, 'facture-' . preg_replace('/[^a-zA-Z0-9_-]/', '-', (string) $invoice['invoice_number']) . '.pdf', 'application/pdf');
    }
}
