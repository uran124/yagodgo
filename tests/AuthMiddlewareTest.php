<?php
namespace Tests;

use App\Middleware\AuthMiddleware;
use PHPUnit\Framework\TestCase;

class AuthMiddlewareTest extends TestCase
{
    private AuthMiddleware $middleware;

    protected function setUp(): void
    {
        $this->middleware = new AuthMiddleware();
    }

    public function testAuthorizesMatchingRole(): void
    {
        $session = [
            'user_id' => 42,
            'role' => 'manager',
        ];

        $this->assertTrue($this->middleware->isAuthorized(['manager', 'admin'], $session));
    }

    public function testRejectsGuestWithoutUserId(): void
    {
        $session = [
            'role' => 'admin',
        ];

        $this->assertFalse($this->middleware->isAuthorized(['admin'], $session));
    }

    public function testRejectsWrongRole(): void
    {
        $session = [
            'user_id' => 42,
            'role' => 'seller',
        ];

        $this->assertFalse($this->middleware->isAuthorized(['partner', 'admin'], $session));
    }
}
