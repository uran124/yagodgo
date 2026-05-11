<?php
/** @var array $clients */
/** @var int $activeCount */
?>
<div class="space-y-6">
  <div class="bg-white p-4 rounded shadow">
    <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-4">
      <div class="grid grid-cols-1 md:grid-cols-4 gap-3 w-full">
        <div>
          <label for="phoneSearch" class="block text-sm text-gray-500 mb-1">Поиск по номеру</label>
          <input id="phoneSearch" type="text" placeholder="Введите цифры номера"
                 class="w-full rounded border border-gray-300 bg-transparent px-3 py-2"
                 autocomplete="off">
        </div>
        <div>
          <label class="block text-sm text-gray-500 mb-1">Заказы (от / до)</label>
          <div class="grid grid-cols-2 gap-2">
            <input id="ordersFrom" type="number" min="0" placeholder="От" class="w-full rounded border border-gray-300 bg-transparent px-3 py-2">
            <input id="ordersTo" type="number" min="0" placeholder="До" class="w-full rounded border border-gray-300 bg-transparent px-3 py-2">
          </div>
        </div>
        <div>
          <label class="block text-sm text-gray-500 mb-1">Последний заказ (от / до)</label>
          <div class="grid grid-cols-2 gap-2">
            <input id="lastOrderFrom" type="date" class="w-full rounded border border-gray-300 bg-transparent px-3 py-2">
            <input id="lastOrderTo" type="date" class="w-full rounded border border-gray-300 bg-transparent px-3 py-2">
          </div>
        </div>
        <div>
          <label class="block text-sm text-gray-500 mb-1">Клубнички (от / до)</label>
          <div class="grid grid-cols-2 gap-2">
            <input id="pointsFrom" type="number" min="0" placeholder="От" class="w-full rounded border border-gray-300 bg-transparent px-3 py-2">
            <input id="pointsTo" type="number" min="0" placeholder="До" class="w-full rounded border border-gray-300 bg-transparent px-3 py-2">
          </div>
        </div>
      </div>
      <p class="text-sm text-gray-500 md:text-right">Начните вводить цифры, чтобы отфильтровать список клиентов для рассылки.</p>
    </div>
    <p class="mt-1 text-xs text-gray-500">Можно комбинировать фильтры по номеру, заказам, дате последнего заказа и клубничкам.</p>
  </div>

  <div class="bg-white p-4 rounded shadow">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 mb-4">
      <div>
        <h2 class="text-lg font-semibold">Клиенты для рассылки</h2>
        <p class="text-sm text-gray-500">Активных номеров: <span id="activeCounter"><?= (int)$activeCount ?></span></p>
      </div>
      <div class="flex gap-2">
        <button type="button" id="exportOpen" class="px-4 py-2 rounded bg-blue-600 text-white shadow">Выгрузить</button>
        <button type="button" id="copyActive" class="px-4 py-2 rounded bg-green-600 text-white shadow">Сохранить</button>
      </div>
    </div>

    <div class="overflow-x-auto">
      <table class="min-w-full divide-y divide-gray-200">
        <thead>
          <tr class="text-left text-xs uppercase tracking-wider text-gray-400">
            <th class="px-4 py-3">Имя</th>
            <th class="px-4 py-3">Телефон</th>
            <th class="px-4 py-3 text-center">Рассылка</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-200" id="mailingTable">
        <?php foreach ($clients as $client):
            $allow = (int)($client['allow_mailing'] ?? 1) === 1;
            $comment = $client['comment'] ?? '';
            $phone = $client['phone'] ?? '';
        ?>
          <tr class="hover:bg-gray-50 cursor-pointer" data-row
              data-user-id="<?= (int)$client['id'] ?>"
              data-name="<?= htmlspecialchars($client['name'] ?? 'Без имени', ENT_QUOTES) ?>"
              data-phone="<?= htmlspecialchars($phone, ENT_QUOTES) ?>"
              data-orders="<?= (int)($client['orders_count'] ?? 0) ?>"
              data-last-order-date="<?= htmlspecialchars((string)($client['last_order_date'] ?? ''), ENT_QUOTES) ?>"
              data-points="<?= (int)($client['points'] ?? 0) ?>"
              data-comment="<?= htmlspecialchars($comment, ENT_QUOTES) ?>"
              data-allow="<?= $allow ? '1' : '0' ?>">
            <td class="px-4 py-3 font-medium flex items-center space-x-2">
              <span class="material-icons-round text-[#C86052] text-base">person</span>
              <span><?= htmlspecialchars($client['name'] ?? 'Без имени') ?></span>
            </td>
            <td class="px-4 py-3 text-gray-300"><?= htmlspecialchars($phone) ?></td>
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
<div id="exportModal" class="fixed inset-0 bg-black/60 flex items-center justify-center z-50 hidden">
  <div class="bg-white rounded-lg shadow-lg w-full max-w-md p-6 relative">
    <button type="button" id="exportClose" class="absolute top-3 right-3 text-gray-400 hover:text-white">
      <span class="material-icons-round">close</span>
    </button>
    <h3 class="text-xl font-semibold mb-4">Выгрузка полей</h3>
    <div class="space-y-2 mb-5">
      <label class="flex items-center gap-2"><input type="checkbox" class="export-field" value="phone" checked> Телефон</label>
      <label class="flex items-center gap-2"><input type="checkbox" class="export-field" value="name" checked> Имя</label>
      <label class="flex items-center gap-2"><input type="checkbox" class="export-field" value="orders"> Заказы</label>
      <label class="flex items-center gap-2"><input type="checkbox" class="export-field" value="points"> Клубнички</label>
    </div>
    <div class="flex justify-end gap-3">
      <button type="button" class="px-4 py-2 rounded border border-gray-500" id="exportCancel">Отмена</button>
      <button type="button" class="px-4 py-2 rounded bg-[#C86052] text-white" id="exportSubmit">Выгрузить</button>
    </div>
  </div>
