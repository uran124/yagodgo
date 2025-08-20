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

    private function basePath(): string
    {
        $role = $_SESSION['role'] ?? '';
        return $role === 'seller' ? '/seller/product-types' : '/admin/product-types';
    }

    public function index(): void
    {
        $role = $_SESSION['role'] ?? '';
        if ($role === 'seller') {
            $stmt  = $this->pdo->prepare("SELECT * FROM product_types WHERE seller_id = ? ORDER BY id DESC");
            $stmt->execute([(int)($_SESSION['user_id'] ?? 0)]);
            $types = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $types = $this->pdo->query("SELECT * FROM product_types ORDER BY id DESC")
                ->fetchAll(PDO::FETCH_ASSOC);
        }

        viewAdmin('product_types/index', [
            'pageTitle' => 'Категории товаров',
            'types'     => $types,
        ]);
    }

    public function edit(): void
    {
        $id   = $_GET['id'] ?? null;
        $type = null;
        if ($id) {
            $role = $_SESSION['role'] ?? '';
            if ($role === 'seller') {
                $stmt = $this->pdo->prepare("SELECT * FROM product_types WHERE id = ? AND seller_id = ?");
                $stmt->execute([(int)$id, (int)($_SESSION['user_id'] ?? 0)]);
            } else {
                $stmt = $this->pdo->prepare("SELECT * FROM product_types WHERE id = ?");
                $stmt->execute([(int)$id]);
            }
            $type = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$type && $role === 'seller') {
                header('Location: ' . $this->basePath());
                exit;
            }
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
        $alias   = trim($_POST['alias'] ?? '');
        $metaT   = trim($_POST['meta_title'] ?? '');
        $metaD   = trim($_POST['meta_description'] ?? '');
        $metaK   = trim($_POST['meta_keywords'] ?? '');
        $h1      = trim($_POST['h1'] ?? '');
        $short   = trim($_POST['short_description'] ?? '');
        $text    = trim($_POST['text'] ?? '');
        $role    = $_SESSION['role'] ?? '';
        $seller  = (int)($_SESSION['user_id'] ?? 0);

        if ($id) {
            if ($role === 'seller') {
                $stmt = $this->pdo->prepare(
                    "UPDATE product_types SET name=?, alias=?, meta_title=?, meta_description=?, meta_keywords=?, h1=?, short_description=?, text=? WHERE id=? AND seller_id=?"
                );
                $stmt->execute([$name, $alias, $metaT, $metaD, $metaK, $h1, $short, $text, (int)$id, $seller]);
            } else {
                $stmt = $this->pdo->prepare(
                    "UPDATE product_types SET name=?, alias=?, meta_title=?, meta_description=?, meta_keywords=?, h1=?, short_description=?, text=? WHERE id=?"
                );
                $stmt->execute([$name, $alias, $metaT, $metaD, $metaK, $h1, $short, $text, (int)$id]);
            }
        } else {
            if ($role === 'seller') {
                $stmt = $this->pdo->prepare(
                    "INSERT INTO product_types (name, alias, meta_title, meta_description, meta_keywords, h1, short_description, text, seller_id) VALUES (?,?,?,?,?,?,?,?,?)"
                );
                $stmt->execute([$name, $alias, $metaT, $metaD, $metaK, $h1, $short, $text, $seller]);
            } else {
                $stmt = $this->pdo->prepare(
                    "INSERT INTO product_types (name, alias, meta_title, meta_description, meta_keywords, h1, short_description, text) VALUES (?,?,?,?,?,?,?,?)"
                );
                $stmt->execute([$name, $alias, $metaT, $metaD, $metaK, $h1, $short, $text]);
            }
        }
        header('Location: ' . $this->basePath());
        exit;
    }
}
