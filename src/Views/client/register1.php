<?php /** @var string|null $error */ ?>

<main class="bg-gradient-to-br from-orange-50 via-white to-pink-50 flex flex-col items-center justify-center px-4 py-4 absolute top-20 overflow-hidden min-h-screen">

  <!-- Декоративные элементы -->
  <div class="absolute top-10 left-10 w-20 h-20 bg-gradient-to-br from-red-400/20 to-pink-500/20 rounded-full blur-xl"></div>
  <div class="absolute bottom-20 right-10 w-32 h-32 bg-gradient-to-br from-orange-400/20 to-red-500/20 rounded-full blur-xl"></div>
  <div class="absolute top-1/2 left-1/4 w-16 h-16 bg-gradient-to-br from-pink-400/20 to-rose-500/20 rounded-full blur-xl"></div>
  <div class="absolute top-1/3 right-1/3 w-24 h-24 bg-gradient-to-br from-yellow-400/20 to-orange-500/20 rounded-full blur-xl"></div>

  <!-- Основная карточка -->
  <div class="w-full max-w-md relative z-10">
    
    <!-- Заголовок с логотипом -->
    <div class="text-center mb-8">
      <h1 class="text-3xl font-bold bg-gradient-to-r from-red-500 to-pink-500 bg-clip-text text-transparent mb-2">
        Регистрация в BerryGo
      </h1>
      <p class="text-gray-500 text-sm">Создайте аккаунт для покупок</p>
    </div>

    <!-- Уведомление об ошибке -->
    <?php if (!empty($_GET['error'])): ?>
      <div class="bg-gradient-to-r from-red-50 to-pink-50 border-l-4 border-red-400 p-4 rounded-2xl mb-6 animate-shake">
        <div class="flex items-center">
          <span class="material-icons-round text-red-500 mr-3">error</span>
          <p class="text-red-700 font-medium"><?= htmlspecialchars($_GET['error']) ?></p>
        </div>
      </div>
    <?php endif; ?>

    <?php
      // Берём значение invite из GET-параметра (раньше был ref)
      $invite_from_get = trim($_GET['invite'] ?? '');
    ?>

    <!-- Форма регистрации -->
    <div class="bg-white rounded-3xl shadow-2xl p-8 backdrop-blur-sm">
      <form id="registerForm" action="/register" method="post" class="space-y-6">

        <!-- Поле имени -->
        <div class="space-y-2">
          <label for="name" class="flex items-center text-sm font-semibold text-gray-700">
            <span class="material-icons-round mr-2 text-red-500">person</span>
            Имя
          </label>
          <div class="relative">
            <input
              id="name"
              name="name"
              type="text"
              placeholder="Ваше имя"
              required
              class="w-full px-4 py-4 text-lg border-2 border-gray-100 rounded-2xl focus:border-red-500 focus:ring-2 focus:ring-red-100 outline-none transition-all text-gray-800 placeholder-gray-400"
            >
          </div>
        </div>

        <!-- Поле телефона -->
        <div class="space-y-2">
          <label for="phone" class="flex items-center text-sm font-semibold text-gray-700">
            <span class="material-icons-round mr-2 text-red-500">phone</span>
            Номер телефона
          </label>
          <div class="relative">
            <div class="absolute left-4 top-1/2 transform -translate-y-1/2 flex items-center">
              <span class="text-gray-700 font-semibold">+7</span>
            </div>
            <input
              id="phone"
              name="phone"
              type="tel"
              maxlength="10"
              inputmode="numeric"
              pattern="\d{10}"
              placeholder="902 923 7794"
              required
              class="w-full pl-16 pr-4 py-4 text-lg border-2 border-gray-100 rounded-2xl focus:border-red-500 focus:ring-2 focus:ring-red-100 outline-none transition-all text-gray-800 placeholder-gray-400"
            >
          </div>
        </div>

        <!-- Поле адреса -->
        <div class="space-y-2">
          <label for="address" class="flex items-center text-sm font-semibold text-gray-700">
            <span class="material-icons-round mr-2 text-red-500">home</span>
            Адрес доставки
          </label>
          <div class="relative">
            <input
              id="address"
              name="address"
              type="text"
              placeholder="Улица, дом, кв."
              required
              class="w-full px-4 py-4 text-lg border-2 border-gray-100 rounded-2xl focus:border-red-500 focus:ring-2 focus:ring-red-100 outline-none transition-all text-gray-800 placeholder-gray-400"
            >
          </div>
        </div>

        <!-- Invite-код (скрытым полем, если передан в GET) -->
        <?php if ($invite_from_get !== ''): ?>
          <input type="hidden" name="invite" value="<?= htmlspecialchars($invite_from_get) ?>">
        <?php endif; ?>

        <!-- Поле для ручного ввода invite-кода (продублируем, если пользователь хочет ввести сам) -->
        <div class="space-y-2">
          <label for="invite" class="flex items-center text-sm font-semibold text-gray-700">
            <span class="material-icons-round mr-2 text-red-500">card_giftcard</span>
            Код-приглашение (если есть)
          </label>
          <div class="relative">
            <input
              id="invite"
              name="invite"
              type="text"
              maxlength="20"
              value="<?= htmlspecialchars($invite_from_get) ?>"
              placeholder="Введите код вашего друга"
              <?= $invite_from_get !== '' ? 'readonly' : '' ?>
              class="w-full px-4 py-4 text-lg border-2 border-gray-100 rounded-2xl focus:border-red-500 focus:ring-2 focus:ring-red-100 outline-none transition-all text-gray-800 placeholder-gray-400 <?= $invite_from_get !== '' ? 'bg-gray-100 cursor-not-allowed' : '' ?>"
            >
          </div>
        </div>

        <!-- PIN-код -->
        <div class="space-y-2">
          <label class="flex items-center text-sm font-semibold text-gray-700">
            <span class="material-icons-round mr-2 text-red-500">lock</span>
            PIN-код
          </label>
          <div id="pinContainer" class="flex justify-center space-x-3">
            <?php for ($i = 0; $i < 4; $i++): ?>
              <input
                type="tel"
                inputmode="numeric"
                maxlength="1"
                class="w-14 h-14 text-center text-2xl font-bold border-2 border-gray-200 rounded-2xl focus:border-red-500 focus:ring-2 focus:ring-red-100 outline-none transition-all text-gray-800 bg-gradient-to-br from-gray-50 to-white hover:from-white hover:to-gray-50"
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
          class="w-full bg-gradient-to-r from-red-500 to-pink-500 text-white font-semibold py-4 rounded-2xl hover:shadow-lg hover:scale-[1.02] transition-all text-lg flex items-center justify-center space-x-3 relative overflow-hidden group"
        >
          <span class="absolute inset-0 bg-gradient-to-r from-pink-500 to-red-500 opacity-0 group-hover:opacity-100 transition-opacity"></span>
          <span class="material-icons-round relative z-10">person_add</span>
          <span class="relative z-10">Зарегистрироваться</span>
        </button>

      </form>
    </div>

    <!-- Вход -->
    <div class="text-center mt-6">
      <p class="text-gray-600 mb-4">
        Уже есть аккаунт?
      </p>
      <a href="/login" 
         class="inline-flex items-center px-6 py-3 bg-white border-2 border-gray-200 rounded-2xl font-medium text-gray-700 hover:border-red-200 hover:text-red-500 transition-all shadow-lg hover:shadow-xl space-x-2">
        <span class="material-icons-round">login</span>
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
  
  /* Эффект фокуса для PIN-кода */
  input[data-pin-input]:focus {
    transform: scale(1.05);
    border-color: #ef4444;
  }
  
  /* Анимация для полей ввода */
  input:focus {
    transform: translateY(-1px);
    box-shadow: 0 8px 25px -5px rgba(239, 68, 68, 0.1);
  }
</style>

<script>
// Маска для телефона: только цифры
document.getElementById('phone').addEventListener('input', function() {
  this.value = this.value.replace(/\D/g, '').slice(0, 10);
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
