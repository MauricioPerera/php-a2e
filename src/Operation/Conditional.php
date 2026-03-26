<?php

declare(strict_types=1);

namespace PHPA2E\Operation;

use PHPA2E\Executor\DataModel;

final class Conditional implements OperationInterface
{
    public function type(): string { return 'Conditional'; }

    public function execute(array $config, DataModel $data): array
    {
        $condition = $config['condition'] ?? [];
        $path = $condition['path'] ?? '';
        $operator = $condition['operator'] ?? '==';
        $value = $condition['value'] ?? null;

        $actual = $data->get($path);

        $result = match ($operator) {
            '==' => $actual == $value,
            '!=' => $actual != $value,
            '>' => $actual > $value,
            '<' => $actual < $value,
            '>=' => $actual >= $value,
            '<=' => $actual <= $value,
            'exists' => $actual !== null,
            'empty' => empty($actual),
            default => false,
        };

        return [
            'condition_met' => $result,
            'branch' => $result ? 'ifTrue' : 'ifFalse',
            'execute' => $result
                ? ($config['ifTrue'] ?? [])
                : ($config['ifFalse'] ?? []),
        ];
    }
}
