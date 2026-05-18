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
        $discount = isset($_POST['ui_preorder_discount_percent']) ? (float)$_POST['ui_preorder_discount_percent'] : 10.0;
        $_POST['ui_preorder_discount_percent'] = (string)max(0.0, min(99.0, $discount));

        $hint = trim((string)($_POST['ui_preorder_price_hint'] ?? ''));
        if ($hint === '') {
            $hint = 'Цена ориентировочная, точная цена будет после поступления';
        }
        $_POST['ui_preorder_price_hint'] = $hint;

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
