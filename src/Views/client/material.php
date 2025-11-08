<?php /** @var array $material */ ?>
<?php /** @var array $products */ ?>
<main class="bg-gradient-to-br from-orange-50 via-white to-pink-50 min-h-screen pb-24">
  <article class="max-w-screen-md mx-auto px-4 pt-6 space-y-4">
    <h1 class="text-3xl font-bold text-gray-800 mb-4">
      <?= htmlspecialchars($material['title']) ?>
    </h1>
    <?php if (!empty($material['created_at'])): ?>
      <div class="flex items-center justify-start mb-4">
        <span class="availability-badge availability-badge--date text-xs">
          Опубликовано <?= date('d.m.Y', strtotime($material['created_at'])) ?>
        </span>
      </div>
    <?php endif; ?>
    <?php if (!empty($material['image_path'])): ?>
      <img src="<?= htmlspecialchars($material['image_path']) ?>" alt="<?= htmlspecialchars($material['title']) ?>" class="w-full rounded-2xl shadow-lg material-image">
    <?php endif; ?>
    <?php if (!empty($material['short_desc'])): ?>
      <p class="text-gray-700 text-lg">
        <?= htmlspecialchars($material['short_desc']) ?>
      </p>
    <?php endif; ?>
    <?php if (!empty($material['text'])): ?>
      <div class="prose prose-sm max-w-none">
        <?= nl2br(htmlspecialchars($material['text'])) ?>
      </div>
    <?php endif; ?>

    <?php if (!empty($products)): ?>
      <h2 class="text-2xl font-bold text-gray-800 mt-6 mb-4">Товары из статьи</h2>
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php foreach ($products as $p): ?>
          <?php include __DIR__ . '/_card.php'; ?>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <div class="mt-8 flex justify-center space-x-4">
      <a href="/" class="bg-gradient-to-r from-red-500 to-pink-500 accent-gradient text-white px-6 py-3 rounded-xl transition">На главную</a>
      <a href="/catalog" class="bg-gradient-to-r from-red-500 to-pink-500 accent-gradient text-white px-6 py-3 rounded-xl transition">В каталог</a>
    </div>
  </article>
</main>
