<?php
/** @var array $settings */
/** @var array $themeColors */
/** @var array $deliveryTariffZones */
$themeColors = $themeColors ?? [];
$deliveryTariffZones = $deliveryTariffZones ?? [];
?>
<form action="/admin/settings" method="post" class="bg-white p-6 rounded shadow max-w-5xl space-y-4">
  <div>
    <label class="block mb-1">Название компании</label>
    <input name="company_name" type="text"
           value="<?= htmlspecialchars($settings['company_name'] ?? '') ?>"
           class="w-full border px-2 py-1 rounded">
  </div>
  <div>
    <label class="block mb-1">Контактный телефон</label>
    <input name="contact_phone" type="text"
           value="<?= htmlspecialchars($settings['contact_phone'] ?? '') ?>"
           class="w-full border px-2 py-1 rounded">
  </div>
  <fieldset class="border border-gray-200 rounded-lg p-4 space-y-3">
    <legend class="px-2 text-sm font-semibold text-gray-600">Ценообразование закупок</legend>
    <div>
      <label class="block mb-1">Наценка к закупке, %</label>
      <input name="pricing_instant_margin_percent" type="number" min="0" max="500" step="0.1"
             value="<?= htmlspecialchars($settings['pricing_instant_margin_percent'] ?? '50') ?>"
             class="w-full border px-2 py-1 rounded">
      <p class="mt-1 text-xs text-gray-500">Цена в наличии считается от закупочной цены с этой наценкой.</p>
    </div>
    <div>
      <label class="block mb-1">Шаг округления цен, ₽</label>
      <input name="pricing_rounding_step" type="number" min="1" max="10000" step="1"
             value="<?= htmlspecialchars($settings['pricing_rounding_step'] ?? '10') ?>"
             class="w-full border px-2 py-1 rounded">
      <p class="mt-1 text-xs text-gray-500">Цена в наличии и предзаказа округляются вниз до этого шага.</p>
    </div>
  </fieldset>
  <fieldset class="border border-gray-200 rounded-lg p-4 space-y-3">
    <legend class="px-2 text-sm font-semibold text-gray-600">Предзаказ (витрина и цены)</legend>
    <div>
      <label class="block mb-1">Скидка предзаказа, %</label>
      <input name="ui_preorder_discount_percent" type="number" min="0" max="99" step="0.1"
             value="<?= htmlspecialchars($settings['ui_preorder_discount_percent'] ?? '10') ?>"
             class="w-full border px-2 py-1 rounded">
      <p class="mt-1 text-xs text-gray-500">Цена предзаказа считается от цены в наличии минус эта скидка.</p>
    </div>
    <div>
      <label class="block mb-1">Подсказка о цене</label>
      <input name="ui_preorder_price_hint" type="text"
             value="<?= htmlspecialchars($settings['ui_preorder_price_hint'] ?? 'Цена ориентировочная, точная цена будет после поступления') ?>"
             class="w-full border px-2 py-1 rounded">
    </div>
    <div>
      <label class="block mb-1">Сообщение при отсутствии товара в блоке «В наличии»</label>
      <textarea name="ui_home_no_stock_message" rows="3"
                class="w-full border px-2 py-1 rounded"><?= htmlspecialchars($settings['ui_home_no_stock_message'] ?? 'На данный момент ягод нет в наличии. Воспользуйтесь нашим предложением предварительного заказа со скидкой 10% — это дополнительная скидка за оформление предварительного бронирования.') ?></textarea>
    </div>
  </fieldset>
  <fieldset class="border border-gray-200 rounded-lg p-4 space-y-4">
    <legend class="px-2 text-sm font-semibold text-gray-600">Оплата Robokassa</legend>
    <div class="rounded-lg bg-blue-50 p-3 text-sm text-blue-900">
      <p class="font-semibold">Интерфейс оплаты</p>
      <p class="mt-1">Покупатель уходит на страницу Robokassa по адресу <code>https://auth.robokassa.ru/Merchant/Index.aspx</code>, а магазин после уведомления на ResultURL сам обновляет статус заказа.</p>
      <p class="mt-1">В кабинете Robokassa укажите методы: <strong>ResultURL — POST</strong>, <strong>SuccessURL — GET</strong>, <strong>FailURL — GET</strong>.</p>
    </div>
    <div class="grid gap-3 sm:grid-cols-2">
      <label class="flex items-center gap-2 rounded border border-gray-200 p-3">
        <input type="checkbox" name="robokassa_enabled" value="1"
               <?= (($settings['robokassa_enabled'] ?? '0') === '1') ? 'checked' : '' ?>
               class="h-4 w-4 rounded border-gray-300 text-[#C86052] focus:ring-[#C86052]">
        <span>
          <span class="block font-medium text-gray-800">Включить оплату</span>
          <span class="block text-xs text-gray-500">Показывать Robokassa как способ оплаты.</span>
        </span>
      </label>
      <label class="flex items-center gap-2 rounded border border-gray-200 p-3">
        <input type="checkbox" name="robokassa_is_test" value="1"
               <?= (($settings['robokassa_is_test'] ?? '1') === '1') ? 'checked' : '' ?>
               class="h-4 w-4 rounded border-gray-300 text-[#C86052] focus:ring-[#C86052]">
        <span>
          <span class="block font-medium text-gray-800">Тестовый режим</span>
          <span class="block text-xs text-gray-500">Передаёт IsTest=1 в форму оплаты.</span>
        </span>
      </label>
    </div>
    <div class="grid gap-3 sm:grid-cols-2">
      <div>
        <label class="block mb-1">MerchantLogin</label>
        <input name="robokassa_merchant_login" type="text" autocomplete="off"
               value="<?= htmlspecialchars($settings['robokassa_merchant_login'] ?? '') ?>"
               class="w-full border px-2 py-1 rounded">
        <p class="mt-1 text-xs text-gray-500">Логин магазина из технических настроек Robokassa.</p>
      </div>
      <div>
        <label class="block mb-1">Алгоритм подписи</label>
        <?php $robokassaHash = strtoupper((string)($settings['robokassa_hash_algorithm'] ?? 'MD5')); ?>
        <select name="robokassa_hash_algorithm" class="w-full border px-2 py-1 rounded">
          <?php foreach (['MD5', 'SHA256', 'SHA384', 'SHA512'] as $algorithm): ?>
            <option value="<?= $algorithm ?>" <?= $robokassaHash === $algorithm ? 'selected' : '' ?>><?= $algorithm ?></option>
          <?php endforeach; ?>
        </select>
        <p class="mt-1 text-xs text-gray-500">Должен совпадать с алгоритмом в кабинете Robokassa.</p>
      </div>
    </div>
    <div class="grid gap-3 sm:grid-cols-2">
      <div>
        <label class="block mb-1">Пароль #1</label>
        <input name="robokassa_password1" type="password" autocomplete="new-password"
               value="" placeholder="<?= !empty($settings['robokassa_password1']) ? 'Сохранён — оставьте пустым, чтобы не менять' : '' ?>"
               class="w-full border px-2 py-1 rounded">
        <p class="mt-1 text-xs text-gray-500">Используется для SignatureValue при переходе к оплате.</p>
      </div>
      <div>
        <label class="block mb-1">Пароль #2</label>
        <input name="robokassa_password2" type="password" autocomplete="new-password"
               value="" placeholder="<?= !empty($settings['robokassa_password2']) ? 'Сохранён — оставьте пустым, чтобы не менять' : '' ?>"
               class="w-full border px-2 py-1 rounded">
        <p class="mt-1 text-xs text-gray-500">Используется для проверки ResultURL-уведомлений.</p>
      </div>
    </div>
    <div class="grid gap-3 sm:grid-cols-2">
      <div>
        <label class="block mb-1">URL оплаты</label>
        <input name="robokassa_payment_url" type="url"
               value="<?= htmlspecialchars($settings['robokassa_payment_url'] ?? 'https://auth.robokassa.ru/Merchant/Index.aspx') ?>"
               class="w-full border px-2 py-1 rounded">
      </div>
      <div>
        <label class="block mb-1">Способ оплаты по умолчанию</label>
        <input name="robokassa_inc_curr_label" type="text"
               value="<?= htmlspecialchars($settings['robokassa_inc_curr_label'] ?? 'BankCard') ?>"
               class="w-full border px-2 py-1 rounded">
        <p class="mt-1 text-xs text-gray-500">Например BankCard. Можно оставить пустым.</p>
      </div>
    </div>
    <div class="grid gap-3 sm:grid-cols-3">
      <div>
        <label class="block mb-1">Язык интерфейса</label>
        <?php $robokassaCulture = (string)($settings['robokassa_culture'] ?? 'ru'); ?>
        <select name="robokassa_culture" class="w-full border px-2 py-1 rounded">
          <option value="ru" <?= $robokassaCulture === 'ru' ? 'selected' : '' ?>>ru</option>
          <option value="en" <?= $robokassaCulture === 'en' ? 'selected' : '' ?>>en</option>
        </select>
      </div>
      <div>
        <label class="block mb-1">Encoding</label>
        <input name="robokassa_encoding" type="text"
               value="<?= htmlspecialchars($settings['robokassa_encoding'] ?? 'UTF-8') ?>"
               class="w-full border px-2 py-1 rounded">
      </div>
      <div>
        <label class="block mb-1">Срок оплаты, минут</label>
        <input name="robokassa_expiration_minutes" type="number" min="0" max="10080" step="1"
               value="<?= htmlspecialchars($settings['robokassa_expiration_minutes'] ?? '60') ?>"
               class="w-full border px-2 py-1 rounded">
      </div>
    </div>
    <div>
      <label class="block mb-1">Описание платежа</label>
      <input name="robokassa_default_description" type="text" maxlength="100"
             value="<?= htmlspecialchars($settings['robokassa_default_description'] ?? 'Оплата заказа BerryGo') ?>"
             class="w-full border px-2 py-1 rounded">
      <p class="mt-1 text-xs text-gray-500">До 100 символов, будет использоваться в Description.</p>
    </div>
    <div class="grid gap-3">
      <div>
        <label class="block mb-1">ResultURL</label>
        <input name="robokassa_result_url" type="url"
               value="<?= htmlspecialchars($settings['robokassa_result_url'] ?? 'https://berrygo.ru/payments/robokassa/result') ?>"
               class="w-full border px-2 py-1 rounded">
        <p class="mt-1 text-xs text-gray-500">Серверное уведомление Robokassa об оплате. На этой стороне нужно обновлять статус заказа.</p>
      </div>
      <div class="grid gap-3 sm:grid-cols-2">
        <div>
          <label class="block mb-1">SuccessURL</label>
          <input name="robokassa_success_url" type="url"
                 value="<?= htmlspecialchars($settings['robokassa_success_url'] ?? 'https://berrygo.ru/payments/robokassa/success') ?>"
                 class="w-full border px-2 py-1 rounded">
        </div>
        <div>
          <label class="block mb-1">FailURL</label>
          <input name="robokassa_fail_url" type="url"
                 value="<?= htmlspecialchars($settings['robokassa_fail_url'] ?? 'https://berrygo.ru/payments/robokassa/fail') ?>"
                 class="w-full border px-2 py-1 rounded">
        </div>
      </div>
    </div>
    <div class="rounded-lg bg-amber-50 p-3 text-xs text-amber-900">
      <p class="font-semibold">Формула подписи для базовой кнопки</p>
      <p class="mt-1 font-mono">MerchantLogin:OutSum:InvId:Пароль#1</p>
      <p class="mt-1">Если добавляются Receipt, StepByStep, ResultUrl2/SuccessUrl2/FailUrl2 или Shp_* параметры, их нужно включать в SignatureValue строго по правилам Robokassa.</p>
    </div>
  </fieldset>
  <fieldset class="border border-gray-200 rounded-lg p-4 space-y-4">
    <legend class="px-2 text-sm font-semibold text-gray-600">Доставка и расстояния</legend>
    <div class="grid gap-3 sm:grid-cols-2">
      <div>
        <label class="block mb-1">Адрес магазина / точки старта</label>
        <input name="delivery_store_address" type="text"
               value="<?= htmlspecialchars($settings['delivery_store_address'] ?? 'Самовывоз: 9 мая, 73') ?>"
               class="w-full border px-2 py-1 rounded">
        <p class="mt-1 text-xs text-gray-500">Используется как человекочитаемая точка старта доставки и самовывоза.</p>
      </div>
      <div>
        <label class="block mb-1">Стоимость доставки по умолчанию, ₽</label>
        <input name="delivery_default_fee" type="number" min="0" max="100000" step="1"
               value="<?= htmlspecialchars($settings['delivery_default_fee'] ?? '300') ?>"
               class="w-full border px-2 py-1 rounded">
        <p class="mt-1 text-xs text-gray-500">Fallback, если расстояние не рассчиталось или тарифная зона не найдена.</p>
      </div>
    </div>
    <div class="grid gap-3 sm:grid-cols-2">
      <div>
        <label class="block mb-1">Широта магазина</label>
        <input name="delivery_store_lat" type="number" min="-90" max="90" step="0.000001"
               value="<?= htmlspecialchars($settings['delivery_store_lat'] ?? '') ?>"
               class="w-full border px-2 py-1 rounded">
      </div>
      <div>
        <label class="block mb-1">Долгота магазина</label>
        <input name="delivery_store_lng" type="number" min="-180" max="180" step="0.000001"
               value="<?= htmlspecialchars($settings['delivery_store_lng'] ?? '') ?>"
               class="w-full border px-2 py-1 rounded">
      </div>
    </div>
    <div class="grid gap-3 sm:grid-cols-2">
      <div>
        <label class="block mb-1">С какой дистанции считать по километражу, км</label>
        <input name="delivery_per_km_from_km" type="number" min="0" max="1000" step="0.1"
               value="<?= htmlspecialchars($settings['delivery_per_km_from_km'] ?? '6') ?>"
               class="w-full border px-2 py-1 rounded">
        <p class="mt-1 text-xs text-gray-500">Если адрес дальше этой границы и не попал в фиксированную зону, цена считается по ставке за км.</p>
      </div>
      <div>
        <label class="block mb-1">Стоимость за километр после границы, ₽</label>
        <input name="delivery_per_km_price" type="number" min="0" max="100000" step="1"
               value="<?= htmlspecialchars($settings['delivery_per_km_price'] ?? '50') ?>"
               class="w-full border px-2 py-1 rounded">
      </div>
    </div>
    <div class="grid gap-3 sm:grid-cols-3">
      <div>
        <label class="block mb-1">OpenRouteService API key</label>
        <input name="openrouteservice_api_key" type="password" autocomplete="new-password"
               placeholder="<?= !empty($settings['openrouteservice_api_key']) ? 'Ключ сохранён' : 'Введите API key' ?>"
               class="w-full border px-2 py-1 rounded">
        <p class="mt-1 text-xs text-gray-500">Для маршрута по дороге через directions/driving-car. Пустое поле не перезаписывает сохранённый ключ.</p>
      </div>
      <div>
        <label class="block mb-1">DaData API key</label>
        <input name="dadata_api_key" type="password" autocomplete="new-password"
               placeholder="<?= !empty($settings['dadata_api_key']) ? 'Ключ сохранён' : 'Введите API key' ?>"
               class="w-full border px-2 py-1 rounded">
        <p class="mt-1 text-xs text-gray-500">Для /clean/address и получения координат адреса доставки.</p>
      </div>
      <div>
        <label class="block mb-1">DaData Secret key</label>
        <input name="dadata_secret_key" type="password" autocomplete="new-password"
               placeholder="<?= !empty($settings['dadata_secret_key']) ? 'Секрет сохранён' : 'Введите Secret key' ?>"
               class="w-full border px-2 py-1 rounded">
        <p class="mt-1 text-xs text-gray-500">Пустое поле не перезаписывает сохранённый секрет.</p>
      </div>
    </div>
    <div class="rounded-lg border border-dashed border-gray-300 p-3 space-y-3">
      <label class="inline-flex items-center gap-2 text-sm font-medium text-gray-700">
        <input name="delivery_taxi_courier_enabled" type="checkbox" value="1"
               <?= ($settings['delivery_taxi_courier_enabled'] ?? '0') === '1' ? 'checked' : '' ?>
               class="rounded border-gray-300 text-pink-600 focus:ring-pink-500">
        Показывать последний вариант доставки: кнопка вызова такси-курьера
      </label>
      <div class="grid gap-3 sm:grid-cols-2">
        <div>
          <label class="block mb-1">Текст кнопки</label>
          <input name="delivery_taxi_courier_button_text" type="text"
                 value="<?= htmlspecialchars($settings['delivery_taxi_courier_button_text'] ?? 'Вызову такси-курьера') ?>"
                 class="w-full border px-2 py-1 rounded">
        </div>
        <div>
          <label class="block mb-1">Подсказка для клиента</label>
          <input name="delivery_taxi_courier_instructions" type="text"
                 value="<?= htmlspecialchars($settings['delivery_taxi_courier_instructions'] ?? '') ?>"
                 placeholder="Например: менеджер подтвердит адрес и поможет вызвать курьера"
                 class="w-full border px-2 py-1 rounded">
        </div>
      </div>
    </div>
  </fieldset>

  <fieldset class="border border-gray-200 rounded-lg p-4 space-y-4">
    <legend class="px-2 text-sm font-semibold text-gray-600">Тарифные зоны доставки</legend>
    <p class="text-xs text-gray-500">Диапазоны задаются в километрах по автомобильной дороге. Рекомендуем не пересекать зоны: например 0–4 км, 4–6 км, 6–8 км.</p>
    <div class="overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead class="text-left text-gray-500">
          <tr>
            <th class="py-2 pr-2">Сорт.</th>
            <th class="py-2 pr-2">От, км</th>
            <th class="py-2 pr-2">До, км</th>
            <th class="py-2 pr-2">Стоимость, ₽</th>
            <th class="py-2 pr-2">Активна</th>
            <th class="py-2 pr-2">Удалить</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <?php
            $zoneRows = $deliveryTariffZones;
            $zoneRows[] = ['id' => '', 'min_km' => '', 'max_km' => '', 'price_rub' => '', 'sort_order' => count($zoneRows) + 1, 'is_active' => 1];
          ?>
          <?php foreach ($zoneRows as $idx => $zone): ?>
            <tr>
              <td class="py-2 pr-2">
                <input type="hidden" name="delivery_tariff_zones[id][<?= $idx ?>]" value="<?= htmlspecialchars((string)($zone['id'] ?? '')) ?>">
                <input name="delivery_tariff_zones[sort_order][<?= $idx ?>]" type="number" step="1"
                       value="<?= htmlspecialchars((string)($zone['sort_order'] ?? ($idx + 1))) ?>"
                       class="w-20 border px-2 py-1 rounded">
              </td>
              <td class="py-2 pr-2">
                <input name="delivery_tariff_zones[min_km][<?= $idx ?>]" type="number" min="0" step="0.001"
                       value="<?= htmlspecialchars((string)($zone['min_km'] ?? '')) ?>"
                       class="w-28 border px-2 py-1 rounded">
              </td>
              <td class="py-2 pr-2">
                <input name="delivery_tariff_zones[max_km][<?= $idx ?>]" type="number" min="0" step="0.001"
                       value="<?= htmlspecialchars((string)($zone['max_km'] ?? '')) ?>"
                       placeholder="без лимита"
                       class="w-28 border px-2 py-1 rounded">
              </td>
              <td class="py-2 pr-2">
                <input name="delivery_tariff_zones[price_rub][<?= $idx ?>]" type="number" min="0" step="1"
                       value="<?= htmlspecialchars((string)($zone['price_rub'] ?? '')) ?>"
                       class="w-28 border px-2 py-1 rounded">
              </td>
              <td class="py-2 pr-2">
                <input name="delivery_tariff_zones[is_active][<?= $idx ?>]" type="checkbox" value="1"
                       <?= (int)($zone['is_active'] ?? 1) === 1 ? 'checked' : '' ?>
                       class="rounded border-gray-300 text-pink-600 focus:ring-pink-500">
              </td>
              <td class="py-2 pr-2">
                <?php if (!empty($zone['id'])): ?>
                  <label class="inline-flex items-center gap-1 text-xs text-red-600">
                    <input name="delivery_tariff_zones[delete][<?= $idx ?>]" type="checkbox" value="1" class="rounded border-gray-300 text-red-600 focus:ring-red-500">
                    удалить
                  </label>
                <?php else: ?>
                  <span class="text-xs text-gray-400">новая</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </fieldset>

  <?php if (!empty($themeColors)): ?>
    <fieldset class="border border-gray-200 rounded-lg p-4 space-y-3">
      <legend class="px-2 text-sm font-semibold text-gray-600">Цветовая тема</legend>
      <div class="grid gap-3 sm:grid-cols-2">
        <label class="block text-sm">
          <span class="block mb-1 text-gray-700">Светлая тема</span>
          <div class="relative">
            <select name="theme_light_primary" class="w-full border px-3 py-2 rounded appearance-none focus:outline-none focus:ring-2 focus:ring-offset-1 focus:ring-emerald-400">
              <?php foreach ($themeColors as $key => $palette): ?>
                <?php $light = $palette['light']; ?>
                <option value="<?= htmlspecialchars($key) ?>"
                        <?= ($settings['theme_light_primary'] ?? 'pink') === $key ? 'selected' : '' ?>>
                  <?= htmlspecialchars($palette['label']) ?>
                </option>
              <?php endforeach; ?>
            </select>
            <span class="pointer-events-none material-icons-round absolute right-2 top-1/2 -translate-y-1/2 text-gray-400">expand_more</span>
          </div>
          <span class="mt-1 flex h-2 rounded-full" style="background: linear-gradient(135deg, <?= htmlspecialchars($themeColors[$settings['theme_light_primary'] ?? 'pink']['light']['strong'] ?? $themeColors['pink']['light']['strong']) ?>, <?= htmlspecialchars($themeColors[$settings['theme_light_primary'] ?? 'pink']['light']['secondary'] ?? $themeColors['pink']['light']['secondary']) ?>);"></span>
        </label>
        <label class="block text-sm">
          <span class="block mb-1 text-gray-700">Тёмная тема</span>
          <div class="relative">
            <select name="theme_dark_primary" class="w-full border px-3 py-2 rounded appearance-none focus:outline-none focus:ring-2 focus:ring-offset-1 focus:ring-emerald-400">
              <?php foreach ($themeColors as $key => $palette): ?>
                <?php $dark = $palette['dark']; ?>
                <option value="<?= htmlspecialchars($key) ?>"
                        <?= ($settings['theme_dark_primary'] ?? 'pink') === $key ? 'selected' : '' ?>>
                  <?= htmlspecialchars($palette['label']) ?>
                </option>
              <?php endforeach; ?>
            </select>
            <span class="pointer-events-none material-icons-round absolute right-2 top-1/2 -translate-y-1/2 text-gray-400">expand_more</span>
          </div>
          <span class="mt-1 flex h-2 rounded-full" style="background: linear-gradient(135deg, <?= htmlspecialchars($themeColors[$settings['theme_dark_primary'] ?? 'pink']['dark']['strong'] ?? $themeColors['pink']['dark']['strong']) ?>, <?= htmlspecialchars($themeColors[$settings['theme_dark_primary'] ?? 'pink']['dark']['secondary'] ?? $themeColors['pink']['dark']['secondary']) ?>);"></span>
        </label>
      </div>
      <p class="text-xs text-gray-500">Выбранный цвет применится к основным кнопкам, ссылкам и акцентам пользовательского интерфейса.</p>
    </fieldset>
  <?php endif; ?>
  <!-- Добавьте другие поля по аналогии -->
  <button type="submit"
          class="accent-gradient text-white px-4 py-2 rounded accent-focus transition">
    Сохранить настройки
  </button>
</form>
