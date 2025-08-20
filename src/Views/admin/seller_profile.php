<?php
/**
 * @var int   $ordersCount
 * @var int   $revenue
 * @var array $payouts
 */
?>
<div class="space-y-6">
  <div class="bg-white rounded shadow p-2 md:p-4">
    <h2 class="text-base md:text-lg font-semibold mb-2">Общая статистика</h2>
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 md:gap-4 text-center">
      <div>
        <div class="text-xl md:text-2xl font-bold text-[#C86052]"><?= $ordersCount ?></div>
        <div class="text-sm text-gray-600">продаж</div>
      </div>
      <div>
        <div class="text-xl md:text-2xl font-bold text-[#C86052]"><?= $revenue ?> ₽</div>
        <div class="text-sm text-gray-600">оборот</div>
      </div>
    </div>
  </div>

  <?php if (!empty($payouts)): ?>
  <div class="bg-white rounded shadow p-2 md:p-4 overflow-x-auto">
    <h2 class="text-base md:text-lg font-semibold mb-2">Последние выплаты</h2>
    <table class="min-w-full text-left text-sm">
      <thead class="bg-gray-100">
        <tr>
          <th class="p-2">Дата</th>
          <th class="p-2">Сумма продажи</th>
          <th class="p-2">Выплата</th>
          <th class="p-2">Статус</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($payouts as $p): ?>
        <tr class="border-b">
          <td class="p-2"><?= htmlspecialchars($p['created_at']) ?></td>
          <td class="p-2"><?= htmlspecialchars($p['gross_amount']) ?></td>
          <td class="p-2"><?= htmlspecialchars($p['payout_amount']) ?></td>
          <td class="p-2"><?= htmlspecialchars($p['status']) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php else: ?>
  <div class="bg-white rounded shadow p-2 md:p-4">
    <p class="text-sm text-gray-600">Выплат пока нет</p>
  </div>
  <?php endif; ?>
</div>
