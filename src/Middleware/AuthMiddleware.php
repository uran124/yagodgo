<?php
namespace App\Middleware;

class AuthMiddleware
{
    /**
     * @param array<int, string> $allowedRoles
     */
    public function handle(array $allowedRoles, string $redirectTo = '/login'): void
    {
        $session = $_SESSION;
        if (!$this->isAuthorized($allowedRoles, $session)) {
            $location = $this->buildRedirectUrl($redirectTo, $allowedRoles, $session);
            header('Location: ' . $location);
            exit;
        }
    }

    /**
     * @param array<int, string> $allowedRoles
     * @param array<string, mixed> $session
     */
    private function buildRedirectUrl(string $redirectTo, array $allowedRoles, array $session): string
    {
        if ($redirectTo !== '/login') {
            return $redirectTo;
        }

        if (!$this->isStaffProtectedRoute($allowedRoles)) {
            return $redirectTo;
        }

        $reason = empty($session['user_id']) ? 'empty_session' : 'role_mismatch';
        $currentRole = (string)($session['role'] ?? 'guest');
        $roles = implode(',', $allowedRoles);
        $query = http_build_query([
            'error' => 'Нет доступа к служебному разделу.',
            'debug_auth' => sprintf('reason=%s; current_role=%s; allowed=%s', $reason, $currentRole, $roles),
        ]);

        return $redirectTo . '?' . $query;
    }

    /**
     * @param array<int, string> $allowedRoles
     */
    private function isStaffProtectedRoute(array $allowedRoles): bool
    {
        return in_array('admin', $allowedRoles, true) || in_array('manager', $allowedRoles, true);
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
