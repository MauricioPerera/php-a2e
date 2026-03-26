<?php
declare(strict_types=1);
namespace PHPA2E\Operation;
use PHPA2E\Executor\DataModel;

final class ConvertTimezone implements OperationInterface
{
    public function type(): string { return 'ConvertTimezone'; }

    public function execute(array $config, DataModel $data): array
    {
        $inputPath = $config['inputPath'] ?? '';
        $fromTimezone = $config['fromTimezone'] ?? 'UTC';
        $toTimezone = $config['toTimezone'] ?? 'UTC';
        $format = $config['format'] ?? 'iso8601';
        $outputPath = $config['outputPath'] ?? '';

        $input = (string)($data->get($inputPath) ?? '');
        $dt = new \DateTimeImmutable($input, new \DateTimeZone($fromTimezone));
        $converted = $dt->setTimezone(new \DateTimeZone($toTimezone));

        $result = match ($format) {
            'timestamp' => $converted->getTimestamp(),
            default => $converted->format('c'),
        };

        if ($outputPath !== '') {
            $data->set($outputPath, $result);
        }

        return ['result' => $result, 'from' => $fromTimezone, 'to' => $toTimezone];
    }
}
