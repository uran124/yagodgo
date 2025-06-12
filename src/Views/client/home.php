<?php /**
 * @var array $saleProducts
 * @var array $inStockProducts
 * @var array $preorderProducts
 * @var string|null $userName
 */ ?>

<main class="bg-gradient-to-br from-orange-50 via-white to-pink-50 min-h-screen pb-24">
  
  <!-- Hero Section -->
  <div class="pt-6 px-4">
    <section class="relative overflow-hidden bg-gradient-to-br from-red-500 via-pink-500 to-rose-400 text-white rounded-3xl shadow-2xl p-8 mb-6">
      <!-- Декоративные элементы -->
      <div class="absolute top-0 right-0 w-32 h-32 bg-white/10 rounded-full -translate-y-16 translate-x-16"></div>
      <div class="absolute bottom-0 left-0 w-24 h-24 bg-white/10 rounded-full translate-y-12 -translate-x-12"></div>
      
      <div class="relative z-10 text-center">
        
        <div class="mb-6">
          <h1 class="text-4xl font-bold mb-3 leading-tight">
            Добро пожаловать в 
            <span class="bg-gradient-to-r from-yellow-200 to-orange-200 bg-clip-text text-transparent">ЯгодGO</span>
          </h1>
          <p class="text-lg opacity-90 mb-2">Свежие ягоды и фрукты из Киргизии</p>
          <p class="text-sm opacity-75">🚀 Доставка за час • 🍓 100% натуральные • ❄️ Всегда свежие</p>
        </div>
        
        <a href="/catalog"
           class="inline-flex items-center px-8 py-4 bg-white text-red-500 font-semibold rounded-2xl hover:bg-gray-50 transition-all shadow-lg hover:shadow-xl hover:scale-105 space-x-3">
          <span class="material-icons-round">store</span>
          <span>Открыть каталог</span>
          <span class="material-icons-round">arrow_forward</span>
        </a>
      </div>
    </section>
  </div>

  <!-- Быстрые категории 
  <section class="px-4 mb-8">
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
      <a href="/category/berries" class="group bg-white rounded-2xl p-6 shadow-lg hover:shadow-xl transition-all hover:-translate-y-1">
        <div class="w-16 h-16 bg-gradient-to-br from-red-400 to-pink-500 rounded-2xl flex items-center justify-center mb-4 mx-auto group-hover:scale-110 transition-transform">
          <span class="text-3xl">🍓</span>
        </div>
        <h3 class="text-center font-semibold text-gray-800 mb-1">Ягоды</h3>
        <p class="text-center text-xs text-gray-500">Клубника, малина, черника</p>
      </a>
      
      <a href="/category/fruits" class="group bg-white rounded-2xl p-6 shadow-lg hover:shadow-xl transition-all hover:-translate-y-1">
        <div class="w-16 h-16 bg-gradient-to-br from-orange-400 to-yellow-500 rounded-2xl flex items-center justify-center mb-4 mx-auto group-hover:scale-110 transition-transform">
          <span class="text-3xl">🍑</span>
        </div>
        <h3 class="text-center font-semibold text-gray-800 mb-1">Фрукты</h3>
        <p class="text-center text-xs text-gray-500">Персики, абрикосы, сливы</p>
      </a>
      
      <a href="/category/exotic" class="group bg-white rounded-2xl p-6 shadow-lg hover:shadow-xl transition-all hover:-translate-y-1">
        <div class="w-16 h-16 bg-gradient-to-br from-emerald-400 to-teal-500 rounded-2xl flex items-center justify-center mb-4 mx-auto group-hover:scale-110 transition-transform">
          <span class="text-3xl">🥝</span>
        </div>
        <h3 class="text-center font-semibold text-gray-800 mb-1">Экзотика</h3>
        <p class="text-center text-xs text-gray-500">Киви, манго, авокадо</p>
      </a>
      
      <a href="/category/dried" class="group bg-white rounded-2xl p-6 shadow-lg hover:shadow-xl transition-all hover:-translate-y-1">
        <div class="w-16 h-16 bg-gradient-to-br from-amber-400 to-orange-500 rounded-2xl flex items-center justify-center mb-4 mx-auto group-hover:scale-110 transition-transform">
          <span class="text-3xl">🥜</span>
        </div>
        <h3 class="text-center font-semibold text-gray-800 mb-1">Сухофрукты</h3>
        <p class="text-center text-xs text-gray-500">Курага, изюм, финики</p>
      </a>
    </div>
  </section>-->

  <!-- Sale Products -->
  <section class="px-4 mb-8">
    <h2 class="text-2xl font-bold text-gray-800 mb-4">💥 Товары со скидкой</h2>
    <div class="flex space-x-4 overflow-x-auto pb-2 no-scrollbar snap-x snap-mandatory">
      <?php foreach ($saleProducts as $p): ?>
        <div class="flex-none w-[66vw] sm:w-1/2 md:w-1/3 snap-start">
          <?php include __DIR__ . '/_card.php'; ?>
        </div>
      <?php endforeach; ?>
      <div class="flex-none w-[66vw] sm:w-1/2 md:w-1/3 snap-start">
        <div class="h-full flex items-center justify-center bg-white rounded-2xl shadow-lg p-4 text-center">
          <p class="text-sm text-gray-600">Узнайте о наших горячих предложениях!</p>
        </div>
      </div>
    </div>
  </section>

  <!-- In Stock Products -->
  <section class="px-4 mb-8">
    <h2 class="text-2xl font-bold text-gray-800 mb-4">📦 В наличии</h2>
    <div class="flex space-x-4 overflow-x-auto pb-2 no-scrollbar snap-x snap-mandatory">
      <?php foreach ($inStockProducts as $p): ?>
        <div class="flex-none w-[66vw] sm:w-1/2 md:w-1/3 snap-start">
          <?php include __DIR__ . '/_card.php'; ?>
        </div>
      <?php endforeach; ?>
      <div class="flex-none w-[66vw] sm:w-1/2 md:w-1/3 snap-start">
        <div class="h-full flex items-center justify-center bg-white rounded-2xl shadow-lg p-4 text-center">
          <p class="text-sm text-gray-600">Все товары из этого раздела готовы к отправке.</p>
        </div>
      </div>
    </div>
  </section>

  <!-- Preorder Products -->
  <section class="px-4 mb-8">
    <h2 class="text-2xl font-bold text-gray-800 mb-4">🛒 Под заказ</h2>
    <div class="flex space-x-4 overflow-x-auto pb-2 no-scrollbar snap-x snap-mandatory">
      <?php foreach ($preorderProducts as $p): ?>
        <div class="flex-none w-[66vw] sm:w-1/2 md:w-1/3 snap-start">
          <?php include __DIR__ . '/_card.php'; ?>
        </div>
      <?php endforeach; ?>
      <div class="flex-none w-[66vw] sm:w-1/2 md:w-1/3 snap-start">
        <div class="h-full flex items-center justify-center bg-white rounded-2xl shadow-lg p-4 text-center">
          <p class="text-sm text-gray-600">Здесь собраны товары, которые мы привезём под заказ.</p>
        </div>
      </div>
    </div>
  </section>

  <!-- Преимущества -->
  <section class="px-4 mb-8">
    <div class="bg-gradient-to-r from-emerald-50 to-teal-50 rounded-3xl p-8">
      <h2 class="text-2xl font-bold text-gray-800 text-center mb-8">Почему выбирают нас?</h2>
      <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="text-center">
          <div class="w-16 h-16 bg-gradient-to-br from-emerald-400 to-teal-500 rounded-2xl flex items-center justify-center mx-auto mb-4">
            <span class="material-icons-round text-2xl text-white">flash_on</span>
          </div>
          <h3 class="font-semibold text-gray-800 mb-2">Быстрая доставка</h3>
          <p class="text-sm text-gray-600">Доставляем свежие ягоды за 1 час по всему Бишкеку</p>
        </div>
        
        <div class="text-center">
          <div class="w-16 h-16 bg-gradient-to-br from-green-400 to-emerald-500 rounded-2xl flex items-center justify-center mx-auto mb-4">
            <span class="material-icons-round text-2xl text-white">eco</span>
          </div>
          <h3 class="font-semibold text-gray-800 mb-2">100% натуральные</h3>
          <p class="text-sm text-gray-600">Только экологически чистые продукты без химии</p>
        </div>
        
        <div class="text-center">
          <div class="w-16 h-16 bg-gradient-to-br from-blue-400 to-cyan-500 rounded-2xl flex items-center justify-center mx-auto mb-4">
            <span class="material-icons-round text-2xl text-white">ac_unit</span>
          </div>
          <h3 class="font-semibold text-gray-800 mb-2">Всегда свежие</h3>
          <p class="text-sm text-gray-600">Строгий контроль качества и холодная цепь поставок</p>
        </div>
      </div>
    </div>
  </section>

</main>