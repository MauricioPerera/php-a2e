<?php
declare(strict_types=1);
namespace PHPA2E\Operation;
use PHPA2E\Executor\DataModel;

final class GetCurrentDateTime implements OperationInterface
{
    public function type(): string { return 'GetCurrentDateTime'; }

    public function execute(array $config, DataModel $data): array
    {
        $timezone = $config['timezone'] ?? 'UTC';
        $format = $config['format'] ?? 'iso8601';
        $formatString = $config['formatString'] ?? '';
        $outputPath = $config['outputPath'] ?? '';

        $tz = new \DateTimeZone($timezone);
        $dt = new \DateTimeImmutable('now', $tz);

        $result = match ($format) {
            'timestamp' => $dt->getTimestamp(),
            'custom' => $dt->format($formatString ?: 'Y-m-d H:i:s'),
            default => $dt->format('c'), // iso8601
        };

        if ($outputPath !== '') {
            $data->set($outputPath, $result);
        }

        return ['datetime' => $result, 'timezone' => $timezone];
    }
}
