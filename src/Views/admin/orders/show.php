<?php /** @var array $order @var array $items @var array $addresses @var array $slots @var array $products */ ?>
<?php $role = $_SESSION['role'] ?? ''; $isManager = in_array($role, ['manager','partner'], true); $base = $role === 'manager' ? '/manager' : ($role === 'partner' ? '/partner' : '/admin'); ?>
<style>
  .order-details { font-size: 0.82rem; gap: 0.35rem; padding-bottom: 4.4rem; }
  .order-details .card { border-radius: 0.7rem; padding: 0.4rem; }
  .order-details .row-gap { gap: 0.5rem; }
  .order-details button,
  .order-details .action-link,
  .order-details input,
  .order-details select,
  .order-details textarea { font-size: 0.84rem; line-height: 1.2; }
  .order-details .header-meta { gap: 0.35rem 0.5rem; }
  .order-details .header-meta span:nth-child(1) { width: 100%; }
  .order-item-card {
    border: 1px solid rgba(255,255,255,.08);
    border-radius: 0.85rem;
    padding: 0.45rem;
    background: rgba(10, 21, 44, 0.25);
    transition: border-color .2s ease, box-shadow .2s ease, transform .2s ease;
  }
  .order-item-card:focus-within {
    border-color: rgba(200, 96, 82, 0.8);
    box-shadow: 0 0 0 3px rgba(200, 96, 82, 0.2);
    transform: translateY(-1px);
  }
  .item-topline { display: flex; justify-content: space-between; gap: 0.5rem; align-items: flex-start; }
  .item-name { font-weight: 600; line-height: 1.3; font-size: 0.9rem; }
  .item-subline { opacity: 0.85; font-size: 0.74rem; }
  .item-editor {
    margin-top: 0.35rem;
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.35rem;
    align-items: end;
  }
  .item-editor label { display: flex; flex-direction: column; gap: 0.2rem; font-size: 0.72rem; opacity: 0.9; }
  .item-editor .line-total {
    grid-column: span 2;
    border-top: 1px dashed rgba(255,255,255,.2);
    padding-top: 0.25rem;
    font-weight: 700;
    display: flex;
    justify-content: space-between;
    align-items: center;
  }
  .item-actions {
    grid-column: span 2;
    display: flex;
    gap: 0.35rem;
    justify-content: flex-end;
  }
  .item-actions button { min-height: 2rem; min-width: 2rem; }
  .order-details .sticky-summary {
    position: fixed;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: 40;
    padding: 0.4rem 0.5rem max(0.4rem, env(safe-area-inset-bottom));
    background: linear-gradient(90deg, rgba(5,10,20,.95), rgba(20,36,69,.95));
    backdrop-filter: blur(8px);
    border-top: 1px solid rgba(255,255,255,.12);
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 0.4rem;
  }
  .order-details .delivery-fields { display: grid; grid-template-columns: 1fr; gap: 0.3rem; }
  .order-details .delivery-fields label { display: flex; flex-direction: column; gap: 0.2rem; }
  .order-details .status-modal-buttons { display: grid; grid-template-columns: 1fr; gap: 0.35rem; max-width: 14rem; margin: 0 auto; }
  .order-details .status-modal-buttons button { width: 100%; min-height: 2.1rem; border-radius: 0.65rem; }
  .order-details .status-open-btn { border: 0; cursor: pointer; }
  .order-details .status-dialog { border: 0; border-radius: 0.9rem; padding: 0; max-width: 18rem; width: calc(100% - 2rem); margin: auto; }
  .order-details .status-dialog::backdrop { background: rgba(3, 6, 14, 0.68); backdrop-filter: blur(2px);}
  .order-details .status-dialog-content { padding: 0.55rem; text-align: center; }
  .order-details .status-dialog-header { justify-content: center; position: relative; }
  .order-details .status-dialog-close { position: absolute; right: 0; top: 0; }
  .order-details .address-select { max-width: 100%; width: 100%; }
  .order-details .address-select option { white-space: normal; word-break: break-word; }

  @media (min-width: 768px) {
    .order-details { font-size: 0.9rem; gap: 1rem; padding-bottom: 1rem; }
    .order-details .card { border-radius: 1rem; padding: 1rem; }
    .order-details .header-meta span:nth-child(1) { width: auto; }
    .item-editor { grid-template-columns: 1.1fr 1.1fr 0.9fr; }
    .item-editor .line-total { grid-column: span 3; }
    .item-actions { grid-column: span 3; }
    .order-details .delivery-fields { grid-template-columns: 1.5fr 1fr 1fr; }
    .order-details .sticky-summary { position: static; border-radius: 0.9rem; border: 0; margin-top: 0.2rem; }
    .order-details .status-modal-buttons { grid-template-columns: 1fr; }
  }
