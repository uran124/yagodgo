<?php /** @var array $materials */ ?>
<?php /** @var array $category */ ?>
<a href="/admin/content/materials/edit?category_id=<?= $category['id'] ?>" class="bg-[#C86052] text-white px-4 py-2 rounded mb-4 inline-flex items-center">
  <span class="material-icons-round text-base mr-1">add</span> Добавить материал
</a>
<table class="min-w-full bg-white rounded shadow overflow-hidden">
  <thead class="bg-gray-200 text-gray-700">
    <tr>
      <th class="p-3 text-left font-semibold">Название материала</th>
      <th class="p-3 text-center font-semibold">Активен</th>
      <th class="p-3 text-center font-semibold">На главной</th>
      <th class="p-3 text-left font-semibold">Дата</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($materials as $m): ?>
    <tr class="border-b hover:bg-gray-50 transition-all duration-200">
      <td class="p-3 text-gray-600">
        <a href="/admin/content/materials/edit?id=<?= $m['id'] ?>&category_id=<?= $category['id'] ?>" class="text-[#C86052] hover:underline">
          <?= htmlspecialchars($m['title']) ?>
        </a>
      </td>
      <td class="p-3 text-center">
        <form action="/admin/content/materials/toggle-active" method="post" class="inline-block">
          <input type="hidden" name="id" value="<?= $m['id'] ?>">
          <input type="hidden" name="category_id" value="<?= $category['id'] ?>">
          <label class="relative inline-flex items-center cursor-pointer">
            <input type="checkbox" onchange="this.form.submit()" <?= !empty($m['is_active']) ? 'checked' : '' ?> class="sr-only peer">
            <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-red-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-red-600"></div>
          </label>
        </form>
      </td>
      <td class="p-3 text-center">
        <form action="/admin/content/materials/toggle-home" method="post" class="inline-block">
          <input type="hidden" name="id" value="<?= $m['id'] ?>">
          <input type="hidden" name="category_id" value="<?= $category['id'] ?>">
          <label class="relative inline-flex items-center cursor-pointer">
            <input type="checkbox" onchange="this.form.submit()" <?= !empty($m['show_on_home']) ? 'checked' : '' ?> class="sr-only peer">
            <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-red-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-red-600"></div>
          </label>
        </form>
      </td>
      <td class="p-3 text-gray-600"><?= htmlspecialchars(substr($m['created_at'],0,10)) ?></td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
