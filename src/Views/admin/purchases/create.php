<?php /** @var array<int,array<string,mixed>> $products */ ?>
<form action="/admin/purchases/store" method="post" class="bg-white p-6 rounded shadow max-w-2xl mx-auto space-y-4">
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

  <div class="grid grid-cols-2 gap-4">
    <div>
      <label class="block mb-1">Количество ящиков</label>
      <input name="boxes_total" type="number" step="0.01" class="w-full border px-2 py-1 rounded" required>
    </div>
    <div>
      <label class="block mb-1">Цена закупки за ящик</label>
      <input name="purchase_price_per_box" type="number" step="0.01" class="w-full border px-2 py-1 rounded" required>
    </div>
    <div>
      <label class="block mb-1">Свободно в продажу</label>
      <input name="boxes_free" type="number" step="0.01" class="w-full border px-2 py-1 rounded" value="10" required>
    </div>
    <div>
      <label class="block mb-1">Под резерв</label>
      <input name="boxes_reserved" type="number" step="0.01" class="w-full border px-2 py-1 rounded" value="0" required>
    </div>
    <div>
      <label class="block mb-1">Доп. расход за ящик</label>
      <input name="extra_cost_per_box" type="number" step="0.01" class="w-full border px-2 py-1 rounded" value="0">
    </div>
    <div>
      <label class="block mb-1">Статус</label>
      <select name="status" class="w-full border px-2 py-1 rounded">
        <option value="purchased">purchased</option>
        <option value="arrived">arrived</option>
        <option value="active">active</option>
      </select>
    </div>
  </div>

  <div>
    <label class="block mb-1">Комментарий</label>
    <textarea name="comment" rows="3" class="w-full border px-2 py-1 rounded"></textarea>
  </div>

  <button type="submit" class="bg-[#C86052] text-white px-4 py-2 rounded hover:bg-[#B44D47]">Сохранить закупку</button>
</form>
