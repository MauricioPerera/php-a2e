<?php

declare(strict_types=1);

namespace PHPA2E\Operation;

use PHPA2E\Executor\DataModel;

final class TransformData implements OperationInterface
{
    public function type(): string { return 'TransformData'; }

    public function execute(array $config, DataModel $data): array
    {
        $inputPath = $config['inputPath'] ?? '';
        $transform = $config['transform'] ?? '';
        $transformConfig = $config['config'] ?? [];
        $outputPath = $config['outputPath'] ?? '';

        $input = $data->get($inputPath);
        if (!is_array($input)) {
            throw new \RuntimeException("TransformData: inputPath is not an array");
        }

        $result = match ($transform) {
            'map' => $this->map($input, $transformConfig),
            'sort' => $this->sort($input, $transformConfig),
            'group' => $this->group($input, $transformConfig),
            'aggregate' => $this->aggregate($input, $transformConfig),
            'select' => $this->select($input, $transformConfig),
            default => throw new \RuntimeException("Unknown transform: {$transform}"),
        };

        if ($outputPath !== '') {
            $data->set($outputPath, $result);
        }

        return ['data' => $result, 'transform' => $transform];
    }

    private function map(array $input, array $config): array
    {
        $fields = $config['fields'] ?? [];
        if (empty($fields)) return $input;

        return array_map(function ($item) use ($fields) {
            $mapped = [];
            foreach ($fields as $from => $to) {
                $mapped[$to] = $item[$from] ?? null;
            }
            return $mapped;
        }, $input);
    }

    private function sort(array $input, array $config): array
    {
        $field = $config['field'] ?? '';
        $order = strtolower($config['order'] ?? 'asc');

        usort($input, function ($a, $b) use ($field, $order) {
            $va = $a[$field] ?? null;
            $vb = $b[$field] ?? null;
            $cmp = $va <=> $vb;
            return $order === 'desc' ? -$cmp : $cmp;
        });

        return $input;
    }

    private function group(array $input, array $config): array
    {
        $field = $config['field'] ?? '';
        $groups = [];
        foreach ($input as $item) {
            $key = (string)($item[$field] ?? 'unknown');
            $groups[$key][] = $item;
        }
        return $groups;
    }

    private function aggregate(array $input, array $config): array
    {
        $field = $config['field'] ?? '';
        $op = $config['operation'] ?? 'count';
        $values = array_column($input, $field);

        return ['result' => match ($op) {
            'count' => count($values),
            'sum' => array_sum($values),
            'avg' => count($values) > 0 ? array_sum($values) / count($values) : 0,
            'min' => !empty($values) ? min($values) : null,
            'max' => !empty($values) ? max($values) : null,
            default => count($values),
        }];
    }

    private function select(array $input, array $config): array
    {
        $fields = $config['fields'] ?? [];
        if (empty($fields)) return $input;

        return array_map(function ($item) use ($fields) {
            $selected = [];
            foreach ($fields as $f) {
                if (isset($item[$f])) {
                    $selected[$f] = $item[$f];
                }
            }
            return $selected;
        }, $input);
    }
}
