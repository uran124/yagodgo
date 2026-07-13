<?php
  $points = (int)($_SESSION['points_balance'] ?? 0);
  $supportUnreadCount = 0;
  if (!empty($_SESSION['user_id'])) {
      try {
          global $pdo;
          if (isset($pdo) && $pdo instanceof PDO) {
              $supportUnreadStmt = $pdo->prepare('SELECT COALESCE(SUM(client_unread_count),0) FROM support_chats WHERE user_id = ?');
              $supportUnreadStmt->execute([$_SESSION['user_id']]);
              $supportUnreadCount = (int)$supportUnreadStmt->fetchColumn();
          }
      } catch (Throwable $e) {
          $supportUnreadCount = 0;
      }
  }

  /** Метаданные страницы */
  $pageMeta = $meta ?? [];
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
  if (!empty($pageMeta)) {
      $meta = array_merge($meta, array_filter($pageMeta, static fn($v) => $v !== null && $v !== ''));
  }

  $lightTheme = get_theme_colors('light');
  $darkTheme  = get_theme_colors('dark');
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
  
  <!-- Favicon и основные иконки -->
  <link rel="icon" href="/assets/images/favicon.svg" type="image/svg+xml">
  <link rel="apple-touch-icon" sizes="180x180" href="/assets/images/icon-192.png">

  <!-- Иконки для Android / PWA -->
  <link rel="icon" type="image/png" sizes="72x72" href="/assets/images/icon-72.png">
  <link rel="icon" type="image/png" sizes="96x96" href="/assets/images/icon-96.png">
  <link rel="icon" type="image/png" sizes="128x128" href="/assets/images/icon-128.png">
  <link rel="icon" type="image/png" sizes="144x144" href="/assets/images/icon-144.png">
  <link rel="icon" type="image/png" sizes="152x152" href="/assets/images/icon-152.png">
  <link rel="icon" type="image/png" sizes="192x192" href="/assets/images/icon-192.png">
  <link rel="icon" type="image/png" sizes="384x384" href="/assets/images/icon-384.png">
  <link rel="icon" type="image/png" sizes="512x512" href="/assets/images/icon-512.png">

  <!-- PWA maskable icons -->
  <link rel="icon" type="image/png" sizes="192x192" href="/assets/images/icon-192-maskable.png" purpose="maskable">
  <link rel="icon" type="image/png" sizes="512x512" href="/assets/images/icon-512-maskable.png" purpose="maskable">

  <link rel="stylesheet" href="/assets/css/theme.css">
  
  <link rel="manifest" href="/manifest.json">
  <meta name="theme-color" content="#FF6B6B">
  <meta name="mobile-web-app-capable" content="yes">

  <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "LocalBusiness",
      "name": "BerryGo",
      "image": "https://berrygo.ru/assets/images/icon-512.png",
      "url": "https://berrygo.ru",
      "telephone": "+7 902 923-77-94",
      "email": "support@berrygo.ru",
      "address": {
        "@type": "PostalAddress",
        "streetAddress": "улица 9 Мая, 73",
        "addressLocality": "Красноярск",
        "addressCountry": "RU"
      },
      "areaServed": {
        "@type": "City",
        "name": "Красноярск"
      },
      "openingHoursSpecification": [
        {
          "@type": "OpeningHoursSpecification",
          "dayOfWeek": [
            "Monday",
            "Tuesday",
            "Wednesday",
            "Thursday",
            "Friday",
            "Saturday",
            "Sunday"
          ],
          "opens": "09:00",
          "closes": "21:00"
        }
      ],
      "sameAs": [
        "https://t.me/berryGo24",
        "https://vk.com/berryGo_ru"
      ],
      "makesOffer": {
        "@type": "Offer",
        "name": "Самовывоз заказов BerryGo",
        "availableDeliveryMethod": "https://schema.org/OnSitePickup",
        "itemOffered": {
          "@type": "Service",
          "name": "Самовывоз заказов BerryGo"
        },
        "areaServed": {
          "@type": "City",
          "name": "Красноярск"
        },
        "pickupLocation": {
          "@type": "Place",
          "name": "Пункт самовывоза BerryGo",
          "address": {
            "@type": "PostalAddress",
            "streetAddress": "улица 9 Мая, 73",
            "addressLocality": "Красноярск",
            "addressCountry": "RU"
          }
        }
      }
    }
  </script>

  <style>
    :root {
      --berry-red: <?= htmlspecialchars($lightTheme['strong']) ?>;
      --berry-pink: <?= htmlspecialchars($lightTheme['secondary']) ?>;
      --accent-primary: <?= htmlspecialchars($lightTheme['primary']) ?>;
      --accent-secondary: <?= htmlspecialchars($lightTheme['secondary']) ?>;
      --accent-strong: <?= htmlspecialchars($lightTheme['strong']) ?>;
      --accent-via: <?= htmlspecialchars($lightTheme['via']) ?>;
      --accent-soft: <?= htmlspecialchars($lightTheme['soft']) ?>;
      --accent-contrast: <?= htmlspecialchars($lightTheme['contrast']) ?>;
      --accent-image: <?= htmlspecialchars($lightTheme['image']) ?>;
      --accent-image-alt: <?= htmlspecialchars($lightTheme['imageAlt']) ?>;
      --fresh-green: #4ECDC4;
      --leaf-green: #45B7AA;
      --cream: #FFF8F5;
      --soft-gray: #F8FAFC;
      --text-dark: #1A202C;
      --text-gray: #4A5568;
    }

    @media (prefers-color-scheme: dark) {
      :root {
        --berry-red: <?= htmlspecialchars($darkTheme['strong']) ?>;
        --berry-pink: <?= htmlspecialchars($darkTheme['secondary']) ?>;
        --accent-primary: <?= htmlspecialchars($darkTheme['primary']) ?>;
        --accent-secondary: <?= htmlspecialchars($darkTheme['secondary']) ?>;
        --accent-strong: <?= htmlspecialchars($darkTheme['strong']) ?>;
        --accent-via: <?= htmlspecialchars($darkTheme['via']) ?>;
        --accent-soft: <?= htmlspecialchars($darkTheme['soft']) ?>;
        --accent-contrast: <?= htmlspecialchars($darkTheme['contrast']) ?>;
        --accent-image: <?= htmlspecialchars($darkTheme['image']) ?>;
        --accent-image-alt: <?= htmlspecialchars($darkTheme['imageAlt']) ?>;
      }
    }

  </style>


  <script>
    if ('serviceWorker' in navigator) {
      window.addEventListener('load', () => {
        navigator.serviceWorker.getRegistrations()
          .then((registrations) => Promise.all(registrations.map((registration) => registration.unregister())))
          .then(() => {
            if (!('caches' in window)) {
              return null;
            }
            return caches.keys().then((keys) => Promise.all(keys.map((key) => caches.delete(key))));
          })
          .catch(() => {});
      });
    }
  </script>
  <meta name="csrf-token" content="<?= htmlspecialchars(csrf_token(), ENT_QUOTES) ?>">
  <script src="/assets/js/local-icons.js" defer></script>
