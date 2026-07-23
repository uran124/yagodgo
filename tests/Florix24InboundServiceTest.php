<?php
declare(strict_types=1);
namespace Tests;

use App\Services\Florix24InboundService;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class Florix24InboundServiceTest extends TestCase
{
    private PDO $pdo;
    private Florix24InboundService $service;

    protected function setUp(): void
    {
        $this->pdo=new PDO('sqlite::memory:');$this->pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT,role TEXT,name TEXT,phone TEXT,password_hash TEXT,points_balance INTEGER DEFAULT 0,is_blocked INTEGER DEFAULT 0,integration_partner_enabled INTEGER DEFAULT 0,created_at TEXT)');
        $this->pdo->exec('CREATE TABLE addresses (id INTEGER PRIMARY KEY AUTOINCREMENT,user_id INTEGER,street TEXT,recipient_name TEXT,recipient_phone TEXT,is_primary INTEGER DEFAULT 0,created_at TEXT)');
        $this->pdo->exec('CREATE TABLE products (id INTEGER PRIMARY KEY,price INTEGER,sale_price INTEGER,is_active INTEGER)');
        $this->pdo->exec('CREATE TABLE orders (id INTEGER PRIMARY KEY AUTOINCREMENT,user_id INTEGER,address_id INTEGER,status TEXT,total_amount INTEGER,delivery_date TEXT,discount_applied INTEGER,points_used INTEGER,integration_source TEXT,external_order_id TEXT,partner_user_id INTEGER,partner_source TEXT,external_partner_id TEXT,external_partner_name TEXT,subtotal_before_points INTEGER,points_discount_amount INTEGER,total_after_points INTEGER,created_at TEXT)');
        $this->pdo->exec('CREATE TABLE order_items (id INTEGER PRIMARY KEY AUTOINCREMENT,order_id INTEGER,product_id INTEGER,quantity REAL,unit_price INTEGER)');
        $this->pdo->exec('CREATE TABLE points_transactions (id INTEGER PRIMARY KEY AUTOINCREMENT,user_id INTEGER,order_id INTEGER,amount INTEGER,transaction_type TEXT,description TEXT,source TEXT,external_order_id TEXT,related_transaction_id INTEGER,created_at TEXT)');
        $this->pdo->exec("INSERT INTO users (id,role,name,phone,password_hash,points_balance,created_at) VALUES (1,'client','Анна','79000000000','',350,CURRENT_TIMESTAMP),(45,'manager','Виктория','79000000001','',0,CURRENT_TIMESTAMP)");
        $this->pdo->exec('UPDATE users SET integration_partner_enabled=1 WHERE id=45');
        $this->pdo->exec('INSERT INTO products (id,price,sale_price,is_active) VALUES (15,1350,0,1),(16,100,0,0)');
        $this->service=new Florix24InboundService($this->pdo);
    }

    public function testLookupNormalizesPhoneAndReturnsBalance(): void
    {
        $result=$this->service->customerByPhone('+7 (900) 000-00-00');
        $this->assertTrue($result['customer_found']);$this->assertSame(350,$result['points']['balance']);$this->assertSame('79000000000',$result['customer']['phone']);
    }

    public function testCreatesSnapshotsAndReplaysFlorixOrderOnce(): void
    {
        $payload=['external_order_id'=>'FLORIX-100025','customer'=>['name'=>'Анна','phone'=>'8 (900) 000-00-00'],'items'=>[['product_id'=>15,'quantity'=>1]],'partner'=>['berrygo_user_id'=>45,'florix_user_id'=>7,'name'=>'Виктория'],'use_all_available_points'=>true];
        $created=$this->service->createOrder($payload);$replay=$this->service->createOrder($payload);
        $this->assertSame(350,$created['customer']['points_used']);$this->assertSame(1000,$created['amounts']['total']);$this->assertTrue($created['partner']['assigned']);
        $this->assertTrue($replay['idempotent_replay']);$this->assertSame($created['order_id'],$replay['order_id']);
        $this->assertSame(1,(int)$this->pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn());$this->assertSame(1,(int)$this->pdo->query("SELECT COUNT(*) FROM points_transactions WHERE transaction_type='usage'")->fetchColumn());
        $this->assertSame(1350,(int)$this->pdo->query('SELECT unit_price FROM order_items')->fetchColumn());$this->assertSame(0,(int)$this->pdo->query('SELECT points_balance FROM users WHERE id=1')->fetchColumn());
    }

    public function testCreatesUnknownCustomerAndRejectsInactiveProduct(): void
    {
        $created=$this->service->createOrder(['external_order_id'=>'FLORIX-NEW','customer'=>['name'=>'Новый','phone'=>'79000000002'],'items'=>[['product_id'=>15,'quantity'=>1]]]);
        $this->assertSame(0,$created['customer']['points_used']);$this->assertSame(1,(int)$this->pdo->query("SELECT COUNT(*) FROM users WHERE phone='79000000002'")->fetchColumn());
        $this->expectException(RuntimeException::class);$this->expectExceptionMessage('product_inactive');
        $this->service->createOrder(['external_order_id'=>'FLORIX-INACTIVE','customer'=>['name'=>'Анна','phone'=>'79000000000'],'items'=>[['product_id'=>16,'quantity'=>1]]]);
    }
}
