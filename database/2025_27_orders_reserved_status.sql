-- Добавляем статус "reserved" для заказов-броней
ALTER TABLE orders
    MODIFY COLUMN status ENUM('reserved','new','processing','assigned','delivered','cancelled')
    NOT NULL DEFAULT 'new';

