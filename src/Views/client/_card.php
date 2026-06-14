<?php
/**
 * @var array $p
 * Ожидаются поля:
 *   - id
 *   - product
 *   - variety
 *   - description
 *   - price            (цена сейчас за позицию/ящик)
 *   - sale_price       (акционная цена, 0 = без акции)
 *   - is_active        (0 или 1)
 *   - image_path
 *   - box_size, box_unit
  *   - delivery_date    (строка 'Y-m-d' или null)
 *   - seller_name
 */
?>
<?php
$search = mb_strtolower(($p['product'] ?? '') . ' ' . ($p['variety'] ?? ''), 'UTF-8');
$img       = trim($p['image_path'] ?? '');
$productImg = trim($p['product_image_path'] ?? '');
$imageFallback = $productImg !== '' && $productImg !== $img ? $productImg : '';
$hasImage  = $img !== '';
$today     = date('Y-m-d');
$d         = $p['delivery_date']     ?? null;
$placeholder = defined('PLACEHOLDER_DATE') ? PLACEHOLDER_DATE : '2025-05-15';
$showDate = $d !== null && $d !== $placeholder;
$preorderDateKnown = $showDate;
$preorderDateText = $preorderDateKnown ? date('d.m.Y', strtotime((string)$d)) : '';
$active    = (int)($p['is_active']    ?? 0);
$price     = floatval($p['price']     ?? 0); // current sale price per box
$sale      = floatval($p['sale_price']?? 0); // sale price per kg
$boxSize   = floatval($p['box_size']  ?? 0);
$boxUnit   = $p['box_unit']           ?? '';

