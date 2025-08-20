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
}
