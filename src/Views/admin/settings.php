<?php
/** @var array $settings */
/** @var array $themeColors */
/** @var array $deliveryTariffZones */
$themeColors = $themeColors ?? [];
$deliveryTariffZones = $deliveryTariffZones ?? [];
$florix24Journal = $florix24Journal ?? [];
$florix24WebhookUrl = $florix24WebhookUrl ?? 'https://berrygo.ru/api/integrations/florix24/order-status';
$settingsSections = $settingsSections ?? ['general' => 'Основные'];
$activeSection = $activeSection ?? 'general';
$sectionUrl = static fn(string $section): string => $section === 'general' ? '/admin/settings' : '/admin/settings/' . $section;
?>
<div class="max-w-5xl space-y-4">
  <?php if (!empty($_GET['message'])): ?>
    <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800"><?= htmlspecialchars((string)$_GET['message']) ?></div>
  <?php endif; ?>
  <?php if (!empty($_GET['error'])): ?>
    <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"><?= htmlspecialchars((string)$_GET['error']) ?></div>
  <?php endif; ?>
  <nav class="flex flex-wrap gap-2 rounded bg-white p-3 shadow" aria-label="Разделы настроек">
    <?php foreach ($settingsSections as $sectionKey => $sectionLabel): ?>
      <?php $isActiveSection = $activeSection === $sectionKey; ?>
      <a href="<?= htmlspecialchars($sectionUrl($sectionKey)) ?>"
         class="rounded-lg px-3 py-2 text-sm font-medium transition <?= $isActiveSection ? 'bg-[#C86052] text-white shadow' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' ?>">
        <?= htmlspecialchars($sectionLabel) ?>
      </a>
    <?php endforeach; ?>
  </nav>

