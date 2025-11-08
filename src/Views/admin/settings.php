<?php
/** @var array $settings */
/** @var array $themeColors */
$themeColors = $themeColors ?? [];
?>
<form action="/admin/settings" method="post" class="bg-white p-6 rounded shadow max-w-lg space-y-4">
  <div>
    <label class="block mb-1">Название компании</label>
    <input name="company_name" type="text"
           value="<?= htmlspecialchars($settings['company_name'] ?? '') ?>"
           class="w-full border px-2 py-1 rounded">
  </div>
  <div>
    <label class="block mb-1">Контактный телефон</label>
    <input name="contact_phone" type="text"
           value="<?= htmlspecialchars($settings['contact_phone'] ?? '') ?>"
           class="w-full border px-2 py-1 rounded">
  </div>
  <?php if (!empty($themeColors)): ?>
    <fieldset class="border border-gray-200 rounded-lg p-4 space-y-3">
      <legend class="px-2 text-sm font-semibold text-gray-600">Цветовая тема</legend>
      <div class="grid gap-3 sm:grid-cols-2">
        <label class="block text-sm">
          <span class="block mb-1 text-gray-700">Светлая тема</span>
          <div class="relative">
            <select name="theme_light_primary" class="w-full border px-3 py-2 rounded appearance-none focus:outline-none focus:ring-2 focus:ring-offset-1 focus:ring-emerald-400">
              <?php foreach ($themeColors as $key => $palette): ?>
                <?php $light = $palette['light']; ?>
                <option value="<?= htmlspecialchars($key) ?>"
                        <?= ($settings['theme_light_primary'] ?? 'pink') === $key ? 'selected' : '' ?>>
                  <?= htmlspecialchars($palette['label']) ?>
                </option>
              <?php endforeach; ?>
            </select>
            <span class="pointer-events-none material-icons-round absolute right-2 top-1/2 -translate-y-1/2 text-gray-400">expand_more</span>
          </div>
          <span class="mt-1 flex h-2 rounded-full" style="background: linear-gradient(135deg, <?= htmlspecialchars($themeColors[$settings['theme_light_primary'] ?? 'pink']['light']['strong'] ?? $themeColors['pink']['light']['strong']) ?>, <?= htmlspecialchars($themeColors[$settings['theme_light_primary'] ?? 'pink']['light']['secondary'] ?? $themeColors['pink']['light']['secondary']) ?>);"></span>
        </label>
        <label class="block text-sm">
          <span class="block mb-1 text-gray-700">Тёмная тема</span>
          <div class="relative">
            <select name="theme_dark_primary" class="w-full border px-3 py-2 rounded appearance-none focus:outline-none focus:ring-2 focus:ring-offset-1 focus:ring-emerald-400">
              <?php foreach ($themeColors as $key => $palette): ?>
                <?php $dark = $palette['dark']; ?>
                <option value="<?= htmlspecialchars($key) ?>"
                        <?= ($settings['theme_dark_primary'] ?? 'pink') === $key ? 'selected' : '' ?>>
                  <?= htmlspecialchars($palette['label']) ?>
                </option>
              <?php endforeach; ?>
            </select>
            <span class="pointer-events-none material-icons-round absolute right-2 top-1/2 -translate-y-1/2 text-gray-400">expand_more</span>
          </div>
          <span class="mt-1 flex h-2 rounded-full" style="background: linear-gradient(135deg, <?= htmlspecialchars($themeColors[$settings['theme_dark_primary'] ?? 'pink']['dark']['strong'] ?? $themeColors['pink']['dark']['strong']) ?>, <?= htmlspecialchars($themeColors[$settings['theme_dark_primary'] ?? 'pink']['dark']['secondary'] ?? $themeColors['pink']['dark']['secondary']) ?>);"></span>
        </label>
      </div>
      <p class="text-xs text-gray-500">Выбранный цвет применится к основным кнопкам, ссылкам и акцентам пользовательского интерфейса.</p>
    </fieldset>
  <?php endif; ?>
  <!-- Добавьте другие поля по аналогии -->
  <button type="submit"
          class="accent-gradient text-white px-4 py-2 rounded accent-focus transition">
    Сохранить настройки
  </button>
</form>
