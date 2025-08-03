<?php /** @var array $user @var array $transactions */ ?>
<?php $role = $_SESSION['role'] ?? ''; $isManager = in_array($role, ['manager','partner'], true); $base = $role === 'manager' ? '/manager' : ($role === 'partner' ? '/partner' : '/admin'); ?>
<h1 class="text-xl font-semibold mb-4">Пользователь #<?= $user['id'] ?></h1>
<div class="bg-white p-4 rounded shadow mb-4">
  <div class="font-semibold text-lg mb-1"><?= htmlspecialchars($user['name']) ?></div>
  <div class="text-sm text-gray-500 mb-2"><?= htmlspecialchars($user['phone']) ?></div>
  <div>
    Баланс: <?= (int)$user['points_balance'] ?> 🍓
    <?php if (($user['rub_balance'] ?? 0) > 0): ?>
      <br><?= (int)$user['rub_balance'] ?> ₽
    <?php endif; ?>
  </div>
</div>
<div class="bg-white p-4 rounded shadow">
  <h2 class="font-semibold mb-2">Транзакции</h2>
  <?php if (empty($transactions)): ?>
    <p class="text-gray-500">Транзакций нет</p>
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
              <a href="<?= $base ?>/orders/<?= $tx['order_id'] ?>" class="text-blue-600 hover:underline">
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
