<?php /** @var array $purchaseBatches @var array $slots @var string $today */ ?>
<?php
$role = $_SESSION['role'] ?? '';
$base = $role === 'manager' ? '/manager' : ($role === 'partner' ? '/partner' : '/admin');
$batches = $purchaseBatches ?? [];
?>

<?php if (!empty($_GET['error'])): ?>
  <div class="mb-4 rounded-2xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">
    Ошибка оформления: <?= htmlspecialchars($_GET['error']) ?>
  </div>
<?php endif; ?>

<div class="mb-4 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
  <div class="font-semibold">Новая логика BerryGo</div>
  <div>Продажа ведется ящиками из конкретной закупки. Цена, остаток и списание фиксируются по <code>purchase_batch_id</code>.</div>
</div>

<form action="<?= $base ?>/orders/create" method="post" class="space-y-4 pb-24" id="orderForm">
  <input type="hidden" name="stock_mode" id="stockMode" value="instant">

  <section id="step1" class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-gray-100">
    <div class="mb-3 flex items-center justify-between gap-3">
      <div>
        <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Шаг 1</p>
        <h2 class="text-lg font-semibold">Клиент</h2>
      </div>
      <span class="rounded-full bg-gray-100 px-3 py-1 text-xs text-gray-600">обязателен</span>
    </div>

    <div id="existBlock" class="space-y-3">
      <div class="relative">
        <label class="mb-1 block text-sm font-medium text-gray-700">Телефон клиента</label>
        <input type="text" id="searchPhone" placeholder="7XXXXXXXXXX" inputmode="tel" class="w-full rounded-xl border px-3 py-3 text-base">
        <input type="hidden" name="user_id" id="userId">
        <ul id="suggestions" class="absolute z-20 mt-1 hidden w-full rounded-xl border bg-white shadow-lg"></ul>
      </div>

      <div id="addressWrapper" class="hidden space-y-2">
        <label class="block text-sm font-medium text-gray-700">Адрес / самовывоз</label>
        <select name="address_id" id="addressSelect" class="w-full rounded-xl border px-3 py-3"></select>
        <input type="text" name="address_new" id="addressNew" placeholder="Новый адрес" class="hidden w-full rounded-xl border px-3 py-3">
      </div>
      <div id="userInfo" class="hidden rounded-xl bg-gray-50 p-3 text-sm text-gray-600"></div>
    </div>

    <div id="newBlock" class="mt-3 hidden space-y-3 rounded-xl border border-dashed border-gray-300 p-3">
      <div class="text-sm font-semibold text-gray-700">Новый клиент</div>
      <input type="text" name="new_name" placeholder="Имя" class="w-full rounded-xl border px-3 py-3">
      <input type="hidden" name="new_phone" id="newPhoneHidden">
      <input type="password" name="new_pin" placeholder="PIN, 4 цифры" maxlength="4" inputmode="numeric" class="w-full rounded-xl border px-3 py-3">
      <input type="text" name="new_address" placeholder="Адрес, пусто = самовывоз" class="w-full rounded-xl border px-3 py-3">
    </div>

    <button type="button" data-next="step2" class="next-step mt-4 w-full rounded-xl bg-[#C86052] px-4 py-3 font-semibold text-white">Далее</button>
  </section>

  <section id="step2" class="hidden rounded-2xl bg-white p-4 shadow-sm ring-1 ring-gray-100">
    <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Шаг 2</p>
    <h2 class="mb-3 text-lg font-semibold">Режим продажи</h2>
    <div class="grid gap-3 sm:grid-cols-2">
      <button type="button" class="mode-card rounded-2xl border-2 border-green-500 bg-green-50 p-4 text-left" data-mode="instant" data-group="in_stock">
        <div class="text-lg font-semibold text-green-800">В наличии</div>
        <div class="text-sm text-green-700">Закупки «Выкуплена» и «Готова к выдаче». Списание свободных ящиков сразу из партии.</div>
      </button>
      <button type="button" class="mode-card rounded-2xl border-2 border-transparent bg-orange-50 p-4 text-left" data-mode="preorder" data-group="preorder">
        <div class="text-lg font-semibold text-orange-800">Предзаказ</div>
        <div class="text-sm text-orange-700">Закупки «Запланирована». Создается бронь по выбранной партии.</div>
      </button>
    </div>
    <button type="button" data-next="step3" class="next-step mt-4 w-full rounded-xl bg-[#C86052] px-4 py-3 font-semibold text-white">Далее</button>
  </section>

  <section id="step3" class="hidden rounded-2xl bg-white p-4 shadow-sm ring-1 ring-gray-100">
    <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Шаг 3</p>
    <h2 class="mb-3 text-lg font-semibold">Закупка</h2>
    <div id="batchList" class="space-y-3"></div>
    <button type="button" data-next="step4" class="next-step mt-4 w-full rounded-xl bg-[#C86052] px-4 py-3 font-semibold text-white">Далее</button>
  </section>

  <section id="step4" class="hidden rounded-2xl bg-white p-4 shadow-sm ring-1 ring-gray-100">
    <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Шаг 4</p>
    <h2 class="mb-3 text-lg font-semibold">Товары закупки</h2>
    <div id="productsList" class="space-y-3"></div>
    <button type="button" data-next="step5" class="next-step mt-4 w-full rounded-xl bg-[#C86052] px-4 py-3 font-semibold text-white">Далее</button>
  </section>

  <section id="step5" class="hidden rounded-2xl bg-white p-4 shadow-sm ring-1 ring-gray-100">
    <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Шаг 5</p>
    <h2 class="mb-3 text-lg font-semibold">Дата и интервал</h2>
    <div class="rounded-xl bg-blue-50 p-3 text-sm text-blue-800">Один заказ создается только на одну дату получения. Для другой даты оформите отдельный заказ.</div>
    <div class="mt-3 grid grid-cols-3 gap-2" id="dateOptions"></div>
    <input type="hidden" id="deliveryDate" name="delivery_date" value="<?= htmlspecialchars($today) ?>">
    <label class="mt-3 block text-sm font-medium text-gray-700">Интервал</label>
    <select name="slot_id" class="mt-1 w-full rounded-xl border px-3 py-3">
      <?php foreach ($slots as $i => $s): ?>
        <option value="<?= $s['id'] ?>" <?= $i === 0 ? 'selected' : '' ?>>
          <?= htmlspecialchars(format_time_range($s['time_from'], $s['time_to'])) ?>
        </option>
      <?php endforeach; ?>
    </select>
    <button type="button" data-next="step6" class="next-step mt-4 w-full rounded-xl bg-[#C86052] px-4 py-3 font-semibold text-white">Далее</button>
  </section>

  <section id="step6" class="hidden rounded-2xl bg-white p-4 shadow-sm ring-1 ring-gray-100">
    <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Шаг 6</p>
    <h2 class="mb-3 text-lg font-semibold">Проверка заказа</h2>
    <div id="itemsList" class="space-y-2 text-sm"></div>
    <div class="mt-3 space-y-2 rounded-xl bg-gray-50 p-3 text-sm">
      <div class="flex justify-between"><span>Товары:</span><span id="sumSubtotal">0 ₽</span></div>
      <div id="rowReferral" class="hidden justify-between"><span>Скидка -10%:</span><span id="sumReferral">0 ₽</span></div>
      <div id="rowPoints" class="hidden justify-between"><span>Баллы:</span><span id="sumPoints">0 ₽</span></div>
      <div id="rowShipping" class="flex justify-between"><span>Доставка:</span><span id="sumShipping">300 ₽</span></div>
      <div class="flex justify-between border-t pt-2 text-base font-semibold"><span>Итого:</span><span id="sumTotal">0 ₽</span></div>
    </div>

    <div class="mt-3 space-y-3">
      <label class="block text-sm font-medium text-gray-700">Купон</label>
      <input type="text" name="coupon_code" class="w-full rounded-xl border px-3 py-3">
      <div id="referralToggleWrap" class="hidden rounded-xl bg-pink-50 p-3">
        <label class="flex items-center justify-between gap-3 text-sm font-medium text-pink-900">
          <span>Применить реферальную скидку менеджера</span>
          <input type="hidden" name="has_used_referral_coupon" value="0">
          <input type="checkbox" id="referralToggle" name="has_used_referral_coupon" value="1" class="h-5 w-5">
        </label>
      </div>
      <div id="pointsBlock" class="hidden rounded-xl bg-gray-50 p-3">
        <label class="flex items-center justify-between gap-3 text-sm font-medium">
          <span>Списать баллы</span>
          <input type="checkbox" name="use_points" id="usePointsToggle" value="1" class="h-5 w-5">
        </label>
        <input type="number" name="points" id="pointsInput" min="0" value="0" class="mt-2 w-full rounded-xl border px-3 py-2">
      </div>
    </div>

    <button type="submit" class="mt-4 w-full rounded-xl bg-green-600 px-4 py-3 font-semibold text-white">Создать заказ</button>
  </section>
