<?php
namespace App\Controllers;

use PDO;
use App\Helpers\ReferralHelper;
use App\Helpers\SmsRu;
use App\Helpers\TelegramSender;
use App\Helpers\MailSender;
use App\Helpers\PhoneNormalizer;

class AuthController
{
    private PDO $pdo;
    private array $smsConfig;
    private array $telegramConfig;
    private array $emailConfig;

    public function __construct(PDO $pdo, array $smsConfig = [], array $telegramConfig = [], array $emailConfig = [])
    {
        $this->pdo = $pdo;
        $this->smsConfig = $smsConfig;
        $this->telegramConfig = $telegramConfig;
        $this->emailConfig = $emailConfig;
    }

    private function validateCsrfOrFail(): bool
    {
        if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
            http_response_code(419);
            echo json_encode(['error' => 'Недействительный токен безопасности']);
            return false;
        }
        return true;
    }

    private function isRateLimited(string $key, int $maxAttempts, int $windowSeconds, int $cooldownSeconds): bool
    {
        $now = time();
        $rateLimits = $_SESSION['rate_limits'] ?? [];
        $data = $rateLimits[$key] ?? [
            'attempts' => 0,
            'window_start' => $now,
            'blocked_until' => 0,
        ];

        if ($data['blocked_until'] > $now) {
            $rateLimits[$key] = $data;
            $_SESSION['rate_limits'] = $rateLimits;
            return true;
        }

        if ($now - $data['window_start'] > $windowSeconds) {
            $data['attempts'] = 0;
            $data['window_start'] = $now;
        }

        $data['attempts']++;
        if ($data['attempts'] > $maxAttempts) {
            $data['blocked_until'] = $now + $cooldownSeconds;
        }

        $rateLimits[$key] = $data;
        $_SESSION['rate_limits'] = $rateLimits;

        return $data['blocked_until'] > $now;
    }

    private function buildRateLimitKey(string $prefix, string $phone): string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        return $prefix . ':' . $ip . ':' . $phone;
    }
    
    /**
     * Показ формы регистрации
     */
    public function showRegistrationForm(): void
    {
        $invite = trim($_GET['invite'] ?? '');
        include 'src/Views/client/register.php';
    }

    /**
 * Обработка регистрации
 */
