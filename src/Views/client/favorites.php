<?php /** @var array $favorites @var array $preorderIntents @var string|null $userName */ ?>

<main class="bg-[#C86052] min-h-screen pb-24">

  <div class="px-4 pt-16 pb-4 flex justify-between items-center">
    <h2 class="text-2xl font-bold text-white">Избранное</h2>
    <?php if ($userName): ?><span class="text-white"><?= htmlspecialchars($userName) ?></span><?php endif; ?>
  </div>

  <div class="px-4 space-y-4">
    <section class="bg-white rounded-xl shadow p-4">
      <h3 class="text-lg font-semibold mb-2">Предварительные заказы</h3>
      <?php if (empty($preorderIntents ?? [])): ?>
        <p class="text-sm text-gray-500">Пока нет предварительных заказов.</p>
      <?php else: ?>
        <div class="space-y-2">
          <?php foreach (($preorderIntents ?? []) as $intent): ?>
            <div class="border rounded-lg p-2 flex items-center justify-between gap-2 text-sm">
              <div>
                <div class="font-medium"><?= htmlspecialchars(trim(($intent['product_name'] ?? '') . ' ' . ($intent['variety'] ?? ''))) ?></div>
                <div class="text-gray-500">Количество: <?= (int)round((float)($intent['requested_boxes'] ?? 0)) ?> ящ.</div>
              </div>
              <div class="text-right">
                <div class="text-xs text-gray-500">Статус</div>
                <div class="font-semibold"><?= htmlspecialchars((string)($intent['status_label'] ?? $intent['status'] ?? '—')) ?></div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>

    <?php if (empty($favorites)): ?>
      <p class="text-white text-center">У вас нет избранных товаров.</p>
      <a href="/catalog" class="mt-4 flex items-center justify-center bg-white text-[#C86052] py-3 rounded-full font-medium hover:bg-gray-100 transition">
        <span class="material-icons mr-2">arrow_back</span> В каталог
      </a>
    <?php else: ?>
      <?php foreach ($favorites as $p): ?>
        <div class="bg-white rounded-xl shadow p-4 flex flex-col">
          <?php $img = $p['image_path'] ?: '/assets/placeholder.png'; ?>
          <img src="<?= htmlspecialchars($img) ?>" alt="<?= htmlspecialchars($p['product']) ?>" class="w-full object-cover rounded-lg mb-3 product-image" style="aspect-ratio:1/1">
          <div class="flex justify-between items-center mb-2">
            <div class="font-semibold"><?= htmlspecialchars($p['product']) ?></div>
            <?php if (!empty($p['variety'])): ?><div class="text-sm"><?= htmlspecialchars($p['variety']) ?></div><?php endif; ?>
            <div class="text-sm"><?= htmlspecialchars($p['box_size']) ?> <?= htmlspecialchars($p['box_unit']) ?></div>
          </div>
          <?php $base=floatval($p['price']??0);$box=floatval($p['box_size']??0);$boxPrice=$base*$box; ?>
          <div class="flex justify-between items-baseline mb-3">
            <div class="text-lg font-bold"><?= number_format($boxPrice,0,'.',' ') ?> ₽</div>
          </div>
          <form action="/cart/add" method="post" class="mt-auto flex add-to-cart-form" data-id="<?= $p['id'] ?>" data-name="<?= htmlspecialchars($p['product'] . ($p['variety'] ? ' ' . $p['variety'] : '')) ?>" data-price="<?= $boxPrice ?>">
            <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
            <input type="hidden" name="stock_mode" value="instant">
            <input type="number" name="quantity" value="1" min="1" step="1" class="w-20 border border-gray-300 px-2 py-1 rounded-l text-center">
            <button type="submit" class="flex-1 flex items-center justify-center bg-[#C86052] text-white py-2 rounded-r font-medium hover:bg-[#B44D47] transition"><span class="material-icons mr-1">shopping_cart</span> В корзину</button>
          </form>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

</main>
