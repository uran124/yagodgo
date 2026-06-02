<?php
namespace App\Controllers;

use App\Helpers\TelegramSender;
use PDO;
use Throwable;

class SupportChatController
{
    private const ACTIVE_ORDER_STATUSES = ['reserved', 'new', 'processing', 'assigned'];
    private const TELEGRAM_CHAT_ID = '-1002055168794';
    private const TELEGRAM_TOPIC_ID = 1785;
    private const MAX_MESSAGE_LENGTH = 2000;
    private const MAX_PHOTO_SIZE = 5242880; // 5 MB
    private const MAX_PHOTOS_PER_MESSAGE = 3;

    private PDO $pdo;
    private array $telegramConfig;

    public function __construct(PDO $pdo, array $telegramConfig = [])
    {
        $this->pdo = $pdo;
        $this->telegramConfig = $telegramConfig;
    }

    public function clientIndex(?int $chatId = null): void
    {
        requireClient();
        $userId = (int)$_SESSION['user_id'];
        $this->markClientRead($chatId, $userId);

        $beforeMessageId = max(0, (int)($_GET['before_message_id'] ?? 0));

        view('client/chat', [
            'userName' => $_SESSION['name'] ?? null,
            'chats' => $this->getClientChats($userId),
            'activeOrders' => $this->getActiveOrders($userId),
            'selectedChat' => $chatId ? $this->getClientChat($chatId, $userId) : null,
            'messages' => $chatId ? $this->getMessages($chatId, $beforeMessageId) : [],
            'beforeMessageId' => $beforeMessageId,
            'attachmentsByMessage' => $chatId ? $this->getAttachmentsByMessage($chatId) : [],
        ]);
    }

    public function startClientChat(): void
    {
        requireClient();
        $this->requireCsrf('/chat');
        $userId = (int)$_SESSION['user_id'];
        $body = $this->normalizeBody($_POST['body'] ?? '');
        $orderId = $this->resolveClientOrderId($userId, $_POST['order_id'] ?? null);

        if ($body === '' && !$this->hasUploadedPhoto()) {
            $this->redirect('/chat?error=' . urlencode('Напишите сообщение или добавьте фото'));
        }

        $this->pdo->beginTransaction();
        try {
            [$chatId, $created] = $this->findOrCreateChat($userId, $orderId);
            $messageId = $this->createMessage($chatId, $userId, (string)($_SESSION['name'] ?? 'Клиент'), $body);
            $this->storeUploadedPhotos($messageId);
            $this->incrementUnread($chatId, true);
            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log('Support chat create failed: ' . $e->getMessage());
            $this->redirect('/chat?error=' . urlencode('Не удалось отправить сообщение'));
        }

        $this->sendTelegramNotice($chatId, $body, $created);
        $this->redirect('/chat/' . $chatId);
    }

    public function clientMessage(int $chatId): void
    {
        requireClient();
        $userId = (int)$_SESSION['user_id'];
        $this->requireCsrf('/chat/' . $chatId);
        $chat = $this->getClientChat($chatId, $userId);
        if (!$chat) {
            http_response_code(404);
            echo 'Чат не найден';
            return;
        }
        $body = $this->normalizeBody($_POST['body'] ?? '');
        if ($body === '' && !$this->hasUploadedPhoto()) {
            $this->redirect('/chat/' . $chatId . '?error=' . urlencode('Напишите сообщение или добавьте фото'));
        }

        $this->pdo->beginTransaction();
        try {
            $messageId = $this->createMessage($chatId, $userId, (string)($_SESSION['name'] ?? 'Клиент'), $body);
            $this->storeUploadedPhotos($messageId);
            $this->incrementUnread($chatId, true);
            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log('Support client message failed: ' . $e->getMessage());
            $this->redirect('/chat/' . $chatId . '?error=' . urlencode('Не удалось отправить сообщение'));
        }

        $this->sendTelegramNotice($chatId, $body, false);
        $this->redirect('/chat/' . $chatId);
    }

    public function staffIndex(?int $chatId = null): void
    {
        requireManager();
        if ($chatId !== null) {
            $this->markStaffRead($chatId);
        }
        $beforeMessageId = max(0, (int)($_GET['before_message_id'] ?? 0));

        viewAdmin('support_chats', [
            'pageTitle' => 'Чаты поддержки',
            'chats' => $this->getStaffChats(),
            'selectedChat' => $chatId ? $this->getStaffChat($chatId) : null,
            'messages' => $chatId ? $this->getMessages($chatId, $beforeMessageId) : [],
            'beforeMessageId' => $beforeMessageId,
            'attachmentsByMessage' => $chatId ? $this->getAttachmentsByMessage($chatId) : [],
            'basePath' => $this->staffBasePath(),
        ]);
    }

