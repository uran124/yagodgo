<?php /** @var array $users */ ?>
<table class="min-w-full bg-white rounded shadow overflow-hidden">
  <thead class="bg-gray-200 text-gray-700">
    <tr>
      <th class="p-2">ID</th>
      <th class="p-2">Имя</th>
      <th class="p-2">Телефон</th>
      <th class="p-2">Роль</th>
      <th class="p-2">Дата регистрации</th>
      <th class="p-2">Действия</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach($users as $u): ?>
    <tr class="border-b hover:bg-gray-50">
      <td class="p-2"><?= $u['id'] ?></td>
      <td class="p-2"><?= htmlspecialchars($u['name']) ?></td>
      <td class="p-2"><?= htmlspecialchars($u['phone']) ?></td>
      <td class="p-2"><?= htmlspecialchars($u['role']) ?></td>
      <td class="p-2"><?= htmlspecialchars($u['created_at']) ?></td>
      <td class="p-2">
        <a href="/admin/users/edit?id=<?= $u['id'] ?>"
           class="flex items-center text-[#C86052] hover:underline">
          <span class="material-icons-round text-base mr-1">edit</span> Редактировать
        </a>
      </td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
