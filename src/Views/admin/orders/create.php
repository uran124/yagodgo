<?php /** @var array $purchaseBatches @var array $slots @var string $today */ ?>
<?php
$role = $_SESSION['role'] ?? '';
$base = $role === 'manager' ? '/manager' : ($role === 'partner' ? '/partner' : '/admin');
$batches = $purchaseBatches ?? [];
?>

<?php if (!empty($_GET['error'])): ?>
  <div class="mb-4 rounded-2xl border border-red-400/40 bg-red-950/40 p-4 text-xs text-red-100">
    Ошибка оформления: <?= htmlspecialchars($_GET['error']) ?>
  </div>
<?php endif; ?>

<form action="<?= $base ?>/orders/create" method="post" class="order-create-form space-y-4 pb-24" id="orderForm">
  <input type="hidden" name="stock_mode" id="stockMode" value="instant">

  <div class="rounded-2xl bg-slate-800/70 p-2 ring-1 ring-slate-700" aria-label="Прогресс оформления заказа">
    <div class="mb-1.5 grid grid-cols-6 gap-1 text-center text-[8px] font-semibold uppercase tracking-tight text-slate-400 sm:text-[10px]">
      <span>Кл.</span><span>Реж.</span><span>Зак.</span><span>Тов.</span><span>Дата</span><span>Итог</span>
    </div>
    <div id="orderProgressSegments" class="grid grid-cols-6 gap-1">
      <?php for ($i = 0; $i < 6; $i++): ?>
        <span class="progress-segment h-1.5 rounded-full <?= $i === 0 ? 'bg-[#F04483]' : 'bg-slate-700' ?>"></span>
      <?php endfor; ?>
    </div>
  </div>

  <section id="step1" class="rounded-2xl bg-slate-800/90 p-3 text-slate-100 shadow-sm ring-1 ring-slate-700">
    <div class="mb-3 flex items-center justify-between gap-3">
      <div>
        <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Шаг 1</p>
        <h2 class="text-base font-semibold">Клиент</h2>
      </div>
      <span class="rounded-full bg-slate-900/80 px-3 py-1 text-xs text-slate-300">обязателен</span>
    </div>

    <div id="existBlock" class="space-y-3">
      <div class="relative">
        <label class="mb-1 block text-xs font-medium text-slate-200">Телефон клиента</label>
        <input type="text" id="searchPhone" placeholder="7XXXXXXXXXX" inputmode="tel" class="w-full rounded-xl border border-slate-600 bg-slate-950 px-3 py-3 text-sm text-white placeholder:text-slate-500" value="" autocomplete="off">
        <input type="hidden" name="user_id" id="userId">
        <ul id="suggestions" class="absolute z-20 mt-1 hidden max-h-64 w-full overflow-y-auto rounded-xl border border-slate-600 bg-slate-900 text-slate-100 shadow-lg"></ul>
      </div>

      <div id="addressWrapper" class="hidden space-y-2">
        <label class="block text-xs font-medium text-slate-200">Адрес / самовывоз</label>
        <select name="address_id" id="addressSelect" class="w-full rounded-xl border border-slate-600 bg-slate-950 px-3 py-3 text-white placeholder:text-slate-500"></select>
        <input type="text" name="address_new" id="addressNew" placeholder="Новый адрес" class="hidden w-full rounded-xl border border-slate-600 bg-slate-950 px-3 py-3 text-white placeholder:text-slate-500">
      </div>
      <div id="userInfo" class="hidden rounded-xl bg-slate-900/70 p-3 text-xs text-slate-200"></div>
    </div>

    <div id="newBlock" class="mt-3 hidden space-y-3 rounded-xl border border-dashed border-slate-600 p-3">
      <div class="text-xs font-semibold text-slate-200">Новый клиент</div>
      <input type="text" name="new_name" placeholder="Имя" class="w-full rounded-xl border border-slate-600 bg-slate-950 px-3 py-3 text-white placeholder:text-slate-500">
      <input type="hidden" name="new_phone" id="newPhoneHidden">
      <input type="password" name="new_pin" placeholder="PIN, 4 цифры" maxlength="4" inputmode="numeric" class="w-full rounded-xl border border-slate-600 bg-slate-950 px-3 py-3 text-white placeholder:text-slate-500">
      <input type="text" name="new_address" placeholder="Адрес, пусто = самовывоз" class="w-full rounded-xl border border-slate-600 bg-slate-950 px-3 py-3 text-white placeholder:text-slate-500">
    </div>

    <div class="order-step-actions fixed inset-x-0 bottom-0 z-50 grid grid-cols-2 gap-2 border-t border-slate-700 bg-slate-800/95 p-2 backdrop-blur sm:static sm:mx-0 sm:mt-4 sm:border-0 sm:bg-transparent sm:p-0"><a href="<?= $base ?>/orders" class="rounded-xl border border-slate-600 bg-slate-900 px-4 py-3 font-semibold text-slate-100 text-center">Назад</a><button type="button" data-next="step2" class="next-step rounded-xl bg-[#F04483] px-4 py-3 font-semibold text-white shadow-sm shadow-pink-950/30">Далее</button></div>
  </section>

  <section id="step2" class="hidden rounded-2xl bg-slate-800/90 p-3 text-slate-100 shadow-sm ring-1 ring-slate-700">
    <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Шаг 2</p>
    <h2 class="mb-3 text-base font-semibold">Режим продажи</h2>
    <div class="grid grid-cols-2 gap-2">
      <button type="button" class="mode-card rounded-2xl border-2 border-emerald-300 bg-transparent bg-white/10 p-3 text-center text-xs font-semibold text-emerald-300 ring-2 ring-[#F04483] transition hover:bg-white/10" data-mode="instant" data-group="in_stock">В наличии</button>
      <button type="button" class="mode-card rounded-2xl border-2 border-amber-300 bg-transparent p-3 text-center text-xs font-semibold text-amber-300 transition hover:bg-white/10" data-mode="preorder" data-group="preorder">Предзаказ -10%</button>
    </div>
    <div class="order-step-actions fixed inset-x-0 bottom-0 z-50 grid grid-cols-2 gap-2 border-t border-slate-700 bg-slate-800/95 p-2 backdrop-blur sm:static sm:mx-0 sm:mt-4 sm:border-0 sm:bg-transparent sm:p-0"><button type="button" data-prev="step1" class="back-step rounded-xl border border-slate-600 bg-slate-900 px-4 py-3 font-semibold text-slate-100">Назад</button><button type="button" data-next="step3" class="next-step rounded-xl bg-[#F04483] px-4 py-3 font-semibold text-white shadow-sm shadow-pink-950/30">Далее</button></div>
  </section>

  <section id="step3" class="hidden rounded-2xl bg-slate-800/90 p-3 text-slate-100 shadow-sm ring-1 ring-slate-700">
    <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Шаг 3</p>
    <h2 class="mb-3 text-base font-semibold">Закупка</h2>
    <div id="batchList" class="space-y-3"></div>
    <div class="order-step-actions fixed inset-x-0 bottom-0 z-50 grid grid-cols-2 gap-2 border-t border-slate-700 bg-slate-800/95 p-2 backdrop-blur sm:static sm:mx-0 sm:mt-4 sm:border-0 sm:bg-transparent sm:p-0"><button type="button" data-prev="step2" class="back-step rounded-xl border border-slate-600 bg-slate-900 px-4 py-3 font-semibold text-slate-100">Назад</button><button type="button" data-next="step4" class="next-step rounded-xl bg-[#F04483] px-4 py-3 font-semibold text-white shadow-sm shadow-pink-950/30">Далее</button></div>
  </section>

  <section id="step4" class="hidden rounded-2xl bg-slate-800/90 p-3 text-slate-100 shadow-sm ring-1 ring-slate-700">
    <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Шаг 4</p>
    <h2 class="mb-3 text-base font-semibold">Товары закупки</h2>
    <div id="productsList" class="space-y-3"></div>
    <div class="order-step-actions fixed inset-x-0 bottom-0 z-50 grid grid-cols-2 gap-2 border-t border-slate-700 bg-slate-800/95 p-2 backdrop-blur sm:static sm:mx-0 sm:mt-4 sm:border-0 sm:bg-transparent sm:p-0"><button type="button" data-prev="step3" class="back-step rounded-xl border border-slate-600 bg-slate-900 px-4 py-3 font-semibold text-slate-100">Назад</button><button type="button" data-next="step5" class="next-step rounded-xl bg-[#F04483] px-4 py-3 font-semibold text-white shadow-sm shadow-pink-950/30">Далее</button></div>
  </section>

  <section id="step5" class="hidden rounded-2xl bg-slate-800/90 p-3 text-slate-100 shadow-sm ring-1 ring-slate-700">
    <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Шаг 5</p>
    <h2 class="mb-3 text-base font-semibold">Дата и интервал</h2>
    <div class="rounded-xl border border-blue-400/30 bg-blue-950/40 p-3 text-xs text-blue-100">Один заказ создается только на одну дату получения. Для другой даты оформите отдельный заказ.</div>
    <div class="mt-3 grid grid-cols-3 gap-2" id="dateOptions"></div>
    <input type="hidden" id="deliveryDate" name="delivery_date" value="<?= htmlspecialchars($today) ?>">
    <label class="mt-3 block text-xs font-medium text-slate-200">Интервал</label>
    <select name="slot_id" class="mt-1 w-full rounded-xl border border-slate-600 bg-slate-950 px-3 py-3 text-white">
      <?php foreach ($slots as $i => $s): ?>
        <option value="<?= $s['id'] ?>" <?= $i === 0 ? 'selected' : '' ?>>
          <?= htmlspecialchars(format_time_range($s['time_from'], $s['time_to'])) ?>
        </option>
      <?php endforeach; ?>
    </select>
    <div class="order-step-actions fixed inset-x-0 bottom-0 z-50 grid grid-cols-2 gap-2 border-t border-slate-700 bg-slate-800/95 p-2 backdrop-blur sm:static sm:mx-0 sm:mt-4 sm:border-0 sm:bg-transparent sm:p-0"><button type="button" data-prev="step4" class="back-step rounded-xl border border-slate-600 bg-slate-900 px-4 py-3 font-semibold text-slate-100">Назад</button><button type="button" data-next="step6" class="next-step rounded-xl bg-[#F04483] px-4 py-3 font-semibold text-white shadow-sm shadow-pink-950/30">Далее</button></div>
  </section>

  <section id="step6" class="hidden rounded-2xl bg-slate-800/90 p-3 text-slate-100 shadow-sm ring-1 ring-slate-700">
    <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Шаг 6</p>
    <h2 class="mb-3 text-base font-semibold">Проверка заказа</h2>
    <div id="itemsList" class="space-y-2 text-xs"></div>
    <div class="mt-3 space-y-2 rounded-xl bg-slate-900/70 p-3 text-xs text-slate-100">
      <div class="flex justify-between"><span>Товары:</span><span id="sumSubtotal">0 ₽</span></div>
      <div id="rowReferral" class="hidden justify-between"><span>Скидка -10%:</span><span id="sumReferral">0 ₽</span></div>
      <div id="rowPoints" class="hidden justify-between"><span>Баллы:</span><span id="sumPoints">0 ₽</span></div>
      <div id="rowShipping" class="flex justify-between"><span>Доставка:</span><span id="sumShipping">300 ₽</span></div>
      <div class="flex justify-between border-t pt-2 text-base font-semibold"><span>Итого:</span><span id="sumTotal">0 ₽</span></div>
    </div>

    <div class="mt-3 space-y-3">
      <label class="block text-xs font-medium text-slate-200">Дополнительный промокод</label>
      <input type="text" name="coupon_code" value="" placeholder="Если есть" class="w-full rounded-xl border border-slate-600 bg-slate-950 px-3 py-3 text-white placeholder:text-slate-500">
      <div id="referralToggleWrap" class="hidden rounded-xl border border-pink-400/30 bg-pink-950/30 p-3">
        <label class="flex items-center justify-between gap-3 text-xs font-medium text-pink-100">
          <span>Списать 10% за первый заказ</span>
          <input type="hidden" name="has_used_referral_coupon" value="0">
          <input type="checkbox" id="referralToggle" name="has_used_referral_coupon" value="1" class="h-5 w-5">
        </label>
      </div>
      <div id="pointsBlock" class="hidden rounded-xl border border-slate-600 bg-slate-900/70 p-3">
        <label class="flex items-center justify-between gap-3 text-xs font-medium">
          <span>Списать баллы</span>
          <input type="checkbox" name="use_points" id="usePointsToggle" value="1" class="h-5 w-5">
        </label>
        <input type="number" name="points" id="pointsInput" min="0" value="0" class="mt-2 w-full rounded-xl border border-slate-600 bg-slate-950 px-3 py-2 text-white">
      </div>
    </div>

    <div class="order-step-actions fixed inset-x-0 bottom-0 z-50 grid grid-cols-2 gap-2 border-t border-slate-700 bg-slate-800/95 p-2 backdrop-blur sm:static sm:mx-0 sm:mt-4 sm:border-0 sm:bg-transparent sm:p-0"><button type="button" data-prev="step5" class="back-step rounded-xl border border-slate-600 bg-slate-900 px-4 py-3 font-semibold text-slate-100">Назад</button><button type="submit" class="rounded-xl bg-emerald-600 px-4 py-3 font-semibold text-white">Создать заказ</button></div>
  </section>
