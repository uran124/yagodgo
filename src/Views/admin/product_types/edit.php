<?php /** @var array|null $type */ ?>
<?php $role = $_SESSION['role'] ?? ''; $base = $role === 'seller' ? '/seller' : '/admin'; ?>
<form action="<?= $base ?>/product-types/save" method="post" class="space-y-4 bg-white p-6 rounded shadow max-w-lg mx-auto">
  <?php if (!empty($type['id'])): ?>
    <input type="hidden" name="id" value="<?= $type['id'] ?>">
  <?php endif; ?>
  <div>
    <label class="block mb-1">Название</label>
    <input name="name" type="text" value="<?= htmlspecialchars($type['name'] ?? '') ?>" class="w-full border px-2 py-1 rounded" required>
  </div>
  <div>
    <label class="block mb-1">Алиас</label>
    <input name="alias" type="text" value="<?= htmlspecialchars($type['alias'] ?? '') ?>" class="w-full border px-2 py-1 rounded" required>
  </div>
  <div>
    <label class="block mb-1">Meta title</label>
    <input name="meta_title" type="text" value="<?= htmlspecialchars($type['meta_title'] ?? '') ?>" class="w-full border px-2 py-1 rounded">
  </div>
  <div>
    <label class="block mb-1">Meta description</label>
    <input name="meta_description" type="text" value="<?= htmlspecialchars($type['meta_description'] ?? '') ?>" class="w-full border px-2 py-1 rounded">
  </div>
  <div>
    <label class="block mb-1">Meta keywords</label>
    <input name="meta_keywords" type="text" value="<?= htmlspecialchars($type['meta_keywords'] ?? '') ?>" class="w-full border px-2 py-1 rounded">
  </div>
  <div>
    <label class="block mb-1">H1</label>
    <input name="h1" type="text" value="<?= htmlspecialchars($type['h1'] ?? '') ?>" class="w-full border px-2 py-1 rounded">
  </div>
  <div>
    <label class="block mb-1">Короткое описание</label>
    <textarea name="short_description" rows="2" class="w-full border px-2 py-1 rounded"><?= htmlspecialchars($type['short_description'] ?? '') ?></textarea>
  </div>
  <div>
    <label class="block mb-1">Текст</label>
    <textarea name="text" rows="5" class="w-full border px-2 py-1 rounded"><?= htmlspecialchars($type['text'] ?? '') ?></textarea>
  </div>
  <button type="submit" class="bg-[#C86052] text-white px-4 py-2 rounded hover:bg-[#B44D47]">Сохранить</button>
</form>
