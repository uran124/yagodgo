<?php
namespace App\Controllers;

use PDO;

class SettingsController
{
    private PDO $pdo;
    public function __construct(PDO $pdo) { $this->pdo = $pdo; }

    /**
     * @return array<string, string>
     */
    private function sections(): array
    {
        return [
            'general'  => 'Основные',
            'pricing'  => 'Цены',
            'preorder' => 'Предзаказ',
            'payments' => 'Оплата',
            'delivery' => 'Доставка',
            'theme'    => 'Тема',
        ];
    }

    private function normalizeSection(?string $section): string
    {
        $section = trim((string)$section);
        return array_key_exists($section, $this->sections()) ? $section : 'general';
    }

    // Форма настроек
    public function index(?string $section = null): void
    {
        $activeSection = $this->normalizeSection($section);
        // Например, читаем из таблицы settings
        $stmt = $this->pdo->query("SELECT setting_key, setting_value FROM settings");
        $all = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        viewAdmin('settings', [
          'pageTitle'           => 'Настройки — ' . $this->sections()[$activeSection],
          'settings'            => $all,
          'themeColors'         => \get_theme_palette(),
          'deliveryTariffZones' => $activeSection === 'delivery' ? $this->getDeliveryTariffZones() : [],
          'settingsSections'    => $this->sections(),
          'activeSection'       => $activeSection,
        ]);
    }

