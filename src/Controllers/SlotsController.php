<?php
namespace App\Controllers;

use PDO;

class SlotsController
{
    private PDO $pdo;
    public function __construct(PDO $pdo) { $this->pdo = $pdo; }

    // Список слотов
    public function index(): void
    {
        $stmt = $this->pdo->query(
          "SELECT id, date, time_from, time_to FROM delivery_slots ORDER BY date, time_from"
        );
        $slots = $stmt->fetchAll(PDO::FETCH_ASSOC);

        viewAdmin('slots/index', [
          'pageTitle' => 'Слоты доставки',
          'slots'     => $slots,
        ]);
    }

    // Форма редактирования/создания
    public function edit(): void
    {
        $id = $_GET['id'] ?? null;
        $slot = null;
        if ($id) {
            $stmt = $this->pdo->prepare("SELECT * FROM delivery_slots WHERE id = ?");
            $stmt->execute([(int)$id]);
            $slot = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        viewAdmin('slots/edit', [
          'pageTitle' => $id ? 'Редактировать слот' : 'Добавить слот',
          'slot'      => $slot,
        ]);
    }

    // Сохранение
    public function save(): void
    {
        $id        = $_POST['id'] ?? null;
        $date      = $_POST['date'] ?? '';
        $timeFrom  = $_POST['time_from'] ?? '';
        $timeTo    = $_POST['time_to'] ?? '';

        if ($id) {
            $stmt = $this->pdo->prepare(
              "UPDATE delivery_slots SET date = ?, time_from = ?, time_to = ? WHERE id = ?"
            );
            $stmt->execute([$date, $timeFrom, $timeTo, (int)$id]);
        } else {
            $stmt = $this->pdo->prepare(
              "INSERT INTO delivery_slots (date, time_from, time_to) VALUES (?, ?, ?)"
            );
            $stmt->execute([$date, $timeFrom, $timeTo]);
        }
        header('Location: /admin/slots');
        exit;
    }

    // Удаление слота
    public function delete(): void
    {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            $this->pdo->prepare("DELETE FROM delivery_slots WHERE id = ?")->execute([$id]);
        }
        header('Location: /admin/slots');
        exit;
    }
}
