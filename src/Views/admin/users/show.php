<?php /** @var array $user @var array $transactions @var array $addresses @var array $referrers */ ?>
<?php
$role = $_SESSION['role'] ?? '';
$isManager = in_array($role, ['manager','partner'], true);
$base = $role === 'manager' ? '/manager' : ($role === 'partner' ? '/partner' : '/admin');
$roleNames = [
  'client'  => 'Клиент',
  'courier' => 'Курьер',
  'admin'   => 'Админ',
  'manager' => 'Менеджер',
  'partner' => 'Партнёр',
  'seller'  => 'Селлер',
];
?>
<form action="<?= $base ?>/users/save" method="post" class="bg-white p-4 rounded shadow mb-4 space-y-4">
  <input type="hidden" name="id" value="<?= $user['id'] ?>">
  <div class="flex justify-between">
    <div>
      <div class="font-semibold">ID: <?= $user['id'] ?></div>
      <div><?= htmlspecialchars($user['name']) ?></div>
      <div class="text-sm text-gray-500"><?= htmlspecialchars($user['phone']) ?></div>
    </div>
    <div>
      <label class="block text-sm mb-1">Роль</label>
      <?php if ($isManager): ?>
        <div><?= $roleNames[$user['role']] ?? htmlspecialchars($user['role']) ?></div>
      <?php else: ?>
        <select name="role" class="border rounded px-2 py-1">
          <option value="client" <?= $user['role']==='client'?'selected':'' ?>>Клиент</option>
          <option value="courier" <?= $user['role']==='courier'?'selected':'' ?>>Курьер</option>
          <option value="admin" <?= $user['role']==='admin'?'selected':'' ?>>Админ</option>
          <option value="manager" <?= $user['role']==='manager'?'selected':'' ?>>Менеджер</option>
          <option value="partner" <?= $user['role']==='partner'?'selected':'' ?>>Партнёр</option>
          <option value="seller" <?= $user['role']==='seller'?'selected':'' ?>>Селлер</option>
        </select>
      <?php endif; ?>
    </div>
  </div>
  <div class="flex justify-between items-center">
    <label class="flex items-center space-x-2">
      <input type="checkbox" name="is_blocked" value="1" <?= !empty($user['is_blocked']) ? 'checked' : '' ?>>
      <span>Заблокирован</span>
    </label>
    <div class="text-sm text-gray-500">Создан: <?= date('d.m.Y H:i', strtotime($user['created_at'])) ?></div>
  </div>
  <div>Telegram: <?= htmlspecialchars($user['telegram_id'] ?? '') ?></div>
  <div>Пригласительный код: <?= htmlspecialchars($user['referral_code']) ?></div>
  <?php if (!$isManager): ?>
    <div>
      <label class="block text-sm mb-1">Реферансье</label>
      <select name="referred_by" class="border rounded px-2 py-1">
        <option value="">—</option>
        <?php foreach ($referrers as $ref): ?>
          <option value="<?= $ref['id'] ?>" <?= $user['referred_by'] == $ref['id'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($ref['name']) ?> (<?= htmlspecialchars($ref['phone']) ?>)
          </option>
        <?php endforeach; ?>
      </select>
    </div>
  <?php endif; ?>
    <div class="flex justify-between">
      <div>Баланс: <?= (int)$user['points_balance'] ?> 🍓</div>
      <?php if ($isManager): ?>
        <div><?= (int)$user['rub_balance'] ?> ₽</div>
      <?php endif; ?>
    </div>
  <button type="submit" class="bg-[#C86052] text-white px-4 py-2 rounded">Сохранить</button>
</form>

<div class="bg-white p-4 rounded shadow mb-4">
  <h2 class="font-semibold mb-2">Адреса доставки</h2>
  <?php if (empty($addresses)): ?>
    <p class="text-gray-500 text-sm mb-2">Адресов нет</p>
  <?php else: ?>
    <ul class="space-y-2 mb-4">
      <?php foreach ($addresses as $addr): ?>
        <li class="flex justify-between items-start">
          <div>
            <div><?= htmlspecialchars($addr['street']) ?></div>
            <div class="text-sm text-gray-500"><?= htmlspecialchars($addr['recipient_name']) ?> <?= htmlspecialchars($addr['recipient_phone']) ?></div>
          </div>
          <form action="<?= $base ?>/users/delete-address" method="post" onsubmit="return confirm('Удалить адрес?');">
            <input type="hidden" name="id" value="<?= $addr['id'] ?>">
            <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
            <button type="submit" class="text-red-600">🗑️</button>
          </form>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
  <form action="<?= $base ?>/users/add-address" method="post" class="space-y-2">
    <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
    <input name="address" class="w-full border px-2 py-1 rounded" placeholder="Новый адрес">
    <input name="recipient_name" class="w-full border px-2 py-1 rounded" placeholder="Имя получателя">
    <input name="recipient_phone" class="w-full border px-2 py-1 rounded" placeholder="Телефон получателя">
    <button type="submit" class="bg-[#C86052] text-white px-4 py-1 rounded">Добавить адрес доставки</button>
  </form>
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
