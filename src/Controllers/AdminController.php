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
        $stats = [
            'today_revenue' => '—',
            'today_orders'  => '—',
            'average_check' => '—',
        ];
        $chartData = [
            'labels' => [],  // метки для графика
            'values' => [],  // значения
        ];

        // Вызываем viewAdmin, который обернёт содержимое в admin_main.php
        viewAdmin('dashboard', [
            'pageTitle' => 'Панель управления',
            'stats'     => $stats,
            'chartData' => $chartData,
        ]);
    }
}
