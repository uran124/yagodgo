<?php
/**
 * @var array        $groups
 * @var float        $subtotal
 * @var int          $pointsBalance
 * @var int          $pointsToUse
 * @var string|null  $couponCode
 * @var array|null   $couponInfo
 * @var float        $finalTotal
 * @var string|null  $userName
 * @var string       $today
 * @var string|null  $couponError
 * @var array        $slots
 * @var array        $addresses
 */

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
$couponError     = $couponError     ?? null;
$slots           = $slots           ?? [];
$addresses       = $addresses       ?? [];
$pickupAddress   = 'Самовывоз: 9 мая, 73';
?>

<main class="bg-gradient-to-br from-orange-50 via-white to-pink-50 min-h-screen pb-24">
  <div class="px-4 space-y-6">

    <?php if (empty($groups)): ?>
      <div class="bg-white rounded-3xl shadow-lg p-12 text-center">
        <div class="w-20 h-20 bg-gradient-to-br from-gray-100 to-gray-200 rounded-2xl flex items-center justify-center mx-auto mb-6">
          <span class="material-icons-round text-4xl text-gray-400">shopping_cart</span>
        </div>
        <h3 class="text-xl font-semibold text-gray-600 mb-3">Корзина пуста</h3>
        <p class="text-gray-500 mb-6">Добавьте товары, чтобы оформить заказ</p>
        <a href="/catalog"
           class="inline-flex items-center space-x-3 bg-gradient-to-r from-red-500 to-pink-500 accent-gradient text-white px-8 py-4 rounded-2xl font-semibold hover:shadow-xl hover:scale-105 transition-all">
          <span class="material-icons-round">store</span>
          <span>Перейти в каталог</span>
        </a>
      </div>

    <?php else: ?>
      <?php
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

      <form action="/checkout" method="post" class="space-y-6" data-checkout-form>
        <input type="hidden" name="selected_orders_present" value="1">
        <?php foreach ($groups as $dateKey => $block): ?>
          <?php
            $isPreorderGroup = false;
            foreach ($block as $groupItem) {
              if (($groupItem['stock_mode'] ?? '') === 'preorder') {
                $isPreorderGroup = true;
                break;
              }
            }
            $placeholderDate = defined('PLACEHOLDER_DATE') ? PLACEHOLDER_DATE : '2025-05-15';
            if ($isPreorderGroup) {
              $label = ($dateKey === 'on_demand' || $dateKey === $placeholderDate)
                ? 'После выкупа закупки'
                : ('Предзаказ на ' . date('d.m.Y', strtotime((string)$dateKey)));
              $emoji = '📦';
            } elseif ($dateKey === $today) {
              $label = 'Сегодня';
              $emoji = '🚀';
            } else {
              $label = date('d.m.Y', strtotime($dateKey));
              $emoji = '📅';
            }
            $orderSum = 0;
            foreach ($block as $it) {
              $orderSum += $it['quantity'] * $it['unit_price'];
            }
            $defaultComment = '';
            foreach ($addresses as $a) {
              if (!empty($a['is_primary'])) {
                $defaultComment = (string)($a['last_checkout_comment'] ?? '');
                break;
              }
            }
            if ($defaultComment === '' && isset($addresses[0])) {
              $defaultComment = (string)($addresses[0]['last_checkout_comment'] ?? '');
            }
          ?>

          <section class="bg-white rounded-3xl shadow-lg overflow-hidden" data-checkout-order data-order-subtotal="<?= (int)$orderSum ?>" data-delivery-fee="300" data-order-selected="1">
            <div class="bg-gradient-to-r from-emerald-50 to-teal-50 p-6 border-b border-emerald-100">
              <div class="flex items-start justify-between gap-3">
                <div class="flex items-start gap-3">
                  <label class="mt-1 inline-flex items-center" title="Оформить этот заказ">
                    <input type="checkbox"
                           name="selected_orders[<?= htmlspecialchars($dateKey) ?>]"
                           value="1"
                           checked
                           class="h-5 w-5 rounded border-emerald-300 text-emerald-600 focus:ring-emerald-500"
                           data-order-select-checkbox>
                  </label>
                  <div>
                    <h3 class="text-lg font-semibold text-gray-800">
                      <?= $emoji ?> Заказ (<?= htmlspecialchars($label) ?>)
                    </h3>
                    <p class="mt-1 text-xs text-gray-500">Галочка включает этот блок в оформление. У каждой даты свой способ получения, адрес и доставка.</p>
                    <?php if ($isPreorderGroup): ?>
                      <p class="mt-2 rounded-xl bg-amber-50 px-3 py-2 text-xs font-medium text-amber-700">Цена предварительная. Точная цена будет после выкупа; заказ останется в статусе «Бронь» до подтверждения.</p>
                    <?php endif; ?>
                  </div>
                </div>
                <div class="rounded-2xl bg-white/80 px-3 py-2 text-right shadow-sm">
                  <div class="text-xs text-gray-500">Товары</div>
                  <div class="font-bold text-gray-800"><?= number_format($orderSum, 0, '.', ' ') ?> ₽</div>
                </div>
              </div>

              <div class="mt-4">
                <label class="block text-sm font-medium text-gray-700 mb-3">
                  <span class="material-icons-round text-sm mr-1 align-middle">schedule</span>
                  Время получения (<?= htmlspecialchars($label) ?>)
                </label>
                <div class="relative">
                  <select name="slot_id[<?= htmlspecialchars($dateKey) ?>]"
                          required
                          class="w-full border-2 border-gray-200 rounded-2xl px-4 py-3 pr-10 focus:ring-2 focus:ring-red-500 focus:border-red-500 outline-none transition-all bg-white">
                    <?php foreach ($slots as $slot): ?>
                      <option value="<?= (int)$slot['id'] ?>">
                        <?= htmlspecialchars($slot['time_from'] . ' - ' . $slot['time_to']) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                  <span class="material-icons-round absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 pointer-events-none">expand_more</span>
                </div>
              </div>

              <div class="mt-4">
                <input type="hidden"
                       name="order_mode[<?= htmlspecialchars($dateKey) ?>]"
                       value="<?= $isPreorderGroup ? 'preorder' : 'instant' ?>">
                <div class="text-xs text-gray-500">Режим заказа определяется на этапе выбора товара.</div>
              </div>
            </div>

            <div class="p-6 space-y-5">
              <div>
                <h4 class="mb-3 flex items-center gap-2 text-sm font-semibold uppercase tracking-wide text-gray-500">
                  <span class="material-icons-round text-base">inventory_2</span>
                  Список товаров
                </h4>
                <div class="space-y-3">
                  <?php foreach ($block as $it): ?>
                    <?php $lineCost = $it['quantity'] * $it['unit_price']; ?>
                    <div class="flex items-center justify-between p-4 bg-gray-50 rounded-2xl">
                      <div class="flex-1">
                        <div class="font-medium text-gray-800">
                          <?= htmlspecialchars($it['product']) ?>
                          <?php if (!empty($it['variety'])): ?>
                            <span class="text-gray-600"><?= htmlspecialchars($it['variety']) ?></span>
                          <?php endif; ?>
                        </div>
                        <div class="text-sm text-gray-500 mt-1">Количество: <?= htmlspecialchars($it['quantity']) ?></div>
                        <?php if ($isPreorderGroup): ?>
                          <div class="mt-1 text-xs text-amber-600">Предварительная цена, точная цена будет после выкупа.</div>
                        <?php endif; ?>
                      </div>
                      <div class="text-right font-semibold text-gray-800"><?= number_format($lineCost, 0, '.', ' ') ?> ₽</div>

                      <input type="hidden"
                             name="items[<?= htmlspecialchars($dateKey) ?>][<?= (int)$it['product_id'] ?>][quantity]"
                             value="<?= htmlspecialchars($it['quantity']) ?>">
                      <input type="hidden"
                             name="items[<?= htmlspecialchars($dateKey) ?>][<?= (int)$it['product_id'] ?>][unit_price]"
                             value="<?= htmlspecialchars($it['unit_price']) ?>">
                    </div>
                  <?php endforeach; ?>
                </div>
              </div>

              <div class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm">
                <h4 class="mb-3 flex items-center gap-2 text-sm font-semibold uppercase tracking-wide text-gray-500">
                  <span class="material-icons-round text-base">location_on</span>
                  Способ получения
                </h4>
                <div class="space-y-3">
                  <select name="address_id[<?= htmlspecialchars($dateKey) ?>]"
                          class="w-full border-2 border-gray-200 rounded-2xl px-4 py-3 focus:ring-2 focus:ring-red-500 focus:border-red-500 outline-none"
                          data-address-select>
                    <?php foreach ($addresses as $a): ?>
                      <?php $addrComment = (string)($a['last_checkout_comment'] ?? ''); ?>
                      <option value="<?= (int)$a['id'] ?>"
                              data-street="<?= htmlspecialchars($a['street']) ?>"
                              data-comment="<?= htmlspecialchars($addrComment) ?>"
                              <?= !empty($a['is_primary']) ? 'selected' : '' ?>>
                        Доставка: <?= htmlspecialchars($a['street']) ?>
                      </option>
                    <?php endforeach; ?>
                    <option value="new" data-street="">Доставка: другой адрес</option>
                    <option value="pickup" data-street="<?= htmlspecialchars($pickupAddress) ?>">Самовывоз: 9 мая 73</option>
                  </select>

                  <div class="space-y-2 hidden" data-new-address-block>
                    <div class="relative" data-checkout-address-suggest>
                      <input type="text"
                             name="new_address[<?= htmlspecialchars($dateKey) ?>]"
                             placeholder="Адрес"
                             autocomplete="off"
                             class="w-full border-2 border-gray-200 rounded-2xl px-4 py-3 focus:ring-2 focus:ring-red-500 focus:border-red-500 outline-none"
                             data-checkout-address-input>
                      <input type="hidden" name="new_address_normalized[<?= htmlspecialchars($dateKey) ?>]" data-checkout-address-selected-address>
                      <input type="hidden" name="new_address_lat[<?= htmlspecialchars($dateKey) ?>]" data-checkout-address-selected-lat>
                      <input type="hidden" name="new_address_lng[<?= htmlspecialchars($dateKey) ?>]" data-checkout-address-selected-lng>
                      <div class="hidden absolute z-30 mt-1 max-h-72 w-full overflow-auto rounded-2xl border border-gray-200 bg-white shadow-2xl" data-checkout-address-list></div>
                      <p class="mt-1 text-xs text-gray-500">Начните вводить адрес и выберите точный вариант из списка.</p>
                    </div>
                  </div>

                  <div data-delivery-comment-block>
                    <label class="mb-1 block text-sm font-medium text-gray-700">Комментарий к получению</label>
                    <textarea name="delivery_comment[<?= htmlspecialchars($dateKey) ?>]"
                              rows="2"
                              placeholder="Например: получатель Марина +7..., подъезд 2, оставить у консьержа"
                              class="w-full border-2 border-gray-200 rounded-2xl px-4 py-3 focus:ring-2 focus:ring-red-500 focus:border-red-500 outline-none"
                              data-delivery-comment><?= htmlspecialchars($defaultComment) ?></textarea>
                  </div>

                  <div class="rounded-2xl bg-emerald-50 p-3 text-sm text-gray-700" data-delivery-result>
                    <div class="flex items-center justify-between gap-3">
                      <span class="font-semibold">Доставка</span>
                      <span class="font-bold text-gray-900" data-delivery-fee>300 ₽</span>
                    </div>
                    <div class="mt-1 text-xs text-gray-500" data-delivery-note>Стоимость будет рассчитана по адресу.</div>
                    <input type="hidden" name="delivery_fee_preview[<?= htmlspecialchars($dateKey) ?>]" value="300" data-delivery-fee-input>
                    <input type="hidden" name="delivery_distance_km_preview[<?= htmlspecialchars($dateKey) ?>]" value="" data-delivery-distance-input>
                    <input type="hidden" name="delivery_pricing_source_preview[<?= htmlspecialchars($dateKey) ?>]" value="" data-delivery-source-input>
                  </div>
                </div>
              </div>

              <div class="space-y-2 rounded-2xl bg-gray-50 p-4">
                <div class="flex justify-between items-center">
                  <span class="font-semibold text-gray-800">Итого по товарам:</span>
                  <span class="font-bold text-gray-800"><?= number_format($orderSum, 0, '.', ' ') ?> ₽</span>
                </div>
                <div class="flex justify-between items-center">
                  <span class="font-semibold text-gray-800">Доставка:</span>
                  <span class="font-bold text-gray-800" data-order-delivery-total>300 ₽</span>
                </div>
                <div class="flex justify-between items-center border-t border-gray-200 pt-2">
                  <span class="font-semibold text-gray-900">Итого блока:</span>
                  <span class="font-bold text-xl text-gray-900" data-order-total><?= number_format($orderSum + 300, 0, '.', ' ') ?> ₽</span>
                </div>
              </div>
            </div>
          </section>
        <?php endforeach; ?>

        <div class="bg-white rounded-3xl shadow-lg p-6">
          <div class="space-y-4">
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
                    <div class="font-semibold text-pink-600" data-points-discount-display>-<?= htmlspecialchars($pointsToUse) ?> 🍓</div>
                    <div class="text-sm text-gray-500">списано с товаров</div>
                  </div>
                </div>
              </div>
            <?php endif; ?>

            <div id="discountStockNotice" class="hidden bg-amber-50 border border-amber-200 text-amber-800 rounded-2xl p-3 text-sm">
              Выбран режим «Выгодный остаток»: купоны и клубнички не применяются.
            </div>

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
                  <input id="couponInput" type="text" name="coupon_code" value="<?= htmlspecialchars($couponCode ?? '') ?>"
                         placeholder="Введите промокод"
                         class="flex-1 border-2 border-gray-200 rounded-2xl px-4 py-3 focus:ring-2 focus:ring-red-500 focus:border-red-500 outline-none transition-all" />
                  <button id="couponApplyBtn" type="submit" name="apply_coupon" value="1"
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

            <div class="bg-gradient-to-r from-emerald-50 to-teal-50 rounded-2xl p-4">
              <div class="flex justify-between items-center">
                <div>
                  <div class="text-sm text-gray-600 mb-1">К оплате</div>
                  <div id="finalTotal" class="text-2xl font-bold text-gray-800"
                       data-subtotal="<?= (int)$subtotal ?>"
                       data-pointstouse="<?= (int)$pointsToUse ?>"
                       data-couponpoints="<?= (int)$couponPoints ?>"
                       data-discountpercent="<?= (float)$discountPercent ?>">
                    <?= number_format($finalTotal, 0, '.', ' ') ?> ₽
                  </div>
                  <div class="mt-1 text-xs text-gray-500">Доставка считается отдельно по каждому адресу и не участвует в баллах.</div>
                </div>
                <div class="w-16 h-16 bg-gradient-to-br from-emerald-400 to-teal-500 rounded-2xl flex items-center justify-center">
                  <span class="material-icons-round text-2xl text-white">payments</span>
                </div>
              </div>
            </div>

            <button type="submit"
                    class="w-full bg-gradient-to-r from-red-500 to-pink-500 accent-gradient text-white py-4 rounded-2xl font-semibold text-lg hover:shadow-xl hover:scale-[1.02] transition-all flex items-center justify-center space-x-3">
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
  const checkoutOrders = Array.from(document.querySelectorAll('[data-checkout-order]'));
  const modeSelects = document.querySelectorAll('input[name^="order_mode"]');

  function format(num) {
    return Math.round(Number(num || 0)).toLocaleString('ru-RU');
  }

  function escapeHtmlCheckout(value) {
    return String(value ?? '').replace(/[&<>"']/g, function (char) {
      return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'}[char];
    });
  }

  function updateTotal() {
    const finalEl = document.getElementById('finalTotal');
    if (!finalEl) return;

    const points = parseFloat(finalEl.dataset.pointstouse || '0');
    const couponPts = parseFloat(finalEl.dataset.couponpoints || '0');
    const discountPercent = parseFloat(finalEl.dataset.discountpercent || '0');
    const selectedOrders = checkoutOrders.filter(order => order.dataset.orderSelected !== '0');
    const selectedSubtotal = selectedOrders.reduce((sum, order) => sum + Number(order.dataset.orderSubtotal || 0), 0);
    const shipping = selectedOrders.reduce((sum, order) => sum + Number(order.dataset.deliveryFee || 0), 0);

    const pointsDiscount = Math.min(points + couponPts, selectedSubtotal);
    const afterPoints = selectedSubtotal - pointsDiscount;
    const couponDiscount = discountPercent > 0 ? Math.floor(afterPoints * (discountPercent / 100)) : 0;
    const final = afterPoints - couponDiscount + shipping;

    finalEl.textContent = format(final) + ' ₽';
    const pointsDisplay = document.querySelector('[data-points-discount-display]');
    if (pointsDisplay) pointsDisplay.textContent = '-' + format(pointsDiscount) + ' 🍓';

    checkoutOrders.forEach(order => {
      const orderSubtotal = Number(order.dataset.orderSubtotal || 0);
      const isSelected = order.dataset.orderSelected !== '0';
      const fee = isSelected ? Number(order.dataset.deliveryFee || 0) : 0;
      const deliveryTotal = order.querySelector('[data-order-delivery-total]');
      const orderTotal = order.querySelector('[data-order-total]');
      if (deliveryTotal) deliveryTotal.textContent = isSelected ? format(fee) + ' ₽' : 'не оформляется';
      if (orderTotal) orderTotal.textContent = isSelected ? format(orderSubtotal + fee) + ' ₽' : 'не выбран';
      order.classList.toggle('opacity-60', !isSelected);
    });
  }

  function updateDiscountStockState() {
    const hasDiscountStock = Array.from(modeSelects).some(input => {
      const order = input.closest('[data-checkout-order]');
      return (!order || order.dataset.orderSelected !== '0') && input.value === 'discount_stock';
    });
    const notice = document.getElementById('discountStockNotice');
    if (notice) notice.classList.toggle('hidden', !hasDiscountStock);

    const couponInput = document.getElementById('couponInput');
    const couponApplyBtn = document.getElementById('couponApplyBtn');
    if (couponInput) {
      couponInput.disabled = hasDiscountStock;
      if (hasDiscountStock) {
        couponInput.value = '';
        couponInput.placeholder = 'Недоступно для выгодного остатка';
      }
    }
    if (couponApplyBtn) {
      couponApplyBtn.disabled = hasDiscountStock;
      couponApplyBtn.classList.toggle('opacity-50', hasDiscountStock);
      couponApplyBtn.classList.toggle('cursor-not-allowed', hasDiscountStock);
    }

    const finalEl = document.getElementById('finalTotal');
    if (finalEl) {
      finalEl.dataset.pointstouse = hasDiscountStock ? '0' : '<?= (int)$pointsToUse ?>';
      finalEl.dataset.couponpoints = hasDiscountStock ? '0' : '<?= (int)$couponPoints ?>';
      finalEl.dataset.discountpercent = hasDiscountStock ? '0' : '<?= (float)$discountPercent ?>';
    }
    updateTotal();
  }

  async function fetchAddressSuggestions(query) {
    if (!query || query.trim().length < 3) return [];

    const response = await fetch('/delivery/address-suggestions?query=' + encodeURIComponent(query.trim()), {
      credentials: 'same-origin',
      headers: {'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json'}
    });

    const text = await response.text();
    let data = null;
    try {
      data = JSON.parse(text);
    } catch (e) {
      throw new Error('Сервер вернул не JSON. Проверьте маршрут /delivery/address-suggestions.');
    }

    if (!response.ok || !data.ok) {
      throw new Error(data.message || 'Не удалось получить подсказки адреса.');
    }

    return Array.isArray(data.suggestions) ? data.suggestions : [];
  }

  async function calculateDelivery(order) {
    const select = order.querySelector('[data-address-select]');
    const feeEl = order.querySelector('[data-delivery-fee]');
    const noteEl = order.querySelector('[data-delivery-note]');
    const feeInput = order.querySelector('[data-delivery-fee-input]');
    const distanceInput = order.querySelector('[data-delivery-distance-input]');
    const sourceInput = order.querySelector('[data-delivery-source-input]');
    if (!select) return;

    const selected = select.options[select.selectedIndex];
    const isPickup = select.value === 'pickup';
    const isNew = select.value === 'new';
    const newBlock = order.querySelector('[data-new-address-block]');
    const commentBlock = order.querySelector('[data-delivery-comment-block]');
    if (newBlock) newBlock.classList.toggle('hidden', !isNew);
    if (commentBlock) commentBlock.classList.toggle('hidden', isPickup);

    if (isPickup) {
      order.dataset.deliveryFee = '0';
      if (feeEl) feeEl.textContent = '0 ₽';
      if (noteEl) noteEl.textContent = 'Самовывоз — доставка 0 ₽.';
      if (feeInput) feeInput.value = '0';
      if (distanceInput) distanceInput.value = '';
      if (sourceInput) sourceInput.value = 'pickup';
      updateTotal();
      return;
    }

    let address = selected ? (selected.dataset.street || '') : '';
    const selectedLat = order.querySelector('[data-checkout-address-selected-lat]')?.value || '';
    const selectedLng = order.querySelector('[data-checkout-address-selected-lng]')?.value || '';
    const selectedAddress = order.querySelector('[data-checkout-address-selected-address]')?.value || '';
    if (isNew) {
      address = order.querySelector('[data-checkout-address-input]')?.value.trim() || '';
    }

    if (!address) {
      order.dataset.deliveryFee = '300';
      if (feeEl) feeEl.textContent = '300 ₽';
      if (noteEl) noteEl.textContent = 'Введите адрес — менеджер уточнит стоимость перед подтверждением.';
      if (feeInput) feeInput.value = '300';
      if (distanceInput) distanceInput.value = '';
      if (sourceInput) sourceInput.value = 'pending_review';
      updateTotal();
      return;
    }

    if (feeEl) feeEl.textContent = 'считаем…';
    if (noteEl) noteEl.textContent = 'Считаем расстояние и тариф доставки.';

    const body = new URLSearchParams();
    body.set('address', address);
    if (selectedLat) body.set('selected_lat', selectedLat);
    if (selectedLng) body.set('selected_lng', selectedLng);
    if (selectedAddress) body.set('selected_address', selectedAddress);

    try {
      const response = await fetch('/delivery/calculate', {
        method: 'POST',
        credentials: 'same-origin',
        headers: {'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest'},
        body
      });
      const data = await response.json();
      if (!response.ok || !data.ok) {
        throw new Error(data.message || 'Не удалось рассчитать доставку');
      }
      const fee = Number(data.delivery_fee ?? data.price_rub ?? 300);
      order.dataset.deliveryFee = String(fee);
      if (feeEl) feeEl.textContent = format(fee) + ' ₽';
      const distanceText = data.distance_km ? `${data.distance_km} км` : 'расстояние уточняется';
      const warning = data.warning ? ` ${data.warning}` : '';
      if (noteEl) noteEl.textContent = `Расстояние: ${distanceText}. ${data.message || ''}${warning}`.trim();
      if (feeInput) feeInput.value = String(fee);
      if (distanceInput) distanceInput.value = data.distance_km || '';
      if (sourceInput) sourceInput.value = data.delivery_pricing_source || data.pricing_source || '';
    } catch (error) {
      order.dataset.deliveryFee = '300';
      if (feeEl) feeEl.textContent = 'от 300 ₽';
      if (noteEl) noteEl.textContent = (error.message || 'Не удалось рассчитать доставку') + '. Точную стоимость подтвердит менеджер.';
      if (feeInput) feeInput.value = '300';
      if (distanceInput) distanceInput.value = '';
      if (sourceInput) sourceInput.value = 'pending_review';
    }

    updateTotal();
  }

  function initAddressSuggestions(order) {
    const root = order.querySelector('[data-checkout-address-suggest]');
    if (!root) return;
    const input = root.querySelector('[data-checkout-address-input]');
    const list = root.querySelector('[data-checkout-address-list]');
    const selectedAddress = root.querySelector('[data-checkout-address-selected-address]');
    const selectedLat = root.querySelector('[data-checkout-address-selected-lat]');
    const selectedLng = root.querySelector('[data-checkout-address-selected-lng]');
    let timer = null;
    let requestId = 0;

    function clearSelected() {
      if (selectedAddress) selectedAddress.value = '';
      if (selectedLat) selectedLat.value = '';
      if (selectedLng) selectedLng.value = '';
    }

    function hideSuggestions() {
      if (!list) return;
      list.innerHTML = '';
      list.classList.add('hidden');
    }

    function renderSuggestions(items, message) {
      if (!input || !list) return;
      list.innerHTML = '';
      if (!items.length) {
        if (message) {
          list.innerHTML = `<div class="px-4 py-3 text-sm text-gray-500">${escapeHtmlCheckout(message)}</div>`;
          list.classList.remove('hidden');
        } else {
          list.classList.add('hidden');
        }
        return;
      }

      items.forEach((item) => {
        const row = document.createElement('button');
        row.type = 'button';
        row.className = 'flex w-full items-start gap-2 border-b border-gray-100 px-4 py-3 text-left text-sm hover:bg-rose-50 focus:bg-rose-50 focus:outline-none';
        const meta = [item.city || '', item.district || '', item.distance_from_center_km ? `${item.distance_from_center_km} км от центра` : ''].filter(Boolean).join(' · ');
        row.innerHTML = `
          <span class="material-icons-round text-base text-red-500">location_on</span>
          <span class="flex-1">
            <span class="block font-semibold text-gray-800">${escapeHtmlCheckout(item.label || item.value || '')}</span>
            <span class="mt-0.5 block text-xs text-gray-500">${escapeHtmlCheckout(meta)}</span>
          </span>
        `;
        row.addEventListener('click', function () {
          const chosenAddress = item.value || item.label || item.unrestricted_value || '';
          input.value = chosenAddress;
          if (selectedAddress) selectedAddress.value = chosenAddress;
          if (selectedLat) selectedLat.value = item.lat || '';
          if (selectedLng) selectedLng.value = item.lng || '';
          hideSuggestions();
          calculateDelivery(order);
        });
        list.appendChild(row);
      });
      list.classList.remove('hidden');
    }

    input.addEventListener('input', function () {
      clearSelected();
      clearTimeout(timer);
      const query = input.value.trim();
      if (query.length < 3) {
        hideSuggestions();
        calculateDelivery(order);
        return;
      }
      const currentRequest = ++requestId;
      timer = setTimeout(async function () {
        try {
          const suggestions = await fetchAddressSuggestions(query);
          if (currentRequest === requestId) {
            renderSuggestions(suggestions, suggestions.length ? '' : 'Не нашли адрес в радиусе доставки. Уточните населённый пункт, улицу и дом.');
          }
        } catch (error) {
          if (error && error.name === 'AbortError') return;
          if (currentRequest === requestId) {
            renderSuggestions([], error.message || 'Не удалось получить подсказки адреса.');
          }
        }
      }, 500);
    });

    document.addEventListener('click', function (event) {
      if (!root.contains(event.target)) hideSuggestions();
    });
  }

  checkoutOrders.forEach(order => {
    const checkbox = order.querySelector('[data-order-select-checkbox]');
    const select = order.querySelector('[data-address-select]');
    const comment = order.querySelector('[data-delivery-comment]');
    if (checkbox) {
      const syncSelected = function () {
        order.dataset.orderSelected = checkbox.checked ? '1' : '0';
        updateDiscountStockState();
      };
      checkbox.addEventListener('change', syncSelected);
      syncSelected();
    }
    initAddressSuggestions(order);
    if (select) {
      select.addEventListener('change', function () {
        const selected = select.options[select.selectedIndex];
        if (comment && selected && selected.dataset.comment !== undefined) {
          comment.value = selected.dataset.comment || '';
        }
        calculateDelivery(order);
      });
    }
    calculateDelivery(order);
  });

  const checkoutForm = document.querySelector('[data-checkout-form]');
  if (checkoutForm) {
    checkoutForm.addEventListener('submit', function (event) {
      const selectedOrders = checkoutOrders.filter(order => order.dataset.orderSelected !== '0');
      if (!selectedOrders.length) {
        event.preventDefault();
        alert('Выберите хотя бы один заказ для оформления.');
        return;
      }
      for (const order of selectedOrders) {
        const select = order.querySelector('[data-address-select]');
        if (!select || select.value !== 'new') continue;
        const input = order.querySelector('[data-checkout-address-input]');
        const selectedAddress = order.querySelector('[data-checkout-address-selected-address]');
        const selectedLat = order.querySelector('[data-checkout-address-selected-lat]');
        const selectedLng = order.querySelector('[data-checkout-address-selected-lng]');
        const value = input ? input.value.trim() : '';
        const looksLikeCoords = /^\s*-?\d+(?:[\.,]\d+)?\s*[,; ]\s*-?\d+(?:[\.,]\d+)?\s*$/.test(value);
        if (value.length >= 3 && !looksLikeCoords && (!selectedAddress?.value || !selectedLat?.value || !selectedLng?.value)) {
          event.preventDefault();
          input.focus();
          alert('Выберите новый адрес из подсказки DaData. Это нужно, чтобы не перепутать город или посёлок.');
          return;
        }
      }
    });
  }

  updateDiscountStockState();
</script>
