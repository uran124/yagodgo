<?php
namespace Tests;

use PHPUnit\Framework\TestCase;

class RouteSignatureTest extends TestCase
{
    public function testRouteExactAndRegexSignaturesAreUnique(): void
    {
        $duplicates = [];
        $seen = [];
        $routesDir = dirname(__DIR__) . '/routes';

        foreach (glob($routesDir . '/*.php') ?: [] as $file) {
            if (basename($file) === 'helpers.php') {
                continue;
            }

            $contents = (string) file_get_contents($file);
            preg_match_all(
                "/\\b(routeExact|routeRegex)\\(\\s*'([^']+)'\\s*,\\s*'([^']+)'/",
                $contents,
                $matches,
                PREG_SET_ORDER | PREG_OFFSET_CAPTURE
            );

            foreach ($matches as $match) {
                $kind = $match[1][0];
                $method = $match[2][0];
                $pattern = $match[3][0];
                $signature = $kind . ' ' . $method . ' ' . $pattern;
                $line = substr_count(substr($contents, 0, $match[0][1]), "\n") + 1;
                $location = str_replace(dirname(__DIR__) . '/', '', $file) . ':' . $line;

                if (isset($seen[$signature])) {
                    $duplicates[] = $signature . ' at ' . $seen[$signature] . ' and ' . $location;
                    continue;
                }

                $seen[$signature] = $location;
            }
        }

        $this->assertSame([], $duplicates);
    }
}
