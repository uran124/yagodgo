<?php /** @var array $orders */ ?>
<h1 class="text-xl mb-4">Заказы</h1>
<?php $currentFilter = $statusFilter ?? ''; ?>
<?php if (isset($_GET['updated'])): ?>
  <div class="mb-4 rounded border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-800">Статус заказа обновлен.</div>
<?php elseif (isset($_GET['error'])): ?>
  <div class="mb-4 rounded border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800">Не удалось обновить статус. Проверьте доступ и последовательность шагов.</div>
<?php endif; ?>

<form method="get" action="/seller/orders" class="mb-4 flex items-end gap-3 rounded border p-3">
  <div>
    <label for="status" class="mb-1 block text-sm text-gray-600">Фильтр по статусу</label>
    <select name="status" id="status" class="rounded border px-3 py-2 text-sm">
      <option value="">Все заказы</option>
      <option value="new" <?= $currentFilter === 'new' ? 'selected' : '' ?>>Новый заказ</option>
      <option value="processing" <?= $currentFilter === 'processing' ? 'selected' : '' ?>>Принят</option>
      <option value="assigned" <?= $currentFilter === 'assigned' ? 'selected' : '' ?>>В работе</option>
      <option value="delivered" <?= $currentFilter === 'delivered' ? 'selected' : '' ?>>Выполнен</option>
      <option value="cancelled" <?= $currentFilter === 'cancelled' ? 'selected' : '' ?>>Отменён</option>
    </select>
  </div>
  <button type="submit" class="rounded border px-3 py-2 text-sm">Применить</button>
  <a href="/seller/orders" class="rounded border px-3 py-2 text-sm">Сбросить</a>
</form>

<?php if (!$orders): ?>
  <div class="rounded border border-dashed p-6 text-center text-gray-500">По выбранному фильтру заказов не найдено.</div>
<?php endif; ?>
<?php foreach ($orders as $o): ?>
  <?php $info = order_status_info($o['status']); ?>
  <div class="mb-6 p-4 border rounded">
    <div class="font-semibold mb-1 flex justify-between">
      <span>#<?= htmlspecialchars($o['id']) ?> | <?= htmlspecialchars($o['delivery_date']) ?> <?= htmlspecialchars(format_time_range($o['slot_from'], $o['slot_to'])) ?></span>
      <span><?= htmlspecialchars($info['label']) ?></span>
    </div>
    <div class="text-sm mb-2"><?= htmlspecialchars($o['client_name']) ?>, <?= htmlspecialchars($o['phone']) ?>, <?= htmlspecialchars($o['address']) ?></div>
    <div class="text-sm mb-1">Состав:</div>
    <ul class="text-sm mb-2">
      <?php foreach ($o['items'] as $it): ?>
        <?php $itemTotal = $it['quantity'] * $it['unit_price']; ?>
        <li>
          <?= htmlspecialchars($it['product_name']) ?><?php if ($it['variety']): ?> «<?= htmlspecialchars($it['variety']) ?>»<?php endif; ?>,
          <?= rtrim(rtrim(number_format($it['boxes'],2,'.',''), '0'), '.') ?> ящ. (<?= rtrim(rtrim(number_format($it['quantity'],2,'.',''), '0'), '.') ?> <?= htmlspecialchars($it['box_unit']) ?>)
          <span class="float-right"><?= number_format($itemTotal, 2, '.', ' ') ?> ₽</span>
        </li>
      <?php endforeach; ?>
    </ul>
    <div class="text-sm flex justify-between"><span>Стоимость позиций (итого)</span><span><?= number_format($o['seller_subtotal'], 2, '.', ' ') ?> ₽</span></div>
    <div class="text-sm flex justify-between"><span>Оплачено клубничками</span><span><?= number_format($o['points_applied'], 2, '.', ' ') ?> ₽</span></div>
    <div class="text-sm flex justify-between"><span>Комиссия BerryGo (<?= (float)$o['commission_rate'] ?>%)</span><span><?= number_format($o['commission'], 2, '.', ' ') ?> ₽</span></div>
    <div class="text-sm flex justify-between font-semibold"><span>Выплата селлеру</span><span><?= number_format($o['payout'], 2, '.', ' ') ?> ₽</span></div>
    <div class="mt-2 flex flex-wrap gap-2 text-sm">
      <?php if ($o['status'] === 'new'): ?>
        <form method="post" action="/seller/orders/status">
          <input type="hidden" name="order_id" value="<?= (int)$o['id'] ?>">
          <input type="hidden" name="status" value="processing">
          <button type="submit" class="border rounded px-3 py-1">✅ Подтвердить</button>
        </form>
      <?php endif; ?>
      <?php if ($o['status'] === 'processing'): ?>
        <form method="post" action="/seller/orders/status">
          <input type="hidden" name="order_id" value="<?= (int)$o['id'] ?>">
          <input type="hidden" name="status" value="assigned">
          <button type="submit" class="border rounded px-3 py-1">🧺 Готово к выдаче</button>
        </form>
      <?php endif; ?>
      <?php if (in_array($o['status'], ['new', 'processing'], true)): ?>
        <form method="post" action="/seller/orders/status">
          <input type="hidden" name="order_id" value="<?= (int)$o['id'] ?>">
          <input type="hidden" name="status" value="cancelled">
          <button type="submit" class="border rounded px-3 py-1">⚠️ Нет в наличии</button>
        </form>
      <?php endif; ?>
    </div>
  </div>
<?php endforeach; ?>
