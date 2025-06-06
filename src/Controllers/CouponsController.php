<?php
namespace App\Controllers;

use PDO;

class CouponsController
{
    private PDO $pdo;
    public function __construct(PDO $pdo) { $this->pdo = $pdo; }

    // Список промокодов
    public function index(): void
    {
        $stmt = $this->pdo->query(
          "SELECT id, code, discount, expires_at, is_active FROM coupons ORDER BY id DESC"
        );
        $coupons = $stmt->fetchAll(PDO::FETCH_ASSOC);

        viewAdmin('coupons/index', [
          'pageTitle' => 'Промокоды',
          'coupons'   => $coupons,
        ]);
    }

    // Форма
    public function edit(): void
    {
        $id = $_GET['id'] ?? null;
        $coupon = null;
        if ($id) {
            $stmt = $this->pdo->prepare("SELECT * FROM coupons WHERE id = ?");
            $stmt->execute([(int)$id]);
            $coupon = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        viewAdmin('coupons/edit', [
          'pageTitle' => $id ? 'Редактировать промокод' : 'Добавить промокод',
          'coupon'    => $coupon,
        ]);
    }

    // Сохранение
    public function save(): void
    {
        $id        = $_POST['id'] ?? null;
        $code      = trim($_POST['code'] ?? '');
        $discount  = (float)($_POST['discount'] ?? 0);
        $expires   = $_POST['expires_at'] ?? '';
        $isActive  = isset($_POST['is_active']) ? 1 : 0;

        if ($id) {
            $stmt = $this->pdo->prepare(
              "UPDATE coupons 
               SET code = ?, discount = ?, expires_at = ?, is_active = ?
               WHERE id = ?"
            );
            $stmt->execute([$code, $discount, $expires, $isActive, (int)$id]);
        } else {
            $stmt = $this->pdo->prepare(
              "INSERT INTO coupons (code, discount, expires_at, is_active)
               VALUES (?, ?, ?, ?)"
            );
            $stmt->execute([$code, $discount, $expires, $isActive]);
        }
        header('Location: /admin/coupons');
        exit;
    }
}
