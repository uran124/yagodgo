<?php
// src/Controllers/BotController.php
namespace App\Controllers;

use App\Helpers\SensitiveData;
use Telegram\Bot\Api; // Предполагаем, что в проекте установлена библиотека irazasyed/telegram-bot-sdk через Composer
// Если вы используете другую, замените на соответствующий класс.

class BotController
{
    protected Api $telegram;
    protected \PDO $pdo;
    protected array $config;
    protected int|string|array $adminChatId;
    protected int|null $adminTopicId = null;

    public function __construct(\PDO $pdo, array $telegramConfig)
    {
        $this->pdo    = $pdo;
        $this->config = $telegramConfig;

        // Создаём экземпляр Telegram API
        $this->telegram = new Api($this->config['bot_token']);

        // chat_id администратора или группы — может быть строкой или массивом
        $this->adminChatId  = $this->config['admin_chat_id'];
        $this->adminTopicId = $this->config['admin_topic_id'] ?? null;
    }

    /**
     * Главный метод для обработки входящих сообщений и команд.
     */
    public function webhook(): void
    {
        try {
            // Получаем всё обновление (Update) из Telegram
            $update = $this->telegram->getWebhookUpdate();

            // Если это обычное текстовое сообщение
            if ($message = $update->getMessage()) {
                $this->handleMessage($message);
                return;
            }

            // Если это callback_query (нажатие на inline-кнопку)
            if ($callbackQuery = $update->getCallbackQuery()) {
                $this->handleCallbackData($callbackQuery);
                return;
            }

            // Другие типы (например, edited_message и др.) пока не обрабатываем
        } catch (\Throwable $e) {
            $rawLogMessage = sprintf("[%s] %s\n%s\n", date('Y-m-d H:i:s'), $e->getMessage(), $e->getTraceAsString());
            $logMessage = SensitiveData::sanitizeText($rawLogMessage, [$this->config['bot_token'] ?? '', $this->config['secret_token'] ?? '']);
            error_log($logMessage, 3, __DIR__ . '/../../log/webhook.log');
            http_response_code(200);
        }
    }

    /**
     * Обработка текстовых сообщений (Message)
     */
    protected function handleMessage($message): void
    {
        $chatId    = $message->getChat()->getId();
        $text      = trim($message->getText() ?? '');
        $from      = $message->getFrom();
        $telegramId = $from->getId();

        // 1) Сначала проверяем, есть ли запись о пользователе в БД (по telegram_id)
        $user = $this->findUserByTelegramId($telegramId);

        // Если пользователь открывает бота по ссылке вида ...?start=PHONE
        // и в базе нет записи по telegram_id, пробуем сопоставить его по номеру
        if (!$user && preg_match('/^\/start\s+(\d{11})$/', $text, $m)) {
            $phone = $m[1];
            $stmt  = $this->pdo->prepare("SELECT id FROM users WHERE phone = ?");
            $stmt->execute([$phone]);
            $byPhone = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($byPhone) {
                $stmtUpd = $this->pdo->prepare("UPDATE users SET telegram_id = ?, chat_id = ? WHERE id = ?");
                $stmtUpd->execute([$telegramId, $chatId, (int)$byPhone['id']]);
                $user = $this->findUserByTelegramId($telegramId);
            }
        }

        // Если пользователь найден, обновляем chat_id при необходимости
        if ($user && ((int)($user['chat_id'] ?? 0) !== $chatId)) {
            $stmt = $this->pdo->prepare("UPDATE users SET chat_id = ? WHERE id = ?");
            $stmt->execute([$chatId, (int)$user['id']]);
        }

        // Если пользователь ещё не передал контакт, но жмёт /start или что-то ещё
        if (!$user) {
            // Если пользователь прислал контакт
            if ($contact = $message->getContact()) {
                $this->handleContact($contact, $chatId, $telegramId);
            } else {
                // Просим поделиться номером телефона
                $this->requestPhone($chatId);
            }
            return;
        }

        // Если пользователь есть, обрабатываем команды
        switch (true) {
            case preg_match('/^\/start(?:\s+\S+)?$/', $text):
                $this->showMainMenu($chatId);
                break;

            case $text === '📋 Меню':
                $this->sendProductList($chatId);
                break;

            case preg_match('/^Кол-во:\s*(\d+)$/u', $text, $qtyMatch):
                $quantity = (int)$qtyMatch[1];
                $this->confirmOrder($chatId, $telegramId, $quantity);
                break;

            case $text === 'Да, заказать':
                $this->placeOrder($chatId, $telegramId);
                break;

            case $text === 'Отмена':
                $this->telegram->sendMessage([
                    'chat_id' => $chatId,
                    'text'    => 'Заказ отменён. Если нужно — снова нажмите «📋 Меню».',
                ]);
                break;

            default:
                $this->telegram->sendMessage([
                    'chat_id' => $chatId,
                    'text'    => 'Извините, не понял команду. Нажмите «📋 Меню» для начала заказа.',
                    'reply_markup' => json_encode([
                        'keyboard' => [
                            [['text' => '📋 Меню']],
                        ],
                        'resize_keyboard' => true,
                    ]),
                ]);
                break;
        }
    }

