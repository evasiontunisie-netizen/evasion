<?php
// ============================================================
// ERP PRO - Database Connection (PDO Singleton)
// ============================================================

class Database {
    private static ?PDO $instance = null;

    public static function getInstance(): PDO {
        if (self::$instance === null) {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                DB_HOST, DB_PORT, DB_NAME, DB_CHARSET
            );
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::MYSQL_ATTR_FOUND_ROWS   => true,
                PDO::ATTR_PERSISTENT         => false,
            ];
            try {
                self::$instance = new PDO($dsn, DB_USER, DB_PASS, $options);
                self::$instance->exec("SET time_zone = '+01:00'");
            } catch (PDOException $e) {
                Logger::error('DB Connection failed: ' . $e->getMessage());
                http_response_code(503);
                die(json_encode(['error' => 'Service unavailable']));
            }
        }
        return self::$instance;
    }

    public static function query(string $sql, array $params = []): PDOStatement {
        $stmt = self::getInstance()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public static function fetch(string $sql, array $params = []): ?array {
        return self::query($sql, $params)->fetch() ?: null;
    }

    public static function fetchAll(string $sql, array $params = []): array {
        return self::query($sql, $params)->fetchAll();
    }

    public static function insert(string $table, array $data): int {
        $cols  = implode(',', array_map(fn($k) => "`$k`", array_keys($data)));
        $placeholders = implode(',', array_fill(0, count($data), '?'));
        self::query("INSERT INTO `$table` ($cols) VALUES ($placeholders)", array_values($data));
        return (int)self::getInstance()->lastInsertId();
    }

    public static function update(string $table, array $data, string $where, array $whereParams = []): int {
        $set = implode(',', array_map(fn($k) => "`$k` = ?", array_keys($data)));
        $stmt = self::query("UPDATE `$table` SET $set WHERE $where", [...array_values($data), ...$whereParams]);
        return $stmt->rowCount();
    }

    public static function beginTransaction(): void { self::getInstance()->beginTransaction(); }
    public static function commit(): void           { self::getInstance()->commit(); }
    public static function rollback(): void         { self::getInstance()->rollBack(); }

    public static function paginate(string $sql, array $params, int $page, int $perPage): array {
        $perPage = min($perPage, MAX_PAGE_SIZE);
        $page    = max(1, $page);
        $offset  = ($page - 1) * $perPage;

        $countSql = "SELECT COUNT(*) as total FROM ($sql) as count_query";
        $total    = (int)(self::fetch($countSql, $params)['total'] ?? 0);

        $data = self::fetchAll("$sql LIMIT $perPage OFFSET $offset", $params);

        return [
            'data'        => $data,
            'total'       => $total,
            'per_page'    => $perPage,
            'current_page'=> $page,
            'last_page'   => (int)ceil($total / $perPage),
            'from'        => $offset + 1,
            'to'          => min($offset + $perPage, $total),
        ];
    }
}
