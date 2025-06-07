<?php /** @var array $orders */ ?>
<ul class="bg-white rounded shadow divide-y">
  <?php foreach ($orders as $o): ?>
    <li class="p-4 flex items-center justify-between hover:bg-gray-50">
      <div class="flex flex-wrap gap-x-4 gap-y-1 text-sm md:text-base">
        <span class="font-semibold">#<?= $o['id'] ?></span>
        <span><?= htmlspecialchars($o['client_name']) ?></span>
        <span><?= $o['total_amount'] ?> ₽</span>
        <span><?= htmlspecialchars($o['status']) ?></span>
        <span class="text-gray-500"><?= $o['created_at'] ?></span>
        <span><?= htmlspecialchars($o['courier_name'] ?? '-') ?></span>
      </div>
      <div class="flex items-center space-x-2 ml-4">
        <a href="/admin/orders/<?= $o['id'] ?>" class="text-[#C86052] hover:text-[#B44D47]" title="Открыть">
          <span class="material-icons-round">open_in_new</span>
        </a>
        <form action="/admin/orders/delete" method="post">
          <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
          <button type="submit" class="text-red-500 hover:text-red-700" title="Удалить">
            <span class="material-icons-round">delete</span>
          </button>
        </form>
      </div>
    </li>
  <?php endforeach; ?>
</ul>