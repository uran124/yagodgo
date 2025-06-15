<?php /** @var array $coupons */ ?>
<a href="/admin/coupons/edit" class="bg-[#C86052] text-white px-4 py-2 rounded mb-4 inline-flex items-center">
  <span class="material-icons-round text-base mr-1">add</span> Добавить купон
</a>
<div class="bg-white p-4 rounded shadow mb-6">
  <form action="/admin/coupons/generate" method="post" class="flex items-end space-x-2">
    <select name="type" class="border px-2 py-1 rounded">
      <option value="points">Баллы</option>
      <option value="discount">Скидка %</option>
    </select>
    <input type="number" name="value" class="border px-2 py-1 rounded w-24" placeholder="Значение" required>
    <input type="date" name="expires_at" class="border px-2 py-1 rounded">
    <button type="submit" class="bg-[#C86052] text-white px-3 py-1 rounded">Сгенерировать</button>
  </form>
</div>
<table class="min-w-full bg-white rounded shadow overflow-hidden">
  <thead class="bg-gray-200 text-gray-700">
    <tr>
      <th class="p-3 text-left font-semibold">Код</th>
      <th class="p-3 text-left font-semibold">Тип</th>
      <th class="p-3 text-left font-semibold">Значение</th>
      <th class="p-3 text-left font-semibold">Истекает</th>
      <th class="p-3 text-center font-semibold">Активен</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($coupons as $c): ?>
    <tr class="border-b hover:bg-gray-50 transition-all duration-200">
      <td class="p-3 font-medium text-gray-600"><?= htmlspecialchars($c['code']) ?></td>
      <td class="p-3 text-gray-600"><?= htmlspecialchars($c['type']) ?></td>
      <td class="p-3 text-gray-600">
        <?= $c['type']==='points' ? $c['points'].' баллов' : $c['discount'].' %' ?>
      </td>
      <td class="p-3 text-gray-600"><?= htmlspecialchars($c['expires_at']) ?></td>
      <td class="p-3 text-center text-gray-600"><?= $c['is_active'] ? 'Да' : 'Нет' ?></td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
