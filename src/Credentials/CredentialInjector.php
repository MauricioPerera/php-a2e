<?php
declare(strict_types=1);
namespace PHPA2E\Credentials;

/**
 * Resolves credentialRef in ApiCall headers, injecting actual values.
 */
final class CredentialInjector
{
    public function __construct(private readonly CredentialsVault $vault) {}

    /** Inject credentials into an ApiCall config. */
    public function inject(array $config): array
    {
        $headers = $config['headers'] ?? [];
        foreach ($headers as $key => $value) {
            if (is_array($value) && isset($value['credentialRef']['id'])) {
                $credId = $value['credentialRef']['id'];
                $credValue = $this->vault->get($credId);
                $credType = $this->vault->getType($credId);

                if ($credValue !== null) {
                    $headers[$key] = match ($credType) {
                        'bearer-token', 'bearer' => "Bearer {$credValue}",
                        default => $credValue,
                    };
                }
            }
        }
        $config['headers'] = $headers;
        return $config;
    }
}
