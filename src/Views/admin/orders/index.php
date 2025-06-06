<?php /** @var array $orders */ ?>
<div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
  <?php foreach ($orders as $o): ?>
    <div class="bg-white rounded shadow p-4 flex flex-col hover:shadow-md transition-shadow">
      <div class="flex justify-between items-center mb-2">
        <span class="font-semibold">#<?= $o['id'] ?></span>
        <span class="text-sm text-gray-500"><?= $o['created_at'] ?></span>
      </div>
      <div class="font-medium text-gray-800 mb-1">
        <?= htmlspecialchars($o['client_name']) ?>
      </div>
      <div class="text-sm text-gray-600 mb-1">
        <?= htmlspecialchars($o['status']) ?>
      </div>
      <div class="text-lg font-semibold mb-1">
        <?= $o['total_amount'] ?> ₽
      </div>
      <div class="text-sm text-gray-500 mb-4">
        Курьер: <?= htmlspecialchars($o['courier_name'] ?? '-') ?>
      </div>
      <div class="flex justify-end space-x-2 mt-auto">
        <a href="/admin/orders/<?= $o['id'] ?>" class="text-[#C86052] hover:text-[#B44D47] flex items-center" title="Открыть">
          <span class="material-icons">open_in_new</span>
        </a>
        <form action="/admin/orders/delete" method="post">
          <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
          <button type="submit" class="text-red-500 hover:text-red-700 flex items-center" title="Удалить">
            <span class="material-icons">delete</span>
          </button>
        </form>
      </div>
    </div>
  <?php endforeach; ?>
</div>
