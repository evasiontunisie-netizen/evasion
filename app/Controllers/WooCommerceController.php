<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Logger;
use App\Core\Request;

final class WooCommerceController extends Controller
{
    public function sync(Request $request): void
    {
        $siteId = (int) $request->params['id'];
        $type = (string) $request->input('type', 'all');
        $site = $this->site($siteId);
        if (!$site) {
            $this->error('WooCommerce site not found', 404);
            return;
        }

        // The connector is intentionally isolated: production deployments can replace
        // this dry-run with queue jobs that call /wp-json/wc/v3 resources per site.
        Database::pdo()->prepare('UPDATE woocommerce_sites SET last_sync_at = NOW() WHERE id = :id')->execute(['id' => $siteId]);
        Logger::activity((int) ($request->user['sub'] ?? 0), 'woocommerce.sync', ['site_id' => $siteId, 'type' => $type]);
        $this->ok(['site' => $site['name'], 'type' => $type, 'status' => 'queued']);
    }

    public function webhook(Request $request): void
    {
        $topic = $request->headers['X-WC-Webhook-Topic'] ?? $request->headers['x-wc-webhook-topic'] ?? 'unknown';
        Logger::info('woocommerce.webhook', ['topic' => $topic, 'payload' => $request->body]);
        $this->ok(['received' => true]);
    }

    private function site(int $id): ?array
    {
        $statement = Database::pdo()->prepare('SELECT * FROM woocommerce_sites WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $id]);
        $site = $statement->fetch();

        return $site ?: null;
    }
}
