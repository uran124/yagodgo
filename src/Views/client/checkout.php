<?php
/**
 * @var array        $groups           // сгруппированные по дате доставки товары
 * @var float        $subtotal         // исходная сумма без учёта баллов
 * @var int          $pointsBalance    // сколько баллов (клубничек) есть у пользователя
 * @var int          $pointsToUse      // сколько баллов автоматически списывается
 * @var string|null  $couponCode       // введённый промокод
 * @var array|null   $couponInfo       // информация о применённом купоне
 * @var float        $finalTotal       // итоговая сумма после всех скидок
 * @var string|null  $userName         // имя пользователя (для вывода в шапке)
 * @var string       $today            // сегодняшняя дата в формате Y-m-d
 * @var string       $address          // текущий адрес доставки пользователя
 * @var string|null  $couponError      // сообщение об ошибке купона
 * @var array        $slots            // доступные временные слоты
 */

// Подставляем значения по умолчанию, чтобы не было «undefined variable»
$groups          = $groups          ?? [];
$subtotal        = $subtotal        ?? 0.0;
$pointsBalance   = $pointsBalance   ?? 0;
$pointsToUse     = $pointsToUse     ?? 0;
$couponCode      = $couponCode      ?? '';
$couponInfo      = $couponInfo      ?? null;
$couponPoints    = $couponPoints    ?? 0;
$discountPercent = $discountPercent ?? 0.0;
$finalTotal      = $finalTotal      ?? ($subtotal - $pointsToUse);
$userName        = $userName        ?? null;
$today           = $today           ?? date('Y-m-d');
$address         = $address         ?? '';
$couponError     = $couponError     ?? null;
$slots           = $slots           ?? [];
?>