    public function staffMessage(int $chatId): void
    {
        requireManager();
        $base = $this->staffBasePath();
        $this->requireCsrf($base . '/chats/' . $chatId);
        $chat = $this->getStaffChat($chatId);
        if (!$chat) {
            http_response_code(404);
            echo 'Чат не найден';
            return;
        }
        $body = $this->normalizeBody($_POST['body'] ?? '');
        if ($body === '' && !$this->hasUploadedPhoto()) {
            $this->redirect($base . '/chats/' . $chatId . '?error=' . urlencode('Напишите сообщение или добавьте фото'));
        }

        $this->pdo->beginTransaction();
        try {
            $messageId = $this->createMessage($chatId, (int)$_SESSION['user_id'], (string)($_SESSION['name'] ?? 'Сотрудник'), $body);
            $this->storeUploadedPhotos($messageId);
            $this->incrementUnread($chatId, false);
            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log('Support staff message failed: ' . $e->getMessage());
            $this->redirect($base . '/chats/' . $chatId . '?error=' . urlencode('Не удалось отправить сообщение'));
        }

        $this->redirect($base . '/chats/' . $chatId);
    }

    public function saveNote(int $chatId): void
    {
        requireManager();
        $base = $this->staffBasePath();
        $this->requireCsrf($base . '/chats/' . $chatId);
        $note = trim((string)($_POST['internal_note'] ?? ''));
        $stmt = $this->pdo->prepare(
            'UPDATE support_chats SET internal_note = ?, internal_note_updated_by = ?, internal_note_updated_at = NOW() WHERE id = ?'
        );
        $stmt->execute([$note !== '' ? $note : null, (int)$_SESSION['user_id'], $chatId]);
        $this->redirect($base . '/chats/' . $chatId);
    }

    public function editMessage(int $chatId, int $messageId): void
    {
        requireManager();
        $base = $this->staffBasePath();
        $this->requireCsrf($base . '/chats/' . $chatId);
        $body = $this->normalizeBody($_POST['body'] ?? '');
        if ($body === '') {
            $this->redirect($base . '/chats/' . $chatId . '?error=' . urlencode('Сообщение не может быть пустым'));
        }
        $chat = $this->getStaffChat($chatId);
        $message = $this->getMessage($chatId, $messageId);
        if (!$chat || !$message || (int)$message['sender_user_id'] === (int)$chat['user_id']) {
            http_response_code(403);
            echo 'Редактирование недоступно';
            return;
        }
        $stmt = $this->pdo->prepare('UPDATE support_messages SET body = ?, edited_at = NOW(), edited_by = ? WHERE id = ? AND chat_id = ?');
        $stmt->execute([$body, (int)$_SESSION['user_id'], $messageId, $chatId]);
        $this->redirect($base . '/chats/' . $chatId . '#message-' . $messageId);
    }

    public function hideMessage(int $chatId, int $messageId): void
    {
        requireManager();
        $base = $this->staffBasePath();
        $this->requireCsrf($base . '/chats/' . $chatId);
        $stmt = $this->pdo->prepare('UPDATE support_messages SET hidden_from_client_at = NOW(), hidden_from_client_by = ? WHERE id = ? AND chat_id = ?');
        $stmt->execute([(int)$_SESSION['user_id'], $messageId, $chatId]);
        $this->redirect($base . '/chats/' . $chatId . '#message-' . $messageId);
    }

