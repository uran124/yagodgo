<?php /** @var array<string,mixed> $batch */ ?>
<?php /** @var array<int,array<string,mixed>> $movements */ ?>
<?php /** @var array<int,array<string,mixed>> $photos */ ?>
<?php /** @var array<int,array<string,mixed>> $products */ ?>
<?php $basePath = $basePath ?? '/admin'; ?>
<?php $flash = $flash ?? null; ?>
<?php $statusLabels = [
  'planned' => 'Запланирована',
  'purchased' => 'Выкуплена',
  'arrived' => 'В магазине',
    'closed' => 'Закрыта',
]; ?>

<div class="mb-4">
  <a href="<?= $basePath ?>/purchases" class="text-[#C86052] hover:underline">← К списку закупок</a>
</div>

<?php if (is_array($flash) && !empty($flash['message'])): ?>
  <div class="<?= ($flash['type'] ?? '') === 'error' ? 'bg-red-50 border-red-200 text-red-700' : 'bg-green-50 border-green-200 text-green-700' ?> border p-3 rounded mb-4">
    <?= htmlspecialchars((string)$flash['message']) ?>
  </div>
<?php endif; ?>

<div class="bg-white p-4 rounded shadow mb-4">
  <h2 class="text-xl font-semibold mb-2">Партия #<?= (int)$batch['id'] ?></h2>
  <p class="mb-2"><a class="text-sm text-[#C86052] hover:underline" href="<?= $basePath ?>/purchases/<?= (int)$batch['id'] ?>/pnl.csv">Скачать P&L CSV</a></p>
  <p><b>Товар:</b> <?= htmlspecialchars(trim(($batch['product_name'] ?? '') . ' ' . ($batch['variety'] ?? ''))) ?></p>
  <p><b>Закупщик:</b> <?= htmlspecialchars((string)($batch['buyer_name'] ?? '—')) ?></p>
  <p><b>Статус:</b> <?= htmlspecialchars((string)($statusLabels[(string)$batch['status']] ?? (string)$batch['status'])) ?></p>
  <p><b>Куплено:</b> <?= (float)$batch['boxes_total'] ?> | <b>Свободно:</b> <?= (float)$batch['boxes_free'] ?> | <b>Резерв:</b> <?= (float)$batch['boxes_reserved'] ?></p>
</div>

<?php if ($basePath !== '/buyer'): ?>
<?php
  $isPlanned = ((string)($batch['status'] ?? '') === 'planned');
  $reservedBoxes = isset($reservedIntentBoxes) ? (float)$reservedIntentBoxes : (float)($batch['boxes_reserved'] ?? 0);
?>