<main class="bg-gradient-to-br from-orange-50 via-white to-pink-50 min-h-screen pb-24">

  <div class="px-4 space-y-6">

    <!-- Если корзина пуста -->
    <?php if (empty($groups)): ?>
      <div class="bg-white rounded-3xl shadow-lg p-12 text-center">
        <div class="w-20 h-20 bg-gradient-to-br from-gray-100 to-gray-200 rounded-2xl flex items-center justify-center mx-auto mb-6">
          <span class="material-icons-round text-4xl text-gray-400">shopping_cart</span>
        </div>
        <h3 class="text-xl font-semibold text-gray-600 mb-3">Корзина пуста</h3>
        <p class="text-gray-500 mb-6">Добавьте товары, чтобы оформить заказ</p>
        <a href="/catalog"
           class="inline-flex items-center space-x-3 bg-gradient-to-r from-red-500 to-pink-500 text-white px-8 py-4 rounded-2xl font-semibold hover:shadow-xl hover:scale-105 transition-all">
          <span class="material-icons-round">store</span>
          <span>Перейти в каталог</span>
        </a>
      </div>

    <?php else: ?>
      <?php
        // Если контроллер не передал subtotal, можно его пересчитать здесь:
        if (!isset($subtotal) || $subtotal === 0.0) {
          $calc = 0.0;
          foreach ($groups as $dateKey => $block) {
            foreach ($block as $it) {
              $calc += ($it['quantity'] * $it['unit_price']);
            }
          }
          $subtotal = $calc;
        }
      ?>

      <form action="/checkout" method="post" class="space-y-6">

        <!-- Перебираем каждый «заказ» (группу товаров) по дате -->
        <?php foreach ($groups as $dateKey => $block): ?>
          <?php
            // Определяем читабельную метку даты
            if ($dateKey === 'on_demand' || $dateKey === (defined('PLACEHOLDER_DATE') ? PLACEHOLDER_DATE : '2025-05-15')) {
              $label = 'Ближайшая возможная дата';
              $emoji = '📦';
            } elseif ($dateKey === $today) {
              $label = 'Сегодня';
              $emoji = '🚀';
            } else {
              $label = date('d.m.Y', strtotime($dateKey));
              $emoji = '📅';
            }
          ?>
          
          <div class="bg-white rounded-3xl shadow-lg overflow-hidden">
            <!-- Заголовок заказа -->
            <div class="bg-gradient-to-r from-emerald-50 to-teal-50 p-6 border-b border-emerald-100">
              <h3 class="text-lg font-semibold text-gray-800 mb-4">
                <?= $emoji ?> Заказ (<?= $label ?>)
              </h3>
              
              <!-- Выбор времени доставки -->
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-3">
                  <span class="material-icons-round text-sm mr-1 align-middle">schedule</span>
                  Время получения (<?= $label ?>)
                </label>
                <div class="relative">
                  <select name="slot_id[<?= htmlspecialchars($dateKey) ?>]"
                          required
                          class="w-full border-2 border-gray-200 rounded-2xl px-4 py-3 pr-10 focus:ring-2 focus:ring-red-500 focus:border-red-500 outline-none transition-all bg-white">
                    <?php foreach ($slots as $slot): ?>
                      <?php $value = sprintf('%02d-%02d', (int)substr($slot['time_from'], 0, 2), (int)substr($slot['time_to'], 0, 2)); ?>
                      <option value="<?= $value ?>">
                        <?= htmlspecialchars($slot['time_from'] . ' - ' . $slot['time_to']) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                  <span class="material-icons-round absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 pointer-events-none">expand_more</span>
                </div>
              </div>
            </div>

            <!-- Список товаров -->
            <div class="p-6">
              <div class="space-y-4">
                <?php $orderSum = 0; ?>
                <?php foreach ($block as $it): ?>
                  <?php
                    $lineCost = $it['quantity'] * $it['unit_price'];
                    $orderSum += $lineCost;
                  ?>
                  <div class="flex items-center justify-between p-4 bg-gray-50 rounded-2xl">
                    <div class="flex-1">
                      <div class="font-medium text-gray-800">
                        <?= htmlspecialchars($it['product']) ?>
                        <?php if (!empty($it['variety'])): ?>
                          <span class="text-gray-600"><?= htmlspecialchars($it['variety']) ?></span>
                        <?php endif; ?>
                      </div>
                      <div class="text-sm text-gray-500 mt-1">
                        Количество: <?= htmlspecialchars($it['quantity']) ?>
                      </div>
                    </div>
                    <div class="text-right">
                      <div class="font-semibold text-gray-800">
                        <?= number_format($lineCost, 0, '.', ' ') ?> ₽
                      </div>
                    </div>
                    
                    <!-- Скрытые поля для передачи в POST -->
                    <input type="hidden"
                           name="items[<?= htmlspecialchars($dateKey) ?>][<?= (int)$it['product_id'] ?>][quantity]"
                           value="<?= htmlspecialchars($it['quantity']) ?>">
                    <input type="hidden"
                           name="items[<?= htmlspecialchars($dateKey) ?>][<?= (int)$it['product_id'] ?>][unit_price]"
                           value="<?= htmlspecialchars($it['unit_price']) ?>">
                  </div>
                <?php endforeach; ?>

                <!-- Итого по заказу -->
                <div class="flex justify-between items-center pt-4 border-t border-gray-200">
                  <span class="font-semibold text-gray-800">Итого по заказу:</span>
                  <span class="font-bold text-xl text-gray-800">
                    <?= number_format($orderSum, 0, '.', ' ') ?> ₽
                  </span>
                </div>
              </div>
            </div>
          </div>
        <?php endforeach; ?>

        <!-- Адрес доставки -->
        <div class="bg-white rounded-3xl shadow-lg p-6">
          <h3 class="text-lg font-semibold text-gray-800 mb-4">
            <span class="material-icons-round text-lg mr-2 align-middle">location_on</span>
            Адрес доставки
          </h3>
          <div class="space-y-2">
            <select name="address_id[default]" id="addressSelect" class="w-full border-2 border-gray-200 rounded-2xl px-4 py-3 focus:ring-2 focus:ring-red-500 focus:border-red-500 outline-none">
              <?php foreach ($addresses as $a): ?>
                <option value="<?= $a['id'] ?>" <?= $a['is_primary'] ? 'selected' : '' ?>><?= htmlspecialchars($a['street']) ?> (<?= htmlspecialchars($a['recipient_name']) ?>)</option>
              <?php endforeach; ?>
              <option value="new">Другой адрес</option>
            </select>
            <div id="newAddressBlock" class="space-y-2 hidden">
              <input type="text" name="new_address" placeholder="Адрес" class="w-full border-2 border-gray-200 rounded-2xl px-4 py-3 focus:ring-2 focus:ring-red-500 focus:border-red-500 outline-none">
              <input type="text" name="recipient_name" value="<?= htmlspecialchars($userName) ?>" placeholder="Имя получателя" class="w-full border-2 border-gray-200 rounded-2xl px-4 py-3 focus:ring-2 focus:ring-red-500 focus:border-red-500 outline-none">
              <input type="tel" name="recipient_phone" value="<?= htmlspecialchars($addresses[0]['recipient_phone'] ?? '') ?>" placeholder="Телефон" class="w-full border-2 border-gray-200 rounded-2xl px-4 py-3 focus:ring-2 focus:ring-red-500 focus:border-red-500 outline-none">
            </div>
          </div>
        </div>

        <!-- Итоговая информация и клубнички -->
        <div class="bg-white rounded-3xl shadow-lg p-6">
          <div class="space-y-4">
            <!-- Информация о клубничках -->
            <?php if ($pointsBalance > 0): ?>
              <div class="bg-gradient-to-r from-pink-50 to-rose-50 rounded-2xl p-4">
                <div class="flex items-center justify-between">
                  <div class="flex items-center space-x-2">
                    <span class="text-2xl">🍓</span>
                    <div>
                      <div class="font-medium text-gray-800">Ваши клубнички</div>
                      <div class="text-sm text-gray-600">Доступно: <?= htmlspecialchars($pointsBalance) ?></div>
                    </div>
                  </div>
                  <div class="text-right">
                    <div class="font-semibold text-pink-600">
                      -<?= htmlspecialchars($pointsToUse) ?> 🍓
                    </div>
                    <div class="text-sm text-gray-500">списано</div>
                  </div>
                </div>
              </div>
            <?php endif; ?>

            <!-- Промокод -->
            <div class="bg-gradient-to-r from-yellow-50 to-orange-50 rounded-2xl p-4">
              <label class="block text-sm font-medium text-gray-700 mb-2">
                <span class="material-icons-round text-sm mr-1 align-middle">sell</span>
                Промокод
              </label>
              <div class="flex flex-col space-y-2">
                <?php if (!empty($lockCoupon)): ?>
                  <input type="text" value="" placeholder="<?= htmlspecialchars($couponCode) ?>" readonly
                         class="flex-1 border-2 border-gray-200 rounded-2xl px-4 py-3 bg-gray-100 cursor-not-allowed" />
                  <input type="hidden" name="coupon_code" value="<?= htmlspecialchars($couponCode) ?>">
                <?php else: ?>
                  <input type="text" name="coupon_code" value="<?= htmlspecialchars($couponCode ?? '') ?>"
                         placeholder="Введите промокод"
                         class="flex-1 border-2 border-gray-200 rounded-2xl px-4 py-3 focus:ring-2 focus:ring-red-500 focus:border-red-500 outline-none transition-all" />
                  <button type="submit" name="apply_coupon" value="1"
                          formaction="/checkout" formmethod="get"
                          class="bg-red-500 text-white px-4 py-3 rounded-2xl font-semibold">Применить</button>
                <?php endif; ?>
              </div>
              <?php if ($couponError): ?>
                <p class="text-red-600 text-sm mt-2"><?= htmlspecialchars($couponError) ?></p>
              <?php elseif ($couponInfo): ?>
                <p class="text-emerald-600 text-sm mt-2">
                  <?php if ($couponInfo['type'] === 'discount'): ?>
                    Скидка <?= htmlspecialchars($couponInfo['discount']) ?>%
                  <?php else: ?>
                    <?= htmlspecialchars($couponInfo['points']) ?> клубничек
                  <?php endif; ?>
                  применена
                </p>
              <?php endif; ?>
            </div>

            <!-- Итоговая сумма -->
            <div class="bg-gradient-to-r from-emerald-50 to-teal-50 rounded-2xl p-4">
              <div class="flex justify-between items-center">
                <div>
                  <div id="pickupRow" class="flex justify-between text-sm text-gray-600 mb-1 hidden">
                    <span>Самовывоз -20%</span>
                    <span id="pickupAmount">-0 ₽</span>
                  </div>
                  <div class="text-sm text-gray-600 mb-1">К оплате</div>
                  <div id="finalTotal" class="text-2xl font-bold text-gray-800"
                       data-subtotal="<?= (int)$subtotal ?>"
                       data-pointstouse="<?= (int)$pointsToUse ?>"
                       data-couponpoints="<?= (int)$couponPoints ?>"
                       data-discountpercent="<?= (float)$discountPercent ?>">
                    <?= number_format($finalTotal, 0, '.', ' ') ?> ₽
                  </div>
                </div>
                <div class="w-16 h-16 bg-gradient-to-br from-emerald-400 to-teal-500 rounded-2xl flex items-center justify-center">
                  <span class="material-icons-round text-2xl text-white">payments</span>
                </div>
              </div>
            </div>

            <!-- Кнопка подтверждения -->
            <button type="submit"
                    class="w-full bg-gradient-to-r from-red-500 to-pink-500 text-white py-4 rounded-2xl font-semibold text-lg hover:shadow-xl hover:scale-[1.02] transition-all flex items-center justify-center space-x-3">
              <span class="material-icons-round">credit_card</span>
              <span>Подтвердить</span>
              <span class="material-icons-round">arrow_forward</span>
            </button>
          </div>
        </div>

      </form>
    <?php endif; ?>

  </div>
