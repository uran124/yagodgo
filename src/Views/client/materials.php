<?php
/**
 * @var array<int,array<string,mixed>> $materials
 * @var array<int,array<string,mixed>> $categories
 * @var array<string,mixed>|null $currentCategory
 */
?>

<main class="bg-gradient-to-br from-orange-50 via-white to-pink-50 min-h-screen pb-24">
  <section class="px-4 pt-6 mb-8">
    <div class="rounded-3xl bg-white/85 p-5 md:p-8 shadow-sm ring-1 ring-red-100">
      <p class="mb-2 text-sm font-semibold text-red-500">База знаний BerryGo</p>
      <h1 class="text-2xl md:text-4xl font-bold text-gray-900 leading-tight">
        <?= $currentCategory ? htmlspecialchars((string)$currentCategory['name']) : 'Полезные материалы о ягодах и фруктах' ?>
      </h1>
      <p class="mt-3 max-w-3xl text-sm md:text-base leading-relaxed text-gray-600">
        <?= $currentCategory
          ? 'Собрали материалы раздела: советы, подборки и SEO-оптимизированные статьи о свежих ягодах, фруктах и доставке по Красноярску.'
          : 'Все статьи BerryGo в одном месте: как выбрать свежую клубнику и черешню, как хранить сезонные ягоды после доставки и какие сорта подходят для семьи, десертов и подарков.' ?>
      </p>
    </div>
  </section>

  <?php if (!empty($categories)): ?>
    <nav class="px-4 mb-6 flex gap-2 overflow-x-auto no-scrollbar" aria-label="Разделы материалов">
      <a href="/content" class="flex-none rounded-full px-4 py-2 text-sm font-semibold shadow-sm <?= $currentCategory ? 'bg-white text-gray-600 ring-1 ring-gray-100 hover:text-red-500' : 'bg-red-500 text-white' ?>">Все материалы</a>
      <?php foreach ($categories as $category): ?>
        <?php $isActive = $currentCategory && (string)$currentCategory['alias'] === (string)$category['alias']; ?>
        <a href="/content/<?= urlencode((string)$category['alias']) ?>" class="flex-none rounded-full px-4 py-2 text-sm font-semibold shadow-sm <?= $isActive ? 'bg-red-500 text-white' : 'bg-white text-gray-600 ring-1 ring-gray-100 hover:text-red-500' ?>">
          <?= htmlspecialchars((string)$category['name']) ?>
          <span class="<?= $isActive ? 'text-white/75' : 'text-gray-400' ?>">(<?= (int)$category['materials_count'] ?>)</span>
        </a>
      <?php endforeach; ?>
    </nav>
  <?php endif; ?>

  <section class="px-4" itemscope itemtype="https://schema.org/ItemList">
    <?php if (empty($materials)): ?>
      <div class="rounded-2xl bg-white p-6 text-gray-600 shadow-sm">Материалы скоро появятся.</div>
    <?php else: ?>
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 md:gap-6">
        <?php foreach ($materials as $index => $m): ?>
          <article class="min-h-full" itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">
            <meta itemprop="position" content="<?= $index + 1 ?>">
            <meta itemprop="name" content="<?= htmlspecialchars((string)$m['title'], ENT_QUOTES) ?>">
            <meta itemprop="url" content="/content/<?= urlencode((string)$m['cat_alias']) ?>/<?= urlencode((string)$m['mat_alias']) ?>">
            <?php if (!$currentCategory): ?>
              <a href="/content/<?= urlencode((string)$m['cat_alias']) ?>" class="mb-2 inline-flex rounded-full bg-red-50 px-2.5 py-1 text-xs font-semibold text-red-500 hover:bg-red-100">
                <?= htmlspecialchars((string)$m['category_name']) ?>
              </a>
            <?php endif; ?>
            <?php $material = $m; include __DIR__ . '/_material_card.php'; ?>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>
</main>
