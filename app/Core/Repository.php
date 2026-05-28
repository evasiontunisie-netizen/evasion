<?php

declare(strict_types=1);

namespace App\Core;

use PDO;

final class Repository
{
    public function __construct(
        private readonly string $table,
        private readonly array $fillable,
        private readonly array $searchable = [],
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

        $sql = "SELECT * FROM {$this->table} {$where} ORDER BY id DESC LIMIT :limit OFFSET :offset";
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
        $statement = Database::pdo()->prepare("SELECT * FROM {$this->table} WHERE id = :id LIMIT 1");
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
        $statement = Database::pdo()->prepare("SELECT * FROM {$this->table} {$where} ORDER BY id DESC LIMIT 5000");
        $statement->execute($params);

        return $statement->fetchAll();
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

        foreach (['status', 'warehouse_id', 'showroom_id', 'customer_id'] as $filter) {
            if (isset($query[$filter]) && $query[$filter] !== '') {
                $clauses[] = "{$filter} = :{$filter}";
                $params[':' . $filter] = $query[$filter];
            }
        }

        return [$clauses === [] ? '' : 'WHERE ' . implode(' AND ', $clauses), $params];
    }
}
