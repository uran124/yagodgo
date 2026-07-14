<?php

use PHPUnit\Framework\TestCase;

final class ClientMixedCartTest extends TestCase
{
    public function testCartMigrationAllowsOneProductInSeveralModes(): void
    {
        $identityMigration = file_get_contents(__DIR__ . '/../database/20260713_cart_items_identity.sql');
        $keyMigration = file_get_contents(__DIR__ . '/../database/20260713_cart_items_purchase_batch_key.sql');
        $dump = file_get_contents(__DIR__ . '/../database/db.sql');

        $this->assertStringContainsString('ADD COLUMN id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT FIRST', $identityMigration);
        $this->assertStringContainsString('ADD PRIMARY KEY (id)', $identityMigration);
        $this->assertStringContainsString('ADD COLUMN purchase_batch_key BIGINT UNSIGNED', $keyMigration);
        $this->assertStringContainsString('COALESCE(purchase_batch_id, 0)', $keyMigration);
        $this->assertStringContainsString('uniq_cart_items_user_product_mode_batch_key (user_id, product_id, stock_mode, purchase_batch_key)', $keyMigration);

        $this->assertStringContainsString('`id` bigint UNSIGNED NOT NULL', $dump);
        $this->assertStringContainsString('`purchase_batch_key` bigint UNSIGNED GENERATED ALWAYS AS', $dump);
        $this->assertStringContainsString('ADD UNIQUE KEY `uniq_cart_items_user_product_mode_batch_key` (`user_id`,`product_id`,`stock_mode`,`purchase_batch_key`)', $dump);
    }

    public function testCartControllerUsesCartItemIdentityInsteadOfProductIdentity(): void
    {
        $controller = file_get_contents(__DIR__ . '/../src/Controllers/ClientController.php');
        $cartView = file_get_contents(__DIR__ . '/../src/Views/client/cart.php');

        $this->assertStringNotContainsString('Этот товар уже есть в корзине в другом режиме', $controller);
        $this->assertStringContainsString('ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)', $controller);
        $this->assertStringContainsString('quantity = quantity + VALUES(quantity)', $controller);
        $this->assertStringContainsString('WHERE user_id = ? AND id = ?', $controller);
        $this->assertStringContainsString('DELETE FROM cart_items WHERE user_id = ? AND id = ?', $controller);
        $this->assertStringContainsString('name="cart_item_id"', $cartView);
        $this->assertStringContainsString('stockMode . \'|\' . $deliveryDate', $controller);
    }


    public function testCheckoutGroupsByCartItemDateAndMode(): void
    {
        $controller = file_get_contents(__DIR__ . '/../src/Controllers/ClientController.php');

        $this->assertStringContainsString('ci.id AS cart_item_id', $controller);
        $this->assertStringContainsString('ci.purchase_batch_id', $controller);
        $this->assertStringContainsString('DATE(pb.purchased_at) AS batch_delivery_date', $controller);
        $this->assertStringContainsString('$_SESSION[\'delivery_date\'][$cartItemId]', $controller);
        $this->assertStringContainsString('$this->cartGroupKey($stockMode, $deliveryDate)', $controller);
        $this->assertStringContainsString('$this->cartGroupKey($stockMode, (string)$date)', $controller);
    }

    public function testClientCheckoutCreatesLinkedOrderGroup(): void
    {
        $controller = file_get_contents(__DIR__ . '/../src/Controllers/ClientController.php');

        $this->assertStringContainsString('createForClientCheckout', $controller);
        $placeOrder = substr($controller, strpos($controller, 'public function placeOrder'));
        $this->assertStringNotContainsString('INSERT INTO orders', $placeOrder);
        $this->assertStringNotContainsString('INSERT INTO order_groups', $placeOrder);
        $this->assertStringContainsString('cart_item_ids_to_delete', $placeOrder);
    }

    public function testClientFacingCopyDoesNotMentionInternalPurchaseWorkflow(): void
    {
        $clientFiles = [
            __DIR__ . '/../src/Views/client/orders.php',
            __DIR__ . '/../src/Views/client/order_show.php',
            __DIR__ . '/../src/Views/client/cart.php',
            __DIR__ . '/../src/Views/client/checkout.php',
        ];
        foreach ($clientFiles as $file) {
            $content = file_get_contents($file);
            $this->assertStringNotContainsString('после выкупа', $content, $file);
            $this->assertSame(0, preg_match('/закупк/iu', $content), $file);
        }

        $controller = file_get_contents(__DIR__ . '/../src/Controllers/ClientController.php');
        $this->assertStringNotContainsString('Этот товар уже есть в корзине в другом режиме', $controller);
        $this->assertStringNotContainsString('Точная цена будет после выкупа', $controller);
        $this->assertStringContainsString('Дата поступления уточняется', $controller);
        $this->assertStringContainsString('Поставка подтверждена', $controller);
    }

    public function testClientPreorderUsesOnlyConfirmedSellableBatches(): void
    {
        $resolver = file_get_contents(__DIR__ . '/../src/Services/SellableBatchResolver.php');
        $controller = file_get_contents(__DIR__ . '/../src/Controllers/ClientController.php');
        $catalog = file_get_contents(__DIR__ . '/../src/Services/ClientCatalogService.php');

        $this->assertStringContainsString("pb.purchased_at IS NOT NULL", $resolver);
        $this->assertStringContainsString('{$plannedAvailableExpr} > 0', $resolver);
        $this->assertStringContainsString('AND pb.preorder_price_per_box > 0', $resolver);
        $this->assertStringContainsString("resolveForProduct(\$productId, 'preorder')", $controller);
        $this->assertStringContainsString('AND pb_p.purchased_at IS NOT NULL', $catalog);
        $this->assertStringContainsString('AND pb_p.preorder_price_per_box > 0', $catalog);
    }

}
