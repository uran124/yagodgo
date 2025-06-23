<?php /** @var array $categories */ ?>
<a href="/admin/content/category/edit" class="bg-[#C86052] text-white px-4 py-2 rounded mb-4 inline-flex items-center">
  <span class="material-icons-round text-base mr-1">add</span> Добавить категорию
</a>
<table class="min-w-full bg-white rounded shadow overflow-hidden">
  <thead class="bg-gray-200 text-gray-700">
    <tr>
      <th class="p-3 text-left font-semibold">Название</th>
      <th class="p-3 text-left font-semibold">Алиас</th>
      <th class="p-3 text-center font-semibold">Материалы</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($categories as $c): ?>
    <tr class="border-b hover:bg-gray-50 transition-all duration-200">
      <td class="p-3 font-medium text-gray-600">
        <a href="/admin/content/category/edit?id=<?= $c['id'] ?>" class="text-[#C86052] hover:underline">
          <?= htmlspecialchars($c['name']) ?>
        </a>
      </td>
      <td class="p-3 text-gray-600"><?= htmlspecialchars($c['alias']) ?></td>
      <td class="p-3 text-center">
        <a href="/admin/content/materials?category_id=<?= $c['id'] ?>" class="text-[#C86052] hover:underline">Открыть</a>
      </td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
