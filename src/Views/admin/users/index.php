<?php /** @var array $users */ ?>
<?php $role = $_SESSION['role'] ?? ''; $isManager = in_array($role, ['manager','partner'], true); $base = $role === 'manager' ? '/manager' : ($role === 'partner' ? '/partner' : '/admin'); ?>
<?php if (!$isManager): ?>
  <?php if (!empty($payouts)): ?>
  <h2 class="text-lg font-semibold mb-2">Запросы на выплаты</h2>
  <table class="min-w-full bg-white rounded shadow overflow-hidden mb-6">
    <thead class="bg-gray-200 text-gray-700">
      <tr>
        <th class="p-3 text-left font-semibold">ID</th>
        <th class="p-3 text-left font-semibold">Имя</th>
        <th class="p-3 text-left font-semibold">Телефон</th>
        <th class="p-3 text-left font-semibold">Баланс, ₽</th>
        <th class="p-3 text-left font-semibold">Действие</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($payouts as $p): ?>
      <tr class="border-b hover:bg-gray-50 transition-all duration-200">
        <td class="p-3 font-medium text-gray-600"><?= $p['id'] ?></td>
        <td class="p-3"><?= htmlspecialchars($p['name']) ?></td>
        <td class="p-3 text-gray-600"><?= htmlspecialchars($p['phone']) ?></td>
        <td class="p-3 text-gray-600"><?= (int)$p['rub_balance'] ?></td>
        <td class="p-3">
          <form action="<?= $base ?>/users/reset-balance" method="post" class="inline">
            <input type="hidden" name="id" value="<?= $p['id'] ?>">
            <input type="hidden" name="redirect" value="<?= $base ?>/users">
            <button type="submit" class="px-2 py-1 bg-[#C86052] text-white rounded text-sm">Рассчетать</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
  <form method="get" class="mb-4 flex">
    <input type="text" name="q" value="<?= htmlspecialchars($search ?? '') ?>" placeholder="Телефон или адрес" class="border rounded px-3 py-2 mr-2 flex-grow">
    <button type="submit" class="bg-[#C86052] text-white px-4 py-2 rounded">Поиск</button>
  </form>
  <a href="<?= $base ?>/users/edit" class="bg-[#C86052] text-white px-4 py-2 rounded mb-4 inline-flex items-center">
    <span class="material-icons-round text-base mr-1">add</span> Добавить пользователя
  </a>
<?php endif; ?>
<table class="min-w-full bg-white rounded shadow overflow-hidden">
  <thead class="bg-gray-200 text-gray-700">
    <tr>
      <?php if (!$isManager): ?>
      <th class="p-3 text-left font-semibold">ID</th>
      <?php endif; ?>
      <th class="p-3 text-left font-semibold">Имя</th>
      <th class="p-3 text-left font-semibold">Телефон</th>
      <th class="p-3 text-left font-semibold">Адрес</th>
      <th class="p-3 text-left font-semibold">Баланс</th>
      <?php if (!$isManager): ?>
      <th class="p-3 text-center font-semibold">Заблокирован</th>
      <?php endif; ?>
    </tr>
  </thead>
  <tbody>
    <?php foreach($users as $u): ?>
    <tr class="border-b hover:bg-gray-50 transition-all duration-200">
      <?php if (!$isManager): ?>
      <td class="p-3 font-medium text-gray-600"><?= $u['id'] ?></td>
      <?php endif; ?>
      <td class="p-3">
        <div class="flex items-center">
            <a href="<?= $base ?>/users/<?= $u['id'] ?>" class="">
                <span class="font-medium"><?= htmlspecialchars($u['name']) ?></span>
            </a>
        </div>
      </td>
      <td class="p-3 text-gray-600"><?= htmlspecialchars($u['phone']) ?></td>
      <td class="p-3 text-gray-600"><?= htmlspecialchars($u['address'] ?? '') ?></td>
      <td class="p-3 text-gray-600">
        <span><?= (int)($u['points_balance'] ?? 0) ?> 🍓</span>
        <?php if (($u['rub_balance'] ?? 0) > 0): ?>
          <br><span><?= (int)$u['rub_balance'] ?> ₽</span>
        <?php endif; ?>
      </td>
      <?php if (!$isManager): ?>
      <td class="p-3 text-center">
        <form action="<?= $base ?>/users/toggle-block" method="post" class="inline-block">
          <input type="hidden" name="id" value="<?= $u['id'] ?>">
          <label class="relative inline-flex items-center cursor-pointer">
            <input type="checkbox" onchange="this.form.submit()" <?= $u['is_blocked'] ? 'checked' : '' ?> class="sr-only peer">
            <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-red-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-red-600"></div>
          </label>
        </form>
      </td>
      <?php endif; ?>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
