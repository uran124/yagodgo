<?php /** @var string|null $error */ ?>

<main class="bg-gradient-to-br from-orange-50 via-white to-pink-50 flex items-center justify-center px-4 py-4 sm:py-8 min-h-screen absolute top-0 left-0 right-0 bottom-0 overflow-auto">

  <!-- Декоративные элементы -->
  <div class="absolute top-10 left-10 w-16 h-16 md:w-20 md:h-20 bg-gradient-to-br from-red-400/20 to-pink-500/20 rounded-full blur-xl"></div>
  <div class="absolute bottom-20 right-10 w-24 h-24 md:w-32 md:h-32 bg-gradient-to-br from-orange-400/20 to-red-500/20 rounded-full blur-xl"></div>
  <div class="absolute top-1/2 left-1/4 w-12 h-12 md:w-16 md:h-16 bg-gradient-to-br from-pink-400/20 to-rose-500/20 rounded-full blur-xl"></div>
  <div class="absolute top-1/3 right-1/3 w-20 h-20 md:w-24 md:h-24 bg-gradient-to-br from-yellow-400/20 to-orange-500/20 rounded-full blur-xl"></div>

  <!-- Основная карточка -->
  <div class="w-full max-w-sm md:max-w-md relative z-10 my-auto">
    
    <!-- Заголовок с логотипом -->
    <div class="text-center mb-3 sm:mb-6">
      <h1 class="text-xl sm:text-2xl md:text-3xl font-bold bg-gradient-to-r from-red-500 to-pink-500 bg-clip-text text-transparent mb-1 sm:mb-2">
        Регистрация в BerryGo
      </h1>

    </div>

    <!-- Уведомление об ошибке -->
    <?php if (!empty($_GET['error'])): ?>
      <div class="bg-gradient-to-r from-red-50 to-pink-50 border-l-4 border-red-400 p-3 sm:p-4 rounded-2xl mb-3 sm:mb-6 animate-shake">
        <div class="flex items-center">
          <span class="material-icons-round text-red-500 mr-2 sm:mr-3 text-lg sm:text-xl">error</span>
          <p class="text-red-700 font-medium text-sm sm:text-base"><?= htmlspecialchars($_GET['error']) ?></p>
        </div>
      </div>
    <?php endif; ?>

    <?php
      // Берём значение invite из GET-параметра (раньше был ref)
      $invite_from_get = trim($_GET['invite'] ?? '');
    ?>

    <!-- Форма регистрации -->
    <div class="bg-white rounded-3xl shadow-2xl p-4 sm:p-6 md:p-8 backdrop-blur-sm">
      <form id="registerForm" action="/register" method="post" class="space-y-3 sm:space-y-4">

        <!-- Блок проверки телефона -->
        <div id="phoneBlock" class="space-y-2">
          <div class="relative">
            <div class="absolute left-3 sm:left-4 top-1/2 transform -translate-y-1/2 flex items-center">
              <span class="material-icons-round text-red-500 mr-1 sm:mr-2 text-lg sm:text-xl">phone</span>
              <span class="text-gray-700 font-semibold text-sm sm:text-base">+7</span>
            </div>
            <input id="phone" name="phone" type="tel" maxlength="10" inputmode="numeric" pattern="\d{10}" placeholder="902 923 7794" required class="w-full pl-16 sm:pl-20 pr-3 sm:pr-4 py-2.5 sm:py-3 md:py-4 text-sm sm:text-base md:text-lg border-2 border-gray-100 rounded-2xl focus:border-red-500 focus:ring-2 focus:ring-red-100 outline-none transition-all text-gray-800 placeholder-gray-400">
          </div>
          <button type="button" id="sendRegCode" class="w-full bg-red-500 text-white py-2 rounded-2xl">Подтвердить</button>
          <div id="codeRegBlock" class="hidden space-y-2">
            <div class="flex space-x-1.5 sm:space-x-2 justify-center">
              <?php for ($i=0;$i<4;$i++): ?>
                <input type="tel" maxlength="1" inputmode="numeric" data-reg-code
                  class="w-10 h-10 sm:w-12 sm:h-12 md:w-14 md:h-14 text-center text-lg sm:text-xl md:text-2xl font-bold border-2 border-gray-200 rounded-2xl focus:border-red-500 focus:ring-2 focus:ring-red-100 outline-none transition-all text-gray-800 bg-gradient-to-br from-gray-50 to-white hover:from-white hover:to-gray-50" />
              <?php endfor; ?>
            </div>
          </div>
        </div>

        <fieldset id="extraFields" disabled class="opacity-50 space-y-3 sm:space-y-4">
        <!-- Поле имени -->
        <div class="relative">
          <div class="absolute left-3 sm:left-4 top-1/2 transform -translate-y-1/2 flex items-center">
            <span class="material-icons-round text-red-500 mr-1 sm:mr-2 text-lg sm:text-xl">person</span>
          </div>
          <input
            id="name"
            name="name"
            type="text"
            placeholder="Ваше имя"
            required
            class="w-full pl-12 sm:pl-16 pr-3 sm:pr-4 py-2.5 sm:py-3 md:py-4 text-sm sm:text-base md:text-lg border-2 border-gray-100 rounded-2xl focus:border-red-500 focus:ring-2 focus:ring-red-100 outline-none transition-all text-gray-800 placeholder-gray-400"
          >
        </div>
        <!-- Поле адреса -->
        <div class="relative">
          <div class="absolute left-3 sm:left-4 top-1/2 transform -translate-y-1/2 flex items-center">
            <span class="material-icons-round text-red-500 mr-1 sm:mr-2 text-lg sm:text-xl">home</span>
          </div>
          <input
            id="address"
            name="address"
            type="text"
            placeholder="Улица, дом, кв."
            required
            class="w-full pl-12 sm:pl-16 pr-3 sm:pr-4 py-2.5 sm:py-3 md:py-4 text-sm sm:text-base md:text-lg border-2 border-gray-100 rounded-2xl focus:border-red-500 focus:ring-2 focus:ring-red-100 outline-none transition-all text-gray-800 placeholder-gray-400"
          >
        </div>

        <!-- Invite-код (скрытым полем, если передан в GET) -->
        <?php if ($invite_from_get !== ''): ?>
          <input type="hidden" name="invite" value="<?= htmlspecialchars($invite_from_get) ?>">
        <?php endif; ?>

        <!-- Поле для ручного ввода invite-кода -->
        <div class="relative">
          <div class="absolute left-3 sm:left-4 top-1/2 transform -translate-y-1/2 flex items-center">
            <span class="material-icons-round text-red-500 mr-1 sm:mr-2 text-lg sm:text-xl">card_giftcard</span>
          </div>
          <input
            id="invite"
            name="invite"
            type="text"
            maxlength="20"
            value="<?= htmlspecialchars($invite_from_get) ?>"
            placeholder="Код-приглашение"
            <?= $invite_from_get !== '' ? 'readonly' : '' ?>
            class="w-full pl-12 sm:pl-16 pr-3 sm:pr-4 py-2.5 sm:py-3 md:py-4 text-sm sm:text-base md:text-lg border-2 border-gray-100 rounded-2xl focus:border-red-500 focus:ring-2 focus:ring-red-100 outline-none transition-all text-gray-800 placeholder-gray-400 <?= $invite_from_get !== '' ? 'bg-gray-100 cursor-not-allowed' : '' ?>"
          >
        </div>

        <!-- PIN-код -->
        <div class="space-y-2 sm:space-y-3">
          <div class="flex items-center justify-center text-xs sm:text-sm font-semibold text-gray-700">
            <span class="material-icons-round mr-1 sm:mr-2 text-red-500 text-lg sm:text-xl">lock</span>
            <span>Придумайте PIN-код</span>
          </div>
          <div id="pinContainer" class="flex justify-center space-x-1.5 sm:space-x-2">
            <?php for ($i = 0; $i < 4; $i++): ?>
              <input
                type="tel"
                inputmode="numeric"
                maxlength="1"
                class="w-10 h-10 sm:w-12 sm:h-12 md:w-14 md:h-14 text-center text-lg sm:text-xl md:text-2xl font-bold border-2 border-gray-200 rounded-2xl focus:border-red-500 focus:ring-2 focus:ring-red-100 outline-none transition-all text-gray-800 bg-gradient-to-br from-gray-50 to-white hover:from-white hover:to-gray-50"
                data-pin-input
                required
              >
            <?php endfor; ?>
          </div>
          <input type="hidden" name="pin" id="pinHidden">
        </div>

        <!-- Кнопка регистрации -->
        <button
          type="submit"
          class="w-full bg-gradient-to-r from-red-500 to-pink-500 text-white font-semibold py-2.5 sm:py-3 md:py-4 rounded-2xl hover:shadow-lg hover:scale-[1.02] transition-all text-sm sm:text-base md:text-lg flex items-center justify-center space-x-2 sm:space-x-3 relative overflow-hidden group mt-4 sm:mt-6"
        >
          <span class="absolute inset-0 bg-gradient-to-r from-pink-500 to-red-500 opacity-0 group-hover:opacity-100 transition-opacity"></span>
          <span class="material-icons-round relative z-10 text-lg sm:text-xl">person_add</span>
          <span class="relative z-10">Зарегистрироваться</span>
        </button>

      </fieldset>
      </form>
    </div>

    <!-- Вход -->
    <div class="text-center mt-3 sm:mt-6">

      <a href="/login" 
         class="inline-flex items-center px-4 sm:px-6 py-2 sm:py-3 bg-white border-2 border-gray-200 rounded-2xl font-medium text-gray-700 hover:border-red-200 hover:text-red-500 transition-all shadow-lg hover:shadow-xl space-x-1 sm:space-x-2 text-xs sm:text-sm">
        <span class="material-icons-round text-lg sm:text-xl">login</span>
        <span>Войти в аккаунт</span>
      </a>
    </div>

  </div>

