<?php /** @var array $products @var array $slots */ ?>
<?php $role = $_SESSION['role'] ?? ''; $isManager = in_array($role, ['manager','partner'], true); $base = $role === 'manager' ? '/manager' : ($role === 'partner' ? '/partner' : '/admin'); ?>

<?php if (!empty($_GET['error'])): ?>
  <div class="mb-4 p-3 bg-red-50 text-red-700 rounded"><?= htmlspecialchars($_GET['error']) ?></div>
<?php endif; ?>

<form action="<?= $base ?>/orders/create" method="post" class="space-y-6" id="orderForm">
  <!-- Шаг 1 -->
  <div id="step1" class="bg-white p-4 rounded shadow space-y-2">
    <h2 class="font-semibold mb-2">Шаг 1. Клиент</h2>
    <div id="existBlock" class="space-y-2">
      <input type="text" id="searchPhone" placeholder="Телефон" class="border px-2 py-1 rounded w-56">
      <input type="hidden" name="user_id" id="userId">
      <ul id="suggestions" class="border bg-white rounded shadow absolute hidden"></ul>
      <div id="addressWrapper" class="space-y-1 hidden">
        <select name="address_id" id="addressSelect" class="border px-2 py-1 rounded w-full"></select>
        <input type="text" name="address_new" id="addressNew" placeholder="Новый адрес" class="border px-2 py-1 rounded w-full hidden">
      </div>
      <div id="userInfo" class="text-sm text-gray-600 hidden"></div>
    </div>
    <div id="newBlock" class="space-y-2 hidden">
      <input type="text" name="new_name" placeholder="Имя" class="border px-2 py-1 rounded w-56">
      <input type="hidden" name="new_phone" id="newPhoneHidden">
      <input type="password" name="new_pin" placeholder="PIN" maxlength="4" class="border px-2 py-1 rounded w-32">
      <input type="text" name="new_address" placeholder="Адрес" class="border px-2 py-1 rounded w-full">
    </div>
    <div>
      <label>Дата доставки:</label>
      <input type="date" name="delivery_date" value="<?= $today ?>" class="border px-2 py-1 rounded">
      <select name="slot_id" class="border px-2 py-1 rounded">
        <?php foreach ($slots as $i => $s): ?>
          <option value="<?= $s['id'] ?>" <?= $i === 0 ? 'selected' : '' ?>>
            <?= htmlspecialchars($s['time_from']) ?>-<?= htmlspecialchars($s['time_to']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <button type="button" id="toStep2" class="bg-[#C86052] text-white px-4 py-2 rounded">Далее</button>
  </div>

  <!-- Шаг 2 -->
  <div id="step2" class="bg-white p-4 rounded shadow space-y-2 hidden">
    <h2 class="font-semibold mb-2">Шаг 2. Товары</h2>
    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-2">
      <?php foreach ($products as $p): ?>
        <div class="border rounded p-2 flex flex-col items-center text-sm min-w-24 max-w-30">
          <?php if (!empty($p['image_path'])): ?>
            <img src="<?= htmlspecialchars($p['image_path']) ?>" class="w-24 h-24 object-cover mb-1" />
          <?php endif; ?>
          <div class="font-medium text-center mb-1">
            <?= htmlspecialchars($p['product']) ?><?php if ($p['variety']) echo ' '.htmlspecialchars($p['variety']); ?><?php if (!empty($p['box_size']) && !empty($p['box_unit'])) echo ' '.$p['box_size'].' '.htmlspecialchars($p['box_unit']); ?>
          </div>
          <div class="flex items-center space-x-1">
            <button type="button" class="dec px-2 bg-gray-200 rounded" data-target="item<?= $p['id'] ?>">-</button>
            <?php
              $n = $p['product'] . ($p['variety'] ? ' '.$p['variety'] : '') .
                   (!empty($p['box_size']) && !empty($p['box_unit']) ? ' '.$p['box_size'].' '.$p['box_unit'] : '');
              $priceBox = $p['price'] * ($p['box_size'] > 0 ? $p['box_size'] : 1);
            ?>
            <input id="item<?= $p['id'] ?>" data-price="<?= $priceBox ?>" data-name="<?= htmlspecialchars($n) ?>" type="number" step="1" min="0" name="items[<?= $p['id'] ?>]" value="0" class="qty border px-1 py-0.5 rounded w-16 text-center">
            <button type="button" class="inc px-2 bg-gray-200 rounded" data-target="item<?= $p['id'] ?>">+</button>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
    <button type="button" id="toStep3" class="bg-[#C86052] text-white px-4 py-2 rounded">Далее</button>
  </div>

  <!-- Шаг 3 -->
  <div id="step3" class="bg-white p-4 rounded shadow space-y-2 hidden">
    <h2 class="font-semibold mb-2">Шаг 3. Подтверждение</h2>
    <div id="itemsList" class="space-y-1 text-sm"></div>
    <div id="summary" class="space-y-1 text-sm">
      <div class="flex justify-between"><span>Стоимость товаров:</span> <span id="sumSubtotal">0</span></div>
      <div id="rowReferral" class="flex justify-between hidden"><span>Скидка -10%</span> <span id="sumReferral">0</span></div>
      <div id="rowShipping" class="flex justify-between hidden"><span>Доставка:</span> <span id="sumShipping">0</span></div>
      <div class="flex justify-between font-semibold border-t pt-1"><span>Итого:</span> <span id="sumTotal">0</span></div>
    </div>
    <div>
      <label>Купон:</label>
      <input type="text" name="coupon_code" class="border px-2 py-1 rounded">
    </div>
    <div id="referralToggleWrap" class="hidden">
      <label class="inline-flex items-center cursor-pointer">
        <input type="hidden" name="has_used_referral_coupon" value="0">
        <input type="checkbox" id="referralToggle" name="has_used_referral_coupon" value="1" class="sr-only peer">
        <div class="w-10 h-5 bg-gray-200 rounded-full peer-checked:bg-[#C86052] relative transition-colors after:content-[''] after:absolute after:left-1 after:top-0.5 after:bg-white after:rounded-full after:h-4 after:w-4 after:transition-transform peer-checked:after:translate-x-5"></div>
        <span class="ml-2 text-sm">Скидка 10% на первый заказ</span>
      </label>
    </div>
    <div id="pointsRow" class="hidden">
      <label>Списано баллов: <span id="pointsAmount">0</span></label>
      <input type="hidden" name="points" id="pointsInput" value="0">
    </div>
    <button type="submit" class="bg-[#C86052] text-white px-4 py-2 rounded">Создать заказ</button>
  </div>
</form>

<script>
  const existBlock = document.getElementById('existBlock');
  const newBlock = document.getElementById('newBlock');
  const userInfo = document.getElementById('userInfo');

  document.getElementById('toStep2').addEventListener('click', ()=>{
    if (document.getElementById('userId').value === '') {
      if (newPhoneInput) newPhoneInput.value = search.value;
    }
    document.getElementById('step1').classList.add('hidden');
    document.getElementById('step2').classList.remove('hidden');
  });
  document.getElementById('toStep3').addEventListener('click', ()=>{
    document.getElementById('step2').classList.add('hidden');
    document.getElementById('step3').classList.remove('hidden');
    updateSummary();
  });

  const search = document.getElementById('searchPhone');
  const sugg = document.getElementById('suggestions');
  const addressWrapper = document.getElementById('addressWrapper');
  const addressSelect = document.getElementById('addressSelect');
  const addressNew = document.getElementById('addressNew');
  const qtyInputs = document.querySelectorAll('.qty');
  const subtotalEl = document.getElementById('sumSubtotal');
  const refRow = document.getElementById('rowReferral');
  const refEl = document.getElementById('sumReferral');
  const shippingRow = document.getElementById('rowShipping');
  const shippingEl = document.getElementById('sumShipping');
  const totalEl = document.getElementById('sumTotal');
  const pointsInput = document.getElementById('pointsInput');
  const pointsRow = document.getElementById('pointsRow');
  const pointsAmount = document.getElementById('pointsAmount');
  const itemsList = document.getElementById('itemsList');
  const newPhoneInput = document.querySelector('input[name="new_phone"]');
  const referralToggleWrap = document.getElementById('referralToggleWrap');
  const referralToggle = document.getElementById('referralToggle');
  const couponInput = document.querySelector('input[name="coupon_code"]');
  const myReferralCode = "<?= htmlspecialchars($_SESSION['referral_code'] ?? '') ?>";
  function cleanPhone(val) {
    let digits = val.replace(/\D/g,'');
    if (digits.startsWith('7') || digits.startsWith('8')) digits = digits.slice(1);
    return digits.slice(0,10);
  }

  search.addEventListener('input', ()=>{
    search.value = cleanPhone(search.value);
    const term = search.value;
    if (term.length < 2) { sugg.classList.add('hidden'); newBlock.classList.add('hidden'); userInfo.classList.add('hidden'); pointsRow.classList.add('hidden'); referralToggleWrap.classList.add('hidden'); referralToggle.checked=false; if(couponInput) couponInput.value=''; return; }
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
              loadAddresses(u.id);
              pointsInput.value = u.points_balance || 0;
              pointsAmount.textContent = u.points_balance || 0;
              pointsRow.classList.remove('hidden');
              newBlock.classList.add('hidden');
              userInfo.textContent = u.referrer_name ? 'Пригласил: '+u.referrer_name : 'Без пригласившего';
              userInfo.classList.remove('hidden');
              referralToggleWrap.classList.add('hidden');
              referralToggle.checked = false;
              if(couponInput) couponInput.value='';
              updateSummary();
            });
            sugg.appendChild(li);
          });
        if (data.length === 0) {
          newBlock.classList.remove('hidden');
          document.getElementById('userId').value='';
          pointsRow.classList.add('hidden');
          userInfo.classList.add('hidden');
          referralToggleWrap.classList.remove('hidden');
          referralToggle.checked = false;
          if(couponInput) couponInput.value = myReferralCode;
        } else {
          sugg.classList.remove('hidden');
          referralToggleWrap.classList.add('hidden');
          referralToggle.checked = false;
          if(couponInput) couponInput.value='';
        }
        });
  });

  document.querySelectorAll('.dec').forEach(btn => {
    btn.addEventListener('click', () => {
      const inp = document.getElementById(btn.dataset.target);
      if (!inp) return;
      inp.stepDown();
      updateSummary();
    });
  });
  document.querySelectorAll('.inc').forEach(btn => {
    btn.addEventListener('click', () => {
      const inp = document.getElementById(btn.dataset.target);
      if (!inp) return;
      inp.stepUp();
      updateSummary();
    });
  });
  qtyInputs.forEach(i=>i.addEventListener('input', updateSummary));
  if (pointsInput) pointsInput.addEventListener('input', updateSummary);
  if (referralToggle) referralToggle.addEventListener('change', updateSummary);

  function updateSummary() {
    let subtotal = 0;
    itemsList.innerHTML = '';
    qtyInputs.forEach(i => {
      const q = parseInt(i.value) || 0;
      const price = parseFloat(i.dataset.price);
      if (q > 0) {
        const row = document.createElement('div');
        row.className = 'flex justify-between';
        row.innerHTML = '<span>'+i.dataset.name+' × '+q+'</span><span>'+(price*q).toFixed(2)+' ₽</span>';
        itemsList.appendChild(row);
      }
      subtotal += q * price;
    });
    subtotalEl.textContent = subtotal.toFixed(2) + ' ₽';
    let total = subtotal;
    const referral = referralToggle && !referralToggleWrap.classList.contains('hidden') && referralToggle.checked;
    if (referral) {
      const d = subtotal * 0.1;
      refEl.textContent = '-' + d.toFixed(2);
      refRow.classList.remove('hidden');
      total -= d;
    } else {
      refRow.classList.add('hidden');
    }
    let points = parseFloat(pointsInput ? pointsInput.value : 0) || 0;
    const maxUse = Math.min(points, total);
    if (pointsInput) pointsInput.value = maxUse;
    pointsAmount.textContent = maxUse;
    total -= maxUse;

    let isPickup = false;
    if (!newBlock.classList.contains('hidden')) {
      const addr = document.querySelector('input[name="new_address"]').value.trim();
      isPickup = addr === '';
    } else if (addressSelect) {
      isPickup = addressSelect.value === 'pickup';
    }
    const shipping = isPickup ? 0 : 300;
    shippingEl.textContent = shipping.toFixed(2) + ' ₽';
    if (shipping > 0) {
      shippingRow.classList.remove('hidden');
    } else {
      shippingRow.classList.add('hidden');
    }
    total += shipping;
    totalEl.textContent = total.toFixed(2) + ' ₽';
  }

  function loadAddresses(uid) {
    fetch('<?= $base ?>/users/addresses?user_id='+uid)
      .then(r=>r.json()).then(list=>{
        addressSelect.innerHTML='';
        list.forEach(a=>{
          const opt=document.createElement('option');
          opt.value=a.id;
          opt.textContent=a.street;
          addressSelect.appendChild(opt);
        });
        const optNew=document.createElement('option');
        optNew.value='new';
        optNew.textContent='Добавить новый адрес';
        addressSelect.appendChild(optNew);
        const optPickup=document.createElement('option');
        optPickup.value='pickup';
        optPickup.textContent='Самовывоз 9 мая 73';
        addressSelect.appendChild(optPickup);
        addressWrapper.classList.remove('hidden');
      });
  }

  if (addressSelect) {
    addressSelect.addEventListener('change', () => {
      if (addressSelect.value === 'new') {
        addressNew.classList.remove('hidden');
      } else {
        addressNew.classList.add('hidden');
      }
    });
  }
