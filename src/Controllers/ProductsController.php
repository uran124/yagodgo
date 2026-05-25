<?php
namespace App\Controllers;

use PDO;

class ProductsController
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Get base path for redirects depending on role
     */
    private function basePath(): string
    {
        $role = $_SESSION['role'] ?? '';
        return match ($role) {
            'manager' => '/manager/products',
            'partner' => '/partner/products',
            'seller'  => '/seller/products',
            default   => '/admin/products',
        };
    }



    // Возвращает массив всех активных товаров
    public function getAllActive(): array
    {
        $stmt = $this->pdo->query("SELECT id, variety, price, unit, image_path FROM products WHERE free_stock_boxes > 0");
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    // Возвращает один товар по ID
    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT id, variety, price, unit, image_path FROM products WHERE id = ?");
        $stmt->execute([$id]);
        $prod = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $prod ?: null;
    }




    /**
     * Список товаров для админки
     */
    public function index(): void
    {
        $role = $_SESSION['role'] ?? '';
        $params = [];
        $selectedSeller = 0;
        $availabilityFilter = trim((string)($_GET['availability'] ?? ''));

        $sql = "SELECT
                p.id,
                p.alias,
                t.name            AS product,
                t.alias           AS type_alias,
                p.variety,
                p.description,
                p.manufacturer,
                p.origin_country,
                p.box_size,
                p.box_unit,
                p.unit,
                COALESCE(pb.instant_price_per_box, p.price) AS price,
                p.free_stock_boxes,
                p.is_active,
                p.image_path,
                DATE(pb.purchased_at) AS delivery_date
             FROM products p
             JOIN product_types t ON t.id = p.product_type_id
             LEFT JOIN purchase_batches pb ON pb.id = p.current_purchase_batch_id";

        if ($role === 'seller') {
            $selectedSeller = $_SESSION['user_id'] ?? 0;
            $sql .= " WHERE p.seller_id = ?";
            $params[] = $selectedSeller;
        } else {
            $selectedSeller = (int)($_GET['seller_id'] ?? 0);
            if ($selectedSeller > 0) {
                $sql .= " WHERE p.seller_id = ?";
                $params[] = $selectedSeller;
            }
        }

        $placeholder = defined('PLACEHOLDER_DATE') ? PLACEHOLDER_DATE : '2025-05-15';
        if ($availabilityFilter === 'reserved') {
            $sql .= (stripos($sql, ' WHERE ') !== false ? " AND " : " WHERE ") . "(pb.purchased_at IS NULL OR DATE(pb.purchased_at) = ?)";
            $params[] = $placeholder;
        } elseif ($availabilityFilter === 'available') {
            $sql .= (stripos($sql, ' WHERE ') !== false ? " AND " : " WHERE ") . "(pb.purchased_at IS NOT NULL AND DATE(pb.purchased_at) <> ?)";
            $params[] = $placeholder;
        }

        $sql .= " ORDER BY t.name, p.variety";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $sellers = [];
        if ($role !== 'seller') {
            $sellers = $this->pdo
                ->query("SELECT id, company_name FROM users WHERE role = 'seller' ORDER BY company_name")
                ->fetchAll(PDO::FETCH_ASSOC);
        }

        viewAdmin('products/index', [
            'pageTitle'      => 'Товары',
            'products'       => $products,
            'sellers'        => $sellers,
            'selectedSeller' => $selectedSeller,
            'availabilityFilter' => $availabilityFilter,
        ]);
    }

    /**
     * Форма создания/редактирования товара
     */
    public function edit(): void
    {
        $id = $_GET['id'] ?? null;
        $product = null;

        if ($id) {
            $role = $_SESSION['role'] ?? '';
            if ($role === 'seller') {
                $stmt = $this->pdo->prepare(
                    "SELECT p.*, DATE(pb.purchased_at) AS delivery_date, COALESCE(pb.instant_price_per_box, p.price) AS price,
                            COALESCE(pb.preorder_price_per_box, p.preorder_price_per_box) AS preorder_price_per_box
                     FROM products p
                     LEFT JOIN purchase_batches pb ON pb.id = p.current_purchase_batch_id
                     WHERE p.id = ? AND p.seller_id = ?"
                );
                $stmt->execute([(int)$id, $_SESSION['user_id'] ?? 0]);
            } else {
                $stmt = $this->pdo->prepare(
                    "SELECT p.*, DATE(pb.purchased_at) AS delivery_date, COALESCE(pb.instant_price_per_box, p.price) AS price,
                            COALESCE(pb.preorder_price_per_box, p.preorder_price_per_box) AS preorder_price_per_box
                     FROM products p
                     LEFT JOIN purchase_batches pb ON pb.id = p.current_purchase_batch_id
                     WHERE p.id = ?"
                );
                $stmt->execute([(int)$id]);
            }
            $product = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        $priceKg = $product['price'] ?? null;

        // Список типов
        $types = $this->pdo
            ->query("SELECT id, name FROM product_types ORDER BY name")
            ->fetchAll(PDO::FETCH_ASSOC);

        viewAdmin('products/edit', [
            'pageTitle'     => $id ? 'Редактировать товар' : 'Добавить товар',
            'product'       => $product,
            'types'         => $types,
            'box_size'      => $product['box_size']      ?? 0,
            'box_unit'      => $product['box_unit']      ?? 'кг',
            'delivery_date' => $product['delivery_date'] ?? null,
            'price_kg'      => $priceKg,
        ]);
    }

    /**
     * Сохранение товара (INSERT или UPDATE)
     */
    public function save(): void
    {
        $id            = $_POST['id'] ?? null;
        $typeId        = (int)($_POST['product_type_id'] ?? 0);
        $role          = $_SESSION['role'] ?? '';
        $sellerId      = $role === 'seller' ? (int)($_SESSION['user_id'] ?? 0) : null;
        $variety       = trim($_POST['variety'] ?? '');
        $alias         = trim($_POST['alias'] ?? '');
        $description   = trim($_POST['description'] ?? '');
        $fullDesc      = trim($_POST['full_description'] ?? '');
        $compositionArr = array_filter(array_map('trim', $_POST['composition'] ?? []));
        $compositionJson = $compositionArr ? json_encode(array_values($compositionArr), JSON_UNESCAPED_UNICODE) : null;
        $metaTitle      = trim($_POST['meta_title'] ?? '');
        $metaDesc       = trim($_POST['meta_description'] ?? '');
        $metaKeys       = trim($_POST['meta_keywords'] ?? '');
        $manufacturer  = trim($_POST['manufacturer'] ?? '');
        $originCountry = trim($_POST['origin_country'] ?? '');
        $boxSize       = (float)($_POST['box_size'] ?? 0);
        $boxUnitRaw    = $_POST['box_unit'] ?? 'кг';
        $boxUnit       = ($boxUnitRaw === 'л' ? 'л' : 'кг');
        $unitRaw       = $_POST['unit'] ?? 'кг';
        $unit          = ($unitRaw === 'л' ? 'л' : 'кг');
        $price        = (float)$_POST['price']; // свободная цена за ящик
        $preorderPrice = (float)($_POST['preorder_price_per_box'] ?? 0);
        $salePrice     = (float)($_POST['sale_price'] ?? 0);
        $isActive      = isset($_POST['is_active']) ? 1 : 0;

        // дата поставки (NULL → под заказ)
        $deliveryDateRaw = trim($_POST['delivery_date'] ?? '');
        $deliveryDate    = $deliveryDateRaw !== '' ? $deliveryDateRaw : null;

        // Обработка загрузки изображения
        $imagePath = null;
        if (!empty($_FILES['image']['tmp_name'])) {
            $tmp     = $_FILES['image']['tmp_name'];
            $dstName = uniqid('prod_', true) . '.webp';
            $dst     = __DIR__ . '/../../uploads/' . $dstName;

            $src = @imagecreatefromstring(file_get_contents($tmp));
            if ($src) {
                $w           = imagesx($src);
                $h           = imagesy($src);
                $targetRatio = 1; // square

                if ($w / $h > $targetRatio) {
                    $newH = $h;
                    $newW = (int)($h * $targetRatio);
                    $x0   = (int)(($w - $newW) / 2);
                    $y0   = 0;
                } else {
                    $newW = $w;
                    $newH = (int)($w / $targetRatio);
                    $x0   = 0;
                    $y0   = (int)(($h - $newH) / 2);
                }

                $crop = imagecrop($src, [
                    'x'      => $x0,
                    'y'      => $y0,
                    'width'  => $newW,
                    'height' => $newH,
                ]);
                if ($crop) {
                    $resized = imagecreatetruecolor(640, 640);
                    imagecopyresampled(
                        $resized, $crop,
                        0, 0, 0, 0,
                        640, 640,
                        $newW, $newH
                    );
                    imagewebp($resized, $dst, 80);
                    imagedestroy($crop);
                    imagedestroy($resized);
                    $imagePath = '/uploads/' . $dstName;
                }
                imagedestroy($src);
            }
        }

        if ($id) {
            $oldDeliveryDate = null;
            $oldStmt = $this->pdo->prepare("SELECT delivery_date FROM products WHERE id = ?");
            $oldStmt->execute([(int)$id]);
            $oldDeliveryDate = $oldStmt->fetchColumn() ?: null;

            // UPDATE
            $sql = "UPDATE products SET
                        product_type_id = ?,
                        alias           = ?,
                        variety         = ?,
                        description     = ?,
                        full_description= ?,
                        composition     = ?,
                        meta_title      = ?,
                        meta_description= ?,
                        meta_keywords   = ?,
                        manufacturer    = ?,
                        origin_country  = ?,
                        box_size        = ?,
                        box_unit        = ?,
                        unit            = ?,
                        price           = ?,
                        sale_price      = ?,
                        delivery_date   = ?,
                        is_active       = ?";
            $params = [
                $typeId, $alias, $variety, $description, $fullDesc, $compositionJson,
                $metaTitle, $metaDesc, $metaKeys,
                $manufacturer, $originCountry, $boxSize, $boxUnit,
                $unit, $price, $salePrice,
                $deliveryDate, $isActive
            ];

            if ($imagePath) {
                $sql      .= ", image_path = ?";
                $params[] = $imagePath;
            }

            $sql      .= " WHERE id = ?";
            $params[]  = (int)$id;
            if ($sellerId) {
                $sql    .= " AND seller_id = ?";
                $params[] = $sellerId;
            }

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $placeholder = defined('PLACEHOLDER_DATE') ? PLACEHOLDER_DATE : '2025-05-15';
            $wasUnknown = ($oldDeliveryDate === null || $oldDeliveryDate === '' || $oldDeliveryDate === $placeholder);
            $isNowKnown = ($deliveryDate !== null && $deliveryDate !== '' && $deliveryDate !== $placeholder);
            if ($stmt->rowCount() > 0 && $isNowKnown && $wasUnknown) {
                $this->activateReservedOrdersByProduct((int)$id, $deliveryDate);
            }

            $batchId = $this->resolveCurrentPurchaseBatchId((int)$id);
            if ($batchId !== null) {
                $updBatch = $this->pdo->prepare("UPDATE purchase_batches SET instant_price_per_box = ?, preorder_price_per_box = ? WHERE id = ?");
                $updBatch->execute([$price, $preorderPrice, $batchId]);
                $updProduct = $this->pdo->prepare("UPDATE products SET instant_price_per_box = ?, preorder_price_per_box = ? WHERE id = ?");
                $updProduct->execute([$price, $preorderPrice, (int)$id]);
            }

        } else {
            // INSERT
            $columns      = "product_type_id,alias,variety,description,full_description,composition,meta_title,meta_description,meta_keywords,manufacturer,origin_country,box_size,box_unit,unit,price,sale_price,delivery_date,is_active";
            // 18 placeholders corresponding to the columns above
            $placeholders = "?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?";
            $params       = [
                $typeId, $alias, $variety, $description, $fullDesc, $compositionJson,
                $metaTitle, $metaDesc, $metaKeys,
                $manufacturer, $originCountry, $boxSize, $boxUnit,
                $unit, $price, $salePrice,
                $deliveryDate, $isActive
            ];
            if ($sellerId) {
                $columns      .= ",seller_id";
                $placeholders .= ",?";
                $params[] = $sellerId;
            }

            if ($imagePath) {
                $columns      .= ",image_path";
                $placeholders .= ",?";
                $params[]      = $imagePath;
            }

            $sql  = "INSERT INTO products ({$columns}) VALUES ({$placeholders})";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
        }

        header('Location: ' . $this->basePath());
        exit;
    }

    // Включение/выключение товара
    public function toggle(): void
    {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            $role = $_SESSION['role'] ?? '';
            $params = [$id];
            $sql = "UPDATE products SET is_active = CASE WHEN is_active=1 THEN 0 ELSE 1 END WHERE id = ?";
            if ($role === 'seller') {
                $sql .= " AND seller_id = ?";
                $params[] = $_SESSION['user_id'] ?? 0;
            }
            $this->pdo->prepare($sql)->execute($params);
        }
        header('Location: ' . $this->basePath());
        exit;
    }

    // Удаление товара
    public function delete(): void
    {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            $role = $_SESSION['role'] ?? '';
            $params = [$id];
            $sql = "DELETE FROM products WHERE id = ?";
            if ($role === 'seller') {
                $sql .= " AND seller_id = ?";
                $params[] = $_SESSION['user_id'] ?? 0;
            }
            $this->pdo->prepare($sql)->execute($params);
        }
        header('Location: ' . $this->basePath());
        exit;
    }

    // Обновление текущей цены продажи активной закупки (за позицию/ящик)
    public function updatePrice(): void
    {
        $id = (int)($_POST['id'] ?? 0);
        $raw = trim($_POST['price'] ?? '');
        if ($id && $raw !== '') {
            $price = (float)$raw;
            $batchId = $this->resolveCurrentPurchaseBatchId($id);
            if ($batchId !== null) {
                $stmt = $this->pdo->prepare("UPDATE purchase_batches SET instant_price_per_box = ? WHERE id = ?");
                $stmt->execute([$price, $batchId]);
            }
        }
        // Redirect back to the page where the price was updated.
        // If the "Referer" header is available, return to that page
        // (including its query string). Otherwise fallback to the
        // appropriate products list based on the user role.
        $referer = $_SERVER['HTTP_REFERER'] ?? '';
        if ($referer !== '') {
            $url = parse_url($referer);
            $path = $url['path'] ?? '/';
            $query = isset($url['query']) ? '?' . $url['query'] : '';
            header('Location: ' . $path . $query);
        } else {
            header('Location: ' . $this->basePath());
        }
        exit;
    }

    // Обновление даты запланированной закупки (planned) для товара
    public function updateDeliveryDate(): void
    {
        $id = (int)($_POST['id'] ?? 0);
        $raw = trim($_POST['delivery_date'] ?? '');
        $date = $raw !== '' ? $raw : null;
        if ($id && $date !== null) {
            $batchId = $this->resolvePlannedPurchaseBatchId($id);
            if ($batchId !== null) {
                $stmt = $this->pdo->prepare("UPDATE purchase_batches SET purchased_at = ? WHERE id = ?");
                $stmt->execute([$date . ' 00:00:00', $batchId]);
                $placeholder = defined('PLACEHOLDER_DATE') ? PLACEHOLDER_DATE : '2025-05-15';
                if ($date !== '' && $date !== $placeholder) {
                    $this->activateReservedOrdersByProduct($id, $date);
                }
            }
        }
        // Determine where to redirect after updating
        $refererPath = parse_url($_SERVER['HTTP_REFERER'] ?? '', PHP_URL_PATH) ?? '';
        if (preg_match('#^/(admin|manager|partner|seller)/#', $refererPath)) {
            $role = $_SESSION['role'] ?? '';
            $base = match ($role) {
                'manager' => '/manager/products',
                'partner' => '/partner/products',
                'seller'  => '/seller/products',
                default   => '/admin/products',
            };
        } else {
            $base = '/';
        }

        header('Location: ' . $base);
        exit;
    }


    private function resolveCurrentPurchaseBatchId(int $productId): ?int
    {
        $params = [$productId];
        $sql = "SELECT pb.id
                FROM purchase_batches pb
                JOIN products p ON p.id = pb.product_id
                WHERE pb.product_id = ?
                  AND pb.status IN ('planned', 'active', 'arrived', 'purchased')";

        $role = $_SESSION['role'] ?? '';
        if ($role === 'seller') {
            $sql .= " AND p.seller_id = ?";
            $params[] = (int)($_SESSION['user_id'] ?? 0);
        }

        $sql .= " ORDER BY FIELD(pb.status, 'planned', 'active', 'arrived', 'purchased'),
                          pb.purchased_at ASC,
                          pb.id ASC
                  LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $batchId = $stmt->fetchColumn();

        return $batchId !== false && $batchId !== null ? (int)$batchId : null;
    }

    private function resolvePlannedPurchaseBatchId(int $productId): ?int
    {
        $params = [$productId];
        $sql = "SELECT pb.id
                FROM purchase_batches pb
                JOIN products p ON p.id = pb.product_id
                WHERE pb.product_id = ?
                  AND pb.status = 'planned'";

        $role = $_SESSION['role'] ?? '';
        if ($role === 'seller') {
            $sql .= " AND p.seller_id = ?";
            $params[] = (int)($_SESSION['user_id'] ?? 0);
        }

        $sql .= " ORDER BY pb.purchased_at ASC, pb.id ASC LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $batchId = $stmt->fetchColumn();

        return $batchId !== false && $batchId !== null ? (int)$batchId : null;
    }

    /**
     * Переводит бронь-заказы в обычные, когда для товара появляется дата поставки.
     */
    private function activateReservedOrdersByProduct(int $productId, string $deliveryDate): void
    {
        $stmt = $this->pdo->prepare(
            "SELECT DISTINCT o.id
             FROM orders o
             JOIN order_items oi ON oi.order_id = o.id
             WHERE oi.product_id = ?
               AND o.status = 'reserved'"
        );
        $stmt->execute([$productId]);
        $orderIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
        if (!$orderIds) {
            return;
        }

        $sumStmt = $this->pdo->prepare(
            "SELECT COALESCE(SUM(oi.quantity * oi.unit_price), 0)
             FROM order_items oi
             WHERE oi.order_id = ?"
        );
        $updStmt = $this->pdo->prepare(
            "UPDATE orders
             SET delivery_date = ?, total_amount = ?, status = 'new'
             WHERE id = ?"
        );
        foreach ($orderIds as $orderId) {
            $sumStmt->execute([$orderId]);
            $rawSum = (float)$sumStmt->fetchColumn();
            $metaStmt = $this->pdo->prepare("SELECT points_used, discount_applied, address_id FROM orders WHERE id = ?");
            $metaStmt->execute([$orderId]);
            $meta = $metaStmt->fetch(PDO::FETCH_ASSOC) ?: ['points_used' => 0, 'discount_applied' => 0, 'address_id' => null];
            $shipping = ((int)($meta['address_id'] ?? 0) > 0) ? 300 : 0;
            $finalTotal = max(0, (int)round($rawSum) - (int)($meta['points_used'] ?? 0) - (int)($meta['discount_applied'] ?? 0) + $shipping);
            $updStmt->execute([$deliveryDate, $finalTotal, $orderId]);
        }
    }
}
