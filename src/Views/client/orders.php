<?php /** @var array $orders @var string|null $userName */ ?>
<?php
function status_classes(string $status): string {
    return match($status) {
        'new' => 'bg-red-100 text-red-800',
        'processing' => 'bg-yellow-100 text-yellow-800',
        'assigned' => 'bg-green-100 text-green-800',
        'delivered' => 'bg-blue-100 text-blue-800',
        'cancelled' => 'bg-gray-100 text-gray-800',
        default => 'bg-gray-100 text-gray-800',
    };
}
?>
<main class="min-h-screen bg-gray-50 pb-6 sm:pb-10">
  <div class="p-3 sm:p-4 space-y-4" id="ordersApp">
    <!-- Filter & search -->
    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-2">
      <div class="flex items-center gap-2">
        <select id="statusFilter" class="border rounded-lg px-3 py-2 text-sm">
          <option value="">Все статусы</option>
          <option value="new">Новые</option>
          <option value="processing">Принятые</option>
          <option value="assigned">Обработанные</option>
          <option value="delivered">Выполненные</option>
          <option value="cancelled">Отмененные</option>
        </select>
        <button id="sortBtn" class="flex items-center border rounded-lg px-3 py-2 text-sm text-gray-600">
          <span class="material-icons-round">swap_vert</span>
        </button>
      </div>
      <input id="searchInput" type="search" placeholder="Поиск по №" class="border rounded-lg px-3 py-2 text-sm flex-1 sm:max-w-xs" />
    </div>

    <!-- Orders list -->
    <div id="ordersContainer" class="mt-4 space-y-2">
      <?php foreach ($orders as $order): ?>
        <?php $info = order_status_info($order['status']); $first = $order['items'][0] ?? null; ?>
        <a href="/orders/<?= $order['id'] ?>" class="order-card flex justify-between items-center p-3 rounded-lg shadow hover:shadow-md transition-colors <?= $info['bg'] ?>" data-status="<?= $order['status'] ?>" data-id="<?= $order['id'] ?>">
          <div class="flex items-center gap-2 flex-wrap">
            <span class="material-icons-round text-lg">shopping_bag</span>
            <span class="font-semibold">#<?= $order['id'] ?>:</span>
            <span>
              <?php
                $date = !empty($order['delivery_date']) ? date('d.m', strtotime($order['delivery_date'])) : date('d.m', strtotime($order['created_at']));
                echo $date;
                if (!empty($order['delivery_slot'])) echo ' ' . htmlspecialchars($order['delivery_slot']);
              ?>
            </span>
            <?php if ($first): ?>
              <span><?= htmlspecialchars($first['product_name']) ?><?php if(!empty($first['variety'])): ?> «<?= htmlspecialchars($first['variety']) ?>»<?php endif; ?></span>
            <?php endif; ?>
            <span><?= number_format($order['total_amount'], 0, '.', ' ') ?>₽</span>
            <span class="order-date hidden"><?= date('d.m.Y H:i', strtotime($order['created_at'])) ?></span>
          </div>
          <span class="status-badge text-sm px-2 py-0.5 rounded-full <?= status_classes($order['status']) ?>">
            <?= order_status_info($order['status'])['label'] ?>
          </span>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</main>

<script>
  document.addEventListener('DOMContentLoaded', function() {

    const filter = document.getElementById('statusFilter');
    const search = document.getElementById('searchInput');
    const sortBtn = document.getElementById('sortBtn');
    const container = document.getElementById('ordersContainer');
    let asc = false;

    function applyFilters() {
      const status = filter.value;
      const term = search.value.trim();
      container.querySelectorAll('.order-card').forEach(card => {
        const matchStatus = !status || card.dataset.status === status;
        const matchSearch = !term || card.dataset.id.includes(term);
        card.style.display = (matchStatus && matchSearch) ? '' : 'none';
      });
    }

    filter.addEventListener('change', applyFilters);
    search.addEventListener('input', applyFilters);

    sortBtn.addEventListener('click', function(){
      const cards = Array.from(container.children);
      cards.sort((a,b)=>{
        const da = new Date(a.querySelector('.order-date').textContent.replace(' · ', ' '));
        const db = new Date(b.querySelector('.order-date').textContent.replace(' · ', ' '));
        return asc ? da - db : db - da;
      });
      asc = !asc;
      cards.forEach(c=>container.appendChild(c));
    });
  });
</script>
