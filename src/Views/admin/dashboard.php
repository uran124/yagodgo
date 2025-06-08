<?php
// Здесь могут быть данные: $stats, $chartData и т.п.
/** @var array $stats */
/** @var array $chartData */
/** @var array $purchaseList */
?>
<div class="space-y-6">
  <div class="grid grid-cols-3 gap-4">
    <div class="bg-white p-4 rounded shadow">
      <h3 class="text-lg font-medium">Сегодняшняя выручка</h3>
      <p class="text-2xl font-bold"><?= htmlspecialchars($stats['today_revenue'] ?? '—') ?> ₽</p>
    </div>
    <div class="bg-white p-4 rounded shadow">
      <h3 class="text-lg font-medium">Новых заказов</h3>
      <p class="text-2xl font-bold"><?= htmlspecialchars($stats['today_orders'] ?? '—') ?></p>
    </div>
    <div class="bg-white p-4 rounded shadow">
      <h3 class="text-lg font-medium">Средний чек</h3>
      <p class="text-2xl font-bold"><?= htmlspecialchars($stats['average_check'] ?? '—') ?> ₽</p>
    </div>
  </div>

  <div class="grid grid-cols-3 gap-4">
    <div class="bg-white p-4 rounded shadow">
      <h3 class="text-lg font-medium">Выполнено за 24ч</h3>
      <p class="text-2xl font-bold"><?= $stats['completed_day'] ?></p>
    </div>
    <div class="bg-white p-4 rounded shadow">
      <h3 class="text-lg font-medium">Выполнено за 7 дней</h3>
      <p class="text-2xl font-bold"><?= $stats['completed_week'] ?></p>
    </div>
    <div class="bg-white p-4 rounded shadow">
      <h3 class="text-lg font-medium">Выполнено за 30 дней</h3>
      <p class="text-2xl font-bold"><?= $stats['completed_month'] ?></p>
    </div>
  </div>

  <div class="grid grid-cols-3 gap-4">
    <div class="bg-white p-4 rounded shadow">
      <h3 class="text-lg font-medium">Новых пользователей 24ч</h3>
      <p class="text-2xl font-bold"><?= $stats['users_day'] ?></p>
    </div>
    <div class="bg-white p-4 rounded shadow">
      <h3 class="text-lg font-medium">Новых пользователей 7д</h3>
      <p class="text-2xl font-bold"><?= $stats['users_week'] ?></p>
    </div>
    <div class="bg-white p-4 rounded shadow">
      <h3 class="text-lg font-medium">Новых пользователей 30д</h3>
      <p class="text-2xl font-bold"><?= $stats['users_month'] ?></p>
    </div>
  </div>

  <!-- Здесь, например, график выручки -->
  <div class="bg-white p-4 rounded shadow">
    <h3 class="text-lg font-medium mb-2">Выручка за 7 дней</h3>
    <canvas id="revenueChart" class="w-full h-64"></canvas>
  </div>
  
  
  
  <?php if (!empty($purchaseList)): ?>
  <div class="bg-white p-4 rounded shadow">
    <h3 class="text-lg font-medium mb-2">Закупка по принятым заказам</h3>
    <table class="min-w-full text-sm">
      <thead>
        <tr>
          <th class="p-2 text-left">Товар</th>
          <th class="p-2 text-left">Количество</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($purchaseList as $row): ?>
        <tr class="border-b">
          <td class="p-2"><?= htmlspecialchars($row['product']) ?></td>
          <td class="p-2">
            <?= $row['qty'] ?> <?= htmlspecialchars($row['unit']) ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
  
  
  
  
  
  
  
  
  
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
  const ctx = document.getElementById('revenueChart').getContext('2d');
  new Chart(ctx, {
    type: 'line',
    data: {
      labels: <?= json_encode($chartData['labels'] ?? []) ?>,
      datasets: [{
        label: 'Выручка',
        data: <?= json_encode($chartData['values'] ?? []) ?>,
        fill: false,
        borderColor: '#C86052'
      }]
    },
    options: { responsive: true, maintainAspectRatio: false }
  });
</script>
