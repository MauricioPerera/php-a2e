<?php

declare(strict_types=1);

namespace PHPA2E\Operation;

use PHPA2E\Executor\DataModel;

final class Loop implements OperationInterface
{
    public function type(): string { return 'Loop'; }

    public function execute(array $config, DataModel $data): array
    {
        $inputPath = $config['inputPath'] ?? '';
        $outputPath = $config['outputPath'] ?? '';
        $operations = $config['operations'] ?? [];

        $input = $data->get($inputPath);
        if (!is_array($input)) {
            throw new \RuntimeException("Loop: inputPath is not an array");
        }

        // Loop returns the list of operations to execute per item
        // The executor handles the actual iteration
        return [
            'items' => count($input),
            'operations' => $operations,
            'outputPath' => $outputPath,
            'data' => $input,
        ];
    }
}
