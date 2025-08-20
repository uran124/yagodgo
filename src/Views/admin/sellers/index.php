<?php /** @var array $sellers */ ?>
<table class="min-w-full bg-white rounded shadow overflow-hidden">
  <thead class="bg-gray-200 text-gray-700">
    <tr>
      <th class="p-3 text-left font-semibold">Компания</th>
      <th class="p-3 text-left font-semibold">Имя</th>
      <th class="p-3 text-left font-semibold">Телефон</th>
      <th class="p-3 text-left font-semibold">Баланс</th>
      <th class="p-3 text-center font-semibold">Редактировать</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($sellers as $s): ?>
    <tr class="border-b hover:bg-gray-50 transition-all duration-200">
      <td class="p-3">
        <a href="/admin/sellers/<?= $s['id'] ?>" class="text-[#C86052] hover:underline"><?= htmlspecialchars($s['company_name']) ?></a>
      </td>
      <td class="p-3"><?= htmlspecialchars($s['name']) ?></td>
      <td class="p-3 text-gray-600"><?= htmlspecialchars($s['phone']) ?></td>
      <td class="p-3 text-gray-600"><?= (int)$s['rub_balance'] ?> ₽</td>
      <td class="p-3 text-center">
        <a href="/admin/sellers/edit?id=<?= $s['id'] ?>" class="text-[#C86052] hover:underline">Редактировать</a>
      </td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