</head>
<body class="bg-gradient-to-br from-orange-50 via-white to-pink-50 text-gray-800 min-h-screen">

  <!-- Header -->
<div id="installPromptBar" class="fixed top-0 left-0 right-0 z-30 px-4 pt-3" style="display:none;">
  <div class="glass-effect border border-white/30 rounded-2xl px-4 py-3 flex items-center justify-between shadow-lg">
    <button id="installLogoBtn" class="group flex flex-row items-center text-left space-x-2 install-pulse">
      <span id="installLogoBtnText" class="font-bold text-sm leading-tight bg-gradient-to-r from-red-500 to-pink-500 bg-clip-text text-transparent transition-opacity duration-500">
        Установите<br>приложение
      </span>
      <span class="material-icons-round text-red-500">install_mobile</span>
    </button>
    <button id="installPromptClose" class="material-icons-round text-gray-500 hover:text-gray-700 p-1" aria-label="Скрыть блок установки приложения">close</button>
  </div>
</div>

<header class="fixed top-0 left-0 right-0 glass-effect flex items-center justify-between p-4 z-20 border-b border-white/20">
  <a href="/" id="logoLink" class="flex items-center">
    <div class="w-10 h-10 flex items-center justify-center floating-animation">
      <img src="/assets/berrygo_strawberry.svg" alt="BerryGo" class="w-10 h-10">
    </div>
  </a>

  <div class="flex items-center space-x-3">
    <?php
      $headerRole = (string)($_SESSION['role'] ?? '');
      $adminSectionUrls = [
        'admin' => '/admin/dashboard',
        'manager' => '/manager/dashboard',
        'partner' => '/partner/dashboard',
        'seller' => '/seller/dashboard',
      ];
      $adminSectionUrl = $adminSectionUrls[$headerRole] ?? null;
    ?>
    <?php if ($adminSectionUrl !== null): ?>
      <a href="<?= htmlspecialchars($adminSectionUrl) ?>"
         class="p-2 text-gray-600 hover:text-[#C86052] transition-colors hover:bg-red-50 rounded-xl"
         title="Перейти в админский раздел"
         aria-label="Перейти в админский раздел">
        <span class="material-icons-round">admin_panel_settings</span>
      </a>
    <?php endif; ?>

    <?php if (!empty($_SESSION['user_id'])): ?>
      <!-- Авторизованный пользователь -->
      <?php $points = (int)($_SESSION['points_balance'] ?? 0); ?>
      <a href="/chat"
         class="relative ml-2 flex items-center space-x-1 rounded-full bg-white/20 px-3 py-1 text-gray-800 shadow-lg backdrop-blur-sm transition-colors hover:bg-pink-100"
         title="Чат поддержки">
        <span class="material-icons-round text-lg text-[#C86052]">support_agent</span>
        <span class="hidden text-sm font-semibold sm:inline">Чат</span>
        <?php if ($supportUnreadCount > 0): ?>
          <span class="absolute -right-1 -top-1 min-w-5 rounded-full bg-red-500 px-1.5 text-center text-[10px] font-bold text-white"><?= $supportUnreadCount ?></span>
        <?php endif; ?>
      </a>
      <button
        id="openPointsPopup"
        class=" shadow-lg ml-2 flex items-center space-x-1 bg-white/20 backdrop-blur-sm rounded-full px-3 py-1 hover:bg-red-100 transition-colors hover:shadow-xl transition-shadow duration-200"
        title="Ваш баланс клубничек"
      >
        <span class="text-xl">🍓</span>
        <span class="font-medium text-gray-800"><?= $points ?></span>
      </button>

      <form action="/logout" method="post">
        <?= csrf_field() ?>
        <button type="submit"
                class="material-icons-round text-2xl text-gray-600 hover:text-red-500 transition-colors p-2 hover:bg-red-50 rounded-xl"
                title="Выйти">
          logout
        </button>
      </form>
    <?php else: ?>
      <!-- Гость -->
      <a href="/login"
         class="relative ml-2 flex items-center space-x-1 rounded-full bg-white/20 px-3 py-1 text-gray-800 backdrop-blur-sm transition-colors hover:bg-pink-100"
         title="Войти, чтобы открыть чат поддержки">
        <span class="material-icons-round text-lg text-[#C86052]">support_agent</span>
        <span class="hidden text-sm font-semibold sm:inline">Чат</span>
      </a>
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
  <?php
    $currentPath = strtok($_SERVER['REQUEST_URI'] ?? '', '?') ?: '/';
    $adminMenuItems = [
      ['href' => '/admin/dashboard', 'icon' => 'dashboard', 'label' => 'Dashboard', 'hover' => 'hover:from-emerald-50 hover:to-teal-50'],
      ['href' => '/admin/orders', 'icon' => 'receipt_long', 'label' => 'Заказы', 'hover' => 'hover:from-blue-50 hover:to-indigo-50'],
      ['href' => '/admin/chats', 'icon' => 'forum', 'label' => 'Чаты', 'hover' => 'hover:from-pink-50 hover:to-red-50'],
      ['href' => '/admin/purchases', 'icon' => 'local_shipping', 'label' => 'Закупки', 'hover' => 'hover:from-sky-50 hover:to-blue-50'],
      ['href' => '/admin/products', 'icon' => 'inventory_2', 'label' => 'Товары', 'hover' => 'hover:from-purple-50 hover:to-pink-50'],
      ['href' => '/admin/product-types', 'icon' => 'category', 'label' => 'Категории', 'hover' => 'hover:from-fuchsia-50 hover:to-purple-50'],
      ['href' => '/admin/slots', 'icon' => 'calendar_today', 'label' => 'Слоты', 'hover' => 'hover:from-orange-50 hover:to-red-50'],
      ['href' => '/admin/coupons', 'icon' => 'local_offer', 'label' => 'Промокоды', 'hover' => 'hover:from-yellow-50 hover:to-orange-50'],
      ['href' => '/admin/content', 'icon' => 'article', 'label' => 'Контент', 'hover' => 'hover:from-lime-50 hover:to-emerald-50'],
      ['href' => '/admin/users', 'icon' => 'people', 'label' => 'Пользователи', 'hover' => 'hover:from-teal-50 hover:to-cyan-50'],
      ['href' => '/admin/sellers', 'icon' => 'storefront', 'label' => 'Селлеры', 'hover' => 'hover:from-rose-50 hover:to-pink-50'],
      ['href' => '/admin/apps', 'icon' => 'apps', 'label' => 'Приложения', 'hover' => 'hover:from-indigo-50 hover:to-violet-50'],
      ['href' => '/admin/settings', 'icon' => 'settings', 'label' => 'Настройки', 'hover' => 'hover:from-gray-100 hover:to-slate-100'],
    ];
  ?>
  <aside
    id="adminSidebar"
    class="fixed top-0 right-0 h-full w-80 glass-effect shadow-2xl transform translate-x-full transition-transform duration-300 z-30 border-l border-white/20"
  >
    <div class="p-6 font-bold text-xl border-b border-gray-100 fresh-gradient text-white rounded-tr-xl">
      <span class="material-icons-round mr-2">admin_panel_settings</span>
      Админ-панель
    </div>
    <nav class="p-4 space-y-1 overflow-y-auto max-h-[calc(100vh-96px)]">
      <?php foreach ($adminMenuItems as $item):
        $isActive = strpos($currentPath, $item['href']) === 0;
      ?>
      <a href="<?= $item['href'] ?>"
         class="flex items-center p-4 rounded-2xl transition-all group <?= $isActive ? 'bg-[#C86052]/10 text-[#C86052]' : 'hover:bg-gradient-to-r ' . $item['hover'] ?>">
        <span class="material-icons-round mr-3 group-hover:scale-110 transition-transform <?= $isActive ? 'text-[#C86052]' : 'text-gray-600' ?>">
          <?= $item['icon'] ?>
        </span>
        <span class="font-medium"><?= $item['label'] ?></span>
      </a>
      <?php endforeach; ?>
    </nav>
  </aside>
  <?php endif; ?>

  <!-- Manager sidebar (off-canvas) -->
  <?php if (($_SESSION['role'] ?? '') === 'manager'): ?>
  <aside
    id="managerSidebar"
    class="fixed top-0 right-0 h-full w-80 glass-effect shadow-2xl transform translate-x-full transition-transform duration-300 z-30 border-l border-white/20"
  >
    <div class="p-6 font-bold text-xl border-b border-gray-100 fresh-gradient text-white rounded-tr-xl">
      <span class="material-icons-round mr-2">manage_accounts</span>
      Менеджер
    </div>
    <nav class="p-4 space-y-1">
      <a href="/manager/orders" class="flex items-center p-4 hover:bg-gradient-to-r hover:from-blue-50 hover:to-indigo-50 rounded-2xl transition-all group">
        <span class="material-icons-round mr-3 text-blue-500 group-hover:scale-110 transition-transform">receipt_long</span>
        <span class="font-medium">Заказы</span>
      </a>
      <a href="/manager/products" class="flex items-center p-4 hover:bg-gradient-to-r hover:from-purple-50 hover:to-pink-50 rounded-2xl transition-all group">
        <span class="material-icons-round mr-3 text-purple-500 group-hover:scale-110 transition-transform">inventory_2</span>
        <span class="font-medium">Товары</span>
      </a>
      <a href="/manager/users" class="flex items-center p-4 hover:bg-gradient-to-r hover:from-teal-50 hover:to-cyan-50 rounded-2xl transition-all group">
        <span class="material-icons-round mr-3 text-teal-500 group-hover:scale-110 transition-transform">people</span>
        <span class="font-medium">Пользователи</span>
      </a>
      <a href="/manager/profile" class="flex items-center p-4 hover:bg-gradient-to-r hover:from-red-50 hover:to-orange-50 rounded-2xl transition-all group">
        <span class="material-icons-round mr-3 text-red-500 group-hover:scale-110 transition-transform">account_circle</span>
        <span class="font-medium">Профиль</span>
      </a>
    </nav>
  </aside>
  <?php endif; ?>

  <!-- Partner sidebar (off-canvas) -->
  <?php if (($_SESSION['role'] ?? '') === 'partner'): ?>
  <aside
    id="partnerSidebar"
    class="fixed top-0 right-0 h-full w-80 glass-effect shadow-2xl transform translate-x-full transition-transform duration-300 z-30 border-l border-white/20"
  >
    <div class="p-6 font-bold text-xl border-b border-gray-100 fresh-gradient text-white rounded-tr-xl">
      <span class="material-icons-round mr-2">manage_accounts</span>
      Партнёр
    </div>
    <nav class="p-4 space-y-1">
      <a href="/partner/orders" class="flex items-center p-4 hover:bg-gradient-to-r hover:from-blue-50 hover:to-indigo-50 rounded-2xl transition-all group">
        <span class="material-icons-round mr-3 text-blue-500 group-hover:scale-110 transition-transform">receipt_long</span>
        <span class="font-medium">Заказы</span>
      </a>
      <a href="/partner/products" class="flex items-center p-4 hover:bg-gradient-to-r hover:from-purple-50 hover:to-pink-50 rounded-2xl transition-all group">
        <span class="material-icons-round mr-3 text-purple-500 group-hover:scale-110 transition-transform">inventory_2</span>
        <span class="font-medium">Товары</span>
      </a>
      <a href="/partner/users" class="flex items-center p-4 hover:bg-gradient-to-r hover:from-teal-50 hover:to-cyan-50 rounded-2xl transition-all group">
        <span class="material-icons-round mr-3 text-teal-500 group-hover:scale-110 transition-transform">people</span>
        <span class="font-medium">Пользователи</span>
      </a>
      <a href="/partner/profile" class="flex items-center p-4 hover:bg-gradient-to-r hover:from-red-50 hover:to-orange-50 rounded-2xl transition-all group">
        <span class="material-icons-round mr-3 text-red-500 group-hover:scale-110 transition-transform">account_circle</span>
        <span class="font-medium">Профиль</span>
      </a>
    </nav>
  </aside>
  <?php endif; ?>
  
  <!-- Install PWA Banner (replaced by logo button) -->

  <!-- Контент -->
  <div class="pt-16 pb-24">
    <?php if ($path !== '/') : ?>
      <?php
        $crumbs = $breadcrumbs ?? [];
        if (empty($crumbs)) {
            $segments = array_filter(explode('/', trim($path, '/')));
            $accum = '';
            foreach ($segments as $i => $seg) {
                $accum .= '/' . $seg;
                $label = ($i === count($segments)-1 && !empty($meta['h1'])) ? $meta['h1'] : $seg;
                $crumbs[] = ['label' => $label, 'url' => $i < count($segments)-1 ? $accum : null];
            }
        }
      ?>
      <nav class="px-4 pb-2 text-sm text-gray-500 mt-4" aria-label="Breadcrumb">
        <a href="/" class="text-red-500 hover:underline">Главная</a>
        <?php foreach ($crumbs as $bc): ?>
          <span class="mx-1">/</span>
          <?php if (!empty($bc['url'])): ?>
            <a href="<?= htmlspecialchars($bc['url']) ?>" class="hover:underline">
              <?= htmlspecialchars($bc['label']) ?>
            </a>
          <?php else: ?>
            <?= htmlspecialchars($bc['label']) ?>
          <?php endif; ?>
        <?php endforeach; ?>
      </nav>
    <?php endif; ?>
    <?= $content ?>
    <?php
      $showMetaText = $showMetaText ?? true;
      if (!empty($meta['text']) && $showMetaText): ?>
      <div class="max-w-screen-lg mx-auto px-4 pb-24 text-gray-700 text-sm">
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
    <ul class="flex justify-between px-1 py-2">
 
      <!-- Каталог -->
      <li class="flex-1">
        <a href="/catalog" class="nav-item flex flex-col items-center py-2 px-1 rounded-xl transition-all <?= isActive('/catalog') ?>">
          <span class="material-icons-round text-[22px] mb-0.5">grid_view</span>
          <span class="text-[10px] font-medium leading-tight">Каталог</span>
        </a>
      </li>
 
      <!-- Корзина -->
      <?php
        $cartClass = ($cartTotal > 0 || $uri === '/cart')
                     ? 'text-red-500 bg-red-50' : 'text-gray-500';
      ?>
      <li class="flex-1">
        <a href="/cart" class="nav-item flex flex-col items-center py-2 px-1 rounded-xl transition-all <?= $cartClass ?> relative">
          <?php if ($cartTotal > 0): ?>
            <div class="absolute top-1 right-1 px-1 h-4 berry-gradient rounded-sm flex items-center justify-center">
              <span class="text-[9px] font-bold text-white leading-none"><?= $cartTotal ?>₽</span>
            </div>
          <?php endif; ?>
          <span class="material-icons-round text-[22px] mb-0.5">shopping_cart</span>
          <span class="text-[10px] font-medium leading-tight">Корзина</span>
        </a>
      </li>
 
      <!-- Заказы -->
      <?php if (in_array($role, ['client','partner','manager','admin','seller'])): ?>
        <li class="flex-1">
          <a href="/orders" class="nav-item flex flex-col items-center py-2 px-1 rounded-xl transition-all <?= isActive('/orders') ?>">
            <span class="material-icons-round text-[22px] mb-0.5">receipt_long</span>
            <span class="text-[10px] font-medium leading-tight">Заказы</span>
          </a>
        </li>
      <?php else: ?>
        <li class="flex-1">
          <div class="nav-item flex flex-col items-center py-2 px-1 rounded-xl text-gray-300">
            <span class="material-icons-round text-[22px] mb-0.5">receipt_long</span>
            <span class="text-[10px] font-medium leading-tight">Заказы</span>
          </div>
        </li>
      <?php endif; ?>
 
      <!-- Уведомления -->
      <?php if ($role === 'admin'): ?>
        <li class="flex-1">
          <a href="/notifications" class="nav-item flex flex-col items-center py-2 px-1 rounded-xl transition-all <?= isActive('/notifications') ?>">
            <span class="material-icons-round text-[22px] mb-0.5">notifications</span>
            <span class="text-[10px] font-medium leading-tight text-center">Уведом.</span>
          </a>
        </li>
      <?php else: ?>
        <li class="flex-1">
          <div class="nav-item flex flex-col items-center py-2 px-1 rounded-xl text-gray-300">
            <span class="material-icons-round text-[22px] mb-0.5">notifications</span>
            <span class="text-[10px] font-medium leading-tight text-center">Уведом.</span>
          </div>
        </li>
      <?php endif; ?>
 
      <!-- Профиль / Войти -->
      <?php if (in_array($role, ['client','partner','manager','admin','seller'])): ?>
        <li class="flex-1">
          <a href="/profile" class="nav-item flex flex-col items-center py-2 px-1 rounded-xl transition-all <?= isActive('/profile') ?>">
            <span class="material-icons-round text-[22px] mb-0.5">person</span>
            <span class="text-[10px] font-medium leading-tight">Профиль</span>
          </a>
        </li>
      <?php else: ?>
        <li class="flex-1">
          <a href="/login" class="nav-item flex flex-col items-center py-2 px-1 rounded-xl transition-all text-gray-500 hover:text-emerald-500 hover:bg-emerald-50">
            <span class="material-icons-round text-[22px] mb-0.5">person</span>
            <span class="text-[10px] font-medium leading-tight">Войти</span>
          </a>
        </li>
      <?php endif; ?>
 
    </ul>
  </nav>

  <footer class="hidden md:block glass-effect border-t border-white/20 mt-8">
    <div class="max-w-screen-xl mx-auto py-10 px-6 text-sm text-gray-700">
      <div class="mb-8 rounded-3xl bg-gradient-to-r from-red-50 via-white to-emerald-50 p-6 shadow-sm">
        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-red-500">BerryGo.ru · свежие ягоды в Красноярске</p>
        <h2 class="mt-2 text-2xl font-extrabold text-gray-900">Свежая клубника, ягоды и фрукты с доставкой и самовывозом</h2>
        <p class="mt-3 max-w-3xl leading-6 text-gray-600">BerryGo помогает купить свежие ягоды в Красноярске онлайн: каталог, предзаказы, акции, кешбэк и полезные материалы о сезонных ягодах собраны на одном сайте.</p>
      </div>
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-8">
        <div>
          <h3 class="font-semibold mb-2">О BerryGo</h3>
          <p class="mb-1">Доставка ягод и фруктов по Красноярску</p>
          <p class="mb-1">Сайт: <a href="https://berrygo.ru" class="text-red-500 hover:underline">https://berryGo.ru</a></p>
          <p class="mb-1">Адрес: Красноярск, ул. 9 Мая, 73</p>
          <p class="mb-1">Время работы: Пн–Вс 9:00–21:00</p>
          <p class="mb-1">Телефон: <a href="tel:+79029237794" class="text-red-500 hover:underline">+7 902 923-77-94</a></p>
          <p>E-mail: <a href="mailto:support@berrygo.ru" class="text-red-500 hover:underline">support@berrygo.ru</a></p>
        </div>
        <div>
          <h3 class="font-semibold mb-2">Популярные разделы</h3>
          <ul class="space-y-1">
            <li><a href="/catalog" class="hover:underline">Каталог ягод и фруктов</a></li>
            <li><a href="/catalog/klubnika" class="hover:underline">Клубника в Красноярске</a></li>
            <li><a href="/catalog/chereshnya" class="hover:underline">Черешня с доставкой</a></li>
            <li><a href="/content" class="hover:underline">Полезные материалы о ягодах</a></li>
            <li><a href="/favorites" class="hover:underline">Избранное и предзаказы</a></li>
            <li><a href="/orders" class="hover:underline">История заказов</a></li>
          </ul>
        </div>
        <div>
          <h3 class="font-semibold mb-2">Мы в сети</h3>
          <ul class="space-y-1">
            <li>
              <a href="tg://resolve?domain=berryGo24" target="_blank" rel="noopener" class="hover:underline">
                Telegram-канал @berryGo24
              </a>
            </li>
            <li>
              <a href="https://vk.com/berryGo_ru" target="_blank" rel="noopener" class="hover:underline">
                VK berryGo_ru
              </a>
            </li>
            <li><a href="https://berrygo.ru" class="hover:underline">Официальный сайт berryGo.ru</a></li>
            <li><a href="/sitemap.xml" class="hover:underline">XML-карта сайта</a></li>
          </ul>
        </div>
        <div>
          <h3 class="font-semibold mb-2">Информация</h3>
          <ul class="space-y-1">
            <li>Самовывоз: ул. 9 Мая, 73</li>
            <li>Заказы онлайн 24/7</li>
            <li>Свежие поставки и предзаказы</li>
            <li>© berryGo, 2023–2026</li>
            <li>Разработка: berryGo team</li>
          </ul>
        </div>
      </div>
    </div>
  </footer>

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

    // Manager sidebar
    const managerToggle = document.getElementById('managerToggle');
    const managerSidebar = document.getElementById('managerSidebar');
    if (managerToggle && managerSidebar) {
      managerToggle.addEventListener('click', () => {
        managerSidebar.classList.toggle('translate-x-full');
      });
      document.addEventListener('click', (e) => {
        if (!managerSidebar.contains(e.target) && !managerToggle.contains(e.target)) {
          managerSidebar.classList.add('translate-x-full');
        }
      });
    }

    // Partner sidebar
    const partnerToggle = document.getElementById('partnerToggle');
    const partnerSidebar = document.getElementById('partnerSidebar');
    if (partnerToggle && partnerSidebar) {
      partnerToggle.addEventListener('click', () => {
        partnerSidebar.classList.toggle('translate-x-full');
      });
      document.addEventListener('click', (e) => {
        if (!partnerSidebar.contains(e.target) && !partnerToggle.contains(e.target)) {
          partnerSidebar.classList.add('translate-x-full');
        }
      });
    }

    // PWA Install
    let deferredPrompt = null;
    const isStandalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;

    const installPromptBar = document.getElementById('installPromptBar');
    const siteHeader = document.querySelector('header');
    const installLogoBtn = document.getElementById('installLogoBtn');
    const installPromptClose = document.getElementById('installPromptClose');

    function showInstalled() {
      if (installPromptBar) installPromptBar.style.display = 'none';
      if (siteHeader) siteHeader.style.top = '0';
    }

    function showInstall() {
      if (installPromptBar && sessionStorage.getItem('installPromptClosed') !== '1') {
        installPromptBar.style.display = 'block';
        if (siteHeader) siteHeader.style.top = '84px';
      }
    }

    window.addEventListener('beforeinstallprompt', (e) => {
      if (isStandalone) return;
      e.preventDefault();
      deferredPrompt = e;
      showInstall();
    });

    window.addEventListener('appinstalled', () => {
      showInstalled();
    });

    document.addEventListener('DOMContentLoaded', () => {
      if (isStandalone) {
        showInstalled();
      } else {
        showInstall();
      }

      installPromptClose?.addEventListener('click', () => {
        sessionStorage.setItem('installPromptClosed', '1');
        showInstalled();
      });

      installLogoBtn?.addEventListener('click', () => {
        if (deferredPrompt) {
          deferredPrompt.prompt();
          deferredPrompt.userChoice.then((choiceResult) => {
            if (choiceResult.outcome === 'accepted') {
              showInstalled();
            }
            deferredPrompt = null;
          });
        } else {
          alert("📱 Установка недоступна — попробуйте с мобильного браузера");
        }
      });
    });
  </script>

