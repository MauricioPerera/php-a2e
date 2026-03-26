<?php
declare(strict_types=1);
namespace PHPA2E\Validation;

final readonly class ValidationError implements \JsonSerializable
{
    public function __construct(
        public string $severity, // 'error' | 'warning'
        public string $message,
        public ?string $operationId = null,
        public ?string $suggestion = null,
    ) {}

    public function jsonSerialize(): array
    {
        return array_filter([
            'severity' => $this->severity,
            'message' => $this->message,
            'operation_id' => $this->operationId,
            'suggestion' => $this->suggestion,
        ], fn($v) => $v !== null);
    }
}
