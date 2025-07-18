<?php
/**
 * @var array|null $product  // данные товара (если редактируем) или null (если добавляем новый)
 * @var array      $types
 * @var float      $box_size
 * @var string     $box_unit
 */
?>
<?php $isManager = ($_SESSION['role'] ?? '') === 'manager'; $base = $isManager ? '/manager' : '/admin'; ?>
<form action="<?= $base ?>/products/save" method="post" enctype="multipart/form-data"
      class="bg-white p-6 rounded shadow max-w-lg mx-auto">

  <?php if (!empty($product['id'])): ?>
    <input type="hidden" name="id" value="<?= $product['id'] ?>">
  <?php endif; ?>

  <div class="mb-4 border-b">
    <nav class="flex space-x-2">
      <button type="button" class="tab-btn px-3 py-2 rounded-t font-medium" data-tab="main">Основная</button>
      <button type="button" class="tab-btn px-3 py-2 rounded-t font-medium" data-tab="desc">Описание</button>
    </nav>
  </div>

  <div id="tab-main" class="tab-content space-y-4">
    <!-- Тип продукта -->
    <div class="flex items-center space-x-2">
      <label class="w-1/3">Продукт</label>
      <select name="product_type_id" class="flex-1 border px-2 py-1 rounded">
        <?php foreach ($types as $t): ?>
          <option value="<?= $t['id'] ?>"
            <?= (isset($product['product_type_id']) && $product['product_type_id'] == $t['id']) ? 'selected' : '' ?>>
            <?= htmlspecialchars($t['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
      <a href="<?= $base ?>/product-types/edit" class="ml-2 text-[#C86052] hover:underline">
        Добавить
      </a>
    </div>

    <!-- Сорт -->
    <div>
      <label class="block mb-1">Сорт (необязательно)</label>
      <input name="variety" type="text"
             value="<?= htmlspecialchars($product['variety'] ?? '') ?>"
             class="w-full border px-2 py-1 rounded">
    </div>
    <div>
      <label class="block mb-1">Алиас</label>
      <input name="alias" type="text"
             value="<?= htmlspecialchars($product['alias'] ?? '') ?>"
             class="w-full border px-2 py-1 rounded" required>
    </div>
    <div>
      <label class="block mb-1">Meta title</label>
      <input name="meta_title" type="text"
             value="<?= htmlspecialchars($product['meta_title'] ?? '') ?>"
             class="w-full border px-2 py-1 rounded">
    </div>
    <div>
      <label class="block mb-1">Meta description</label>
      <input name="meta_description" type="text"
             value="<?= htmlspecialchars($product['meta_description'] ?? '') ?>"
             class="w-full border px-2 py-1 rounded">
    </div>
    <div>
      <label class="block mb-1">Meta keywords</label>
      <input name="meta_keywords" type="text"
             value="<?= htmlspecialchars($product['meta_keywords'] ?? '') ?>"
             class="w-full border px-2 py-1 rounded">
    </div>
    <!-- Описание -->
    <div>
      <label class="block mb-1">Описание</label>
      <textarea name="description" rows="3"
                class="w-full border px-2 py-1 rounded"><?= htmlspecialchars($product['description'] ?? '') ?></textarea>
    </div>
    <div>
      <label class="block mb-1">Подробное описание</label>
      <textarea name="full_description" rows="5"
                class="w-full border px-2 py-1 rounded"><?= htmlspecialchars($product['full_description'] ?? '') ?></textarea>
    </div>
  </div>

  <div id="tab-desc" class="tab-content space-y-4 hidden">
    <div>
      <label class="block mb-1">Состав</label>
      <div id="composition-fields">
        <?php
        $composition = [];
        if (!empty($product['composition'])) {
            $dec = json_decode($product['composition'], true);
            if (is_array($dec)) {
                $composition = $dec;
            }
        }
        if (!$composition) { $composition = ['']; }
        foreach ($composition as $c): ?>
          <div class="flex items-center mb-2">
            <input type="text" name="composition[]" value="<?= htmlspecialchars($c) ?>" class="flex-1 border px-2 py-1 rounded" />
            <button type="button" class="ml-2 text-red-600 remove-composition"><span class="material-icons-round">delete</span></button>
          </div>
        <?php endforeach; ?>
      </div>
      <button type="button" id="add-composition" class="mt-2 text-[#C86052] flex items-center"><span class="material-icons-round mr-1">add</span>Добавить компонент</button>
    </div>

    <!-- Размер ящика -->
    <div class="flex items-center space-x-2">
      <label class="w-1/3">Вес/объём ящика</label>
      <input name="box_size" type="number" step="0.01"
             value="<?= htmlspecialchars($box_size) ?>"
             class="border px-2 py-1 rounded w-1/2" required>
      <select name="box_unit" class="border px-2 py-1 rounded">
        <option value="кг" <?= $box_unit==='кг'?'selected':'' ?>>кг</option>
        <option value="л"  <?= $box_unit==='л'?'selected':'' ?>>л</option>
      </select>
    </div>

    <!-- Дата поставки -->
    <div>
      <label class="block mb-1">Дата следующей поставки</label>
      <input
        type="date"
        name="delivery_date"
        value="<?= htmlspecialchars($product['delivery_date'] ?? '') ?>"
        class="w-full border px-2 py-1 rounded focus:ring-2 focus:ring-[#C86052] outline-none"
      >
      <p class="text-sm text-gray-500 mt-1">
        Оставьте пустым, если дата неизвестна (под заказ)
      </p>
    </div>

    <!-- Акционная цена за кг -->
    <div>
      <label class="block mb-1">Акционная цена за кг (₽)<span class="text-gray-500 text-sm ml-1">(0 — без акции)</span></label>
      <input name="sale_price" type="number" step="0.01"
             value="<?= htmlspecialchars($product['sale_price'] ?? 0) ?>"
             class="w-full border px-2 py-1 rounded">
    </div>

    <!-- Активность товара -->
    <div class="flex items-center space-x-2">
      <label class="block mb-1">Активен?</label>
      <input name="is_active" type="checkbox" value="1"
        <?= (!isset($product['is_active']) || $product['is_active']==1) ? 'checked' : '' ?>>
    </div>

    <!-- Производитель -->
    <div>
      <label class="block mb-1">Производитель</label>
      <input name="manufacturer" type="text"
             value="<?= htmlspecialchars($product['manufacturer'] ?? '') ?>"
             class="w-full border px-2 py-1 rounded">
    </div>

    <!-- Картинка -->
    <div>
      <label class="block mb-1">Изображение</label>
      <input name="image" type="file" accept="image/*" class="w-full">
      <?php if (!empty($product['image_path'])): ?>
        <img src="<?= htmlspecialchars($product['image_path']) ?>"
             class="mt-2 w-24 h-24 object-cover rounded">
      <?php endif; ?>
    </div>

    <!-- Цена за ящик с учётом скидки -->
    <div>
      <label class="block mb-1">Цена за ящик (₽)</label>
      <input name="price" type="number" step="0.01"
             value="<?= htmlspecialchars($price_box ?? '') ?>"
             class="w-full border px-2 py-1 rounded" required>
    </div>

    <!-- Остаток (ящиков) -->
    <div>
      <label class="block mb-1">Остаток (ящиков)</label>
      <input name="stock_boxes" type="number" step="0.01"
             value="<?= htmlspecialchars($product['stock_boxes'] ?? '') ?>"
             class="w-full border px-2 py-1 rounded" required>
    </div>
  </div>

  <button type="submit"
          class="bg-[#C86052] text-white px-4 py-2 rounded hover:bg-[#B44D47]">
    Сохранить
  </button>
</form>
<script>
  document.addEventListener('DOMContentLoaded', function(){
    const tabs = document.querySelectorAll('.tab-btn');
    const contents = document.querySelectorAll('.tab-content');
    function activate(tab){
      tabs.forEach(b=>b.classList.remove('bg-[#C86052]','text-white'));
      contents.forEach(c=>c.classList.add('hidden'));
      document.getElementById('tab-'+tab).classList.remove('hidden');
      document.querySelector('.tab-btn[data-tab="'+tab+'"]').classList.add('bg-[#C86052]','text-white');
    }
    tabs.forEach(b=>b.addEventListener('click',()=>activate(b.dataset.tab)));
    activate('main');
  });

  document.getElementById('add-composition').addEventListener('click', function () {
    const container = document.getElementById('composition-fields');
    const div = document.createElement('div');
    div.className = 'flex items-center mb-2';
    div.innerHTML = '<input type="text" name="composition[]" class="flex-1 border px-2 py-1 rounded" /> <button type="button" class="ml-2 text-red-600 remove-composition"><span class="material-icons-round">delete</span></button>';
    container.appendChild(div);
  });
  document.getElementById('composition-fields').addEventListener('click', function(e){
    if(e.target.closest('.remove-composition')){
      e.target.closest('.flex').remove();
    }
  });
</script>
