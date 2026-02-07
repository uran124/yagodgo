<?php
namespace App\Helpers;

final class PhoneNormalizer
{
    public static function normalize(string $raw): string
    {
        $digits = preg_replace('/\D+/', '', $raw);
        if (strlen($digits) === 10) {
            return '7' . $digits;
        }
        if (strlen($digits) === 11) {
            $first = $digits[0];
            $rest  = substr($digits, 1);
            return ($first === '8' ? '7' : $first) . $rest;
        }
        return $digits;
    }
}
