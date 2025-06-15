<?php
namespace App\Controllers;

use PDO;
use App\Helpers\ReferralHelper;
use App\Helpers\SmsRu;

class AuthController
{
    private PDO $pdo;
    private array $smsConfig;

    public function __construct(PDO $pdo, array $smsConfig = [])
    {
        $this->pdo = $pdo;
        $this->smsConfig = $smsConfig;
    }

    /**
     * Приводит любой ввод телефона к формату "7XXXXXXXXXX"
     */
    private function normalizePhone(string $raw): string
    {
        // Оставляем только цифры
        $digits = preg_replace('/\D+/', '', $raw);
        // Если ввели 10 цифр — добавляем "7" спереди
        if (strlen($digits) === 10) {
            return '7' . $digits;
        }
        // Если 11 цифр и первая — 8 или 7
        if (strlen($digits) === 11) {
            $first = $digits[0];
            $rest  = substr($digits, 1);
            return ($first === '8' ? '7' : $first) . $rest;
        }
        // Иначе возвращаем как есть (но без плюсов и лишнего)
        return $digits;
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
    $phone = $this->normalizePhone($phoneRaw);
    $pin   = trim($pinRaw);

    if (empty($_SESSION['reg_verified']) || $_SESSION['reg_phone'] !== $phone) {
        header('Location: /register?error=Подтвердите+номер');
        exit;
    }

    // Валидация
    if (
        $name === '' ||
        !preg_match('/^7\d{10}$/', $phone) ||
        !preg_match('/^\d{4}$/', $pin) ||
        $address === ''
    ) {
        header('Location: /register?error=Неверные+данные');
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
                (role, name, phone, password_hash, referral_code, referred_by, has_used_referral_coupon, points_balance, created_at)
            VALUES 
                ('client', ?, ?, ?, ?, ?, 0, 0, NOW())
        ");
        $stmt->execute([$name, $phone, $pinHash, $newCode, $referredBy]);
        $userId = (int)$this->pdo->lastInsertId();

        // 2) Сохраняем адрес
        $stmt = $this->pdo->prepare("
            INSERT INTO addresses (user_id, street, created_at)
            VALUES (?, ?, NOW())
        ");
        $stmt->execute([$userId, $address]);

        // 3) Если есть пригласивший, фиксируем связь в таблице referrals
        if ($referredBy !== null) {
            $stmt = $this->pdo->prepare("
                INSERT INTO referrals (referrer_id, referred_id, created_at)
                VALUES (?, ?, NOW())
            ");
            $stmt->execute([$referredBy, $userId]);
        }

        $this->pdo->commit();
    } catch (\Exception $e) {
        $this->pdo->rollBack();
        header('Location: /register?error=Ошибка+регистрации');
        exit;
    }

    // Устанавливаем сессию
    $_SESSION['user_id']        = $userId;
    $_SESSION['role']           = 'client';
    $_SESSION['name']           = $name;
    $_SESSION['referral_code']  = $newCode;
    // Новый пользователь получает баланс 0 при регистрации
    $_SESSION['points_balance'] = 0;

    unset($_SESSION['reg_verified'], $_SESSION['reg_phone'], $_SESSION['reg_code']);

    header('Location: /');
    exit;
}


    /**
     * Показ формы логина
     */
    public function showLoginForm(): void
    {
        include 'src/Views/client/login.php';
    }

    /**
     * Обработка входа
     */
    public function login(): void
    {
        $phoneRaw = $_POST['phone'] ?? '';
        $pinRaw   = $_POST['pin'] ?? '';

        $phone = $this->normalizePhone($phoneRaw);
        $pin   = trim($pinRaw);

        // Валидация формата перед запросом
        if (!preg_match('/^7\d{10}$/', $phone) || !preg_match('/^\d{4}$/', $pin)) {
            header('Location: /login?error=Неверный+телефон+или+PIN');
            exit;
        }

        $stmt = $this->pdo->prepare(
            "SELECT id, role, password_hash, name, referral_code, points_balance
             FROM users
             WHERE phone = ?"
        );
        $stmt->execute([$phone]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($pin, $user['password_hash'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role']    = $user['role'];
            $_SESSION['name']    = $user['name'];
            $_SESSION['points_balance'] = (int)$user['points_balance'];
            $_SESSION['referral_code']  = $user['referral_code'];
            
            header('Location: /');
            exit;
        } else {
            header('Location: /login?error=Неверный+телефон+или+PIN');
            exit;
        }
    }

    // Отправка кода подтверждения при регистрации
    public function sendRegistrationCode(): void
    {
        $phone = $this->normalizePhone($_POST['phone'] ?? '');
        header('Content-Type: application/json');

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
        $sms = new SmsRu($this->smsConfig['api_id'] ?? '');
        $ok = $sms->send($phone, "Код подтверждения: {$code}");
        echo json_encode(['success' => $ok]);
    }

    // Проверка кода для регистрации
    public function verifyRegistrationCode(): void
    {
        $phone = $this->normalizePhone($_POST['phone'] ?? '');
        $code  = trim($_POST['code'] ?? '');
        $valid = isset($_SESSION['reg_phone'], $_SESSION['reg_code']) &&
            $_SESSION['reg_phone'] === $phone && $_SESSION['reg_code'] == $code;
        if ($valid) {
            $_SESSION['reg_verified'] = true;
        }
        header('Content-Type: application/json');
        echo json_encode(['success' => $valid]);
    }

    // Страница восстановления PIN
    public function showResetPinForm(): void
    {
        view('client/reset_pin', [
            'error' => $_GET['error'] ?? null,
        ]);
    }

    // Отправка кода для смены PIN
    public function sendResetPinCode(): void
    {
        $phone = $this->normalizePhone($_POST['phone'] ?? '');
        if (!preg_match('/^7\d{10}$/', $phone)) {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Неверный номер']);
            return;
        }
        $code = random_int(1000, 9999);
        $_SESSION['reset_phone'] = $phone;
        $_SESSION['reset_code'] = $code;
        $sms = new SmsRu($this->smsConfig['api_id'] ?? '');
        $ok = $sms->send($phone, "Код сброса PIN: {$code}");
        header('Content-Type: application/json');
        echo json_encode(['success' => $ok]);
    }

    // Проверка кода для восстановления PIN
    public function verifyResetPinCode(): void
    {
        $phone = $this->normalizePhone($_POST['phone'] ?? '');
        $code  = trim($_POST['code'] ?? '');
        $valid = isset($_SESSION['reset_phone'], $_SESSION['reset_code']) &&
            $_SESSION['reset_phone'] === $phone && $_SESSION['reset_code'] == $code;
        header('Content-Type: application/json');
        echo json_encode(['success' => $valid]);
    }

    // Смена PIN после подтверждения
    public function resetPin(): void
    {
        $phone = $this->normalizePhone($_POST['phone'] ?? '');
        $code  = trim($_POST['code'] ?? '');
        $pin   = trim($_POST['pin'] ?? '');
        $validCode = isset($_SESSION['reset_phone'], $_SESSION['reset_code']) &&
            $_SESSION['reset_phone'] === $phone && $_SESSION['reset_code'] == $code;
        if (!$validCode || !preg_match('/^\d{4}$/', $pin)) {
            header('Location: /reset-pin?error=Неверные+данные');
            exit;
        }
        $hash = password_hash($pin, PASSWORD_DEFAULT);
        $stmt = $this->pdo->prepare("UPDATE users SET password_hash = ? WHERE phone = ?");
        $stmt->execute([$hash, $phone]);
        unset($_SESSION['reset_phone'], $_SESSION['reset_code']);
        header('Location: /login?success=PIN+обновлен');
        exit;
    }

    /**
     * Выход (разлогин)
     */
    public function logout(): void
    {
        session_destroy();
        header('Location: /login');
        exit;
    }
}