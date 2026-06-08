<?php /** @var string|null $userName */ ?>
<?php /** @var string|null $tgStart */ ?>
<?php /** @var array<int,array<string,mixed>> $notifications */ ?>
<?php /** @var array<int,array<string,mixed>> $preorderOffers */ ?>
<main class="bg-gradient-to-br from-orange-50 via-white to-pink-50 min-h-screen pb-24">
  <div class="px-4 pt-6 space-y-6">
    <a href="https://t.me/YagodgoBot<?= $tgStart ? '?start=' . $tgStart : '' ?>" class="flex items-center justify-center space-x-2 bg-blue-500 text-white rounded-2xl py-3 shadow-lg hover:bg-blue-600 transition">
      <span class="material-icons-round">telegram</span>
      <span class="font-semibold">Подключить уведомления</span>
    </a>
    <p class="text-center text-gray-600 text-sm">
      После перехода нажмите <strong>Start</strong> в Telegram.
    </p>

    <button type="button"
            data-open-notify-settings
            class="w-full bg-white border border-gray-200 rounded-2xl py-3 text-gray-800 font-semibold shadow-sm hover:bg-gray-50 transition">
      Настройка уведомлений
    </button>

    <section class="bg-white rounded-3xl shadow divide-y divide-gray-100 overflow-hidden">
      <div class="px-4 py-3 bg-gray-50">
        <h2 class="text-sm font-semibold text-gray-700">Уведомления от приложения</h2>
      </div>
      <?php if (!empty($notifications)): ?>
        <?php foreach ($notifications as $n): ?>
          <div class="p-4">
            <p class="text-sm font-medium text-gray-800"><?= htmlspecialchars((string)($n['description'] ?? 'Уведомление')) ?></p>
            <?php if (!empty($n['code'])): ?>
              <p class="text-xs text-gray-400 mt-1">Код: <?= htmlspecialchars((string)$n['code']) ?></p>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <div class="p-4 text-sm text-gray-500">Пока нет новых уведомлений.</div>
      <?php endif; ?>
    </section>

    <section class="bg-white rounded-3xl shadow divide-y divide-gray-100 overflow-hidden">
      <div class="px-4 py-3 bg-gray-50">
        <h2 class="text-sm font-semibold text-gray-700">Предварительные заказы</h2>
      </div>
      <?php if (!empty($preorderOffers)): ?>
        <?php foreach ($preorderOffers as $offer): ?>
          <?php
            $status = (string)($offer['status'] ?? '');
            $expiresTs = !empty($offer['offer_expires_at']) ? strtotime((string)$offer['offer_expires_at']) : false;
            $isExpiredOffer = $expiresTs !== false && $expiresTs < time();
            $isActiveOffer = in_array($status, ['awaiting_price_confirmation', 'offer_sent'], true) && !$isExpiredOffer;
            $expires = $expiresTs !== false ? date('d.m H:i', $expiresTs) : null;
            $expectedPrice = (float)($offer['expected_price_per_box'] ?? 0);
            $finalPrice = (float)($offer['offered_price_per_box'] ?? 0);
            $priceDelta = ($expectedPrice > 0 && $finalPrice > 0) ? $finalPrice - $expectedPrice : null;
            $desired = !empty($offer['desired_delivery_date']) ? date('d.m.Y', strtotime((string)$offer['desired_delivery_date'])) : 'Не имеет значения';
            $dateChange = is_array($offer['date_change'] ?? null) ? $offer['date_change'] : null;
            $dateChangeMeta = is_array($dateChange['meta'] ?? null) ? $dateChange['meta'] : [];
            $dateChangeReason = (string)($dateChangeMeta['reason'] ?? 'rescheduled');
            $oldDate = !empty($dateChangeMeta['old_desired_delivery_date']) ? date('d.m.Y', strtotime((string)$dateChangeMeta['old_desired_delivery_date'])) : $desired;
            $proposedDate = !empty($dateChangeMeta['proposed_delivery_date']) ? date('d.m.Y', strtotime((string)$dateChangeMeta['proposed_delivery_date'])) : null;
            $nextSupplyDate = !empty($dateChangeMeta['next_supply_date']) ? date('d.m.Y', strtotime((string)$dateChangeMeta['next_supply_date'])) : null;
            $showProposedButton = $proposedDate && ($dateChangeReason !== 'cancelled' || $proposedDate !== $nextSupplyDate);
          ?>
          <div class="p-4 space-y-2">
            <p class="text-sm font-semibold text-gray-800">
              Пришла поставка: <?= htmlspecialchars((string)$offer['product_name']) ?> <?= htmlspecialchars((string)$offer['variety']) ?>
            </p>
            <div class="rounded-2xl border <?= $isActiveOffer ? 'border-emerald-100 bg-emerald-50' : 'border-gray-100 bg-gray-50' ?> p-3 space-y-1">
              <p class="text-xs font-semibold <?= $isActiveOffer ? 'text-emerald-900' : 'text-gray-600' ?>">
                <?= $isActiveOffer ? 'Финальная цена готова к подтверждению' : 'Статус предзаказа: ' . htmlspecialchars($status) ?>
              </p>
              <p class="text-xs text-gray-700">
                Бронь: <?= (float)($offer['requested_boxes'] ?? 0) ?> ящ., дата получения: <?= htmlspecialchars($desired) ?>
              </p>
              <?php if ($expectedPrice > 0 || $finalPrice > 0): ?>
                <p class="text-xs text-gray-700">
                  <?php if ($expectedPrice > 0): ?>Ожидали: <b><?= number_format($expectedPrice, 0, '.', ' ') ?> ₽</b>.<?php endif; ?>
                  <?php if ($finalPrice > 0): ?> Финальная цена: <b><?= number_format($finalPrice, 0, '.', ' ') ?> ₽</b>.<?php endif; ?>
                  <?php if ($priceDelta !== null && abs($priceDelta) >= 0.01): ?>
                    Изменение: <b class="<?= $priceDelta > 0 ? 'text-rose-700' : 'text-emerald-700' ?>"><?= ($priceDelta > 0 ? '+' : '') . number_format($priceDelta, 0, '.', ' ') ?> ₽</b>.
                  <?php endif; ?>
                </p>
              <?php endif; ?>
              <?php if ($expires && $isActiveOffer): ?>
                <p class="text-xs text-amber-700">Подтвердите финальную цену до <?= htmlspecialchars($expires) ?>, иначе бронь истечёт.</p>
              <?php elseif ($isExpiredOffer || $status === 'expired'): ?>
                <p class="text-xs text-gray-500">Время подтверждения финальной цены истекло.</p>
              <?php endif; ?>
            </div>
            <?php if ($dateChange): ?>
              <div class="rounded-2xl border border-amber-200 bg-amber-50 p-3 space-y-2">
                <p class="text-sm font-semibold text-amber-900">
                  <?= $dateChangeReason === 'cancelled' ? 'Поставка по предзаказу отменена' : 'Поставка по предзаказу перенесена' ?>
                </p>
                <p class="text-xs text-amber-800">
                  Текущая дата получения: <b><?= htmlspecialchars($oldDate) ?></b>.
                  <?php if ($proposedDate): ?> Новая дата: <b><?= htmlspecialchars($proposedDate) ?></b>.<?php endif; ?>
                  <?php if ($nextSupplyDate): ?> Следующая поставка: <b><?= htmlspecialchars($nextSupplyDate) ?></b>.<?php endif; ?>
                </p>
                <div class="flex flex-wrap gap-2">
                  <?php if ($showProposedButton): ?>
                    <button type="button"
                            class="px-3 py-2 rounded-lg text-xs text-white bg-emerald-600"
                            data-date-change-decision="accept_new"
                            data-intent-id="<?= (int)$offer['id'] ?>">
                      Подтвердить <?= htmlspecialchars($proposedDate) ?>
                    </button>
                  <?php endif; ?>
                  <?php if ($nextSupplyDate): ?>
                    <button type="button"
                            class="px-3 py-2 rounded-lg text-xs text-white bg-blue-600"
                            data-date-change-decision="next_supply"
                            data-intent-id="<?= (int)$offer['id'] ?>">
                      Следующая поставка <?= htmlspecialchars($nextSupplyDate) ?>
                    </button>
                  <?php else: ?>
                    <button type="button"
                            class="px-3 py-2 rounded-lg text-xs text-white bg-blue-600"
                            data-date-change-decision="wait_next"
                            data-intent-id="<?= (int)$offer['id'] ?>">
                      Ждать следующую поставку
                    </button>
                  <?php endif; ?>
                  <button type="button"
                          class="px-3 py-2 rounded-lg text-xs text-white bg-rose-600"
                          data-date-change-decision="cancel"
                          data-intent-id="<?= (int)$offer['id'] ?>">
                    Отменить
                  </button>
                </div>
              </div>
            <?php endif; ?>
            <div class="flex gap-2">
              <button type="button"
                      class="px-3 py-2 rounded-lg text-sm text-white <?= $isActiveOffer ? 'bg-emerald-600' : 'bg-gray-300 cursor-not-allowed' ?>"
                      data-preorder-action="confirm"
                      data-intent-id="<?= (int)$offer['id'] ?>"
                      <?= $isActiveOffer ? '' : 'disabled' ?>>
                Подтвердить цену
              </button>
              <button type="button"
                      class="px-3 py-2 rounded-lg text-sm text-white <?= $isActiveOffer ? 'bg-rose-600' : 'bg-gray-300 cursor-not-allowed' ?>"
                      data-preorder-action="decline"
                      data-intent-id="<?= (int)$offer['id'] ?>"
                      <?= $isActiveOffer ? '' : 'disabled' ?>>
                Отказаться от цены
              </button>
            </div>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <div class="p-4 text-sm text-gray-500">Нет активных предложений по предзаказам.</div>
      <?php endif; ?>
    </section>
  </div>

  <div data-notify-modal class="fixed inset-0 bg-black/40 z-50 hidden items-end sm:items-center justify-center">
    <div class="bg-white w-full sm:max-w-xl sm:rounded-2xl rounded-t-2xl p-4 sm:p-6 max-h-[85vh] overflow-y-auto">
      <div class="flex items-center justify-between mb-4">
        <h3 class="text-base sm:text-lg font-semibold text-gray-800">Настройка уведомлений</h3>
        <button type="button" data-close-notify-settings class="text-gray-500 hover:text-gray-700">
          <span class="material-icons-round">close</span>
        </button>
      </div>

      <div class="bg-white rounded-2xl border divide-y divide-gray-100">
        <?php
          $items = [
            'Уведомления об изменении статуса моих заказов',
            'Сообщения об акциях и спецпредложениях',
            'Информационные сообщения',
            'Поступление клубники',
            'Поступление черешни',
            'Поступление ежевики'
          ];
        ?>
        <?php foreach ($items as $label): ?>
          <div class="flex items-center justify-between p-4">
            <span class="text-gray-800 text-sm flex-1 pr-3"><?= $label ?></span>
            <label class="relative inline-flex items-center cursor-pointer">
              <input type="checkbox" class="sr-only peer">
              <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-red-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-red-600"></div>
            </label>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</main>

