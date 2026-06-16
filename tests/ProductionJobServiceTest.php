<?php
use PHPUnit\Framework\TestCase;
use App\Services\ProductionJobService;

class ProductionJobServiceTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec('CREATE TABLE production_jobs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            order_id INTEGER NOT NULL,
            product_id INTEGER NULL,
            executor_type TEXT NULL,
            executor_id INTEGER NULL,
            fulfillment_model TEXT NOT NULL DEFAULT "by_berrygo_on_site",
            production_location TEXT NOT NULL DEFAULT "shop",
            status TEXT NOT NULL DEFAULT "new",
            production_deadline TEXT NULL,
            handover_deadline TEXT NULL,
            bonus_type TEXT NOT NULL DEFAULT "internal_bonus",
            bonus_value REAL NOT NULL DEFAULT 0,
            bonus_amount_locked REAL NOT NULL DEFAULT 0,
            materials_required TEXT NULL,
            materials_delivery_required INTEGER NOT NULL DEFAULT 0,
            materials_delivery_cost REAL NOT NULL DEFAULT 0,
            result_delivery_required INTEGER NOT NULL DEFAULT 0,
            result_delivery_cost REAL NOT NULL DEFAULT 0,
            manager_comment TEXT NULL,
            assigned_at TEXT NULL,
            started_at TEXT NULL,
            photo_uploaded_at TEXT NULL,
            approved_at TEXT NULL,
            handed_over_at TEXT NULL,
            completed_at TEXT NULL,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL
        )');
        $this->pdo->exec('CREATE TABLE production_job_events (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            job_id INTEGER NOT NULL,
            order_id INTEGER NOT NULL,
            from_status TEXT NULL,
            to_status TEXT NOT NULL,
            changed_by_user_id INTEGER NULL,
            changed_by_role TEXT NULL,
            comment TEXT NULL,
            created_at TEXT NOT NULL
        )');
    }

    public function testCreateStoresProductionJobInsideOrderFlowAndRecordsEvent(): void
    {
        $service = new ProductionJobService($this->pdo);

        $jobId = $service->create([
            'order_id' => 77,
            'product_id' => 12,
            'fulfillment_model' => 'by_berrygo_remote',
            'production_location' => 'remote',
            'bonus_value' => 10,
            'materials_delivery_required' => true,
            'materials_delivery_cost' => 350,
        ]);

        $job = $this->pdo->query('SELECT order_id, product_id, status, fulfillment_model, production_location, materials_delivery_required, materials_delivery_cost FROM production_jobs WHERE id = ' . $jobId)->fetch(PDO::FETCH_ASSOC);
        $this->assertSame(77, (int)$job['order_id']);
        $this->assertSame(12, (int)$job['product_id']);
        $this->assertSame('new', $job['status']);
        $this->assertSame('by_berrygo_remote', $job['fulfillment_model']);
        $this->assertSame('remote', $job['production_location']);
        $this->assertSame(1, (int)$job['materials_delivery_required']);
        $this->assertSame(350.0, (float)$job['materials_delivery_cost']);

        $event = $this->pdo->query('SELECT job_id, order_id, from_status, to_status, comment FROM production_job_events')->fetch(PDO::FETCH_ASSOC);
        $this->assertSame($jobId, (int)$event['job_id']);
        $this->assertSame(77, (int)$event['order_id']);
        $this->assertNull($event['from_status']);
        $this->assertSame('new', $event['to_status']);
        $this->assertSame('production_job_created', $event['comment']);
    }

    public function testAssignAtomicallyAllowsOnlyFirstExecutor(): void
    {
        $service = new ProductionJobService($this->pdo);
        $jobId = $service->create(['order_id' => 88]);

        $this->assertTrue($service->assignAtomically($jobId, 5, 'internal_staff', 1, 'admin'));
        $this->assertFalse($service->assignAtomically($jobId, 6, 'internal_staff', 2, 'manager'));

        $job = $this->pdo->query('SELECT executor_id, executor_type, status FROM production_jobs WHERE id = ' . $jobId)->fetch(PDO::FETCH_ASSOC);
        $this->assertSame(5, (int)$job['executor_id']);
        $this->assertSame('internal_staff', $job['executor_type']);
        $this->assertSame('assigned', $job['status']);

        $events = $this->pdo->query('SELECT from_status, to_status, comment FROM production_job_events ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
        $this->assertCount(2, $events);
        $this->assertSame('new', $events[1]['from_status']);
        $this->assertSame('assigned', $events[1]['to_status']);
        $this->assertSame('production_job_assigned', $events[1]['comment']);
    }
}
