<?php /** @var array|null $user */ ?>
<?php $isNew = empty($user); ?>

<?php if (!empty($_GET['error'])): ?>
  <div class="bg-red-50 border-l-4 border-red-400 p-3 mb-4 rounded">
    <p class="text-red-700 text-sm"><?= htmlspecialchars($_GET['error']) ?></p>
  </div>
<?php endif; ?>

<?php if ($isNew): ?>
  <form action="/admin/users/save" method="post" class="bg-white p-6 rounded shadow max-w-md space-y-4">
    <div>
      <label class="block mb-1">Имя</label>
      <input name="name" type="text" class="w-full border px-2 py-1 rounded" required>
    </div>
    <div>
      <label class="block mb-1">Телефон (7XXXXXXXXXX)</label>
      <input name="phone" type="tel" class="w-full border px-2 py-1 rounded" required>
    </div>
    <div>
      <label class="block mb-1">Адрес</label>
      <input name="address" type="text" class="w-full border px-2 py-1 rounded" required>
    </div>
    <div>
      <label class="block mb-1">Invite-код (необязательно)</label>
      <input name="invite" type="text" class="w-full border px-2 py-1 rounded">
    </div>
    <div>
      <label class="block mb-1">PIN (4 цифры)</label>
      <input name="pin" type="password" maxlength="4" class="w-full border px-2 py-1 rounded" required>
    </div>
    <button type="submit" class="bg-[#C86052] text-white px-4 py-2 rounded">Создать</button>
  </form>
<?php else: ?>
  <form action="/admin/users/save" method="post" class="bg-white p-6 rounded shadow max-w-md space-y-4">
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
      </select>
    </div>
    <div class="flex items-center space-x-2">
      <input type="checkbox" name="is_blocked" value="1" <?= !empty($user['is_blocked']) ? 'checked' : '' ?>>
      <span>Заблокирован</span>
    </div>
    <button type="submit" class="bg-[#C86052] text-white px-4 py-2 rounded">Сохранить</button>
  </form>
<?php endif; ?>

