<?php /** @var array $order @var array $items */ ?>
<div class="space-y-4">
  <div class="flex justify-between items-center bg-white p-4 rounded shadow">
    <div class="flex flex-wrap items-center gap-2">
      <span class="font-semibold">Заказ #<?= $order['id'] ?></span>
      <span><?= htmlspecialchars($order['client_name']) ?></span>
      <span><?= htmlspecialchars($order['phone']) ?></span>
    </div>
    <div class="flex items-start">
      <?php $info = order_status_info($order['status']); ?>
      <span class="inline-flex items-center px-2 py-0.5 rounded-full text-sm font-medium <?= $info['badge'] ?>">
        <?= $info['label'] ?>
      </span>
    </div>
  </div>

  <div class="bg-white p-4 rounded shadow space-y-1">
    <?php foreach ($items as $it): ?>
      <?php $lineCost = $it['quantity'] * $it['unit_price']; ?>
      <div class="flex justify-between py-1">
        <span>
          <?= htmlspecialchars($it['product_name']) ?>
          <?php if (!empty($it['variety'])): ?> <?= htmlspecialchars($it['variety']) ?><?php endif; ?>
          <?php if (!empty($it['box_size']) && !empty($it['box_unit'])): ?>
            <?= ' ' . $it['box_size'] . ' ' . htmlspecialchars($it['box_unit']) ?>
          <?php endif; ?>
          × <?= $it['quantity'] ?>
        </span>
        <span><?= number_format($lineCost, 0, '.', ' ') ?> ₽</span>
      </div>
    <?php endforeach; ?>

    <?php if (($pointsFromBalance ?? 0) > 0): ?>
      <div class="flex justify-between text-pink-600 py-1 border-t">
        <span>Списание клубничек</span>
        <span>-<?= $pointsFromBalance ?></span>
      </div>
    <?php endif; ?>

    <?php if (!empty($coupon)): ?>
      <div class="flex justify-between py-1">
        <span>Купон <?= htmlspecialchars($coupon['code']) ?></span>
        <span>
          <?php if ($coupon['type'] === 'discount'): ?>
            -<?= htmlspecialchars($coupon['discount']) ?>%
          <?php else: ?>
            -<?= htmlspecialchars($coupon['points']) ?> клубничек
          <?php endif; ?>
        </span>
      </div>
    <?php endif; ?>

    <div class="flex justify-between font-bold border-t pt-2">
      <span>Итого:</span>
      <span><?= $order['total_amount'] ?> ₽</span>
    </div>
  </div>

  <div class="flex flex-wrap gap-2 items-center">
    <?php $btnClasses = [
        'processing' => 'bg-yellow-700 hover:bg-yellow-800',
        'assigned'   => 'bg-green-700 hover:bg-green-800',
        'delivered'  => 'bg-gray-700 hover:bg-gray-800',
        'cancelled'  => 'bg-gray-600 hover:bg-gray-700',
    ]; ?>
    <?php foreach ([
        'processing' => 'Принят',
        'assigned'   => 'Обработан',
        'delivered'  => 'Выполнен',
        'cancelled'  => 'Отменен'
      ] as $st => $label): ?>
      <form action="/admin/orders/status" method="post">
        <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
        <input type="hidden" name="status" value="<?= $st ?>">
        <button class="px-3 py-1 rounded text-white <?= $btnClasses[$st] ?>" type="submit"><?= $label ?></button>
      </form>
    <?php endforeach; ?>
    <form class="ml-auto" action="/admin/orders/delete" method="post" onsubmit="return confirm('Удалить этот заказ?');">
      <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
      <button class="px-3 py-1 rounded text-white bg-red-700 hover:bg-red-800" type="submit">Удалить</button>
    </form>
  </div>

  <div>
    <a href="/admin/orders" class="inline-block px-4 py-2 rounded text-white bg-purple-700 hover:bg-purple-800">Вернуться к заказам</a>
  </div>
</div>
