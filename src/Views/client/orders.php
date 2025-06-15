<?php /** @var array $orders @var string|null $userName */ ?>

<main class="bg-gradient-to-br from-orange-50 via-white to-pink-50 min-h-screen pb-24">

  <!-- Hero Header -->
  <div class="pt-6 px-4 mb-6">
    <div class="bg-gradient-to-r from-blue-500 to-indigo-500 rounded-3xl p-6 text-white shadow-2xl relative overflow-hidden">
      <!-- Декоративные элементы -->
      <div class="absolute top-0 right-0 w-32 h-32 bg-white/10 rounded-full -translate-y-16 translate-x-16"></div>
      <div class="absolute bottom-0 left-0 w-24 h-24 bg-white/10 rounded-full translate-y-12 -translate-x-12"></div>
      
      <div class="relative z-10">
        <div class="flex justify-between items-center mb-4">
          <div class="flex items-center space-x-3">
            <div class="w-12 h-12 bg-white/20 backdrop-blur-sm rounded-2xl flex items-center justify-center">
              <span class="material-icons-round text-2xl">receipt_long</span>
            </div>
            <div>
              <h1 class="text-2xl font-bold">Мои заказы</h1>
              <p class="text-blue-100 text-sm">История ваших покупок</p>
            </div>
          </div>
          <?php if ($userName): ?>
            <div class="text-right">
              <div class="inline-flex items-center px-3 py-2 bg-white/20 backdrop-blur-sm rounded-full text-sm">
                <span class="material-icons-round mr-2 text-lg">person</span>
                <span class="font-medium"><?= htmlspecialchars($userName) ?></span>
              </div>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <div class="px-4">
    <?php if (empty($orders)): ?>
      <!-- Пустое состояние -->
      <div class="bg-white rounded-3xl p-12 text-center shadow-lg">
        <div class="w-24 h-24 bg-gradient-to-br from-gray-100 to-gray-200 rounded-3xl flex items-center justify-center mx-auto mb-6">
          <span class="material-icons-round text-5xl text-gray-400">shopping_bag</span>
        </div>
        <h3 class="text-2xl font-bold text-gray-600 mb-3">Заказов пока нет</h3>
        <p class="text-gray-500 mb-8 max-w-sm mx-auto">Время сделать первый заказ! Свежие ягоды и фрукты ждут вас в каталоге.</p>
        
        <div class="space-y-3">
          <a href="/catalog" 
             class="inline-flex items-center px-8 py-4 bg-gradient-to-r from-red-500 to-pink-500 text-white rounded-2xl font-semibold hover:shadow-lg hover:scale-105 transition-all space-x-3">
            <span class="material-icons-round">store</span>
            <span>Перейти в каталог</span>
            <span class="material-icons-round">arrow_forward</span>
          </a>
          
          <div class="pt-4">
            <a href="/" 
               class="inline-flex items-center text-gray-500 hover:text-gray-700 transition-colors space-x-2">
              <span class="material-icons-round">home</span>
              <span class="font-medium">На главную</span>
            </a>
          </div>
        </div>
      </div>

    <?php else: ?>
      <!-- Список заказов -->
      <div class="space-y-4">
        <?php foreach ($orders as $index => $o): ?>
          <?php $info = order_status_info($o['status']); ?>
          <a href="/orders/<?= $o['id'] ?>"
             class="block rounded-2xl shadow-lg hover:shadow-xl transition-all hover:-translate-y-1 overflow-hidden group <?= $info['bg'] ?>">
            
            <!-- Заголовок заказа -->
            <div class="p-6 pb-4">
              <div class="flex items-center justify-between mb-3">
                <div class="flex items-center space-x-3">
                  <div class="w-12 h-12 bg-gradient-to-br from-blue-400 to-indigo-500 rounded-2xl flex items-center justify-center">
                    <span class="material-icons-round text-white text-xl">shopping_bag</span>
                  </div>
                  <div>
                    <h3 class="font-bold text-gray-800 text-lg">Заказ #<?= $o['id'] ?></h3>
                    <p class="text-gray-500 text-sm"><?= date('d.m.Y в H:i', strtotime($o['created_at'])) ?></p>
                  </div>
                </div>
                
                <span class="material-icons-round text-gray-400 group-hover:text-gray-600 transition-colors">
                  chevron_right
                </span>
              </div>

              <!-- Статус заказа -->
              <div class="flex items-center justify-between">
                <div class="flex items-center space-x-2">
                  <?php
                  $status = $o['status'];
                  $statusConfig = [
                    'new' => ['bg-red-100', 'text-red-800', 'fiber_new', 'Новый заказ'],
                    'processing' => ['bg-yellow-100', 'text-yellow-800', 'hourglass_empty', 'Принят'],
                    'assigned' => ['bg-green-100', 'text-green-800', 'check_circle', 'Обработан'],
                    'delivered' => ['bg-gray-200', 'text-gray-800', 'done_all', 'Выполнен'],
                    'cancelled' => ['bg-gray-50', 'text-gray-500', 'cancel', 'Отменен']
                  ];
                  $config = $statusConfig[$status] ?? ['bg-gray-100', 'text-gray-800', 'help', $status];
                  ?>
                  
                  <span class="inline-flex items-center px-3 py-1.5 rounded-full text-sm font-medium <?= $config[0] ?> <?= $config[1] ?>">
                    <span class="material-icons-round mr-1.5 text-sm"><?= $config[2] ?></span>
                    <?= $config[3] ?>
                  </span>
                </div>
                
                <div class="text-right">
                  <div class="text-xl font-bold text-gray-800"><?= number_format($o['total_amount'], 0, '.', ' ') ?> ₽</div>
                </div>
              </div>
            </div>

            <!-- Прогресс-бар (для активных заказов) -->
            <?php if (in_array($status, ['new', 'processing', 'assigned'])): ?>
              <div class="px-6 pb-4">
                <div class="bg-gray-100 rounded-full h-2 overflow-hidden">
                  <?php
                  $progress = [
                    'new' => 33,
                    'processing' => 66,
                    'assigned' => 100
                  ][$status] ?? 0;
                  ?>
                  <div class="h-full bg-gradient-to-r from-blue-500 to-indigo-500 rounded-full transition-all duration-1000" 
                       style="width: <?= $progress ?>%"></div>
                </div>
              </div>
            <?php endif; ?>

            <!-- Нижняя полоса с дополнительной информацией -->
            <div class="bg-gradient-to-r from-gray-50 to-blue-50 px-6 py-3 border-t border-gray-100">
              <div class="flex items-center justify-between text-sm">
                <span class="text-gray-600 flex items-center">
                  <span class="material-icons-round mr-1 text-sm">access_time</span>
                  <?= date('d.m.Y', strtotime($o['created_at'])) ?>
                </span>
                <span class="text-blue-600 font-medium flex items-center">
                  Подробнее
                  <span class="material-icons-round ml-1 text-sm">arrow_forward</span>
                </span>
              </div>
            </div>
          </a>
        <?php endforeach; ?>
      </div>

      <!-- Действия внизу -->
      <div class="mt-8 bg-white rounded-2xl shadow-lg p-6">
        <h3 class="font-bold text-gray-800 mb-4 text-center">Что-то еще?</h3>
        <div class="grid grid-cols-2 gap-3">
          <a href="/catalog"
             class="flex items-center justify-center px-4 py-3 bg-gradient-to-r from-red-500 to-pink-500 text-white rounded-2xl font-medium hover:shadow-lg transition-all space-x-2">
            <span class="material-icons-round text-lg">store</span>
            <span>Каталог</span>
          </a>
          <a href="/profile"
             class="flex items-center justify-center px-4 py-3 bg-gray-100 text-gray-700 rounded-2xl font-medium hover:bg-gray-200 transition-all space-x-2">
            <span class="material-icons-round text-lg">person</span>
            <span>Профиль</span>
          </a>
        </div>
      </div>
    <?php endif; ?>
  </div>

</main>