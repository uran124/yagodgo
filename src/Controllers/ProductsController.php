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
        $stmt = $this->pdo->query("SELECT id, variety, price, unit, image_path FROM products WHERE stock_boxes > 0");
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
                p.price,
                p.stock_boxes,
                p.is_active,
                p.image_path,
                p.delivery_date
             FROM products p
             JOIN product_types t ON t.id = p.product_type_id";

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
                $stmt = $this->pdo->prepare("SELECT * FROM products WHERE id = ? AND seller_id = ?");
                $stmt->execute([(int)$id, $_SESSION['user_id'] ?? 0]);
            } else {
                $stmt = $this->pdo->prepare("SELECT * FROM products WHERE id = ?");
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
        $price        = (float)$_POST['price']; // price per kg/l
        $salePrice     = (float)($_POST['sale_price'] ?? 0);
        $stockBoxes    = (float)$_POST['stock_boxes'];
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
                $targetRatio = 16 / 9;

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
                    $resized = imagecreatetruecolor(640, 360);
                    imagecopyresampled(
                        $resized, $crop,
                        0, 0, 0, 0,
                        640, 360,
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
                        stock_boxes     = ?,
                        delivery_date   = ?,
                        is_active       = ?";
            $params = [
                $typeId, $alias, $variety, $description, $fullDesc, $compositionJson,
                $metaTitle, $metaDesc, $metaKeys,
                $manufacturer, $originCountry, $boxSize, $boxUnit,
                $unit, $price, $salePrice, $stockBoxes,
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

        } else {
            // INSERT
            $columns      = "product_type_id,alias,variety,description,full_description,composition,meta_title,meta_description,meta_keywords,manufacturer,origin_country,box_size,box_unit,unit,price,sale_price,stock_boxes,delivery_date,is_active";
            // 19 placeholders corresponding to the columns above
            $placeholders = "?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?";
            $params       = [
                $typeId, $alias, $variety, $description, $fullDesc, $compositionJson,
                $metaTitle, $metaDesc, $metaKeys,
                $manufacturer, $originCountry, $boxSize, $boxUnit,
                $unit, $price, $salePrice, $stockBoxes,
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

    // Обновление даты поставки товара
    public function updateDeliveryDate(): void
    {
        $id = (int)($_POST['id'] ?? 0);
        $raw = trim($_POST['delivery_date'] ?? '');
        $date = $raw !== '' ? $raw : null;
        if ($id) {
            $role = $_SESSION['role'] ?? '';
            $params = [$date, $id];
            $sql = "UPDATE products SET delivery_date = ? WHERE id = ?";
            if ($role === 'seller') {
                $sql .= " AND seller_id = ?";
                $params[] = $_SESSION['user_id'] ?? 0;
            }
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
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
}
