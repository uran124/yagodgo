<?php /** @var array<int,array<string,mixed>> $batches */ ?>
<?php $basePath = $basePath ?? '/admin'; ?>
<?php $flash = $flash ?? null; ?>
<?php $buyers = $buyers ?? []; ?>
<?php $filters = $filters ?? ['status' => '', 'buyer_id' => 0]; ?>
<?php $summary = $summary ?? []; ?>
<?php $preorderDemand = $preorderDemand ?? []; ?>
<?php $preorderDemandTotals = $preorderDemandTotals ?? []; ?>
<?php $statusLabels = [
  'planned' => 'Запланирована',
  'purchased' => 'Выкуплена',
  'arrived' => 'Готова к выдаче',
]; ?>
<style>
  .purchase-filter-row { display: flex; flex-wrap: wrap; gap: 0.35rem; align-items: end; margin-bottom: 0.35rem !important; padding: 0.35rem !important; }
  .purchase-filter-field { min-width: 130px; }
  .purchase-filter-select { padding: 0.3rem 0.45rem !important; font-size: 0.8125rem; }
  .purchase-filter-reset { padding: 0.3rem 0.55rem; font-size: 0.72rem; }
  .purchase-preorder-scroll { overflow-x: auto; -webkit-overflow-scrolling: touch; scrollbar-width: thin; }
  .purchase-preorder-metrics { display: flex; gap: 0.45rem; min-width: max-content; }
  .purchase-preorder-metric { min-width: 145px; padding: 0.55rem !important; border-radius: 0.5rem; }
  .purchase-qty-chip { display: inline-flex; align-items: center; padding: 0.15rem 0.5rem; border-radius: 9999px; background: rgba(148, 163, 184, 0.15); }
  .purchase-preorders-box { padding: 0.55rem !important; margin-bottom: 0.4rem !important; }
  .purchase-preorders-title { font-size: 1.1rem; line-height: 1.25; margin-bottom: 0.35rem !important; }
  .purchase-preorders-table { border-radius: 0.55rem; overflow: hidden; border: 1px solid rgba(148, 163, 184, 0.25); }
  .purchase-preorders-table thead th { font-size: 0.78rem; padding-top: 0.45rem; padding-bottom: 0.45rem; }
  .purchase-preorders-table td { font-size: 0.84rem; padding-top: 0.45rem; padding-bottom: 0.45rem; }
  .purchase-preorders-table .purchase-col-product { width: auto; max-width: 0; }
  .purchase-preorders-table .purchase-col-product-name { display:block; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .purchase-preorders-table .purchase-col-qty, .purchase-preorders-table .purchase-col-confirm { width: 96px; white-space: nowrap; text-align: right; }
  .purchase-preorders-table .purchase-col-intents { width: 78px; white-space: nowrap; text-align: right; }
  .purchase-preorders-table .purchase-mobile-icon { display: none; }
  .purchase-actions-row { margin-bottom: 0.4rem !important; gap: 0.35rem; }
  .purchase-actions-btn { padding: 0.45rem 0.6rem !important; font-size: 0.82rem !important; }
  .purchase-list { display: grid; gap: 0.45rem; }
  .purchase-item { border: 1px solid rgba(148, 163, 184, 0.25); border-radius: 0.65rem; padding: 0.5rem; background: rgba(255,255,255,0.85); }
  .purchase-item-top { display: grid; grid-template-columns: 74px 1fr; gap: 0.55rem; }
  .purchase-meta-line { display: flex; align-items: center; gap: 0.35rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .purchase-status-dot { width: 8px; height: 8px; border-radius: 9999px; background: #22c55e; display: inline-block; }
  .purchase-item-actions { margin-top: 0.45rem; display: flex; flex-wrap: nowrap; gap: 0.3rem; overflow-x: auto; }
  .purchase-action-form { display: inline-flex; align-items: center; gap: 0.2rem; flex: 0 0 auto; }
  .reserve-modal-backdrop { position: fixed; inset: 0; background: rgba(15, 23, 42, 0.55); display: none; align-items: center; justify-content: center; z-index: 60; padding: 0.75rem; }
  .reserve-modal-backdrop.is-open { display: flex; }
  .reserve-modal { width: 100%; max-width: 420px; background: #fff; border-radius: 0.75rem; border: 1px solid #cbd5e1; max-height: 78vh; display: flex; flex-direction: column; }
  .reserve-modal-list { overflow-y: auto; max-height: 56vh; }
  .reserve-modal-item { display:flex; justify-content: space-between; gap:0.5rem; padding:0.5rem; border-bottom:1px solid #e2e8f0; }
  .purchase-row-meta { color: #1f2937; }
  .purchase-row-subline { color: #374151; }
  .purchase-card-form input { background: #fff; }
  [data-theme='dark'] .purchase-photo-fallback { border-color: #475569; color: #94a3b8; background: #1e293b; }
  [data-theme='dark'] .purchase-row-meta { color: #e2e8f0; }
  [data-theme='dark'] .purchase-row-subline { color: #cbd5e1; }
  [data-theme='dark'] .purchase-card-form { background: rgba(15, 23, 42, 0.65); border-color: #475569; }
  [data-theme='dark'] .purchase-card-form input {
    background: #0f172a;
    color: #e2e8f0;
    border-color: #475569;
  }
  [data-theme='dark'] .purchase-card-form input::placeholder { color: #94a3b8; }
  [data-theme='dark'] .purchase-btn-discount { background: #facc15; color: #111827; }
  [data-theme='dark'] .purchase-btn-discount:hover { background: #fde047; }
  [data-theme='dark'] .purchase-btn-writeoff { background: #ef4444; color: #fff; }
  [data-theme='dark'] .purchase-btn-writeoff:hover { background: #f87171; }
  [data-theme='dark'] .purchase-btn-cancel { background: #334155; color: #f8fafc; border-color: #64748b; }
  [data-theme='dark'] .purchase-btn-cancel:hover { background: #475569; }
  [data-theme='dark'] .purchase-item { background: rgba(15, 23, 42, 0.78); border-color: #334155; color: #e2e8f0; }
  [data-theme='dark'] .purchase-preorders-box,
  [data-theme='dark'] #purchase-auto-filter { background: rgba(15, 23, 42, 0.74); border-color: #334155; }
  [data-theme='dark'] .purchase-preorders-title,
  [data-theme='dark'] .purchase-preorders-table td,
  [data-theme='dark'] .purchase-preorders-table th,
  [data-theme='dark'] .text-gray-900 { color: #e2e8f0 !important; }
  [data-theme='dark'] .text-gray-700, [data-theme='dark'] .text-gray-600, [data-theme='dark'] .text-gray-500 { color: #94a3b8 !important; }
  [data-theme='dark'] .purchase-filter-select, [data-theme='dark'] .purchase-filter-reset { background: #0f172a; color: #e2e8f0; border-color: #475569; }
  [data-theme='dark'] .purchase-preorders-table { border-color: #475569; }
  [data-theme='dark'] .reserve-modal { background: #0f172a; border-color: #334155; color: #e2e8f0; }
  [data-theme='dark'] .reserve-modal-item { border-bottom-color: #334155; }
  @media (max-width: 767px) {
    .purchase-filter-row { gap: 0.3rem; margin-bottom: 0.3rem !important; padding: 0.3rem !important; }
    .purchase-filter-field { min-width: 120px; flex: 1 1 120px; }
    .purchase-filter-select { padding: 0.28rem 0.4rem !important; font-size: 0.79rem; }
    .purchase-filter-reset { padding: 0.28rem 0.45rem; font-size: 0.72rem; }
    .purchase-preorders-box { padding: 0.45rem !important; margin-bottom: 0.3rem !important; }
    .purchase-preorders-title { font-size: 1rem; margin-bottom: 0.25rem !important; }
    .purchase-preorder-metrics { gap: 0.35rem; }
    .purchase-preorder-metric { min-width: 132px; padding: 0.45rem !important; }
    .purchase-preorders-table thead th { font-size: 0.75rem; padding-top: 0.36rem; padding-bottom: 0.36rem; }
    .purchase-preorders-table td { font-size: 0.8rem; padding-top: 0.4rem; padding-bottom: 0.4rem; }
    .purchase-preorders-table { table-layout: fixed; width: 100%; }
    .purchase-preorders-table .purchase-col-qty,
    .purchase-preorders-table .purchase-col-confirm { width: 54px; }
    .purchase-preorders-table .purchase-col-intents { display: none; }
    .purchase-preorders-table .purchase-mobile-icon { display: inline-flex; vertical-align: middle; font-size: 0.95rem; line-height: 1; }
    .purchase-preorders-table .purchase-desktop-label { display: none; }
    .purchase-actions-row { margin-bottom: 0.3rem !important; gap: 0.3rem; }
    .purchase-actions-btn { padding: 0.38rem 0.52rem !important; font-size: 0.76rem !important; }
    .purchase-mobile-row { display: grid; grid-template-columns: 84px minmax(0,1fr); gap: 0.75rem; }
    .purchase-mobile-photo { width: 84px; }
    .purchase-mobile-card { gap: 0.5rem !important; }
    .purchase-mobile-meta { display: grid; grid-template-columns: minmax(0,1fr) auto; gap: 0.4rem; align-items: start; }
    .purchase-mobile-title { display: flex; flex-wrap: wrap; gap: 0.35rem; line-height: 1.35; }
    .purchase-mobile-ops { display: grid; grid-template-columns: 1fr; gap: 0.45rem; }
    .purchase-mobile-form { display: inline-flex !important; grid-template-columns: none; gap: 0.25rem; width: auto; }
    .purchase-mobile-form .purchase-reason { display: none; }
  }
</style>
<?php if (is_array($flash) && !empty($flash['message'])): ?>
  <div class="<?= ($flash['type'] ?? '') === 'error' ? 'bg-red-50 border-red-200 text-red-700' : 'bg-green-50 border-green-200 text-green-700' ?> border p-3 rounded mb-4">
    <?= htmlspecialchars((string)$flash['message']) ?>
  </div>
<?php endif; ?>
<form method="get" class="bg-white rounded border p-3 mb-4 purchase-filter-row" id="purchase-auto-filter">
  <div class="purchase-filter-field flex-1 min-w-[150px]">
    <label class="text-xs text-gray-600">Статус</label>
    <select name="status" class="purchase-filter-select w-full border rounded px-2 py-2 text-sm">
      <option value="">Все</option>
      <?php foreach (['planned','purchased','arrived'] as $st): ?>
        <option value="<?= $st ?>" <?= (($filters['status'] ?? '') === $st) ? 'selected' : '' ?>><?= htmlspecialchars((string)($statusLabels[$st] ?? $st)) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="purchase-filter-field flex-1 min-w-[150px]">
    <label class="text-xs text-gray-600">Закупщик</label>
    <select name="buyer_id" class="purchase-filter-select w-full border rounded px-2 py-2 text-sm">
      <option value="0">Все</option>
      <?php foreach ($buyers as $buyer): ?>
        <option value="<?= (int)$buyer['id'] ?>" <?= ((int)($filters['buyer_id'] ?? 0) === (int)$buyer['id']) ? 'selected' : '' ?>><?= htmlspecialchars((string)$buyer['name']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="flex gap-1 whitespace-nowrap">
    <a class="purchase-filter-reset bg-gray-100 rounded" href="<?= $basePath ?>/purchases">Сбросить</a>
  </div>
</form>

<div class="purchase-preorders-box bg-white rounded border p-4 mb-4">
  <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
    <h2 class="purchase-preorders-title text-lg font-semibold text-gray-900">Предварительные заказы на закупку</h2>
  </div>
  <div class="purchase-preorder-scroll mb-3" id="preorder-metrics-scroll">
  <div class="purchase-preorder-metrics">
    <div class="purchase-preorder-metric rounded bg-amber-50 border border-amber-100 p-3">
      <div class="text-xs text-amber-700">Нужно купить (ящ.)</div>
      <div class="text-xl font-semibold text-amber-900"><?= number_format((float)($preorderDemandTotals['requested_boxes'] ?? 0), 2, '.', ' ') ?></div>
    </div>
    <div class="purchase-preorder-metric rounded bg-emerald-50 border border-emerald-100 p-3">
      <div class="text-xs text-emerald-700">Подтверждено (ящ.)</div>
      <div class="text-xl font-semibold text-emerald-900"><?= number_format((float)($preorderDemandTotals['confirmed_boxes'] ?? 0), 2, '.', ' ') ?></div>
    </div>
    <div class="purchase-preorder-metric rounded bg-blue-50 border border-blue-100 p-3">
      <div class="text-xs text-blue-700">Всего заявок</div>
      <div class="text-xl font-semibold text-blue-900"><?= (int)($preorderDemandTotals['intents_count'] ?? 0) ?></div>
    </div>
  </div>
  </div>

  <div class="overflow-x-auto">
    <table class="purchase-preorders-table min-w-full text-sm">
      <thead>
        <tr class="text-left text-gray-600 border-b">
          <th class="py-2 pr-3 purchase-col-product">Товар</th>
          <th class="py-2 pr-3 purchase-col-qty"><span class="purchase-desktop-label">Нужно купить (ящ.)</span><span class="material-icons-round purchase-mobile-icon" title="Нужно купить">inventory_2</span></th>
          <th class="py-2 pr-3 purchase-col-confirm"><span class="purchase-desktop-label">Подтверждено (ящ.)</span><span class="material-icons-round purchase-mobile-icon" title="Подтверждено">task_alt</span></th>
          <th class="py-2 pr-3 purchase-col-intents">Заявок</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$preorderDemand): ?>
          <tr>
            <td colspan="4" class="py-3 text-gray-500">Нет активных предзаказов в ожидании закупки.</td>
          </tr>
        <?php else: ?>
          <?php foreach ($preorderDemand as $row): ?>
            <tr class="border-b last:border-b-0">
              <td class="py-2 pr-3 font-medium text-gray-900 purchase-col-product"><span class="purchase-col-product-name"><?= htmlspecialchars(trim((string)($row['product_name'] ?? '') . ' ' . (string)($row['variety'] ?? ''))) ?></span></td>
              <td class="py-2 pr-3 purchase-col-qty"><?= number_format((float)($row['requested_boxes'] ?? 0), 2, '.', ' ') ?></td>
              <td class="py-2 pr-3 purchase-col-confirm"><?= number_format((float)($row['confirmed_boxes'] ?? 0), 2, '.', ' ') ?></td>
              <td class="py-2 pr-3 purchase-col-intents"><?= (int)($row['intents_count'] ?? 0) ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="purchase-actions-row flex items-center mb-4">
  <a href="<?= $basePath ?>/purchases/create" class="purchase-actions-btn bg-[#C86052] text-white px-4 py-2 rounded inline-flex items-center">
    <span class="material-icons-round text-base mr-1">add</span> Добавить закупку
  </a>
  <form method="post" action="<?= $basePath ?>/purchases/preorders/maintenance" class="ml-2">
    <?= csrf_field() ?>
    <button class="purchase-actions-btn bg-gray-100 text-gray-700 px-3 py-2 rounded text-sm" type="submit">Обновить предзаказы</button>
  </form>
</div>

<div class="purchase-list">
    <?php foreach ($batches as $batch): ?>
      <div class="purchase-item <?= ((int)($batch['is_closed'] ?? 0) === 1) ? 'bg-gray-50 text-gray-400' : '' ?>">
        <div class="purchase-item-top">
          <div class="purchase-mobile-photo">
          <?php if (!empty($batch['preview_photo'])): ?>
            <img src="<?= htmlspecialchars((string)$batch['preview_photo']) ?>" class="h-20 w-20 rounded object-cover border border-gray-200" alt="Фото партии #<?= (int)$batch['id'] ?>">
          <?php else: ?>
            <div class="purchase-photo-fallback h-20 w-20 rounded border border-dashed border-gray-300 text-[10px] text-gray-400 flex items-center justify-center">нет фото</div>
          <?php endif; ?>
          </div>
          <div class="purchase-mobile-card flex flex-col gap-2">
            <div class="flex items-start justify-between gap-2">
              <div class="purchase-meta-line text-xs text-gray-700">
                <b>#<?= (int)$batch['id'] ?></b>
                <span><?= htmlspecialchars(substr((string)($batch['purchased_at'] ?? ''), 0, 10)) ?></span>
                <span><?= htmlspecialchars((string)($batch['buyer_name'] ?? '—')) ?></span>
              </div>
              <div>
                <?php if ((int)($batch['is_closed'] ?? 0) === 0): ?>
                  <span class="purchase-status-dot" title="Активная"></span>
                <?php endif; ?>
              </div>
            </div>
            <div class="flex items-start justify-between gap-2 text-sm">
              <a class="text-[#C86052] hover:underline font-medium" href="<?= $basePath ?>/purchases/<?= (int)$batch['id'] ?>"><?= htmlspecialchars(trim(($batch['product_name'] ?? '') . ' ' . ($batch['variety'] ?? ''))) ?></a>
              <b><?= number_format((float)$batch['purchase_price_per_box'], 0, '.', ' ') ?> ₽</b>
            </div>
            <div class="text-sm">Куплено: <b class="purchase-qty-chip"><?= (int)round((float)$batch['boxes_total']) ?></b> , Свободно: <b class="purchase-qty-chip"><?= (int)round((float)$batch['boxes_free']) ?></b></div>
          </div>
        </div>
            <div class="purchase-item-actions">
              <form method="post" action="<?= $basePath ?>/purchases/move-to-discount" class="purchase-action-form purchase-mobile-form purchase-card-form flex items-center gap-1 bg-yellow-900/30 border border-yellow-700 rounded px-2 py-1">
                <?= csrf_field() ?>
                <input type="hidden" name="batch_id" value="<?= (int)$batch['id'] ?>">
                <input name="boxes" type="number" step="0.01" min="0.01" placeholder="ящ." class="w-16 border rounded px-1 py-1 text-xs text-gray-900">
                <input name="reason" type="text" placeholder="причина" class="purchase-reason w-24 border rounded px-1 py-1 text-xs text-gray-900">
                <button class="purchase-btn-discount text-xs bg-yellow-500 hover:bg-yellow-400 text-gray-900 px-2 py-1 rounded" type="submit">Уценка</button>
              </form>
              <form method="post" action="<?= $basePath ?>/purchases/write-off" class="purchase-action-form purchase-mobile-form purchase-card-form flex items-center gap-1 bg-red-900/30 border border-red-700 rounded px-2 py-1">
                <?= csrf_field() ?>
                <input type="hidden" name="batch_id" value="<?= (int)$batch['id'] ?>">
                <input name="boxes" type="number" step="0.01" min="0.01" placeholder="ящ." class="w-16 border rounded px-1 py-1 text-xs text-gray-900">
                <input name="comment" type="text" placeholder="причина" class="purchase-reason w-24 border rounded px-1 py-1 text-xs text-gray-900">
                <button class="purchase-btn-writeoff text-xs bg-red-500 hover:bg-red-400 text-white px-2 py-1 rounded" type="submit">Списать</button>
              </form>
              <button type="button" class="purchase-btn-cancel text-xs bg-slate-600 hover:bg-slate-500 text-white px-2 py-1 rounded border border-transparent js-open-reserve-modal" data-batch-id="<?= (int)$batch['id'] ?>">Снять бронь</button>
            </div>
      </div>
    <?php endforeach; ?>
</div>
<div id="reserve-modal-backdrop" class="reserve-modal-backdrop">
  <div class="reserve-modal">
    <div class="p-3 border-b font-semibold flex justify-between"><span>Список брони</span><button type="button" id="reserve-modal-close">✕</button></div>
    <div id="reserve-modal-list" class="reserve-modal-list"></div>
  </div>
</div>
<script>
  (function () {
    var filterForm = document.getElementById('purchase-auto-filter');
    if (filterForm) {
      var selects = filterForm.querySelectorAll('select');
      selects.forEach(function (el) {
        el.addEventListener('change', function () {
          filterForm.submit();
        });
      });
    }

    var wrap = document.getElementById('preorder-metrics-scroll');
    if (!wrap) return;
    var startX = 0;
    var startScroll = 0;
    var dragging = false;
    wrap.addEventListener('pointerdown', function (e) {
      dragging = true;
      startX = e.clientX;
      startScroll = wrap.scrollLeft;
    });
    wrap.addEventListener('pointermove', function (e) {
      if (!dragging) return;
      wrap.scrollLeft = startScroll - (e.clientX - startX);
    });
    ['pointerup', 'pointercancel', 'pointerleave'].forEach(function (ev) {
      wrap.addEventListener(ev, function () { dragging = false; });
    });

    var modal = document.getElementById('reserve-modal-backdrop');
    var closeBtn = document.getElementById('reserve-modal-close');
    var list = document.getElementById('reserve-modal-list');
    function closeModal(){ modal.classList.remove('is-open'); list.innerHTML=''; }
    if (closeBtn) closeBtn.addEventListener('click', closeModal);
    if (modal) modal.addEventListener('click', function(e){ if (e.target === modal) closeModal(); });
    document.querySelectorAll('.js-open-reserve-modal').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var batchId = btn.getAttribute('data-batch-id');
        fetch('<?= $basePath ?>/purchases/reservations?batch_id=' + encodeURIComponent(batchId))
          .then(function (r) { return r.json(); })
          .then(function (data) {
            var items = (data && data.items) ? data.items : [];
            if (!items.length) { list.innerHTML = '<div class="p-3 text-sm text-gray-500">Нет активных броней.</div>'; }
            else {
              list.innerHTML = items.map(function (it) {
                return '<div class="reserve-modal-item"><div><div class="text-sm font-semibold">' + (it.customer_name || '—') + '</div><div class="text-xs text-gray-500">' + (it.customer_phone || '—') + '</div></div><div class="flex gap-1"><button class="text-green-600">✓</button><button class="text-red-500">✕</button></div></div>';
              }).join('');
            }
            modal.classList.add('is-open');
          });
      });
    });
  })();
</script>
