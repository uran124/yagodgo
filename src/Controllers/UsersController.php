<?php

namespace App\Controllers;

use PDO;
use App\Helpers\Auth;

class UsersController
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Просмотр профиля (клиент)
     */
    public function showProfile(): void
    {
        // Получаем информацию о текущем залогиненном пользователе
        $authUser = Auth::user();
        if (!$authUser) {
            header('Location: /login');
            exit;
        }

        // 1) Извлекаем полный набор полей пользователя из БД
        $stmt = $this->pdo->prepare(
            "SELECT u.id, u.name, u.phone, u.referral_code, u.points_balance, u.referred_by
             FROM users u
             WHERE u.id = ?"
        );
        $stmt->execute([$authUser['id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user) {
            // Если вдруг в сессии лежит несуществующий ID
            header('Location: /login');
            exit;
        }

        // 2) Получаем текущий адрес пользователя (последний по дате)
        $stmt = $this->pdo->prepare(
            "SELECT street 
             FROM addresses 
             WHERE user_id = ? 
             ORDER BY created_at DESC 
             LIMIT 1"
        );
        $stmt->execute([$user['id']]);
        $addressRow = $stmt->fetch(PDO::FETCH_ASSOC);
        $address = $addressRow['street'] ?? '';

        // 3) История баллов (points_transactions)
        $stmt = $this->pdo->prepare(
            "SELECT id, amount, transaction_type, description, order_id, created_at
             FROM points_transactions
             WHERE user_id = ?
             ORDER BY created_at DESC"
        );
        $stmt->execute([$user['id']]);
        $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 4) Активные заказы пользователя (new, processing, assigned)
        $stmt = $this->pdo->prepare(
            "SELECT id, status, total_amount, created_at
             FROM orders
             WHERE user_id = ? AND status IN ('new','processing','assigned')
             ORDER BY created_at DESC"
        );
        $stmt->execute([$user['id']]);
        $activeOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 5) Статистика по рефералам
        // Количество пользователей, пришедших по ссылке
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM users WHERE referred_by = ?");
        $stmt->execute([$user['id']]);
        $refUsers = (int)$stmt->fetchColumn();

        // Количество выполненных заказов от таких пользователей
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*)
             FROM orders o
             JOIN users u ON u.id = o.user_id
             WHERE u.referred_by = ? AND o.status = 'delivered'"
        );
        $stmt->execute([$user['id']]);
        $refOrders = (int)$stmt->fetchColumn();

        // Сумма начисленных баллов по реферальным заказам
        $stmt = $this->pdo->prepare(
            "SELECT SUM(o.points_accrued)
             FROM orders o
             JOIN users u ON u.id = o.user_id
             WHERE u.referred_by = ? AND o.status = 'delivered'"
        );
        $stmt->execute([$user['id']]);
        $refPoints = (int)($stmt->fetchColumn() ?: 0);

        // 6) Рендерим клиентский шаблон через общий layout,
        // чтобы подключились все стили из /src/Views/layouts/main.php
        view('client/profile', [
            'user'         => $user,
            'address'      => $address,
            'transactions' => $transactions,
            'activeOrders' => $activeOrders,
            'refStats'     => [
                'users'  => $refUsers,
                'orders' => $refOrders,
                'points' => $refPoints,
            ],
        ]);
    }

    /**
     * Сохранение нового адреса (POST /profile)
     */
    public function saveAddress(): void
    {
        $authUser = Auth::user();
        if (!$authUser) {
            header('Location: /login');
            exit;
        }

        $address = trim($_POST['address'] ?? '');
        if ($address === '') {
            header('Location: /profile?error=Введите+корректный+адрес');
            exit;
        }

        $stmt = $this->pdo->prepare(
            "INSERT INTO addresses (user_id, street, created_at)
             VALUES (?, ?, NOW())"
        );
        $stmt->execute([$authUser['id'], $address]);

        header('Location: /profile?success=Адрес+сохранён');
        exit;
    }

    /**
     * Список пользователей (админ)
     */
    public function index(): void
    {
        $stmt = $this->pdo->query(
          "SELECT u.id, u.name, u.phone, u.role, u.created_at,
                  u.referral_code, u.points_balance,
                  ref.name AS referrer_name
           FROM users u
           LEFT JOIN users ref ON ref.id = u.referred_by
           ORDER BY u.created_at DESC"
        );
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        viewAdmin('users/index', [
          'pageTitle' => 'Пользователи',
          'users'     => $users,
        ]);
    }

    /**
     * Форма редактирования пользователя (админ)
     */
    public function edit(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        $stmt = $this->pdo->prepare(
            "SELECT id, name, phone, role, is_blocked 
             FROM users 
             WHERE id = ?"
        );
        $stmt->execute([$id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        viewAdmin('users/edit', [
          'pageTitle' => 'Редактировать пользователя',
          'user'      => $user,
        ]);
    }

    /**
     * Обновление пользователя (POST, админ)
     */
    public function save(): void
    {
        $id        = (int)($_POST['id'] ?? 0);
        $role      = $_POST['role'] ?? 'client';
        $isBlocked = isset($_POST['is_blocked']) ? 1 : 0;

        $stmt = $this->pdo->prepare(
          "UPDATE users 
           SET role = ?, is_blocked = ? 
           WHERE id = ?"
        );
        $stmt->execute([$role, $isBlocked, $id]);

        header('Location: /admin/users');
        exit;
    }
}
