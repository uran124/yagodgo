<?php
namespace App\Services;

use App\Models\OrdersRepository;
use PDO;

class AdminOrdersPageService
{
    private PDO $pdo;
    private OrdersRepository $ordersRepository;

    public function __construct(PDO $pdo, ?OrdersRepository $ordersRepository = null)
    {
        $this->pdo = $pdo;
        $this->ordersRepository = $ordersRepository ?? new OrdersRepository($pdo);
    }

    /**
     * @return array<string, mixed>
     */
    public function buildIndexData(int $managerId, int $page, int $perPage): array
    {
        $page = max(1, $page);
        $offset = ($page - 1) * $perPage;

        $totalOrders = $this->ordersRepository->countOrdersForIndex($managerId);
        $totalPages = max(1, (int)ceil($totalOrders / $perPage));
        if ($page > $totalPages) {
            $page = $totalPages;
            $offset = ($page - 1) * $perPage;
        }

        $orders = $this->ordersRepository->hydrateOrders(
            $this->ordersRepository->fetchOrdersForIndex($managerId, $perPage, $offset)
        );

        $managersStmt = $this->pdo->query("SELECT id, name FROM users WHERE role = 'manager' ORDER BY name");

        return [
            'orders' => $orders,
            'managers' => $managersStmt->fetchAll(PDO::FETCH_ASSOC),
            'selectedManager' => $managerId,
            'page' => $page,
            'totalPages' => $totalPages,
            'totalOrders' => $totalOrders,
            'perPage' => $perPage,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findShowData(int $id): ?array
    {
        $order = $this->fetchOrder($id);
        if (!$order) {
            return null;
        }

        $coupon = $this->fetchCouponInfo($order['coupon_code'] ?? null);
        $pointsFromBalance = (int)($order['points_used'] ?? 0);
        if ($coupon && $coupon['type'] === 'points') {
            $pointsFromBalance = max(0, $pointsFromBalance - (int)$coupon['points']);
        }

        return [
            'order' => $order,
            'items' => $this->fetchOrderItems($id),
            'transactions' => $this->fetchTransactions($id),
            'coupon' => $coupon,
            'pointsFromBalance' => $pointsFromBalance,
            'addresses' => $this->fetchAddresses((int)$order['user_id']),
            'slots' => $this->fetchSlots(),
            'products' => $this->fetchActiveProducts(),
            'productionJobs' => $this->fetchProductionJobs($id),
            'productionExecutors' => $this->fetchProductionExecutors(),
        ];
    }


    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchProductionJobs(int $orderId): array
    {
        if (!$this->tableExists('production_jobs')) {
            return [];
        }

        $stmt = $this->pdo->prepare(
            "SELECT *
" .
            "FROM production_jobs
" .
            "WHERE order_id = ?
" .
            "ORDER BY created_at DESC, id DESC"
        );
        $stmt->execute([$orderId]);
        $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        if (!$jobs || !$this->tableExists('production_job_events')) {
            foreach ($jobs as &$job) {
                $job['events'] = [];
            }
            unset($job);
            return $jobs;
        }

        $jobIds = array_values(array_filter(array_map(static fn (array $job): int => (int)($job['id'] ?? 0), $jobs)));
        if (!$jobIds) {
            return $jobs;
        }

        $placeholders = implode(',', array_fill(0, count($jobIds), '?'));
        $eventsStmt = $this->pdo->prepare(
            "SELECT *
" .
            "FROM production_job_events
" .
            "WHERE job_id IN ({$placeholders})
" .
            "ORDER BY created_at DESC, id DESC"
        );
        $eventsStmt->execute($jobIds);

        $eventsByJob = [];
        foreach ($eventsStmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $event) {
            $eventsByJob[(int)$event['job_id']][] = $event;
        }

        foreach ($jobs as &$job) {
            $job['events'] = $eventsByJob[(int)$job['id']] ?? [];
        }
        unset($job);

        return $jobs;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchProductionExecutors(): array
    {
        if ($this->tableExists('production_executor_settings')) {
            $stmt = $this->pdo->query(
                "SELECT u.id, u.name, u.role, pes.executor_type, pes.current_mode,
" .
                "       pes.default_fulfillment_model, pes.default_bonus_percent, pes.default_bonus_amount,
" .
                "       pes.max_active_jobs,
" .
                "       COALESCE(active_jobs.active_count, 0) AS active_jobs_count
" .
                "FROM production_executor_settings pes
" .
                "JOIN users u ON u.id = pes.user_id
" .
                "LEFT JOIN (
" .
                "    SELECT executor_id, COUNT(*) AS active_count
" .
                "    FROM production_jobs
" .
                "    WHERE executor_type = 'internal_staff'
" .
                "      AND status IN ('assigned','materials_pending','materials_sent','materials_received','in_progress','photo_uploaded','approved','ready_for_handover')
" .
                "    GROUP BY executor_id
" .
                ") active_jobs ON active_jobs.executor_id = pes.user_id
" .
                "WHERE pes.executor_type = 'internal_staff'
" .
                "  AND pes.is_active = 1
" .
                "  AND pes.current_mode IN ('on_shift', 'remote_available')
" .
                "  AND COALESCE(active_jobs.active_count, 0) < pes.max_active_jobs
" .
                "ORDER BY CASE pes.current_mode WHEN 'on_shift' THEN 1 WHEN 'remote_available' THEN 2 ELSE 9 END, u.name, u.id"
            );

            return $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
        }

        $stmt = $this->pdo->query(
            "SELECT id, name, role, 'internal_staff' AS executor_type, 'on_shift' AS current_mode,
" .
            "       'by_berrygo_on_site' AS default_fulfillment_model, 10.00 AS default_bonus_percent, 0.00 AS default_bonus_amount,
" .
            "       1 AS max_active_jobs, 0 AS active_jobs_count
" .
            "FROM users
" .
            "WHERE role IN ('admin', 'manager', 'partner')
" .
            "ORDER BY name, id"
        );

        return $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
    }

    private function tableExists(string $table): bool
    {
        $driver = (string)$this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $stmt = $this->pdo->prepare("SELECT name FROM sqlite_master WHERE type = 'table' AND name = ?");
            $stmt->execute([$table]);
            return (bool)$stmt->fetchColumn();
        }

        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
        $stmt->execute([$table]);
        return (int)$stmt->fetchColumn() > 0;
    }

    /**
     * @return array<string, mixed>
     */
    public function buildCreateData(): array
    {
        return [
            'products' => $this->fetchCreateFormProducts(),
            'purchaseBatches' => $this->fetchCreateFormPurchaseBatches(),
            'slots' => $this->fetchSlots(),
            'today' => date('Y-m-d'),
        ];
    }

    /**
     * @return array<string, mixed>|false
     */
    private function fetchOrder(int $id)
    {
        $stmt = $this->pdo->prepare(
            "SELECT o.*, d.time_from AS slot_from, d.time_to AS slot_to,\n" .
            "       u.name AS client_name, u.phone, u.has_used_referral_coupon, a.street AS address\n" .
            "FROM orders o\n" .
            "JOIN users u ON u.id = o.user_id\n" .
            "JOIN addresses a ON a.id = o.address_id\n" .
            "LEFT JOIN delivery_slots d ON d.id = o.slot_id\n" .
            "WHERE o.id = ?"
        );
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchOrderItems(int $orderId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT oi.product_id, oi.quantity, oi.boxes, oi.unit_price, t.name AS product_name, p.unit, p.variety, p.box_size, p.box_unit\n" .
            "FROM order_items oi\n" .
            "JOIN products p ON p.id = oi.product_id\n" .
            "JOIN product_types t ON t.id = p.product_type_id\n" .
            "WHERE oi.order_id = ?"
        );
        $stmt->execute([$orderId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchTransactions(int $orderId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT pt.*\n" .
            "FROM points_transactions pt\n" .
            "WHERE pt.order_id = ?\n" .
            "ORDER BY pt.created_at DESC"
        );
        $stmt->execute([$orderId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchAddresses(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT id, street, last_checkout_comment, delivery_distance_km, delivery_distance_m, delivery_distance_provider FROM addresses WHERE user_id = ? ORDER BY is_primary DESC, created_at ASC"
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchSlots(): array
    {
        $stmt = $this->pdo->query("SELECT id, time_from, time_to FROM delivery_slots ORDER BY time_from");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchCouponInfo(?string $couponCode): ?array
    {
        if ($couponCode === null || $couponCode === '') {
            return null;
        }

        $couponStmt = $this->pdo->prepare(
            "SELECT code, type, discount, points FROM coupons WHERE code = ?"
        );
        $couponStmt->execute([$couponCode]);
        $coupon = $couponStmt->fetch(PDO::FETCH_ASSOC) ?: null;
        if ($coupon) {
            return $coupon;
        }

        $refStmt = $this->pdo->prepare("SELECT id FROM users WHERE referral_code = ?");
        $refStmt->execute([$couponCode]);
        if (!$refStmt->fetch()) {
            return null;
        }

        return [
            'code' => $couponCode,
            'type' => 'discount',
            'discount' => 10,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchActiveProducts(): array
    {
        $stmt = $this->pdo->query(
            "SELECT p.id, t.name AS product, p.variety, p.price, p.box_size,\n" .
            "       COALESCE((\n" .
            "           SELECT CASE WHEN pb.status = 'planned' THEN COALESCE(NULLIF(pb.preorder_price_per_box, 0), NULLIF(p.preorder_price_per_box, 0), p.price, 0) ELSE pb.instant_price_per_box END\n" .
            "           FROM purchase_batches pb\n" .
            "           WHERE pb.product_id = p.id\n" .
            "             AND (\n" .
            "               (pb.status IN ('purchased', 'arrived') AND pb.boxes_free > 0 AND pb.instant_price_per_box > 0)\n" .
            "               OR pb.status = 'planned'\n" .
            "             )\n" .
            "           ORDER BY CASE WHEN pb.status IN ('purchased', 'arrived') THEN 1 ELSE 2 END, pb.purchased_at ASC, pb.id ASC\n" .
            "           LIMIT 1\n" .
            "       ), p.price) AS price_per_box\n" .
            "FROM products p\n" .
            "JOIN product_types t ON t.id = p.product_type_id\n" .
            "WHERE p.is_active = 1\n" .
            "ORDER BY t.name"
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }


    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchCreateFormPurchaseBatches(): array
    {
        $plannedAvailableExpr = "(COALESCE(NULLIF(pb.boxes_total, 0), pb.boxes_free + pb.boxes_reserved) - pb.boxes_reserved)";
        $availableExpr = "CASE WHEN pb.status = 'planned' THEN {$plannedAvailableExpr} ELSE pb.boxes_free END";

        $stmt = $this->pdo->query(
            "SELECT pb.id AS purchase_batch_id, pb.product_id, pb.status, pb.purchased_at,\n" .
            "       pb.box_size_snapshot, pb.box_unit_snapshot, pb.boxes_free, pb.boxes_total, pb.boxes_reserved,\n" .
            "       {$availableExpr} AS available_boxes,\n" .
            "       pb.instant_price_per_box, pb.preorder_price_per_box,\n" .
            "       p.price AS product_price_per_box, p.preorder_price_per_box AS product_preorder_price_per_box,\n" .
            "       t.name AS product, p.variety, p.image_path, p.box_size, p.box_unit\n" .
            "FROM purchase_batches pb\n" .
            "JOIN products p ON p.id = pb.product_id\n" .
            "JOIN product_types t ON t.id = p.product_type_id\n" .
            "WHERE p.is_active = 1\n" .
            "  AND (\n" .
            "    (pb.status IN ('purchased', 'arrived') AND pb.boxes_free > 0 AND pb.instant_price_per_box > 0)\n" .
            "    OR pb.status = 'planned'\n" .
            "  )\n" .
            "ORDER BY pb.purchased_at ASC, pb.status ASC, t.name ASC, p.variety ASC"
        );

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            $isPreorder = ($row['status'] ?? '') === 'planned';
            $row['stock_mode'] = $isPreorder ? 'preorder' : 'instant';
            $row['mode_group'] = $isPreorder ? 'preorder' : 'in_stock';
            $row['mode_label'] = $isPreorder ? 'Предзаказ' : 'В наличии';
            $row['available_boxes'] = (float)($row['available_boxes'] ?? 0);
            $row['display_box_size'] = (float)($row['box_size_snapshot'] ?: ($row['box_size'] ?: 1));
            $fallbackPreorderPrice = (float)(($row['product_preorder_price_per_box'] ?? 0) ?: ($row['product_price_per_box'] ?? 0));
            $row['price_per_box'] = (float)($isPreorder ? (((float)$row['preorder_price_per_box'] > 0) ? $row['preorder_price_per_box'] : $fallbackPreorderPrice) : $row['instant_price_per_box']);
            $row['batch_date'] = substr((string)($row['purchased_at'] ?? ''), 0, 10);
            $row['display_box_unit'] = (string)($row['box_unit_snapshot'] ?: ($row['box_unit'] ?? ''));
        }
        unset($row);

        return $rows;
    }


    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchCreateFormProducts(): array
    {
        $stmt = $this->pdo->query(
            "SELECT p.id, t.name AS product, p.variety, p.price, p.image_path, p.box_size, p.box_unit\n" .
            "FROM products p\n" .
            "JOIN product_types t ON t.id = p.product_type_id\n" .
            "WHERE p.is_active = 1\n" .
            "ORDER BY t.name"
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
