<?php
declare(strict_types=1);
namespace PHPA2E\Knowledge;

/**
 * API catalog: registered APIs with endpoints, auth, and metadata.
 */
final class ApiKnowledgeBase
{
    /** @var array<string, array> */
    private array $apis = [];

    public function __construct(?string $defsPath = null)
    {
        if ($defsPath !== null && file_exists($defsPath)) {
            $data = json_decode(file_get_contents($defsPath), true);
            $this->apis = $data['apis'] ?? [];
        }
    }

    public function addApi(string $id, string $baseUrl, array $endpoints = [], ?array $authentication = null): void
    {
        $this->apis[$id] = [
            'id' => $id,
            'baseUrl' => $baseUrl,
            'endpoints' => $endpoints,
            'authentication' => $authentication,
        ];
    }

    public function getApi(string $id): ?array { return $this->apis[$id] ?? null; }

    public function allApis(): array { return $this->apis; }

    /** Check if a URL matches any registered API. */
    public function isUrlRegistered(string $url): bool
    {
        foreach ($this->apis as $api) {
            if (str_starts_with($url, $api['baseUrl'])) return true;
        }
        return false;
    }

    /** Build capability catalog for an agent. */
    public function buildCatalog(): array
    {
        return ['apis' => $this->apis];
    }
}
