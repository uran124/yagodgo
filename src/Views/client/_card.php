<?php
/** @var array<string,mixed> $p */
$search = mb_strtolower(trim((string)($p['product'] ?? '') . ' ' . (string)($p['variety'] ?? '')), 'UTF-8');
$img = trim((string)($p['image_path'] ?? ''));
$productImg = trim((string)($p['product_image_path'] ?? ''));
$imageFallback = $productImg !== '' && $productImg !== $img ? $productImg : '';
$hasImage = $img !== '';
$active = (int)($p['is_active'] ?? 0) === 1;
$boxSize = (float)($p['box_size'] ?? 0);
$boxUnit = trim((string)($p['box_unit'] ?? ''));
$boxLabel = trim(rtrim(rtrim(number_format($boxSize, 2, '.', ''), '0'), '.') . ' ' . $boxUnit);
$productTitle = trim((string)($p['product'] ?? '') . ' ' . (string)($p['variety'] ?? ''));

$role = (string)($_SESSION['role'] ?? '');
$isStaff = in_array($role, ['admin', 'manager'], true);
$isLoggedIn = !empty($_SESSION['user_id']) && in_array($role, ['client', 'partner', 'manager', 'seller', 'admin'], true);
$basePath = $role === 'manager' ? '/manager' : '/admin';
$cardSection = (string)($cardSection ?? ($p['catalog_section'] ?? ''));

$regularPrice = (float)($p['regular_price'] ?? $p['price'] ?? 0);
$expectedPreorderPrice = (float)($p['expected_preorder_price'] ?? 0);
$instantPriceBox = (float)($p['instant_price_per_box'] ?? 0);
$instantAvailableBoxes = max(0, (int)floor((float)($p['instant_available_boxes'] ?? 0)));
$discountPriceBox = (float)($p['available_discount_price_per_box'] ?? 0);
$discountAvailableBoxes = max(0, (int)floor((float)($p['discount_available_boxes'] ?? 0)));
$isDiscountOffer = $cardSection === 'sale' && $discountPriceBox > 0 && $discountAvailableBoxes > 0;
$buyPriceBox = $isDiscountOffer ? $discountPriceBox : $instantPriceBox;
$buyAvailableBoxes = $isDiscountOffer ? $discountAvailableBoxes : $instantAvailableBoxes;
$buyStockMode = $isDiscountOffer ? 'discount_stock' : 'instant';
$preorderAvailableBoxes = max(0, (int)floor((float)($p['preorder_available_boxes'] ?? 0)));
$canBuyNow = $active && $buyPriceBox > 0 && $buyAvailableBoxes > 0;
$canPreorder = $active && (int)($p['can_preorder'] ?? 0) === 1 && $expectedPreorderPrice > 0;
$preorderDiscountPercent = (float)(get_setting('ui_preorder_discount_percent', '10') ?? '10');
$preorderDiscountPercent = max(0, min(99, $preorderDiscountPercent));
$preorderMax = $preorderAvailableBoxes > 0 ? $preorderAvailableBoxes : 99;
$quantityMax = max(1, $buyAvailableBoxes, $preorderMax);

$confirmedPreorderDate = trim((string)($p['preorder_availability_date'] ?? ''));
$deliveryDate = trim((string)($p['delivery_date'] ?? ''));
$today = date('Y-m-d');
$badgeText = '';
$badgeClass = '';
if ($active && $canBuyNow) {
    $badgeText = 'В наличии';
    $badgeClass = 'availability-badge--instock';
} elseif ($active && $canPreorder && $confirmedPreorderDate !== '') {
    $badgeText = 'Предзаказ · ' . date('d.m', strtotime($confirmedPreorderDate));
    $badgeClass = 'availability-badge--date';
} elseif ($active && $canPreorder) {
    $badgeText = 'Предзаказ';
    $badgeClass = 'availability-badge--date';
}

