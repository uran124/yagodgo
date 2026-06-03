<?php
/** @var array<int,array<string,mixed>> $chats */
/** @var array<int,array<string,mixed>> $activeOrders */
/** @var array<string,mixed>|null $selectedChat */
/** @var array<int,array<string,mixed>> $messages */
/** @var int $beforeMessageId */
/** @var array<int,array<int,array<string,mixed>>> $attachmentsByMessage */
$selectedChatId = $selectedChat ? (int)$selectedChat['id'] : 0;
$error = $_GET['error'] ?? '';
$chatTitle = static function (array $chat): string {
    return !empty($chat['order_id']) ? 'Заказ #' . $chat['order_id'] : 'Обращение без заказа';
};
?>
<div class="pt-20 pb-28 px-4 max-w-6xl mx-auto">
  <div class="mb-5 flex items-center justify-between gap-3">
    <div>
      <h1 class="text-2xl font-bold text-gray-900">Чат поддержки</h1>
      <p class="text-sm text-gray-500">Пишите по текущим заказам или создайте обращение без заказа.</p>
    </div>
    <a href="/" class="rounded-full bg-white/80 px-4 py-2 text-sm font-semibold text-gray-600 shadow">На главную</a>
  </div>

  <?php if ($error): ?>
    <div class="mb-4 rounded-2xl border border-red-200 bg-red-50 p-4 text-sm text-red-700"><?= htmlspecialchars((string)$error) ?></div>
  <?php endif; ?>

  <div class="grid gap-5 lg:grid-cols-[320px_1fr]">
    <section class="space-y-4">
      <div class="rounded-3xl bg-white/85 p-4 shadow-xl backdrop-blur">
        <h2 class="mb-3 font-bold text-gray-800">Ваши чаты</h2>
        <?php if ($chats === []): ?>
          <p class="rounded-2xl bg-gray-50 p-4 text-sm text-gray-500">Пока нет обращений.</p>
        <?php else: ?>
          <div class="space-y-2">
            <?php foreach ($chats as $chat): ?>
              <?php $active = (int)$chat['id'] === $selectedChatId; ?>
              <a href="/chat/<?= (int)$chat['id'] ?>" class="block rounded-2xl border p-3 transition <?= $active ? 'border-pink-300 bg-pink-50' : 'border-gray-100 bg-white hover:border-pink-200' ?>">
                <div class="flex items-center justify-between gap-2">
                  <span class="font-semibold text-gray-800"><?= htmlspecialchars($chatTitle($chat)) ?></span>
                  <?php if ((int)($chat['client_unread_count'] ?? 0) > 0): ?>
                    <span class="rounded-full bg-red-500 px-2 py-0.5 text-xs font-bold text-white"><?= (int)$chat['client_unread_count'] ?></span>
                  <?php endif; ?>
                </div>
                <p class="mt-1 line-clamp-2 text-xs text-gray-500"><?= htmlspecialchars(mb_substr((string)($chat['last_body'] ?? 'Фото'), 0, 90)) ?></p>
              </a>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <form action="/chat/start" method="post" enctype="multipart/form-data" class="rounded-3xl bg-white/85 p-4 shadow-xl backdrop-blur" id="newSupportChatForm">
        <?= csrf_field() ?>
        <h2 class="mb-3 font-bold text-gray-800">Новое сообщение</h2>
        <textarea name="body" maxlength="2000" rows="4" class="w-full rounded-2xl border border-gray-200 p-3 text-sm focus:border-pink-400 focus:outline-none" placeholder="Напишите вопрос поддержке"></textarea>
        <label class="mt-3 block rounded-2xl border border-dashed border-pink-200 bg-pink-50/60 p-3 text-sm text-gray-600">
          <span class="font-semibold text-pink-600">Добавить фото</span>
          <input type="file" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple class="mt-2 block w-full text-xs">
          <span class="mt-1 block text-xs text-gray-400">До 3 фото, до 5 МБ каждое.</span>
        </label>

        <?php if ($activeOrders !== []): ?>
          <div id="orderChoice" class="mt-4 hidden rounded-2xl bg-gray-50 p-3">
            <p class="mb-2 text-sm font-semibold text-gray-700">Ваш вопрос связан с заказом?</p>
            <div class="flex flex-wrap gap-2">
              <?php foreach ($activeOrders as $order): ?>
                <button type="button" data-order-id="<?= (int)$order['id'] ?>" class="support-order-choice rounded-full bg-white px-3 py-2 text-xs font-semibold text-gray-700 shadow hover:bg-pink-100">
                  Заказ #<?= (int)$order['id'] ?><?= !empty($order['delivery_date']) ? ' · ' . htmlspecialchars((string)$order['delivery_date']) : '' ?>
                </button>
              <?php endforeach; ?>
              <button type="button" data-order-id="" class="support-order-choice rounded-full bg-gray-800 px-3 py-2 text-xs font-semibold text-white shadow hover:bg-gray-700">Нет</button>
            </div>
          </div>
          <input type="hidden" name="order_id" id="supportOrderId" value="">
          <button type="button" id="showOrderChoiceBtn" class="mt-4 w-full rounded-2xl bg-gradient-to-r from-pink-500 to-red-400 px-4 py-3 font-bold text-white shadow-lg">Отправить</button>
          <button type="submit" id="submitSupportChatBtn" class="hidden"></button>
        <?php else: ?>
          <button type="submit" class="mt-4 w-full rounded-2xl bg-gradient-to-r from-pink-500 to-red-400 px-4 py-3 font-bold text-white shadow-lg">Отправить</button>
        <?php endif; ?>
      </form>
    </section>

    <section class="min-h-[520px] rounded-3xl bg-white/90 p-4 shadow-xl backdrop-blur">
      <?php if (!$selectedChat): ?>
        <div class="flex h-full min-h-[420px] flex-col items-center justify-center text-center text-gray-500">
          <span class="material-icons-round mb-3 text-5xl text-pink-300">support_agent</span>
          <p class="font-semibold">Выберите чат или отправьте новое сообщение.</p>
        </div>
      <?php else: ?>
        <div class="mb-4 border-b border-gray-100 pb-3">
          <h2 class="text-xl font-bold text-gray-900"><?= htmlspecialchars($chatTitle($selectedChat)) ?></h2>
          <p class="text-xs text-gray-500"><?= !empty($beforeMessageId) ? 'Показаны более ранние сообщения.' : 'Показываются последние 50 сообщений.' ?></p>
        </div>
        <?php if (count($messages) === 50): ?>
          <?php $firstMessageId = (int)($messages[0]['id'] ?? 0); ?>
          <a href="/chat/<?= (int)$selectedChat['id'] ?>?before_message_id=<?= $firstMessageId ?>" class="mb-4 inline-flex rounded-full bg-gray-100 px-3 py-1.5 text-xs font-bold text-gray-600">Показать ещё 50 ранних</a>
        <?php endif; ?>
        <?php if (!empty($beforeMessageId)): ?>
          <a href="/chat/<?= (int)$selectedChat['id'] ?>" class="mb-4 ml-2 inline-flex rounded-full bg-pink-100 px-3 py-1.5 text-xs font-bold text-pink-700">Вернуться к последним</a>
        <?php endif; ?>
        <div class="space-y-3">
          <?php foreach ($messages as $message): ?>
            <?php
              $isMine = (int)($message['sender_user_id'] ?? 0) === (int)($_SESSION['user_id'] ?? 0);
              $hidden = !empty($message['hidden_from_client_at']);
            ?>
            <div id="message-<?= (int)$message['id'] ?>" class="flex <?= $isMine ? 'justify-end' : 'justify-start' ?>">
              <div class="max-w-[82%] rounded-2xl px-4 py-3 text-sm shadow <?= $isMine ? 'bg-pink-500 text-white' : 'bg-gray-100 text-gray-800' ?>">
                <div class="mb-1 text-[11px] opacity-70"><?= htmlspecialchars((string)($message['sender_name_snapshot'] ?? '')) ?> · <?= htmlspecialchars((string)$message['created_at']) ?></div>
                <?php if ($hidden): ?>
                  <div class="italic opacity-80">Сообщение удалено</div>
                <?php else: ?>
                  <?php if (!empty($message['body'])): ?><div class="whitespace-pre-wrap"><?= nl2br(htmlspecialchars((string)$message['body'])) ?></div><?php endif; ?>
                  <?php if (!empty($attachmentsByMessage[(int)$message['id']])): ?>
                    <div class="mt-2 grid grid-cols-2 gap-2">
                      <?php foreach ($attachmentsByMessage[(int)$message['id']] as $photo): ?>
                        <a href="<?= htmlspecialchars((string)$photo['file_path']) ?>" target="_blank" rel="noopener"><img src="<?= htmlspecialchars((string)$photo['file_path']) ?>" alt="Фото" class="max-h-40 rounded-xl object-cover"></a>
                      <?php endforeach; ?>
                    </div>
                  <?php endif; ?>
                <?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
        <form action="/chat/<?= (int)$selectedChat['id'] ?>/messages" method="post" enctype="multipart/form-data" class="mt-5 rounded-2xl bg-gray-50 p-3">
          <?= csrf_field() ?>
          <textarea name="body" maxlength="2000" rows="3" class="w-full rounded-2xl border border-gray-200 p-3 text-sm focus:border-pink-400 focus:outline-none" placeholder="Ваш ответ"></textarea>
          <div class="mt-3 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <input type="file" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple class="text-xs text-gray-500">
            <button type="submit" class="rounded-2xl bg-gray-900 px-5 py-2.5 text-sm font-bold text-white">Отправить</button>
          </div>
        </form>
      <?php endif; ?>
    </section>
  </div>
</div>
<script>
(() => {
  const showBtn = document.getElementById('showOrderChoiceBtn');
  const orderChoice = document.getElementById('orderChoice');
  const orderInput = document.getElementById('supportOrderId');
  const submitBtn = document.getElementById('submitSupportChatBtn');
  if (!showBtn || !orderChoice || !orderInput || !submitBtn) return;
  showBtn.addEventListener('click', () => {
    orderChoice.classList.remove('hidden');
    showBtn.textContent = 'Выберите заказ или Нет';
  });
  document.querySelectorAll('.support-order-choice').forEach((button) => {
    button.addEventListener('click', () => {
      orderInput.value = button.dataset.orderId || '';
      submitBtn.click();
    });
  });
})();
</script>
