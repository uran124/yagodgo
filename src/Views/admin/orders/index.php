<?php /** @var array $orders */ ?>
<?php $role = $_SESSION['role'] ?? ''; $isManager = ($role === 'manager'); $isStaff = in_array($role, ['admin','manager','partner'], true); $base = $role === 'manager' ? '/manager' : ($role === 'partner' ? '/partner' : '/admin'); ?>
<?php $managers = $managers ?? []; $selectedManager = $selectedManager ?? 0; $slots = $slots ?? []; ?>
<style>
  /* mobile-first compact layout */
  .orders-filter {
    margin-bottom: 0.75rem;
    gap: 0.35rem;
  }
  .orders-filter a,
  .orders-filter select,
  .orders-filter button,
  .orders-filter input,
  .date-filter button {
    min-height: 2rem;
    padding: 0.3rem 0.42rem;
    font-size: 0.74rem;
    line-height: 1.1;
    border-radius: 0.375rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    box-sizing: border-box;
  }
  .date-filter {
    margin-bottom: 0.75rem;
    gap: 0.35rem;
  }
  #ordersCards {
    gap: 0.2rem;
  }
  .order-card {
    padding: 0.2rem 0.3rem !important;
    border-radius: 0.5rem;
  }
  .order-card .font-bold {
    font-size: 0.74rem;
    line-height: 1.15;
  }
  .order-card .text-sm {
    font-size: 0.75rem !important;
    line-height: 1.1;
  }
  .order-card .font-semibold {
    font-size: 0.74rem;
    line-height: 1.1;
  }
  .order-card .mt-2 {
    margin-top: 0.12rem;
  }
  .order-card .mt-1 {
    margin-top: 0.1rem;
  }
  .order-card .pt-1 {
    padding-top: 0.1rem;
  }
  .order-card .py-0\.5 {
    padding-top: 0.05rem;
    padding-bottom: 0.05rem;
  }
  .order-card .px-2 {
    padding-left: 0.2rem;
    padding-right: 0.2rem;
  }

  @media (min-width: 641px) {
    .orders-filter {
      margin-bottom: 0.75rem;
      gap: 0.45rem;
    }
    .orders-filter a,
    .orders-filter select,
    .orders-filter button,
    .orders-filter input,
    .date-filter button {
      min-height: 2.15rem;
      padding: 0.42rem 0.62rem;
      font-size: 0.82rem;
      line-height: 1.15;
    }
    .date-filter {
      margin-bottom: 0.75rem;
      gap: 0.45rem;
    }
    .order-card {
      padding: 0.35rem 0.5rem !important;
      border-radius: 0.5rem;
    }
    .order-card .font-bold {
      font-size: 0.82rem;
    }
    .order-card .text-sm {
      font-size: 0.875rem !important;
    }
    .order-card .font-semibold {
      font-size: 0.8rem;
    }
  }
</style>
<?php if (!empty($_GET['msg'])): ?>
  <div class="mb-4 p-3 rounded bg-green-50 text-green-800 border border-green-200">
    <?= htmlspecialchars($_GET['msg']) ?>
  </div>
<?php endif; ?>
<div class="orders-filter mb-3 flex flex-row flex-nowrap items-center gap-2 overflow-x-auto">
  <a href="<?= $base ?>/orders/create" class="px-2 py-1 bg-[#C86052] text-white rounded text-xs md:text-sm whitespace-nowrap shrink-0">Создать новый</a>
  <select id="statusFilter" class="border rounded px-2 py-1 text-sm shrink-0">
    <option value="">Все статусы</option>
    <option value="new">Новые</option>
    <option value="processing">Принятые</option>
    <option value="assigned">В работе</option>
    <option value="delivered">Выполненные</option>
    <option value="cancelled">Отмененные</option>
    <option value="reserved">Бронь</option>
  </select>
  <?php if ($role === 'manager' || !empty($managers)): ?>
    <select id="managerFilter" class="border rounded px-2 py-1 text-sm shrink-0">
      <option value="">Все менеджеры</option>
      <?php foreach ($managers as $m): ?>
        <option value="<?= $m['id'] ?>" <?= $selectedManager == $m['id'] ? 'selected' : '' ?>><?= htmlspecialchars($m['name']) ?></option>
      <?php endforeach; ?>
    </select>
  <?php endif; ?>
