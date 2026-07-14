<?php /** @var array $inStockOffers @var array $preorderOffers @var array $slots @var string $today */ ?>
<?php
$role = $_SESSION['role'] ?? '';
$base = $role === 'manager' ? '/manager' : ($role === 'partner' ? '/partner' : '/admin');
$inStockOffers = $inStockOffers ?? [];
$preorderOffers = $preorderOffers ?? [];
$slots = $slots ?? [];
$today = $today ?? date('Y-m-d');
?>

<?php if (!empty($_GET['error'])): ?>
  <div class="mb-4 rounded-2xl border border-red-400/40 bg-red-950/40 p-4 text-xs text-red-100">
    Ошибка оформления: <?= htmlspecialchars($_GET['error']) ?>
  </div>
<?php endif; ?>

<form action="<?= $base ?>/orders/create" method="post" class="order-create-form space-y-4 pb-24" id="orderForm">
  <?= csrf_field() ?>
  <input type="hidden" id="deliveryDate" name="delivery_date" value="<?= htmlspecialchars($today) ?>">

  <div class="rounded-2xl bg-slate-800/70 p-2 ring-1 ring-slate-700" aria-label="Прогресс оформления заказа">
    <div class="mb-1.5 grid grid-cols-4 gap-1 text-center text-[10px] font-semibold uppercase tracking-tight text-slate-400">
      <span>Клиент</span><span>Товары</span><span>Получение</span><span>Проверка</span>
    </div>
    <div id="orderProgressSegments" class="grid grid-cols-4 gap-1">
      <?php for ($i = 0; $i < 4; $i++): ?>
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
        <input type="text" name="address_new" id="addressNew" placeholder="Новый адрес" class="hidden w-full rounded-xl border border-slate-600 bg-slate-950 px-3 py-3 text-white placeholder:text-slate-500" autocomplete="off">
        <ul id="addressNewSuggestions" class="hidden max-h-64 overflow-y-auto rounded-xl border border-slate-600 bg-slate-950 text-sm text-slate-100 shadow-lg"></ul>
      </div>
      <div id="deliveryCalcBlock" class="hidden space-y-2 rounded-xl border border-slate-700 bg-slate-900/70 p-3">
        <div class="flex items-center justify-between gap-2">
          <div>
            <div class="text-xs font-semibold uppercase tracking-wide text-slate-400">Доставка</div>
            <div id="deliveryCalcText" class="text-sm font-semibold text-slate-100">Стоимость будет рассчитана по адресу</div>
            <div id="deliveryCalcNote" class="text-xs text-slate-400">Выберите адрес или самовывоз.</div>
          </div>
          <button type="button" id="deliveryRecalcBtn" class="rounded-lg border border-slate-600 px-3 py-2 text-xs font-semibold text-slate-100">Пересчитать</button>
        </div>
        <div id="deliveryCommentWrapper" class="space-y-1">
          <label class="block text-xs font-medium text-slate-200">Комментарий к получению</label>
          <textarea name="delivery_comment" id="deliveryComment" rows="2" placeholder="Например: получатель Марина +7..., подъезд 2" class="w-full rounded-xl border border-slate-600 bg-slate-950 px-3 py-2 text-sm text-white placeholder:text-slate-500"></textarea>
        </div>
        <label class="block text-xs font-medium text-slate-200">Километраж вручную (если нужно скорректировать)</label>
        <input type="number" name="delivery_distance_km_manual" id="deliveryDistanceManual" min="0" step="0.001" placeholder="Например: 8.22" class="w-full rounded-xl border border-slate-600 bg-slate-950 px-3 py-2 text-sm text-white placeholder:text-slate-500">
        <input type="hidden" name="delivery_fee_preview" id="deliveryFeePreview" value="300">
        <input type="hidden" name="delivery_distance_km_preview" id="deliveryDistancePreview" value="">
        <input type="hidden" name="delivery_pricing_source_preview" id="deliverySourcePreview" value="pending_review">
        <input type="hidden" name="selected_lat" id="deliverySelectedLat" value="">
        <input type="hidden" name="selected_lng" id="deliverySelectedLng" value="">
        <input type="hidden" name="selected_address" id="deliverySelectedAddress" value="">
      </div>
      <div id="userInfo" class="hidden rounded-xl bg-slate-900/70 p-3 text-xs text-slate-200"></div>
    </div>

    <div id="newBlock" class="mt-3 hidden space-y-3 rounded-xl border border-dashed border-slate-600 p-3">
      <div class="text-xs font-semibold text-slate-200">Новый клиент</div>
      <input type="text" name="new_name" placeholder="Имя" class="w-full rounded-xl border border-slate-600 bg-slate-950 px-3 py-3 text-white placeholder:text-slate-500">
      <input type="hidden" name="new_phone" id="newPhoneHidden">
      <input type="password" name="new_pin" placeholder="PIN, 4 цифры" maxlength="4" inputmode="numeric" class="w-full rounded-xl border border-slate-600 bg-slate-950 px-3 py-3 text-white placeholder:text-slate-500">
      <input type="text" name="new_address" id="newClientAddress" placeholder="Адрес, пусто = самовывоз" class="w-full rounded-xl border border-slate-600 bg-slate-950 px-3 py-3 text-white placeholder:text-slate-500" autocomplete="off">
      <ul id="newClientAddressSuggestions" class="hidden max-h-64 overflow-y-auto rounded-xl border border-slate-600 bg-slate-950 text-sm text-slate-100 shadow-lg"></ul>
    </div>

    <div class="order-step-actions fixed inset-x-0 bottom-0 z-50 grid grid-cols-2 gap-2 border-t border-slate-700 bg-slate-800/95 p-2 backdrop-blur sm:static sm:mx-0 sm:mt-4 sm:border-0 sm:bg-transparent sm:p-0"><a href="<?= $base ?>/orders" class="rounded-xl border border-slate-600 bg-slate-900 px-4 py-3 font-semibold text-slate-100 text-center">Назад</a><button type="button" data-next="step2" class="next-step rounded-xl bg-[#F04483] px-4 py-3 font-semibold text-white shadow-sm shadow-pink-950/30">Далее</button></div>
  </section>

  <section id="step2" class="hidden rounded-2xl bg-slate-800/90 p-3 text-slate-100 shadow-sm ring-1 ring-slate-700">
    <div class="mb-3 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
      <div>
        <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Шаг 2</p>
        <h2 class="text-base font-semibold">Состав заказа</h2>
        <p class="text-xs text-slate-400">Выберите товары из наличия и будущих поставок. Закупки система подставит сама.</p>
      </div>
      <div class="rounded-xl bg-slate-900/70 px-3 py-2 text-xs text-slate-200"><span id="selectedCount">0</span> позиций · <span id="selectedSubtotalTop">0 ₽</span></div>
    </div>
    <input id="productSearch" type="search" placeholder="Поиск по названию или сорту" class="mb-3 w-full rounded-xl border border-slate-600 bg-slate-950 px-3 py-3 text-sm text-white placeholder:text-slate-500">

    <?php
    $renderOffer = static function (array $offer, string $mode) use ($today): void {
        $id = (int)$offer['purchase_batch_id'];
        $name = trim((string)($offer['product_name'] ?? 'Товар'));
        $variety = trim((string)($offer['variety'] ?? ''));
        $title = trim($name . ($variety !== '' ? ' · ' . $variety : ''));
        $date = substr((string)($offer['availability_date'] ?? ''), 0, 10);
        $dateLabel = $date !== '' ? date('d.m', strtotime($date)) : '';
        $badge = $mode === 'preorder' ? 'Под заказ · поступление ' . $dateLabel : 'В наличии · партия от ' . $dateLabel;
        $badgeClass = $mode === 'preorder' ? 'bg-amber-500/15 text-amber-200 ring-amber-400/30' : 'bg-emerald-500/15 text-emerald-200 ring-emerald-400/30';
        $img = (string)($offer['image_path'] ?? '');
        ?>
        <article class="offer-card rounded-2xl border border-slate-700 bg-slate-900/70 p-3" data-search="<?= htmlspecialchars(function_exists('mb_strtolower') ? mb_strtolower($title) : strtolower($title)) ?>" data-mode="<?= $mode ?>" data-date="<?= htmlspecialchars($date) ?>" data-title="<?= htmlspecialchars($title) ?>" data-price="<?= (float)$offer['price_per_box'] ?>" data-max="<?= (float)$offer['available_boxes'] ?>">
          <div class="flex gap-3">
            <div class="h-16 w-16 shrink-0 overflow-hidden rounded-xl bg-slate-800">
              <?php if ($img !== ''): ?><img src="<?= htmlspecialchars($img) ?>" alt="" class="h-full w-full object-cover"><?php endif; ?>
            </div>
            <div class="min-w-0 flex-1">
              <div class="flex flex-wrap items-center gap-2">
                <h3 class="font-semibold text-slate-100"><?= htmlspecialchars($name) ?></h3>
                <span class="rounded-full px-2 py-1 text-[11px] font-semibold ring-1 <?= $badgeClass ?>"><?= htmlspecialchars($badge) ?></span>
              </div>
              <?php if ($variety !== ''): ?><div class="text-xs text-slate-400">Сорт: <?= htmlspecialchars($variety) ?></div><?php endif; ?>
              <div class="mt-1 grid gap-1 text-xs text-slate-300 sm:grid-cols-3">
                <span>Фасовка: <?= htmlspecialchars((string)$offer['box_size']) ?> <?= htmlspecialchars((string)$offer['box_unit']) ?></span>
                <span><?= $mode === 'preorder' ? 'Лимит' : 'Свободно' ?>: <?= number_format((float)$offer['available_boxes'], 0, '.', ' ') ?> ящ.</span>
                <span class="font-semibold text-white"><?= number_format((float)$offer['price_per_box'], 0, '.', ' ') ?> ₽/ящ.</span>
              </div>
            </div>
            <div class="w-24 shrink-0">
              <label class="mb-1 block text-[11px] text-slate-400">Количество</label>
              <input class="qty w-full rounded-xl border border-slate-600 bg-slate-950 px-2 py-2 text-center text-white" type="number" min="0" step="1" max="<?= (float)$offer['available_boxes'] ?>" name="items[<?= $mode ?>][<?= $id ?>]" value="0" data-mode="<?= $mode ?>" data-id="<?= $id ?>" data-date="<?= htmlspecialchars($date) ?>" data-title="<?= htmlspecialchars($title) ?>" data-price="<?= (float)$offer['price_per_box'] ?>" data-max="<?= (float)$offer['available_boxes'] ?>">
              <input type="hidden" name="delivery_dates[<?= $mode ?>][<?= $id ?>]" value="<?= htmlspecialchars($mode === 'preorder' && $date !== '' ? $date : $today) ?>" data-delivery-date-for="<?= $mode ?>:<?= $id ?>">
            </div>
          </div>
        </article>
        <?php
    };
    ?>

    <div class="space-y-4">
      <div>
        <h3 class="mb-2 text-sm font-semibold text-emerald-200">В наличии</h3>
        <div class="space-y-2" id="inStockOffers">
          <?php if (!$inStockOffers): ?><div class="rounded-xl border border-slate-700 bg-slate-900/70 p-3 text-xs text-slate-400">Нет товаров в наличии.</div><?php endif; ?>
          <?php foreach ($inStockOffers as $offer) $renderOffer($offer, 'instant'); ?>
        </div>
      </div>
      <div>
        <h3 class="mb-2 text-sm font-semibold text-amber-200">Под заказ</h3>
        <div class="space-y-2" id="preorderOffers">
          <?php if (!$preorderOffers): ?><div class="rounded-xl border border-slate-700 bg-slate-900/70 p-3 text-xs text-slate-400">Нет подтверждённых будущих поставок.</div><?php endif; ?>
          <?php foreach ($preorderOffers as $offer) $renderOffer($offer, 'preorder'); ?>
        </div>
      </div>
    </div>

    <div class="mt-4 rounded-2xl border border-slate-700 bg-slate-900/80 p-3">
      <div class="mb-2 flex items-center justify-between"><h3 class="text-sm font-semibold">Выбрано</h3><span id="selectedSubtotalBottom" class="text-sm font-semibold">0 ₽</span></div>
      <div id="selectedItemsPreview" class="space-y-2 text-xs text-slate-300">Пока ничего не выбрано.</div>
    </div>

    <div class="order-step-actions fixed inset-x-0 bottom-0 z-50 grid grid-cols-2 gap-2 border-t border-slate-700 bg-slate-800/95 p-2 backdrop-blur sm:static sm:mx-0 sm:mt-4 sm:border-0 sm:bg-transparent sm:p-0"><button type="button" data-prev="step1" class="back-step rounded-xl border border-slate-600 bg-slate-900 px-4 py-3 font-semibold text-slate-100">Назад</button><button type="button" data-next="step3" class="next-step rounded-xl bg-[#F04483] px-4 py-3 font-semibold text-white shadow-sm shadow-pink-950/30">Далее</button></div>
  </section>

  <section id="step3" class="hidden rounded-2xl bg-slate-800/90 p-3 text-slate-100 shadow-sm ring-1 ring-slate-700">
    <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Шаг 3</p>
    <h2 class="mb-3 text-base font-semibold">Получение</h2>
    <div id="deliveryGroups" class="space-y-3"></div>
    <label class="mt-3 block text-xs font-medium text-slate-200">Интервал доставки</label>
    <select name="slot_id" id="slotSelect" class="mt-1 w-full rounded-xl border border-slate-600 bg-slate-950 px-3 py-3 text-white">
      <?php foreach ($slots as $i => $s): ?>
        <option value="<?= $s['id'] ?>" <?= $i === 0 ? 'selected' : '' ?>><?= htmlspecialchars(format_time_range($s['time_from'], $s['time_to'])) ?></option>
      <?php endforeach; ?>
    </select>
    <div class="order-step-actions fixed inset-x-0 bottom-0 z-50 grid grid-cols-2 gap-2 border-t border-slate-700 bg-slate-800/95 p-2 backdrop-blur sm:static sm:mx-0 sm:mt-4 sm:border-0 sm:bg-transparent sm:p-0"><button type="button" data-prev="step2" class="back-step rounded-xl border border-slate-600 bg-slate-900 px-4 py-3 font-semibold text-slate-100">Назад</button><button type="button" data-next="step4" class="next-step rounded-xl bg-[#F04483] px-4 py-3 font-semibold text-white shadow-sm shadow-pink-950/30">Далее</button></div>
  </section>

  <section id="step4" class="hidden rounded-2xl bg-slate-800/90 p-3 text-slate-100 shadow-sm ring-1 ring-slate-700">
    <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Шаг 4</p>
    <h2 class="mb-3 text-base font-semibold">Проверка заказа</h2>
    <div id="orderCreationNotice" class="mb-3 rounded-xl border border-blue-400/30 bg-blue-950/40 p-3 text-xs text-blue-100"></div>
    <div id="itemsList" class="space-y-2 text-xs"></div>
    <div id="deliveriesSummary" class="mt-3 space-y-2 text-xs"></div>
    <div class="mt-3 space-y-2 rounded-xl bg-slate-900/70 p-3 text-xs text-slate-100">
      <div class="flex justify-between"><span>Товары:</span><span id="sumSubtotal">0 ₽</span></div>
      <div id="rowReferral" class="hidden justify-between"><span>Скидка -10%:</span><span id="sumReferral">0 ₽</span></div>
      <div id="rowPoints" class="hidden justify-between"><span>Баллы:</span><span id="sumPoints">0 ₽</span></div>
      <div id="rowShipping" class="flex justify-between"><span>Доставка:</span><span id="sumShipping">300 ₽</span></div>
      <div class="flex justify-between border-t border-slate-700 pt-2 text-base font-semibold"><span>Итого:</span><span id="sumTotal">0 ₽</span></div>
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
        <label class="flex items-center justify-between gap-3 text-xs font-medium"><span>Списать баллы</span><input type="checkbox" name="use_points" id="usePointsToggle" value="1" class="h-5 w-5"></label>
        <input type="number" name="points" id="pointsInput" min="0" value="0" class="mt-2 w-full rounded-xl border border-slate-600 bg-slate-950 px-3 py-2 text-white">
      </div>
    </div>

    <div class="order-step-actions fixed inset-x-0 bottom-0 z-50 grid grid-cols-2 gap-2 border-t border-slate-700 bg-slate-800/95 p-2 backdrop-blur sm:static sm:mx-0 sm:mt-4 sm:border-0 sm:bg-transparent sm:p-0"><button type="button" data-prev="step3" class="back-step rounded-xl border border-slate-600 bg-slate-900 px-4 py-3 font-semibold text-slate-100">Назад</button><button type="submit" class="rounded-xl bg-emerald-600 px-4 py-3 font-semibold text-white">Создать заказ</button></div>
  </section>