<?php if ($isPlanned): ?>
<div class="bg-slate-900 text-slate-100 p-4 rounded-xl shadow mb-4 border border-slate-700">
  <h3 class="font-semibold mb-4 text-lg">Выкуп запланированной закупки</h3>
  <form action="<?= $basePath ?>/purchases/purchased" method="post" enctype="multipart/form-data" class="space-y-4 js-purchase-form">
    <?= csrf_field() ?>
    <input type="hidden" name="batch_id" value="<?= (int)$batch['id'] ?>">
    <input type="hidden" name="product_id" value="<?= (int)$batch['product_id'] ?>">

    <div class="space-y-3 text-sm">
      <div>
        <div class="text-slate-400 mb-1">Товар</div>
        <div class="rounded-lg border border-slate-600 bg-slate-800 px-3 py-2 font-semibold">
          <?= htmlspecialchars(trim(($batch['product_name'] ?? '') . ' ' . ($batch['variety'] ?? ''))) ?>
        </div>
      </div>

      <div>
        <div class="text-slate-400 mb-1">Статус</div>
        <div class="rounded-lg border border-slate-600 bg-slate-800 px-3 py-2">Запланирована</div>
      </div>

      <label class="block">
        <span class="text-slate-400 block mb-1">Дата закупки</span>
        <input name="planned_supply_date" type="date" value="<?= htmlspecialchars(substr((string)$batch['purchased_at'], 0, 10)) ?>" class="w-full rounded-lg border border-slate-600 bg-slate-800 px-3 py-2 text-slate-100">
      </label>

      <label class="block">
        <span class="text-slate-400 block mb-1">Закупка</span>
        <input name="purchase_price_per_box" type="number" step="1" value="<?= (int)round((float)$batch['purchase_price_per_box']) ?>" class="w-full rounded-lg border border-slate-600 bg-slate-800 px-3 py-2 text-slate-100 js-purchase-price" required>
      </label>

      <label class="block">
        <span class="text-slate-400 block mb-1">В наличии</span>
        <input name="instant_price_per_box" type="number" step="1" value="<?= (int)round((float)($batch['instant_price_per_box'] ?? 0)) ?>" class="w-full rounded-lg border border-slate-600 bg-slate-800 px-3 py-2 text-slate-100 js-instant-price" data-auto="1">
      </label>

      <label class="block">
        <span class="text-slate-400 block mb-1">Предзаказ</span>
        <input name="preorder_price_per_box" type="number" step="1" value="<?= (int)round((float)($batch['preorder_price_per_box'] ?? 0)) ?>" class="w-full rounded-lg border border-slate-600 bg-slate-800 px-3 py-2 text-slate-100 js-preorder-price" data-auto="1">
      </label>

      <div>
        <div class="text-slate-400 mb-1">Забронированные</div>
        <div class="rounded-lg border border-slate-600 bg-slate-800 px-3 py-2 font-semibold"><?= (float)$reservedBoxes ?> ящ.</div>
      </div>

      <label class="block">
        <span class="text-slate-400 block mb-1">Выкуплено ящиков</span>
        <input name="boxes_total" type="number" step="0.01" min="0" value="<?= (float)$batch['boxes_total'] ?>" class="w-full rounded-lg border border-slate-600 bg-slate-800 px-3 py-2 text-slate-100" required>
        <span class="text-xs text-slate-500 mt-1 block">После нажатия “Выкуплено” бронь распределится по очереди, остаток станет свободной продажей.</span>
      </label>

      <label class="block">
        <span class="text-slate-400 block mb-1">Комментарий</span>
        <textarea name="comment" rows="2" class="w-full rounded-lg border border-slate-600 bg-slate-800 px-3 py-2 text-slate-100"><?= htmlspecialchars((string)($batch['comment'] ?? '')) ?></textarea>
      </label>

      <div>
        <span class="text-slate-400 block mb-2">Фото закупки</span>
        <input type="file" class="hidden js-gallery-input" accept="image/*" multiple>
        <input type="file" class="hidden js-camera-input" accept="image/*" capture="environment">
        <input type="file" name="photos[]" class="hidden js-cropped-photo-input" accept="image/webp" multiple>
        <div class="flex flex-wrap gap-2">
          <button type="button" class="rounded-lg border border-slate-500 px-4 py-2 text-sm hover:bg-slate-800 js-open-gallery">Галерея</button>
          <button type="button" class="rounded-lg border border-slate-500 px-4 py-2 text-sm hover:bg-slate-800 js-open-camera">Камера</button>
        </div>
        <div class="mt-2 text-xs text-slate-500 js-photo-status">Фото необязательно. Если фото нет, используется фото из карточки товара.</div>
        <div class="mt-3 flex flex-wrap gap-2 js-photo-preview"></div>
      </div>
    </div>

    <button type="submit" class="w-full md:w-auto bg-emerald-600 hover:bg-emerald-500 text-white px-5 py-3 rounded-lg font-semibold">Выкуплено</button>
  </form>
