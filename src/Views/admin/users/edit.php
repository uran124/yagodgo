<?php /** @var array|null $user */ ?>
<?php $isNew = empty($user); ?>
<?php $role = $_SESSION['role'] ?? ''; $isManager = in_array($role, ['manager','partner'], true); $base = $role === 'manager' ? '/manager' : ($role === 'partner' ? '/partner' : '/admin'); ?>

<?php if (!empty($_GET['error'])): ?>
  <div class="bg-red-50 border-l-4 border-red-400 p-3 mb-4 rounded">
    <p class="text-red-700 text-sm"><?= htmlspecialchars($_GET['error']) ?></p>
  </div>
<?php endif; ?>

<?php if ($isNew): ?>
  <form action="<?= $base ?>/users/save" method="post" class="bg-white p-6 rounded shadow max-w-md space-y-4">
    <div>
      <label class="block mb-1">Имя</label>
      <input name="name" type="text" class="w-full border px-2 py-1 rounded" required>
    </div>
    <div>
      <label class="block mb-1">Телефон (9XXXXXXXXX)</label>
      <input name="phone" type="tel" class="w-full border px-2 py-1 rounded" required>
    </div>
    <div>
      <label class="block mb-1">Адрес</label>
      <input name="address" type="text" class="w-full border px-2 py-1 rounded" required>
    </div>
    <div>
      <label class="block mb-1">Invite-код (необязательно)</label>
      <input name="invite" type="text" value="<?= htmlspecialchars($_SESSION['referral_code'] ?? '') ?>"
             class="w-full border px-2 py-1 rounded" <?= $isManager ? 'disabled' : '' ?>>
      <?php if ($isManager): ?>
        <input type="hidden" name="invite" value="<?= htmlspecialchars($_SESSION['referral_code'] ?? '') ?>">
      <?php endif; ?>
    </div>
    <div>
      <label class="block mb-1">PIN (4 цифры)</label>
      <input name="pin" type="password" maxlength="4" class="w-full border px-2 py-1 rounded" required>
    </div>
    <button type="submit" class="bg-[#C86052] text-white px-4 py-2 rounded">Создать</button>
  </form>
  <script>
    const phoneInput = document.querySelector('input[name="phone"]');
    function cleanPhone(val) {
      let digits = val.replace(/\D/g, '');
      if (digits.startsWith('7') || digits.startsWith('8')) digits = digits.slice(1);
      return digits.slice(0, 10);
    }
    if (phoneInput) {
      phoneInput.addEventListener('input', () => {
        phoneInput.value = cleanPhone(phoneInput.value);
      });
    }
  </script>
<?php else: ?>
  <form action="<?= $base ?>/users/save" method="post" class="bg-white p-6 rounded shadow max-w-md space-y-4">
    <input type="hidden" name="id" value="<?= $user['id'] ?>">
    <div>
      <label class="block mb-1">Имя</label>
      <input type="text" value="<?= htmlspecialchars($user['name']) ?>" class="w-full border px-2 py-1 rounded" disabled>
    </div>
    <div>
      <label class="block mb-1">Телефон</label>
      <input type="text" value="<?= htmlspecialchars($user['phone']) ?>" class="w-full border px-2 py-1 rounded" disabled>
    </div>
    <div>
      <label class="block mb-1">Роль</label>
      <select name="role" class="w-full border px-2 py-1 rounded">
        <option value="client" <?= $user['role']==='client'?'selected':'' ?>>Клиент</option>
        <option value="courier" <?= $user['role']==='courier'?'selected':'' ?>>Курьер</option>
        <option value="admin" <?= $user['role']==='admin'?'selected':'' ?>>Админ</option>
        <option value="manager" <?= $user['role']==='manager'?'selected':'' ?>>Менеджер</option>
        <option value="partner" <?= $user['role']==='partner'?'selected':'' ?>>Партнёр</option>
        <option value="seller" <?= $user['role']==='seller'?'selected':'' ?>>Селлер</option>
      </select>
    </div>
    <div>
      <label class="block mb-1">Название компании</label>
      <input type="text" name="company_name" value="<?= htmlspecialchars($user['company_name'] ?? '') ?>" class="w-full border px-2 py-1 rounded">
    </div>
    <div>
      <label class="block mb-1">Адрес самовывоза</label>
      <input type="text" name="pickup_address" value="<?= htmlspecialchars($user['pickup_address'] ?? '') ?>" class="w-full border px-2 py-1 rounded">
    </div>
    <div>
      <label class="block mb-1">Стоимость доставки</label>
      <input type="number" step="0.01" name="delivery_cost" value="<?= htmlspecialchars($user['delivery_cost'] ?? '') ?>" class="w-full border px-2 py-1 rounded">
    </div>
    <div class="flex items-center space-x-2">
      <input type="checkbox" name="is_blocked" value="1" <?= !empty($user['is_blocked']) ? 'checked' : '' ?>>
      <span>Заблокирован</span>
    </div>
    <?php if (!empty($user['rub_balance'])): ?>
    <div>
      <div class="mb-1">Баланс, ₽</div>
      <div class="flex items-center space-x-2">
        <span class="font-mono text-lg"><?= (int)$user['rub_balance'] ?></span>
        <form action="<?= $base ?>/users/reset-balance" method="post" class="inline">
          <input type="hidden" name="id" value="<?= $user['id'] ?>">
          <button type="submit" class="px-2 py-1 bg-[#C86052] text-white rounded text-sm">Выплачено</button>
        </form>
      </div>
    </div>
    <?php endif; ?>
    <button type="submit" class="bg-[#C86052] text-white px-4 py-2 rounded">Сохранить</button>
  </form>
<?php endif; ?>

