<?php
namespace App\Middleware;

class AuthMiddleware
{
    /**
     * @param array<int, string> $allowedRoles
     */
    public function handle(array $allowedRoles, string $redirectTo = '/login'): void
    {
        if (!$this->isAuthorized($allowedRoles)) {
            header('Location: ' . $redirectTo);
            exit;
        }
    }

    /**
     * @param array<int, string> $allowedRoles
     * @param array<string, mixed>|null $session
     */
    public function isAuthorized(array $allowedRoles, ?array $session = null): bool
    {
        $session ??= $_SESSION;
        if (empty($session['user_id'])) {
            return false;
        }

        $role = $session['role'] ?? '';
        return in_array($role, $allowedRoles, true);
    }
}
