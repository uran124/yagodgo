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
          "SELECT id, code, discount, points, type, expires_at, is_active FROM coupons ORDER BY id DESC"
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
        $points    = (int)($_POST['points'] ?? 0);
        $type      = $_POST['type'] ?? 'discount';
        $expires   = $_POST['expires_at'] ?? '';
        $isActive  = isset($_POST['is_active']) ? 1 : 0;

        if ($id) {
            $stmt = $this->pdo->prepare(
              "UPDATE coupons
               SET code = ?, discount = ?, points = ?, type = ?, expires_at = ?, is_active = ?
               WHERE id = ?"
            );
            $stmt->execute([$code, $discount, $points, $type, $expires, $isActive, (int)$id]);
        } else {
            $stmt = $this->pdo->prepare(
              "INSERT INTO coupons (code, discount, points, type, expires_at, is_active)
               VALUES (?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([$code, $discount, $points, $type, $expires, $isActive]);
        }
        header('Location: /admin/coupons');
        exit;
    }

    // Генерация промокода (POST)
    public function generate(): void
    {
        $type    = $_POST['type'] ?? 'discount';
        $value   = (int)($_POST['value'] ?? 0);
        $expires = $_POST['expires_at'] ?? null;

        $code = strtoupper(substr(md5(uniqid('', true)), 0, 8));

        $stmt = $this->pdo->prepare(
            "INSERT INTO coupons (code, discount, points, type, expires_at, is_active) VALUES (?, ?, ?, ?, ?, 1)"
        );
        $discount = ($type === 'discount') ? $value : 0;
        $points   = ($type === 'points') ? $value : 0;
        $stmt->execute([$code, $discount, $points, $type, $expires]);

        header('Location: /admin/coupons');
        exit;
    }
}
