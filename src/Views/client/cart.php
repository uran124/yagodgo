<?php /** 
  @var array $items       // каждый элемент содержит delivery_date (или null), unit_price, quantity, product, variety, image_path и т.д.
  @var string|null $userName
*/ ?>

<main class="bg-gradient-to-br from-orange-50 via-white to-pink-50 min-h-screen pb-24">


  <?php if (empty($items)): ?>
    <!-- Пустая корзина -->
    <div class="px-4">
      <div class="bg-white rounded-3xl p-12 text-center shadow-lg">
        <div class="w-24 h-24 bg-gradient-to-br from-gray-100 to-gray-200 rounded-3xl flex items-center justify-center mx-auto mb-6">
          <span class="material-icons-round text-5xl text-gray-400">shopping_cart</span>
        </div>
        <h2 class="text-2xl font-bold text-gray-600 mb-3">Ваша корзина пуста</h2>
        <p class="text-gray-500 mb-6">Свежие ягоды и фрукты из Киргизии ждут вас в каталоге!</p>
        <a href="/catalog"
           class="inline-flex items-center px-8 py-4 bg-gradient-to-r from-red-500 to-pink-500 text-white font-semibold rounded-2xl hover:shadow-lg hover:shadow-red-500/25 transition-all hover:scale-105 space-x-3">
          <span class="material-icons-round">store</span>
          <span>Смотреть каталог</span>
          <span class="material-icons-round">arrow_forward</span>
        </a>
      </div>
    </div>
  <?php else: ?>
    <!-- Товары в корзине -->
    <div class="px-4 space-y-4">
      <?php 
      $totalAmount = 0;
      $today = new DateTimeImmutable('today');
      foreach ($items as $it): 
        // Считаем сумму за эту позицию
        $unitPriceToUse = (float)$it['unit_price'];
        $rowSum = $unitPriceToUse * $it['quantity'];
        $totalAmount += $rowSum;

        // Подготовка опций дат
        $options = [];
        $d = $it['delivery_date']; // может быть null или строка 'YYYY-MM-DD'
        if ($d === null) {
          // Под заказ: select станет disabled
          $options = [];
        } else {
          $deliveryDate = new DateTimeImmutable($d);
          if ($deliveryDate <= $today) {
            // В наличии: Сегодня, Завтра, Послезавтра
            $options[] = ['value'=>$today->format('Y-m-d'), 'label'=>'Сегодня'];
            for ($i = 1; $i <= 2; $i++) {
              $dOpt = $today->modify("+{$i} day")->format('Y-m-d');
              $options[] = ['value'=>$dOpt, 'label'=>date('d.m.Y', strtotime($dOpt))];
            }
          } else {
            // Предзаказ: дата поступления и два следующих дня
            $d0 = $deliveryDate->format('Y-m-d');
            $options[] = ['value'=>$d0, 'label'=>date('d.m.Y', strtotime($d0))];
            for ($i = 1; $i <= 2; $i++) {
              $next = $deliveryDate->modify("+{$i} day")->format('Y-m-d');
              $options[] = ['value'=>$next, 'label'=>date('d.m.Y', strtotime($next))];
            }
          }
        }
      ?>

      <!-- Карточка товара -->
      <div class="bg-white rounded-2xl shadow-lg p-4 space-y-3">
        <div class="flex justify-between">
          <div class="font-medium text-gray-800">
            <?= htmlspecialchars($it['product']) ?>
            <?php if (!empty($it['variety'])): ?>
              <?= ' ' . htmlspecialchars($it['variety']) ?>
            <?php endif; ?>
            <?php if (!empty($it['box_size']) && !empty($it['box_unit'])): ?>
              (<?= htmlspecialchars($it['box_size'] . ' ' . $it['box_unit']) ?>)
            <?php endif; ?>
          </div>
          <div class="font-semibold text-gray-800">
            <?= number_format($unitPriceToUse, 0, '.', ' ') ?> ₽/ящик
          </div>
        </div>

        <div class="flex justify-between items-center">
          <div class="flex items-center space-x-3">
            <?php if ($it['quantity'] > 1): ?>
              <form action="/cart/update" method="post">
                <input type="hidden" name="product_id" value="<?= $it['product_id'] ?>">
                <button type="submit" name="action" value="decrease" class="w-8 h-8 flex items-center justify-center bg-gray-100 rounded-full">
                  <span class="material-icons-round text-gray-600">remove</span>
                </button>
              </form>
            <?php else: ?>
              <form action="/cart/remove" method="post">
                <input type="hidden" name="product_id" value="<?= $it['product_id'] ?>">
                <button type="submit" class="w-8 h-8 flex items-center justify-center bg-gray-100 rounded-full">
                  <span class="material-icons-round text-gray-600">delete</span>
                </button>
              </form>
            <?php endif; ?>
            <span class="font-medium text-gray-800"><?= $it['quantity'] ?></span>
            <form action="/cart/update" method="post">
              <input type="hidden" name="product_id" value="<?= $it['product_id'] ?>">
              <button type="submit" name="action" value="increase" class="w-8 h-8 flex items-center justify-center bg-gray-100 rounded-full">
                <span class="material-icons-round text-gray-600">add</span>
              </button>
            </form>
          </div>
          <div class="font-semibold text-gray-800">
            <?= number_format($rowSum, 0, '.', ' ') ?> ₽
          </div>
        </div>

        <div>
          <label class="block text-sm text-gray-600 mb-1">Дата доставки</label>
          <select name="delivery_date[<?= $it['product_id'] ?>]"
                  form="checkoutForm"
                  <?= empty($options) ? 'disabled' : 'required' ?>
                  class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm focus:border-red-500 focus:ring-2 focus:ring-red-500/20 focus:outline-none transition-all bg-white shadow-sm">
            <?php if (!empty($options)): ?>
              <?php foreach ($options as $opt): ?>
                <option value="<?= htmlspecialchars($opt['value']) ?>">
                  <?= htmlspecialchars($opt['label']) ?>
                </option>
              <?php endforeach; ?>
            <?php else: ?>
              <option>Ближайшая возможная дата</option>
            <?php endif; ?>
          </select>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Итоговая секция -->
    <div class="px-4 mt-8">
      <div class="bg-white rounded-3xl shadow-2xl p-6 border border-gray-100">
        <!-- Сводка заказа -->
        <div class="mb-6">
          <h3 class="text-xl font-bold text-gray-800 mb-4 text-center">📋 Сводка заказа</h3>
          
          <div class="space-y-3 mb-4">
            <div class="flex justify-between items-center py-2 border-b border-gray-100">
              <span class="text-gray-600">Товаров в корзине:</span>
              <span class="font-semibold"><?= count($items) ?> шт.</span>
            </div>
            <div class="flex justify-between items-center py-2">
              <span class="text-lg font-semibold text-gray-800">Итого к оплате:</span>
              <span class="text-2xl font-bold bg-gradient-to-r from-red-500 to-pink-500 bg-clip-text text-transparent">
                <?= number_format($totalAmount, 0, '.', ' ') ?> ₽
              </span>
            </div>
          </div>
        </div>
        
        <div class="flex justify-between items-center space-x-4">
          <a href="/catalog"
             class="flex items-center px-4 sm:px-6 py-2 sm:py-3 bg-gradient-to-r from-gray-100 to-gray-200 text-gray-700 rounded-2xl font-medium hover:from-gray-200 hover:to-gray-300 transition-all space-x-1 sm:space-x-2 text-sm sm:text-base">
            <span class="material-icons-round">arrow_back</span>
            <span>В каталог</span>
          </a>
          <form id="checkoutForm" action="/checkout" method="get">
            <button type="submit"
                    class="flex items-center px-4 sm:px-6 py-2 sm:py-3 bg-gradient-to-r from-red-500 to-pink-500 text-white rounded-2xl font-semibold hover:shadow-lg hover:shadow-red-500/25 transition-all space-x-1 sm:space-x-2 text-sm sm:text-base">
              <span>Оформить</span>
              <span class="material-icons-round">arrow_forward</span>
            </button>
          </form>
        </div>
      </div>
    </div>


  <?php endif; ?>
</main>
