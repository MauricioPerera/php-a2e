<?php
declare(strict_types=1);
namespace PHPA2E\Operation;
use PHPA2E\Executor\DataModel;

final class Wait implements OperationInterface
{
    public function type(): string { return 'Wait'; }

    public function execute(array $config, DataModel $data): array
    {
        $duration = (int)($config['duration'] ?? 0);
        $maxMs = 600000; // 10 minutes max
        $duration = min($duration, $maxMs);

        if ($duration > 0) {
            usleep($duration * 1000); // ms to microseconds
        }

        return ['waited_ms' => $duration];
    }
}
