<?php

declare(strict_types=1);

namespace App\Core;

use PDO;

final class Repository
{
    public function __construct(
        private string $table,
        private array $fillable,
        private array $searchable = [],
    ) {
    }

    public function paginate(array $query): array
    {
        $page = max(1, (int) ($query['page'] ?? 1));
        $perPage = min(100, max(5, (int) ($query['per_page'] ?? 20)));
        $offset = ($page - 1) * $perPage;
        [$where, $params] = $this->where($query);

        $count = Database::pdo()->prepare("SELECT COUNT(*) FROM {$this->table} {$where}");
        $count->execute($params);
        $total = (int) $count->fetchColumn();

        $sql = $this->selectSql($where) . ' ORDER BY ' . $this->orderBy($query) . ' LIMIT :limit OFFSET :offset';
        $statement = Database::pdo()->prepare($sql);
        foreach ($params as $key => $value) {
            $statement->bindValue($key, $value);
        }
        $statement->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $statement->bindValue(':offset', $offset, PDO::PARAM_INT);
        $statement->execute();

        return [
            'items' => $statement->fetchAll(),
            'meta' => ['page' => $page, 'per_page' => $perPage, 'total' => $total, 'pages' => (int) ceil($total / $perPage)],
        ];
    }

    public function find(int $id): ?array
    {
        $statement = Database::pdo()->prepare($this->selectSql('WHERE id = :id') . ' LIMIT 1');
        $statement->execute([':id' => $id]);
        $row = $statement->fetch();

        return $row ?: null;
    }

    public function create(array $data): array
    {
        $payload = $this->payload($data);
        $columns = array_keys($payload);
        $placeholders = array_map(static fn (string $column): string => ':' . $column, $columns);
        $sql = "INSERT INTO {$this->table} (" . implode(',', $columns) . ') VALUES (' . implode(',', $placeholders) . ')';
        $statement = Database::pdo()->prepare($sql);
        $statement->execute($payload);

        return $this->find((int) Database::pdo()->lastInsertId()) ?? [];
    }

    public function update(int $id, array $data): ?array
    {
        $payload = $this->payload($data);
        if ($payload === []) {
            return $this->find($id);
        }

        $sets = array_map(static fn (string $column): string => "{$column} = :{$column}", array_keys($payload));
        $payload['id'] = $id;
        $statement = Database::pdo()->prepare("UPDATE {$this->table} SET " . implode(', ', $sets) . ' WHERE id = :id');
        $statement->execute($payload);

        return $this->find($id);
    }

    public function delete(int $id): bool
    {
        $statement = Database::pdo()->prepare("DELETE FROM {$this->table} WHERE id = :id");
        return $statement->execute(['id' => $id]);
    }

    public function exportRows(array $query): array
    {
        [$where, $params] = $this->where($query);
        $statement = Database::pdo()->prepare($this->selectSql($where) . ' ORDER BY ' . $this->orderBy($query) . ' LIMIT 5000');
        $statement->execute($params);

        return $statement->fetchAll();
    }

    private function selectSql(string $where): string
    {
        if ($this->table === 'products') {
            return "SELECT products.*, (SELECT path FROM product_images WHERE product_id = products.id ORDER BY sort_order ASC, id ASC LIMIT 1) AS image_url FROM products {$where}";
        }

        if ($this->table === 'users') {
            return "SELECT id, role_id, name, email, avatar_path, two_factor_enabled, status, last_login_at, created_at, updated_at FROM users {$where}";
        }

        return "SELECT * FROM {$this->table} {$where}";
    }

    private function payload(array $data): array
    {
        $payload = [];
        foreach ($this->fillable as $field) {
            if (array_key_exists($field, $data)) {
                $payload[$field] = is_string($data[$field]) ? Security::cleanString($data[$field]) : $data[$field];
            }
        }

        return $payload;
    }

    private function where(array $query): array
    {
        $clauses = [];
        $params = [];
        if (!empty($query['q']) && $this->searchable !== []) {
            $parts = [];
            foreach ($this->searchable as $index => $column) {
                $key = ':q' . $index;
                $parts[] = "{$column} LIKE {$key}";
                $params[$key] = '%' . $query['q'] . '%';
            }
            $clauses[] = '(' . implode(' OR ', $parts) . ')';
        }

        foreach (['status', 'warehouse_id', 'showroom_id', 'customer_id', 'user_id', 'role_id', 'channel', 'payment_status'] as $filter) {
            if (isset($query[$filter]) && $query[$filter] !== '') {
                $clauses[] = "{$filter} = :{$filter}";
                $params[':' . $filter] = $query[$filter];
            }
        }

        $dateColumn = $this->dateColumn();
        if (!empty($query['date_from'])) {
            $clauses[] = "{$dateColumn} >= :date_from";
            $params[':date_from'] = $query['date_from'] . ' 00:00:00';
        }
        if (!empty($query['date_to'])) {
            $clauses[] = "{$dateColumn} <= :date_to";
            $params[':date_to'] = $query['date_to'] . ' 23:59:59';
        }

        return [$clauses === [] ? '' : 'WHERE ' . implode(' AND ', $clauses), $params];
    }

    private function orderBy(array $query): string
    {
        $allowed = array_merge(['id', 'created_at', 'updated_at', 'status'], $this->fillable, $this->searchable);
        $column = (string) ($query['sort_by'] ?? 'id');
        if (!in_array($column, array_unique($allowed), true)) {
            $column = 'id';
        }
        $direction = strtolower((string) ($query['sort_dir'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';

        return "{$column} {$direction}";
    }

    private function dateColumn(): string
    {
        return in_array($this->table, ['expenses', 'invoices'], true) ? ($this->table === 'expenses' ? 'expense_date' : 'issue_date') : 'created_at';
    }
}