<script>
  // Enable mouse drag scrolling for carousels
  document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.scroll-row').forEach(row => {
      let isDown = false;
      let startX;
      let scrollLeft;

      const start = x => {
        isDown = true;
        startX = x;
        scrollLeft = row.scrollLeft;
      };

      const move = x => {
        if (!isDown) return;
        row.scrollLeft = scrollLeft - (x - startX);
      };

      row.addEventListener('mousedown', e => start(e.pageX));
      row.addEventListener('mousemove', e => {
        if (!isDown) return;
        e.preventDefault();
        move(e.pageX);
      });
      window.addEventListener('mouseup', () => { isDown = false; });

      row.addEventListener('touchstart', e => start(e.touches[0].pageX));
      row.addEventListener('touchmove', e => move(e.touches[0].pageX));
      window.addEventListener('touchend', () => { isDown = false; });
    });
  });
</script>
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

  <script src="/assets/js/embla-carousel.umd.min.js"></script>
  <script src="/assets/js/embla-carousel-autoplay.umd.js"></script>
  <script src="/assets/js/embla-carousel-fade.umd.js"></script>
<script>
  document.addEventListener('DOMContentLoaded', () => {
    // Drag free carousels
    document.querySelectorAll('.embla.drag-free').forEach(wrapper => {
      if (wrapper.classList.contains('product-mobile-carousel') && window.matchMedia('(max-width: 639px)').matches) {
        return;
      }
      const viewport = wrapper.querySelector('.embla__viewport');
      const embla = EmblaCarousel(viewport, { dragFree: true });
      const prev = wrapper.querySelector('[data-dir="left"]');
      const next = wrapper.querySelector('[data-dir="right"]');
      if (prev && next) {
        prev.addEventListener('click', () => embla.scrollPrev());
        next.addEventListener('click', () => embla.scrollNext());
      }
    });

    // News fade carousel
    const news = document.querySelector('.embla-news');
    if (news) {
      const viewport = news.querySelector('.embla__viewport');
      const dotsContainer = news.querySelector('.embla__dots');
      const embla = EmblaCarousel(viewport, { loop: true }, [
        EmblaCarouselAutoplay({ delay: 4000, stopOnInteraction: false }),
        EmblaCarouselFade()
      ]);
      const slides = embla.slideNodes();
      const dots = slides.map((_, i) => {
        const b = document.createElement('button');
        b.addEventListener('click', () => embla.scrollTo(i));
        dotsContainer.appendChild(b);
        return b;
      });
      function update() {
        const index = embla.selectedScrollSnap();
        slides.forEach((s, i) => {
          s.classList.toggle('is-selected', i === index);
        });
        dots.forEach((d, i) => {
          d.classList.toggle('is-active', i === index);
        });
      }
      embla.on('select', update);
      update();
    }
  });
</script>





      
      
      
      
      

      
      
      
      
      

  


<?php include __DIR__ . '/scripts.php'; ?>
</body>
</html>
