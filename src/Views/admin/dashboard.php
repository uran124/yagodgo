<?php
// Здесь могут быть данные: $stats, $chartData и т.п.
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

  <!-- Здесь, например, график выручки -->
  <div class="bg-white p-4 rounded shadow">
    <h3 class="text-lg font-medium mb-2">Выручка за 7 дней</h3>
    <canvas id="revenueChart" class="w-full h-64"></canvas>
  </div>
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
