<?php
  $points = (int)($_SESSION['points_balance'] ?? 0);

  /** Метаданные страницы */
  global $pdo;
  $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/';
  $slugMap = [
    '/'          => 'home',
    '/catalog'   => 'catalog',
    '/about'     => 'about_app',
    '/about_app' => 'about_app',
    '/contacts'  => 'contacts',
  ];
  $pageSlug = $slugMap[$path] ?? trim($path, '/');

  $meta = [
    'title'       => 'BerryGo',
    'description' => '',
    'keywords'    => '',
    'h1'          => '',
    'text'        => '',
  ];

  if (isset($pdo)) {
      $stmt = $pdo->prepare('SELECT title, description, keywords, h1, text FROM metadata WHERE page = ? LIMIT 1');
      if ($stmt->execute([$pageSlug])) {
          $row = $stmt->fetch(PDO::FETCH_ASSOC);
          if ($row) {
              $meta = $row;
          }
      }
  }
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title><?= htmlspecialchars($meta['title'] ?? 'BerryGo') ?></title>
  <?php if (!empty($meta['description'])): ?>
    <meta name="description" content="<?= htmlspecialchars($meta['description']) ?>">
  <?php endif; ?>
  <?php if (!empty($meta['keywords'])): ?>
    <meta name="keywords" content="<?= htmlspecialchars($meta['keywords']) ?>">
  <?php endif; ?>
  
  <link rel="icon" href="/assets/images/favicon.svg" type="image/svg+xml">
  <link rel="apple-touch-icon" href="/assets/images/favicon.svg">

  <!-- Google Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  
  <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
  <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
  
  <link rel="manifest" href="/manifest.json">
  <meta name="theme-color" content="#FF6B6B">
  <meta name="mobile-web-app-capable" content="yes">
  
  <style>
    :root {
      --berry-red: #FF6B6B;
      --berry-pink: #FF8E8E;
      --fresh-green: #4ECDC4;
      --leaf-green: #45B7AA;
      --cream: #FFF8F5;
      --soft-gray: #F8FAFC;
      --text-dark: #1A202C;
      --text-gray: #4A5568;
    }
    
    * {
      font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
    }
    
    body {
      background: linear-gradient(135deg, var(--cream) 0%, #FFF 100%);
    }
    
    .berry-gradient {
      background: linear-gradient(135deg, var(--berry-red) 0%, var(--berry-pink) 100%);
    }
    
    .fresh-gradient {
      background: linear-gradient(135deg, var(--fresh-green) 0%, var(--leaf-green) 100%);
    }
    
    .glass-effect {
      background: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(20px);
      border: 1px solid rgba(255, 255, 255, 0.2);
    }
    
    .card-shadow {
      box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
    }
    
    .berry-shadow {
      box-shadow: 0 20px 40px -10px rgba(255, 107, 107, 0.3);
    }
    
    .floating-animation {
      animation: float 3s ease-in-out infinite;
    }
    
    @keyframes float {
      0%, 100% { transform: translateY(0px); }
      50% { transform: translateY(-5px); }
    }
    
    .nav-item {
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }
    
    .nav-item:hover {
      transform: translateY(-2px);
    }
    
    .install-pulse {
      animation: pulse 2s infinite;
    }
    
    @keyframes pulse {
      0%, 100% { transform: scale(1); }
      50% { transform: scale(1.05); }
    }

    .no-scrollbar::-webkit-scrollbar {
      display: none;
    }
    .no-scrollbar {
      -ms-overflow-style: none;
      scrollbar-width: none;
    }

  </style>

  
  <!-- <script>
    if ('serviceWorker' in navigator) {
      navigator.serviceWorker.register('/service-worker.js');
    }
  </script>
  -->
</head>
<body class="bg-gradient-to-br from-orange-50 via-white to-pink-50 text-gray-800 min-h-screen">

  <!-- Header -->
<header class="fixed top-0 left-0 right-0 glass-effect flex items-center justify-between p-4 z-20 border-b border-white/20">
  <a href="/" id="logoLink" class="flex items-center space-x-3">
    <div class="w-10 h-10 berry-gradient rounded-2xl flex items-center justify-center floating-animation">
      <img src="/assets/berrygo_strawberry.svg" alt="BerryGo" class="w-6 h-6 filter brightness-0 invert">
    </div>
    <span id="logoText" class="font-bold text-xl bg-gradient-to-r from-red-500 to-pink-500 bg-clip-text text-transparent" style="display:none;">BerryGo</span>
  </a>
  <button id="installLogoBtn" class="install-pulse flex flex-col items-center ml-2" style="display:none;">
    <span id="installLogoBtnText" class="font-bold text-xs text-red-600 text-center leading-tight transition-opacity duration-500">BerryGo</span>
    <div class="flex space-x-1 mt-1">
      <img src="/assets/googleplay.svg" alt="Google Play" class="w-4 h-4">
      <img src="/assets/apple.svg" alt="App Store" class="w-4 h-4">
    </div>
  </button>

  <div class="flex items-center space-x-3">
    <?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
      <button id="adminToggle" class="material-icons-round text-2xl text-gray-600 hover:text-emerald-500 transition-colors p-2 hover:bg-emerald-50 rounded-xl">settings</button>
    <?php endif; ?>

    <?php if (!empty($_SESSION['user_id'])): ?>
      <!-- Авторизованный пользователь -->
      <?php $points = (int)($_SESSION['points_balance'] ?? 0); ?>
      <button
        id="openPointsPopup"
        class=" shadow-lg ml-2 flex items-center space-x-1 bg-white/20 backdrop-blur-sm rounded-full px-3 py-1 hover:bg-red-100 transition-colors hover:shadow-xl transition-shadow duration-200"
        title="Ваш баланс клубничек"
      >
        <span class="text-xl">🍓</span>
        <span class="font-medium text-gray-800"><?= $points ?></span>
      </button>

      <form action="/logout" method="post">
        <button type="submit"
                class="material-icons-round text-2xl text-gray-600 hover:text-red-500 transition-colors p-2 hover:bg-red-50 rounded-xl"
                title="Выйти">
          logout
        </button>
      </form>
    <?php else: ?>
      <!-- Гость -->
      <a href="/login"
         class="ml-2 flex items-center space-x-1 bg-white/20 backdrop-blur-sm rounded-full px-3 py-1 hover:bg-white/30 transition-colors"
         title="Войти, чтобы собирать клубнички"
      >
        <span class="text-xl">🍓</span>
        <span class="font-medium text-gray-800">0</span>
      </a>
    <?php endif; ?>
  </div>
</header>


  <!-- Админ-sidebar (off-canvas) -->
  <?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
  <aside
    id="adminSidebar"
    class="fixed top-0 right-0 h-full w-80 glass-effect shadow-2xl transform translate-x-full transition-transform duration-300 z-30 border-l border-white/20"
  >
    <div class="p-6 font-bold text-xl border-b border-gray-100 fresh-gradient text-white rounded-tr-xl">
      <span class="material-icons-round mr-2">admin_panel_settings</span>
      Админ-панель
    </div>
    <nav class="p-4 space-y-1">
      <a href="/admin/dashboard" class="flex items-center p-4 hover:bg-gradient-to-r hover:from-emerald-50 hover:to-teal-50 rounded-2xl transition-all group">
        <span class="material-icons-round mr-3 text-emerald-500 group-hover:scale-110 transition-transform">dashboard</span> 
        <span class="font-medium">Dashboard</span>
      </a>
      <a href="/admin/orders" class="flex items-center p-4 hover:bg-gradient-to-r hover:from-blue-50 hover:to-indigo-50 rounded-2xl transition-all group">
        <span class="material-icons-round mr-3 text-blue-500 group-hover:scale-110 transition-transform">receipt_long</span> 
        <span class="font-medium">Заказы</span>
      </a>
      <a href="/admin/products" class="flex items-center p-4 hover:bg-gradient-to-r hover:from-purple-50 hover:to-pink-50 rounded-2xl transition-all group">
        <span class="material-icons-round mr-3 text-purple-500 group-hover:scale-110 transition-transform">inventory_2</span> 
        <span class="font-medium">Товары</span>
      </a>
      <a href="/admin/slots" class="flex items-center p-4 hover:bg-gradient-to-r hover:from-orange-50 hover:to-red-50 rounded-2xl transition-all group">
        <span class="material-icons-round mr-3 text-orange-500 group-hover:scale-110 transition-transform">calendar_today</span> 
        <span class="font-medium">Слоты</span>
      </a>
      <a href="/admin/coupons" class="flex items-center p-4 hover:bg-gradient-to-r hover:from-yellow-50 hover:to-orange-50 rounded-2xl transition-all group">
        <span class="material-icons-round mr-3 text-yellow-500 group-hover:scale-110 transition-transform">local_offer</span> 
        <span class="font-medium">Промокоды</span>
      </a>
      <a href="/admin/users" class="flex items-center p-4 hover:bg-gradient-to-r hover:from-teal-50 hover:to-cyan-50 rounded-2xl transition-all group">
        <span class="material-icons-round mr-3 text-teal-500 group-hover:scale-110 transition-transform">people</span> 
        <span class="font-medium">Пользователи</span>
      </a>
    </nav>
  </aside>
  <?php endif; ?>
  
  <!-- Install PWA Banner (replaced by logo button) -->

  <!-- Контент -->
  <div class="pt-16">
    <?= $content ?>
    <?php if (!empty($meta['text'])): ?>
      <div class="hidden lg:block max-w-screen-lg mx-auto px-4 pb-24 text-gray-700 text-sm">
        <?php if (!empty($meta['h1'])): ?>
          <h1 class="text-2xl font-bold mb-4"><?= htmlspecialchars($meta['h1']) ?></h1>
        <?php endif; ?>
        <p><?= nl2br(htmlspecialchars($meta['text'])) ?></p>
      </div>
    <?php endif; ?>
  </div>

  <!-- Bottom Navigation -->
  <?php
  $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
  $role = $_SESSION['role'] ?? 'guest';
  $cartTotal = $_SESSION['cart_total'] ?? 0;

  function isActive(string $path) {
      return ($_SERVER['REQUEST_URI'] === $path) ? 'text-red-500 bg-red-50' : 'text-gray-500';
  }
  ?>
  
  <nav class="fixed bottom-0 left-0 w-full glass-effect border-t border-white/20 shadow-2xl z-20">
    <ul class="flex justify-between px-2 py-2">
      <!-- Главная -->
      <li class="flex-1 mr-1">
        <a href="/" class="nav-item flex flex-col items-center py-3 px-2 rounded-2xl transition-all <?= isActive('/') ?>">
          <span class="material-icons-round text-xl mb-1">home</span>
          <span class="text-xs font-medium">Главная</span>
        </a>
      </li>
      
      <!-- Каталог -->
      <li class="flex-1 mx-1">
        <a href="/catalog" class="nav-item flex flex-col items-center py-3 px-2 rounded-2xl transition-all <?= isActive('/catalog') ?>">
          <span class="material-icons-round text-xl mb-1">grid_view</span>
          <span class="text-xs font-medium">Каталог</span>
        </a>
      </li>
      
      <!-- Корзина -->
      <?php
        $cartClass = ($cartTotal > 0 || $uri === '/cart')
                     ? 'text-red-500 bg-red-50' : 'text-gray-500';
      ?>
      <li class="flex-1 mx-1">
        <a href="/cart" class="nav-item flex flex-col items-center py-3 px-2 rounded-2xl transition-all <?= $cartClass ?> relative">
          <?php if ($cartTotal > 0): ?>
            <div class="absolute -top-1 -right-5 px-1 h-5 berry-gradient rounded-sm flex items-center justify-center">
              <span class="text-xs font text-white"><?= $cartTotal ?>₽</span>
            </div>
          <?php endif; ?>
          <span class="material-icons-round text-xl mb-1">shopping_cart</span>
          <span class="text-xs font-medium">Корзина</span>
        </a>
      </li>
      
      <!-- Мои заказы -->
      <?php if ($role === 'client'): ?>
        <li class="flex-1 mx-1">
          <a href="/orders" class="nav-item flex flex-col items-center py-3 px-2 rounded-2xl transition-all <?= isActive('/orders') ?>">
            <span class="material-icons-round text-xl mb-1">receipt_long</span>
            <span class="text-xs font-medium">Заказы</span>
          </a>
        </li>
      <?php else: ?>
        <li class="flex-1 ml-1">
          <div class="nav-item flex flex-col items-center py-3 px-2 rounded-2xl text-gray-400">
            <span class="material-icons-round text-xl mb-1">receipt_long</span>
            <span class="text-xs font-medium">Заказы</span>
          </div>
        </li>
      <?php endif; ?>

      <!-- Профиль -->
      <?php if ($role === 'client'): ?>
        <li class="flex-1">
          <a href="/profile" class="nav-item flex flex-col items-center py-3 px-2 rounded-2xl transition-all <?= isActive('/profile') ?>">
            <span class="material-icons-round text-xl mb-1">person</span>
            <span class="text-xs font-medium">Профиль</span>
          </a>
        </li>
      <?php else: ?>
        <li class="flex-1">
          <a href="/login" class="nav-item flex flex-col items-center py-3 px-2 rounded-2xl transition-all text-gray-500 hover:text-emerald-500 hover:bg-emerald-50">
            <span class="material-icons-round text-xl mb-1">person</span>
            <span class="text-xs font-medium">Войти</span>
          </a>
        </li>
      <?php endif; ?>
    </ul>
  </nav>

  <script>
    // Admin sidebar
    const adminToggle = document.getElementById('adminToggle');
    const adminSidebar = document.getElementById('adminSidebar');
    if (adminToggle && adminSidebar) {
      adminToggle.addEventListener('click', () => {
        adminSidebar.classList.toggle('translate-x-full');
      });
      document.addEventListener('click', (e) => {
        if (!adminSidebar.contains(e.target) && !adminToggle.contains(e.target)) {
          adminSidebar.classList.add('translate-x-full');
        }
      });
    }

    // PWA Install
    let deferredPrompt = null;
    const isStandalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;

    const logoText = document.getElementById('logoText');
    const installLogoBtn = document.getElementById('installLogoBtn');
    const installLogoBtnText = document.getElementById('installLogoBtnText');

    function showInstalled() {
      if (logoText) logoText.style.display = 'inline';
      if (installLogoBtn) installLogoBtn.style.display = 'none';
    }

    function showInstall() {
      if (installLogoBtn) installLogoBtn.style.display = 'flex';
      if (logoText) logoText.style.display = 'none';
    }

    window.addEventListener('beforeinstallprompt', (e) => {
      if (isStandalone) return;
      e.preventDefault();
      deferredPrompt = e;
      showInstall();
    });

    window.addEventListener('appinstalled', () => {
      console.log('✅ Приложение установлено');
      showInstalled();
    });

    document.addEventListener('DOMContentLoaded', () => {
      if (isStandalone) {
        showInstalled();
      } else {
        showInstall();
      }

      let alt = false;
      setInterval(() => {
        if (!installLogoBtnText) return;
        installLogoBtnText.classList.add('opacity-0');
        setTimeout(() => {
          installLogoBtnText.innerHTML = alt ? 'BerryGo' : 'Установите<br>приложение';
          installLogoBtnText.classList.remove('opacity-0');
          alt = !alt;
        }, 500);
      }, 3000);

      installLogoBtn?.addEventListener('click', () => {
        if (deferredPrompt) {
          deferredPrompt.prompt();
          deferredPrompt.userChoice.then((choiceResult) => {
            if (choiceResult.outcome === 'accepted') {
              console.log('✅ Пользователь установил приложение');
              showInstalled();
            } else {
              console.log('❌ Пользователь отказался от установки');
            }
            deferredPrompt = null;
          });
        } else {
          alert("📱 Установка недоступна — попробуйте с мобильного браузера");
        }
      });
    });
  </script>
  
  
  
      
      
      
      <!-- DEBUG OVERLAY (скрыт по умолчанию) 
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
      <pre id="debugContent" style="white-space: pre-wrap;"></pre>
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
      // Получаем переменную debugData из PHP, если она определена
      const __DEBUG_DATA__ = <?= json_encode($debugData ?? [], JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT) ?>;

      const overlay = document.getElementById('debugOverlay');
      const content = document.getElementById('debugContent');
      const btnOpen = document.getElementById('debugOpenBtn');
      const btnClose = document.getElementById('debugToggle');

      // Заполняем содержимое, если debugData есть
      if (__DEBUG_DATA__ && Object.keys(__DEBUG_DATA__).length > 0) {
        content.textContent = JSON.stringify(__DEBUG_DATA__, null, 2);
      } else {
        content.textContent = "Нет данных для отладки";
      }

      // Открыть оверлей
      btnOpen.addEventListener('click', () => {
        overlay.style.display = 'block';
        btnOpen.style.display = 'none';
      });

      // Закрыть оверлей
      btnClose.addEventListener('click', () => {
        overlay.style.display = 'none';
        btnOpen.style.display = 'block';
      });
    </script>
      -->
      
      
      
      
      
      
      
      
      
      
      
      
<!-- ====== МОДАЛЬНОЕ ОКНО «Клубнички» – ЯРКИЙ СТИЛЬ, ОПТИМИЗИРОВАННОЕ ДЛЯ МОБИЛЬНЫХ УСТРОЙСТВ ====== -->
<div id="pointsPopupBackdrop" class="fixed inset-0 bg-black/50 z-50 hidden"></div>
<div id="pointsPopup" class="fixed inset-0 flex items-center justify-center z-50 hidden px-4">
  <div class="relative bg-gradient-to-br from-red-500 via-pink-500 to-rose-400 text-white rounded-3xl shadow-2xl w-full max-w-md sm:max-w-lg overflow-hidden">
    <!-- Декоративные полупрозрачные круги -->
    <div class="absolute top-0 right-0 w-24 h-24 sm:w-32 sm:h-32 bg-white/10 rounded-full -translate-y-12 translate-x-12 sm:-translate-y-16 sm:translate-x-16"></div>
    <div class="absolute bottom-0 left-0 w-16 h-16 sm:w-24 sm:h-24 bg-white/10 rounded-full translate-y-8 -translate-x-8 sm:translate-y-12 sm:-translate-x-12"></div>

    <!-- Заголовок и кнопка закрытия -->
    <div class="relative z-10 flex justify-between items-center px-4 sm:px-6 py-3 sm:py-4 border-b border-white/20">
      <h2 class="text-xl sm:text-2xl font-bold flex items-center space-x-2">
        <span class="text-2xl sm:text-3xl">🍓</span>
        <span>Ваши клубнички</span>
      </h2>
      <button id="closePointsPopup" class="text-white text-2xl hover:opacity-80 px-2 sm:px-3">&times;</button>
    </div>

    <!-- Содержимое поп-апа -->
    <div class="relative z-10 p-4 sm:p-6 space-y-4 sm:space-y-6">
      <!-- Баланс -->
      <div class="text-center">
        <div class="text-4xl sm:text-5xl font-extrabold">
           <?= (int)($_SESSION['points_balance'] ?? 0) ?> <span class="text-2xl sm:text-3xl">🍓</span>
        </div>
        <p class="mt-2 text-base sm:text-lg opacity-90">
          Это ваши подарочные баллы — 1 🍓 = 1 ₽. Каждый раз, когда вы дарите другу скидку 10% по вашей ссылке или купону, он экономит и радуется, а вы получаете 🍓 за каждый его заказ!
        </p>
      </div>

      <hr class="border-white/30">

      <!-- Блок «Подарите другу скидку» -->
      <div class="bg-white/10 rounded-2xl p-3 sm:p-4 space-y-3 sm:space-y-4">
        <h3 class="text-lg sm:text-xl font-semibold text-white">
          Подарите другу <span class="text-yellow-200">10 %</span> скидку!
        </h3>
        <p class="text-sm sm:text-base text-white/90">
          Отправьте ссылку или купон — и ваш друг сразу получит подарок.
        </p>

        <!-- Ссылка-приглашение -->
        <div class="relative flex flex-col sm:flex-row items-stretch sm:items-center space-y-2 sm:space-y-0 sm:space-x-2">
            <?php $inviteLink = "https://berrygo.ru/?invite=" . urlencode($_SESSION['referral_code'] ?? ''); ?>
            <input
              id="inviteLinkInput"
              type="text"
              readonly
              value="<?= htmlspecialchars($inviteLink) ?>"
              class="flex-1 bg-white/20 rounded-lg px-3 py-2 text-xs sm:text-sm text-white outline-none cursor-pointer transition-all"
              onclick="copyText(this, 'linkCopied')"
            />
             <span id="linkCopied" class="absolute top-0 right-3 sm:right-0 mt-2 mr-2 text-xs text-green-300 opacity-0 transition-opacity">Скопировано!</span>
        </div>

        <!-- Купон -->
        <div class="relative flex items-center space-x-2">
          <code
              id="inviteCode"
              class="bg-white/20 rounded-lg px-3 py-2 font-mono text-sm sm:text-base text-white cursor-pointer transition-all"
              onclick="copyText(this, 'codeCopied')"
            >
              <?= htmlspecialchars($_SESSION['referral_code'] ?? '') ?>
            </code>

          <span id="codeCopied" class="absolute top-0 right-0 mt-2 mr-2 text-xs text-green-300 opacity-0 transition-opacity">Скопировано!</span>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
  // Открыть/закрыть модальное окно «Клубничек»
  const btnOpenPoints = document.getElementById('openPointsPopup');
  const popup = document.getElementById('pointsPopup');
  const backdrop = document.getElementById('pointsPopupBackdrop');
  const btnClosePoints = document.getElementById('closePointsPopup');

  function showPointsPopup() {
    backdrop.classList.remove('hidden');
    popup.classList.remove('hidden');
  }
  function hidePointsPopup() {
    popup.classList.add('hidden');
    backdrop.classList.add('hidden');
  }
  if (btnOpenPoints) {
    btnOpenPoints.addEventListener('click', showPointsPopup);
  }
  if (btnClosePoints) {
    btnClosePoints.addEventListener('click', hidePointsPopup);
  }
  backdrop.addEventListener('click', hidePointsPopup);

  // Функция копирования с анимацией и подсказкой
  function copyText(el, hintId) {
    let textToCopy = el.value ?? el.innerText;
    navigator.clipboard.writeText(textToCopy).then(() => {
      // Показать подсказку «Скопировано!»
      const hintEl = document.getElementById(hintId);
      hintEl.classList.remove('opacity-0');
      hintEl.classList.add('opacity-100');

      // Скрыть подсказку через 1,2 сек
      setTimeout(() => {
        hintEl.classList.remove('opacity-100');
        hintEl.classList.add('opacity-0');
      }, 1200);

      // Краткая анимация подсветки поля
      el.classList.add('bg-white/40', 'scale-105');
      setTimeout(() => {
        el.classList.remove('bg-white/40', 'scale-105');
      }, 400);
    }).catch(() => {
      alert('Не удалось скопировать.');
    });
  }
</script>

<script>
  // Equalize heights of product cards on the home page
  function equalizeProductRows() {
    document.querySelectorAll('.eq-row').forEach(row => {
      let max = 0;
      const items = Array.from(row.children);
      items.forEach(el => {
        el.style.height = 'auto';
        const h = el.offsetHeight;
        if (h > max) max = h;
      });
      items.forEach(el => {
        el.style.height = max + 'px';
      });
    });
  }

  document.addEventListener('DOMContentLoaded', equalizeProductRows);
  window.addEventListener('resize', () => {
    clearTimeout(window.eqResizeTimeout);
    window.eqResizeTimeout = setTimeout(equalizeProductRows, 100);
  });
</script>

<script>
  // Scroll arrows for product rows on desktop
  document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.scroll-wrapper').forEach(wrapper => {
      const row = wrapper.querySelector('.scroll-row');
      wrapper.querySelectorAll('button[data-dir]').forEach(btn => {
        btn.addEventListener('click', () => {
          const dir = btn.dataset.dir === 'left' ? -1 : 1;
          row.scrollBy({left: dir * row.clientWidth, behavior: 'smooth'});
        });
      });
    });
  });
</script>





      
      
      
      
      

      
      
      
      
      

  
</body>
</html>