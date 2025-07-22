<?php
namespace Tests;

use PHPUnit\Framework\TestCase;
use App\Controllers\OrdersController;
use PDO;
use ReflectionClass;

class OrdersControllerTest extends TestCase
{
    public function testNormalizePhoneAddsPrefix(): void
    {
        if (!class_exists('App\\Controllers\\OrdersController')) {
            require_once __DIR__ . '/../src/Controllers/OrdersController.php';
        }
        $controller = new OrdersController(new PDO('sqlite::memory:'));
        $ref = new ReflectionClass($controller);
        $method = $ref->getMethod('normalizePhone');
        $method->setAccessible(true);
        $result = $method->invoke($controller, '2222222222');
        $this->assertSame('72222222222', $result);
    }
}