</form>

<script>
  const basePath = '<?= $base ?>';
  const serverToday = '<?= htmlspecialchars($today) ?>';
  const clientNow = new Date();
  const clientToday = clientNow.getFullYear() + '-' + String(clientNow.getMonth() + 1).padStart(2, '0') + '-' + String(clientNow.getDate()).padStart(2, '0');
  const today = serverToday > clientToday ? serverToday : clientToday;
  if (document.getElementById('deliveryDate') && document.getElementById('deliveryDate').value < today) document.getElementById('deliveryDate').value = today;
  const state = { selectedClientCanReferral: true, deliveryFee: 300 };
  const steps = ['step1','step2','step3','step4'];

  const rub = value => Number(value || 0).toFixed(0) + ' ₽';
  const formatDate = iso => iso ? iso.split('-').reverse().slice(0,2).join('.') : '';
  const addDays = (iso, days) => {
    const parts = String(iso || today).split('-').map(Number);
    const d = new Date(parts[0], (parts[1] || 1) - 1, parts[2] || 1);
    d.setDate(d.getDate() + days);
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return y + '-' + m + '-' + day;
  };
  const escapeHtml = text => String(text || '').replace(/[&<>'"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[c]));

  function showStep(stepId) {
    if (stepId === 'step3' && selectedItems().length === 0) { alert('Добавьте хотя бы одну позицию.'); return; }
    steps.forEach(id => document.getElementById(id).classList.toggle('hidden', id !== stepId));
    const index = Math.max(0, steps.indexOf(stepId));
    document.querySelectorAll('.progress-segment').forEach((segment, i) => {
      segment.classList.toggle('bg-[#F04483]', i <= index);
      segment.classList.toggle('bg-slate-700', i > index);
    });
    if (stepId === 'step3') renderDeliveryGroups();
    if (stepId === 'step4') { prepareReferralToggle(); updateSummary(); }
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }
  document.querySelectorAll('.next-step').forEach(btn => btn.addEventListener('click', () => showStep(btn.dataset.next)));
  document.querySelectorAll('.back-step').forEach(btn => btn.addEventListener('click', () => showStep(btn.dataset.prev)));

  function selectedItems() {
    return Array.from(document.querySelectorAll('.qty')).map(input => {
      const qty = Number(input.value || 0);
      if (qty <= 0) return null;
      const max = Number(input.dataset.max || input.max || 0);
      if (qty > max) input.value = max;
      return {
        input,
        mode: input.dataset.mode,
        id: input.dataset.id,
        title: input.dataset.title,
        date: input.dataset.date || today,
        price: Number(input.dataset.price || 0),
        qty: Number(input.value || 0),
      };
    }).filter(Boolean);
  }

  function groupedSelected() {
    const map = new Map();
    selectedItems().forEach(item => {
      const date = item.mode === 'preorder' ? item.date : (document.getElementById('deliveryDate').value || today);
      const key = item.mode + '|' + date;
      if (!map.has(key)) map.set(key, { mode: item.mode, date, items: [], subtotal: 0 });
      const group = map.get(key);
      group.items.push(item);
      group.subtotal += item.qty * item.price;
    });
    return Array.from(map.values());
  }

  function syncDeliveryDateInputs() {
    groupedSelected().forEach(group => {
      group.items.forEach(item => {
        const hidden = document.querySelector('[data-delivery-date-for="' + item.mode + ':' + item.id + '"]');
        if (hidden) hidden.value = group.date;
      });
    });
  }

  function updateSelectedPreview() {
    const items = selectedItems();
    const subtotal = items.reduce((sum, item) => sum + item.qty * item.price, 0);
    document.getElementById('selectedCount').textContent = String(items.length);
    document.getElementById('selectedSubtotalTop').textContent = rub(subtotal);
    document.getElementById('selectedSubtotalBottom').textContent = rub(subtotal);
    const box = document.getElementById('selectedItemsPreview');
    if (items.length === 0) { box.textContent = 'Пока ничего не выбрано.'; return; }
    box.innerHTML = items.map(item => '<div class="flex justify-between rounded-xl bg-slate-950/60 p-2"><span>' + escapeHtml(item.title) + ' · ' + (item.mode === 'preorder' ? 'под заказ' : 'в наличии') + ' × ' + item.qty + '</span><span>' + rub(item.qty * item.price) + '</span></div>').join('');
  }

  document.querySelectorAll('.qty').forEach(input => input.addEventListener('input', () => { updateSelectedPreview(); updateSummary(); }));
  document.getElementById('productSearch').addEventListener('input', event => {
    const q = event.target.value.trim().toLowerCase();
    document.querySelectorAll('.offer-card').forEach(card => card.classList.toggle('hidden', q !== '' && !card.dataset.search.includes(q)));
  });

  function renderDeliveryGroups() {
    const box = document.getElementById('deliveryGroups');
    const groups = groupedSelected();
    if (groups.length === 0) { box.innerHTML = '<div class="rounded-xl bg-slate-900/70 p-3 text-xs text-slate-400">Добавьте товары на предыдущем шаге.</div>'; return; }
    box.innerHTML = groups.map((group, i) => {
      const baseDate = (group.mode === 'preorder' && group.date > today) ? group.date : today;
      const options = [0, 1, 2].map(offset => {
        const value = addDays(baseDate, offset);
        const checked = value === group.date ? 'checked' : '';
        const active = value === group.date ? ' border-pink-400 bg-pink-500/20 text-white' : ' border-slate-600 bg-slate-950 text-slate-100';
        return '<label class="cursor-pointer rounded-xl border px-3 py-2 text-center text-xs transition' + active + '"><input class="delivery-group-date sr-only" type="radio" name="delivery_group_date_' + i + '" data-mode="' + group.mode + '" data-old-date="' + group.date + '" value="' + value + '" ' + checked + '> ' + formatDate(value) + '</label>';
      }).join('');
      return '<div class="rounded-2xl border border-slate-700 bg-slate-900/70 p-3"><div class="mb-2 flex items-center justify-between gap-2"><div><div class="text-sm font-semibold">' + (group.mode === 'preorder' ? 'Под заказ' : 'В наличии') + '</div><div class="text-xs text-slate-400">Базовая дата: ' + formatDate(baseDate) + ' · товаров: ' + group.items.length + '</div></div><div class="text-xs text-slate-300">Доставка: ' + rub(isPickup() ? 0 : state.deliveryFee) + '</div></div><div class="grid grid-cols-3 gap-2">' + options + '</div></div>';
    }).join('');
    document.querySelectorAll('.delivery-group-date').forEach(radio => radio.addEventListener('change', event => {
      const mode = event.target.dataset.mode;
      const oldDate = event.target.dataset.oldDate;
      const newDate = event.target.value;
      selectedItems().filter(item => item.mode === mode && (item.mode === 'preorder' ? item.date : (document.getElementById('deliveryDate').value || today)) === oldDate).forEach(item => {
        if (mode === 'preorder') item.input.dataset.date = newDate;
      });
      if (mode === 'instant') document.getElementById('deliveryDate').value = newDate;
      syncDeliveryDateInputs();
      renderDeliveryGroups();
      updateSummary();
    }));
    syncDeliveryDateInputs();
  }

  function isPickup() {
    const newBlock = document.getElementById('newBlock');
    const addressSelect = document.getElementById('addressSelect');
    if (!newBlock.classList.contains('hidden')) return (document.getElementById('newClientAddress').value.trim() === '');
    return addressSelect && addressSelect.value === 'pickup';
  }

  function prepareReferralToggle() {
    const wrap = document.getElementById('referralToggleWrap');
    const toggle = document.getElementById('referralToggle');
    if (state.selectedClientCanReferral) wrap.classList.remove('hidden'); else { wrap.classList.add('hidden'); if (toggle) toggle.checked = false; }
  }

  function updateSummary() {
    const groups = groupedSelected();
    syncDeliveryDateInputs();
    const subtotal = groups.reduce((sum, g) => sum + g.subtotal, 0);
    document.getElementById('sumSubtotal').textContent = rub(subtotal);
    const referralToggle = document.getElementById('referralToggle');
    const referral = referralToggle && !document.getElementById('referralToggleWrap').classList.contains('hidden') && referralToggle.checked;
    const discount = referral ? Math.floor(subtotal * 0.1) : 0;
    document.getElementById('sumReferral').textContent = '-' + rub(discount);
    document.getElementById('rowReferral').classList.toggle('hidden', !referral);
    document.getElementById('rowReferral').classList.toggle('flex', referral);
    let afterDiscount = subtotal - discount;
    const usePoints = document.getElementById('usePointsToggle').checked;
    const points = usePoints ? Math.min(Number(document.getElementById('pointsInput').value || 0), afterDiscount) : 0;
    afterDiscount -= points;
    document.getElementById('sumPoints').textContent = '-' + rub(points);
    document.getElementById('rowPoints').classList.toggle('hidden', points <= 0);
    document.getElementById('rowPoints').classList.toggle('flex', points > 0);
    const deliveryKeys = new Set(groups.map(g => g.date + '|address|' + (document.getElementById('slotSelect') ? document.getElementById('slotSelect').value : '')));
    const shipping = isPickup() ? 0 : deliveryKeys.size * Number(state.deliveryFee || 300);
    document.getElementById('sumShipping').textContent = rub(shipping);
    document.getElementById('sumTotal').textContent = rub(afterDiscount + shipping);
    document.getElementById('itemsList').innerHTML = groups.map(g => '<div class="rounded-xl bg-slate-900/70 p-2"><div class="mb-1 font-semibold">' + (g.mode === 'preorder' ? 'Под заказ' : 'В наличии') + ' · получение ' + formatDate(g.date) + '</div>' + g.items.map(item => '<div class="flex justify-between"><span>' + escapeHtml(item.title) + ' × ' + item.qty + ' ящ.</span><span>' + rub(item.qty * item.price) + '</span></div>').join('') + '</div>').join('');
    document.getElementById('deliveriesSummary').innerHTML = Array.from(deliveryKeys).map(key => key.split('|')[0]).map(date => '<div class="rounded-xl border border-slate-700 bg-slate-900/70 p-2">Доставка ' + formatDate(date) + ': ' + rub(isPickup() ? 0 : state.deliveryFee) + '</div>').join('');
    document.getElementById('orderCreationNotice').innerHTML = 'Будет создано ' + groups.length + ' связанных заказа:<ol class="mt-2 list-decimal pl-5">' + groups.map(g => '<li>' + (g.mode === 'preorder' ? 'Предзаказ' : 'Товары в наличии') + ' — получение ' + formatDate(g.date) + '</li>').join('') + '</ol>';
    updateSelectedPreview();
  }

  ['referralToggle','usePointsToggle','pointsInput','slotSelect'].forEach(id => { const el = document.getElementById(id); if (el) el.addEventListener('input', updateSummary); if (el) el.addEventListener('change', updateSummary); });

  const searchPhone = document.getElementById('searchPhone');
  const sugg = document.getElementById('suggestions');
  const userIdInput = document.getElementById('userId');
  const userInfo = document.getElementById('userInfo');
  const addressWrapper = document.getElementById('addressWrapper');
  const addressSelect = document.getElementById('addressSelect');
  const addressNew = document.getElementById('addressNew');
  const newBlock = document.getElementById('newBlock');
  const newPhoneHidden = document.getElementById('newPhoneHidden');
  const deliveryCalcBlock = document.getElementById('deliveryCalcBlock');
  const deliveryCalcText = document.getElementById('deliveryCalcText');
  const deliveryCalcNote = document.getElementById('deliveryCalcNote');
  const deliveryRecalcBtn = document.getElementById('deliveryRecalcBtn');
  const deliveryDistanceManual = document.getElementById('deliveryDistanceManual');
  const deliveryFeePreview = document.getElementById('deliveryFeePreview');
  const deliveryDistancePreview = document.getElementById('deliveryDistancePreview');
  const deliverySourcePreview = document.getElementById('deliverySourcePreview');
  const deliverySelectedLat = document.getElementById('deliverySelectedLat');
  const deliverySelectedLng = document.getElementById('deliverySelectedLng');
  const deliverySelectedAddress = document.getElementById('deliverySelectedAddress');

  searchPhone.addEventListener('input', () => {
    const q = searchPhone.value.trim().replace(/\D+/g, '');
    userIdInput.value = ''; addressWrapper.classList.add('hidden'); userInfo.classList.add('hidden'); document.getElementById('pointsBlock').classList.add('hidden');
    if (q.length === 0) { sugg.classList.add('hidden'); newBlock.classList.add('hidden'); return; }
    fetch(basePath + '/users/search?term=' + encodeURIComponent(q)).then(r => r.json()).then(list => {
      sugg.innerHTML = '';
      if (list.length === 0) { createNewClient(q); return; }
      newBlock.classList.add('hidden');
      list.forEach(u => { const li = document.createElement('li'); li.className = 'cursor-pointer px-3 py-2 hover:bg-slate-800'; li.textContent = (u.name || 'Клиент') + ' — ' + u.phone; li.addEventListener('click', () => selectUser(u)); sugg.appendChild(li); });
      sugg.classList.remove('hidden');
    });
  });

  function selectUser(u) {
    searchPhone.value = u.phone; userIdInput.value = u.id; sugg.classList.add('hidden'); newBlock.classList.add('hidden');
    userInfo.textContent = (u.name || 'Клиент') + ', баланс: ' + (u.points_balance || 0) + ' баллов'; userInfo.classList.remove('hidden');
    document.getElementById('pointsBlock').classList.remove('hidden'); state.selectedClientCanReferral = Number(u.has_used_referral_coupon || 0) === 0; prepareReferralToggle();
    fetch(basePath + '/users/addresses?user_id=' + u.id).then(r => r.json()).then(list => {
      addressSelect.innerHTML = '';
      list.forEach(a => { const opt = document.createElement('option'); opt.value = a.id; opt.textContent = a.street; opt.dataset.street = a.street || ''; opt.dataset.comment = a.last_checkout_comment || ''; opt.dataset.distanceKm = a.delivery_distance_km || ''; addressSelect.appendChild(opt); });
      const optNew = document.createElement('option'); optNew.value = 'new'; optNew.textContent = 'Добавить новый адрес'; addressSelect.appendChild(optNew);
      const optPickup = document.createElement('option'); optPickup.value = 'pickup'; optPickup.textContent = 'Самовывоз 9 мая 73'; addressSelect.appendChild(optPickup);
      addressWrapper.classList.remove('hidden'); deliveryCalcBlock.classList.remove('hidden'); calculateDelivery(); updateSummary();
    });
  }
  function createNewClient(phone) { userIdInput.value = ''; newPhoneHidden.value = phone; newBlock.classList.remove('hidden'); addressWrapper.classList.add('hidden'); deliveryCalcBlock.classList.remove('hidden'); document.getElementById('pointsBlock').classList.add('hidden'); state.selectedClientCanReferral = true; calculateDelivery(); updateSummary(); sugg.classList.add('hidden'); }

  function currentDeliveryAddress() {
    if (!newBlock.classList.contains('hidden')) return document.getElementById('newClientAddress').value.trim();
    if (addressSelect.value === 'pickup') return 'Самовывоз';
    if (addressSelect.value === 'new') return addressNew.value.trim();
    const selected = addressSelect.options[addressSelect.selectedIndex];
    return selected ? (selected.dataset.street || selected.textContent || '').trim() : '';
  }
  async function calculateDelivery() {
    if (isPickup()) { state.deliveryFee = 0; deliveryCalcText.textContent = '0 ₽'; deliveryCalcNote.textContent = 'Самовывоз — доставка 0 ₽.'; deliveryFeePreview.value = '0'; deliverySourcePreview.value = 'pickup'; updateSummary(); return; }
    const address = currentDeliveryAddress();
    if (!address) { state.deliveryFee = 300; deliveryCalcText.textContent = 'от 300 ₽'; deliveryCalcNote.textContent = 'Адрес не указан — точную стоимость проверит менеджер.'; deliveryFeePreview.value = '300'; deliverySourcePreview.value = 'pending_review'; updateSummary(); return; }
    deliveryCalcText.textContent = 'считаем…';
    const body = new URLSearchParams(); body.set('address', address);
    if (deliveryDistanceManual && deliveryDistanceManual.value.trim() !== '') body.set('delivery_distance_km_manual', deliveryDistanceManual.value.trim());
    if (deliverySelectedAddress.value && deliverySelectedLat.value && deliverySelectedLng.value) { body.set('selected_address', deliverySelectedAddress.value); body.set('selected_lat', deliverySelectedLat.value); body.set('selected_lng', deliverySelectedLng.value); }
    try {
      const response = await fetch(basePath + '/delivery/calculate', { method: 'POST', credentials: 'same-origin', headers: {'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest'}, body });
      const data = await response.json(); if (!response.ok || !data.ok) throw new Error(data.message || 'Не удалось рассчитать доставку');
      const fee = Number(data.delivery_fee || data.price_rub || 300); state.deliveryFee = fee; deliveryCalcText.textContent = rub(fee); deliveryCalcNote.textContent = 'Расстояние: ' + (data.distance_km ? data.distance_km + ' км' : 'уточняется'); deliveryFeePreview.value = String(fee); deliveryDistancePreview.value = data.distance_km || ''; deliverySourcePreview.value = data.delivery_pricing_source || data.pricing_source || '';
    } catch (e) { state.deliveryFee = 300; deliveryCalcText.textContent = 'от 300 ₽'; deliveryCalcNote.textContent = (e.message || 'Не удалось рассчитать доставку') + '. Точную стоимость подтвердит менеджер.'; deliveryFeePreview.value = '300'; deliverySourcePreview.value = 'pending_review'; }
    updateSummary();
  }

  addressSelect.addEventListener('change', () => { addressNew.classList.toggle('hidden', addressSelect.value !== 'new'); const selected = addressSelect.options[addressSelect.selectedIndex]; if (selected && selected.dataset.comment !== undefined) document.getElementById('deliveryComment').value = selected.dataset.comment || ''; if (deliveryDistanceManual) deliveryDistanceManual.value = selected && selected.dataset.distanceKm ? selected.dataset.distanceKm : ''; calculateDelivery(); updateSummary(); });
  [addressNew, document.getElementById('newClientAddress'), deliveryDistanceManual].forEach(el => { if (el) el.addEventListener('input', () => { calculateDelivery(); updateSummary(); }); });
  if (deliveryRecalcBtn) deliveryRecalcBtn.addEventListener('click', calculateDelivery);

  function resetSelectedDeliverySuggestion() { deliverySelectedLat.value = ''; deliverySelectedLng.value = ''; deliverySelectedAddress.value = ''; }
  function renderDeliverySuggestions(input, list, suggestions) {
    if (!list) return;
    list.innerHTML = '';
    if (!Array.isArray(suggestions) || suggestions.length === 0) { list.classList.add('hidden'); return; }
    suggestions.forEach(item => {
      const li = document.createElement('li');
      li.className = 'cursor-pointer border-b border-slate-800 px-3 py-2 hover:bg-slate-900 last:border-b-0';
      li.innerHTML = '<div class="font-semibold text-slate-100">' + escapeHtml(item.value || item.unrestricted_value || '') + '</div><div class="text-xs text-slate-400">' + escapeHtml(item.label || '') + '</div>';
      li.addEventListener('mousedown', event => {
        event.preventDefault();
        const selectedAddress = item.value || item.label || item.unrestricted_value || '';
        input.value = selectedAddress;
        deliverySelectedLat.value = item.lat || '';
        deliverySelectedLng.value = item.lng || '';
        deliverySelectedAddress.value = selectedAddress;
        if (deliveryDistanceManual) deliveryDistanceManual.value = '';
        list.classList.add('hidden');
        calculateDelivery();
        updateSummary();
      });
      list.appendChild(li);
    });
    list.classList.remove('hidden');
  }
  function attachDeliverySuggest(input, list) {
    if (!input || !list) return;
    let timer = null;
    input.addEventListener('input', () => {
      resetSelectedDeliverySuggestion();
      clearTimeout(timer);
      const query = input.value.trim();
      if (query.length < 3) { list.classList.add('hidden'); list.innerHTML = ''; return; }
      timer = setTimeout(async () => {
        try {
          const response = await fetch(basePath + '/delivery/address-suggestions?query=' + encodeURIComponent(query), { credentials: 'same-origin', headers: {'X-Requested-With': 'XMLHttpRequest'} });
          const data = await response.json();
          if (!response.ok || !data.ok) throw new Error(data.message || 'DaData не вернула подсказки');
          renderDeliverySuggestions(input, list, data.suggestions || []);
        } catch (error) {
          list.innerHTML = '<li class="px-3 py-2 text-xs text-red-200">' + escapeHtml(error.message || 'Ошибка подсказок DaData') + '</li>';
          list.classList.remove('hidden');
        }
      }, 250);
    });
  }
  attachDeliverySuggest(addressNew, document.getElementById('addressNewSuggestions'));
  attachDeliverySuggest(document.getElementById('newClientAddress'), document.getElementById('newClientAddressSuggestions'));

  updateSelectedPreview();
</script>
