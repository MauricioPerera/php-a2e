<?php
declare(strict_types=1);
namespace PHPA2E\Executor;

final readonly class ExecutionResult implements \JsonSerializable
{
    public function __construct(
        public string $executionId,
        public string $status, // success | error | partial_success
        public array $results,
        public float $durationMs,
        public ?string $error = null,
    ) {}

    public function jsonSerialize(): array
    {
        $data = [
            'execution_id' => $this->executionId,
            'status' => $this->status,
            'results' => $this->results,
            'duration_ms' => round($this->durationMs, 1),
        ];
        if ($this->error !== null) {
            $data['error'] = $this->error;
        }
        return $data;
    }
}
