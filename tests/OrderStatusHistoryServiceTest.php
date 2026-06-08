<?php
namespace Tests;

use App\Services\OrderStatusHistoryService;
use PDO;
use PHPUnit\Framework\TestCase;

class OrderStatusHistoryServiceTest extends TestCase
{
    public function testRecordPersistsStatusChange(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE order_status_history (id INTEGER PRIMARY KEY AUTOINCREMENT, order_id INTEGER NOT NULL, from_status TEXT NULL, to_status TEXT NOT NULL, changed_by_user_id INTEGER NULL, changed_by_role TEXT NULL, comment TEXT NULL, created_at TEXT NOT NULL)');

        (new OrderStatusHistoryService($pdo))->record(10, 'new', 'confirmed', 5, 'manager', 'Проверено');

        $row = $pdo->query('SELECT order_id, from_status, to_status, changed_by_user_id, changed_by_role, comment FROM order_status_history')->fetch(PDO::FETCH_ASSOC);
        $this->assertSame(10, (int)$row['order_id']);
        $this->assertSame('new', $row['from_status']);
        $this->assertSame('confirmed', $row['to_status']);
        $this->assertSame(5, (int)$row['changed_by_user_id']);
        $this->assertSame('manager', $row['changed_by_role']);
        $this->assertSame('Проверено', $row['comment']);
    }
}
