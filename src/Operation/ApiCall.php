<?php

declare(strict_types=1);

namespace PHPA2E\Operation;

use PHPA2E\Executor\DataModel;

final class ApiCall implements OperationInterface
{
    public function type(): string { return 'ApiCall'; }

    public function execute(array $config, DataModel $data): array
    {
        $method = strtoupper($config['method'] ?? 'GET');
        $url = $data->resolveReferences($config['url'] ?? '');
        $timeout = (int)($config['timeout'] ?? 30000);
        $outputPath = $config['outputPath'] ?? '';
        $headers = $config['headers'] ?? [];
        $body = $config['body'] ?? null;

        // Resolve header references
        $curlHeaders = [];
        foreach ($headers as $key => $value) {
            if (is_array($value) && isset($value['credentialRef'])) {
                continue; // CredentialInjector handles this before execution
            }
            $curlHeaders[] = "{$key}: " . (is_string($value) ? $data->resolveReferences($value) : json_encode($value));
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $curlHeaders,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT_MS => $timeout,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);

        if ($body !== null && in_array($method, ['POST', 'PUT', 'PATCH'])) {
            $bodyStr = is_string($body) ? $data->resolveReferences($body) : json_encode($body);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $bodyStr);
            if (!in_array('Content-Type', array_map(fn($h) => explode(':', $h)[0], $curlHeaders))) {
                $curlHeaders[] = 'Content-Type: application/json';
                curl_setopt($ch, CURLOPT_HTTPHEADER, $curlHeaders);
            }
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new \RuntimeException("ApiCall failed: {$error}");
        }

        $parsed = json_decode($response, true) ?? $response;

        if ($outputPath !== '') {
            $data->set($outputPath, $parsed);
        }

        return [
            'status' => $httpCode,
            'data' => $parsed,
            'url' => $url,
            'method' => $method,
        ];
    }
}
