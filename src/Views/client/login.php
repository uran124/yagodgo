<?php /** @var string|null $error */ ?>

<main class="bg-gradient-to-br from-orange-50 via-white to-pink-50 flex flex-col items-center justify-center px-4 py-4 fixed inset-0 overflow-auto">

  <!-- Декоративные элементы -->
  <div class="absolute top-10 left-10 w-20 h-20 bg-gradient-to-br from-red-400/20 to-pink-500/20 rounded-full blur-xl"></div>
  <div class="absolute bottom-20 right-10 w-32 h-32 bg-gradient-to-br from-orange-400/20 to-red-500/20 rounded-full blur-xl"></div>
  <div class="absolute top-1/2 left-1/4 w-16 h-16 bg-gradient-to-br from-pink-400/20 to-rose-500/20 rounded-full blur-xl"></div>

  <!-- Основная карточка -->
  <div class="w-full max-w-md relative z-10">
    
    <!-- Заголовок с логотипом -->
    <div class="text-center mb-8">
      <h1 class="text-3xl font-bold bg-gradient-to-r from-red-500 to-pink-500 bg-clip-text text-transparent mb-2">
        Вход в BerryGo
      </h1>
      <p class="text-gray-500 text-sm">Войдите, чтобы делать покупки</p>
    </div>

    <!-- Уведомление об ошибке -->
    <?php if (!empty($error)): ?>
      <div class="bg-gradient-to-r from-red-50 to-pink-50 border-l-4 border-red-400 p-4 rounded-2xl mb-6 animate-shake">
        <div class="flex items-center">
          <span class="material-icons-round text-red-500 mr-3">error</span>
          <p class="text-red-700 font-medium"><?= htmlspecialchars($error) ?></p>
        </div>
      </div>
    <?php endif; ?>

    <!-- Форма входа -->
    <div class="bg-white rounded-3xl shadow-2xl p-8 backdrop-blur-sm">
      <form id="loginForm" action="/login" method="post" class="space-y-6">

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
          <!-- скрытое поле для полного PIN -->
          <input type="hidden" name="pin" id="pinHidden">
        </div>

      </form>
    </div>

    <!-- Регистрация -->
    <div class="text-center mt-6">

      <a href="/register"
         class="inline-flex items-center px-6 py-3 bg-white border-2 border-gray-200 rounded-2xl font-medium text-gray-700 hover:border-red-200 hover:text-red-500 transition-all shadow-lg hover:shadow-xl space-x-2">
        <span class="material-icons-round">person_add</span>
        <span>Зарегистрироваться</span>
      </a>
      <div class="mt-3">
        <a href="/reset-pin" class="text-sm text-red-500 hover:underline">Забыли PIN-код?</a>
      </div>
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
  }
</style>

<script>
// Маска для телефона: только цифры, автоматический фокус
document.getElementById('phone').addEventListener('input', function(e) {
  // Убираем все не-цифры и ограничиваем до 10 символов
  this.value = this.value.replace(/\D/g, '').slice(0, 10);
});

// PIN-код: автопереход между полями и сбор в скрытое поле
const pinInputs = document.querySelectorAll('input[data-pin-input]');
const pinHidden = document.getElementById('pinHidden');
const loginForm = document.getElementById('loginForm');

pinInputs.forEach((input, idx) => {
  // Ввод цифры
  input.addEventListener('input', () => {
    // Только цифры, максимум 1 символ
    input.value = input.value.replace(/\D/, '').slice(0, 1);
    
    // Автопереход к следующему полю
    if (input.value && idx < pinInputs.length - 1) {
      pinInputs[idx + 1].focus();
    }
    
    // Обновляем скрытое поле
    updateHiddenPin();
    
    // Проверяем, заполнены ли все поля PIN-кода
    checkPinComplete();
  });
  
  // Обработка Backspace
  input.addEventListener('keydown', (e) => {
    if (e.key === 'Backspace') {
      if (!input.value && idx > 0) {
        // Переходим к предыдущему полю, если текущее пустое
        pinInputs[idx - 1].focus();
        pinInputs[idx - 1].value = '';
        updateHiddenPin();
      } else if (input.value) {
        // Очищаем текущее поле
        input.value = '';
        updateHiddenPin();
      }
    }
  });
  
  // Анимация при фокусе
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

// Обновление скрытого поля PIN
function updateHiddenPin() {
  const pin = Array.from(pinInputs).map(i => i.value).join('');
  pinHidden.value = pin;
  
  // Визуальная обратная связь при полном заполнении
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

// Проверка завершенности ввода PIN и автоматический сабмит
function checkPinComplete() {
  const pin = pinHidden.value;
  const phone = document.getElementById('phone').value.replace(/\D/g, '');
  
  if (pin.length === 4) {
    // Проверяем корректность номера телефона
    if (phone.length !== 10) {
      alert('Пожалуйста, введите корректный номер телефона (10 цифр)');
      document.getElementById('phone').focus();
      return;
    }
    
    // Автоматический сабмит формы через небольшую задержку для лучшего UX
    setTimeout(() => {
      loginForm.submit();
    }, 300);
  }
}

// Валидация формы при отправке (дополнительная проверка)
loginForm.addEventListener('submit', function(e) {
  const phone = document.getElementById('phone').value.replace(/\D/g, '');
  const pin = pinHidden.value;
  
  if (phone.length !== 10) {
    e.preventDefault();
    alert('Пожалуйста, введите корректный номер телефона (10 цифр)');
    document.getElementById('phone').focus();
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