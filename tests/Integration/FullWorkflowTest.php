<?php
declare(strict_types=1);
namespace PHPA2E\Tests\Integration;

use PHPUnit\Framework\TestCase;
use PHPA2E\A2E;
use PHPA2E\Config;
use PHPA2E\Executor\DataModel;
use PHPA2E\Protocol\WorkflowParser;
use PHPA2E\Operation\OperationRegistry;
use PHPA2E\Executor\WorkflowExecutor;
use PHPA2E\Validation\WorkflowValidator;
use PHPA2E\Auth\AgentAuth;
use PHPA2E\Credentials\CredentialsVault;
use PHPA2E\Credentials\CredentialInjector;
use PHPA2E\RateLimiting\RateLimiter;
use PHPA2E\RateLimiting\RateLimitConfig;

final class FullWorkflowTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/a2e-test-' . uniqid();
        mkdir($this->tmpDir, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->cleanDir($this->tmpDir);
    }

    private function cleanDir(string $dir): void
    {
        if (!is_dir($dir)) return;
        foreach (scandir($dir) as $f) {
            if ($f === '.' || $f === '..') continue;
            $p = $dir . '/' . $f;
            is_dir($p) ? $this->cleanDir($p) : unlink($p);
        }
        rmdir($dir);
    }

    // ── DataModel ──────────────────────────────────────────────────

    public function testDataModelSetGet(): void
    {
        $dm = new DataModel();
        $dm->set('/workflow/users', [['name' => 'Alice'], ['name' => 'Bob']]);
        $this->assertCount(2, $dm->get('/workflow/users'));
        $this->assertSame('Alice', $dm->get('/workflow/users/0/name'));
    }

    public function testDataModelResolveReferences(): void
    {
        $dm = new DataModel();
        $dm->set('/config/baseUrl', 'https://api.example.com');
        $this->assertSame('https://api.example.com/users', $dm->resolveReferences('{/config/baseUrl}/users'));
    }

    // ── WorkflowParser ─────────────────────────────────────────────

    public function testParseValidJsonl(): void
    {
        $jsonl = implode("\n", [
            json_encode(['type' => 'operationUpdate', 'operationId' => 'op-1', 'operation' => ['Calculate' => ['inputPath' => '/workflow/x', 'operation' => 'add', 'operand' => 5, 'outputPath' => '/workflow/result']]]),
            json_encode(['type' => 'beginExecution', 'executionId' => 'exec-1', 'operationOrder' => ['op-1']]),
        ]);

        $parsed = WorkflowParser::parse($jsonl);
        $this->assertCount(1, $parsed['operations']);
        $this->assertSame(['op-1'], $parsed['executionOrder']);
        $this->assertSame('exec-1', $parsed['executionId']);
    }

    public function testAutoSynthesizeBeginExecution(): void
    {
        $jsonl = json_encode(['type' => 'operationUpdate', 'operationId' => 'op-1', 'operation' => ['Wait' => ['duration' => 0]]]);
        $parsed = WorkflowParser::parse($jsonl);
        $this->assertSame(['op-1'], $parsed['executionOrder']);
    }

    // ── Operations ─────────────────────────────────────────────────

    public function testFilterData(): void
    {
        $registry = OperationRegistry::withDefaults();
        $dm = new DataModel();
        $dm->set('/workflow/users', [
            ['name' => 'Alice', 'age' => 30],
            ['name' => 'Bob', 'age' => 17],
            ['name' => 'Carol', 'age' => 25],
        ]);

        $result = $registry->get('FilterData')->execute([
            'inputPath' => '/workflow/users',
            'conditions' => [['field' => 'age', 'operator' => '>=', 'value' => 18]],
            'outputPath' => '/workflow/adults',
        ], $dm);

        $this->assertSame(2, $result['count']);
        $this->assertCount(2, $dm->get('/workflow/adults'));
    }

    public function testTransformDataSort(): void
    {
        $registry = OperationRegistry::withDefaults();
        $dm = new DataModel();
        $dm->set('/workflow/items', [
            ['name' => 'C', 'price' => 30],
            ['name' => 'A', 'price' => 10],
            ['name' => 'B', 'price' => 20],
        ]);

        $registry->get('TransformData')->execute([
            'inputPath' => '/workflow/items',
            'transform' => 'sort',
            'config' => ['field' => 'price', 'order' => 'asc'],
            'outputPath' => '/workflow/sorted',
        ], $dm);

        $sorted = $dm->get('/workflow/sorted');
        $this->assertSame('A', $sorted[0]['name']);
        $this->assertSame('C', $sorted[2]['name']);
    }

    public function testTransformDataAggregate(): void
    {
        $registry = OperationRegistry::withDefaults();
        $dm = new DataModel();
        $dm->set('/workflow/items', [
            ['price' => 10], ['price' => 20], ['price' => 30],
        ]);

        $result = $registry->get('TransformData')->execute([
            'inputPath' => '/workflow/items',
            'transform' => 'aggregate',
            'config' => ['field' => 'price', 'operation' => 'sum'],
            'outputPath' => '/workflow/total',
        ], $dm);

        $this->assertSame(60, $dm->get('/workflow/total')['result']);
    }

    public function testConditional(): void
    {
        $registry = OperationRegistry::withDefaults();
        $dm = new DataModel();
        $dm->set('/workflow/count', 5);

        $result = $registry->get('Conditional')->execute([
            'condition' => ['path' => '/workflow/count', 'operator' => '>', 'value' => 3],
            'ifTrue' => ['send-notification'],
            'ifFalse' => ['skip'],
        ], $dm);

        $this->assertTrue($result['condition_met']);
        $this->assertSame('ifTrue', $result['branch']);
        $this->assertSame(['send-notification'], $result['execute']);
    }

    public function testCalculate(): void
    {
        $registry = OperationRegistry::withDefaults();
        $dm = new DataModel();
        $dm->set('/workflow/x', 10);

        $registry->get('Calculate')->execute([
            'inputPath' => '/workflow/x',
            'operation' => 'multiply',
            'operand' => 3,
            'outputPath' => '/workflow/result',
        ], $dm);

        $this->assertSame(30.0, $dm->get('/workflow/result'));
    }

    public function testFormatText(): void
    {
        $registry = OperationRegistry::withDefaults();
        $dm = new DataModel();
        $dm->set('/workflow/name', 'hello world');

        $result = $registry->get('FormatText')->execute([
            'inputPath' => '/workflow/name',
            'format' => 'upper',
            'outputPath' => '/workflow/formatted',
        ], $dm);

        $this->assertSame('HELLO WORLD', $dm->get('/workflow/formatted'));
    }

    public function testExtractText(): void
    {
        $registry = OperationRegistry::withDefaults();
        $dm = new DataModel();
        $dm->set('/workflow/text', 'Email: user@example.com and admin@test.org');

        $registry->get('ExtractText')->execute([
            'inputPath' => '/workflow/text',
            'pattern' => '[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}',
            'extractAll' => true,
            'outputPath' => '/workflow/emails',
        ], $dm);

        $this->assertCount(2, $dm->get('/workflow/emails'));
    }

    public function testValidateDataEmail(): void
    {
        $registry = OperationRegistry::withDefaults();
        $dm = new DataModel();
        $dm->set('/workflow/email', 'user@example.com');

        $result = $registry->get('ValidateData')->execute([
            'inputPath' => '/workflow/email',
            'validationType' => 'email',
            'outputPath' => '/workflow/valid',
        ], $dm);

        $this->assertTrue($result['valid']);
    }

    public function testEncodeDecode(): void
    {
        $registry = OperationRegistry::withDefaults();
        $dm = new DataModel();
        $dm->set('/workflow/text', 'Hello World');

        $registry->get('EncodeDecode')->execute([
            'inputPath' => '/workflow/text',
            'operation' => 'encode',
            'encoding' => 'base64',
            'outputPath' => '/workflow/encoded',
        ], $dm);

        $this->assertSame(base64_encode('Hello World'), $dm->get('/workflow/encoded'));
    }

    public function testGetCurrentDateTime(): void
    {
        $registry = OperationRegistry::withDefaults();
        $dm = new DataModel();

        $registry->get('GetCurrentDateTime')->execute([
            'timezone' => 'UTC',
            'outputPath' => '/workflow/now',
        ], $dm);

        $this->assertNotNull($dm->get('/workflow/now'));
    }

    public function testMergeData(): void
    {
        $registry = OperationRegistry::withDefaults();
        $dm = new DataModel();
        $dm->set('/workflow/a', [1, 2, 3]);
        $dm->set('/workflow/b', [4, 5, 6]);

        $registry->get('MergeData')->execute([
            'sources' => ['/workflow/a', '/workflow/b'],
            'strategy' => 'concat',
            'outputPath' => '/workflow/merged',
        ], $dm);

        $this->assertSame([1, 2, 3, 4, 5, 6], $dm->get('/workflow/merged'));
    }

    // ── WorkflowExecutor ───────────────────────────────────────────

    public function testExecuteMultiStepWorkflow(): void
    {
        $jsonl = implode("\n", [
            json_encode(['type' => 'operationUpdate', 'operationId' => 'set-data', 'operation' => ['StoreData' => ['inputPath' => '/workflow/input', 'key' => 'test']]]),
            json_encode(['type' => 'operationUpdate', 'operationId' => 'calc', 'operation' => ['Calculate' => ['inputPath' => '/workflow/num', 'operation' => 'add', 'operand' => 10, 'outputPath' => '/workflow/result']]]),
            json_encode(['type' => 'beginExecution', 'executionId' => 'test-1', 'operationOrder' => ['calc']]),
        ]);

        $executor = new WorkflowExecutor(OperationRegistry::withDefaults());

        // Pre-set some data in the workflow
        $result = $executor->execute(
            // We need data in the model, so use a workflow that only does calculation
            json_encode(['type' => 'operationUpdate', 'operationId' => 'get-time', 'operation' => ['GetCurrentDateTime' => ['outputPath' => '/workflow/now']]]) . "\n" .
            json_encode(['type' => 'beginExecution', 'executionId' => 'e1', 'operationOrder' => ['get-time']])
        );

        $this->assertSame('success', $result->status);
        $this->assertArrayHasKey('get-time', $result->results);
    }

    // ── Validation ─────────────────────────────────────────────────

    public function testValidateValidWorkflow(): void
    {
        $jsonl = implode("\n", [
            json_encode(['type' => 'operationUpdate', 'operationId' => 'op-1', 'operation' => ['GetCurrentDateTime' => ['outputPath' => '/workflow/now']]]),
            json_encode(['type' => 'beginExecution', 'executionId' => 'e1', 'operationOrder' => ['op-1']]),
        ]);

        $validator = new WorkflowValidator(OperationRegistry::withDefaults());
        $result = $validator->validate($jsonl);

        $this->assertTrue($result['valid']);
        $this->assertSame(0, $result['errors']);
    }

    public function testValidateRejectsUnknownOperation(): void
    {
        $jsonl = json_encode(['type' => 'operationUpdate', 'operationId' => 'op-1', 'operation' => ['FakeOp' => ['foo' => 'bar']]]);
        $validator = new WorkflowValidator(OperationRegistry::withDefaults());
        $result = $validator->validate($jsonl);

        $this->assertFalse($result['valid']);
        $this->assertGreaterThan(0, $result['errors']);
    }

    public function testValidateRejectsMissingRequiredFields(): void
    {
        $jsonl = json_encode(['type' => 'operationUpdate', 'operationId' => 'op-1', 'operation' => ['ApiCall' => ['method' => 'GET']]]);
        $validator = new WorkflowValidator(OperationRegistry::withDefaults());
        $result = $validator->validate($jsonl);

        $this->assertFalse($result['valid']);
    }

    // ── Auth ───────────────────────────────────────────────────────

    public function testAgentRegistrationAndAuth(): void
    {
        $auth = new AgentAuth();
        $apiKey = $auth->register('agent-1', 'Test Agent', ['api-1'], ['cred-1'], ['ApiCall']);

        $agentId = $auth->authenticate($apiKey);
        $this->assertSame('agent-1', $agentId);

        $this->assertTrue($auth->isApiAllowed('agent-1', 'api-1'));
        $this->assertFalse($auth->isApiAllowed('agent-1', 'api-2'));
        $this->assertTrue($auth->isOperationAllowed('agent-1', 'ApiCall'));
        $this->assertFalse($auth->isOperationAllowed('agent-1', 'FilterData'));
    }

    public function testAuthRejectsInvalidKey(): void
    {
        $auth = new AgentAuth();
        $auth->register('agent-1', 'Test');
        $this->assertNull($auth->authenticate('wrong-key'));
    }

    // ── Credentials ────────────────────────────────────────────────

    public function testCredentialsVault(): void
    {
        $vault = new CredentialsVault('test-master-key');
        $vault->store('api-token', 'bearer-token', 'secret-value-123', ['api' => 'test']);

        $this->assertTrue($vault->has('api-token'));
        $this->assertSame('secret-value-123', $vault->get('api-token'));
        $this->assertSame('bearer-token', $vault->getType('api-token'));

        $list = $vault->list();
        $this->assertCount(1, $list);
        $first = array_values($list)[0];
        $this->assertArrayNotHasKey('encrypted', $first); // Never expose encrypted value
    }

    public function testCredentialInjector(): void
    {
        $vault = new CredentialsVault('test-key');
        $vault->store('my-token', 'bearer-token', 'abc123');
        $injector = new CredentialInjector($vault);

        $config = [
            'method' => 'GET',
            'url' => 'https://api.example.com',
            'headers' => [
                'Authorization' => ['credentialRef' => ['id' => 'my-token']],
                'Accept' => 'application/json',
            ],
        ];

        $injected = $injector->inject($config);
        $this->assertSame('Bearer abc123', $injected['headers']['Authorization']);
        $this->assertSame('application/json', $injected['headers']['Accept']);
    }

    // ── Rate Limiter ───────────────────────────────────────────────

    public function testRateLimiter(): void
    {
        $limiter = new RateLimiter(new RateLimitConfig(requestsPerMinute: 3));

        $this->assertTrue($limiter->check('agent-1')['allowed']);
        $this->assertTrue($limiter->check('agent-1')['allowed']);
        $this->assertTrue($limiter->check('agent-1')['allowed']);
        $this->assertFalse($limiter->check('agent-1')['allowed']); // 4th request blocked
    }

    // ── A2E Facade ─────────────────────────────────────────────────

    public function testA2EFacade(): void
    {
        $a2e = new A2E(new Config(dataDir: $this->tmpDir, masterKey: 'test'));

        // Capabilities
        $caps = $a2e->capabilities();
        $this->assertNotEmpty($caps['capabilities']['supportedOperations']);
        $this->assertContains('ApiCall', $caps['capabilities']['supportedOperations']);
        $this->assertContains('FilterData', $caps['capabilities']['supportedOperations']);

        // Validate + Execute
        $jsonl = implode("\n", [
            json_encode(['type' => 'operationUpdate', 'operationId' => 'now', 'operation' => ['GetCurrentDateTime' => ['timezone' => 'UTC', 'outputPath' => '/workflow/now']]]),
            json_encode(['type' => 'operationUpdate', 'operationId' => 'fmt', 'operation' => ['FormatText' => ['inputPath' => '/workflow/now', 'format' => 'upper', 'outputPath' => '/workflow/upper']]]),
            json_encode(['type' => 'beginExecution', 'executionId' => 'e1', 'operationOrder' => ['now', 'fmt']]),
        ]);

        $validation = $a2e->validate($jsonl);
        $this->assertTrue($validation['valid']);

        $result = $a2e->execute($jsonl);
        $this->assertSame('success', $result->status);
        $this->assertArrayHasKey('now', $result->results);
        $this->assertArrayHasKey('fmt', $result->results);
    }

    public function testA2ERejectsInvalidWorkflow(): void
    {
        $a2e = new A2E(new Config(dataDir: $this->tmpDir, masterKey: 'test'));

        $jsonl = json_encode(['type' => 'operationUpdate', 'operationId' => 'bad', 'operation' => ['NonExistent' => []]]);
        $result = $a2e->execute($jsonl);
        $this->assertSame('error', $result->status);
    }
}
