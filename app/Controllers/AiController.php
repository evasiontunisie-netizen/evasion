<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;

final class AiController extends Controller
{
    public function insights(Request $request): void
    {
        $pdo = Database::pdo();
        $lowStock = (int) $pdo->query('SELECT COUNT(*) FROM stock s JOIN products p ON p.id = s.product_id WHERE s.quantity <= p.minimum_stock')->fetchColumn();
        $openTickets = (int) $pdo->query("SELECT COUNT(*) FROM tickets WHERE status IN ('open','in_progress')")->fetchColumn();
        $revenue = (float) $pdo->query("SELECT COALESCE(SUM(grand_total),0) FROM orders WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn();
        $expenses = (float) $pdo->query("SELECT COALESCE(SUM(amount),0) FROM expenses WHERE expense_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)")->fetchColumn();
        $topProduct = $pdo->query('SELECT name, SUM(quantity) AS units FROM order_items GROUP BY name ORDER BY units DESC LIMIT 1')->fetch();

        $actions = [];
        if ($lowStock > 0) {
            $actions[] = "Reapprovisionner {$lowStock} produit(s) sous seuil minimum.";
        }
        if ($openTickets > 0) {
            $actions[] = "Prioriser {$openTickets} ticket(s) SAV ouverts avant fin de journee.";
        }
        if ($expenses > 0 && $revenue > 0 && ($expenses / max($revenue, 1)) > 0.45) {
            $actions[] = 'Verifier les depenses: elles depassent 45% du CA recent.';
        }
        if ($topProduct) {
            $actions[] = 'Mettre en avant le top produit: ' . $topProduct['name'] . ' (' . $topProduct['units'] . ' ventes).';
        }
        if ($actions === []) {
            $actions[] = 'Operation stable: continuer le suivi des ventes, stocks et tickets.';
        }

        $this->ok([
            'score' => max(42, min(98, 92 - ($lowStock * 2) - $openTickets)),
            'summary' => 'Assistant IA ERP: analyse rapide des ventes, stocks, SAV et comptabilite.',
            'actions' => array_slice($actions, 0, 5),
            'metrics' => [
                'low_stock' => $lowStock,
                'open_tickets' => $openTickets,
                'revenue_30d' => $revenue,
                'expenses_30d' => $expenses,
            ],
        ]);
    }

    public function ask(Request $request): void
    {
        $question = strtolower((string) $request->input('question', ''));
        $answer = 'Je recommande de commencer par dashboard, stock faible, tickets urgents et ventes du jour.';
        if (str_contains($question, 'stock')) {
            $answer = 'Action stock: filtre les produits sous minimum, cree un transfert depot -> showroom, puis verifie les reservations web.';
        } elseif (str_contains($question, 'vente') || str_contains($question, 'ca')) {
            $answer = 'Action ventes: compare les canaux POS/WooCommerce, pousse les top produits et controle les remises.';
        } elseif (str_contains($question, 'ticket') || str_contains($question, 'sav')) {
            $answer = 'Action SAV: traite urgent/haute priorite, assigne un responsable et ajoute une note client claire.';
        } elseif (str_contains($question, 'facture') || str_contains($question, 'compta')) {
            $answer = 'Action compta: genere les factures PDF, controle TVA, depenses et marge avant cloture.';
        }

        $this->ok(['answer' => $answer]);
    }
}
