<?php
declare(strict_types=1);
namespace PHPA2E\Operation;
use PHPA2E\Executor\DataModel;

final class StoreData implements OperationInterface
{
    public function type(): string { return 'StoreData'; }

    public function execute(array $config, DataModel $data): array
    {
        $inputPath = $config['inputPath'] ?? '';
        $key = $config['key'] ?? '';
        $storage = $config['storage'] ?? 'localStorage';

        $value = $data->get($inputPath);
        $data->set("/store/{$key}", $value);

        return ['stored' => true, 'key' => $key, 'storage' => $storage];
    }
}
