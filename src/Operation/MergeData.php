<?php
declare(strict_types=1);
namespace PHPA2E\Operation;
use PHPA2E\Executor\DataModel;

final class MergeData implements OperationInterface
{
    public function type(): string { return 'MergeData'; }

    public function execute(array $config, DataModel $data): array
    {
        $sources = $config['sources'] ?? [];
        $strategy = $config['strategy'] ?? 'concat';
        $outputPath = $config['outputPath'] ?? '';

        $datasets = array_map(fn($path) => $data->get($path) ?? [], $sources);

        $result = match ($strategy) {
            'concat' => array_merge(...$datasets),
            'union' => array_values(array_unique(array_merge(...$datasets), SORT_REGULAR)),
            'intersect' => count($datasets) >= 2
                ? array_values(array_intersect(...$datasets))
                : ($datasets[0] ?? []),
            'deepMerge' => array_replace_recursive(...$datasets),
            default => array_merge(...$datasets),
        };

        if ($outputPath !== '') {
            $data->set($outputPath, $result);
        }

        return ['data' => $result, 'strategy' => $strategy, 'sources' => count($sources)];
    }
}
