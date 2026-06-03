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
$shortTime = static function (?string $dateTime): string {
    if (!$dateTime) {
        return '';
    }
    $ts = strtotime($dateTime);
    return $ts ? date('H:i', $ts) : $dateTime;
};
$chatIcon = static function (array $chat): string {
    return !empty($chat['order_id']) ? 'receipt_long' : 'support_agent';
};
?>
<style>
  .client-telegram-shell {
    background:
      radial-gradient(circle at 18% 10%, rgba(255, 107, 138, 0.09), transparent 26%),
      linear-gradient(135deg, rgba(255, 248, 250, 0.96), rgba(241, 245, 249, 0.96));
  }
  .client-telegram-scroll::-webkit-scrollbar { width: 8px; height: 8px; }
  .client-telegram-scroll::-webkit-scrollbar-track { background: rgba(226, 232, 240, 0.65); }
  .client-telegram-scroll::-webkit-scrollbar-thumb { background: rgba(148, 163, 184, 0.8); border-radius: 999px; }
  .client-chat-wall {
    background-color: #f8fafc;
    background-image:
      radial-gradient(circle at 18px 18px, rgba(255, 107, 138, 0.08) 1px, transparent 1px),
      radial-gradient(circle at 52px 44px, rgba(14, 165, 233, 0.055) 1px, transparent 1px);
    background-size: 72px 72px;
  }
  .client-bubble-in,
  .client-bubble-out { position: relative; }
  .client-bubble-in::before {
    content: '';
    position: absolute;
    left: -6px;
    bottom: 0;
    width: 13px;
    height: 13px;
    background: #ffffff;
    clip-path: polygon(100% 0, 100% 100%, 0 100%);
  }
  .client-bubble-out::after {
    content: '';
    position: absolute;
    right: -6px;
    bottom: 0;
    width: 13px;
    height: 13px;
    background: #ff5c8a;
    clip-path: polygon(0 0, 100% 100%, 0 100%);
  }
  .client-chat-search::placeholder { color: #94a3b8; }
  .client-new-chat-panel[open] summary .client-new-chat-chevron { transform: rotate(180deg); }
</style>

<div class="pt-20 pb-28 px-3 sm:px-4 max-w-7xl mx-auto">
  <?php if ($error): ?>
    <div class="mb-4 rounded-2xl border border-red-200 bg-red-50 p-4 text-sm font-semibold text-red-700 shadow-sm"><?= htmlspecialchars((string)$error) ?></div>
  <?php endif; ?>

  <?php if (!$selectedChat): ?>
    <section class="client-telegram-shell lg:hidden overflow-hidden rounded-3xl border border-slate-200 text-slate-900 shadow-2xl shadow-slate-200/70">
      <header class="flex h-16 items-center gap-3 border-b border-slate-200 bg-white/90 px-4 backdrop-blur">
        <a href="/" class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-slate-500 transition hover:bg-slate-100 hover:text-pink-500" title="На главную">
          <span class="material-icons-round">arrow_back</span>
        </a>
        <div class="min-w-0 flex-1">
          <div class="text-base font-black text-slate-900">Чат поддержки</div>
          <div class="text-xs text-slate-500">Выберите диалог или создайте новый</div>
        </div>
        <span class="rounded-full bg-pink-100 px-2.5 py-1 text-xs font-black text-pink-600"><?= count($chats) ?></span>
      </header>
      <div class="p-3">
        <label class="relative block">
          <span class="material-icons-round pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-lg text-slate-400">search</span>
          <input id="clientMobileChatSearch" type="search" class="client-chat-search h-11 w-full rounded-full border border-transparent bg-slate-100 pl-11 pr-4 text-sm text-slate-800 outline-none focus:border-pink-300 focus:bg-white" placeholder="Поиск по чатам">
        </label>
      </div>
      <div class="client-telegram-scroll max-h-[calc(100vh-20rem)] overflow-y-auto pb-3">
        <?php if ($chats === []): ?>
          <div class="mx-3 rounded-2xl border border-slate-200 bg-white p-4 text-sm text-slate-500">Пока нет обращений.</div>
        <?php endif; ?>
        <?php foreach ($chats as $chat): ?>
          <?php $lastBody = trim((string)($chat['last_body'] ?? '')) !== '' ? (string)$chat['last_body'] : 'Фото'; ?>
          <a href="/chat/<?= (int)$chat['id'] ?>" data-client-chat-row data-search-text="<?= htmlspecialchars(mb_strtolower($chatTitle($chat) . ' ' . $lastBody)) ?>" class="mx-2 flex gap-3 rounded-2xl px-3 py-3 transition hover:bg-white">
            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-pink-400 to-rose-500 text-white shadow-lg shadow-pink-100">
              <span class="material-icons-round"><?= htmlspecialchars($chatIcon($chat)) ?></span>
            </div>
            <div class="min-w-0 flex-1">
              <div class="flex items-start justify-between gap-2">
                <div class="truncate text-sm font-black text-slate-900"><?= htmlspecialchars($chatTitle($chat)) ?></div>
                <div class="shrink-0 text-[11px] text-slate-400"><?= htmlspecialchars($shortTime((string)($chat['last_message_at'] ?? $chat['created_at'] ?? ''))) ?></div>
              </div>
              <div class="mt-0.5 flex items-center justify-between gap-2">
                <div class="truncate text-xs text-slate-500"><?= htmlspecialchars(mb_substr($lastBody, 0, 90)) ?></div>
                <?php if ((int)($chat['client_unread_count'] ?? 0) > 0): ?><span class="shrink-0 rounded-full bg-pink-500 px-2 py-0.5 text-[11px] font-black text-white"><?= (int)$chat['client_unread_count'] ?></span><?php endif; ?>
              </div>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
      <div class="border-t border-slate-200 bg-white/90 p-3">
        <details class="client-new-chat-panel rounded-2xl border border-pink-100 bg-pink-50/70 p-3">
          <summary class="flex cursor-pointer list-none items-center justify-between gap-2 text-sm font-black text-pink-600">
            <span class="flex items-center gap-2"><span class="material-icons-round text-lg">add_comment</span>Новое обращение</span>
            <span class="material-icons-round client-new-chat-chevron transition">expand_more</span>
          </summary>
          <form action="/chat/start" method="post" enctype="multipart/form-data" class="mt-3" data-client-new-chat-form>
            <?= csrf_field() ?>
            <textarea name="body" maxlength="2000" rows="3" class="w-full rounded-2xl border border-pink-100 bg-white p-3 text-sm text-slate-800 placeholder:text-slate-400 focus:border-pink-300 focus:outline-none" placeholder="Напишите вопрос поддержке"></textarea>
            <label class="mt-2 flex cursor-pointer items-center gap-2 rounded-2xl border border-dashed border-pink-200 bg-white/80 px-3 py-2 text-xs font-bold text-pink-600">
              <span class="material-icons-round text-lg">attach_file</span> Добавить фото
              <input type="file" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple class="hidden">
            </label>
            <?php if ($activeOrders !== []): ?>
              <div class="mt-3 hidden rounded-2xl bg-white p-3" data-client-order-choice>
                <p class="mb-2 text-xs font-black text-slate-700">Ваш вопрос связан с заказом?</p>
                <div class="flex flex-wrap gap-2">
                  <?php foreach ($activeOrders as $order): ?>
                    <button type="button" data-order-id="<?= (int)$order['id'] ?>" class="client-order-choice rounded-full bg-pink-50 px-3 py-2 text-xs font-bold text-pink-700 hover:bg-pink-100">Заказ #<?= (int)$order['id'] ?></button>
                  <?php endforeach; ?>
                  <button type="button" data-order-id="" class="client-order-choice rounded-full bg-slate-900 px-3 py-2 text-xs font-bold text-white">Нет</button>
                </div>
              </div>
              <input type="hidden" name="order_id" value="" data-client-order-input>
              <button type="button" class="mt-3 w-full rounded-2xl bg-pink-500 px-4 py-3 text-sm font-black text-white shadow-lg shadow-pink-100" data-client-show-orders>Отправить</button>
              <button type="submit" class="hidden" data-client-submit></button>
            <?php else: ?>
              <button type="submit" class="mt-3 w-full rounded-2xl bg-pink-500 px-4 py-3 text-sm font-black text-white shadow-lg shadow-pink-100">Отправить</button>
            <?php endif; ?>
          </form>
        </details>
      </div>
    </section>
  <?php endif; ?>

  <div class="<?= !$selectedChat ? 'hidden lg:block' : 'block' ?> client-telegram-shell h-[calc(100vh-8rem)] min-h-[620px] overflow-hidden rounded-3xl border border-slate-200 text-slate-900 shadow-2xl shadow-slate-200/70">
    <div class="grid h-full min-w-0 grid-cols-1 lg:grid-cols-[360px_minmax(0,1fr)]">
      <aside class="<?= $selectedChat ? 'hidden lg:flex' : 'flex' ?> min-h-0 min-w-0 flex-col border-r border-slate-200 bg-white/95 backdrop-blur">
        <div class="flex h-16 shrink-0 items-center gap-3 border-b border-slate-200 px-4">
          <a href="/" class="flex h-10 w-10 items-center justify-center rounded-full text-slate-500 transition hover:bg-slate-100 hover:text-pink-500" title="На главную">
            <span class="material-icons-round">arrow_back</span>
          </a>
          <label class="relative min-w-0 flex-1">
            <span class="material-icons-round pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-lg text-slate-400">search</span>
            <input id="clientChatSearch" type="search" class="client-chat-search h-10 w-full rounded-full border border-transparent bg-slate-100 pl-11 pr-4 text-sm text-slate-800 outline-none transition focus:border-pink-300 focus:bg-white" placeholder="Поиск">
          </label>
          <span class="rounded-full bg-pink-100 px-2.5 py-1 text-xs font-black text-pink-600"><?= count($chats) ?></span>
        </div>
        <div class="client-telegram-scroll min-h-0 flex-1 overflow-y-auto py-2">
          <?php if ($chats === []): ?>
            <div class="mx-3 rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-500">Пока нет обращений.</div>
          <?php endif; ?>
          <?php foreach ($chats as $chat): ?>
            <?php
              $active = (int)$chat['id'] === $selectedChatId;
              $lastBody = trim((string)($chat['last_body'] ?? '')) !== '' ? (string)$chat['last_body'] : 'Фото';
            ?>
            <a href="/chat/<?= (int)$chat['id'] ?>" data-client-chat-row data-search-text="<?= htmlspecialchars(mb_strtolower($chatTitle($chat) . ' ' . $lastBody)) ?>" class="mx-2 flex gap-3 rounded-2xl px-3 py-3 transition <?= $active ? 'bg-pink-500 text-white shadow-lg shadow-pink-100' : 'hover:bg-slate-50' ?>">
              <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full <?= $active ? 'bg-white/20 text-white' : 'bg-gradient-to-br from-pink-400 to-rose-500 text-white' ?> shadow-lg shadow-pink-100/70">
                <span class="material-icons-round"><?= htmlspecialchars($chatIcon($chat)) ?></span>
              </div>
              <div class="min-w-0 flex-1">
                <div class="flex items-start justify-between gap-2">
                  <div class="truncate text-sm font-black <?= $active ? 'text-white' : 'text-slate-900' ?>"><?= htmlspecialchars($chatTitle($chat)) ?></div>
                  <div class="shrink-0 text-[11px] <?= $active ? 'text-pink-50' : 'text-slate-400' ?>"><?= htmlspecialchars($shortTime((string)($chat['last_message_at'] ?? $chat['created_at'] ?? ''))) ?></div>
                </div>
                <div class="mt-0.5 flex items-center justify-between gap-2">
                  <div class="truncate text-xs <?= $active ? 'text-pink-50' : 'text-slate-500' ?>"><?= htmlspecialchars(mb_substr($lastBody, 0, 90)) ?></div>
                  <?php if ((int)($chat['client_unread_count'] ?? 0) > 0): ?><span class="shrink-0 rounded-full <?= $active ? 'bg-white text-pink-600' : 'bg-pink-500 text-white' ?> px-2 py-0.5 text-[11px] font-black"><?= (int)$chat['client_unread_count'] ?></span><?php endif; ?>
                </div>
              </div>
            </a>
          <?php endforeach; ?>
        </div>
        <div class="border-t border-slate-200 p-3">
          <details class="client-new-chat-panel rounded-2xl border border-pink-100 bg-pink-50/70 p-3">
            <summary class="flex cursor-pointer list-none items-center justify-between gap-2 text-sm font-black text-pink-600">
              <span class="flex items-center gap-2"><span class="material-icons-round text-lg">add_comment</span>Новое обращение</span>
              <span class="material-icons-round client-new-chat-chevron transition">expand_more</span>
            </summary>
            <form action="/chat/start" method="post" enctype="multipart/form-data" class="mt-3" data-client-new-chat-form>
              <?= csrf_field() ?>
              <textarea name="body" maxlength="2000" rows="3" class="w-full rounded-2xl border border-pink-100 bg-white p-3 text-sm text-slate-800 placeholder:text-slate-400 focus:border-pink-300 focus:outline-none" placeholder="Напишите вопрос поддержке"></textarea>
              <label class="mt-2 flex cursor-pointer items-center gap-2 rounded-2xl border border-dashed border-pink-200 bg-white/80 px-3 py-2 text-xs font-bold text-pink-600">
                <span class="material-icons-round text-lg">attach_file</span> Добавить фото
                <input type="file" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple class="hidden">
              </label>
              <?php if ($activeOrders !== []): ?>
                <div class="mt-3 hidden rounded-2xl bg-white p-3" data-client-order-choice>
                  <p class="mb-2 text-xs font-black text-slate-700">Ваш вопрос связан с заказом?</p>
                  <div class="flex flex-wrap gap-2">
                    <?php foreach ($activeOrders as $order): ?>
                      <button type="button" data-order-id="<?= (int)$order['id'] ?>" class="client-order-choice rounded-full bg-pink-50 px-3 py-2 text-xs font-bold text-pink-700 hover:bg-pink-100">Заказ #<?= (int)$order['id'] ?></button>
                    <?php endforeach; ?>
                    <button type="button" data-order-id="" class="client-order-choice rounded-full bg-slate-900 px-3 py-2 text-xs font-bold text-white">Нет</button>
                  </div>
                </div>
                <input type="hidden" name="order_id" value="" data-client-order-input>
                <button type="button" class="mt-3 w-full rounded-2xl bg-pink-500 px-4 py-3 text-sm font-black text-white shadow-lg shadow-pink-100" data-client-show-orders>Отправить</button>
                <button type="submit" class="hidden" data-client-submit></button>
              <?php else: ?>
                <button type="submit" class="mt-3 w-full rounded-2xl bg-pink-500 px-4 py-3 text-sm font-black text-white shadow-lg shadow-pink-100">Отправить</button>
              <?php endif; ?>
            </form>
          </details>
        </div>
      </aside>

      <section class="flex min-h-0 min-w-0 flex-col bg-slate-50">
        <?php if (!$selectedChat): ?>
          <div class="flex h-full min-h-[420px] flex-col items-center justify-center text-center text-slate-500">
            <div class="mb-5 flex h-24 w-24 items-center justify-center rounded-full bg-white text-pink-400 shadow-xl shadow-slate-200/80">
              <span class="material-icons-round text-5xl">support_agent</span>
            </div>
            <p class="text-xl font-black text-slate-900">Выберите чат</p>
            <p class="mt-2 max-w-sm text-sm text-slate-500">Откройте диалог слева или создайте новое обращение в поддержку.</p>
          </div>
        <?php else: ?>
          <header class="flex h-16 shrink-0 items-center justify-between gap-3 border-b border-slate-200 bg-white/95 px-4 backdrop-blur">
            <div class="flex min-w-0 items-center gap-3">
              <a href="/chat" class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-slate-500 transition hover:bg-slate-100 hover:text-pink-500 lg:hidden" title="К списку чатов">
                <span class="material-icons-round">arrow_back</span>
              </a>
              <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-pink-400 to-rose-500 text-white shadow-lg shadow-pink-100">
                <span class="material-icons-round"><?= htmlspecialchars($chatIcon($selectedChat)) ?></span>
              </div>
              <div class="min-w-0">
                <div class="truncate text-sm font-black text-slate-900"><?= htmlspecialchars($chatTitle($selectedChat)) ?></div>
                <div class="truncate text-xs text-slate-500"><?= !empty($beforeMessageId) ? 'ранние сообщения' : 'последние 50 сообщений' ?></div>
              </div>
            </div>
            <div class="flex shrink-0 items-center gap-1 text-slate-400">
              <a href="/chat" class="hidden rounded-full p-2 transition hover:bg-slate-100 hover:text-pink-500 sm:inline-flex" title="К списку">
                <span class="material-icons-round">forum</span>
              </a>
              <span class="material-icons-round rounded-full p-2 transition hover:bg-slate-100 hover:text-pink-500" title="Поддержка">support_agent</span>
            </div>
          </header>

          <main class="client-chat-wall client-telegram-scroll min-h-0 flex-1 overflow-y-auto px-4 py-5 sm:px-6">
            <div class="mx-auto flex max-w-4xl flex-col gap-3">
              <div class="flex justify-center">
                <span class="rounded-full bg-white/90 px-3 py-1 text-xs font-black text-slate-500 shadow-sm">Чат поддержки</span>
              </div>
              <?php if (count($messages) === 50 || !empty($beforeMessageId)): ?>
                <div class="flex flex-wrap justify-center gap-2">
                  <?php if (count($messages) === 50): ?>
                    <?php $firstMessageId = (int)($messages[0]['id'] ?? 0); ?>
                    <a href="/chat/<?= (int)$selectedChat['id'] ?>?before_message_id=<?= $firstMessageId ?>" class="rounded-full bg-white px-3 py-1.5 text-xs font-black text-sky-600 shadow-sm hover:bg-sky-50">Показать ещё 50 ранних</a>
                  <?php endif; ?>
                  <?php if (!empty($beforeMessageId)): ?>
                    <a href="/chat/<?= (int)$selectedChat['id'] ?>" class="rounded-full bg-white px-3 py-1.5 text-xs font-black text-pink-600 shadow-sm hover:bg-pink-50">Вернуться к последним</a>
                  <?php endif; ?>
                </div>
              <?php endif; ?>

              <?php foreach ($messages as $message): ?>
                <?php
                  $isMine = (int)($message['sender_user_id'] ?? 0) === (int)($_SESSION['user_id'] ?? 0);
                  $hidden = !empty($message['hidden_from_client_at']);
                ?>
                <div id="message-<?= (int)$message['id'] ?>" class="flex <?= $isMine ? 'justify-end' : 'justify-start' ?>">
                  <article class="<?= $isMine ? 'client-bubble-out rounded-br-md bg-pink-500 text-white' : 'client-bubble-in rounded-bl-md bg-white text-slate-900' ?> max-w-[min(82%,680px)] rounded-2xl px-3.5 py-2 text-sm shadow-lg <?= $isMine ? 'shadow-pink-100' : 'shadow-slate-200/80' ?>">
                    <div class="mb-1 flex flex-wrap items-center gap-2 text-[11px] <?= $isMine ? 'text-pink-50' : 'text-sky-600' ?>">
                      <span class="font-black"><?= htmlspecialchars($isMine ? ($_SESSION['name'] ?? 'Вы') : (string)($message['sender_name_snapshot'] ?? 'Поддержка')) ?></span>
                    </div>
                    <?php if ($hidden): ?>
                      <div class="italic <?= $isMine ? 'text-pink-50' : 'text-slate-500' ?>">Сообщение удалено</div>
                    <?php else: ?>
                      <?php if (!empty($message['body'])): ?><div class="whitespace-pre-wrap leading-relaxed"><?= nl2br(htmlspecialchars((string)$message['body'])) ?></div><?php endif; ?>
                      <?php if (!empty($attachmentsByMessage[(int)$message['id']])): ?>
                        <div class="mt-2 grid grid-cols-2 gap-2 sm:grid-cols-3">
                          <?php foreach ($attachmentsByMessage[(int)$message['id']] as $photo): ?>
                            <a href="<?= htmlspecialchars((string)$photo['file_path']) ?>" target="_blank" rel="noopener"><img src="<?= htmlspecialchars((string)$photo['file_path']) ?>" alt="Фото" class="max-h-52 rounded-xl border border-white/40 object-cover"></a>
                          <?php endforeach; ?>
                        </div>
                      <?php endif; ?>
                    <?php endif; ?>
                    <div class="mt-1 flex items-center justify-end gap-1 text-[11px] <?= $isMine ? 'text-pink-50/85' : 'text-slate-400' ?>">
                      <span><?= htmlspecialchars($shortTime((string)$message['created_at'])) ?></span>
                      <?php if ($isMine): ?><span class="material-icons-round text-sm">done_all</span><?php endif; ?>
                    </div>
                  </article>
                </div>
              <?php endforeach; ?>
            </div>
          </main>

          <footer class="shrink-0 border-t border-slate-200 bg-white/95 px-3 py-3 backdrop-blur">
            <form action="/chat/<?= (int)$selectedChat['id'] ?>/messages" method="post" enctype="multipart/form-data" class="mx-auto flex max-w-4xl items-end gap-2">
              <?= csrf_field() ?>
              <label class="flex h-11 w-11 shrink-0 cursor-pointer items-center justify-center rounded-full text-slate-400 transition hover:bg-slate-100 hover:text-pink-500" title="Добавить фото">
                <span class="material-icons-round">attach_file</span>
                <input type="file" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple class="hidden">
              </label>
              <textarea name="body" maxlength="2000" rows="1" class="min-h-11 flex-1 resize-none rounded-2xl border border-transparent bg-slate-100 px-4 py-3 text-sm text-slate-800 placeholder:text-slate-400 outline-none focus:border-pink-300 focus:bg-white" placeholder="Сообщение..."></textarea>
              <button type="submit" class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-pink-500 text-white shadow-lg shadow-pink-100 transition hover:bg-pink-400" title="Отправить">
                <span class="material-icons-round">send</span>
              </button>
            </form>
          </footer>
        <?php endif; ?>
      </section>
    </div>
  </div>
</div>

<script>
(() => {
  const bindSearch = (inputId, rowSelector) => {
    const input = document.getElementById(inputId);
    if (!input) return;
    const rows = Array.from(document.querySelectorAll(rowSelector));
    input.addEventListener('input', () => {
      const query = input.value.trim().toLowerCase();
      rows.forEach((row) => {
        const haystack = row.dataset.searchText || '';
        row.classList.toggle('hidden', query !== '' && !haystack.includes(query));
      });
    });
  };

  const bindNewChatForm = (form) => {
    const showBtn = form.querySelector('[data-client-show-orders]');
    const orderChoice = form.querySelector('[data-client-order-choice]');
    const orderInput = form.querySelector('[data-client-order-input]');
    const submitBtn = form.querySelector('[data-client-submit]');
    if (!showBtn || !orderChoice || !orderInput || !submitBtn) return;
    showBtn.addEventListener('click', () => {
      orderChoice.classList.remove('hidden');
      showBtn.textContent = 'Выберите заказ или Нет';
    });
    form.querySelectorAll('.client-order-choice').forEach((button) => {
      button.addEventListener('click', () => {
        orderInput.value = button.dataset.orderId || '';
        submitBtn.click();
      });
    });
  };

  bindSearch('clientChatSearch', '[data-client-chat-row]');
  bindSearch('clientMobileChatSearch', '[data-client-chat-row]');
  document.querySelectorAll('[data-client-new-chat-form]').forEach(bindNewChatForm);
})();
</script>