    /**
     * Обработка callback_query (Inline-кнопки с выбором товара)
     */
    protected function handleCallbackData($callbackQuery): void
    {
        $data    = $callbackQuery->getData(); // например, "choose_3"
        $chatId  = $callbackQuery->getMessage()->getChat()->getId();
        $telegramId = $callbackQuery->getFrom()->getId();

        if (preg_match('/^choose_(\d+)$/', $data, $m)) {
            $productId = (int)$m[1];
            $this->askQuantity($chatId, $productId);
        }

        // Обязательно отвечаем Telegram, чтобы не висело «часики»
        $this->telegram->answerCallbackQuery([
            'callback_query_id' => $callbackQuery->getId(),
        ]);
    }

    /**
     * Просим пользователя поделиться контактом (номер телефона)
     */
    protected function requestPhone(int $chatId): void
    {
        $keyboard = [
            'keyboard' => [
                [['text' => 'Поделиться номером', 'request_contact' => true]],
            ],
            'resize_keyboard' => true,
            'one_time_keyboard' => true,
        ];

        $this->telegram->sendMessage([
            'chat_id'      => $chatId,
            'text'         => 'Для продолжения работы, пожалуйста, поделитесь вашим номером телефона.',
            'reply_markup' => json_encode($keyboard),
        ]);
    }

    /**
     * Обработка контакта (contact) — сохраняем пользователя в БД
     */
    protected function handleContact($contact, int $chatId, int $telegramId): void
    {
        $phone = $contact->getPhoneNumber();
        $firstName = $contact->getFirstName() ?: 'Клиент';

        // 1) Проверяем, нет ли уже другого пользователя с таким телефоном
        $stmt = $this->pdo->prepare("SELECT id, telegram_id FROM users WHERE phone = ?");
        $stmt->execute([$phone]);
        $existing = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($existing && (int)$existing['telegram_id'] !== $telegramId) {
            // Телефон уже занят
            $this->telegram->sendMessage([
                'chat_id' => $chatId,
                'text'    => 'Этот номер уже используется другим пользователем.',
            ]);
            return;
        }

        if (!$existing) {
            // Создаём новую запись в users
            $stmtIns = $this->pdo->prepare(
                "INSERT INTO users (name, phone, telegram_id, chat_id, role) VALUES (?, ?, ?, ?, 'client')"
            );
            $stmtIns->execute([$firstName, $phone, $telegramId, $chatId]);
            $userId = (int)$this->pdo->lastInsertId();
        } else {
            // Обновляем telegram_id и chat_id у существующего пользователя
            $stmtUpd = $this->pdo->prepare("UPDATE users SET telegram_id = ?, chat_id = ? WHERE id = ?");
            $stmtUpd->execute([$telegramId, $chatId, (int)$existing['id']]);
            $userId = (int)$existing['id'];
        }

        // Сообщаем, что номер сохранён, и показываем главное меню
        $this->telegram->sendMessage([
            'chat_id' => $chatId,
            'text'    => 'Номер сохранён! Теперь вы можете начать заказ, нажав «📋 Меню».',
            'reply_markup' => json_encode([
                'keyboard' => [
                    [['text' => '📋 Меню']],
                ],
                'resize_keyboard' => true,
            ]),
        ]);
    }

