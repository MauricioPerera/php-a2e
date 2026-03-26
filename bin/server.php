<?php
declare(strict_types=1);

$autoloadPaths = [__DIR__ . '/../vendor/autoload.php', __DIR__ . '/../../../autoload.php'];
foreach ($autoloadPaths as $path) { if (file_exists($path)) { require_once $path; break; } }

use PHPA2E\A2E;
use PHPA2E\Config;
use PHPA2E\Http\Router;
use PHPA2E\Http\Controller\HealthController;
use PHPA2E\Http\Controller\CapabilitiesController;
use PHPA2E\Http\Controller\WorkflowController;
use PHPA2E\Http\Controller\ExecutionController;

$dir = './data'; $apiKey = null; $masterKey = 'default-key';
$args = array_slice($argv ?? [], 1);
for ($i = 0; $i < count($args); $i++) {
    match ($args[$i]) {
        '--dir' => $dir = $args[++$i] ?? $dir,
        '--api-key' => $apiKey = $args[++$i] ?? null,
        '--master-key' => $masterKey = $args[++$i] ?? $masterKey,
        default => null,
    };
}

$a2e = new A2E(new Config(dataDir: $dir, masterKey: $masterKey, logDir: $dir . '/logs'));
$currentAgentId = null;

$getAgentId = function () use (&$currentAgentId) { return $currentAgentId; };

$router = new Router();
HealthController::register($router, $a2e);
CapabilitiesController::register($router, $a2e, $getAgentId);
WorkflowController::register($router, $a2e, $getAgentId);
ExecutionController::register($router, $a2e);

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$uri = $_SERVER['REQUEST_URI'] ?? '/';
$body = file_get_contents('php://input') ?: '';

// CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key');
if ($method === 'OPTIONS') { http_response_code(204); exit; }

// Auth
if ($apiKey !== null && !str_starts_with($uri, '/health')) {
    $token = $_SERVER['HTTP_X_API_KEY'] ?? '';
    if ($token === '') {
        $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        $token = str_starts_with($auth, 'Bearer ') ? substr($auth, 7) : '';
    }
    $agentId = $a2e->auth->authenticate($token);
    if ($agentId === null && !hash_equals($apiKey, $token)) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
    $currentAgentId = $agentId;
}

[$status, $data] = $router->dispatch($method, $uri, $body);
http_response_code($status);
header('Content-Type: application/json');
echo json_encode($data, JSON_UNESCAPED_SLASHES);
