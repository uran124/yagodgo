<?php /** @var array $user @var array $transactions @var array $addresses @var array $referrers */ ?>
<?php
$role = $_SESSION['role'] ?? '';
$isManager = in_array($role, ['manager','partner'], true);
$base = $role === 'manager' ? '/manager' : ($role === 'partner' ? '/partner' : '/admin');
$roleNames = [
  'client'  => 'Клиент',
  'courier' => 'Курьер',
  'admin'   => 'Админ',
  'manager' => 'Менеджер',
  'partner' => 'Партнёр',
  'seller'  => 'Селлер',
];
$currentPath = $_SERVER['REQUEST_URI'] ?? '';
$redirectPath = $currentPath ? (parse_url($currentPath, PHP_URL_PATH) ?: '') : '';
?>
<?php if (!empty($_GET['error'])): ?>
  <div class="bg-red-50 border-l-4 border-red-400 p-3 mb-4 rounded">
    <p class="text-red-700 text-sm"><?= htmlspecialchars($_GET['error']) ?></p>
  </div>
<?php endif; ?>

<form action="<?= $base ?>/users/save" method="post" class="bg-white p-4 rounded shadow mb-4 space-y-4">
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= $user['id'] ?>">
  <?php if ($redirectPath): ?>
    <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirectPath) ?>">
  <?php endif; ?>
  <div class="flex justify-between">
    <div>
      <div class="font-semibold mb-3">ID: <?= $user['id'] ?></div>
      <div class="space-y-3">
        <div>
          <label for="user-name-input" class="block text-sm text-gray-600 mb-1">Имя</label>
          <input
            id="user-name-input"
            name="name"
            type="text"
            class="border rounded px-2 py-1 w-full max-w-xs"
            value="<?= htmlspecialchars($user['name']) ?>"
            placeholder="<?= htmlspecialchars($user['name']) ?>"
            required
          >
        </div>
        <div>
          <label for="user-phone-input" class="block text-sm text-gray-600 mb-1">Телефон</label>
          <input
            id="user-phone-input"
            name="phone"
            type="tel"
            class="border rounded px-2 py-1 w-full max-w-xs"
            value="<?= htmlspecialchars($user['phone']) ?>"
            placeholder="<?= htmlspecialchars($user['phone']) ?>"
            required
          >
        </div>
      </div>
    </div>
    <div>
      <label class="block text-sm mb-1">Роль</label>
      <?php if ($isManager): ?>
        <div><?= $roleNames[$user['role']] ?? htmlspecialchars($user['role']) ?></div>
      <?php else: ?>
        <select name="role" class="border rounded px-2 py-1">
          <option value="client" <?= $user['role']==='client'?'selected':'' ?>>Клиент</option>
          <option value="courier" <?= $user['role']==='courier'?'selected':'' ?>>Курьер</option>
          <option value="admin" <?= $user['role']==='admin'?'selected':'' ?>>Админ</option>
          <option value="manager" <?= $user['role']==='manager'?'selected':'' ?>>Менеджер</option>
          <option value="partner" <?= $user['role']==='partner'?'selected':'' ?>>Партнёр</option>
          <option value="seller" <?= $user['role']==='seller'?'selected':'' ?>>Селлер</option>
        </select>
      <?php endif; ?>
    </div>
  </div>
  <div class="flex justify-between items-center">
    <label class="flex items-center space-x-2">
      <input type="checkbox" name="is_blocked" value="1" <?= !empty($user['is_blocked']) ? 'checked' : '' ?>>
      <span>Заблокирован</span>
    </label>
    <div class="text-sm text-gray-500">Создан: <?= date('d.m.Y H:i', strtotime($user['created_at'])) ?></div>
  </div>
  <?php if (!$isManager): ?>
    <label class="flex items-start gap-2 rounded border border-amber-200 bg-amber-50 p-3 text-sm">
      <input type="checkbox" name="integration_partner_enabled" value="1" <?= !empty($user['integration_partner_enabled']) ? 'checked' : '' ?> <?= in_array($user['role'], ['partner','manager','admin'], true) ? '' : 'disabled' ?>>
      <span><strong>Партнёр интеграции Florix24</strong><br><span class="text-xs text-gray-600">Доступно только ролям партнёра, менеджера или администратора. Без этого флага заказ не назначает пользователя партнёром.</span></span>
    </label>
  <?php endif; ?>
  <div>Telegram: <?= htmlspecialchars($user['telegram_id'] ?? '') ?></div>
  <div>Пригласительный код: <?= htmlspecialchars($user['referral_code']) ?></div>
  <?php if (!$isManager): ?>
    <div>
      <label class="block text-sm mb-1">Реферансье</label>
      <select name="referred_by" class="border rounded px-2 py-1">
        <option value="">—</option>
        <?php foreach ($referrers as $ref): ?>
          <option value="<?= $ref['id'] ?>" <?= $user['referred_by'] == $ref['id'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($ref['name']) ?> (<?= htmlspecialchars($ref['phone']) ?>)
          </option>
        <?php endforeach; ?>
      </select>
    </div>
  <?php endif; ?>
    <div class="flex justify-between">
      <div>Баланс: <?= (int)$user['points_balance'] ?> 🍓</div>
      <?php if ($isManager): ?>
        <div><?= (int)$user['rub_balance'] ?> ₽</div>
      <?php endif; ?>
  </div>
  <div class="flex items-center justify-between">
    <button type="submit" class="bg-[#C86052] text-white px-4 py-2 rounded">Сохранить</button>
  </div>
</form>

<?php if ($role === 'admin'): ?>
  <form
    action="<?= $base ?>/users/delete"
    method="post"
    onsubmit="return confirm('Удалить пользователя? Это действие нельзя отменить.');"
    class="mb-4"
  >
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= $user['id'] ?>">
    <?php if ($redirectPath): ?>
      <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirectPath) ?>">
    <?php endif; ?>
    <button
      type="submit"
      class="border border-red-200 text-red-600 px-4 py-2 rounded hover:bg-red-50 transition"
    >
      Удалить
    </button>
  </form>
