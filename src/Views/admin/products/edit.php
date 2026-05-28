<?php
/**
 * @var array|null $product
 * @var array      $types
 * @var array      $activeBatches
 * @var float      $box_size
 * @var string     $box_unit
 */
$role = $_SESSION['role'] ?? '';
$isManager = in_array($role, ['manager','partner','seller'], true);
$base = $role === 'manager' ? '/manager' : ($role === 'partner' ? '/partner' : ($role === 'seller' ? '/seller' : '/admin'));
$activeBatches = $activeBatches ?? [];
$statusLabels = [
  'planned' => 'Запланирована',
  'purchased' => 'Выкуплена',
  'arrived' => 'В магазине',
  'active' => 'Активна',
  'discount' => 'Уценка',
  'closed' => 'Закрыта',
];
?>
<div class="bg-white dark:bg-slate-900 p-6 rounded shadow max-w-4xl mx-auto">
  <div class="mb-4 border-b border-slate-200 dark:border-slate-700">
    <nav class="flex flex-wrap gap-2">
      <button type="button" class="tab-btn px-3 py-2 rounded-t font-medium" data-tab="purchases">Закупки</button>
      <button type="button" class="tab-btn px-3 py-2 rounded-t font-medium" data-tab="main">Основная</button>
      <button type="button" class="tab-btn px-3 py-2 rounded-t font-medium" data-tab="desc">Описание / значения по умолчанию</button>
    </nav>
  </div>

  <div id="tab-purchases" class="tab-content space-y-4">
    <?php if (empty($product['id'])): ?>
      <div class="rounded border border-amber-200 bg-amber-50 p-4 text-amber-800">Сначала сохраните карточку товара, затем появятся закупки.</div>
    <?php elseif (!$activeBatches): ?>
      <div class="rounded border border-slate-200 bg-slate-50 p-4 text-slate-600">Активных закупок по этому товару нет.</div>
    <?php else: ?>
      <?php foreach ($activeBatches as $batch): ?>
        <?php
          $batchId = (int)$batch['id'];
          $purchasePrice = (float)($batch['purchase_price_per_box'] ?? 0);
          $instantPrice = (float)($batch['instant_price_per_box'] ?? 0);
          $preorderPrice = (float)($batch['preorder_price_per_box'] ?? 0);
        ?>
        <div class="rounded-xl border border-slate-700 bg-slate-800/90 text-slate-100 p-4 shadow-lg">
          <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-3 mb-3">
            <div class="space-y-1">
              <div class="font-semibold">Закупка #<?= $batchId ?><?= !empty($batch['supply_date']) ? ' от ' . htmlspecialchars((string)$batch['supply_date']) : '' ?></div>
              <div class="text-sm text-slate-300">Статус: <?= htmlspecialchars($statusLabels[$batch['status'] ?? ''] ?? (string)($batch['status'] ?? '')) ?></div>
              <div class="text-sm text-slate-300">Куплено: <?= (float)($batch['boxes_total'] ?? 0) ?> ящ. | Свободно: <?= (float)($batch['boxes_free'] ?? 0) ?> ящ. | Резерв: <?= (float)($batch['boxes_reserved'] ?? 0) ?> ящ.</div>
            </div>
            <a href="<?= $base ?>/purchases/<?= $batchId ?>" class="text-sm text-pink-300 hover:text-pink-200 hover:underline">Открыть закупку</a>
          </div>

          <form action="<?= $base ?>/products/purchase/update" method="post" enctype="multipart/form-data" class="grid grid-cols-1 md:grid-cols-[110px_1fr_auto] gap-4 items-start js-batch-price-form">
            <?= csrf_field() ?>
            <input type="hidden" name="product_id" value="<?= (int)$product['id'] ?>">
            <input type="hidden" name="batch_id" value="<?= $batchId ?>">

            <div>
              <?php if (!empty($batch['preview_photo'])): ?>
                <img src="<?= htmlspecialchars((string)$batch['preview_photo']) ?>" class="w-24 h-24 rounded-lg object-cover border border-slate-600" alt="Фото закупки">
              <?php else: ?>
                <div class="w-24 h-24 rounded-lg border border-dashed border-slate-600 text-xs text-slate-400 flex items-center justify-center bg-slate-900/50">нет фото</div>
              <?php endif; ?>
              <label class="mt-2 inline-flex cursor-pointer text-sm text-pink-300 hover:text-pink-200 hover:underline">
                добавить
                <input type="file" name="batch_photo" accept="image/*" class="hidden">
              </label>
            </div>

            <div class="space-y-3">
              <label class="block text-sm">
                <span class="block mb-1 text-slate-300">Закупка</span>
                <input name="purchase_price_per_box" type="number" step="1" value="<?= (int)round($purchasePrice) ?>" class="w-full border border-slate-600 bg-slate-900/70 text-slate-100 px-3 py-2 rounded js-purchase-price">
              </label>
              <label class="block text-sm">
                <span class="block mb-1 text-slate-300">В наличии</span>
                <input name="instant_price_per_box" type="number" step="1" value="<?= (int)round($instantPrice) ?>" class="w-full border border-slate-600 bg-slate-900/70 text-slate-100 px-3 py-2 rounded js-instant-price">
              </label>
              <label class="block text-sm">
                <span class="block mb-1 text-slate-300">Предзаказ</span>
                <input name="preorder_price_per_box" type="number" step="1" value="<?= (int)round($preorderPrice) ?>" class="w-full border border-slate-600 bg-slate-900/70 text-slate-100 px-3 py-2 rounded js-preorder-price">
              </label>
            </div>

            <button type="submit" class="bg-[#C86052] text-white px-4 py-2 rounded hover:bg-[#B44D47]">Сохранить</button>
          </form>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <form id="product-card-form" action="<?= $base ?>/products/save" method="post" enctype="multipart/form-data">
  <?php if (!empty($product['id'])): ?>
    <input type="hidden" name="id" value="<?= (int)$product['id'] ?>">
  <?php endif; ?>

  <div id="tab-main" class="tab-content space-y-4 hidden">
    <div class="flex items-center space-x-2">
      <label class="w-1/3">Продукт</label>
      <select name="product_type_id" class="flex-1 border px-2 py-1 rounded">
        <?php foreach ($types as $t): ?>
          <option value="<?= (int)$t['id'] ?>" <?= (isset($product['product_type_id']) && $product['product_type_id'] == $t['id']) ? 'selected' : '' ?>><?= htmlspecialchars($t['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <a href="<?= $base ?>/product-types/edit" class="ml-2 text-[#C86052] hover:underline">Добавить</a>
    </div>
    <div><label class="block mb-1">Сорт</label><input name="variety" type="text" value="<?= htmlspecialchars($product['variety'] ?? '') ?>" class="w-full border px-2 py-1 rounded"></div>
    <div><label class="block mb-1">Алиас</label><input name="alias" type="text" value="<?= htmlspecialchars($product['alias'] ?? '') ?>" class="w-full border px-2 py-1 rounded" required></div>
    <div><label class="block mb-1">Meta title</label><input name="meta_title" type="text" value="<?= htmlspecialchars($product['meta_title'] ?? '') ?>" class="w-full border px-2 py-1 rounded"></div>
    <div><label class="block mb-1">Meta description</label><input name="meta_description" type="text" value="<?= htmlspecialchars($product['meta_description'] ?? '') ?>" class="w-full border px-2 py-1 rounded"></div>
    <div><label class="block mb-1">Meta keywords</label><input name="meta_keywords" type="text" value="<?= htmlspecialchars($product['meta_keywords'] ?? '') ?>" class="w-full border px-2 py-1 rounded"></div>
    <div><label class="block mb-1">Краткое описание</label><textarea name="description" rows="3" class="w-full border px-2 py-1 rounded"><?= htmlspecialchars($product['description'] ?? '') ?></textarea></div>
    <div><label class="block mb-1">Подробное описание</label><textarea name="full_description" rows="5" class="w-full border px-2 py-1 rounded"><?= htmlspecialchars($product['full_description'] ?? '') ?></textarea></div>
  </div>

  <div id="tab-desc" class="tab-content space-y-4 hidden">
    <div class="rounded border border-sky-200 bg-sky-50 p-3 text-sm text-sky-800">Поля ниже — значения товара по умолчанию. Они не меняют цены и остатки активных закупок.</div>
    <div>
      <label class="block mb-1">Состав</label>
      <div id="composition-fields">
        <?php
        $composition = [];
        if (!empty($product['composition'])) {
            $dec = json_decode($product['composition'], true);
            if (is_array($dec)) { $composition = $dec; }
        }
        if (!$composition) { $composition = ['']; }
        foreach ($composition as $c): ?>
          <div class="flex items-center mb-2"><input type="text" name="composition[]" value="<?= htmlspecialchars($c) ?>" class="flex-1 border px-2 py-1 rounded"><button type="button" class="ml-2 text-red-600 remove-composition"><span class="material-icons-round">delete</span></button></div>
        <?php endforeach; ?>
      </div>
      <button type="button" id="add-composition" class="mt-2 text-[#C86052] flex items-center"><span class="material-icons-round mr-1">add</span>Добавить компонент</button>
    </div>
    <div class="flex items-center space-x-2"><label class="w-1/3">Вес/объём ящика</label><input name="box_size" type="number" step="0.01" value="<?= htmlspecialchars((string)$box_size) ?>" class="border px-2 py-1 rounded w-1/2" required><select name="box_unit" class="border px-2 py-1 rounded"><option value="кг" <?= $box_unit==='кг'?'selected':'' ?>>кг</option><option value="л" <?= $box_unit==='л'?'selected':'' ?>>л</option></select></div>
    <div><label class="block mb-1">Дата закупки по умолчанию</label><input type="date" name="delivery_date" value="<?= htmlspecialchars($product['delivery_date'] ?? '') ?>" class="w-full border px-2 py-1 rounded"></div>
    <div><label class="block mb-1">Цена по умолчанию в наличии за ящик (₽)</label><input name="price" type="number" step="0.01" value="<?= htmlspecialchars((string)($price_kg ?? 0)) ?>" class="w-full border px-2 py-1 rounded" required></div>
    <div><label class="block mb-1">Цена по умолчанию по брони за ящик (₽)</label><input name="preorder_price_per_box" type="number" step="0.01" value="<?= htmlspecialchars($product['preorder_price_per_box'] ?? 0) ?>" class="w-full border px-2 py-1 rounded"></div>
    <div><label class="block mb-1">Акционная цена по умолчанию (₽)</label><input name="sale_price" type="number" step="0.01" value="<?= htmlspecialchars($product['sale_price'] ?? 0) ?>" class="w-full border px-2 py-1 rounded"></div>
    <div class="flex items-center space-x-2"><label class="block mb-1">Активен?</label><input name="is_active" type="checkbox" value="1" <?= (!isset($product['is_active']) || $product['is_active']==1) ? 'checked' : '' ?>></div>
    <div><label class="block mb-1">Производитель</label><input name="manufacturer" type="text" value="<?= htmlspecialchars($product['manufacturer'] ?? '') ?>" class="w-full border px-2 py-1 rounded"></div>
    <div><label class="block mb-1">Изображение по умолчанию</label><input name="image" type="file" accept="image/*" class="w-full"><?php if (!empty($product['image_path'])): ?><img src="<?= htmlspecialchars($product['image_path']) ?>" class="mt-2 w-24 h-24 object-cover rounded"><?php endif; ?></div>
  </div>

  <button type="submit" class="mt-4 bg-[#C86052] text-white px-4 py-2 rounded hover:bg-[#B44D47]">Сохранить карточку товара</button>
</form>
</div>
<script>
document.addEventListener('DOMContentLoaded', function(){
  const tabs = document.querySelectorAll('.tab-btn');
  const contents = document.querySelectorAll('.tab-content');
  function activate(tab){
    tabs.forEach(b=>b.classList.remove('bg-[#C86052]','text-white'));
    contents.forEach(c=>c.classList.add('hidden'));
    const pane = document.getElementById('tab-'+tab);
    if (pane) pane.classList.remove('hidden');
    const btn = document.querySelector('.tab-btn[data-tab="'+tab+'"]');
    if (btn) btn.classList.add('bg-[#C86052]','text-white');
  }
  tabs.forEach(b=>b.addEventListener('click',()=>activate(b.dataset.tab)));
  activate('purchases');

  document.querySelectorAll('.js-batch-price-form').forEach((form) => {
    const purchase = form.querySelector('.js-purchase-price');
    const instant = form.querySelector('.js-instant-price');
    const preorder = form.querySelector('.js-preorder-price');
    if (!purchase || !instant || !preorder) return;
    const roundToStep = (value, step = 10) => Math.floor(value / step) * step;
    const recalc = () => {
      const base = parseFloat(String(purchase.value).replace(',', '.')) || 0;
      if (base <= 0) return;
      instant.value = String(roundToStep(base * 1.50, 10));
      preorder.value = String(roundToStep(base * 1.35, 10));
    };
    purchase.addEventListener('input', recalc);
    purchase.addEventListener('change', recalc);
  });

  const addBtn = document.getElementById('add-composition');
  const container = document.getElementById('composition-fields');
  if (addBtn && container) {
    addBtn.addEventListener('click', function () {
      const div = document.createElement('div');
      div.className = 'flex items-center mb-2';
      div.innerHTML = '<input type="text" name="composition[]" class="flex-1 border px-2 py-1 rounded" /> <button type="button" class="ml-2 text-red-600 remove-composition"><span class="material-icons-round">delete</span></button>';
      container.appendChild(div);
    });
    container.addEventListener('click', function(e){ if(e.target.closest('.remove-composition')){ e.target.closest('.flex').remove(); } });
  }
});
</script>
