<?php /** @var array $product */ ?>
<main class="bg-gradient-to-br from-orange-50 via-white to-pink-50 min-h-screen pb-52 md:pb-24">
  <article class="max-w-screen-md mx-auto" itemscope itemtype="https://schema.org/Product">

    <!-- Изображение — от края до края на мобиле, скруглённое на десктопе -->
    <?php if (!empty($product['image_path'])): ?>
      <div class="md:px-4 md:pt-6">
        <img src="<?= htmlspecialchars($product['image_path']) ?>"
             alt="<?= htmlspecialchars($product['product']) ?>"
             class="w-full object-cover md:rounded-2xl md:shadow-lg product-image"
             style="aspect-ratio:1/1; max-height:420px; object-fit:cover"
             itemprop="image">
      </div>
    <?php endif; ?>

    <!-- Основной контент -->
    <div class="px-4 pt-4 md:pt-6 md:flex md:space-x-6">

      <!-- Левая колонка (на мобиле — всё в один поток) -->
      <div class="md:w-1/2 space-y-3 md:space-y-4">

        <!-- Заголовок -->
        <h1 class="text-xl sm:text-2xl md:text-3xl font-bold text-gray-900 leading-snug" itemprop="name">
          <?= htmlspecialchars($product['product']) ?>
          <?php if (!empty($product['variety'])): ?>
            <?= ' ' . htmlspecialchars($product['variety']) ?>
          <?php endif; ?>
          <?php if (!empty($product['box_size'])): ?>
            <span class="font-normal text-gray-500"> (<?= htmlspecialchars($product['box_size'] . ' ' . $product['box_unit']) ?>)</span>
          <?php endif; ?>
        </h1>
        <meta itemprop="brand" content="<?= htmlspecialchars($product['manufacturer'] ?? 'BerryGo') ?>">

        <!-- Состав -->
        <?php
        $comp = [];
        if (!empty($product['composition'])) {
            $dec = json_decode($product['composition'], true);
            if (is_array($dec)) { $comp = $dec; }
        }
        if ($comp): ?>
          <div class="bg-white rounded-xl px-4 py-3 shadow-sm">
            <h2 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Состав</h2>
            <ul class="space-y-1">
              <?php foreach ($comp as $c): ?>
                <li class="flex items-start gap-2 text-sm text-gray-700">
                  <span class="mt-1.5 w-1.5 h-1.5 rounded-full bg-pink-400 shrink-0"></span>
                  <?= htmlspecialchars($c) ?>
                </li>
              <?php endforeach; ?>
            </ul>
          </div>
        <?php endif; ?>

        <!-- Описание -->
        <?php $desc = $product['full_description'] !== '' ? $product['full_description'] : ($product['description'] ?? ''); ?>
        <?php if ($desc !== ''): ?>
          <p class="text-sm sm:text-base text-gray-600 leading-relaxed" itemprop="description">
            <?= nl2br(htmlspecialchars($desc)) ?>
          </p>
        <?php endif; ?>
      </div>

      <!-- Правая колонка / sticky-блок покупки -->
      <div class="md:w-1/2">
        <?php
        $active = (int)($product['is_active'] ?? 0);
        $price  = floatval($product['price'] ?? 0); // цена за ящик из закупки
        $sale   = floatval($product['sale_price'] ?? 0); // legacy-акция за базовую единицу, если заполнена
        $boxSize = floatval($product['box_size'] ?? 0);
        $boxUnit = $product['box_unit'] ?? '';
        $regularBox = $price;
        $regularKg  = $boxSize > 0 ? round($regularBox / $boxSize, 2) : round($regularBox, 2);
        $priceBox   = $sale > 0 && $boxSize > 0 ? ($sale * $boxSize) : $regularBox;
        $pricePerKg = $boxSize > 0 ? round($priceBox / $boxSize, 2) : round($priceBox, 2);
        $deliveryDate = (string)($product['delivery_date'] ?? '');
        $placeholderDate = defined('PLACEHOLDER_DATE') ? PLACEHOLDER_DATE : '2025-05-15';
        $preorderDateKnown = ($deliveryDate !== '' && $deliveryDate !== $placeholderDate);
        $preorderDateText = $preorderDateKnown ? date('d.m.Y', strtotime($deliveryDate)) : '';
        ?>

        <!-- Блок цены + кнопок (floating над навбаром на мобиле, встроенный на md+) -->
        <div class="fixed bottom-[72px] left-3 right-3 z-19 bg-white/95 backdrop-blur-md rounded-2xl border border-gray-100 shadow-[0_-2px_24px_rgba(0,0,0,0.10)] px-4 pt-3 pb-3
                    md:relative md:bottom-auto md:left-auto md:right-auto md:z-auto md:bg-transparent md:backdrop-blur-none md:rounded-none md:border-0 md:shadow-none md:px-0 md:pt-0 md:pb-0 md:mt-0 md:sticky md:top-6
                    product-card"
             data-base-box="<?= $sale > 0 ? $priceBox : $regularBox ?>"
             data-base-kg="<?= $sale > 0 ? $pricePerKg : $regularKg ?>">

          <!-- Цена -->
          <div class="md:bg-white md:rounded-2xl md:shadow-sm md:p-4 md:mb-3 pb-2 md:pb-4">
            <?php if ($sale > 0): ?>
              <div class="flex items-end gap-2 mb-0.5">
                <span class="text-2xl sm:text-3xl font-bold text-gray-900 box-price leading-none">
                  <?= number_format($priceBox, 0, '.', ' ') ?> ₽
                </span>
                <span class="text-sm text-gray-400 line-through leading-none pb-0.5">
                  <?= number_format($regularBox, 0, '.', ' ') ?> ₽
                </span>
              </div>
              <p class="text-xs text-gray-400 kg-price"><?= htmlspecialchars($pricePerKg) ?> ₽/кг</p>
            <?php else: ?>
              <div class="flex items-end justify-between">
                <span class="text-2xl sm:text-3xl font-bold text-gray-900 box-price leading-none">
                  <?= number_format($regularBox, 0, '.', ' ') ?> ₽
                </span>
                <span class="text-xs text-gray-400 kg-price pb-0.5"><?= htmlspecialchars($regularKg) ?> ₽/кг</span>
              </div>
            <?php endif; ?>
          </div>

          <div itemprop="offers" itemscope itemtype="https://schema.org/Offer">
            <meta itemprop="price" content="<?= number_format($priceBox, 2, '.', '') ?>">
            <meta itemprop="priceCurrency" content="RUB">
            <link itemprop="availability" href="<?= $active ? 'http://schema.org/InStock' : 'http://schema.org/OutOfStock' ?>">
          </div>

          <?php if (in_array((string)($_SESSION['role'] ?? ''), ['client','partner','manager','seller','admin']) && $active): ?>
            <form action="/cart/add" method="post"
                  class="add-to-cart-form"
                  data-id="<?= $product['id'] ?>"
                  data-name="<?= htmlspecialchars($product['product'] . ($product['variety'] ? ' ' . $product['variety'] : '')) ?>"
                  data-price="<?= $priceBox ?>">
              <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
              <input type="hidden" name="stock_mode" value="instant">

              <!-- Qty stepper + кнопка «В корзину» -->
              <div class="flex items-center gap-2 mb-2">
                <div class="flex items-center rounded-xl border border-gray-200 bg-gray-50 overflow-hidden h-11 shrink-0">
                  <button type="button"
                          class="w-10 h-11 flex items-center justify-center text-gray-500 hover:bg-gray-100 active:bg-gray-200 transition-colors"
                          onclick="let inp=this.nextElementSibling; if(+inp.value>1) inp.value=+inp.value-1;">
                    <span class="material-icons-round text-base leading-none">remove</span>
                  </button>
                  <input type="number"
                         id="buyNowQty"
                         name="quantity"
                         value="1"
                         min="1"
                         step="1"
                         class="w-11 h-11 text-center text-sm font-medium bg-transparent border-x border-gray-200 focus:outline-none" />
                  <button type="button"
                          class="w-10 h-11 flex items-center justify-center text-gray-500 hover:bg-gray-100 active:bg-gray-200 transition-colors"
                          onclick="let inp=this.previousElementSibling; inp.value=+inp.value+1;">
                    <span class="material-icons-round text-base leading-none">add</span>
                  </button>
                </div>

                <button type="submit"
                        class="flex-1 h-11 flex items-center justify-center gap-2 bg-gradient-to-r from-red-500 to-pink-500 accent-gradient text-white font-semibold text-sm rounded-xl transition-opacity hover:opacity-90 active:opacity-80">
                  <span class="material-icons-round text-base leading-none">add_shopping_cart</span>
                  В корзину
                </button>
              </div>
            </form>

            <!-- Предзаказ — вторичное действие -->
            <button id="preorderBtn" type="button"
                    <?= $preorderDateKnown ? '' : 'disabled' ?>
                    class="w-full h-10 flex items-center justify-center gap-1.5 border font-medium text-sm rounded-xl transition-colors mb-1 <?= $preorderDateKnown ? 'border-emerald-500 text-emerald-700 hover:bg-emerald-50 active:bg-emerald-100' : 'border-gray-200 text-gray-400 bg-gray-50 cursor-not-allowed' ?>">
              <span class="material-icons-round text-base leading-none">schedule</span>
              <?= $preorderDateKnown ? ('Предзаказ −10% · ' . $preorderDateText) : 'Предзаказ −10%' ?>
            </button>
            <p class="text-xs text-gray-400 text-center mb-1"><?= $preorderDateKnown ? ('Следующая поставка: ' . htmlspecialchars($preorderDateText)) : 'Дата следующей поставки уточняется' ?></p>
            <p id="preorderHint" class="text-xs text-gray-400 text-center hidden"></p>

            <script>
              document.getElementById('preorderBtn')?.addEventListener('click', async () => {
                const qtyInput = document.getElementById('buyNowQty');
                const qty = qtyInput ? parseFloat(qtyInput.value || '1') : 1;
                const payload = new URLSearchParams();
                payload.set('product_id', '<?= (int)$product['id'] ?>');
                payload.set('requested_boxes', String(qty > 0 ? qty : 1));
                const res = await fetch('/preorder-intents', {
                  method: 'POST',
                  headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                  body: payload.toString()
                });
                const data = await res.json();
                const hint = document.getElementById('preorderHint');
                if (hint) {
                  hint.classList.remove('hidden');
                  hint.textContent = data?.message || 'Предзаказ сохранён';
                }
              });
            </script>

          <?php else: ?>
            <?php if (!empty($_SESSION['user_id']) && !$active): ?>
              <button disabled
                      class="w-full h-11 bg-gray-100 text-gray-400 text-sm rounded-xl cursor-not-allowed">
                Товар недоступен
              </button>
            <?php else: ?>
              <a href="/login"
                 class="w-full h-11 flex items-center justify-center gap-2 bg-gradient-to-r from-red-500 to-pink-500 accent-gradient text-white font-semibold text-sm rounded-xl transition-opacity hover:opacity-90">
                <span class="material-icons-round text-base">login</span>
                Войдите, чтобы заказать
              </a>
            <?php endif; ?>
          <?php endif; ?>

        </div>
      </div>
    </div>

    <!-- Навигационные ссылки -->
    <div class="px-4 pt-6 pb-2 flex flex-wrap gap-2 md:px-4">
      <a href="/"
         class="flex items-center gap-1 text-xs font-medium text-gray-500 bg-white border border-gray-200 px-3 py-2 rounded-full hover:bg-gray-50 transition-colors">
        <span class="material-icons-round text-sm leading-none">home</span>
        На главную
      </a>
      <a href="/catalog"
         class="flex items-center gap-1 text-xs font-medium text-gray-500 bg-white border border-gray-200 px-3 py-2 rounded-full hover:bg-gray-50 transition-colors">
        <span class="material-icons-round text-sm leading-none">grid_view</span>
        Каталог
      </a>
      <a href="/catalog/<?= urlencode($product['type_alias']) ?>"
         class="flex items-center gap-1 text-xs font-medium text-gray-500 bg-white border border-gray-200 px-3 py-2 rounded-full hover:bg-gray-50 transition-colors">
        <span class="material-icons-round text-sm leading-none">chevron_right</span>
        <?= htmlspecialchars($product['product']) ?>
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
        id: '<?= $product['id'] ?>',
        name: '<?= addslashes($product['product'] . ($product['variety'] ? ' ' . $product['variety'] : '')) ?>',
        price: <?= ($product['sale_price']>0?
            ($product['sale_price']*$product['box_size']):
            ($product['price']*$product['box_size'])) ?>,
        quantity: 1
      }]
    }
  }
});
</script>
