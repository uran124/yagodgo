<?php

use PHPUnit\Framework\TestCase;

final class ClientMixedCartTest extends TestCase
{
    public function testCartMigrationAllowsOneProductInSeveralModes(): void
    {
        $migration = file_get_contents(__DIR__ . '/../database/20260713_cart_items_identity.sql');
        $dump = file_get_contents(__DIR__ . '/../database/db.sql');

        $this->assertStringContainsString('ADD COLUMN id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT FIRST', $migration);
        $this->assertStringContainsString('ADD PRIMARY KEY (id)', $migration);
        $this->assertStringContainsString('uniq_cart_items_user_product_mode_batch (user_id, product_id, stock_mode, purchase_batch_id)', $migration);

        $this->assertStringContainsString('`id` bigint UNSIGNED NOT NULL', $dump);
        $this->assertStringContainsString('ADD UNIQUE KEY `uniq_cart_items_user_product_mode_batch` (`user_id`,`product_id`,`stock_mode`,`purchase_batch_id`)', $dump);
    }

    public function testCartControllerUsesCartItemIdentityInsteadOfProductIdentity(): void
    {
        $controller = file_get_contents(__DIR__ . '/../src/Controllers/ClientController.php');
        $cartView = file_get_contents(__DIR__ . '/../src/Views/client/cart.php');

        $this->assertStringNotContainsString('Этот товар уже есть в корзине в другом режиме', $controller);
        $this->assertStringContainsString('ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)', $controller);
        $this->assertStringContainsString('WHERE user_id = ? AND id = ?', $controller);
        $this->assertStringContainsString('DELETE FROM cart_items WHERE user_id = ? AND id = ?', $controller);
        $this->assertStringContainsString('name="cart_item_id"', $cartView);
        $this->assertStringContainsString('stockMode . \'|\' . $deliveryDate', $controller);
    }

    public function testClientCheckoutCreatesLinkedOrderGroup(): void
    {
        $controller = file_get_contents(__DIR__ . '/../src/Controllers/ClientController.php');

        $this->assertStringContainsString('INSERT INTO order_groups (user_id, created_by_user_id, comment)', $controller);
        $this->assertStringContainsString('user_id, order_group_id, address_id', $controller);
        $this->assertStringContainsString('$orderGroupId', $controller);
    }
}
