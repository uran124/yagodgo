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

