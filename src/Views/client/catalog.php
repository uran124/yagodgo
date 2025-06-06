<?php /** @var array $products @var string|null $userName */ ?>

<main class="bg-gradient-to-br from-orange-50 via-white to-pink-50 min-h-screen pb-24">

  <!-- Хедер с названием и приветствием -->
  <div class="pt-6 px-4 mb-6">
    <div class="bg-gradient-to-r from-red-500 to-pink-500 rounded-3xl p-6 text-white shadow-2xl flex flex-col md:flex-row justify-between items-center">
      <div class="mb-4 md:mb-0">
        <h1 class="text-3xl font-bold mb-1">🛒 Каталог</h1>
        <p class="text-red-100 text-sm">Свежие ягоды и фрукты из Киргизии</p>
      </div>
      <?php if ($userName): ?>
        <div class="inline-flex items-center bg-white/20 backdrop-blur-sm px-4 py-2 rounded-full">
          <span class="material-icons-round text-lg mr-2">person</span>
          <span class="font-medium"><?= htmlspecialchars($userName) ?></span>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Поле поиска и фильтры -->
  <div class="px-4 mb-6">
    <div class="bg-white rounded-2xl shadow-lg p-4 space-y-4">
      <div class="relative">
        <input type="text"
               placeholder="Поиск ягод и фруктов..."
               class="w-full pl-12 pr-4 py-3 border border-gray-200 rounded-2xl focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500 transition-all text-gray-700 placeholder-gray-400">
        <span class="material-icons-round absolute left-4 top-1/2 transform -translate-y-1/2 text-gray-400">search</span>
      </div>
      <div class="flex space-x-2 overflow-x-auto pb-2">
        <button class="flex-shrink-0 px-4 py-2 bg-red-500 text-white rounded-full text-sm font-medium hover:bg-red-600 transition-colors">Все</button>
        <button class="flex-shrink-0 px-4 py-2 bg-gray-100 text-gray-700 rounded-full text-sm font-medium hover:bg-gray-200 transition-colors">🍓 Ягоды</button>
        <button class="flex-shrink-0 px-4 py-2 bg-gray-100 text-gray-700 rounded-full text-sm font-medium hover:bg-gray-200 transition-colors">🍑 Фрукты</button>
        <button class="flex-shrink-0 px-4 py-2 bg-gray-100 text-gray-700 rounded-full text-sm font-medium hover:bg-gray-200 transition-colors">🥝 Экзотика</button>
        <button class="flex-shrink-0 px-4 py-2 bg-gray-100 text-gray-700 rounded-full text-sm font-medium hover:bg-gray-200 transition-colors">💰 Акции</button>
      </div>
    </div>
  </div>

  <!-- Сетка товаров -->
  <div class="px-4">
    <?php if (empty($products)): ?>
      <div class="bg-white rounded-3xl p-12 text-center shadow-lg">
        <div class="w-24 h-24 bg-gradient-to-br from-gray-100 to-gray-200 rounded-3xl flex items-center justify-center mx-auto mb-6">
          <span class="material-icons-round text-5xl text-gray-400">inventory_2</span>
        </div>
        <h3 class="text-2xl font-bold text-gray-600 mb-3">Каталог пока пуст</h3>
        <p class="text-gray-500 mb-6">Мы готовим для вас самые свежие ягоды и фрукты из Киргизии!</p>
        <a href="/" class="inline-flex items-center px-6 py-3 bg-gradient-to-r from-red-500 to-pink-500 text-white rounded-2xl font-medium hover:shadow-lg transition-all">
          <span class="material-icons-round mr-2">home</span>
          На главную
        </a>
      </div>
    <?php else: ?>
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php foreach ($products as $p): ?>
          <?php include __DIR__ . '/_card.php'; ?>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

</main>
