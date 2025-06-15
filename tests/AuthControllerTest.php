<?php
namespace Tests;

use PHPUnit\Framework\TestCase;
use App\Controllers\AuthController;
use PDO;

class AuthControllerTest extends TestCase
{
    protected function setUp(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        if (!class_exists('App\\Controllers\\AuthController')) {
            require_once __DIR__ . '/../src/Controllers/AuthController.php';
            require_once __DIR__ . '/../src/Helpers/SmsRu.php';
        }
        $_SESSION = [];
        $_POST = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        $_POST = [];
    }

    public function testVerifyResetPinCode(): void
    {
        $_SESSION['reset_phone'] = '79029237794';
        $_SESSION['reset_code'] = '1234';
        $_POST['phone'] = '79029237794';
        $_POST['code'] = '1234';

        $controller = new AuthController(new PDO('sqlite::memory:'));
        ob_start();
        @$controller->verifyResetPinCode();
        $output = ob_get_clean();
        $data = json_decode($output, true);

        $this->assertSame(['success' => true], $data);
    }
}
