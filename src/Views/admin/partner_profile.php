<?php /** @var int $clientCount @var int $ordersCount @var int $revenue */ ?>
<div class="space-y-6">
  <div class="bg-white rounded shadow p-2 md:p-4">
    <h2 class="text-base md:text-lg font-semibold mb-2">Общая статистика</h2>
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 md:gap-4 text-center">
      <div>
        <div class="text-xl md:text-2xl font-bold text-[#C86052]"><?= $ordersCount ?></div>
        <div class="text-sm text-gray-600">продаж</div>
      </div>
      <div>
        <div class="text-xl md:text-2xl font-bold text-[#C86052]"><?= $clientCount ?></div>
        <div class="text-sm text-gray-600">клиентов</div>
      </div>
      <div>
        <div class="text-xl md:text-2xl font-bold text-[#C86052]"><?= $revenue ?></div>
        <div class="text-sm text-gray-600">оборот, ₽</div>
      </div>
    </div>
  </div>
</div>
