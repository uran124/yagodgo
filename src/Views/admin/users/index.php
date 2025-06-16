<?php /** @var array $users */ ?>
<form method="get" class="mb-4 flex">
  <input type="text" name="q" value="<?= htmlspecialchars($search ?? '') ?>" placeholder="Телефон или адрес" class="border rounded px-3 py-2 mr-2 flex-grow">
  <button type="submit" class="bg-[#C86052] text-white px-4 py-2 rounded">Поиск</button>
</form>
<a href="/admin/users/edit" class="bg-[#C86052] text-white px-4 py-2 rounded mb-4 inline-flex items-center">
  <span class="material-icons-round text-base mr-1">add</span> Добавить пользователя
</a>
<table class="min-w-full bg-white rounded shadow overflow-hidden">
  <thead class="bg-gray-200 text-gray-700">
    <tr>
      <th class="p-3 text-left font-semibold">ID</th>
      <th class="p-3 text-left font-semibold">Имя</th>
      <th class="p-3 text-left font-semibold">Телефон</th>
      <th class="p-3 text-left font-semibold">Адрес</th>
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
      <td class="p-3 text-gray-600"><?= htmlspecialchars($u['address'] ?? '') ?></td>
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
