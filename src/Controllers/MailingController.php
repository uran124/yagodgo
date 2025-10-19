<?php

namespace App\Controllers;

use PDO;

class MailingController
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function index(): void
    {
        $sql = "SELECT u.id, u.name, u.phone, COALESCE(mc.allow_mailing, 1) AS allow_mailing, mc.comment
                FROM users u
                LEFT JOIN mailing_clients mc ON mc.user_id = u.id
                WHERE u.role = 'client'";
        $sql .= " ORDER BY u.name";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        $clients = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $activeCount = 0;
        foreach ($clients as $client) {
            if ((int)($client['allow_mailing'] ?? 1) === 1) {
                $activeCount++;
            }
        }

        viewAdmin('apps/mailing', [
            'pageTitle'     => 'Рассылка',
            'clients'       => $clients,
            'activeCount'   => $activeCount,
        ]);
    }

    public function toggle(): void
    {
        $userId = (int)($_POST['user_id'] ?? 0);
        $allow = isset($_POST['allow']) ? (int)$_POST['allow'] : 0;

        if ($userId <= 0) {
            $this->jsonResponse(['success' => false, 'message' => 'Некорректный пользователь'], 400);
            return;
        }

        $stmt = $this->pdo->prepare(
            "INSERT INTO mailing_clients (user_id, allow_mailing)
             VALUES (?, ?)
             ON DUPLICATE KEY UPDATE allow_mailing = VALUES(allow_mailing), updated_at = CURRENT_TIMESTAMP"
        );
        $stmt->execute([$userId, $allow === 1 ? 1 : 0]);

        $this->jsonResponse(['success' => true]);
    }

    public function updateComment(): void
    {
        $userId = (int)($_POST['user_id'] ?? 0);
        $comment = trim((string)($_POST['comment'] ?? ''));

        if ($userId <= 0) {
            $this->jsonResponse(['success' => false, 'message' => 'Некорректный пользователь'], 400);
            return;
        }

        $stmt = $this->pdo->prepare(
            "INSERT INTO mailing_clients (user_id, comment)
             VALUES (?, ?)
             ON DUPLICATE KEY UPDATE comment = VALUES(comment), updated_at = CURRENT_TIMESTAMP"
        );
        $stmt->execute([$userId, $comment]);

        $this->jsonResponse(['success' => true]);
    }

    private function jsonResponse(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit;
    }
}
