<?php
// ============================================================
// ERP PRO - Logger Helper
// ============================================================

class Logger {
    private static array $levels = ['debug' => 0, 'info' => 1, 'warning' => 2, 'error' => 3];

    public static function log(string $level, string $message, array $context = []): void {
        if (!is_dir(LOG_PATH)) mkdir(LOG_PATH, 0755, true);

        $date    = date('Y-m-d');
        $time    = date('Y-m-d H:i:s');
        $file    = LOG_PATH . "/$date.log";
        $ctx     = $context ? ' ' . json_encode($context, JSON_UNESCAPED_UNICODE) : '';
        $entry   = "[$time] [$level] $message$ctx\n";

        file_put_contents($file, $entry, FILE_APPEND | LOCK_EX);
    }

    public static function info(string $msg, array $ctx = []): void    { self::log('INFO',    $msg, $ctx); }
    public static function error(string $msg, array $ctx = []): void   { self::log('ERROR',   $msg, $ctx); }
    public static function warning(string $msg, array $ctx = []): void { self::log('WARNING', $msg, $ctx); }
    public static function debug(string $msg, array $ctx = []): void   { if (APP_DEBUG) self::log('DEBUG', $msg, $ctx); }

    public static function activity(int $userId, string $action, string $module, string $desc, array $old = [], array $new = []): void {
        try {
            Database::insert('activity_logs', [
                'user_id'     => $userId,
                'action'      => $action,
                'module'      => $module,
                'description' => $desc,
                'ip_address'  => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent'  => $_SERVER['HTTP_USER_AGENT'] ?? null,
                'old_values'  => $old ? json_encode($old) : null,
                'new_values'  => $new ? json_encode($new) : null,
            ]);
        } catch (\Throwable $e) {
            self::error('Activity log failed: ' . $e->getMessage());
        }
    }
}