<?php endif; ?>
<script>
  (function () {
    const phoneInput = document.getElementById('user-phone-input');
    if (!phoneInput) {
      return;
    }
    const cleanPhone = (value) => {
      let digits = value.replace(/\D/g, '');
      if (digits.startsWith('7') || digits.startsWith('8')) {
        digits = digits.slice(1);
      }
      return digits.slice(0, 10);
    };
    phoneInput.addEventListener('input', () => {
      phoneInput.value = cleanPhone(phoneInput.value);
    });
  })();
</script>

<div class="bg-white p-4 rounded shadow mb-4">
  <h2 class="font-semibold mb-2">Адреса доставки</h2>
  <?php if (empty($addresses)): ?>
    <p class="text-gray-500 text-sm mb-2">Адресов нет</p>
  <?php else: ?>
    <ul class="space-y-2 mb-4">
      <?php foreach ($addresses as $addr): ?>
        <li class="rounded border border-gray-100 p-3">
          <div class="flex justify-between items-start gap-3">
            <div>
              <div><?= htmlspecialchars($addr['street']) ?></div>
              <div class="text-sm text-gray-500"><?= htmlspecialchars($addr['recipient_name']) ?> <?= htmlspecialchars($addr['recipient_phone']) ?></div>
              <div class="text-xs text-gray-500 mt-1">
                Км: <?= $addr['delivery_distance_km'] !== null && $addr['delivery_distance_km'] !== '' ? htmlspecialchars((string)$addr['delivery_distance_km']) : 'не задано' ?>
                <?php if (!empty($addr['delivery_distance_provider'])): ?> · <?= htmlspecialchars((string)$addr['delivery_distance_provider']) ?><?php endif; ?>
              </div>
            </div>
            <form action="<?= $base ?>/users/delete-address" method="post" onsubmit="return confirm('Удалить адрес?');">
              <?= csrf_field() ?>
              <input type="hidden" name="id" value="<?= $addr['id'] ?>">
              <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
              <button type="submit" class="text-red-600">🗑️</button>
            </form>
          </div>
          <form action="<?= $base ?>/users/update-address-delivery" method="post" class="mt-2 grid gap-2 md:grid-cols-[140px_1fr_auto]">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= $addr['id'] ?>">
            <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
            <input name="delivery_distance_km_manual" type="number" min="0" step="0.001" value="<?= htmlspecialchars((string)($addr['delivery_distance_km'] ?? '')) ?>" class="border px-2 py-1 rounded" placeholder="Км вручную">
            <input name="last_checkout_comment" value="<?= htmlspecialchars((string)($addr['last_checkout_comment'] ?? '')) ?>" class="border px-2 py-1 rounded" placeholder="Комментарий/получатель по адресу">
            <button type="submit" class="bg-gray-800 text-white px-3 py-1 rounded">Сохранить</button>
          </form>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
  <form action="<?= $base ?>/users/add-address" method="post" class="space-y-2" data-user-address-form>
    <?= csrf_field() ?>
    <input type="hidden" name="user_id" value="<?= $user['id'] ?>">

    <div class="relative" data-user-address-root>
      <input
        name="address"
        class="w-full border px-2 py-1 rounded"
        placeholder="Новый адрес"
        autocomplete="off"
        data-user-address-input
      >
      <input type="hidden" name="selected_address" data-user-address-selected-address>
      <input type="hidden" name="selected_lat" data-user-address-selected-lat>
      <input type="hidden" name="selected_lng" data-user-address-selected-lng>
      <div class="hidden absolute z-30 mt-1 max-h-72 w-full overflow-auto rounded-lg border border-slate-600 bg-slate-950 text-slate-100 shadow-2xl" data-user-address-suggestions></div>
    </div>

    <input name="recipient_name" class="w-full border px-2 py-1 rounded" placeholder="Имя получателя">
    <input name="recipient_phone" class="w-full border px-2 py-1 rounded" placeholder="Телефон получателя">
    <input
      name="delivery_distance_km_manual"
      type="number"
      min="0"
      step="0.001"
      class="w-full border px-2 py-1 rounded"
      placeholder="Км вручную, если нужно скорректировать"
      data-user-address-distance-manual
    >
    <input type="hidden" name="delivery_distance_km_preview" data-user-address-distance-preview>
    <input type="hidden" name="delivery_distance_m_preview" data-user-address-distance-m-preview>
    <input type="hidden" name="delivery_pricing_source_preview" data-user-address-source-preview>
    <input type="hidden" name="delivery_tariff_zone_id_preview" data-user-address-zone-preview>
    <div class="hidden rounded-lg border border-slate-600 bg-slate-900/70 px-3 py-2 text-xs text-slate-200" data-user-address-delivery-result></div>
    <input name="last_checkout_comment" class="w-full border px-2 py-1 rounded" placeholder="Комментарий/получатель по адресу">
    <button type="submit" class="bg-[#C86052] text-white px-4 py-1 rounded">Добавить адрес доставки</button>
  </form>
