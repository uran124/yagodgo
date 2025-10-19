<?php
/** @var array $clients */
/** @var int $activeCount */
?>
<div class="space-y-6">
  <div class="bg-white p-4 rounded shadow">
    <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-4">
      <div class="w-full md:max-w-xs">
        <label for="phoneSearch" class="block text-sm text-gray-500 mb-1">Поиск по номеру</label>
        <input id="phoneSearch" type="text" placeholder="Введите цифры номера"
               class="w-full rounded border border-gray-300 bg-transparent px-3 py-2"
               autocomplete="off">
        <p class="mt-1 text-xs text-gray-500">Фильтрация выполняется по совпадению цифр в правильном порядке.</p>
      </div>
      <p class="text-sm text-gray-500 md:text-right">Начните вводить цифры, чтобы отфильтровать список клиентов для рассылки.</p>
    </div>
  </div>

  <div class="bg-white p-4 rounded shadow">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 mb-4">
      <div>
        <h2 class="text-lg font-semibold">Клиенты для рассылки</h2>
        <p class="text-sm text-gray-500">Активных номеров: <span id="activeCounter"><?= (int)$activeCount ?></span></p>
      </div>
      <button type="button" id="copyActive" class="px-4 py-2 rounded bg-green-600 text-white shadow">Сохранить</button>
    </div>

    <div class="overflow-x-auto">
      <table class="min-w-full divide-y divide-gray-200">
        <thead>
          <tr class="text-left text-xs uppercase tracking-wider text-gray-400">
            <th class="px-4 py-3">Имя</th>
            <th class="px-4 py-3">Телефон</th>
            <th class="px-4 py-3">Налёт</th>
            <th class="px-4 py-3 text-center">Рассылка</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-200" id="mailingTable">
        <?php foreach ($clients as $client):
            $allow = (int)($client['allow_mailing'] ?? 1) === 1;
            $comment = $client['comment'] ?? '';
            $naletNumber = $client['nalet_number'] ?? '';
            $phone = $client['phone'] ?? '';
        ?>
          <tr class="hover:bg-gray-50 cursor-pointer" data-row
              data-user-id="<?= (int)$client['id'] ?>"
              data-name="<?= htmlspecialchars($client['name'] ?? 'Без имени', ENT_QUOTES) ?>"
              data-phone="<?= htmlspecialchars($phone, ENT_QUOTES) ?>"
              data-comment="<?= htmlspecialchars($comment, ENT_QUOTES) ?>"
              data-nalet="<?= htmlspecialchars($naletNumber, ENT_QUOTES) ?>"
              data-allow="<?= $allow ? '1' : '0' ?>">
            <td class="px-4 py-3 font-medium flex items-center space-x-2">
              <span class="material-icons-round text-[#C86052] text-base">person</span>
              <span><?= htmlspecialchars($client['name'] ?? 'Без имени') ?></span>
            </td>
            <td class="px-4 py-3 text-gray-300"><?= htmlspecialchars($phone) ?></td>
            <td class="px-4 py-3 text-gray-300" data-nalet-cell><?= htmlspecialchars($naletNumber) ?></td>
            <td class="px-4 py-3">
              <label class="relative inline-flex items-center cursor-pointer select-none" onclick="event.stopPropagation();">
                <input type="checkbox" class="sr-only peer mailing-toggle"
                       data-user-id="<?= (int)$client['id'] ?>"
                       <?= $allow ? 'checked' : '' ?>>
                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-red-300 rounded-full peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-red-600"></div>
              </label>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <p id="noResults" class="hidden text-center text-gray-500 py-6">Нет клиентов по вашему запросу</p>
      <?php if (!$clients): ?>
        <p class="text-center text-gray-500 py-6">Нет клиентов для отображения</p>
      <?php endif; ?>
    </div>
  </div>
</div>

<div id="toast" class="fixed bottom-6 right-6 bg-white text-gray-800 px-4 py-2 rounded shadow hidden"></div>

<div id="commentModal" class="fixed inset-0 bg-black/60 flex items-center justify-center z-50 hidden">
  <div class="bg-white rounded-lg shadow-lg w-full max-w-lg p-6 relative">
    <button type="button" id="modalClose" class="absolute top-3 right-3 text-gray-400 hover:text-white">
      <span class="material-icons-round">close</span>
    </button>
    <h3 class="text-xl font-semibold mb-4" id="modalTitle">Комментарий</h3>
    <form id="commentForm" class="space-y-4">
      <input type="hidden" name="user_id" id="commentUserId">
      <div>
        <label for="modalNalet" class="block text-sm text-gray-500 mb-1">Номер налёта</label>
        <input id="modalNalet" name="nalet_number" type="text" class="w-full rounded border border-gray-300 bg-transparent px-3 py-2">
      </div>
      <div>
        <label for="modalComment" class="block text-sm text-gray-500 mb-1">Комментарий</label>
        <textarea id="modalComment" name="comment" rows="5" class="w-full rounded border border-gray-300 bg-transparent px-3 py-2"></textarea>
      </div>
      <div class="flex justify-end space-x-3">
        <button type="button" class="px-4 py-2 rounded border border-gray-500" id="modalCancel">Отмена</button>
        <button type="submit" class="px-4 py-2 rounded bg-[#C86052] text-white">Сохранить</button>
      </div>
    </form>
  </div>
