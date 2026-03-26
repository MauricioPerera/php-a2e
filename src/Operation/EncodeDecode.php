<?php
declare(strict_types=1);
namespace PHPA2E\Operation;
use PHPA2E\Executor\DataModel;

final class EncodeDecode implements OperationInterface
{
    public function type(): string { return 'EncodeDecode'; }

    public function execute(array $config, DataModel $data): array
    {
        $inputPath = $config['inputPath'] ?? '';
        $operation = $config['operation'] ?? 'encode';
        $encoding = $config['encoding'] ?? 'base64';
        $outputPath = $config['outputPath'] ?? '';

        $input = (string)($data->get($inputPath) ?? '');

        $result = match ("{$operation}:{$encoding}") {
            'encode:base64' => base64_encode($input),
            'decode:base64' => base64_decode($input) ?: '',
            'encode:url' => urlencode($input),
            'decode:url' => urldecode($input),
            'encode:html' => htmlspecialchars($input, ENT_QUOTES | ENT_HTML5),
            'decode:html' => html_entity_decode($input, ENT_QUOTES | ENT_HTML5),
            default => $input,
        };

        if ($outputPath !== '') {
            $data->set($outputPath, $result);
        }

        return ['result' => $result, 'operation' => $operation, 'encoding' => $encoding];
    }
}
