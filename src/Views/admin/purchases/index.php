<?php /** @var array<int,array<string,mixed>> $batches */ ?>
<?php $basePath = $basePath ?? '/admin'; ?>
<?php $flash = $flash ?? null; ?>
<?php $buyers = $buyers ?? []; ?>
<?php $filters = $filters ?? ['status' => '', 'buyer_id' => 0]; ?>
<?php $summary = $summary ?? []; ?>
<?php $preorderDemand = $preorderDemand ?? []; ?>
<?php $preorderDemandTotals = $preorderDemandTotals ?? []; ?>
<?php $statusLabels = [
  'planned' => 'Запланирована',
  'purchased' => 'Выкуплена',
  'arrived' => 'Готова к выдаче',
]; ?>
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
      <?php foreach (['planned','purchased','arrived'] as $st): ?>
        <option value="<?= $st ?>" <?= (($filters['status'] ?? '') === $st) ? 'selected' : '' ?>><?= htmlspecialchars((string)($statusLabels[$st] ?? $st)) ?></option>
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

<div class="bg-white rounded border p-4 mb-4">
  <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
    <h2 class="text-lg font-semibold text-gray-900">Предварительные заказы на закупку</h2>
    <div class="text-xs text-gray-500">Актуально для ролей: админ, менеджер, закупщик</div>
  </div>
  <div class="grid grid-cols-1 md:grid-cols-4 gap-3 mb-3">
    <div class="rounded bg-amber-50 border border-amber-100 p-3">
      <div class="text-xs text-amber-700">Нужно купить (ящ.)</div>
      <div class="text-xl font-semibold text-amber-900"><?= number_format((float)($preorderDemandTotals['requested_boxes'] ?? 0), 2, '.', ' ') ?></div>
    </div>
    <div class="rounded bg-emerald-50 border border-emerald-100 p-3">
      <div class="text-xs text-emerald-700">Подтверждено (ящ.)</div>
      <div class="text-xl font-semibold text-emerald-900"><?= number_format((float)($preorderDemandTotals['confirmed_boxes'] ?? 0), 2, '.', ' ') ?></div>
    </div>
    <div class="rounded bg-blue-50 border border-blue-100 p-3">
      <div class="text-xs text-blue-700">Всего заявок</div>
      <div class="text-xl font-semibold text-blue-900"><?= (int)($preorderDemandTotals['intents_count'] ?? 0) ?></div>
    </div>
    <div class="rounded bg-gray-50 border border-gray-200 p-3">
      <div class="text-xs text-gray-600">Товаров в заявках</div>
      <div class="text-xl font-semibold text-gray-900"><?= (int)($preorderDemandTotals['products_count'] ?? 0) ?></div>
    </div>
  </div>

  <div class="overflow-x-auto">
    <table class="min-w-full text-sm">
      <thead>
        <tr class="text-left text-gray-600 border-b">
          <th class="py-2 pr-3">Товар</th>
          <th class="py-2 pr-3">Нужно купить (ящ.)</th>
          <th class="py-2 pr-3">Подтверждено (ящ.)</th>
          <th class="py-2 pr-3">Заявок</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$preorderDemand): ?>
          <tr>
            <td colspan="4" class="py-3 text-gray-500">Нет активных предзаказов в ожидании закупки.</td>
          </tr>
        <?php else: ?>
          <?php foreach ($preorderDemand as $row): ?>
            <tr class="border-b last:border-b-0">
              <td class="py-2 pr-3 font-medium text-gray-900"><?= htmlspecialchars(trim((string)($row['product_name'] ?? '') . ' ' . (string)($row['variety'] ?? ''))) ?></td>
              <td class="py-2 pr-3"><?= number_format((float)($row['requested_boxes'] ?? 0), 2, '.', ' ') ?></td>
              <td class="py-2 pr-3"><?= number_format((float)($row['confirmed_boxes'] ?? 0), 2, '.', ' ') ?></td>
              <td class="py-2 pr-3"><?= (int)($row['intents_count'] ?? 0) ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="flex items-center mb-4">
  <a href="<?= $basePath ?>/purchases/create" class="bg-[#C86052] text-white px-4 py-2 rounded inline-flex items-center">
    <span class="material-icons-round text-base mr-1">add</span> Добавить закупку
  </a>
  <form method="post" action="<?= $basePath ?>/purchases/preorders/maintenance" class="ml-2">
    <?= csrf_field() ?>
    <button class="bg-gray-100 text-gray-700 px-3 py-2 rounded text-sm" type="submit">Обновить статусы предзаказов</button>
  </form>
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
      <tr class="border-b transition-all duration-200 <?= ((int)($batch['is_closed'] ?? 0) === 1) ? 'bg-gray-50 text-gray-400' : 'hover:bg-orange-50 bg-white' ?>">
        <td class="p-3"><?= (int)$batch['id'] ?></td>
        <td class="p-3">
          <a class="text-[#C86052] hover:underline font-medium" href="<?= $basePath ?>/purchases/<?= (int)$batch['id'] ?>">
            <?= htmlspecialchars(trim(($batch['product_name'] ?? '') . ' ' . ($batch['variety'] ?? ''))) ?>
          </a>
        </td>
        <td class="p-3"><?= htmlspecialchars((string)($batch['buyer_name'] ?? '—')) ?></td>
        <td class="p-3"><?= (float)$batch['boxes_total'] ?></td>
        <td class="p-3"><?= (float)$batch['boxes_free'] ?></td>
        <td class="p-3"><?= (float)$batch['boxes_reserved'] ?></td>
        <td class="p-3"><?= number_format((float)$batch['purchase_price_per_box'], 2, '.', ' ') ?> ₽</td>
        <td class="p-3"><?= number_format((float)$batch['instant_price_per_box'], 2, '.', ' ') ?> ₽</td>
        <td class="p-3">
          <?= htmlspecialchars((string)($statusLabels[(string)$batch['status']] ?? (string)$batch['status'])) ?>
          <?php if ((int)($batch['is_closed'] ?? 0) === 1): ?>
            <span class="ml-1 text-[11px] px-1 py-0.5 rounded bg-gray-200 text-gray-600">закрыта</span>
          <?php endif; ?>
        </td>
        <td class="p-3"><?= (int)($batch['age_days'] ?? 0) ?> дн.</td>
        <td class="p-3">
          <div class="flex flex-wrap gap-2">
            <?php if (($batch['status'] ?? '') === 'planned'): ?>
              <form method="post" action="<?= $basePath ?>/purchases/purchased">
                <?= csrf_field() ?>
                <input type="hidden" name="batch_id" value="<?= (int)$batch['id'] ?>">
                <button class="text-xs bg-indigo-100 px-2 py-1 rounded" type="submit">Выкуплена</button>
              </form>
            <?php elseif (($batch['status'] ?? '') === 'purchased'): ?>
              <form method="post" action="<?= $basePath ?>/purchases/arrived">
                <?= csrf_field() ?>
                <input type="hidden" name="batch_id" value="<?= (int)$batch['id'] ?>">
                <label class="inline-flex items-center gap-1 text-[11px] text-gray-600 mr-1">
                  <input type="checkbox" name="move_leftovers_to_discount" value="1">
                  В уценку
                </label>
                <button class="text-xs bg-blue-100 px-2 py-1 rounded" type="submit">Готова к выдаче</button>
              </form>
            <?php endif; ?>
            <?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
              <form method="post" action="<?= $basePath ?>/purchases/move-to-discount" class="flex items-center gap-1">
                <?= csrf_field() ?>
                <input type="hidden" name="batch_id" value="<?= (int)$batch['id'] ?>">
                <input name="boxes" type="number" step="0.01" min="0.01" placeholder="ящ." class="w-16 border rounded px-1 py-1 text-xs">
                <input name="reason" type="text" required placeholder="причина" class="w-24 border rounded px-1 py-1 text-xs">
                <button class="text-xs bg-yellow-100 px-2 py-1 rounded" type="submit">Уценить</button>
              </form>
            <?php endif; ?>
            <form method="post" action="<?= $basePath ?>/purchases/cancel-reservations">
              <?= csrf_field() ?>
              <input type="hidden" name="batch_id" value="<?= (int)$batch['id'] ?>">
              <button class="text-xs bg-orange-100 px-2 py-1 rounded" type="submit">Отменить бронь</button>
            </form>
            <?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
              <form method="post" action="<?= $basePath ?>/purchases/write-off" class="flex items-center gap-1">
                <?= csrf_field() ?>
                <input type="hidden" name="batch_id" value="<?= (int)$batch['id'] ?>">
                <input name="boxes" type="number" step="0.01" min="0.01" placeholder="ящ." class="w-16 border rounded px-1 py-1 text-xs">
                <input name="comment" type="text" required placeholder="причина" class="w-24 border rounded px-1 py-1 text-xs">
                <button class="text-xs bg-red-100 px-2 py-1 rounded" type="submit">Списать</button>
              </form>
            <?php endif; ?>
          </div>
        </td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>
