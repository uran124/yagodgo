<?php
declare(strict_types=1);
namespace Tests;
use App\Services\Florix24InboundJournalService;
use PDO;
use PHPUnit\Framework\TestCase;

final class Florix24InboundJournalServiceTest extends TestCase
{
    public function testFiltersInboundJournalWithoutExposingPayloads(): void
    {
        $pdo=new PDO('sqlite::memory:');$pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE integration_request_logs (id INTEGER PRIMARY KEY,source TEXT,endpoint TEXT,http_status INTEGER,external_order_id TEXT,partner_user_id INTEGER,points_used INTEGER,error_code TEXT,correlation_id TEXT,processing_ms INTEGER,request_payload TEXT,response_payload TEXT,created_at TEXT)');
        $pdo->exec("INSERT INTO integration_request_logs VALUES (1,'florix24','/api/v1/integrations/florix/orders',200,'FLORIX-1',45,350,NULL,'abc',10,'{\"token\":\"secret\"}','{}','2026-07-23 10:00:00')");
        $result=(new Florix24InboundJournalService($pdo))->search(['external_order_id'=>'FLORIX-1']);
        $this->assertSame(1,$result['total']);$this->assertSame('abc',$result['rows'][0]['correlation_id']);$this->assertArrayNotHasKey('request_payload',$result['rows'][0]);
    }
}
