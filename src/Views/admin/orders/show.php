<?php /** @var array $order @var array $items @var array $addresses @var array $slots @var array $products */ ?>
<?php $role = $_SESSION['role'] ?? ''; $isManager = in_array($role, ['manager','partner'], true); $base = $role === 'manager' ? '/manager' : ($role === 'partner' ? '/partner' : '/admin'); ?>
<style>
  @media (max-width: 640px) {
    .order-details button { padding: 0.25rem 0.5rem; font-size: 0.75rem; }
    .order-details span { font-size: 0.75rem; }
  }
</style>
<div class="order-details space-y-4">
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
      <div class="flex justify-between items-center py-1">
        <span>
          <?= htmlspecialchars($it['product_name']) ?>
          <?php if (!empty($it['variety'])): ?> <?= htmlspecialchars($it['variety']) ?><?php endif; ?>
          <?php if (!empty($it['box_size']) && !empty($it['box_unit'])): ?>
            <?= ' ' . $it['box_size'] . ' ' . htmlspecialchars($it['box_unit']) ?>
          <?php endif; ?>
        </span>
        <form action="<?= $base ?>/orders/update-item" method="post" class="flex items-center space-x-2">
          <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
          <input type="hidden" name="product_id" value="<?= $it['product_id'] ?>">
          <input type="number" name="quantity" value="<?= $it['quantity'] ?>" step="0.01" class="w-20 border px-1 py-0.5 rounded"> кг
          <input type="number" name="unit_price" value="<?= $it['unit_price'] ?>" step="1" class="w-20 border px-1 py-0.5 rounded"> ₽
          <button type="submit" class="bg-[#C86052] text-white px-2 py-1 rounded">OK</button>
          <button type="submit" formaction="<?= $base ?>/orders/delete-item" class="text-red-600" onclick="return confirm('Удалить позицию?');">✕</button>
          <span class="ml-2"><?= number_format($lineCost, 0, '.', ' ') ?> ₽</span>
        </form>
      </div>
    <?php endforeach; ?>

    <form action="<?= $base ?>/orders/add-item" method="post" class="flex items-center space-x-2 pt-2 border-t">
      <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
      <select name="product_id" class="border px-1 py-0.5 rounded">
        <?php foreach ($products as $p): ?>
          <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['product']) ?><?php if(!empty($p['variety'])): ?> <?= htmlspecialchars($p['variety']) ?><?php endif; ?></option>
        <?php endforeach; ?>
      </select>
      <input type="number" name="quantity" step="0.01" placeholder="Кг" class="w-20 border px-1 py-0.5 rounded">
      <input type="number" name="unit_price" step="1" placeholder="₽" class="w-20 border px-1 py-0.5 rounded">
      <button type="submit" class="bg-green-700 text-white px-2 py-1 rounded">Добавить</button>
    </form>

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

  <form action="<?= $base ?>/orders/comment" method="post" class="bg-white p-4 rounded shadow space-y-2">
    <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
    <label class="block">
      <span class="block mb-1">Комментарий:</span>
      <textarea name="comment" rows="3" class="w-full border px-2 py-1 rounded"><?= htmlspecialchars($order['comment'] ?? '') ?></textarea>
    </label>
    <button type="submit" class="bg-blue-700 text-white px-3 py-1 rounded">Сохранить</button>
  </form>

  <form action="<?= $base ?>/orders/referral" method="post" class="bg-white p-4 rounded shadow space-y-2">
    <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
    <input type="hidden" name="user_id" value="<?= $order['user_id'] ?>">
    <label class="inline-flex items-center cursor-pointer">
      <input type="hidden" name="has_used_referral_coupon" value="0">
      <input type="checkbox" name="has_used_referral_coupon" value="1" class="sr-only peer" <?= ($order['has_used_referral_coupon'] ?? 0) ? 'checked' : '' ?>>
      <div class="w-10 h-5 bg-gray-200 rounded-full peer-checked:bg-[#C86052] relative transition-colors after:content-[''] after:absolute after:left-1 after:top-0.5 after:bg-white after:rounded-full after:h-4 after:w-4 after:transition-transform peer-checked:after:translate-x-5"></div>
      <span class="ml-2 text-sm">Скидка 10% на первый заказ</span>
    </label>
    <button type="submit" class="bg-blue-700 text-white px-3 py-1 rounded">Сохранить</button>
  </form>

  <form action="<?= $base ?>/orders/update-delivery" method="post" class="bg-white p-4 rounded shadow space-y-2">
    <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
    <div class="space-x-2 flex flex-wrap items-center">
      <label>
        <span class="mr-1">Адрес:</span>
        <select name="address_id" class="border px-2 py-1 rounded">
          <?php foreach ($addresses as $a): ?>
            <option value="<?= $a['id'] ?>" <?= $a['id'] == $order['address_id'] ? 'selected' : '' ?>><?= htmlspecialchars($a['street']) ?></option>
          <?php endforeach; ?>
          <option value="pickup" <?= $order['address_id'] === null ? 'selected' : '' ?>>Самовывоз 9 мая 73</option>
        </select>
      </label>
      <label>
        <span class="mr-1">Дата:</span>
        <input type="date" name="delivery_date" value="<?= htmlspecialchars($order['delivery_date']) ?>" class="border px-2 py-1 rounded">
      </label>
      <label>
        <span class="mr-1">Слот:</span>
        <select name="slot_id" class="border px-2 py-1 rounded">
          <?php foreach ($slots as $s): ?>
            <option value="<?= $s['id'] ?>" <?= $s['id'] == $order['slot_id'] ? 'selected' : '' ?>><?= htmlspecialchars($s['time_from']) ?>-<?= htmlspecialchars($s['time_to']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <button type="submit" class="bg-blue-700 text-white px-3 py-1 rounded">Сохранить</button>
    </div>
  </form>

  <div class="flex flex-wrap gap-2 items-center">
    <?php $btnClasses = [
        'processing' => 'bg-yellow-700 hover:bg-yellow-800',
        'assigned'   => 'bg-green-700 hover:bg-green-800',
        'delivered'  => 'bg-gray-700 hover:bg-gray-800',
        'cancelled'  => 'bg-gray-600 hover:bg-gray-700',
    ]; ?>
    <?php foreach ([
        'processing' => 'Принят',
        'assigned'   => 'В работе',
        'delivered'  => 'Выполнен',
        'cancelled'  => 'Отменен'
      ] as $st => $label): ?>
      <form action="<?= $base ?>/orders/status" method="post">
        <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
        <input type="hidden" name="status" value="<?= $st ?>">
        <button class="px-3 py-1 rounded text-white <?= $btnClasses[$st] ?>" type="submit"><?= $label ?></button>
      </form>
    <?php endforeach; ?>
    <form class="ml-auto" action="<?= $base ?>/orders/delete" method="post" onsubmit="return confirm('Удалить этот заказ?');">
      <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
      <button class="px-3 py-1 rounded text-white bg-red-700 hover:bg-red-800" type="submit">Удалить</button>
    </form>
  </div>

  <div>
    <a href="<?= $base ?>/orders" class="inline-block px-4 py-2 rounded text-white bg-purple-700 hover:bg-purple-800">Вернуться к заказам</a>
  </div>
</div>
