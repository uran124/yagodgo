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
$initials = static function (?string $name): string {
    $name = trim((string)$name);
    if ($name === '') {
        return 'BG';
    }
    $parts = preg_split('/\s+/u', $name) ?: [];
    $letters = '';
    foreach (array_slice($parts, 0, 2) as $part) {
        $letters .= mb_substr($part, 0, 1);
    }
    return mb_strtoupper($letters ?: mb_substr($name, 0, 2));
};
$shortTime = static function (?string $dateTime): string {
    if (!$dateTime) {
        return '';
    }
    $ts = strtotime($dateTime);
    return $ts ? date('H:i', $ts) : $dateTime;
};
?>
<style>
  .support-telegram-shell {
    background:
      radial-gradient(circle at 18% 12%, rgba(56, 189, 248, 0.08), transparent 26%),
      linear-gradient(135deg, #111b26 0%, #17212b 100%);
  }
  .support-telegram-scroll::-webkit-scrollbar { width: 8px; height: 8px; }
  .support-telegram-scroll::-webkit-scrollbar-track { background: rgba(15, 23, 42, 0.35); }
  .support-telegram-scroll::-webkit-scrollbar-thumb { background: rgba(100, 116, 139, 0.7); border-radius: 999px; }
  .support-chat-wall {
    background-color: #0e1621;
    background-image:
      radial-gradient(circle at 18px 18px, rgba(148, 163, 184, 0.05) 1px, transparent 1px),
      radial-gradient(circle at 52px 44px, rgba(148, 163, 184, 0.035) 1px, transparent 1px);
    background-size: 72px 72px;
  }
  .support-bubble-in,
  .support-bubble-out { position: relative; }
  .support-bubble-in::before {
    content: '';
    position: absolute;
    left: -6px;
    bottom: 0;
    width: 13px;
    height: 13px;
    background: #182533;
    clip-path: polygon(100% 0, 100% 100%, 0 100%);
  }
  .support-bubble-out::after {
    content: '';
    position: absolute;
    right: -6px;
    bottom: 0;
    width: 13px;
    height: 13px;
    background: #2b5278;
    clip-path: polygon(0 0, 100% 100%, 0 100%);
  }
  .support-chat-search::placeholder { color: #74869a; }
  .support-chat-note[open] summary { background: rgba(51, 65, 85, 0.9); }
</style>

<div class="support-telegram-shell h-[calc(100vh-9rem)] min-h-[620px] overflow-hidden rounded-2xl border border-slate-700/70 text-slate-100 shadow-2xl shadow-black/30">
  <div class="grid h-full min-w-0 grid-cols-1 lg:grid-cols-[360px_minmax(0,1fr)]">
    <aside class="flex min-h-0 min-w-0 flex-col border-r border-slate-800/90 bg-[#17212b]">
      <div class="flex h-16 shrink-0 items-center gap-3 border-b border-slate-800/80 px-4">
        <button type="button" class="flex h-10 w-10 items-center justify-center rounded-full text-slate-400 transition hover:bg-slate-700/70 hover:text-slate-100" aria-label="Меню">
          <span class="material-icons-round">menu</span>
        </button>
        <label class="relative min-w-0 flex-1">
          <span class="material-icons-round pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-lg text-slate-500">search</span>
          <input id="supportChatSearch" type="search" class="support-chat-search h-10 w-full rounded-full border border-transparent bg-[#242f3d] pl-11 pr-4 text-sm text-slate-100 outline-none transition focus:border-sky-500/40 focus:bg-[#202b38]" placeholder="Поиск">
        </label>
        <span class="rounded-full bg-sky-500/15 px-2.5 py-1 text-xs font-bold text-sky-200 ring-1 ring-sky-400/20"><?= count($chats) ?></span>
      </div>

      <div class="support-telegram-scroll min-h-0 flex-1 overflow-y-auto py-2" id="supportChatList">
        <?php if ($chats === []): ?>
          <div class="mx-3 rounded-2xl border border-slate-700 bg-slate-900/60 p-4 text-sm text-slate-400">Пока нет обращений.</div>
        <?php endif; ?>
        <?php foreach ($chats as $chat): ?>
          <?php
            $active = (int)$chat['id'] === $selectedChatId;
            $clientName = (string)($chat['client_name'] ?? 'Клиент');
            $lastBody = trim((string)($chat['last_body'] ?? '')) !== '' ? (string)$chat['last_body'] : 'Фото';
          ?>
          <a href="<?= htmlspecialchars($basePath) ?>/chats/<?= (int)$chat['id'] ?>" data-chat-row data-search-text="<?= htmlspecialchars(mb_strtolower($clientName . ' ' . $chatTitle($chat) . ' ' . ($chat['client_phone'] ?? '') . ' ' . $lastBody)) ?>" class="mx-2 flex gap-3 rounded-xl px-3 py-2.5 transition <?= $active ? 'bg-[#2b5278]' : 'hover:bg-[#202b38]' ?>">
            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-sky-400 to-cyan-500 text-sm font-black text-white shadow-lg shadow-black/20">
              <?= htmlspecialchars($initials($clientName)) ?>
            </div>
            <div class="min-w-0 flex-1">
              <div class="flex items-start justify-between gap-2">
                <div class="truncate text-sm font-bold text-white"><?= htmlspecialchars($clientName) ?></div>
                <div class="shrink-0 text-[11px] <?= $active ? 'text-sky-100' : 'text-slate-500' ?>"><?= htmlspecialchars($shortTime((string)($chat['last_message_at'] ?? $chat['created_at'] ?? ''))) ?></div>
              </div>
              <div class="mt-0.5 flex items-center justify-between gap-2">
                <div class="min-w-0">
                  <div class="truncate text-xs <?= $active ? 'text-sky-100' : 'text-slate-400' ?>"><?= htmlspecialchars($chatTitle($chat)) ?><?= !empty($chat['client_phone']) ? ' · ' . htmlspecialchars((string)$chat['client_phone']) : '' ?></div>
                  <div class="mt-0.5 truncate text-xs <?= $active ? 'text-slate-100' : 'text-slate-300' ?>"><?= htmlspecialchars(mb_substr($lastBody, 0, 90)) ?></div>
                </div>
                <?php if ((int)($chat['staff_unread_count'] ?? 0) > 0): ?>
                  <span class="shrink-0 rounded-full bg-sky-500 px-2 py-0.5 text-[11px] font-black text-white"><?= (int)$chat['staff_unread_count'] ?></span>
                <?php elseif (!empty($chat['internal_note'])): ?>
                  <span class="material-icons-round shrink-0 text-base text-amber-300" title="Есть заметка">sticky_note_2</span>
                <?php endif; ?>
              </div>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
    </aside>

    <section class="flex min-h-0 min-w-0 flex-col bg-[#0e1621]">
      <?php if ($error): ?>
        <div class="m-3 rounded-xl border border-red-400/50 bg-red-950/40 p-3 text-sm text-red-100"><?= htmlspecialchars((string)$error) ?></div>
      <?php endif; ?>

      <?php if (!$selectedChat): ?>
        <div class="flex h-full min-h-[420px] flex-col items-center justify-center text-center text-slate-400">
          <div class="mb-5 flex h-24 w-24 items-center justify-center rounded-full bg-[#17212b] text-pink-300 shadow-2xl shadow-black/20">
            <span class="material-icons-round text-5xl">forum</span>
          </div>
          <p class="text-xl font-bold text-slate-100">Выберите чат</p>
          <p class="mt-2 max-w-sm text-sm text-slate-400">Откройте обращение слева, чтобы увидеть переписку, заметки и ответить клиенту.</p>
        </div>
      <?php else: ?>
        <header class="flex h-16 shrink-0 items-center justify-between gap-3 border-b border-slate-800 bg-[#17212b] px-4">
          <div class="flex min-w-0 items-center gap-3">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-sky-400 to-cyan-500 text-xs font-black text-white">
              <?= htmlspecialchars($initials((string)($selectedChat['client_name'] ?? 'Клиент'))) ?>
            </div>
            <div class="min-w-0">
              <div class="truncate text-sm font-bold text-white"><?= htmlspecialchars((string)($selectedChat['client_name'] ?? 'Клиент')) ?></div>
              <div class="truncate text-xs text-slate-400"><?= htmlspecialchars($chatTitle($selectedChat)) ?><?= !empty($selectedChat['client_phone']) ? ' · ' . htmlspecialchars((string)$selectedChat['client_phone']) : '' ?> · <?= !empty($beforeMessageId) ? 'ранние сообщения' : 'последние 50 сообщений' ?></div>
            </div>
          </div>
          <div class="flex shrink-0 items-center gap-1 text-slate-400">
            <a href="<?= htmlspecialchars($basePath) ?>/chats" class="hidden rounded-full p-2 transition hover:bg-slate-700/70 hover:text-slate-100 sm:inline-flex" title="К списку">
              <span class="material-icons-round">arrow_back</span>
            </a>
            <details class="support-chat-note relative">
              <summary class="flex h-10 cursor-pointer list-none items-center gap-1 rounded-full px-3 text-sm font-bold text-amber-200 transition hover:bg-slate-700/70">
                <span class="material-icons-round text-base">sticky_note_2</span>
                <span class="hidden sm:inline">Заметка</span>
              </summary>
              <form method="post" action="<?= htmlspecialchars($basePath) ?>/chats/<?= (int)$selectedChat['id'] ?>/note" class="absolute right-0 top-12 z-20 w-80 rounded-2xl border border-slate-700 bg-[#17212b] p-3 shadow-2xl shadow-black/50">
                <?= csrf_field() ?>
                <label class="mb-2 block text-xs font-bold uppercase tracking-wide text-amber-200">Внутренняя заметка</label>
                <textarea name="internal_note" rows="4" class="w-full rounded-xl border border-slate-600 bg-[#0e1621] p-3 text-sm text-slate-100 placeholder:text-slate-500 focus:border-amber-300 focus:outline-none" placeholder="Внутренняя заметка для сотрудников"><?= htmlspecialchars((string)($selectedChat['internal_note'] ?? '')) ?></textarea>
                <button type="submit" class="mt-2 rounded-xl bg-amber-500 px-4 py-2 text-xs font-bold text-slate-950 hover:bg-amber-400">Сохранить</button>
                <?php if (!empty($selectedChat['internal_note_updated_at'])): ?>
                  <div class="mt-2 text-[11px] text-slate-400">Обновлено: <?= htmlspecialchars((string)$selectedChat['internal_note_updated_at']) ?><?= !empty($selectedChat['note_user_name']) ? ' · ' . htmlspecialchars((string)$selectedChat['note_user_name']) : '' ?></div>
                <?php endif; ?>
              </form>
            </details>
            <span class="material-icons-round rounded-full p-2 transition hover:bg-slate-700/70 hover:text-slate-100" title="Поиск">search</span>
            <span class="material-icons-round rounded-full p-2 transition hover:bg-slate-700/70 hover:text-slate-100" title="Ещё">more_vert</span>
          </div>
        </header>

        <main class="support-chat-wall support-telegram-scroll min-h-0 flex-1 overflow-y-auto px-4 py-5 sm:px-6">
          <div class="mx-auto flex max-w-5xl flex-col gap-3">
            <div class="flex justify-center">
              <span class="rounded-full bg-[#1f2c3a]/90 px-3 py-1 text-xs font-bold text-slate-300 shadow">Чат поддержки</span>
            </div>
            <?php if (count($messages) === 50 || !empty($beforeMessageId)): ?>
              <div class="flex flex-wrap justify-center gap-2">
                <?php if (count($messages) === 50): ?>
                  <?php $firstMessageId = (int)($messages[0]['id'] ?? 0); ?>
                  <a href="<?= htmlspecialchars($basePath) ?>/chats/<?= (int)$selectedChat['id'] ?>?before_message_id=<?= $firstMessageId ?>" class="rounded-full bg-[#1f2c3a] px-3 py-1.5 text-xs font-bold text-sky-200 hover:bg-[#26384a]">Показать ещё 50 ранних</a>
                <?php endif; ?>
                <?php if (!empty($beforeMessageId)): ?>
                  <a href="<?= htmlspecialchars($basePath) ?>/chats/<?= (int)$selectedChat['id'] ?>" class="rounded-full bg-[#1f2c3a] px-3 py-1.5 text-xs font-bold text-pink-200 hover:bg-[#26384a]">Вернуться к последним</a>
                <?php endif; ?>
              </div>
            <?php endif; ?>

            <?php foreach ($messages as $message): ?>
              <?php
                $isClient = (int)($message['sender_user_id'] ?? 0) === (int)$selectedChat['user_id'];
                $hidden = !empty($message['hidden_from_client_at']);
              ?>
              <div id="message-<?= (int)$message['id'] ?>" class="flex <?= $isClient ? 'justify-start' : 'justify-end' ?>">
                <article class="<?= $isClient ? 'support-bubble-in rounded-bl-md bg-[#182533]' : 'support-bubble-out rounded-br-md bg-[#2b5278]' ?> max-w-[min(82%,720px)] rounded-2xl px-3.5 py-2 text-sm text-slate-50 shadow-lg shadow-black/20">
                  <div class="mb-1 flex flex-wrap items-center gap-2 text-[11px] <?= $isClient ? 'text-sky-300' : 'text-sky-100' ?>">
                    <span class="font-bold"><?= htmlspecialchars((string)($message['sender_name_snapshot'] ?? '')) ?></span>
                    <?php if ($hidden): ?><span class="rounded-full bg-red-500/20 px-2 py-0.5 font-bold text-red-100">удалено для клиента</span><?php endif; ?>
                    <?php if (!empty($message['edited_at'])): ?><span class="text-slate-300/80">изменено</span><?php endif; ?>
                  </div>
                  <?php if (!empty($message['body'])): ?><div class="whitespace-pre-wrap leading-relaxed text-slate-50"><?= nl2br(htmlspecialchars((string)$message['body'])) ?></div><?php endif; ?>
                  <?php if (!empty($attachmentsByMessage[(int)$message['id']])): ?>
                    <div class="mt-2 grid grid-cols-2 gap-2 sm:grid-cols-3">
                      <?php foreach ($attachmentsByMessage[(int)$message['id']] as $photo): ?>
                        <a href="<?= htmlspecialchars((string)$photo['file_path']) ?>" target="_blank" rel="noopener"><img src="<?= htmlspecialchars((string)$photo['file_path']) ?>" alt="Фото" class="max-h-52 rounded-xl border border-white/10 object-cover"></a>
                      <?php endforeach; ?>
                    </div>
                  <?php endif; ?>
                  <div class="mt-1 flex items-center justify-end gap-1 text-[11px] text-slate-300/80">
                    <span><?= htmlspecialchars($shortTime((string)$message['created_at'])) ?></span>
                    <?php if (!$isClient): ?><span class="material-icons-round text-sm text-sky-200">done_all</span><?php endif; ?>
                  </div>
                  <?php if (!$isClient): ?>
                    <details class="mt-2 rounded-xl bg-slate-950/25 p-2">
                      <summary class="cursor-pointer text-xs font-bold text-sky-100">Редактировать</summary>
                      <form method="post" action="<?= htmlspecialchars($basePath) ?>/chats/<?= (int)$selectedChat['id'] ?>/messages/<?= (int)$message['id'] ?>/edit" class="mt-2 space-y-2">
                        <?= csrf_field() ?>
                        <textarea name="body" rows="3" class="w-full rounded-xl border border-slate-500 bg-[#0e1621] p-2 text-sm text-slate-100 placeholder:text-slate-500 focus:border-sky-300 focus:outline-none"><?= htmlspecialchars((string)($message['body'] ?? '')) ?></textarea>
                        <button type="submit" class="rounded-lg bg-sky-500 px-3 py-1.5 text-xs font-bold text-white hover:bg-sky-400">Сохранить</button>
                      </form>
                    </details>
                  <?php endif; ?>
                  <?php if (!$hidden): ?>
                    <form method="post" action="<?= htmlspecialchars($basePath) ?>/chats/<?= (int)$selectedChat['id'] ?>/messages/<?= (int)$message['id'] ?>/hide" class="mt-2">
                      <?= csrf_field() ?>
                      <button type="submit" class="text-xs font-bold text-red-200 hover:text-red-100" onclick="return confirm('Скрыть сообщение от клиента?')">Скрыть от клиента</button>
                    </form>
                  <?php endif; ?>
                </article>
              </div>
            <?php endforeach; ?>
          </div>
        </main>

        <footer class="shrink-0 border-t border-slate-800 bg-[#17212b] px-3 py-3">
          <form action="<?= htmlspecialchars($basePath) ?>/chats/<?= (int)$selectedChat['id'] ?>/messages" method="post" enctype="multipart/form-data" class="mx-auto flex max-w-5xl items-end gap-2">
            <?= csrf_field() ?>
            <label class="flex h-11 w-11 shrink-0 cursor-pointer items-center justify-center rounded-full text-slate-400 transition hover:bg-slate-700/70 hover:text-slate-100" title="Добавить фото">
              <span class="material-icons-round">attach_file</span>
              <input type="file" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple class="hidden">
            </label>
            <textarea name="body" maxlength="2000" rows="1" class="min-h-11 flex-1 resize-none rounded-2xl border border-transparent bg-[#242f3d] px-4 py-3 text-sm text-slate-100 placeholder:text-slate-500 outline-none focus:border-sky-500/40" placeholder="Сообщение..."></textarea>
            <button type="submit" class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-sky-500 text-white shadow-lg shadow-sky-950/30 transition hover:bg-sky-400" title="Отправить">
              <span class="material-icons-round">send</span>
            </button>
          </form>
        </footer>
      <?php endif; ?>
    </section>
  </div>
</div>

<script>
(() => {
  const input = document.getElementById('supportChatSearch');
  if (!input) return;
  const rows = Array.from(document.querySelectorAll('[data-chat-row]'));
  input.addEventListener('input', () => {
    const query = input.value.trim().toLowerCase();
    rows.forEach((row) => {
      const haystack = row.dataset.searchText || '';
      row.classList.toggle('hidden', query !== '' && !haystack.includes(query));
    });
  });
})();
</script>
