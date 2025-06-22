<?php /** @var array $orders */ ?>
<div class="mb-4 flex flex-col md:flex-row md:items-end md:justify-between gap-2">
  <select id="statusFilter" class="border rounded px-3 py-2 text-sm">
    <option value="">Все статусы</option>
    <option value="new">Новые</option>
    <option value="processing">Принятые</option>
    <option value="assigned">В работе</option>
    <option value="delivered">Выполненные</option>
    <option value="cancelled">Отмененные</option>
  </select>
  <div class="flex items-center gap-2">
    <button id="todayBtn" class="px-3 py-2 bg-gray-200 rounded text-sm">Сегодня</button>
    <button id="tomorrowBtn" class="px-3 py-2 bg-gray-200 rounded text-sm">Завтра</button>
    <input type="date" id="dateFrom" class="border rounded px-2 py-1 text-sm">
    <span class="text-gray-500">-</span>
    <input type="date" id="dateTo" class="border rounded px-2 py-1 text-sm">
    <button id="clearDate" class="px-3 py-2 bg-gray-200 rounded text-sm">Без даты</button>
  </div>
</div>

<table class="min-w-full bg-white rounded shadow overflow-hidden">
  <thead class="bg-gray-200 text-gray-700">
    <tr>
      <th class="p-3 text-left font-semibold cursor-pointer sortable" data-sort="id">№</th>
      <th class="p-3 text-left font-semibold cursor-pointer sortable" data-sort="created">Дата оформления</th>
      <th class="p-3 text-left font-semibold cursor-pointer sortable" data-sort="delivery">Дата доставки</th>
      <th class="p-3 text-left font-semibold">Слот</th>
      <th class="p-3 text-left font-semibold">Клиент</th>
      <th class="p-3 text-left font-semibold">Телефон</th>
      <th class="p-3 text-left font-semibold">Адрес</th>
      <th class="p-3 text-left font-semibold">Сумма</th>
    </tr>
  </thead>
  <tbody id="ordersTable">
    <?php foreach ($orders as $o): ?>
      <?php
        $bg = in_array($o['status'], ['new','processing'], true) ? 'bg-gray-200' : '';
        $dateAttr = $o['delivery_date'] ? date('Y-m-d', strtotime($o['delivery_date'])) : '';
        $createdAttr = date('Y-m-d H:i', strtotime($o['created_at']));
        $deliveryAttr = $o['delivery_date'] ? date('Y-m-d', strtotime($o['delivery_date'])) : '';
      ?>
      <tr data-status="<?= $o['status'] ?>" data-date="<?= $dateAttr ?>" data-created="<?= $createdAttr ?>" data-id="<?= $o['id'] ?>" data-delivery="<?= $deliveryAttr ?>" class="border-b hover:bg-gray-50 cursor-pointer <?= $bg ?>" onclick="location.href='/admin/orders/<?= $o['id'] ?>'">
        <?php
          $numCls = match($o['status']) {
            'new' => 'text-red-500',
            'processing' => 'text-yellow-400',
            'assigned' => 'text-green-500',
            default => ''
          };
        ?>
        <td class="p-3 font-semibold <?= $numCls ?>">#<?= $o['id'] ?></td>
        <td class="p-3 text-gray-600"><?= date('d.m H:i', strtotime($o['created_at'])) ?></td>
        <td class="p-3 text-gray-600">
          <?php if ($o['delivery_date']): ?>
            <?= date('d.m', strtotime($o['delivery_date'])) ?> <?= htmlspecialchars(format_slot($o['delivery_slot'])) ?>
          <?php endif; ?>
        </td>
        <td class="p-3 text-gray-600"><?= htmlspecialchars(format_slot($o['delivery_slot'])) ?></td>
        <td class="p-3 text-gray-600"><?= htmlspecialchars($o['client_name']) ?></td>
        <td class="p-3 text-gray-600"><?= htmlspecialchars($o['phone']) ?></td>
        <td class="p-3 text-gray-600"><?= htmlspecialchars($o['address']) ?></td>
        <td class="p-3 text-gray-600"><?= $o['total_amount'] ?> ₽</td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>

<script>
  document.addEventListener('DOMContentLoaded', function () {
    const statusFilter = document.getElementById('statusFilter');
    const dateFrom = document.getElementById('dateFrom');
    const dateTo = document.getElementById('dateTo');
    const todayBtn = document.getElementById('todayBtn');
    const tomorrowBtn = document.getElementById('tomorrowBtn');
    const clearDate = document.getElementById('clearDate');
    let rows = document.querySelectorAll('#ordersTable tr');

    function applyFilters() {
      const s = statusFilter.value;
      const from = dateFrom.value;
      const to = dateTo.value;
      rows.forEach(row => {
        const st = row.dataset.status;
        const d = row.dataset.date;
        let visible = true;
        if (s && st !== s) visible = false;
        if (from && (!d || d < from)) visible = false;
        if (to && (!d || d > to)) visible = false;
        row.style.display = visible ? '' : 'none';
      });
    }

    statusFilter.addEventListener('change', applyFilters);
    dateFrom.addEventListener('change', applyFilters);
    dateTo.addEventListener('change', applyFilters);

    todayBtn.addEventListener('click', () => {
      const d = new Date().toISOString().slice(0,10);
      dateFrom.value = d; dateTo.value = d;
      applyFilters();
    });

    tomorrowBtn.addEventListener('click', () => {
      const t = new Date();
      t.setDate(t.getDate()+1);
      const d = t.toISOString().slice(0,10);
      dateFrom.value = d; dateTo.value = d;
      applyFilters();
    });

    clearDate.addEventListener('click', () => {
      dateFrom.value = '';
      dateTo.value = '';
      applyFilters();
    });

    document.querySelectorAll('th.sortable').forEach(th => {
      th.addEventListener('click', function () {
        const field = th.dataset.sort;
        const dir = th.dataset.dir === 'asc' ? 'desc' : 'asc';
        th.dataset.dir = dir;
        sortRows(field, dir);
      });
    });

    function sortRows(field, dir) {
      const tbody = document.getElementById('ordersTable');
      const arr = Array.from(tbody.querySelectorAll('tr'));
      arr.sort((a, b) => {
        let av = a.dataset[field];
        let bv = b.dataset[field];
        if (field === 'id') {
          av = parseInt(av, 10);
          bv = parseInt(bv, 10);
        } else {
          av = new Date(av || 0);
          bv = new Date(bv || 0);
        }
        return dir === 'asc' ? av - bv : bv - av;
      });
      arr.forEach(r => tbody.appendChild(r));
      rows = document.querySelectorAll('#ordersTable tr');
    }
  });
</script>
