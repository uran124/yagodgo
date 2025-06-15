<?php
/**
 * @var array  $user          // ['id'=>..., 'name'=>..., 'phone'=>..., 'referral_code'=>..., 'points_balance'=>..., 'referred_by'=>...]
 * @var string $address
 * @var array  $transactions  // каждая транзакция ['id'=>..., 'amount'=>..., 'transaction_type'=>..., 'description'=>..., 'created_at'=>..., 'order_id'=>...]
 */
?>

<main class="bg-gradient-to-br from-orange-50 via-white to-pink-50 min-h-screen pb-24">

  <div class="px-4 space-y-6">

    <!-- Основная информация -->
    <div class="bg-white rounded-3xl shadow-lg overflow-hidden">
      <div class="bg-gradient-to-r from-gray-50 to-emerald-50 px-6 py-4 border-b border-gray-100">
        <h2 class="font-bold text-gray-800 flex items-center">
          <span class="material-icons-round mr-2 text-emerald-500">badge</span>
          Основная информация
        </h2>
      </div>
      <div class="p-6 space-y-4">
        <div class="flex items-center space-x-4">
          <div class="w-12 h-12 bg-gradient-to-br from-blue-400 to-indigo-500 rounded-2xl flex items-center justify-center flex-shrink-0">
            <span class="material-icons-round text-white">person</span>
          </div>
          <div class="flex-1 text-lg font-semibold text-gray-800">
            <?= htmlspecialchars($user['name'] ?? 'Не указано') ?>
          </div>
        </div>
        <div class="flex items-center space-x-4">
          <div class="w-12 h-12 bg-gradient-to-br from-green-400 to-emerald-500 rounded-2xl flex items-center justify-center flex-shrink-0">
            <span class="material-icons-round text-white">phone</span>
          </div>
          <div class="flex-1 text-lg font-semibold text-gray-800">
            <?= htmlspecialchars($user['phone'] ?? 'Не указан') ?>
          </div>
        </div>
        <form action="/profile" method="post" class="space-y-4">
          <div class="flex items-start space-x-4">
            <div class="w-12 h-12 bg-gradient-to-br from-orange-400 to-red-500 rounded-2xl flex items-center justify-center flex-shrink-0 mt-1">
              <span class="material-icons-round text-white">home</span>
            </div>
            <textarea
              name="address"
              rows="3"
              class="flex-1 border border-gray-300 rounded-2xl px-4 py-3 text-gray-800 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 transition-all resize-none"
            ><?= htmlspecialchars($address) ?></textarea>
          </div>
          <div class="flex justify-end">
            <button type="submit" class="bg-emerald-500 text-white px-4 py-2 rounded-lg hover:bg-emerald-600 transition">Обновить</button>
          </div>
        </form>
        <div class="flex items-center space-x-4">
          <div class="w-12 h-12 bg-gradient-to-br from-pink-400 to-red-500 rounded-2xl flex items-center justify-center flex-shrink-0">
            <span class="text-xl">🍓</span>
          </div>
          <div class="flex-1 text-lg font-semibold text-gray-800">
            <?= (int)$user['points_balance'] ?>
          </div>
        </div>
      </div>
    </div>

    <!-- Активные заказы -->
    <div class="bg-white rounded-3xl shadow-lg overflow-hidden">
      <div class="bg-gradient-to-r from-gray-50 to-blue-50 px-6 py-4 border-b border-gray-100">
        <h2 class="font-bold text-gray-800 flex items-center">
          <span class="material-icons-round mr-2 text-blue-500">local_shipping</span>
          Активные заказы
        </h2>
      </div>
      <div class="p-6 space-y-4">
        <?php if (empty($activeOrders)): ?>
          <p class="text-gray-500">Нет активных заказов</p>
        <?php else: ?>
          <?php foreach ($activeOrders as $ao): ?>
            <?php
            $status = $ao['status'];
            $cfg = [
              'new' => ['bg-red-100','text-red-800','Новый заказ'],
              'processing' => ['bg-yellow-100','text-yellow-800','Принят'],
              'assigned' => ['bg-green-100','text-green-800','Обработан'],
            ][$status] ?? ['bg-gray-100','text-gray-800',$status];
            ?>
            <div class="p-4 rounded-2xl border flex items-center justify-between">
              <div>
                <div class="font-semibold text-gray-800 mb-1">Заказ #<?= $ao['id'] ?></div>
                <div class="text-sm text-gray-500"><?= date('d.m.Y H:i', strtotime($ao['created_at'])) ?></div>
              </div>
              <div class="text-right space-y-1">
                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium <?= $cfg[0] ?> <?= $cfg[1] ?>">
                  <?= $cfg[2] ?>
                </span>
                <div class="font-semibold text-gray-800">
                  <?= number_format($ao['total_amount'], 0, '.', ' ') ?> ₽
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>

    <!-- Бонусы -->
    <div class="bg-white rounded-3xl shadow-2xl overflow-hidden">
      <div class="bg-gradient-to-r from-red-500 to-pink-500 px-6 py-4 border-b border-red-400">
        <h2 class="font-bold text-white flex items-center">
          <span class="material-icons-round mr-2 text-white">card_giftcard</span>
          Бонусы
        </h2>
      </div>
      <div class="p-6 space-y-4 text-gray-700 bg-gradient-to-br from-red-50 via-white to-pink-50">
        <p>Подарите другу 10 % скидку на первый заказ и получайте клубнички за каждый его заказ!</p>
        <p>Скопируйте ссылку и отправьте другу:</p>
        <div class="flex items-center space-x-2">
          <?php $refLink = "https://berrygo.ru/register?invite=" . urlencode($user['referral_code']); ?>
          <input type="text" readonly value="<?= htmlspecialchars($refLink) ?>" class="flex-1 bg-white/80 rounded-lg px-3 py-2 text-sm text-gray-800 outline-none cursor-pointer" onclick="copyInviteLink()" title="Нажмите, чтобы скопировать">
          <button onclick="copyInviteLink()" class="bg-red-500 text-white px-4 py-2 rounded-lg hover:bg-pink-500 transition">Копировать</button>
        </div>
        <p class="text-sm text-gray-500">Или используйте купон:</p>
        <div class="flex items-center space-x-2">
          <code class="bg-white/80 rounded-lg px-3 py-2 font-mono cursor-pointer" onclick="copyInviteCode()" title="Нажмите, чтобы скопировать">
            <?= htmlspecialchars($user['referral_code']) ?>
          </code>
          <button onclick="copyInviteCode()" class="bg-red-500 text-white px-4 py-2 rounded-lg hover:bg-pink-500 transition">Копировать</button>
        </div>
        <div class="grid grid-cols-3 gap-4 pt-4">
          <div class="text-center p-4 bg-gradient-to-br from-emerald-50 to-teal-50 rounded-2xl">
            <div class="text-2xl font-bold text-emerald-600 mb-1"><?= $refStats['users'] ?></div>
            <div class="text-xs text-gray-600">приглашено</div>
          </div>
          <div class="text-center p-4 bg-gradient-to-br from-yellow-50 to-orange-50 rounded-2xl">
            <div class="text-2xl font-bold text-orange-600 mb-1"><?= $refStats['orders'] ?></div>
            <div class="text-xs text-gray-600">заказов</div>
          </div>
          <div class="text-center p-4 bg-gradient-to-br from-pink-50 to-red-50 rounded-2xl">
            <div class="text-2xl font-bold text-pink-600 mb-1"><?= $refStats['points'] ?></div>
            <div class="text-xs text-gray-600">клубничек</div>
          </div>
        </div>
      </div>
    </div>

    <!-- История баллов -->
    <div class="bg-white rounded-3xl shadow-lg overflow-hidden">
      <div class="bg-gradient-to-r from-gray-50 to-blue-50 px-6 py-4 border-b border-gray-100">
        <h2 class="font-bold text-gray-800 flex items-center">
          <span class="material-icons-round mr-2 text-blue-500">receipt_long</span>
          История клубничек
        </h2>
      </div>
      <div class="p-6 overflow-x-auto">
        <?php if (empty($transactions)): ?>
          <p class="text-gray-500">История пуста</p>
        <?php else: ?>
          <table class="w-full text-left">
            <thead>
              <tr>
                <th class="px-4 py-2 text-sm text-gray-500">Дата</th>
                <th class="px-4 py-2 text-sm text-gray-500">Сумма</th>
                <th class="px-4 py-2 text-sm text-gray-500">Тип</th>
                <th class="px-4 py-2 text-sm text-gray-500">Описание</th>
                <th class="px-4 py-2 text-sm text-gray-500">Заказ</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($transactions as $tx): ?>
                <tr class="border-t">
                  <td class="px-4 py-2 text-sm text-gray-700"><?= date('d.m.Y H:i', strtotime($tx['created_at'])) ?></td>
                  <td class="px-4 py-2 text-sm">
                    <?php if ((int)$tx['amount'] > 0): ?>
                      <span class="text-green-600 font-semibold">+<?= $tx['amount'] ?></span>
                    <?php else: ?>
                      <span class="text-red-600 font-semibold"><?= $tx['amount'] ?></span>
                    <?php endif; ?>
                    <span class="text-sm">🍓</span>
                  </td>
                  <td class="px-4 py-2 text-sm text-gray-700">
                    <?= $tx['transaction_type'] === 'accrual' ? 'Приз' : 'Трата' ?>
                  </td>
                  <td class="px-4 py-2 text-sm text-gray-700"><?= htmlspecialchars($tx['description']) ?></td>
                  <td class="px-4 py-2 text-sm text-gray-700">
                    <?php if (!empty($tx['order_id'])): ?>
                      <a href="/orders/<?= $tx['order_id'] ?>" class="text-blue-600 hover:underline">
                        #<?= $tx['order_id'] ?>
                      </a>
                    <?php else: ?>
                      —
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>
    </div>

    <!-- Быстрые действия -->
    <div class="bg-white rounded-3xl shadow-lg overflow-hidden">
      <div class="bg-gradient-to-r from-gray-50 to-blue-50 px-6 py-4 border-b border-gray-100">
        <h2 class="font-bold text-gray-800 flex items-center">
          <span class="material-icons-round mr-2 text-blue-500">bolt</span>
          Быстрые действия
        </h2>
      </div>
      <div class="p-6 grid grid-cols-2 gap-4">
        <a href="/orders"
           class="flex flex-col items-center p-4 bg-gradient-to-br from-blue-50 to-indigo-50 rounded-2xl hover:shadow-lg transition-all hover:scale-105 group">
          <div class="w-12 h-12 bg-gradient-to-br from-blue-500 to-indigo-500 rounded-2xl flex items-center justify-center mb-3 group-hover:scale-110 transition-transform">
            <span class="material-icons-round text-white">receipt_long</span>
          </div>
          <span class="font-semibold text-gray-800 text-sm text-center">Мои заказы</span>
        </a>
        <a href="/catalog"
           class="flex flex-col items-center p-4 bg-gradient-to-br from-red-50 to-pink-50 rounded-2xl hover:shadow-lg transition-all hover:scale-105 group">
          <div class="w-12 h-12 bg-gradient-to-br from-red-500 to-pink-500 rounded-2xl flex items-center justify-center mb-3 group-hover:scale-110 transition-transform">
            <span class="material-icons-round text-white">store</span>
          </div>
          <span class="font-semibold text-gray-800 text-sm text-center">Каталог</span>
        </a>
      </div>
    </div>

    <!-- Дополнительная информация -->
    <div class="bg-gradient-to-r from-indigo-50 to-purple-50 rounded-3xl p-6 text-center">
      <div class="w-16 h-16 bg-gradient-to-br from-indigo-400 to-purple-500 rounded-3xl flex items-center justify-center mx-auto mb-4">
        <span class="material-icons-round text-2xl text-white">favorite</span>
      </div>
      <h3 class="font-bold text-gray-800 mb-2">Спасибо, что с нами!</h3>
      <p class="text-sm text-gray-600 mb-4">Мы ценим каждого клиента и стараемся делать лучший сервис для вас.</p>
      <a href="/catalog" 
         class="inline-flex items-center px-6 py-3 bg-gradient-to-r from-indigo-500 to-purple-500 text-white rounded-2xl font-medium hover:shadow-lg transition-all space-x-2">
        <span class="material-icons-round">shopping_cart</span>
        <span>Сделать заказ</span>
      </a>
    </div>

  </div>

</main>

<script>
  function copyInviteLink() {
    const link = "<?= addslashes($refLink) ?>";
    navigator.clipboard.writeText(link)
      .then(() => alert('Ссылка скопирована в буфер обмена!'));
  }
  function copyInviteCode() {
    const code = "<?= addslashes(htmlspecialchars($user['referral_code'])) ?>";
    navigator.clipboard.writeText(code)
      .then(() => alert('Купон скопирован в буфер обмена!'));
  }
</script>