</div>
<div class="date-filter mb-3 flex flex-row flex-wrap items-center gap-2">
  <button data-filter="active" class="date-btn px-3 py-2 bg-[#C86052] text-white rounded text-sm">Активные</button>
  <button data-filter="today" class="date-btn px-3 py-2 bg-gray-200 rounded text-sm">Сегодня</button>
  <button data-filter="tomorrow" class="date-btn px-3 py-2 bg-gray-200 rounded text-sm">Завтра</button>
  <button data-filter="completed" class="date-btn px-3 py-2 bg-gray-200 rounded text-sm">Завершенные</button>
  <button data-filter="all" class="date-btn ml-auto px-3 py-2 bg-[#C86052] text-white rounded text-sm">Все</button>
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
          <a href="<?= $base ?>/orders/<?= $o['id'] ?>" class="block text-sm font-bold<?php if($isStaff): ?> text-white decoration-white<?php endif; ?>">
            #<?= $o['id'] ?> <?php if ($o['delivery_date']): ?> | <?= date('d.m', strtotime($o['delivery_date'])) ?> <?= htmlspecialchars(format_time_range($o['slot_from'], $o['slot_to'])) ?><?php endif; ?><?php
              $hasCreator = !empty($o['created_by_user_id']);
              $buyerId = isset($o['user_id']) ? (int)$o['user_id'] : 0;
              $creatorId = (int)($o['created_by_user_id'] ?? 0);
              $isCreatorDifferent = !$buyerId || ($creatorId !== $buyerId);
            ?><?php if ($hasCreator && $isCreatorDifferent && !empty($o['author_name'])): ?> | <span class="font-normal"><?= htmlspecialchars($o['author_name']) ?></span><?php endif; ?>
          </a>
          <span class="inline-flex items-center px-2 py-0.5 rounded-full text-sm font-medium <?= order_status_info($o['status'])['badge'] ?>">
            <?= order_status_info($o['status'])['label'] ?>
          </span>
        </div>
        <div class="text-sm text-gray-600 mt-1">
          <?= htmlspecialchars($o['client_name']) ?>,
          <a href="tg://resolve?phone=<?= $wa ?>" class="<?php if($isStaff): ?>text-green-600 underline hover:text-green-700<?php else: ?>hover:underline<?php endif; ?>" target="_blank"><?= htmlspecialchars($o['phone']) ?></a>,
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
          <?php if (($o['status'] ?? '') === 'reserved' && (int)($o['total_amount'] ?? 0) <= 0): ?>
            <span>Цена уточняется</span>
          <?php else: ?>
            <span><?= number_format($o['total_amount'], 0, '.', ' ') ?> ₽</span>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
</div>

<div id="ordersLoadState" class="mt-1 text-center text-xs text-gray-500"></div>


<button id="scrollTopBtn" type="button" aria-label="Наверх" class="fixed right-4 bottom-5 z-40 w-10 h-10 rounded-full bg-[#C86052] text-white shadow-lg transition-all duration-300" style="opacity:0;pointer-events:none;transform:translateY(12px);">↑</button>

<script>
  document.addEventListener('DOMContentLoaded', function () {
    const statusFilter = document.getElementById('statusFilter');
    const dateButtons = document.querySelectorAll('.date-btn');
    let dateFilter = 'active';
    const managerFilter = document.getElementById('managerFilter');
    const isManager = <?= $isStaff ? 'true' : 'false' ?>;
    let rows = document.querySelectorAll('#ordersCards .order-card');
    const cardsWrap = document.getElementById('ordersCards');
    const loadState = document.getElementById('ordersLoadState');
    const totalPages = <?= (int)($totalPages ?? 1) ?>;
    let currentPage = <?= (int)($page ?? 1) ?>;
    let loading = false;

    async function loadNextPage() {
      if (loading || currentPage >= totalPages) return;
      loading = true;
      if (loadState) loadState.textContent = 'Загружаем заказы…';
      const params = new URLSearchParams(window.location.search);
      params.set('page', String(currentPage + 1));
      const res = await fetch(`${window.location.pathname}?${params.toString()}`, {headers: {'X-Requested-With': 'XMLHttpRequest'}});
      const html = await res.text();
      const doc = new DOMParser().parseFromString(html, 'text/html');
      const nextCards = doc.querySelectorAll('#ordersCards .order-card');
      nextCards.forEach(card => cardsWrap.appendChild(card));
      currentPage += 1;
      rows = document.querySelectorAll('#ordersCards .order-card');
      applyFilters();
      if (loadState) loadState.textContent = currentPage >= totalPages ? 'Все заказы загружены' : '';
      loading = false;
    }

    const observer = new IntersectionObserver((entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) loadNextPage();
      });
    }, {rootMargin: '300px'});
    if (loadState) observer.observe(loadState);

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
        } else if (dateFilter === 'active') {
          if (!['new','processing','assigned','reserved'].includes(st)) visible = false;
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
        } else if (dateFilter === 'all') {
          visible = true;
        }
        row.style.display = visible ? '' : 'none';
      });
    }

    statusFilter.addEventListener('change', applyFilters);
    dateButtons.forEach(btn => {
      btn.addEventListener('click', () => {
        dateFilter = btn.dataset.filter;
        dateButtons.forEach(b => {
          b.classList.toggle('bg-[#C86052]', b === btn);
          b.classList.toggle('text-white', b === btn);
          b.classList.toggle('bg-gray-200', b !== btn);
        });
        applyFilters();
      });
    });


    const scrollTopBtn = document.getElementById('scrollTopBtn');
    const scrollContainer = document.querySelector('main.overflow-auto') || document.querySelector('main') || window;
    const getScrollTop = () => scrollContainer === window ? window.scrollY : scrollContainer.scrollTop;
    const scrollToTop = () => {
      if (scrollContainer === window) {
        window.scrollTo({ top: 0, behavior: 'smooth' });
      } else {
        scrollContainer.scrollTo({ top: 0, behavior: 'smooth' });
      }
    };
    const handleScrollTopVisibility = () => {
      const visible = getScrollTop() > 120;
      if (!scrollTopBtn) return;
      scrollTopBtn.style.opacity = visible ? '1' : '0';
      scrollTopBtn.style.pointerEvents = visible ? 'auto' : 'none';
      scrollTopBtn.style.transform = visible ? 'translateY(0)' : 'translateY(12px)';
    };
    scrollContainer.addEventListener('scroll', handleScrollTopVisibility, { passive: true });
    handleScrollTopVisibility();
    scrollTopBtn?.addEventListener('click', scrollToTop);

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

    applyFilters();

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
