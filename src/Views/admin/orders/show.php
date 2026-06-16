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
    <div class="flex flex-wrap items-start gap-2">
      <?php $info = order_status_info($order['status']); ?>
      <button type="button" class="status-open-btn inline-flex items-center px-2 py-0.5 rounded-full text-sm font-medium <?= $info['badge'] ?>" data-open-status-modal="true" title="Изменить статус">
        <?= $info['label'] ?>
      </button>
      <?php $paymentInfo = payment_status_info($order['payment_status'] ?? null); ?>
      <span class="inline-flex items-center px-2 py-0.5 rounded-full text-sm font-medium <?= $paymentInfo['badge'] ?>" title="Способ оплаты: <?= htmlspecialchars(payment_method_label($order['payment_method'] ?? null)) ?>">
        <?= htmlspecialchars($paymentInfo['label']) ?>
      </span>
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
          <?= csrf_field() ?>
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
      <?= csrf_field() ?>
      <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
      <label class="col-span-2">Товар
      <select name="product_id" class="border px-2 py-1 rounded w-full">
        <?php foreach ($products as $p): ?>
          <?php $productBoxSize = (float)($p['box_size'] ?? 1); if ($productBoxSize <= 0) { $productBoxSize = 1.0; } ?>
          <?php $productPricePerBox = (float)($p['price_per_box'] ?? (($p['price'] ?? 0) * $productBoxSize)); ?>
          <option value="<?= $p['id'] ?>" data-box-size="<?= htmlspecialchars((string)$productBoxSize) ?>" data-price-per-box="<?= htmlspecialchars((string)$productPricePerBox) ?>"><?= htmlspecialchars($p['product']) ?><?php if(!empty($p['variety'])): ?> <?= htmlspecialchars($p['variety']) ?><?php endif; ?></option>
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

    <?php
      $deliveryFee = max(0, (int)($order['delivery_fee'] ?? 0));
      $deliveryDistance = $order['delivery_distance_km'] ?? null;
      $deliverySource = (string)($order['delivery_pricing_source'] ?? '');
      $deliveryComment = trim((string)($order['delivery_comment'] ?? ''));
    ?>
    <?php if ($deliveryFee > 0 || $deliveryDistance !== null || $deliveryComment !== ''): ?>
      <div class="border-t py-2 space-y-1 text-sm">
        <div class="flex justify-between">
          <span>Доставка<?php if ($deliveryDistance !== null && $deliveryDistance !== ''): ?> · <?= htmlspecialchars((string)$deliveryDistance) ?> км<?php endif; ?></span>
          <span><?= number_format($deliveryFee, 0, '.', ' ') ?> ₽</span>
        </div>
        <?php if ($deliverySource !== ''): ?>
          <div class="text-xs text-gray-500">Источник расчёта: <?= htmlspecialchars($deliverySource) ?></div>
        <?php endif; ?>
        <?php if ($deliveryComment !== ''): ?>
          <div class="text-xs text-gray-600">Комментарий доставки: <?= nl2br(htmlspecialchars($deliveryComment)) ?></div>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <div class="flex justify-between font-bold border-t pt-2">
      <span>Итого:</span>
      <span><?= $order['total_amount'] ?> ₽</span>
    </div>
  </div>


  <?php
    $productionStatusLabels = [
      'new' => 'Новое',
      'assigned' => 'Назначено',
      'materials_pending' => 'Ждёт материалы',
      'materials_sent' => 'Материалы отправлены',
      'materials_received' => 'Материалы получены',
      'in_progress' => 'В работе',
      'photo_uploaded' => 'Фото загружено',
      'approved' => 'Принято менеджером',
      'ready_for_handover' => 'Готово к передаче',
      'handed_over' => 'Передано',
      'completed' => 'Завершено',
      'cancelled' => 'Отменено',
      'problem' => 'Проблема',
    ];
    $fulfillmentLabels = [
      'by_berrygo_on_site' => 'berryGo · на смене',
      'by_berrygo_remote' => 'berryGo · удалённо',
      'by_partner_under_berrygo_brand' => 'Партнёр под брендом berryGo',
      'by_seller' => 'Исполнение селлером',
      'by_berrygo_from_seller_stock' => 'berryGo со склада селлера',
    ];
    $productionLocationLabels = [
      'shop' => 'На точке berryGo',
      'remote' => 'Удалённо',
      'partner' => 'У партнёра',
      'seller' => 'У селлера',
    ];
    $bonusLabels = [
      'salary' => 'В зарплате',
      'internal_bonus' => 'Внутренний бонус',
      'fixed_payout' => 'Фикс. выплата',
      'commission' => 'Комиссия',
      'subscription' => 'Абонентка',
      'commission_plus_subscription' => 'Комиссия + абонентка',
      'fixed_fee_per_order' => 'Фикс за заказ',
    ];
    $productionJobs = $productionJobs ?? [];
    $productionExecutors = $productionExecutors ?? [];
  ?>
  <div class="bg-white p-2 md:p-4 rounded shadow card space-y-3">
    <div class="flex flex-wrap items-center justify-between gap-2">
      <div>
        <h2 class="font-semibold">Производство</h2>
        <p class="text-xs text-gray-500">Производственные задания остаются внутри этого заказа и не создают отдельный заказ.</p>
      </div>
      <span class="text-xs rounded-full bg-slate-100 px-2 py-1 text-slate-600">Заданий: <?= count($productionJobs) ?></span>
    </div>

    <?php if (empty($productionJobs)): ?>
      <div class="rounded border border-dashed border-slate-300 p-3 text-sm text-gray-600">Пока нет производственных заданий для этого заказа.</div>
    <?php else: ?>
      <div class="space-y-2">
        <?php foreach ($productionJobs as $job): ?>
          <?php
            $jobStatus = (string)($job['status'] ?? 'new');
            $executorId = (int)($job['executor_id'] ?? 0);
            $events = $job['events'] ?? [];
          ?>
          <div class="rounded border border-slate-200 p-3 space-y-2">
            <div class="flex flex-wrap items-center justify-between gap-2">
              <div class="font-semibold">Задание #<?= (int)$job['id'] ?></div>
              <span class="rounded-full bg-blue-50 px-2 py-1 text-xs font-medium text-blue-700"><?= htmlspecialchars($productionStatusLabels[$jobStatus] ?? $jobStatus) ?></span>
            </div>
            <div class="grid gap-1 text-sm md:grid-cols-2">
              <div><span class="text-gray-500">Модель:</span> <?= htmlspecialchars($fulfillmentLabels[$job['fulfillment_model'] ?? ''] ?? (string)($job['fulfillment_model'] ?? '—')) ?></div>
              <div><span class="text-gray-500">Место:</span> <?= htmlspecialchars($productionLocationLabels[$job['production_location'] ?? ''] ?? (string)($job['production_location'] ?? '—')) ?></div>
              <div><span class="text-gray-500">Готовность:</span> <?= htmlspecialchars((string)($job['production_deadline'] ?? '—')) ?></div>
              <div><span class="text-gray-500">Передача:</span> <?= htmlspecialchars((string)($job['handover_deadline'] ?? '—')) ?></div>
              <div><span class="text-gray-500">Бонус:</span> <?= htmlspecialchars($bonusLabels[$job['bonus_type'] ?? ''] ?? (string)($job['bonus_type'] ?? '—')) ?> · <?= number_format((float)($job['bonus_amount_locked'] ?? 0), 0, '.', ' ') ?> ₽</div>
              <div><span class="text-gray-500">Материалы:</span> <?= number_format((float)($job['estimated_materials_cost'] ?? 0), 0, '.', ' ') ?> ₽</div>
              <div><span class="text-gray-500">Такси/логистика:</span> <?= number_format((float)(($job['materials_delivery_cost'] ?? 0) + ($job['result_delivery_cost'] ?? 0)), 0, '.', ' ') ?> ₽</div>
              <div><span class="text-gray-500">Прогноз маржи:</span> <?= $job['estimated_margin_amount'] !== null ? number_format((float)$job['estimated_margin_amount'], 0, '.', ' ') . ' ₽' : '—' ?> · <?= htmlspecialchars((string)($job['margin_status'] ?? 'unknown')) ?></div>
              <div><span class="text-gray-500">Исполнитель:</span> <?= $executorId > 0 ? ('#' . $executorId . ' · ' . htmlspecialchars((string)($job['executor_type'] ?? ''))) : 'не назначен' ?></div>
            </div>
            <?php if (!empty($job['manager_comment'])): ?>
              <div class="rounded bg-slate-50 p-2 text-sm text-slate-700"><?= nl2br(htmlspecialchars((string)$job['manager_comment'])) ?></div>
            <?php endif; ?>

            <?php $photos = $job['photos'] ?? []; ?>
            <div class="border-t pt-2 space-y-2">
              <div class="text-sm font-medium">Фото-контроль</div>
              <?php if (empty($photos)): ?>
                <div class="text-xs text-amber-700">Фото результата ещё не загружено.</div>
              <?php else: ?>
                <div class="grid gap-2 md:grid-cols-3">
                  <?php foreach ($photos as $photo): ?>
                    <div class="rounded border border-slate-200 p-2 text-xs space-y-2">
                      <a href="<?= htmlspecialchars((string)$photo['image_path']) ?>" target="_blank" rel="noopener">
                        <img src="<?= htmlspecialchars((string)$photo['image_path']) ?>" alt="Фото производства" class="h-28 w-full rounded object-cover">
                      </a>
                      <div>Тип: <?= htmlspecialchars((string)($photo['photo_type'] ?? 'ready')) ?></div>
                      <div>Проверка: <?= htmlspecialchars((string)($photo['review_status'] ?? 'pending')) ?></div>
                      <?php if (($photo['review_status'] ?? 'pending') === 'pending'): ?>
                        <div class="flex flex-wrap gap-1">
                          <form action="<?= $base ?>/orders/production/photo-review" method="post">
                            <?= csrf_field() ?>
                            <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                            <input type="hidden" name="photo_id" value="<?= (int)$photo['id'] ?>">
                            <input type="hidden" name="review_status" value="approved">
                            <button class="rounded bg-green-700 px-2 py-1 text-white" type="submit">Принять</button>
                          </form>
                          <form action="<?= $base ?>/orders/production/photo-review" method="post">
                            <?= csrf_field() ?>
                            <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                            <input type="hidden" name="photo_id" value="<?= (int)$photo['id'] ?>">
                            <input type="hidden" name="review_status" value="rejected">
                            <button class="rounded border border-red-600 px-2 py-1 text-red-700" type="submit">Отклонить</button>
                          </form>
                        </div>
                      <?php endif; ?>
                    </div>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
              <form action="<?= $base ?>/orders/production/photo" method="post" enctype="multipart/form-data" class="flex flex-wrap items-end gap-2">
                <?= csrf_field() ?>
                <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                <input type="hidden" name="job_id" value="<?= (int)$job['id'] ?>">
                <label class="text-xs">Тип фото
                  <select name="photo_type" class="block rounded border px-2 py-1 text-sm">
                    <option value="ready">Готовый набор</option>
                    <option value="packaging">Упаковка</option>
                    <option value="handover">Передача</option>
                  </select>
                </label>
                <label class="text-xs">Фото
                  <input type="file" name="photo" accept="image/*" class="block text-sm">
                </label>
                <button class="rounded bg-slate-800 px-3 py-1 text-sm text-white" type="submit">Загрузить фото</button>
              </form>
            </div>

            <?php if ($executorId === 0 && !empty($productionExecutors)): ?>
              <form action="<?= $base ?>/orders/production/assign" method="post" class="flex flex-wrap items-end gap-2 border-t pt-2">
                <?= csrf_field() ?>
                <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                <input type="hidden" name="job_id" value="<?= (int)$job['id'] ?>">
                <input type="hidden" name="executor_type" value="internal_staff">
                <label class="text-xs">Назначить внутреннего исполнителя
                  <select name="executor_id" class="block border px-2 py-1 rounded text-sm">
                    <?php foreach ($productionExecutors as $executor): ?>
                      <option value="<?= (int)$executor['id'] ?>"><?= htmlspecialchars($executor['name'] ?: ('#' . $executor['id'])) ?> · <?= htmlspecialchars($executor['role'] ?? '') ?> · <?= htmlspecialchars($executor['current_mode'] ?? 'on_shift') ?></option>
                    <?php endforeach; ?>
                  </select>
                </label>
                <button class="rounded bg-[#C86052] px-3 py-1 text-sm text-white" type="submit">Назначить</button>
              </form>
            <?php endif; ?>

            <?php if (!empty($events)): ?>
              <details class="text-xs text-gray-600">
                <summary class="cursor-pointer">История производства</summary>
                <div class="mt-1 space-y-1">
                  <?php foreach ($events as $event): ?>
                    <div class="rounded bg-slate-50 p-2">
                      <span class="font-medium"><?= htmlspecialchars((string)($event['created_at'] ?? '')) ?></span>
                      · <?= htmlspecialchars((string)($event['from_status'] ?? '—')) ?> → <?= htmlspecialchars((string)($event['to_status'] ?? '')) ?>
                      <?php if (!empty($event['comment'])): ?> · <?= htmlspecialchars((string)$event['comment']) ?><?php endif; ?>
                    </div>
                  <?php endforeach; ?>
                </div>
              </details>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <details class="rounded border border-slate-200 p-3">
      <summary class="cursor-pointer font-medium">Создать производственное задание</summary>
      <form action="<?= $base ?>/orders/production/create" method="post" class="mt-3 grid gap-2 md:grid-cols-2">
        <?= csrf_field() ?>
        <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
        <label class="text-sm">Модель исполнения
          <select name="fulfillment_model" class="mt-1 w-full border px-2 py-1 rounded">
            <option value="by_berrygo_on_site">berryGo · на смене</option>
            <option value="by_berrygo_remote">berryGo · удалённо</option>
          </select>
        </label>
        <label class="text-sm">Место
          <select name="production_location" class="mt-1 w-full border px-2 py-1 rounded">
            <option value="shop">На точке berryGo</option>
            <option value="remote">Удалённо</option>
          </select>
        </label>
        <label class="text-sm">Дедлайн готовности
          <input type="datetime-local" name="production_deadline" class="mt-1 w-full border px-2 py-1 rounded">
        </label>
        <label class="text-sm">Дедлайн передачи
          <input type="datetime-local" name="handover_deadline" class="mt-1 w-full border px-2 py-1 rounded">
        </label>
        <label class="text-sm">Тип бонуса
          <select name="bonus_type" class="mt-1 w-full border px-2 py-1 rounded">
            <option value="internal_bonus">Внутренний бонус</option>
            <option value="salary">В зарплате</option>
            <option value="fixed_payout">Фикс. выплата</option>
          </select>
        </label>
        <label class="text-sm">Бонус зафиксирован, ₽
          <input type="number" name="bonus_amount_locked" min="0" step="1" value="0" class="mt-1 w-full border px-2 py-1 rounded">
        </label>
        <label class="text-sm">Материалы, ₽
          <input type="number" name="estimated_materials_cost" min="0" step="1" value="0" class="mt-1 w-full border px-2 py-1 rounded">
        </label>
        <label class="text-sm">Такси материалов, ₽
          <input type="number" name="materials_delivery_cost" min="0" step="1" value="0" class="mt-1 w-full border px-2 py-1 rounded">
        </label>
        <label class="text-sm">Такси результата, ₽
          <input type="number" name="result_delivery_cost" min="0" step="1" value="0" class="mt-1 w-full border px-2 py-1 rounded">
        </label>
        <label class="text-sm">Минимальная маржа, ₽
          <input type="number" name="minimum_margin_amount" min="0" step="1" value="0" class="mt-1 w-full border px-2 py-1 rounded">
        </label>
        <label class="text-sm md:col-span-2">Комментарий менеджера
          <textarea name="manager_comment" rows="2" class="mt-1 w-full border px-2 py-1 rounded" placeholder="Например: клубника в шоколаде, сделать на смене"></textarea>
        </label>
        <div class="md:col-span-2">
          <button class="rounded bg-green-700 px-3 py-1 text-white" type="submit">Создать задание</button>
        </div>
      </form>
    </details>
  </div>

  <form action="<?= $base ?>/orders/comment" method="post" class="bg-white p-2 md:p-4 rounded shadow card space-y-1 md:space-y-2" data-autosave="true">
    <?= csrf_field() ?>
    <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
    <label class="block">
      <span class="block mb-1">Комментарий:</span>
      <textarea name="comment" rows="3" class="w-full border px-2 py-1 rounded"><?= htmlspecialchars($order['comment'] ?? '') ?></textarea>
    </label>
  </form>

  <form action="<?= $base ?>/orders/referral" method="post" class="bg-white p-2 md:p-4 rounded shadow card space-y-1 md:space-y-2" data-autosave="true">
    <?= csrf_field() ?>
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
    <?= csrf_field() ?>
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
      <label>
        <span class="mr-1">Км вручную:</span>
        <input type="number" name="delivery_distance_km_manual" min="0" step="0.001" value="<?= htmlspecialchars((string)($order['delivery_distance_km'] ?? '')) ?>" placeholder="Например: 8.22" class="border px-2 py-1 rounded w-32">
      </label>
      <label class="flex-1 min-w-[220px]">
        <span class="mr-1">Комментарий доставки:</span>
        <textarea name="delivery_comment" rows="1" placeholder="Получатель, телефон, подъезд, пожелания" class="border px-2 py-1 rounded w-full align-middle"><?= htmlspecialchars((string)($order['delivery_comment'] ?? '')) ?></textarea>
      </label>
      <?php if (in_array((string)($order['status'] ?? ''), ['completed', 'cancelled', 'returned'], true)): ?>
        <div class="basis-full text-xs text-amber-700">Заказ уже завершён/отменён: адрес, дату и комментарий можно сохранить, стоимость доставки не пересчитывается.</div>
      <?php else: ?>
        <div class="basis-full text-xs text-gray-500">При смене адреса или километража стоимость доставки пересчитается, итог заказа изменится на разницу доставки.</div>
      <?php endif; ?>
    </div>
  </form>

  <?php $btnClasses = [
      'confirmed' => 'bg-yellow-700 hover:bg-yellow-800',
      'shipped'    => 'bg-green-700 hover:bg-green-800',
      'completed'  => 'bg-gray-700 hover:bg-gray-800',
      'cancelled'  => 'bg-gray-600 hover:bg-gray-700',
      'returned'   => 'bg-orange-600 hover:bg-orange-700',
  ]; ?>
  <dialog class="status-dialog" data-status-dialog>
    <div class="status-dialog-content bg-white">
      <div class="flex items-center mb-3 status-dialog-header">
        <h3 class="font-semibold">Изменить статус заказа</h3>
        <button type="button" class="px-2 py-1 rounded border status-dialog-close" data-close-status-modal>✕</button>
      </div>
      <div class="status-modal-buttons">
        <?php
          $statusOptions = [
            'confirmed' => 'Подтверждён',
            'shipped'    => 'В пути',
            'completed'  => 'Выполнен',
            'cancelled'  => 'Отменён',
            'returned'   => 'Возврат',
          ];
          if ((string)($order['status'] ?? '') === 'completed') {
            $statusOptions = ['returned' => 'Возврат'];
          }
        ?>
        <?php foreach ($statusOptions as $st => $label): ?>
          <form action="<?= $base ?>/orders/status" method="post">
            <?= csrf_field() ?>
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
      <?= csrf_field() ?>
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
      const pricePerBox = Number(selected.dataset.pricePerBox || 0);

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