</main>

<style>
  @keyframes float {
    0%, 100% { transform: translateY(0px); }
    50% { transform: translateY(-5px); }
  }
  
  @keyframes shake {
    0%, 100% { transform: translateX(0); }
    25% { transform: translateX(-5px); }
    75% { transform: translateX(5px); }
  }
  
  .floating-animation {
    animation: float 3s ease-in-out infinite;
  }
  
  .animate-shake {
    animation: shake 0.5s ease-in-out;
  }
  
  /* Эффект фокуса для PIN-кода и кода из SMS */
  input[data-pin-input]:focus,
  input[data-reg-code]:focus {
    transform: scale(1.05);
    border-color: #ef4444;
  }
  
  /* Анимация для полей ввода */
  input:focus {
    transform: translateY(-1px);
    box-shadow: 0 8px 25px -5px rgba(239, 68, 68, 0.1);
  }

  /* Адаптивность для очень маленьких экранов */
  @media (max-width: 360px) {
    .w-10 { width: 2.25rem; }
    .h-10 { height: 2.25rem; }
  }
  
  /* Дополнительная оптимизация для мобильных */
  @media (max-width: 640px) {
    .min-h-screen {
      min-height: 100vh;
      min-height: 100dvh; /* Для новых браузеров */
    }
  }
