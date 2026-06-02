<?php
/** @var array<int,array<string,mixed>> $chats */
/** @var array<string,mixed>|null $selectedChat */
/** @var array<int,array<string,mixed>> $messages */
/** @var int $beforeMessageId */
/** @var array<int,array<int,array<string,mixed>>> $attachmentsByMessage */
/** @var string $basePath */
$selectedChatId = $selectedChat ? (int)$selectedChat['id'] : 0;
$error = $_GET['error'] ?? '';
$chatTitle = static function (array $chat): string {
    return !empty($chat['order_id']) ? 'Заказ #' . $chat['order_id'] : 'Без заказа';
};
?>
<div class="grid min-h-[calc(100vh-9rem)] gap-4 lg:grid-cols-[360px_1fr]">
  <aside class="rounded-2xl bg-white p-4 shadow-lg">
    <div class="mb-4 flex items-center justify-between gap-2">
      <div>
        <h2 class="text-lg font-bold text-gray-100 md:text-gray-700">Обращения</h2>
        <p class="text-xs text-gray-400">Все чаты видны менеджерам и администраторам.</p>
      </div>
      <span class="rounded-full bg-[#C86052]/20 px-2 py-1 text-xs font-bold text-[#C86052]"><?= count($chats) ?></span>
    </div>
    <div class="space-y-2 overflow-y-auto pr-1 lg:max-h-[calc(100vh-14rem)]">
      <?php if ($chats === []): ?>
        <div class="rounded-xl bg-gray-100 p-4 text-sm text-gray-500">Пока нет обращений.</div>
      <?php endif; ?>
      <?php foreach ($chats as $chat): ?>
        <?php $active = (int)$chat['id'] === $selectedChatId; ?>
        <a href="<?= htmlspecialchars($basePath) ?>/chats/<?= (int)$chat['id'] ?>" class="block rounded-2xl border p-3 transition <?= $active ? 'border-[#C86052] bg-[#C86052]/10' : 'border-gray-200 bg-white hover:border-[#C86052]/60' ?>">
          <div class="flex items-start justify-between gap-2">
            <div class="min-w-0">
              <div class="truncate font-bold text-gray-100 md:text-gray-800"><?= htmlspecialchars((string)($chat['client_name'] ?? 'Клиент')) ?></div>
              <div class="text-xs text-gray-400"><?= htmlspecialchars($chatTitle($chat)) ?><?= !empty($chat['client_phone']) ? ' · ' . htmlspecialchars((string)$chat['client_phone']) : '' ?></div>
            </div>
            <?php if ((int)($chat['staff_unread_count'] ?? 0) > 0): ?>
              <span class="rounded-full bg-red-500 px-2 py-0.5 text-xs font-bold text-white"><?= (int)$chat['staff_unread_count'] ?></span>
            <?php endif; ?>
          </div>
          <p class="mt-2 line-clamp-2 text-xs text-gray-500"><?= htmlspecialchars(mb_substr((string)($chat['last_body'] ?? 'Фото'), 0, 110)) ?></p>
          <div class="mt-2 flex items-center justify-between gap-2 text-[11px] text-gray-400">
            <span><?= htmlspecialchars((string)($chat['last_message_at'] ?? $chat['created_at'] ?? '')) ?></span>
            <?php if (!empty($chat['internal_note'])): ?>
              <span class="rounded-full bg-amber-100 px-2 py-0.5 font-semibold text-amber-700">есть заметка</span>
            <?php endif; ?>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  </aside>

  <section class="rounded-2xl bg-white p-4 shadow-lg">
    <?php if ($error): ?>
      <div class="mb-4 rounded-xl border border-red-300 bg-red-50 p-3 text-sm text-red-700"><?= htmlspecialchars((string)$error) ?></div>
    <?php endif; ?>

    <?php if (!$selectedChat): ?>
      <div class="flex h-full min-h-[420px] flex-col items-center justify-center text-center text-gray-400">
        <span class="material-icons-round mb-3 text-6xl text-[#C86052]">forum</span>
        <p class="text-lg font-semibold">Выберите обращение слева.</p>
      </div>
    <?php else: ?>
      <div class="mb-4 grid gap-3 border-b border-gray-200 pb-4 xl:grid-cols-[1fr_320px]">
        <div>
          <h2 class="text-xl font-bold text-gray-100 md:text-gray-800"><?= htmlspecialchars((string)($selectedChat['client_name'] ?? 'Клиент')) ?> · <?= htmlspecialchars($chatTitle($selectedChat)) ?></h2>
          <p class="text-sm text-gray-400">
            <?= !empty($selectedChat['client_phone']) ? htmlspecialchars((string)$selectedChat['client_phone']) . ' · ' : '' ?>
            <?= !empty($beforeMessageId) ? 'Показаны более ранние сообщения.' : 'Показываются последние 50 сообщений.' ?>
          </p>
        </div>
        <form method="post" action="<?= htmlspecialchars($basePath) ?>/chats/<?= (int)$selectedChat['id'] ?>/note" class="rounded-2xl bg-amber-50 p-3">
          <?= csrf_field() ?>
          <label class="mb-1 block text-xs font-bold uppercase tracking-wide text-amber-700">Заметка</label>
          <textarea name="internal_note" rows="3" class="w-full rounded-xl border border-amber-200 bg-white p-2 text-sm text-gray-800" placeholder="Внутренняя заметка для сотрудников"><?= htmlspecialchars((string)($selectedChat['internal_note'] ?? '')) ?></textarea>
          <button type="submit" class="mt-2 rounded-xl bg-amber-500 px-3 py-1.5 text-xs font-bold text-white">Сохранить заметку</button>
          <?php if (!empty($selectedChat['internal_note_updated_at'])): ?>
            <div class="mt-1 text-[11px] text-amber-700">Обновлено: <?= htmlspecialchars((string)$selectedChat['internal_note_updated_at']) ?><?= !empty($selectedChat['note_user_name']) ? ' · ' . htmlspecialchars((string)$selectedChat['note_user_name']) : '' ?></div>
          <?php endif; ?>
        </form>
      </div>

      <?php if (count($messages) === 50): ?>
        <?php $firstMessageId = (int)($messages[0]['id'] ?? 0); ?>
        <a href="<?= htmlspecialchars($basePath) ?>/chats/<?= (int)$selectedChat['id'] ?>?before_message_id=<?= $firstMessageId ?>" class="mb-4 inline-flex rounded-full bg-gray-100 px-3 py-1.5 text-xs font-bold text-gray-600">Показать ещё 50 ранних</a>
      <?php endif; ?>
      <?php if (!empty($beforeMessageId)): ?>
        <a href="<?= htmlspecialchars($basePath) ?>/chats/<?= (int)$selectedChat['id'] ?>" class="mb-4 ml-2 inline-flex rounded-full bg-[#C86052]/20 px-3 py-1.5 text-xs font-bold text-[#C86052]">Вернуться к последним</a>
      <?php endif; ?>
      <div class="space-y-3">
        <?php foreach ($messages as $message): ?>
          <?php
            $isClient = (int)($message['sender_user_id'] ?? 0) === (int)$selectedChat['user_id'];
            $hidden = !empty($message['hidden_from_client_at']);
          ?>
          <div id="message-<?= (int)$message['id'] ?>" class="flex <?= $isClient ? 'justify-start' : 'justify-end' ?>">
            <div class="max-w-[86%] rounded-2xl px-4 py-3 text-sm shadow <?= $isClient ? 'bg-gray-100 text-gray-800' : 'bg-[#C86052]/20 text-gray-100 md:text-gray-800' ?>">
              <div class="mb-1 flex flex-wrap items-center gap-2 text-[11px] opacity-70">
                <span><?= htmlspecialchars((string)($message['sender_name_snapshot'] ?? '')) ?></span>
                <span><?= htmlspecialchars((string)$message['created_at']) ?></span>
                <?php if (!empty($message['edited_at'])): ?><span>изменено <?= htmlspecialchars((string)$message['edited_at']) ?></span><?php endif; ?>
                <?php if ($hidden): ?><span class="rounded-full bg-red-100 px-2 py-0.5 font-bold text-red-600">удалено для клиента</span><?php endif; ?>
              </div>
              <?php if (!empty($message['body'])): ?><div class="whitespace-pre-wrap"><?= nl2br(htmlspecialchars((string)$message['body'])) ?></div><?php endif; ?>
              <?php if (!empty($attachmentsByMessage[(int)$message['id']])): ?>
                <div class="mt-2 grid grid-cols-2 gap-2 sm:grid-cols-3">
                  <?php foreach ($attachmentsByMessage[(int)$message['id']] as $photo): ?>
                    <a href="<?= htmlspecialchars((string)$photo['file_path']) ?>" target="_blank" rel="noopener"><img src="<?= htmlspecialchars((string)$photo['file_path']) ?>" alt="Фото" class="max-h-44 rounded-xl object-cover"></a>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
              <?php if (!$isClient): ?>
                <details class="mt-3 rounded-xl bg-white/40 p-2">
                  <summary class="cursor-pointer text-xs font-bold">Редактировать</summary>
                  <form method="post" action="<?= htmlspecialchars($basePath) ?>/chats/<?= (int)$selectedChat['id'] ?>/messages/<?= (int)$message['id'] ?>/edit" class="mt-2 space-y-2">
                    <?= csrf_field() ?>
                    <textarea name="body" rows="3" class="w-full rounded-xl border border-gray-300 p-2 text-sm text-gray-800"><?= htmlspecialchars((string)($message['body'] ?? '')) ?></textarea>
                    <button type="submit" class="rounded-lg bg-gray-900 px-3 py-1.5 text-xs font-bold text-white">Сохранить</button>
                  </form>
                </details>
              <?php endif; ?>
              <?php if (!$hidden): ?>
                <form method="post" action="<?= htmlspecialchars($basePath) ?>/chats/<?= (int)$selectedChat['id'] ?>/messages/<?= (int)$message['id'] ?>/hide" class="mt-2">
                  <?= csrf_field() ?>
                  <button type="submit" class="text-xs font-bold text-red-500" onclick="return confirm('Скрыть сообщение от клиента?')">Скрыть от клиента</button>
                </form>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <form action="<?= htmlspecialchars($basePath) ?>/chats/<?= (int)$selectedChat['id'] ?>/messages" method="post" enctype="multipart/form-data" class="mt-5 rounded-2xl bg-gray-100 p-3">
        <?= csrf_field() ?>
        <textarea name="body" maxlength="2000" rows="3" class="w-full rounded-2xl border border-gray-300 p-3 text-sm text-gray-800" placeholder="Ответ клиенту"></textarea>
        <div class="mt-3 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <input type="file" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple class="text-xs text-gray-500">
          <button type="submit" class="rounded-2xl bg-[#C86052] px-5 py-2.5 text-sm font-bold text-white">Отправить</button>
        </div>
      </form>
    <?php endif; ?>
  </section>
</div>