</form>

<?php if (!empty($debugData)): ?>
  <pre class="mt-4 overflow-auto rounded-xl bg-gray-900 p-3 text-xs text-white"><?= htmlspecialchars(json_encode($debugData, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)) ?></pre>
<?php endif; ?>

<script>
  const batches = <?= json_encode($batches, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK) ?>;
  const basePath = '<?= $base ?>';
  const today = '<?= htmlspecialchars($today) ?>';
  const state = { mode: 'instant', group: 'in_stock', batchKey: null, selectedClientCanReferral: true };
  const steps = ['step1','step2','step3','step4','step5','step6'];
  const batchList = document.getElementById('batchList');
  const productsList = document.getElementById('productsList');
  const dateOptions = document.getElementById('dateOptions');
  const deliveryDate = document.getElementById('deliveryDate');
  const stockMode = document.getElementById('stockMode');
  const itemsList = document.getElementById('itemsList');
  const subtotalEl = document.getElementById('sumSubtotal');
  const totalEl = document.getElementById('sumTotal');
  const shippingEl = document.getElementById('sumShipping');
  const shippingRow = document.getElementById('rowShipping');
  const referralToggleWrap = document.getElementById('referralToggleWrap');
  const referralToggle = document.getElementById('referralToggle');
  const refRow = document.getElementById('rowReferral');
  const refEl = document.getElementById('sumReferral');
  const pointsBlock = document.getElementById('pointsBlock');
  const usePointsToggle = document.getElementById('usePointsToggle');
  const pointsInput = document.getElementById('pointsInput');
  const pointsRow = document.getElementById('rowPoints');
  const pointsEl = document.getElementById('sumPoints');

  function showStep(stepId) {
    steps.forEach(id => document.getElementById(id).classList.toggle('hidden', id !== stepId));
    const index = Math.max(0, steps.indexOf(stepId));
    document.querySelectorAll('.progress-segment').forEach((segment, i) => {
      segment.classList.toggle('bg-[#F04483]', i <= index);
      segment.classList.toggle('bg-slate-700', i > index);
    });
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }

  document.querySelectorAll('.next-step').forEach(btn => btn.addEventListener('click', () => {
    let nextStep = btn.dataset.next;
    if (nextStep === 'step3') {
      const grouped = groupedBatches();
      if (grouped.size === 1) {
        state.batchKey = grouped.keys().next().value;
        renderProducts();
        nextStep = 'step4';
      } else {
        renderBatches();
      }
    }
    if (nextStep === 'step4') renderProducts();
    if (nextStep === 'step5') renderDates();
    if (nextStep === 'step6') {
      prepareReferralToggle();
      updateSummary();
    }
    showStep(nextStep);
  }));

  document.querySelectorAll('.back-step').forEach(btn => btn.addEventListener('click', () => {
    let prevStep = btn.dataset.prev;
    if (prevStep === 'step3' && groupedBatches().size === 1) {
      prevStep = 'step2';
    }
    showStep(prevStep);
  }));

  document.querySelectorAll('.mode-card').forEach(btn => btn.addEventListener('click', () => {
    state.mode = btn.dataset.mode;
    state.group = btn.dataset.group;
    state.batchKey = null;
    stockMode.value = state.mode;
    document.querySelectorAll('.mode-card').forEach(card => card.classList.remove('bg-white/10', 'ring-2', 'ring-[#F04483]'));
    btn.classList.add('bg-white/10', 'ring-2', 'ring-[#F04483]');
  }));

  function groupedBatches() {
    const grouped = new Map();
    batches.filter(b => b.mode_group === state.group).forEach(b => {
      const key = [b.batch_date, b.status].join('|');
      if (!grouped.has(key)) grouped.set(key, []);
      grouped.get(key).push(b);
    });
    return grouped;
  }

  function statusLabel(status) {
    return { planned: 'Запланирована', purchased: 'Выкуплена', arrived: 'Готова к выдаче / В наличии' }[status] || status;
  }

  function renderBatches() {
    batchList.innerHTML = '';
    const grouped = groupedBatches();
    if (grouped.size === 0) {
      batchList.innerHTML = '<div class="rounded-xl border border-slate-600 bg-slate-900/70 p-4 text-xs text-slate-300">Нет доступных закупок для выбранного режима.</div>';
      return;
    }
    grouped.forEach((items, key) => {
      const [date, status] = key.split('|');
      const totalBoxes = items.reduce((sum, b) => sum + Number(b.available_boxes || 0), 0);
      const button = document.createElement('button');
      button.type = 'button';
      button.className = 'batch-card w-full rounded-2xl border-2 p-4 text-left text-slate-100 ' + (state.mode === 'preorder' ? 'border-amber-500/40 bg-amber-950/30' : 'border-emerald-500/40 bg-emerald-950/30');
      button.dataset.key = key;
      const displayDate = date || 'дата уточняется';
      button.innerHTML = '<div class="flex items-start justify-between gap-3"><div><div class="font-semibold">Закупка ' + displayDate + '</div><div class="text-xs text-slate-300">' + statusLabel(status) + '</div></div><div class="rounded-full bg-slate-950 px-3 py-1 text-xs font-semibold text-slate-100">' + totalBoxes + ' ящ.</div></div>';
      button.addEventListener('click', () => {
        state.batchKey = key;
        document.querySelectorAll('.batch-card').forEach(card => card.classList.remove('ring-2', 'ring-[#F04483]'));
        button.classList.add('ring-2', 'ring-[#F04483]');
      });
      batchList.appendChild(button);
    });
  }

  function selectedBatchItems() {
    if (!state.batchKey) return [];
    return batches.filter(b => b.mode_group === state.group && [b.batch_date, b.status].join('|') === state.batchKey);
  }

  function productName(b) {
    return b.product + (b.variety ? ' ' + b.variety : '');
  }

  function escapeHtml(value) {
    return String(value).replace(/[&<>"']/g, char => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[char]));
  }

  function renderProducts() {
    productsList.innerHTML = '';
    const selected = selectedBatchItems();
    if (selected.length === 0) {
      productsList.innerHTML = '<div class="rounded-xl border border-red-400/30 bg-red-950/40 p-4 text-xs text-red-100">Сначала выберите закупку.</div>';
      return;
    }
    selected.forEach(b => {
      const card = document.createElement('div');
      card.className = 'rounded-2xl border border-slate-600 bg-slate-900/70 p-3 text-slate-100 shadow-sm';
      const safeName = escapeHtml(productName(b));
      const safeImage = b.image_path ? escapeHtml(b.image_path) : '';
      card.innerHTML = `
        <div class="flex gap-3">
          ${safeImage ? `<img src="${safeImage}" class="h-16 w-16 rounded-xl object-cover" alt="">` : ''}
          <div class="min-w-0 flex-1">
            <div class="font-semibold">${safeName}</div>
            <div class="text-xs text-slate-300">Свободно: <b>${b.available_boxes}</b> ящиков</div>
            <div class="text-xs text-slate-300">Цена: <b>${Number(b.price_per_box).toFixed(0)} ₽/ящик</b></div>
            <div class="text-xs text-slate-400">purchase_batch_id: ${b.purchase_batch_id}</div>
          </div>
        </div>
        <div class="mt-3 flex items-center justify-between gap-2">
          <button type="button" class="dec rounded-xl bg-slate-700 px-4 py-3 text-lg text-white" data-target="batch${b.purchase_batch_id}">−</button>
          <input id="batch${b.purchase_batch_id}" name="batch_items[${b.purchase_batch_id}]" type="number" min="0" max="${b.available_boxes}" step="1" value="0" data-price="${b.price_per_box}" data-name="${safeName}" class="qty w-24 rounded-xl border border-slate-600 bg-slate-950 px-3 py-3 text-center text-lg text-white">
          <button type="button" class="inc rounded-xl bg-slate-700 px-4 py-3 text-lg text-white" data-target="batch${b.purchase_batch_id}">+</button>
        </div>`;
      productsList.appendChild(card);
    });
    wireQtyButtons();
  }

  function wireQtyButtons() {
    document.querySelectorAll('.dec').forEach(btn => btn.addEventListener('click', () => {
      const input = document.getElementById(btn.dataset.target);
      input.stepDown();
      updateSummary();
    }));
    document.querySelectorAll('.inc').forEach(btn => btn.addEventListener('click', () => {
      const input = document.getElementById(btn.dataset.target);
      const max = Number(input.max || 0);
      if (Number(input.value || 0) < max) input.stepUp();
      updateSummary();
    }));
    document.querySelectorAll('.qty').forEach(input => input.addEventListener('input', updateSummary));
  }

  function addDays(isoDate, days) {
    const d = new Date(isoDate + 'T00:00:00');
    d.setDate(d.getDate() + days);
    return d.toISOString().slice(0, 10);
  }

  function formatDateShort(isoDate) {
    const [year, month, day] = isoDate.split('-');
    return day + '.' + month;
  }

  function renderDates() {
    const selected = selectedBatchItems();
    const baseDate = state.mode === 'preorder' && selected[0] && selected[0].batch_date ? selected[0].batch_date : today;
    dateOptions.innerHTML = '';
    for (let i = 0; i < 3; i++) {
      const value = addDays(baseDate, i);
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'date-option rounded-xl border px-2 py-3 text-xs font-semibold ' + (i === 0 ? 'border-[#F04483] bg-[#F04483] text-white' : 'border-slate-600 bg-slate-900 text-slate-100');
      btn.dataset.date = value;
      btn.textContent = formatDateShort(value);
      btn.addEventListener('click', () => {
        deliveryDate.value = value;
        document.querySelectorAll('.date-option').forEach(el => {
          el.classList.remove('border-[#F04483]', 'bg-[#F04483]', 'text-white');
          el.classList.add('border-slate-600', 'bg-slate-900', 'text-slate-100');
        });
        btn.classList.remove('border-slate-600', 'bg-slate-900', 'text-slate-100');
        btn.classList.add('border-[#F04483]', 'bg-[#F04483]', 'text-white');
      });
      dateOptions.appendChild(btn);
      if (i === 0) deliveryDate.value = value;
    }
  }

  function isPickup() {
    const newBlock = document.getElementById('newBlock');
    const addressSelect = document.getElementById('addressSelect');
    if (!newBlock.classList.contains('hidden')) {
      const input = document.querySelector('input[name="new_address"]');
      return input && input.value.trim() === '';
    }
    return addressSelect && addressSelect.value === 'pickup';
  }

  function prepareReferralToggle() {
    if (!referralToggleWrap) return;
    if (state.selectedClientCanReferral) {
      referralToggleWrap.classList.remove('hidden');
    } else {
      referralToggleWrap.classList.add('hidden');
      if (referralToggle) referralToggle.checked = false;
    }
  }

  function updateSummary() {
    let subtotal = 0;
    itemsList.innerHTML = '';
    document.querySelectorAll('.qty').forEach(input => {
      const qty = Number(input.value || 0);
      const max = Number(input.max || 0);
      if (qty > max) input.value = max;
      const safeQty = Number(input.value || 0);
      const price = Number(input.dataset.price || 0);
      if (safeQty > 0) {
        subtotal += safeQty * price;
        const row = document.createElement('div');
        row.className = 'flex justify-between rounded-xl bg-slate-900/70 p-2 text-slate-100';
        row.innerHTML = '<span>' + input.dataset.name + ' × ' + safeQty + ' ящ.</span><span>' + (safeQty * price).toFixed(0) + ' ₽</span>';
        itemsList.appendChild(row);
      }
    });
    subtotalEl.textContent = subtotal.toFixed(0) + ' ₽';

    let total = subtotal;
    const referral = referralToggle && !referralToggleWrap.classList.contains('hidden') && referralToggle.checked;
    if (referral) {
      const discount = Math.floor(subtotal * 0.1);
      refEl.textContent = '-' + discount + ' ₽';
      refRow.classList.remove('hidden');
      refRow.classList.add('flex');
      total -= discount;
    } else {
      refRow.classList.add('hidden');
      refRow.classList.remove('flex');
    }

    const usePoints = usePointsToggle && usePointsToggle.checked;
    const requestedPoints = Number(pointsInput ? pointsInput.value || 0 : 0);
    const points = usePoints ? Math.min(requestedPoints, total) : 0;
    if (points > 0) {
      pointsEl.textContent = '-' + points.toFixed(0) + ' ₽';
      pointsRow.classList.remove('hidden');
      pointsRow.classList.add('flex');
      total -= points;
    } else {
      pointsRow.classList.add('hidden');
      pointsRow.classList.remove('flex');
    }

    const shipping = isPickup() ? 0 : 300;
    shippingEl.textContent = shipping.toFixed(0) + ' ₽';
    shippingRow.classList.toggle('hidden', shipping === 0);
    total += shipping;
    totalEl.textContent = total.toFixed(0) + ' ₽';
  }

  const searchPhone = document.getElementById('searchPhone');
  const sugg = document.getElementById('suggestions');
  const userIdInput = document.getElementById('userId');
  const userInfo = document.getElementById('userInfo');
  const addressWrapper = document.getElementById('addressWrapper');
  const addressSelect = document.getElementById('addressSelect');
  const addressNew = document.getElementById('addressNew');
  const newBlock = document.getElementById('newBlock');
  const newPhoneHidden = document.getElementById('newPhoneHidden');
  searchPhone.value = '';

  searchPhone.addEventListener('input', () => {
    const raw = searchPhone.value.trim();
    const q = raw.replace(/\D+/g, '');
    userIdInput.value = '';
    addressWrapper.classList.add('hidden');
    userInfo.classList.add('hidden');
    pointsBlock.classList.add('hidden');
    state.selectedClientCanReferral = true;
    if (q.length === 0) {
      sugg.classList.add('hidden');
      newBlock.classList.add('hidden');
      return;
    }

    fetch(basePath + '/users/search?term=' + encodeURIComponent(q))
      .then(r => r.json())
      .then(list => {
        sugg.innerHTML = '';
        if (list.length === 0) {
          createNewClient(q);
          return;
        }

        newBlock.classList.add('hidden');
        list.forEach(u => {
          const li = document.createElement('li');
          li.className = 'cursor-pointer px-3 py-2 text-slate-100 hover:bg-slate-800';
          li.textContent = (u.name || 'Клиент') + ' — ' + u.phone;
          li.addEventListener('click', () => selectUser(u));
          sugg.appendChild(li);
        });
        sugg.classList.remove('hidden');
      });
  });

  function selectUser(u) {
    searchPhone.value = u.phone;
    userIdInput.value = u.id;
    newBlock.classList.add('hidden');
    userInfo.textContent = (u.name || 'Клиент') + ', баланс: ' + (u.points_balance || 0) + ' баллов';
    userInfo.classList.remove('hidden');
    pointsBlock.classList.remove('hidden');
    state.selectedClientCanReferral = Number(u.has_used_referral_coupon || 0) === 0;
    prepareReferralToggle();
    sugg.classList.add('hidden');
    loadAddresses(u.id);
  }

  function createNewClient(phone) {
    userIdInput.value = '';
    newPhoneHidden.value = phone;
    newBlock.classList.remove('hidden');
    addressWrapper.classList.add('hidden');
    pointsBlock.classList.add('hidden');
    userInfo.classList.add('hidden');
    state.selectedClientCanReferral = true;
    prepareReferralToggle();
    sugg.classList.add('hidden');
    updateSummary();
  }

  function loadAddresses(uid) {
    fetch(basePath + '/users/addresses?user_id=' + uid)
      .then(r => r.json())
      .then(list => {
        addressSelect.innerHTML = '';
        list.forEach(a => {
          const opt = document.createElement('option');
          opt.value = a.id;
          opt.textContent = a.street;
          addressSelect.appendChild(opt);
        });
        const optNew = document.createElement('option');
        optNew.value = 'new';
        optNew.textContent = 'Добавить новый адрес';
        addressSelect.appendChild(optNew);
        const optPickup = document.createElement('option');
        optPickup.value = 'pickup';
        optPickup.textContent = 'Самовывоз 9 мая 73';
        addressSelect.appendChild(optPickup);
        addressWrapper.classList.remove('hidden');
        updateSummary();
      });
  }

  addressSelect.addEventListener('change', () => {
    addressNew.classList.toggle('hidden', addressSelect.value !== 'new');
    updateSummary();
  });
  document.querySelector('input[name="new_address"]').addEventListener('input', updateSummary);
  if (referralToggle) referralToggle.addEventListener('change', updateSummary);
  if (usePointsToggle) usePointsToggle.addEventListener('change', updateSummary);
  if (pointsInput) pointsInput.addEventListener('input', updateSummary);
</script>
