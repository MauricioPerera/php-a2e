<?php
declare(strict_types=1);
namespace PHPA2E\RateLimiting;

final class RateLimiter
{
    /** @var array<string, array{requests: float[], api_calls: float[]}> */
    private array $records = [];
    /** @var array<string, RateLimitConfig> */
    private array $customLimits = [];

    public function __construct(private readonly RateLimitConfig $defaultConfig = new RateLimitConfig()) {}

    public function setAgentLimits(string $agentId, RateLimitConfig $config): void
    {
        $this->customLimits[$agentId] = $config;
    }

    /**
     * @return array{allowed: bool, error: ?string, retryAfter: ?float}
     */
    public function check(string $agentId, bool $isApiCall = false): array
    {
        $config = $this->customLimits[$agentId] ?? $this->defaultConfig;
        $now = microtime(true);

        if (!isset($this->records[$agentId])) {
            $this->records[$agentId] = ['requests' => [], 'api_calls' => []];
        }

        $rec = &$this->records[$agentId];

        // Clean old entries
        $rec['requests'] = array_values(array_filter($rec['requests'], fn($t) => ($now - $t) < 86400));
        $rec['api_calls'] = array_values(array_filter($rec['api_calls'], fn($t) => ($now - $t) < 3600));

        // Check request limits
        $lastMinute = count(array_filter($rec['requests'], fn($t) => ($now - $t) < 60));
        if ($lastMinute >= $config->requestsPerMinute) {
            return ['allowed' => false, 'error' => "Rate limit: {$config->requestsPerMinute} requests/minute", 'retryAfter' => 60.0 - ($now - ($rec['requests'][0] ?? $now))];
        }

        $lastHour = count(array_filter($rec['requests'], fn($t) => ($now - $t) < 3600));
        if ($lastHour >= $config->requestsPerHour) {
            return ['allowed' => false, 'error' => "Rate limit: {$config->requestsPerHour} requests/hour", 'retryAfter' => 3600.0];
        }

        if ($isApiCall) {
            $apiLastMinute = count(array_filter($rec['api_calls'], fn($t) => ($now - $t) < 60));
            if ($apiLastMinute >= $config->apiCallsPerMinute) {
                return ['allowed' => false, 'error' => "Rate limit: {$config->apiCallsPerMinute} API calls/minute", 'retryAfter' => 60.0];
            }
        }

        // Record
        $rec['requests'][] = $now;
        if ($isApiCall) $rec['api_calls'][] = $now;

        return ['allowed' => true, 'error' => null, 'retryAfter' => null];
    }

    public function status(string $agentId): array
    {
        $config = $this->customLimits[$agentId] ?? $this->defaultConfig;
        $now = microtime(true);
        $rec = $this->records[$agentId] ?? ['requests' => [], 'api_calls' => []];

        return [
            'requests_per_minute' => ['limit' => $config->requestsPerMinute, 'used' => count(array_filter($rec['requests'], fn($t) => ($now - $t) < 60))],
            'requests_per_hour' => ['limit' => $config->requestsPerHour, 'used' => count(array_filter($rec['requests'], fn($t) => ($now - $t) < 3600))],
            'api_calls_per_minute' => ['limit' => $config->apiCallsPerMinute, 'used' => count(array_filter($rec['api_calls'], fn($t) => ($now - $t) < 60))],
        ];
    }
}
