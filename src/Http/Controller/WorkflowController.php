<?php
declare(strict_types=1);
namespace PHPA2E\Http\Controller;

use PHPA2E\A2E;
use PHPA2E\Http\Router;

final class WorkflowController
{
    public static function register(Router $router, A2E $a2e, callable $getAgentId): void
    {
        $router->post('/api/v1/workflows/validate', function (array $data) use ($a2e) {
            $jsonl = $data['workflow'] ?? '';
            if ($jsonl === '') return [400, ['error' => 'Required: workflow']];
            return [200, $a2e->validate($jsonl)];
        });

        $router->post('/api/v1/workflows/execute', function (array $data) use ($a2e, $getAgentId) {
            $jsonl = $data['workflow'] ?? '';
            if ($jsonl === '') return [400, ['error' => 'Required: workflow']];
            $validate = $data['validate'] ?? true;
            $result = $a2e->execute($jsonl, $getAgentId(), $validate);
            $status = $result->status === 'error' ? 400 : 200;
            return [$status, $result->jsonSerialize()];
        });
    }
}
