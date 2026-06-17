<?php
namespace App\Controllers;

use App\Services\ProductionDashboardService;
use PDO;

class ProductionController
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function index(): void
    {
        $data = (new ProductionDashboardService($this->pdo))->buildIndexData($_GET['status'] ?? null);

        viewAdmin('production/index', array_merge([
            'pageTitle' => 'Производство',
        ], $data));
    }
}