<form action="<?= htmlspecialchars($sectionUrl($activeSection)) ?>" method="post" class="bg-white p-6 rounded shadow space-y-4">
  <?= csrf_field() ?>
  <?php if ($activeSection === 'general'): ?>
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
  <?php endif; ?>

  <?php if ($activeSection === 'pricing'): ?>
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
  <?php endif; ?>

  <?php if ($activeSection === 'preorder'): ?>
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
  <?php endif; ?>

  <?php if ($activeSection === 'payments'): ?>
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
    <fieldset class="rounded-lg border border-gray-200 p-3">
      <legend class="px-2 text-sm font-semibold text-gray-600">Доступные способы оплаты</legend>
      <p class="mb-3 text-xs text-gray-500">Оплата открывается только после статуса «Подтверждён». Здесь включаются способы, которые можно показывать клиенту.</p>
      <div class="grid gap-2 sm:grid-cols-2">
        <?php foreach ([
          'payment_method_online_robokassa_enabled' => 'Онлайн Robokassa',
          'payment_method_cash_on_delivery_enabled' => 'Наличными при доставке',
          'payment_method_cash_pickup_enabled' => 'Наличными при самовывозе',
          'payment_method_card_on_delivery_enabled' => 'Картой при доставке',
          'payment_method_card_pickup_enabled' => 'Картой при самовывозе',
        ] as $paymentSettingKey => $paymentSettingLabel): ?>
          <?php $paymentDefault = $paymentSettingKey === 'payment_method_online_robokassa_enabled' || $paymentSettingKey === 'payment_method_cash_on_delivery_enabled' || $paymentSettingKey === 'payment_method_cash_pickup_enabled' ? '1' : '0'; ?>
          <label class="flex items-center gap-2 rounded border border-gray-200 p-2 text-sm">
            <input type="checkbox" name="<?= htmlspecialchars($paymentSettingKey) ?>" value="1"
                   <?= (($settings[$paymentSettingKey] ?? $paymentDefault) === '1') ? 'checked' : '' ?>
                   class="h-4 w-4 rounded border-gray-300 text-[#C86052] focus:ring-[#C86052]">
            <span><?= htmlspecialchars($paymentSettingLabel) ?></span>
          </label>
        <?php endforeach; ?>
      </div>
    </fieldset>
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
  <?php endif; ?>

  <?php if ($activeSection === 'registration_notifications'): ?>
  <fieldset class="border border-gray-200 rounded-lg p-4 space-y-4">
    <legend class="px-2 text-sm font-semibold text-gray-600">Регистрация</legend>
    <label class="flex items-start gap-3 rounded border border-gray-200 p-3">
      <input type="checkbox" name="registration_phone_verification_enabled" value="1"
             <?= (($settings['registration_phone_verification_enabled'] ?? '1') === '1') ? 'checked' : '' ?>
             class="mt-1 h-4 w-4 rounded border-gray-300 text-[#C86052] focus:ring-[#C86052]">
      <span>
        <span class="block font-medium text-gray-800">Подтверждать телефон при регистрации</span>
        <span class="block text-xs text-gray-500">Если выключено, SMS не отправляется: аккаунт создаётся сразу, email становится обязательным, а продолжить работу можно только после подтверждения email по ссылке.</span>
      </span>
    </label>
    <div>
      <label class="block mb-1">Срок действия ссылки подтверждения email, минут</label>
      <input name="registration_email_verification_ttl_minutes" type="number" min="5" max="1440" step="1"
             value="<?= htmlspecialchars($settings['registration_email_verification_ttl_minutes'] ?? '60') ?>"
             class="w-full border px-2 py-1 rounded">
      <p class="mt-1 text-xs text-gray-500">Используется в аварийном режиме, когда подтверждение телефона выключено.</p>
    </div>
  </fieldset>
  <?php endif; ?>

  <?php if ($activeSection === 'delivery'): ?>
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
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
      <div>
        <label class="block mb-1">OpenRouteService API key</label>
        <input name="openrouteservice_api_key" type="password" autocomplete="new-password"
               placeholder="<?= !empty($settings['openrouteservice_api_key']) ? 'Ключ сохранён' : 'Введите API key' ?>"
               class="w-full border px-2 py-1 rounded">
        <p class="mt-1 text-xs text-gray-500">Для маршрута по дороге через directions/driving-car. Пустое поле не перезаписывает сохранённый ключ.</p>
      </div>
      <div>
        <label class="block mb-1">ORS радиус привязки, м</label>
        <input name="openrouteservice_snap_radius_m" type="number" min="1" max="50000" step="1"
               value="<?= htmlspecialchars($settings['openrouteservice_snap_radius_m'] ?? '2000') ?>"
               class="w-full border px-2 py-1 rounded">
        <p class="mt-1 text-xs text-gray-500">Если ORS пишет «within a radius of 350m», увеличиваем поиск ближайшей автомобильной дороги.</p>
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
    <div class="rounded-lg border border-slate-700 bg-slate-950/40 p-3 space-y-3">
      <div>
        <p class="text-sm font-semibold text-slate-100">Ограничение подсказок DaData</p>
        <p class="mt-1 text-xs text-slate-400">Адреса ищем в круге около Красноярска, чтобы «Ленина, 10» не выбирался молча из другого города. Как на первом сайте: центр + радиус 60 км.</p>
      </div>
      <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <div>
          <label class="block mb-1">Центр поиска DaData: широта</label>
          <input name="delivery_dadata_center_lat" type="number" min="-90" max="90" step="0.000001"
                 value="<?= htmlspecialchars($settings['delivery_dadata_center_lat'] ?? '56.233717') ?>"
                 class="w-full border px-2 py-1 rounded">
        </div>
        <div>
          <label class="block mb-1">Центр поиска DaData: долгота</label>
          <input name="delivery_dadata_center_lng" type="number" min="-180" max="180" step="0.000001"
                 value="<?= htmlspecialchars($settings['delivery_dadata_center_lng'] ?? '92.842600') ?>"
                 class="w-full border px-2 py-1 rounded">
        </div>
        <div>
          <label class="block mb-1">Радиус поиска, м</label>
          <input name="delivery_dadata_radius_m" type="number" min="1000" max="300000" step="1000"
                 value="<?= htmlspecialchars($settings['delivery_dadata_radius_m'] ?? '60000') ?>"
                 class="w-full border px-2 py-1 rounded">
        </div>
        <div>
          <label class="block mb-1">Сколько вариантов показывать</label>
          <input name="delivery_dadata_suggestion_count" type="number" min="1" max="20" step="1"
                 value="<?= htmlspecialchars($settings['delivery_dadata_suggestion_count'] ?? '8') ?>"
                 class="w-full border px-2 py-1 rounded">
        </div>
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

  <fieldset class="delivery-test-panel border rounded-lg p-4 space-y-3" data-delivery-test>
    <legend class="delivery-test-legend px-2 text-sm font-semibold">Проверка стоимости по адресу</legend>
    <p class="delivery-test-muted text-xs">Введите адрес клиента и проверьте, в какую сохранённую тарифную зону он попадает. Если DaData не настроена, можно ввести координаты: 56.010, 92.852.</p>
    <div class="flex flex-col gap-2 sm:flex-row">
      <div class="relative flex-1">
        <input type="text"
               class="delivery-test-input w-full border px-3 py-2 rounded"
               placeholder="Например: Ленина 10 или Красноярск, ул. 9 Мая, 73"
               autocomplete="off"
               data-delivery-test-address>
        <input type="hidden" data-delivery-test-selected-address>
        <input type="hidden" data-delivery-test-selected-lat>
        <input type="hidden" data-delivery-test-selected-lng>
        <div class="delivery-suggestion-list hidden absolute z-30 mt-1 max-h-72 w-full overflow-auto rounded-lg border border-slate-700 bg-slate-950 shadow-2xl" data-delivery-suggestion-list></div>
      </div>
      <button type="button"
              class="delivery-test-button px-4 py-2 rounded inline-flex items-center justify-center text-sm"
              data-delivery-test-button>
        <span class="material-icons-round text-base mr-1">search</span> Проверить
      </button>
    </div>
    <div class="delivery-test-result hidden rounded border px-3 py-2 text-sm" data-delivery-test-result></div>
  </fieldset>

  <fieldset class="border border-gray-200 rounded-lg p-4 space-y-4" data-delivery-tariffs>
    <legend class="px-2 text-sm font-semibold text-gray-600">Тарифные зоны доставки</legend>
    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
      <p class="text-xs text-gray-500">Диапазоны задаются в километрах по автомобильной дороге. Рекомендуем не пересекать зоны: например 0–4 км, 4–6 км, 6–8 км.</p>
      <button type="button"
              class="bg-[#C86052] text-white px-4 py-2 rounded inline-flex items-center text-sm hover:bg-[#B44D47]"
              data-add-delivery-tariff>
        <span class="material-icons-round text-base mr-1">add</span> Добавить вариант
      </button>
    </div>
    <div class="overflow-x-auto">
      <table class="min-w-full bg-white rounded shadow overflow-hidden text-sm">
        <thead class="bg-gray-200 text-gray-700">
          <tr>
            <th class="p-3 text-left font-semibold">Сорт.</th>
            <th class="p-3 text-left font-semibold">От, км</th>
            <th class="p-3 text-left font-semibold">До, км</th>
            <th class="p-3 text-left font-semibold">Стоимость, ₽</th>
            <th class="p-3 text-center font-semibold">Активна</th>
            <th class="p-3 text-center font-semibold">Действие</th>
          </tr>
        </thead>
        <tbody data-delivery-tariff-list>
          <?php
            $zoneRows = $deliveryTariffZones;
            $zoneRows[] = ['id' => '', 'min_km' => '', 'max_km' => '', 'price_rub' => '', 'sort_order' => count($zoneRows) + 1, 'is_active' => 1];
          ?>
          <?php foreach ($zoneRows as $idx => $zone): ?>
            <?php
              $zoneId = (string)($zone['id'] ?? '');
              $isNewZone = $zoneId === '';
              $isActiveZone = (int)($zone['is_active'] ?? 1) === 1;
            ?>
            <tr class="border-b hover:bg-gray-50 transition-all duration-200" data-delivery-tariff-row>
              <td class="p-3">
                <input type="hidden" name="delivery_tariff_zones[id][<?= $idx ?>]" value="<?= htmlspecialchars($zoneId) ?>">
                <input name="delivery_tariff_zones[sort_order][<?= $idx ?>]" type="number" step="1"
                       value="<?= htmlspecialchars((string)($zone['sort_order'] ?? ($idx + 1))) ?>"
                       class="w-20 border px-2 py-1 rounded">
              </td>
              <td class="p-3">
                <input name="delivery_tariff_zones[min_km][<?= $idx ?>]" type="number" min="0" step="0.001"
                       value="<?= htmlspecialchars((string)($zone['min_km'] ?? '')) ?>"
                       class="w-28 border px-2 py-1 rounded">
              </td>
              <td class="p-3">
                <input name="delivery_tariff_zones[max_km][<?= $idx ?>]" type="number" min="0" step="0.001"
                       value="<?= htmlspecialchars((string)($zone['max_km'] ?? '')) ?>"
                       placeholder="без лимита"
                       class="w-28 border px-2 py-1 rounded">
              </td>
              <td class="p-3">
                <input name="delivery_tariff_zones[price_rub][<?= $idx ?>]" type="number" min="0" step="1"
                       value="<?= htmlspecialchars((string)($zone['price_rub'] ?? '')) ?>"
                       class="w-28 border px-2 py-1 rounded">
              </td>
              <td class="p-3 text-center">
                <input name="delivery_tariff_zones[is_active][<?= $idx ?>]" type="checkbox" value="1"
                       <?= $isActiveZone ? 'checked' : '' ?>
                       class="rounded border-gray-300 text-pink-600 focus:ring-pink-500">
              </td>
              <td class="p-3 text-center">
                <?php if (!$isNewZone): ?>
                  <label class="inline-flex items-center gap-1 text-xs text-red-600">
                    <input name="delivery_tariff_zones[delete][<?= $idx ?>]" type="checkbox" value="1" class="rounded border-gray-300 text-red-600 focus:ring-red-500" data-delete-delivery-tariff>
                    удалить
                  </label>
                <?php else: ?>
                  <button type="button" class="inline-flex items-center text-xs text-red-600 hover:underline" data-remove-delivery-tariff>
                    <span class="material-icons-round text-sm mr-1">close</span> убрать
                  </button>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <template data-delivery-tariff-template>
      <tr class="border-b hover:bg-gray-50 transition-all duration-200" data-delivery-tariff-row>
        <td class="p-3">
          <input type="hidden" name="delivery_tariff_zones[id][__INDEX__]" value="">
          <input name="delivery_tariff_zones[sort_order][__INDEX__]" type="number" step="1" value="__SORT__" class="w-20 border px-2 py-1 rounded">
        </td>
        <td class="p-3">
          <input name="delivery_tariff_zones[min_km][__INDEX__]" type="number" min="0" step="0.001" class="w-28 border px-2 py-1 rounded">
        </td>
        <td class="p-3">
          <input name="delivery_tariff_zones[max_km][__INDEX__]" type="number" min="0" step="0.001" placeholder="без лимита" class="w-28 border px-2 py-1 rounded">
        </td>
        <td class="p-3">
          <input name="delivery_tariff_zones[price_rub][__INDEX__]" type="number" min="0" step="1" class="w-28 border px-2 py-1 rounded">
        </td>
        <td class="p-3 text-center">
          <input name="delivery_tariff_zones[is_active][__INDEX__]" type="checkbox" value="1" checked class="rounded border-gray-300 text-pink-600 focus:ring-pink-500">
        </td>
        <td class="p-3 text-center">
          <button type="button" class="inline-flex items-center text-xs text-red-600 hover:underline" data-remove-delivery-tariff>
            <span class="material-icons-round text-sm mr-1">close</span> убрать
          </button>
        </td>
      </tr>
    </template>
  </fieldset>
  <?php endif; ?>

  <?php if ($activeSection === 'integrations'): ?>
  <fieldset class="border border-gray-200 rounded-lg p-4 space-y-3">
    <legend class="px-2 text-sm font-semibold text-gray-600">Входящий API Florix24</legend>
    <?php if (!empty($florix24NewToken)): ?><div class="rounded border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900"><strong>Скопируйте токен сейчас — повторно он не показывается:</strong><code class="mt-2 block break-all rounded bg-white p-2"><?= htmlspecialchars($florix24NewToken) ?></code></div><?php endif; ?>
    <?php if (!empty($florixInboundClient)): ?><dl class="grid gap-2 text-sm sm:grid-cols-2"><div><dt class="text-gray-500">Префикс</dt><dd class="font-mono"><?= htmlspecialchars($florixInboundClient['token_prefix'] ?: '—') ?>…</dd></div><div><dt class="text-gray-500">Статус</dt><dd><?= !empty($florixInboundClient['is_active']) && empty($florixInboundClient['revoked_at']) ? 'Активен' : 'Отключен' ?></dd></div><div><dt class="text-gray-500">Последнее использование</dt><dd><?= htmlspecialchars($florixInboundClient['last_used_at'] ?: '—') ?></dd></div><div><dt class="text-gray-500">Права</dt><dd><?= htmlspecialchars(implode(', ', json_decode($florixInboundClient['permissions'] ?? '[]', true) ?: [])) ?></dd></div></dl><?php else: ?><p class="text-sm text-gray-500">Токен для входящего API ещё не создан.</p><?php endif; ?>
    <div class="flex gap-2"><form method="post" action="/admin/settings/integrations/florix24/inbound-token"><?= csrf_field() ?><button class="rounded bg-[#C86052] px-3 py-2 text-sm font-semibold text-white"><?= empty($florixInboundClient) ? 'Создать токен' : 'Ротировать токен' ?></button></form><?php if (!empty($florixInboundClient) && empty($florixInboundClient['revoked_at'])): ?><form method="post" action="/admin/settings/integrations/florix24/inbound-token/revoke"><?= csrf_field() ?><button class="rounded border border-red-300 px-3 py-2 text-sm font-semibold text-red-700">Отключить ключ</button></form><?php endif; ?></div>
    <?php if (!empty($florixInboundClient)): ?><form method="post" action="/admin/settings/integrations/florix24/network-policy" class="rounded border bg-gray-50 p-3 text-sm"><?= csrf_field() ?><label class="flex items-center gap-2 font-medium"><input type="checkbox" name="ip_check_enabled" value="1" <?= !empty($florixInboundClient['ip_check_enabled']) ? 'checked' : '' ?>> Включить IP-ограничение</label><label class="mt-2 block text-xs text-gray-600">Разрешённые IP/CIDR, по одному на строку<textarea name="ip_allowlist" rows="3" class="mt-1 w-full border p-2 font-mono text-xs"><?= htmlspecialchars($florixInboundClient['allowed_ips'] ?? '') ?></textarea></label><p class="mt-1 text-xs text-gray-500">По умолчанию проверка отключена. `X-Forwarded-For` не используется, пока не настроен trusted proxy.</p><button class="mt-2 rounded border border-gray-300 bg-white px-3 py-1.5 text-xs font-semibold">Сохранить сетевую политику</button></form><?php endif; ?>
  </fieldset>
  <fieldset class="border border-gray-200 rounded-lg p-4 space-y-4" data-florix24-settings>
    <legend class="px-2 text-sm font-semibold text-gray-600">Florix24</legend>

    <div class="rounded-lg border border-blue-200 bg-blue-50 p-4 text-sm text-blue-900">
      <p class="font-semibold">Автоматическая передача заказов</p>
      <p class="mt-1">После включения все новые заказы с сайта, из админ-панели и Telegram будут ставиться в очередь и отправляться во Florix24. Старые заказы не отправляются.</p>
    </div>

    <label class="flex items-start gap-3 rounded-lg border border-gray-200 p-4">
      <input type="checkbox" name="florix24_enabled" value="1"
             <?= (($settings['florix24_enabled'] ?? '0') === '1') ? 'checked' : '' ?>
             class="mt-1 h-4 w-4 rounded border-gray-300 text-[#C86052] focus:ring-[#C86052]">
      <span>
        <span class="block font-semibold text-gray-800">Включить интеграцию с Florix24</span>
        <span class="block text-xs text-gray-500">Момент первого включения станет началом синхронизации. Заказы, созданные раньше, останутся только в BerryGo.</span>
      </span>
    </label>

    <div class="grid gap-4 sm:grid-cols-2">
      <div class="sm:col-span-2">
        <label class="block mb-1 font-medium">Адрес Florix24</label>
        <input name="florix24_base_url" type="url" required
               value="<?= htmlspecialchars($settings['florix24_base_url'] ?? 'https://florix24.ru') ?>"
               class="w-full border px-3 py-2 rounded" placeholder="https://florix24.ru">
      </div>
      <div>
        <label class="block mb-1 font-medium">API-токен</label>
        <input name="florix24_api_token" type="password" autocomplete="new-password"
               value="" class="w-full border px-3 py-2 rounded"
               placeholder="<?= !empty($settings['florix24_api_token']) ? 'Токен сохранён — оставьте пустым, чтобы не менять' : 'Вставьте токен из Florix24' ?>">
        <p class="mt-1 text-xs text-gray-500">Полностью токен после сохранения не отображается.</p>
      </div>
      <div>
        <label class="block mb-1 font-medium">Webhook secret</label>
        <input name="florix24_webhook_secret" type="password" autocomplete="new-password"
               value="" class="w-full border px-3 py-2 rounded"
               placeholder="<?= !empty($settings['florix24_webhook_secret']) ? 'Секрет сохранён — оставьте пустым, чтобы не менять' : 'Вставьте секрет из Florix24' ?>">
        <p class="mt-1 text-xs text-gray-500">Используется для проверки подписи входящих статусов.</p>
      </div>
    </div>

    <div class="grid gap-3 sm:grid-cols-2">
      <?php
      $florixToggles = [
        'florix24_send_orders' => ['Отправлять новые заказы', 'Любой новый заказ BerryGo автоматически уходит во Florix24.'],
        'florix24_send_statuses' => ['Отправлять изменения статусов', 'Передавать new, confirmed, completed и cancelled.'],
        'florix24_receive_statuses' => ['Принимать статусы из Florix24', 'Обновлять основные статусы BerryGo по подписанному webhook.'],
        'florix24_auto_retry' => ['Повторять отправку при ошибке', 'Очередь автоматически повторяет запросы при временной недоступности.'],
      ];
      ?>
      <?php foreach ($florixToggles as $toggleKey => [$toggleLabel, $toggleHint]): ?>
        <label class="flex items-start gap-3 rounded-lg border border-gray-200 p-3">
          <input type="checkbox" name="<?= htmlspecialchars($toggleKey) ?>" value="1"
                 <?= (($settings[$toggleKey] ?? '1') === '1') ? 'checked' : '' ?>
                 class="mt-1 h-4 w-4 rounded border-gray-300 text-[#C86052] focus:ring-[#C86052]">
          <span>
            <span class="block text-sm font-medium text-gray-800"><?= htmlspecialchars($toggleLabel) ?></span>
            <span class="block text-xs text-gray-500"><?= htmlspecialchars($toggleHint) ?></span>
          </span>
        </label>
      <?php endforeach; ?>
    </div>

    <div class="rounded-lg border border-gray-200 bg-gray-50 p-4">
      <label class="block mb-1 text-sm font-semibold text-gray-700">Webhook URL для Florix24</label>
      <div class="flex flex-col gap-2 sm:flex-row">
        <input type="text" readonly data-florix24-webhook-url
               value="<?= htmlspecialchars($florix24WebhookUrl) ?>"
               class="min-w-0 flex-1 border bg-white px-3 py-2 rounded font-mono text-sm">
        <button type="button" data-copy-florix24-webhook
                class="rounded border border-gray-300 bg-white px-4 py-2 text-sm font-medium hover:bg-gray-100">Скопировать</button>
      </div>
      <p class="mt-2 text-xs text-gray-500">Этот адрес укажите в Florix24 в поле адреса отправки статусов.</p>
    </div>

    <div class="flex flex-wrap items-center gap-3">
      <button type="button" data-test-florix24
              class="rounded border border-[#C86052] px-4 py-2 text-sm font-semibold text-[#C86052] hover:bg-red-50">
        Проверить подключение
      </button>
      <span data-florix24-test-result class="text-sm text-gray-600"></span>
    </div>

    <?php if (!empty($settings['florix24_enabled_at'])): ?>
      <p class="text-xs text-gray-500">Синхронизация новых заказов начата: <strong><?= htmlspecialchars($settings['florix24_enabled_at']) ?></strong>.</p>
    <?php endif; ?>
  </fieldset>
  <?php endif; ?>

  <?php if ($activeSection === 'theme' && !empty($themeColors)): ?>
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
  <button type="submit"
          class="accent-gradient text-white px-4 py-2 rounded accent-focus transition">
    Сохранить раздел
  </button>
