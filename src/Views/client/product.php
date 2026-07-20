<?php /** @var array<string,mixed> $product */ ?>
<?php
$active = (int)($product['is_active'] ?? 0) === 1;
$role = (string)($_SESSION['role'] ?? '');
$isLoggedIn = !empty($_SESSION['user_id']) && in_array($role, ['client', 'partner', 'manager', 'seller', 'admin'], true);

$catalogSection = (string)($product['catalog_section'] ?? '');
$regularPrice = (float)($product['regular_price'] ?? $product['price'] ?? 0);
$expectedPreorderPrice = (float)($product['expected_preorder_price'] ?? 0);
$instantPriceBox = (float)($product['instant_price_per_box'] ?? 0);
$instantAvailableBoxes = max(0, (int)floor((float)($product['instant_available_boxes'] ?? 0)));
$discountPriceBox = (float)($product['available_discount_price_per_box'] ?? 0);
$discountAvailableBoxes = max(0, (int)floor((float)($product['discount_available_boxes'] ?? 0)));
$isDiscountOffer = $catalogSection === 'sale' && $discountPriceBox > 0 && $discountAvailableBoxes > 0;
$buyPriceBox = $isDiscountOffer ? $discountPriceBox : $instantPriceBox;
$buyAvailableBoxes = $isDiscountOffer ? $discountAvailableBoxes : $instantAvailableBoxes;
$buyStockMode = $isDiscountOffer ? 'discount_stock' : 'instant';
$preorderAvailableBoxes = max(0, (int)floor((float)($product['preorder_available_boxes'] ?? 0)));
$canBuyNow = $active && $buyPriceBox > 0 && $buyAvailableBoxes > 0;
$canPreorder = $active && (int)($product['can_preorder'] ?? 0) === 1 && $expectedPreorderPrice > 0;

$preorderDiscountPercent = function_exists('get_setting')
    ? (float)(get_setting('ui_preorder_discount_percent', '10') ?? '10')
    : 10.0;