    /**
     * Вывод главного меню после успешного /start или верификации
     */
    protected function showMainMenu(int $chatId): void
    {
        $this->telegram->sendMessage([
            'chat_id' => $chatId,
            'text'    => "Добро пожаловать в BerryGo! Выберите действие:",
            'reply_markup' => json_encode([
                'keyboard' => [
                    [['text' => '📋 Меню'], ['text' => '🛒 Мои заказы']],
                ],
                'resize_keyboard' => true,
            ]),
        ]);
    }

    /**
     * Отправляем список активных товаров из БД
     */
    protected function sendProductList(int $chatId): void
    {
        // Предполагаем, что в ProductsController реализован метод getAllActive()
        $productsController = new ProductsController($this->pdo);
        $products = $productsController->getAllActive(); // Вернёт массив объектов/массивов с id, variety, price, unit, image_path

        foreach ($products as $product) {
            $text  = "{$product['id']}. {$product['variety']} — {$product['price']}₽/{$product['unit']}";
            $photo = $product['image_path'] ?? null;

            if ($photo) {
                $this->telegram->sendPhoto([
                    'chat_id' => $chatId,
                    'photo'   => $photo,
                    'caption' => $text . "\nНажмите кнопку ниже, чтобы выбрать.",
                    'reply_markup' => json_encode([
                        'inline_keyboard' => [
                            [['text' => "Выбрать #{$product['id']}", 'callback_data' => "choose_{$product['id']}"]]
                        ],
                    ]),
                ]);
            } else {
                $this->telegram->sendMessage([
                    'chat_id' => $chatId,
                    'text'    => $text . "\nНажмите кнопку ниже, чтобы выбрать.",
                    'reply_markup' => json_encode([
                        'inline_keyboard' => [
                            [['text' => "Выбрать #{$product['id']}", 'callback_data' => "choose_{$product['id']}"]]
                        ],
                    ]),
                ]);
            }
        }
    }

    /**
     * Запрашиваем у пользователя количество (в килограммах)
     */
    protected function askQuantity(int $chatId, int $productId): void
    {
        // Сохраняем текущий шаг и product_id в сессию
        $_SESSION['bot'][$chatId]['step']      = 'await_qty';
        $_SESSION['bot'][$chatId]['product_id']= $productId;

        $this->telegram->sendMessage([
            'chat_id' => $chatId,
            'text'    => "Введите количество (в килограммах) для товара #{$productId}, например «Кол-во: 2».",
            'reply_markup' => json_encode([
                'keyboard' => [
                    [['text' => 'Отмена']],
                ],
                'one_time_keyboard' => true,
                'resize_keyboard'   => true,
            ]),
        ]);
    }

    /**
     * Подтверждаем детали заказа (показываем итоговую сумму)
     */
    protected function confirmOrder(int $chatId, int $telegramId, int $quantity): void
    {
        // Достаём product_id, который ранее сохранили в сессии
        $productId = $_SESSION['bot'][$chatId]['product_id'] ?? null;
        if (!$productId) {
            // Если вдруг нет product_id — просим начать сначала
            $this->telegram->sendMessage([
                'chat_id' => $chatId,
                'text'    => 'Что-то пошло не так. Пожалуйста, снова нажмите «📋 Меню».',
            ]);
            return;
        }

        // Берём данные о продукте
        $productsController = new ProductsController($this->pdo);
        $product = $productsController->find($productId); // допустим, вернёт ['id'=>.., 'variety'=>.., 'price'=>.., 'unit'=>..]

        $sum = $product['price'] * $quantity;

        // Сохраняем детали заказа в сессии для последующего подтверждения
        $_SESSION['bot'][$chatId]['quantity'] = $quantity;
        $_SESSION['bot'][$chatId]['sum']      = $sum;

        $text = "Подтвердите заказ:\n\n".
                "• Товар: {$product['variety']}\n".
                "• Количество: {$quantity} {$product['unit']}\n".
                "• Сумма: {$sum} руб.\n\n".
                "Нажмите «Да, заказать» или «Отмена».";

        $this->telegram->sendMessage([
            'chat_id' => $chatId,
            'text'    => $text,
            'reply_markup' => json_encode([
                'keyboard' => [
                    [['text' => 'Да, заказать'], ['text' => 'Отмена']],
                ],
                'one_time_keyboard' => true,
                'resize_keyboard'   => true,
            ]),
        ]);
    }

