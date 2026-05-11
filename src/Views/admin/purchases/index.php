<?php /** @var array<int,array<string,mixed>> $batches */ ?>
<?php $basePath = $basePath ?? '/admin'; ?>
<div class="flex items-center mb-4">
  <a href="<?= $basePath ?>/purchases/create" class="bg-[#C86052] text-white px-4 py-2 rounded inline-flex items-center">
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
      <th class="p-3 text-left font-semibold">Действия</th>
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
        <td class="p-3">
          <div class="flex flex-wrap gap-2">
            <form method="post" action="<?= $basePath ?>/purchases/arrived">
              <input type="hidden" name="batch_id" value="<?= (int)$batch['id'] ?>">
              <button class="text-xs bg-blue-100 px-2 py-1 rounded" type="submit">Поступила</button>
            </form>
            <form method="post" action="<?= $basePath ?>/purchases/move-to-discount" class="flex items-center gap-1">
              <input type="hidden" name="batch_id" value="<?= (int)$batch['id'] ?>">
              <input name="boxes" type="number" step="0.01" min="0.01" placeholder="ящ." class="w-16 border rounded px-1 py-1 text-xs">
              <button class="text-xs bg-yellow-100 px-2 py-1 rounded" type="submit">Уценить</button>
            </form>
            <form method="post" action="<?= $basePath ?>/purchases/write-off" class="flex items-center gap-1">
              <input type="hidden" name="batch_id" value="<?= (int)$batch['id'] ?>">
              <input name="boxes" type="number" step="0.01" min="0.01" placeholder="ящ." class="w-16 border rounded px-1 py-1 text-xs">
              <input name="comment" type="text" placeholder="комм." class="w-24 border rounded px-1 py-1 text-xs">
              <button class="text-xs bg-red-100 px-2 py-1 rounded" type="submit">Списать</button>
            </form>
            <form method="post" action="<?= $basePath ?>/purchases/close">
              <input type="hidden" name="batch_id" value="<?= (int)$batch['id'] ?>">
              <button class="text-xs bg-gray-100 px-2 py-1 rounded" type="submit">Закрыть</button>
            </form>
          </div>
        </td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>
