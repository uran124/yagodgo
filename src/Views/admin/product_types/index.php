<?php /** @var array $types */ ?>
<a href="/admin/product-types/edit" class="bg-[#C86052] text-white px-4 py-2 rounded mb-4 inline-flex items-center">
  <span class="material-icons-round text-base mr-1">add</span> Добавить категорию
</a>
<table class="min-w-full bg-white rounded shadow overflow-hidden">
  <thead class="bg-gray-200 text-gray-700">
    <tr>
      <th class="p-3 text-left font-semibold">Название</th>
      <th class="p-3 text-center font-semibold">Редактировать</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($types as $t): ?>
    <tr class="border-b hover:bg-gray-50 transition-all duration-200">
      <td class="p-3 text-gray-600"><?= htmlspecialchars($t['name']) ?></td>
      <td class="p-3 text-center">
        <a href="/admin/product-types/edit?id=<?= $t['id'] ?>" class="text-[#C86052] hover:underline">Редактировать</a>
      </td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