    /**
     * Фактически создаём заказ в БД и уведомляем админа
     */
    protected function placeOrder(int $chatId, int $telegramId): void
    {
        // 1) Получаем данные из сессии
        $sessionData = $_SESSION['bot'][$chatId] ?? [];
        $productId   = $sessionData['product_id'] ?? null;
        $quantity    = $sessionData['quantity'] ?? null;
        $sum         = $sessionData['sum'] ?? null;

        if (!$productId || !$quantity || !$sum) {
            $this->telegram->sendMessage([
                'chat_id' => $chatId,
                'text'    => 'Не удалось найти данные заказа. Пожалуйста, начните заново через «📋 Меню».',
            ]);
            return;
        }

        // 2) Получаем ID пользователя (user_id) по telegram_id
        $stmtUser = $this->pdo->prepare("SELECT id FROM users WHERE telegram_id = ?");
        $stmtUser->execute([$telegramId]);
        $userId = (int)$stmtUser->fetchColumn();
        if (!$userId) {
            $this->telegram->sendMessage([
                'chat_id' => $chatId,
                'text'    => 'Пользователь не найден в базе. Пожалуйста, пройдите регистрацию заново.',
            ]);
            return;
        }

        // 3) Получаем адрес пользователя (последний сохранённый в таблице users, поле address_id)
        $stmtAddr = $this->pdo->prepare("SELECT address_id FROM users WHERE id = ?");
        $stmtAddr->execute([$userId]);
        $addressId = (int)$stmtAddr->fetchColumn();
        if (!$addressId) {
            $this->telegram->sendMessage([
                'chat_id' => $chatId,
                'text'    => 'У вас не указан адрес. Пожалуйста, зайдите в профиль на сайте и добавьте адрес.',
            ]);
            return;
        }

        // 4) Создаём запись в таблице orders
        $stmtOrder = $this->pdo->prepare("
            INSERT INTO orders (user_id, address_id, total_amount, created_at)
            VALUES (?, ?, ?, NOW())
        ");
        $stmtOrder->execute([$userId, $addressId, $sum]);
        $orderId = (int)$this->pdo->lastInsertId();

        // 5) Создаём запись в order_items
        // Для unit_price делим сумму на количество
        $unitPrice = round($sum / $quantity, 2);
        $stmtItem = $this->pdo->prepare("
            INSERT INTO order_items (order_id, product_id, quantity, unit_price)
            VALUES (?, ?, ?, ?)
        ");
        $stmtItem->execute([$orderId, $productId, $quantity, $unitPrice]);

        // 6) Уведомление администратору/группе
        $textAdmin = "🆕 *Новый заказ* #{$orderId}:" . PHP_EOL .
                     "• Клиент (telegram_id): {$telegramId}" . PHP_EOL .
                     "• Товар ID: {$productId}" . PHP_EOL .
                     "• Количество: {$quantity}" . PHP_EOL .
                     "• Сумма: {$sum} руб." . PHP_EOL .
                     "[Посмотреть в админке](https://berrygo.ru/admin/orders/{$orderId})";

        // Проверяем, может быть, admin_chat_id — массив, тогда шлём всем
        $params = [
            'text'       => $textAdmin,
            'parse_mode' => 'Markdown',
        ];
        if ($this->adminTopicId !== null) {
            $params['message_thread_id'] = $this->adminTopicId;
        }

        if (is_array($this->adminChatId)) {
            foreach ($this->adminChatId as $chat) {
                $params['chat_id'] = $chat;
                $this->telegram->sendMessage($params);
            }
        } else {
            $params['chat_id'] = $this->adminChatId;
            $this->telegram->sendMessage($params);
        }

        // 7) Отправляем подтверждение клиенту
        $this->telegram->sendMessage([
            'chat_id' => $chatId,
            'text'    => "Спасибо! Ваш заказ №{$orderId} принят и будет обработан в ближайшее время.",
            'reply_markup' => json_encode([
                'keyboard' => [
                    [['text' => '📋 Меню'], ['text' => '🛒 Мои заказы']],
                ],
                'resize_keyboard' => true,
            ]),
        ]);

        // 8) Очищаем сессионные данные по заказу
        unset($_SESSION['bot'][$chatId]);
    }

    /**
     * Ищем пользователя в таблице users по telegram_id
     */
    protected function findUserByTelegramId(int $telegramId): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE telegram_id = ?");
        $stmt->execute([$telegramId]);
        $user = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $user ?: null;
    }
}
