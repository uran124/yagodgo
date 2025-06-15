<?php
/** @var array $chartData */
/** @var string $mode */
/** @var int $year */
/** @var int $month */
?>
<form method="get" class="flex space-x-2 mb-4">
  <select name="mode" id="mode" class="border px-2 py-1 rounded">
    <option value="month" <?= $mode==='month' ? 'selected' : '' ?>>По дням</option>
    <option value="year" <?= $mode==='year' ? 'selected' : '' ?>>По месяцам</option>
  </select>
  <select name="month" id="monthSelect" class="border px-2 py-1 rounded" <?= $mode==='month' ? '' : 'style="display:none"' ?>>
    <?php for ($m=1; $m<=12; $m++): ?>
      <option value="<?= $m ?>" <?= $month==$m?'selected':'' ?>><?= date('F', mktime(0,0,0,$m,1)) ?></option>
    <?php endfor; ?>
  </select>
  <select name="year" id="yearSelect" class="border px-2 py-1 rounded">
    <?php $currentYear=(int)date('Y'); for ($y=$currentYear; $y>=$currentYear-5; $y--): ?>
      <option value="<?= $y ?>" <?= $year==$y?'selected':'' ?>><?= $y ?></option>
    <?php endfor; ?>
  </select>
  <button type="submit" class="bg-[#C86052] text-white px-3 py-1 rounded">Показать</button>
</form>
<div class="space-y-6">
  <div class="bg-white p-4 rounded shadow">
    <canvas id="ordersChart" class="w-full h-64"></canvas>
  </div>
  <div class="bg-white p-4 rounded shadow">
    <canvas id="revenueChart" class="w-full h-64"></canvas>
  </div>
  <div class="bg-white p-4 rounded shadow">
    <canvas id="usersChart" class="w-full h-64"></canvas>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
  const labels = <?= json_encode($chartData['labels']) ?>;
  const ordersData = <?= json_encode($chartData['orders']) ?>;
  const revenueData = <?= json_encode($chartData['revenue']) ?>;
  const usersData = <?= json_encode($chartData['users']) ?>;

  const baseOptions = {responsive: true, maintainAspectRatio: false};

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
  new Chart(document.getElementById('usersChart').getContext('2d'), {
    type: 'bar',
    data: { labels, datasets: [{ label: 'Новые пользователи', data: usersData, backgroundColor: '#03A9F4' }] },
    options: baseOptions
  });

  document.getElementById('mode').addEventListener('change', function() {
    document.getElementById('monthSelect').style.display = this.value === 'month' ? '' : 'none';
  });
</script>
