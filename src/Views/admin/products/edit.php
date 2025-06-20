<?php
/** 
 * @var array|null $product  // данные товара (если редактируем) или null (если добавляем новый)
 * @var array      $types
 * @var float      $box_size
 * @var string     $box_unit
 */
?>
<form action="/admin/products/save" method="post" enctype="multipart/form-data"
      class="space-y-4 bg-white p-6 rounded shadow max-w-lg mx-auto">

  <?php if (!empty($product['id'])): ?>
    <input type="hidden" name="id" value="<?= $product['id'] ?>">
  <?php endif; ?>

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
    <a href="/admin/product-types/edit" class="ml-2 text-[#C86052] hover:underline">
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

  <!-- Описание -->
  <div>
    <label class="block mb-1">Описание</label>
    <textarea name="description" rows="3"
              class="w-full border px-2 py-1 rounded"><?= htmlspecialchars($product['description'] ?? '') ?></textarea>
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

  <!-- Цена за кг -->
  <div>
    <label class="block mb-1">Цена за кг (₽)</label>
    <input name="price" type="number" step="0.01"
           value="<?= htmlspecialchars($product['price'] ?? '') ?>"
           class="w-full border px-2 py-1 rounded" required>
  </div>

  <!-- Остаток (ящиков) -->
  <div>
    <label class="block mb-1">Остаток (ящиков)</label>
    <input name="stock_boxes" type="number" step="0.01"
           value="<?= htmlspecialchars($product['stock_boxes'] ?? '') ?>"
           class="w-full border px-2 py-1 rounded" required>
  </div>

  <button type="submit"
          class="bg-[#C86052] text-white px-4 py-2 rounded hover:bg-[#B44D47]">
    Сохранить
  </button>
</form>
