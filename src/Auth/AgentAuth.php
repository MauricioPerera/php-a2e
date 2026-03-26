<?php
declare(strict_types=1);
namespace PHPA2E\Auth;

final class AgentAuth
{
    /** @var array<string, array> */
    private array $agents = [];
    private ?string $storagePath;

    public function __construct(?string $storagePath = null)
    {
        $this->storagePath = $storagePath;
        if ($storagePath !== null && file_exists($storagePath)) {
            $data = json_decode(file_get_contents($storagePath), true);
            $this->agents = $data['agents'] ?? [];
        }
    }

    /**
     * Register an agent and return their API key (show once).
     */
    public function register(string $agentId, string $name, array $allowedApis = [], array $allowedCredentials = [], array $allowedOperations = []): string
    {
        $apiKey = bin2hex(random_bytes(32));
        $this->agents[$agentId] = [
            'id' => $agentId,
            'name' => $name,
            'api_key_hash' => hash('sha256', $apiKey),
            'allowed_apis' => $allowedApis,
            'allowed_credentials' => $allowedCredentials,
            'allowed_operations' => $allowedOperations,
            'created_at' => date('c'),
            'last_used' => null,
        ];
        $this->save();
        return $apiKey;
    }

    /** Authenticate by API key, return agent ID or null. */
    public function authenticate(string $apiKey): ?string
    {
        $hash = hash('sha256', $apiKey);
        foreach ($this->agents as $agentId => $agent) {
            if (hash_equals($agent['api_key_hash'], $hash)) {
                $this->agents[$agentId]['last_used'] = date('c');
                return $agentId;
            }
        }
        return null;
    }

    public function isApiAllowed(string $agentId, string $apiId): bool
    {
        $allowed = $this->agents[$agentId]['allowed_apis'] ?? [];
        return empty($allowed) || in_array($apiId, $allowed);
    }

    public function isCredentialAllowed(string $agentId, string $credentialId): bool
    {
        $allowed = $this->agents[$agentId]['allowed_credentials'] ?? [];
        return empty($allowed) || in_array($credentialId, $allowed);
    }

    public function isOperationAllowed(string $agentId, string $operationType): bool
    {
        $allowed = $this->agents[$agentId]['allowed_operations'] ?? [];
        return empty($allowed) || in_array($operationType, $allowed);
    }

    public function getAgent(string $agentId): ?array
    {
        return $this->agents[$agentId] ?? null;
    }

    /** Filter capabilities based on agent permissions. */
    public function filterCapabilities(string $agentId, array $apis, array $credentials, array $operations): array
    {
        $agent = $this->agents[$agentId] ?? null;
        if ($agent === null) return ['apis' => [], 'credentials' => [], 'operations' => []];

        return [
            'apis' => empty($agent['allowed_apis']) ? $apis : array_intersect_key($apis, array_flip($agent['allowed_apis'])),
            'credentials' => empty($agent['allowed_credentials']) ? $credentials : array_filter($credentials, fn($c) => in_array($c['id'], $agent['allowed_credentials'])),
            'operations' => empty($agent['allowed_operations']) ? $operations : array_intersect($operations, $agent['allowed_operations']),
        ];
    }

    private function save(): void
    {
        if ($this->storagePath !== null) {
            $dir = dirname($this->storagePath);
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            file_put_contents($this->storagePath, json_encode(['agents' => $this->agents], JSON_PRETTY_PRINT));
        }
    }
}
