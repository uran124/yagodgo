<?php
namespace App\Models;

use PDO;

class OrdersRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Fetch orders list for admin/manager index.
     *
     * @return array<int, array<string, mixed>>
     */
    public function fetchOrdersForIndex(int $managerId, int $limit, int $offset): array
    {
        $sql = "SELECT o.id, o.status, o.total_amount, o.delivery_date,\n" .
               "       o.points_used, o.coupon_code, o.discount_applied,\n" .
               "       o.slot_id, d.time_from AS slot_from, d.time_to AS slot_to,\n" .
               "       u.name AS client_name, u.phone, a.street AS address,\n" .
               "       o.created_at, o.comment\n" .
               "FROM orders o\n" .
               "JOIN users u ON u.id = o.user_id\n" .
               "LEFT JOIN addresses a ON a.id = o.address_id\n" .
               "LEFT JOIN delivery_slots d ON d.id = o.slot_id";
        $params = [];
        if ($managerId > 0) {
            $sql .= " WHERE u.referred_by = ?";
            $params[] = $managerId;
        }
        $sql .= " ORDER BY o.delivery_date DESC, d.time_from DESC\n" .
                " LIMIT ? OFFSET ?";
        $params[] = (int)$limit;
        $params[] = (int)$offset;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $orders ?: [];
    }

    public function countOrdersForIndex(int $managerId): int
    {
        $sql = "SELECT COUNT(*)\n" .
               "FROM orders o\n" .
               "JOIN users u ON u.id = o.user_id";
        $params = [];
        if ($managerId > 0) {
            $sql .= " WHERE u.referred_by = ?";
            $params[] = $managerId;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Enrich orders with items and coupon metadata.
     *
     * @param array<int, array<string, mixed>> $orders
     * @return array<int, array<string, mixed>>
     */
    public function hydrateOrders(array $orders): array
    {
        if (!$orders) {
            return [];
        }

        $orderIds = array_column($orders, 'id');
        $itemsByOrder = [];
        if ($orderIds) {
            $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
            $iStmt = $this->pdo->prepare(
                "SELECT oi.order_id, oi.quantity, oi.boxes, oi.unit_price,\n" .
                "       t.name AS product_name, p.variety, p.box_size, p.box_unit\n" .
                "FROM order_items oi\n" .
                "JOIN products p ON p.id = oi.product_id\n" .
                "JOIN product_types t ON t.id = p.product_type_id\n" .
                "WHERE oi.order_id IN ($placeholders)\n" .
                "ORDER BY oi.order_id"
            );
            $iStmt->execute($orderIds);
            while ($row = $iStmt->fetch(PDO::FETCH_ASSOC)) {
                $itemsByOrder[$row['order_id']][] = $row;
            }
        }

        $codes = array_values(array_filter(
            array_column($orders, 'coupon_code'),
            fn($c) => $c !== null && $c !== ''
        ));
        $couponInfo = [];
        if ($codes) {
            $placeholders = implode(',', array_fill(0, count($codes), '?'));
            $cStmt = $this->pdo->prepare(
                "SELECT code, type, discount, points FROM coupons WHERE code IN ($placeholders)"
            );
            $cStmt->execute($codes);
            foreach ($cStmt->fetchAll(PDO::FETCH_ASSOC) as $c) {
                $couponInfo[$c['code']] = $c;
            }
        }

        $refStmt = $this->pdo->prepare("SELECT id FROM users WHERE referral_code = ?");

        foreach ($orders as &$o) {
            $o['items'] = $itemsByOrder[$o['id']] ?? [];

            $coupon = null;
            if (!empty($o['coupon_code'])) {
                $coupon = $couponInfo[$o['coupon_code']] ?? null;
                if (!$coupon) {
                    $refStmt->execute([$o['coupon_code']]);
                    if ($refStmt->fetch()) {
                        $coupon = [
                            'code' => $o['coupon_code'],
                            'type' => 'discount',
                            'discount' => 10,
                        ];
                    }
                }
            }
            $o['coupon'] = $coupon;

            $pointsFromBalance = (int)($o['points_used'] ?? 0);
            if ($coupon && $coupon['type'] === 'points') {
                $pointsFromBalance = max(0, $pointsFromBalance - (int)$coupon['points']);
            }
            $o['points_from_balance'] = $pointsFromBalance;
            $o['coupon_discount'] = (int)$o['discount_applied'];
        }
        unset($o);

        return $orders;
    }
}
