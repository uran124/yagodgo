<?php /** @var string|null $userName */ ?>
<?php /** @var string|null $tgStart */ ?>
<?php /** @var array<int,array<string,mixed>> $notifications */ ?>
<main class="bg-gradient-to-br from-orange-50 via-white to-pink-50 min-h-screen pb-24">
  <div class="px-4 pt-6 space-y-6">
    <a href="https://t.me/YagodgoBot<?= $tgStart ? '?start=' . $tgStart : '' ?>" class="flex items-center justify-center space-x-2 bg-blue-500 text-white rounded-2xl py-3 shadow-lg hover:bg-blue-600 transition">
      <span class="material-icons-round">telegram</span>
      <span class="font-semibold">Подключить уведомления</span>
    </a>
    <p class="text-center text-gray-600 text-sm">
      После перехода нажмите <strong>Start</strong> в Telegram.
    </p>

    <button type="button"
            data-open-notify-settings
            class="w-full bg-white border border-gray-200 rounded-2xl py-3 text-gray-800 font-semibold shadow-sm hover:bg-gray-50 transition">
      Настройка уведомлений
    </button>

    <section class="bg-white rounded-3xl shadow divide-y divide-gray-100 overflow-hidden">
      <div class="px-4 py-3 bg-gray-50">
        <h2 class="text-sm font-semibold text-gray-700">Уведомления от приложения</h2>
      </div>
      <?php if (!empty($notifications)): ?>
        <?php foreach ($notifications as $n): ?>
          <div class="p-4">
            <p class="text-sm font-medium text-gray-800"><?= htmlspecialchars((string)($n['description'] ?? 'Уведомление')) ?></p>
            <?php if (!empty($n['code'])): ?>
              <p class="text-xs text-gray-400 mt-1">Код: <?= htmlspecialchars((string)$n['code']) ?></p>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <div class="p-4 text-sm text-gray-500">Пока нет новых уведомлений.</div>
      <?php endif; ?>
    </section>
  </div>

  <div data-notify-modal class="fixed inset-0 bg-black/40 z-50 hidden items-end sm:items-center justify-center">
    <div class="bg-white w-full sm:max-w-xl sm:rounded-2xl rounded-t-2xl p-4 sm:p-6 max-h-[85vh] overflow-y-auto">
      <div class="flex items-center justify-between mb-4">
        <h3 class="text-base sm:text-lg font-semibold text-gray-800">Настройка уведомлений</h3>
        <button type="button" data-close-notify-settings class="text-gray-500 hover:text-gray-700">
          <span class="material-icons-round">close</span>
        </button>
      </div>

      <div class="bg-white rounded-2xl border divide-y divide-gray-100">
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
  </div>
</main>

<script>
  (() => {
    const openBtn = document.querySelector('[data-open-notify-settings]');
    const closeBtn = document.querySelector('[data-close-notify-settings]');
    const modal = document.querySelector('[data-notify-modal]');
    if (!openBtn || !closeBtn || !modal) return;

    const open = () => {
      modal.classList.remove('hidden');
      modal.classList.add('flex');
    };
    const close = () => {
      modal.classList.add('hidden');
      modal.classList.remove('flex');
    };

    openBtn.addEventListener('click', open);
    closeBtn.addEventListener('click', close);
    modal.addEventListener('click', (e) => {
      if (e.target === modal) close();
    });
  })();
</script>
