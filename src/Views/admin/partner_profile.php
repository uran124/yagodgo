<?php
/**
 * @var int   $clientCount
 * @var int   $ordersCount
 * @var int   $revenue
 * @var int   $tenPercent
 * @var int   $pointsBalance
 * @var int   $rubBalance
 * @var array $payoutTransactions
 * @var array $chartLabels
 * @var array $chartClients
 * @var array $chartOrders
 * @var array $chartRevenue
 */
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
        <div class="text-xl md:text-2xl font-bold text-[#C86052]"><?= $clientCount ?></div>
        <div class="text-sm text-gray-600">клиентов</div>
      </div>
      <div>
        <div class="text-xl md:text-2xl font-bold text-[#C86052]"><?= $revenue ?></div>
        <div class="text-sm text-gray-600">оборот, ₽</div>
      </div>
    </div>
  </div>
  <div class="bg-white rounded shadow p-2 md:p-4">
    <h2 class="text-base md:text-lg font-semibold mb-2">Статистика по месяцам</h2>
    <div class="space-y-4">
      <div><canvas id="clientsChart" class="w-full h-48 md:h-64"></canvas></div>
      <div><canvas id="ordersChart" class="w-full h-48 md:h-64"></canvas></div>
      <div><canvas id="revenueChart" class="w-full h-48 md:h-64"></canvas></div>
    </div>
  </div>
  <div class="bg-white rounded shadow p-2 md:p-4">
    <h2 class="text-base md:text-lg font-semibold mb-2">Баланс</h2>
    <div class="grid grid-cols-2 md:grid-cols-3 gap-2 md:gap-4 text-center">
      <div>
        <div class="text-xl md:text-2xl font-bold text-[#C86052]"><?= $tenPercent ?> ₽</div>
        <div class="text-sm text-gray-600">10% от клиентов</div>
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
      <form method="POST" action="/partner/payout">
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
</div>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
  const labels = <?= json_encode($chartLabels) ?>;
  const clientsData = <?= json_encode($chartClients) ?>;
  const ordersData = <?= json_encode($chartOrders) ?>;
  const revenueData = <?= json_encode($chartRevenue) ?>;

  const baseOptions = {responsive: true, maintainAspectRatio: false};

  new Chart(document.getElementById('clientsChart').getContext('2d'), {
    type: 'bar',
    data: { labels, datasets: [{ label: 'Клиенты', data: clientsData, backgroundColor: '#03A9F4' }] },
    options: baseOptions
  });

  new Chart(document.getElementById('ordersChart').getContext('2d'), {
    type: 'bar',
    data: { labels, datasets: [{ label: 'Заказы', data: ordersData, backgroundColor: '#C86052' }] },
    options: baseOptions
  });

  new Chart(document.getElementById('revenueChart').getContext('2d'), {
    type: 'bar',
    data: { labels, datasets: [{ label: 'Выручка', data: revenueData, backgroundColor: '#4CAF50' }] },
    options: baseOptions
  });
</script>
