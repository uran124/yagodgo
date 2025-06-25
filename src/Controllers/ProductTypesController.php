<?php
namespace App\Controllers;

use PDO;

class ProductTypesController
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function index(): void
    {
        $types = $this->pdo->query("SELECT * FROM product_types ORDER BY id DESC")
            ->fetchAll(PDO::FETCH_ASSOC);

        viewAdmin('product_types/index', [
            'pageTitle' => 'Категории товаров',
            'types'     => $types,
        ]);
    }

    public function edit(): void
    {
        $id = $_GET['id'] ?? null;
        $type = null;
        if ($id) {
            $stmt = $this->pdo->prepare("SELECT * FROM product_types WHERE id = ?");
            $stmt->execute([(int)$id]);
            $type = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        viewAdmin('product_types/edit', [
            'pageTitle' => $id ? 'Редактировать категорию' : 'Добавить категорию',
            'type'      => $type,
        ]);
    }

    public function save(): void
    {
        $id      = $_POST['id'] ?? null;
        $name    = trim($_POST['name'] ?? '');
        $metaT   = trim($_POST['meta_title'] ?? '');
        $metaD   = trim($_POST['meta_description'] ?? '');
        $metaK   = trim($_POST['meta_keywords'] ?? '');
        $h1      = trim($_POST['h1'] ?? '');
        $short   = trim($_POST['short_description'] ?? '');
        $text    = trim($_POST['text'] ?? '');

        if ($id) {
            $stmt = $this->pdo->prepare(
                "UPDATE product_types SET name=?, meta_title=?, meta_description=?, meta_keywords=?, h1=?, short_description=?, text=? WHERE id=?"
            );
            $stmt->execute([$name, $metaT, $metaD, $metaK, $h1, $short, $text, (int)$id]);
        } else {
            $stmt = $this->pdo->prepare(
                "INSERT INTO product_types (name, meta_title, meta_description, meta_keywords, h1, short_description, text) VALUES (?,?,?,?,?,?,?)"
            );
            $stmt->execute([$name, $metaT, $metaD, $metaK, $h1, $short, $text]);
        }
        header('Location: /admin/product-types');
        exit;
    }
}
