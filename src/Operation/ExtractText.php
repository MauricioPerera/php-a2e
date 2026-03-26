<?php
declare(strict_types=1);
namespace PHPA2E\Operation;
use PHPA2E\Executor\DataModel;

final class ExtractText implements OperationInterface
{
    public function type(): string { return 'ExtractText'; }

    public function execute(array $config, DataModel $data): array
    {
        $inputPath = $config['inputPath'] ?? '';
        $pattern = $config['pattern'] ?? '';
        $extractAll = (bool)($config['extractAll'] ?? false);
        $outputPath = $config['outputPath'] ?? '';

        $input = (string)($data->get($inputPath) ?? '');

        if ($extractAll) {
            preg_match_all("/{$pattern}/u", $input, $matches);
            $result = $matches[0] ?? [];
        } else {
            preg_match("/{$pattern}/u", $input, $matches);
            $result = $matches[0] ?? null;
        }

        if ($outputPath !== '') {
            $data->set($outputPath, $result);
        }

        return ['result' => $result, 'pattern' => $pattern];
    }
}
