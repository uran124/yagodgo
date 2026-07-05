<?php /**
 * @var array $saleProducts
 * @var array $regularProducts
 * @var array $sellerProducts
 * @var array $preorderProducts
 * @var array $discountProducts
 * @var array $materialCategories
 * @var string|null $userName
 */ ?>

<main class="bg-gradient-to-br from-orange-50 via-white to-pink-50 min-h-screen pb-24">

  <div class="pt-4 px-4">
    <div class="grid grid-cols-1 gap-4 md:gap-6">

      <!-- Hero — компактный на мобиле, полный на десктопе -->
      <section class="relative overflow-hidden bg-gradient-to-br from-red-500 via-pink-500 to-rose-400 accent-gradient-via text-white rounded-2xl md:rounded-3xl shadow-xl md:shadow-2xl p-5 md:p-8">
        <div class="absolute top-0 right-0 w-24 h-24 md:w-32 md:h-32 bg-white/10 rounded-full -translate-y-12 translate-x-12 md:-translate-y-16 md:translate-x-16"></div>
        <div class="absolute bottom-0 left-0 w-16 h-16 md:w-24 md:h-24 bg-white/10 rounded-full translate-y-8 -translate-x-8 md:translate-y-12 md:-translate-x-12"></div>

        <div class="relative z-10 flex items-center justify-between md:block md:text-center">
          <!-- Текст -->
          <div class="flex-1 md:mb-6">
            <h1 class="text-xl md:text-4xl font-bold leading-tight mb-1 md:mb-3">
              <span class="bg-gradient-to-r from-yellow-200 to-orange-200 bg-clip-text text-transparent">BerryGo</span>
              <span class="hidden md:inline"> — доставка свежих ягод и фруктов в Красноярске</span>
              <span class="md:hidden"> — ягоды в Красноярске</span>
            </h1>
            <p class="text-sm md:text-lg opacity-90 mb-1 md:mb-2">Клубника, черешня, сезонные ягоды и фрукты из Киргизии с доставкой на дом</p>
            <p class="hidden md:block text-sm opacity-75">🚀 Доставка за час • 🍓 100% натуральные • ❄️ Всегда свежие</p>
            <!-- Теги только на мобиле, горизонтально -->
            <div class="flex gap-2 mt-1.5 md:hidden flex-wrap">
              <span class="text-[10px] bg-white/20 rounded-full px-2 py-0.5">🚀 За час</span>
              <span class="text-[10px] bg-white/20 rounded-full px-2 py-0.5">🍓 Натуральные</span>
              <span class="text-[10px] bg-white/20 rounded-full px-2 py-0.5">❄️ Свежие</span>
            </div>
          </div>

          <!-- Кнопка: иконка на мобиле, полная на десктопе -->
          <a href="/catalog"
             class="ml-3 shrink-0 md:ml-0 md:inline-flex items-center px-8 py-4 bg-white text-red-500 font-semibold rounded-2xl hover:bg-gray-50 transition-all shadow-lg hover:shadow-xl hover:scale-105 space-x-3
                    flex items-center justify-center w-12 h-12 md:w-auto md:h-auto rounded-xl md:rounded-2xl">
            <span class="material-icons-round text-xl md:text-base">store</span>
            <span class="hidden md:inline">Открыть каталог</span>
            <span class="hidden md:inline material-icons-round">arrow_forward</span>
          </a>
        </div>
      </section>

    </div>
  </div>

  <?php if (!empty($saleProducts)): ?>
    <section class="px-4 mb-8 mt-6">
      <h2 class="text-xl md:text-2xl font-bold text-gray-800 mb-4">💥 Акции</h2>
      <div class="embla drag-free has-arrows relative">
        <button data-dir="left" class="hidden items-center justify-center w-8 h-8 absolute left-0 top-1/2 -translate-y-1/2 bg-white shadow rounded-full z-10 hover:bg-gray-100">
          <span class="material-icons-round text-gray-600">chevron_left</span>
        </button>
        <button data-dir="right" class="hidden items-center justify-center w-8 h-8 absolute right-0 top-1/2 -translate-y-1/2 bg-white shadow rounded-full z-10 hover:bg-gray-100">
          <span class="material-icons-round text-gray-600">chevron_right</span>
        </button>
        <div class="embla__viewport">
          <div class="embla__container space-x-4 pb-2 no-scrollbar eq-row">
            <?php foreach ($saleProducts as $p): ?>
              <div class="embla__slide flex-none w-[64vw] sm:w-1/2 md:w-[350px]">
                <?php $cardSection = 'sale'; ?>
                <?php include __DIR__ . '/_card.php'; ?>
              </div>
            <?php endforeach; ?>
            <div class="embla__slide flex-none w-[64vw] sm:w-1/2 md:w-[350px]">
              <div class="h-full flex items-center justify-center bg-red-50 rounded-2xl shadow-lg p-4 text-center">
                <p class="text-sm font-semibold text-red-800">Акционная клубника в Красноярске: купите спелую фермерскую ягоду со скидкой до 25 %! Лучшие сорта Клери и Черный принц по невероятно выгодным ценам. Успейте заказать сегодня — акция действует до конца недели, пока ягоды не разобрали! 🍓</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>
  <?php endif; ?>

  <section class="px-4 mb-8">
    <h2 class="text-xl md:text-2xl font-bold text-gray-800 mb-4">🚚 В наличии</h2>
    <?php $noStockMessage = (string)(get_setting('ui_home_no_stock_message', 'На данный момент ягод нет в наличии. Воспользуйтесь нашим предложением предварительного заказа со скидкой 10% — это дополнительная скидка за оформление предварительного бронирования.') ?? ''); ?>
    <?php if (!empty($regularProducts)): ?>
      <div class="embla drag-free has-arrows relative">
        <button data-dir="left" class="hidden items-center justify-center w-8 h-8 absolute left-0 top-1/2 -translate-y-1/2 bg-white shadow rounded-full z-10 hover:bg-gray-100">
          <span class="material-icons-round text-gray-600">chevron_left</span>
        </button>
        <button data-dir="right" class="hidden items-center justify-center w-8 h-8 absolute right-0 top-1/2 -translate-y-1/2 bg-white shadow rounded-full z-10 hover:bg-gray-100">
          <span class="material-icons-round text-gray-600">chevron_right</span>
        </button>
        <div class="embla__viewport">
          <div class="embla__container space-x-4 pb-2 no-scrollbar eq-row">
            <?php foreach ($regularProducts as $p): ?>
              <div class="embla__slide flex-none w-[64vw] sm:w-1/2 md:w-[350px]">
                <?php $cardSection = 'in_stock'; ?>
                <?php include __DIR__ . '/_card.php'; ?>
              </div>
            <?php endforeach; ?>
            <div class="embla__slide flex-none w-[64vw] sm:w-1/2 md:w-[350px]">
              <div class="h-full flex items-center justify-center bg-green-50 rounded-2xl shadow-lg p-4 text-center">
                <p class="text-sm font-semibold text-green-800">Клубника в наличии в Красноярске: мгновенная доставка за 24 ч — прямо с фермы к вашему столу! Сорта Клери и Черный принц в фасовках от 1 кг. 🍓🚀</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    <?php else: ?>
      <div class="bg-amber-50 border border-amber-200 rounded-2xl shadow-sm p-4 md:p-6 text-amber-900">
        <p class="text-sm md:text-base font-medium"><?= htmlspecialchars($noStockMessage) ?></p>
      </div>
    <?php endif; ?>
  </section>

  <?php if (!empty($sellerProducts)): ?>
    <section class="px-4 mb-8">
      <h2 class="text-xl md:text-2xl font-bold text-gray-800 mb-4">🤝 Товары от селлеров</h2>
      <div class="embla drag-free has-arrows relative">
        <button data-dir="left" class="hidden items-center justify-center w-8 h-8 absolute left-0 top-1/2 -translate-y-1/2 bg-white shadow rounded-full z-10 hover:bg-gray-100">
          <span class="material-icons-round text-gray-600">chevron_left</span>
        </button>
        <button data-dir="right" class="hidden items-center justify-center w-8 h-8 absolute right-0 top-1/2 -translate-y-1/2 bg-white shadow rounded-full z-10 hover:bg-gray-100">
          <span class="material-icons-round text-gray-600">chevron_right</span>
        </button>
        <div class="embla__viewport">
          <div class="embla__container space-x-4 pb-2 no-scrollbar eq-row">
            <?php foreach ($sellerProducts as $p): ?>
              <div class="embla__slide flex-none w-[64vw] sm:w-1/2 md:w-[350px]">
                <?php $cardSection = 'seller'; ?>
                <?php include __DIR__ . '/_card.php'; ?>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </section>
  <?php endif; ?>

  <?php if (!empty($preorderProducts)): ?>
    <section class="px-4 mb-8">
      <h2 class="text-xl md:text-2xl font-bold text-gray-800 mb-4">🛒 Предварительный заказ -10%</h2>
      <div class="embla drag-free has-arrows relative">
        <button data-dir="left" class="hidden items-center justify-center w-8 h-8 absolute left-0 top-1/2 -translate-y-1/2 bg-white shadow rounded-full z-10 hover:bg-gray-100">
          <span class="material-icons-round text-gray-600">chevron_left</span>
        </button>
        <button data-dir="right" class="hidden items-center justify-center w-8 h-8 absolute right-0 top-1/2 -translate-y-1/2 bg-white shadow rounded-full z-10 hover:bg-gray-100">
          <span class="material-icons-round text-gray-600">chevron_right</span>
        </button>
        <div class="embla__viewport">
          <div class="embla__container space-x-4 pb-2 no-scrollbar eq-row">
            <?php foreach ($preorderProducts as $p): ?>
              <div class="embla__slide flex-none w-[64vw] sm:w-1/2 md:w-[350px]">
                <?php $cardSection = 'preorder'; ?>
                <?php include __DIR__ . '/_card.php'; ?>
              </div>
            <?php endforeach; ?>
            <div class="embla__slide flex-none w-[64vw] sm:w-1/2 md:w-[350px]">
              <div class="h-full flex items-center justify-center bg-blue-50 rounded-2xl shadow-lg p-4 text-center">
                <p class="text-sm font-semibold text-blue-800">Клубника и другие ягоды под заказ с доставкой в Красноярске: эксклюзивные сорта от 1 кг. Идеально для праздников и корпоративов! 🍓✨</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>
  <?php endif; ?>

  <?php if (!empty($discountProducts)): ?>
    <section class="px-4 mb-8">
      <h2 class="text-xl md:text-2xl font-bold text-gray-800 mb-4">🏷️ Выгодные остатки</h2>
      <div class="embla drag-free has-arrows relative">
        <button data-dir="left" class="hidden items-center justify-center w-8 h-8 absolute left-0 top-1/2 -translate-y-1/2 bg-white shadow rounded-full z-10 hover:bg-gray-100">
          <span class="material-icons-round text-gray-600">chevron_left</span>
        </button>
        <button data-dir="right" class="hidden items-center justify-center w-8 h-8 absolute right-0 top-1/2 -translate-y-1/2 bg-white shadow rounded-full z-10 hover:bg-gray-100">
          <span class="material-icons-round text-gray-600">chevron_right</span>
        </button>
        <div class="embla__viewport">
          <div class="embla__container space-x-4 pb-2 no-scrollbar eq-row">
            <?php foreach ($discountProducts as $p): ?>
              <div class="embla__slide flex-none w-[64vw] sm:w-1/2 md:w-[350px]">
                <?php $cardSection = 'sale'; ?>
                <?php include __DIR__ . '/_card.php'; ?>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </section>
  <?php endif; ?>

  <?php if (!empty($materials)): ?>
    <section class="px-4 mb-8" aria-labelledby="home-materials-title">
      <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-3 mb-4">
        <div class="max-w-3xl">
          <h2 id="home-materials-title" class="text-xl md:text-2xl font-bold text-gray-800">Полезные материалы о ягодах и фруктах</h2>
          <p class="text-sm text-gray-600 mt-1 leading-relaxed">
            Рассказываем, как выбрать свежую клубнику, черешню и другие сезонные ягоды в Красноярске, как хранить фрукты после доставки и какие сорта лучше подходят для десертов, заготовок и подарков.
          </p>
        </div>
        <a href="/content" class="inline-flex items-center text-sm font-semibold text-red-500 hover:text-red-600">
          Все материалы
          <span class="material-icons-round text-base ml-1">arrow_forward</span>
        </a>
      </div>
      <?php if (!empty($materialCategories)): ?>
        <nav class="flex gap-2 overflow-x-auto no-scrollbar pb-3 mb-3" aria-label="Разделы материалов">
          <a href="/content" class="flex-none rounded-full bg-red-500 px-3 py-1.5 text-xs font-semibold text-white shadow-sm">Все материалы</a>
          <?php foreach ($materialCategories as $category): ?>
            <a href="/content/<?= urlencode((string)$category['alias']) ?>" class="flex-none rounded-full bg-white px-3 py-1.5 text-xs font-semibold text-gray-600 shadow-sm ring-1 ring-gray-100 hover:text-red-500">
              <?= htmlspecialchars((string)$category['name']) ?>
              <span class="text-gray-400">(<?= (int)$category['materials_count'] ?>)</span>
            </a>
          <?php endforeach; ?>
        </nav>
      <?php endif; ?>
      <div class="embla drag-free has-arrows relative" itemscope itemtype="https://schema.org/ItemList">
        <button data-dir="left" class="hidden md:flex items-center justify-center w-8 h-8 absolute left-0 top-1/2 -translate-y-1/2 bg-white shadow rounded-full z-10 hover:bg-gray-100" aria-label="Предыдущие материалы">
          <span class="material-icons-round text-gray-600">chevron_left</span>
        </button>
        <button data-dir="right" class="hidden md:flex items-center justify-center w-8 h-8 absolute right-0 top-1/2 -translate-y-1/2 bg-white shadow rounded-full z-10 hover:bg-gray-100" aria-label="Следующие материалы">
          <span class="material-icons-round text-gray-600">chevron_right</span>
        </button>
        <div class="embla__viewport">
          <div class="embla__container space-x-4 pb-2 no-scrollbar eq-row">
            <?php foreach ($materials as $index => $m): ?>
              <div class="embla__slide flex-none w-[72vw] sm:w-1/2 md:w-1/3 lg:w-1/4" itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">
                <meta itemprop="position" content="<?= $index + 1 ?>">
                <meta itemprop="name" content="<?= htmlspecialchars((string)$m['title'], ENT_QUOTES) ?>">
                <meta itemprop="url" content="/content/<?= urlencode((string)$m['cat_alias']) ?>/<?= urlencode((string)$m['mat_alias']) ?>">
                <?php if (!empty($m['category_name'])): ?>
                  <a href="/content/<?= urlencode((string)$m['cat_alias']) ?>" class="mb-2 inline-flex rounded-full bg-red-50 px-2.5 py-1 text-xs font-semibold text-red-500 hover:bg-red-100">
                    <?= htmlspecialchars((string)$m['category_name']) ?>
                  </a>
                <?php endif; ?>
                <?php $material = $m; include __DIR__ . '/_material_card.php'; ?>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </section>
  <?php endif; ?>

  <!-- Преимущества — горизонтальный свайпер на мобиле, сетка на десктопе -->
  <section class="mb-8">
    <h2 class="text-xl md:text-2xl font-bold text-gray-800 mb-4 px-4">Почему выбирают нас?</h2>

    <!-- Мобиле: горизонтальный скролл без embla (простой overflow) -->
    <div class="md:hidden flex gap-3 overflow-x-auto no-scrollbar px-4 pb-2">

      <div class="flex-none w-52 bg-white rounded-2xl shadow-sm p-4 flex flex-col items-center text-center">
        <div class="w-12 h-12 bg-gradient-to-br from-emerald-400 to-teal-500 rounded-xl flex items-center justify-center mb-3">
          <span class="material-icons-round text-xl text-white">flash_on</span>
        </div>
        <h3 class="font-semibold text-gray-800 text-sm mb-1">Быстрая доставка</h3>
        <p class="text-xs text-gray-500 leading-relaxed">Доставляем свежие ягоды за 1 час по Красноярску</p>
      </div>

      <div class="flex-none w-52 bg-white rounded-2xl shadow-sm p-4 flex flex-col items-center text-center">
        <div class="w-12 h-12 bg-gradient-to-br from-green-400 to-emerald-500 rounded-xl flex items-center justify-center mb-3">
          <span class="material-icons-round text-xl text-white">eco</span>
        </div>
        <h3 class="font-semibold text-gray-800 text-sm mb-1">100% натуральные</h3>
        <p class="text-xs text-gray-500 leading-relaxed">Только экологически чистые продукты без химии</p>
      </div>

      <div class="flex-none w-52 bg-white rounded-2xl shadow-sm p-4 flex flex-col items-center text-center">
        <div class="w-12 h-12 bg-gradient-to-br from-blue-400 to-cyan-500 rounded-xl flex items-center justify-center mb-3">
          <span class="material-icons-round text-xl text-white">ac_unit</span>
        </div>
        <h3 class="font-semibold text-gray-800 text-sm mb-1">Всегда свежие</h3>
        <p class="text-xs text-gray-500 leading-relaxed">Строгий контроль качества и холодная цепь поставок</p>
      </div>

      <div class="flex-none w-52 bg-white rounded-2xl shadow-sm p-4 flex flex-col items-center text-center">
        <div class="w-12 h-12 bg-gradient-to-br from-orange-400 to-rose-500 rounded-xl flex items-center justify-center mb-3">
          <span class="material-icons-round text-xl text-white">favorite</span>
        </div>
        <h3 class="font-semibold text-gray-800 text-sm mb-1">Прямо с фермы</h3>
        <p class="text-xs text-gray-500 leading-relaxed">Фермерские поставки из Киргизии без посредников</p>
      </div>

    </div>

    <!-- Десктоп: обычная сетка с градиентным фоном -->
    <div class="hidden md:block px-4">
      <div class="bg-gradient-to-r from-emerald-50 to-teal-50 rounded-3xl p-8">
        <div class="grid grid-cols-3 gap-6">
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
    </div>
  </section>

</main>
