<?php
namespace App\Services;

use App\Models\OrdersRepository;
use PDO;

class AdminOrdersPageService
{
    private PDO $pdo;
    private OrdersRepository $ordersRepository;

    public function __construct(PDO $pdo, ?OrdersRepository $ordersRepository = null)
    {
        $this->pdo = $pdo;
        $this->ordersRepository = $ordersRepository ?? new OrdersRepository($pdo);
    }

    /**
     * @return array<string, mixed>
     */
    public function buildIndexData(int $managerId, int $page, int $perPage): array
    {
        $page = max(1, $page);
        $offset = ($page - 1) * $perPage;

        $totalOrders = $this->ordersRepository->countOrdersForIndex($managerId);
        $totalPages = max(1, (int)ceil($totalOrders / $perPage));
        if ($page > $totalPages) {
            $page = $totalPages;
            $offset = ($page - 1) * $perPage;
        }

        $orders = $this->ordersRepository->hydrateOrders(
            $this->ordersRepository->fetchOrdersForIndex($managerId, $perPage, $offset)
        );

        $managersStmt = $this->pdo->query("SELECT id, name FROM users WHERE role = 'manager' ORDER BY name");

        return [
            'orders' => $orders,
            'managers' => $managersStmt->fetchAll(PDO::FETCH_ASSOC),
            'selectedManager' => $managerId,
            'page' => $page,
            'totalPages' => $totalPages,
            'totalOrders' => $totalOrders,
            'perPage' => $perPage,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findShowData(int $id): ?array
    {
        $order = $this->fetchOrder($id);
        if (!$order) {
            return null;
        }

        $coupon = $this->fetchCouponInfo($order['coupon_code'] ?? null);
        $pointsFromBalance = (int)($order['points_used'] ?? 0);
        if ($coupon && $coupon['type'] === 'points') {
            $pointsFromBalance = max(0, $pointsFromBalance - (int)$coupon['points']);
        }

        return [
            'order' => $order,
            'items' => $this->fetchOrderItems($id),
            'transactions' => $this->fetchTransactions($id),
            'coupon' => $coupon,
            'pointsFromBalance' => $pointsFromBalance,
            'addresses' => $this->fetchAddresses((int)$order['user_id']),
            'slots' => $this->fetchSlots(),
            'products' => $this->fetchActiveProducts(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function buildCreateData(array $session = []): array
    {
        return [
            'products' => $this->fetchCreateFormProducts(),
            'slots' => $this->fetchSlots(),
            'debugData' => $session['debug_order_data'] ?? [],
            'today' => date('Y-m-d'),
        ];
    }

    /**
     * @return array<string, mixed>|false
     */
    private function fetchOrder(int $id)
    {
        $stmt = $this->pdo->prepare(
            "SELECT o.*, d.time_from AS slot_from, d.time_to AS slot_to,\n" .
            "       u.name AS client_name, u.phone, u.has_used_referral_coupon, a.street AS address\n" .
            "FROM orders o\n" .
            "JOIN users u ON u.id = o.user_id\n" .
            "JOIN addresses a ON a.id = o.address_id\n" .
            "LEFT JOIN delivery_slots d ON d.id = o.slot_id\n" .
            "WHERE o.id = ?"
        );
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchOrderItems(int $orderId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT oi.product_id, oi.quantity, oi.boxes, oi.unit_price, t.name AS product_name, p.unit, p.variety, p.box_size, p.box_unit\n" .
            "FROM order_items oi\n" .
            "JOIN products p ON p.id = oi.product_id\n" .
            "JOIN product_types t ON t.id = p.product_type_id\n" .
            "WHERE oi.order_id = ?"
        );
        $stmt->execute([$orderId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchTransactions(int $orderId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT pt.*\n" .
            "FROM points_transactions pt\n" .
            "WHERE pt.order_id = ?\n" .
            "ORDER BY pt.created_at DESC"
        );
        $stmt->execute([$orderId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchAddresses(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT id, street FROM addresses WHERE user_id = ? ORDER BY is_primary DESC, created_at ASC"
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchSlots(): array
    {
        $stmt = $this->pdo->query("SELECT id, time_from, time_to FROM delivery_slots ORDER BY time_from");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchCouponInfo(?string $couponCode): ?array
    {
        if ($couponCode === null || $couponCode === '') {
            return null;
        }

        $couponStmt = $this->pdo->prepare(
            "SELECT code, type, discount, points FROM coupons WHERE code = ?"
        );
        $couponStmt->execute([$couponCode]);
        $coupon = $couponStmt->fetch(PDO::FETCH_ASSOC) ?: null;
        if ($coupon) {
            return $coupon;
        }

        $refStmt = $this->pdo->prepare("SELECT id FROM users WHERE referral_code = ?");
        $refStmt->execute([$couponCode]);
        if (!$refStmt->fetch()) {
            return null;
        }

        return [
            'code' => $couponCode,
            'type' => 'discount',
            'discount' => 10,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchActiveProducts(): array
    {
        $stmt = $this->pdo->query(
            "SELECT p.id, t.name AS product, p.variety\n" .
            "FROM products p\n" .
            "JOIN product_types t ON t.id = p.product_type_id\n" .
            "WHERE p.is_active = 1\n" .
            "ORDER BY t.name"
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchCreateFormProducts(): array
    {
        $stmt = $this->pdo->query(
            "SELECT p.id, t.name AS product, p.variety, p.price, p.image_path, p.box_size, p.box_unit\n" .
            "FROM products p\n" .
            "JOIN product_types t ON t.id = p.product_type_id\n" .
            "WHERE p.is_active = 1\n" .
            "ORDER BY t.name"
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
