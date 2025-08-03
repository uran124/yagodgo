<?php
/**
 * @var array $p
 * Ожидаются поля:
 *   - id
 *   - product
 *   - variety
 *   - description
 *   - price            (основная цена)
 *   - sale_price       (акционная цена, 0 = без акции)
 *   - is_active        (0 или 1)
 *   - image_path
 *   - box_size, box_unit
 *   - delivery_date    (строка 'Y-m-d' или null)
 */
?>
<?php
$search = mb_strtolower(($p['product'] ?? '') . ' ' . ($p['variety'] ?? ''), 'UTF-8');
$img       = trim($p['image_path'] ?? '');
$hasImage  = $img !== '';
$today     = date('Y-m-d');
$d         = $p['delivery_date']     ?? null;
$placeholder = defined('PLACEHOLDER_DATE') ? PLACEHOLDER_DATE : '2025-05-15';
$showDate = $d !== null && $d !== $placeholder;
$active    = (int)($p['is_active']    ?? 0);
$price     = floatval($p['price']     ?? 0); // base price per kg
$sale      = floatval($p['sale_price']?? 0); // sale price per kg
$boxSize   = floatval($p['box_size']  ?? 0);
$boxUnit   = $p['box_unit']           ?? '';

$effectiveKg = $sale > 0 ? $sale : $price;
$priceBox   = $effectiveKg * $boxSize;
$pricePerKg = round($effectiveKg, 2);
$regularBox = $price * $boxSize;
$role     = $_SESSION['role'] ?? '';
$isStaff  = in_array($role, ['admin','manager','partner'], true);
$basePath = $role === 'manager' ? '/manager' : ($role === 'partner' ? '/partner' : '/admin');
$regularKg  = round($price, 2);
?>
<div class="product-card bg-white rounded-2xl shadow-lg overflow-hidden flex flex-col hover:shadow-2xl transition-shadow duration-200 h-full max-w-[350px]"
     data-search="<?= htmlspecialchars($search) ?>"
     data-type="<?= htmlspecialchars($p['type_alias'] ?? '') ?>"
     data-sale="<?= ($p['sale_price'] ?? 0) > 0 ? '1' : '0' ?>"
     data-base-box="<?= $sale > 0 ? $priceBox : $regularBox ?>"
     data-base-kg="<?= $sale > 0 ? $pricePerKg : $regularKg ?>">
  <div class="relative">
    <?php if ($hasImage): ?>
      <a href="/catalog/<?= urlencode($p['type_alias']) ?>/<?= urlencode($p['alias']) ?>">
        <img src="<?= htmlspecialchars($img) ?>"
             alt="<?= htmlspecialchars($p['product'] ?? '') ?>"
             class="w-full object-cover h-40 sm:h-48">
      </a>
    <?php else: ?>
      <div class="w-full h-40 sm:h-48 bg-pink-50 flex flex-col items-center justify-center">
        <span class="material-icons-round text-4xl text-pink-400 mb-1">image</span>
        <span class="text-pink-700 text-sm">изображение подгружается</span>
      </div>
    <?php endif; ?>

    <?php if (!$active): ?>
      <!-- Товар отключён в админке -->
      <span class="absolute top-3 left-3 bg-gray-400 text-white text-xs font-semibold px-2 py-1 rounded-full opacity-80">
        Не активен
      </span>
    <?php else: ?>
      <!-- Бейджик даты / наличия -->
      <?php if ($showDate && $d <= $today): ?>
        <span class="absolute top-3 left-3 bg-green-100 text-green-800 text-xs font-semibold px-2 py-1 rounded-full <?= $isStaff ? 'cursor-pointer' : '' ?>" <?= $isStaff ? 'data-edit-date="' . $p['id'] . '"' : '' ?>>
          В наличии
        </span>
      <?php elseif ($showDate): ?>
        <span class="absolute top-3 left-3 bg-orange-100 text-orange-800 text-xs font-semibold px-2 py-1 rounded-full <?= $isStaff ? 'cursor-pointer' : '' ?>" <?= $isStaff ? 'data-edit-date="' . $p['id'] . '"' : '' ?>>
          <?= date('d.m.Y', strtotime($d)) ?>
        </span>
      <?php else: ?>
        <span class="absolute top-3 left-3 bg-blue-100 text-blue-800 text-xs font-semibold px-2 py-1 rounded-full <?= $isStaff ? 'cursor-pointer' : '' ?>" <?= $isStaff ? 'data-edit-date="' . $p['id'] . '"' : '' ?>>
          Ближайшая возможная дата
        </span>
      <?php endif; ?>

      <?php if ($isStaff): ?>
        <div class="absolute top-10 left-3 bg-white border rounded shadow p-2 z-10 hidden" data-date-form="<?= $p['id'] ?>">
          <form action="<?= $basePath ?>/products/update-date" method="post" class="flex items-center space-x-2">
            <input type="hidden" name="id" value="<?= $p['id'] ?>">
            <input type="date" name="delivery_date" value="<?= htmlspecialchars($d ?? '') ?>" class="border px-1 py-1 rounded text-sm">
            <button type="submit" class="bg-blue-500 text-white rounded px-2 py-1 text-xs">Обновить</button>
          </form>
        </div>
      <?php endif; ?>

      <!-- Бейджик скидки (если есть) -->
      <?php if ($sale > 0 && $price > 0): 
        $percent = round((($price - $sale) / $price) * 100);
      ?>
        <span class="absolute top-12 left-3 bg-red-100 text-red-800 text-xs font-semibold px-2 py-1 rounded-full">
          −<?= $percent ?>%
        </span>
      <?php endif; ?>
    <?php endif; ?>
  </div>

  <div class="p-3 sm:p-4 flex-1 flex flex-col">
    <!-- Название и сорт -->
    <div class="mb-2">
      <?php $boxLabel = htmlspecialchars($boxSize . ' ' . $boxUnit); ?>
      <h3 class="text-base sm:text-lg font-semibold text-gray-800">
        <a href="/catalog/<?= urlencode($p['type_alias']) ?>/<?= urlencode($p['alias']) ?>" class="hover:underline">
          <?= htmlspecialchars($p['product']      ?? '') ?>
          <?php if (!empty($p['variety'])): ?>
            <?= ' ' . htmlspecialchars($p['variety']) ?>
          <?php endif; ?>
          <?php if ($boxSize > 0 && $boxUnit !== ''): ?>
            <?= ' (' . $boxLabel . ')' ?>
          <?php endif; ?>
        </a>
      </h3>
    </div>

    <!-- Описание (если есть) -->

