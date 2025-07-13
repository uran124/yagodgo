<?php
/** @var array $products */
/** @var array $meta */
/** @var string $short_description */
/** @var bool $hideFilters */
?>

<main class="bg-gradient-to-br from-orange-50 via-white to-pink-50 min-h-screen pb-24">

  <?php if (!empty($meta['h1']) || !empty($short_description)): ?>
    <div class="max-w-screen-lg mx-auto px-4 pt-6 pb-4 text-gray-700 space-y-2">
      <?php if (!empty($meta['h1'])): ?>
        <h1 class="text-2xl font-bold">
          <?= htmlspecialchars($meta['h1']) ?>
        </h1>
      <?php endif; ?>
      <?php if (!empty($short_description)): ?>
        <p class="text-sm">
          <?= nl2br(htmlspecialchars($short_description)) ?>
        </p>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <?php if (empty($hideFilters)): ?>
  <!-- Поле поиска и фильтры -->
  <div class="px-4 mb-6">
    <div class="bg-white rounded-2xl shadow-lg p-4 space-y-4">
      <div class="relative">
        <input id="catalogSearch" type="text"
               placeholder="Поиск ягод и фруктов..."
               class="w-full pl-12 pr-4 py-3 border border-gray-200 rounded-2xl focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500 transition-all text-gray-700 placeholder-gray-400">
        <span class="material-icons-round absolute left-4 top-1/2 transform -translate-y-1/2 text-gray-400">search</span>
      </div>
      <div class="flex space-x-2 overflow-x-auto pb-2" id="quickFilters">
        <button data-filter="all" class="flex-shrink-0 px-4 py-2 bg-red-500 text-white rounded-full text-sm font-medium hover:bg-red-600 transition-colors">Все</button>
        <button data-filter="sale" class="flex-shrink-0 px-4 py-2 bg-gray-100 text-gray-700 rounded-full text-sm font-medium hover:bg-gray-200 transition-colors">Спецпредложения</button>
        <?php foreach (($types ?? []) as $t): ?>
          <button data-filter="<?= htmlspecialchars($t['alias']) ?>" class="flex-shrink-0 px-4 py-2 bg-gray-100 text-gray-700 rounded-full text-sm font-medium hover:bg-gray-200 transition-colors">
            <?= htmlspecialchars($t['name']) ?>
          </button>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
  <?php endif; ?>

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
    <div id="productsContainer" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 items-stretch">
      <?php foreach ($products as $p): ?>
        <?php include __DIR__ . '/_card.php'; ?>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>

  <?php if (!empty($meta['text'])): ?>
    <div class="max-w-screen-lg mx-auto px-4 py-6 text-gray-700 text-sm">
      <p><?= nl2br(htmlspecialchars($meta['text'])) ?></p>
    </div>
  <?php endif; ?>

  <?php if (empty($hideFilters)): ?>
  <script>
    document.addEventListener('DOMContentLoaded', () => {
      const search = document.getElementById('catalogSearch');
      const container = document.getElementById('productsContainer');
      const allCards = Array.from(container.querySelectorAll('.product-card'));
      const filterButtons = document.querySelectorAll('#quickFilters [data-filter]');
      let currentFilter = 'all';

      function applyFilter() {
        const term = search.value.trim().toLowerCase();
        const cards = allCards.filter(c => {
          const matchesTerm = c.dataset.search.includes(term);
          const matchesFilter = currentFilter === 'all'
            ? true
            : currentFilter === 'sale'
              ? c.dataset.sale === '1'
              : c.dataset.type === currentFilter;
          return matchesTerm && matchesFilter;
        }).sort((a, b) => a.dataset.search.localeCompare(b.dataset.search, 'ru'));
        container.innerHTML = '';
        cards.forEach(c => container.appendChild(c));
      }

      search.addEventListener('input', applyFilter);
      filterButtons.forEach(btn => btn.addEventListener('click', () => {
        filterButtons.forEach(b => b.classList.remove('bg-red-500', 'text-white'));
        filterButtons.forEach(b => b.classList.add('bg-gray-100', 'text-gray-700'));
        btn.classList.remove('bg-gray-100', 'text-gray-700');
        btn.classList.add('bg-red-500', 'text-white');
        currentFilter = btn.dataset.filter;
        applyFilter();
      }));
    });
  </script>
  <?php endif; ?>

</main>
