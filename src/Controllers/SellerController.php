<?php
namespace App\Controllers;

use PDO;

class SellerController
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function dashboard(): void
    {
        $sellerId = $_SESSION['user_id'] ?? 0;
        $mode  = $_GET['mode'] ?? 'month';
        $year  = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
        $month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('n');

        $labels = [];
        $ordersData = [];
        $revenueData = [];

        if ($mode === 'year') {
            $stmt = $this->pdo->prepare(
                "SELECT MONTH(created_at) AS m, COUNT(DISTINCT order_id) AS orders, SUM(gross_amount) AS revenue
                 FROM seller_payouts WHERE seller_id = ? AND YEAR(created_at) = ? GROUP BY m"
            );
            $stmt->execute([$sellerId, $year]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $ordersByMonth = [];
            $revenueByMonth = [];
            foreach ($rows as $r) {
                $m = (int)$r['m'];
                $ordersByMonth[$m] = (int)$r['orders'];
                $revenueByMonth[$m] = (float)$r['revenue'];
            }
            for ($m = 1; $m <= 12; $m++) {
                $labels[] = $m;
                $ordersData[] = $ordersByMonth[$m] ?? 0;
                $revenueData[] = $revenueByMonth[$m] ?? 0;
            }
        } else {
            $start = sprintf('%04d-%02d-01', $year, $month);
            $stmt = $this->pdo->prepare(
                "SELECT DATE(created_at) AS d, COUNT(DISTINCT order_id) AS orders, SUM(gross_amount) AS revenue
                 FROM seller_payouts WHERE seller_id = ? AND created_at >= ? AND created_at < DATE_ADD(?, INTERVAL 1 MONTH)
                 GROUP BY d"
            );
            $stmt->execute([$sellerId, $start, $start]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $ordersByDay = [];
            $revenueByDay = [];
            foreach ($rows as $r) {
                $ordersByDay[$r['d']] = (int)$r['orders'];
                $revenueByDay[$r['d']] = (float)$r['revenue'];
            }
            $days = (int)date('t', strtotime($start));
            for ($d = 1; $d <= $days; $d++) {
                $dateStr = sprintf('%04d-%02d-%02d', $year, $month, $d);
                $labels[] = $d;
                $ordersData[] = $ordersByDay[$dateStr] ?? 0;
                $revenueData[] = $revenueByDay[$dateStr] ?? 0;
            }
        }

        $chartData = [
            'labels' => $labels,
            'orders' => $ordersData,
            'revenue' => $revenueData,
            'users' => array_fill(0, count($labels), 0),
        ];

        viewAdmin('dashboard', [
            'pageTitle' => 'Статистика продаж',
            'chartData' => $chartData,
            'mode'      => $mode,
            'year'      => $year,
            'month'     => $month,
        ]);
    }

    public function orders(): void
    {
        $sellerId = (int)($_SESSION['user_id'] ?? 0);

        $stmt = $this->pdo->prepare(
            "SELECT o.id, o.status, o.points_used, o.delivery_date,\n"
            . "       d.time_from AS slot_from, d.time_to AS slot_to,\n"
            . "       u.name AS client_name, u.phone, a.street AS address,\n"
            . "       sp.gross_amount AS seller_subtotal,\n"
            . "       sp.commission_rate, sp.commission_amount, sp.payout_amount,\n"
            . "       (SELECT SUM(quantity * unit_price) FROM order_items WHERE order_id = o.id) AS order_total\n"
            . "FROM seller_payouts sp\n"
            . "JOIN orders o ON o.id = sp.order_id\n"
            . "JOIN users u ON u.id = o.user_id\n"
            . "LEFT JOIN addresses a ON a.id = o.address_id\n"
            . "LEFT JOIN delivery_slots d ON d.id = o.slot_id\n"
            . "WHERE sp.seller_id = ?\n"
            . "ORDER BY o.delivery_date DESC, d.time_from DESC"
        );
        $stmt->execute([$sellerId]);
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $orderIds = array_column($orders, 'id');
        $itemsByOrder = [];
        if ($orderIds) {
            $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
            $iStmt = $this->pdo->prepare(
                "SELECT oi.order_id, oi.quantity, oi.boxes, oi.unit_price,\n"
                . "       t.name AS product_name, p.variety, p.box_size, p.box_unit\n"
                . "FROM order_items oi\n"
                . "JOIN products p ON p.id = oi.product_id\n"
                . "JOIN product_types t ON t.id = p.product_type_id\n"
                . "WHERE oi.order_id IN ($placeholders) AND p.seller_id = ?\n"
                . "ORDER BY oi.order_id"
            );
            $iStmt->execute([...$orderIds, $sellerId]);
            while ($row = $iStmt->fetch(PDO::FETCH_ASSOC)) {
                $itemsByOrder[$row['order_id']][] = $row;
            }
        }

        foreach ($orders as &$o) {
            $o['items'] = $itemsByOrder[$o['id']] ?? [];
            $orderTotal = (float)($o['order_total'] ?? 0);
            $sellerSubtotal = (float)($o['seller_subtotal'] ?? 0);
            $pointsUsed = (float)($o['points_used'] ?? 0);
            $o['points_applied'] = $orderTotal > 0
                ? round($pointsUsed * $sellerSubtotal / $orderTotal, 2)
                : 0.0;
            $o['commission'] = (float)($o['commission_amount'] ?? 0);
            $o['payout'] = (float)($o['payout_amount'] ?? 0);
            unset($o['order_total'], $o['commission_amount'], $o['payout_amount']);
        }
        unset($o);

        viewAdmin('seller_orders', [
            'pageTitle' => 'Заказы',
            'orders'    => $orders,
        ]);
    }
}