</style>

<script>
// Маска для телефона: только цифры
const phoneInput = document.getElementById('phone');
phoneInput.addEventListener('input', function() {
  this.value = this.value.replace(/\D/g, '').slice(0, 10);
});

const sendRegBtn = document.getElementById('sendRegCode');
const codeRegBlock = document.getElementById('codeRegBlock');
const regCodeInputs = document.querySelectorAll('input[data-reg-code]');
const extraFields = document.getElementById('extraFields');

sendRegBtn.addEventListener('click', () => {
  const phone = phoneInput.value.replace(/\D/g, '');
  if (phone.length !== 10) { alert('Введите номер'); return; }
  fetch('/api/send-reg-code', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:'phone='+phone})
    .then(r => r.json())
    .then(d => {
      if (d.blocked) {
        alert('Ваш номер заблокирован. Если вы считаете блокировку ошибочной, свяжитесь со службой поддержки');
        const btn = document.createElement('a');
        btn.href = 'https://wa.me/79029237794';
        btn.textContent = 'Написать в WhatsApp';
        btn.className = 'mt-2 inline-block bg-green-500 text-white px-4 py-2 rounded-2xl';
        codeRegBlock.innerHTML = '';
        codeRegBlock.classList.remove('hidden');
        codeRegBlock.appendChild(btn);
        return;
      }
      if (d.exists) {
        alert('Вы уже зарегистрированы. Через 10 секунд произойдёт переход на страницу входа');
        setTimeout(() => { window.location.href = '/login'; }, 10000);
        return;
      }
      if (d.success) {
        codeRegBlock.classList.remove('hidden');
        regCodeInputs[0].focus();
      }
    });
});

