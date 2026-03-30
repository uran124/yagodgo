<?php

declare(strict_types=1);

function routeExact(string $expectedMethod, string $expectedUri, string $method, string $uri): bool
{
    return $method === $expectedMethod && $uri === $expectedUri;
}

function routeRegex(string $expectedMethod, string $pattern, string $method, string $uri, ?array &$matches = null): bool
{
    if ($method !== $expectedMethod) {
        return false;
    }

    return (bool) preg_match($pattern, $uri, $matches);
}
