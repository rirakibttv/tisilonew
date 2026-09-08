<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GoogleServiceAccountTokenService
{
    /**
     * @param  array<int, string>  $scopes
     */
    public function accessToken(string $serviceAccountJson, array $scopes): string
    {
        $credentials = json_decode(trim($serviceAccountJson), true);
        if (! is_array($credentials)) {
            throw new RuntimeException('The Google service-account JSON is invalid.');
        }

        $email = trim((string) ($credentials['client_email'] ?? ''));
        $privateKey = (string) ($credentials['private_key'] ?? '');
        $tokenUri = trim((string) ($credentials['token_uri'] ?? 'https://oauth2.googleapis.com/token'));

        if ($email === '' || $privateKey === '' || ! filter_var($tokenUri, FILTER_VALIDATE_URL)) {
            throw new RuntimeException('The Google service-account JSON must contain client_email, private_key, and a valid token_uri.');
        }

        sort($scopes);
        $cacheKey = 'google-service-account-token:'.hash('sha256', $email.'|'.implode(' ', $scopes));

        return Cache::remember($cacheKey, now()->addMinutes(50), function () use ($email, $privateKey, $tokenUri, $scopes): string {
            $issuedAt = now()->getTimestamp();
            $header = $this->base64Url(json_encode(['alg' => 'RS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR));
            $claims = $this->base64Url(json_encode([
                'iss' => $email,
                'scope' => implode(' ', $scopes),
                'aud' => $tokenUri,
                'iat' => $issuedAt,
                'exp' => $issuedAt + 3600,
            ], JSON_THROW_ON_ERROR));
            $unsigned = $header.'.'.$claims;
            $signature = '';

            if (! openssl_sign($unsigned, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
                throw new RuntimeException('The Google service-account private key could not sign the authentication request.');
            }

            $response = Http::asForm()
                ->acceptJson()
                ->timeout(15)
                ->retry(2, 250, throw: false)
                ->post($tokenUri, [
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion' => $unsigned.'.'.$this->base64Url($signature),
                ]);

            $token = trim((string) $response->json('access_token'));
            if (! $response->successful() || $token === '') {
                $message = trim((string) ($response->json('error_description') ?: $response->json('error')));
                throw new RuntimeException('Google authentication failed'.($message !== '' ? ': '.$message : " (HTTP {$response->status()})"));
            }

            return $token;
        });
    }

    private function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
