<?php /** @var array $order @var array $items */ ?>
<div class="space-y-4">
  <h2 class="text-xl font-semibold">Заказ #<?= $order['id'] ?></h2>
  <div class="bg-white p-4 rounded shadow">
    <p><strong>Клиент:</strong> <?= htmlspecialchars($order['client_name']) ?></p>
    <p><strong>Телефон:</strong> <?= htmlspecialchars($order['phone']) ?></p>
    <p><strong>Адрес:</strong> <?= htmlspecialchars($order['address']) ?></p>
    <?php $info = order_status_info($order['status']); ?>
    <p><strong>Статус:</strong>
      <span class="inline-flex items-center px-2 py-0.5 rounded-full text-sm font-medium <?= $info['badge'] ?>">
        <?= $info['label'] ?>
      </span>
    </p>
  </div>
  <div class="bg-white p-4 rounded shadow">
    <h3 class="font-medium mb-2">Товары</h3>
    <ul class="divide-y">
      <?php foreach ($items as $it): ?>
      <li class="py-2 flex justify-between">
        <span><?= htmlspecialchars($it['product_name']) ?> (<?= $it['quantity'] ?> <?= $it['unit'] ?>)</span>
        <span><?= $it['unit_price'] ?> ₽</span>
      </li>
      <?php endforeach; ?>
    </ul>
    <p class="mt-4 text-right font-bold">Итого: <?= $order['total_amount'] ?> ₽</p>

    <?php if (!empty($coupon)): ?>
      <p class="mt-2">
        Купон <?= htmlspecialchars($coupon['code']) ?>:
        <?php if ($coupon['type'] === 'discount'): ?>
          скидка <?= htmlspecialchars($coupon['discount']) ?>%
        <?php else: ?>
          <?= htmlspecialchars($coupon['points']) ?> клубничек
        <?php endif; ?>
      </p>
    <?php endif; ?>

    <?php if (($pointsFromBalance ?? 0) > 0): ?>
      <p class="mt-1 text-pink-600">Списано клубничек: <?= $pointsFromBalance ?></p>
    <?php endif; ?>
  </div>
  <!-- Кнопки действий -->
  <div class="flex space-x-2">
    <form action="/admin/orders/assign" method="post">
      <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
      <button class="bg-[#C86052] text-white px-4 py-2 rounded hover:bg-[#B44D47]">Назначить курьера</button>
    </form>
    <?php foreach (['processing' => 'Принят', 'assigned' => 'Обработан', 'delivered' => 'Выполнен', 'cancelled' => 'Отменен'] as $st => $label): ?>
      <form action="/admin/orders/status" method="post">
        <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
        <input type="hidden" name="status" value="<?= $st ?>">
        <button class="bg-gray-300 px-3 py-1 rounded hover:bg-gray-400" type="submit"><?= $label ?></button>
      </form>
    <?php endforeach; ?>
    <form action="/admin/orders/delete" method="post" onsubmit="return confirm('Удалить этот заказ?');">
      <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
      <button class="bg-red-500 text-white px-3 py-1 rounded hover:bg-red-600">Удалить</button>
    </form>
    
    
    
  </div>
</div>
