<?php
use PHPUnit\Framework\TestCase;
use App\Services\ProductionDashboardService;

class ProductionDashboardServiceTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT)');
        $this->pdo->exec('CREATE TABLE orders (id INTEGER PRIMARY KEY, user_id INTEGER, status TEXT, delivery_date TEXT)');
        $this->pdo->exec('CREATE TABLE products (id INTEGER PRIMARY KEY, variety TEXT)');
        $this->pdo->exec('CREATE TABLE production_jobs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            order_id INTEGER NOT NULL,
            product_id INTEGER NULL,
            status TEXT NOT NULL DEFAULT "new",
            production_deadline TEXT NULL,
            created_at TEXT NOT NULL
        )');
        $this->pdo->exec("INSERT INTO users (id, name) VALUES (1, 'Client')");
        $this->pdo->exec("INSERT INTO orders (id, user_id, status, delivery_date) VALUES (10, 1, 'new', '2026-06-20')");
        $this->pdo->exec("INSERT INTO products (id, variety) VALUES (5, 'Набор 12 ягод')");
        $this->pdo->exec("INSERT INTO production_jobs (order_id, product_id, status, production_deadline, created_at) VALUES
            (10, 5, 'new', datetime('now', '+2 hours'), datetime('now')),
            (10, 5, 'in_progress', datetime('now', '-1 hour'), datetime('now')),
            (10, 5, 'completed', datetime('now', '-1 hour'), datetime('now'))");
    }

    public function testBuildIndexDataReturnsActiveSummaryAndJobs(): void
    {
        $data = (new ProductionDashboardService($this->pdo))->buildIndexData();

        $this->assertSame('', $data['statusFilter']);
        $this->assertSame(2, $data['summary']['all_active']);
        $this->assertSame(1, $data['summary']['new']);
        $this->assertSame(1, $data['summary']['in_progress']);
        $this->assertSame(1, $data['summary']['overdue']);
        $this->assertCount(2, $data['jobs']);
        $this->assertSame('Набор 12 ягод', $data['jobs'][0]['product_name']);
    }

    public function testBuildIndexDataFiltersOverdueJobs(): void
    {
        $data = (new ProductionDashboardService($this->pdo))->buildIndexData('overdue');

        $this->assertSame('overdue', $data['statusFilter']);
        $this->assertCount(1, $data['jobs']);
        $this->assertSame('in_progress', $data['jobs'][0]['status']);
    }
}
