<?php
namespace App\Controllers;

use PDO;

class SeoController
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // Список всех страниц с метаданными
    public function index(): void
    {
        $productTypes = $this->pdo->query(
            "SELECT id, name, alias, meta_title, meta_description, meta_keywords FROM product_types ORDER BY id DESC"
        )->fetchAll(PDO::FETCH_ASSOC);

        $products = $this->pdo->query(
            "SELECT p.id, p.variety, p.alias, t.alias AS type_alias, p.meta_title, p.meta_description, p.meta_keywords
             FROM products p
             JOIN product_types t ON t.id = p.product_type_id
             ORDER BY p.id DESC"
        )->fetchAll(PDO::FETCH_ASSOC);

        $categories = $this->pdo->query(
            "SELECT id, name, alias, meta_title, meta_description, meta_keywords
             FROM content_categories ORDER BY id DESC"
        )->fetchAll(PDO::FETCH_ASSOC);

        $materials = $this->pdo->query(
            "SELECT m.id, m.title, m.alias, c.alias AS cat_alias, m.meta_title, m.meta_description, m.meta_keywords
             FROM materials m
             JOIN content_categories c ON c.id = m.category_id
             ORDER BY m.id DESC"
        )->fetchAll(PDO::FETCH_ASSOC);

        $pages = $this->pdo->query(
            "SELECT page, title, description, keywords FROM metadata ORDER BY page"
        )->fetchAll(PDO::FETCH_ASSOC);

        viewAdmin('seo/index', [
            'pageTitle'    => 'SEO',
            'productTypes' => $productTypes,
            'products'     => $products,
            'categories'   => $categories,
            'materials'    => $materials,
            'pages'        => $pages,
        ]);
    }

    // Форма редактирования
    public function edit(): void
    {
        $type = $_GET['type'] ?? '';
        $id   = (int)($_GET['id'] ?? 0);
        $page = $_GET['page'] ?? '';
        $data = null;

        switch ($type) {
            case 'product':
                $stmt = $this->pdo->prepare(
                    "SELECT id, meta_title, meta_description, meta_keywords,
                            CONCAT(t.alias,'/',p.alias) AS path
                     FROM products p
                     JOIN product_types t ON t.id = p.product_type_id
                     WHERE p.id = ?"
                );
                $stmt->execute([$id]);
                $data = $stmt->fetch(PDO::FETCH_ASSOC);
                break;
            case 'product_type':
                $stmt = $this->pdo->prepare(
                    "SELECT id, meta_title, meta_description, meta_keywords,
                            alias AS path FROM product_types WHERE id = ?"
                );
                $stmt->execute([$id]);
                $data = $stmt->fetch(PDO::FETCH_ASSOC);
                break;
            case 'material':
                $stmt = $this->pdo->prepare(
                    "SELECT m.id, m.meta_title, m.meta_description, m.meta_keywords,
                            CONCAT(c.alias,'/',m.alias) AS path
                     FROM materials m
                     JOIN content_categories c ON c.id = m.category_id
                     WHERE m.id = ?"
                );
                $stmt->execute([$id]);
                $data = $stmt->fetch(PDO::FETCH_ASSOC);
                break;
            case 'category':
                $stmt = $this->pdo->prepare(
                    "SELECT id, meta_title, meta_description, meta_keywords,
                            alias AS path FROM content_categories WHERE id = ?"
                );
                $stmt->execute([$id]);
                $data = $stmt->fetch(PDO::FETCH_ASSOC);
                break;
            case 'page':
                $stmt = $this->pdo->prepare(
                    "SELECT page, title, description, keywords FROM metadata WHERE page = ? LIMIT 1"
                );
                $stmt->execute([$page]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($row) { $data = $row; }
                break;
        }

        viewAdmin('seo/edit', [
            'pageTitle' => 'Редактировать SEO',
            'type'      => $type,
            'data'      => $data,
        ]);
    }

    // Сохранение
    public function save(): void
    {
        $type  = $_POST['type'] ?? '';
        $id    = (int)($_POST['id'] ?? 0);
        $page  = $_POST['page'] ?? '';

        switch ($type) {
            case 'product':
                $stmt = $this->pdo->prepare(
                    "UPDATE products SET meta_title=?, meta_description=?, meta_keywords=? WHERE id=?"
                );
                $stmt->execute([
                    trim($_POST['meta_title'] ?? ''),
                    trim($_POST['meta_description'] ?? ''),
                    trim($_POST['meta_keywords'] ?? ''),
                    $id,
                ]);
                break;
            case 'product_type':
                $stmt = $this->pdo->prepare(
                    "UPDATE product_types SET meta_title=?, meta_description=?, meta_keywords=? WHERE id=?"
                );
                $stmt->execute([
                    trim($_POST['meta_title'] ?? ''),
                    trim($_POST['meta_description'] ?? ''),
                    trim($_POST['meta_keywords'] ?? ''),
                    $id,
                ]);
                break;
            case 'material':
                $stmt = $this->pdo->prepare(
                    "UPDATE materials SET meta_title=?, meta_description=?, meta_keywords=? WHERE id=?"
                );
                $stmt->execute([
                    trim($_POST['meta_title'] ?? ''),
                    trim($_POST['meta_description'] ?? ''),
                    trim($_POST['meta_keywords'] ?? ''),
                    $id,
                ]);
                break;
            case 'category':
                $stmt = $this->pdo->prepare(
                    "UPDATE content_categories SET meta_title=?, meta_description=?, meta_keywords=? WHERE id=?"
                );
                $stmt->execute([
                    trim($_POST['meta_title'] ?? ''),
                    trim($_POST['meta_description'] ?? ''),
                    trim($_POST['meta_keywords'] ?? ''),
                    $id,
                ]);
                break;
            case 'page':
                $stmt = $this->pdo->prepare(
                    "REPLACE INTO metadata (page, title, description, keywords) VALUES (?,?,?,?)"
                );
                $stmt->execute([
                    $page,
                    trim($_POST['title'] ?? ''),
                    trim($_POST['description'] ?? ''),
                    trim($_POST['keywords'] ?? ''),
                ]);
                break;
        }

        header('Location: /admin/apps/seo');
        exit;
    }
}
