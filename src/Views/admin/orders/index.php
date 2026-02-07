<?php /** @var array $orders */ ?>
<?php $role = $_SESSION['role'] ?? ''; $isManager = ($role === 'manager'); $isStaff = in_array($role, ['manager','partner'], true); $base = $role === 'manager' ? '/manager' : ($role === 'partner' ? '/partner' : '/admin'); ?>
<?php $managers = $managers ?? []; $selectedManager = $selectedManager ?? 0; $slots = $slots ?? []; ?>
<style>
  @media (max-width: 640px) {
    .orders-filter select,
    .orders-filter button,
    .orders-filter input,
    .date-filter button {
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
<div class="orders-filter mb-4 flex flex-row flex-wrap items-end gap-2">
  <a href="<?= $base ?>/orders/create" class="px-2 py-1 md:px-3 md:py-2 bg-[#C86052] text-white rounded text-xs md:text-sm whitespace-nowrap">Создать новый</a>
  <select id="statusFilter" class="border rounded px-3 py-2 text-sm">
    <option value="">Все статусы</option>
    <option value="new">Новые</option>
    <option value="processing">Принятые</option>
    <option value="assigned">В работе</option>
    <option value="delivered">Выполненные</option>
    <option value="cancelled">Отмененные</option>
  </select>
  <?php if ($role === 'manager' || !empty($managers)): ?>
    <select id="managerFilter" class="border rounded px-3 py-2 text-sm">
      <option value="">Все менеджеры</option>
      <?php foreach ($managers as $m): ?>
        <option value="<?= $m['id'] ?>" <?= $selectedManager == $m['id'] ? 'selected' : '' ?>><?= htmlspecialchars($m['name']) ?></option>
      <?php endforeach; ?>
    </select>
  <?php endif; ?>
</div>
<div class="date-filter mb-4 flex flex-row flex-wrap gap-2">
  <button data-filter="today" class="date-btn px-3 py-2 bg-gray-200 rounded text-sm">Сегодня</button>
  <button data-filter="tomorrow" class="date-btn px-3 py-2 bg-gray-200 rounded text-sm">Завтра</button>
  <button data-filter="upcoming" class="date-btn px-3 py-2 bg-gray-200 rounded text-sm">Ближайшие</button>
  <button data-filter="completed" class="date-btn px-3 py-2 bg-gray-200 rounded text-sm">Завершенные</button>
</div>

<div id="ordersCards" class="space-y-3">
    <?php foreach ($orders as $o): ?>
      <?php
        $bg = in_array($o['status'], ['new','processing'], true) ? 'bg-gray-200' : '';
        $dateAttr = $o['delivery_date'] ? date('Y-m-d', strtotime($o['delivery_date'])) : '';
        $createdAttr = date('Y-m-d H:i', strtotime($o['created_at']));
        $deliveryAttr = $o['delivery_date'] ? date('Y-m-d', strtotime($o['delivery_date'])) : '';
        $slotAttr = $o['slot_id'] ?? '';
      ?>
      <?php $wa = normalize_phone($o['phone']); ?>
      <div class="order-card block bg-white p-2 sm:p-4 rounded shadow hover:bg-gray-50 <?= $bg ?>" data-status="<?= $o['status'] ?>" data-date="<?= $dateAttr ?>" data-created="<?= $createdAttr ?>" data-id="<?= $o['id'] ?>" data-delivery="<?= $deliveryAttr ?>" data-slot="<?= $slotAttr ?>">
        <div class="flex justify-between items-center">
          <a href="<?= $base ?>/orders/<?= $o['id'] ?>" class="flex flex-col font-bold<?php if($isStaff): ?> text-white decoration-white<?php endif; ?>">
            #<?= $o['id'] ?> <?php if ($o['delivery_date']): ?> | <?= date('d.m', strtotime($o['delivery_date'])) ?> <?= htmlspecialchars(format_time_range($o['slot_from'], $o['slot_to'])) ?><?php endif; ?>
          </a>
          <span class="inline-flex items-center px-2 py-0.5 rounded-full text-sm font-medium <?= order_status_info($o['status'])['badge'] ?>">
            <?= order_status_info($o['status'])['label'] ?>
          </span>
        </div>
        <div class="text-sm text-gray-600 mt-1">
          <?= htmlspecialchars($o['client_name']) ?>,
          <a href="https://wa.me/<?= $wa ?>" class="<?php if($isStaff): ?>text-green-600 underline hover:text-green-700<?php else: ?>hover:underline<?php endif; ?>" target="_blank"><?= htmlspecialchars($o['phone']) ?></a>,
          <a href="#" class="copy-address hover:underline" data-address="<?= htmlspecialchars($o['address'], ENT_QUOTES) ?>"><?= htmlspecialchars($o['address']) ?></a>
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
        <?php if (!empty($o['comment'])): ?>
          <div class="text-sm text-gray-700 mt-1"><?= nl2br(htmlspecialchars($o['comment'])) ?></div>
        <?php endif; ?>
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
        <div class="flex justify-between font-semibold border-t pt-1 mt-1">
          <span>Стоимость заказа:</span>
          <span><?= number_format($o['total_amount'], 0, '.', ' ') ?> ₽</span>
        </div>
      </div>
    <?php endforeach; ?>
</div>

<?php if (($totalPages ?? 1) > 1): ?>
  <?php
    $page = $page ?? 1;
    $totalPages = $totalPages ?? 1;
    $managerParam = !empty($selectedManager) ? (int)$selectedManager : null;
    $queryBase = $managerParam ? ['manager' => $managerParam] : [];
  ?>
  <div class="mt-4 flex flex-wrap items-center gap-2">
    <?php
      $prevPage = max(1, $page - 1);
      $nextPage = min($totalPages, $page + 1);
      $prevQuery = http_build_query(array_merge($queryBase, ['page' => $prevPage]));
      $nextQuery = http_build_query(array_merge($queryBase, ['page' => $nextPage]));
    ?>
    <a class="px-3 py-1 rounded border text-sm <?= $page <= 1 ? 'opacity-50 pointer-events-none' : '' ?>" href="?<?= $prevQuery ?>">Назад</a>
    <span class="text-sm text-gray-600">Стр. <?= $page ?> из <?= $totalPages ?></span>
    <a class="px-3 py-1 rounded border text-sm <?= $page >= $totalPages ? 'opacity-50 pointer-events-none' : '' ?>" href="?<?= $nextQuery ?>">Вперёд</a>
  </div>
<?php endif; ?>

<script>
  document.addEventListener('DOMContentLoaded', function () {
    const statusFilter = document.getElementById('statusFilter');
    const dateButtons = document.querySelectorAll('.date-btn');
    let dateFilter = '';
    const managerFilter = document.getElementById('managerFilter');
    const isManager = <?= $isStaff ? 'true' : 'false' ?>;
    let rows = document.querySelectorAll('#ordersCards .order-card');

    function applyFilters() {
      const s = statusFilter.value;
      rows.forEach(row => {
        const st = row.dataset.status;
        const d = row.dataset.delivery;
        
        let visible = true;
        if (s && st !== s) visible = false;
        if (dateFilter === 'today') {
          const today = new Date().toISOString().slice(0,10);
          if (!d || d !== today) visible = false;
        } else if (dateFilter === 'tomorrow') {
          const t = new Date();
          t.setDate(t.getDate() + 1);
          const tomorrow = t.toISOString().slice(0,10);
          if (!d || d !== tomorrow) visible = false;
        } else if (dateFilter === 'upcoming') {
          if (!['new','processing','assigned'].includes(st)) visible = false;
        } else if (dateFilter === 'completed') {
          if (st !== 'delivered') {
            visible = false;
          } else {
            const end = new Date();
            const start = new Date();
            start.setDate(end.getDate() - 6);
            const dt = d ? new Date(d) : null;
            if (!dt || dt < start || dt > end) visible = false;
          }
        }
        row.style.display = visible ? '' : 'none';
      });
    }

    statusFilter.addEventListener('change', applyFilters);
    dateButtons.forEach(btn => {
      btn.addEventListener('click', () => {
        dateFilter = btn.dataset.filter;
        dateButtons.forEach(b => b.classList.toggle('bg-[#C86052]', b === btn));
        applyFilters();
      });
    });

    managerFilter?.addEventListener('change', () => {
      const val = managerFilter.value;
      const params = new URLSearchParams(window.location.search);
      if (val) { params.set('manager', val); } else { params.delete('manager'); }
      window.location.search = params.toString();
    });

    document.querySelectorAll('.copy-address').forEach(el => {
      el.addEventListener('click', e => {
        e.preventDefault();
        const addr = el.dataset.address;
        navigator.clipboard.writeText(addr);
      });
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
      if (!tbody) return;
      const arr = Array.from(tbody.querySelectorAll('tr'));
      arr.sort((a, b) => {
        let av = a.dataset[field];
        let bv = b.dataset[field];
        if (field === 'id') {
          av = parseInt(av, 10);
          bv = parseInt(bv, 10);
        } else if (field === 'slot') {
          av = parseInt(av || 0, 10);
          bv = parseInt(bv || 0, 10);
        } else {
          av = new Date(av || 0);
          bv = new Date(bv || 0);
        }
        return dir === 'asc' ? av - bv : bv - av;
      });
      arr.forEach(r => tbody.appendChild(r));
      rows = document.querySelectorAll('#ordersCards .order-card');
    }
  });
</script>