$regularBox = $price;
$regularKg  = $boxSize > 0 ? round($regularBox / $boxSize, 2) : round($regularBox, 2);
$effectiveKg = $sale > 0 ? $sale : $regularKg;
$priceBox   = $sale > 0 ? ($sale * $boxSize) : $regularBox;
$pricePerKg = round($effectiveKg, 2);
$currentPriceBoxForEdit = (float)($p['current_price_per_box'] ?? 0);
if ($currentPriceBoxForEdit <= 0) {
    $currentPriceBoxForEdit = $regularBox;
}
$role     = $_SESSION['role'] ?? '';
$isStaff  = in_array($role, ['admin','manager'], true);
$isRegularViewer = in_array($role, ['', 'client'], true);
$hidePriceForPreorder = (!$showDate && $isRegularViewer);
$basePath = $role === 'manager' ? '/manager' : '/admin';
$preorderPurchaseDate = !empty($p['latest_purchase_date']) ? date('d.m.Y', strtotime((string)$p['latest_purchase_date'])) : '';
$cardSection = (string)($cardSection ?? '');
$isPreorderSection = $cardSection === 'preorder';
$isSaleSection = $cardSection === 'sale';
$isInStockSection = $cardSection === 'in_stock';
$hasPlannedBatch = (int)($p['has_planned_batch'] ?? 0) === 1;
$preorderDiscountPercent = (float)(get_setting('ui_preorder_discount_percent', '10') ?? '10');
$preorderDiscountPercent = max(0.0, min(99.0, $preorderDiscountPercent));
$discountFactor = (100 - $preorderDiscountPercent) / 100;
$batchPreorderBox = (float)($p['preorder_price_per_box'] ?? 0);
$preorderDiscountBox = $batchPreorderBox > 0 ? round($batchPreorderBox, 0) : round($regularBox * $discountFactor, 0);
$preorderPriceHint = (string)(get_setting('ui_preorder_price_hint', 'Цена ориентировочная, точная цена будет после поступления') ?? '');
$plannedDateRaw = $hasPlannedBatch ? (string)($p['next_planned_date'] ?? '') : '';
$showInStockBadge = $showDate && $d <= $today && !$isPreorderSection;
$showNextSupplyBadge = $hasPlannedBatch && $plannedDateRaw !== '' && !$isPreorderSection;
$nextSupplyDateText = $showNextSupplyBadge ? date('d.m.Y', strtotime($plannedDateRaw)) : '';
?>
<div class="product-card bg-white rounded-2xl shadow-lg overflow-hidden flex flex-col hover:shadow-2xl transition-shadow duration-200 sm:h-full max-w-[350px]"
     data-search="<?= htmlspecialchars($search) ?>"
     data-type="<?= htmlspecialchars($p['type_alias'] ?? '') ?>"
     data-sale="<?= ($p['sale_price'] ?? 0) > 0 ? '1' : '0' ?>"
     data-base-box="<?= $sale > 0 ? $priceBox : $regularBox ?>"
     data-base-kg="<?= $sale > 0 ? $pricePerKg : $regularKg ?>">
  <div class="relative">
    <?php if ($hasImage): ?>
      <a href="/catalog/<?= urlencode($p['type_alias']) ?>/<?= urlencode($p['alias']) ?>">
        <img src="<?= htmlspecialchars($img) ?>"
             alt="<?= htmlspecialchars($p['product'] ?? '') ?>"
             <?php if ($imageFallback !== ''): ?>
             onerror="this.onerror=null;this.src='<?= htmlspecialchars($imageFallback, ENT_QUOTES) ?>'"
             <?php endif; ?>
             class="w-full object-cover product-image" style="aspect-ratio:1/1">
      </a>
    <?php else: ?>
      <div class="w-full product-image product-image-placeholder flex flex-col items-center justify-center" style="aspect-ratio:1/1">
        <span class="material-icons-round text-4xl accent-text mb-1">image</span>
        <span class="text-sm accent-text">изображение подгружается</span>
      </div>
    <?php endif; ?>

    <?php if (!$active): ?>
      <!-- Товар отключён в админке -->
      <span class="absolute top-3 left-3 bg-gray-400 text-white text-xs font-semibold px-2 py-1 rounded-full opacity-80">
        Не активен
      </span>
    <?php else: ?>
      <!-- Бейджик даты / наличия -->
      <?php if ($showInStockBadge): ?>
        <span class="absolute top-3 left-3 text-[11px] font-semibold px-2 py-1 rounded-full border border-[#C86052] bg-white text-[#C86052] inline-flex items-center gap-1 <?= $isStaff ? 'cursor-pointer' : '' ?>" <?= $isStaff ? 'data-edit-date="' . $p['id'] . '"' : '' ?>>
          <span class="material-icons-round text-[13px] leading-none">storefront</span>
          <span>В магазине</span>
        </span>
      <?php elseif ($showDate): ?>
        <span class="availability-badge availability-badge--date absolute top-3 left-3 text-xs font-semibold <?= $isStaff ? 'cursor-pointer' : '' ?>" <?= $isStaff ? 'data-edit-date="' . $p['id'] . '"' : '' ?>>
          <?= date('d.m.Y', strtotime($d)) ?>
        </span>
      <?php else: ?>
        <span class="availability-badge availability-badge--placeholder absolute top-3 left-3 text-xs font-semibold <?= $isStaff ? 'cursor-pointer' : '' ?>" <?= $isStaff ? 'data-edit-date="' . $p['id'] . '"' : '' ?>>
          нет даты
        </span>
      <?php endif; ?>

      <?php if ($showNextSupplyBadge): ?>
        <span class="absolute top-3 right-3 text-[11px] font-semibold px-2 py-1 rounded-full border border-emerald-500 bg-white text-emerald-700 inline-flex items-center gap-1">
          <span class="material-icons-round text-[13px] leading-none">local_shipping</span>
          <span><?= htmlspecialchars($nextSupplyDateText) ?></span>
        </span>
      <?php endif; ?>

      <?php if ($isStaff): ?>
        <div class="absolute top-10 left-3 bg-white border rounded shadow p-2 z-10 hidden" data-date-form="<?= $p['id'] ?>">
          <form action="<?= $basePath ?>/products/update-date" method="post" class="flex items-center space-x-2">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= $p['id'] ?>">
            <input type="date" name="delivery_date" value="<?= htmlspecialchars($d ?? '') ?>" class="border px-1 py-1 rounded text-sm">
            <button type="submit" class="bg-blue-500 text-white rounded px-2 py-1 text-xs">Обновить закупку</button>
          </form>
          <p class="mt-1 text-[10px] text-gray-500">Изменяется дата активной закупки.</p>
        </div>
      <?php endif; ?>

      <!-- Бейджик скидки (если есть) -->
      <?php if ($sale > 0 && $price > 0): 
        $percent = round((($price - $sale) / $price) * 100);
      ?>
        <span class="absolute top-12 left-3 bg-red-100 text-red-800 text-xs font-semibold px-2 py-1 rounded-full">
          −<?= $percent ?>%
        </span>
      <?php endif; ?>
    <?php endif; ?>
  </div>

  <div class="p-3 sm:p-4 flex-1 flex flex-col">
    <!-- Название и сорт -->
    <div class="mb-2">
      <?php $boxLabel = htmlspecialchars($boxSize . ' ' . $boxUnit); ?>
      <h3 class="text-sm sm:text-lg font-semibold text-gray-800">
        <a href="/catalog/<?= urlencode($p['type_alias']) ?>/<?= urlencode($p['alias']) ?>" class="hover:underline">
          <?= htmlspecialchars($p['product']      ?? '') ?>
          <?php if (!empty($p['variety'])): ?>
            <?= ' ' . htmlspecialchars($p['variety']) ?>
          <?php endif; ?>
          <?php if ($boxSize > 0 && $boxUnit !== ''): ?>
            <?= ' (' . $boxLabel . ')' ?>
          <?php endif; ?>
        </a>
      </h3>
    </div>

    <!-- Описание (если есть) -->
    <?php if (!empty($p['description'])): ?>
      <p class="hidden sm:block text-xs sm:text-sm text-gray-600 mb-1">
        <?= htmlspecialchars($p['description']) ?>
      </p>
    <?php endif; ?>

    <div class="text-[10px] sm:text-xs text-gray-500 mb-2">Продавец: <?= htmlspecialchars($p['seller_name'] ?? 'berryGo') ?></div>

    <!-- ───────────── БЛОК ЦЕНЫ И КНОПОК ───────────── -->
    <div class="mt-auto pt-3 border-t border-gray-100">

      <!-- Цена -->
      <?php if ($hidePriceForPreorder): ?>
        <p class="text-base font-semibold text-gray-400 mb-3">Цена уточняется</p>

      <?php elseif ($isPreorderSection): ?>
        <div class="flex items-end gap-2 mb-1">
          <span class="text-base text-gray-400 line-through leading-none pb-0.5 <?= $isStaff ? 'cursor-pointer' : '' ?>"
                <?= $isStaff ? 'data-edit-price="' . $p['id'] . '"' : '' ?>>
            <?= number_format($regularBox, 0, '.', ' ') ?> ₽
          </span>
          <span class="text-xl sm:text-2xl font-bold text-gray-900 box-price leading-none <?= $isStaff ? 'cursor-pointer' : '' ?>"
                <?= $isStaff ? 'data-edit-price="' . $p['id'] . '"' : '' ?>>
            <?= number_format($preorderDiscountBox, 0, '.', ' ') ?> ₽
          </span>
        </div>
        <p class="text-[10px] leading-tight text-red-500 mb-3"><?= htmlspecialchars($preorderPriceHint) ?></p>

      <?php elseif ($sale > 0): ?>
        <!-- Акционная цена -->
        <div class="flex items-end gap-2 mb-1">
          <span class="text-xl sm:text-2xl font-bold text-gray-900 box-price leading-none <?= $isStaff ? 'cursor-pointer' : '' ?>"
                <?= $isStaff ? 'data-edit-price="' . $p['id'] . '"' : '' ?>>
            <?= number_format($priceBox, 0, '.', ' ') ?> ₽
          </span>
          <span class="text-sm text-gray-400 line-through leading-none pb-0.5">
            <?= number_format($regularBox, 0, '.', ' ') ?> ₽
          </span>
        </div>
        <p class="text-xs text-gray-400 mb-3 kg-price <?= $isStaff ? 'cursor-pointer' : '' ?>"
           <?= $isStaff ? 'data-edit-price="' . $p['id'] . '"' : '' ?>>
          <?= htmlspecialchars($pricePerKg) ?> ₽/кг
        </p>

      <?php else: ?>
        <!-- Обычная цена -->
        <div class="flex items-end justify-between mb-1">
          <span class="text-xl sm:text-2xl font-bold text-gray-900 box-price leading-none <?= $isStaff ? 'cursor-pointer' : '' ?>"
                <?= $isStaff ? 'data-edit-price="' . $p['id'] . '"' : '' ?>>
            <?= number_format($regularBox, 0, '.', ' ') ?> ₽
          </span>
          <span class="text-xs text-gray-400 kg-price leading-none pb-0.5 <?= $isStaff ? 'cursor-pointer' : '' ?>"
                <?= $isStaff ? 'data-edit-price="' . $p['id'] . '"' : '' ?>>
            <?= htmlspecialchars($regularKg) ?> ₽/кг
          </span>
        </div>
        <div class="mb-3"></div>
      <?php endif; ?>

      <!-- Форма редактирования закупочной цены (только стафф) -->
      <?php if ($isStaff): ?>
        <div class="mt-2 hidden" data-price-form="<?= $p['id'] ?>">
          <form action="<?= $basePath ?>/products/update-price" method="post" class="flex items-center space-x-2">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= $p['id'] ?>">
            <input type="hidden" name="purchase_batch_id" value="<?= (int)($p['purchase_batch_id'] ?? 0) ?>">
            <input type="hidden" name="price_context" value="<?= htmlspecialchars($isPreorderSection ? 'preorder' : ($isInStockSection ? 'in_stock' : $cardSection)) ?>">
            <input type="number" step="0.01" name="price" value="<?= htmlspecialchars((string)($isPreorderSection ? $preorderDiscountBox : $currentPriceBoxForEdit)) ?>" class="border px-1 py-1 rounded text-sm w-24" title="Цена сейчас за позицию">
            <button type="submit" class="bg-blue-500 text-white rounded px-2 py-1 text-xs">Обновить цену сейчас</button>
          </form>
          <p class="mt-1 text-[10px] text-gray-500"><?= $isPreorderSection ? 'В предзаказе обновляется цена брони активных закупок и будущая цена planned-закупок до скидки.' : 'Изменяется поле «цена сейчас» активной позиции (за позицию).' ?></p>
        </div>
      <?php endif; ?>

      <!-- Кнопки действий -->
      <?php if (in_array((string)($_SESSION['role'] ?? ''), ['client','partner','manager','seller','admin']) && $active): ?>

        <form action="/cart/add" method="post"
              class="add-to-cart-form"
              data-id="<?= $p['id'] ?>"
              data-name="<?= htmlspecialchars($p['product'] . ($p['variety'] ? ' ' . $p['variety'] : '')) ?>"
              data-price="<?= $priceBox ?>">
          <?= csrf_field() ?>

          <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
          <input type="hidden" name="stock_mode" value="instant">

          <!-- Строка: qty-stepper + кнопка корзины -->
          <div class="flex items-center gap-2 mb-2">
            <!-- Qty stepper -->
            <div class="flex items-center gap-1 h-10 shrink-0">
              <button type="button"
                      class="w-9 h-9 rounded-full border border-gray-200 flex items-center justify-center text-gray-600 hover:bg-gray-50 active:bg-gray-100 transition-colors"
                      onclick="let inp=this.nextElementSibling; if(+inp.value>1) inp.value=+inp.value-1;">
                <span class="material-icons-round text-base leading-none">remove</span>
              </button>
              <input type="number"
                     name="quantity"
                     value="1"
                     min="1"
                     step="1"
                     class="w-8 text-center text-lg font-bold bg-transparent border-0 focus:outline-none preorder-qty" />
              <button type="button"
                      class="w-9 h-9 rounded-full border border-gray-200 flex items-center justify-center text-gray-600 hover:bg-gray-50 active:bg-gray-100 transition-colors"
                      onclick="let inp=this.previousElementSibling; inp.value=+inp.value+1;">
                <span class="material-icons-round text-base leading-none">add</span>
              </button>
            </div>

            <!-- Кнопка «В корзину» — растягивается на всю оставшуюся ширину -->
            <button type="submit"
                    <?= $isPreorderSection ? 'disabled' : '' ?>
                    class="flex-1 h-10 flex items-center justify-center gap-1.5 <?= $isPreorderSection ? 'bg-gray-100 text-gray-400 cursor-not-allowed' : 'bg-gradient-to-r from-red-500 to-pink-500 accent-gradient text-white hover:opacity-90 active:opacity-80' ?> font-semibold text-sm rounded-xl transition-opacity shrink-0">
              <span class="material-icons-round text-base leading-none">add_shopping_cart</span>
              <span class="hidden sm:inline">В корзину</span>
            </button>
          </div>
        </form>

        <!-- Предзаказ — вторичное действие: outline-стиль, меньше веса -->
        <button type="button"
                <?= $isSaleSection ? 'disabled' : '' ?>
                class="w-full h-9 flex items-center justify-center gap-1.5 border font-medium text-xs sm:text-sm rounded-xl transition-colors preorder-intent-btn <?= $isSaleSection ? 'border-gray-200 text-gray-400 bg-gray-50 cursor-not-allowed' : 'border-emerald-500 text-emerald-700 hover:bg-emerald-50 active:bg-emerald-100' ?>"
                data-product-id="<?= (int)$p['id'] ?>"
                data-product-title="<?= htmlspecialchars(trim(($p['product'] ?? '') . ' ' . ($p['variety'] ?? ''))) ?>"
                data-preorder-price="<?= (float)$preorderDiscountBox ?>"
                data-preorder-discount="<?= (float)$preorderDiscountPercent ?>"
                data-source-section="<?= htmlspecialchars($cardSection) ?>"
                data-delivery-date="<?= htmlspecialchars((string)($d ?? '')) ?>"
                data-planned-date="<?= htmlspecialchars($plannedDateRaw) ?>">
          <span class="material-icons-round text-base leading-none">schedule</span>
          Предзаказ −10%
        </button>

        <?php if (!$isSaleSection && !$showNextSupplyBadge): ?>
          <p class="mt-1.5 text-[11px] text-gray-400 text-center">Дата следующей поставки уточняется</p>
        <?php elseif ($preorderPurchaseDate !== ''): ?>
          <p class="mt-1.5 text-[11px] text-gray-400 text-center">Дата закупки: <?= htmlspecialchars($preorderPurchaseDate) ?></p>
        <?php endif; ?>

        <p class="mt-1.5 text-[11px] text-gray-400 hidden preorder-intent-hint"></p>

      <?php else: ?>
        <!-- Гость или неактивный товар -->
        <?php if (!empty($_SESSION['user_id']) && !$active): ?>
          <button disabled
                  class="w-full h-10 bg-gray-100 text-gray-400 text-sm rounded-xl cursor-not-allowed">
            Товар недоступен
          </button>
        <?php else: ?>
          <a href="/login"
             class="w-full h-10 flex items-center justify-center gap-1.5 bg-gradient-to-r from-red-500 to-pink-500 accent-gradient text-white font-semibold text-sm rounded-xl transition-opacity hover:opacity-90">
            <span class="material-icons-round text-base">login</span>
            Войдите, чтобы заказать
          </a>
        <?php endif; ?>
      <?php endif; ?>

    </div>
    <!-- ───────────── / БЛОК ЦЕНЫ И КНОПОК ───────────── -->

  </div>
