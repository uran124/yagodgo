<?php /** 
  @var array $items       // каждый элемент содержит delivery_date (или null), unit_price, quantity, product, variety, image_path и т.д.
  @var string|null $userName
*/ ?>

<main class="bg-gradient-to-br from-orange-50 via-white to-pink-50 min-h-screen pb-24">

  <!-- Header Section -->
  <div class="pt-6 px-4 mb-6">
    <div class="bg-gradient-to-r from-red-500 to-pink-500 rounded-3xl p-6 text-white shadow-2xl">
      <div class="flex justify-between items-center">
        <div>
          <h1 class="text-3xl font-bold mb-2">🛒 Корзина</h1>  
          <p class="text-red-100 text-sm">Ваши выбранные товары</p>
        </div>
        <?php if ($userName): ?>
          <div class="text-right">
            <div class="inline-flex items-center px-4 py-2 bg-white/20 backdrop-blur-sm rounded-full">
              <span class="material-icons-round mr-2 text-lg">person</span>
              <span class="font-medium"><?= htmlspecialchars($userName) ?></span>
            </div>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

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
      <div class="bg-white rounded-3xl shadow-lg p-5 hover:shadow-xl transition-all">
        <div class="flex space-x-4 mb-4">
          <!-- Изображение товара -->
          <div class="relative flex-shrink-0">
            <img src="<?= htmlspecialchars($it['image_path'] ?: '/assets/placeholder.png') ?>"
                 alt="<?= htmlspecialchars($it['product']) ?>"
                 class="w-20 h-20 sm:w-24 sm:h-24 object-cover rounded-2xl">

            <!-- Статус товара -->
            <div class="absolute -top-2 -right-2">
              <?php if ($d !== null && $d <= $today->format('Y-m-d')): ?>
                <span class="inline-flex items-center px-2 py-1 bg-gradient-to-r from-emerald-400 to-green-500 text-white text-xs font-semibold rounded-full shadow-lg">
                  <span class="w-1.5 h-1.5 bg-white rounded-full mr-1 animate-pulse"></span>
                  В наличии
                </span>
              <?php elseif ($d !== null): ?>
                <span class="inline-flex items-center px-2 py-1 bg-gradient-to-r from-orange-400 to-amber-500 text-white text-xs font-semibold rounded-full shadow-lg">
                  <span class="material-icons-round mr-1 text-xs">schedule</span>
                  <?= date('d.m', strtotime($d)) ?>
                </span>
              <?php else: ?>
                <span class="inline-flex items-center px-2 py-1 bg-gradient-to-r from-blue-400 to-indigo-500 text-white text-xs font-semibold rounded-full shadow-lg">
                  <span class="material-icons-round mr-1 text-xs">info</span>
                  Под заказ
                </span>
              <?php endif; ?>
            </div>
          </div>

          <!-- Информация о товаре -->
          <div class="flex-1 min-w-0">
            <div class="mb-3">
              <h3 class="font-bold text-lg text-gray-800 mb-1 line-clamp-2">
                <?= htmlspecialchars($it['product']) ?>
                <?php if (!empty($it['variety'])): ?>
                  <span class="text-red-500">, <?= htmlspecialchars($it['variety']) ?></span>
                <?php endif; ?>
              </h3>
            </div>

            <!-- Цена -->
            <div class="mb-3 space-y-1">
              <div class="text-2xl font-bold bg-gradient-to-r from-red-500 to-pink-500 bg-clip-text text-transparent">
                <?= number_format($unitPriceToUse, 0, '.', ' ') ?> ₽
              </div>
              <div class="text-sm text-gray-500">
                <?= $it['quantity'] ?> × <?= number_format($unitPriceToUse, 0, '.', ' ') ?> ₽
              </div>
            </div>
          </div>
        </div>

        <!-- Выбор даты доставки -->
        <div class="mb-4 p-4 bg-gradient-to-r from-blue-50 to-indigo-50 rounded-2xl">
          <label class="block text-sm font-medium text-gray-700 mb-2">
            <span class="material-icons-round text-sm align-middle mr-2 text-blue-500">local_shipping</span>
            Дата доставки
          </label>
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

        <!-- Контролы количества и удаления -->
        <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between space-y-3 sm:space-y-0 sm:space-x-4">
          <!-- Контрол количества -->
          <form action="/cart/update" method="post" class="flex items-center justify-center sm:justify-start">
            <input type="hidden" name="product_id" value="<?= $it['product_id'] ?>">

            <div class="flex items-center bg-gray-100 rounded-2xl p-1">
              <button name="action" value="decrease" type="submit"
                      class="w-10 h-10 flex items-center justify-center bg-white rounded-xl shadow-sm hover:bg-gray-50 transition-colors">
                <span class="material-icons-round text-gray-600">remove</span>
              </button>
              <input type="number" name="quantity" value="<?= $it['quantity'] ?>"
                     min="1" class="w-16 text-center bg-transparent font-semibold text-gray-800 outline-none py-2">
              <button name="action" value="increase" type="submit"
                      class="w-10 h-10 flex items-center justify-center bg-white rounded-xl shadow-sm hover:bg-gray-50 transition-colors">
                <span class="material-icons-round text-gray-600">add</span>
              </button>
            </div>
          </form>

          <!-- Кнопка удаления -->
          <form action="/cart/remove" method="post" class="w-full sm:w-auto">
            <input type="hidden" name="product_id" value="<?= $it['product_id'] ?>">
            <button type="submit"
                    class="w-full sm:w-auto bg-gradient-to-r from-red-500 to-pink-500 text-white px-6 py-3 rounded-2xl font-semibold hover:shadow-lg hover:shadow-red-500/25 transition-all flex items-center justify-center space-x-2">
              <span class="material-icons-round">delete</span>
              <span>Удалить</span>
            </button>
          </form>
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
        
        <!-- Основная кнопка оформления -->
        <form id="checkoutForm" action="/checkout" method="get" class="mb-4">
          <button type="submit"
                  class="w-full bg-gradient-to-r from-red-500 to-pink-500 text-white font-semibold px-8 py-4 rounded-2xl hover:shadow-lg hover:shadow-red-500/25 transition-all hover:scale-[1.02] flex items-center justify-center space-x-3 text-lg">
            <span class="material-icons-round">payment</span>
            <span>Оформить заказ</span>
            <span class="material-icons-round">arrow_forward</span>
          </button>
        </form>

        <!-- Дополнительные действия -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <a href="/catalog"
             class="flex items-center justify-center py-3 bg-gradient-to-r from-gray-100 to-gray-200 text-gray-700 rounded-2xl font-medium hover:from-gray-200 hover:to-gray-300 transition-all space-x-2">
            <span class="material-icons-round">arrow_back</span>
            <span>Продолжить покупки</span>
          </a>
          <form action="/cart/clear" method="post" class="w-full">
            <button type="submit"
                    class="w-full flex items-center justify-center py-3 bg-gradient-to-r from-red-100 to-pink-100 text-red-600 rounded-2xl font-medium hover:from-red-200 hover:to-pink-200 transition-all space-x-2">
              <span class="material-icons-round">clear_all</span>
              <span>Очистить корзину</span>
            </button>
          </form>
        </div>
      </div>
    </div>

    <!-- Преимущества (дополнительная информация) -->
    <div class="px-4 mt-6">
      <div class="bg-gradient-to-r from-emerald-50 to-teal-50 rounded-3xl p-6">
        <h3 class="font-bold text-gray-800 text-center mb-4">🚀 Быстрая доставка</h3>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-center">
          <div>
            <div class="w-12 h-12 bg-gradient-to-br from-emerald-400 to-teal-500 rounded-2xl flex items-center justify-center mx-auto mb-2">
              <span class="material-icons-round text-white">flash_on</span>
            </div>
            <p class="text-sm text-gray-600 font-medium">За 1 час</p>
          </div>
          <div>
            <div class="w-12 h-12 bg-gradient-to-br from-green-400 to-emerald-500 rounded-2xl flex items-center justify-center mx-auto mb-2">
              <span class="material-icons-round text-white">eco</span>
            </div>
            <p class="text-sm text-gray-600 font-medium">100% натуральные</p>
          </div>
          <div>
            <div class="w-12 h-12 bg-gradient-to-br from-blue-400 to-cyan-500 rounded-2xl flex items-center justify-center mx-auto mb-2">
              <span class="material-icons-round text-white">ac_unit</span>
            </div>
            <p class="text-sm text-gray-600 font-medium">Всегда свежие</p>
          </div>
        </div>
      </div>
    </div>

  <?php endif; ?>
</main>
