# BerryGo — архитектура файлов проекта

## 1) Корневая структура

```text
/
├── index.php                     # Единая точка входа и ручной роутинг
├── composer.json                 # PHP-зависимости, автозагрузка, scripts
├── phpunit.xml                   # Конфигурация тестов
├── README.md                     # Документация по запуску и структуре
├── manifest.json                 # PWA-манифест
├── service-worker.js             # Service Worker для фронтенда
│
├── bootstrap/                    # Инициализация приложения
├── config/                       # Runtime-конфиги (БД, интеграции, константы)
├── src/                          # Основной код (MVC + сервисы + middleware)
├── database/                     # SQL-миграции (ручное применение)
├── tests/                        # PHPUnit-тесты
├── bin/                          # CLI-скрипты (например, генерация sitemap)
├── assets/                       # Статические ассеты
├── uploads/                      # Загруженные медиа
└── log/                          # Логи интеграций/вебхуков
```

---

## 2) Инициализация и жизненный цикл запроса

```text
HTTP Request
   ↓
index.php
   ↓
bootstrap/app.php      # окружение, сессия, БД, helper'ы
bootstrap/views.php    # функции рендеринга шаблонов
bootstrap/auth.php     # auth wrappers и role guards
   ↓
Routing в index.php
   ↓
Controller (src/Controllers/*)
   ↓
Service/Model/Helper
   ↓
View (src/Views/*)
   ↓
HTTP Response
```

---

## 3) Каталог `src/` (ядро приложения)

```text
src/
├── Controllers/                 # Контроллеры HTTP-эндпоинтов
│   ├── AdminController.php
│   ├── AuthController.php
│   ├── ClientController.php
│   ├── OrdersController.php
│   ├── ProductsController.php
│   ├── SellersController.php
│   ├── ...
│
├── Services/                    # Прикладная бизнес-логика
│   ├── AdminOrdersPageService.php
│   └── ClientCatalogService.php
│
├── Models/                      # Доступ к данным и доменные сущности
│   ├── Order.php
│   ├── OrdersRepository.php
│   ├── User.php
│   └── PointsTransaction.php
│
├── Middleware/                  # Middleware уровня доступа
│   └── AuthMiddleware.php
│
├── Helpers/                     # Вспомогательные интеграционные классы
│   ├── Auth.php
│   ├── MailSender.php
│   ├── PhoneNormalizer.php
│   ├── ReferralHelper.php
│   ├── SmsRu.php
│   └── TelegramSender.php
│
├── Views/                       # PHP-шаблоны интерфейса
│   ├── client/                  # Клиентская часть
│   ├── admin/                   # Админка
│   └── layouts/                 # Общие layout-шаблоны
│
└── helpers.php                  # Глобальные helper-функции
```

---

## 4) Каталог `config/`

```text
config/
├── database.php      # Параметры подключения к MySQL/MariaDB
├── constants.php     # Бизнес-константы (наценки, коэффициенты и т.п.)
├── telegram.php      # Telegram-интеграция
├── sms.php           # SMS-интеграция
└── email.php         # Email-настройки
```

---

## 5) Каталог `database/`

- Хранит SQL-файлы миграций в формате `YYYY_NN_description.sql`.
- Применяются вручную в лексикографическом порядке.
- Покрывают эволюцию схемы: адреса, заказы, продукты, SEO, слоты доставки, seller/partner-функции, рассылки.

---

## 6) Каталог `tests/`

```text
tests/
├── AuthControllerTest.php
├── AuthMiddlewareTest.php
├── ClientCatalogServiceTest.php
├── OrderTest.php
├── OrdersControllerTest.php
├── ReferralHelperTest.php
├── SellerOrdersTest.php
├── SellerProfileTest.php
└── ...
```

- Unit-тесты покрывают middleware, сервисы и часть контроллеров/моделей.

---

## 7) Каталог `bin/`

```text
bin/
└── generate_sitemap.php   # Генерация sitemap.xml
```

Используется для операционных задач и запуска по cron.

---

## 8) Визуальная карта ответственности по каталогам

- `bootstrap/` — инфраструктурный старт приложения.
- `src/Controllers/` — orchestration HTTP-сценариев.
- `src/Services/` — бизнес-правила и use-case логика.
- `src/Models/` — работа с сущностями и БД.
- `src/Views/` — UI-шаблоны.
- `config/` — параметры окружения и интеграций.
- `database/` — история изменений БД.
- `tests/` — проверка корректности критичных компонентов.

---

## 9) Рекомендации по развитию файловой архитектуры

1. Добавить автоматический migration runner (вместо ручного применения SQL).
2. Вынести маршруты из `index.php` в отдельный `routes/` каталог.
3. Разделить `Views/admin` на доменные подпапки по bounded context.
4. Добавить `docs/adr/` для архитектурных решений (ADR).
5. Добавить CI-пайплайн с обязательным прогоном тестов и статического анализа.
