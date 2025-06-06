<?php
class AuthMiddleware {
    public function handle($role, $next) {
        if (empty($_SESSION['user_id']) || $_SESSION['role'] !== $role) {
            header('Location: /login');
            exit;
        }
        return $next();
    }
}
