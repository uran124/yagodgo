<?php
namespace Tests;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use App\Middleware\CsrfMiddleware;

class CsrfProtectionTest extends TestCase
{
    protected function setUp(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        require_once __DIR__ . '/../src/helpers.php';
        require_once __DIR__ . '/../src/Middleware/CsrfMiddleware.php';
        $_SESSION = [];
        $_POST = [];
        unset($_SERVER['HTTP_X_CSRF_TOKEN']);
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        $_POST = [];
        unset($_SERVER['HTTP_X_CSRF_TOKEN']);
    }

    public function testCsrfRequestTokenReadsPostBeforeHeader(): void
    {
        $_POST['csrf_token'] = 'from-post';
        $_SERVER['HTTP_X_CSRF_TOKEN'] = 'from-header';

        $this->assertSame('from-post', csrf_request_token());
    }

    public function testCsrfRequestTokenReadsAjaxHeader(): void
    {
        $_SERVER['HTTP_X_CSRF_TOKEN'] = 'from-header';

        $this->assertSame('from-header', csrf_request_token());
    }

    public function testMachineToMachineCallbacksAreCsrfExempt(): void
    {
        $this->assertTrue(is_csrf_exempt_path('/telegram/webhook'));
        $this->assertTrue(is_csrf_exempt_path('/telegram/callback'));
        $this->assertTrue(is_csrf_exempt_path('/payments/robokassa/result'));
        $this->assertTrue(is_csrf_exempt_path('/api/integrations/florix24/order-status'));
        $this->assertFalse(is_csrf_exempt_path('/admin/orders/status'));
    }

    public function testCsrfMiddlewareProtectsOnlyBrowserPostRoutes(): void
    {
        $middleware = new CsrfMiddleware();

        $this->assertFalse($middleware->shouldProtect('GET', '/admin/orders/status'));
        $this->assertFalse($middleware->shouldProtect('POST', '/telegram/webhook'));
        $this->assertFalse($middleware->shouldProtect('POST', '/payments/robokassa/result'));
        $this->assertFalse($middleware->shouldProtect('POST', '/api/integrations/florix24/order-status'));
        $this->assertTrue($middleware->shouldProtect('POST', '/admin/orders/status'));
        $this->assertTrue($middleware->shouldProtect('post', '/checkout'));
    }

    public function testAllPostFormsRenderCsrfField(): void
    {
        $missing = [];
        $root = dirname(__DIR__) . '/src/Views';
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $contents = (string) file_get_contents($file->getPathname());
            if (!preg_match_all('/<form\b.*?<\/form>/is', $contents, $matches, PREG_OFFSET_CAPTURE)) {
                continue;
            }

            foreach ($matches[0] as [$form, $offset]) {
                if (!preg_match('/method\s*=\s*(["\'])?post\1?/i', $form)) {
                    continue;
                }

                if (strpos($form, 'csrf_field') === false && strpos($form, 'csrf_token') === false) {
                    $line = substr_count(substr($contents, 0, $offset), "\n") + 1;
                    $missing[] = str_replace(dirname(__DIR__) . '/', '', $file->getPathname()) . ':' . $line;
                }
            }
        }

        $this->assertSame([], $missing);
    }

    public function testIntegrationTokenLifecycleFormIsNotNestedInSettingsForm(): void
    {
        $view = (string) file_get_contents(dirname(__DIR__) . '/src/Views/admin/settings.php');
        $integrationStart = strpos($view, "<?php if (\$activeSection === 'integrations'): ?>");
        $tokenForm = strpos($view, 'action="/admin/settings/integrations/florix24/inbound-token"');
        $settingsFormAfterToken = strpos($view, '<form action="<?= htmlspecialchars($sectionUrl($activeSection)) ?>" method="post"', $tokenForm);

        $this->assertNotFalse($integrationStart);
        $this->assertNotFalse($tokenForm);
        $this->assertNotFalse($settingsFormAfterToken);
        $this->assertLessThan($tokenForm, strpos($view, '</form>', $integrationStart));
        $this->assertLessThan($settingsFormAfterToken, $tokenForm);
    }
}
