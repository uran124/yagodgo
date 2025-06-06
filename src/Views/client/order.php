<?php
/**
 * @var array       $order      // [ 'id', 'user_id', 'address_id', 'status', 'total_amount', 'created_at', 'address' ]
 * @var array       $items      // каждый элемент: [ 'product_id', 'quantity', 'unit_price', 'variety', 'product' ]
 * @var string|null $userName
 * @var array       $debugData  // отладочные данные (если нужны)
 */
?>

<main class="bg-gradient-to-br from-orange-50 via-white to-pink-50 min-h-screen pb-24">

  <!-- Заголовок -->
  <div class="pt-6 px-4 mb-6">
    <div class="bg-gradient-to-r from-red-500 to-pink-500 rounded-3xl p-6 text-white shadow-2xl">
      <div class="flex justify-between items-center">
        <h1 class="text-3xl font-bold mb-0">Заказ №<?= htmlspecialchars($order['id']) ?></h1>
        <?php if ($userName): ?>
          <div class="inline-flex items-center bg-white/20 backdrop-blur-sm rounded-full px-4 py-2">
            <span class="material-icons-round mr-2 text-lg">person</span>
            <span class="font-medium"><?= htmlspecialchars($userName) ?></span>
          </div>
        <?php endif; ?>
      </div>
      <p class="mt-2 text-red-100 text-sm">
        Дата создания: <?= date('d.m.Y H:i', strtotime($order['created_at'])) ?>
      </p>
    </div>
  </div>

  <div class="px-4 space-y-6">

    <!-- Статус и адрес -->
    <div class="bg-white rounded-3xl shadow-lg p-6">
      <div class="flex justify-between items-center mb-4">
        <div>
          <h2 class="text-lg font-semibold text-gray-800">Статус заказа:</h2>
          <p class="text-gray-700">
            <?= htmlspecialchars(ucfirst($order['status'])) ?>
          </p>
        </div>
        <div>
          <h2 class="text-lg font-semibold text-gray-800">Адрес доставки:</h2>
          <p class="text-gray-700">
            <?= htmlspecialchars($order['address'] ?? 'Не указан') ?>
          </p>
        </div>
      </div>
      <div>
        <h2 class="text-lg font-semibold text-gray-800 mb-2">Товары в этом заказе:</h2>
        <ul class="divide-y divide-gray-200">
          <?php foreach ($items as $it): ?>
            <li class="py-3 flex justify-between">
              <div>
                <span class="font-medium text-gray-800">
                  <?= htmlspecialchars($it['product']) ?>
                  <?php if (!empty($it['variety'])): ?>
                    , <?= htmlspecialchars($it['variety']) ?>
                  <?php endif; ?>
                </span>
                <p class="text-sm text-gray-600">
                  Количество: <?= (float)$it['quantity'] ?> × <?= number_format($it['unit_price'], 0, '.', ' ') ?> ₽
                </p>
              </div>
              <div class="text-gray-800 font-semibold">
                <?= number_format($it['quantity'] * $it['unit_price'], 0, '.', ' ') ?> ₽
              </div>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
      <div class="mt-6 flex justify-end items-center space-x-2">
        <span class="text-lg font-bold text-gray-800">Итого:</span>
        <span class="text-2xl font-extrabold bg-gradient-to-r from-red-500 to-pink-500 bg-clip-text text-transparent">
          <?= number_format($order['total_amount'], 0, '.', ' ') ?> ₽
        </span>
      </div>
    </div>

    <!-- Кнопка «Назад к списку заказов» -->
    <div class="text-center">
      <a href="/orders"
         class="inline-flex items-center px-6 py-3 bg-gradient-to-r from-gray-100 to-gray-200 text-gray-700 rounded-2xl font-medium hover:from-gray-200 hover:to-gray-300 transition-all shadow-lg hover:shadow-xl space-x-2">
        <span class="material-icons-round">arrow_back</span>
        <span>К списку заказов</span>
      </a>
    </div>

    <!-- DEBUG-оверлей (необязательно; показывает debugData) -->
    <div id="debugOverlay" style="
        position: fixed;
        bottom: 10px;
        right: 10px;
        width: 320px;
        max-height: 50vh;
        overflow-y: auto;
        background: rgba(0,0,0,0.8);
        color: #fff;
        font-family: monospace;
        font-size: 12px;
        line-height: 1.2;
        padding: 8px;
        border-radius: 6px;
        z-index: 9999;
        display: none;
    ">
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
        <strong>DEBUG INFO</strong>
        <button id="debugToggle" style="
            background: transparent;
            border: none;
            color: #fff;
            font-weight: bold;
            cursor: pointer;
            font-size: 14px;
        ">×</button>
      </div>
      <pre id="debugContent" style="white-space: pre-wrap;"><?= 
        htmlspecialchars(json_encode($debugData, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)) 
      ?></pre>
    </div>
    <button id="debugOpenBtn" style="
        position: fixed;
        bottom: 10px;
        right: 10px;
        background: #e53e3e;
        color: #fff;
        border: none;
        border-radius: 4px;
        padding: 6px 10px;
        font-size: 12px;
        cursor: pointer;
        z-index: 9999;
    ">Открыть DEBUG</button>
  </div>
</main>

<script>
  const overlay = document.getElementById('debugOverlay');
  const content = document.getElementById('debugContent');
  const btnOpen = document.getElementById('debugOpenBtn');
  const btnClose = document.getElementById('debugToggle');

  // Открыть оверлей
  btnOpen.addEventListener('click', () => {
    overlay.style.display = 'block';
    btnOpen.style.display = 'none';
  });

  // Закрыть оверлей
  btnClose.addEventListener('click', () => {
    overlay.style.display = 'none';
    btnOpen.style.display = 'block';
  });
</script>
