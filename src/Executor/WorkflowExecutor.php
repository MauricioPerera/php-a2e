<?php

declare(strict_types=1);

namespace PHPA2E\Executor;

use PHPA2E\Operation\OperationRegistry;
use PHPA2E\Protocol\WorkflowParser;
use PHPA2E\Credentials\CredentialInjector;

final class WorkflowExecutor
{
    public function __construct(
        private readonly OperationRegistry $registry,
        private readonly ?CredentialInjector $credentialInjector = null,
    ) {}

    /**
     * Execute a JSONL workflow string.
     */
    public function execute(string $jsonl): ExecutionResult
    {
        $start = hrtime(true);

        $parsed = WorkflowParser::parse($jsonl);
        $operations = $parsed['operations'];
        $order = $parsed['executionOrder'];
        $executionId = $parsed['executionId'];

        $dataModel = new DataModel();
        $results = [];
        $errors = 0;

        foreach ($order as $opId) {
            $opDef = $operations[$opId] ?? null;
            if ($opDef === null) {
                $results[$opId] = ['error' => "Operation not defined: {$opId}"];
                $errors++;
                continue;
            }

            $opType = $opDef['type'];
            $opConfig = $opDef['config'];

            $handler = $this->registry->get($opType);
            if ($handler === null) {
                $results[$opId] = ['error' => "Unknown operation type: {$opType}"];
                $errors++;
                continue;
            }

            // Inject credentials if available
            if ($this->credentialInjector !== null && $opType === 'ApiCall') {
                $opConfig = $this->credentialInjector->inject($opConfig);
            }

            try {
                $opStart = hrtime(true);
                $result = $handler->execute($opConfig, $dataModel);
                $opMs = (hrtime(true) - $opStart) / 1_000_000;

                // Handle Conditional branching
                if ($opType === 'Conditional' && !empty($result['execute'])) {
                    foreach ($result['execute'] as $branchOpId) {
                        if (isset($operations[$branchOpId])) {
                            $branchDef = $operations[$branchOpId];
                            $branchHandler = $this->registry->get($branchDef['type']);
                            if ($branchHandler) {
                                $branchConfig = $branchDef['config'];
                                if ($this->credentialInjector && $branchDef['type'] === 'ApiCall') {
                                    $branchConfig = $this->credentialInjector->inject($branchConfig);
                                }
                                $results[$branchOpId] = $branchHandler->execute($branchConfig, $dataModel);
                            }
                        }
                    }
                }

                $result['_duration_ms'] = round($opMs, 1);
                $results[$opId] = $result;
            } catch (\Throwable $e) {
                $results[$opId] = [
                    'error' => $e->getMessage(),
                    'type' => get_class($e),
                ];
                $errors++;
            }
        }

        $durationMs = (hrtime(true) - $start) / 1_000_000;

        $status = match (true) {
            $errors === 0 => 'success',
            $errors < count($order) => 'partial_success',
            default => 'error',
        };

        return new ExecutionResult(
            executionId: $executionId,
            status: $status,
            results: $results,
            durationMs: $durationMs,
            error: $errors > 0 ? "{$errors} operation(s) failed" : null,
        );
    }
}
