<?php /** @var array $products */ ?>

<a href="/admin/products/edit" class="bg-[#C86052] text-white px-4 py-2 rounded mb-4 inline-flex items-center">
  <span class="material-icons-round text-base mr-1">add</span> Добавить товар</a>

<table class="min-w-full bg-white rounded shadow overflow-hidden">
  <thead class="bg-gray-200 text-gray-700">
    <tr>
      <th class="p-2">ID</th>
      <th class="p-2">Продукт</th>
      <th class="p-2">Сорт</th>
      <th class="p-2">Страна</th>
      <th class="p-2">Цена</th>
      <th class="p-2">Остаток (ящиков)</th>
      <th class="p-2">Изображение</th>
      <th class="p-2">Действия</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($products as $p): ?>
      <tr class="border-b hover:bg-gray-50">
        <td class="p-2"><?= $p['id'] ?></td>
        <td class="p-2"><?= htmlspecialchars($p['product']) ?></td>
        <td class="p-2"><?= htmlspecialchars($p['variety']) ?></td>
        <td class="p-2"><?= htmlspecialchars($p['origin_country']) ?></td>
        <td class="p-2"><?= $p['price'] ?> ₽/<?= $p['unit'] ?></td>
        <td class="p-2"><?= $p['stock_boxes'] ?></td>
        <td class="p-2">
          <?php if ($p['image_path']): ?>
            <img src="<?= htmlspecialchars($p['image_path']) ?>" alt="" class="w-12 h-12 object-cover rounded">
          <?php endif; ?>
        </td>
        <td class="p-2">
          <a href="/admin/products/edit?id=<?= $p['id'] ?>"
             class="text-[#C86052] hover:underline">
            Редактировать
          </a>
        </td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>
