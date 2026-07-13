<?php
namespace App\Controllers;

use PDO;
use App\Helpers\SensitiveData;
use App\Helpers\Auth;
use App\Helpers\ReferralHelper;
use App\Helpers\PhoneNormalizer;
use App\Models\OrdersRepository;
use App\Models\Order;
use App\Services\AdminOrdersPageService;
use App\Services\OrderStockOrchestrator;
use App\Services\StockService;
use App\Services\StockDeficitService;
use App\Services\DeliveryPricingService;
use App\Services\OrderStatusHistoryService;
use App\Services\OrderReturnService;
use App\Services\OrderTotalsService;
use App\Services\OrderGroupCreationService;
use App\Services\ProductionJobService;

class OrdersController
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Returns base path depending on user role
     */
    private function basePath(): string
    {
        $role = $_SESSION['role'] ?? '';
        return match ($role) {
            'manager' => '/manager/orders',
            'partner' => '/partner/orders',
            default   => '/admin/orders',
        };
    }

    /**
     * Find the project manager who receives the base 3% from every completed sale.
     * Root managers (without a referrer) are preferred; if none exist, fall back to the first manager.
     */
    private function findProjectManagerId(): int
    {
        $stmt = $this->pdo->query(
            "SELECT id FROM users WHERE role = 'manager' AND referred_by IS NULL ORDER BY id LIMIT 1"
        );
        $managerId = (int)($stmt ? ($stmt->fetchColumn() ?: 0) : 0);
        if ($managerId > 0) {
            return $managerId;
        }

        $stmt = $this->pdo->query("SELECT id FROM users WHERE role = 'manager' ORDER BY id LIMIT 1");
        return (int)($stmt ? ($stmt->fetchColumn() ?: 0) : 0);
    }

    /**
     * Ensure pickup address exists for the user and return its ID.
     */
    private function ensurePickupAddress(int $userId): int
    {
        $stmt = $this->pdo->prepare("SELECT name, phone FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        $name = $user['name'] ?? '';
        $phone = $user['phone'] ?? '';

        $street = 'Самовывоз: 9 мая, 73';
        $stmt = $this->pdo->prepare("SELECT id FROM addresses WHERE user_id = ? AND street = ? AND recipient_name = ? AND recipient_phone = ?");
        $stmt->execute([$userId, $street, $name, $phone]);
        if ($id = $stmt->fetchColumn()) {
            return (int)$id;
        }

        $this->pdo->prepare("INSERT INTO addresses (user_id, street, recipient_name, recipient_phone, is_primary, created_at) VALUES (?, ?, ?, ?, 0, NOW())")
            ->execute([$userId, $street, $name, $phone]);

        return (int)$this->pdo->lastInsertId();
    }


    private function columnExists(string $table, string $column): bool
    {
        static $cache = [];
        $key = spl_object_id($this->pdo) . '.' . $table . '.' . $column;
        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }

        $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $stmt = $this->pdo->query("PRAGMA table_info({$table})");
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                if (($row['name'] ?? '') === $column) {
                    return $cache[$key] = true;
                }
            }
            return $cache[$key] = false;
        }

        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
        );
        $stmt->execute([$table, $column]);
        return $cache[$key] = ((int)$stmt->fetchColumn() > 0);
    }

    /**
     * @return array{box_size:float,unit_price:float}
     */
    private function fetchManualItemPricing(int $productId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT p.box_size, p.price,\n" .
            "       COALESCE((\n" .
            "           SELECT CASE WHEN pb.status = 'planned' THEN COALESCE(NULLIF(pb.preorder_price_per_box, 0), NULLIF(p.preorder_price_per_box, 0), p.price, 0) ELSE pb.instant_price_per_box END\n" .
            "           FROM purchase_batches pb\n" .
            "           WHERE pb.product_id = p.id\n" .
            "             AND (\n" .
            "               (pb.status IN ('purchased', 'arrived') AND pb.boxes_free > 0 AND pb.instant_price_per_box > 0)\n" .
            "               OR (pb.status = 'planned' AND COALESCE(NULLIF(pb.preorder_price_per_box, 0), NULLIF(p.preorder_price_per_box, 0), p.price, 0) > 0)\n" .
            "             )\n" .
            "           ORDER BY CASE WHEN pb.status IN ('purchased', 'arrived') THEN 1 ELSE 2 END, pb.purchased_at ASC, pb.id ASC\n" .
            "           LIMIT 1\n" .
            "       ), p.price * COALESCE(NULLIF(p.box_size, 0), 1)) AS price_per_box\n" .
            "FROM products p\n" .
            "WHERE p.id = ?"
        );
        $stmt->execute([$productId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $boxSize = (float)($row['box_size'] ?? 1);
        if ($boxSize <= 0) {
            $boxSize = 1.0;
        }

        $pricePerBox = (float)($row['price_per_box'] ?? 0);
        if ($pricePerBox <= 0) {
            $pricePerBox = (float)($row['price'] ?? 0) * $boxSize;
        }

        return [
            'box_size' => $boxSize,
            'unit_price' => $boxSize > 0 ? $pricePerBox / $boxSize : $pricePerBox,
        ];
    }

    // Список заказов (админ/менеджер)
    public function index(): void
    {
        $managerId = isset($_GET['manager']) ? (int)$_GET['manager'] : 0;
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 25;
        $pageService = new AdminOrdersPageService($this->pdo);
        $data = $pageService->buildIndexData($managerId, $page, $perPage);

        viewAdmin('orders/index', array_merge([
            'pageTitle' => 'Заказы',
        ], $data));
    }

    // Детали заказа (админ)
    public function show(int $id): void
    {
        $pageService = new AdminOrdersPageService($this->pdo);
        $data = $pageService->findShowData($id);

        if ($data === null) {
            $msg = urlencode("Заказ {$id} удалён");
            header('Location: ' . $this->basePath() . "?msg={$msg}");
            exit;
        }

        viewAdmin('orders/show', array_merge([
            'pageTitle' => "Заказ #{$id}",
        ], $data));
    }


    public function createProductionJob(): void
    {
        $orderId = (int)($_POST['order_id'] ?? 0);
        if ($orderId <= 0) {
            header('Location: ' . $this->basePath());
            exit;
        }

        $productionDeadline = str_replace('T', ' ', (string)($_POST['production_deadline'] ?? ''));
        $handoverDeadline = str_replace('T', ' ', (string)($_POST['handover_deadline'] ?? ''));

        $service = new ProductionJobService($this->pdo);
        $service->create([
            'order_id' => $orderId,
            'fulfillment_model' => $_POST['fulfillment_model'] ?? 'by_berrygo_on_site',
            'production_location' => $_POST['production_location'] ?? 'shop',
            'production_deadline' => $productionDeadline !== '' ? $productionDeadline : null,
            'handover_deadline' => $handoverDeadline !== '' ? $handoverDeadline : null,
            'bonus_type' => $_POST['bonus_type'] ?? 'internal_bonus',
            'bonus_value' => (float)($_POST['bonus_value'] ?? 0),
            'bonus_amount_locked' => (float)($_POST['bonus_amount_locked'] ?? 0),
            'materials_delivery_required' => ((float)($_POST['materials_delivery_cost'] ?? 0)) > 0,
            'materials_delivery_cost' => (float)($_POST['materials_delivery_cost'] ?? 0),
            'result_delivery_required' => ((float)($_POST['result_delivery_cost'] ?? 0)) > 0,
            'result_delivery_cost' => (float)($_POST['result_delivery_cost'] ?? 0),
            'estimated_materials_cost' => (float)($_POST['estimated_materials_cost'] ?? 0),
            'minimum_margin_amount' => (float)($_POST['minimum_margin_amount'] ?? 0),
            'margin_status' => 'manual',
            'manager_comment' => trim((string)($_POST['manager_comment'] ?? '')) ?: null,
        ]);

        header('Location: ' . $this->basePath() . '/' . $orderId . '?msg=' . urlencode('Производственное задание создано'));
        exit;
    }

    public function assignProductionJob(): void
    {
        $orderId = (int)($_POST['order_id'] ?? 0);
        $jobId = (int)($_POST['job_id'] ?? 0);
        $executorId = (int)($_POST['executor_id'] ?? 0);
        $executorType = (string)($_POST['executor_type'] ?? 'internal_staff');
        $executorRef = (string)($_POST['executor_ref'] ?? '');
        if ($executorRef !== '' && str_contains($executorRef, ':')) {
            [$refType, $refId] = explode(':', $executorRef, 2);
            $executorType = $refType !== '' ? $refType : $executorType;
            $executorId = (int)$refId;
        }

        if ($orderId <= 0 || $jobId <= 0 || $executorId <= 0) {
            header('Location: ' . ($orderId > 0 ? $this->basePath() . '/' . $orderId : $this->basePath()) . '?error=' . urlencode('invalid production assignment'));
            exit;
        }

        $assigned = (new ProductionJobService($this->pdo))->assignAtomically(
            $jobId,
            $executorId,
            $executorType,
            isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null,
            $_SESSION['role'] ?? null
        );

        $param = $assigned ? 'msg' : 'error';
        $message = $assigned ? 'Исполнитель назначен' : 'Задание уже назначено или недоступно';
        header('Location: ' . $this->basePath() . '/' . $orderId . '?' . $param . '=' . urlencode($message));
        exit;
    }


    public function uploadProductionPhoto(): void
    {
        $orderId = (int)($_POST['order_id'] ?? 0);
        $jobId = (int)($_POST['job_id'] ?? 0);
        $photoType = (string)($_POST['photo_type'] ?? 'ready');
        if ($orderId <= 0 || $jobId <= 0 || empty($_FILES['photo']['tmp_name'])) {
            header('Location: ' . ($orderId > 0 ? $this->basePath() . '/' . $orderId : $this->basePath()) . '?error=' . urlencode('invalid production photo'));
            exit;
        }

        $tmp = (string)$_FILES['photo']['tmp_name'];
        $name = (string)($_FILES['photo']['name'] ?? 'photo');
        $error = (int)($_FILES['photo']['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error !== UPLOAD_ERR_OK || !is_uploaded_file($tmp)) {
            header('Location: ' . $this->basePath() . '/' . $orderId . '?error=' . urlencode('Фото не загружено'));
            exit;
        }

        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            $ext = 'jpg';
        }
        $dir = dirname(__DIR__, 2) . '/uploads/production';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $fileName = uniqid('production_' . $jobId . '_', true) . '.' . $ext;
        $absPath = $dir . '/' . $fileName;
        if (!move_uploaded_file($tmp, $absPath)) {
            header('Location: ' . $this->basePath() . '/' . $orderId . '?error=' . urlencode('Не удалось сохранить фото'));
            exit;
        }

        (new ProductionJobService($this->pdo))->addPhoto(
            $jobId,
            '/uploads/production/' . $fileName,
            $photoType,
            isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null,
            $_SESSION['role'] ?? null
        );

        header('Location: ' . $this->basePath() . '/' . $orderId . '?msg=' . urlencode('Фото производства загружено'));
        exit;
    }

    public function reviewProductionPhoto(): void
    {
        $orderId = (int)($_POST['order_id'] ?? 0);
        $photoId = (int)($_POST['photo_id'] ?? 0);
        $reviewStatus = (string)($_POST['review_status'] ?? 'rejected');
        $comment = trim((string)($_POST['review_comment'] ?? '')) ?: null;
        if ($orderId <= 0 || $photoId <= 0) {
            header('Location: ' . ($orderId > 0 ? $this->basePath() . '/' . $orderId : $this->basePath()) . '?error=' . urlencode('invalid production review'));
            exit;
        }

        (new ProductionJobService($this->pdo))->reviewPhoto(
            $photoId,
            $reviewStatus,
            isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null,
            $comment
        );

        header('Location: ' . $this->basePath() . '/' . $orderId . '?msg=' . urlencode('Проверка фото сохранена'));
        exit;
    }

    // Форма создания заказа вручную (админ)
    public function create(): void
    {
        $pageService = new AdminOrdersPageService($this->pdo);
        $data = $pageService->buildCreateData();

        viewAdmin('orders/create', array_merge([
            'pageTitle' => 'Создать заказ',
        ], $data));
    }

    // Сохранить заказ (POST, админ)
    /**
     * @param array<string,mixed> $items
     * @param array<string,mixed> $deliveryDates
     * @return array<int,array{stock_mode:string,purchase_batch_id:int,boxes:float,delivery_date:string}>
     */
    private function normalizeManualGroupedItems(array $items, array $deliveryDates, string $fallbackDate): array
    {
        $normalized = [];
        foreach (['instant', 'preorder'] as $mode) {
            if (!isset($items[$mode]) || !is_array($items[$mode])) {
                continue;
            }
            foreach ($items[$mode] as $batchId => $boxesRaw) {
                $boxes = (float)str_replace(',', '.', (string)$boxesRaw);
                if ($boxes <= 0) {
                    continue;
                }
                $date = $fallbackDate;
                if (isset($deliveryDates[$mode]) && is_array($deliveryDates[$mode]) && isset($deliveryDates[$mode][$batchId])) {
                    $date = (string)$deliveryDates[$mode][$batchId];
                }
                $date = substr($date, 0, 10);
                $normalized[] = [
                    'stock_mode' => $mode,
                    'purchase_batch_id' => (int)$batchId,
                    'boxes' => $boxes,
                    'delivery_date' => $date,
                ];
            }
        }
        return $normalized;
    }

    public function storeManual(): void
    {
        $userId = (int)($_POST['user_id'] ?? 0);
        $isNew  = $userId === 0;
        $hasUsedReferral = 0;
        $isPickup = false;

        if ($isNew) {
            $name  = trim($_POST['new_name'] ?? '');
            if ($name === '') {
                $name = 'Клиент';
            }
            $phone = PhoneNormalizer::normalize($_POST['new_phone'] ?? '');
            $address = trim($_POST['new_address'] ?? '');
            $isPickup = $address === '';
            if ($isPickup) {
                $address = 'Самовывоз: 9 мая, 73';
            }
            $pin   = trim($_POST['new_pin'] ?? '');
            if (!preg_match('/^7\d{10}$/', $phone) || !preg_match('/^\d{4}$/', $pin)) {
                header('Location: ' . $this->basePath() . '/create?error=' . urlencode('invalid user'));
                exit;
            }

            // Check for duplicate phone number to avoid unique constraint errors
            $stmt = $this->pdo->prepare('SELECT id FROM users WHERE phone = ?');
            $stmt->execute([$phone]);
            if ($stmt->fetchColumn()) {
                header('Location: ' . $this->basePath() . '/create?error=' . urlencode('phone_exists'));
                exit;
            }

            $refCode = ReferralHelper::generateUniqueCode($this->pdo, 8);
            $managerId = $_SESSION['user_id'] ?? null;
            $pinHash = password_hash($pin, PASSWORD_DEFAULT);
            $hasUsedReferral = isset($_POST['has_used_referral_coupon']) && $_POST['has_used_referral_coupon'] === '1' ? 1 : 0;

            $this->pdo->beginTransaction();
            $stmt = $this->pdo->prepare(
                "INSERT INTO users (role, name, phone, password_hash, referral_code, referred_by, has_used_referral_coupon, points_balance, created_at) VALUES ('client', ?, ?, ?, ?, ?, ?, 0, NOW())"
            );
            $stmt->execute([$name, $phone, $pinHash, $refCode, $managerId, $hasUsedReferral]);
            $userId = (int)$this->pdo->lastInsertId();

            if ($address !== '') {
                $stmtA = $this->pdo->prepare(
                    "INSERT INTO addresses (user_id, street, recipient_name, recipient_phone, is_primary, created_at) VALUES (?, ?, ?, ?, 1, NOW())"
                );
                $stmtA->execute([$userId, $address, $name, $phone]);
                $addressId = (int)$this->pdo->lastInsertId();
            } else {
                $addressId = null;
            }

            if ($managerId) {
                $this->pdo->prepare(
                    "INSERT IGNORE INTO referrals (referrer_id, referred_id, created_at) VALUES (?, ?, NOW())"
                )->execute([$managerId, $userId]);
            }
            $this->pdo->commit();
            $referralDiscount = $hasUsedReferral === 1;
        } else {
            $addrInput = $_POST['address_id'] ?? null;
            $isPickup = ($addrInput === 'pickup');
            if ($isPickup) {
                $addressId = $this->ensurePickupAddress($userId);
            } elseif ($addrInput === 'new') {
                $newStreet = trim($_POST['address_new'] ?? '');
                if ($newStreet === '') {
                    header('Location: ' . $this->basePath() . '/create?error=' . urlencode('address'));
                    exit;
                }
                $stmtUser = $this->pdo->prepare('SELECT name, phone FROM users WHERE id = ?');
                $stmtUser->execute([$userId]);
                $userRow = $stmtUser->fetch(PDO::FETCH_ASSOC) ?: ['name' => '', 'phone' => ''];
                $stmtA = $this->pdo->prepare(
                    "INSERT INTO addresses (user_id, street, recipient_name, recipient_phone, is_primary, created_at) VALUES (?, ?, ?, ?, 0, NOW())"
                );
                $stmtA->execute([$userId, $newStreet, $userRow['name'] ?? '', $userRow['phone'] ?? '']);
                $addressId = (int)$this->pdo->lastInsertId();
            } elseif ($addrInput !== null && $addrInput !== '') {
                $addressId = is_numeric($addrInput) ? (int)$addrInput : null;
            } else {
                $addressId = null;
            }

            $hasUsedReferral = 1;
            $referralDiscount = isset($_POST['has_used_referral_coupon']) && $_POST['has_used_referral_coupon'] === '1';
            if ($referralDiscount) {
                $stmtReferralUser = $this->pdo->prepare('SELECT has_used_referral_coupon FROM users WHERE id = ?');
                $stmtReferralUser->execute([$userId]);
                $hasUsedReferral = (int)$stmtReferralUser->fetchColumn();
                $referralDiscount = $hasUsedReferral === 0;
            }
        }

        if ($userId <= 0) {
            header('Location: ' . $this->basePath() . '/create?error=' . urlencode('user'));
            exit;
        }

        $slotId = $_POST['slot_id'] ?? null;
        $deliveryDate = $_POST['delivery_date'] ?? null;
        $couponCode = trim($_POST['coupon_code'] ?? '');

        if (isset($_POST['items']) && is_array($_POST['items'])) {
            $selectedItems = $this->normalizeManualGroupedItems($_POST['items'], $_POST['delivery_dates'] ?? [], (string)$deliveryDate);
            if ($selectedItems === []) {
                header('Location: ' . $this->basePath() . '/create?error=' . urlencode('Добавьте товары в заказ'));
                exit;
            }

            $pointsBalance = 0;
            $usePoints = (int)($_POST['use_points'] ?? 0) === 1;
            if (!$isNew && $usePoints) {
                $stmtPoints = $this->pdo->prepare('SELECT points_balance FROM users WHERE id = ?');
                $stmtPoints->execute([$userId]);
                $pointsBalance = (int)$stmtPoints->fetchColumn();
            }

            try {
                $service = new OrderGroupCreationService($this->pdo);
                $service->createForManualOrder(
                    $userId,
                    (int)$addressId,
                    $slotId !== null && $slotId !== '' ? (int)$slotId : null,
                    $selectedItems,
                    [
                        'created_by_user_id' => (int)($_SESSION['user_id'] ?? 0),
                        'coupon_code' => $couponCode,
                        'delivery_fee' => (int)($_POST['delivery_fee_preview'] ?? 300),
                        'delivery_comment' => trim((string)($_POST['delivery_comment'] ?? '')),
                        'referral_discount' => $referralDiscount,
                        'points' => $usePoints ? (int)($_POST['points'] ?? 0) : 0,
                        'available_points' => $pointsBalance,
                    ]
                );
                if ($referralDiscount && $hasUsedReferral === 0) {
                    $this->pdo->prepare('UPDATE users SET has_used_referral_coupon = 1 WHERE id = ?')->execute([$userId]);
                }
                header('Location: ' . $this->basePath());
                exit;
            } catch (\Throwable $e) {
                error_log('[manual_order_group] ' . $e->getMessage());
                header('Location: ' . $this->basePath() . '/create?error=' . urlencode($e->getMessage() ?: 'Заказ не создан. Попробуйте ещё раз'));
                exit;
            }
        }

        $items = $_POST['batch_items'] ?? [];
        if (!$items) {
            header('Location: ' . $this->basePath() . '/create?error=' . urlencode('empty'));
            exit;
        }

        $selectedMode = $_POST['stock_mode'] ?? '';
        if (!in_array($selectedMode, ['instant', 'preorder'], true)) {
            header('Location: ' . $this->basePath() . '/create?error=' . urlencode('stock_mode'));
            exit;
        }

        $batchIds = [];
        foreach ($items as $batchId => $boxes) {
            if ((float)$boxes > 0) {
                $batchIds[] = (int)$batchId;
            }
        }
        if ($batchIds === []) {
            header('Location: ' . $this->basePath() . '/create?error=' . urlencode('empty'));
            exit;
        }

        $placeholders = implode(',', array_fill(0, count($batchIds), '?'));
        $stmtBatches = $this->pdo->prepare(
            "SELECT pb.id AS purchase_batch_id, pb.product_id, pb.status, pb.purchased_at,\n" .
            "       pb.boxes_free, pb.boxes_total, pb.boxes_reserved,\n" .
            "       pb.instant_price_per_box, pb.preorder_price_per_box,\n" .
            "       p.price AS product_price_per_box, p.preorder_price_per_box AS product_preorder_price_per_box,\n" .
            "       COALESCE(NULLIF(pb.box_size_snapshot, 0), NULLIF(p.box_size, 0), 1) AS box_size\n" .
            "FROM purchase_batches pb\n" .
            "JOIN products p ON p.id = pb.product_id\n" .
            "WHERE pb.id IN ({$placeholders})"
        );
        $stmtBatches->execute($batchIds);
        $batchRows = [];
        foreach ($stmtBatches->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $batchRows[(int)$row['purchase_batch_id']] = $row;
        }

        $total = 0;
        $itemsPrepared = [];
        $dateBases = [];
        foreach ($batchIds as $batchId) {
            if (!isset($batchRows[$batchId])) {
                header('Location: ' . $this->basePath() . '/create?error=' . urlencode('batch'));
                exit;
            }
            $boxes = (float)$items[$batchId];
            $batch = $batchRows[$batchId];
            $isPreorderBatch = ($batch['status'] ?? '') === 'planned';
            $mode = $isPreorderBatch ? 'preorder' : 'instant';
            if ($mode !== $selectedMode) {
                header('Location: ' . $this->basePath() . '/create?error=' . urlencode('mixed_mode'));
                exit;
            }
            if ($mode === 'instant' && !in_array($batch['status'], ['purchased', 'arrived'], true)) {
                header('Location: ' . $this->basePath() . '/create?error=' . urlencode('batch_status'));
                exit;
            }
            $availableBoxes = $mode === 'preorder'
                ? max(0.0, (float)($batch['boxes_total'] ?: ((float)$batch['boxes_free'] + (float)$batch['boxes_reserved'])) - (float)$batch['boxes_reserved'])
                : (float)$batch['boxes_free'];
            if ($boxes > $availableBoxes) {
                header('Location: ' . $this->basePath() . '/create?error=' . urlencode('stock'));
                exit;
            }

            $pricePerBox = (float)($mode === 'preorder' ? $batch['preorder_price_per_box'] : $batch['instant_price_per_box']);
            if ($mode === 'preorder' && $pricePerBox <= 0) {
                $pricePerBox = (float)(($batch['product_preorder_price_per_box'] ?? 0) ?: ($batch['product_price_per_box'] ?? 0));
            }
            if ($pricePerBox <= 0) {
                header('Location: ' . $this->basePath() . '/create?error=' . urlencode('price'));
                exit;
            }
            $total += $boxes * $pricePerBox;
            $batchDate = substr((string)($batch['purchased_at'] ?? ''), 0, 10);
            $dateBases[] = $mode === 'preorder' && $batchDate !== '' ? $batchDate : ($deliveryDate !== '' ? $deliveryDate : date('Y-m-d'));
            $itemsPrepared[] = [
                'product_id' => (int)$batch['product_id'],
                'purchase_batch_id' => $batchId,
                'boxes' => $boxes,
                'box_size' => (float)$batch['box_size'],
                'price_per_box' => $pricePerBox,
                'stock_mode' => $mode,
            ];
        }

        if (count(array_unique($dateBases)) > 1) {
            header('Location: ' . $this->basePath() . '/create?error=' . urlencode('mixed_delivery_date'));
            exit;
        }

        $baseDate = min($dateBases);
        $allowedDates = [];
        for ($i = 0; $i <= 2; $i++) {
            $allowedDates[] = date('Y-m-d', strtotime($baseDate . " +{$i} day"));
        }
        if (!in_array($deliveryDate, $allowedDates, true)) {
            header('Location: ' . $this->basePath() . '/create?error=' . urlencode('delivery_date'));
            exit;
        }

        if ($referralDiscount) {
            $total = (int)floor($total * 0.9);
            if ($hasUsedReferral === 0) {
                $this->pdo->prepare("UPDATE users SET has_used_referral_coupon = 1 WHERE id = ?")->execute([$userId]);
            }
        }

        $pointsUsed = 0;
        $usePoints = (int)($_POST['use_points'] ?? 0) === 1;
        if (!$isNew && $usePoints) {
            $stmtPoints = $this->pdo->prepare("SELECT points_balance FROM users WHERE id = ?");
            $stmtPoints->execute([$userId]);
            $balance = (int)$stmtPoints->fetchColumn();
            $pointsUsed = min($balance, (int)($_POST['points'] ?? 0), (int)$total);
            $total -= $pointsUsed;
        }

        $deliveryComment = trim((string)($_POST['delivery_comment'] ?? ''));
        $deliveryDistanceManualRaw = str_replace(',', '.', trim((string)($_POST['delivery_distance_km_manual'] ?? '')));
        $selectedLatRaw = str_replace(',', '.', trim((string)($_POST['selected_lat'] ?? '')));
        $selectedLngRaw = str_replace(',', '.', trim((string)($_POST['selected_lng'] ?? '')));
        $selectedAddressRaw = trim((string)($_POST['selected_address'] ?? ''));
        $selectedAddressPayload = [];
        if ($selectedLatRaw !== '' && $selectedLngRaw !== '' && is_numeric($selectedLatRaw) && is_numeric($selectedLngRaw)) {
            $selectedAddressPayload = [
                'selected_lat' => $selectedLatRaw,
                'selected_lng' => $selectedLngRaw,
                'selected_address' => $selectedAddressRaw,
            ];
        }
        $deliveryFee = 300;
        $deliveryDistanceKm = null;
        $deliveryDistanceM = null;
        $deliveryTariffZoneId = null;
        $deliveryPricingSource = 'pending_review';
        $deliveryLat = null;
        $deliveryLng = null;
        $deliveryNormalizedAddress = null;
        $deliveryDistanceError = null;

        if ($isPickup) {
            $deliveryFee = 0;
            $deliveryPricingSource = 'pickup';
        } else {
            $addressForDelivery = '';
            if ($isNew) {
                $addressForDelivery = $address;
            } elseif (($addrInput ?? null) === 'new') {
                $addressForDelivery = $newStreet ?? '';
            } elseif (!empty($addressId)) {
                $stmtAddress = $this->pdo->prepare('SELECT street FROM addresses WHERE id = ? AND user_id = ?');
                $stmtAddress->execute([$addressId, $userId]);
                $addressForDelivery = (string)($stmtAddress->fetchColumn() ?: '');
            }

            try {
                $deliveryPricing = new DeliveryPricingService($this->pdo);
                if ($deliveryDistanceManualRaw !== '' && is_numeric($deliveryDistanceManualRaw)) {
                    $deliveryDistanceKm = max(0.0, (float)$deliveryDistanceManualRaw);
                    $deliveryDistanceM = (int)round($deliveryDistanceKm * 1000);
                    $pricing = $deliveryPricing->calculatePriceForDistance($deliveryDistanceKm);
                    $deliveryFee = (int)$pricing['price_rub'];
                    $deliveryTariffZoneId = is_array($pricing['zone']) && isset($pricing['zone']['id']) ? (int)$pricing['zone']['id'] : null;
                    $deliveryPricingSource = 'manual';
                    $deliveryNormalizedAddress = $addressForDelivery;
                } elseif ($addressForDelivery !== '') {
                    $deliveryCalc = $deliveryPricing->calculateForAddress($addressForDelivery, null, $selectedAddressPayload);
                    $deliveryFee = (int)($deliveryCalc['delivery_fee'] ?? $deliveryCalc['price_rub'] ?? 300);
                    $deliveryDistanceKm = isset($deliveryCalc['distance_km']) && $deliveryCalc['distance_km'] !== '' ? (float)$deliveryCalc['distance_km'] : null;
                    $deliveryDistanceM = isset($deliveryCalc['distance_m']) && $deliveryCalc['distance_m'] !== '' ? (int)round((float)$deliveryCalc['distance_m']) : null;
                    $deliveryTariffZoneId = $deliveryCalc['delivery_tariff_zone_id'] ?? null;
                    $deliveryPricingSource = (string)($deliveryCalc['delivery_pricing_source'] ?? $deliveryCalc['pricing_source'] ?? '');
                    $deliveryLat = $deliveryCalc['lat'] ?? null;
                    $deliveryLng = $deliveryCalc['lng'] ?? null;
                    $deliveryNormalizedAddress = $deliveryCalc['normalized_address'] ?? $deliveryCalc['address'] ?? $addressForDelivery;
                }
            } catch (\Throwable $e) {
                $deliveryFee = 300;
                $deliveryPricingSource = 'pending_review';
                $deliveryDistanceError = $e->getMessage();
            }
        }

        $shippingFee = $deliveryFee;
        $total += $shippingFee;

        if (!empty($addressId) && !$isPickup) {
            $addressSet = [];
            $addressValues = [];
            foreach ([
                'last_checkout_comment' => $deliveryComment,
                'delivery_distance_km' => $deliveryDistanceKm,
                'delivery_distance_m' => $deliveryDistanceM,
                'delivery_lat' => $deliveryLat,
                'delivery_lng' => $deliveryLng,
                'delivery_normalized_address' => $deliveryNormalizedAddress,
                'delivery_distance_provider' => $deliveryPricingSource,
                'delivery_distance_error' => $deliveryDistanceError,
            ] as $column => $value) {
                if ($this->columnExists('addresses', $column)) {
                    $addressSet[] = $column . ' = ?';
                    $addressValues[] = $value;
                }
            }
            if ($this->columnExists('addresses', 'delivery_distance_calculated_at')) {
                $addressSet[] = 'delivery_distance_calculated_at = NOW()';
            }
            if ($addressSet) {
                $addressValues[] = $addressId;
                $addressValues[] = $userId;
                $this->pdo->prepare('UPDATE addresses SET ' . implode(', ', $addressSet) . ' WHERE id = ? AND user_id = ?')
                    ->execute($addressValues);
            }
        }

        $createdByUserId = (int)($_SESSION['user_id'] ?? 0);
        $orderStatus = $selectedMode === 'preorder' ? 'reserved' : 'new';
        $orderMode = $selectedMode === 'preorder' ? 'preorder' : 'instant';
        $orderBatchId = count($itemsPrepared) === 1 ? $itemsPrepared[0]['purchase_batch_id'] : null;

        try {
            $this->pdo->beginTransaction();
            $orderColumns = ['user_id', 'address_id', 'slot_id', 'status', 'total_amount', 'discount_applied', 'points_used', 'points_accrued', 'coupon_code', 'delivery_date', 'created_by_user_id', 'created_at'];
            $orderValues = [$userId, $addressId, $slotId, $orderStatus, $total, 0, $pointsUsed, 0, $couponCode, $deliveryDate, $createdByUserId > 0 ? $createdByUserId : null];
            $orderPlaceholders = ['?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', 'NOW()'];
            if ($this->columnExists('orders', 'order_mode')) {
                $orderColumns[] = 'order_mode';
                $orderValues[] = $orderMode;
                $orderPlaceholders[] = '?';
            }
            if ($this->columnExists('orders', 'purchase_batch_id')) {
                $orderColumns[] = 'purchase_batch_id';
                $orderValues[] = $orderBatchId;
                $orderPlaceholders[] = '?';
            }
            foreach ([
                'delivery_fee' => $deliveryFee,
                'delivery_distance_km' => $deliveryDistanceKm,
                'delivery_tariff_zone_id' => $deliveryTariffZoneId,
                'delivery_pricing_source' => $deliveryPricingSource,
                'delivery_comment' => $deliveryComment,
            ] as $column => $value) {
                if ($this->columnExists('orders', $column)) {
                    $orderColumns[] = $column;
                    $orderValues[] = $value;
                    $orderPlaceholders[] = '?';
                }
            }

            $stmt = $this->pdo->prepare(
                'INSERT INTO orders (' . implode(', ', $orderColumns) . ') VALUES (' . implode(', ', $orderPlaceholders) . ')'
            );
            $stmt->execute($orderValues);
            $orderId = (int)$this->pdo->lastInsertId();

            $stmtItem = $this->pdo->prepare(
                "INSERT INTO order_items (order_id, product_id, quantity, boxes, unit_price, stock_mode, purchase_batch_id) VALUES (?, ?, ?, ?, ?, ?, ?)"
            );
            $orderStock = new OrderStockOrchestrator($this->pdo, new StockService($this->pdo));
            foreach ($itemsPrepared as $data) {
                $itemPayload = [
                    'quantity' => $data['boxes'],
                    'box_size' => $data['box_size'],
                    'unit_price' => $data['price_per_box'],
                    'purchase_batch_id' => $data['purchase_batch_id'],
                ];
                if ($selectedMode === 'preorder') {
                    $orderStock->persistOrderItemWithStock(
                        $stmtItem,
                        $orderId,
                        $data['product_id'],
                        $itemPayload,
                        $data['stock_mode'],
                        true
                    );
                } else {
                    $orderStock->persistOrderItemOnly(
                        $stmtItem,
                        $orderId,
                        $data['product_id'],
                        $itemPayload,
                        $data['stock_mode']
                    );
                }
            }
            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            header('Location: ' . $this->basePath() . '/create?error=' . urlencode($e->getMessage()));
            exit;
        }

        if ($pointsUsed > 0) {
            $this->pdo->prepare("UPDATE users SET points_balance = points_balance - ? WHERE id = ?")->execute([$pointsUsed, $userId]);
            $desc = "Списание {$pointsUsed} клубничек за заказ #{$orderId}";
            $this->pdo->prepare(
                "INSERT INTO points_transactions (user_id, order_id, amount, transaction_type, description, created_at) VALUES (?, ?, ?, 'usage', ?, NOW())"
            )->execute([$userId, $orderId, -$pointsUsed, $desc]);
        }

        header('Location: ' . $this->basePath());
        exit;
    }

    // Назначить курьера (POST, админ)
    public function assign(): void
    {
        $orderId   = (int)($_POST['order_id'] ?? 0);
        $courierId = (int)($_POST['courier_id'] ?? 0);
        if ($orderId && $courierId) {
            $currentStatusStmt = $this->pdo->prepare('SELECT status FROM orders WHERE id = ?');
            $currentStatusStmt->execute([$orderId]);
            $fromStatus = $currentStatusStmt->fetchColumn();

            if ((string)$fromStatus === 'new') {
                $stockService = new StockService($this->pdo);
                (new OrderStockOrchestrator($this->pdo, $stockService))->applyStockForOrderId($orderId);
                (new StockDeficitService($this->pdo))->notifyAdminsIfChanged('назначен курьер на заказ №' . $orderId);
            }

            $stmt = $this->pdo->prepare(
                "UPDATE orders SET assigned_to = ?, status = 'shipped' WHERE id = ?"
            );
            $stmt->execute([$courierId, $orderId]);

            (new OrderStatusHistoryService($this->pdo))->record(
                $orderId,
                $fromStatus !== false ? (string)$fromStatus : null,
                'shipped',
                isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null,
                isset($_SESSION['role']) ? (string)$_SESSION['role'] : null,
                'Назначен курьер'
            );
        }
        header('Location: ' . $this->basePath() . '/' . $orderId);
        exit;
    }

    // Обновление адреса и времени доставки
    public function updateDelivery(): void
    {
        $orderId = (int)($_POST['order_id'] ?? 0);
        if ($orderId) {
            $stmtOrder = $this->pdo->prepare('SELECT id, status, total_amount, delivery_fee, user_id FROM orders WHERE id = ?');
            $stmtOrder->execute([$orderId]);
            $order = $stmtOrder->fetch(PDO::FETCH_ASSOC) ?: null;

            $addressRaw = $_POST['address_id'] ?? null;
            if ($addressRaw === 'pickup') {
                $addressId = null;
            } elseif ($addressRaw !== null && $addressRaw !== '') {
                $addressId = is_numeric($addressRaw) ? (int)$addressRaw : null;
            } else {
                $addressId = null;
            }

            $deliveryDate = $_POST['delivery_date'] ?? null;
            $slotId = $_POST['slot_id'] ?? null;
            if ($slotId === '') {
                $slotId = null;
            }

            $deliveryComment = trim((string)($_POST['delivery_comment'] ?? ''));
            $manualDistanceRaw = str_replace(',', '.', trim((string)($_POST['delivery_distance_km_manual'] ?? '')));
            $selectedLatRaw = str_replace(',', '.', trim((string)($_POST['selected_lat'] ?? '')));
            $selectedLngRaw = str_replace(',', '.', trim((string)($_POST['selected_lng'] ?? '')));
            $selectedAddressRaw = trim((string)($_POST['selected_address'] ?? ''));
            $selectedAddressPayload = [];
            if ($selectedLatRaw !== '' && $selectedLngRaw !== '' && is_numeric($selectedLatRaw) && is_numeric($selectedLngRaw)) {
                $selectedAddressPayload = [
                    'selected_lat' => $selectedLatRaw,
                    'selected_lng' => $selectedLngRaw,
                    'selected_address' => $selectedAddressRaw,
                ];
            }
            $canReprice = $order && !in_array((string)($order['status'] ?? ''), ['completed', 'cancelled', 'returned'], true);

            $deliveryFee = max(0, (int)($order['delivery_fee'] ?? 0));
            $deliveryDistanceKm = null;
            $deliveryTariffZoneId = null;
            $deliveryPricingSource = $addressId === null ? 'pickup' : 'pending_review';
            $deliveryDistanceM = null;
            $deliveryLat = null;
            $deliveryLng = null;
            $deliveryNormalizedAddress = null;
            $deliveryDistanceError = null;

            if ($canReprice) {
                if ($addressId === null) {
                    $deliveryFee = 0;
                    $deliveryPricingSource = 'pickup';
                } else {
                    $addressForDelivery = '';
                    if ($addressId > 0) {
                        $stmtAddress = $this->pdo->prepare('SELECT street FROM addresses WHERE id = ? AND user_id = ?');
                        $stmtAddress->execute([$addressId, (int)($order['user_id'] ?? 0)]);
                        $addressForDelivery = (string)($stmtAddress->fetchColumn() ?: '');
                    }

                    try {
                        $deliveryPricing = new DeliveryPricingService($this->pdo);
                        if ($manualDistanceRaw !== '' && is_numeric($manualDistanceRaw)) {
                            $deliveryDistanceKm = max(0.0, (float)$manualDistanceRaw);
                            $deliveryDistanceM = (int)round($deliveryDistanceKm * 1000);
                            $pricing = $deliveryPricing->calculatePriceForDistance($deliveryDistanceKm);
                            $deliveryFee = (int)($pricing['price_rub'] ?? 300);
                            $deliveryTariffZoneId = is_array($pricing['zone'] ?? null) && isset($pricing['zone']['id']) ? (int)$pricing['zone']['id'] : null;
                            $deliveryPricingSource = 'manual';
                            $deliveryNormalizedAddress = $addressForDelivery;
                        } elseif ($addressForDelivery !== '') {
                            $deliveryCalc = $deliveryPricing->calculateForAddress($addressForDelivery, null, $selectedAddressPayload);
                            $deliveryFee = (int)($deliveryCalc['delivery_fee'] ?? $deliveryCalc['price_rub'] ?? 300);
                            $deliveryDistanceKm = isset($deliveryCalc['distance_km']) && $deliveryCalc['distance_km'] !== '' ? (float)$deliveryCalc['distance_km'] : null;
                            $deliveryDistanceM = isset($deliveryCalc['distance_m']) && $deliveryCalc['distance_m'] !== '' ? (int)round((float)$deliveryCalc['distance_m']) : null;
                            $deliveryTariffZoneId = $deliveryCalc['delivery_tariff_zone_id'] ?? null;
                            $deliveryPricingSource = (string)($deliveryCalc['delivery_pricing_source'] ?? $deliveryCalc['pricing_source'] ?? 'pending_review');
                            $deliveryLat = $deliveryCalc['lat'] ?? null;
                            $deliveryLng = $deliveryCalc['lng'] ?? null;
                            $deliveryNormalizedAddress = $deliveryCalc['normalized_address'] ?? $deliveryCalc['address'] ?? $addressForDelivery;
                        } else {
                            $deliveryFee = 300;
                            $deliveryPricingSource = 'pending_review';
                        }
                    } catch (\Throwable $e) {
                        $deliveryFee = 300;
                        $deliveryPricingSource = 'pending_review';
                        $deliveryDistanceError = $e->getMessage();
                    }
                }
            }

            $setParts = ['address_id = ?', 'delivery_date = ?', 'slot_id = ?'];
            $values = [$addressId, $deliveryDate, $slotId];

            if ($canReprice) {
                $oldDeliveryFee = max(0, (int)($order['delivery_fee'] ?? 0));
                $newTotalAmount = max(0, (int)($order['total_amount'] ?? 0) - $oldDeliveryFee + $deliveryFee);
                $setParts[] = 'total_amount = ?';
                $values[] = $newTotalAmount;

                foreach ([
                    'delivery_fee' => $deliveryFee,
                    'delivery_distance_km' => $deliveryDistanceKm,
                    'delivery_tariff_zone_id' => $deliveryTariffZoneId,
                    'delivery_pricing_source' => $deliveryPricingSource,
                    'delivery_comment' => $deliveryComment,
                ] as $column => $value) {
                    if ($this->columnExists('orders', $column)) {
                        $setParts[] = $column . ' = ?';
                        $values[] = $value;
                    }
                }
            } elseif ($this->columnExists('orders', 'delivery_comment')) {
                $setParts[] = 'delivery_comment = ?';
                $values[] = $deliveryComment;
            }

            $values[] = $orderId;
            $stmt = $this->pdo->prepare('UPDATE orders SET ' . implode(', ', $setParts) . ' WHERE id = ?');
            $stmt->execute($values);

            if ($canReprice && $addressId !== null) {
                $addressSet = [];
                $addressValues = [];
                foreach ([
                    'last_checkout_comment' => $deliveryComment,
                    'delivery_distance_km' => $deliveryDistanceKm,
                    'delivery_distance_m' => $deliveryDistanceM,
                    'delivery_lat' => $deliveryLat,
                    'delivery_lng' => $deliveryLng,
                    'delivery_normalized_address' => $deliveryNormalizedAddress,
                    'delivery_distance_provider' => $deliveryPricingSource,
                    'delivery_distance_calculated_at' => date('Y-m-d H:i:s'),
                    'delivery_distance_error' => $deliveryDistanceError,
                ] as $column => $value) {
                    if ($this->columnExists('addresses', $column)) {
                        $addressSet[] = $column . ' = ?';
                        $addressValues[] = $value;
                    }
                }
                if ($addressSet) {
                    $addressValues[] = $addressId;
                    $stmtAddressUpdate = $this->pdo->prepare('UPDATE addresses SET ' . implode(', ', $addressSet) . ' WHERE id = ?');
                    $stmtAddressUpdate->execute($addressValues);
                }
            }
        }
        header('Location: ' . $this->basePath() . '/' . $orderId);
        exit;
    }

    // Обновить статус (POST, админ)
    public function updateStatus(): void
    {
        $orderId = (int)($_POST['order_id'] ?? 0);
        $status  = $_POST['status'] ?? '';
        if ($orderId && in_array($status, ['reserved','new','confirmed','shipped','completed','cancelled','returned'], true)) {
            // Получаем текущий статус и данные заказа
            $stmt = $this->pdo->prepare(
                "SELECT status, user_id, total_amount, delivery_fee, points_accrued, manager_points_accrued, points_used, created_by_user_id FROM orders WHERE id = ?"
            );
            $stmt->execute([$orderId]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($order) {
                $stockService = new StockService($this->pdo);
                $orderStock = new OrderStockOrchestrator($this->pdo, $stockService);
                if (in_array($status, ['confirmed', 'shipped', 'completed'], true) && (string)$order['status'] === 'new') {
                    $orderStock->applyStockForOrderId($orderId);
                }
                if ($status === 'completed' && $order['status'] !== 'completed') {
                    $orderStock->commitReservedStockByOrderId($orderId);
                }
                if (in_array($status, ['confirmed', 'shipped', 'completed'], true)) {
                    (new StockDeficitService($this->pdo))->notifyAdminsIfChanged('заказ №' . $orderId . ' → ' . $status);
                }
                if ($status === 'cancelled' && (string)$order['status'] === 'completed') {
                    header('Location: ' . $this->basePath() . '/' . $orderId);
                    exit;
                }
                if ($status === 'returned') {
                    if ((string)$order['status'] !== 'completed') {
                        header('Location: ' . $this->basePath() . '/' . $orderId);
                        exit;
                    }
                    $this->pdo->beginTransaction();
                    try {
                        (new OrderReturnService($this->pdo, $stockService))->returnCompletedOrder(
                            $orderId,
                            isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null
                        );
                        $this->pdo->prepare("UPDATE orders SET status = ? WHERE id = ?")->execute([$status, $orderId]);
                        (new OrderStatusHistoryService($this->pdo))->record(
                            $orderId,
                            (string)$order['status'],
                            $status,
                            isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null,
                            isset($_SESSION['role']) ? (string)$_SESSION['role'] : null
                        );
                        $this->pdo->commit();
                    } catch (\Throwable $e) {
                        if ($this->pdo->inTransaction()) {
                            $this->pdo->rollBack();
                        }
                        throw $e;
                    }
                    header('Location: ' . $this->basePath() . '/' . $orderId);
                    exit;
                }

                // Если переводим в completed впервые — начисляем бонусы
                if ($status === 'completed' && $order['status'] !== 'completed') {
                    $userId = (int)$order['user_id'];
                    // total_amount уже хранит сумму после скидок/клубничек; доставку исключаем из базы бонусов.
                    $sum    = max(0, (int)$order['total_amount'] - max(0, (int)($order['delivery_fee'] ?? 0)));

                    // 5% личный бонус
                    $personal = (int) floor($sum * 0.05);
                    if ($personal > 0) {
                        $this->pdo->prepare(
                            "UPDATE users SET points_balance = points_balance + ? WHERE id = ?"
                        )->execute([$personal, $userId]);

                        $desc = "Начисление {$personal} за заказ №{$orderId}";
                        $this->pdo->prepare(
                            "INSERT INTO points_transactions (user_id, order_id, amount, transaction_type, description, created_at) VALUES (?, ?, ?, 'accrual', ?, NOW())"
                        )->execute([$userId, $orderId, $personal, $desc]);
                    }

                    // Бонусы пригласившему и управляющему менеджеру (если ещё не начислено)
                    if ((int)$order['points_accrued'] === 0 && (int)$order['manager_points_accrued'] === 0) {
                        $refStmt = $this->pdo->prepare("SELECT referred_by FROM users WHERE id = ?");
                        $refStmt->execute([$userId]);
                        $refId = (int)($refStmt->fetchColumn() ?: 0);

                        $refBonus = 0;
                        if ($refId) {
                            // Получаем роль пригласившего для расчёта партнёрского/реферального процента.
                            $infoStmt = $this->pdo->prepare("SELECT role FROM users WHERE id = ?");
                            $infoStmt->execute([$refId]);
                            $refInfo = $infoStmt->fetch(PDO::FETCH_ASSOC) ?: [];

                            $refRole = $refInfo['role'] ?? '';
                            $isPartnerReferrer = ($refRole === 'partner');
                            $isFirstClientOrder = false;
                            if ($isPartnerReferrer) {
                                $ordersCountStmt = $this->pdo->prepare(
                                    "SELECT COUNT(*) FROM orders WHERE user_id = ? AND status = 'completed' AND id <> ?"
                                );
                                $ordersCountStmt->execute([$userId, $orderId]);
                                $isFirstClientOrder = ((int)$ordersCountStmt->fetchColumn() === 0);
                            }

                            $isSelfPlacedOrder = empty($order['created_by_user_id']);
                            $isManagerReferrer = ($refRole === 'manager');
                            if ($isManagerReferrer) {
                                $refBonus = $isSelfPlacedOrder ? Order::calculateManagerReferralBonus($sum) : 0;
                            } else {
                                $refBonus = Order::calculateReferralBonus($sum, $isPartnerReferrer, $isFirstClientOrder);
                            }
                            if ($refBonus > 0) {
                                $this->pdo->prepare(
                                    "UPDATE users SET points_balance = points_balance + ? WHERE id = ?"
                                )->execute([$refBonus, $refId]);

                                $refDesc = $isManagerReferrer
                                    ? "Бонус менеджера за самостоятельный заказ по ссылке №{$orderId}"
                                    : "Бонус за заказ №{$orderId}";
                                $this->pdo->prepare(
                                    "INSERT INTO points_transactions (user_id, order_id, amount, transaction_type, description, created_at) VALUES (?, ?, ?, 'accrual', ?, NOW())"
                                )->execute([$refId, $orderId, $refBonus, $refDesc]);
                            }
                        }

                        // Управляющий менеджер получает 3% от каждой продажи независимо от реферального источника.
                        $managerBonus = 0;
                        $projectManagerId = $this->findProjectManagerId();
                        if ($projectManagerId > 0) {
                            $managerBonus = Order::calculateProjectManagerBonus($sum);
                            if ($managerBonus > 0) {
                                $this->pdo->prepare(
                                    "UPDATE users SET points_balance = points_balance + ? WHERE id = ?"
                                )->execute([$managerBonus, $projectManagerId]);

                                $mgrDesc = "Базовые 3% менеджера за заказ №{$orderId}";
                                $this->pdo->prepare(
                                    "INSERT INTO points_transactions (user_id, order_id, amount, transaction_type, description, created_at) VALUES (?, ?, ?, 'accrual', ?, NOW())"
                                )->execute([$projectManagerId, $orderId, $managerBonus, $mgrDesc]);
                            }
                        }

                        $this->pdo->prepare(
                            "UPDATE orders SET points_accrued = ?, manager_points_accrued = ? WHERE id = ?"
                        )->execute([$refBonus, $managerBonus, $orderId]);
                    }

                    // Начисляем селлерам выплаты по заказу
                    $payoutsStmt = $this->pdo->prepare("SELECT seller_id, payout_amount FROM seller_payouts WHERE order_id = ?");
                    $payoutsStmt->execute([$orderId]);
                    $payoutRows = $payoutsStmt->fetchAll(PDO::FETCH_ASSOC);
                    if ($payoutRows) {
                        $uStmt = $this->pdo->prepare("UPDATE users SET rub_balance = rub_balance + ? WHERE id = ?");
                        foreach ($payoutRows as $pr) {
                            $uStmt->execute([(float)$pr['payout_amount'], (int)$pr['seller_id']]);
                        }
                        $this->pdo->prepare("UPDATE seller_payouts SET status = 'accrued' WHERE order_id = ?")
                             ->execute([$orderId]);
                    }
                }

                $stmt = $this->pdo->prepare(
                    "UPDATE orders SET status = ? WHERE id = ?"
                );
                $stmt->execute([$status, $orderId]);

                (new OrderStatusHistoryService($this->pdo))->record(
                    $orderId,
                    (string)$order['status'],
                    $status,
                    isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null,
                    isset($_SESSION['role']) ? (string)$_SESSION['role'] : null
                );

                if ($status === 'cancelled') {
                    if ($order['status'] !== 'cancelled') {
                        $orderStock->rollbackReservationByOrderId($orderId);
                    }

                    $cnt = $this->pdo->prepare(
                        "SELECT COUNT(*) FROM orders WHERE user_id = ? AND id <> ? AND status NOT IN ('cancelled','returned')"
                    );
                    $cnt->execute([(int)$order['user_id'], $orderId]);
                    if ((int)$cnt->fetchColumn() === 0) {
                        $this->pdo->prepare("UPDATE users SET has_used_referral_coupon = 0 WHERE id = ?")
                                 ->execute([(int)$order['user_id']]);
                    }

                    if ($order['status'] !== 'cancelled') {
                        $pointsBack = (int)$order['points_used'];
                        if ($pointsBack > 0) {
                            $this->pdo->prepare(
                                "UPDATE users SET points_balance = points_balance + ? WHERE id = ?"
                            )->execute([$pointsBack, (int)$order['user_id']]);

                            $desc = "Возврат {$pointsBack} за отмену заказа №{$orderId}";
                            $this->pdo->prepare(
                                "INSERT INTO points_transactions (user_id, order_id, amount, transaction_type, description, created_at) VALUES (?, ?, ?, 'accrual', ?, NOW())"
                            )->execute([(int)$order['user_id'], $orderId, $pointsBack, $desc]);
                        }
                    }
                }
            }
        }
        header('Location: ' . $this->basePath() . '/' . $orderId);
        exit;
    }

    // Форма подтверждения заказа (клиент)
    public function checkoutForm(): void
    {
        $user = Auth::user();
        if (!$user) {
            header('Location: /login');
            exit;
        }

        $stmt = $this->pdo->prepare(
            "SELECT ci.product_id, t.name AS product, p.variety, ci.quantity, ci.unit_price, DATE(pb.purchased_at) AS delivery_date\n" .
            "FROM cart_items ci\n" .
            "JOIN products p ON p.id = ci.product_id\n" .
            "JOIN product_types t ON t.id = p.product_type_id\n" .
            "LEFT JOIN purchase_batches pb ON pb.id = ci.purchase_batch_id\n" .
            "WHERE ci.user_id = ?"
        );
        $stmt->execute([$user['id']]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $groups = [];
        $subtotal = 0.0;
        foreach ($items as $it) {
            $key = $it['delivery_date'] ?: 'on_demand';
            $groups[$key][] = $it;
            $subtotal += $it['quantity'] * $it['unit_price'];
        }

        $currentPoints = $user['points_balance'] ?? 0;
        $maxPointsUse  = (int)$subtotal;

        include __DIR__ . '/../../src/Views/client/checkout.php';
    }

    // Обработка и сохранение заказа (POST /checkout)
    public function store(): void
    {
        $user = Auth::user();
        if (!$user) {
            header('Location: /login');
            exit;
        }

        $stmt = $this->pdo->prepare(
            "SELECT ci.product_id, ci.purchase_batch_id, ci.stock_mode, ci.quantity, ci.unit_price, p.box_size\n" .
            "FROM cart_items ci\n" .
            "JOIN products p ON p.id = ci.product_id\n" .
            "WHERE ci.user_id = ?"
        );
        $stmt->execute([$user['id']]);
        $cartItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (empty($cartItems)) {
            header('Location: /cart?error=' . urlencode('Корзина пуста'));
            exit;
        }

        $totalAmount = 0;
        foreach ($cartItems as &$ci) {
            $ci['kg_qty'] = $ci['quantity'] * (float)$ci['box_size'];
            $ci['kg_price'] = ((float)$ci['box_size'] > 0)
                ? $ci['unit_price'] / (float)$ci['box_size']
                : $ci['unit_price'];
            $totalAmount += $ci['kg_qty'] * $ci['kg_price'];
        }
        unset($ci);

        try {
            $this->pdo->beginTransaction();
            $stockService = new StockService($this->pdo);
            $orderStock = new OrderStockOrchestrator($this->pdo, $stockService);

            // Логика скидок и баллов
            $discount = 0;
            if ($user['referred_by'] !== null && $user['has_used_referral_coupon'] == 0) {
                $discount = (int)floor($totalAmount * 0.10);
                $this->pdo->prepare("UPDATE users SET has_used_referral_coupon = 1 WHERE id = ?")->execute([$user['id']]);
            }

            $pointsUsed = 0;
            $maxPointsUse = (int)($totalAmount - $discount);
            if (!empty($_POST['use_points']) && $user['points_balance'] > 0) {
                $requested = intval($_POST['points_to_use'] ?? 0);
                $pointsUsed = min($requested, $maxPointsUse, $user['points_balance']);
                if ($pointsUsed > 0) {
                    $this->pdo->prepare("UPDATE users SET points_balance = points_balance - ? WHERE id = ?")->execute([$pointsUsed, $user['id']]);
                    $this->pdo->prepare(
                        "INSERT INTO points_transactions (user_id, order_id, amount, transaction_type, description, created_at)
                         VALUES (?, NULL, ?, 'usage', ?, NOW())"
                    )->execute([$user['id'], -$pointsUsed, "Списание {$pointsUsed} за заказ"]);
                }
            }

            // Сохраняем заказ
            $stmtOrder = $this->pdo->prepare(
                "INSERT INTO orders
                (user_id, address_id, slot_id, status, total_amount, discount_applied, points_used, points_accrued, delivery_date, created_at)
                 VALUES (?, ?, ?, 'new', ?, ?, ?, 0, ?, NOW())"
            );
            $stmtOrder->execute([
                $user['id'],
                $_POST['address_id'],
                $_POST['slot_id'] ?? null,
                $totalAmount,
                $discount,
                $pointsUsed,
                PLACEHOLDER_DATE
            ]);
            $orderId = (int)$this->pdo->lastInsertId();

            $stmtItem = $this->pdo->prepare(
                "INSERT INTO order_items (order_id, product_id, quantity, boxes, unit_price, stock_mode, purchase_batch_id) VALUES (?, ?, ?, ?, ?, ?, ?)"
            );
            foreach ($cartItems as $ci) {
                $mode = (string)($ci['stock_mode'] ?? 'instant');
                $batchId = isset($ci['purchase_batch_id']) ? (int)$ci['purchase_batch_id'] : 0;
                if (in_array($mode, ['instant', 'discount_stock'], true) && $batchId <= 0) {
                    throw new \RuntimeException('Для позиции корзины не определена партия отгрузки.');
                }

                $orderStock->persistOrderItemOnly(
                    $stmtItem,
                    $orderId,
                    (int)$ci['product_id'],
                    [
                        'quantity' => (float)$ci['quantity'],
                        'box_size' => (float)$ci['box_size'],
                        'unit_price' => (float)$ci['unit_price'],
                        'purchase_batch_id' => $batchId > 0 ? $batchId : null,
                    ],
                    $mode
                );
            }

            // Начисление бонусов по заказу
            $personalBonus = (int)floor(($totalAmount - $discount - $pointsUsed) * 0.05);
            if ($personalBonus > 0) {
                $this->pdo->prepare("UPDATE orders SET points_accrued = ? WHERE id = ?")->execute([$personalBonus, $orderId]);
                $this->pdo->prepare("UPDATE users SET points_balance = points_balance + ? WHERE id = ?")->execute([$personalBonus, $user['id']]);
                $this->pdo->prepare(
                    "INSERT INTO points_transactions (user_id, order_id, amount, transaction_type, description, created_at)
                     VALUES (?, ?, ?, 'accrual', ?, NOW())"
                )->execute([$user['id'], $orderId, $personalBonus, "Начислено {$personalBonus} за заказ"]);
            }

            $this->pdo->prepare("DELETE FROM cart_items WHERE user_id = ?")->execute([$user['id']]);
            $this->pdo->commit();

            // Уведомляем админов в Telegram
            $this->notifyAdmins($orderId);

            header('Location: /orders/thankyou');
            exit;

        } catch (\Exception $e) {
            $this->pdo->rollBack();
            header('Location: /checkout?error=' . urlencode('Ошибка при оформлении заказа'));
            exit;
        }
    }

    // Уведомление администраторам
    public function notifyAdmins(int $orderId): void
    {
        $cfg    = require __DIR__ . '/../../config/telegram.php';
        $token  = $cfg['bot_token'];
        $chatId = $cfg['admin_chat_id'];

        // Получаем основные данные заказа и пользователя
        $stmt = $this->pdo->prepare(
            "SELECT o.created_at, o.total_amount, o.delivery_date,
" .
            "       d.time_from AS slot_from, d.time_to AS slot_to, u.name, u.phone
" .
            "FROM orders o
" .
            "JOIN users u ON u.id = o.user_id
" .
            "LEFT JOIN delivery_slots d ON d.id = o.slot_id
" .
            "WHERE o.id = ?"
        );
        $stmt->execute([$orderId]);
        $order = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$order) {
            return;
        }

        // Получаем первую позицию заказа (если их несколько, берём первую)
        $stmtItems = $this->pdo->prepare(
            "SELECT t.name AS product, p.variety, p.unit, oi.quantity, oi.boxes, oi.unit_price
             FROM order_items oi
             JOIN products p ON p.id = oi.product_id
             JOIN product_types t ON t.id = p.product_type_id
             WHERE oi.order_id = ?
             LIMIT 1"
        );
        $stmtItems->execute([$orderId]);
        $item = $stmtItems->fetch(\PDO::FETCH_ASSOC);

        $deliveryDate = $order['delivery_date'] ?? null;
        $deliverySlot = format_time_range($order['slot_from'] ?? null, $order['slot_to'] ?? null);
        $placeholder = defined('PLACEHOLDER_DATE') ? PLACEHOLDER_DATE : '2025-05-15';
        if ($deliveryDate && $deliveryDate !== $placeholder) {
            $deliveryText = date('d.m.Y', strtotime($deliveryDate));
        } else {
            $deliveryText = 'Ближайшая возможная дата';
        }
        if ($deliverySlot !== '') {
            $deliveryText .= ' ' . $deliverySlot;
        }

        $line1 = $order['phone'] . ', ' . $order['name'];

        if ($item) {
            $productInfo = trim($item['product'] . ' ' . $item['variety']);
            if ($item['unit']) {
                $productInfo .= ' ' . $item['unit'];
            }
            $line2 = sprintf(
                '%s, %s, %s, %.0f',
                $deliveryText,
                $productInfo,
                $item['quantity'],
                $order['total_amount']
            );
        } else {
            $line2 = sprintf('%s, сумма %.0f', $deliveryText, $order['total_amount']);
        }

        $line3 = 'https://berrygo.ru/admin/orders/' . $orderId;

        $text = $line1 . "\n" . $line2 . "\n" . $line3;

        $payloadData = [
            'chat_id'    => $chatId,
            'text'       => $text,
            'parse_mode' => 'Markdown',
        ];
        if (!empty($cfg['admin_topic_id'])) {
            $payloadData['message_thread_id'] = (int)$cfg['admin_topic_id'];
        }

        $relayUrl = trim((string)($cfg['relay_url'] ?? ''));
        $relaySecret = (string)($cfg['relay_secret'] ?? '');
        if ($relayUrl !== '') {
            $url = $relayUrl;
            $payloadData = [
                'bot_token' => $token,
                'method' => 'sendMessage',
                'params' => $payloadData,
                'secret' => $relaySecret,
            ];
        } else {
            $url = "https://api.telegram.org/bot{$token}/sendMessage";
        }

        $payload = json_encode($payloadData, JSON_UNESCAPED_UNICODE);

        // Инициализируем cURL
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json; charset=UTF-8'
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);

        $response = curl_exec($ch);
        $errno    = curl_errno($ch);
        $error    = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // Логируем результат (в файл или через ваш логгер)
        $logEntry = date('Y-m-d H:i:s')
            . " | notifyAdmins | order={$orderId} | http_code={$httpCode}";
        $safeResponse = $response === false ? 'false' : SensitiveData::sanitizeText((string)$response, [$token]);
        $safeError = SensitiveData::sanitizeText((string)$error, [$token]);
        if ($errno) {
            $logEntry .= " | curl_error={$safeError}";
        }
        $logEntry .= " | response=" . $safeResponse . "\n";
        file_put_contents(__DIR__ . '/../../log/telegram_notify.log', $logEntry, FILE_APPEND);
        // если используете PSR-3 логгер:
        // $this->logger?->error('notifyAdmins', ['orderId'=>$orderId,'http'=>$httpCode,'curlErr'=>$error,'resp'=>$response]);
    }

    // Удаление заказа (POST, админ)
    public function delete(): void
    {
        $orderId = (int)($_POST['order_id'] ?? 0);
        if ($orderId) {
            $stmt = $this->pdo->prepare("SELECT user_id, status, points_used FROM orders WHERE id = ?");
            $stmt->execute([$orderId]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);
            $userId = (int)($order['user_id'] ?? 0);

            if (($order['status'] ?? '') !== 'cancelled') {
                $stockService = new StockService($this->pdo);
                $orderStock = new OrderStockOrchestrator($this->pdo, $stockService);
                $orderStock->rollbackReservationByOrderId($orderId);
            }

            $this->pdo->prepare("DELETE FROM order_items WHERE order_id = ?")->execute([$orderId]);
            $this->pdo->prepare("DELETE FROM points_transactions WHERE order_id = ?")->execute([$orderId]);


            if ($userId && ($order['status'] ?? '') !== 'cancelled') {
                $pointsBack = (int)($order['points_used'] ?? 0);
                if ($pointsBack > 0) {
                    $this->pdo->prepare(
                        "UPDATE users SET points_balance = points_balance + ? WHERE id = ?"
                    )->execute([$pointsBack, $userId]);

                    $desc = "Возврат {$pointsBack} за удаление заказа №{$orderId}";
                    $this->pdo->prepare(
                        "INSERT INTO points_transactions (user_id, order_id, amount, transaction_type, description, created_at) VALUES (?, ?, ?, 'accrual', ?, NOW())"
                    )->execute([$userId, $orderId, $pointsBack, $desc]);
                }
            }

            $this->pdo->prepare("DELETE FROM orders WHERE id = ?")->execute([$orderId]);

            if ($userId) {
                $cnt = $this->pdo->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ? AND status NOT IN ('cancelled','returned')");
                $cnt->execute([$userId]);
                if ((int)$cnt->fetchColumn() === 0) {
                    $this->pdo->prepare("UPDATE users SET has_used_referral_coupon = 0 WHERE id = ?")
                             ->execute([$userId]);
                }
            }
        }
        header('Location: ' . $this->basePath());
        exit;
    }

    // Обновление количества товара в заказе (POST, админ)
    public function updateItem(): void
    {
        $orderId   = (int)($_POST['order_id'] ?? 0);
        $productId = (int)($_POST['product_id'] ?? 0);
        $qty       = (float)($_POST['quantity'] ?? 0);
        $price     = (float)($_POST['unit_price'] ?? 0);
        if ($orderId && $productId && $qty > 0) {
            $pricing = $this->fetchManualItemPricing($productId);
            $boxSize = $pricing['box_size'];
            if ($price <= 0) {
                $price = $pricing['unit_price'];
            }
            $boxes = $boxSize > 0 ? $qty / $boxSize : $qty;
            $this->pdo->prepare(
                "UPDATE order_items SET quantity = ?, boxes = ?, unit_price = ? WHERE order_id = ? AND product_id = ?"
            )->execute([$qty, $boxes, $price, $orderId, $productId]);

            $this->recalculateTotals($orderId);
        }
        header('Location: ' . $this->basePath() . '/' . $orderId);
        exit;
    }

    public function addItem(): void
    {
        $orderId   = (int)($_POST['order_id'] ?? 0);
        $productId = (int)($_POST['product_id'] ?? 0);
        $qty       = (float)($_POST['quantity'] ?? 0);
        $price     = (float)($_POST['unit_price'] ?? 0);
        if ($orderId && $productId && $qty > 0) {
            $pricing = $this->fetchManualItemPricing($productId);
            $boxSize = $pricing['box_size'];
            if ($price <= 0) {
                $price = $pricing['unit_price'];
            }
            $boxes = $boxSize > 0 ? $qty / $boxSize : $qty;

            $check = $this->pdo->prepare("SELECT 1 FROM order_items WHERE order_id = ? AND product_id = ?");
            $check->execute([$orderId, $productId]);
            if ($check->fetch()) {
                $this->pdo->prepare(
                    "UPDATE order_items SET quantity = ?, boxes = ?, unit_price = ? WHERE order_id = ? AND product_id = ?"
                )->execute([$qty, $boxes, $price, $orderId, $productId]);
            } else {
                $this->pdo->prepare(
                    "INSERT INTO order_items (order_id, product_id, quantity, boxes, unit_price) VALUES (?, ?, ?, ?, ?)"
                )->execute([$orderId, $productId, $qty, $boxes, $price]);
            }

            $this->recalculateTotals($orderId);
        }
        header('Location: ' . $this->basePath() . '/' . $orderId);
        exit;
    }

    public function deleteItem(): void
    {
        $orderId   = (int)($_POST['order_id'] ?? 0);
        $productId = (int)($_POST['product_id'] ?? 0);
        if ($orderId && $productId) {
            $this->pdo->prepare(
                "DELETE FROM order_items WHERE order_id = ? AND product_id = ?"
            )->execute([$orderId, $productId]);

            $this->recalculateTotals($orderId);
        }
        header('Location: ' . $this->basePath() . '/' . $orderId);
        exit;
    }

    // Обновление скидки первого заказа
    public function updateReferral(): void
    {
        $orderId = (int)($_POST['order_id'] ?? 0);
        $userId  = (int)($_POST['user_id'] ?? 0);
        $value   = isset($_POST['has_used_referral_coupon']) && $_POST['has_used_referral_coupon'] === '1' ? 1 : 0;
        if ($orderId && $userId) {
            $this->pdo->prepare(
                "UPDATE users SET has_used_referral_coupon = ? WHERE id = ?"
            )->execute([$value, $userId]);
            $this->recalculateTotals($orderId);
        }
        header('Location: ' . $this->basePath() . '/' . $orderId);
        exit;
    }

    public function updateComment(): void
    {
        $orderId = (int)($_POST['order_id'] ?? 0);
        $comment = trim($_POST['comment'] ?? '');
        if ($orderId) {
            $this->pdo->prepare("UPDATE orders SET comment = ? WHERE id = ?")
                      ->execute([$comment, $orderId]);
        }
        header('Location: ' . $this->basePath() . '/' . $orderId);
        exit;
    }

    private function recalculateTotals(int $orderId): void
    {
        (new OrderTotalsService($this->pdo))->recalculate($orderId);
    }
}
