<?php
namespace App\Helpers;

use PDO;

class ReferralHelper
{
    /**
     * Генерирует случайный реферальный код длиной $length символов.
     * Проверяет уникальность в БД, используя переданный PDO.
     *
     * @param PDO $pdo
     * @param int $length
     * @return string
     */
    public static function generateUniqueCode(PDO $pdo, int $length = 8): string
    {
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // без похожих символов
        $maxIndex = strlen($chars) - 1;

        do {
            $code = '';
            for ($i = 0; $i < $length; $i++) {
                $code .= $chars[random_int(0, $maxIndex)];
            }
            // Проверяем, что такого кода ещё нет в таблице users
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE referral_code = ?");
            $stmt->execute([$code]);
            $exists = (int)$stmt->fetchColumn() > 0;
        } while ($exists);

        return $code;
    }
}