</form>
<?php if ($activeSection === 'integrations'): ?>
  <section class="rounded bg-white p-5 shadow space-y-3">
    <div><h2 class="text-lg font-semibold text-gray-800">Входящий журнал Florix24</h2><p class="text-xs text-gray-500">Входящие API-запросы, без токенов и секретов. Всего: <?= (int)($florixInboundJournal['total'] ?? 0) ?></p></div>
    <form method="get" class="grid gap-2 sm:grid-cols-4"><input type="hidden" name="" value=""><input name="inbound_order" value="<?= htmlspecialchars($_GET['inbound_order'] ?? '') ?>" placeholder="Внешний заказ" class="border p-2 rounded text-sm"><input name="inbound_correlation" value="<?= htmlspecialchars($_GET['inbound_correlation'] ?? '') ?>" placeholder="Correlation ID" class="border p-2 rounded text-sm"><select name="inbound_status" class="border p-2 rounded text-sm"><option value="">Все HTTP-коды</option><?php foreach ([200,401,403,422,429,500] as $status): ?><option value="<?= $status ?>" <?= ((string)($_GET['inbound_status'] ?? '') === (string)$status) ? 'selected' : '' ?>><?= $status ?></option><?php endforeach; ?></select><button class="rounded border px-3 py-2 text-sm">Фильтровать</button></form>
    <div class="overflow-x-auto"><table class="min-w-full text-xs"><thead><tr class="border-b text-left text-gray-500"><th class="p-2">Дата</th><th class="p-2">Endpoint</th><th class="p-2">HTTP</th><th class="p-2">Заказ</th><th class="p-2">Партнёр/баллы</th><th class="p-2">Correlation ID</th><th class="p-2">Время</th><th class="p-2">Ошибка</th></tr></thead><tbody><?php foreach (($florixInboundJournal['rows'] ?? []) as $entry): ?><tr class="border-b"><td class="p-2 whitespace-nowrap"><?= htmlspecialchars((string)$entry['created_at']) ?></td><td class="p-2 font-mono"><?= htmlspecialchars((string)$entry['endpoint']) ?></td><td class="p-2"><?= (int)$entry['http_status'] ?></td><td class="p-2"><?= htmlspecialchars((string)($entry['external_order_id'] ?: '—')) ?></td><td class="p-2"><?= $entry['partner_user_id'] ? '#'.(int)$entry['partner_user_id'].' / '.(int)$entry['points_used'] : (int)$entry['points_used'] ?></td><td class="p-2 font-mono"><?= htmlspecialchars((string)$entry['correlation_id']) ?></td><td class="p-2"><?= (int)$entry['processing_ms'] ?> ms</td><td class="p-2 text-red-700"><?= htmlspecialchars((string)($entry['error_code'] ?: '—')) ?></td></tr><?php endforeach; ?><?php if (empty($florixInboundJournal['rows'])): ?><tr><td colspan="8" class="p-4 text-center text-gray-500">Запросов пока нет.</td></tr><?php endif; ?></tbody></table></div>
  </section>
  <section class="rounded bg-white p-5 shadow space-y-3">
    <div class="flex flex-wrap items-center justify-between gap-2">
      <div>
        <h2 class="text-lg font-semibold text-gray-800">Журнал Florix24</h2>
        <p class="text-xs text-gray-500">Последние исходящие и входящие события. Токены и секреты в журнал не записываются.</p>
      </div>
    </div>
    <div class="overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead>
          <tr class="border-b text-left text-xs uppercase tracking-wide text-gray-500">
            <th class="px-2 py-2">Дата</th>
            <th class="px-2 py-2">Заказ</th>
            <th class="px-2 py-2">Событие</th>
            <th class="px-2 py-2">Направление</th>
            <th class="px-2 py-2">Статус</th>
            <th class="px-2 py-2">Попытки</th>
            <th class="px-2 py-2">Ошибка</th>
            <th class="px-2 py-2"></th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$florix24Journal): ?>
            <tr><td colspan="8" class="px-2 py-5 text-center text-gray-500">Событий пока нет.</td></tr>
          <?php else: ?>
            <?php foreach ($florix24Journal as $event): ?>
              <?php
                $status = (string)($event['status'] ?? '');
                $statusClass = in_array($status, ['sent', 'processed'], true)
                  ? 'bg-emerald-100 text-emerald-800'
                  : ($status === 'conflict' ? 'bg-amber-100 text-amber-800' : (in_array($status, ['error'], true) ? 'bg-red-100 text-red-800' : 'bg-gray-100 text-gray-700'));
              ?>
              <tr class="border-b align-top">
                <td class="px-2 py-2 whitespace-nowrap"><?= htmlspecialchars((string)($event['created_at'] ?? '')) ?></td>
                <td class="px-2 py-2"><?= (int)($event['entity_id'] ?? 0) > 0 ? '#' . (int)$event['entity_id'] : '—' ?></td>
                <td class="px-2 py-2 font-mono text-xs"><?= htmlspecialchars((string)($event['event_type'] ?? '')) ?></td>
                <td class="px-2 py-2"><?= (($event['direction'] ?? '') === 'incoming') ? 'Florix24 → BerryGo' : 'BerryGo → Florix24' ?></td>
                <td class="px-2 py-2"><span class="inline-flex rounded-full px-2 py-1 text-xs font-semibold <?= $statusClass ?>"><?= htmlspecialchars($status) ?></span></td>
                <td class="px-2 py-2 text-center"><?= (int)($event['attempts'] ?? 0) ?></td>
                <td class="max-w-xs px-2 py-2 text-xs text-red-700 break-words"><?= htmlspecialchars((string)($event['last_error'] ?? '')) ?></td>
                <td class="px-2 py-2">
                  <?php if (($event['direction'] ?? '') === 'outgoing' && in_array($status, ['error', 'conflict'], true)): ?>
                    <form method="post" action="/admin/settings/integrations/florix24/retry">
                      <?= csrf_field() ?>
                      <input type="hidden" name="event_id" value="<?= (int)$event['id'] ?>">
                      <button type="submit" class="whitespace-nowrap rounded border border-gray-300 px-2 py-1 text-xs hover:bg-gray-100">Повторить</button>
                    </form>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>

