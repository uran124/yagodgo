<?php /** @var array $users */ ?>
<table class="min-w-full bg-white rounded shadow overflow-hidden">
  <thead class="bg-gray-200 text-gray-700">
    <tr>
      <th class="p-3 text-left font-semibold">ID</th>
      <th class="p-3 text-left font-semibold">Имя</th>
      <th class="p-3 text-left font-semibold">Телефон</th>
      <th class="p-3 text-left font-semibold">Роль</th>
      <th class="p-3 text-center font-semibold">Заблокирован</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach($users as $u): ?>
    <tr class="border-b hover:bg-gray-50 transition-all duration-200">
      <td class="p-3 font-medium text-gray-600"><?= $u['id'] ?></td>
      <td class="p-3">
        <div class="flex items-center">
            <a href="/admin/users/edit?id=<?= $u['id'] ?>" class="">
                <span class="font-medium"><?= htmlspecialchars($u['name']) ?></span>
            </a>
        </div>
      </td>
      <td class="p-3 text-gray-600"><?= htmlspecialchars($u['phone']) ?></td>
      <td class="p-3">
        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?= $u['role'] === 'admin' ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800' ?>">
          <?= htmlspecialchars($u['role']) ?>
        </span>
      </td>
      <td class="p-3 text-center">
        <form action="/admin/users/toggle-block" method="post" class="inline-block">
          <input type="hidden" name="id" value="<?= $u['id'] ?>">
          <label class="relative inline-flex items-center cursor-pointer">
            <input type="checkbox" onchange="this.form.submit()" <?= $u['is_blocked'] ? 'checked' : '' ?> class="sr-only peer">
            <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-red-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-red-600"></div>
          </label>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
