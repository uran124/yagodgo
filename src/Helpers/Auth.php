<?php
namespace App\Helpers;

/**
 * Класс для простого получения текущего пользователя из сессии.
 */
class Auth
{
    /**
     * Возвращает информацию о залогиненном пользователе (из сессии) или null, если не залогинен.
     *
     * @return array|null Массив вида ['id' => ..., 'role' => ..., 'name' => ...] или null.
     */
    public static function user(): ?array
    {
        if (!isset($_SESSION['user_id'])) {
            return null;
        }

        return [
            'id'   => $_SESSION['user_id'],
            'role' => $_SESSION['role'] ?? '',
            'name' => $_SESSION['name'] ?? '',
        ];
    }
}
