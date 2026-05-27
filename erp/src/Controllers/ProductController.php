<?php
// ============================================================
// ERP PRO - Product Controller
// ============================================================

class ProductController {

    public function index(): void {
        AuthMiddleware::authenticate();

        $page    = (int)($_GET['page']    ?? 1);
        $perPage = (int)($_GET['per_page']?? DEFAULT_PAGE_SIZE);
        $search  = $_GET['search']   ?? '';
        $cat     = $_GET['category'] ?? '';
        $brand   = $_GET['brand']    ?? '';
        $active  = $_GET['active']   ?? '';
        $sort    = in_array($_GET['sort'] ?? '', ['name','sale_price','created_at','sku']) ? $_GET['sort'] : 'created_at';
        $dir     = ($_GET['dir'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';

        $where  = ['1=1'];
        $params = [];

        if ($search) {
            $where[]  = "(p.name LIKE ? OR p.sku LIKE ? OR p.barcode LIKE ?)";
            $like     = "%$search%";
            $params   = array_merge($params, [$like, $like, $like]);
        }
        if ($cat)    { $where[] = 'p.category_id = ?'; $params[] = $cat; }
        if ($brand)  { $where[] = 'p.brand_id = ?';    $params[] = $brand; }
        if ($active !== '') { $where[] = 'p.is_active = ?'; $params[] = (int)$active; }

        $whereStr = implode(' AND ', $where);
        $sql = "SELECT p.*, c.name as category_name, b.name as brand_name,
                       (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as primary_image,
                       COALESCE(SUM(s.quantity), 0) as total_stock
                FROM products p
                LEFT JOIN categories c ON c.id = p.category_id
                LEFT JOIN brands b ON b.id = p.brand_id
                LEFT JOIN stock s ON s.product_id = p.id
                WHERE $whereStr
                GROUP BY p.id
                ORDER BY p.$sort $dir";

        Response::paginated(Database::paginate($sql, $params, $page, $perPage));
    }

    public function show(int $id): void {
        AuthMiddleware::authenticate();

        $product = Database::fetch(
            "SELECT p.*, c.name as category_name, b.name as brand_name, s.name as supplier_name
             FROM products p
             LEFT JOIN categories c ON c.id = p.category_id
             LEFT JOIN brands b ON b.id = p.brand_id
             LEFT JOIN suppliers s ON s.id = p.supplier_id
             WHERE p.id = ?",
            [$id]
        );
        if (!$product) Response::notFound('Produit introuvable');

        $product['images']   = Database::fetchAll("SELECT * FROM product_images WHERE product_id = ? ORDER BY is_primary DESC, sort_order ASC", [$id]);
        $product['variants'] = $this->getVariants($id);
        $product['stock']    = Database::fetchAll(
            "SELECT s.*, w.name as warehouse_name, w.code as warehouse_code
             FROM stock s JOIN warehouses w ON w.id = s.warehouse_id WHERE s.product_id = ?",
            [$id]
        );

        Response::success($product);
    }

    public function store(): void {
        $user = AuthMiddleware::authenticate();
        AuthMiddleware::requirePermission('products.create');

        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $v = Validator::make($input, [
            'name'        => 'required|string|max:255',
            'sale_price'  => 'required|numeric',
            'category_id' => 'nullable|integer',
            'brand_id'    => 'nullable|integer',
            'supplier_id' => 'nullable|integer',
            'sku'         => 'nullable|string|max:100',
            'barcode'     => 'nullable|string|max:100',
        ]);
        if ($v->fails()) Response::error('Données invalides', 422, $v->errors());

        $data = $v->validated();
        $data['slug'] = $this->generateSlug($data['name']);
        if (empty($data['sku'])) $data['sku'] = strtoupper(substr(md5($data['name'] . time()), 0, 10));

        $id = Database::insert('products', array_filter($data, fn($v) => $v !== null));
        Logger::activity($user['id'], 'create', 'products', "Produit créé: {$data['name']}");

        Response::success(['id' => $id], 'Produit créé', 201);
    }

    public function update(int $id): void {
        $user = AuthMiddleware::authenticate();
        AuthMiddleware::requirePermission('products.update');

        $product = Database::fetch("SELECT id,name FROM products WHERE id = ?", [$id]);
        if (!$product) Response::notFound('Produit introuvable');

        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $allowed = ['name','sku','barcode','description','short_description','purchase_price',
                    'sale_price','promo_price','promo_start','promo_end','tax_rate','weight',
                    'unit','min_stock','is_active','is_featured','category_id','brand_id',
                    'supplier_id','meta_title','meta_description'];
        $data = array_intersect_key($input, array_flip($allowed));

        if (empty($data)) Response::error('Aucune donnée à mettre à jour', 422);
        if (isset($data['name'])) $data['slug'] = $this->generateSlug($data['name'], $id);

        $old = Database::fetch("SELECT * FROM products WHERE id = ?", [$id]);
        Database::update('products', $data, 'id = ?', [$id]);
        Logger::activity($user['id'], 'update', 'products', "Produit modifié: {$product['name']}", $old, $data);

        Response::success(null, 'Produit mis à jour');
    }

    public function destroy(int $id): void {
        $user = AuthMiddleware::authenticate();
        AuthMiddleware::requirePermission('products.delete');

        $product = Database::fetch("SELECT id,name FROM products WHERE id = ?", [$id]);
        if (!$product) Response::notFound('Produit introuvable');

        Database::query("DELETE FROM products WHERE id = ?", [$id]);
        Logger::activity($user['id'], 'delete', 'products', "Produit supprimé: {$product['name']}");

        Response::success(null, 'Produit supprimé');
    }

    public function stockByWarehouse(int $id): void {
        AuthMiddleware::authenticate();
        $stock = Database::fetchAll(
            "SELECT s.*, w.name as warehouse_name, w.code, w.type
             FROM stock s JOIN warehouses w ON w.id = s.warehouse_id
             WHERE s.product_id = ? ORDER BY w.name",
            [$id]
        );
        Response::success($stock);
    }

    public function importExcel(): void {
        $user = AuthMiddleware::authenticate();
        AuthMiddleware::requirePermission('products.import');
        // Handled by frontend uploader + separate import service
        Response::success(null, 'Import initié');
    }

    public function lowStock(): void {
        AuthMiddleware::authenticate();
        $warehouseId = $_GET['warehouse_id'] ?? null;
        $sql = "SELECT p.id, p.name, p.sku, p.min_stock,
                       COALESCE(SUM(s.quantity), 0) as total_stock,
                       (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as primary_image
                FROM products p LEFT JOIN stock s ON s.product_id = p.id";
        $params = [];
        if ($warehouseId) { $sql .= " AND s.warehouse_id = ?"; $params[] = $warehouseId; }
        $sql .= " WHERE p.is_active = 1 GROUP BY p.id HAVING total_stock <= p.min_stock ORDER BY total_stock ASC";
        Response::success(Database::fetchAll($sql, $params));
    }

    private function getVariants(int $productId): array {
        $variants = Database::fetchAll(
            "SELECT pv.*, GROUP_CONCAT(CONCAT(a.name, ':', av.value) SEPARATOR ', ') as attributes_str
             FROM product_variants pv
             LEFT JOIN variant_attributes va ON va.variant_id = pv.id
             LEFT JOIN attributes a ON a.id = va.attribute_id
             LEFT JOIN attribute_values av ON av.id = va.attribute_value_id
             WHERE pv.product_id = ?
             GROUP BY pv.id",
            [$productId]
        );
        return $variants;
    }

    private function generateSlug(string $name, int $excludeId = 0): string {
        $slug = strtolower(preg_replace('/[^a-zA-Z0-9\-]/', '-', iconv('UTF-8', 'ASCII//TRANSLIT', $name)));
        $slug = preg_replace('/-+/', '-', trim($slug, '-'));
        $base = $slug;
        $i    = 1;
        while (true) {
            $exists = Database::fetch(
                "SELECT id FROM products WHERE slug = ? AND id != ?",
                [$slug, $excludeId]
            );
            if (!$exists) break;
            $slug = "$base-$i";
            $i++;
        }
        return $slug;
    }
}
