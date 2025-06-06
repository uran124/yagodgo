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

        $stats = [
            'today_revenue' => $todayRevenue,
            'today_orders'  => $todayOrders,
            'average_check' => $averageCheck,
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

        // Данные для графика (заглушка)
        $chartData = [
            'labels' => [],
            'values' => [],
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
