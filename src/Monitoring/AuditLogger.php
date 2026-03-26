<?php
declare(strict_types=1);
namespace PHPA2E\Monitoring;

final class AuditLogger
{
    private string $logDir;

    public function __construct(string $logDir = 'logs')
    {
        $this->logDir = rtrim($logDir, '/\\');
        if (!is_dir($this->logDir)) {
            mkdir($this->logDir, 0755, true);
        }
    }

    public function logExecutionStart(string $executionId, string $agentId, string $workflowJsonl): void
    {
        $this->append([
            'event' => 'execution_start',
            'execution_id' => $executionId,
            'agent_id' => $agentId,
            'workflow_size' => strlen($workflowJsonl),
            'timestamp' => date('c'),
        ]);
    }

    public function logOperationResult(string $executionId, string $operationId, string $status, ?float $durationMs = null, ?string $error = null): void
    {
        $this->append(array_filter([
            'event' => 'operation_result',
            'execution_id' => $executionId,
            'operation_id' => $operationId,
            'status' => $status,
            'duration_ms' => $durationMs ? round($durationMs, 1) : null,
            'error' => $error,
            'timestamp' => date('c'),
        ], fn($v) => $v !== null));
    }

    public function logExecutionComplete(string $executionId, string $status, float $durationMs): void
    {
        $this->append([
            'event' => 'execution_complete',
            'execution_id' => $executionId,
            'status' => $status,
            'duration_ms' => round($durationMs, 1),
            'timestamp' => date('c'),
        ]);
    }

    /** Query logs by execution ID. */
    public function getExecution(string $executionId): array
    {
        $entries = [];
        $file = $this->logDir . '/executions_' . date('Ymd') . '.jsonl';
        if (!file_exists($file)) return $entries;

        foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $entry = json_decode($line, true);
            if (($entry['execution_id'] ?? '') === $executionId) {
                $entries[] = $entry;
            }
        }
        return $entries;
    }

    private function append(array $entry): void
    {
        $file = $this->logDir . '/executions_' . date('Ymd') . '.jsonl';
        file_put_contents($file, json_encode($entry) . "\n", FILE_APPEND | LOCK_EX);
    }
}
