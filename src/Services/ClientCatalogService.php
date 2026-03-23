<?php
namespace App\Services;

use PDO;

class ClientCatalogService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * @return array<string, mixed>
     */
    public function getHomePageData(): array
    {
        return [
            'saleProducts' => $this->fetchProducts(
                'p.is_active = 1 AND p.sale_price > 0',
                'p.id DESC',
                [],
                10
            ),
            'regularProducts' => $this->fetchProducts(
                'p.is_active = 1 AND p.delivery_date IS NOT NULL AND p.seller_id IS NULL',
                'p.id DESC',
                [],
                10
            ),
            'sellerProducts' => $this->fetchProducts(
                'p.is_active = 1 AND p.seller_id IS NOT NULL',
                'p.id DESC',
                [],
                10
            ),
            'preorderProducts' => $this->fetchProducts(
                'p.is_active = 1 AND p.delivery_date IS NULL AND p.seller_id IS NULL',
                'p.id DESC',
                [],
                10
            ),
            'materials' => $this->fetchLatestMaterials(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getCatalogData(?string $today = null): array
    {
        $today ??= date('Y-m-d');

        $products = $this->fetchProducts(
            'p.is_active = 1',
            "CASE WHEN p.sale_price > 0 THEN 0 ELSE 1 END,\n" .
            "CASE\n" .
            "  WHEN p.delivery_date IS NULL THEN 3\n" .
            "  WHEN p.delivery_date > ? THEN 2\n" .
            "  ELSE 1\n" .
            "END,\n" .
            "COALESCE(p.delivery_date, '9999-12-31'),\n" .
            "p.id DESC",
            [$today]
        );

        return [
            'products' => $products,
            'types' => $this->fetchActiveTypes(),
            'debugData' => [
                'productsCount' => count($products),
                'today' => $today,
            ],
        ];
    }

    /**
     * @param array<int, mixed> $params
     * @return array<int, array<string, mixed>>
     */
    private function fetchProducts(string $where, string $orderBy, array $params = [], ?int $limit = null): array
    {
        $sql = "SELECT p.id,\n" .
            "       p.alias,\n" .
            "       t.name AS product,\n" .
            "       t.alias AS type_alias,\n" .
            "       p.variety,\n" .
            "       p.description,\n" .
            "       p.origin_country,\n" .
            "       p.box_size,\n" .
            "       p.box_unit,\n" .
            "       p.price,\n" .
            "       p.sale_price,\n" .
            "       p.is_active,\n" .
            "       p.image_path,\n" .
            "       p.delivery_date,\n" .
            "       COALESCE(u.company_name, u.name, 'berryGo') AS seller_name\n" .
            "FROM products p\n" .
            "JOIN product_types t ON t.id = p.product_type_id\n" .
            "LEFT JOIN users u ON u.id = p.seller_id\n" .
            "WHERE {$where}\n" .
            "ORDER BY {$orderBy}";

        if ($limit !== null) {
            $sql .= "\nLIMIT " . (int)$limit;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchLatestMaterials(): array
    {
        $stmt = $this->pdo->query(
            "SELECT m.id, m.alias AS mat_alias, m.title, m.short_desc, m.image_path,\n" .
            "       c.alias AS cat_alias\n" .
            "FROM materials m\n" .
            "JOIN content_categories c ON c.id = m.category_id\n" .
            "ORDER BY m.created_at DESC\n" .
            "LIMIT 5"
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchActiveTypes(): array
    {
        $stmt = $this->pdo->query(
            "SELECT DISTINCT t.id, t.name, t.alias\n" .
            "FROM product_types t\n" .
            "JOIN products p ON p.product_type_id = t.id\n" .
            "WHERE p.is_active = 1\n" .
            "ORDER BY t.name"
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
