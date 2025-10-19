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
        $selectedNalet = trim((string)($_GET['nalet'] ?? ''));

        $sql = "SELECT u.id, u.name, u.phone, mc.allow_mailing, mc.comment, mc.nalet_number
                FROM users u
                LEFT JOIN mailing_clients mc ON mc.user_id = u.id
                WHERE u.role = 'client'";
        $params = [];

        if ($selectedNalet !== '') {
            $sql .= " AND mc.nalet_number = ?";
            $params[] = $selectedNalet;
        }

        $sql .= " ORDER BY u.name";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $clients = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $naletStmt = $this->pdo->query(
            "SELECT DISTINCT nalet_number FROM mailing_clients WHERE nalet_number <> '' ORDER BY nalet_number"
        );
        $naletNumbers = $naletStmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

        $activeCount = 0;
        foreach ($clients as $client) {
            if ((int)($client['allow_mailing'] ?? 0) === 1) {
                $activeCount++;
            }
        }

        viewAdmin('apps/mailing', [
            'pageTitle'     => 'Рассылка',
            'clients'       => $clients,
            'naletNumbers'  => $naletNumbers,
            'selectedNalet' => $selectedNalet,
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
        $naletNumber = trim((string)($_POST['nalet_number'] ?? ''));

        if ($userId <= 0) {
            $this->jsonResponse(['success' => false, 'message' => 'Некорректный пользователь'], 400);
            return;
        }

        $stmt = $this->pdo->prepare(
            "INSERT INTO mailing_clients (user_id, comment, nalet_number)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE comment = VALUES(comment), nalet_number = VALUES(nalet_number), updated_at = CURRENT_TIMESTAMP"
        );
        $stmt->execute([$userId, $comment, $naletNumber]);

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
