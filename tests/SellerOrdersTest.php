<?php
namespace {
    if (!function_exists('viewAdmin')) {
        function viewAdmin(string $template, array $data = []): void
        {
            echo $template . PHP_EOL;
            echo json_encode($data);
        }
    }
}

namespace Tests {

use PHPUnit\Framework\TestCase;
use App\Controllers\SellerController;
use PDO;

class SellerOrdersTest extends TestCase
{
    protected function setUp(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        $_SESSION = [];
    }

    public function testSellerOrderCalculations(): void
    {
        if (!class_exists('App\\Controllers\\SellerController')) {
            require_once __DIR__ . '/../src/Controllers/SellerController.php';
        }

        $_SESSION['user_id'] = 1;
        $_SESSION['role'] = 'seller';

        $pdo = new PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE orders (id INT, user_id INT, address_id INT, slot_id INT, status TEXT, points_used REAL, delivery_date TEXT)');
        $pdo->exec('CREATE TABLE users (id INT, name TEXT, phone TEXT)');
        $pdo->exec('CREATE TABLE addresses (id INT, street TEXT)');
        $pdo->exec('CREATE TABLE delivery_slots (id INT, time_from TEXT, time_to TEXT)');
        $pdo->exec('CREATE TABLE order_items (id INTEGER PRIMARY KEY AUTOINCREMENT, order_id INT, product_id INT, quantity REAL, boxes REAL, unit_price REAL)');
        $pdo->exec('CREATE TABLE products (id INT, product_type_id INT, seller_id INT, box_size REAL, box_unit TEXT, variety TEXT)');
        $pdo->exec('CREATE TABLE product_types (id INT, name TEXT)');

        $pdo->exec("INSERT INTO users (id,name,phone) VALUES (2,'Людмила','79025505385')");
        $pdo->exec("INSERT INTO addresses (id,street) VALUES (1,'Елены Стасовой 48Е')");
        $pdo->exec("INSERT INTO delivery_slots (id,time_from,time_to) VALUES (1,'18:00','22:00')");
        $pdo->exec("INSERT INTO orders (id,user_id,address_id,slot_id,status,points_used,delivery_date) VALUES (1,2,1,1,'new',100,'2024-08-12')");
        $pdo->exec("INSERT INTO product_types (id,name) VALUES (1,'Клубника')");
        $pdo->exec("INSERT INTO products (id,product_type_id,seller_id,box_size,box_unit,variety) VALUES (1,1,1,2.0,'кг','Клери')");
        $pdo->exec("INSERT INTO products (id,product_type_id,seller_id,box_size,box_unit,variety) VALUES (2,1,2,2.0,'кг','')");
        $pdo->exec("INSERT INTO order_items (order_id,product_id,quantity,boxes,unit_price) VALUES (1,1,2.0,1,600)");
        $pdo->exec("INSERT INTO order_items (order_id,product_id,quantity,boxes,unit_price) VALUES (1,2,3.0,1.5,800)");

        $controller = new SellerController($pdo);
        ob_start();
        $controller->orders();
        $output = ob_get_clean();

        [$template, $json] = explode("\n", trim($output), 2);
        $this->assertSame('seller_orders', $template);
        $data = json_decode($json, true);
        $this->assertEquals(1, count($data['orders']));
        $order = $data['orders'][0];
        $this->assertEquals(1200, $order['seller_subtotal']);
        $this->assertEquals(360, $order['commission']);
        $this->assertEquals(840, $order['payout']);
        $this->assertEquals(33.33, $order['points_applied']);
        $this->assertEquals('79******385', $order['phone']);
    }
}

}