</main>

<script>
  const select = document.getElementById('addressSelect');
  const block = document.getElementById('newAddressBlock');
  function applyPickup() {
    if (!select) return;
    const enabled = localStorage.getItem('pickupEnabled') === '1';
    let opt = select.querySelector('option[value="pickup"]');
    if (enabled) {
      if (!opt) {
        opt = document.createElement('option');
        opt.value = 'pickup';
        opt.textContent = 'Самовывоз 9 мая 73';
        select.prepend(opt);
      }
      select.value = 'pickup';
      block.classList.add('hidden');
    } else if (opt) {
      if (opt.selected) select.selectedIndex = 0;
      opt.remove();
    }
  }
  function toggleBlock() {
    if (select.value === 'new') {
      block.classList.remove('hidden');
    } else {
      block.classList.add('hidden');
    }
  }

  function format(num) {
    return num.toLocaleString('ru-RU');
  }

  function updateTotal() {
    applyPickup();
    toggleBlock();
    const finalEl = document.getElementById('finalTotal');
    if (!finalEl) return;
    const subtotal = parseFloat(finalEl.dataset.subtotal);
    const points = parseFloat(finalEl.dataset.pointstouse);
    const couponPts = parseFloat(finalEl.dataset.couponpoints);
    const discountPercent = parseFloat(finalEl.dataset.discountpercent);

    const pickup = select && select.value === 'pickup';
    let pickupDiscount = 0;
    let subAfterPickup = subtotal;
    if (pickup) {
      pickupDiscount = Math.floor(subtotal * 0.20);
      subAfterPickup -= pickupDiscount;
    }

    const pointsDiscount = Math.min(points + couponPts, subAfterPickup);
    const afterPoints = subAfterPickup - pointsDiscount;
    let couponDiscount = 0;
    if (discountPercent > 0) {
      couponDiscount = Math.floor(afterPoints * (discountPercent / 100));
    }

    const final = afterPoints - couponDiscount;
    finalEl.textContent = format(final) + ' ₽';

    const row = document.getElementById('pickupRow');
    const amtEl = document.getElementById('pickupAmount');
    if (pickup && row && amtEl) {
      amtEl.textContent = '-' + format(pickupDiscount) + ' ₽';
      row.classList.remove('hidden');
    } else if (row) {
      row.classList.add('hidden');
    }
  }

  if (select) {
    select.addEventListener('change', () => {
      updateTotal();
    });
    updateTotal();
  }
</script>
