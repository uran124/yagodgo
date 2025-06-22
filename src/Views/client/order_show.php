<?php
/**
 * @var array $order   // информация из таблицы orders:
 *   id, user_id, address_id, status, total_amount, delivery_date, delivery_slot, created_at, client_name, address
 * @var array $items   // список позиций: product_id, quantity, unit_price, variety, product_name
 * @var string|null $userName
 */

// Чтобы не было «undefined variable»
$order    = $order    ?? [];
$items    = $items    ?? [];
$userName = $userName ?? null;
$coupon   = $coupon   ?? null;
$pointsFromBalance = $pointsFromBalance ?? 0;

// Считаем «сырьевую» сумму (без учёта скидки)
$rawSum = 0;
foreach ($items as $it) {
    $rawSum += ($it['quantity'] * $it['unit_price']);
}
// Сумма скидки в рублях
$discount = max(0, $rawSum - $order['total_amount']);
?>

<main class="bg-gradient-to-br from-orange-50 via-white to-pink-50 min-h-screen pb-24">

  <!-- Шапка заказа -->
  <div class="pt-6 px-4 mb-6">
    <section class="relative overflow-hidden bg-gradient-to-br from-red-500 via-pink-500 to-rose-400 text-white rounded-3xl shadow-2xl p-6">
      <!-- Декоративные круги -->
      <div class="absolute top-0 right-0 w-24 h-24 bg-white/10 rounded-full -translate-y-12 translate-x-12"></div>
      <div class="absolute bottom-0 left-0 w-16 h-16 bg-white/10 rounded-full translate-y-8 -translate-x-8"></div>
      
      <div class="relative z-10 space-y-1">
        <?php $info = order_status_info($order['status']); ?>
        <div class="flex justify-between items-center">
          <h2 class="text-2xl font-bold">📦 Заказ №<?= htmlspecialchars($order['id']) ?></h2>
          <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium <?= $info['badge'] ?>">
            <?= $info['label'] ?>
          </span>
        </div>
        <p class="text-white/80 text-sm">
          <?= date('d.m.Y H:i', strtotime($order['created_at'])) ?>
        </p>
      </div>
    </section>
  </div>

  <div class="px-4 space-y-6">

    <!-- Блок с деталями доставки -->
    <div class="bg-white rounded-3xl shadow-lg p-6">
      <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
        <span class="material-icons-round mr-2 text-red-500">local_shipping</span>
        Доставка
      </h3>
      <p class="text-gray-700 mb-2 flex items-center">
        <span class="material-icons-round align-middle mr-2">calendar_today</span>
        <?php $placeholder = defined('PLACEHOLDER_DATE') ? PLACEHOLDER_DATE : '2025-05-15'; ?>
        <?php if ($order['delivery_date'] === $placeholder): ?>
          Ближайшая возможная дата
        <?php else: ?>
          <?= date('d.m.Y', strtotime($order['delivery_date'])) ?> <?= htmlspecialchars($order['delivery_slot']) ?>
        <?php endif; ?>
      </p>
      <p class="text-gray-700 mb-2 flex items-center">
        <span class="material-icons-round align-middle mr-2">location_on</span>
        <?= htmlspecialchars($order['address']) ?>
      </p>
      <?php if (!empty($order['recipient_name']) || !empty($order['recipient_phone'])): ?>
      <p class="text-gray-700 mb-2 flex items-center">
        <span class="material-icons-round align-middle mr-2">person</span>
        <?= htmlspecialchars($order['recipient_name'] ?? '') ?><?php if (!empty($order['recipient_phone'])): ?>, <?= htmlspecialchars($order['recipient_phone']) ?><?php endif; ?>
      </p>
      <?php endif; ?>
      <?php if (!empty($order['comment'])): ?>
      <p class="text-gray-700 flex items-center">
        <span class="material-icons-round align-middle mr-2">comment</span>
        <?= nl2br(htmlspecialchars($order['comment'])) ?>
      </p>
      <?php endif; ?>
    </div>

    <!-- Блок со списком товаров -->
    <div class="bg-white rounded-3xl shadow-lg p-6">
      <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
        <span class="material-icons-round mr-2 text-red-500">inventory_2</span>
        Товары в заказе
      </h3>
      <ul class="space-y-4">
        <?php foreach ($items as $it): ?>
          <?php
            $lineCost = $it['quantity'] * $it['unit_price'];
          ?>
          <li class="flex items-center justify-between p-4 bg-gray-50 rounded-2xl">
            <?php $boxes = isset($it['boxes']) ? $it['boxes'] : ($it['box_size']>0 ? round($it['quantity']/$it['box_size'],1) : $it['quantity']); ?>
            <span class="text-gray-800">
              <?= htmlspecialchars($it['product_name']) ?><?php if (!empty($it['variety'])): ?> <span class="text-gray-600"><?= htmlspecialchars($it['variety']) ?></span><?php endif; ?>
              x<?= $boxes ?> (<?= htmlspecialchars($it['quantity']) ?> кг)
            </span>
            <span class="font-semibold text-gray-800">
              <?= number_format($lineCost, 0, '.', ' ') ?> ₽
            </span>
          </li>
        <?php endforeach; ?>
      </ul>

      <!-- Итого по «сырьевым» товарам -->
      <div class="flex justify-between items-center pt-4 border-t border-gray-200 mt-6">
        <span class="font-semibold text-gray-800">Сумма без скидки:</span>
        <span class="font-bold text-xl text-gray-800">
          <?= number_format($rawSum, 0, '.', ' ') ?> ₽
        </span>
      </div>

      <div class="mt-4 space-y-1">
        <h4 class="font-semibold text-gray-800">Примененные скидки:</h4>
        <?php if ($pointsFromBalance > 0): ?>
        <div class="flex justify-between items-center">
          <span class="font-medium text-pink-600 flex items-center">
            <span class="text-2xl mr-1">🍓</span>
            Списано клубничек
          </span>
          <span class="text-pink-600 font-semibold">-<?= number_format($pointsFromBalance, 0, '.', ' ') ?></span>
        </div>
        <?php endif; ?>
        <?php if ($coupon): ?>
        <div class="flex justify-between items-center">
          <span class="font-medium text-gray-700">Промокод <?= htmlspecialchars($coupon['code']) ?></span>
          <span class="font-medium text-gray-700">
            <?php if ($coupon['type'] === 'discount'): ?>-<?= htmlspecialchars($coupon['discount']) ?>%<?php else: ?>-<?= htmlspecialchars($coupon['points']) ?><?php endif; ?>
          </span>
        </div>
        <?php endif; ?>
      </div>

      <!-- Окончательная сумма -->
      <div class="flex justify-between items-center pt-4 border-t border-gray-200 mt-4">
        <span class="font-semibold text-gray-800 text-lg">Стоимость заказа:</span>
        <span class="font-bold text-2xl text-gray-800">
          <?= number_format($order['total_amount'], 0, '.', ' ') ?> ₽
        </span>
      </div>
    </div>

    <!-- Кнопки навигации -->
    <div class="flex flex-col sm:flex-row gap-4">
      <a href="/orders"
         class="flex-1 inline-flex items-center justify-center bg-gradient-to-r from-gray-100 to-gray-200 text-gray-700 px-6 py-3 rounded-2xl font-medium hover:from-gray-200 hover:to-gray-300 transition-all space-x-2">
        <span class="material-icons-round">arrow_back</span>
        <span>Вернуться в заказы</span>
      </a>
      <a href="/catalog"
         class="flex-1 inline-flex items-center justify-center bg-gradient-to-r from-red-500 to-pink-500 text-white px-6 py-3 rounded-2xl font-medium hover:shadow-lg transition-all space-x-2">
        <span class="material-icons-round">store</span>
        <span>Вернуться в каталог</span>
      </a>
    </div>

  </div>
</main>
