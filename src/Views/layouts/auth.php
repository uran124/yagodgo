<?php
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
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title><?= htmlspecialchars($meta['title']) ?></title>
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
  <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">

  <meta name="csrf-token" content="<?= htmlspecialchars(csrf_token(), ENT_QUOTES) ?>">
</head>
<body class="bg-gradient-to-br from-orange-50 via-white to-pink-50 text-gray-800 min-h-screen">
<?= $content ?>
<?php include __DIR__ . '/scripts.php'; ?>
</body>
</html>
