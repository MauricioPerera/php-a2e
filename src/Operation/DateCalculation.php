<?php
declare(strict_types=1);
namespace PHPA2E\Operation;
use PHPA2E\Executor\DataModel;

final class DateCalculation implements OperationInterface
{
    public function type(): string { return 'DateCalculation'; }

    public function execute(array $config, DataModel $data): array
    {
        $inputPath = $config['inputPath'] ?? '';
        $operation = $config['operation'] ?? 'add';
        $outputPath = $config['outputPath'] ?? '';

        $input = (string)($data->get($inputPath) ?? 'now');
        $dt = new \DateTimeImmutable($input);

        $parts = [];
        foreach (['years' => 'Y', 'months' => 'M', 'days' => 'D'] as $key => $code) {
            if (isset($config[$key]) && (int)$config[$key] > 0) {
                $parts[] = (int)$config[$key] . $code;
            }
        }
        $timeParts = [];
        foreach (['hours' => 'H', 'minutes' => 'M', 'seconds' => 'S'] as $key => $code) {
            if (isset($config[$key]) && (int)$config[$key] > 0) {
                $timeParts[] = (int)$config[$key] . $code;
            }
        }

        $spec = 'P' . implode('', $parts);
        if (!empty($timeParts)) {
            $spec .= 'T' . implode('', $timeParts);
        }
        if ($spec === 'P') $spec = 'P0D';

        $interval = new \DateInterval($spec);
        $result = $operation === 'subtract' ? $dt->sub($interval) : $dt->add($interval);
        $formatted = $result->format('c');

        if ($outputPath !== '') {
            $data->set($outputPath, $formatted);
        }

        return ['result' => $formatted, 'operation' => $operation];
    }
}
