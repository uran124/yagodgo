<?php /** @var array $material */ ?>
<main class="bg-gradient-to-br from-orange-50 via-white to-pink-50 min-h-screen pb-24">
  <article class="max-w-screen-md mx-auto px-4 pt-6 space-y-4">
    <h1 class="text-3xl font-bold text-gray-800 mb-4">
      <?= htmlspecialchars($material['title']) ?>
    </h1>
    <?php if (!empty($material['image_path'])): ?>
      <img src="<?= htmlspecialchars($material['image_path']) ?>" alt="<?= htmlspecialchars($material['title']) ?>" class="w-full rounded-2xl shadow-lg">
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
  </article>
</main>
