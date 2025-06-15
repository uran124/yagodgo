<?php
namespace App\Controllers;

use PDO;

class AdminController
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Главное окно админки (Dashboard)
     */
    public function dashboard(): void
    {
        $mode  = $_GET['mode'] ?? 'month';
        $year  = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
        $month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('n');

        $labels      = [];
        $ordersData  = [];
        $revenueData = [];
        $usersData   = [];

        if ($mode === 'year') {
            // Статистика по месяцам выбранного года
            $stmt = $this->pdo->prepare(
                "SELECT MONTH(created_at) AS m, COUNT(*) AS orders, SUM(total_amount) AS revenue
                 FROM orders WHERE YEAR(created_at) = ? GROUP BY m"
            );
            $stmt->execute([$year]);
            $orderRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $ordersByMonth  = [];
            $revenueByMonth = [];
            foreach ($orderRows as $row) {
                $m = (int)$row['m'];
                $ordersByMonth[$m]  = (int)$row['orders'];
                $revenueByMonth[$m] = (int)$row['revenue'];
            }

            $stmt = $this->pdo->prepare(
                "SELECT MONTH(created_at) AS m, COUNT(*) AS users
                 FROM users WHERE YEAR(created_at) = ? GROUP BY m"
            );
            $stmt->execute([$year]);
            $userRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $usersByMonth = [];
            foreach ($userRows as $row) {
                $m = (int)$row['m'];
                $usersByMonth[$m] = (int)$row['users'];
            }

            for ($m = 1; $m <= 12; $m++) {
                $labels[]      = $m;
                $ordersData[]  = $ordersByMonth[$m]  ?? 0;
                $revenueData[] = $revenueByMonth[$m] ?? 0;
                $usersData[]   = $usersByMonth[$m]   ?? 0;
            }
        } else {
            // Статистика по дням выбранного месяца
            $start = sprintf('%04d-%02d-01', $year, $month);

            $stmt = $this->pdo->prepare(
                "SELECT DATE(created_at) AS d, COUNT(*) AS orders, SUM(total_amount) AS revenue
                 FROM orders
                 WHERE created_at >= ? AND created_at < DATE_ADD(?, INTERVAL 1 MONTH)
                 GROUP BY d"
            );
            $stmt->execute([$start, $start]);
            $orderRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $ordersByDay  = [];
            $revenueByDay = [];
            foreach ($orderRows as $row) {
                $ordersByDay[$row['d']]  = (int)$row['orders'];
                $revenueByDay[$row['d']] = (int)$row['revenue'];
            }

            $stmt = $this->pdo->prepare(
                "SELECT DATE(created_at) AS d, COUNT(*) AS users
                 FROM users
                 WHERE created_at >= ? AND created_at < DATE_ADD(?, INTERVAL 1 MONTH)
                 GROUP BY d"
            );
            $stmt->execute([$start, $start]);
            $userRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $usersByDay = [];
            foreach ($userRows as $row) {
                $usersByDay[$row['d']] = (int)$row['users'];
            }

            $daysInMonth = (int)date('t', strtotime($start));
            for ($d = 1; $d <= $daysInMonth; $d++) {
                $dateStr = sprintf('%04d-%02d-%02d', $year, $month, $d);
                $labels[]      = $d;
                $ordersData[]  = $ordersByDay[$dateStr]  ?? 0;
                $revenueData[] = $revenueByDay[$dateStr] ?? 0;
                $usersData[]   = $usersByDay[$dateStr]   ?? 0;
            }
        }

        $chartData = [
            'labels'  => $labels,
            'orders'  => $ordersData,
            'revenue' => $revenueData,
            'users'   => $usersData,
        ];

        viewAdmin('dashboard', [
            'pageTitle' => 'Панель управления',
            'chartData' => $chartData,
            'mode'      => $mode,
            'year'      => $year,
            'month'     => $month,
        ]);
    }
}
