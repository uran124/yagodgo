<?php /** @var array|null $category */ ?>
<form action="/admin/content/category/save" method="post" class="space-y-4 bg-white p-6 rounded shadow max-w-md">
  <?php if (!empty($category['id'])): ?>
    <input type="hidden" name="id" value="<?= $category['id'] ?>">
  <?php endif; ?>
  <div>
    <label class="block mb-1">Название</label>
    <input name="name" type="text" value="<?= htmlspecialchars($category['name'] ?? '') ?>" class="w-full border px-2 py-1 rounded" required>
  </div>
  <div>
    <label class="block mb-1">Алиас</label>
    <input name="alias" type="text" value="<?= htmlspecialchars($category['alias'] ?? '') ?>" class="w-full border px-2 py-1 rounded" required>
  </div>
  <button type="submit" class="bg-[#C86052] text-white px-4 py-2 rounded hover:bg-[#B44D47]">Сохранить</button>
</form>