$currentPriceForEdit = $cardSection === 'preorder' ? $expectedPreorderPrice : ($instantPriceBox > 0 ? $instantPriceBox : $regularPrice);
?>
<div class="product-card bg-white rounded-2xl shadow-lg overflow-hidden flex flex-col hover:shadow-2xl transition-shadow duration-200 sm:h-full max-w-[350px]"
     data-product-card
     data-search="<?= htmlspecialchars($search) ?>"
     data-type="<?= htmlspecialchars((string)($p['type_alias'] ?? '')) ?>"
     data-sale="<?= ((float)($p['sale_price'] ?? 0)) > 0 ? '1' : '0' ?>"
     data-buy-max="<?= $buyAvailableBoxes ?>"
     data-preorder-max="<?= $preorderMax ?>">
  <div class="relative">
    <?php if ($hasImage): ?>
      <a href="/catalog/<?= urlencode((string)($p['type_alias'] ?? '')) ?>/<?= urlencode((string)($p['alias'] ?? '')) ?>">
        <img src="<?= htmlspecialchars($img) ?>"
             alt="<?= htmlspecialchars($productTitle) ?>"
             <?php if ($imageFallback !== ''): ?>onerror="this.onerror=null;this.src='<?= htmlspecialchars($imageFallback, ENT_QUOTES) ?>'"<?php endif; ?>
             class="w-full object-cover product-image" style="aspect-ratio:1/1">
      </a>
    <?php else: ?>
      <div class="w-full product-image product-image-placeholder flex flex-col items-center justify-center" style="aspect-ratio:1/1">
        <span class="material-icons-round text-4xl accent-text mb-1">image</span>
        <span class="text-sm accent-text">изображение подгружается</span>
      </div>
    <?php endif; ?>

    <?php if (!$active): ?>
      <span class="absolute top-3 left-3 bg-gray-400 text-white text-xs font-semibold px-2 py-1 rounded-full opacity-80">Не активен</span>
    <?php elseif ($badgeText !== ''): ?>
      <span class="availability-badge <?= htmlspecialchars($badgeClass) ?> absolute top-3 left-3 text-xs font-semibold <?= $isStaff ? 'cursor-pointer' : '' ?>"
            <?= $isStaff ? 'data-edit-date="' . (int)$p['id'] . '"' : '' ?>>
        <?= htmlspecialchars($badgeText) ?>
      </span>
    <?php endif; ?>

    <?php if ($isStaff && $active && empty($p['seller_id'])): ?>
      <div class="absolute top-10 left-3 bg-white border rounded shadow p-2 z-10 hidden" data-date-form="<?= (int)$p['id'] ?>">
        <form action="<?= $basePath ?>/products/update-date" method="post" class="flex items-center space-x-2">
          <?= csrf_field() ?>
          <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
          <input type="date" name="delivery_date" value="<?= htmlspecialchars($deliveryDate) ?>" class="border px-1 py-1 rounded text-sm">
          <button type="submit" class="bg-blue-500 text-white rounded px-2 py-1 text-xs">Обновить дату</button>
        </form>
      </div>
    <?php endif; ?>
  </div>

  <div class="p-3 sm:p-4 flex-1 flex flex-col">
    <div class="mb-2">
      <h3 class="text-sm sm:text-lg font-semibold text-gray-800 mt-0 mb-0">
        <a href="/catalog/<?= urlencode((string)($p['type_alias'] ?? '')) ?>/<?= urlencode((string)($p['alias'] ?? '')) ?>" class="hover:underline">
          <?= htmlspecialchars($productTitle) ?><?= $boxLabel !== '' ? ' (' . htmlspecialchars($boxLabel) . ')' : '' ?>
        </a>
      </h3>
    </div>

    <?php if (!empty($p['description'])): ?>
      <p class="hidden sm:block text-xs sm:text-sm text-gray-600 mb-1"><?= htmlspecialchars((string)$p['description']) ?></p>
    <?php endif; ?>

    <div class="text-[10px] sm:text-xs text-gray-500 mb-2">Продавец: <?= htmlspecialchars((string)($p['seller_name'] ?? 'berryGo')) ?></div>

    <div class="product-card-actions mt-auto pt-3 border-t border-gray-100">
      <div class="product-card-price-row">
        <div class="product-card-price-block <?= $isStaff ? 'cursor-pointer' : '' ?>" <?= $isStaff ? 'data-edit-price="' . (int)$p['id'] . '"' : '' ?>>
          <?php if ($canBuyNow && $buyPriceBox > 0): ?>
            <div class="product-card-current-price"><?= number_format($buyPriceBox, 0, '.', ' ') ?> ₽</div>
            <?php if ($canPreorder): ?>
              <div class="product-card-preorder-price">Предзаказ: <span><?= number_format($expectedPreorderPrice, 0, '.', ' ') ?> ₽*</span></div>
            <?php endif; ?>
          <?php elseif ($canPreorder): ?>
            <div class="product-card-price-pair">
              <span class="product-card-regular-price"><?= number_format($regularPrice, 0, '.', ' ') ?> ₽</span>
              <span class="product-card-expected-price"><?= number_format($expectedPreorderPrice, 0, '.', ' ') ?> ₽*</span>
            </div>
          <?php elseif ($regularPrice > 0): ?>
            <div class="product-card-current-price"><?= number_format($regularPrice, 0, '.', ' ') ?> ₽</div>
          <?php else: ?>
            <div class="product-card-price-pending">Цена уточняется</div>
          <?php endif; ?>
        </div>

        <div class="product-card-quantity" data-card-qty data-max="<?= $quantityMax ?>">
          <button type="button" aria-label="Уменьшить количество" data-qty-minus>−</button>
          <input type="number" value="1" min="1" max="<?= $quantityMax ?>" step="1" inputmode="numeric" aria-label="Количество" data-qty-input>
          <button type="button" aria-label="Увеличить количество" data-qty-plus>+</button>
        </div>
      </div>

      <?php if ($canPreorder): ?>
        <p class="product-card-price-note">* Ожидаемая цена. Точная стоимость будет подтверждена после поступления в магазин.</p>
      <?php endif; ?>

      <?php if ($isStaff): ?>
        <div class="mt-2 hidden" data-price-form="<?= (int)$p['id'] ?>">
          <form action="<?= $basePath ?>/products/update-price" method="post" class="flex items-center space-x-2">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
            <input type="hidden" name="price_context" value="<?= htmlspecialchars($cardSection === 'preorder' ? 'preorder' : 'in_stock') ?>">
            <input type="number" step="0.01" name="price" value="<?= htmlspecialchars((string)$currentPriceForEdit) ?>" class="border px-1 py-1 rounded text-sm w-24">
            <button type="submit" class="bg-blue-500 text-white rounded px-2 py-1 text-xs">Обновить</button>
          </form>
        </div>
      <?php endif; ?>

      <div class="product-card-buttons">
        <?php if ($canBuyNow && $isLoggedIn): ?>
          <form action="/cart/add" method="post"
                class="add-to-cart-form product-card-buy-form"
                data-id="<?= (int)$p['id'] ?>"
                data-name="<?= htmlspecialchars($productTitle) ?>"
                data-price="<?= $buyPriceBox ?>"
                data-buy-max="<?= $buyAvailableBoxes ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>">
            <input type="hidden" name="stock_mode" value="<?= htmlspecialchars($buyStockMode) ?>">
            <input type="hidden" name="quantity" value="1" data-cart-quantity>
            <button type="submit" class="product-card-button product-card-button--buy">
              <span class="material-icons-round">add_shopping_cart</span><span>Купить</span>
            </button>
          </form>
        <?php elseif ($canBuyNow): ?>
          <a href="/login" class="product-card-button product-card-button--buy">
            <span class="material-icons-round">add_shopping_cart</span><span>Купить</span>
          </a>
        <?php else: ?>
          <button type="button" disabled class="product-card-button product-card-button--disabled">Нет в наличии</button>
        <?php endif; ?>

        <?php if ($canPreorder && $isLoggedIn): ?>
          <button type="button"
                  class="product-card-button product-card-button--preorder preorder-intent-btn"
                  data-product-id="<?= (int)$p['id'] ?>"
                  data-product-title="<?= htmlspecialchars($productTitle) ?>"
                  data-preorder-price="<?= htmlspecialchars((string)$expectedPreorderPrice) ?>"
                  data-preorder-discount="<?= htmlspecialchars((string)$preorderDiscountPercent) ?>"
                  data-source-section="<?= htmlspecialchars($cardSection) ?>"
                  data-delivery-date="<?= htmlspecialchars($deliveryDate) ?>"
                  data-supply-date="<?= htmlspecialchars($confirmedPreorderDate) ?>"
                  data-preorder-max="<?= $preorderMax ?>"
                  data-unit="ящик">
            <span class="material-icons-round">schedule</span><span>Предзаказ −<?= number_format($preorderDiscountPercent, 0, '.', '') ?>%</span>
          </button>
        <?php elseif ($canPreorder): ?>
          <a href="/login" class="product-card-button product-card-button--preorder">
            <span class="material-icons-round">schedule</span><span>Предзаказ −<?= number_format($preorderDiscountPercent, 0, '.', '') ?>%</span>
          </a>
        <?php endif; ?>
      </div>

      <p class="product-card-message hidden" data-card-message aria-live="polite"></p>
    </div>
  </div>
</div>
