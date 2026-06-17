<?php
namespace App\Controllers;

use PDO;
use App\Services\PartnerProfileService;
use App\Services\SellerEconomicsService;

class SellersController
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    private function basePath(): string
    {
        return '/admin/sellers';
    }

    /**
     * List all sellers
     */
    public function index(): void
    {
        $stmt = $this->pdo->query("SELECT id, company_name, name, phone, rub_balance FROM users WHERE role = 'seller' ORDER BY company_name");
        $sellers = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        viewAdmin('sellers/index', [
            'pageTitle' => 'Селлеры',
            'sellers'   => $sellers,
        ]);
    }

    /**
     * Show seller profile with orders
     */
    public function show(int $id): void
    {
        $stmt = $this->pdo->prepare("SELECT id, company_name, pickup_address, name, phone, rub_balance FROM users WHERE id = ? AND role = 'seller'");
        $stmt->execute([$id]);
        $seller = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$seller) {
            http_response_code(404);
            echo 'Селлер не найден';
            return;
        }

        // Orders for seller (similar to SellerController::orders)
        $oStmt = $this->pdo->prepare(
            "SELECT o.id, o.status, o.points_used, o.delivery_date,\n" .
            "       d.time_from AS slot_from, d.time_to AS slot_to,\n" .
            "       u.name AS client_name, u.phone, a.street AS address,\n" .
            "       SUM(oi.quantity * oi.unit_price) AS seller_subtotal,\n" .
            "       (SELECT SUM(quantity * unit_price) FROM order_items WHERE order_id = o.id) AS order_total\n" .
            "FROM orders o\n" .
            "JOIN order_items oi ON oi.order_id = o.id\n" .
            "JOIN products p ON p.id = oi.product_id\n" .
            "JOIN users u ON u.id = o.user_id\n" .
            "LEFT JOIN addresses a ON a.id = o.address_id\n" .
            "LEFT JOIN delivery_slots d ON d.id = o.slot_id\n" .
            "WHERE p.seller_id = ?\n" .
            "GROUP BY o.id, o.status, o.points_used, o.delivery_date, d.time_from, d.time_to, u.name, u.phone, a.street\n" .
            "ORDER BY o.delivery_date DESC, d.time_from DESC"
        );
        $oStmt->execute([$id]);
        $orders = $oStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $orderIds = array_column($orders, 'id');
        $itemsByOrder = [];
        if ($orderIds) {
            $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
            $iStmt = $this->pdo->prepare(
                "SELECT oi.order_id, oi.quantity, oi.boxes, oi.unit_price,\n" .
                "       t.name AS product_name, p.variety, p.box_size, p.box_unit\n" .
                "FROM order_items oi\n" .
                "JOIN products p ON p.id = oi.product_id\n" .
                "JOIN product_types t ON t.id = p.product_type_id\n" .
                "WHERE oi.order_id IN ($placeholders) AND p.seller_id = ?\n" .
                "ORDER BY oi.order_id"
            );
            $iStmt->execute([...$orderIds, $id]);
            while ($row = $iStmt->fetch(PDO::FETCH_ASSOC)) {
                $itemsByOrder[$row['order_id']][] = $row;
            }
        }

        $economics = new SellerEconomicsService($this->pdo);
        foreach ($orders as &$o) {
            $o['items'] = $itemsByOrder[$o['id']] ?? [];
            $orderTotal = (float)($o['order_total'] ?? 0);
            $sellerSubtotal = (float)($o['seller_subtotal'] ?? 0);
            $pointsUsed = (float)($o['points_used'] ?? 0);
            $o = array_merge($o, $economics->calculate($id, $sellerSubtotal, $orderTotal, $pointsUsed));
            unset($o['order_total']);
        }
        unset($o);

        viewAdmin('sellers/show', [
            'pageTitle' => 'Профиль селлера',
            'seller'    => $seller,
            'orders'    => $orders,
        ]);
    }

    public function edit(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        $seller = null;
        if ($id) {
            $stmt = $this->pdo->prepare("SELECT id, company_name, pickup_address, delivery_cost, work_mode FROM users WHERE id = ? AND role = 'seller'");
            $stmt->execute([$id]);
            $seller = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($seller) {
                $seller['partner_profile'] = (new PartnerProfileService($this->pdo))->find($id);
            }
            if (!$seller) {
                header('Location: ' . $this->basePath());
                exit;
            }
        }

        viewAdmin('sellers/edit', [
            'pageTitle' => 'Редактировать селлера',
            'seller'    => $seller,
        ]);
    }

    public function save(): void
    {
        $id = (int)($_POST['id'] ?? 0);
        $company = trim($_POST['company_name'] ?? '');
        $pickup  = trim($_POST['pickup_address'] ?? '');
        $cost    = (float)($_POST['delivery_cost'] ?? 0);
        $mode    = $_POST['work_mode'] ?? 'berrygo_store';
        $monetizationModel = (string)($_POST['monetization_model'] ?? 'commission');
        $clientVisibility = (string)($_POST['client_visibility'] ?? 'seller_visible');
        $commissionRate = (float)($_POST['commission_rate'] ?? 30);
        $subscriptionFee = (float)($_POST['subscription_fee'] ?? 0);
        $fixedFeePerOrder = (float)($_POST['fixed_fee_per_order'] ?? 0);

        if ($id) {
            $stmt = $this->pdo->prepare("UPDATE users SET company_name = ?, pickup_address = ?, delivery_cost = ?, work_mode = ? WHERE id = ? AND role = 'seller'");
            $stmt->execute([$company, $pickup, $cost, $mode, $id]);

            (new PartnerProfileService($this->pdo))->save([
                'user_id' => $id,
                'partner_type' => 'marketplace_seller',
                'status' => 'active',
                'default_fulfillment_model' => $mode === 'berrygo_store' ? 'by_berrygo_from_seller_stock' : 'by_seller',
                'monetization_model' => $monetizationModel,
                'client_visibility' => $clientVisibility,
                'commission_rate' => $commissionRate,
                'subscription_fee' => $subscriptionFee,
                'fixed_fee_per_order' => $fixedFeePerOrder,
            ]);
        }

        header('Location: ' . $this->basePath());
        exit;
    }
}
