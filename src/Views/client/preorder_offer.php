<?php /** @var array $offer */ ?>
<main class="bg-gradient-to-br from-orange-50 via-white to-pink-50 min-h-screen pb-24">
  <div class="max-w-screen-sm mx-auto px-4 pt-8">
    <div class="bg-white rounded-3xl shadow-lg p-6 space-y-4">
      <h1 class="text-2xl font-bold text-gray-800">Подтверждение предзаказа</h1>
      <p class="text-gray-600">
        <?= htmlspecialchars($offer['product_name'] . (!empty($offer['variety']) ? ' ' . $offer['variety'] : '')) ?>,
        <?= htmlspecialchars((string)$offer['requested_boxes']) ?> кор.
      </p>
      <p class="text-gray-700 font-semibold">
        Цена: <?= number_format((float)($offer['offered_price_per_box'] ?? 0), 0, '.', ' ') ?> ₽ за коробку
      </p>
      <p class="text-sm text-gray-500">Подтвердите до: <?= htmlspecialchars((string)($offer['offer_expires_at'] ?? '')) ?></p>

      <?php if (($offer['status'] ?? '') === 'offer_sent'): ?>
        <div class="flex gap-3">
          <button id="offerConfirmBtn" class="flex-1 w-full bg-green-600 text-white rounded-xl px-4 py-3 font-semibold">Да, подтверждаю</button>
          <button id="offerDeclineBtn" class="flex-1 w-full bg-gray-200 text-gray-700 rounded-xl px-4 py-3 font-semibold">Нет, отказаться</button>
        </div>
        <script>
          async function postOffer(action) {
            const res = await fetch('/preorder-intents/<?= (int)$offer['id'] ?>/' + action, {method: 'POST'});
            const data = await res.json();
            if (action === 'confirm' && data?.continue_url) {
              window.location.href = data.continue_url;
              return;
            }
            window.location.reload();
          }
          document.getElementById('offerConfirmBtn')?.addEventListener('click', () => postOffer('confirm'));
          document.getElementById('offerDeclineBtn')?.addEventListener('click', () => postOffer('decline'));
        </script>
      <?php else: ?>
        <div class="p-3 rounded-xl bg-gray-100 text-gray-700 text-sm">Статус оффера: <?= htmlspecialchars((string)$offer['status']) ?></div>
      <?php endif; ?>

      <a href="/catalog/<?= urlencode((string)$offer['type_alias']) ?>/<?= urlencode((string)$offer['product_alias']) ?>" class="inline-block text-red-600">Вернуться к товару</a>
    </div>
  </div>
</main>
