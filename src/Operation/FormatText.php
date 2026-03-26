<?php
declare(strict_types=1);
namespace PHPA2E\Operation;
use PHPA2E\Executor\DataModel;

final class FormatText implements OperationInterface
{
    public function type(): string { return 'FormatText'; }

    public function execute(array $config, DataModel $data): array
    {
        $inputPath = $config['inputPath'] ?? '';
        $format = $config['format'] ?? '';
        $outputPath = $config['outputPath'] ?? '';
        $template = $config['template'] ?? '';
        $replacements = $config['replacements'] ?? [];

        $input = (string)($data->get($inputPath) ?? '');

        $result = match ($format) {
            'upper' => mb_strtoupper($input),
            'lower' => mb_strtolower($input),
            'title' => mb_convert_case($input, MB_CASE_TITLE),
            'capitalize' => mb_strtoupper(mb_substr($input, 0, 1)) . mb_substr($input, 1),
            'trim' => trim($input),
            'template' => $data->resolveReferences($template),
            'replace' => str_replace(array_keys($replacements), array_values($replacements), $input),
            default => $input,
        };

        if ($outputPath !== '') {
            $data->set($outputPath, $result);
        }

        return ['result' => $result, 'format' => $format];
    }
}
