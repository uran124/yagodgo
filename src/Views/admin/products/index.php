<?php /** @var array $products */ ?>

<a href="/admin/products/edit" class="bg-[#C86052] text-white px-4 py-2 rounded mb-4 inline-flex items-center">
  <span class="material-icons-round text-base mr-1">add</span> Добавить товар</a>

<table class="min-w-full bg-white rounded shadow overflow-hidden">
  <thead class="bg-gray-200 text-gray-700">
    <tr>
      <th class="p-2">Продукт</th>
      <th class="p-2">Сорт</th>
      <th class="p-2">Вес ящика</th>
      <th class="p-2">Цена</th>
      <th class="p-2">Остаток (ящиков)</th>
      <th class="p-2">Активен</th>
      <th class="p-2">Удалить</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($products as $p): ?>
      <tr class="border-b hover:bg-gray-50">
        <td class="p-2">
          <a href="/admin/products/edit?id=<?= $p['id'] ?>" class="text-[#C86052] hover:underline">
            <?= htmlspecialchars($p['product']) ?>
          </a>
        </td>
        <td class="p-2"><?= htmlspecialchars($p['variety']) ?></td>
        <td class="p-2">
          <?= $p['box_size'] ?> <?= htmlspecialchars($p['box_unit']) ?>
        </td>
        <td class="p-2"><?= $p['price'] ?> ₽/<?= $p['unit'] ?></td>
        <td class="p-2"><?= $p['stock_boxes'] ?></td>
        <td class="p-2">
          <form action="/admin/products/toggle" method="post">
            <input type="hidden" name="id" value="<?= $p['id'] ?>">
            <input type="checkbox" name="active" onchange="this.form.submit()" <?= $p['is_active'] ? 'checked' : '' ?>>
          </form>
        </td>
        <td class="p-2 text-center">
          <form action="/admin/products/delete" method="post" onsubmit="return confirm('Удалить товар?');">
            <input type="hidden" name="id" value="<?= $p['id'] ?>">
            <button type="submit" class="text-red-600">
              <span class="material-icons-round">delete</span>
            </button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>
