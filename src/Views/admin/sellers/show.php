<?php /** @var array $seller @var array $orders */ ?>
<div class="bg-white rounded shadow p-4 mb-6">
  <div class="mb-2"><span class="font-semibold">Название:</span> <?= htmlspecialchars($seller['company_name']) ?></div>
  <div class="mb-2"><span class="font-semibold">Адрес:</span> <?= htmlspecialchars($seller['pickup_address']) ?></div>
  <div class="mb-2"><span class="font-semibold">Имя:</span> <?= htmlspecialchars($seller['name']) ?></div>
  <div class="mb-2"><span class="font-semibold">Телефон:</span> <?= htmlspecialchars($seller['phone']) ?></div>
  <div><span class="font-semibold">Баланс:</span> <?= (int)$seller['rub_balance'] ?> ₽</div>
</div>
<h2 class="text-lg font-semibold mb-4">Заказы</h2>
<?php if ($orders): ?>
  <?php foreach ($orders as $o): ?>
    <?php $info = order_status_info($o['status']); ?>
    <div class="mb-6 p-4 border rounded">
      <div class="font-semibold mb-1 flex justify-between">
        <span>#<?= htmlspecialchars($o['id']) ?> | <?= htmlspecialchars($o['delivery_date']) ?> <?= htmlspecialchars($o['slot_from']) ?>–<?= htmlspecialchars($o['slot_to']) ?></span>
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
    </div>
  <?php endforeach; ?>
<?php else: ?>
  <p class="text-sm text-gray-600">Заказов пока нет</p>
<?php endif; ?>
