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
          <button type="submit" class="text-red-500 hover:text-red-700" title="Удалить">
    </li>
</ul>
          <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
          <button type="submit" class="text-red-500 hover:text-red-700 flex items-center" title="Удалить">
            <span class="material-icons-round">delete</span>
          </button>
        </form>
      </div>
    </div>
  <?php endforeach; ?>
</div>