<script>
(function () {
  const root = document.querySelector('[data-florix24-settings]');
  if (!root) return;
  const copyButton = root.querySelector('[data-copy-florix24-webhook]');
  const webhookInput = root.querySelector('[data-florix24-webhook-url]');
  const testButton = root.querySelector('[data-test-florix24]');
  const result = root.querySelector('[data-florix24-test-result]');
  const form = root.closest('form');

  if (copyButton && webhookInput) {
    copyButton.addEventListener('click', async function () {
      try {
        await navigator.clipboard.writeText(webhookInput.value);
        copyButton.textContent = 'Скопировано';
        setTimeout(() => { copyButton.textContent = 'Скопировать'; }, 1500);
      } catch (e) {
        webhookInput.select();
        document.execCommand('copy');
      }
    });
  }

  if (testButton && form) {
    testButton.addEventListener('click', async function () {
      testButton.disabled = true;
      if (result) {
        result.textContent = 'Проверяем…';
        result.className = 'text-sm text-gray-600';
      }
      const body = new URLSearchParams();
      const csrf = form.querySelector('input[name="csrf_token"]');
      const baseUrl = form.querySelector('input[name="florix24_base_url"]');
      const token = form.querySelector('input[name="florix24_api_token"]');
      if (csrf) body.set('csrf_token', csrf.value);
      if (baseUrl) body.set('base_url', baseUrl.value);
      if (token) body.set('api_token', token.value);
      try {
        const response = await fetch('/admin/settings/integrations/florix24/test', {
          method: 'POST',
          headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
          body: body.toString(),
          credentials: 'same-origin'
        });
        const data = await response.json();
        if (result) {
          result.textContent = data.message || (data.ok ? 'Подключение установлено.' : 'Ошибка подключения.');
          result.className = data.ok ? 'text-sm font-medium text-emerald-700' : 'text-sm font-medium text-red-700';
        }
      } catch (e) {
        if (result) {
          result.textContent = 'Не удалось выполнить проверку: ' + (e.message || 'ошибка сети');
          result.className = 'text-sm font-medium text-red-700';
        }
      } finally {
        testButton.disabled = false;
      }
    });
  }
})();
</script>
<?php endif; ?>
<?php if ($activeSection === 'delivery'): ?>