</div>

<script>
  const toastEl = document.getElementById('toast');
  const rows = Array.from(document.querySelectorAll('[data-row]'));
  const noResultsMessage = document.getElementById('noResults');
  const searchInput = document.getElementById('phoneSearch');

  function showToast(message) {
    toastEl.textContent = message;
    toastEl.classList.remove('hidden');
    setTimeout(() => toastEl.classList.add('hidden'), 3000);
  }

  function updateActiveCounter() {
    let count = 0;
    rows.forEach(row => {
      if (row.dataset.allow === '1' && !row.classList.contains('hidden')) {
        count++;
      }
    });
    document.getElementById('activeCounter').textContent = count;
  }

  function normalizeDigits(value) {
    return value.replace(/\D+/g, '');
  }

  function isSubsequence(haystack, needle) {
    if (needle === '') {
      return true;
    }
    let position = 0;
    for (const char of needle) {
      position = haystack.indexOf(char, position);
      if (position === -1) {
        return false;
      }
      position += 1;
    }
    return true;
  }

  function applySearchFilter() {
    const query = normalizeDigits(searchInput ? searchInput.value : '');
    let visibleCount = 0;

    rows.forEach(row => {
      const phoneDigits = normalizeDigits(row.dataset.phone || '');
      const matches = query === '' || isSubsequence(phoneDigits, query);
      row.classList.toggle('hidden', !matches);
      if (matches) {
        visibleCount++;
      }
    });

    if (noResultsMessage) {
      const shouldShow = visibleCount === 0 && rows.length > 0;
      noResultsMessage.classList.toggle('hidden', !shouldShow);
    }

    updateActiveCounter();
  }

  document.querySelectorAll('.mailing-toggle').forEach(toggle => {
    toggle.addEventListener('change', async () => {
      const userId = toggle.dataset.userId;
      const allow = toggle.checked ? '1' : '0';
      const row = toggle.closest('[data-row]');

      try {
        const response = await fetch('/admin/apps/mailing/toggle', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: new URLSearchParams({ user_id: userId, allow })
        });
        if (!response.ok) {
          throw new Error('Ошибка сохранения');
        }
        row.dataset.allow = allow;
        applySearchFilter();
      } catch (err) {
        toggle.checked = !toggle.checked;
        showToast(err.message || 'Не удалось сохранить изменение');
      }
    });
  });

  const modal = document.getElementById('commentModal');
  const modalTitle = document.getElementById('modalTitle');
  const modalComment = document.getElementById('modalComment');
  const modalNalet = document.getElementById('modalNalet');
  const modalUserId = document.getElementById('commentUserId');

  function openModal(row) {
    modalTitle.textContent = row.dataset.name + ' — ' + row.dataset.phone;
    modalComment.value = row.dataset.comment || '';
    modalNalet.value = row.dataset.nalet || '';
    modalUserId.value = row.dataset.userId;
    modal.classList.remove('hidden');
  }

  function closeModal() {
    modal.classList.add('hidden');
  }

  rows.forEach(row => {
    row.addEventListener('click', (event) => {
      if (event.target.closest('.mailing-toggle') || event.target.closest('label')) {
        return;
      }
      openModal(row);
    });
  });

  document.getElementById('modalClose').addEventListener('click', closeModal);
  document.getElementById('modalCancel').addEventListener('click', closeModal);

  document.getElementById('commentForm').addEventListener('submit', async (event) => {
    event.preventDefault();
    const formData = new FormData(event.target);

    try {
      const response = await fetch('/admin/apps/mailing/comment', {
        method: 'POST',
        body: formData
      });
      if (!response.ok) {
        throw new Error('Ошибка сохранения');
      }
      const userId = formData.get('user_id');
      const row = document.querySelector(`[data-row][data-user-id="${userId}"]`);
      if (row) {
        const comment = formData.get('comment') || '';
        const nalet = formData.get('nalet_number') || '';
        row.dataset.comment = comment;
        row.dataset.nalet = nalet;
        const cell = row.querySelector('[data-nalet-cell]');
        if (cell) {
          cell.textContent = nalet;
        }
      }
      showToast('Комментарий сохранён');
      closeModal();
    } catch (err) {
      showToast(err.message || 'Не удалось сохранить');
    }
  });

  document.getElementById('copyActive').addEventListener('click', async () => {
    const phones = rows
      .filter(row => row.dataset.allow === '1' && row.dataset.phone && !row.classList.contains('hidden'))
      .map(row => row.dataset.phone.trim())
      .filter(Boolean);

    if (!phones.length) {
      showToast('Нет активных номеров для копирования');
      return;
    }

    const text = phones.join('\n');

    try {
      await navigator.clipboard.writeText(text);
      showToast('Номера скопированы в буфер обмена');
    } catch (err) {
      showToast('Не удалось скопировать номера');
    }
  });

  if (searchInput) {
    searchInput.addEventListener('input', applySearchFilter);
  }

  applySearchFilter();
</script>