public function register(): void
{
    $nameRaw     = $_POST['name'] ?? '';
    $phoneRaw    = $_POST['phone'] ?? '';
    $address     = trim($_POST['address'] ?? '');
    $pinRaw      = $_POST['pin'] ?? '';
    // Читаем код-приглашение из POST-поля "invite"
    $inputInvite = trim($_POST['invite'] ?? '');

    $name  = trim($nameRaw);
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        header('Location: /register?error=' . urlencode('Ошибка безопасности. Обновите страницу и попробуйте снова.'));
        exit;
    }

    $phone = PhoneNormalizer::normalize($phoneRaw);
    $pin   = trim($pinRaw);

    if (empty($_SESSION['reg_verified']) || $_SESSION['reg_phone'] !== $phone) {
        header('Location: /register?error=' . urlencode('Подтвердите номер'));
        exit;
    }

    // Валидация
    if (
        $name === '' ||
        !preg_match('/^7\d{10}$/', $phone) ||
        !preg_match('/^\d{4}$/', $pin) ||
        $address === ''
    ) {
        header('Location: /register?error=' . urlencode('Неверные данные'));
        exit;
    }

    // Собираем ID пригласившего (если введён код invite)
    $referredBy = null;
    if ($inputInvite !== '') {
        $stmt = $this->pdo->prepare("SELECT id FROM users WHERE referral_code = ?");
        $stmt->execute([$inputInvite]);
        $found = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($found) {
            $referredBy = (int)$found['id'];
        }
    }

    // Генерация собственного реферального кода (8 символов)
    $newCode = ReferralHelper::generateUniqueCode($this->pdo, 8);

    // Хешируем PIN
    $pinHash = password_hash($pin, PASSWORD_DEFAULT);

    // Сохраняем в БД
    try {
        $this->pdo->beginTransaction();

        // 1) Вставляем нового пользователя
        $stmt = $this->pdo->prepare("
            INSERT INTO users 
                (role, name, phone, password_hash, referral_code, referred_by, has_used_referral_coupon, points_balance, rub_balance, created_at)
            VALUES 
                ('client', ?, ?, ?, ?, ?, 0, 0, 0, NOW())
        ");
        $stmt->execute([$name, $phone, $pinHash, $newCode, $referredBy]);
        $userId = (int)$this->pdo->lastInsertId();

        // 2) Сохраняем адрес
        $stmt = $this->pdo->prepare("
            INSERT INTO addresses (user_id, street, recipient_name, recipient_phone, is_primary, created_at)
            VALUES (?, ?, ?, ?, 1, NOW())
        ");
        $stmt->execute([$userId, $address, $name, $phone]);

        // 3) Если есть пригласивший, фиксируем связь в таблице referrals
        if ($referredBy !== null) {
            $stmt = $this->pdo->prepare("
                INSERT IGNORE INTO referrals (referrer_id, referred_id, created_at)
                VALUES (?, ?, NOW())
            ");
            $stmt->execute([$referredBy, $userId]);
        }

        $this->pdo->commit();
    } catch (\Exception $e) {
        $this->pdo->rollBack();
        header('Location: /register?error=' . urlencode('Ошибка регистрации'));
        exit;
    }

    // Устанавливаем сессию
    $_SESSION['user_id']        = $userId;
    $_SESSION['role']           = 'client';
    $_SESSION['name']           = $name;
    $_SESSION['referral_code']  = $newCode;
    // Новый пользователь получает баланс 0 при регистрации
    $_SESSION['points_balance'] = 0;

    $_SESSION['rub_balance'] = 0;
    unset($_SESSION['reg_verified'], $_SESSION['reg_phone'], $_SESSION['reg_code']);
    unset($_SESSION['invite_code']);

    header('Location: /notifications');
    exit;
}


    /**
     * Показ формы логина
     */
    public function showLoginForm(): void
    {
        $error = $_GET['error'] ?? null;
        include 'src/Views/client/login.php';
    }

    /**
     * Обработка входа
     */
    public function login(): void
    {
        $phoneRaw = $_POST['phone'] ?? '';
        $pinRaw   = $_POST['pin'] ?? '';

        $phone = PhoneNormalizer::normalize($phoneRaw);
        $pin   = trim($pinRaw);

        $limitKey = $this->buildRateLimitKey('login', $phone);
        if ($this->isRateLimited($limitKey, 5, 300, 600)) {
            header('Location: /login?error=' . urlencode('Слишком много попыток. Попробуйте позже.'));
            exit;
        }

        if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
            header('Location: /login?error=' . urlencode('Ошибка безопасности. Обновите страницу и попробуйте снова.'));
            exit;
        }

        // Валидация формата перед запросом
        if (!preg_match('/^7\d{10}$/', $phone) || !preg_match('/^\d{4}$/', $pin)) {
            header('Location: /login?error=' . urlencode('Неверный телефон или PIN'));
            exit;
        }

        $stmt = $this->pdo->prepare(
            "SELECT id, role, password_hash, name, referral_code, points_balance, rub_balance
             FROM users
             WHERE phone = ?"
        );
        $stmt->execute([$phone]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($pin, $user['password_hash'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role']    = $user['role'];
            $_SESSION['name']    = $user['name'];
            $_SESSION['rub_balance'] = (int)$user['rub_balance'];
            $_SESSION['points_balance'] = (int)$user['points_balance'];
            $_SESSION['referral_code']  = $user['referral_code'];
            unset($_SESSION['rate_limits'][$limitKey]);

            header('Location: /');
            exit;
        } else {
            header('Location: /login?error=' . urlencode('Неверный телефон или PIN'));
            exit;
        }
    }

    // Отправка кода подтверждения при регистрации
    public function sendRegistrationCode(): void
    {
        $phone = PhoneNormalizer::normalize($_POST['phone'] ?? '');
        $method = $_POST['method'] ?? 'sms';
        $email  = trim($_POST['email'] ?? '');
        header('Content-Type: application/json; charset=UTF-8');

        if (!$this->validateCsrfOrFail()) {
            return;
        }

        $limitKey = $this->buildRateLimitKey('reg-code', $phone);
        if ($this->isRateLimited($limitKey, 3, 300, 600)) {
            echo json_encode(['error' => 'Слишком много попыток. Попробуйте позже.', 'rate_limited' => true]);
            return;
        }

        if (!preg_match('/^7\d{10}$/', $phone)) {
            echo json_encode(['error' => 'Неверный номер']);
            return;
        }

        // Проверяем наличие пользователя и его блокировку
        try {
            $stmt = $this->pdo->prepare("SELECT is_blocked FROM users WHERE phone = ?");
            $stmt->execute([$phone]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            // Совместимость со схемой без поля is_blocked
            $stmt = $this->pdo->prepare("SELECT 1 FROM users WHERE phone = ?");
            $stmt->execute([$phone]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($existing) {
                echo json_encode(['exists' => true]);
                return;
            }
        }

        if ($existing) {
            if (isset($existing['is_blocked']) && $existing['is_blocked']) {
                echo json_encode(['blocked' => true]);
            } else {
                echo json_encode(['exists' => true]);
            }
            return;
        }

        $code = random_int(1000, 9999);
        $_SESSION['reg_phone'] = $phone;
        $_SESSION['reg_code'] = $code;

        $ok = false;
        if ($method === 'telegram') {
            $stmt = $this->pdo->prepare("SELECT chat_id FROM users WHERE phone = ? AND chat_id IS NOT NULL");
            $stmt->execute([$phone]);
            $chat = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($chat && $chat['chat_id']) {
                $tg = new TelegramSender($this->telegramConfig['bot_token'] ?? '');
                $topicId = $this->telegramConfig['admin_topic_id'] ?? null;
                $ok = $tg->send($chat['chat_id'], "Код подтверждения: {$code}", $topicId);
            }
        } elseif ($method === 'email') {
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $mailer = new MailSender($this->emailConfig['from'] ?? 'noreply@example.com');
                $ok = $mailer->send($email, 'Код подтверждения', "Код подтверждения: {$code}");
            }
        } else {
            $sms = new SmsRu($this->smsConfig['api_id'] ?? '');
            $ok = $sms->send($phone, "Код подтверждения: {$code}");
        }

        echo json_encode(['success' => $ok]);
    }

    // Проверка кода для регистрации
    public function verifyRegistrationCode(): void
    {
        header('Content-Type: application/json; charset=UTF-8');
        if (!$this->validateCsrfOrFail()) {
            return;
        }

        $phone = PhoneNormalizer::normalize($_POST['phone'] ?? '');
        $code  = trim($_POST['code'] ?? '');
        $valid = isset($_SESSION['reg_phone'], $_SESSION['reg_code']) &&
            $_SESSION['reg_phone'] === $phone && $_SESSION['reg_code'] == $code;
        if ($valid) {
            $_SESSION['reg_verified'] = true;
        }
        echo json_encode(['success' => $valid]);
    }

    // Страница восстановления PIN
    public function showResetPinForm(): void
    {
        viewAuth('client/reset_pin', [
            'error' => $_GET['error'] ?? null,
        ]);
    }

    // Отправка кода для смены PIN
    public function sendResetPinCode(): void
    {
        $phone = PhoneNormalizer::normalize($_POST['phone'] ?? '');
        header('Content-Type: application/json; charset=UTF-8');

        if (!$this->validateCsrfOrFail()) {
            return;
        }

        $limitKey = $this->buildRateLimitKey('reset-code', $phone);
        if ($this->isRateLimited($limitKey, 3, 300, 600)) {
            echo json_encode(['success' => false, 'error' => 'Слишком много попыток. Попробуйте позже.']);
            return;
        }

        if (!preg_match('/^7\d{10}$/', $phone)) {
            echo json_encode(['success' => false, 'error' => 'Неверный номер']);
            return;
        }

        $stmt = $this->pdo->prepare("SELECT chat_id FROM users WHERE phone = ?");
        $stmt->execute([$phone]);
        $chat = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$chat) {
            echo json_encode(['success' => false, 'error' => 'Пользователь с таким номером не найден']);
            return;
        }
        if (empty($chat['chat_id'])) {
            echo json_encode(['success' => false, 'error' => 'Телеграм-бот не подключен для этого номера']);
            return;
        }

        $code = random_int(10000, 99999);
        $_SESSION['reset_phone'] = $phone;
        $_SESSION['reset_code'] = $code;

        $tg = new TelegramSender($this->telegramConfig['bot_token'] ?? '');
        $ok = $tg->send($chat['chat_id'], "Одноразовый код для сброса PIN: {$code}");

        echo json_encode(['success' => $ok]);
    }

    // Проверка кода для восстановления PIN
    public function verifyResetPinCode(): void
    {
        header('Content-Type: application/json; charset=UTF-8');
        if (!$this->validateCsrfOrFail()) {
            return;
        }

        $phone = PhoneNormalizer::normalize($_POST['phone'] ?? '');
        $code  = trim($_POST['code'] ?? '');
        $valid = preg_match('/^\d{5}$/', $code) && isset($_SESSION['reset_phone'], $_SESSION['reset_code']) &&
            $_SESSION['reset_phone'] === $phone && $_SESSION['reset_code'] == $code;
        echo json_encode(['success' => $valid]);
    }

    // Смена PIN после подтверждения
    public function resetPin(): void
    {
        if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
            header('Location: /reset-pin?error=' . urlencode('Ошибка безопасности. Обновите страницу и попробуйте снова.'));
            exit;
        }

        $phone = PhoneNormalizer::normalize($_POST['phone'] ?? '');
        $code  = trim($_POST['code'] ?? '');
        $pin   = trim($_POST['pin'] ?? '');
        $validCode = isset($_SESSION['reset_phone'], $_SESSION['reset_code']) &&
            $_SESSION['reset_phone'] === $phone && $_SESSION['reset_code'] == $code;
        if (!$validCode || !preg_match('/^\d{5}$/', $code) || !preg_match('/^\d{4}$/', $pin)) {
            header('Location: /reset-pin?error=' . urlencode('Неверные данные'));
            exit;
        }
        $hash = password_hash($pin, PASSWORD_DEFAULT);
        $stmt = $this->pdo->prepare("UPDATE users SET password_hash = ? WHERE phone = ?");
        $stmt->execute([$hash, $phone]);
        unset($_SESSION['reset_phone'], $_SESSION['reset_code']);
        header('Location: /login?success=' . urlencode('PIN обновлен'));
        exit;
    }

    /**
     * Выход (разлогин)
     */
    public function logout(): void
    {
        if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
            header('Location: /login?error=' . urlencode('Ошибка безопасности. Обновите страницу и попробуйте снова.'));
            exit;
        }
        session_destroy();
        header('Location: /login');
        exit;
    }
}