    private function getClientChats(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT c.*, o.status AS order_status, o.delivery_date, u.name AS client_name,
                    (SELECT body FROM support_messages WHERE chat_id = c.id ORDER BY id DESC LIMIT 1) AS last_body
             FROM support_chats c
             JOIN users u ON u.id = c.user_id
             LEFT JOIN orders o ON o.id = c.order_id
             WHERE c.user_id = ?
             ORDER BY COALESCE(c.last_message_at, c.created_at) DESC, c.id DESC"
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function getStaffChats(): array
    {
        $stmt = $this->pdo->query(
            "SELECT c.*, u.name AS client_name, u.phone AS client_phone, o.status AS order_status, o.delivery_date,
                    note_user.name AS note_user_name,
                    (SELECT body FROM support_messages WHERE chat_id = c.id ORDER BY id DESC LIMIT 1) AS last_body
             FROM support_chats c
             JOIN users u ON u.id = c.user_id
             LEFT JOIN orders o ON o.id = c.order_id
             LEFT JOIN users note_user ON note_user.id = c.internal_note_updated_by
             ORDER BY c.staff_unread_count DESC, COALESCE(c.last_message_at, c.created_at) DESC, c.id DESC"
        );
        return $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
    }

    private function getClientChat(int $chatId, int $userId): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT c.*, u.name AS client_name, u.phone AS client_phone, o.status AS order_status, o.delivery_date
             FROM support_chats c
             JOIN users u ON u.id = c.user_id
             LEFT JOIN orders o ON o.id = c.order_id
             WHERE c.id = ? AND c.user_id = ?"
        );
        $stmt->execute([$chatId, $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function getStaffChat(int $chatId): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT c.*, u.name AS client_name, u.phone AS client_phone, o.status AS order_status, o.delivery_date,
                    note_user.name AS note_user_name
             FROM support_chats c
             JOIN users u ON u.id = c.user_id
             LEFT JOIN orders o ON o.id = c.order_id
             LEFT JOIN users note_user ON note_user.id = c.internal_note_updated_by
             WHERE c.id = ?"
        );
        $stmt->execute([$chatId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function getMessages(int $chatId, int $beforeMessageId = 0): array
    {
        $where = 'chat_id = ?';
        $params = [$chatId];
        if ($beforeMessageId > 0) {
            $where .= ' AND id < ?';
            $params[] = $beforeMessageId;
        }
        $stmt = $this->pdo->prepare(
            "SELECT * FROM (
                SELECT * FROM support_messages
                WHERE {$where}
                ORDER BY id DESC
                LIMIT 50
             ) recent ORDER BY id ASC"
        );
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function getMessage(int $chatId, int $messageId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM support_messages WHERE id = ? AND chat_id = ? LIMIT 1');
        $stmt->execute([$messageId, $chatId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function getAttachmentsByMessage(int $chatId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT a.*
             FROM support_message_attachments a
             JOIN support_messages m ON m.id = a.message_id
             WHERE m.chat_id = ?
             ORDER BY a.id ASC"
        );
        $stmt->execute([$chatId]);
        $result = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $result[(int)$row['message_id']][] = $row;
        }
        return $result;
    }

    private function getActiveOrders(int $userId): array
    {
        $placeholders = implode(',', array_fill(0, count(self::ACTIVE_ORDER_STATUSES), '?'));
        $stmt = $this->pdo->prepare(
            "SELECT id, status, delivery_date, total_amount, created_at
             FROM orders
             WHERE user_id = ? AND status IN ({$placeholders})
             ORDER BY id DESC"
        );
        $stmt->execute(array_merge([$userId], self::ACTIVE_ORDER_STATUSES));
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function resolveClientOrderId(int $userId, mixed $rawOrderId): ?int
    {
        $orderId = (int)$rawOrderId;
        if ($orderId <= 0) {
            return null;
        }
        $placeholders = implode(',', array_fill(0, count(self::ACTIVE_ORDER_STATUSES), '?'));
        $stmt = $this->pdo->prepare("SELECT id FROM orders WHERE id = ? AND user_id = ? AND status IN ({$placeholders}) LIMIT 1");
        $stmt->execute(array_merge([$orderId, $userId], self::ACTIVE_ORDER_STATUSES));
        return $stmt->fetchColumn() ? $orderId : null;
    }

    private function findOrCreateChat(int $userId, ?int $orderId): array
    {
        if ($orderId !== null) {
            $stmt = $this->pdo->prepare('SELECT id FROM support_chats WHERE user_id = ? AND order_id = ? LIMIT 1');
            $stmt->execute([$userId, $orderId]);
            $existingId = $stmt->fetchColumn();
            if ($existingId) {
                return [(int)$existingId, false];
            }
        }

        $stmt = $this->pdo->prepare('INSERT INTO support_chats (user_id, order_id, last_message_at) VALUES (?, ?, NOW())');
        $stmt->execute([$userId, $orderId]);
        return [(int)$this->pdo->lastInsertId(), true];
    }

    private function createMessage(int $chatId, int $senderUserId, string $senderName, string $body): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO support_messages (chat_id, sender_user_id, sender_name_snapshot, body) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$chatId, $senderUserId, $senderName, $body !== '' ? $body : null]);
        $messageId = (int)$this->pdo->lastInsertId();
        $this->pdo->prepare('UPDATE support_chats SET last_message_at = NOW() WHERE id = ?')->execute([$chatId]);
        return $messageId;
    }

    private function incrementUnread(int $chatId, bool $fromClient): void
    {
        if ($fromClient) {
            $this->pdo->prepare('UPDATE support_chats SET staff_unread_count = staff_unread_count + 1, client_unread_count = 0 WHERE id = ?')->execute([$chatId]);
        } else {
            $this->pdo->prepare('UPDATE support_chats SET client_unread_count = client_unread_count + 1, staff_unread_count = 0 WHERE id = ?')->execute([$chatId]);
        }
    }

    private function markClientRead(?int $chatId, int $userId): void
    {
        if ($chatId === null) {
            return;
        }
        $stmt = $this->pdo->prepare('UPDATE support_chats SET client_unread_count = 0 WHERE id = ? AND user_id = ?');
        $stmt->execute([$chatId, $userId]);
    }

    private function markStaffRead(int $chatId): void
    {
        $stmt = $this->pdo->prepare('UPDATE support_chats SET staff_unread_count = 0 WHERE id = ?');
        $stmt->execute([$chatId]);
    }

    private function normalizeBody(mixed $raw): string
    {
        $body = trim((string)$raw);
        if (mb_strlen($body) > self::MAX_MESSAGE_LENGTH) {
            $body = mb_substr($body, 0, self::MAX_MESSAGE_LENGTH);
        }
        return $body;
    }

    private function hasUploadedPhoto(): bool
    {
        if (!isset($_FILES['photos']) || !is_array($_FILES['photos']['tmp_name'] ?? null)) {
            return false;
        }
        foreach ($_FILES['photos']['tmp_name'] as $i => $tmpName) {
            $error = $_FILES['photos']['error'][$i] ?? UPLOAD_ERR_NO_FILE;
            if ($error === UPLOAD_ERR_OK && is_uploaded_file((string)$tmpName)) {
                return true;
            }
        }
        return false;
    }

    private function storeUploadedPhotos(int $messageId): void
    {
        if (!isset($_FILES['photos']) || !is_array($_FILES['photos']['tmp_name'] ?? null)) {
            return;
        }
        $dir = __DIR__ . '/../../uploads/support/' . date('Y/m');
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $tmpFiles = $_FILES['photos']['tmp_name'];
        $errors = $_FILES['photos']['error'] ?? [];
        $names = $_FILES['photos']['name'] ?? [];
        $sizes = $_FILES['photos']['size'] ?? [];
        $stored = 0;
        foreach ($tmpFiles as $i => $tmpName) {
            if ($stored >= self::MAX_PHOTOS_PER_MESSAGE) {
                break;
            }
            if (($errors[$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string)$tmpName)) {
                continue;
            }
            $size = (int)($sizes[$i] ?? 0);
            if ($size <= 0 || $size > self::MAX_PHOTO_SIZE) {
                continue;
            }
            $mime = mime_content_type((string)$tmpName) ?: '';
            $ext = match ($mime) {
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp',
                default => '',
            };
            if ($ext === '') {
                continue;
            }
            $fileName = 'support_' . date('Ymd_His') . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
            $absPath = $dir . '/' . $fileName;
            if (!move_uploaded_file((string)$tmpName, $absPath)) {
                continue;
            }
            $relPath = '/uploads/support/' . date('Y/m') . '/' . $fileName;
            $stmt = $this->pdo->prepare(
                'INSERT INTO support_message_attachments (message_id, file_path, original_name, mime_type, file_size) VALUES (?, ?, ?, ?, ?)'
            );
            $stmt->execute([$messageId, $relPath, (string)($names[$i] ?? ''), $mime, $size]);
            $stored++;
        }
    }

    private function sendTelegramNotice(int $chatId, string $body, bool $created): void
    {
        $token = (string)($this->telegramConfig['bot_token'] ?? '');
        if ($token === '') {
            return;
        }
        $chat = $this->getStaffChat($chatId);
        if (!$chat) {
            return;
        }
        $host = $_SERVER['HTTP_HOST'] ?? '';
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $link = $host !== '' ? $scheme . '://' . $host . '/admin/chats/' . $chatId : '/admin/chats/' . $chatId;
        $text = $body !== '' ? $body : 'Пользователь отправил фото';
        $orderLine = !empty($chat['order_id']) ? 'Заказ #' . $chat['order_id'] : 'Без заказа';
        $title = $created ? 'Создано новое обращение в чат поддержки' : 'Новое сообщение в чате поддержки';
        $message = $title . "\n" .
            'Клиент: ' . (string)($chat['client_name'] ?? 'Клиент') . "\n" .
            $orderLine . "\n" .
            'Сообщение: ' . $text . "\n" .
            'Ссылка: ' . $link;
        $sender = new TelegramSender(
            $token,
            $this->telegramConfig['relay_url'] ?? null,
            $this->telegramConfig['relay_secret'] ?? null
        );
        $sender->send(self::TELEGRAM_CHAT_ID, $message, self::TELEGRAM_TOPIC_ID);
    }

    private function requireCsrf(string $redirect): void
    {
        if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
            $this->redirect($redirect . (str_contains($redirect, '?') ? '&' : '?') . 'error=' . urlencode('Истекла сессия, обновите страницу'));
        }
    }

    private function staffBasePath(): string
    {
        return ($_SESSION['role'] ?? '') === 'admin' ? '/admin' : '/manager';
    }

    private function redirect(string $url): never
    {
        header('Location: ' . $url);
        exit;
    }
}