</div>
<?php else: ?>
<div class="bg-slate-900 text-slate-100 p-4 rounded-xl shadow mb-4 border border-slate-700">
  <h3 class="font-semibold mb-4 text-lg">Редактирование закупки</h3>
  <form action="<?= $basePath ?>/purchases/update" method="post" enctype="multipart/form-data" class="space-y-3 js-purchase-form">
    <?= csrf_field() ?>
    <input type="hidden" name="batch_id" value="<?= (int)$batch['id'] ?>">

    <div class="space-y-3 text-sm">
      <label class="block">
        <span class="text-slate-400 block mb-1">Товар</span>
        <select name="product_id" class="w-full rounded-lg border border-slate-600 bg-slate-800 px-3 py-2 text-slate-100" required>
          <?php foreach (($products ?? []) as $p): ?>
            <option value="<?= (int)$p['id'] ?>" <?= ((int)$batch['product_id'] === (int)$p['id']) ? 'selected' : '' ?>>
              <?= htmlspecialchars(trim(($p['product_name'] ?? '') . ' ' . ($p['variety'] ?? '') . ' ' . ($p['box_size'] ?? '') . ' ' . ($p['box_unit'] ?? ''))) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </label>

      <label class="block">
        <span class="text-slate-400 block mb-1">Статус</span>
        <select name="status" class="w-full rounded-lg border border-slate-600 bg-slate-800 px-3 py-2 text-slate-100">
          <?php foreach (['purchased','arrived'] as $st): ?>
            <option value="<?= $st ?>" <?= (($batch['status'] ?? '') === $st) ? 'selected' : '' ?>><?= htmlspecialchars((string)$statusLabels[$st]) ?></option>
          <?php endforeach; ?>
        </select>
      </label>

      <label class="block"><span class="text-slate-400 block mb-1">Дата</span><input name="planned_supply_date" type="date" value="<?= htmlspecialchars(substr((string)$batch['purchased_at'], 0, 10)) ?>" class="w-full rounded-lg border border-slate-600 bg-slate-800 px-3 py-2 text-slate-100"></label>
      <label class="block"><span class="text-slate-400 block mb-1">Куплено ящиков</span><input name="boxes_total" type="number" step="0.01" value="<?= (float)$batch['boxes_total'] ?>" class="w-full rounded-lg border border-slate-600 bg-slate-800 px-3 py-2 text-slate-100" required></label>
      <label class="block"><span class="text-slate-400 block mb-1">Закупка</span><input name="purchase_price_per_box" type="number" step="1" value="<?= (int)round((float)$batch['purchase_price_per_box']) ?>" class="w-full rounded-lg border border-slate-600 bg-slate-800 px-3 py-2 text-slate-100 js-purchase-price" required></label>
      <label class="block"><span class="text-slate-400 block mb-1">В наличии</span><input name="instant_price_per_box" type="number" step="1" value="<?= (int)round((float)($batch['instant_price_per_box'] ?? 0)) ?>" class="w-full rounded-lg border border-slate-600 bg-slate-800 px-3 py-2 text-slate-100 js-instant-price"></label>
      <label class="block"><span class="text-slate-400 block mb-1">Предзаказ</span><input name="preorder_price_per_box" type="number" step="1" value="<?= (int)round((float)($batch['preorder_price_per_box'] ?? 0)) ?>" class="w-full rounded-lg border border-slate-600 bg-slate-800 px-3 py-2 text-slate-100 js-preorder-price"></label>
      <label class="block"><span class="text-slate-400 block mb-1">Свободно ящиков</span><input name="boxes_free" type="number" step="0.01" value="<?= (float)$batch['boxes_free'] ?>" class="w-full rounded-lg border border-slate-600 bg-slate-800 px-3 py-2 text-slate-100" required></label>
      <label class="block"><span class="text-slate-400 block mb-1">Резерв ящиков</span><input name="boxes_reserved" type="number" step="0.01" value="<?= (float)$batch['boxes_reserved'] ?>" class="w-full rounded-lg border border-slate-600 bg-slate-800 px-3 py-2 text-slate-100" required></label>
      <label class="block"><span class="text-slate-400 block mb-1">Комментарий</span><textarea name="comment" rows="2" class="w-full rounded-lg border border-slate-600 bg-slate-800 px-3 py-2 text-slate-100"><?= htmlspecialchars((string)($batch['comment'] ?? '')) ?></textarea></label>
    </div>

    <button type="submit" class="bg-[#C86052] text-white px-4 py-2 rounded">Сохранить изменения</button>
  </form>
</div>

<div class="bg-slate-900 text-slate-100 p-4 rounded-xl shadow mb-4 border border-slate-700">
  <h3 class="font-semibold mb-3">Этап закупки</h3>
  <div class="flex flex-wrap gap-2">
    <?php if (($batch['status'] ?? '') === 'purchased'): ?>
      <form method="post" action="<?= $basePath ?>/purchases/arrived">
        <?= csrf_field() ?>
        <input type="hidden" name="batch_id" value="<?= (int)$batch['id'] ?>">
        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded">В магазине</button>
      </form>
    <?php else: ?>
      <span class="inline-flex items-center px-3 py-2 rounded bg-slate-800 text-slate-300 text-sm">Этап завершён: готова к выдаче</span>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>

