<?php /** @var array<int,array<string,mixed>> $batches */ ?>
<div class="flex items-center mb-4">
  <a href="/admin/purchases/create" class="bg-[#C86052] text-white px-4 py-2 rounded inline-flex items-center">
    <span class="material-icons-round text-base mr-1">add</span> Добавить закупку
  </a>
</div>

<table class="min-w-full bg-white rounded shadow overflow-hidden">
  <thead class="bg-gray-200 text-gray-700">
    <tr>
      <th class="p-3 text-left font-semibold">ID</th>
      <th class="p-3 text-left font-semibold">Товар</th>
      <th class="p-3 text-left font-semibold">Закупщик</th>
      <th class="p-3 text-left font-semibold">Куплено</th>
      <th class="p-3 text-left font-semibold">Свободно</th>
      <th class="p-3 text-left font-semibold">Зарезервировано</th>
      <th class="p-3 text-left font-semibold">Цена закупки</th>
      <th class="p-3 text-left font-semibold">Цена сейчас</th>
      <th class="p-3 text-left font-semibold">Статус</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($batches as $batch): ?>
      <tr class="border-b hover:bg-gray-50 transition-all duration-200">
        <td class="p-3"><?= (int)$batch['id'] ?></td>
        <td class="p-3"><?= htmlspecialchars(trim(($batch['product_name'] ?? '') . ' ' . ($batch['variety'] ?? ''))) ?></td>
        <td class="p-3"><?= htmlspecialchars((string)($batch['buyer_name'] ?? '—')) ?></td>
        <td class="p-3"><?= (float)$batch['boxes_total'] ?></td>
        <td class="p-3"><?= (float)$batch['boxes_free'] ?></td>
        <td class="p-3"><?= (float)$batch['boxes_reserved'] ?></td>
        <td class="p-3"><?= number_format((float)$batch['purchase_price_per_box'], 2, '.', ' ') ?> ₽</td>
        <td class="p-3"><?= number_format((float)$batch['instant_price_per_box'], 2, '.', ' ') ?> ₽</td>
        <td class="p-3"><?= htmlspecialchars((string)$batch['status']) ?></td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>
