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
use App\Controllers\UsersController;
use PDO;

class SellerProfileTest extends TestCase
{
    protected function setUp(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        $_SESSION = [];
    }

    public function testSellerProfileRenders(): void
    {
        if (!class_exists('App\\Controllers\\UsersController')) {
            require_once __DIR__ . '/../src/Controllers/UsersController.php';
            require_once __DIR__ . '/../src/Helpers/Auth.php';
        }

        $_SESSION['user_id'] = 1;
        $_SESSION['role'] = 'seller';

        $pdo = new PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE seller_payouts (id INTEGER PRIMARY KEY AUTOINCREMENT, seller_id INT, gross_amount REAL, payout_amount REAL, status TEXT, created_at TEXT)');
        $pdo->exec("INSERT INTO seller_payouts (seller_id, gross_amount, payout_amount, status, created_at) VALUES (1, 100, 70, 'pending', '2024-01-01')");

        $controller = new UsersController($pdo);
        ob_start();
        $controller->sellerProfile();
        $output = ob_get_clean();

        $this->assertStringContainsString('seller_profile', $output);
        $this->assertStringContainsString('"ordersCount":1', $output);
    }
}

}
