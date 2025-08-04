<?php
/** @var int $directClients */
/** @var int $secondClients */
/** @var int $ordersCount */
/** @var array $partnerStats */
/** @var int $directBonus */
/** @var int $secondBonus */
/** @var int $pointsBalance */
/** @var int $rubBalance */
/** @var array $payoutTransactions */
?>
<div class="space-y-6">
  <div class="bg-white rounded shadow p-2 md:p-4">
    <h2 class="text-base md:text-lg font-semibold mb-2">Общая статистика</h2>
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 md:gap-4 text-center">
      <div>
        <div class="text-xl md:text-2xl font-bold text-[#C86052]"><?= $ordersCount ?></div>
        <div class="text-sm text-gray-600">продаж</div>
      </div>
      <div>
        <div class="text-xl md:text-2xl font-bold text-[#C86052]"><?= $directClients ?></div>
        <div class="text-sm text-gray-600">прямых клиентов</div>
      </div>
      <div>
        <div class="text-xl md:text-2xl font-bold text-[#C86052]"><?= $secondClients ?></div>
        <div class="text-sm text-gray-600">клиентов второго уровня</div>
      </div>
    </div>
  </div>
  <div class="bg-white rounded shadow p-2 md:p-4">
    <h2 class="text-base md:text-lg font-semibold mb-2">Баланс</h2>
    <div class="grid grid-cols-2 md:grid-cols-4 gap-2 md:gap-4 text-center">
      <div>
        <div class="text-xl md:text-2xl font-bold text-[#C86052]"><?= $directBonus ?> ₽</div>
        <div class="text-sm text-gray-600">3% от прямых</div>
      </div>
      <div>
        <div class="text-xl md:text-2xl font-bold text-[#C86052]"><?= $secondBonus ?> ₽</div>
        <div class="text-sm text-gray-600">3% от второго уровня</div>
      </div>
      <div>
        <div class="text-xl md:text-2xl font-bold text-[#C86052]"><?= $pointsBalance ?> <span class="text-lg">🍓</span></div>
        <div class="text-sm text-gray-600">баланс клубничек</div>
      </div>
      <div>
        <div class="text-xl md:text-2xl font-bold text-[#C86052]"><?= $rubBalance ?> ₽</div>
        <div class="text-sm text-gray-600">баланс рублей</div>
      </div>
    </div>
    <div class="text-center mt-4">
      <form method="POST" action="/manager/payout">
        <button class="bg-[#C86052] text-white px-4 py-2 rounded">Запросить выплату</button>
      </form>
    </div>
    <?php if (!empty($payoutTransactions)): ?>
    <div class="mt-4 overflow-x-auto">
      <table class="min-w-full text-left text-sm">
        <thead class="bg-gray-100">
          <tr>
            <th class="p-2">Дата</th>
            <th class="p-2">Сумма</th>
            <th class="p-2">Описание</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($payoutTransactions as $tx): ?>
          <tr class="border-b">
            <td class="p-2"><?= htmlspecialchars($tx['created_at']) ?></td>
            <td class="p-2"><?= -$tx['amount'] ?> ₽</td>
            <td class="p-2"><?= htmlspecialchars($tx['description']) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
  <div class="bg-white rounded shadow p-2 md:p-4">
    <h2 class="text-base md:text-lg font-semibold mb-4">Партнёры</h2>
    <?php if (empty($partnerStats)): ?>
      <p class="text-gray-600">Партнёров нет</p>
    <?php else: ?>
    <table class="min-w-full text-left text-sm">
      <thead class="bg-gray-100">
        <tr>
          <th class="p-2">Имя</th>
          <th class="p-2">Телефон</th>
          <th class="p-2 text-center">Клиенты</th>
          <th class="p-2 text-center">Заказы</th>
          <th class="p-2 text-center">Сумма</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($partnerStats as $ps): ?>
        <tr class="border-b">
          <td class="p-2"><?= htmlspecialchars($ps['name']) ?></td>
          <td class="p-2"><?= htmlspecialchars($ps['phone']) ?></td>
          <td class="p-2 text-center"><?= $ps['clients'] ?></td>
          <td class="p-2 text-center"><?= $ps['orders'] ?></td>
          <td class="p-2 text-center"><?= $ps['revenue'] ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>
</div>
