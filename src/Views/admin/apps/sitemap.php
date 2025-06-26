<?php
/** @var array $materials */
/** @var array $products */
/** @var array $settings */
?>
<div class="mb-4">
  <form action="/admin/apps/sitemap/generate" method="post" class="inline-block">
    <button type="submit" class="bg-[#C86052] text-white px-4 py-2 rounded">Обновить</button>
  </form>
  <span class="ml-4 text-gray-600">Последнее обновление: <?= $settings['last_generated'] ? htmlspecialchars($settings['last_generated']) : 'никогда' ?></span>
</div>

<h2 class="text-xl font-semibold mb-2">Материалы</h2>
<table class="min-w-full bg-white rounded shadow overflow-hidden mb-6">
  <thead class="bg-gray-200 text-gray-700">
    <tr>
      <th class="p-3 text-left font-semibold">Заголовок</th>
      <th class="p-3 text-center font-semibold">В sitemap</th>
    </tr>
  </thead>
  <tbody>
  <?php foreach ($materials as $m): ?>
    <tr class="border-b hover:bg-gray-50 transition-all duration-200">
      <td class="p-3 text-gray-600"><?= htmlspecialchars($m['title']) ?></td>
      <td class="p-3 text-center">
        <form action="/admin/apps/sitemap/toggle-item" method="post" class="inline-block">
          <input type="hidden" name="type" value="material">
          <input type="hidden" name="id" value="<?= $m['id'] ?>">
          <label class="relative inline-flex items-center cursor-pointer">
            <input type="checkbox" onchange="this.form.submit()" class="sr-only peer" <?= $m['in_sitemap'] ? 'checked' : '' ?>>
            <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-red-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-red-600"></div>
          </label>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>

<h2 class="text-xl font-semibold mb-2">Товары</h2>
<table class="min-w-full bg-white rounded shadow overflow-hidden">
  <thead class="bg-gray-200 text-gray-700">
    <tr>
      <th class="p-3 text-left font-semibold">Товар</th>
      <th class="p-3 text-center font-semibold">В sitemap</th>
    </tr>
  </thead>
  <tbody>
  <?php foreach ($products as $p): ?>
    <tr class="border-b hover:bg-gray-50 transition-all duration-200">
      <td class="p-3 text-gray-600">
        <?= htmlspecialchars($p['product'] . ' ' . $p['variety']) ?>
      </td>
      <td class="p-3 text-center">
        <form action="/admin/apps/sitemap/toggle-item" method="post" class="inline-block">
          <input type="hidden" name="type" value="product">
          <input type="hidden" name="id" value="<?= $p['id'] ?>">
          <label class="relative inline-flex items-center cursor-pointer">
            <input type="checkbox" onchange="this.form.submit()" class="sr-only peer" <?= $p['in_sitemap'] ? 'checked' : '' ?>>
            <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-red-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-red-600"></div>
          </label>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