<div class="bg-white p-4 rounded shadow mb-4 border border-amber-200">
  <h3 class="font-semibold text-amber-700 mb-2">Закрытие закупки</h3>
  <form method="post" action="<?= $basePath ?>/purchases/close" onsubmit="return confirm('Закрыть закупку? Данные сохранятся, свободный остаток станет 0.');">
    <?= csrf_field() ?>
    <input type="hidden" name="batch_id" value="<?= (int)$batch['id'] ?>">
    <button type="submit" class="bg-amber-600 text-white px-4 py-2 rounded">Закрыть закупку</button>
  </form>
</div>
<?php endif; ?>


<div class="bg-white p-4 rounded shadow mb-4">
  <h3 class="font-semibold mb-2">P&L партии</h3>
  <div class="grid grid-cols-1 md:grid-cols-2 gap-2 text-sm">
    <p><b>Выручка (продано):</b> <?= number_format((float)($pnl['revenue_sold'] ?? 0), 0, '.', ' ') ?> ₽</p>
    <p><b>Выручка (выгодный остаток):</b> <?= number_format((float)($pnl['revenue_discount'] ?? 0), 0, '.', ' ') ?> ₽</p>
    <p><b>Выручка (итого):</b> <?= number_format((float)($pnl['revenue_total'] ?? 0), 0, '.', ' ') ?> ₽</p>
    <p><b>Себестоимость (продано):</b> <?= number_format((float)($pnl['cost_sold'] ?? 0), 0, '.', ' ') ?> ₽</p>
    <p><b>Себестоимость (выгодный остаток):</b> <?= number_format((float)($pnl['cost_discount'] ?? 0), 0, '.', ' ') ?> ₽</p>
    <p><b>Себестоимость (списано):</b> <?= number_format((float)($pnl['cost_written_off'] ?? 0), 0, '.', ' ') ?> ₽</p>
    <p><b>Себестоимость (признано):</b> <?= number_format((float)($pnl['cost_total_recognized'] ?? 0), 0, '.', ' ') ?> ₽</p>
    <p><b>Валовая маржа:</b> <?= number_format((float)($pnl['gross_margin'] ?? 0), 0, '.', ' ') ?> ₽</p>
    <p><b>Остаток в деньгах:</b> <?= number_format((float)($pnl['inventory_value_remaining'] ?? 0), 0, '.', ' ') ?> ₽</p>
  </div>
</div>

<div class="bg-white p-4 rounded shadow mb-4">
  <h3 class="font-semibold mb-2">Фото партии</h3>
  <?php if ($photos === []): ?>
    <p class="text-gray-500">Фото пока не добавлены.</p>
  <?php else: ?>
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
      <?php foreach ($photos as $photo): ?>
        <div>
          <img src="<?= htmlspecialchars((string)$photo['image_path']) ?>" class="w-full h-32 object-cover rounded" alt="Фото партии">
          <?php if ($basePath !== '/buyer'): ?>
            <form method="post" action="<?= $basePath ?>/purchases/photos/delete" class="mt-1">
              <?= csrf_field() ?>
              <input type="hidden" name="photo_id" value="<?= (int)$photo['id'] ?>">
              <button type="submit" class="text-xs text-red-600 hover:underline">Удалить фото</button>
            </form>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<div class="bg-white p-4 rounded shadow">
  <h3 class="font-semibold mb-2">Движения склада</h3>
  <table class="min-w-full">
    <thead>
      <tr class="text-left border-b">
        <th class="py-2">Тип</th>
        <th class="py-2">Режим</th>
        <th class="py-2">Δ ящиков</th>
        <th class="py-2">Пользователь</th>
        <th class="py-2">Комментарий</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($movements as $m): ?>
        <tr class="border-b">
          <td class="py-2"><?= htmlspecialchars((string)$m['movement_type']) ?></td>
          <td class="py-2"><?= htmlspecialchars((string)$m['stock_mode']) ?></td>
          <td class="py-2"><?= (float)$m['boxes_delta'] ?></td>
          <td class="py-2"><?= htmlspecialchars((string)($m['user_name'] ?? '—')) ?></td>
          <td class="py-2"><?= htmlspecialchars((string)($m['comment'] ?? '')) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php if ($basePath !== '/buyer'): ?>
