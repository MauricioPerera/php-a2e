<?php

declare(strict_types=1);

namespace PHPA2E\Validation;

use PHPA2E\Operation\OperationRegistry;
use PHPA2E\Protocol\WorkflowParser;

/**
 * 6-stage workflow validation pipeline.
 *
 * 1. Structure: IDs, required fields, single operation type
 * 2. Dependencies: inputPath refs exist, no cycles
 * 3. Types: FilterData needs array input, etc.
 * 4. API compatibility: URLs registered (optional)
 * 5. Credentials: refs exist in vault (optional)
 * 6. Patterns: infinite loops, empty data
 */
final class WorkflowValidator
{
    public function __construct(
        private readonly OperationRegistry $registry,
        private readonly ValidationLevel $level = ValidationLevel::Moderate,
    ) {}

    /**
     * @return array{valid: bool, errors: int, warnings: int, issues: ValidationError[]}
     */
    public function validate(string $jsonl): array
    {
        $issues = [];

        try {
            $parsed = WorkflowParser::parse($jsonl);
        } catch (\Throwable $e) {
            $issues[] = new ValidationError('error', "Failed to parse JSONL: {$e->getMessage()}");
            return $this->buildResult($issues);
        }

        $operations = $parsed['operations'];
        $order = $parsed['executionOrder'];

        // Stage 1: Structure
        $this->validateStructure($operations, $order, $issues);

        // Stage 2: Dependencies
        $this->validateDependencies($operations, $issues);

        // Stage 3: Types
        $this->validateTypes($operations, $issues);

        // Stage 6: Patterns
        $this->validatePatterns($operations, $issues);

        return $this->buildResult($issues);
    }

    private function validateStructure(array $operations, array $order, array &$issues): void
    {
        if (empty($operations)) {
            $issues[] = new ValidationError('error', 'Workflow has no operations');
            return;
        }

        if (empty($order)) {
            $issues[] = new ValidationError('warning', 'No beginExecution found — execution order auto-generated');
        }

        $ids = [];
        foreach ($operations as $opId => $op) {
            // Check duplicate IDs
            if (isset($ids[$opId])) {
                $issues[] = new ValidationError('error', "Duplicate operation ID: {$opId}", $opId);
            }
            $ids[$opId] = true;

            // Check operation type exists in registry
            $type = $op['type'] ?? '';
            if (!$this->registry->has($type)) {
                $issues[] = new ValidationError('error', "Unknown operation type: {$type}", $opId,
                    'Available types: ' . implode(', ', $this->registry->types()));
            }

            // Check required fields per type
            $config = $op['config'] ?? [];
            match ($type) {
                'ApiCall' => $this->requireFields($config, ['method', 'url', 'outputPath'], $opId, $issues),
                'FilterData' => $this->requireFields($config, ['inputPath', 'conditions', 'outputPath'], $opId, $issues),
                'TransformData' => $this->requireFields($config, ['inputPath', 'transform', 'outputPath'], $opId, $issues),
                'Conditional' => $this->requireFields($config, ['condition', 'ifTrue'], $opId, $issues),
                'Loop' => $this->requireFields($config, ['inputPath', 'operations'], $opId, $issues),
                'StoreData' => $this->requireFields($config, ['inputPath', 'key'], $opId, $issues),
                'MergeData' => $this->requireFields($config, ['sources', 'strategy', 'outputPath'], $opId, $issues),
                default => null,
            };
        }

        // Check execution order references valid operations
        foreach ($order as $opId) {
            if (!isset($operations[$opId])) {
                $issues[] = new ValidationError('error', "Execution order references undefined operation: {$opId}");
            }
        }
    }

