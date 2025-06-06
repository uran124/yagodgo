<?php /** @var array $orders */ ?>
<table class="min-w-full bg-white rounded shadow overflow-hidden">
  <thead class="bg-gray-200 text-gray-700">
    <tr>
      <th class="p-2">#</th>
      <th class="p-2">Клиент</th>
      <th class="p-2">Сумма</th>
      <th class="p-2">Статус</th>
      <th class="p-2">Дата</th>
      <th class="p-2">Курьер</th>
      <th class="p-2">Действия</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach($orders as $o): ?>
    <tr class="border-b hover:bg-gray-50">
      <td class="p-2"><?= $o['id'] ?></td>
      <td class="p-2"><?= htmlspecialchars($o['client_name']) ?></td>
      <td class="p-2"><?= $o['total_amount'] ?> ₽</td>
      <td class="p-2"><?= htmlspecialchars($o['status']) ?></td>
      <td class="p-2"><?= $o['created_at'] ?></td>
      <td class="p-2"><?= htmlspecialchars($o['courier_name'] ?? '-') ?></td>
      <td class="p-2">
        <a href="/admin/orders/<?= $o['id'] ?>" class="flex items-center text-[#C86052] hover:underline">
          <span class="material-icons-round text-base mr-1">open_in_new</span> Открыть
        </a>
      </td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
