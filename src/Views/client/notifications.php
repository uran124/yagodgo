<?php /** @var string|null $userName */ ?>
<?php /** @var string|null $tgStart */ ?>
<main class="bg-gradient-to-br from-orange-50 via-white to-pink-50 min-h-screen pb-24">
  <div class="px-4 pt-6 space-y-6">
    <a href="https://t.me/YagodgoBot<?= $tgStart ? '?start=' . $tgStart : '' ?>" class="flex items-center justify-center space-x-2 bg-blue-500 text-white rounded-2xl py-3 shadow-lg hover:bg-blue-600 transition">
      <span class="material-icons-round">telegram</span>
      <span class="font-semibold">Подключить уведомления</span>
    </a>
    <p class="text-center text-gray-600 text-sm">
      После перехода нажмите <strong>Start</strong> в Telegram.
    </p>

    <div class="bg-white rounded-3xl shadow divide-y divide-gray-100">
      <?php
        $items = [
          'Уведомления об изменении статуса моих заказов',
          'Сообщения об акциях и спецпредложениях',
          'Информационные сообщения',
          'Поступление клубники',
          'Поступление черешни',
          'Поступление ежевики'
        ];
      ?>
      <?php foreach ($items as $label): ?>
        <div class="flex items-center justify-between p-4">
          <span class="text-gray-800 text-sm flex-1 pr-3"><?= $label ?></span>
          <label class="relative inline-flex items-center cursor-pointer">
            <input type="checkbox" class="sr-only peer">
            <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-red-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-red-600"></div>
          </label>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</main>
