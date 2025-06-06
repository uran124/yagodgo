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
          'pageTitle' => 'Настройки',
          'settings'  => $all,
        ]);
    }

    // Сохранение
    public function save(): void
    {
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
