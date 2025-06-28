<?php
/** @var array $productTypes */
/** @var array $products */
/** @var array $categories */
/** @var array $materials */
/** @var array $pages */
?>

<h2 class="text-xl font-semibold mb-2">Категории товаров</h2>
<table class="min-w-full bg-white rounded shadow overflow-hidden mb-6">
  <thead class="bg-gray-200 text-gray-700">
    <tr>
      <th class="p-3 text-left font-semibold">Страница</th>
      <th class="p-3 text-left font-semibold">Title</th>
      <th class="p-3 text-left font-semibold">Description</th>
      <th class="p-3 text-left font-semibold">Keywords</th>
      <th class="p-3 text-center font-semibold">Редактировать</th>
    </tr>
  </thead>
  <tbody>
  <?php foreach ($productTypes as $pt): ?>
    <tr class="border-b hover:bg-gray-50 transition-all duration-200">
      <td class="p-3 text-gray-600">/catalog/<?= htmlspecialchars($pt['alias']) ?></td>
      <td class="p-3 text-gray-600"><?= htmlspecialchars($pt['meta_title']) ?></td>
      <td class="p-3 text-gray-600"><?= htmlspecialchars($pt['meta_description']) ?></td>
      <td class="p-3 text-gray-600"><?= htmlspecialchars($pt['meta_keywords']) ?></td>
      <td class="p-3 text-center">
        <a href="/admin/apps/seo/edit?type=product_type&id=<?= $pt['id'] ?>" class="text-[#C86052] hover:underline">Редактировать</a>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>

<h2 class="text-xl font-semibold mb-2">Товары</h2>
<table class="min-w-full bg-white rounded shadow overflow-hidden mb-6">
  <thead class="bg-gray-200 text-gray-700">
    <tr>
      <th class="p-3 text-left font-semibold">Страница</th>
      <th class="p-3 text-left font-semibold">Title</th>
      <th class="p-3 text-left font-semibold">Description</th>
      <th class="p-3 text-left font-semibold">Keywords</th>
      <th class="p-3 text-center font-semibold">Редактировать</th>
    </tr>
  </thead>
  <tbody>
  <?php foreach ($products as $p): ?>
    <tr class="border-b hover:bg-gray-50 transition-all duration-200">
      <td class="p-3 text-gray-600">/catalog/<?= htmlspecialchars($p['type_alias'].'/'.$p['alias']) ?></td>
      <td class="p-3 text-gray-600"><?= htmlspecialchars($p['meta_title']) ?></td>
      <td class="p-3 text-gray-600"><?= htmlspecialchars($p['meta_description']) ?></td>
      <td class="p-3 text-gray-600"><?= htmlspecialchars($p['meta_keywords']) ?></td>
      <td class="p-3 text-center">
        <a href="/admin/apps/seo/edit?type=product&id=<?= $p['id'] ?>" class="text-[#C86052] hover:underline">Редактировать</a>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>

<h2 class="text-xl font-semibold mb-2">Категории материалов</h2>
<table class="min-w-full bg-white rounded shadow overflow-hidden mb-6">
  <thead class="bg-gray-200 text-gray-700">
    <tr>
      <th class="p-3 text-left font-semibold">Страница</th>
      <th class="p-3 text-left font-semibold">Title</th>
      <th class="p-3 text-left font-semibold">Description</th>
      <th class="p-3 text-left font-semibold">Keywords</th>
      <th class="p-3 text-center font-semibold">Редактировать</th>
    </tr>
  </thead>
  <tbody>
  <?php foreach ($categories as $c): ?>
    <tr class="border-b hover:bg-gray-50 transition-all duration-200">
      <td class="p-3 text-gray-600">/content/<?= htmlspecialchars($c['alias']) ?></td>
      <td class="p-3 text-gray-600"><?= htmlspecialchars($c['meta_title']) ?></td>
      <td class="p-3 text-gray-600"><?= htmlspecialchars($c['meta_description']) ?></td>
      <td class="p-3 text-gray-600"><?= htmlspecialchars($c['meta_keywords']) ?></td>
      <td class="p-3 text-center">
        <a href="/admin/apps/seo/edit?type=category&id=<?= $c['id'] ?>" class="text-[#C86052] hover:underline">Редактировать</a>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>

<h2 class="text-xl font-semibold mb-2">Материалы</h2>
<table class="min-w-full bg-white rounded shadow overflow-hidden mb-6">
  <thead class="bg-gray-200 text-gray-700">
    <tr>
      <th class="p-3 text-left font-semibold">Страница</th>
      <th class="p-3 text-left font-semibold">Title</th>
      <th class="p-3 text-left font-semibold">Description</th>
      <th class="p-3 text-left font-semibold">Keywords</th>
      <th class="p-3 text-center font-semibold">Редактировать</th>
    </tr>
  </thead>
  <tbody>
  <?php foreach ($materials as $m): ?>
    <tr class="border-b hover:bg-gray-50 transition-all duration-200">
      <td class="p-3 text-gray-600">/content/<?= htmlspecialchars($m['cat_alias'].'/'.$m['alias']) ?></td>
      <td class="p-3 text-gray-600"><?= htmlspecialchars($m['meta_title']) ?></td>
      <td class="p-3 text-gray-600"><?= htmlspecialchars($m['meta_description']) ?></td>
      <td class="p-3 text-gray-600"><?= htmlspecialchars($m['meta_keywords']) ?></td>
      <td class="p-3 text-center">
        <a href="/admin/apps/seo/edit?type=material&id=<?= $m['id'] ?>" class="text-[#C86052] hover:underline">Редактировать</a>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>

<h2 class="text-xl font-semibold mb-2">Системные страницы</h2>
<table class="min-w-full bg-white rounded shadow overflow-hidden">
  <thead class="bg-gray-200 text-gray-700">
    <tr>
      <th class="p-3 text-left font-semibold">Страница</th>
      <th class="p-3 text-left font-semibold">Title</th>
      <th class="p-3 text-left font-semibold">Description</th>
      <th class="p-3 text-left font-semibold">Keywords</th>
      <th class="p-3 text-center font-semibold">Редактировать</th>
    </tr>
  </thead>
  <tbody>
  <?php foreach ($pages as $p): ?>
    <tr class="border-b hover:bg-gray-50 transition-all duration-200">
      <td class="p-3 text-gray-600">/<?= htmlspecialchars($p['page']) ?></td>
      <td class="p-3 text-gray-600"><?= htmlspecialchars($p['title']) ?></td>
      <td class="p-3 text-gray-600"><?= htmlspecialchars($p['description']) ?></td>
      <td class="p-3 text-gray-600"><?= htmlspecialchars($p['keywords']) ?></td>
      <td class="p-3 text-center">
        <a href="/admin/apps/seo/edit?type=page&page=<?= htmlspecialchars($p['page']) ?>" class="text-[#C86052] hover:underline">Редактировать</a>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