    // Сохранение
    public function save(?string $section = null): void
    {
        $activeSection = $this->normalizeSection($section);
        $allowedKeys = [
            'general' => ['company_name', 'contact_phone'],
            'pricing' => ['pricing_instant_margin_percent', 'pricing_rounding_step'],
            'preorder' => ['ui_preorder_discount_percent', 'ui_preorder_price_hint', 'ui_home_no_stock_message'],
            'payments' => [
                'robokassa_enabled',
                'robokassa_is_test',
                'robokassa_merchant_login',
                'robokassa_hash_algorithm',
                'robokassa_password1',
                'robokassa_password2',
                'robokassa_payment_url',
                'robokassa_inc_curr_label',
                'robokassa_culture',
                'robokassa_encoding',
                'robokassa_expiration_minutes',
                'robokassa_default_description',
                'robokassa_result_url',
                'robokassa_success_url',
                'robokassa_fail_url',
            ],
            'delivery' => [
                'delivery_store_address',
                'delivery_default_fee',
                'delivery_store_lat',
                'delivery_store_lng',
                'delivery_per_km_from_km',
                'delivery_per_km_price',
                'openrouteservice_api_key',
                'dadata_api_key',
                'dadata_secret_key',
                'delivery_taxi_courier_enabled',
                'delivery_taxi_courier_button_text',
                'delivery_taxi_courier_instructions',
            ],
            'theme' => ['theme_light_primary', 'theme_dark_primary'],
        ][$activeSection];
        $_POST = array_intersect_key($_POST, array_flip(array_merge($allowedKeys, ['delivery_tariff_zones'])));
        if ($activeSection === 'pricing') {
            $instantMargin = isset($_POST['pricing_instant_margin_percent']) ? (float)$_POST['pricing_instant_margin_percent'] : 50.0;
            $_POST['pricing_instant_margin_percent'] = (string)max(0.0, min(500.0, $instantMargin));

            $roundingStep = isset($_POST['pricing_rounding_step']) ? (int)$_POST['pricing_rounding_step'] : 10;
            $_POST['pricing_rounding_step'] = (string)max(1, min(10000, $roundingStep));
        }

        if ($activeSection === 'preorder') {
            $discount = isset($_POST['ui_preorder_discount_percent']) ? (float)$_POST['ui_preorder_discount_percent'] : 10.0;
            $_POST['ui_preorder_discount_percent'] = (string)max(0.0, min(99.0, $discount));

            $hint = trim((string)($_POST['ui_preorder_price_hint'] ?? ''));
            if ($hint === '') {
                $hint = 'Цена ориентировочная, точная цена будет после поступления';
            }
            $_POST['ui_preorder_price_hint'] = $hint;

            $noStockMessage = trim((string)($_POST['ui_home_no_stock_message'] ?? ''));
            if ($noStockMessage === '') {
                $noStockMessage = 'На данный момент ягод нет в наличии. Воспользуйтесь нашим предложением предварительного заказа со скидкой 10% — это дополнительная скидка за оформление предварительного бронирования.';
            }
            $_POST['ui_home_no_stock_message'] = $noStockMessage;
        }

        if ($activeSection === 'payments') {
            $_POST['robokassa_enabled'] = isset($_POST['robokassa_enabled']) ? '1' : '0';
            $_POST['robokassa_is_test'] = isset($_POST['robokassa_is_test']) ? '1' : '0';

            $robokassaHash = strtoupper(trim((string)($_POST['robokassa_hash_algorithm'] ?? 'MD5')));
            if (!in_array($robokassaHash, ['MD5', 'SHA256', 'SHA384', 'SHA512'], true)) {
                $robokassaHash = 'MD5';
            }
            $_POST['robokassa_hash_algorithm'] = $robokassaHash;

            $robokassaCulture = trim((string)($_POST['robokassa_culture'] ?? 'ru'));
            $_POST['robokassa_culture'] = in_array($robokassaCulture, ['ru', 'en'], true) ? $robokassaCulture : 'ru';

            $robokassaExpiration = isset($_POST['robokassa_expiration_minutes']) ? (int)$_POST['robokassa_expiration_minutes'] : 60;
            $_POST['robokassa_expiration_minutes'] = (string)max(0, min(10080, $robokassaExpiration));

            $robokassaEncoding = strtoupper(trim((string)($_POST['robokassa_encoding'] ?? 'UTF-8')));
            $_POST['robokassa_encoding'] = $robokassaEncoding !== '' ? $robokassaEncoding : 'UTF-8';

            $robokassaDescription = trim((string)($_POST['robokassa_default_description'] ?? ''));
            if ($robokassaDescription === '') {
                $robokassaDescription = 'Оплата заказа BerryGo';
            }
            $_POST['robokassa_default_description'] = mb_substr($robokassaDescription, 0, 100);

            $robokassaUrlDefaults = [
                'robokassa_payment_url' => 'https://auth.robokassa.ru/Merchant/Index.aspx',
                'robokassa_result_url'  => 'https://berrygo.ru/payments/robokassa/result',
                'robokassa_success_url' => 'https://berrygo.ru/payments/robokassa/success',
                'robokassa_fail_url'    => 'https://berrygo.ru/payments/robokassa/fail',
            ];
            foreach ($robokassaUrlDefaults as $key => $defaultUrl) {
                $url = trim((string)($_POST[$key] ?? ''));
                $_POST[$key] = filter_var($url, FILTER_VALIDATE_URL) ? $url : $defaultUrl;
            }

            foreach (['robokassa_password1', 'robokassa_password2'] as $passwordKey) {
                if (trim((string)($_POST[$passwordKey] ?? '')) === '') {
                    unset($_POST[$passwordKey]);
                }
            }
        }

        if ($activeSection === 'delivery') {
            $_POST['delivery_taxi_courier_enabled'] = isset($_POST['delivery_taxi_courier_enabled']) ? '1' : '0';

            $deliveryDefaults = [
                'delivery_store_address' => 'Самовывоз: 9 мая, 73',
                'delivery_default_fee' => '300',
                'delivery_per_km_from_km' => '6',
                'delivery_per_km_price' => '50',
                'delivery_taxi_courier_button_text' => 'Вызову такси-курьера',
            ];
            foreach ($deliveryDefaults as $key => $defaultValue) {
                $value = trim((string)($_POST[$key] ?? ''));
                $_POST[$key] = $value !== '' ? $value : $defaultValue;
            }

            foreach (['delivery_default_fee', 'delivery_per_km_price'] as $moneyKey) {
                $value = isset($_POST[$moneyKey]) ? (int)$_POST[$moneyKey] : 0;
                $_POST[$moneyKey] = (string)max(0, min(100000, $value));
            }

            foreach (['delivery_store_lat', 'delivery_store_lng', 'delivery_per_km_from_km'] as $floatKey) {
                $value = str_replace(',', '.', trim((string)($_POST[$floatKey] ?? '')));
                if ($value === '' || !is_numeric($value)) {
                    $_POST[$floatKey] = $floatKey === 'delivery_per_km_from_km' ? '6' : '';
                    continue;
                }
                $number = (float)$value;
                if ($floatKey === 'delivery_store_lat') {
                    $number = max(-90.0, min(90.0, $number));
                } elseif ($floatKey === 'delivery_store_lng') {
                    $number = max(-180.0, min(180.0, $number));
                } else {
                    $number = max(0.0, min(1000.0, $number));
                }
                $_POST[$floatKey] = rtrim(rtrim(number_format($number, 6, '.', ''), '0'), '.');
            }

            $_POST['delivery_taxi_courier_instructions'] = trim((string)($_POST['delivery_taxi_courier_instructions'] ?? ''));

            foreach (['openrouteservice_api_key', 'dadata_api_key', 'dadata_secret_key'] as $passwordKey) {
                if (trim((string)($_POST[$passwordKey] ?? '')) === '') {
                    unset($_POST[$passwordKey]);
                }
            }
        }

        $deliveryTariffZones = ($activeSection === 'delivery' && is_array($_POST['delivery_tariff_zones'] ?? null)) ? $_POST['delivery_tariff_zones'] : [];
        unset($_POST['delivery_tariff_zones']);

        $this->pdo->beginTransaction();
        try {
            if ($activeSection === 'delivery') {
                $this->saveDeliveryTariffZones($deliveryTariffZones);
            }

            foreach ($_POST as $key => $value) {
                if (!is_scalar($value)) {
                    continue;
                }
                $stmt = $this->pdo->prepare(
                  "REPLACE INTO settings (setting_key, setting_value) VALUES (?, ?)"
                );
                $stmt->execute([$key, trim((string)$value)]);
            }
            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        header('Location: ' . $this->sectionUrl($activeSection));
        exit;
    }

    private function sectionUrl(string $section): string
    {
        return $section === 'general' ? '/admin/settings' : '/admin/settings/' . $section;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getDeliveryTariffZones(): array
    {
        $this->ensureDeliveryTariffZonesTable();
        if (!$this->tableExists('delivery_tariff_zones')) {
            return [];
        }

        $stmt = $this->pdo->query(
            "SELECT id, min_km, max_km, price_rub, sort_order, is_active
             FROM delivery_tariff_zones
             ORDER BY sort_order ASC, min_km ASC, id ASC"
        );

        return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    }

    /**
     * @param array<string, mixed> $zones
     */
    private function saveDeliveryTariffZones(array $zones): void
    {
        $this->ensureDeliveryTariffZonesTable();
        if (!$this->tableExists('delivery_tariff_zones')) {
            return;
        }

        $ids = $zones['id'] ?? [];
        $mins = $zones['min_km'] ?? [];
        $maxes = $zones['max_km'] ?? [];
        $prices = $zones['price_rub'] ?? [];
        $orders = $zones['sort_order'] ?? [];
        $actives = $zones['is_active'] ?? [];
        $deletes = $zones['delete'] ?? [];

        $count = max(count((array)$mins), count((array)$maxes), count((array)$prices), count((array)$ids));
        $upsert = $this->pdo->prepare(
            "INSERT INTO delivery_tariff_zones (id, min_km, max_km, price_rub, sort_order, is_active)
             VALUES (?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
               min_km = VALUES(min_km),
               max_km = VALUES(max_km),
               price_rub = VALUES(price_rub),
               sort_order = VALUES(sort_order),
               is_active = VALUES(is_active)"
        );
        $delete = $this->pdo->prepare("DELETE FROM delivery_tariff_zones WHERE id = ?");

        for ($i = 0; $i < $count; $i++) {
            $id = (int)($ids[$i] ?? 0);
            if ($id > 0 && isset($deletes[$i])) {
                $delete->execute([$id]);
                continue;
            }

            $minRaw = str_replace(',', '.', trim((string)($mins[$i] ?? '')));
            $maxRaw = str_replace(',', '.', trim((string)($maxes[$i] ?? '')));
            $price = (int)($prices[$i] ?? 0);
            if ($minRaw === '' && $maxRaw === '' && $price <= 0) {
                continue;
            }
            if ($minRaw === '' || !is_numeric($minRaw) || $price <= 0) {
                continue;
            }

            $min = max(0.0, (float)$minRaw);
            $max = ($maxRaw !== '' && is_numeric($maxRaw)) ? max(0.0, (float)$maxRaw) : null;
            if ($max !== null && $max <= $min) {
                continue;
            }

            $sortOrder = (int)($orders[$i] ?? ($i + 1));
            $isActive = isset($actives[$i]) ? 1 : 0;
            $upsert->execute([
                $id > 0 ? $id : null,
                $this->formatDecimal($min),
                $max !== null ? $this->formatDecimal($max) : null,
                max(0, min(100000, $price)),
                $sortOrder,
                $isActive,
            ]);
        }
    }


    private function ensureDeliveryTariffZonesTable(): void
    {
        if ($this->tableExists('delivery_tariff_zones')) {
            return;
        }

        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS delivery_tariff_zones (
              id int UNSIGNED NOT NULL AUTO_INCREMENT,
              min_km decimal(8,3) NOT NULL,
              max_km decimal(8,3) DEFAULT NULL,
              price_rub int UNSIGNED NOT NULL,
              sort_order int NOT NULL DEFAULT 0,
              is_active tinyint(1) NOT NULL DEFAULT 1,
              created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
              updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (id),
              KEY idx_delivery_tariff_zones_active_range (is_active, min_km, max_km),
              KEY idx_delivery_tariff_zones_sort (sort_order, min_km)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
    }

    private function tableExists(string $table): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?"
        );
        $stmt->execute([$table]);
        return (int)$stmt->fetchColumn() > 0;
    }

    private function formatDecimal(float $value): string
    {
        return rtrim(rtrim(number_format($value, 3, '.', ''), '0'), '.');
    }
}
