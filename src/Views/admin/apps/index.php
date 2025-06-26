<?php /** @var array $sitemap */ ?>
<div class="bg-white p-4 rounded shadow">
  <div class="flex items-center justify-between">
    <div>
      <h2 class="text-lg font-semibold mb-1">Sitemap</h2>
      <div class="text-sm text-gray-600">Последнее обновление:
        <?= $sitemap['last_generated'] ? htmlspecialchars($sitemap['last_generated']) : 'никогда' ?>
      </div>
    </div>
    <form action="/admin/apps/sitemap/toggle" method="post">
      <label class="relative inline-flex items-center cursor-pointer">
        <input type="checkbox" onchange="this.form.submit()" class="sr-only peer" <?= $sitemap['is_active'] ? 'checked' : '' ?>>
        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-red-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-red-600"></div>
      </label>
    </form>
  </div>
  <a href="/admin/apps/sitemap" class="text-[#C86052] hover:underline mt-4 inline-block">Настройки</a>
</div>