</div>

<script>
  const toastEl = document.getElementById('toast');
  const rows = Array.from(document.querySelectorAll('[data-row]'));
  const noResultsMessage = document.getElementById('noResults');
  const searchInput = document.getElementById('phoneSearch');
  const ordersFromInput = document.getElementById('ordersFrom');
  const ordersToInput = document.getElementById('ordersTo');
  const lastOrderFromInput = document.getElementById('lastOrderFrom');
  const lastOrderToInput = document.getElementById('lastOrderTo');
  const pointsFromInput = document.getElementById('pointsFrom');
  const pointsToInput = document.getElementById('pointsTo');

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
    const ordersFrom = Number(ordersFromInput?.value || 0);
    const ordersTo = Number(ordersToInput?.value || Number.MAX_SAFE_INTEGER);
    const pointsFrom = Number(pointsFromInput?.value || 0);
    const pointsTo = Number(pointsToInput?.value || Number.MAX_SAFE_INTEGER);
    const lastOrderFrom = lastOrderFromInput?.value ? new Date(lastOrderFromInput.value) : null;
    const lastOrderTo = lastOrderToInput?.value ? new Date(lastOrderToInput.value) : null;
    let visibleCount = 0;

    rows.forEach(row => {
      const phoneDigits = normalizeDigits(row.dataset.phone || '');
      const hasOrders = row.dataset.orders !== undefined && row.dataset.orders !== '';
      const hasPoints = row.dataset.points !== undefined && row.dataset.points !== '';
      const hasLastOrder = row.dataset.lastOrderDate !== undefined && row.dataset.lastOrderDate !== '';
      const orders = Number(row.dataset.orders || 0);
      const points = Number(row.dataset.points || 0);
      const rowLastOrder = hasLastOrder ? new Date(row.dataset.lastOrderDate) : null;
      const hasValidLastOrder = rowLastOrder instanceof Date && !Number.isNaN(rowLastOrder.getTime());
      const matchesPhone = query === '' || isSubsequence(phoneDigits, query);
      const matchesOrders = !hasOrders || (orders >= ordersFrom && orders <= ordersTo);
      const matchesPoints = !hasPoints || (points >= pointsFrom && points <= pointsTo);
      const matchesLastOrderFrom = !lastOrderFrom || !hasValidLastOrder || rowLastOrder >= lastOrderFrom;
      const matchesLastOrderTo = !lastOrderTo || !hasValidLastOrder || rowLastOrder <= lastOrderTo;
      const matches = matchesPhone && matchesOrders && matchesPoints && matchesLastOrderFrom && matchesLastOrderTo;
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
  const modalUserId = document.getElementById('commentUserId');

  function openModal(row) {
    modalTitle.textContent = row.dataset.name + ' — ' + row.dataset.phone;
    modalComment.value = row.dataset.comment || '';
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
        row.dataset.comment = comment;
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
  [ordersFromInput, ordersToInput, lastOrderFromInput, lastOrderToInput, pointsFromInput, pointsToInput]
    .forEach(input => input && input.addEventListener('input', applySearchFilter));

  const exportModal = document.getElementById('exportModal');
  const exportOpenBtn = document.getElementById('exportOpen');
  const exportCloseBtn = document.getElementById('exportClose');
  const exportCancelBtn = document.getElementById('exportCancel');
  const exportSubmitBtn = document.getElementById('exportSubmit');

  function closeExportModal() {
    exportModal.classList.add('hidden');
  }

  exportOpenBtn.addEventListener('click', () => exportModal.classList.remove('hidden'));
  exportCloseBtn.addEventListener('click', closeExportModal);
  exportCancelBtn.addEventListener('click', closeExportModal);

  exportSubmitBtn.addEventListener('click', async () => {
    const selectedFields = Array.from(document.querySelectorAll('.export-field:checked')).map(el => el.value);
    if (!selectedFields.length) {
      showToast('Выберите хотя бы одно поле');
      return;
    }

    const lines = rows
      .filter(row => !row.classList.contains('hidden'))
      .map(row => selectedFields.map(field => {
        if (field === 'phone') return (row.dataset.phone || '').trim();
        if (field === 'name') return (row.dataset.name || '').trim();
        if (field === 'orders') return String(Number(row.dataset.orders || 0));
        if (field === 'points') return String(Number(row.dataset.points || 0));
        return '';
      }).join(' ').trim())
      .filter(Boolean);

    if (!lines.length) {
      showToast('Нет данных для выгрузки');
      return;
    }

    try {
      await navigator.clipboard.writeText(lines.join('\n'));
      showToast('Данные скопированы в буфер обмена');
      closeExportModal();
    } catch (err) {
      showToast('Не удалось скопировать данные');
    }
  });

  applySearchFilter();
</script>
