<?php

declare(strict_types=1);

namespace PHPA2E;

use PHPA2E\Auth\AgentAuth;
use PHPA2E\Credentials\CredentialInjector;
use PHPA2E\Credentials\CredentialsVault;
use PHPA2E\Executor\ExecutionResult;
use PHPA2E\Executor\WorkflowExecutor;
use PHPA2E\Knowledge\ApiKnowledgeBase;
use PHPA2E\Monitoring\AuditLogger;
use PHPA2E\Operation\OperationRegistry;
use PHPA2E\RateLimiting\RateLimiter;
use PHPA2E\Validation\WorkflowValidator;

/**
 * A2E Facade — main entry point for the Agent-to-Execution protocol.
 *
 * Usage:
 *   $a2e = new A2E(new Config(dataDir: './data', masterKey: 'secret'));
 *   $result = $a2e->validate($jsonl);
 *   $result = $a2e->execute($jsonl, 'agent-id');
 */
final class A2E
{
    public readonly OperationRegistry $operations;
    public readonly AgentAuth $auth;
    public readonly CredentialsVault $vault;
    public readonly ApiKnowledgeBase $knowledge;
    public readonly WorkflowValidator $validator;
    public readonly RateLimiter $rateLimiter;
    public readonly AuditLogger $audit;

    private readonly WorkflowExecutor $executor;
    private readonly Config $config;

    public function __construct(Config $config)
    {
        $this->config = $config;

        $this->operations = OperationRegistry::withDefaults();
        $this->auth = new AgentAuth($config->authPath);
        $this->vault = new CredentialsVault($config->masterKey, $config->vaultPath);
        $this->knowledge = new ApiKnowledgeBase($config->apiDefsPath);
        $this->validator = new WorkflowValidator($this->operations);
        $this->rateLimiter = new RateLimiter($config->rateLimits);
        $this->audit = new AuditLogger($config->logDir);

        $injector = new CredentialInjector($this->vault);
        $this->executor = new WorkflowExecutor($this->operations, $injector);
    }

    /**
     * Validate a JSONL workflow.
     *
     * @return array{valid: bool, errors: int, warnings: int, issues: array}
     */
    public function validate(string $jsonl): array
    {
        return $this->validator->validate($jsonl);
    }

    /**
     * Execute a JSONL workflow.
     */
    public function execute(string $jsonl, ?string $agentId = null, bool $validateFirst = true): ExecutionResult
    {
        // Rate limit check
        if ($agentId !== null) {
            $rateCheck = $this->rateLimiter->check($agentId, true);
            if (!$rateCheck['allowed']) {
                return new ExecutionResult(
                    executionId: 'rate-limited',
                    status: 'error',
                    results: [],
                    durationMs: 0,
                    error: $rateCheck['error'],
                );
            }
        }

        // Validate first
        if ($validateFirst) {
            $validation = $this->validate($jsonl);
            if (!$validation['valid']) {
                $errors = array_map(fn($e) => $e->jsonSerialize(), $validation['issues']);
                return new ExecutionResult(
                    executionId: 'validation-failed',
                    status: 'error',
                    results: ['validation_errors' => $errors],
                    durationMs: 0,
                    error: "Validation failed: {$validation['errors']} error(s)",
                );
            }
        }

        // Audit
        $executionId = 'exec-' . bin2hex(random_bytes(8));
        if ($agentId !== null) {
            $this->audit->logExecutionStart($executionId, $agentId, $jsonl);
        }

        // Execute
        $result = $this->executor->execute($jsonl);

        // Audit completion
        if ($agentId !== null) {
            $this->audit->logExecutionComplete($result->executionId, $result->status, $result->durationMs);
        }

        return $result;
    }

    /**
     * Get agent capabilities.
     */
    public function capabilities(?string $agentId = null): array
    {
        $allApis = $this->knowledge->allApis();
        $allCreds = $this->vault->list();
        $allOps = $this->operations->types();

        if ($agentId !== null) {
            $filtered = $this->auth->filterCapabilities($agentId, $allApis, $allCreds, $allOps);
            return [
                'agent_id' => $agentId,
                'capabilities' => [
                    'availableApis' => $filtered['apis'],
                    'availableCredentials' => $filtered['credentials'],
                    'supportedOperations' => $filtered['operations'],
                ],
            ];
        }

        return [
            'capabilities' => [
                'availableApis' => $allApis,
                'availableCredentials' => $allCreds,
                'supportedOperations' => $allOps,
            ],
        ];
    }
}