$preorderDiscountPercent = max(0, min(99, $preorderDiscountPercent));
$preorderMax = $preorderAvailableBoxes > 0 ? $preorderAvailableBoxes : 99;
$quantityMax = max(1, $buyAvailableBoxes, $preorderMax);
$confirmedPreorderDate = trim((string)($product['preorder_availability_date'] ?? ''));
$sourceDeliveryDate = trim((string)($product['delivery_date'] ?? ''));
$schemaPrice = $canBuyNow ? $buyPriceBox : $regularPrice;
$boxSize = (float)($product['box_size'] ?? 0);
$boxUnit = trim((string)($product['box_unit'] ?? ''));
$productTitle = trim((string)($product['product'] ?? '') . ' ' . (string)($product['variety'] ?? ''));
?>
<main class="bg-gradient-to-br from-orange-50 via-white to-pink-50 min-h-screen pb-52 md:pb-24">
  <article class="max-w-screen-md mx-auto" itemscope itemtype="https://schema.org/Product">

    <?php if (!empty($product['image_path'])): ?>
      <div class="md:px-4 md:pt-6">
        <img src="<?= htmlspecialchars((string)$product['image_path']) ?>"
             alt="<?= htmlspecialchars($productTitle) ?>"
             class="w-full object-cover md:rounded-2xl md:shadow-lg product-image"
             style="aspect-ratio:1/1; max-height:420px; object-fit:cover"
             itemprop="image">
      </div>
    <?php endif; ?>

    <div class="px-4 pt-4 md:pt-6 md:flex md:space-x-6">
      <div class="md:w-1/2 space-y-3 md:space-y-4">
        <h1 class="text-xl sm:text-2xl md:text-3xl font-bold text-gray-900 leading-snug" itemprop="name">
          <?= htmlspecialchars((string)($product['product'] ?? '')) ?>
          <?php if (!empty($product['variety'])): ?>
            <?= ' ' . htmlspecialchars((string)$product['variety']) ?>
          <?php endif; ?>
          <?php if ($boxSize > 0 && $boxUnit !== ''): ?>
            <span class="font-normal text-gray-500"> (<?= htmlspecialchars(rtrim(rtrim(number_format($boxSize, 2, '.', ''), '0'), '.') . ' ' . $boxUnit) ?>)</span>
          <?php endif; ?>
        </h1>
        <meta itemprop="brand" content="<?= htmlspecialchars((string)($product['manufacturer'] ?? 'BerryGo')) ?>">

        <?php
        $composition = [];
        if (!empty($product['composition'])) {
            $decoded = json_decode((string)$product['composition'], true);
            if (is_array($decoded)) {
                $composition = $decoded;
            }
        }
        ?>
        <?php if ($composition): ?>
          <div class="bg-white rounded-xl px-4 py-3 shadow-sm">
            <h2 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Состав</h2>
            <ul class="space-y-1">
              <?php foreach ($composition as $item): ?>
                <li class="flex items-start gap-2 text-sm text-gray-700">
                  <span class="mt-1.5 w-1.5 h-1.5 rounded-full bg-pink-400 shrink-0"></span>
                  <?= htmlspecialchars((string)$item) ?>
                </li>
              <?php endforeach; ?>
            </ul>
          </div>
        <?php endif; ?>

        <?php
        $fullDescription = trim((string)($product['full_description'] ?? ''));
        $description = $fullDescription !== '' ? $fullDescription : trim((string)($product['description'] ?? ''));
        ?>
        <?php if ($description !== ''): ?>
          <p class="text-sm sm:text-base text-gray-600 leading-relaxed" itemprop="description">
            <?= nl2br(htmlspecialchars($description)) ?>
          </p>
        <?php endif; ?>
      </div>

      <div class="md:w-1/2">
        <div class="fixed bottom-[72px] left-3 right-3 z-19 bg-white/95 backdrop-blur-md rounded-2xl border border-gray-100 shadow-[0_-2px_24px_rgba(0,0,0,0.10)] px-4 pt-3 pb-3
                    md:relative md:bottom-auto md:left-auto md:right-auto md:z-auto md:bg-transparent md:backdrop-blur-none md:rounded-none md:border-0 md:shadow-none md:px-0 md:pt-0 md:pb-0 md:mt-0 md:sticky md:top-6"
             data-product-card
             data-buy-max="<?= $buyAvailableBoxes ?>"
             data-preorder-max="<?= $preorderMax ?>">

          <div class="md:bg-white md:rounded-2xl md:shadow-sm md:p-4 md:mb-3 product-card-actions">
            <div class="product-card-price-row">
              <div class="product-card-price-block">
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

            <div itemprop="offers" itemscope itemtype="https://schema.org/Offer">
              <?php if ($schemaPrice > 0): ?>
                <meta itemprop="price" content="<?= number_format($schemaPrice, 2, '.', '') ?>">
              <?php endif; ?>
              <meta itemprop="priceCurrency" content="RUB">
              <link itemprop="availability" href="<?= $canBuyNow ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock' ?>">
            </div>

            <div class="product-card-buttons">
              <?php if ($canBuyNow && $isLoggedIn): ?>
                <form action="/cart/add" method="post"
                      class="add-to-cart-form product-card-buy-form"
                      data-id="<?= (int)$product['id'] ?>"
                      data-name="<?= htmlspecialchars($productTitle) ?>"
                      data-price="<?= $buyPriceBox ?>"
                      data-buy-max="<?= $buyAvailableBoxes ?>">
                  <?= csrf_field() ?>
                  <input type="hidden" name="product_id" value="<?= (int)$product['id'] ?>">
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
                        data-product-id="<?= (int)$product['id'] ?>"
                        data-product-title="<?= htmlspecialchars($productTitle) ?>"
                        data-preorder-price="<?= htmlspecialchars((string)$expectedPreorderPrice) ?>"
                        data-preorder-discount="<?= htmlspecialchars((string)$preorderDiscountPercent) ?>"
                        data-source-section="<?= htmlspecialchars($catalogSection) ?>"
                        data-delivery-date="<?= htmlspecialchars($sourceDeliveryDate) ?>"
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
    </div>

    <div class="px-4 pt-6 pb-2 flex flex-wrap gap-2 md:px-4">
      <a href="/" class="flex items-center gap-1 text-xs font-medium text-gray-500 bg-white border border-gray-200 px-3 py-2 rounded-full hover:bg-gray-50 transition-colors">
        <span class="material-icons-round text-sm leading-none">home</span>
        На главную
      </a>
      <a href="/catalog" class="flex items-center gap-1 text-xs font-medium text-gray-500 bg-white border border-gray-200 px-3 py-2 rounded-full hover:bg-gray-50 transition-colors">
        <span class="material-icons-round text-sm leading-none">grid_view</span>
        Каталог
      </a>
      <a href="/catalog/<?= urlencode((string)$product['type_alias']) ?>" class="flex items-center gap-1 text-xs font-medium text-gray-500 bg-white border border-gray-200 px-3 py-2 rounded-full hover:bg-gray-50 transition-colors">
        <span class="material-icons-round text-sm leading-none">chevron_right</span>
        <?= htmlspecialchars((string)$product['product']) ?>
      </a>
    </div>
  </article>
</main>
<script>
window.dataLayer = window.dataLayer || [];
window.dataLayer.push({
  ecommerce: {
    currencyCode: 'RUB',
    detail: {
      products: [{
        id: '<?= (int)$product['id'] ?>',
        name: '<?= addslashes($productTitle) ?>',
        price: <?= json_encode((float)$schemaPrice) ?>,
        quantity: 1
      }]
    }
  }
});
</script>
