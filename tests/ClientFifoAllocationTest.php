<?php
namespace Tests;

use App\Controllers\ClientController;
use PDO;
use PHPUnit\Framework\TestCase;

class ClientFifoAllocationTest extends TestCase
{
    private PDO $pdo;
    private ClientController $controller;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->pdo->exec('CREATE TABLE purchase_batches (
            id INTEGER PRIMARY KEY,
            product_id INTEGER,
            purchased_at TEXT,
            status TEXT,
            boxes_free REAL DEFAULT 0,
            boxes_discount REAL DEFAULT 0
        )');

        $this->controller = new ClientController($this->pdo);
    }

    public function testAllocateFifoBatchesSplitsAcrossOldestBatches(): void
    {
        $this->pdo->exec("INSERT INTO purchase_batches (id, product_id, purchased_at, status, boxes_free) VALUES
            (10, 1, '2026-05-01 10:00:00', 'active', 3),
            (11, 1, '2026-05-02 10:00:00', 'active', 5)");

        $allocations = $this->invokeAllocate(1, 6.0, 'instant');

        $this->assertCount(2, $allocations);
        $this->assertSame(10, $allocations[0]['batch_id']);
        $this->assertSame(3.0, $allocations[0]['boxes']);
        $this->assertSame(11, $allocations[1]['batch_id']);
        $this->assertSame(3.0, $allocations[1]['boxes']);
    }

    public function testAllocateFifoBatchesThrowsOnInsufficientStock(): void
    {
        $this->pdo->exec("INSERT INTO purchase_batches (id, product_id, purchased_at, status, boxes_free) VALUES
            (10, 1, '2026-05-01 10:00:00', 'active', 1)");

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Недостаточно остатков партии для отгрузки по FIFO.');

        $this->invokeAllocate(1, 2.0, 'instant');
    }

    /** @return array<int, array{batch_id:int, boxes:float}> */
    private function invokeAllocate(int $productId, float $requiredBoxes, string $mode): array
    {
        $method = new \ReflectionMethod(ClientController::class, 'allocateFifoBatches');
        $method->setAccessible(true);

        /** @var array<int, array{batch_id:int, boxes:float}> $result */
        $result = $method->invoke($this->controller, $productId, $requiredBoxes, $mode);
        return $result;
    }
}
