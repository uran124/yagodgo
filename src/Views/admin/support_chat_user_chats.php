<?php
/** @var array<string,mixed> $user */
/** @var array<int,array<string,mixed>> $chats */
/** @var string $basePath */
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
$userName = (string)($user['name'] ?? 'Клиент');
?>
<style>
  .support-telegram-shell {
    background: linear-gradient(135deg, #111b26 0%, #17212b 100%);
  }
  .support-telegram-scroll::-webkit-scrollbar { width: 8px; height: 8px; }
  .support-telegram-scroll::-webkit-scrollbar-track { background: rgba(15, 23, 42, 0.35); }
  .support-telegram-scroll::-webkit-scrollbar-thumb { background: rgba(100, 116, 139, 0.7); border-radius: 999px; }
  .support-chat-search::placeholder { color: #74869a; }
</style>

<div class="support-telegram-shell h-[calc(100vh-9rem)] min-h-[620px] overflow-hidden rounded-2xl border border-slate-700/70 text-slate-100 shadow-2xl shadow-black/30">
  <div class="flex h-full min-w-0 flex-col bg-[#17212b]">
    <header class="flex h-16 shrink-0 items-center gap-3 border-b border-slate-800 px-4">
      <a href="<?= htmlspecialchars($basePath) ?>/chats" class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-slate-400 transition hover:bg-slate-700/70 hover:text-white" title="К клиентам">
        <span class="material-icons-round">arrow_back</span>
      </a>
      <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-sky-400 to-cyan-500 text-sm font-black text-white shadow-lg shadow-black/20"><?= htmlspecialchars($initials($userName)) ?></div>
      <div class="min-w-0 flex-1">
        <div class="truncate text-base font-bold text-white"><?= htmlspecialchars($userName) ?></div>
        <div class="truncate text-xs text-slate-400"><?= !empty($user['phone']) ? htmlspecialchars((string)$user['phone']) . ' · ' : '' ?><?= count($chats) ?> чат(а)</div>
      </div>
      <span class="material-icons-round text-slate-400">forum</span>
    </header>

    <div class="p-3">
      <label class="relative block">
        <span class="material-icons-round pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-lg text-slate-500">search</span>
        <input id="supportUserChatSearch" type="search" class="support-chat-search h-11 w-full rounded-full border border-transparent bg-[#242f3d] pl-11 pr-4 text-sm text-slate-100 outline-none focus:border-sky-500/40" placeholder="Поиск по чатам клиента">
      </label>
    </div>

    <main class="support-telegram-scroll min-h-0 flex-1 overflow-y-auto pb-3">
      <?php if ($chats === []): ?>
        <div class="mx-3 rounded-2xl border border-slate-700 bg-slate-900/60 p-4 text-sm text-slate-400">У клиента пока нет чатов.</div>
      <?php endif; ?>
      <?php foreach ($chats as $chat): ?>
        <?php $lastBody = trim((string)($chat['last_body'] ?? '')) !== '' ? (string)$chat['last_body'] : 'Фото'; ?>
        <a href="<?= htmlspecialchars($basePath) ?>/chats/<?= (int)$chat['id'] ?>" data-user-chat-row data-search-text="<?= htmlspecialchars(mb_strtolower($chatTitle($chat) . ' ' . $lastBody . ' ' . ($chat['order_status'] ?? ''))) ?>" class="mx-2 flex gap-3 rounded-xl px-3 py-3 transition hover:bg-[#202b38]">
          <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full <?= !empty($chat['order_id']) ? 'bg-[#2b5278]' : 'bg-[#242f3d]' ?> text-sky-100">
            <span class="material-icons-round"><?= !empty($chat['order_id']) ? 'receipt_long' : 'support_agent' ?></span>
          </div>
          <div class="min-w-0 flex-1">
            <div class="flex items-start justify-between gap-2">
              <div class="truncate text-sm font-bold text-white"><?= htmlspecialchars($chatTitle($chat)) ?></div>
              <div class="shrink-0 text-[11px] text-slate-500"><?= htmlspecialchars($shortTime((string)($chat['last_message_at'] ?? $chat['created_at'] ?? ''))) ?></div>
            </div>
            <div class="mt-0.5 truncate text-xs text-slate-400"><?= !empty($chat['order_status']) ? htmlspecialchars((string)$chat['order_status']) . ' · ' : '' ?><?= !empty($chat['delivery_date']) ? htmlspecialchars((string)$chat['delivery_date']) : 'обращение' ?></div>
            <div class="mt-0.5 flex items-center justify-between gap-2">
              <div class="truncate text-xs text-slate-300"><?= htmlspecialchars(mb_substr($lastBody, 0, 100)) ?></div>
              <?php if ((int)($chat['staff_unread_count'] ?? 0) > 0): ?><span class="shrink-0 rounded-full bg-sky-500 px-2 py-0.5 text-[11px] font-black text-white"><?= (int)$chat['staff_unread_count'] ?></span><?php endif; ?>
            </div>
          </div>
        </a>
      <?php endforeach; ?>
    </main>
  </div>
</div>

<script>
(() => {
  const input = document.getElementById('supportUserChatSearch');
  if (!input) return;
  const rows = Array.from(document.querySelectorAll('[data-user-chat-row]'));
  input.addEventListener('input', () => {
    const query = input.value.trim().toLowerCase();
    rows.forEach((row) => {
      const haystack = row.dataset.searchText || '';
      row.classList.toggle('hidden', query !== '' && !haystack.includes(query));
    });
  });
})();
</script>
