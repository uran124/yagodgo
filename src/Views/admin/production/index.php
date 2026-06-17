<?php /** @var array $summary @var array $jobs @var string $statusFilter */ ?>
<?php
  $statusLabels = [
    '' => 'Все активные',
    'new' => 'Новые',
    'assigned' => 'Назначены',
    'in_progress' => 'В работе',
    'photo_uploaded' => 'Фото на проверке',
    'approved' => 'Фото принято',
    'problem' => 'Проблемы',
    'overdue' => 'Просрочены',
  ];
  $base = ($_SESSION['role'] ?? '') === 'manager' ? '/manager' : (($_SESSION['role'] ?? '') === 'partner' ? '/partner' : '/admin');
?>
<div class="space-y-4">
  <div class="flex flex-wrap items-center justify-between gap-2">
    <div>
      <h1 class="text-xl font-semibold">Производство</h1>
      <p class="text-sm text-gray-500">Операционная панель производственных заданий внутри существующих заказов.</p>
    </div>
    <a href="<?= $base ?>/orders" class="rounded border px-3 py-2 text-sm">К заказам</a>
  </div>

  <div class="grid gap-2 md:grid-cols-4">
    <?php foreach (['all_active' => 'Все активные', 'new' => 'Новые', 'in_progress' => 'В работе', 'photo_uploaded' => 'Фото', 'problem' => 'Проблемы', 'overdue' => 'Просрочены'] as $key => $label): ?>
      <a href="<?= $base ?>/production<?= $key === 'all_active' ? '' : ('?status=' . urlencode($key)) ?>" class="rounded border bg-white p-3 shadow-sm <?= ($statusFilter === $key || ($key === 'all_active' && $statusFilter === '')) ? 'border-[#C86052]' : 'border-slate-200' ?>">
        <div class="text-xs text-gray-500"><?= htmlspecialchars($label) ?></div>
        <div class="text-2xl font-semibold"><?= (int)($summary[$key] ?? 0) ?></div>
      </a>
    <?php endforeach; ?>
  </div>

  <div class="rounded bg-white shadow">
    <div class="border-b p-3 font-semibold"><?= htmlspecialchars($statusLabels[$statusFilter] ?? 'Производственные задания') ?></div>
    <?php if (empty($jobs)): ?>
      <div class="p-6 text-center text-gray-500">Заданий по выбранному фильтру нет.</div>
    <?php else: ?>
      <div class="divide-y">
        <?php foreach ($jobs as $job): ?>
          <div class="grid gap-2 p-3 md:grid-cols-[1fr_auto]">
            <div>
              <div class="font-medium">Задание #<?= (int)$job['id'] ?> · заказ #<?= (int)$job['order_id'] ?></div>
              <div class="text-sm text-gray-600"><?= htmlspecialchars((string)($job['product_name'] ?? 'Товар')) ?> · клиент: <?= htmlspecialchars((string)($job['client_name'] ?? '—')) ?></div>
              <div class="text-xs text-gray-500">Статус заказа: <?= htmlspecialchars((string)($job['order_status'] ?? '—')) ?> · доставка: <?= htmlspecialchars((string)($job['delivery_date'] ?? '—')) ?></div>
            </div>
            <div class="text-sm md:text-right">
              <div class="font-medium"><?= htmlspecialchars((string)($job['status'] ?? '')) ?></div>
              <div class="text-gray-500">Готовность: <?= htmlspecialchars((string)($job['production_deadline'] ?? '—')) ?></div>
              <a href="<?= $base ?>/orders/<?= (int)$job['order_id'] ?>" class="text-[#C86052] hover:underline">Открыть заказ</a>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>
