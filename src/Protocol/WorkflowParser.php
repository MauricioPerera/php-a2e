<?php

declare(strict_types=1);

namespace PHPA2E\Protocol;

/**
 * Parses JSONL workflow strings into structured operations and execution order.
 *
 * JSONL format: each line is a JSON object with "type" field:
 *   - "operationUpdate": defines/updates an operation
 *   - "beginExecution": signals start with operation order
 */
final class WorkflowParser
{
    /**
     * Parse a JSONL workflow string.
     *
     * @return array{operations: array<string, array>, executionOrder: string[], executionId: string}
     */
    public static function parse(string $jsonl): array
    {
        $operations = [];
        $executionOrder = [];
        $executionId = '';

        $lines = array_filter(array_map('trim', explode("\n", $jsonl)));

        foreach ($lines as $lineNum => $line) {
            if ($line === '') continue;

            $msg = json_decode($line, true);
            if (!is_array($msg)) {
                throw new \RuntimeException("Invalid JSON at line " . ($lineNum + 1));
            }

            $type = $msg['type'] ?? '';

            match ($type) {
                'operationUpdate' => self::processOperationUpdate($msg, $operations),
                'beginExecution' => self::processBeginExecution($msg, $executionOrder, $executionId),
                default => null, // Ignore unknown types
            };
        }

        // Auto-synthesize beginExecution if missing
        if (empty($executionOrder) && !empty($operations)) {
            $executionOrder = array_keys($operations);
            $executionId = 'auto-' . bin2hex(random_bytes(4));
        }

        return [
            'operations' => $operations,
            'executionOrder' => $executionOrder,
            'executionId' => $executionId,
        ];
    }

    private static function processOperationUpdate(array $msg, array &$operations): void
    {
        $opId = $msg['operationId'] ?? '';
        $operation = $msg['operation'] ?? [];

        if ($opId === '' || empty($operation)) {
            return;
        }

        // Extract the single operation type
        $opType = array_key_first($operation);
        $opConfig = $operation[$opType] ?? [];

        $operations[$opId] = [
            'id' => $opId,
            'type' => $opType,
            'config' => $opConfig,
        ];
    }

    private static function processBeginExecution(array $msg, array &$executionOrder, string &$executionId): void
    {
        $executionId = $msg['executionId'] ?? 'exec-' . bin2hex(random_bytes(4));
        $executionOrder = $msg['operationOrder'] ?? [];
    }
}
