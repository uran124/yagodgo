<?php /** @var string $type */ ?>
<?php /** @var array|null $data */ ?>
<form action="/admin/apps/seo/save" method="post" class="space-y-4 bg-white p-6 rounded shadow max-w-lg mx-auto">
  <input type="hidden" name="type" value="<?= htmlspecialchars($type) ?>">
  <?php if ($type !== 'page'): ?>
    <input type="hidden" name="id" value="<?= (int)($data['id'] ?? 0) ?>">
    <div>
      <label class="block mb-1">Страница</label>
      <input type="text" value="<?= htmlspecialchars('/'.($data['path'] ?? '')) ?>" class="w-full border px-2 py-1 rounded bg-gray-100" disabled>
    </div>
    <div>
      <label class="block mb-1">Meta title</label>
      <input name="meta_title" type="text" value="<?= htmlspecialchars($data['meta_title'] ?? '') ?>" class="w-full border px-2 py-1 rounded">
    </div>
    <div>
      <label class="block mb-1">Meta description</label>
      <input name="meta_description" type="text" value="<?= htmlspecialchars($data['meta_description'] ?? '') ?>" class="w-full border px-2 py-1 rounded">
    </div>
    <div>
      <label class="block mb-1">Meta keywords</label>
      <input name="meta_keywords" type="text" value="<?= htmlspecialchars($data['meta_keywords'] ?? '') ?>" class="w-full border px-2 py-1 rounded">
    </div>
  <?php else: ?>
    <input type="hidden" name="page" value="<?= htmlspecialchars($data['page'] ?? '') ?>">
    <div>
      <label class="block mb-1">Страница</label>
      <input type="text" value="<?= htmlspecialchars('/'.($data['page'] ?? '')) ?>" class="w-full border px-2 py-1 rounded bg-gray-100" disabled>
    </div>
    <div>
      <label class="block mb-1">Title</label>
      <input name="title" type="text" value="<?= htmlspecialchars($data['title'] ?? '') ?>" class="w-full border px-2 py-1 rounded">
    </div>
    <div>
      <label class="block mb-1">Description</label>
      <input name="description" type="text" value="<?= htmlspecialchars($data['description'] ?? '') ?>" class="w-full border px-2 py-1 rounded">
    </div>
    <div>
      <label class="block mb-1">Keywords</label>
      <input name="keywords" type="text" value="<?= htmlspecialchars($data['keywords'] ?? '') ?>" class="w-full border px-2 py-1 rounded">
    </div>
  <?php endif; ?>
  <button type="submit" class="bg-[#C86052] text-white px-4 py-2 rounded">Сохранить</button>
</form>
