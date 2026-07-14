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

    public function testFindProjectManagerPrefersRootManager(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, role TEXT NOT NULL, referred_by INTEGER NULL)');
        $pdo->exec("INSERT INTO users (id, role, referred_by) VALUES (6, 'manager', 17), (17, 'manager', NULL), (41, 'partner', 17)");

        $controller = new OrdersController($pdo);
        $ref = new ReflectionClass($controller);
        $method = $ref->getMethod('findProjectManagerId');
        $method->setAccessible(true);

        $this->assertSame(17, $method->invoke($controller));
    }

    public function testFindProjectManagerFallsBackToFirstManager(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, role TEXT NOT NULL, referred_by INTEGER NULL)');
        $pdo->exec("INSERT INTO users (id, role, referred_by) VALUES (6, 'manager', 17), (17, 'manager', 1)");

        $controller = new OrdersController($pdo);
        $ref = new ReflectionClass($controller);
        $method = $ref->getMethod('findProjectManagerId');
        $method->setAccessible(true);

        $this->assertSame(6, $method->invoke($controller));
    }


    public function testAdminDeliveryDatePickerUsesLocalDatesAndClickableLabels(): void
    {
        $source = file_get_contents(__DIR__ . '/../src/Views/admin/orders/create.php');

        $this->assertStringContainsString('clientToday', $source);
        $this->assertStringContainsString('serverToday > clientToday ? serverToday : clientToday', $source);
        $this->assertStringContainsString('new Date(parts[0], (parts[1] || 1) - 1, parts[2] || 1)', $source);
        $this->assertStringContainsString('cursor-pointer rounded-xl border', $source);
        $this->assertStringNotContainsString('toISOString().slice(0, 10)', $source);
    }

    public function testManualStoreDoesNotExposeLegacyMixedOrderErrors(): void
    {
        $source = file_get_contents(__DIR__ . '/../src/Controllers/OrdersController.php');

        $this->assertStringNotContainsString('mixed_mode', $source);
        $this->assertStringNotContainsString('mixed_delivery_date', $source);
        $this->assertStringNotContainsString("\$_POST['batch_items']", $source);
    }

}
