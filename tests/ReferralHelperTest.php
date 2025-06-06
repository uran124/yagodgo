<?php
namespace Tests;

use PHPUnit\Framework\TestCase;
use PDO;

class ReferralHelperTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        if (!class_exists('App\\Helpers\\ReferralHelper')) {
            require_once __DIR__ . '/../src/Helpers/ReferralHelper.php';
        }
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, referral_code TEXT)');
        $this->pdo->exec("INSERT INTO users (referral_code) VALUES ('ABCDEFG1'), ('ABCDEFG2')");
    }

    public function testGenerateUniqueCode(): void
    {
        $code = \App\Helpers\ReferralHelper::generateUniqueCode($this->pdo, 8);
        $this->assertSame(8, strlen($code));
        $this->assertMatchesRegularExpression('/^[ABCDEFGHJKLMNPQRSTUVWXYZ23456789]+$/', $code);
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM users WHERE referral_code = ?');
        $stmt->execute([$code]);
        $this->assertSame('0', $stmt->fetchColumn());
    }
}