<script>
  (() => {
    const openBtn = document.querySelector('[data-open-notify-settings]');
    const closeBtn = document.querySelector('[data-close-notify-settings]');
    const modal = document.querySelector('[data-notify-modal]');
    if (!openBtn || !closeBtn || !modal) return;

    const open = () => {
      modal.classList.remove('hidden');
      modal.classList.add('flex');
    };
    const close = () => {
      modal.classList.add('hidden');
      modal.classList.remove('flex');
    };

    openBtn.addEventListener('click', open);
    closeBtn.addEventListener('click', close);
    modal.addEventListener('click', (e) => {
      if (e.target === modal) close();
    });

    document.querySelectorAll('[data-date-change-decision]').forEach((btn) => {
      btn.addEventListener('click', async () => {
        const decision = btn.getAttribute('data-date-change-decision');
        const intentId = btn.getAttribute('data-intent-id');
        if (!decision || !intentId) return;
        const payload = new URLSearchParams();
        payload.set('decision', decision);
        await fetch(`/preorder-intents/${intentId}/date-change`, {
          method: 'POST',
          headers: {'Content-Type': 'application/x-www-form-urlencoded'},
          body: payload.toString()
        });
        window.location.reload();
      });
    });

    document.querySelectorAll('[data-preorder-action]').forEach((btn) => {
      btn.addEventListener('click', async () => {
        const action = btn.getAttribute('data-preorder-action');
        const intentId = btn.getAttribute('data-intent-id');
        if (!action || !intentId) return;
        const res = await fetch(`/preorder-intents/${intentId}/${action}`, { method: 'POST' });
        const data = await res.json();
        if (action === 'confirm' && data?.continue_url) {
          window.location.href = data.continue_url;
          return;
        }
        window.location.reload();
      });
    });
  })();
</script>
