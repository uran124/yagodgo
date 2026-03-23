<?php

if (!function_exists('viewAdmin')) {
    function viewAdmin(string $template, array $data = []): void
    {
        $pageTitle = $data['pageTitle'] ?? '';
        extract($data, EXTR_SKIP);
        ob_start();
        require __DIR__ . "/../src/Views/admin/{$template}.php";
        $content = ob_get_clean();
        $role = $_SESSION['role'] ?? '';
        if ($role === 'seller') {
            require __DIR__ . '/../src/Views/layouts/seller_main.php';
        } elseif (in_array($role, ['manager', 'partner'], true)) {
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
        return match($status) {
            'new' => [
                'label'  => 'Новый заказ',
                'badge'  => 'bg-red-100 text-red-800',
                'bg'     => 'bg-red-50',
            ],
            'processing' => [
                'label' => 'Принят',
                'badge' => 'bg-yellow-100 text-yellow-800',
                'bg'    => 'bg-yellow-50',
            ],
            'assigned' => [
                'label' => 'В работе',
                'badge' => 'bg-green-100 text-green-800',
                'bg'    => 'bg-green-50',
            ],
            'delivered' => [
                'label' => 'Выполнен',
                'badge' => 'bg-blue-100 text-blue-800',
                'bg'    => 'bg-blue-50',
            ],
            'cancelled' => [
                'label' => 'Отменен',
                'badge' => 'bg-gray-100 text-gray-800',
                'bg'    => 'bg-gray-50',
            ],
            default => [
                'label' => $status,
                'badge' => 'bg-gray-100 text-gray-800',
                'bg'    => '',
            ],
        };
    }
}