<div class="md:hidden fixed bottom-0 left-0 right-0 z-40 bg-white border-t border-gray-200 p-3">
  <div class="grid grid-cols-3 gap-2">
    <a href="<?= $basePath ?>/purchases" class="h-10 rounded-lg border border-gray-300 text-gray-700 text-sm flex items-center justify-center">Вернуться</a>
    <button type="submit" form="" onclick="document.querySelector('form[action$=\"/purchases/update\"]')?.requestSubmit();" class="h-10 rounded-lg border border-gray-300 text-gray-700 text-sm">Сохранить</button>
    <?php if (($batch['status'] ?? '') === 'planned'): ?>
      <button type="submit" onclick="document.querySelector('form[action$=\"/purchases/purchased\"]')?.requestSubmit();" class="h-10 rounded-lg bg-emerald-600 text-white text-sm">Выкуплено</button>
    <?php elseif (($batch['status'] ?? '') === 'purchased'): ?>
      <button type="submit" onclick="document.querySelector('form[action$=\"/purchases/arrived\"]')?.requestSubmit();" class="h-10 rounded-lg bg-blue-600 text-white text-sm">В магазине</button>
    <?php else: ?>
      <button type="button" disabled class="h-10 rounded-lg bg-gray-200 text-gray-500 text-sm">Готово</button>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.js-purchase-form').forEach((form) => {
    const purchase = form.querySelector('.js-purchase-price');
    const instant = form.querySelector('.js-instant-price');
    const preorder = form.querySelector('.js-preorder-price');
    if (!purchase || !instant || !preorder) return;

    const roundToStep = (value, step = 10) => Math.round(value / step) * step;
    const recalc = () => {
      const base = parseFloat(String(purchase.value).replace(',', '.')) || 0;
      if (base <= 0) return;
      instant.value = String(roundToStep(base * 1.50, 10));
      preorder.value = String(roundToStep(base * 1.35, 10));
    };
    purchase.addEventListener('input', recalc);
    purchase.addEventListener('change', recalc);
  });

  const galleryBtn = document.querySelector('.js-open-gallery');
  const cameraBtn = document.querySelector('.js-open-camera');
  const galleryInput = document.querySelector('.js-gallery-input');
  const cameraInput = document.querySelector('.js-camera-input');
  const outputInput = document.querySelector('.js-cropped-photo-input');
  const statusNode = document.querySelector('.js-photo-status');
  const previewNode = document.querySelector('.js-photo-preview');
  if (!outputInput || !galleryInput || !cameraInput) return;

  const dt = new DataTransfer();
  let currentFile = null;
  let image = null;
  let imageUrl = '';
  let scale = 1;
  let offsetX = 0;
  let offsetY = 0;
  let isDragging = false;
  let startX = 0;
  let startY = 0;

  const modal = document.createElement('div');
  modal.className = 'fixed inset-0 z-50 hidden items-center justify-center bg-black/80 p-4';
  modal.innerHTML = `
    <div class="w-full max-w-md rounded-2xl bg-slate-900 border border-slate-700 p-4 text-slate-100 shadow-2xl">
      <div class="flex items-center justify-between mb-3">
        <div class="font-semibold">Обрезка фото под квадрат</div>
        <button type="button" class="text-slate-400 hover:text-white js-crop-close">✕</button>
      </div>
      <div class="relative mx-auto h-72 w-72 overflow-hidden rounded-xl bg-slate-950 border border-slate-700 touch-none js-crop-area">
        <img class="absolute left-1/2 top-1/2 max-w-none select-none js-crop-img" draggable="false" alt="Фото закупки">
        <div class="pointer-events-none absolute inset-0 ring-2 ring-pink-500/80 rounded-xl"></div>
      </div>
      <label class="block mt-4 text-sm text-slate-300">Масштаб</label>
      <input type="range" min="1" max="3" step="0.01" value="1" class="w-full js-crop-scale">
      <div class="mt-4 grid grid-cols-2 gap-2">
        <button type="button" class="rounded-lg border border-slate-600 px-4 py-2 js-crop-cancel">Отмена</button>
        <button type="button" class="rounded-lg bg-pink-500 px-4 py-2 font-semibold text-white js-crop-apply">Добавить</button>
      </div>
      <p class="mt-2 text-xs text-slate-500">Перетащите фото пальцем или мышью, чтобы выбрать нужную часть.</p>
    </div>`;
  document.body.appendChild(modal);

  const cropArea = modal.querySelector('.js-crop-area');
  const cropImg = modal.querySelector('.js-crop-img');
  const scaleInput = modal.querySelector('.js-crop-scale');

  const render = () => {
    if (!image || !cropImg) return;
    const area = 288;
    const base = Math.max(area / image.naturalWidth, area / image.naturalHeight);
    const finalScale = base * scale;
    cropImg.style.width = `${image.naturalWidth * finalScale}px`;
    cropImg.style.height = `${image.naturalHeight * finalScale}px`;
    cropImg.style.transform = `translate(calc(-50% + ${offsetX}px), calc(-50% + ${offsetY}px))`;
  };

  const openCropper = (file) => {
    currentFile = file;
    image = new Image();
    imageUrl = URL.createObjectURL(file);
    scale = 1;
    offsetX = 0;
    offsetY = 0;
    scaleInput.value = '1';
    image.onload = () => {
      cropImg.src = imageUrl;
      modal.classList.remove('hidden');
      modal.classList.add('flex');
      render();
    };
    image.src = imageUrl;
  };

  const closeCropper = () => {
    modal.classList.add('hidden');
    modal.classList.remove('flex');
    if (imageUrl) URL.revokeObjectURL(imageUrl);
    imageUrl = '';
    currentFile = null;
    image = null;
  };

  const addCroppedFile = async () => {
    if (!image || !currentFile) return closeCropper();
    const area = 288;
    const canvas = document.createElement('canvas');
    canvas.width = 900;
    canvas.height = 900;
    const ctx = canvas.getContext('2d');
    if (!ctx) return closeCropper();

    const base = Math.max(area / image.naturalWidth, area / image.naturalHeight);
    const finalScale = base * scale;
    const shownW = image.naturalWidth * finalScale;
    const shownH = image.naturalHeight * finalScale;
    const left = area / 2 - shownW / 2 + offsetX;
    const top = area / 2 - shownH / 2 + offsetY;
    const ratio = canvas.width / area;

    ctx.fillStyle = '#ffffff';
    ctx.fillRect(0, 0, canvas.width, canvas.height);
    ctx.drawImage(image, left * ratio, top * ratio, shownW * ratio, shownH * ratio);

    canvas.toBlob((blob) => {
      if (blob) {
        const safeName = (currentFile.name || 'photo').replace(/\.[^.]+$/, '') + '.webp';
        const file = new File([blob], safeName, { type: 'image/webp' });
        dt.items.add(file);
        outputInput.files = dt.files;
        if (statusNode) statusNode.textContent = `Добавлено фото: ${dt.files.length}`;
        if (previewNode) {
          const img = document.createElement('img');
          img.src = URL.createObjectURL(file);
          img.className = 'h-16 w-16 rounded-lg object-cover border border-slate-600';
          previewNode.appendChild(img);
        }
      }
      closeCropper();
    }, 'image/webp', 0.88);
  };

  const handleFiles = (files) => {
    const first = Array.from(files || []).find((file) => file.type && file.type.startsWith('image/'));
    if (first) openCropper(first);
  };

  galleryBtn?.addEventListener('click', () => galleryInput.click());
  cameraBtn?.addEventListener('click', () => cameraInput.click());
  galleryInput.addEventListener('change', () => handleFiles(galleryInput.files));
  cameraInput.addEventListener('change', () => handleFiles(cameraInput.files));

  modal.querySelector('.js-crop-close')?.addEventListener('click', closeCropper);
  modal.querySelector('.js-crop-cancel')?.addEventListener('click', closeCropper);
  modal.querySelector('.js-crop-apply')?.addEventListener('click', addCroppedFile);
  scaleInput.addEventListener('input', () => {
    scale = parseFloat(scaleInput.value) || 1;
    render();
  });

  cropArea.addEventListener('pointerdown', (e) => {
    isDragging = true;
    startX = e.clientX - offsetX;
    startY = e.clientY - offsetY;
    cropArea.setPointerCapture(e.pointerId);
  });
  cropArea.addEventListener('pointermove', (e) => {
    if (!isDragging) return;
    offsetX = e.clientX - startX;
    offsetY = e.clientY - startY;
    render();
  });
  cropArea.addEventListener('pointerup', () => { isDragging = false; });
  cropArea.addEventListener('pointercancel', () => { isDragging = false; });
});
</script>
