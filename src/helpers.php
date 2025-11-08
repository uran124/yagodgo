<?php

if (!function_exists('format_slot')) {
    /**
     * Convert delivery slot string "09-12" to "09:00 - 12:00".
     */
    function format_slot(?string $slot): string
    {
        if (!$slot) {
            return '';
        }
        if (preg_match('/^(\d{1,2})-(\d{1,2})$/', $slot, $m)) {
            $from = str_pad($m[1], 2, '0', STR_PAD_LEFT) . ':00';
            $to   = str_pad($m[2], 2, '0', STR_PAD_LEFT) . ':00';
            return "$from - $to";
        }
        return $slot;
    }
}

if (!function_exists('format_time_range')) {
    /**
     * Format time range "HH:MM" to display as "HH:MM - HH:MM".
     */
    function format_time_range(?string $from, ?string $to): string
    {
        if (!$from || !$to) {
            return '';
        }
        return substr($from, 0, 5) . ' - ' . substr($to, 0, 5);
    }
}

if (!function_exists('get_setting')) {
    /**
     * Retrieve a setting value from the database with simple in-request caching.
     */
    function get_setting(string $key, ?string $default = null): ?string
    {
        static $settingsCache = null;

        if ($settingsCache === null) {
            $settingsCache = [];
            try {
                global $pdo;
                if (isset($pdo) && $pdo instanceof PDO) {
                    $stmt = $pdo->query('SELECT setting_key, setting_value FROM settings');
                    if ($stmt !== false) {
                        $settingsCache = $stmt->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];
                    }
                }
            } catch (Throwable $e) {
                // Silently ignore issues (e.g. table does not exist in tests) and use defaults.
                $settingsCache = [];
            }
        }

        return $settingsCache[$key] ?? $default;
    }
}

