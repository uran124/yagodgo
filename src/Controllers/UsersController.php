<?php

namespace App\Controllers;

use PDO;
use App\Helpers\Auth;
use App\Helpers\ReferralHelper;

class UsersController
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Get base path for redirects depending on role
     */
    private function basePath(): string
    {
        $role = $_SESSION['role'] ?? '';
        return match ($role) {
            'manager' => '/manager/users',
            'partner' => '/partner/users',
            default   => '/admin/users',
        };
    }

    /**
     * Нормализует телефон к формату 7XXXXXXXXXX
     */
    private function normalizePhone(string $raw): string
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

    /**
     * Просмотр профиля (клиент)
     */
    public function showProfile(): void
    {
        // Получаем информацию о текущем залогиненном пользователе
        $authUser = Auth::user();
        if (!$authUser) {
            header('Location: /login');
            exit;
        }

        // 1) Извлекаем полный набор полей пользователя из БД
        $stmt = $this->pdo->prepare(
            "SELECT u.id, u.name, u.phone, u.role, u.referral_code,
                    u.points_balance, u.rub_balance, u.referred_by
             FROM users u
             WHERE u.id = ?"
        );
        $stmt->execute([$authUser['id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user) {
            // Если вдруг в сессии лежит несуществующий ID
            header('Location: /login');
            exit;
        }

        // 2) Получаем все адреса пользователя, первый — основной
        $stmt = $this->pdo->prepare(
            "SELECT id, street, recipient_name, recipient_phone, is_primary
             FROM addresses
             WHERE user_id = ?
             ORDER BY is_primary DESC, created_at ASC"
        );
        $stmt->execute([$user['id']]);
        $addresses = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $address = $addresses[0]['street'] ?? '';

        // 3) История баллов (points_transactions)
        $stmt = $this->pdo->prepare(
            "SELECT id, amount, transaction_type, description, order_id, created_at
             FROM points_transactions
             WHERE user_id = ?
             ORDER BY created_at DESC"
        );
        $stmt->execute([$user['id']]);
        $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 4) Активные заказы пользователя (new, processing, assigned)
        $stmt = $this->pdo->prepare(
            "SELECT id, status, total_amount, created_at
             FROM orders
             WHERE user_id = ? AND status IN ('new','processing','assigned')
             ORDER BY created_at DESC"
        );
        $stmt->execute([$user['id']]);
        $activeOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 5) Статистика по рефералам
        // Количество пользователей, пришедших по ссылке
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM users WHERE referred_by = ?");
        $stmt->execute([$user['id']]);
        $refUsers = (int)$stmt->fetchColumn();

        // Количество выполненных заказов от таких пользователей
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*)
             FROM orders o
             JOIN users u ON u.id = o.user_id
             WHERE u.referred_by = ? AND o.status = 'delivered'"
        );
        $stmt->execute([$user['id']]);
        $refOrders = (int)$stmt->fetchColumn();

        // Сумма начисленных баллов по реферальным заказам
        $stmt = $this->pdo->prepare(
            "SELECT SUM(o.points_accrued)
             FROM orders o
             JOIN users u ON u.id = o.user_id
             WHERE u.referred_by = ? AND o.status = 'delivered'"
        );
        $stmt->execute([$user['id']]);
        $refPoints = (int)($stmt->fetchColumn() ?: 0);

        // 6) Рендерим клиентский шаблон через общий layout,
        // чтобы подключились все стили из /src/Views/layouts/main.php
        view('client/profile', [
            'user'         => $user,
            'address'      => $address,
            'addresses'    => $addresses,
            'transactions' => $transactions,
            'activeOrders' => $activeOrders,
            'refStats'     => [
                'users'  => $refUsers,
                'orders' => $refOrders,
                'points' => $refPoints,
            ],
        ]);
    }

    /**
     * Профиль менеджера со статистикой
     */
    public function managerProfile(): void
    {
        $authUser = Auth::user();
        if (!$authUser || ($authUser['role'] ?? '') !== 'manager') {
            header('Location: /login');
            exit;
        }

        $managerId = (int)$authUser['id'];

        // 1) Прямые рефералы менеджера
        $stmt = $this->pdo->prepare(
            "SELECT id, role, name, phone FROM users WHERE referred_by = ?"
        );
        $stmt->execute([$managerId]);
        $directUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $directClientIds  = [];
        $partnerIds       = [];
        foreach ($directUsers as $du) {
            if ($du['role'] === 'partner') {
                $partnerIds[] = (int)$du['id'];
            } else {
                $directClientIds[] = (int)$du['id'];
            }
        }

        // 2) Второй уровень
        $secondUsers = [];
        if ($directUsers) {
            $placeholders = implode(',', array_fill(0, count($directUsers), '?'));
            $stmt = $this->pdo->prepare(
                "SELECT id, role, referred_by FROM users WHERE referred_by IN ($placeholders)"
            );
            $stmt->execute(array_column($directUsers, 'id'));
            $secondUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        $secondClientIds = [];
        foreach ($secondUsers as $su) {
            if ($su['role'] === 'client') {
                $secondClientIds[] = (int)$su['id'];
            }
        }

        $allClientIds = array_merge($directClientIds, $secondClientIds);

        $orderCount = 0;
        if ($allClientIds) {
            $placeholders = implode(',', array_fill(0, count($allClientIds), '?'));
            $stmt = $this->pdo->prepare(
                "SELECT COUNT(*) FROM orders WHERE user_id IN ($placeholders) AND status = 'delivered'"
            );
            $stmt->execute($allClientIds);
            $orderCount = (int)$stmt->fetchColumn();
        }

        // Начисления менеджера: 3% от прямых и 3% от второго уровня
        $directBonus = 0;
        if ($directClientIds) {
            $ph = implode(',', array_fill(0, count($directClientIds), '?'));
            $stmt = $this->pdo->prepare(
                "SELECT SUM(points_accrued) FROM orders WHERE user_id IN ($ph) AND status='delivered'"
            );
            $stmt->execute($directClientIds);
            $directBonus = (int)($stmt->fetchColumn() ?: 0);
        }

        $secondBonus = 0;
        if ($secondClientIds) {
            $ph = implode(',', array_fill(0, count($secondClientIds), '?'));
            $stmt = $this->pdo->prepare(
                "SELECT SUM(manager_points_accrued) FROM orders WHERE user_id IN ($ph) AND status='delivered'"
            );
            $stmt->execute($secondClientIds);
            $secondBonus = (int)($stmt->fetchColumn() ?: 0);
        }

        // Балансы
        $balStmt = $this->pdo->prepare("SELECT points_balance, rub_balance FROM users WHERE id = ?");
        $balStmt->execute([$managerId]);
        $balances = $balStmt->fetch(PDO::FETCH_ASSOC) ?: ['points_balance' => 0, 'rub_balance' => 0];
        $pointsBalance = (int)$balances['points_balance'];
        $rubBalance = (int)$balances['rub_balance'];

        // Транзакции по выплатам
        $payoutStmt = $this->pdo->prepare(
            "SELECT id, amount, description, created_at FROM points_transactions \n" .
            "WHERE user_id = ? AND transaction_type = 'payout' ORDER BY created_at DESC"
        );
        $payoutStmt->execute([$managerId]);
        $payoutTransactions = $payoutStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // Статистика по каждому партнёру
        $partnerStats = [];
        foreach ($partnerIds as $pid) {
            $pStmt = $this->pdo->prepare("SELECT name, phone FROM users WHERE id = ?");
            $pStmt->execute([$pid]);
            $info = $pStmt->fetch(PDO::FETCH_ASSOC) ?: ['name' => '', 'phone' => ''];

            $cStmt = $this->pdo->prepare("SELECT id FROM users WHERE referred_by = ? AND role = 'client'");
            $cStmt->execute([$pid]);
            $clientIds = array_column($cStmt->fetchAll(PDO::FETCH_ASSOC), 'id');
            $clientCount = count($clientIds);

            $orders = 0;
            $revenue = 0;
            if ($clientIds) {
                $ph = implode(',', array_fill(0, count($clientIds), '?'));
                $oStmt = $this->pdo->prepare("SELECT COUNT(*) AS cnt, SUM(total_amount) AS sum FROM orders WHERE user_id IN ($ph) AND status='delivered'");
                $oStmt->execute($clientIds);
                $row = $oStmt->fetch(PDO::FETCH_ASSOC) ?: [];
                $orders = (int)($row['cnt'] ?? 0);
                $revenue = (int)($row['sum'] ?? 0);
            }

            $partnerStats[] = [
                'id'      => $pid,
                'name'    => $info['name'],
                'phone'   => $info['phone'],
                'clients' => $clientCount,
                'orders'  => $orders,
                'revenue' => $revenue,
            ];
        }

        viewAdmin('manager_profile', [
            'pageTitle'        => 'Профиль менеджера',
            'directClients'    => count($directUsers),
            'secondClients'    => count($secondUsers),
            'ordersCount'      => $orderCount,
            'partnerStats'     => $partnerStats,
            'directBonus'      => $directBonus,
            'secondBonus'      => $secondBonus,
            'pointsBalance'    => $pointsBalance,
            'rubBalance'       => $rubBalance,
            'payoutTransactions' => $payoutTransactions,
        ]);
    }

    // Запрос выплаты: конвертация баллов в рубли
    public function requestPayout(): void
    {
        $authUser = Auth::user();
        if (!$authUser || !in_array($authUser['role'] ?? '', ['manager','partner'], true)) {
            header('Location: /login');
            exit;
        }

        $userId = (int)$authUser['id'];
        $stmt = $this->pdo->prepare("SELECT points_balance FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $points = (int)($stmt->fetchColumn() ?: 0);

        if ($points > 0) {
            $this->pdo->prepare(
                "UPDATE users SET points_balance = 0, rub_balance = rub_balance + ? WHERE id = ?"
            )->execute([$points, $userId]);

            $desc = 'Запрос выплаты на ' . $points . ' ₽';
            $this->pdo->prepare(
                "INSERT INTO points_transactions (user_id, amount, transaction_type, description, created_at) VALUES (?, ?, 'payout', ?, NOW())"
            )->execute([$userId, -$points, $desc]);
        }

        $redirect = ($authUser['role'] === 'partner') ? '/partner/profile' : '/manager/profile';
        header('Location: ' . $redirect);
        exit;
    }

    /**
     * Профиль партнёра со статистикой
     */
    public function partnerProfile(): void
    {
        $authUser = Auth::user();
        if (!$authUser || ($authUser['role'] ?? '') !== 'partner') {
            header('Location: /login');
            exit;
        }

        $partnerId = (int)$authUser['id'];

        $stmt = $this->pdo->prepare("SELECT id FROM users WHERE referred_by = ? AND role = 'client'");
        $stmt->execute([$partnerId]);
        $clientIds = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'id');
        $clientCount = count($clientIds);

        $ordersCount = 0;
        $revenue = 0;
        if ($clientIds) {
            $placeholders = implode(',', array_fill(0, count($clientIds), '?'));
            $oStmt = $this->pdo->prepare("SELECT COUNT(*) AS cnt, SUM(total_amount) AS sum FROM orders WHERE user_id IN ($placeholders) AND status='delivered'");
            $oStmt->execute($clientIds);
            $row = $oStmt->fetch(PDO::FETCH_ASSOC) ?: [];
            $ordersCount = (int)($row['cnt'] ?? 0);
            $revenue = (int)($row['sum'] ?? 0);
        }

        $tenPercent = (int)round($revenue * 0.10);

        $balStmt = $this->pdo->prepare("SELECT points_balance, rub_balance FROM users WHERE id = ?");
        $balStmt->execute([$partnerId]);
        $balances = $balStmt->fetch(PDO::FETCH_ASSOC) ?: ['points_balance' => 0, 'rub_balance' => 0];
        $pointsBalance = (int)$balances['points_balance'];
        $rubBalance = (int)$balances['rub_balance'];

        $payoutStmt = $this->pdo->prepare(
            "SELECT id, amount, description, created_at FROM points_transactions \n" .
            "WHERE user_id = ? AND transaction_type = 'payout' ORDER BY created_at DESC"
        );
        $payoutStmt->execute([$partnerId]);
        $payoutTransactions = $payoutStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // Monthly statistics for charts (last 12 months including current)
        $startDate = date('Y-m-01', strtotime('-11 months'));

        // Clients per month
        $cStmt = $this->pdo->prepare(
            "SELECT DATE_FORMAT(created_at, '%Y-%m') AS ym, COUNT(*) AS cnt
             FROM users
             WHERE referred_by = ? AND role = 'client' AND created_at >= ?
             GROUP BY ym"
        );
        $cStmt->execute([$partnerId, $startDate]);
        $clientRows = $cStmt->fetchAll(PDO::FETCH_ASSOC);
        $clientsByMonth = [];
        foreach ($clientRows as $row) {
            $clientsByMonth[$row['ym']] = (int)$row['cnt'];
        }

        // Orders and revenue per month
        $ordersByMonth = [];
        $revenueByMonth = [];
        if ($clientIds) {
            $placeholders = implode(',', array_fill(0, count($clientIds), '?'));
            $oStmt = $this->pdo->prepare(
                "SELECT DATE_FORMAT(created_at, '%Y-%m') AS ym, COUNT(*) AS cnt, SUM(total_amount) AS sum
                 FROM orders
                 WHERE user_id IN ($placeholders) AND status='delivered' AND created_at >= ?
                 GROUP BY ym"
            );
            $params = $clientIds;
            $params[] = $startDate;
            $oStmt->execute($params);
            $orderRows = $oStmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($orderRows as $row) {
                $ordersByMonth[$row['ym']] = (int)$row['cnt'];
                $revenueByMonth[$row['ym']] = (int)$row['sum'];
            }
        }

        $labels = [];
        $monthlyClients = [];
        $monthlyOrders = [];
        $monthlyRevenue = [];
        for ($i = 0; $i < 12; $i++) {
            $monthKey = date('Y-m', strtotime("$startDate +$i months"));
            $labels[] = $monthKey;
            $monthlyClients[] = $clientsByMonth[$monthKey] ?? 0;
            $monthlyOrders[] = $ordersByMonth[$monthKey] ?? 0;
            $monthlyRevenue[] = $revenueByMonth[$monthKey] ?? 0;
        }

        viewAdmin('partner_profile', [
            'pageTitle'   => 'Профиль партнёра',
            'clientCount' => $clientCount,
            'ordersCount' => $ordersCount,
            'revenue'     => $revenue,
            'tenPercent'  => $tenPercent,
            'pointsBalance' => $pointsBalance,
            'rubBalance'    => $rubBalance,
            'payoutTransactions' => $payoutTransactions,
            'chartLabels' => $labels,
            'chartClients' => $monthlyClients,
            'chartOrders' => $monthlyOrders,
            'chartRevenue' => $monthlyRevenue,
        ]);
    }

    /**
     * Сохранение нового адреса (POST /profile)
     */
    public function saveAddress(): void
    {
        $authUser = Auth::user();
        if (!$authUser) {
            header('Location: /login');
            exit;
        }

        $address = trim($_POST['address'] ?? '');
        if ($address === '') {
            header('Location: /profile?error=Введите+корректный+адрес');
            exit;
        }

        // Получаем имя и телефон пользователя
        $stmtUser = $this->pdo->prepare("SELECT name, phone FROM users WHERE id = ?");
        $stmtUser->execute([$authUser['id']]);
        $userInfo = $stmtUser->fetch(PDO::FETCH_ASSOC) ?: ['name' => '', 'phone' => ''];

        // Определяем, есть ли уже основной адрес
        $stmtCheck = $this->pdo->prepare("SELECT COUNT(*) FROM addresses WHERE user_id = ? AND is_primary = 1");
        $stmtCheck->execute([$authUser['id']]);
        $isPrimary = $stmtCheck->fetchColumn() == 0 ? 1 : 0;

        $stmt = $this->pdo->prepare(
            "INSERT INTO addresses (user_id, street, recipient_name, recipient_phone, is_primary, created_at)
             VALUES (?, ?, ?, ?, ?, NOW())"
        );
        $stmt->execute([
            $authUser['id'],
            $address,
            $userInfo['name'],
            $userInfo['phone'],
            $isPrimary
        ]);

        header('Location: /profile?success=Адрес+сохранён');
        exit;
    }

    /**
     * Список пользователей (админ)
     */
    public function index(): void
    {
        $auth    = Auth::user();
        $isStaff = $auth && in_array($auth['role'], ['manager', 'partner'], true);
        $search  = trim($_GET['q'] ?? '');

        $baseSql = "SELECT u.id, u.name, u.phone, u.role, u.created_at,
                            u.referral_code, u.points_balance, u.rub_balance, u.is_blocked,
                            (SELECT street FROM addresses WHERE user_id = u.id ORDER BY created_at DESC LIMIT 1) AS address,
                            ref.name AS referrer_name
                     FROM users u
                     LEFT JOIN users ref ON ref.id = u.referred_by";

        $params     = [];
        $conditions = [];
        if ($search !== '') {
            $conditions[] = "(u.phone LIKE ? OR EXISTS(SELECT 1 FROM addresses a WHERE a.user_id = u.id AND a.street LIKE ?))";
            $params[]     = "%$search%";
            $params[]     = "%$search%";
        }
        if ($isStaff) {
            $conditions[] = "(u.role NOT IN ('partner','manager') OR u.id = ?)";
            $params[]     = $auth['id'];
        }
        if ($conditions) {
            $baseSql .= ' WHERE ' . implode(' AND ', $conditions);
        }
        $baseSql .= " ORDER BY u.created_at DESC";

        try {
            $stmt = $this->pdo->prepare($baseSql);
            $stmt->execute($params);
        } catch (\PDOException $e) {
            // База может не поддерживать поле is_blocked
            $baseSql = "SELECT u.id, u.name, u.phone, u.role, u.created_at,
                                u.referral_code, u.points_balance, u.rub_balance,
                                (SELECT street FROM addresses WHERE user_id = u.id ORDER BY created_at DESC LIMIT 1) AS address,
                                ref.name AS referrer_name
                         FROM users u
                         LEFT JOIN users ref ON ref.id = u.referred_by";
            $conditions = [];
            $params     = [];
            if ($search !== '') {
                $conditions[] = "(u.phone LIKE ? OR EXISTS(SELECT 1 FROM addresses a WHERE a.user_id = u.id AND a.street LIKE ?))";
                $params[]     = "%$search%";
                $params[]     = "%$search%";
            }
            if ($isStaff) {
                $conditions[] = "(u.role NOT IN ('partner','manager') OR u.id = ?)";
                $params[]     = $auth['id'];
            }
            if ($conditions) {
                $baseSql .= ' WHERE ' . implode(' AND ', $conditions);
            }
            $baseSql .= " ORDER BY u.created_at DESC";

            $stmt = $this->pdo->prepare($baseSql);
            $stmt->execute($params);
        }

        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!$isStaff) {
            $payoutStmt = $this->pdo->query(
                "SELECT id, name, phone, role, rub_balance
                 FROM users
                 WHERE rub_balance <> 0 AND role IN ('partner','manager')
                 ORDER BY rub_balance DESC"
            );
            $payouts = $payoutStmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $payouts = [];
        }

        viewAdmin('users/index', [
            'pageTitle' => 'Пользователи',
            'users'     => $users,
            'search'    => $search,
            'payouts'   => $payouts,
        ]);
    }

    /**
     * Детальная информация о пользователе и история клубничек
     */
    public function show(int $id): void
    {
        $auth = Auth::user();

        $stmt = $this->pdo->prepare(
            "SELECT id, name, phone, role, is_blocked, telegram_id, referral_code, referred_by,
                    points_balance, rub_balance, created_at
             FROM users WHERE id = ?"
        );
        $stmt->execute([$id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user) {
            header('Location: ' . $this->basePath());
            exit;
        }
        if (
            $auth &&
            in_array($auth['role'], ['manager', 'partner'], true) &&
            $auth['id'] !== $id &&
            in_array($user['role'], ['manager', 'partner'], true)
        ) {
            header('Location: ' . $this->basePath());
            exit;
        }

        $addrStmt = $this->pdo->prepare(
            "SELECT id, street, recipient_name, recipient_phone
             FROM addresses
             WHERE user_id = ?
             ORDER BY created_at ASC"
        );
        $addrStmt->execute([$id]);
        $addresses = $addrStmt->fetchAll(PDO::FETCH_ASSOC);

        $tStmt = $this->pdo->prepare(
            "SELECT id, amount, transaction_type, description, order_id, created_at
             FROM points_transactions
             WHERE user_id = ?
             ORDER BY created_at DESC"
        );
        $tStmt->execute([$id]);
        $transactions = $tStmt->fetchAll(PDO::FETCH_ASSOC);

        $refStmt = $this->pdo->query("SELECT id, name, phone FROM users ORDER BY name");
        $referrers = $refStmt->fetchAll(PDO::FETCH_ASSOC);

        viewAdmin('users/show', [
            'pageTitle'    => 'Пользователь #' . $id,
            'user'         => $user,
            'transactions' => $transactions,
            'addresses'    => $addresses,
            'referrers'    => $referrers,
        ]);
    }

    /**
     * Форма редактирования пользователя (админ)
     */
    public function edit(): void
    {
        $auth = Auth::user();
        $id   = (int)($_GET['id'] ?? 0);
        $user = null;

        if ($id) {
            try {
                $stmt = $this->pdo->prepare(
                    "SELECT id, name, phone, role, is_blocked, rub_balance
                     FROM users
                     WHERE id = ?"
                );
                $stmt->execute([$id]);
            } catch (\PDOException $e) {
                $stmt = $this->pdo->prepare(
                    "SELECT id, name, phone, role, rub_balance
                     FROM users
                     WHERE id = ?"
                );
                $stmt->execute([$id]);
            }
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        if ($auth && in_array($auth['role'], ['manager', 'partner'], true)) {
            if ($id && $auth['id'] !== $id && in_array($user['role'] ?? '', ['manager', 'partner'], true)) {
                header('Location: ' . $this->basePath());
                exit;
            }
        }

        viewAdmin('users/edit', [
            'pageTitle' => $id ? 'Редактировать пользователя' : 'Добавить пользователя',
            'user'      => $user,
        ]);
    }

    /**
     * Обновление пользователя (POST, админ)
     */
    public function save(): void
    {
        $auth       = Auth::user();
        $authStaff  = $auth && in_array($auth['role'], ['manager', 'partner'], true);
        $id         = (int)($_POST['id'] ?? 0);
        $targetRole = 'client';
        $currentRef = null;

        if ($authStaff && $id) {
            $stmt = $this->pdo->prepare("SELECT role, referred_by FROM users WHERE id = ?");
            $stmt->execute([$id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
            $targetRole = $row['role'] ?? 'client';
            $currentRef = $row['referred_by'] ?? null;
            if ($auth['id'] !== $id && in_array($targetRole, ['manager', 'partner'], true)) {
                header('Location: ' . $this->basePath());
                exit;
            }
        }

        if ($id) {
            if ($authStaff) {
                $role  = $targetRole;
                $refBy = $currentRef;
            } else {
                $role  = $_POST['role'] ?? 'client';
                $refBy = (int)($_POST['referred_by'] ?? 0) ?: null;
            }
            $isBlocked = isset($_POST['is_blocked']) ? 1 : 0;

            try {
                $stmt = $this->pdo->prepare(
                    "UPDATE users
                     SET role = ?, is_blocked = ?, referred_by = ?
                     WHERE id = ?"
                );
                $stmt->execute([$role, $isBlocked, $refBy, $id]);
            } catch (\PDOException $e) {
                try {
                    $stmt = $this->pdo->prepare(
                        "UPDATE users
                         SET role = ?, referred_by = ?
                         WHERE id = ?"
                    );
                    $stmt->execute([$role, $refBy, $id]);
                } catch (\PDOException $e2) {
                    $stmt = $this->pdo->prepare(
                        "UPDATE users
                         SET role = ?
                         WHERE id = ?"
                    );
                    $stmt->execute([$role, $id]);
                }
            }
        } else {
            $nameRaw  = $_POST['name'] ?? '';
            $phoneRaw = $_POST['phone'] ?? '';
            $address  = trim($_POST['address'] ?? '');
            $pinRaw   = $_POST['pin'] ?? '';
            $invite   = trim($_POST['invite'] ?? '');

            $name  = trim($nameRaw);
            $phone = $this->normalizePhone($phoneRaw);
            $pin   = trim($pinRaw);

            if (
                $name === '' ||
                !preg_match('/^7\d{10}$/', $phone) ||
                !preg_match('/^\d{4}$/', $pin) ||
                $address === ''
            ) {
                header('Location: ' . $this->basePath() . '/edit?error=Неверные+данные');
                exit;
            }

            $referredBy = null;
            if ($invite !== '') {
                $stmt = $this->pdo->prepare("SELECT id FROM users WHERE referral_code = ?");
                $stmt->execute([$invite]);
                $found = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($found) {
                    $referredBy = (int)$found['id'];
                }
            }

            $refCode = ReferralHelper::generateUniqueCode($this->pdo, 8);
            $pinHash = password_hash($pin, PASSWORD_DEFAULT);

            try {
                $this->pdo->beginTransaction();

                $stmt = $this->pdo->prepare(
                    "INSERT INTO users
                        (role, name, phone, password_hash, referral_code, referred_by, has_used_referral_coupon, points_balance, created_at)
                     VALUES ('client', ?, ?, ?, ?, ?, 0, 0, NOW())"
                );
                $stmt->execute([$name, $phone, $pinHash, $refCode, $referredBy]);
                $newId = (int)$this->pdo->lastInsertId();

                $stmt = $this->pdo->prepare(
                    "INSERT INTO addresses (user_id, street, recipient_name, recipient_phone, is_primary, created_at) VALUES (?, ?, ?, ?, 1, NOW())"
                );
                $stmt->execute([$newId, $address, $name, $phone]);

                if ($referredBy !== null) {
                    $stmt = $this->pdo->prepare(
                        "INSERT IGNORE INTO referrals (referrer_id, referred_id, created_at) VALUES (?, ?, NOW())"
                    );
                    $stmt->execute([$referredBy, $newId]);
                }

                $this->pdo->commit();
            } catch (\Exception $e) {
                $this->pdo->rollBack();
                header('Location: ' . $this->basePath() . '/edit?error=Ошибка+создания');
                exit;
            }
        }

        header('Location: ' . $this->basePath());
        exit;
    }

    public function setPrimaryAddress(): void
    {
        $authUser = Auth::user();
        if (!$authUser) {
            header('Location: /login');
            exit;
        }

        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            $this->pdo->prepare(
                "UPDATE addresses SET is_primary = CASE WHEN id = ? THEN 1 ELSE 0 END WHERE user_id = ?"
            )->execute([$id, $authUser['id']]);
        }
        header('Location: /profile');
        exit;
    }

    public function deleteAddress(): void
    {
        $authUser = Auth::user();
        if (!$authUser) {
            header('Location: /login');
            exit;
        }

        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            $this->pdo->prepare("DELETE FROM addresses WHERE id = ? AND user_id = ?")
                 ->execute([$id, $authUser['id']]);
        }
        header('Location: /profile');
        exit;
    }

    // Блокировка/разблокировка
    public function toggleBlock(): void
    {
        $auth = Auth::user();
        $id   = (int)($_POST['id'] ?? 0);
        if ($auth && in_array($auth['role'], ['manager', 'partner'], true)) {
            $stmt = $this->pdo->prepare("SELECT role FROM users WHERE id = ?");
            $stmt->execute([$id]);
            $targetRole = $stmt->fetchColumn();
            if ($auth['id'] !== $id && in_array($targetRole, ['manager', 'partner'], true)) {
                header('Location: ' . $this->basePath());
                exit;
            }
        }

        if ($id) {
            try {
                $this->pdo->prepare(
                    "UPDATE users SET is_blocked = CASE WHEN is_blocked=1 THEN 0 ELSE 1 END WHERE id = ?"
                )->execute([$id]);
            } catch (\PDOException $e) {
                // Если поле отсутствует, просто игнорируем переключение
            }
        }
        header('Location: ' . $this->basePath());
        exit;
    }

    // Сброс баланса партнёра (после выплаты)
    public function resetRubBalance(): void
    {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            $this->pdo->prepare("UPDATE users SET rub_balance = 0 WHERE id = ?")
                 ->execute([$id]);
        }
        $redirect = $_POST['redirect'] ?? ($this->basePath() . '/edit?id=' . $id);
        header('Location: ' . $redirect);
        exit;
    }

    // Добавление адреса (админ/менеджер/партнёр)
    public function addAddressAdmin(): void
    {
        $auth    = Auth::user();
        $userId  = (int)($_POST['user_id'] ?? 0);
        $address = trim($_POST['address'] ?? '');
        $name    = trim($_POST['recipient_name'] ?? '');
        $phone   = trim($_POST['recipient_phone'] ?? '');

        if ($userId && $address !== '') {
            if ($auth && in_array($auth['role'], ['manager', 'partner'], true) && $auth['id'] !== $userId) {
                $stmt = $this->pdo->prepare("SELECT role FROM users WHERE id = ?");
                $stmt->execute([$userId]);
                $targetRole = $stmt->fetchColumn();
                if (in_array($targetRole, ['manager', 'partner'], true)) {
                    header('Location: ' . $this->basePath());
                    exit;
                }
            }
            $stmt = $this->pdo->prepare(
                "INSERT INTO addresses (user_id, street, recipient_name, recipient_phone, is_primary, created_at)
                 VALUES (?, ?, ?, ?, 0, NOW())"
            );
            $stmt->execute([$userId, $address, $name, $phone]);
        }
        header('Location: ' . $this->basePath() . '/' . $userId);
        exit;
    }

    // Удаление адреса (админ/менеджер/партнёр)
    public function deleteAddressAdmin(): void
    {
        $auth   = Auth::user();
        $id     = (int)($_POST['id'] ?? 0);
        $userId = (int)($_POST['user_id'] ?? 0);
        if ($id && $userId) {
            if ($auth && in_array($auth['role'], ['manager', 'partner'], true) && $auth['id'] !== $userId) {
                $stmt = $this->pdo->prepare("SELECT role FROM users WHERE id = ?");
                $stmt->execute([$userId]);
                $targetRole = $stmt->fetchColumn();
                if (in_array($targetRole, ['manager', 'partner'], true)) {
                    header('Location: ' . $this->basePath());
                    exit;
                }
            }
            $this->pdo->prepare("DELETE FROM addresses WHERE id = ? AND user_id = ?")
                 ->execute([$id, $userId]);
        }
        header('Location: ' . $this->basePath() . '/' . $userId);
        exit;
    }

    // Поиск пользователей по телефону (JSON)
    public function searchPhone(): void
    {
        $auth = Auth::user();

        $term = preg_replace('/\D+/', '', $_GET['term'] ?? '');
        if ($term === '') {
            echo json_encode([]);
            return;
        }

        $sql = "SELECT u.id, u.name, u.phone, u.points_balance, u.referred_by, ref.name AS referrer_name
                FROM users u
                LEFT JOIN users ref ON ref.id = u.referred_by
                WHERE u.phone LIKE ?";
        $params = ['%' . $term . '%'];
        if ($auth && in_array($auth['role'], ['manager', 'partner'], true)) {
            $sql    .= " AND (u.role NOT IN ('partner','manager') OR u.id = ?)";
            $params[] = $auth['id'];
        }
        $sql .= " ORDER BY u.phone LIMIT 5";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $res = $stmt->fetchAll(PDO::FETCH_ASSOC);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($res);
    }

    // Список адресов пользователя (JSON)
    public function addresses(): void
    {
        $auth = Auth::user();
        $uid  = (int)($_GET['user_id'] ?? 0);
        if ($uid <= 0) {
            echo json_encode([]);
            return;
        }
        if ($auth && in_array($auth['role'], ['manager', 'partner'], true) && $auth['id'] !== $uid) {
            $stmt = $this->pdo->prepare("SELECT role FROM users WHERE id = ?");
            $stmt->execute([$uid]);
            $targetRole = $stmt->fetchColumn();
            if (in_array($targetRole, ['manager', 'partner'], true)) {
                echo json_encode([]);
                return;
            }
        }
        $stmt = $this->pdo->prepare(
            "SELECT id, street FROM addresses WHERE user_id = ? ORDER BY is_primary DESC, created_at ASC"
        );
        $stmt->execute([$uid]);
        $res = $stmt->fetchAll(PDO::FETCH_ASSOC);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($res);
    }
}
