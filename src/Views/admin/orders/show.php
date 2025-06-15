<?php /** @var array $order @var array $items */ ?>
<div class="space-y-4">
  <h2 class="text-xl font-semibold">Заказ #<?= $order['id'] ?></h2>
  <div class="bg-white p-4 rounded shadow">
    <p><strong>Клиент:</strong> <?= htmlspecialchars($order['client_name']) ?></p>
    <p><strong>Телефон:</strong> <?= htmlspecialchars($order['phone']) ?></p>
    <p><strong>Адрес:</strong> <?= htmlspecialchars($order['address']) ?></p>
    <p><strong>Статус:</strong> <?= htmlspecialchars($order['status']) ?></p>
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
  </div>
  <!-- Кнопки действий -->
  <div class="flex space-x-2">
    <form action="/admin/orders/assign" method="post">
      <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
      <button class="bg-[#C86052] text-white px-4 py-2 rounded hover:bg-[#B44D47]">Назначить курьера</button>
    </form>
    <form action="/admin/orders/status" method="post">
      <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
      <select name="status" class="border px-2 py-1 rounded">
        <?php foreach (['new','processing','assigned','delivered','cancelled'] as $st): ?>
          <option value="<?= $st ?>" <?= $order['status']===$st?'selected':'' ?>><?= $st ?></option>
        <?php endforeach; ?>
      </select>
      <button class="bg-gray-300 px-3 py-1 rounded hover:bg-gray-400 ml-2">Обновить</button>
    </form>
        <form action="/admin/orders/delete" method="post" onsubmit="return confirm('Удалить этот заказ?');">
      <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
      <button class="bg-red-500 text-white px-3 py-1 rounded hover:bg-red-600">Удалить</button>
    </form>
    
    
    
  </div>
</div>
