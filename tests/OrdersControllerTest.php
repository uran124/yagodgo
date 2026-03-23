<?php
namespace Tests;

use PHPUnit\Framework\TestCase;
use App\Controllers\OrdersController;
use PDO;
use ReflectionClass;

class OrdersControllerTest extends TestCase
{
    public function testBasePathUsesManagerSectionForManagerRole(): void
    {
        if (!class_exists('App\\Controllers\\OrdersController')) {
            require_once __DIR__ . '/../src/Controllers/OrdersController.php';
        }
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        $_SESSION['role'] = 'manager';

        $controller = new OrdersController(new PDO('sqlite::memory:'));
        $ref = new ReflectionClass($controller);
        $method = $ref->getMethod('basePath');
        $method->setAccessible(true);
        $result = $method->invoke($controller);
        $this->assertSame('/manager/orders', $result);
    }
}