</form>

<?php if (!empty($debugData)): ?>
  <pre class="mt-4 overflow-auto rounded-xl bg-gray-900 p-3 text-xs text-white"><?= htmlspecialchars(json_encode($debugData, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)) ?></pre>
<?php endif; ?>

<script>
  const batches = <?= json_encode($batches, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK) ?>;
  const basePath = '<?= $base ?>';
  const today = '<?= htmlspecialchars($today) ?>';
  const myReferralCode = <?= json_encode($_SESSION['referral_code'] ?? '') ?>;

  const state = { mode: 'instant', group: 'in_stock', batchKey: null };
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
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }

  document.querySelectorAll('.next-step').forEach(btn => btn.addEventListener('click', () => {
    if (btn.dataset.next === 'step3') renderBatches();
    if (btn.dataset.next === 'step4') renderProducts();
    if (btn.dataset.next === 'step5') renderDates();
    if (btn.dataset.next === 'step6') updateSummary();
    showStep(btn.dataset.next);
  }));

  document.querySelectorAll('.mode-card').forEach(btn => btn.addEventListener('click', () => {
    state.mode = btn.dataset.mode;
    state.group = btn.dataset.group;
    state.batchKey = null;
    stockMode.value = state.mode;
    document.querySelectorAll('.mode-card').forEach(card => card.classList.remove('border-green-500', 'border-orange-500'));
    btn.classList.add(state.mode === 'preorder' ? 'border-orange-500' : 'border-green-500');
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
      batchList.innerHTML = '<div class="rounded-xl bg-gray-50 p-4 text-sm text-gray-500">Нет доступных закупок для выбранного режима.</div>';
      return;
    }
    grouped.forEach((items, key) => {
      const [date, status] = key.split('|');
      const totalBoxes = items.reduce((sum, b) => sum + Number(b.available_boxes || 0), 0);
      const button = document.createElement('button');
      button.type = 'button';
      button.className = 'batch-card w-full rounded-2xl border-2 p-4 text-left ' + (state.mode === 'preorder' ? 'border-orange-200 bg-orange-50' : 'border-green-200 bg-green-50');
      button.dataset.key = key;
      button.innerHTML = '<div class="flex items-start justify-between gap-3"><div><div class="font-semibold">Закупка ' + date + '</div><div class="text-sm text-gray-600">' + statusLabel(status) + '</div></div><div class="rounded-full bg-white px-3 py-1 text-sm font-semibold">' + totalBoxes + ' ящ.</div></div>';
      button.addEventListener('click', () => {
        state.batchKey = key;
        document.querySelectorAll('.batch-card').forEach(card => card.classList.remove('ring-2', 'ring-[#C86052]'));
        button.classList.add('ring-2', 'ring-[#C86052]');
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
      productsList.innerHTML = '<div class="rounded-xl bg-red-50 p-4 text-sm text-red-700">Сначала выберите закупку.</div>';
      return;
    }
    selected.forEach(b => {
      const card = document.createElement('div');
      card.className = 'rounded-2xl border bg-white p-3 shadow-sm';
      const safeName = escapeHtml(productName(b));
      const safeImage = b.image_path ? escapeHtml(b.image_path) : '';
      card.innerHTML = `
        <div class="flex gap-3">
          ${safeImage ? `<img src="${safeImage}" class="h-16 w-16 rounded-xl object-cover" alt="">` : ''}
          <div class="min-w-0 flex-1">
            <div class="font-semibold">${safeName}</div>
            <div class="text-sm text-gray-600">Свободно: <b>${b.available_boxes}</b> ящиков</div>
            <div class="text-sm text-gray-600">Цена: <b>${Number(b.price_per_box).toFixed(0)} ₽/ящик</b></div>
            <div class="text-xs text-gray-400">purchase_batch_id: ${b.purchase_batch_id}</div>
          </div>
        </div>
        <div class="mt-3 flex items-center justify-between gap-2">
          <button type="button" class="dec rounded-xl bg-gray-100 px-4 py-3 text-lg" data-target="batch${b.purchase_batch_id}">−</button>
          <input id="batch${b.purchase_batch_id}" name="batch_items[${b.purchase_batch_id}]" type="number" min="0" max="${b.available_boxes}" step="1" value="0" data-price="${b.price_per_box}" data-name="${safeName}" class="qty w-24 rounded-xl border px-3 py-3 text-center text-lg">
          <button type="button" class="inc rounded-xl bg-gray-100 px-4 py-3 text-lg" data-target="batch${b.purchase_batch_id}">+</button>
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

  function renderDates() {
    const selected = selectedBatchItems();
    const baseDate = state.mode === 'preorder' && selected[0] ? selected[0].batch_date : today;
    dateOptions.innerHTML = '';
    for (let i = 0; i < 3; i++) {
      const value = addDays(baseDate, i);
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'date-option rounded-xl border px-2 py-3 text-sm font-semibold ' + (i === 0 ? 'border-[#C86052] bg-[#C86052] text-white' : 'bg-white');
      btn.dataset.date = value;
      btn.textContent = i === 0 ? value : '+' + i + ' день';
      btn.addEventListener('click', () => {
        deliveryDate.value = value;
        document.querySelectorAll('.date-option').forEach(el => el.classList.remove('border-[#C86052]', 'bg-[#C86052]', 'text-white'));
        btn.classList.add('border-[#C86052]', 'bg-[#C86052]', 'text-white');
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
        row.className = 'flex justify-between rounded-xl bg-gray-50 p-2';
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
  const couponInput = document.querySelector('input[name="coupon_code"]');

  searchPhone.addEventListener('input', () => {
    const q = searchPhone.value.trim();
    userIdInput.value = '';
    if (q.length < 3) {
      sugg.classList.add('hidden');
      return;
    }
    fetch(basePath + '/users/search?phone=' + encodeURIComponent(q))
      .then(r => r.json())
      .then(list => {
        sugg.innerHTML = '';
        list.forEach(u => {
          const li = document.createElement('li');
          li.className = 'cursor-pointer px-3 py-2 hover:bg-gray-100';
          li.textContent = (u.name || 'Клиент') + ' — ' + u.phone;
          li.addEventListener('click', () => selectUser(u));
          sugg.appendChild(li);
        });
        const liNew = document.createElement('li');
        liNew.className = 'cursor-pointer px-3 py-2 font-semibold text-[#C86052] hover:bg-gray-100';
        liNew.textContent = 'Создать нового клиента';
        liNew.addEventListener('click', () => createNewClient(q));
        sugg.appendChild(liNew);
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
    referralToggleWrap.classList.add('hidden');
    if (couponInput) couponInput.value = '';
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
    referralToggleWrap.classList.remove('hidden');
    if (couponInput) couponInput.value = myReferralCode;
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
