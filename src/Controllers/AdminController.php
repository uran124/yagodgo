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
        // Здесь вы можете подготовить любые данные для дашборда:
        
        
        
                // === Статистика по заказам ===

        // Сегодняшняя дата
        $today = date('Y-m-d');

        // Сумма выручки за сегодня
        $stmt = $this->pdo->prepare(
            "SELECT SUM(total_amount) FROM orders WHERE DATE(created_at) = ?"
        );
        $stmt->execute([$today]);
        $todayRevenue = (int)($stmt->fetchColumn() ?: 0);

        // Количество заказов за сегодня
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM orders WHERE DATE(created_at) = ?"
        );
        $stmt->execute([$today]);
        $todayOrders = (int)($stmt->fetchColumn() ?: 0);

        // Средний чек (по всем заказам)
        $stmt = $this->pdo->query(
            "SELECT AVG(total_amount) FROM orders"
        );
        $averageCheck = (int)($stmt->fetchColumn() ?: 0);

        // Количество выполненных заказов за разные периоды
        $completedDay = (int)$this->pdo->query(
            "SELECT COUNT(*) FROM orders WHERE status = 'delivered' " .
            "AND created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)"
        )->fetchColumn();

        $completedWeek = (int)$this->pdo->query(
            "SELECT COUNT(*) FROM orders WHERE status = 'delivered' " .
            "AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
        )->fetchColumn();

        $completedMonth = (int)$this->pdo->query(
            "SELECT COUNT(*) FROM orders WHERE status = 'delivered' " .
            "AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
        )->fetchColumn();

        // Новые пользователи за периоды
        $newUsersDay = (int)$this->pdo->query(
            "SELECT COUNT(*) FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)"
        )->fetchColumn();

        $newUsersWeek = (int)$this->pdo->query(
            "SELECT COUNT(*) FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
        )->fetchColumn();

        $newUsersMonth = (int)$this->pdo->query(
            "SELECT COUNT(*) FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
        )->fetchColumn();
        
        
        
        
        
        
        $stats = [
            'today_revenue'   => $todayRevenue,
            'today_orders'    => $todayOrders,
            'average_check'   => $averageCheck,
            'completed_day'   => $completedDay,
            'completed_week'  => $completedWeek,
            'completed_month' => $completedMonth,
            'users_day'       => $newUsersDay,
            'users_week'      => $newUsersWeek,
            'users_month'     => $newUsersMonth,
        ];
        
        
        // === Необходимое количество товаров по принятым заказам ===
        $stmt = $this->pdo->query(
            "SELECT
                CONCAT(pt.name, ' ', p.variety) AS product,
                SUM(oi.quantity)                AS qty,
                p.unit                          AS unit
             FROM order_items oi
             JOIN orders o       ON o.id = oi.order_id
             JOIN products p     ON p.id = oi.product_id
             JOIN product_types pt ON pt.id = p.product_type_id
             WHERE o.status IN ('processing','assigned')
             GROUP BY oi.product_id, product, p.unit
             ORDER BY product"
        );
        $purchaseList = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // === Выручка по дням за последнюю неделю ===
        $rows = $this->pdo->query(
            "SELECT DATE(created_at) AS day, SUM(total_amount) AS revenue
             FROM orders
             WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
             GROUP BY day
             ORDER BY day"
        )->fetchAll(PDO::FETCH_ASSOC);

        $revenueByDay = [];
        foreach ($rows as $row) {
            $revenueByDay[$row['day']] = (int) $row['revenue'];
        }

        $labels = [];
        $values = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-$i days"));
            $labels[] = date('d.m', strtotime($date));
            $values[] = $revenueByDay[$date] ?? 0;
        }

        $chartData = [
            'labels' => $labels,
            'values' => $values,
        ];

        // Отображаем дашборд
        viewAdmin('dashboard', [
            'pageTitle'    => 'Панель управления',
            'stats'        => $stats,
            'chartData'    => $chartData,
            'purchaseList' => $purchaseList,
        ]);
    }
}
