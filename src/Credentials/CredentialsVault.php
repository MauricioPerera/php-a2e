<?php
declare(strict_types=1);
namespace PHPA2E\Credentials;

/**
 * Encrypted credential storage. Agents reference by ID, never see values.
 * Uses AES-256-CBC (native PHP openssl).
 */
final class CredentialsVault
{
    /** @var array<string, array> */
    private array $credentials = [];
    private string $encryptionKey;

    public function __construct(string $masterKey, private readonly ?string $storagePath = null)
    {
        $this->encryptionKey = hash('sha256', $masterKey, true);
        if ($storagePath !== null && file_exists($storagePath)) {
            $data = json_decode(file_get_contents($storagePath), true);
            $this->credentials = $data['credentials'] ?? [];
        }
    }

    public function store(string $id, string $type, string $value, array $metadata = [], string $description = ''): void
    {
        $iv = random_bytes(16);
        $encrypted = openssl_encrypt($value, 'aes-256-cbc', $this->encryptionKey, OPENSSL_RAW_DATA, $iv);
        $this->credentials[$id] = [
            'id' => $id,
            'type' => $type,
            'encrypted' => base64_encode($iv . $encrypted),
            'metadata' => $metadata,
            'description' => $description,
        ];
        $this->save();
    }

    /** Get decrypted value. Only the server calls this, never exposed to agents. */
    public function get(string $id): ?string
    {
        $cred = $this->credentials[$id] ?? null;
        if ($cred === null) return null;

        $raw = base64_decode($cred['encrypted']);
        $iv = substr($raw, 0, 16);
        $encrypted = substr($raw, 16);
        return openssl_decrypt($encrypted, 'aes-256-cbc', $this->encryptionKey, OPENSSL_RAW_DATA, $iv) ?: null;
    }

    /** List credentials with metadata only (no values). */
    public function list(): array
    {
        return array_map(fn($c) => [
            'id' => $c['id'],
            'type' => $c['type'],
            'description' => $c['description'],
            'metadata' => $c['metadata'],
        ], $this->credentials);
    }

    public function has(string $id): bool
    {
        return isset($this->credentials[$id]);
    }

    public function getType(string $id): ?string
    {
        return $this->credentials[$id]['type'] ?? null;
    }

    private function save(): void
    {
        if ($this->storagePath !== null) {
            $dir = dirname($this->storagePath);
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            file_put_contents($this->storagePath, json_encode(['credentials' => $this->credentials], JSON_PRETTY_PRINT));
        }
    }
}