<?php if (!empty($p['description'])): ?>
  <p class="text-xs sm:text-sm text-gray-600 mb-3 sm:mb-4 flex-1">
    <?= htmlspecialchars($p['description']) ?>
  </p>
<?php else: ?>
  <div class="flex-1"></div>
<?php endif; ?>



    <!-- Блок цены -->
    <div class="mt-auto">
      <?php if ($sale > 0): ?>
        <!-- Акционная цена -->
        <div class="flex items-baseline space-x-2 mb-3">
          <div class="text-xs sm:text-sm text-gray-400 line-through">
            <?= number_format($regularBox, 0, '.', ' ') ?> ₽/ящик
          </div>
          <div class="text-lg sm:text-xl font-bold text-red-600 box-price">
            <?= number_format($priceBox, 0, '.', ' ') ?> ₽/ящик
          </div>
        </div>
        <div class="text-xs sm:text-sm text-gray-400 mb-3 kg-price">
          <?= htmlspecialchars($pricePerKg) ?> ₽/кг
        </div>
      <?php else: ?>
        <!-- Обычная цена -->
        <div class="flex justify-between items-center mb-3">
          <div class="text-xl sm:text-2xl font-bold text-gray-800 box-price">
            <?= number_format($regularBox, 0, '.', ' ') ?> ₽/ящик
          </div>
          <div class="text-xs sm:text-sm text-gray-400 kg-price">
            <?= htmlspecialchars($regularKg) ?> ₽/кг
          </div>
        </div>
      <?php endif; ?>

      <!-- Кнопка «В корзину» или «Войдите» -->
      <?php if (in_array((string)($_SESSION['role'] ?? ''), ['client','partner']) && $active): ?>
        <form action="/cart/add" method="post" class="flex items-center space-x-2 add-to-cart-form" data-id="<?= $p['id'] ?>" data-name="<?= htmlspecialchars($p['product'] . ($p['variety'] ? ' ' . $p['variety'] : '')) ?>" data-price="<?= $priceBox ?>">
          <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
          <div class="flex items-center space-x-2">
            <button type="button"
                    class="w-8 h-8 flex items-center justify-center bg-gray-100 rounded-full"
                    onclick="let inp=this.nextElementSibling; if(+inp.value>1) inp.value=+inp.value-1;">
              <span class="material-icons-round text-gray-600 text-base">remove</span>
            </button>
            <input type="number"
                   name="quantity"
                   value="1"
                   min="1"
                   step="1"
                   class="w-12 text-center border border-gray-200 rounded-md" />
            <button type="button"
                    class="w-8 h-8 flex items-center justify-center bg-gray-100 rounded-full"
                    onclick="let inp=this.previousElementSibling; inp.value=+inp.value+1;">
              <span class="material-icons-round text-gray-600 text-base">add</span>
            </button>
          </div>

          <button type="submit"
                  class="ml-2 bg-gradient-to-r from-red-500 to-pink-500 text-white px-2 py-2 rounded-lg hover:from-pink-500 hover:to-red-500 transition-all flex items-center text-sm">
            <span class="material-icons-round text-base mr-1">shopping_cart</span>
            В корзину
          </button>
        </form>
      <?php else: ?>
        <!-- Гость или неактивный товар -->
        <?php if (!empty($_SESSION['user_id']) && !$active): ?>
          <button disabled
                  class="w-full bg-gray-100 text-gray-500 px-3 py-2 rounded-lg text-sm text-center cursor-not-allowed">
            Товар недоступен
          </button>
        <?php else: ?>
          <a href="/login"
             class="w-full bg-gradient-to-r from-red-500 to-pink-500 text-white px-3 py-2 rounded-lg hover:from-pink-500 hover:to-red-500 transition-all text-sm flex items-center justify-center space-x-1">
            <span class="material-icons-round text-base">login</span>
            <span>Войдите, чтобы заказать</span>
          </a>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>
</div>
