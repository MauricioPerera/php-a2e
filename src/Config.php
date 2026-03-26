<?php
declare(strict_types=1);
namespace PHPA2E;

use PHPA2E\RateLimiting\RateLimitConfig;

final readonly class Config
{
    public function __construct(
        public string $dataDir = './data',
        public string $masterKey = 'change-this-key',
        public ?string $vaultPath = null,
        public ?string $authPath = null,
        public ?string $apiDefsPath = null,
        public string $logDir = 'logs',
        public RateLimitConfig $rateLimits = new RateLimitConfig(),
    ) {}
}