</div>
<script>
  (function() {
    const root = document.currentScript.previousElementSibling;
    if (!root) return;
    const btn = root.querySelector('.preorder-intent-btn');
    const qtyInput = root.querySelector('.preorder-qty');
    const hint = root.querySelector('.preorder-intent-hint');
    if (!btn) return;

    const toIso = (d) => {
      const y = d.getFullYear();
      const m = String(d.getMonth() + 1).padStart(2, '0');
      const day = String(d.getDate()).padStart(2, '0');
      return `${y}-${m}-${day}`;
    };

    const todayDate = new Date();
    todayDate.setHours(0, 0, 0, 0);
    const minPreorderDate = new Date(todayDate);
    minPreorderDate.setDate(todayDate.getDate() + 2);
    const minPreorderIso = toIso(minPreorderDate);
    const basePlanned = btn.dataset.plannedDate || btn.dataset.deliveryDate || '';
    const plannedDate = basePlanned ? new Date(basePlanned + 'T00:00:00') : null;
    const defaultDate = plannedDate && !Number.isNaN(plannedDate.getTime()) && plannedDate >= minPreorderDate
      ? toIso(plannedDate)
      : minPreorderIso;

    const overlay = document.createElement('div');
    overlay.className = 'fixed inset-0 z-[120] bg-black/40 hidden items-center justify-center p-2 sm:p-4';
    overlay.innerHTML = `
      <div class="bg-white w-full max-w-[360px] sm:max-w-md rounded-2xl p-3 sm:p-5">
        <h3 class="text-base sm:text-lg font-bold mb-1.5 preorder-modal-title"></h3>
        <p class="text-xs sm:text-sm text-gray-600 mb-2.5">Ориентировочная цена с учетом скидки -10%</p>
        <div class="flex items-center justify-between mb-2">
          <span class="text-xs sm:text-sm">Количество:</span>
          <div class="flex items-center rounded-lg border border-gray-200 overflow-hidden">
            <button type="button" class="px-2.5 py-1 preorder-minus">−</button>
            <input type="number" min="1" step="1" value="1" class="w-10 text-center preorder-modal-qty" />
            <button type="button" class="px-2.5 py-1 preorder-plus">+</button>
          </div>
        </div>
        <p class="text-xl sm:text-2xl font-bold mb-2.5 preorder-modal-price"></p>
        <label class="block text-xs sm:text-sm mb-1" for="preorder-modal-date-${btn.dataset.productId || '0'}">Дата получения предзаказа:</label>
        <input id="preorder-modal-date-${btn.dataset.productId || '0'}" type="date" min="${minPreorderIso}" value="${defaultDate}" class="w-full h-10 rounded-xl border border-gray-200 px-3 text-sm preorder-modal-date mb-3">
        <div class="flex gap-2">
          <button type="button" class="flex-1 h-9 sm:h-10 rounded-xl border border-gray-200 preorder-cancel text-sm">Отмена</button>
          <button type="button" class="flex-1 h-9 sm:h-10 rounded-xl text-white bg-emerald-600 preorder-submit text-sm">Забронировать</button>
        </div>
      </div>`;
    document.body.appendChild(overlay);

    const modalQty = overlay.querySelector('.preorder-modal-qty');
    const modalTitle = overlay.querySelector('.preorder-modal-title');
    const modalPrice = overlay.querySelector('.preorder-modal-price');
    const dateInput = overlay.querySelector('.preorder-modal-date');
    const submitBtn = overlay.querySelector('.preorder-submit');

    const closeModal = () => overlay.classList.add('hidden');
    overlay.querySelector('.preorder-cancel').addEventListener('click', closeModal);
    overlay.addEventListener('click', (e) => { if (e.target === overlay) closeModal(); });
    overlay.querySelector('.preorder-minus').addEventListener('click', () => { modalQty.value = String(Math.max(1, parseInt(modalQty.value || '1', 10) - 1)); });
    overlay.querySelector('.preorder-plus').addEventListener('click', () => { modalQty.value = String(Math.max(1, parseInt(modalQty.value || '1', 10) + 1)); });

    btn.addEventListener('click', async () => {
      modalTitle.textContent = `Предзаказ ${btn.dataset.productTitle || ''}`;
      modalPrice.textContent = `${Math.round(parseFloat(btn.dataset.preorderPrice || '0')).toLocaleString('ru-RU')} ₽`;
      modalQty.value = qtyInput ? String(Math.max(1, parseFloat(qtyInput.value || '1'))) : '1';
      if (dateInput) dateInput.value = defaultDate;
      overlay.classList.remove('hidden');
    });

    submitBtn.addEventListener('click', async () => {
      const qty = parseFloat(modalQty.value || '1');
      const payload = new URLSearchParams();
      payload.set('product_id', btn.dataset.productId || '0');
      payload.set('requested_boxes', String(qty > 0 ? qty : 1));
      payload.set('source_section', btn.dataset.sourceSection || '');
      payload.set('source_delivery_date', btn.dataset.deliveryDate || '');
      payload.set('desired_delivery_date', dateInput?.value || minPreorderIso);
      payload.set('expected_price_per_box', btn.dataset.preorderPrice || '0');
      payload.set('discount_percent_snapshot', btn.dataset.preorderDiscount || '10');
      const res = await fetch('/preorder-intents', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: payload.toString()
      });
      const data = await res.json();
      if (hint) {
        hint.classList.remove('hidden');
        hint.textContent = data?.message || 'Предзаказ сохранён';
      }
      if (data?.ok && data?.cart_url) {
        window.location.href = data.cart_url;
      }
      closeModal();
    });
  })();
</script>
