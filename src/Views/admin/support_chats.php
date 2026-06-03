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
<div class="grid min-h-[calc(100vh-9rem)] gap-4 text-slate-100 lg:grid-cols-[360px_1fr]">
  <aside class="rounded-2xl border border-slate-700/70 bg-[#172235] p-4 shadow-2xl shadow-black/20">
    <div class="mb-4 flex items-center justify-between gap-2">
      <div>
        <h2 class="text-lg font-bold text-slate-50">Обращения</h2>
        <p class="text-xs text-slate-400">Все чаты видны менеджерам и администраторам.</p>
      </div>
      <span class="rounded-full bg-pink-500/20 px-2 py-1 text-xs font-bold text-pink-200 ring-1 ring-pink-400/30"><?= count($chats) ?></span>
    </div>
    <div class="space-y-2 overflow-y-auto pr-1 lg:max-h-[calc(100vh-14rem)]">
      <?php if ($chats === []): ?>
        <div class="rounded-xl border border-slate-700 bg-slate-900/70 p-4 text-sm text-slate-400">Пока нет обращений.</div>
      <?php endif; ?>
      <?php foreach ($chats as $chat): ?>
        <?php $active = (int)$chat['id'] === $selectedChatId; ?>
        <a href="<?= htmlspecialchars($basePath) ?>/chats/<?= (int)$chat['id'] ?>" class="block rounded-2xl border p-3 transition <?= $active ? 'border-pink-400 bg-pink-500/12 shadow-lg shadow-pink-950/20' : 'border-slate-700 bg-slate-900/45 hover:border-pink-400/70 hover:bg-slate-800/70' ?>">
          <div class="flex items-start justify-between gap-2">
            <div class="min-w-0">
              <div class="truncate font-bold text-slate-50"><?= htmlspecialchars((string)($chat['client_name'] ?? 'Клиент')) ?></div>
              <div class="text-xs text-slate-400"><?= htmlspecialchars($chatTitle($chat)) ?><?= !empty($chat['client_phone']) ? ' · ' . htmlspecialchars((string)$chat['client_phone']) : '' ?></div>
            </div>
            <?php if ((int)($chat['staff_unread_count'] ?? 0) > 0): ?>
              <span class="rounded-full bg-red-500 px-2 py-0.5 text-xs font-bold text-white shadow shadow-red-950/30"><?= (int)$chat['staff_unread_count'] ?></span>
            <?php endif; ?>
          </div>
          <p class="mt-2 line-clamp-2 text-xs text-slate-300"><?= htmlspecialchars(mb_substr((string)($chat['last_body'] ?? 'Фото'), 0, 110)) ?></p>
          <div class="mt-2 flex items-center justify-between gap-2 text-[11px] text-slate-500">
            <span><?= htmlspecialchars((string)($chat['last_message_at'] ?? $chat['created_at'] ?? '')) ?></span>
            <?php if (!empty($chat['internal_note'])): ?>
              <span class="rounded-full bg-amber-400/15 px-2 py-0.5 font-semibold text-amber-200 ring-1 ring-amber-300/20">есть заметка</span>
            <?php endif; ?>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  </aside>

  <section class="rounded-2xl border border-slate-700/70 bg-[#172235] p-4 shadow-2xl shadow-black/20">
    <?php if ($error): ?>
      <div class="mb-4 rounded-xl border border-red-400/50 bg-red-950/40 p-3 text-sm text-red-100"><?= htmlspecialchars((string)$error) ?></div>
    <?php endif; ?>

    <?php if (!$selectedChat): ?>
      <div class="flex h-full min-h-[420px] flex-col items-center justify-center text-center text-slate-400">
        <span class="material-icons-round mb-3 text-6xl text-pink-300">forum</span>
        <p class="text-lg font-semibold text-slate-200">Выберите обращение слева.</p>
      </div>
    <?php else: ?>
      <div class="mb-4 grid gap-3 border-b border-slate-700 pb-4 xl:grid-cols-[1fr_320px]">
        <div>
          <h2 class="text-xl font-bold text-slate-50"><?= htmlspecialchars((string)($selectedChat['client_name'] ?? 'Клиент')) ?> · <?= htmlspecialchars($chatTitle($selectedChat)) ?></h2>
          <p class="text-sm text-slate-400">
            <?= !empty($selectedChat['client_phone']) ? htmlspecialchars((string)$selectedChat['client_phone']) . ' · ' : '' ?>
            <?= !empty($beforeMessageId) ? 'Показаны более ранние сообщения.' : 'Показываются последние 50 сообщений.' ?>
          </p>
        </div>
        <form method="post" action="<?= htmlspecialchars($basePath) ?>/chats/<?= (int)$selectedChat['id'] ?>/note" class="rounded-2xl border border-amber-300/30 bg-amber-400/10 p-3 shadow-lg shadow-black/10">
          <?= csrf_field() ?>
          <label class="mb-1 block text-xs font-bold uppercase tracking-wide text-amber-200">Заметка</label>
          <textarea name="internal_note" rows="3" class="w-full rounded-xl border border-amber-200/20 bg-slate-900/80 p-2 text-sm text-slate-100 placeholder:text-slate-500 focus:border-amber-300 focus:outline-none" placeholder="Внутренняя заметка для сотрудников"><?= htmlspecialchars((string)($selectedChat['internal_note'] ?? '')) ?></textarea>
          <button type="submit" class="mt-2 rounded-xl bg-amber-500 px-3 py-1.5 text-xs font-bold text-slate-950 shadow hover:bg-amber-400">Сохранить заметку</button>
          <?php if (!empty($selectedChat['internal_note_updated_at'])): ?>
            <div class="mt-1 text-[11px] text-amber-200/80">Обновлено: <?= htmlspecialchars((string)$selectedChat['internal_note_updated_at']) ?><?= !empty($selectedChat['note_user_name']) ? ' · ' . htmlspecialchars((string)$selectedChat['note_user_name']) : '' ?></div>
          <?php endif; ?>
        </form>
      </div>

      <?php if (count($messages) === 50): ?>
        <?php $firstMessageId = (int)($messages[0]['id'] ?? 0); ?>
        <a href="<?= htmlspecialchars($basePath) ?>/chats/<?= (int)$selectedChat['id'] ?>?before_message_id=<?= $firstMessageId ?>" class="mb-4 inline-flex rounded-full bg-slate-800 px-3 py-1.5 text-xs font-bold text-slate-200 ring-1 ring-slate-700 hover:bg-slate-700">Показать ещё 50 ранних</a>
      <?php endif; ?>
      <?php if (!empty($beforeMessageId)): ?>
        <a href="<?= htmlspecialchars($basePath) ?>/chats/<?= (int)$selectedChat['id'] ?>" class="mb-4 ml-2 inline-flex rounded-full bg-pink-500/15 px-3 py-1.5 text-xs font-bold text-pink-200 ring-1 ring-pink-400/30 hover:bg-pink-500/25">Вернуться к последним</a>
      <?php endif; ?>
      <div class="space-y-3">
        <?php foreach ($messages as $message): ?>
          <?php
            $isClient = (int)($message['sender_user_id'] ?? 0) === (int)$selectedChat['user_id'];
            $hidden = !empty($message['hidden_from_client_at']);
          ?>
          <div id="message-<?= (int)$message['id'] ?>" class="flex <?= $isClient ? 'justify-start' : 'justify-end' ?>">
            <div class="max-w-[86%] rounded-2xl border px-4 py-3 text-sm shadow-lg <?= $isClient ? 'border-slate-700 bg-slate-900/75 text-slate-100' : 'border-pink-400/30 bg-pink-500/15 text-slate-100' ?>">
              <div class="mb-1 flex flex-wrap items-center gap-2 text-[11px] text-slate-400">
                <span class="font-semibold text-slate-300"><?= htmlspecialchars((string)($message['sender_name_snapshot'] ?? '')) ?></span>
                <span><?= htmlspecialchars((string)$message['created_at']) ?></span>
                <?php if (!empty($message['edited_at'])): ?><span>изменено <?= htmlspecialchars((string)$message['edited_at']) ?></span><?php endif; ?>
                <?php if ($hidden): ?><span class="rounded-full bg-red-500/15 px-2 py-0.5 font-bold text-red-200 ring-1 ring-red-400/30">удалено для клиента</span><?php endif; ?>
              </div>
              <?php if (!empty($message['body'])): ?><div class="whitespace-pre-wrap text-slate-100"><?= nl2br(htmlspecialchars((string)$message['body'])) ?></div><?php endif; ?>
              <?php if (!empty($attachmentsByMessage[(int)$message['id']])): ?>
                <div class="mt-2 grid grid-cols-2 gap-2 sm:grid-cols-3">
                  <?php foreach ($attachmentsByMessage[(int)$message['id']] as $photo): ?>
                    <a href="<?= htmlspecialchars((string)$photo['file_path']) ?>" target="_blank" rel="noopener"><img src="<?= htmlspecialchars((string)$photo['file_path']) ?>" alt="Фото" class="max-h-44 rounded-xl border border-slate-700 object-cover"></a>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
              <?php if (!$isClient): ?>
                <details class="mt-3 rounded-xl border border-slate-700 bg-slate-950/35 p-2">
                  <summary class="cursor-pointer text-xs font-bold text-slate-300">Редактировать</summary>
                  <form method="post" action="<?= htmlspecialchars($basePath) ?>/chats/<?= (int)$selectedChat['id'] ?>/messages/<?= (int)$message['id'] ?>/edit" class="mt-2 space-y-2">
                    <?= csrf_field() ?>
                    <textarea name="body" rows="3" class="w-full rounded-xl border border-slate-600 bg-slate-950/80 p-2 text-sm text-slate-100 placeholder:text-slate-500 focus:border-pink-300 focus:outline-none"><?= htmlspecialchars((string)($message['body'] ?? '')) ?></textarea>
                    <button type="submit" class="rounded-lg bg-slate-100 px-3 py-1.5 text-xs font-bold text-slate-950 hover:bg-white">Сохранить</button>
                  </form>
                </details>
              <?php endif; ?>
              <?php if (!$hidden): ?>
                <form method="post" action="<?= htmlspecialchars($basePath) ?>/chats/<?= (int)$selectedChat['id'] ?>/messages/<?= (int)$message['id'] ?>/hide" class="mt-2">
                  <?= csrf_field() ?>
                  <button type="submit" class="text-xs font-bold text-red-300 hover:text-red-200" onclick="return confirm('Скрыть сообщение от клиента?')">Скрыть от клиента</button>
                </form>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <form action="<?= htmlspecialchars($basePath) ?>/chats/<?= (int)$selectedChat['id'] ?>/messages" method="post" enctype="multipart/form-data" class="mt-5 rounded-2xl border border-slate-700 bg-slate-950/45 p-3">
        <?= csrf_field() ?>
        <textarea name="body" maxlength="2000" rows="3" class="w-full rounded-2xl border border-slate-500 bg-slate-950/70 p-3 text-sm text-slate-100 placeholder:text-slate-500 focus:border-pink-300 focus:outline-none" placeholder="Ответ клиенту"></textarea>
        <div class="mt-3 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <input type="file" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple class="text-xs text-slate-300 file:mr-3 file:rounded-lg file:border-0 file:bg-pink-500 file:px-3 file:py-1.5 file:text-xs file:font-bold file:text-white">
          <button type="submit" class="rounded-2xl bg-pink-500 px-5 py-2.5 text-sm font-bold text-white shadow-lg shadow-pink-950/20 hover:bg-pink-400">Отправить</button>
        </div>
      </form>
    <?php endif; ?>
  </section>
</div>
