<?php
declare(strict_types=1);
namespace PHPA2E\RateLimiting;

final readonly class RateLimitConfig
{
    public function __construct(
        public int $requestsPerMinute = 60,
        public int $requestsPerHour = 1000,
        public int $requestsPerDay = 10000,
        public int $apiCallsPerMinute = 30,
        public int $apiCallsPerHour = 500,
    ) {}
}
