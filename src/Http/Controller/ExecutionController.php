<?php
declare(strict_types=1);
namespace PHPA2E\Http\Controller;

use PHPA2E\A2E;
use PHPA2E\Http\Router;

final class ExecutionController
{
    public static function register(Router $router, A2E $a2e): void
    {
        $router->get('/api/v1/executions/{id}', function (array $data, array $params) use ($a2e) {
            $id = $params['id'] ?? '';
            $entries = $a2e->audit->getExecution($id);
            if (empty($entries)) return [404, ['error' => "Execution not found: {$id}"]];
            return [200, ['execution_id' => $id, 'timeline' => $entries]];
        });

        $router->get('/api/v1/rate-limit/status', function (array $data, array $params, array $query) use ($a2e) {
            $agentId = $query['agent_id'] ?? 'default';
            return [200, $a2e->rateLimiter->status($agentId)];
        });
    }
}
