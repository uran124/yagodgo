<?php /** @var array<int,array<string,mixed>> $products */ ?>
<?php $basePath = $basePath ?? '/admin'; ?>
<?php $flash = $flash ?? null; ?>
<?php $statusLabels = [
  'planned' => 'Запланирована',
  'purchased' => 'Выкуплена',
  'arrived' => 'Готова к выдаче',
]; ?>
<form action="<?= $basePath ?>/purchases/store" method="post" enctype="multipart/form-data" class="bg-white p-6 rounded shadow max-w-2xl mx-auto space-y-4 pb-24 md:pb-6">
  <?= csrf_field() ?>
  <?php if (is_array($flash) && !empty($flash['message'])): ?>
    <div class="<?= ($flash['type'] ?? '') === 'error' ? 'bg-red-50 border-red-200 text-red-700' : 'bg-green-50 border-green-200 text-green-700' ?> border p-3 rounded">
      <?= htmlspecialchars((string)$flash['message']) ?>
    </div>
  <?php endif; ?>
  <div>
    <label class="block mb-1">Товар</label>
    <select name="product_id" class="w-full border px-2 py-1 rounded" required>
      <option value="">Выберите товар</option>
      <?php foreach ($products as $p): ?>
        <option value="<?= (int)$p['id'] ?>">
          <?= htmlspecialchars(trim(($p['product_name'] ?? '') . ' ' . ($p['variety'] ?? '') . ' ' . ($p['box_size'] ?? '') . ' ' . ($p['box_unit'] ?? ''))) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>

  <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div>
      <label class="block mb-1">Дата</label>
      <input name="planned_supply_date" type="date" class="w-full border px-2 py-1 rounded" value="<?= date('Y-m-d') ?>">
    </div>
    <div>
      <label class="block mb-1">Ожидаемая цена за ящик</label>
      <input name="purchase_price_per_box" type="number" step="0.01" class="w-full border px-2 py-1 rounded" placeholder="Можно оставить пустым">
    </div>
  </div>
  <input type="hidden" name="boxes_total" value="0">
  <input type="hidden" name="boxes_reserved" value="0">
  <input type="hidden" name="boxes_free" value="0">
  <input type="hidden" name="extra_cost_per_box" value="0">
  <input type="hidden" name="comment" value="">

  <div class="hidden md:flex items-center justify-between pt-3 border-t border-gray-100">
    <a href="<?= $basePath ?>/purchases" class="px-4 py-2 rounded border border-gray-300 text-gray-700 hover:bg-gray-50">Вернуться</a>
    <button type="submit" class="bg-[#C86052] text-white px-4 py-2 rounded hover:bg-[#B44D47]">Создать</button>
  </div>

  <div class="md:hidden fixed bottom-0 left-0 right-0 z-40 bg-white border-t border-gray-200 p-3">
    <div class="grid grid-cols-3 gap-2">
      <a href="<?= $basePath ?>/purchases" class="h-10 rounded-lg border border-gray-300 text-gray-700 text-sm flex items-center justify-center">Вернуться</a>
      <button type="submit" class="h-10 rounded-lg border border-gray-300 text-gray-700 text-sm">Сохранить</button>
      <button type="submit" class="h-10 rounded-lg bg-[#C86052] text-white text-sm">Создать</button>
    </div>
  </div>
</form>
