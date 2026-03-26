<?php

declare(strict_types=1);

namespace PHPA2E\Executor;

/**
 * Hierarchical data model for workflow state.
 *
 * Uses JSON Pointer-like paths: /workflow/users, /workflow/filtered
 * Operations read from and write to this model during execution.
 */
final class DataModel
{
    private array $data = [];

    /**
     * Get a value by path.
     *
     * @param string $path e.g., "/workflow/users" or "/workflow/users/0/name"
     * @return mixed
     */
    public function get(string $path): mixed
    {
        $segments = self::parsePath($path);
        $current = $this->data;

        foreach ($segments as $seg) {
            if (is_array($current) && (isset($current[$seg]) || array_key_exists($seg, $current))) {
                $current = $current[$seg];
            } elseif (is_array($current) && is_numeric($seg) && isset($current[(int)$seg])) {
                $current = $current[(int)$seg];
            } else {
                return null;
            }
        }

        return $current;
    }

    /**
     * Set a value at path, creating intermediate arrays as needed.
     */
    public function set(string $path, mixed $value): void
    {
        $segments = self::parsePath($path);
        $ref = &$this->data;

        foreach ($segments as $i => $seg) {
            if ($i === count($segments) - 1) {
                $ref[$seg] = $value;
            } else {
                if (!isset($ref[$seg]) || !is_array($ref[$seg])) {
                    $ref[$seg] = [];
                }
                $ref = &$ref[$seg];
            }
        }
    }

    /**
     * Check if a path exists.
     */
    public function has(string $path): bool
    {
        return $this->get($path) !== null;
    }

    /**
     * Resolve path references in a string.
     * Replaces {/workflow/data} with the actual value.
     */
    public function resolveReferences(string $text): string
    {
        return preg_replace_callback('/\{(\/[^}]+)\}/', function ($matches) {
            $value = $this->get($matches[1]);
            if (is_array($value)) return json_encode($value);
            return (string)($value ?? $matches[0]);
        }, $text);
    }

    /**
     * Get all data.
     */
    public function all(): array
    {
        return $this->data;
    }

    /**
     * Parse a JSON Pointer path into segments.
     *
     * @return string[]
     */
    private static function parsePath(string $path): array
    {
        $path = ltrim($path, '/');
        if ($path === '') return [];
        return explode('/', $path);
    }
}
