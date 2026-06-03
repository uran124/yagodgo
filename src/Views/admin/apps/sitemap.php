<?php
/** @var array $settings */
/** @var array $staticPages */
/** @var array $categories */
/** @var array $materials */
/** @var array $productTypes */
/** @var array $products */
/** @var bool $hasCategoryInSitemap */
/** @var bool $hasTypeInSitemap */

$switch = static function (string $type, int $id, bool $checked, bool $enabled = true): void { ?>
  <form action="/admin/apps/sitemap/toggle-item" method="post" class="inline-block">
    <input type="hidden" name="type" value="<?= htmlspecialchars($type) ?>">
    <input type="hidden" name="id" value="<?= $id ?>">
    <label class="relative inline-flex items-center <?= $enabled ? 'cursor-pointer' : 'cursor-not-allowed opacity-50' ?>">
      <input type="checkbox" onchange="this.form.submit()" class="sr-only peer" <?= $checked ? 'checked' : '' ?> <?= $enabled ? '' : 'disabled' ?>>
      <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-red-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-red-600"></div>
    </label>
  </form>
<?php };

$pageSwitch = static function (string $key, bool $checked): void { ?>
  <form action="/admin/apps/sitemap/toggle-page" method="post" class="inline-block">
    <input type="hidden" name="key" value="<?= htmlspecialchars($key) ?>">
    <label class="relative inline-flex items-center cursor-pointer">
      <input type="checkbox" onchange="this.form.submit()" class="sr-only peer" <?= $checked ? 'checked' : '' ?>>
      <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-red-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-red-600"></div>
    </label>
  </form>
<?php };
?>
<div class="mb-6 rounded-2xl bg-white p-5 shadow">
  <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
    <div>
      <h1 class="text-2xl font-bold text-gray-900">Карта сайта</h1>
      <p class="mt-1 text-sm text-gray-600">В sitemap попадают только отмеченные и активные страницы BerryGo.</p>
      <p class="mt-1 text-sm text-gray-500">Последнее обновление: <?= $settings['last_generated'] ? htmlspecialchars((string)$settings['last_generated']) : 'никогда' ?></p>
    </div>
    <form action="/admin/apps/sitemap/generate" method="post">
      <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-[#C86052] px-5 py-3 font-semibold text-white shadow hover:bg-[#B44D47]">
        <span class="material-icons-round text-base">sync</span>
        Обновить sitemap.xml
      </button>
    </form>
  </div>
</div>

<div class="grid gap-6 xl:grid-cols-2">
  <section class="rounded-2xl bg-white shadow overflow-hidden">
    <div class="border-b px-5 py-4">
      <h2 class="text-xl font-semibold">Страницы приложения</h2>
      <p class="text-sm text-gray-500">Основные публичные страницы.</p>
    </div>
    <table class="min-w-full">
      <thead class="bg-gray-100 text-gray-700">
        <tr><th class="p-3 text-left">Страница</th><th class="p-3 text-left">URL</th><th class="p-3 text-center">В карте</th></tr>
      </thead>
      <tbody>
      <?php foreach ($staticPages as $page): ?>
        <tr class="border-t hover:bg-gray-50">
          <td class="p-3 font-medium text-gray-800"><?= htmlspecialchars((string)$page['label']) ?></td>
          <td class="p-3 text-gray-500">/<?= htmlspecialchars((string)$page['path']) ?></td>
          <td class="p-3 text-center"><?php $pageSwitch((string)$page['key'], (bool)$page['in_sitemap']); ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </section>

  <section class="rounded-2xl bg-white shadow overflow-hidden">
    <div class="border-b px-5 py-4">
      <h2 class="text-xl font-semibold">Категории товаров</h2>
      <p class="text-sm text-gray-500">Показываются категории, где есть активные товары.</p>
    </div>
    <table class="min-w-full">
      <thead class="bg-gray-100 text-gray-700">
        <tr><th class="p-3 text-left">Категория</th><th class="p-3 text-left">URL</th><th class="p-3 text-center">В карте</th></tr>
      </thead>
      <tbody>
      <?php foreach ($productTypes as $type): ?>
        <tr class="border-t hover:bg-gray-50">
          <td class="p-3 text-gray-800"><?= htmlspecialchars((string)$type['name']) ?> <span class="text-xs text-gray-400">(<?= (int)$type['active_products_count'] ?>)</span></td>
          <td class="p-3 text-gray-500">/catalog/<?= htmlspecialchars((string)$type['alias']) ?></td>
          <td class="p-3 text-center"><?php $switch('product_type', (int)$type['id'], (bool)$type['in_sitemap'], $hasTypeInSitemap); ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </section>

  <section class="rounded-2xl bg-white shadow overflow-hidden">
    <div class="border-b px-5 py-4">
      <h2 class="text-xl font-semibold">Разделы материалов</h2>
      <p class="text-sm text-gray-500">Фильтр: отключение раздела убирает из sitemap все его материалы.</p>
    </div>
    <table class="min-w-full">
      <thead class="bg-gray-100 text-gray-700">
        <tr><th class="p-3 text-left">Раздел</th><th class="p-3 text-left">Активных материалов</th><th class="p-3 text-center">В карте</th></tr>
      </thead>
      <tbody>
      <?php foreach ($categories as $category): ?>
        <tr class="border-t hover:bg-gray-50">
          <td class="p-3 text-gray-800"><?= htmlspecialchars((string)$category['name']) ?></td>
          <td class="p-3 text-gray-500"><?= (int)$category['active_materials_count'] ?></td>
          <td class="p-3 text-center"><?php $switch('category', (int)$category['id'], (bool)$category['in_sitemap'], $hasCategoryInSitemap); ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </section>

  <section class="rounded-2xl bg-white shadow overflow-hidden">
    <div class="border-b px-5 py-4">
      <h2 class="text-xl font-semibold">Активные товары</h2>
      <p class="text-sm text-gray-500">Все включённые и активные товарные карточки.</p>
    </div>
    <table class="min-w-full">
      <thead class="bg-gray-100 text-gray-700">
        <tr><th class="p-3 text-left">Товар</th><th class="p-3 text-left">URL</th><th class="p-3 text-center">В карте</th></tr>
      </thead>
      <tbody>
      <?php foreach ($products as $product): ?>
        <tr class="border-t hover:bg-gray-50">
          <td class="p-3 text-gray-800"><?= htmlspecialchars(trim((string)$product['product'] . ' ' . (string)$product['variety'])) ?></td>
          <td class="p-3 text-gray-500">/catalog/<?= htmlspecialchars((string)$product['type_alias']) ?>/<?= htmlspecialchars((string)$product['alias']) ?></td>
          <td class="p-3 text-center"><?php $switch('product', (int)$product['id'], (bool)$product['in_sitemap']); ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </section>
</div>

<section class="mt-6 rounded-2xl bg-white shadow overflow-hidden">
  <div class="border-b px-5 py-4">
    <h2 class="text-xl font-semibold">Активные материалы</h2>
    <p class="text-sm text-gray-500">Статьи, новости и промо-материалы, доступные пользователям.</p>
  </div>
  <table class="min-w-full">
    <thead class="bg-gray-100 text-gray-700">
      <tr><th class="p-3 text-left">Материал</th><th class="p-3 text-left">Раздел</th><th class="p-3 text-left">URL</th><th class="p-3 text-center">В карте</th></tr>
    </thead>
    <tbody>
    <?php foreach ($materials as $material): ?>
      <tr class="border-t hover:bg-gray-50">
        <td class="p-3 text-gray-800"><?= htmlspecialchars((string)$material['title']) ?></td>
        <td class="p-3 text-gray-500"><?= htmlspecialchars((string)$material['category_name']) ?></td>
        <td class="p-3 text-gray-500">/content/<?= htmlspecialchars((string)$material['category_alias']) ?>/<?= htmlspecialchars((string)$material['alias']) ?></td>
        <td class="p-3 text-center"><?php $switch('material', (int)$material['id'], (bool)$material['in_sitemap']); ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</section>
