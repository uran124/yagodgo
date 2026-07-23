<?php
namespace App\Controllers;

use PDO;
use App\Services\PricingService;
use App\Services\CatalogFeedService;

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
        $stmt = $this->pdo->query(
            "SELECT p.id, p.variety, p.price, p.unit, p.image_path
             FROM products p
             WHERE p.is_active = 1
               AND EXISTS (
                 SELECT 1
                 FROM purchase_batches pb
                 WHERE pb.product_id = p.id
                   AND pb.status IN ('active', 'arrived', 'purchased')
                   AND (pb.boxes_free > 0 OR pb.boxes_discount > 0)
               )"
        );
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
                COALESCE(pb.instant_price_per_box, 0) AS price,
                COALESCE(pb.boxes_free, 0) AS free_stock_boxes,
                p.is_active,
                p.image_path,
                DATE(pb.purchased_at) AS delivery_date
             FROM products p
             JOIN product_types t ON t.id = p.product_type_id
             LEFT JOIN purchase_batches pb ON pb.id = (
                SELECT pb2.id
                FROM purchase_batches pb2
                WHERE pb2.product_id = p.id
                  AND pb2.status IN ('purchased', 'arrived')
                  AND (pb2.boxes_free > 0 OR pb2.boxes_discount > 0)
                ORDER BY pb2.purchased_at ASC, pb2.id ASC
                LIMIT 1
             )";

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
                    "SELECT p.*, DATE(pb.purchased_at) AS delivery_date
                     FROM products p
                     LEFT JOIN purchase_batches pb ON pb.id = (
                        SELECT pb2.id
                        FROM purchase_batches pb2
                        WHERE pb2.product_id = p.id
                          AND pb2.status IN ('purchased', 'arrived')
                          AND (pb2.boxes_free > 0 OR pb2.boxes_discount > 0)
                        ORDER BY pb2.purchased_at ASC, pb2.id ASC
                        LIMIT 1
                     )
                     WHERE p.id = ? AND p.seller_id = ?"
                );
                $stmt->execute([(int)$id, $_SESSION['user_id'] ?? 0]);
            } else {
                $stmt = $this->pdo->prepare(
                    "SELECT p.*, DATE(pb.purchased_at) AS delivery_date
                     FROM products p
                     LEFT JOIN purchase_batches pb ON pb.id = (
                        SELECT pb2.id
                        FROM purchase_batches pb2
                        WHERE pb2.product_id = p.id
                          AND pb2.status IN ('purchased', 'arrived')
                          AND (pb2.boxes_free > 0 OR pb2.boxes_discount > 0)
                        ORDER BY pb2.purchased_at ASC, pb2.id ASC
                        LIMIT 1
                     )
                     WHERE p.id = ?"
                );
                $stmt->execute([(int)$id]);
            }
            $product = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        $priceKg = $product['price'] ?? null;
        $activeBatches = [];
        if ($id) {
            $batchSql = "SELECT pb.*, DATE(pb.purchased_at) AS supply_date, photo.image_path AS preview_photo
                         FROM purchase_batches pb
                         LEFT JOIN (
                           SELECT purchase_batch_id, MAX(id) AS latest_photo_id
                           FROM purchase_batch_photos
                           GROUP BY purchase_batch_id
                         ) latest_photo ON latest_photo.purchase_batch_id = pb.id
                         LEFT JOIN purchase_batch_photos photo ON photo.id = latest_photo.latest_photo_id
                         WHERE pb.product_id = ?
                           AND pb.status IN ('planned', 'purchased', 'arrived')
                           AND (
                               pb.boxes_free > 0
                               OR pb.boxes_reserved > 0
                               OR EXISTS (
                                  SELECT 1 FROM preorder_intents pi
                                  WHERE pi.purchase_batch_id = pb.id
                                    AND pi.status IN ('linked_to_batch','awaiting_price_confirmation','confirmed','offer_sent')
                               )
                               OR EXISTS (
                                  SELECT 1 FROM order_items oi JOIN orders o ON o.id = oi.order_id
                                  WHERE oi.purchase_batch_id = pb.id
                                    AND o.status NOT IN ('completed','cancelled','returned')
                               )
                           )
                         ORDER BY FIELD(pb.status, 'planned', 'purchased', 'arrived', 'active'), pb.purchased_at ASC, pb.id ASC";
            $batchStmt = $this->pdo->prepare($batchSql);
            $batchStmt->execute([(int)$id]);
            $activeBatches = $batchStmt->fetchAll(PDO::FETCH_ASSOC);
        }

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
            'activeBatches' => $activeBatches,
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
        $externalCatalogEnabled = isset($_POST['external_catalog_enabled']) ? 1 : 0;
        $externalName = trim((string)($_POST['external_name'] ?? ''));
        $externalDescription = trim((string)($_POST['external_description'] ?? ''));
        $externalSku = trim((string)($_POST['external_sku'] ?? ''));

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
                        preorder_price_per_box = ?,
                        sale_price      = ?,
                        delivery_date   = ?,
                        is_active       = ?, external_catalog_enabled = ?, external_name = ?, external_description = ?, external_sku = ?, external_updated_at = CURRENT_TIMESTAMP";
            $params = [
                $typeId, $alias, $variety, $description, $fullDesc, $compositionJson,
                $metaTitle, $metaDesc, $metaKeys,
                $manufacturer, $originCountry, $boxSize, $boxUnit,
                $unit, $price, $preorderPrice, $salePrice,
                $deliveryDate, $isActive, $externalCatalogEnabled, $externalName ?: null, $externalDescription ?: null, $externalSku ?: null
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
            (new CatalogFeedService($this->pdo))->markDirty();
            $placeholder = defined('PLACEHOLDER_DATE') ? PLACEHOLDER_DATE : '2025-05-15';
            $wasUnknown = ($oldDeliveryDate === null || $oldDeliveryDate === '' || $oldDeliveryDate === $placeholder);
            $isNowKnown = ($deliveryDate !== null && $deliveryDate !== '' && $deliveryDate !== $placeholder);
            if ($stmt->rowCount() > 0 && $isNowKnown && $wasUnknown) {
                $this->activateReservedOrdersByProduct((int)$id, $deliveryDate);
            }

            // Product edit saves default SKU values only.
            // Active purchase prices are edited on the "Закупки" tab and stored in purchase_batches.

        } else {
            // INSERT
            $columns      = "product_type_id,alias,variety,description,full_description,composition,meta_title,meta_description,meta_keywords,manufacturer,origin_country,box_size,box_unit,unit,price,preorder_price_per_box,sale_price,delivery_date,is_active,external_catalog_enabled,external_name,external_description,external_sku,external_updated_at";
            // 19 placeholders corresponding to the columns above
            $placeholders = "?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,CURRENT_TIMESTAMP";
            $params       = [
                $typeId, $alias, $variety, $description, $fullDesc, $compositionJson,
                $metaTitle, $metaDesc, $metaKeys,
                $manufacturer, $originCountry, $boxSize, $boxUnit,
                $unit, $price, $preorderPrice, $salePrice,
                $deliveryDate, $isActive, $externalCatalogEnabled, $externalName ?: null, $externalDescription ?: null, $externalSku ?: null
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
            (new CatalogFeedService($this->pdo))->markDirty();
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
            (new CatalogFeedService($this->pdo))->markDirty();
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

    // Inline update of a concrete purchase batch from product card.
    public function updatePurchaseFromProduct(): void
    {
        $batchId = (int)($_POST['batch_id'] ?? 0);
        $productId = (int)($_POST['product_id'] ?? 0);
        if ($batchId <= 0 || $productId <= 0) {
            header('Location: ' . $this->basePath());
            exit;
        }

        $purchasePrice = (float)($_POST['purchase_price_per_box'] ?? 0);
        // Operational prices are always derived from purchase price and current pricing settings.
        $boxSizeStmt = $this->pdo->prepare('SELECT box_size FROM products WHERE id = ? LIMIT 1');
        $boxSizeStmt->execute([$productId]);
        $boxSize = max(1.0, (float)$boxSizeStmt->fetchColumn());
        $pricingService = new PricingService($this->pdo);
        $prices = $pricingService->calculateFromPurchase($purchasePrice, $boxSize);
        $settings = $pricingService->getSettings();
        $instantPrice = (float)$prices['instant_price_per_box'];
        $preorderPrice = (float)$prices['preorder_price_per_box'];

        $params = [
            $purchasePrice,
            (float)$settings['pricing_preorder_margin_percent'],
            (float)$settings['ui_preorder_discount_percent'],
            (float)$settings['pricing_instant_margin_percent'],
            $instantPrice,
            $preorderPrice,
            $batchId,
            $productId,
        ];
        $sql = "UPDATE purchase_batches pb
                JOIN products p ON p.id = pb.product_id
                SET pb.purchase_price_per_box = ?, pb.preorder_margin_percent = ?, pb.preorder_discount_percent = ?, pb.instant_margin_percent = ?, pb.instant_price_per_box = ?, pb.preorder_price_per_box = ?
                WHERE pb.id = ? AND pb.product_id = ?";
        if (($_SESSION['role'] ?? '') === 'seller') {
            $sql .= " AND p.seller_id = ?";
            $params[] = (int)($_SESSION['user_id'] ?? 0);
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        if (!empty($_FILES['batch_photo']['tmp_name'])) {
            $tmp = $_FILES['batch_photo']['tmp_name'];
            $dstName = uniqid('batch_', true) . '.webp';
            $dst = __DIR__ . '/../../uploads/' . $dstName;
            $src = @imagecreatefromstring((string)file_get_contents($tmp));
            if ($src) {
                $w = imagesx($src);
                $h = imagesy($src);
                $size = min($w, $h);
                $x0 = (int)(($w - $size) / 2);
                $y0 = (int)(($h - $size) / 2);
                $crop = imagecrop($src, ['x' => $x0, 'y' => $y0, 'width' => $size, 'height' => $size]);
                if ($crop) {
                    $resized = imagecreatetruecolor(640, 640);
                    imagecopyresampled($resized, $crop, 0, 0, 0, 0, 640, 640, $size, $size);
                    imagewebp($resized, $dst, 80);
                    imagedestroy($crop);
                    imagedestroy($resized);
                    $this->pdo->prepare('INSERT INTO purchase_batch_photos (purchase_batch_id, image_path) VALUES (?, ?)')
                        ->execute([$batchId, '/uploads/' . $dstName]);
                }
                imagedestroy($src);
            }
        }

        $referer = $_SERVER['HTTP_REFERER'] ?? '';
        header('Location: ' . ($referer !== '' ? $referer : ($this->basePath() . '/edit?id=' . $productId)));
        exit;
    }

    // Обновление текущей цены продажи активной закупки (за позицию/ящик)
    public function updatePrice(): void
    {
        $id = (int)($_POST['id'] ?? 0);
        $raw = trim((string)($_POST['price'] ?? ''));
        $context = trim((string)($_POST['price_context'] ?? ''));
        $postedBatchId = (int)($_POST['purchase_batch_id'] ?? 0);

        if ($id && $raw !== '') {
            $price = max(0.0, (float)$raw);
            if ($context === 'preorder') {
                $this->updatePreorderPricesFromCard($id, $price);
            } elseif ($context === 'in_stock') {
                $batchId = $postedBatchId > 0 ? $postedBatchId : $this->resolveInStockPurchaseBatchId($id);
                if ($batchId !== null) {
                    $this->updateInStockPriceFromCard($id, $batchId, $price);
                }
            } else {
                // Legacy fallback for old forms that do not send context yet.
                $batchId = $this->resolveCurrentPurchaseBatchId($id);
                if ($batchId !== null) {
                    $stmt = $this->pdo->prepare("UPDATE purchase_batches SET instant_price_per_box = ? WHERE id = ?");
                    $stmt->execute([$price, $batchId]);
                }
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


    private function updateInStockPriceFromCard(int $productId, int $batchId, float $price): void
    {
        $params = [$price, $batchId, $productId];
        $sql = "UPDATE purchase_batches pb
                JOIN products p ON p.id = pb.product_id
                SET pb.instant_price_per_box = ?
                WHERE pb.id = ?
                  AND pb.product_id = ?
                  AND pb.status IN ('purchased', 'arrived')";

        if (($_SESSION['role'] ?? '') === 'seller') {
            $sql .= " AND p.seller_id = ?";
            $params[] = (int)($_SESSION['user_id'] ?? 0);
        }

        $this->pdo->prepare($sql)->execute($params);
    }

    private function updatePreorderPricesFromCard(int $productId, float $preorderPrice): void
    {
        $settings = (new PricingService($this->pdo))->getSettings();
        $discount = max(0.0, min(99.0, (float)($settings['ui_preorder_discount_percent'] ?? 10)));
        $discountFactor = (100.0 - $discount) / 100.0;
        $instantPrice = $discountFactor > 0 ? round($preorderPrice / $discountFactor, 2) : $preorderPrice;

        $role = $_SESSION['role'] ?? '';
        $sellerId = (int)($_SESSION['user_id'] ?? 0);
        $sellerSql = '';
        $sellerParams = [];
        if ($role === 'seller') {
            $sellerSql = ' AND p.seller_id = ?';
            $sellerParams[] = $sellerId;
        }

        $this->pdo->beginTransaction();
        try {
            $preorderStmt = $this->pdo->prepare(
                "UPDATE purchase_batches pb
                 JOIN products p ON p.id = pb.product_id
                 SET pb.preorder_price_per_box = ?, pb.preorder_discount_percent = ?
                 WHERE pb.product_id = ?
                   AND pb.status IN ('planned', 'purchased', 'arrived')" . $sellerSql
            );
            $preorderStmt->execute(array_merge([$preorderPrice, $discount, $productId], $sellerParams));

            $instantStmt = $this->pdo->prepare(
                "UPDATE purchase_batches pb
                 JOIN products p ON p.id = pb.product_id
                 SET pb.instant_price_per_box = ?, pb.preorder_discount_percent = ?
                 WHERE pb.product_id = ?
                   AND pb.status = 'planned'" . $sellerSql
            );
            $instantStmt->execute(array_merge([$instantPrice, $discount, $productId], $sellerParams));

            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    private function resolveInStockPurchaseBatchId(int $productId): ?int
    {
        $params = [$productId];
        $sql = "SELECT pb.id
                FROM purchase_batches pb
                JOIN products p ON p.id = pb.product_id
                WHERE pb.product_id = ?
                  AND pb.status IN ('purchased', 'arrived')
                  AND (pb.boxes_free > 0 OR pb.boxes_discount > 0)";

        if (($_SESSION['role'] ?? '') === 'seller') {
            $sql .= " AND p.seller_id = ?";
            $params[] = (int)($_SESSION['user_id'] ?? 0);
        }

        $sql .= " ORDER BY pb.purchased_at ASC, pb.id ASC LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $batchId = $stmt->fetchColumn();

        return $batchId !== false && $batchId !== null ? (int)$batchId : null;
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
     * Фиксирует дату/цену reserved-заказов после выкупа, не переводя их в обычный new.
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
             SET delivery_date = ?, total_amount = ?
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
