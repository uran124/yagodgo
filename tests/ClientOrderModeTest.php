<?php
namespace Tests;

use App\Controllers\ClientController;
use PDO;
use PHPUnit\Framework\TestCase;

class ClientOrderModeTest extends TestCase
{
    public function testNormalizeOrderModesFallsBackToDefaultModes(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $controller = new ClientController($pdo);

        $itemsByDate = [
            (defined('PLACEHOLDER_DATE') ? PLACEHOLDER_DATE : '2025-05-15') => [1 => ['quantity' => 1]],
            '2026-05-20' => [2 => ['quantity' => 1]],
        ];

        $modes = $controller->normalizeOrderModes($itemsByDate, [
            '2026-05-20' => 'wrong_mode',
        ]);

        $this->assertSame('preorder', $modes[(defined('PLACEHOLDER_DATE') ? PLACEHOLDER_DATE : '2025-05-15')]);
        $this->assertSame('instant', $modes['2026-05-20']);
    }


    public function testNormalizeOrderModesKeepsPostedPreorderForRealDesiredDate(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $controller = new ClientController($pdo);

        $modes = $controller->normalizeOrderModes([
            '2026-06-12' => [7 => ['quantity' => 1]],
        ], [
            '2026-06-12' => 'preorder',
        ]);

        $this->assertSame('preorder', $modes['2026-06-12']);
    }

    public function testShouldDisableRewardsForDiscountStock(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $controller = new ClientController($pdo);

        $this->assertTrue($controller->shouldDisableRewardsForModes([
            '2026-05-20' => 'discount_stock',
        ]));

        $this->assertFalse($controller->shouldDisableRewardsForModes([
            '2026-05-20' => 'instant',
            (defined('PLACEHOLDER_DATE') ? PLACEHOLDER_DATE : '2025-05-15') => 'preorder',
        ]));
    }
}
