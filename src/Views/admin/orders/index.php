<?php /** @var array $orders */ ?>
<?php $isManager = ($_SESSION['role'] ?? '') === 'manager'; ?>
<?php $base = $isManager ? '/manager' : '/admin'; ?>
<?php $managers = $managers ?? []; $selectedManager = $selectedManager ?? 0; ?>
<style>
  @media (max-width: 640px) {
    .orders-filter select,
    .orders-filter button,
    .orders-filter input {
      padding: 0.25rem 0.5rem;
      font-size: 0.75rem;
    }
    .orders-table th,
    .orders-table td {
      padding: 0.25rem;
      font-size: 0.75rem;
    }
  }
</style>
<?php if (!empty($_GET['msg'])): ?>
  <div class="mb-4 p-3 rounded bg-green-50 text-green-800 border border-green-200">
    <?= htmlspecialchars($_GET['msg']) ?>
  </div>
<?php endif; ?>
<div class="mb-2">
  <a href="<?= $base ?>/orders/create" class="px-2 py-1 md:px-3 md:py-2 bg-[#C86052] text-white rounded text-xs md:text-sm">Создать заказ</a>
</div>
<div class="orders-filter mb-4 flex flex-col md:flex-row md:items-end md:justify-between gap-2">
  <select id="statusFilter" class="border rounded px-3 py-2 text-sm">
    <option value="">Все статусы</option>
    <option value="new">Новые</option>
    <option value="processing">Принятые</option>
    <option value="assigned">В работе</option>
    <option value="delivered">Выполненные</option>
    <option value="cancelled">Отмененные</option>
  </select>
  <?php if ($isManager || !empty($managers)): ?>
    <select id="managerFilter" class="border rounded px-3 py-2 text-sm">
      <option value="">Все менеджеры</option>
      <?php foreach ($managers as $m): ?>
        <option value="<?= $m['id'] ?>" <?= $selectedManager == $m['id'] ? 'selected' : '' ?>><?= htmlspecialchars($m['name']) ?></option>
      <?php endforeach; ?>
    </select>
  <?php endif; ?>
  <div class="flex items-center gap-2">
    <button id="todayBtn" class="px-3 py-2 bg-gray-200 rounded text-sm">Сегодня</button>
    <button id="tomorrowBtn" class="px-3 py-2 bg-gray-200 rounded text-sm">Завтра</button>
    <input type="date" id="dateFrom" class="border rounded px-2 py-1 text-sm">
    <span class="text-gray-500">-</span>
    <input type="date" id="dateTo" class="border rounded px-2 py-1 text-sm">
    <button id="clearDate" class="px-3 py-2 bg-gray-200 rounded text-sm">Без даты</button>
  </div>
</div>

