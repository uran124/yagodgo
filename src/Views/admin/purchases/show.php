<?php /** @var array<string,mixed> $batch */ ?>
<?php /** @var array<int,array<string,mixed>> $movements */ ?>
<?php /** @var array<int,array<string,mixed>> $photos */ ?>
<?php $basePath = $basePath ?? '/admin'; ?>

<div class="mb-4">
  <a href="<?= $basePath ?>/purchases" class="text-[#C86052] hover:underline">← К списку закупок</a>
</div>

<div class="bg-white p-4 rounded shadow mb-4">
  <h2 class="text-xl font-semibold mb-2">Партия #<?= (int)$batch['id'] ?></h2>
  <p><b>Товар:</b> <?= htmlspecialchars(trim(($batch['product_name'] ?? '') . ' ' . ($batch['variety'] ?? ''))) ?></p>
  <p><b>Закупщик:</b> <?= htmlspecialchars((string)($batch['buyer_name'] ?? '—')) ?></p>
  <p><b>Статус:</b> <?= htmlspecialchars((string)$batch['status']) ?></p>
  <p><b>Куплено:</b> <?= (float)$batch['boxes_total'] ?> | <b>Свободно:</b> <?= (float)$batch['boxes_free'] ?> | <b>Резерв:</b> <?= (float)$batch['boxes_reserved'] ?></p>
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
