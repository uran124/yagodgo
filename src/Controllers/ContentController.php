<?php
namespace App\Controllers;

use PDO;

class ContentController
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // ===== CATEGORIES =====
    public function categories(): void
    {
        $cats = $this->pdo->query("SELECT * FROM content_categories ORDER BY id DESC")
            ->fetchAll(PDO::FETCH_ASSOC);

        viewAdmin('content/categories_index', [
            'pageTitle' => 'Контент – Категории',
            'categories' => $cats,
        ]);
    }

    public function editCategory(): void
    {
        $id = $_GET['id'] ?? null;
        $category = null;
        if ($id) {
            $stmt = $this->pdo->prepare("SELECT * FROM content_categories WHERE id = ?");
            $stmt->execute([(int)$id]);
            $category = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        viewAdmin('content/category_edit', [
            'pageTitle' => $id ? 'Редактировать категорию' : 'Добавить категорию',
            'category'  => $category,
        ]);
    }

    public function saveCategory(): void
    {
        $id    = $_POST['id'] ?? null;
        $name  = trim($_POST['name'] ?? '');
        $alias = trim($_POST['alias'] ?? '');

        if ($id) {
            $stmt = $this->pdo->prepare(
                "UPDATE content_categories SET name=?, alias=? WHERE id=?"
            );
            $stmt->execute([$name, $alias, (int)$id]);
        } else {
            $stmt = $this->pdo->prepare(
                "INSERT INTO content_categories (name, alias) VALUES (?, ?)"
            );
            $stmt->execute([$name, $alias]);
        }
        header('Location: /admin/content');
        exit;
    }

    // ===== MATERIALS =====
    public function materials(): void
    {
        $catId = (int)($_GET['category_id'] ?? 0);
        $stmtC = $this->pdo->prepare("SELECT * FROM content_categories WHERE id = ?");
        $stmtC->execute([$catId]);
        $category = $stmtC->fetch(PDO::FETCH_ASSOC);

        $stmt = $this->pdo->prepare("SELECT * FROM materials WHERE category_id=? ORDER BY id DESC");
        $stmt->execute([$catId]);
        $materials = $stmt->fetchAll(PDO::FETCH_ASSOC);

        viewAdmin('content/materials_index', [
            'pageTitle' => 'Материалы',
            'category' => $category,
            'materials' => $materials,
        ]);
    }

    public function editMaterial(): void
    {
        $id = $_GET['id'] ?? null;
        $catId = (int)($_GET['category_id'] ?? 0);
        $material = null;
        if ($id) {
            $stmt = $this->pdo->prepare("SELECT * FROM materials WHERE id=?");
            $stmt->execute([(int)$id]);
            $material = $stmt->fetch(PDO::FETCH_ASSOC);
            $catId = (int)($material['category_id'] ?? $catId);
        }

        $stmtC = $this->pdo->prepare("SELECT * FROM content_categories WHERE id = ?");
        $stmtC->execute([$catId]);
        $category = $stmtC->fetch(PDO::FETCH_ASSOC);

        $products = (new ProductsController($this->pdo))->getAllActive();

        viewAdmin('content/material_edit', [
            'pageTitle' => $id ? 'Редактировать материал' : 'Добавить материал',
            'material'  => $material,
            'category'  => $category,
            'products'  => $products,
        ]);
    }

    public function saveMaterial(): void
    {
        $id         = $_POST['id'] ?? null;
        $categoryId = (int)($_POST['category_id'] ?? 0);
        $title      = trim($_POST['title'] ?? '');
        $shortDesc  = trim($_POST['short_desc'] ?? '');
        $text       = trim($_POST['text'] ?? '');
        $metaTitle  = trim($_POST['meta_title'] ?? '');
        $metaDesc   = trim($_POST['meta_description'] ?? '');
        $metaKeys   = trim($_POST['meta_keywords'] ?? '');
        $prod1      = (int)($_POST['product1_id'] ?? 0) ?: null;
        $prod2      = (int)($_POST['product2_id'] ?? 0) ?: null;
        $prod3      = (int)($_POST['product3_id'] ?? 0) ?: null;

        $imagePath = null;
        if (!empty($_FILES['image']['tmp_name'])) {
            $tmp     = $_FILES['image']['tmp_name'];
            $dstName = uniqid('mat_', true) . '.webp';
            $dst     = __DIR__ . '/../../uploads/' . $dstName;
            $src = @imagecreatefromstring(file_get_contents($tmp));
            if ($src) {
                $w = imagesx($src);
                $h = imagesy($src);
                $targetRatio = 16/9;
                if ($w/$h > $targetRatio) {
                    $newH = $h;
                    $newW = (int)($h * $targetRatio);
                    $x0 = (int)(($w - $newW)/2);
                    $y0 = 0;
                } else {
                    $newW = $w;
                    $newH = (int)($w / $targetRatio);
                    $x0 = 0;
                    $y0 = (int)(($h - $newH)/2);
                }
                $crop = imagecrop($src, [ 'x'=>$x0,'y'=>$y0,'width'=>$newW,'height'=>$newH ]);
                if ($crop) {
                    $resized = imagecreatetruecolor(640,360);
                    imagecopyresampled($resized,$crop,0,0,0,0,640,360,$newW,$newH);
                    imagewebp($resized,$dst,80);
                    imagedestroy($crop);
                    imagedestroy($resized);
                    $imagePath = '/uploads/' . $dstName;
                }
                imagedestroy($src);
            }
        }

        if ($id) {
            $sql = "UPDATE materials SET category_id=?, title=?, short_desc=?, text=?, meta_title=?, meta_description=?, meta_keywords=?, product1_id=?, product2_id=?, product3_id=?";
            $params = [$categoryId, $title, $shortDesc, $text, $metaTitle, $metaDesc, $metaKeys, $prod1, $prod2, $prod3];
            if ($imagePath) { $sql .= ", image_path=?"; $params[] = $imagePath; }
            $sql .= " WHERE id=?";
            $params[] = (int)$id;
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
        } else {
            $columns = "category_id,title,short_desc,text,meta_title,meta_description,meta_keywords,product1_id,product2_id,product3_id";
            $placeholders = "?,?,?,?,?,?,?,?,?,?";
            $params = [$categoryId,$title,$shortDesc,$text,$metaTitle,$metaDesc,$metaKeys,$prod1,$prod2,$prod3];
            if ($imagePath) { $columns .= ",image_path"; $placeholders .= ",?"; $params[] = $imagePath; }
            $sql = "INSERT INTO materials ($columns) VALUES ($placeholders)";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
        }
        header('Location: /admin/content/materials?category_id='.$categoryId);
        exit;
    }
}