</style>
<div class="order-details space-y-2 md:space-y-4">
  <div class="flex justify-between items-center bg-white p-2 md:p-4 rounded shadow card">
    <div class="flex flex-wrap items-center header-meta">
      <span class="font-semibold">Заказ #<?= $order['id'] ?></span>
      <span><?= htmlspecialchars($order['client_name']) ?></span>
      <span><?= htmlspecialchars($order['phone']) ?></span>
    </div>
    <div class="flex items-start">
      <?php $info = order_status_info($order['status']); ?>
      <button type="button" class="status-open-btn inline-flex items-center px-2 py-0.5 rounded-full text-sm font-medium <?= $info['badge'] ?>" data-open-status-modal="true" title="Изменить статус">
        <?= $info['label'] ?>
      </button>
    </div>
  </div>

  <div class="bg-white p-2 md:p-4 rounded shadow card space-y-1">
    <?php foreach ($items as $it): ?>
      <?php $lineCost = $it['quantity'] * $it['unit_price']; ?>
      <?php $boxSize = (float)($it['box_size'] ?? 1); if ($boxSize <= 0) { $boxSize = 1.0; } ?>
      <?php $boxesQty = (float)$it['quantity'] / $boxSize; ?>
      <?php $boxPrice = (float)$it['unit_price'] * $boxSize; ?>
      <div class="order-item-card">
        <div class="item-topline">
          <div>
            <div class="item-name">
              <?= htmlspecialchars($it['product_name']) ?>
              <?php if (!empty($it['variety'])): ?> <?= htmlspecialchars($it['variety']) ?><?php endif; ?>
            </div>
            <?php if (!empty($it['box_size']) && !empty($it['box_unit'])): ?>
              <div class="item-subline"><?= $it['box_size'] . ' ' . htmlspecialchars($it['box_unit']) ?></div>
            <?php endif; ?>
          </div>
        </div>
        <form action="<?= $base ?>/orders/update-item" method="post" class="item-editor" data-autosave="true">
          <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
          <input type="hidden" name="product_id" value="<?= $it['product_id'] ?>">
          <input type="hidden" name="quantity" value="<?= $it['quantity'] ?>" data-base-field="quantity">
          <input type="hidden" name="unit_price" value="<?= $it['unit_price'] ?>" data-base-field="unit_price">
          <label>Количество ящиков
            <input type="number" value="<?= rtrim(rtrim(number_format($boxesQty, 2, '.', ''), '0'), '.') ?>" step="1" min="1" class="w-full border px-2 py-1 rounded" data-ui-field="boxes" data-box-size="<?= htmlspecialchars((string)$boxSize) ?>">
          </label>
          <label>Цена за ящик (₽)
            <input type="number" value="<?= rtrim(rtrim(number_format($boxPrice, 2, '.', ''), '0'), '.') ?>" step="1" min="0" class="w-full border px-2 py-1 rounded" data-ui-field="box_price">
          </label>
          <div class="line-total">
            <span>Сумма позиции</span>
            <span><?= number_format($lineCost, 0, '.', ' ') ?> ₽</span>
          </div>
          <div class="item-actions">
            <button type="submit" class="bg-[#C86052] text-white px-2 py-1 rounded" title="Сохранить">Сохранить</button>
            <button type="submit" formaction="<?= $base ?>/orders/delete-item" class="text-red-600 border border-red-500 px-2 py-1 rounded" onclick="return confirm('Удалить позицию?');" title="Удалить">Удалить</button>
          </div>
        </form>
      </div>
    <?php endforeach; ?>

    <form action="<?= $base ?>/orders/add-item" method="post" class="pt-2 border-t item-editor">
      <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
      <label class="col-span-2">Товар
      <select name="product_id" class="border px-2 py-1 rounded w-full">
        <?php foreach ($products as $p): ?>
          <?php $productBoxSize = (float)($p['box_size'] ?? 1); if ($productBoxSize <= 0) { $productBoxSize = 1.0; } ?>
          <?php $productPricePerKg = (float)($p['price'] ?? 0); ?>
          <option value="<?= $p['id'] ?>" data-box-size="<?= htmlspecialchars((string)$productBoxSize) ?>" data-price-per-kg="<?= htmlspecialchars((string)$productPricePerKg) ?>"><?= htmlspecialchars($p['product']) ?><?php if(!empty($p['variety'])): ?> <?= htmlspecialchars($p['variety']) ?><?php endif; ?></option>
        <?php endforeach; ?>
      </select>
      </label>
      <input type="hidden" name="quantity" value="" data-base-field="quantity">
      <input type="hidden" name="unit_price" value="" data-base-field="unit_price">
      <label>Количество ящиков
      <input type="number" step="1" min="1" value="1" placeholder="1" class="w-full border px-2 py-1 rounded" data-ui-field="boxes">
      </label>
      <label>Цена за ящик (₽)
      <input type="number" step="1" min="0" value="" placeholder="0" class="w-full border px-2 py-1 rounded" data-ui-field="box_price">
      </label>
      <div class="item-actions">
        <button type="submit" class="bg-green-700 text-white px-3 py-1 rounded" title="Добавить">Добавить позицию</button>
      </div>
    </form>

    <?php if (($pointsFromBalance ?? 0) > 0): ?>
      <div class="flex justify-between text-pink-600 py-1 border-t">
        <span>Списание клубничек</span>
        <span>-<?= $pointsFromBalance ?></span>
      </div>
    <?php endif; ?>

    <?php if (!empty($coupon)): ?>
      <div class="flex justify-between py-1">
        <span>Купон <?= htmlspecialchars($coupon['code']) ?></span>
        <span>
          <?php if ($coupon['type'] === 'discount'): ?>
            -<?= htmlspecialchars($coupon['discount']) ?>%
          <?php else: ?>
            -<?= htmlspecialchars($coupon['points']) ?> клубничек
          <?php endif; ?>
        </span>
      </div>
    <?php endif; ?>

    <div class="flex justify-between font-bold border-t pt-2">
      <span>Итого:</span>
      <span><?= $order['total_amount'] ?> ₽</span>
    </div>
  </div>

  <form action="<?= $base ?>/orders/comment" method="post" class="bg-white p-2 md:p-4 rounded shadow card space-y-1 md:space-y-2" data-autosave="true">
    <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
    <label class="block">
      <span class="block mb-1">Комментарий:</span>
      <textarea name="comment" rows="3" class="w-full border px-2 py-1 rounded"><?= htmlspecialchars($order['comment'] ?? '') ?></textarea>
    </label>
  </form>

  <form action="<?= $base ?>/orders/referral" method="post" class="bg-white p-2 md:p-4 rounded shadow card space-y-1 md:space-y-2" data-autosave="true">
    <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
    <input type="hidden" name="user_id" value="<?= $order['user_id'] ?>">
    <label class="inline-flex items-center cursor-pointer">
      <input type="hidden" name="has_used_referral_coupon" value="0">
      <input type="checkbox" name="has_used_referral_coupon" value="1" class="sr-only peer" <?= ($order['has_used_referral_coupon'] ?? 0) ? 'checked' : '' ?>>
      <div class="w-10 h-5 bg-gray-200 rounded-full peer-checked:bg-[#C86052] relative transition-colors after:content-[''] after:absolute after:left-1 after:top-0.5 after:bg-white after:rounded-full after:h-4 after:w-4 after:transition-transform peer-checked:after:translate-x-5"></div>
      <span class="ml-2 text-sm">Скидка 10% на первый заказ</span>
    </label>
  </form>

  <form action="<?= $base ?>/orders/update-delivery" method="post" class="bg-white p-2 md:p-4 rounded shadow card space-y-1 md:space-y-2" data-autosave="true">
    <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
    <div class="delivery-fields">
      <label>
        <span class="mr-1">Адрес:</span>
        <select name="address_id" class="border px-2 py-1 rounded address-select">
          <?php foreach ($addresses as $a): ?>
            <option value="<?= $a['id'] ?>" title="<?= htmlspecialchars($a['street']) ?>" <?= $a['id'] == $order['address_id'] ? 'selected' : '' ?>><?= htmlspecialchars($a['street']) ?></option>
          <?php endforeach; ?>
          <option value="pickup" <?= $order['address_id'] === null ? 'selected' : '' ?>>Самовывоз 9 мая 73</option>
        </select>
      </label>
      <label>
        <span class="mr-1">Дата:</span>
        <input type="date" name="delivery_date" value="<?= htmlspecialchars($order['delivery_date']) ?>" class="border px-2 py-1 rounded">
      </label>
      <label>
        <span class="mr-1">Слот:</span>
        <select name="slot_id" class="border px-2 py-1 rounded">
          <?php foreach ($slots as $s): ?>
            <option value="<?= $s['id'] ?>" <?= $s['id'] == $order['slot_id'] ? 'selected' : '' ?>><?= htmlspecialchars(format_time_range($s['time_from'], $s['time_to'])) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
    </div>
  </form>

  <?php $btnClasses = [
      'processing' => 'bg-yellow-700 hover:bg-yellow-800',
      'assigned'   => 'bg-green-700 hover:bg-green-800',
      'delivered'  => 'bg-gray-700 hover:bg-gray-800',
      'cancelled'  => 'bg-gray-600 hover:bg-gray-700',
  ]; ?>
  <dialog class="status-dialog" data-status-dialog>
    <div class="status-dialog-content bg-white">
      <div class="flex items-center mb-3 status-dialog-header">
        <h3 class="font-semibold">Изменить статус заказа</h3>
        <button type="button" class="px-2 py-1 rounded border status-dialog-close" data-close-status-modal>✕</button>
      </div>
      <div class="status-modal-buttons">
        <?php foreach ([
            'processing' => 'Принят',
            'assigned'   => 'В работе',
            'delivered'  => 'Выполнен',
            'cancelled'  => 'Отменен'
          ] as $st => $label): ?>
          <form action="<?= $base ?>/orders/status" method="post">
            <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
            <input type="hidden" name="status" value="<?= $st ?>">
            <button class="px-3 py-1 rounded text-white <?= $btnClasses[$st] ?>" type="submit"><?= $label ?></button>
          </form>
        <?php endforeach; ?>
      </div>
    </div>
  </dialog>

  <div class="card bg-white shadow">
    <form action="<?= $base ?>/orders/delete" method="post" onsubmit="return confirm('Удалить этот заказ?');">
      <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
      <button class="px-2 py-1 text-xs rounded text-red-700 border border-red-700 hover:bg-red-700 hover:text-white" type="submit" title="Удалить заказ">Удалить заказ</button>
    </form>
  </div>

  <div class="sticky-summary">
    <div class="font-semibold">Итого: <?= $order['total_amount'] ?> ₽</div>
    <a href="<?= $base ?>/orders" class="inline-block px-4 py-2 rounded text-white bg-purple-700 hover:bg-purple-800 action-link" title="К заказам">К заказам</a>
  </div>

