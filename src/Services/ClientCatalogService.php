<?php
namespace App\Services;

use PDO;

class ClientCatalogService
{
    private const HOME_SALE_WHERE = "p.is_active = 1
                 AND p.seller_id IS NULL
                 AND availability.has_discount_batch = 1";
    private const HOME_IN_STOCK_WHERE = "p.is_active = 1
                 AND p.seller_id IS NULL
                 AND availability.has_in_stock_batch = 1";
    private const HOME_PREORDER_WHERE = "p.is_active = 1
                 AND p.seller_id IS NULL
                 AND availability.has_planned_batch = 1";

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
                10,
                'discount'
            ),
            'regularProducts' => $this->fetchProducts(
                self::HOME_IN_STOCK_WHERE,
                'p.id DESC',
                [],
                10,
                'in_stock'
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
                10,
                'preorder'
            ),
            'discountProducts' => $this->fetchProducts(
                'p.is_active = 1 AND availability.has_discount_batch = 1',
                'p.id DESC',
                [],
                10,
                'discount'
            ),
            'materials' => $this->fetchHomeMaterials(),
            'materialCategories' => $this->fetchMaterialCategories(),
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
            "  WHEN availability.next_planned_date IS NULL THEN 3\n" .
            "  WHEN DATE(availability.next_planned_date) > ? THEN 2\n" .
            "  ELSE 1\n" .
            "END,\n" .
            "COALESCE(DATE(availability.next_planned_date), '9999-12-31'),\n" .
            "p.id DESC",
            [$today]
        );

        foreach ($products as &$product) {
            $isSeller = !empty($product['seller_id']);

            $hasPlannedBatch = (int)($product['has_planned_batch'] ?? 0) === 1;
            $hasInStockBatch = (int)($product['has_in_stock_batch'] ?? 0) === 1;
            $hasDiscountBatch = (int)($product['has_discount_batch'] ?? 0) === 1;

            if (!$isSeller && $hasDiscountBatch) {
                $product['catalog_section'] = 'sale';
            } elseif (!$isSeller && $hasInStockBatch) {
                $product['catalog_section'] = 'in_stock';
            } elseif ($isSeller) {
                $product['catalog_section'] = 'seller';
            } elseif (!$isSeller && $hasPlannedBatch) {
                $product['catalog_section'] = 'preorder';
            } else {
                $product['catalog_section'] = 'other';
            }
        }
        unset($product);

        return [
            'products' => $products,
            'types' => $this->fetchActiveTypes(),
        ];
    }

    /**
     * @param array<int, mixed> $params
     * @return array<int, array<string, mixed>>
     */
    private function fetchProducts(string $where, string $orderBy, array $params = [], ?int $limit = null, string $offerMode = 'auto'): array
    {
        $batchSelectorCondition = match ($offerMode) {
            'preorder' => "pb2.status = 'planned'",
            'discount' => "pb2.status IN ('purchased', 'arrived') AND pb2.boxes_discount > 0 AND pb2.discount_price_per_box > 0",
            'in_stock' => "pb2.status IN ('purchased', 'arrived') AND pb2.boxes_free > 0 AND pb2.instant_price_per_box > 0",
            default => "((pb2.status IN ('purchased', 'arrived') AND (pb2.boxes_free > 0 OR pb2.boxes_discount > 0)) OR pb2.status = 'planned')",
        };
        $batchSelectorOrder = match ($offerMode) {
            'preorder' => "pb2.purchased_at ASC, pb2.id ASC",
            'discount' => "pb2.purchased_at ASC, pb2.id ASC",
            'in_stock' => "pb2.purchased_at ASC, pb2.id ASC",
            default => "CASE WHEN pb2.status IN ('purchased', 'arrived') AND pb2.boxes_free > 0 THEN 1 WHEN pb2.status IN ('purchased', 'arrived') AND pb2.boxes_discount > 0 THEN 2 WHEN pb2.status = 'planned' THEN 3 ELSE 9 END, pb2.purchased_at ASC, pb2.id ASC",
        };

        $sql = "SELECT p.id,\n" .
            "       pb.id AS purchase_batch_id,\n" .
            "       p.alias,\n" .
            "       t.name AS product,\n" .
            "       t.alias AS type_alias,\n" .
            "       p.variety,\n" .
            "       p.description,\n" .
            "       p.origin_country,\n" .
            "       p.box_size,\n" .
            "       p.box_unit,\n" .
            "       CASE\n" .
            "         WHEN COALESCE(pb.boxes_discount, 0) > 0 AND COALESCE(pb.discount_price_per_box, 0) > 0 THEN pb.discount_price_per_box\n" .
            "         WHEN pb.status = 'planned' THEN COALESCE(NULLIF(pb.preorder_price_per_box, 0), NULLIF(p.preorder_price_per_box, 0), p.price, 0)\n" .
            "         ELSE COALESCE(pb.instant_price_per_box, p.price, 0)\n" .
            "       END AS price,\n" .
            "       COALESCE(pb.instant_price_per_box, 0) AS current_price_per_box,\n" .
            "       CASE WHEN pb.status = 'planned' THEN COALESCE(NULLIF(pb.preorder_price_per_box, 0), NULLIF(p.preorder_price_per_box, 0), p.price, 0) ELSE COALESCE(pb.preorder_price_per_box, 0) END AS preorder_price_per_box,\n" .
            "       p.sale_price,\n" .
            "       p.is_active,\n" .
            "       COALESCE(NULLIF(batch_photo.image_path, ''), NULLIF(p.image_path, ''), '') AS image_path,\n" .
            "       p.image_path AS product_image_path,\n" .
            "       batch_photo.image_path AS batch_image_path,\n" .
            "       DATE(pb.purchased_at) AS delivery_date,\n" .
            "       COALESCE(u.company_name, u.name, 'berryGo') AS seller_name,\n" .
            "       p.seller_id,\n" .
            "       pb.status AS purchase_batch_status,\n" .
            "       COALESCE(pb.boxes_free, 0) AS batch_boxes_free,\n" .
            "       COALESCE(pb.boxes_discount, 0) AS batch_boxes_discount,\n" .
            "       COALESCE(availability.has_planned_batch, 0) AS has_planned_batch,\n" .
            "       COALESCE(availability.has_in_stock_batch, 0) AS has_in_stock_batch,\n" .
            "       COALESCE(availability.has_discount_batch, 0) AS has_discount_batch,\n" .
            "       DATE(availability.next_planned_date) AS next_planned_date,\n" .
            "       instant_pb.id AS instant_purchase_batch_id,\n" .
            "       COALESCE(instant_pb.boxes_free, 0) AS instant_available_boxes,\n" .
            "       COALESCE(instant_pb.instant_price_per_box, 0) AS instant_price_per_box,\n" .
            "       preorder_pb.id AS preorder_purchase_batch_id,\n" .
            "       DATE(preorder_pb.purchased_at) AS preorder_availability_date,\n" .
            "       (COALESCE(NULLIF(preorder_pb.boxes_total, 0), preorder_pb.boxes_free + preorder_pb.boxes_reserved) - preorder_pb.boxes_reserved) AS preorder_available_boxes,\n" .
            "       COALESCE(NULLIF(preorder_pb.preorder_price_per_box, 0), NULLIF(p.preorder_price_per_box, 0), p.price, 0) AS confirmed_preorder_price_per_box\n" .
            "FROM products p\n" .
            "JOIN product_types t ON t.id = p.product_type_id\n" .
            "LEFT JOIN users u ON u.id = p.seller_id\n" .
            "LEFT JOIN (\n" .
            "    SELECT pbx.product_id,\n" .
            "           MAX(CASE WHEN pbx.status = 'planned' THEN 1 ELSE 0 END) AS has_planned_batch,\n" .
            "           MAX(CASE WHEN pbx.status IN ('purchased', 'arrived') AND pbx.boxes_free > 0 THEN 1 ELSE 0 END) AS has_in_stock_batch,\n" .
            "           MAX(CASE WHEN pbx.status IN ('purchased', 'arrived') AND pbx.boxes_discount > 0 THEN 1 ELSE 0 END) AS has_discount_batch,\n" .
            "           MIN(CASE WHEN pbx.status = 'planned' THEN pbx.purchased_at ELSE NULL END) AS next_planned_date\n" .
            "    FROM purchase_batches pbx\n" .
            "    GROUP BY pbx.product_id\n" .
            ") availability ON availability.product_id = p.id\n" .
            "LEFT JOIN purchase_batches instant_pb ON instant_pb.id = (\n" .
            "    SELECT pb_i.id\n" .
            "    FROM purchase_batches pb_i\n" .
            "    WHERE pb_i.product_id = p.id\n" .
            "      AND pb_i.status IN ('purchased', 'arrived')\n" .
            "      AND pb_i.boxes_free > 0\n" .
            "      AND pb_i.instant_price_per_box > 0\n" .
            "    ORDER BY pb_i.purchased_at ASC, pb_i.id ASC\n" .
            "    LIMIT 1\n" .
            ")\n" .
            "LEFT JOIN purchase_batches preorder_pb ON preorder_pb.id = (\n" .
            "    SELECT pb_p.id\n" .
            "    FROM purchase_batches pb_p\n" .
            "    WHERE pb_p.product_id = p.id\n" .
            "      AND pb_p.status = 'planned'\n" .
            "      AND pb_p.purchased_at IS NOT NULL\n" .
            "      AND (COALESCE(NULLIF(pb_p.boxes_total, 0), pb_p.boxes_free + pb_p.boxes_reserved) - pb_p.boxes_reserved) > 0\n" .
            "      AND COALESCE(NULLIF(pb_p.preorder_price_per_box, 0), NULLIF(p.preorder_price_per_box, 0), p.price, 0) > 0\n" .
            "    ORDER BY pb_p.purchased_at ASC, pb_p.id ASC\n" .
            "    LIMIT 1\n" .
            ")\n" .
            "LEFT JOIN purchase_batches pb ON pb.id = (\n" .
            "    SELECT pb2.id\n" .
            "    FROM purchase_batches pb2\n" .
            "    WHERE pb2.product_id = p.id\n" .
            "      AND {$batchSelectorCondition}\n" .
            "    ORDER BY {$batchSelectorOrder}\n" .
            "    LIMIT 1\n" .
            ")\n" .
            "LEFT JOIN purchase_batch_photos batch_photo ON batch_photo.id = (\n" .
            "    SELECT pbp.id\n" .
            "    FROM purchase_batch_photos pbp\n" .
            "    WHERE pbp.purchase_batch_id = pb.id\n" .
            "    ORDER BY pbp.id DESC\n" .
            "    LIMIT 1\n" .
            ")\n" .
            "WHERE {$where}\n" .
            "ORDER BY {$orderBy}";

        if ($limit !== null) {
            $sql .= "\nLIMIT " . (int)$limit;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $preorderDiscountPercent = max(0.0, min(99.0, (float)(get_setting('ui_preorder_discount_percent', '10') ?? '10')));
        $preorderDiscountFactor = (100.0 - $preorderDiscountPercent) / 100.0;
        foreach ($products as &$product) {
            $this->normalizeProductImagePaths($product);
            $regularPrice = (float)($product['regular_price'] ?? 0);
            $expectedPreorderPrice = (float)($product['confirmed_preorder_price_per_box'] ?? 0);
            if ((int)($product['can_preorder'] ?? 0) === 1 && $expectedPreorderPrice <= 0 && $regularPrice > 0) {
                $expectedPreorderPrice = round($regularPrice * $preorderDiscountFactor, 0);
            }
            $product['regular_price'] = $regularPrice;
            $product['expected_preorder_price'] = $expectedPreorderPrice;
            $product['confirmed_preorder_price_per_box'] = $expectedPreorderPrice;
        }
        unset($product);

        return $products;
    }

    /**
     * @param array<string, mixed> $product
     */
    private function normalizeProductImagePaths(array &$product): void
    {
        $batchImage = $this->normalizeExistingPublicFile((string)($product['batch_image_path'] ?? ''));
        $productImage = $this->normalizeExistingPublicFile((string)($product['product_image_path'] ?? ''));

        $product['batch_image_path'] = $batchImage;
        $product['product_image_path'] = $productImage;
        $product['image_path'] = $batchImage !== '' ? $batchImage : $productImage;
    }

    private function normalizeExistingPublicFile(string $path): string
    {
        $path = trim($path);
        if ($path === '') {
            return '';
        }

        if (preg_match('~^https?://~i', $path) === 1) {
            return $path;
        }

        if ($path[0] !== '/') {
            $path = '/' . $path;
        }

        if (str_starts_with($path, '/uploads/') || str_starts_with($path, '/assets/')) {
            $absolutePath = dirname(__DIR__, 2) . $path;
            if (!is_file($absolutePath)) {
                return '';
            }
        }

        return $path;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchHomeMaterials(): array
    {
        $stmt = $this->pdo->query(
            "SELECT m.id, m.alias AS mat_alias, m.title, m.short_desc, m.image_path,\n" .
            "       c.name AS category_name, c.alias AS cat_alias\n" .
            "FROM materials m\n" .
            "JOIN content_categories c ON c.id = m.category_id\n" .
            "WHERE m.is_active = 1\n" .
            "ORDER BY c.name ASC, m.created_at DESC, m.id DESC"
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchMaterialCategories(): array
    {
        $stmt = $this->pdo->query(
            "SELECT c.id, c.name, c.alias, COUNT(m.id) AS materials_count\n" .
            "FROM content_categories c\n" .
            "JOIN materials m ON m.category_id = c.id AND m.is_active = 1\n" .
            "GROUP BY c.id, c.name, c.alias\n" .
            "ORDER BY c.name ASC"
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