</script>

<?php if (!empty($debugData)): ?>
  <div id="debugOverlay" style="
      position: fixed;
      bottom: 10px;
      right: 10px;
      width: 320px;
      max-height: 50vh;
      overflow-y: auto;
      background: rgba(0,0,0,0.8);
      color: #fff;
      font-family: monospace;
      font-size: 12px;
      line-height: 1.2;
      padding: 8px;
      border-radius: 6px;
      z-index: 9999;
      display: none;
  ">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
      <strong>DEBUG INFO</strong>
      <button id="debugToggle" style="
          background: transparent;
          border: none;
          color: #fff;
          font-weight: bold;
          cursor: pointer;
          font-size: 14px;
      ">×</button>
    </div>
    <pre id="debugContent" style="white-space: pre-wrap;">
<?= htmlspecialchars(json_encode($debugData, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)) ?>
    </pre>
  </div>
  <button id="debugOpenBtn" style="
      position: fixed;
      bottom: 10px;
      right: 10px;
      background: #e53e3e;
      color: #fff;
      border: none;
      border-radius: 4px;
      padding: 6px 10px;
      font-size: 12px;
      cursor: pointer;
      z-index: 9999;
  ">Открыть DEBUG</button>
  <script>
    const overlay = document.getElementById('debugOverlay');
    const content = document.getElementById('debugContent');
    const btnOpen = document.getElementById('debugOpenBtn');
    const btnClose = document.getElementById('debugToggle');
    btnOpen.addEventListener('click', () => {
      overlay.style.display = 'block';
      btnOpen.style.display = 'none';
    });
    btnClose.addEventListener('click', () => {
      overlay.style.display = 'none';
      btnOpen.style.display = 'block';
    });
  </script>
<?php endif; ?>