</div>

<script>
  (function () {
    const form = document.querySelector('[data-user-address-form]');
    if (!form) return;

    const basePath = <?= json_encode($base, JSON_UNESCAPED_UNICODE) ?>;
    const addressInput = form.querySelector('[data-user-address-input]');
    const suggestionsBox = form.querySelector('[data-user-address-suggestions]');
    const selectedAddressInput = form.querySelector('[data-user-address-selected-address]');
    const selectedLatInput = form.querySelector('[data-user-address-selected-lat]');
    const selectedLngInput = form.querySelector('[data-user-address-selected-lng]');
    const distanceManualInput = form.querySelector('[data-user-address-distance-manual]');
    const distancePreviewInput = form.querySelector('[data-user-address-distance-preview]');
    const distanceMPreviewInput = form.querySelector('[data-user-address-distance-m-preview]');
    const sourcePreviewInput = form.querySelector('[data-user-address-source-preview]');
    const zonePreviewInput = form.querySelector('[data-user-address-zone-preview]');
    const resultBox = form.querySelector('[data-user-address-delivery-result]');
    let selectedSuggestion = null;
    let requestToken = 0;
    let calcTimer = null;

    const escapeHtml = (value) => String(value || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');

    const clearSelectedSuggestion = () => {
      selectedSuggestion = null;
      if (selectedAddressInput) selectedAddressInput.value = '';
      if (selectedLatInput) selectedLatInput.value = '';
      if (selectedLngInput) selectedLngInput.value = '';
      clearDeliveryPreview();
    };

    const clearDeliveryPreview = () => {
      if (distancePreviewInput) distancePreviewInput.value = '';
      if (distanceMPreviewInput) distanceMPreviewInput.value = '';
      if (sourcePreviewInput) sourcePreviewInput.value = '';
      if (zonePreviewInput) zonePreviewInput.value = '';
      if (resultBox) {
        resultBox.classList.add('hidden');
        resultBox.textContent = '';
      }
    };

    const showResult = (text, isOk) => {
      if (!resultBox) return;
      resultBox.classList.remove('hidden');
      resultBox.classList.toggle('border-emerald-500/60', !!isOk);
      resultBox.classList.toggle('border-amber-500/60', !isOk);
      resultBox.textContent = text;
    };

    const hideSuggestions = () => {
      if (!suggestionsBox) return;
      suggestionsBox.classList.add('hidden');
      suggestionsBox.innerHTML = '';
    };

    const renderSuggestions = (items, emptyText) => {
      if (!suggestionsBox) return;
      suggestionsBox.innerHTML = '';
      if (!items.length) {
        if (emptyText) {
          suggestionsBox.innerHTML = '<div class="px-3 py-2 text-xs text-slate-400">' + escapeHtml(emptyText) + '</div>';
          suggestionsBox.classList.remove('hidden');
        } else {
          hideSuggestions();
        }
        return;
      }

      items.forEach((item) => {
        const row = document.createElement('button');
        row.type = 'button';
        row.className = 'block w-full border-b border-slate-800 px-3 py-2 text-left hover:bg-slate-800 focus:bg-slate-800 focus:outline-none';
        const metaParts = [];
        if (item.city) metaParts.push(item.city);
        if (item.district) metaParts.push(item.district);
        if (item.qc_geo !== undefined && item.qc_geo !== null && item.qc_geo !== '') metaParts.push('qc_geo=' + item.qc_geo);
        if (item.distance_from_center_km) metaParts.push(item.distance_from_center_km + ' км от центра поиска');
        row.innerHTML = '<div class="text-sm font-semibold text-slate-100">' + escapeHtml(item.value || item.label || '') + '</div>'
          + '<div class="text-xs text-slate-400">' + escapeHtml(metaParts.join(' · ')) + '</div>';
        row.addEventListener('click', () => {
          selectedSuggestion = item;
          // В поле и в БД сохраняем короткий адрес DaData, как в админке настроек.
          const selectedAddress = item.value || item.label || item.unrestricted_value || '';
          addressInput.value = selectedAddress;
          if (selectedAddressInput) selectedAddressInput.value = selectedAddress;
          if (selectedLatInput) selectedLatInput.value = item.lat || '';
          if (selectedLngInput) selectedLngInput.value = item.lng || '';
          hideSuggestions();
          calculateDelivery();
        });
        suggestionsBox.appendChild(row);
      });
      suggestionsBox.classList.remove('hidden');
    };

    const fetchSuggestions = async (query) => {
      const response = await fetch(basePath + '/delivery/address-suggestions?query=' + encodeURIComponent(query), {
        credentials: 'same-origin',
        headers: {'X-Requested-With': 'XMLHttpRequest'}
      });
      const text = await response.text();
      let data;
      try {
        data = JSON.parse(text);
      } catch (error) {
        throw new Error('Сервер вернул не JSON');
      }
      if (!response.ok || !data.ok) {
        throw new Error(data.message || 'Не удалось получить подсказки DaData');
      }
      return data.suggestions || [];
    };

    const calculateDelivery = async () => {
      const address = addressInput ? addressInput.value.trim() : '';
      if (!address) {
        clearDeliveryPreview();
        return;
      }

      const body = new URLSearchParams();
      body.set('address', address);
      if (selectedSuggestion && selectedSuggestion.lat && selectedSuggestion.lng) {
        body.set('selected_address', selectedAddressInput ? selectedAddressInput.value : address);
        body.set('selected_lat', selectedSuggestion.lat);
        body.set('selected_lng', selectedSuggestion.lng);
      }
      if (distanceManualInput && distanceManualInput.value.trim() !== '') {
        body.set('delivery_distance_km_manual', distanceManualInput.value.trim());
      }

      showResult('Считаем доставку…', true);
      try {
        const response = await fetch(basePath + '/delivery/calculate', {
          method: 'POST',
          credentials: 'same-origin',
          headers: {'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest'},
          body
        });
        const text = await response.text();
        let data;
        try {
          data = JSON.parse(text);
        } catch (error) {
          throw new Error('Сервер вернул не JSON');
        }
        if (!response.ok || !data.ok) throw new Error(data.message || 'Не удалось рассчитать доставку');

        if (distancePreviewInput) distancePreviewInput.value = data.distance_km || '';
        if (distanceMPreviewInput) distanceMPreviewInput.value = data.distance_m || '';
        if (sourcePreviewInput) sourcePreviewInput.value = data.delivery_pricing_source || data.pricing_source || '';
        if (zonePreviewInput) zonePreviewInput.value = data.delivery_tariff_zone_id || '';

        const distanceText = data.distance_km ? data.distance_km + ' км' : 'км не определён';
        const feeText = data.delivery_fee !== undefined ? data.delivery_fee + ' ₽' : 'стоимость уточняется';
        const warning = data.warning ? ' ' + data.warning : '';
        showResult('Доставка: ' + feeText + '. Расстояние: ' + distanceText + '. ' + (data.message || '') + warning, true);
      } catch (error) {
        clearDeliveryPreview();
        showResult((error.message || 'Не удалось рассчитать доставку') + '. Можно указать километраж вручную.', false);
      }
    };

    const scheduleSuggestions = () => {
      const query = addressInput ? addressInput.value.trim() : '';
      requestToken++;
      const token = requestToken;
      if (query.length < 3) {
        hideSuggestions();
        return;
      }
      window.setTimeout(async () => {
        if (token !== requestToken) return;
        try {
          const items = await fetchSuggestions(query);
          if (token !== requestToken) return;
          renderSuggestions(items, 'Нет адресов в радиусе поиска.');
        } catch (error) {
          if (token !== requestToken) return;
          renderSuggestions([], error.message || 'Ошибка подсказок DaData.');
        }
      }, 250);
    };

    if (addressInput) {
      addressInput.addEventListener('input', () => {
        clearSelectedSuggestion();
        scheduleSuggestions();
      });
      addressInput.addEventListener('blur', () => {
        window.setTimeout(hideSuggestions, 200);
        if (calcTimer) window.clearTimeout(calcTimer);
        calcTimer = window.setTimeout(calculateDelivery, 300);
      });
    }

    if (distanceManualInput) {
      distanceManualInput.addEventListener('input', () => {
        if (calcTimer) window.clearTimeout(calcTimer);
        calcTimer = window.setTimeout(calculateDelivery, 300);
      });
    }
  })();
</script>

<div class="bg-white p-4 rounded shadow">
  <h2 class="font-semibold mb-2">Транзакции</h2>
  <?php if (empty($transactions)): ?>
    <p class="text-gray-500">Транзакций нет</p>
  <?php else: ?>
    <table class="w-full text-left">
      <thead>
        <tr>
          <th class="px-4 py-2 text-sm text-gray-500">Дата</th>
          <th class="px-4 py-2 text-sm text-gray-500">Сумма</th>
          <th class="px-4 py-2 text-sm text-gray-500">Тип</th>
          <th class="px-4 py-2 text-sm text-gray-500">Описание</th>
          <th class="px-4 py-2 text-sm text-gray-500">Заказ</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($transactions as $tx): ?>
        <tr class="border-t">
          <td class="px-4 py-2 text-sm text-gray-700"><?= date('d.m.Y H:i', strtotime($tx['created_at'])) ?></td>
          <td class="px-4 py-2 text-sm">
            <?php if ((int)$tx['amount'] > 0): ?>
              <span class="text-green-600 font-semibold">+<?= $tx['amount'] ?></span>
            <?php else: ?>
              <span class="text-red-600 font-semibold"><?= $tx['amount'] ?></span>
            <?php endif; ?>
            <span class="text-sm">🍓</span>
          </td>
          <td class="px-4 py-2 text-sm text-gray-700">
            <?= $tx['transaction_type'] === 'accrual' ? 'Приз' : 'Трата' ?>
          </td>
          <td class="px-4 py-2 text-sm text-gray-700"><?= htmlspecialchars($tx['description']) ?></td>
          <td class="px-4 py-2 text-sm text-gray-700">
            <?php if (!empty($tx['order_id'])): ?>
              <a href="<?= $base ?>/orders/<?= $tx['order_id'] ?>" class="text-blue-600 hover:underline">
                #<?= $tx['order_id'] ?>
              </a>
            <?php else: ?>
              —
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>
