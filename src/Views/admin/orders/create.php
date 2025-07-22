<?php /** @var array $products @var array $slots */ ?>
<?php $isManager = ($_SESSION['role'] ?? '') === 'manager'; $base = $isManager ? '/manager' : '/admin'; ?>

<?php if (!empty($_GET['error'])): ?>
  <div class="mb-4 p-3 bg-red-50 text-red-700 rounded"><?= htmlspecialchars($_GET['error']) ?></div>
<?php endif; ?>

<form action="<?= $base ?>/orders/create" method="post" class="space-y-6" id="orderForm">
  <!-- Шаг 1 -->
  <div id="step1" class="bg-white p-4 rounded shadow space-y-2">
    <h2 class="font-semibold mb-2">Шаг 1. Клиент</h2>
    <div>
      <input type="radio" name="user_mode" value="existing" id="modeExist" checked>
      <label for="modeExist">Существующий</label>
      <input type="radio" name="user_mode" value="new" id="modeNew" class="ml-4">
      <label for="modeNew">Новый пользователь</label>
    </div>
    <div id="existBlock" class="space-y-2">
      <input type="text" id="searchPhone" placeholder="Телефон" class="border px-2 py-1 rounded w-56">
      <input type="hidden" name="user_id" id="userId">
      <ul id="suggestions" class="border bg-white rounded shadow absolute hidden"></ul>
    </div>
    <div id="newBlock" class="space-y-2 hidden">
      <input type="text" name="new_name" placeholder="Имя" class="border px-2 py-1 rounded w-56">
      <input type="tel" name="new_phone" placeholder="Телефон 7XXXXXXXXXX" class="border px-2 py-1 rounded w-56">
      <input type="password" name="new_pin" placeholder="PIN" maxlength="4" class="border px-2 py-1 rounded w-32">
      <input type="text" name="new_address" placeholder="Адрес" class="border px-2 py-1 rounded w-full">
    </div>
    <div>
      <label><input type="checkbox" name="pickup" id="pickupChk"> Самовывоз</label>
    </div>
    <div>
      <label>Дата доставки:</label>
      <input type="date" name="delivery_date" class="border px-2 py-1 rounded">
      <select name="slot_id" class="border px-2 py-1 rounded">
        <option value="">-- слот --</option>
        <?php foreach ($slots as $s): ?>
          <option value="<?= $s['id'] ?>">
            <?= htmlspecialchars($s['date']) ?> <?= htmlspecialchars($s['time_from']) ?>-<?= htmlspecialchars($s['time_to']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <button type="button" id="toStep2" class="bg-[#C86052] text-white px-4 py-2 rounded">Далее</button>
  </div>

  <!-- Шаг 2 -->
  <div id="step2" class="bg-white p-4 rounded shadow space-y-2 hidden">
    <h2 class="font-semibold mb-2">Шаг 2. Товары</h2>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
      <?php foreach ($products as $p): ?>
        <div class="border rounded p-2 flex flex-col items-center">
          <?php if (!empty($p['image_path'])): ?>
            <img src="<?= htmlspecialchars($p['image_path']) ?>" class="w-24 h-24 object-cover mb-2" />
          <?php endif; ?>
          <div class="font-medium text-center mb-1">
            <?= htmlspecialchars($p['product']) ?><?php if ($p['variety']) echo ' '.htmlspecialchars($p['variety']); ?>
          </div>
          <input type="number" step="0.01" name="items[<?= $p['id'] ?>]" placeholder="Кол-во" class="border px-2 py-1 rounded w-24">
        </div>
      <?php endforeach; ?>
    </div>
    <button type="button" id="toStep3" class="bg-[#C86052] text-white px-4 py-2 rounded">Далее</button>
  </div>

  <!-- Шаг 3 -->
  <div id="step3" class="bg-white p-4 rounded shadow space-y-2 hidden">
    <h2 class="font-semibold mb-2">Шаг 3. Подтверждение</h2>
    <div>
      <label>Купон:</label>
      <input type="text" name="coupon_code" class="border px-2 py-1 rounded">
    </div>
    <div>
      <label>Списать баллы:</label>
      <input type="number" name="points" class="border px-2 py-1 rounded w-24">
    </div>
    <button type="submit" class="bg-[#C86052] text-white px-4 py-2 rounded">Создать заказ</button>
  </div>
</form>

<script>
  const existBlock = document.getElementById('existBlock');
  const newBlock = document.getElementById('newBlock');
  document.getElementById('modeExist').addEventListener('change', ()=>{
    existBlock.classList.remove('hidden');
    newBlock.classList.add('hidden');
  });
  document.getElementById('modeNew').addEventListener('change', ()=>{
    existBlock.classList.add('hidden');
    newBlock.classList.remove('hidden');
  });

  document.getElementById('toStep2').addEventListener('click', ()=>{
    document.getElementById('step1').classList.add('hidden');
    document.getElementById('step2').classList.remove('hidden');
  });
  document.getElementById('toStep3').addEventListener('click', ()=>{
    document.getElementById('step2').classList.add('hidden');
    document.getElementById('step3').classList.remove('hidden');
  });

  const search = document.getElementById('searchPhone');
  const sugg = document.getElementById('suggestions');
  search.addEventListener('input', ()=>{
    const term = search.value.replace(/\D/g,'');
    if (term.length < 3) { sugg.classList.add('hidden'); return; }
    fetch('<?= $base ?>/users/search?term='+term)
      .then(r=>r.json()).then(data=>{
        sugg.innerHTML='';
        data.forEach(u=>{
          const li=document.createElement('li');
          li.textContent = u.phone+' '+u.name;
          li.className='px-2 py-1 cursor-pointer hover:bg-gray-100';
          li.addEventListener('click', ()=>{
            search.value=u.phone;
            document.getElementById('userId').value=u.id;
            sugg.classList.add('hidden');
          });
          sugg.appendChild(li);
        });
        sugg.classList.remove('hidden');
      });
  });
</script>
