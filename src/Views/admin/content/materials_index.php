<?php /** @var array $materials */ ?>
<?php /** @var array $category */ ?>
<a href="/admin/content/materials/edit?category_id=<?= $category['id'] ?>" class="bg-[#C86052] text-white px-4 py-2 rounded mb-4 inline-flex items-center">
  <span class="material-icons-round text-base mr-1">add</span> Добавить материал
</a>
<table class="min-w-full bg-white rounded shadow overflow-hidden">
  <thead class="bg-gray-200 text-gray-700">
    <tr>
      <th class="p-3 text-left font-semibold">Заголовок</th>
      <th class="p-3 text-left font-semibold">Дата</th>
      <th class="p-3 text-center font-semibold">Редактировать</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($materials as $m): ?>
    <tr class="border-b hover:bg-gray-50 transition-all duration-200">
      <td class="p-3 text-gray-600"><?= htmlspecialchars($m['title']) ?></td>
      <td class="p-3 text-gray-600"><?= htmlspecialchars(substr($m['created_at'],0,10)) ?></td>
      <td class="p-3 text-center">
        <a href="/admin/content/materials/edit?id=<?= $m['id'] ?>&category_id=<?= $category['id'] ?>" class="text-[#C86052] hover:underline">Редактировать</a>
      </td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
