<?php
/** @var array $settings */
/** @var array $themeColors */
$themeColors = $themeColors ?? [];
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
