<?php /**
 * @var array $saleProducts
 * @var array $regularProducts
 * @var array $sellerProducts
 * @var array $preorderProducts
 * @var string|null $userName
 */ ?>

<main class="bg-gradient-to-br from-orange-50 via-white to-pink-50 min-h-screen pb-24">

  <div class="pt-6 px-4">
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
      <section class="relative overflow-hidden bg-gradient-to-br from-red-500 via-pink-500 to-rose-400 text-white rounded-3xl shadow-2xl p-8 mb-6 lg:col-span-2">
        <!-- Декоративные элементы -->
        <div class="absolute top-0 right-0 w-32 h-32 bg-white/10 rounded-full -translate-y-16 translate-x-16"></div>
        <div class="absolute bottom-0 left-0 w-24 h-24 bg-white/10 rounded-full translate-y-12 -translate-x-12"></div>

        <div class="relative z-10 text-center">

          <div class="mb-6">
            <h1 class="text-4xl font-bold mb-3 leading-tight">
              Добро пожаловать в
              <span class="bg-gradient-to-r from-yellow-200 to-orange-200 bg-clip-text text-transparent">BerryGo</span>
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

      <?php if (!empty($materials)): ?>
      <section class="hidden md:block lg:col-span-1">
        <div class="embla embla-news embla--fade">
          <div class="embla__viewport">
            <div class="embla__container eq-row">
              <?php foreach ($materials as $m): ?>
                <div class="embla__slide">
                  <?php $material = $m; include __DIR__ . '/_material_card.php'; ?>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
          <div class="embla__controls">
            <div class="embla__dots"></div>
          </div>
        </div>
      </section>
      <?php endif; ?>
    </div>
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
    <h2 class="text-2xl font-bold text-gray-800 mb-4">💥 Наши спецпредложения</h2>
    <div class="embla drag-free has-arrows relative">
      <button data-dir="left" class="hidden md:flex items-center justify-center w-8 h-8 absolute left-0 top-1/2 -translate-y-1/2 bg-white shadow rounded-full z-10 hover:bg-gray-100">
        <span class="material-icons-round text-gray-600">chevron_left</span>
      </button>
      <button data-dir="right" class="hidden md:flex items-center justify-center w-8 h-8 absolute right-0 top-1/2 -translate-y-1/2 bg-white shadow rounded-full z-10 hover:bg-gray-100">
        <span class="material-icons-round text-gray-600">chevron_right</span>
      </button>
      <div class="embla__viewport">
        <div class="embla__container space-x-4 pb-2 no-scrollbar eq-row">
          <?php foreach ($saleProducts as $p): ?>
            <div class="embla__slide flex-none w-[52vw] sm:w-1/2 md:w-1/3">
              <?php include __DIR__ . '/_card.php'; ?>
            </div>
          <?php endforeach; ?>
          <div class="embla__slide flex-none w-[52vw] sm:w-1/2 md:w-1/3">
            <div class="h-full flex items-center justify-center bg-red-50 rounded-2xl shadow-lg p-4 text-center">
              <p class="text-sm font-semibold text-red-800">Акционная клубника в Красноярске: купите спелую фермерскую ягоду со скидкой до 25 %! Лучшие сорта Клери и Черный принц по невероятно выгодным ценам. Успейте заказать сегодня — акция действует до конца недели, пока ягоды не разобрали! 🍓</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- Regular Delivery Products -->
  <section class="px-4 mb-8">
    <h2 class="text-2xl font-bold text-gray-800 mb-4">🚚 Регулярные поставки</h2>
    <div class="embla drag-free has-arrows relative">
      <button data-dir="left" class="hidden md:flex items-center justify-center w-8 h-8 absolute left-0 top-1/2 -translate-y-1/2 bg-white shadow rounded-full z-10 hover:bg-gray-100">
        <span class="material-icons-round text-gray-600">chevron_left</span>
      </button>
      <button data-dir="right" class="hidden md:flex items-center justify-center w-8 h-8 absolute right-0 top-1/2 -translate-y-1/2 bg-white shadow rounded-full z-10 hover:bg-gray-100">
        <span class="material-icons-round text-gray-600">chevron_right</span>
      </button>
      <div class="embla__viewport">
        <div class="embla__container space-x-4 pb-2 no-scrollbar eq-row">
          <?php foreach ($regularProducts as $p): ?>
            <div class="embla__slide flex-none w-[52vw] sm:w-1/2 md:w-1/3">
              <?php include __DIR__ . '/_card.php'; ?>
            </div>
          <?php endforeach; ?>
          <div class="embla__slide flex-none w-[52vw] sm:w-1/2 md:w-1/3">
            <div class="h-full flex items-center justify-center bg-green-50 rounded-2xl shadow-lg p-4 text-center">
              <p class="text-sm font-semibold text-green-800">Клубника в наличии в Красноярске: мгновенная доставка за 24 ч — прямо с фермы к вашему столу! Сорта Клери и Черный принц в фасовках от 1 кг. Купите клубнику онлайн с удобной оплатой и гарантий качества каждой ягодки. 🍓🚀</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- Seller Products -->
  <section class="px-4 mb-8">
    <h2 class="text-2xl font-bold text-gray-800 mb-4">🤝 Товары от селлеров</h2>
    <div class="embla drag-free has-arrows relative">
      <button data-dir="left" class="hidden md:flex items-center justify-center w-8 h-8 absolute left-0 top-1/2 -translate-y-1/2 bg-white shadow rounded-full z-10 hover:bg-gray-100">
        <span class="material-icons-round text-gray-600">chevron_left</span>
      </button>
      <button data-dir="right" class="hidden md:flex items-center justify-center w-8 h-8 absolute right-0 top-1/2 -translate-y-1/2 bg-white shadow rounded-full z-10 hover:bg-gray-100">
        <span class="material-icons-round text-gray-600">chevron_right</span>
      </button>
      <div class="embla__viewport">
        <div class="embla__container space-x-4 pb-2 no-scrollbar eq-row">
          <?php foreach ($sellerProducts as $p): ?>
            <div class="embla__slide flex-none w-[52vw] sm:w-1/2 md:w-1/3">
              <?php include __DIR__ . '/_card.php'; ?>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </section>

  <!-- Preorder Products -->
  <section class="px-4 mb-8">
    <h2 class="text-2xl font-bold text-gray-800 mb-4">🛒 Товары под заказ</h2>
    <div class="embla drag-free has-arrows relative">
      <button data-dir="left" class="hidden md:flex items-center justify-center w-8 h-8 absolute left-0 top-1/2 -translate-y-1/2 bg-white shadow rounded-full z-10 hover:bg-gray-100">
        <span class="material-icons-round text-gray-600">chevron_left</span>
      </button>
      <button data-dir="right" class="hidden md:flex items-center justify-center w-8 h-8 absolute right-0 top-1/2 -translate-y-1/2 bg-white shadow rounded-full z-10 hover:bg-gray-100">
        <span class="material-icons-round text-gray-600">chevron_right</span>
      </button>
      <div class="embla__viewport">
        <div class="embla__container space-x-4 pb-2 no-scrollbar eq-row">
          <?php foreach ($preorderProducts as $p): ?>
            <div class="embla__slide flex-none w-[52vw] sm:w-1/2 md:w-1/3">
              <?php include __DIR__ . '/_card.php'; ?>
            </div>
          <?php endforeach; ?>
          <div class="embla__slide flex-none w-[52vw] sm:w-1/2 md:w-1/3">
            <div class="h-full flex items-center justify-center bg-blue-50 rounded-2xl shadow-lg p-4 text-center">
              <p class="text-sm font-semibold text-blue-800">Клубника другие ягоды и фрукты под заказ с доставкой в Красноярске: эксклюзивные сорта и объёмы от 1 кг. Идеально для праздников, корпоративов и подарков! Заранее выберите свой идеальный набор — индивидуальная упаковка, свежесть гарантирована, доставка в удобное время. 🍓✨</p>
            </div>
          </div>
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
          <p class="text-sm text-gray-600">Доставляем свежие ягоды за 1 час по Красноярску</p>
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