<?php
declare(strict_types=1);
namespace PHPA2E\Operation;
use PHPA2E\Executor\DataModel;

final class Calculate implements OperationInterface
{
    public function type(): string { return 'Calculate'; }

    public function execute(array $config, DataModel $data): array
    {
        $inputPath = $config['inputPath'] ?? '';
        $operation = $config['operation'] ?? '';
        $operand = $config['operand'] ?? null;
        $precision = $config['precision'] ?? null;
        $outputPath = $config['outputPath'] ?? '';

        $input = $data->get($inputPath);
        $val = is_numeric($input) ? (float)$input : 0.0;

        $result = match ($operation) {
            'add' => $val + (float)$operand,
            'subtract' => $val - (float)$operand,
            'multiply' => $val * (float)$operand,
            'divide' => (float)$operand != 0 ? $val / (float)$operand : null,
            'power' => pow($val, (float)$operand),
            'modulo' => (int)$operand != 0 ? fmod($val, (float)$operand) : null,
            'round' => round($val, $precision ?? 0),
            'ceil' => ceil($val),
            'floor' => floor($val),
            'abs' => abs($val),
            'sum' => is_array($input) ? array_sum($input) : $val,
            'average' => is_array($input) && count($input) > 0 ? array_sum($input) / count($input) : $val,
            'max' => is_array($input) ? max($input) : $val,
            'min' => is_array($input) ? min($input) : $val,
            default => $val,
        };

        if ($outputPath !== '') {
            $data->set($outputPath, $result);
        }

        return ['result' => $result, 'operation' => $operation];
    }
}
