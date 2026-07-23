<?php
declare(strict_types=1);
namespace Tests;

use App\Services\Florix24InboundService;
use PDO;
use PHPUnit\Framework\TestCase;

final class Florix24PartnerRewardTest extends TestCase
{
    public function testCancellationReversesCompletedPartnerRewardExactlyOnce(): void
    {
        $pdo = new PDO('sqlite::memory:'); $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, points_balance INTEGER NOT NULL)');
        $pdo->exec('CREATE TABLE orders (id INTEGER PRIMARY KEY, user_id INTEGER, status TEXT, points_used INTEGER, integration_source TEXT, external_order_id TEXT)');
        $pdo->exec('CREATE TABLE order_status_history (id INTEGER PRIMARY KEY AUTOINCREMENT, order_id INTEGER, from_status TEXT, to_status TEXT, changed_by_user_id INTEGER, changed_by_role TEXT, comment TEXT, created_at TEXT)');
        $pdo->exec('CREATE TABLE points_transactions (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, order_id INTEGER, amount INTEGER, transaction_type TEXT, description TEXT, source TEXT, external_order_id TEXT, related_transaction_id INTEGER, created_at TEXT)');
        $pdo->exec("INSERT INTO users (id, points_balance) VALUES (1, 0), (2, 100)");
        $pdo->exec("INSERT INTO orders (id,user_id,status,points_used,integration_source,external_order_id) VALUES (10,1,'completed',0,'florix24','FLORIX-10')");
        $pdo->exec("INSERT INTO points_transactions (user_id,order_id,amount,transaction_type,description,source,created_at) VALUES (2,10,100,'partner_reward','reward','florix24',CURRENT_TIMESTAMP)");

        $service = new Florix24InboundService($pdo);
        $first = $service->cancel('FLORIX-10');
        $second = $service->cancel('FLORIX-10');

        $this->assertSame('cancelled', $first['status']);
        $this->assertSame(0, $first['points_returned']);
        $this->assertSame(0, $second['points_returned']);
        $this->assertSame(0, (int)$pdo->query('SELECT points_balance FROM users WHERE id=2')->fetchColumn());
        $this->assertSame(1, (int)$pdo->query("SELECT COUNT(*) FROM points_transactions WHERE transaction_type='partner_reward_reversal'")->fetchColumn());
        $this->assertSame(1, (int)$pdo->query("SELECT COUNT(*) FROM order_status_history WHERE order_id=10 AND from_status='completed' AND to_status='cancelled'")->fetchColumn());
    }
}
