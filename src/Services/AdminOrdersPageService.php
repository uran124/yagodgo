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
            'purchaseBatches' => $this->fetchCreateFormPurchaseBatches(),
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
            "SELECT p.id, t.name AS product, p.variety, p.price, p.box_size,\n" .
            "       COALESCE((\n" .
            "           SELECT CASE WHEN pb.status = 'planned' THEN pb.preorder_price_per_box ELSE pb.instant_price_per_box END\n" .
            "           FROM purchase_batches pb\n" .
            "           WHERE pb.product_id = p.id\n" .
            "             AND (\n" .
            "               (pb.status IN ('purchased', 'arrived') AND pb.boxes_free > 0 AND pb.instant_price_per_box > 0)\n" .
            "               OR (pb.status = 'planned' AND (COALESCE(NULLIF(pb.boxes_total, 0), pb.boxes_free + pb.boxes_reserved) - pb.boxes_reserved) > 0 AND pb.preorder_price_per_box > 0)\n" .
            "             )\n" .
            "           ORDER BY CASE WHEN pb.status IN ('purchased', 'arrived') THEN 1 ELSE 2 END, pb.purchased_at ASC, pb.id ASC\n" .
            "           LIMIT 1\n" .
            "       ), p.price * COALESCE(NULLIF(p.box_size, 0), 1)) AS price_per_box\n" .
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
    private function fetchCreateFormPurchaseBatches(): array
    {
        $plannedAvailableExpr = "(COALESCE(NULLIF(pb.boxes_total, 0), pb.boxes_free + pb.boxes_reserved) - pb.boxes_reserved)";
        $availableExpr = "CASE WHEN pb.status = 'planned' THEN CASE WHEN {$plannedAvailableExpr} > 0 THEN {$plannedAvailableExpr} ELSE 0 END ELSE pb.boxes_free END";

        $stmt = $this->pdo->query(
            "SELECT pb.id AS purchase_batch_id, pb.product_id, pb.status, pb.purchased_at,\n" .
            "       pb.box_size_snapshot, pb.box_unit_snapshot, pb.boxes_free, pb.boxes_total, pb.boxes_reserved,\n" .
            "       {$availableExpr} AS available_boxes,\n" .
            "       pb.instant_price_per_box, pb.preorder_price_per_box,\n" .
            "       t.name AS product, p.variety, p.image_path, p.box_size, p.box_unit\n" .
            "FROM purchase_batches pb\n" .
            "JOIN products p ON p.id = pb.product_id\n" .
            "JOIN product_types t ON t.id = p.product_type_id\n" .
            "WHERE p.is_active = 1\n" .
            "  AND (\n" .
            "    (pb.status IN ('purchased', 'arrived') AND pb.boxes_free > 0 AND pb.instant_price_per_box > 0)\n" .
            "    OR (pb.status = 'planned' AND {$plannedAvailableExpr} > 0 AND pb.preorder_price_per_box > 0)\n" .
            "  )\n" .
            "ORDER BY pb.purchased_at ASC, pb.status ASC, t.name ASC, p.variety ASC"
        );

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            $isPreorder = ($row['status'] ?? '') === 'planned';
            $row['stock_mode'] = $isPreorder ? 'preorder' : 'instant';
            $row['mode_group'] = $isPreorder ? 'preorder' : 'in_stock';
            $row['mode_label'] = $isPreorder ? 'Предзаказ' : 'В наличии';
            $row['available_boxes'] = (float)($row['available_boxes'] ?? 0);
            $row['price_per_box'] = (float)($isPreorder ? $row['preorder_price_per_box'] : $row['instant_price_per_box']);
            $row['batch_date'] = substr((string)($row['purchased_at'] ?? ''), 0, 10);
            $row['display_box_size'] = (float)($row['box_size_snapshot'] ?: ($row['box_size'] ?: 1));
            $row['display_box_unit'] = (string)($row['box_unit_snapshot'] ?: ($row['box_unit'] ?? ''));
        }
        unset($row);

        return $rows;
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
