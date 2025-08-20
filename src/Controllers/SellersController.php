<?php
namespace App\Controllers;

use PDO;

class SellersController
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * List all sellers
     */
    public function index(): void
    {
        $stmt = $this->pdo->query("SELECT id, company_name, name, phone, rub_balance FROM users WHERE role = 'seller' ORDER BY company_name");
        $sellers = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        viewAdmin('sellers/index', [
            'pageTitle' => 'Селлеры',
            'sellers'   => $sellers,
        ]);
    }

    /**
     * Show seller profile with orders
     */
    public function show(int $id): void
    {
        $stmt = $this->pdo->prepare("SELECT id, company_name, pickup_address, name, phone, rub_balance FROM users WHERE id = ? AND role = 'seller'");
        $stmt->execute([$id]);
        $seller = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$seller) {
            http_response_code(404);
            echo 'Селлер не найден';
            return;
        }

        // Orders for seller (similar to SellerController::orders)
        $oStmt = $this->pdo->prepare(
            "SELECT o.id, o.status, o.points_used, o.delivery_date,\n" .
            "       d.time_from AS slot_from, d.time_to AS slot_to,\n" .
            "       u.name AS client_name, u.phone, a.street AS address,\n" .
            "       SUM(oi.quantity * oi.unit_price) AS seller_subtotal,\n" .
            "       (SELECT SUM(quantity * unit_price) FROM order_items WHERE order_id = o.id) AS order_total\n" .
            "FROM orders o\n" .
            "JOIN order_items oi ON oi.order_id = o.id\n" .
            "JOIN products p ON p.id = oi.product_id\n" .
            "JOIN users u ON u.id = o.user_id\n" .
            "LEFT JOIN addresses a ON a.id = o.address_id\n" .
            "LEFT JOIN delivery_slots d ON d.id = o.slot_id\n" .
            "WHERE p.seller_id = ?\n" .
            "GROUP BY o.id, o.status, o.points_used, o.delivery_date, d.time_from, d.time_to, u.name, u.phone, a.street\n" .
            "ORDER BY o.delivery_date DESC, d.time_from DESC"
        );
        $oStmt->execute([$id]);
        $orders = $oStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

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
                "WHERE oi.order_id IN ($placeholders) AND p.seller_id = ?\n" .
                "ORDER BY oi.order_id"
            );
            $iStmt->execute([...$orderIds, $id]);
            while ($row = $iStmt->fetch(PDO::FETCH_ASSOC)) {
                $itemsByOrder[$row['order_id']][] = $row;
            }
        }

        foreach ($orders as &$o) {
            $o['items'] = $itemsByOrder[$o['id']] ?? [];
            $orderTotal = (float)($o['order_total'] ?? 0);
            $sellerSubtotal = (float)($o['seller_subtotal'] ?? 0);
            $pointsUsed = (float)($o['points_used'] ?? 0);
            $o['commission_rate'] = 30.0;
            $o['commission'] = round($sellerSubtotal * $o['commission_rate'] / 100, 2);
            $o['payout'] = $sellerSubtotal - $o['commission'];
            $o['points_applied'] = $orderTotal > 0
                ? round($pointsUsed * $sellerSubtotal / $orderTotal, 2)
                : 0.0;
            unset($o['order_total']);
        }
        unset($o);

        viewAdmin('sellers/show', [
            'pageTitle' => 'Профиль селлера',
            'seller'    => $seller,
            'orders'    => $orders,
        ]);
    }
}
