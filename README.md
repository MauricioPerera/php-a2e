# PHP A2E

Agent-to-Execution protocol for PHP — declarative workflow execution for AI agents. Port of the [A2E protocol](https://github.com/MauricioPerera/a2e) to native PHP.

Zero external dependencies. Only PHP 8.1+.

```
composer require mauricioperera/php-a2e
```

## What is A2E?

A2E enables AI agents to execute complex workflows **without arbitrary code execution**. The agent describes *what* to do (declarative JSONL), the server validates and executes it from a catalog of pre-approved operations.

```
Agent (LLM)  →  JSONL workflow  →  Validate (6 stages)  →  Execute  →  Structured results
               (declarative)       (security boundary)     (safe)
```

## Quick Start

```php
use PHPA2E\A2E;
use PHPA2E\Config;

$a2e = new A2E(new Config(masterKey: 'your-secret-key'));

// Define a workflow in JSONL
$workflow = implode("\n", [
    json_encode(['type' => 'operationUpdate', 'operationId' => 'fetch-users',
        'operation' => ['ApiCall' => [
            'method' => 'GET',
            'url' => 'https://jsonplaceholder.typicode.com/users',
            'outputPath' => '/workflow/users',
        ]]]),
    json_encode(['type' => 'operationUpdate', 'operationId' => 'filter-active',
        'operation' => ['FilterData' => [
            'inputPath' => '/workflow/users',
            'conditions' => [['field' => 'id', 'operator' => '<=', 'value' => 5]],
            'outputPath' => '/workflow/filtered',
        ]]]),
    json_encode(['type' => 'beginExecution', 'executionId' => 'exec-1',
        'operationOrder' => ['fetch-users', 'filter-active']]),
]);

// Validate
$validation = $a2e->validate($workflow);
// { valid: true, errors: 0, warnings: 0, issues: [] }

// Execute
$result = $a2e->execute($workflow);
// { status: "success", results: { "fetch-users": {...}, "filter-active": {count: 5} } }
```

## 16 Built-in Operations

| Operation | Description |
|-----------|-------------|
| **ApiCall** | HTTP requests (GET/POST/PUT/DELETE/PATCH) with credential injection |
| **FilterData** | Array filtering with conditions (==, !=, >, <, contains, etc.) |
| **TransformData** | Data transformation: map, sort, group, aggregate, select |
| **Conditional** | If/else branching based on data model values |
| **Loop** | Array iteration with sub-operations |
| **StoreData** | Persist data to storage |
| **Wait** | Execution delay (ms) |
| **MergeData** | Combine data: concat, union, intersect, deepMerge |
| **Calculate** | Math: add, subtract, multiply, divide, sum, average, round, etc. |
| **FormatText** | String: upper, lower, title, template, replace |
| **ExtractText** | Regex extraction (single or all matches) |
| **ValidateData** | Validate: email, url, number, phone, date, custom regex |
| **EncodeDecode** | Base64, URL, HTML encode/decode |
| **GetCurrentDateTime** | Timezone-aware timestamps |
| **ConvertTimezone** | Timezone conversion |
| **DateCalculation** | Date math: add/subtract years, months, days, hours |

## Security Model

- **Catalog-based trust**: Only registered operations can execute
- **Credential isolation**: Agents reference by ID, never see values (AES-256-CBC encrypted)
- **Per-agent permissions**: API whitelist, credential access, operation whitelist
- **6-stage validation**: Structure, dependencies, types, API compatibility, credentials, patterns
- **Rate limiting**: Per-agent sliding window (requests/minute, hour, day)
- **Audit trail**: JSONL execution logs with sensitive data redaction

## Validation Pipeline

```php
$result = $a2e->validate($jsonl);
// Stage 1: Structure    — IDs exist, no duplicates, required fields
// Stage 2: Dependencies — inputPath references exist, no cycles
// Stage 3: Types        — FilterData needs array input, etc.
// Stage 4: API compat   — URLs registered (optional)
// Stage 5: Credentials  — refs exist in vault (optional)
// Stage 6: Patterns     — infinite loops, large workflows
```

## Agent Authentication

```php
// Register an agent with permissions
$apiKey = $a2e->auth->register('agent-1', 'My Agent',
    allowedApis: ['user-api'],
    allowedCredentials: ['api-token'],
    allowedOperations: ['ApiCall', 'FilterData'],
);

// Agent authenticates with API key
$agentId = $a2e->auth->authenticate($apiKey);

// Capabilities filtered by permissions
$caps = $a2e->capabilities('agent-1');
```

## Credentials Vault

```php
// Store encrypted credential
$a2e->vault->store('api-token', 'bearer-token', 'secret-value', ['api' => 'users']);

// In workflows, agents reference by ID only
// {"Authorization": {"credentialRef": {"id": "api-token"}}}
// → Server injects: {"Authorization": "Bearer secret-value"}
```

## HTTP API

```bash
php -S 0.0.0.0:3210 bin/server.php -- --master-key SECRET --api-key AUTH
```

| Method | Route | Description |
|--------|-------|-------------|
| GET | `/health` | Health check |
| GET | `/api/v1/capabilities` | Agent's available operations/APIs |
| POST | `/api/v1/workflows/validate` | Validate JSONL workflow |
| POST | `/api/v1/workflows/execute` | Execute JSONL workflow |
| GET | `/api/v1/executions/{id}` | Execution timeline |
| GET | `/api/v1/rate-limit/status` | Rate limit usage |

## Custom Operations

```php
use PHPA2E\Operation\OperationInterface;
use PHPA2E\Executor\DataModel;

class SendSlackMessage implements OperationInterface
{
    public function type(): string { return 'SendSlack'; }

    public function execute(array $config, DataModel $data): array
    {
        $message = $data->resolveReferences($config['message']);
        // ... send to Slack
        return ['sent' => true, 'channel' => $config['channel']];
    }
}

$a2e->operations->register(new SendSlackMessage());
```

## The PHP AI Agent Ecosystem

| Package | Purpose |
|---------|---------|
| [php-vector-store](https://github.com/MauricioPerera/php-vector-store) | Vector database (storage engine) |
| [php-agent-memory](https://github.com/MauricioPerera/php-agent-memory) | Agent memory + dream consolidation (the brain) |
| [php-agent-shell](https://github.com/MauricioPerera/php-agent-shell) *(coming soon)* | CLI execution + vector discovery (the hands) |
| **php-a2e** | Declarative workflow execution (the orchestrator) |
| [Neuron AI](https://github.com/neuron-core/neuron-ai) | Agent framework (the body) |

## Testing

```bash
composer install
vendor/bin/phpunit    # 26 tests, 54 assertions
```

## License

MIT
