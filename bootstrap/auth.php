<?php

if (!function_exists('requireRole')) {
    function requireRole(string ...$roles): void
    {
        global $authMiddleware;
        $authMiddleware->handle($roles);
    }
}

if (!function_exists('requireCsrf')) {
    function requireCsrf(string $method, string $uri): void
    {
        global $csrfMiddleware;
        $csrfMiddleware->handle($method, $uri);
    }
}

if (!function_exists('requireClient')) {
    function requireClient(): void
    {
        requireRole('client', 'partner', 'admin', 'manager', 'seller');
    }
}

if (!function_exists('requireAdmin')) {
    function requireAdmin(): void
    {
        requireRole('admin');
    }
}

if (!function_exists('requireManager')) {
    function requireManager(): void
    {
        requireRole('manager', 'admin');
    }
}

if (!function_exists('requireSeller')) {
    function requireSeller(): void
    {
        requireRole('seller', 'admin');
    }
}

if (!function_exists('requirePartner')) {
    function requirePartner(): void
    {
        requireRole('partner', 'manager', 'admin');
    }
}

if (!function_exists('requireBuyer')) {
    function requireBuyer(): void
    {
        requireRole('buyer', 'manager', 'admin');
    }
}
