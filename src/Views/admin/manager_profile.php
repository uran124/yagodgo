<?php
/** @var int $directClients */
/** @var int $secondClients */
/** @var int $ordersCount */
/** @var array $partnerStats */
/** @var int $directBonus */
/** @var int $secondBonus */
/** @var int $pointsBalance */
/** @var array $managerAccruals */
/** @var int $rubBalance */
/** @var array $payoutTransactions */
?>
<div class="space-y-6">
  <div class="bg-white rounded shadow p-2 md:p-4">
    <h2 class="text-base md:text-lg font-semibold mb-2">Общая статистика</h2>
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 md:gap-4 text-center">
      <div>
        <div class="text-xl md:text-2xl font-bold text-[#C86052]"><?= $ordersCount ?></div>
        <div class="text-sm text-gray-600">продаж</div>
      </div>
      <div>
        <div class="text-xl md:text-2xl font-bold text-[#C86052]"><?= $directClients ?></div>
        <div class="text-sm text-gray-600">прямых клиентов</div>
      </div>
      <div>
        <div class="text-xl md:text-2xl font-bold text-[#C86052]"><?= $secondClients ?></div>
        <div class="text-sm text-gray-600">клиентов второго уровня</div>
      </div>
    </div>
  </div>
  <div class="bg-white rounded shadow p-2 md:p-4">
    <h2 class="text-base md:text-lg font-semibold mb-2">Баланс</h2>
    <div class="grid grid-cols-2 md:grid-cols-4 gap-2 md:gap-4 text-center">
      <div>
        <div class="text-xl md:text-2xl font-bold text-[#C86052]"><?= $directBonus ?> ₽</div>
        <div class="text-sm text-gray-600">+3% за самостоятельные заказы по своей ссылке</div>
      </div>
      <div>
        <div class="text-xl md:text-2xl font-bold text-[#C86052]"><?= $secondBonus ?> ₽</div>
        <div class="text-sm text-gray-600">базовые 3% от всех продаж</div>
      </div>
      <div>
        <div class="text-xl md:text-2xl font-bold text-[#C86052]"><?= $pointsBalance ?> <span class="text-lg">🍓</span></div>
        <div class="text-sm text-gray-600">баланс клубничек</div>
      </div>
      <div>
        <div class="text-xl md:text-2xl font-bold text-[#C86052]"><?= $rubBalance ?> ₽</div>
        <div class="text-sm text-gray-600">баланс рублей</div>
      </div>
    </div>
    <?php $inviteLink = "https://berrygo.ru/?invite=" . urlencode($_SESSION['referral_code'] ?? ''); ?>
    <div class="mt-4 text-center space-y-2">
      <div class="text-sm text-gray-600">Пригласительная ссылка</div>
      <button onclick="copyInviteLink()" class="underline break-all">
        <?= htmlspecialchars($inviteLink) ?>
      </button>
      <div class="text-sm text-gray-600">Пригласительный код</div>
      <button onclick="copyInviteCode()" class="underline">
        <?= htmlspecialchars($_SESSION['referral_code'] ?? '') ?>
      </button>
    </div>
    <div class="mt-4 rounded-lg border border-[#F2D1CB] bg-[#FFF7F5] p-3 text-sm text-gray-700">
      <div class="font-semibold text-gray-900 mb-1">Как начисляется менеджерский бонус</div>
      <ul class="list-disc pl-5 space-y-1">
        <li>Базовые 3% начисляются менеджеру со всех завершённых заказов проекта.</li>
        <li>Дополнительные +3% начисляются только за самостоятельные заказы клиентов, пришедших по ссылке менеджера.</li>
        <li>Если заказ за клиента оформлен через панель менеджера, дополнительные +3% не начисляются.</li>
      </ul>
    </div>
    <div class="text-center mt-4">
      <form method="POST" action="/manager/payout">
        <button class="bg-[#C86052] text-white px-4 py-2 rounded">Запросить выплату</button>
      </form>
    </div>
    <?php if (!empty($managerAccruals)): ?>
    <div class="mt-4 overflow-x-auto">
      <h3 class="text-sm font-semibold text-gray-800 mb-2">Последние начисления менеджера</h3>
      <table class="min-w-full text-left text-sm">
        <thead class="bg-gray-100">
          <tr>
            <th class="p-2">Дата</th>
            <th class="p-2">Сумма</th>
            <th class="p-2">Основание</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($managerAccruals as $tx): ?>
          <tr class="border-b">
            <td class="p-2"><?= htmlspecialchars($tx['created_at']) ?></td>
            <td class="p-2"><?= (int)$tx['amount'] ?> ₽</td>
            <td class="p-2"><?= htmlspecialchars($tx['description']) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
    <?php if (!empty($payoutTransactions)): ?>
    <div class="mt-4 overflow-x-auto">
      <table class="min-w-full text-left text-sm">
        <thead class="bg-gray-100">
          <tr>
            <th class="p-2">Дата</th>
            <th class="p-2">Сумма</th>
            <th class="p-2">Описание</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($payoutTransactions as $tx): ?>
          <tr class="border-b">
            <td class="p-2"><?= htmlspecialchars($tx['created_at']) ?></td>
            <td class="p-2"><?= -$tx['amount'] ?> ₽</td>
            <td class="p-2"><?= htmlspecialchars($tx['description']) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
  <div class="bg-white rounded shadow p-2 md:p-4">
    <h2 class="text-base md:text-lg font-semibold mb-4">Партнёры</h2>
    <?php if (empty($partnerStats)): ?>
      <p class="text-gray-600">Партнёров нет</p>
    <?php else: ?>
    <table class="min-w-full text-left text-sm">
      <thead class="bg-gray-100">
        <tr>
          <th class="p-2">Имя</th>
          <th class="p-2">Телефон</th>
          <th class="p-2 text-center">Клиенты</th>
          <th class="p-2 text-center">Заказы</th>
          <th class="p-2 text-center">Сумма</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($partnerStats as $ps): ?>
        <tr class="border-b">
          <td class="p-2"><?= htmlspecialchars($ps['name']) ?></td>
          <td class="p-2"><?= htmlspecialchars($ps['phone']) ?></td>
          <td class="p-2 text-center"><?= $ps['clients'] ?></td>
          <td class="p-2 text-center"><?= $ps['orders'] ?></td>
          <td class="p-2 text-center"><?= $ps['revenue'] ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>
</div>
<script>
function copyInviteLink() {
  const link = "<?= addslashes($inviteLink) ?>";
  navigator.clipboard.writeText(link).then(() => alert('Ссылка скопирована в буфер обмена!'));
}
function copyInviteCode() {
  const code = "<?= addslashes($_SESSION['referral_code'] ?? '') ?>";
  navigator.clipboard.writeText(code).then(() => alert('Код скопирован в буфер обмена!'));
}
</script>
