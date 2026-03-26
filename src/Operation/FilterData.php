<?php

declare(strict_types=1);

namespace PHPA2E\Operation;

use PHPA2E\Executor\DataModel;

final class FilterData implements OperationInterface
{
    public function type(): string { return 'FilterData'; }

    public function execute(array $config, DataModel $data): array
    {
        $inputPath = $config['inputPath'] ?? '';
        $conditions = $config['conditions'] ?? [];
        $outputPath = $config['outputPath'] ?? '';

        $input = $data->get($inputPath);
        if (!is_array($input)) {
            throw new \RuntimeException("FilterData: inputPath '{$inputPath}' is not an array");
        }

        $filtered = array_values(array_filter($input, function ($item) use ($conditions) {
            foreach ($conditions as $cond) {
                $field = $cond['field'] ?? '';
                $operator = $cond['operator'] ?? '==';
                $value = $cond['value'] ?? null;
                $actual = is_array($item) ? ($item[$field] ?? null) : null;

                if (!self::evaluate($actual, $operator, $value)) {
                    return false;
                }
            }
            return true;
        }));

        if ($outputPath !== '') {
            $data->set($outputPath, $filtered);
        }

        return ['count' => count($filtered), 'data' => $filtered];
    }

    private static function evaluate(mixed $actual, string $operator, mixed $value): bool
    {
        return match ($operator) {
            '==' => $actual == $value,
            '!=' => $actual != $value,
            '>' => $actual > $value,
            '<' => $actual < $value,
            '>=' => $actual >= $value,
            '<=' => $actual <= $value,
            'in' => is_array($value) && in_array($actual, $value),
            'contains' => is_string($actual) && str_contains($actual, (string)$value),
            'startsWith' => is_string($actual) && str_starts_with($actual, (string)$value),
            'endsWith' => is_string($actual) && str_ends_with($actual, (string)$value),
            default => false,
        };
    }
}
