<?php

if (!function_exists('viewAdmin')) {
    function viewAdmin(string $template, array $data = []): void
    {
        $pageTitle = $data['pageTitle'] ?? '';
        $useAdminMainLayout = !empty($data['useAdminMainLayout']);
        extract($data, EXTR_SKIP);
        ob_start();
        require __DIR__ . "/../src/Views/admin/{$template}.php";
        $content = ob_get_clean();
        $role = $_SESSION['role'] ?? '';
        if ($role === 'seller') {
            require __DIR__ . '/../src/Views/layouts/seller_main.php';
        } elseif (in_array($role, ['manager', 'partner'], true) && !$useAdminMainLayout) {
            require __DIR__ . '/../src/Views/layouts/manager_main.php';
        } else {
            require __DIR__ . '/../src/Views/layouts/admin_main.php';
        }
    }
}

if (!function_exists('viewManager')) {
    function viewManager(string $template, array $data = []): void
    {
        $pageTitle = $data['pageTitle'] ?? '';
        extract($data, EXTR_SKIP);
        ob_start();
        require __DIR__ . "/../src/Views/admin/{$template}.php";
        $content = ob_get_clean();
        require __DIR__ . '/../src/Views/layouts/manager_main.php';
    }
}

if (!function_exists('view')) {
    function view(string $template, array $data = []): void
    {
        extract($data, EXTR_SKIP);
        ob_start();
        require __DIR__ . "/../src/Views/{$template}.php";
        $content = ob_get_clean();
        require __DIR__ . '/../src/Views/layouts/main.php';
    }
}

if (!function_exists('viewAuth')) {
    function viewAuth(string $template, array $data = []): void
    {
        extract($data, EXTR_SKIP);
        ob_start();
        require __DIR__ . "/../src/Views/{$template}.php";
        $content = ob_get_clean();
        require __DIR__ . '/../src/Views/layouts/auth.php';
    }
}

if (!function_exists('order_status_info')) {
    function order_status_info(string $status): array
    {
        return \App\Support\OrderStatuses::info($status);
    }
}
