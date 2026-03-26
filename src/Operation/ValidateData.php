<?php
declare(strict_types=1);
namespace PHPA2E\Operation;
use PHPA2E\Executor\DataModel;

final class ValidateData implements OperationInterface
{
    public function type(): string { return 'ValidateData'; }

    public function execute(array $config, DataModel $data): array
    {
        $inputPath = $config['inputPath'] ?? '';
        $validationType = $config['validationType'] ?? '';
        $pattern = $config['pattern'] ?? '';
        $outputPath = $config['outputPath'] ?? '';

        $input = $data->get($inputPath);
        $value = is_string($input) ? $input : (string)$input;

        $valid = match ($validationType) {
            'email' => filter_var($value, FILTER_VALIDATE_EMAIL) !== false,
            'url' => filter_var($value, FILTER_VALIDATE_URL) !== false,
            'number' => is_numeric($value),
            'integer' => filter_var($value, FILTER_VALIDATE_INT) !== false,
            'phone' => (bool)preg_match('/^\+?[\d\s\-()]{7,20}$/', $value),
            'date' => strtotime($value) !== false,
            'custom' => $pattern !== '' && (bool)preg_match("/{$pattern}/u", $value),
            default => false,
        };

        $result = ['valid' => $valid, 'type' => $validationType, 'value' => $value];

        if ($outputPath !== '') {
            $data->set($outputPath, $result);
        }

        return $result;
    }
}
