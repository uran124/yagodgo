<?php /** @var array<int,array<string,mixed>> $products */ ?>
<?php $basePath = $basePath ?? '/admin'; ?>
<?php $flash = $flash ?? null; ?>
<?php $pricingInstantMarginPercent = (float)(get_setting('pricing_instant_margin_percent', '50') ?? '50'); ?>
<?php $pricingPreorderDiscountPercent = max(0.0, min(99.0, (float)(get_setting('ui_preorder_discount_percent', '10') ?? '10'))); ?>
<?php $pricingRoundingStep = max(1, (int)(get_setting('pricing_rounding_step', '10') ?? '10')); ?>
<?php $statusLabels = [
  'planned' => 'Запланирована',
  'purchased' => 'Выкуплена',
  'arrived' => 'Готова к выдаче',
]; ?>
<form action="<?= $basePath ?>/purchases/store" method="post" enctype="multipart/form-data" class="mobile-bottom-action-spacer bg-white p-4 sm:p-6 rounded shadow w-full max-w-2xl mx-auto space-y-4 pb-24 md:pb-6">
  <?= csrf_field() ?>
  <?php if (is_array($flash) && !empty($flash['message'])): ?>
    <div class="<?= ($flash['type'] ?? '') === 'error' ? 'bg-red-50 border-red-200 text-red-700' : 'bg-green-50 border-green-200 text-green-700' ?> border p-3 rounded">
      <?= htmlspecialchars((string)$flash['message']) ?>
    </div>
  <?php endif; ?>
  <div>
    <label class="block mb-1">Товар</label>
    <select id="purchase-product-id" name="product_id" class="w-full border px-2 py-1 rounded" required>
      <option value="">Выберите товар</option>
      <?php foreach ($products as $p): ?>
        <option value="<?= (int)$p['id'] ?>">
          <?= htmlspecialchars(trim(($p['product_name'] ?? '') . ' ' . ($p['variety'] ?? '') . ' ' . ($p['box_size'] ?? '') . ' ' . ($p['box_unit'] ?? ''))) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>

  <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div>
      <label class="block mb-1">Дата</label>
      <input id="planned-supply-date" name="planned_supply_date" type="date" class="w-full border px-2 py-1 rounded" aria-describedby="planned-date-help">
      <p id="planned-date-help" class="mt-1 text-xs text-gray-500">Можно оставить пустой, если точная дата закупки пока неизвестна.</p>
    </div>
    <div>
      <label class="block mb-1">Закупочная цена за ящик</label>
      <input name="purchase_price_per_box" type="number" step="0.01" class="w-full border px-2 py-1 rounded" placeholder="Можно оставить пустым">
      <p class="mt-1 text-xs text-gray-500">Цена в наличии = закупка + <?= htmlspecialchars((string)$pricingInstantMarginPercent) ?>%, предзаказ = цена в наличии − <?= htmlspecialchars((string)$pricingPreorderDiscountPercent) ?>%. Округление вниз до <?= htmlspecialchars((string)$pricingRoundingStep) ?> ₽.</p>
    </div>
  </div>
  <input type="hidden" name="boxes_total" value="0">
  <input type="hidden" name="boxes_reserved" value="0">
  <input type="hidden" name="boxes_free" value="0">
  <input type="hidden" name="extra_cost_per_box" value="0">
  <input type="hidden" name="comment" value="">

  <div id="matching-preorders-panel" class="hidden rounded border border-emerald-100 bg-emerald-50 p-3 text-sm text-emerald-900">
    <div class="font-semibold mb-2">Подходящие предзаказы для этой даты</div>
    <div id="matching-preorders-list" class="space-y-1 text-xs"></div>
  </div>

  <div class="hidden md:flex items-center justify-between pt-3 border-t border-gray-100">
    <a href="<?= $basePath ?>/purchases" class="px-4 py-2 rounded border border-gray-300 text-gray-700 hover:bg-gray-50">Вернуться</a>
    <button type="submit" class="bg-[#C86052] text-white px-4 py-2 rounded hover:bg-[#B44D47]">Создать</button>
  </div>

  <div class="mobile-sticky-actions md:hidden fixed bottom-0 left-0 right-0 z-40 bg-white border-t border-gray-200 p-3">
    <div class="grid grid-cols-3 gap-2">
      <a href="<?= $basePath ?>/purchases" class="h-10 rounded-lg border border-gray-300 text-gray-700 text-sm flex items-center justify-center">Вернуться</a>
      <button type="submit" class="h-10 rounded-lg border border-gray-300 text-gray-700 text-sm">Сохранить</button>
      <button type="submit" class="h-10 rounded-lg bg-[#C86052] text-white text-sm">Создать</button>
    </div>
  </div>
</form>

<script>
  (function () {
    var productSelect = document.getElementById('purchase-product-id');
    var dateInput = document.getElementById('planned-supply-date');
    var panel = document.getElementById('matching-preorders-panel');
    var list = document.getElementById('matching-preorders-list');
    function escapeHtml(value) {
      return String(value == null ? '' : value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
    }
    async function refreshMatchingPreorders() {
      var productId = productSelect ? productSelect.value : '';
      var plannedDate = dateInput ? dateInput.value : '';
      if (!productId || !plannedDate || !panel || !list) {
        if (panel) panel.classList.add('hidden');
        return;
      }
      var params = new URLSearchParams();
      params.set('product_id', productId);
      params.set('planned_supply_date', plannedDate);
      params.set('matching_only', '1');
      var res = await fetch('<?= $basePath ?>/purchases/preorders/intents?' + params.toString(), { headers: { 'Accept': 'application/json' } });
      var data = await res.json();
      var items = Array.isArray(data.items) ? data.items : [];
      var dates = Array.isArray(data.covered_date_labels) ? data.covered_date_labels : [];
      panel.classList.remove('hidden');
      if (!items.length) {
        list.innerHTML = '<div class="text-emerald-800/70">На окно ' + escapeHtml(dates.join(', ')) + ' активных предзаказов нет.</div>';
        return;
      }
      list.innerHTML = '<div class="mb-1 text-emerald-800/80">Окно закупки: ' + escapeHtml(dates.join(', ')) + '</div>' + items.map(function (item) {
        return '<div class="rounded bg-white/70 px-2 py-1 border border-emerald-100"><b>' + escapeHtml(item.customer_name || '') + '</b> · ' + escapeHtml(item.requested_boxes || 0) + ' ящ. · дата получения: <b>' + escapeHtml(item.desired_delivery_date_label || 'Не имеет значения') + '</b></div>';
      }).join('');
    }
    if (productSelect) productSelect.addEventListener('change', refreshMatchingPreorders);
    if (dateInput) dateInput.addEventListener('change', refreshMatchingPreorders);
  })();
</script>
