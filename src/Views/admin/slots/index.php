<?php /** @var array $slots */ ?>
<a href="/admin/slots/edit" class="bg-[#C86052] text-white px-4 py-2 rounded mb-4 inline-flex items-center">
  <span class="material-icons-round text-base mr-1">add</span> Добавить слот
</a>
<table class="min-w-full bg-white rounded shadow overflow-hidden">
  <thead class="bg-gray-200 text-gray-700">
    <tr>
      <th class="p-2">Дата</th>
      <th class="p-2">Время с</th>
      <th class="p-2">Время до</th>
      <th class="p-2">Действия</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($slots as $s): ?>
    <tr class="border-b hover:bg-gray-50">
      <td class="p-2"><?= htmlspecialchars($s['date']) ?></td>
      <td class="p-2"><?= htmlspecialchars($s['time_from']) ?></td>
      <td class="p-2"><?= htmlspecialchars($s['time_to']) ?></td>
      <td class="p-2 flex space-x-2">
        <a href="/admin/slots/edit?id=<?= $s['id'] ?>" class="text-[#C86052]">
          <span class="material-icons-round">edit</span>
        </a>
        <form action="/admin/slots/delete" method="post" onsubmit="return confirm('Удалить слот?');">
          <input type="hidden" name="id" value="<?= $s['id'] ?>">
          <button type="submit" class="text-red-600">
            <span class="material-icons-round">delete</span>
          </button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
