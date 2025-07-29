<?php /** @var array $product */ ?>
<main class="bg-gradient-to-br from-orange-50 via-white to-pink-50 min-h-screen pb-24">
  <article class="max-w-screen-md mx-auto px-4 pt-6 space-y-6" itemscope itemtype="https://schema.org/Product">
    <div class="md:flex md:space-x-6">
      <div class="md:w-1/2 mb-4 md:mb-0">
        <?php if (!empty($product['image_path'])): ?>
          <img src="<?= htmlspecialchars($product['image_path']) ?>" alt="<?= htmlspecialchars($product['product']) ?>" class="w-full rounded-2xl shadow-lg" itemprop="image">
        <?php endif; ?>
      </div>
      <div class="md:w-1/2 space-y-4">
        <h1 class="text-3xl font-bold text-gray-800" itemprop="name">
          <?= htmlspecialchars($product['product']) ?>
          <?php if (!empty($product['variety'])): ?>
            <?= ' ' . htmlspecialchars($product['variety']) ?>
          <?php endif; ?>

          <?php if (!empty($product['box_size'])): ?>
            <?= ' (' . htmlspecialchars($product['box_size'] . ' ' . $product['box_unit']) . ')' ?>
          <?php endif; ?>
        </h1>
        <meta itemprop="brand" content="<?= htmlspecialchars($product['manufacturer'] ?? 'BerryGo') ?>">
        <?php
        $comp = [];
        if (!empty($product['composition'])) {
            $dec = json_decode($product['composition'], true);
            if (is_array($dec)) { $comp = $dec; }
        }
        if ($comp): ?>
          <div>
            <h2 class="font-semibold mb-1">Состав:</h2>
            <ul class="list-disc list-inside text-gray-700">
              <?php foreach ($comp as $c): ?>
                <li><?= htmlspecialchars($c) ?></li>
              <?php endforeach; ?>
            </ul>
          </div>
        <?php endif; ?>
        <?php $desc = $product['full_description'] !== '' ? $product['full_description'] : ($product['description'] ?? ''); ?>
        <?php if ($desc !== ''): ?>
          <p class="text-gray-700 text-lg" itemprop="description">
            <?= nl2br(htmlspecialchars($desc)) ?>
          </p>
        <?php endif; ?>


        <?php
        $active = (int)($product['is_active'] ?? 0);
        $price  = floatval($product['price'] ?? 0);
        $sale   = floatval($product['sale_price'] ?? 0);
        $boxSize = floatval($product['box_size'] ?? 0);
        $boxUnit = $product['box_unit'] ?? '';
        $effectiveKg = $sale > 0 ? $sale : $price;
        $priceBox   = $effectiveKg * $boxSize;
        $pricePerKg = round($effectiveKg, 2);
        $regularBox = $price * $boxSize;
        $regularKg  = round($price, 2);
        ?>
        <div class="space-y-3 product-card" data-base-box="<?= $sale > 0 ? $priceBox : $regularBox ?>" data-base-kg="<?= $sale > 0 ? $pricePerKg : $regularKg ?>">
          <?php if ($sale > 0): ?>
            <div class="flex items-baseline space-x-2">
              <div class="text-sm text-gray-400 line-through">
                <?= number_format($regularBox, 0, '.', ' ') ?> ₽/ящик
              </div>
              <div class="text-xl font-bold text-red-600 box-price">
                <?= number_format($priceBox, 0, '.', ' ') ?> ₽/ящик
              </div>
            </div>
            <div class="text-sm text-gray-400 kg-price">
              <?= htmlspecialchars($pricePerKg) ?> ₽/кг
            </div>
          <?php else: ?>
            <div class="flex justify-between items-center">
              <div class="text-2xl font-bold text-gray-800 box-price">
                <?= number_format($regularBox, 0, '.', ' ') ?> ₽/ящик
              </div>
              <div class="text-sm text-gray-400 kg-price">
                <?= htmlspecialchars($regularKg) ?> ₽/кг
              </div>
            </div>
          <?php endif; ?>

          <div itemprop="offers" itemscope itemtype="https://schema.org/Offer">
            <meta itemprop="price" content="<?= number_format($priceBox, 2, '.', '') ?>">
            <meta itemprop="priceCurrency" content="RUB">
            <link itemprop="availability" href="<?= $active ? 'http://schema.org/InStock' : 'http://schema.org/OutOfStock' ?>">
          </div>

          <?php if (in_array((string)($_SESSION['role'] ?? ''), ['client','partner']) && $active): ?>
            <form action="/cart/add" method="post" class="flex items-center space-x-2 add-to-cart-form" data-id="<?= $product['id'] ?>" data-name="<?= htmlspecialchars($product['product'] . ($product['variety'] ? ' ' . $product['variety'] : '')) ?>" data-price="<?= $priceBox ?>">
              <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
              <div class="flex items-center space-x-2">
                <button type="button" class="w-8 h-8 flex items-center justify-center bg-gray-100 rounded-full" onclick="let inp=this.nextElementSibling; if(+inp.value>1) inp.value=+inp.value-1;">
                  <span class="material-icons-round text-gray-600 text-base">remove</span>
                </button>
                <input type="number" name="quantity" value="1" min="1" step="1" class="w-12 text-center border border-gray-200 rounded-md" />
                <button type="button" class="w-8 h-8 flex items-center justify-center bg-gray-100 rounded-full" onclick="let inp=this.previousElementSibling; inp.value=+inp.value+1;">
                  <span class="material-icons-round text-gray-600 text-base">add</span>
                </button>
              </div>
              <button type="submit" class="ml-2 bg-gradient-to-r from-red-500 to-pink-500 text-white px-2 py-2 rounded-lg hover:from-pink-500 hover:to-red-500 transition-all flex items-center text-sm">
                <span class="material-icons-round text-base mr-1">shopping_cart</span>
                В корзину
              </button>
            </form>
          <?php else: ?>
            <?php if (!empty($_SESSION['user_id']) && !$active): ?>
              <button disabled class="w-full bg-gray-100 text-gray-500 px-3 py-2 rounded-lg text-sm text-center cursor-not-allowed">Товар недоступен</button>
            <?php else: ?>
              <a href="/login" class="w-full bg-gradient-to-r from-red-500 to-pink-500 text-white px-3 py-2 rounded-lg hover:from-pink-500 hover:to-red-500 transition-all text-sm flex items-center justify-center space-x-1">
                <span class="material-icons-round text-base">login</span>
                <span>Войдите, чтобы заказать</span>
              </a>
            <?php endif; ?>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <div class="flex justify-center space-x-4 pt-4">
      <a href="/" class="bg-gradient-to-r from-red-500 to-pink-500 text-white px-6 py-3 rounded-xl hover:from-pink-500 hover:to-red-500 transition">На главную</a>
      <a href="/catalog" class="bg-gradient-to-r from-red-500 to-pink-500 text-white px-6 py-3 rounded-xl hover:from-pink-500 hover:to-red-500 transition">В каталог</a>
      <a href="/catalog/<?= urlencode($product['type_alias']) ?>" class="bg-gradient-to-r from-red-500 to-pink-500 text-white px-6 py-3 rounded-xl hover:from-pink-500 hover:to-red-500 transition">В раздел <?= htmlspecialchars($product['product']) ?></a>
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
