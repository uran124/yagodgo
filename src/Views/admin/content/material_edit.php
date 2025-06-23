<?php
/** @var array|null $material */
/** @var array $category */
/** @var array $products */
?>
<form action="/admin/content/materials/save" method="post" enctype="multipart/form-data" class="space-y-4 bg-white p-6 rounded shadow max-w-lg">
  <input type="hidden" name="category_id" value="<?= $category['id'] ?>">
  <?php if (!empty($material['id'])): ?>
    <input type="hidden" name="id" value="<?= $material['id'] ?>">
  <?php endif; ?>
  <div>
    <label class="block mb-1">Изображение (16:9)</label>
    <input name="image" type="file" accept="image/*" class="w-full">
    <?php if (!empty($material['image_path'])): ?>
      <img src="<?= htmlspecialchars($material['image_path']) ?>" class="mt-2 w-32 h-18 object-cover rounded">
    <?php endif; ?>
  </div>
  <div>
    <label class="block mb-1">Заголовок</label>
    <input name="title" type="text" value="<?= htmlspecialchars($material['title'] ?? '') ?>" class="w-full border px-2 py-1 rounded" required>
  </div>
  <div>
    <label class="block mb-1">Алиас</label>
    <input name="alias" type="text" value="<?= htmlspecialchars($material['alias'] ?? '') ?>" class="w-full border px-2 py-1 rounded" required>
  </div>
  <div>
    <label class="block mb-1">Короткое описание</label>
    <textarea name="short_desc" rows="2" class="w-full border px-2 py-1 rounded"><?= htmlspecialchars($material['short_desc'] ?? '') ?></textarea>
  </div>
  <div>
    <label class="block mb-1">Текст</label>
    <textarea name="text" rows="5" class="w-full border px-2 py-1 rounded"><?= htmlspecialchars($material['text'] ?? '') ?></textarea>
  </div>
  <div>
    <label class="block mb-1">Meta title</label>
    <input name="meta_title" type="text" value="<?= htmlspecialchars($material['meta_title'] ?? '') ?>" class="w-full border px-2 py-1 rounded">
  </div>
  <div>
    <label class="block mb-1">Meta description</label>
    <input name="meta_description" type="text" value="<?= htmlspecialchars($material['meta_description'] ?? '') ?>" class="w-full border px-2 py-1 rounded">
  </div>
  <div>
    <label class="block mb-1">Meta keywords</label>
    <input name="meta_keywords" type="text" value="<?= htmlspecialchars($material['meta_keywords'] ?? '') ?>" class="w-full border px-2 py-1 rounded">
  </div>
  <div>
    <label class="block mb-1">Товар 1</label>
    <select name="product1_id" class="w-full border px-2 py-1 rounded">
      <option value="">-- не выбрано --</option>
      <?php foreach ($products as $p): ?>
        <option value="<?= $p['id'] ?>" <?= isset($material['product1_id']) && $material['product1_id']==$p['id'] ? 'selected' : '' ?>><?= htmlspecialchars($p['variety'] ?: $p['product']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div>
    <label class="block mb-1">Товар 2</label>
    <select name="product2_id" class="w-full border px-2 py-1 rounded">
      <option value="">-- не выбрано --</option>
      <?php foreach ($products as $p): ?>
        <option value="<?= $p['id'] ?>" <?= isset($material['product2_id']) && $material['product2_id']==$p['id'] ? 'selected' : '' ?>><?= htmlspecialchars($p['variety'] ?: $p['product']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div>
    <label class="block mb-1">Товар 3</label>
    <select name="product3_id" class="w-full border px-2 py-1 rounded">
      <option value="">-- не выбрано --</option>
      <?php foreach ($products as $p): ?>
        <option value="<?= $p['id'] ?>" <?= isset($material['product3_id']) && $material['product3_id']==$p['id'] ? 'selected' : '' ?>><?= htmlspecialchars($p['variety'] ?: $p['product']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <button type="submit" class="bg-[#C86052] text-white px-4 py-2 rounded hover:bg-[#B44D47]">Сохранить</button>
</form>