<?php if ($isManager): ?>
  <div id="ordersCards" class="space-y-3">
    <?php foreach ($orders as $o): ?>
      <?php
        $bg = in_array($o['status'], ['new','processing'], true) ? 'bg-gray-200' : '';
        $dateAttr = $o['delivery_date'] ? date('Y-m-d', strtotime($o['delivery_date'])) : '';
        $createdAttr = date('Y-m-d H:i', strtotime($o['created_at']));
        $deliveryAttr = $o['delivery_date'] ? date('Y-m-d', strtotime($o['delivery_date'])) : '';
      ?>
      <div class="order-card bg-white p-4 rounded shadow <?= $bg ?>" data-status="<?= $o['status'] ?>" data-date="<?= $dateAttr ?>" data-created="<?= $createdAttr ?>" data-id="<?= $o['id'] ?>" data-delivery="<?= $deliveryAttr ?>">
        <div class="flex justify-between items-center">
          <div class="flex flex-col">
            <span class="font-semibold">#<?= $o['id'] ?><?php if ($o['delivery_date']): ?>, <?= date('d.m', strtotime($o['delivery_date'])) ?> <?= htmlspecialchars(format_time_range($o['slot_from'], $o['slot_to'])) ?><?php endif; ?></span>
          </div>
          <span class="inline-flex items-center px-2 py-0.5 rounded-full text-sm font-medium <?= order_status_info($o['status'])['badge'] ?>">
            <?= order_status_info($o['status'])['label'] ?>
          </span>
        </div>
        <div class="text-sm text-gray-600 mt-1">
          <?= htmlspecialchars($o['client_name']) ?>, <?= htmlspecialchars($o['phone']) ?>, <?= htmlspecialchars($o['address']) ?>
        </div>
        <div class="font-semibold mt-2">Состав:</div>
        <?php foreach ($o['items'] as $it): ?>
          <?php $lineCost = $it['quantity'] * $it['unit_price']; ?>
          <?php $boxes = isset($it['boxes']) ? $it['boxes'] : ($it['box_size']>0 ? round($it['quantity']/$it['box_size'],1) : $it['quantity']); ?>
          <div class="flex justify-between text-sm">
            <span><?= htmlspecialchars($it['product_name']) ?><?php if(!empty($it['variety'])): ?> «<?= htmlspecialchars($it['variety']) ?>»<?php endif; ?>, <?= $boxes ?> ящ. (<?= htmlspecialchars($it['quantity']) ?> кг)</span>
            <span><?= number_format($lineCost, 0, '.', ' ') ?> ₽</span>
          </div>
        <?php endforeach; ?>
        <?php if (($o['points_from_balance'] ?? 0) > 0): ?>
          <div class="flex justify-between text-sm text-pink-600 border-t mt-1 pt-1">
            <span>Списано баллов:</span>
            <span>-<?= $o['points_from_balance'] ?></span>
          </div>
        <?php endif; ?>
        <?php if (!empty($o['coupon']) && $o['coupon']['type'] === 'discount' && $o['coupon_discount'] > 0): ?>
          <div class="flex justify-between text-sm">
            <span>Скидка КУПОН:</span>
            <span>-<?= number_format($o['coupon_discount'], 0, '.', ' ') ?> ₽</span>
          </div>
        <?php endif; ?>
        <?php if (($o['pickup_discount'] ?? 0) > 0): ?>
          <div class="flex justify-between text-sm">
            <span>Скидка за самовывоз:</span>
            <span>-<?= number_format($o['pickup_discount'], 0, '.', ' ') ?> ₽</span>
          </div>
        <?php endif; ?>
        <div class="flex justify-between font-semibold border-t pt-1 mt-1">
          <span>Стоимость заказа:</span>
          <span><?= number_format($o['total_amount'], 0, '.', ' ') ?> ₽</span>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php else: ?>
<div class="overflow-x-auto">
<table class="orders-table min-w-full bg-white rounded shadow overflow-hidden">
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
      <tr data-status="<?= $o['status'] ?>" data-date="<?= $dateAttr ?>" data-created="<?= $createdAttr ?>" data-id="<?= $o['id'] ?>" data-delivery="<?= $deliveryAttr ?>" class="border-b hover:bg-gray-50 cursor-pointer <?= $bg ?>" onclick="location.href='<?= $base ?>/orders/<?= $o['id'] ?>'">
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
            <?= date('d.m', strtotime($o['delivery_date'])) ?> <?= htmlspecialchars(format_time_range($o['slot_from'], $o['slot_to'])) ?>
          <?php endif; ?>
        </td>
        <td class="p-3 text-gray-600"><?= htmlspecialchars(format_time_range($o['slot_from'], $o['slot_to'])) ?></td>
        <td class="p-3 text-gray-600"><?= htmlspecialchars($o['client_name']) ?></td>
        <td class="p-3 text-gray-600"><?= htmlspecialchars($o['phone']) ?></td>
        <td class="p-3 text-gray-600"><?= htmlspecialchars($o['address']) ?></td>
        <td class="p-3 text-gray-600"><?= $o['total_amount'] ?> ₽</td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>
</div>
<?php endif; ?>

<script>
  document.addEventListener('DOMContentLoaded', function () {
    const statusFilter = document.getElementById('statusFilter');
    const dateFrom = document.getElementById('dateFrom');
    const dateTo = document.getElementById('dateTo');
    const todayBtn = document.getElementById('todayBtn');
    const tomorrowBtn = document.getElementById('tomorrowBtn');
    const clearDate = document.getElementById('clearDate');
    const managerFilter = document.getElementById('managerFilter');
    const isManager = <?= $isManager ? 'true' : 'false' ?>;
    let rows = document.querySelectorAll(isManager ? '#ordersCards .order-card' : '#ordersTable tr');

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

    managerFilter?.addEventListener('change', () => {
      const val = managerFilter.value;
      const params = new URLSearchParams(window.location.search);
      if (val) { params.set('manager', val); } else { params.delete('manager'); }
      window.location.search = params.toString();
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
      rows = document.querySelectorAll(isManager ? '#ordersCards .order-card' : '#ordersTable tr');
    }
  });
</script>
