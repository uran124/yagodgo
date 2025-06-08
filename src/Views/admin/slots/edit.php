<?php /** @var array|null $slot */ ?>
<form action="/admin/slots/save" method="post" class="bg-white p-6 rounded shadow max-w-md space-y-4">
  <?php if ($slot): ?>
    <input type="hidden" name="id" value="<?= $slot['id'] ?>">
  <?php endif; ?>
  <div>
    <label class="block mb-1">Дата</label>
    <input type="date" name="date" value="<?= htmlspecialchars($slot['date'] ?? '') ?>" class="w-full border px-2 py-1 rounded" required>
  </div>
  <div class="flex space-x-2">
    <div class="flex-1">
      <label class="block mb-1">Время с</label>
      <input type="time" name="time_from" value="<?= htmlspecialchars($slot['time_from'] ?? '') ?>" class="w-full border px-2 py-1 rounded" required>
    </div>
    <div class="flex-1">
      <label class="block mb-1">Время до</label>
      <input type="time" name="time_to" value="<?= htmlspecialchars($slot['time_to'] ?? '') ?>" class="w-full border px-2 py-1 rounded" required>
    </div>
  </div>
  <button type="submit" class="bg-[#C86052] text-white px-4 py-2 rounded">Сохранить</button>
</form>
