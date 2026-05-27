<?php
// ============================================================
// ERP PRO - Notifications Controller
// ============================================================

class NotificationController {

    public function index(): void {
        $user = AuthMiddleware::authenticate();

        $notifications = Database::fetchAll(
            "SELECT * FROM notifications WHERE user_id = ? OR user_id IS NULL ORDER BY created_at DESC LIMIT 50",
            [$user['id']]
        );
        $unread = Database::fetch(
            "SELECT COUNT(*) as c FROM notifications WHERE (user_id = ? OR user_id IS NULL) AND is_read = 0",
            [$user['id']]
        )['c'];

        Response::success(['notifications' => $notifications, 'unread' => $unread]);
    }

    public function markRead(int $id): void {
        $user = AuthMiddleware::authenticate();
        Database::update('notifications', ['is_read' => 1, 'read_at' => date('Y-m-d H:i:s')],
            'id = ? AND (user_id = ? OR user_id IS NULL)', [$id, $user['id']]);
        Response::success(null, 'Marqué comme lu');
    }

    public function markAllRead(): void {
        $user = AuthMiddleware::authenticate();
        Database::query(
            "UPDATE notifications SET is_read = 1, read_at = NOW() WHERE (user_id = ? OR user_id IS NULL) AND is_read = 0",
            [$user['id']]
        );
        Response::success(null, 'Tout marqué comme lu');
    }

    public function stream(): void {
        $user = AuthMiddleware::authenticate();

        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no');

        set_time_limit(60);
        $lastId = (int)($_GET['last_id'] ?? 0);

        while (true) {
            $new = Database::fetchAll(
                "SELECT * FROM notifications WHERE id > ? AND (user_id = ? OR user_id IS NULL) ORDER BY id ASC LIMIT 10",
                [$lastId, $user['id']]
            );

            foreach ($new as $n) {
                $lastId = $n['id'];
                echo "id: {$n['id']}\n";
                echo "event: notification\n";
                echo "data: " . json_encode($n) . "\n\n";
            }

            ob_flush();
            flush();

            if (connection_aborted()) break;
            sleep(5);
        }
        exit;
    }
}
