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

        foreach ($_POST as $key => $value) {
            $stmt = $this->pdo->prepare(
              "REPLACE INTO settings (setting_key, setting_value) VALUES (?, ?)"
            );
            $stmt->execute([$key, trim($value)]);
        }
        header('Location: /admin/settings');
        exit;
    }
}
