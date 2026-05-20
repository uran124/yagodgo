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
$preorderDiscountPercent = (float)(get_setting('ui_preorder_discount_percent', '10') ?? '10');
$preorderDiscountPercent = max(0.0, min(99.0, $preorderDiscountPercent));
$discountFactor = (100 - $preorderDiscountPercent) / 100;
$preorderDiscountBox = round($regularBox * $discountFactor, 0);
$preorderPriceHint = (string)(get_setting('ui_preorder_price_hint', 'Цена ориентировочная, точная цена будет после поступления') ?? '');
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
      <?php if ($showDate && $d <= $today): ?>
        <span class="availability-badge availability-badge--instock absolute top-3 left-3 text-xs font-semibold <?= $isStaff ? 'cursor-pointer' : '' ?>" <?= $isStaff ? 'data-edit-date="' . $p['id'] . '"' : '' ?>>
          В наличии
        </span>
      <?php elseif ($showDate): ?>
        <span class="availability-badge availability-badge--date absolute top-3 left-3 text-xs font-semibold <?= $isStaff ? 'cursor-pointer' : '' ?>" <?= $isStaff ? 'data-edit-date="' . $p['id'] . '"' : '' ?>>
          <?= date('d.m.Y', strtotime($d)) ?>
        </span>
      <?php else: ?>
        <span class="availability-badge availability-badge--placeholder absolute top-3 left-3 text-xs font-semibold <?= $isStaff ? 'cursor-pointer' : '' ?>" <?= $isStaff ? 'data-edit-date="' . $p['id'] . '"' : '' ?>>
          Ближайшая возможная дата
        </span>
      <?php endif; ?>

      <?php if ($isStaff): ?>
        <div class="absolute top-10 left-3 bg-white border rounded shadow p-2 z-10 hidden" data-date-form="<?= $p['id'] ?>">
          <form action="<?= $basePath ?>/products/update-date" method="post" class="flex items-center space-x-2">
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
          <span class="text-base text-gray-400 line-through leading-none pb-0.5">
            <?= number_format($regularBox, 0, '.', ' ') ?> ₽
          </span>
          <span class="text-xl sm:text-2xl font-bold text-gray-900 box-price leading-none">
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
            <input type="hidden" name="id" value="<?= $p['id'] ?>">
            <input type="number" step="0.01" name="price" value="<?= htmlspecialchars((string)$currentPriceBoxForEdit) ?>" class="border px-1 py-1 rounded text-sm w-24" title="Цена сейчас за позицию">
            <button type="submit" class="bg-blue-500 text-white rounded px-2 py-1 text-xs">Обновить цену сейчас</button>
          </form>
          <p class="mt-1 text-[10px] text-gray-500">Изменяется поле «цена сейчас» активной позиции (за позицию).</p>
        </div>
      <?php endif; ?>

      <!-- Кнопки действий -->
      <?php if (in_array((string)($_SESSION['role'] ?? ''), ['client','partner','seller','admin']) && $active): ?>

        <form action="/cart/add" method="post"
              class="add-to-cart-form"
              data-id="<?= $p['id'] ?>"
              data-name="<?= htmlspecialchars($p['product'] . ($p['variety'] ? ' ' . $p['variety'] : '')) ?>"
              data-price="<?= $priceBox ?>">

          <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
          <input type="hidden" name="stock_mode" value="instant">

          <!-- Строка: qty-stepper + кнопка корзины -->
          <div class="flex items-center gap-2 mb-2">
            <!-- Qty stepper -->
            <div class="flex items-center rounded-xl border border-gray-200 bg-gray-50 overflow-hidden h-10 shrink-0">
              <button type="button"
                      class="w-9 h-10 flex items-center justify-center text-gray-500 hover:bg-gray-100 active:bg-gray-200 transition-colors"
                      onclick="let inp=this.nextElementSibling; if(+inp.value>1) inp.value=+inp.value-1;">
                <span class="material-icons-round text-base leading-none">remove</span>
              </button>
              <input type="number"
                     name="quantity"
                     value="1"
                     min="1"
                     step="1"
                     class="w-10 h-10 text-center text-sm font-medium bg-transparent border-x border-gray-200 focus:outline-none preorder-qty" />
              <button type="button"
                      class="w-9 h-10 flex items-center justify-center text-gray-500 hover:bg-gray-100 active:bg-gray-200 transition-colors"
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
                <?= ($isSaleSection || !$preorderDateKnown) ? 'disabled' : '' ?>
                class="w-full h-9 flex items-center justify-center gap-1.5 border font-medium text-xs sm:text-sm rounded-xl transition-colors preorder-intent-btn <?= ($isSaleSection || !$preorderDateKnown) ? 'border-gray-200 text-gray-400 bg-gray-50 cursor-not-allowed' : 'border-emerald-500 text-emerald-700 hover:bg-emerald-50 active:bg-emerald-100' ?>"
                data-product-id="<?= (int)$p['id'] ?>"
                data-source-section="<?= htmlspecialchars($cardSection) ?>"
                data-delivery-date="<?= htmlspecialchars((string)($d ?? '')) ?>">
          <span class="material-icons-round text-base leading-none">schedule</span>
          <?= $isInStockSection ? 'Предзаказ' : 'Предзаказ −10%' ?>
        </button>

        <?php if ($preorderDateKnown): ?>
          <p class="mt-1.5 text-[11px] text-gray-400 text-center">Следующая поставка: <?= htmlspecialchars($preorderDateText) ?></p>
        <?php elseif (!$isSaleSection): ?>
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
    btn.addEventListener('click', async () => {
      const qty = qtyInput ? parseFloat(qtyInput.value || '1') : 1;
      const payload = new URLSearchParams();
      payload.set('product_id', btn.dataset.productId || '0');
      payload.set('requested_boxes', String(qty > 0 ? qty : 1));
      payload.set('source_section', btn.dataset.sourceSection || '');
      payload.set('source_delivery_date', btn.dataset.deliveryDate || '');
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
    });
  })();
</script>
