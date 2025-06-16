<?php /** @var array $order @var array $items */ ?>
<div class="space-y-4">
  <div class="flex justify-between bg-white p-4 rounded shadow">
    <div>
      <div class="font-semibold mb-1">Заказ #<?= $order['id'] ?></div>
      <div><?= htmlspecialchars($order['client_name']) ?></div>
      <div><?= htmlspecialchars($order['phone']) ?></div>
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
      <div class="flex justify-between py-1">
        <span><?= htmlspecialchars($it['product_name']) ?> (<?= $it['quantity'] ?> <?= $it['unit'] ?>)</span>
        <span><?= $it['unit_price'] ?> ₽</span>
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

  <div class="flex flex-wrap gap-2">
    <?php foreach (['processing' => 'Принят', 'assigned' => 'Обработан', 'delivered' => 'Выполнен', 'cancelled' => 'Отменен'] as $st => $label): ?>
      <form action="/admin/orders/status" method="post">
        <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
        <input type="hidden" name="status" value="<?= $st ?>">
        <button class="status-btn px-3 py-1 rounded" type="submit"><?= $label ?></button>
      </form>
    <?php endforeach; ?>
    <form action="/admin/orders/delete" method="post" onsubmit="return confirm('Удалить этот заказ?');">
      <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
      <button class="status-btn bg-red-600 hover:bg-red-700 px-3 py-1 rounded" type="submit">Удалить</button>
    </form>
  </div>

  <div>
    <a href="/admin/orders" class="status-btn px-4 py-2 rounded inline-block">Вернуться к заказам</a>
  </div>
</div>
