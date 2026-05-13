<?php /** @var array<int,array<string,mixed>> $batches */ ?>
<?php $basePath = $basePath ?? '/admin'; ?>
<?php $flash = $flash ?? null; ?>
<?php $buyers = $buyers ?? []; ?>
<?php $filters = $filters ?? ['status' => '', 'buyer_id' => 0]; ?>
<?php $summary = $summary ?? []; ?>
<?php if (is_array($flash) && !empty($flash['message'])): ?>
  <div class="<?= ($flash['type'] ?? '') === 'error' ? 'bg-red-50 border-red-200 text-red-700' : 'bg-green-50 border-green-200 text-green-700' ?> border p-3 rounded mb-4">
    <?= htmlspecialchars((string)$flash['message']) ?>
  </div>
<?php endif; ?>
<div class="grid grid-cols-1 md:grid-cols-4 gap-3 mb-4">
  <div class="bg-white rounded border p-3"><div class="text-xs text-gray-500">Всего партий</div><div class="text-xl font-semibold"><?= (int)($summary['total_batches'] ?? 0) ?></div></div>
  <div class="bg-white rounded border p-3"><div class="text-xs text-gray-500">Остаток (ящ.)</div><div class="text-xl font-semibold"><?= number_format((float)($summary['remaining_boxes'] ?? 0), 2, '.', ' ') ?></div></div>
  <div class="bg-white rounded border p-3"><div class="text-xs text-gray-500">Списано (ящ.)</div><div class="text-xl font-semibold text-red-600"><?= number_format((float)($summary['written_off_boxes'] ?? 0), 2, '.', ' ') ?></div></div>
  <div class="bg-white rounded border p-3"><div class="text-xs text-gray-500">Средний возраст</div><div class="text-xl font-semibold"><?= number_format((float)($summary['avg_age_days'] ?? 0), 1, '.', ' ') ?> дн.</div></div>
</div>

<form method="get" class="bg-white rounded border p-3 mb-4 grid grid-cols-1 md:grid-cols-4 gap-3 items-end">
  <div>
    <label class="text-xs text-gray-600">Статус</label>
    <select name="status" class="w-full border rounded px-2 py-2 text-sm">
      <option value="">Все</option>
      <?php foreach (['planned','purchased','arrived','active','sold_out','closed','cancelled'] as $st): ?>
        <option value="<?= $st ?>" <?= (($filters['status'] ?? '') === $st) ? 'selected' : '' ?>><?= $st ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div>
    <label class="text-xs text-gray-600">Закупщик</label>
    <select name="buyer_id" class="w-full border rounded px-2 py-2 text-sm">
      <option value="0">Все</option>
      <?php foreach ($buyers as $buyer): ?>
        <option value="<?= (int)$buyer['id'] ?>" <?= ((int)($filters['buyer_id'] ?? 0) === (int)$buyer['id']) ? 'selected' : '' ?>><?= htmlspecialchars((string)$buyer['name']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="md:col-span-2 flex gap-2">
    <button class="bg-gray-900 text-white px-4 py-2 rounded" type="submit">Применить</button>
    <a class="bg-gray-100 px-4 py-2 rounded" href="<?= $basePath ?>/purchases">Сбросить</a>
  </div>
</form>

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
      <th class="p-3 text-left font-semibold">Возраст</th>
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
        <td class="p-3"><?= (int)($batch['age_days'] ?? 0) ?> дн.</td>
        <td class="p-3">
          <div class="flex flex-wrap gap-2">
            <a class="text-xs bg-green-100 px-2 py-1 rounded" href="<?= $basePath ?>/purchases/<?= (int)$batch['id'] ?>">Открыть</a>
            <form method="post" action="<?= $basePath ?>/purchases/arrived">
              <?= csrf_field() ?>
              <input type="hidden" name="batch_id" value="<?= (int)$batch['id'] ?>">
              <button class="text-xs bg-blue-100 px-2 py-1 rounded" type="submit">Поступила</button>
            </form>
            <form method="post" action="<?= $basePath ?>/purchases/move-to-discount" class="flex items-center gap-1">
              <?= csrf_field() ?>
              <input type="hidden" name="batch_id" value="<?= (int)$batch['id'] ?>">
              <input name="boxes" type="number" step="0.01" min="0.01" placeholder="ящ." class="w-16 border rounded px-1 py-1 text-xs">
              <button class="text-xs bg-yellow-100 px-2 py-1 rounded" type="submit">Уценить</button>
            </form>
            <form method="post" action="<?= $basePath ?>/purchases/write-off" class="flex items-center gap-1">
              <?= csrf_field() ?>
              <input type="hidden" name="batch_id" value="<?= (int)$batch['id'] ?>">
              <input name="boxes" type="number" step="0.01" min="0.01" placeholder="ящ." class="w-16 border rounded px-1 py-1 text-xs">
              <input name="comment" type="text" placeholder="комм." class="w-24 border rounded px-1 py-1 text-xs">
              <button class="text-xs bg-red-100 px-2 py-1 rounded" type="submit">Списать</button>
            </form>
            <form method="post" action="<?= $basePath ?>/purchases/close">
              <?= csrf_field() ?>
              <input type="hidden" name="batch_id" value="<?= (int)$batch['id'] ?>">
              <button class="text-xs bg-gray-100 px-2 py-1 rounded" type="submit">Закрыть</button>
            </form>
          </div>
        </td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>