</div>

<script>
  (() => {
    const syncBoxInputs = (form) => {
      const quantityBase = form.querySelector('[data-base-field="quantity"]');
      const unitPriceBase = form.querySelector('[data-base-field="unit_price"]');
      const boxesField = form.querySelector('[data-ui-field="boxes"]');
      const boxPriceField = form.querySelector('[data-ui-field="box_price"]');
      if (!quantityBase || !unitPriceBase || !boxesField || !boxPriceField) return;

      let boxSize = Number(boxesField.dataset.boxSize || 0);
      if (!Number.isFinite(boxSize) || boxSize <= 0) {
        const productSelect = form.querySelector('select[name="product_id"]');
        if (productSelect && productSelect.selectedOptions.length > 0) {
          boxSize = Number(productSelect.selectedOptions[0].dataset.boxSize || 1);
        } else {
          boxSize = 1;
        }
      }
      if (!Number.isFinite(boxSize) || boxSize <= 0) boxSize = 1;

      const boxes = Number(boxesField.value || 0);
      const boxPrice = Number(boxPriceField.value || 0);
      quantityBase.value = Number.isFinite(boxes) ? String(boxes * boxSize) : '0';
      unitPriceBase.value = Number.isFinite(boxPrice) ? String(boxPrice / boxSize) : '0';
    };

    const updateAddItemDefaults = (form, force = false) => {
      const productSelect = form.querySelector('select[name="product_id"]');
      const boxesField = form.querySelector('[data-ui-field="boxes"]');
      const boxPriceField = form.querySelector('[data-ui-field="box_price"]');
      if (!productSelect || !boxesField || !boxPriceField) return;

      const selected = productSelect.selectedOptions[0];
      if (!selected) return;
      const boxSize = Number(selected.dataset.boxSize || 1);
      const pricePerKg = Number(selected.dataset.pricePerKg || 0);
      const pricePerBox = pricePerKg * (boxSize > 0 ? boxSize : 1);

      if (force || !boxesField.value) {
        boxesField.value = '1';
      }
      if (force || !boxPriceField.value || boxPriceField.dataset.autofilled === '1') {
        boxPriceField.value = String(Math.round(pricePerBox));
        boxPriceField.dataset.autofilled = '1';
      }
    };

    document.querySelectorAll('.item-editor').forEach((form) => {
      const isAddItemForm = form.action.includes('/orders/add-item');
      if (isAddItemForm) {
        updateAddItemDefaults(form, true);
      }
      syncBoxInputs(form);
      form.querySelectorAll('[data-ui-field="boxes"], [data-ui-field="box_price"], select[name="product_id"]').forEach((field) => {
        if (isAddItemForm && field.matches('[data-ui-field="box_price"]')) {
          field.addEventListener('input', () => { field.dataset.autofilled = '0'; });
        }
        if (isAddItemForm && field.matches('select[name="product_id"]')) {
          field.addEventListener('change', () => updateAddItemDefaults(form, true));
        }
        field.addEventListener('input', () => syncBoxInputs(form));
        field.addEventListener('change', () => syncBoxInputs(form));
      });
      form.addEventListener('submit', () => syncBoxInputs(form));
    });


    const statusDialog = document.querySelector('[data-status-dialog]');
    const openStatusBtn = document.querySelector('[data-open-status-modal="true"]');
    if (statusDialog && openStatusBtn) {
      openStatusBtn.addEventListener('click', () => statusDialog.showModal());
      statusDialog.querySelectorAll('[data-close-status-modal]').forEach((btn) => {
        btn.addEventListener('click', () => statusDialog.close());
      });
      statusDialog.addEventListener('click', (event) => {
        const rect = statusDialog.getBoundingClientRect();
        const inside = rect.top <= event.clientY && event.clientY <= rect.bottom
          && rect.left <= event.clientX && event.clientX <= rect.right;
        if (!inside) statusDialog.close();
      });
    }

    document.querySelectorAll('form[data-autosave="true"]').forEach((form) => {
      let dirty = false;
      form.querySelectorAll('input, select, textarea').forEach((field) => {
        if (field.type === 'hidden') return;
        field.addEventListener('change', () => { dirty = true; });
        field.addEventListener('blur', () => {
          if (!dirty) return;
          dirty = false;
          if (typeof form.requestSubmit === 'function') {
            form.requestSubmit();
          } else {
            form.submit();
          }
        });
      });
    });
  })();
</script>
