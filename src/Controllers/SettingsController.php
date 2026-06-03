<?php
namespace App\Controllers;

use PDO;

class SettingsController
{
    private PDO $pdo;
    public function __construct(PDO $pdo) { $this->pdo = $pdo; }

    // Форма настроек
    public function index(): void
    {
        // Например, читаем из таблицы settings
        $stmt = $this->pdo->query("SELECT setting_key, setting_value FROM settings");
        $all = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        viewAdmin('settings', [
          'pageTitle'   => 'Настройки',
          'settings'    => $all,
          'themeColors' => \get_theme_palette(),
        ]);
    }

    // Сохранение
    public function save(): void
    {
        $instantMargin = isset($_POST['pricing_instant_margin_percent']) ? (float)$_POST['pricing_instant_margin_percent'] : 50.0;
        $_POST['pricing_instant_margin_percent'] = (string)max(0.0, min(500.0, $instantMargin));

        $roundingStep = isset($_POST['pricing_rounding_step']) ? (int)$_POST['pricing_rounding_step'] : 10;
        $_POST['pricing_rounding_step'] = (string)max(1, min(10000, $roundingStep));

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

        foreach ($_POST as $key => $value) {
            if (!is_scalar($value)) {
                continue;
            }
            $stmt = $this->pdo->prepare(
              "REPLACE INTO settings (setting_key, setting_value) VALUES (?, ?)"
            );
            $stmt->execute([$key, trim((string)$value)]);
        }
        header('Location: /admin/settings');
        exit;
    }
}
