<?php /** @var array $product */ ?>
<main class="bg-gradient-to-br from-orange-50 via-white to-pink-50 min-h-screen pb-24">
  <article class="max-w-screen-md mx-auto px-4 pt-6 space-y-4">
    <h1 class="text-3xl font-bold text-gray-800 mb-4">
      <?= htmlspecialchars($product['product']) ?>
      <?php if (!empty($product['variety'])): ?>
        <?= ' ' . htmlspecialchars($product['variety']) ?>
      <?php endif; ?>
      <?php if (!empty($product['box_size'])): ?>
        <?= ' (' . htmlspecialchars($product['box_size'] . ' ' . $product['box_unit']) . ')' ?>
      <?php endif; ?>
    </h1>
    <?php if (!empty($product['image_path'])): ?>
      <img src="<?= htmlspecialchars($product['image_path']) ?>" alt="<?= htmlspecialchars($product['product']) ?>" class="w-full rounded-2xl shadow-lg">
    <?php endif; ?>
    <?php if (!empty($product['full_description'])): ?>
      <p class="text-gray-700 text-lg">
        <?= nl2br(htmlspecialchars($product['full_description'])) ?>
      </p>
    <?php endif; ?>
    <div class="mt-4">
      <?php include __DIR__ . '/_card.php'; ?>
    </div>
    <div class="mt-8 flex justify-center space-x-4">
      <a href="/" class="bg-gradient-to-r from-red-500 to-pink-500 text-white px-6 py-3 rounded-xl hover:from-pink-500 hover:to-red-500 transition">На главную</a>
      <a href="/catalog" class="bg-gradient-to-r from-red-500 to-pink-500 text-white px-6 py-3 rounded-xl hover:from-pink-500 hover:to-red-500 transition">В каталог</a>
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
            ($product['sale_price']*$product['box_size']+BOX_MARKUP):
            ($product['price']*$product['box_size']+BOX_MARKUP)) ?>,
        quantity: 1
      }]
    }
  }
});
</script>