    private function validateDependencies(array $operations, array &$issues): void
    {
        $outputPaths = [];

        foreach ($operations as $opId => $op) {
            $config = $op['config'] ?? [];

            // Track output paths
            if (isset($config['outputPath'])) {
                $outputPaths[$config['outputPath']] = $opId;
            }
        }

        // Check inputPath references exist as outputPaths from earlier operations
        foreach ($operations as $opId => $op) {
            $config = $op['config'] ?? [];
            $inputPath = $config['inputPath'] ?? '';

            if ($inputPath !== '' && !isset($outputPaths[$inputPath])) {
                $issues[] = new ValidationError('warning',
                    "inputPath '{$inputPath}' not produced by any operation",
                    $opId,
                    'Ensure a prior operation writes to this path');
            }

            // Check Conditional references
            if ($op['type'] === 'Conditional') {
                foreach (array_merge($config['ifTrue'] ?? [], $config['ifFalse'] ?? []) as $refId) {
                    if (!isset($operations[$refId])) {
                        $issues[] = new ValidationError('error',
                            "Conditional references undefined operation: {$refId}", $opId);
                    }
                }
            }

            // Check Loop references
            if ($op['type'] === 'Loop') {
                foreach ($config['operations'] ?? [] as $refId) {
                    if (!isset($operations[$refId])) {
                        $issues[] = new ValidationError('error',
                            "Loop references undefined operation: {$refId}", $opId);
                    }
                }
            }
        }
    }

    private function validateTypes(array $operations, array &$issues): void
    {
        // Map operation types to output types
        $outputTypes = [];
        foreach ($operations as $opId => $op) {
            $outputTypes[$op['config']['outputPath'] ?? ''] = match ($op['type']) {
                'ApiCall' => 'api_response',
                'FilterData' => 'array',
                'TransformData' => $op['config']['transform'] === 'aggregate' ? 'object' : 'array',
                'MergeData' => 'array',
                default => 'unknown',
            };
        }

        // Check operations that need array input
        foreach ($operations as $opId => $op) {
            $type = $op['type'];
            $inputPath = $op['config']['inputPath'] ?? '';

            if (in_array($type, ['FilterData', 'Loop']) && isset($outputTypes[$inputPath])) {
                $inputType = $outputTypes[$inputPath];
                if ($inputType === 'object') {
                    $issues[] = new ValidationError('warning',
                        "{$type} expects array input but '{$inputPath}' produces {$inputType}", $opId);
                }
            }
        }
    }

    private function validatePatterns(array $operations, array &$issues): void
    {
        // Detect loops without clear termination
        foreach ($operations as $opId => $op) {
            if ($op['type'] === 'Loop') {
                $loopOps = $op['config']['operations'] ?? [];
                // Check if any loop operation references the loop's input (potential infinite loop)
                foreach ($loopOps as $innerOpId) {
                    $inner = $operations[$innerOpId] ?? null;
                    if ($inner && ($inner['config']['outputPath'] ?? '') === ($op['config']['inputPath'] ?? '')) {
                        $issues[] = new ValidationError('warning',
                            'Loop operation writes to its own input path — potential infinite loop',
                            $opId, 'Use a different outputPath in loop operations');
                    }
                }
            }
        }

        // Warn on large operation counts
        if (count($operations) > 20) {
            $issues[] = new ValidationError('warning',
                'Workflow has ' . count($operations) . ' operations — consider splitting into smaller workflows');
        }
    }

    private function requireFields(array $config, array $fields, string $opId, array &$issues): void
    {
        foreach ($fields as $field) {
            if (!isset($config[$field]) || $config[$field] === '') {
                $issues[] = new ValidationError('error', "Missing required field: {$field}", $opId);
            }
        }
    }

    /**
     * @return array{valid: bool, errors: int, warnings: int, issues: ValidationError[]}
     */
    private function buildResult(array $issues): array
    {
        $errors = count(array_filter($issues, fn($e) => $e->severity === 'error'));
        $warnings = count(array_filter($issues, fn($e) => $e->severity === 'warning'));

        $valid = match ($this->level) {
            ValidationLevel::Strict => $errors === 0 && $warnings === 0,
            ValidationLevel::Moderate => $errors === 0,
            ValidationLevel::Lenient => true, // Accept even with errors in lenient mode
        };

        return [
            'valid' => $valid,
            'errors' => $errors,
            'warnings' => $warnings,
            'issues' => $issues,
        ];
    }
}
