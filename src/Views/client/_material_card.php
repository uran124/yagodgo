<?php
/** @var array $material */
?>
<a href="/content/<?= urlencode($material['cat_alias']) ?>/<?= urlencode($material['mat_alias']) ?>" class="block bg-white rounded-2xl shadow-lg hover:shadow-2xl transition-shadow duration-200 h-full overflow-hidden">
  <?php if (!empty($material['image_path'])): ?>
    <img src="<?= htmlspecialchars($material['image_path']) ?>" alt="<?= htmlspecialchars($material['title']) ?>" class="w-full h-40 object-cover rounded-t-2xl material-card-image">
  <?php endif; ?>
  <div class="p-3 sm:p-4">
    <h3 class="text-base sm:text-lg font-semibold text-gray-800 mb-2">
      <?= htmlspecialchars($material['title']) ?>
    </h3>
    <?php if (!empty($material['short_desc'])): ?>
      <p class="text-sm text-gray-600"><?= htmlspecialchars($material['short_desc']) ?></p>
    <?php endif; ?>
  </div>
</a>
