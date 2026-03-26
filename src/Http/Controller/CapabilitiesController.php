<?php
declare(strict_types=1);
namespace PHPA2E\Http\Controller;

use PHPA2E\A2E;
use PHPA2E\Http\Router;

final class CapabilitiesController
{
    public static function register(Router $router, A2E $a2e, callable $getAgentId): void
    {
        $router->get('/api/v1/capabilities', function () use ($a2e, $getAgentId) {
            return [200, $a2e->capabilities($getAgentId())];
        });
    }
}
