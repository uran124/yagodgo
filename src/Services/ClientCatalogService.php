<?php
namespace App\Services;

use PDO;

class ClientCatalogService
{
    private const HOME_SALE_WHERE = "p.is_active = 1 AND p.current_purchase_batch_id IS NOT NULL
                 AND pb.status = 'arrived' AND p.discount_stock_boxes > 0";
    private const HOME_IN_STOCK_WHERE = "p.is_active = 1 AND p.current_purchase_batch_id IS NOT NULL
                 AND p.seller_id IS NULL AND pb.status = 'purchased'
                 AND p.free_stock_boxes > 0";
    private const HOME_PREORDER_WHERE = "p.is_active = 1 AND p.current_purchase_batch_id IS NOT NULL
                 AND p.seller_id IS NULL AND pb.status = 'planned'";

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
                self::HOME_SALE_WHERE,
                'p.id DESC',
                [],
                10
            ),
            'regularProducts' => $this->fetchProducts(
                self::HOME_IN_STOCK_WHERE,
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
                self::HOME_PREORDER_WHERE,
                'p.id DESC',
                [],
                10
            ),
            'discountProducts' => $this->fetchProducts(
                'p.is_active = 1 AND p.discount_stock_boxes > 0',
                'p.discount_stock_boxes DESC, p.id DESC',
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
            "CASE\n" .
            "  WHEN pb.purchased_at IS NULL THEN 3\n" .
            "  WHEN DATE(pb.purchased_at) > ? THEN 2\n" .
            "  ELSE 1\n" .
            "END,\n" .
            "COALESCE(DATE(pb.purchased_at), '9999-12-31'),\n" .
            "p.id DESC",
            []
        );

        foreach ($products as &$product) {
            $batchStatus = (string)($product['purchase_batch_status'] ?? '');
            $isSeller = !empty($product['seller_id']);
            $freeStock = (float)($product['free_stock_boxes'] ?? 0);
            $discountStock = (float)($product['discount_stock_boxes'] ?? 0);

            if (!$isSeller && $batchStatus === 'arrived' && $discountStock > 0) {
                $product['catalog_section'] = 'sale';
            } elseif (!$isSeller && $batchStatus === 'purchased' && $freeStock > 0) {
                $product['catalog_section'] = 'in_stock';
            } elseif ($isSeller) {
                $product['catalog_section'] = 'seller';
            } elseif (!$isSeller && $batchStatus === 'planned') {
                $product['catalog_section'] = 'preorder';
            } else {
                $product['catalog_section'] = 'other';
            }
        }
        unset($product);

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
            "       COALESCE(pb.instant_unit_price, p.price) AS price,\n" .
            "       COALESCE(pb.purchase_price_per_box, 0) AS purchase_price_per_box,\n" .
            "       p.sale_price,\n" .
            "       p.is_active,\n" .
            "       p.image_path,\n" .
            "       DATE(pb.purchased_at) AS delivery_date,\n" .
            "       COALESCE(u.company_name, u.name, 'berryGo') AS seller_name\n" .
            "FROM products p\n" .
            "JOIN product_types t ON t.id = p.product_type_id\n" .
            "LEFT JOIN users u ON u.id = p.seller_id\n" .
            "LEFT JOIN purchase_batches pb ON pb.id = p.current_purchase_batch_id\n" .
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
            "WHERE m.is_active = 1 AND m.show_on_home = 1\n" .
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
