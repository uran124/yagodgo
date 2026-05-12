<?php /** @var array<string,mixed> $batch */ ?>
<?php /** @var array<int,array<string,mixed>> $movements */ ?>
<?php /** @var array<int,array<string,mixed>> $photos */ ?>
<?php $basePath = $basePath ?? '/admin'; ?>
<?php $flash = $flash ?? null; ?>

<div class="mb-4">
  <a href="<?= $basePath ?>/purchases" class="text-[#C86052] hover:underline">← К списку закупок</a>
</div>

<?php if (is_array($flash) && !empty($flash['message'])): ?>
  <div class="<?= ($flash['type'] ?? '') === 'error' ? 'bg-red-50 border-red-200 text-red-700' : 'bg-green-50 border-green-200 text-green-700' ?> border p-3 rounded mb-4">
    <?= htmlspecialchars((string)$flash['message']) ?>
  </div>
<?php endif; ?>

<div class="bg-white p-4 rounded shadow mb-4">
  <h2 class="text-xl font-semibold mb-2">Партия #<?= (int)$batch['id'] ?></h2>
  <p class="mb-2"><a class="text-sm text-[#C86052] hover:underline" href="<?= $basePath ?>/purchases/<?= (int)$batch['id'] ?>/pnl.csv">Скачать P&L CSV</a></p>
  <p><b>Товар:</b> <?= htmlspecialchars(trim(($batch['product_name'] ?? '') . ' ' . ($batch['variety'] ?? ''))) ?></p>
  <p><b>Закупщик:</b> <?= htmlspecialchars((string)($batch['buyer_name'] ?? '—')) ?></p>
  <p><b>Статус:</b> <?= htmlspecialchars((string)$batch['status']) ?></p>
  <p><b>Куплено:</b> <?= (float)$batch['boxes_total'] ?> | <b>Свободно:</b> <?= (float)$batch['boxes_free'] ?> | <b>Резерв:</b> <?= (float)$batch['boxes_reserved'] ?></p>
</div>


<div class="bg-white p-4 rounded shadow mb-4">
  <h3 class="font-semibold mb-2">P&L партии</h3>
  <div class="grid grid-cols-1 md:grid-cols-2 gap-2 text-sm">
    <p><b>Выручка (продано):</b> <?= number_format((float)($pnl['revenue_sold'] ?? 0), 2, '.', ' ') ?> ₽</p>
    <p><b>Выручка (выгодный остаток):</b> <?= number_format((float)($pnl['revenue_discount'] ?? 0), 2, '.', ' ') ?> ₽</p>
    <p><b>Выручка (итого):</b> <?= number_format((float)($pnl['revenue_total'] ?? 0), 2, '.', ' ') ?> ₽</p>
    <p><b>Себестоимость (продано):</b> <?= number_format((float)($pnl['cost_sold'] ?? 0), 2, '.', ' ') ?> ₽</p>
    <p><b>Себестоимость (выгодный остаток):</b> <?= number_format((float)($pnl['cost_discount'] ?? 0), 2, '.', ' ') ?> ₽</p>
    <p><b>Себестоимость (списано):</b> <?= number_format((float)($pnl['cost_written_off'] ?? 0), 2, '.', ' ') ?> ₽</p>
    <p><b>Себестоимость (признано):</b> <?= number_format((float)($pnl['cost_total_recognized'] ?? 0), 2, '.', ' ') ?> ₽</p>
    <p><b>Валовая маржа:</b> <?= number_format((float)($pnl['gross_margin'] ?? 0), 2, '.', ' ') ?> ₽</p>
    <p><b>Остаток в деньгах:</b> <?= number_format((float)($pnl['inventory_value_remaining'] ?? 0), 2, '.', ' ') ?> ₽</p>
  </div>
</div>

<div class="bg-white p-4 rounded shadow mb-4">
  <h3 class="font-semibold mb-2">Фото партии</h3>
  <?php if ($photos === []): ?>
    <p class="text-gray-500">Фото пока не добавлены.</p>
  <?php else: ?>
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
      <?php foreach ($photos as $photo): ?>
        <div>
          <img src="<?= htmlspecialchars((string)$photo['image_path']) ?>" class="w-full h-32 object-cover rounded" alt="Фото партии">
          <?php if ($basePath !== '/buyer'): ?>
            <form method="post" action="<?= $basePath ?>/purchases/photos/delete" class="mt-1">
              <?= csrf_field() ?>
              <input type="hidden" name="photo_id" value="<?= (int)$photo['id'] ?>">
              <button type="submit" class="text-xs text-red-600 hover:underline">Удалить фото</button>
            </form>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<div class="bg-white p-4 rounded shadow">
  <h3 class="font-semibold mb-2">Движения склада</h3>
  <table class="min-w-full">
    <thead>
      <tr class="text-left border-b">
        <th class="py-2">Тип</th>
        <th class="py-2">Режим</th>
        <th class="py-2">Δ ящиков</th>
        <th class="py-2">Пользователь</th>
        <th class="py-2">Комментарий</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($movements as $m): ?>
        <tr class="border-b">
          <td class="py-2"><?= htmlspecialchars((string)$m['movement_type']) ?></td>
          <td class="py-2"><?= htmlspecialchars((string)$m['stock_mode']) ?></td>
          <td class="py-2"><?= (float)$m['boxes_delta'] ?></td>
          <td class="py-2"><?= htmlspecialchars((string)($m['user_name'] ?? '—')) ?></td>
          <td class="py-2"><?= htmlspecialchars((string)($m['comment'] ?? '')) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
