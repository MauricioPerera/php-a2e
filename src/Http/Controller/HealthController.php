<?php
declare(strict_types=1);
namespace PHPA2E\Http\Controller;

use PHPA2E\A2E;
use PHPA2E\Http\Router;

final class HealthController
{
    public static function register(Router $router, A2E $a2e): void
    {
        $router->get('/health', fn() => [200, ['status' => 'ok', 'version' => '0.1.0', 'operations' => count($a2e->operations->types())]]);
    }
}
