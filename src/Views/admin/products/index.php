<?php /** @var array $products */ ?>
<?php $role = $_SESSION['role'] ?? ''; $isManager = in_array($role, ['manager','partner','seller'], true); $base = $role === 'manager' ? '/manager' : ($role === 'partner' ? '/partner' : ($role === 'seller' ? '/seller' : '/admin')); ?>

<div class="flex items-center mb-4">
  <a href="<?= $base ?>/products/edit" class="bg-[#C86052] text-white px-4 py-2 rounded inline-flex items-center">
    <span class="material-icons-round text-base mr-1">add</span> Добавить товар
  </a>
  <?php if (!$isManager): ?>
  <form method="get" action="<?= $base ?>/products" class="ml-auto">
    <select name="seller_id" onchange="this.form.submit()" class="border rounded px-2 py-1">
      <option value="">Все селлеры</option>
      <?php foreach ($sellers as $s): ?>
      <option value="<?= $s['id'] ?>" <?= ($selectedSeller ?? 0) == $s['id'] ? 'selected' : '' ?>><?= htmlspecialchars($s['company_name']) ?></option>
      <?php endforeach; ?>
    </select>
  </form>
  <?php endif; ?>
</div>

<table class="min-w-full bg-white rounded shadow overflow-hidden">
  <thead class="bg-gray-200 text-gray-700">
    <tr>
      <th class="p-3 text-left font-semibold">Продукт</th>
      <?php if (!$isManager): ?>
      <th class="p-3 text-left font-semibold">Алиас</th>
      <?php endif; ?>
      <th class="p-3 text-left font-semibold">Сорт</th>
      <th class="p-3 text-left font-semibold">Вес ящика</th>
      <th class="p-3 text-left font-semibold">Цена за кг/л</th>
      <?php if (!$isManager): ?>
      <th class="p-3 text-left font-semibold">Остаток (ящиков)</th>
      <?php endif; ?>
      <th class="p-3 text-center font-semibold">Активен</th>
      <?php if (!$isManager): ?>
      <th class="p-3 text-center font-semibold">Удалить</th>
      <?php endif; ?>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($products as $p): ?>
      <tr class="border-b hover:bg-gray-50 transition-all duration-200">
        <td class="p-3 font-medium text-gray-600">
          <a href="<?= $base ?>/products/edit?id=<?= $p['id'] ?>" class="text-[#C86052] hover:underline">
            <?= htmlspecialchars($p['product']) ?>
          </a>
        </td>
        <?php if (!$isManager): ?>
        <td class="p-3 text-gray-600">
          <?= htmlspecialchars($p['alias']) ?>
        </td>
        <?php endif; ?>
        <td class="p-3 text-gray-600"><?= htmlspecialchars($p['variety']) ?></td>
        <td class="p-3 text-gray-600">
          <?= $p['box_size'] ?> <?= htmlspecialchars($p['box_unit']) ?>
        </td>
        <td class="p-3 text-gray-600">
          <?= number_format($p['price'], 2, '.', ' ') ?> ₽/<?= htmlspecialchars($p['unit']) ?>
        </td>
        <?php if (!$isManager): ?>
        <td class="p-3 text-gray-600"><?= $p['stock_boxes'] ?></td>
        <?php endif; ?>
        <td class="p-3 text-center">
          <form action="<?= $base ?>/products/toggle" method="post" class="inline-block">
            <input type="hidden" name="id" value="<?= $p['id'] ?>">
            <label class="relative inline-flex items-center cursor-pointer">
              <input type="checkbox" name="active" onchange="this.form.submit()" <?= $p['is_active'] ? 'checked' : '' ?> class="sr-only peer">
              <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-red-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-red-600"></div>
            </label>
          </form>
        </td>
        <?php if (!$isManager): ?>
        <td class="p-3 text-center">
          <form action="<?= $base ?>/products/delete" method="post" onsubmit="return confirm('Удалить товар?');">
            <input type="hidden" name="id" value="<?= $p['id'] ?>">
            <button type="submit" class="text-red-600">
              <span class="material-icons-round">delete</span>
            </button>
          </form>
        </td>
        <?php endif; ?>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>
