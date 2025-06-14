<?php /** @var array $favorites @var string|null $userName */ ?>

<main class="bg-[#C86052] min-h-screen pb-24">

  <!-- Шапка -->
  <div class="px-4 pt-16 pb-4 flex justify-between items-center">
    <h2 class="text-2xl font-bold text-white">Избранное</h2>
    <?php if ($userName): ?><span class="text-white"><?= htmlspecialchars($userName) ?></span><?php endif; ?>
  </div>

  <div class="px-4 space-y-4">
    <?php if (empty($favorites)): ?>
      <p class="text-white text-center">У вас нет избранных товаров.</p>
      <a href="/catalog" class="mt-4 flex items-center justify-center bg-white text-[#C86052] py-3 rounded-full font-medium hover:bg-gray-100 transition">
        <span class="material-icons mr-2">arrow_back</span> В каталог
      </a>
    <?php else: ?>
      <?php foreach ($favorites as $p): ?>
        <div class="bg-white rounded-xl shadow p-4 flex flex-col">
          <?php $img = $p['image_path'] ?: '/assets/placeholder.png'; ?>
          <img src="<?= htmlspecialchars($img) ?>"
               alt="<?= htmlspecialchars($p['product']) ?>"
               class="w-full object-cover rounded-lg mb-3"
               style="aspect-ratio:16/9">

          <div class="flex justify-between items-center mb-2">
            <div class="font-semibold"><?= htmlspecialchars($p['product']) ?></div>
            <?php if (!empty($p['variety'])): ?><div class="text-sm"><?= htmlspecialchars($p['variety']) ?></div><?php endif; ?>
            <div class="text-sm"><?= htmlspecialchars($p['box_size']) ?> <?= htmlspecialchars($p['box_unit']) ?></div>
          </div>

          <div class="flex justify-between items-baseline mb-3">
            <div class="text-lg font-bold"><?= htmlspecialchars($p['price']) ?> ₽/ящик</div>
            <?php $unitPrice = $p['box_size']>0?round($p['price']/$p['box_size'],2):0; ?>
            <div class="text-sm text-gray-500">≈ <?= htmlspecialchars($unitPrice) ?> ₽/кг</div>
          </div>

          <form action="/cart/add" method="post" class="mt-auto flex">
            <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
            <input type="number" name="quantity" value="1" min="1" step="1"
                   class="w-20 border border-gray-300 px-2 py-1 rounded-l text-center">
            <button type="submit"
                    class="flex-1 flex items-center justify-center bg-[#C86052] text-white py-2 rounded-r font-medium hover:bg-[#B44D47] transition">
              <span class="material-icons mr-1">shopping_cart</span> В корзину
            </button>
          </form>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

</main>
