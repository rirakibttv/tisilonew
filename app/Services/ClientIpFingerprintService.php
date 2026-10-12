<?php

namespace App\Services;

use Illuminate\Http\Request;

class ClientIpFingerprintService
{
    public function resolve(Request $request): ?string
    {
        $cloudflareIp = trim((string) $request->header('CF-Connecting-IP'));
        if ($cloudflareIp !== '' && filter_var($cloudflareIp, FILTER_VALIDATE_IP)) {
            return $cloudflareIp;
        }

        $ip = trim((string) $request->ip());

        return $ip !== '' && filter_var($ip, FILTER_VALIDATE_IP) ? $ip : null;
    }

    public function fingerprint(Request $request): ?string
    {
        $ip = $this->resolve($request);

        return $ip === null ? null : $this->fingerprintIp($ip);
    }

    public function fingerprintIp(string $ip): ?string
    {
        $ip = trim($ip);
        if (! filter_var($ip, FILTER_VALIDATE_IP)) {
            return null;
        }

        return hash_hmac('sha256', $ip, $this->hashKey());
    }

    private function hashKey(): string
    {
        $key = (string) config('app.key');
        if (str_starts_with($key, 'base64:')) {
            $decoded = base64_decode(substr($key, 7), true);

            return $decoded === false ? $key : $decoded;
        }

        return $key !== '' ? $key : 'tisilo-visitor-fingerprint';
    }
}
