<?php

namespace App\Services\Google;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Exchanges a Google Cloud service account key for a short-lived OAuth2
 * access token, using the RFC 7523 JWT Bearer flow.
 *
 * This is implemented directly against Google's documented HTTP endpoints
 * (no google/apiclient dependency) so the full auth mechanism — signing,
 * claims, token exchange — is transparent and auditable in one place.
 */
class ServiceAccountTokenProvider
{
    private ?array $credentials = null;

    public function __construct(
        private readonly string $credentialsPath,
        private readonly string $scope,
        private readonly string $tokenUri,
    ) {}

    /**
     * Returns a valid bearer access token for the configured service account
     * + scope. Tokens are cached for less than their real lifetime so we
     * don't re-sign a new JWT and hit Google's token endpoint on every URL
     * submission.
     */
    public function getAccessToken(): string
    {
        $cacheKey = 'google_indexing:access_token:'.md5($this->credentialsPath.$this->scope);

        return Cache::remember($cacheKey, now()->addMinutes(50), fn () => $this->requestNewAccessToken());
    }

    private function requestNewAccessToken(): string
    {
        $credentials = $this->loadCredentials();
        $assertion = $this->buildSignedJwt($credentials);

        $response = Http::asForm()->post($this->tokenUri, [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $assertion,
        ]);

        $accessToken = $response->json('access_token');

        if (! $response->successful() || ! is_string($accessToken)) {
            throw new RuntimeException(
                'Google OAuth2 token exchange failed: HTTP '.$response->status().' '.$response->body()
            );
        }

        return $accessToken;
    }

    private function loadCredentials(): array
    {
        if ($this->credentials !== null) {
            return $this->credentials;
        }

        if (! is_file($this->credentialsPath)) {
            throw new RuntimeException("Google service account credentials file not found at [{$this->credentialsPath}].");
        }

        $decoded = json_decode((string) file_get_contents($this->credentialsPath), true);

        if (! is_array($decoded) || ! isset($decoded['private_key'], $decoded['client_email'])) {
            throw new RuntimeException('Google service account credentials file is malformed.');
        }

        return $this->credentials = $decoded;
    }

    private function buildSignedJwt(array $credentials): string
    {
        $now = time();

        $header = $this->base64UrlEncode((string) json_encode([
            'alg' => 'RS256',
            'typ' => 'JWT',
        ]));

        $claims = $this->base64UrlEncode((string) json_encode([
            'iss' => $credentials['client_email'],
            'scope' => $this->scope,
            'aud' => $this->tokenUri,
            'iat' => $now,
            'exp' => $now + 3600,
        ]));

        $signingInput = "{$header}.{$claims}";

        $signature = '';
        $signed = openssl_sign($signingInput, $signature, $credentials['private_key'], OPENSSL_ALGO_SHA256);

        if (! $signed) {
            throw new RuntimeException('Failed to sign JWT assertion with the service account private key.');
        }

        return $signingInput.'.'.$this->base64UrlEncode($signature);
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