<style>
  [data-delivery-test].delivery-test-panel {
    background: #111827;
    border-color: #374151;
    color: #e5e7eb;
    box-shadow: 0 12px 30px rgba(0, 0, 0, .25);
  }
  [data-delivery-test] .delivery-test-legend {
    color: #f9fafb;
    background: #111827;
  }
  [data-delivery-test] .delivery-test-muted {
    color: #9ca3af;
  }
  [data-delivery-test] .delivery-test-input {
    background: #0b1220;
    border-color: #4b5563;
    color: #f9fafb;
    outline: none;
  }
  [data-delivery-test] .delivery-test-input::placeholder {
    color: #6b7280;
  }
  [data-delivery-test] .delivery-test-input:focus {
    border-color: #fb7185;
    box-shadow: 0 0 0 3px rgba(251, 113, 133, .16);
  }
  [data-delivery-test] .delivery-test-button {
    background: linear-gradient(135deg, #fb7185, #c86052);
    color: #fff;
    border: 1px solid rgba(255, 255, 255, .08);
  }
  [data-delivery-test] .delivery-test-button:hover {
    filter: brightness(1.07);
  }
  [data-delivery-test] .delivery-test-button:disabled {
    opacity: .65;
    cursor: wait;
  }
  [data-delivery-test] .delivery-test-result {
    background: #0b1220;
    border-color: #374151;
    color: #e5e7eb;
  }
  [data-delivery-test] .delivery-test-result--ok {
    border-color: rgba(34, 197, 94, .45);
    box-shadow: inset 4px 0 0 rgba(34, 197, 94, .75);
  }
  [data-delivery-test] .delivery-test-result--error {
    border-color: rgba(248, 113, 113, .55);
    box-shadow: inset 4px 0 0 rgba(248, 113, 113, .9);
  }
  [data-delivery-test] .delivery-test-price {
    color: #ffffff;
    font-size: 1.35rem;
    line-height: 1.2;
  }
  [data-delivery-test] .delivery-test-warning {
    margin-top: .6rem;
    padding: .65rem .75rem;
    border-radius: .65rem;
    border: 1px solid rgba(251, 191, 36, .35);
    background: rgba(251, 191, 36, .10);
    color: #fde68a;
  }
  [data-delivery-test] .delivery-test-details {
    border-color: #374151;
    background: rgba(15, 23, 42, .72);
  }
  [data-delivery-test] .delivery-test-summary {
    color: #f3f4f6;
  }
  [data-delivery-test] .delivery-test-diagnostics-table th,
  [data-delivery-test] .delivery-test-diagnostics-table td {
    border-bottom: 1px solid rgba(75, 85, 99, .65);
    padding: .45rem .35rem;
    vertical-align: top;
  }
  [data-delivery-test] .delivery-test-diagnostics-table th {
    color: #9ca3af;
    font-weight: 600;
    white-space: nowrap;
  }
  [data-delivery-test] .delivery-test-diagnostics-table td {
    color: #e5e7eb;
  }
  [data-delivery-test] pre {
    background: #020617;
    color: #d1d5db;
    border: 1px solid #1f2937;
    border-radius: .5rem;
    padding: .75rem;
  }
  [data-delivery-test] .delivery-suggestion-item {
    display: flex;
    width: 100%;
    gap: .6rem;
    padding: .7rem .85rem;
    border: 0;
    border-bottom: 1px solid rgba(51, 65, 85, .85);
    background: transparent;
    color: #e5e7eb;
    text-align: left;
    cursor: pointer;
  }
  [data-delivery-test] .delivery-suggestion-item:hover,
  [data-delivery-test] .delivery-suggestion-item:focus {
    background: rgba(190, 91, 77, .18);
    outline: none;
  }
  [data-delivery-test] .delivery-suggestion-main {
    display: block;
    font-weight: 700;
  }
  [data-delivery-test] .delivery-suggestion-meta {
    display: block;
    margin-top: .18rem;
    color: #94a3b8;
    font-size: .78rem;
    line-height: 1.25;
  }
</style>
<script>
(function () {
  const root = document.querySelector('[data-delivery-tariffs]');
  const testRoot = document.querySelector('[data-delivery-test]');
  if (!root && !testRoot) return;

  const list = root ? root.querySelector('[data-delivery-tariff-list]') : null;
  const template = root ? root.querySelector('[data-delivery-tariff-template]') : null;
  let nextIndex = list ? list.querySelectorAll('[data-delivery-tariff-row]').length : 0;

  function addRow() {
    if (!list || !template) return;
    const sortOrder = list.querySelectorAll('[data-delivery-tariff-row]').length + 1;
    const html = template.innerHTML.replaceAll('__INDEX__', String(nextIndex)).replaceAll('__SORT__', String(sortOrder));
    const wrapper = document.createElement('tbody');
    wrapper.innerHTML = html.trim();
    const row = wrapper.firstElementChild;
    if (!row) return;
    list.appendChild(row);
    nextIndex += 1;
    const firstInput = row.querySelector('input[name*="[min_km]"]');
    if (firstInput) firstInput.focus();
  }

  function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, function (char) {
      return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'}[char];
    });
  }

  function setTestResult(message, isError) {
    if (!testRoot) return;
    const result = testRoot.querySelector('[data-delivery-test-result]');
    if (!result) return;
    result.classList.remove('hidden', 'delivery-test-result--ok', 'delivery-test-result--error');
    result.classList.add(isError ? 'delivery-test-result--error' : 'delivery-test-result--ok');
    result.innerHTML = message;
  }

  function getSuggestionElements() {
    if (!testRoot) return {};
    return {
      input: testRoot.querySelector('[data-delivery-test-address]'),
      list: testRoot.querySelector('[data-delivery-suggestion-list]'),
      selectedAddress: testRoot.querySelector('[data-delivery-test-selected-address]'),
      selectedLat: testRoot.querySelector('[data-delivery-test-selected-lat]'),
      selectedLng: testRoot.querySelector('[data-delivery-test-selected-lng]'),
    };
  }

  function clearSelectedSuggestion() {
    const els = getSuggestionElements();
    if (els.selectedAddress) els.selectedAddress.value = '';
    if (els.selectedLat) els.selectedLat.value = '';
    if (els.selectedLng) els.selectedLng.value = '';
  }

  function hideAddressSuggestions() {
    const {list} = getSuggestionElements();
    if (!list) return;
    list.innerHTML = '';
    list.classList.add('hidden');
  }

  function renderAddressSuggestions(items, message) {
    const {input, list, selectedAddress, selectedLat, selectedLng} = getSuggestionElements();
    if (!input || !list) return;

    list.innerHTML = '';
    if (!items.length) {
      if (message) {
        const empty = document.createElement('div');
        empty.className = 'px-3 py-2 text-xs text-slate-400';
        empty.textContent = message;
        list.appendChild(empty);
        list.classList.remove('hidden');
      } else {
        list.classList.add('hidden');
      }
      return;
    }

    items.forEach((item) => {
      const row = document.createElement('button');
      row.type = 'button';
      row.className = 'delivery-suggestion-item';
      const meta = [
        item.city || '',
        item.district || '',
        item.qc_geo !== undefined && item.qc_geo !== null ? `qc_geo=${item.qc_geo}` : '',
        item.distance_from_center_km ? `${item.distance_from_center_km} км от центра поиска` : '',
      ].filter(Boolean).join(' · ');
      row.innerHTML = `
        <span class="material-icons-round text-base text-[#C86052]">location_on</span>
        <span class="flex-1">
          <span class="delivery-suggestion-main">${escapeHtml(item.label || item.value || '')}</span>
          <span class="delivery-suggestion-meta">${escapeHtml(meta)}</span>
        </span>
      `;
      row.addEventListener('click', () => {
        const chosenAddress = item.value || item.label || item.unrestricted_value || '';
        input.value = chosenAddress;
        if (selectedAddress) selectedAddress.value = chosenAddress;
        if (selectedLat) selectedLat.value = item.lat || '';
        if (selectedLng) selectedLng.value = item.lng || '';
        hideAddressSuggestions();
        setTestResult(`Адрес выбран: ${escapeHtml(chosenAddress)}. Теперь можно проверить тариф.`, false);
      });
      list.appendChild(row);
    });
    list.classList.remove('hidden');
  }

  async function fetchAddressSuggestions(query) {
    if (!query || query.trim().length < 3) return [];
    const url = '/admin/settings/delivery/address-suggestions?query=' + encodeURIComponent(query.trim());
    const response = await fetch(url, {credentials: 'same-origin', headers: {'X-Requested-With': 'XMLHttpRequest'}});
    const text = await response.text();
    let data = null;
    try {
      data = JSON.parse(text);
    } catch (e) {
      throw new Error('Сервер подсказок вернул не JSON: ' + text.slice(0, 160));
    }
    if (!response.ok || !data.ok) {
      throw new Error(data.message || 'Не удалось получить подсказки адреса.');
    }
    return data.suggestions || [];
  }

  function formatDiagnosticValue(value) {
    if (value === null || value === undefined || value === '') return '—';
    if (Array.isArray(value)) return '[' + value.map(formatDiagnosticValue).join(', ') + ']';
    if (typeof value === 'object') return JSON.stringify(value, null, 2);
    if (typeof value === 'boolean') return value ? 'да' : 'нет';
    return String(value);
  }

  function diagnosticRow(label, value) {
    return `<tr class="border-b border-gray-200 align-top"><td class="py-1 pr-3 font-medium text-gray-600 whitespace-nowrap">${escapeHtml(label)}</td><td class="py-1 text-gray-800"><code class="break-all whitespace-pre-wrap">${escapeHtml(formatDiagnosticValue(value))}</code></td></tr>`;
  }

  function serviceStatusLine(title, item) {
    if (!item) return diagnosticRow(title, 'нет данных');
    if (item.attempted === false) {
      return diagnosticRow(title, item.skipped_reason || 'не запускался');
    }
    const ok = item.ok === true ? 'OK' : 'ОШИБКА';
    const http = item.http_code ? `HTTP ${item.http_code}` : 'HTTP —';
    const time = item.total_time_ms ? `${item.total_time_ms} мс` : 'время —';
    const details = [ok, http, time, item.decoded_error || item.curl_error || item.json_error || ''].filter(Boolean).join(' | ');
    return diagnosticRow(title, details);
  }

  function buildDeliveryDiagnostics(data) {
    const d = data.diagnostics || {};
    const dadata = d.dadata || {};
    const ors = d.openrouteservice || {};
    const rows = [];

    rows.push(diagnosticRow('Исходный адрес', data.requested_address || ''));
    rows.push(diagnosticRow('Нормализованный адрес', data.address || ''));
    rows.push(diagnosticRow('Координаты магазина', data.store ? `${data.store.lat}, ${data.store.lng}` : ''));
    rows.push(diagnosticRow('Координаты клиента', data.destination ? `${data.destination.lat}, ${data.destination.lng}` : `${data.lat}, ${data.lng}`));
    rows.push(diagnosticRow('Порядок координат ORS', ors.coordinate_order || '[longitude, latitude]'));
    rows.push(diagnosticRow('ORS радиус привязки', ors.snap_radius_m ? `${ors.snap_radius_m} м` : ''));
    rows.push(diagnosticRow('Координаты, отправленные в ORS', ors.request_payload ? ors.request_payload.coordinates : ''));
    rows.push(diagnosticRow('ORS radiuses payload', ors.request_payload ? ors.request_payload.radiuses : ''));
    rows.push(diagnosticRow('ORS подсказка', ors.routing_hint || ''));
    rows.push(diagnosticRow('Источник DaData', dadata.source || ''));
    rows.push(serviceStatusLine('DaData clean/address', dadata.clean));
    rows.push(diagnosticRow('DaData clean qc_geo', dadata.clean ? dadata.clean.qc_geo : ''));
    rows.push(diagnosticRow('DaData clean адрес', dadata.clean ? dadata.clean.result_address : ''));
    rows.push(serviceStatusLine('DaData suggest/address', dadata.suggest));
    rows.push(diagnosticRow('DaData suggest qc_geo', dadata.suggest ? dadata.suggest.qc_geo : ''));
    rows.push(diagnosticRow('DaData suggest адрес', dadata.suggest ? (dadata.suggest.value || dadata.suggest.unrestricted_value) : ''));
    rows.push(diagnosticRow('ORS endpoint', ors.endpoint || ''));
    rows.push(diagnosticRow('ORS profile/format', [ors.profile || '', ors.format || ''].filter(Boolean).join('/')));
    rows.push(serviceStatusLine('OpenRouteService', ors));
    rows.push(diagnosticRow('ORS decoded_error', ors.decoded_error || ''));
    rows.push(diagnosticRow('ORS body_preview', ors.body_preview || ''));
    rows.push(diagnosticRow('Fallback', ors.fallback ? `${ors.fallback.used ? 'использован' : 'не использован'}: ${ors.fallback.reason || ''}` : ''));
    rows.push(diagnosticRow('Источник цены', data.pricing_source || ''));

    const rawJson = JSON.stringify(d, null, 2);
    return `
      <details class="delivery-test-details mt-3 rounded border p-2" open>
        <summary class="delivery-test-summary cursor-pointer font-semibold">Диагностика по шагам</summary>
        <div class="mt-2 overflow-x-auto">
          <table class="delivery-test-diagnostics-table min-w-full text-xs">${rows.join('')}</table>
        </div>
      </details>
      <details class="delivery-test-details mt-2 rounded border p-2">
        <summary class="delivery-test-summary cursor-pointer font-semibold">Полный diagnostics JSON</summary>
        <pre class="mt-2 max-h-96 overflow-auto whitespace-pre-wrap break-words text-xs">${escapeHtml(rawJson)}</pre>
      </details>
    `;
  }

  async function testDeliveryAddress() {
    if (!testRoot) return;
    const input = testRoot.querySelector('[data-delivery-test-address]');
    const button = testRoot.querySelector('[data-delivery-test-button]');
    const address = input ? input.value.trim() : '';
    if (!address) {
      setTestResult('Введите адрес для проверки.', true);
      return;
    }

    if (button) button.disabled = true;
    setTestResult('Проверяем адрес и тариф…', false);

    try {
      const selectedAddress = testRoot.querySelector('[data-delivery-test-selected-address]');
      const selectedLat = testRoot.querySelector('[data-delivery-test-selected-lat]');
      const selectedLng = testRoot.querySelector('[data-delivery-test-selected-lng]');
      const looksLikeCoords = /^\s*-?\d+(?:[\.,]\d+)?\s*[,; ]\s*-?\d+(?:[\.,]\d+)?\s*$/.test(address);
      if (!looksLikeCoords && (!selectedAddress?.value || !selectedLat?.value || !selectedLng?.value)) {
        const suggestions = await fetchAddressSuggestions(address);
        renderAddressSuggestions(suggestions, 'DaData не вернула подходящих адресов в заданном радиусе.');
        if (suggestions.length > 0) {
          setTestResult(`Найдено вариантов: ${suggestions.length}. Выберите точный адрес из списка, чтобы не подставить первый попавшийся.`, true);
          return;
        }
      }

      const body = new URLSearchParams();
      body.set('address', address);
      if (selectedAddress?.value && selectedLat?.value && selectedLng?.value) {
        body.set('selected_address', selectedAddress.value);
        body.set('selected_lat', selectedLat.value);
        body.set('selected_lng', selectedLng.value);
      }
      const response = await fetch('/admin/settings/delivery/test-tariff', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
        credentials: 'same-origin',
        body: body.toString(),
      });
      const text = await response.text();
      let data = null;
      try {
        data = JSON.parse(text);
      } catch (e) {
        throw new Error('Сервер вернул не JSON. Проверьте маршрут /admin/settings/delivery/test-tariff. Ответ: ' + text.slice(0, 160));
      }
      if (!response.ok || !data.ok) {
        throw new Error(data.message || 'Не удалось проверить адрес.');
      }

      const zone = data.zone
        ? `Зона: ${escapeHtml(data.zone.min_km)}–${escapeHtml(data.zone.max_km ?? '∞')} км.`
        : 'Фиксированная зона не найдена.';
      const distanceExtra = data.duration_min
        ? `, время: ${escapeHtml(data.duration_min)} мин.`
        : '';
      setTestResult(
        `<strong>${escapeHtml(data.price_rub)} ₽</strong><br>` +
        `${zone}<br>` +
        `Расстояние: ${escapeHtml(data.distance_km)} км (${escapeHtml(data.distance_source)}${distanceExtra}).<br>` +
        `${escapeHtml(data.message)}<br>` +
        `<span class="text-xs">Адрес: ${escapeHtml(data.address)}. ${escapeHtml(data.distance_note)}</span>` +
        buildDeliveryDiagnostics(data),
        false
      );
    } catch (error) {
      setTestResult(escapeHtml(error.message || 'Не удалось проверить адрес.'), true);
    } finally {
      if (button) button.disabled = false;
    }
  }

  if (testRoot) {
    const button = testRoot.querySelector('[data-delivery-test-button]');
    const input = testRoot.querySelector('[data-delivery-test-address]');
    if (button) button.addEventListener('click', testDeliveryAddress);
    if (input) {
      let timer = null;
      let requestId = 0;
      input.addEventListener('input', function () {
        clearSelectedSuggestion();
        clearTimeout(timer);
        const query = input.value.trim();
        if (query.length < 3) {
          hideAddressSuggestions();
          return;
        }
        const currentRequest = ++requestId;
        timer = setTimeout(async function () {
          try {
            const suggestions = await fetchAddressSuggestions(query);
            if (currentRequest === requestId) {
              renderAddressSuggestions(suggestions, suggestions.length ? '' : 'Нет адресов в радиусе поиска.');
            }
          } catch (error) {
            if (currentRequest === requestId) {
              renderAddressSuggestions([], error.message || 'Не удалось получить подсказки.');
            }
          }
        }, 250);
      });
      input.addEventListener('keydown', function (event) {
        if (event.key === 'Enter') {
          event.preventDefault();
          testDeliveryAddress();
        }
      });
      document.addEventListener('click', function (event) {
        if (!testRoot.contains(event.target)) {
          hideAddressSuggestions();
        }
      });
    }
  }

  if (root) {
    root.addEventListener('click', function (event) {
      const removeButton = event.target.closest('[data-remove-delivery-tariff]');
      if (removeButton) {
        const row = removeButton.closest('[data-delivery-tariff-row]');
        if (row) row.remove();
        return;
      }

      if (event.target.closest('[data-add-delivery-tariff]')) {
        addRow();
      }
    });

    root.addEventListener('change', function (event) {
      const deleteCheckbox = event.target.closest('[data-delete-delivery-tariff]');
      if (!deleteCheckbox) return;
      const row = deleteCheckbox.closest('[data-delivery-tariff-row]');
      if (!row) return;
      row.classList.toggle('opacity-50', deleteCheckbox.checked);
      row.classList.toggle('bg-red-50', deleteCheckbox.checked);
    });
  }

})();
</script>
<?php endif; ?>

</div>
