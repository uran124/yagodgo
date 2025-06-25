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
        $stmt = $this->pdo->query(
            "SELECT
                p.id,
                t.name            AS product,
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
             JOIN product_types t ON t.id = p.product_type_id
             ORDER BY t.name, p.variety"
        );
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

        viewAdmin('products/index', [
            'pageTitle' => 'Товары',
            'products'  => $products,
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
            $stmt = $this->pdo->prepare("SELECT * FROM products WHERE id = ?");
            $stmt->execute([(int)$id]);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);
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
        ]);
    }

    /**
     * Сохранение товара (INSERT или UPDATE)
     */
    public function save(): void
    {
        $id            = $_POST['id'] ?? null;
        $typeId        = (int)($_POST['product_type_id'] ?? 0);
        $variety       = trim($_POST['variety'] ?? '');
        $description   = trim($_POST['description'] ?? '');
        $fullDesc      = trim($_POST['full_description'] ?? '');
        $compositionArr = array_filter(array_map('trim', $_POST['composition'] ?? []));
        $compositionJson = $compositionArr ? json_encode(array_values($compositionArr), JSON_UNESCAPED_UNICODE) : null;
        $manufacturer  = trim($_POST['manufacturer'] ?? '');
        $originCountry = trim($_POST['origin_country'] ?? '');
        $boxSize       = (float)($_POST['box_size'] ?? 0);
        $boxUnitRaw    = $_POST['box_unit'] ?? 'кг';
        $boxUnit       = ($boxUnitRaw === 'л' ? 'л' : 'кг');
        $unitRaw       = $_POST['unit'] ?? 'кг';
        $unit          = ($unitRaw === 'л' ? 'л' : 'кг');
        $price         = (float)$_POST['price'];
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
                        variety         = ?,
                        description     = ?,
                        full_description= ?,
                        composition     = ?,
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
                $typeId, $variety, $description, $fullDesc, $compositionJson, $manufacturer,
                $originCountry, $boxSize, $boxUnit,
                $unit, $price, $salePrice, $stockBoxes,
                $deliveryDate, $isActive
            ];

            if ($imagePath) {
                $sql      .= ", image_path = ?";
                $params[] = $imagePath;
            }

            $sql      .= " WHERE id = ?";
            $params[]  = (int)$id;

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);

        } else {
            // INSERT
            $columns      = "product_type_id,variety,description,full_description,composition,manufacturer,origin_country,box_size,box_unit,unit,price,sale_price,stock_boxes,delivery_date,is_active";
            // 15 placeholders corresponding to the columns above
            $placeholders = "?,?,?,?,?,?,?,?,?,?,?,?,?,?,?";
            $params       = [
                $typeId, $variety, $description, $fullDesc, $compositionJson, $manufacturer,
                $originCountry, $boxSize, $boxUnit,
                $unit, $price, $salePrice, $stockBoxes,
                $deliveryDate, $isActive
            ];

            if ($imagePath) {
                $columns      .= ",image_path";
                $placeholders .= ",?";
                $params[]      = $imagePath;
            }

            $sql  = "INSERT INTO products ({$columns}) VALUES ({$placeholders})";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
        }

        header('Location: /admin/products');
        exit;
    }

    // Включение/выключение товара
    public function toggle(): void
    {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            $this->pdo->prepare(
                "UPDATE products SET is_active = CASE WHEN is_active=1 THEN 0 ELSE 1 END WHERE id = ?"
            )->execute([$id]);
        }
        header('Location: /admin/products');
        exit;
    }

    // Удаление товара
    public function delete(): void
    {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            $this->pdo->prepare("DELETE FROM products WHERE id = ?")->execute([$id]);
        }
        header('Location: /admin/products');
        exit;
    }
}