function verifyRegCode() {
  const code = Array.from(regCodeInputs).map(i => i.value).join('');
  if (code.length !== 4) return;
  const phone = phoneInput.value.replace(/\D/g, '');
  fetch('/api/verify-reg-code', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:'phone='+phone+'&code='+code})
    .then(r => r.json()).then(d => {
      if (d.success) {
        extraFields.disabled = false;
        extraFields.classList.remove('opacity-50');
        document.getElementById('phoneBlock').classList.add('hidden');
      } else {
        alert('Неверный код');
        regCodeInputs.forEach(i => i.value = '');
        regCodeInputs[0].focus();
      }
    });
}

regCodeInputs.forEach((input, idx) => {
  input.addEventListener('input', () => {
    input.value = input.value.replace(/\D/, '').slice(0, 1);
    if (input.value && idx < regCodeInputs.length - 1) {
      regCodeInputs[idx + 1].focus();
    }
    verifyRegCode();
  });

  input.addEventListener('keydown', (e) => {
    if (e.key === 'Backspace' && !input.value && idx > 0) {
      regCodeInputs[idx - 1].focus();
    }
  });
});

// PIN-код: автопереход и сбор в скрытое поле
const pinInputs = document.querySelectorAll('input[data-pin-input]');
const pinHidden = document.getElementById('pinHidden');

pinInputs.forEach((input, idx) => {
  input.addEventListener('input', () => {
    input.value = input.value.replace(/\D/, '').slice(0, 1);
    if (input.value && idx < pinInputs.length - 1) {
      pinInputs[idx + 1].focus();
    }
    updateHiddenPin();
  });

  input.addEventListener('keydown', (e) => {
    if (e.key === 'Backspace') {
      if (!input.value && idx > 0) {
        pinInputs[idx - 1].focus();
        pinInputs[idx - 1].value = '';
        updateHiddenPin();
      } else if (input.value) {
        input.value = '';
        updateHiddenPin();
      }
    }
  });

  input.addEventListener('focus', () => {
    input.style.transform = 'scale(1.05)';
    input.style.borderColor = '#ef4444';
  });
  
  input.addEventListener('blur', () => {
    input.style.transform = 'scale(1)';
    if (!input.value) {
      input.style.borderColor = '#e5e7eb';
    }
  });
});

function updateHiddenPin() {
  const pin = Array.from(pinInputs).map(i => i.value).join('');
  pinHidden.value = pin;
  
  if (pin.length === 4) {
    pinInputs.forEach(input => {
      input.style.backgroundColor = '#f0fdf4';
      input.style.borderColor = '#22c55e';
    });
  } else {
    pinInputs.forEach(input => {
      input.style.backgroundColor = '';
      input.style.borderColor = input === document.activeElement ? '#ef4444' : '#e5e7eb';
    });
  }
}

// Валидация формы при отправке
document.getElementById('registerForm').addEventListener('submit', function(e) {
  const name = document.getElementById('name').value.trim();
  const phone = document.getElementById('phone').value.replace(/\D/g, '');
  const address = document.getElementById('address').value.trim();
  const pin = pinHidden.value;
  
  if (name.length < 2) {
    e.preventDefault();
    alert('Пожалуйста, введите корректное имя (минимум 2 символа)');
    document.getElementById('name').focus();
    return;
  }
  
  if (phone.length !== 10) {
    e.preventDefault();
    alert('Пожалуйста, введите корректный номер телефона (10 цифр)');
    document.getElementById('phone').focus();
    return;
  }
  
  if (address.length < 5) {
    e.preventDefault();
    alert('Пожалуйста, введите корректный адрес доставки');
    document.getElementById('address').focus();
    return;
  }
  
  if (pin.length !== 4) {
    e.preventDefault();
    alert('Пожалуйста, введите 4-значный PIN-код');
    pinInputs[0].focus();
    return;
  }
});
</script>