if (!function_exists('get_theme_palette')) {
    /**
     * Color palettes for configurable light/dark primary accents.
     *
     * @return array<string, array<string, array<string, string>>>
     */
    function get_theme_palette(): array
    {
        return [
            'pink' => [
                'label' => 'Розовый',
                'light' => [
                    'primary'   => '#ff6b8a',
                    'secondary' => '#ff8fb3',
                    'strong'    => '#ff5c7c',
                    'via'       => '#ff7aa1',
                    'soft'      => '#ffe6f0',
                    'contrast'  => '#7b1c35',
                    'image'     => '#fff0f6',
                    'imageAlt'  => '#ffd9e7',
                ],
                'dark' => [
                    'primary'   => '#f472b6',
                    'secondary' => '#ec4899',
                    'strong'    => '#fb7185',
                    'via'       => '#f973c5',
                    'soft'      => 'rgba(244, 114, 182, 0.22)',
                    'contrast'  => '#ffffff',
                    'image'     => 'rgba(244, 114, 182, 0.25)',
                    'imageAlt'  => 'rgba(244, 114, 182, 0.12)',
                ],
            ],
            'raspberry' => [
                'label' => 'Малиновый',
                'light' => [
                    'primary'   => '#e8476b',
                    'secondary' => '#ff6f9d',
                    'strong'    => '#d13659',
                    'via'       => '#f05c81',
                    'soft'      => '#ffe1eb',
                    'contrast'  => '#6e142c',
                    'image'     => '#ffe8ef',
                    'imageAlt'  => '#ffc9da',
                ],
                'dark' => [
                    'primary'   => '#fb5288',
                    'secondary' => '#e11d48',
                    'strong'    => '#f43f5e',
                    'via'       => '#fb7185',
                    'soft'      => 'rgba(240, 82, 122, 0.24)',
                    'contrast'  => '#ffffff',
                    'image'     => 'rgba(240, 82, 122, 0.28)',
                    'imageAlt'  => 'rgba(240, 82, 122, 0.14)',
                ],
            ],
            'lavender' => [
                'label' => 'Лавандовый',
                'light' => [
                    'primary'   => '#8d6bff',
                    'secondary' => '#b388ff',
                    'strong'    => '#7a5df3',
                    'via'       => '#a27bff',
                    'soft'      => '#ede7ff',
                    'contrast'  => '#2f1c7b',
                    'image'     => '#f3edff',
                    'imageAlt'  => '#dcd3ff',
                ],
                'dark' => [
                    'primary'   => '#a78bfa',
                    'secondary' => '#7c3aed',
                    'strong'    => '#c084fc',
                    'via'       => '#9f7aea',
                    'soft'      => 'rgba(167, 139, 250, 0.24)',
                    'contrast'  => '#f8fafc',
                    'image'     => 'rgba(167, 139, 250, 0.28)',
                    'imageAlt'  => 'rgba(167, 139, 250, 0.14)',
                ],
            ],
            'green' => [
                'label' => 'Зелёный',
                'light' => [
                    'primary'   => '#42b883',
                    'secondary' => '#68d89b',
                    'strong'    => '#35a573',
                    'via'       => '#56c691',
                    'soft'      => '#def7ec',
                    'contrast'  => '#0b3b2a',
                    'image'     => '#e8faf1',
                    'imageAlt'  => '#c4efd9',
                ],
                'dark' => [
                    'primary'   => '#34d399',
                    'secondary' => '#10b981',
                    'strong'    => '#22c55e',
                    'via'       => '#4ade80',
                    'soft'      => 'rgba(52, 211, 153, 0.22)',
                    'contrast'  => '#022c22',
                    'image'     => 'rgba(52, 211, 153, 0.26)',
                    'imageAlt'  => 'rgba(52, 211, 153, 0.12)',
                ],
            ],
            'blue' => [
                'label' => 'Голубой',
                'light' => [
                    'primary'   => '#3ba7ff',
                    'secondary' => '#63c7ff',
                    'strong'    => '#258ce6',
                    'via'       => '#4fb8ff',
                    'soft'      => '#e0f2ff',
                    'contrast'  => '#0f2949',
                    'image'     => '#e7f6ff',
                    'imageAlt'  => '#cde8ff',
                ],
                'dark' => [
                    'primary'   => '#38bdf8',
                    'secondary' => '#0284c7',
                    'strong'    => '#0ea5e9',
                    'via'       => '#22d3ee',
                    'soft'      => 'rgba(56, 189, 248, 0.24)',
                    'contrast'  => '#f8fafc',
                    'image'     => 'rgba(56, 189, 248, 0.26)',
                    'imageAlt'  => 'rgba(56, 189, 248, 0.12)',
                ],
            ],
            'orange' => [
                'label' => 'Оранжевый',
                'light' => [
                    'primary'   => '#ff8a4c',
                    'secondary' => '#ffb26b',
                    'strong'    => '#ff7a33',
                    'via'       => '#ffa364',
                    'soft'      => '#ffe9da',
                    'contrast'  => '#5f2306',
                    'image'     => '#fff3ea',
                    'imageAlt'  => '#ffd9bd',
                ],
                'dark' => [
                    'primary'   => '#fb923c',
                    'secondary' => '#f97316',
                    'strong'    => '#f59e0b',
                    'via'       => '#facc15',
                    'soft'      => 'rgba(251, 146, 60, 0.24)',
                    'contrast'  => '#0f172a',
                    'image'     => 'rgba(251, 146, 60, 0.26)',
                    'imageAlt'  => 'rgba(251, 146, 60, 0.12)',
                ],
            ],
        ];
    }
}

if (!function_exists('get_theme_colors')) {
    /**
     * Return color set for requested mode (light or dark).
     *
     * @return array<string, string>
     */
    function get_theme_colors(string $mode = 'light'): array
    {
        $palette = get_theme_palette();
        $settingKey = $mode === 'dark' ? 'theme_dark_primary' : 'theme_light_primary';
        $choice = get_setting($settingKey, 'pink');
        if (!isset($palette[$choice][$mode])) {
            $choice = 'pink';
        }
        return $palette[$choice][$mode] + ['key' => $choice, 'label' => $palette[$choice]['label']];
    }
}

