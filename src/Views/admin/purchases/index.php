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
<style>
  .purchase-row-meta { color: #1f2937; }
  .purchase-row-subline { color: #374151; }
  .purchase-card-form input { background: #fff; }
  [data-theme='dark'] .purchase-photo-fallback { border-color: #475569; color: #94a3b8; background: #1e293b; }
  [data-theme='dark'] .purchase-row-meta { color: #e2e8f0; }
  [data-theme='dark'] .purchase-row-subline { color: #cbd5e1; }
  [data-theme='dark'] .purchase-card-form { background: rgba(15, 23, 42, 0.65); border-color: #475569; }
  [data-theme='dark'] .purchase-card-form input {
    background: #0f172a;
    color: #e2e8f0;
    border-color: #475569;
  }
  [data-theme='dark'] .purchase-card-form input::placeholder { color: #94a3b8; }
  [data-theme='dark'] .purchase-btn-discount { background: #facc15; color: #111827; }
  [data-theme='dark'] .purchase-btn-discount:hover { background: #fde047; }
  [data-theme='dark'] .purchase-btn-writeoff { background: #ef4444; color: #fff; }
  [data-theme='dark'] .purchase-btn-writeoff:hover { background: #f87171; }
  [data-theme='dark'] .purchase-btn-cancel { background: #334155; color: #f8fafc; border-color: #64748b; }
  [data-theme='dark'] .purchase-btn-cancel:hover { background: #475569; }
</style>
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

<table class="min-w-full bg-white rounded shadow overflow-hidden text-sm">
  <thead class="bg-gray-200 text-gray-700">
    <tr>
      <th class="p-3 text-left font-semibold">Фото</th>
      <th class="p-3 text-left font-semibold">Закупка</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($batches as $batch): ?>
      <tr class="border-b align-top transition-all duration-200 <?= ((int)($batch['is_closed'] ?? 0) === 1) ? 'bg-gray-50 text-gray-400' : 'hover:bg-orange-50 bg-white' ?>">
        <td class="p-3 w-24">
          <?php if (!empty($batch['preview_photo'])): ?>
            <img src="<?= htmlspecialchars((string)$batch['preview_photo']) ?>" class="h-20 w-20 rounded object-cover border border-gray-200" alt="Фото партии #<?= (int)$batch['id'] ?>">
          <?php else: ?>
            <div class="purchase-photo-fallback h-20 w-20 rounded border border-dashed border-gray-300 text-[10px] text-gray-400 flex items-center justify-center">нет фото</div>
          <?php endif; ?>
        </td>
        <td class="p-3">
          <div class="flex flex-col gap-2">
            <div class="flex items-center justify-between gap-3">
              <div class="purchase-row-meta text-sm font-semibold text-gray-900">
                #<?= (int)$batch['id'] ?> · <?= htmlspecialchars(substr((string)($batch['purchased_at'] ?? ''), 0, 10)) ?>
              </div>
              <div class="text-xs text-gray-500">
                <?= htmlspecialchars((string)($batch['buyer_name'] ?? '—')) ?>
              </div>
            </div>
            <div class="purchase-row-subline text-sm text-gray-800">
              <a class="text-[#C86052] hover:underline font-medium" href="<?= $basePath ?>/purchases/<?= (int)$batch['id'] ?>"><?= htmlspecialchars(trim(($batch['product_name'] ?? '') . ' ' . ($batch['variety'] ?? ''))) ?></a>
              · Стоимость закупки: <b><?= number_format((float)$batch['purchase_price_per_box'], 2, '.', ' ') ?> ₽</b>
              · Куплено: <b><?= (float)$batch['boxes_total'] ?></b>
              · Свободно: <b><?= (float)$batch['boxes_free'] ?></b>
            </div>
            <div class="flex flex-wrap gap-2">
              <form method="post" action="<?= $basePath ?>/purchases/move-to-discount" class="purchase-card-form flex items-center gap-1 bg-yellow-900/30 border border-yellow-700 rounded px-2 py-1">
                <?= csrf_field() ?>
                <input type="hidden" name="batch_id" value="<?= (int)$batch['id'] ?>">
                <input name="boxes" type="number" step="0.01" min="0.01" placeholder="ящ." class="w-16 border rounded px-1 py-1 text-xs text-gray-900">
                <input name="reason" type="text" required placeholder="причина" class="w-24 border rounded px-1 py-1 text-xs text-gray-900">
                <button class="purchase-btn-discount text-xs bg-yellow-500 hover:bg-yellow-400 text-gray-900 px-2 py-1 rounded" type="submit">Уценка</button>
              </form>
              <form method="post" action="<?= $basePath ?>/purchases/write-off" class="purchase-card-form flex items-center gap-1 bg-red-900/30 border border-red-700 rounded px-2 py-1">
                <?= csrf_field() ?>
                <input type="hidden" name="batch_id" value="<?= (int)$batch['id'] ?>">
                <input name="boxes" type="number" step="0.01" min="0.01" placeholder="ящ." class="w-16 border rounded px-1 py-1 text-xs text-gray-900">
                <input name="comment" type="text" required placeholder="причина" class="w-24 border rounded px-1 py-1 text-xs text-gray-900">
                <button class="purchase-btn-writeoff text-xs bg-red-500 hover:bg-red-400 text-white px-2 py-1 rounded" type="submit">Списать</button>
              </form>
              <form method="post" action="<?= $basePath ?>/purchases/cancel-reservations" class="purchase-card-form flex items-center gap-1 bg-slate-800 border border-slate-600 rounded px-2 py-1">
                <?= csrf_field() ?>
                <input type="hidden" name="batch_id" value="<?= (int)$batch['id'] ?>">
                <button class="purchase-btn-cancel text-xs bg-slate-600 hover:bg-slate-500 text-white px-2 py-1 rounded border border-transparent" type="submit">Снять бронь</button>
              </form>
            </div>
          </div>
        </td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>
