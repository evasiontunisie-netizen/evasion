<?php
// ============================================================
// ERP PRO - API Response Helper
// ============================================================

class Response {
    public static function json(mixed $data, int $code = 200): void {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function success(mixed $data = null, string $message = 'Success', int $code = 200): void {
        self::json(['success' => true, 'message' => $message, 'data' => $data], $code);
    }

    public static function error(string $message, int $code = 400, mixed $errors = null): void {
        $body = ['success' => false, 'message' => $message];
        if ($errors !== null) $body['errors'] = $errors;
        self::json($body, $code);
    }

    public static function paginated(array $paginatedData, string $message = 'Success'): void {
        self::json([
            'success' => true,
            'message' => $message,
            'data'    => $paginatedData['data'],
            'meta'    => [
                'total'        => $paginatedData['total'],
                'per_page'     => $paginatedData['per_page'],
                'current_page' => $paginatedData['current_page'],
                'last_page'    => $paginatedData['last_page'],
                'from'         => $paginatedData['from'],
                'to'           => $paginatedData['to'],
            ],
        ]);
    }

    public static function unauthorized(string $message = 'Unauthorized'): void {
        self::error($message, 401);
    }

    public static function forbidden(string $message = 'Forbidden'): void {
        self::error($message, 403);
    }

    public static function notFound(string $message = 'Not found'): void {
        self::error($message, 404);
    }
}
