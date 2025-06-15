<?php /** @var array $orders @var string|null $userName */ ?>
<?php
function status_classes(string $status): string {
    return match($status) {
        'new' => 'bg-red-100 text-red-800',
        'processing' => 'bg-yellow-100 text-yellow-800',
        'assigned' => 'bg-green-100 text-green-800',
        'delivered' => 'bg-gray-200 text-gray-800',
        'cancelled' => 'text-gray-500',
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
    <div id="ordersContainer" class="mt-4 space-y-4">
      <?php foreach ($orders as $order): ?>
        <?php $info = order_status_info($order['status']); ?>
        <div class="order-card rounded-2xl shadow p-3 sm:p-4 hover:-translate-y-1 hover:shadow-lg transition-transform <?= $info['bg'] ?>" data-status="<?= $order['status'] ?>" data-id="<?= $order['id'] ?>">
          <div class="flex justify-between items-start">
            <div class="flex items-center space-x-2">
              <span class="material-icons-round text-lg">shopping_bag</span>
              <span class="font-semibold">Заказ #<?= $order['id'] ?></span>
            </div>
            <div class="order-date text-xs text-gray-500">
              <?= date('d.m.Y', strtotime($order['created_at'])) ?> · <?= date('H:i', strtotime($order['created_at'])) ?>
            </div>
          </div>
          <div class="mt-2">
            <span class="status-badge inline-block text-sm px-2 py-0.5 rounded-full <?= status_classes($order['status']) ?>">
              <?= order_status_info($order['status'])['label'] ?>
            </span>
          </div>
          <div class="mt-2">
            <button type="button" class="toggle-items text-sm text-gray-600 flex items-center">
              <span class="material-icons-round mr-1 text-base">inventory_2</span>
              <?= count($order['items'] ?? []) ?> позиции
            </button>
            <ul class="items-list hidden mt-1 ml-5 text-sm list-disc text-gray-700">
              <?php foreach (($order['items'] ?? []) as $it): ?>
                <li><?= htmlspecialchars($it['product_name']) ?><?php if(!empty($it['variety'])): ?> «<?= htmlspecialchars($it['variety']) ?>»<?php endif; ?> — <?= $it['quantity'] ?> кг</li>
              <?php endforeach; ?>
            </ul>
          </div>
          <?php if (!empty($order['delivery_date'])): ?>
            <div class="mt-2 text-sm text-gray-600 flex items-center">
              <span class="material-icons-round mr-1 text-base">local_shipping</span>
              Доставка: <?= date('d.m.Y', strtotime($order['delivery_date'])) ?><?php if($order['delivery_slot']): ?>, <?= htmlspecialchars($order['delivery_slot']) ?><?php endif; ?>
            </div>
          <?php else: ?>
            <button class="mt-2 text-sm text-blue-600 underline">Выбрать слот</button>
          <?php endif; ?>
          <?php if (!empty($order['address'])): ?>
            <div class="mt-1 text-sm text-gray-600 flex items-center">
              <span class="material-icons-round mr-1 text-base">location_on</span>
              Адрес: <?= htmlspecialchars($order['address']) ?>
            </div>
          <?php endif; ?>
          <div class="mt-3 font-semibold">Итого: <?= number_format($order['total_amount'], 0, '.', ' ') ?> ₽</div>
          <div class="mt-3 flex flex-col sm:flex-row gap-2">
            <a href="/orders/<?= $order['id'] ?>" class="px-3 py-2 bg-gray-100 rounded-lg text-sm text-center">Детали</a>
            <button class="btn-repeat px-3 py-2 border rounded-lg text-sm" aria-label="Повторить заказ" data-id="<?= $order['id'] ?>">Повторить</button>
            <?php if ($order['status'] === 'new'): ?>
              <button class="btn-cancel px-3 py-2 border border-red-600 text-red-600 rounded-lg text-sm" aria-label="Отменить заказ" data-id="<?= $order['id'] ?>">Отменить</button>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</main>

<script>
  document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.toggle-items').forEach(function(btn){
      btn.addEventListener('click', function(){
        const list = btn.nextElementSibling;
        if (list) list.classList.toggle('hidden');
      });
    });

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
