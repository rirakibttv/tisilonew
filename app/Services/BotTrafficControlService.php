<?php

namespace App\Services;

use App\Models\SiteSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class BotTrafficControlService
{
    private const SETTING_KEY = 'bot_security';

    public function __construct(
        private BotTrafficService $botTraffic,
        private ClientIpFingerprintService $fingerprints,
    ) {}

    /** @return array{type: 'bot'|'ip', label: string}|null */
    public function blockedBy(Request $request): ?array
    {
        $settings = $this->settings();
        $ipHash = $this->fingerprints->fingerprint($request);

        if ($ipHash !== null && in_array($ipHash, $settings['blocked_ip_hashes'], true)) {
            return ['type' => 'ip', 'label' => $this->maskedFingerprint($ipHash)];
        }

        $bot = $this->botTraffic->identify($request->userAgent());
        if ($bot !== null && $this->isBotBlocked($bot['name'], $settings)) {
            return ['type' => 'bot', 'label' => $bot['name']];
        }

        return null;
    }

    public function blockBot(string $botName): void
    {
        $botName = $this->cleanBotName($botName);
        if ($botName === '') {
            return;
        }

        $settings = $this->settings();
        if (! $this->isBotBlocked($botName, $settings)) {
            $settings['blocked_bots'][] = $botName;
            $this->save($settings);
        }
    }

    public function unblockBot(string $botName): void
    {
        $needle = mb_strtolower($this->cleanBotName($botName));
        $settings = $this->settings();
        $settings['blocked_bots'] = array_values(array_filter(
            $settings['blocked_bots'],
            fn (string $value): bool => mb_strtolower($value) !== $needle,
        ));
        $this->save($settings);
    }

    public function blockIpHash(string $ipHash): void
    {
        $ipHash = strtolower(trim($ipHash));
        if (! preg_match('/^[a-f0-9]{64}$/', $ipHash)) {
            return;
        }

        $settings = $this->settings();
        if (! in_array($ipHash, $settings['blocked_ip_hashes'], true)) {
            $settings['blocked_ip_hashes'][] = $ipHash;
            $this->save($settings);
        }
    }

    public function unblockIpHash(string $ipHash): void
    {
        $settings = $this->settings();
        $settings['blocked_ip_hashes'] = array_values(array_diff(
            $settings['blocked_ip_hashes'],
            [strtolower(trim($ipHash))],
        ));
        $this->save($settings);
    }

    public function botIsBlocked(string $botName): bool
    {
        return $this->isBotBlocked($botName, $this->settings());
    }

    public function ipHashIsBlocked(?string $ipHash): bool
    {
        return $ipHash !== null
            && in_array(strtolower(trim($ipHash)), $this->settings()['blocked_ip_hashes'], true);
    }

    public function maskedFingerprint(?string $ipHash): string
    {
        $ipHash = strtolower(trim((string) $ipHash));

        return preg_match('/^[a-f0-9]{64}$/', $ipHash)
            ? 'IP-'.strtoupper(substr($ipHash, -12))
            : 'Unknown IP';
    }

    /** @return array{blocked_bots: list<string>, blocked_ip_hashes: list<string>} */
    public function settings(): array
    {
        $values = SiteSetting::valuesFor(self::SETTING_KEY);

        return [
            'blocked_bots' => $this->stringList($values['blocked_bots'] ?? []),
            'blocked_ip_hashes' => array_values(array_filter(
                $this->stringList($values['blocked_ip_hashes'] ?? []),
                fn (string $value): bool => (bool) preg_match('/^[a-f0-9]{64}$/', $value),
            )),
        ];
    }

    /** @param array{blocked_bots: list<string>, blocked_ip_hashes: list<string>} $settings */
    private function isBotBlocked(string $botName, array $settings): bool
    {
        $needle = mb_strtolower($this->cleanBotName($botName));

        foreach ($settings['blocked_bots'] as $blockedBot) {
            if (mb_strtolower($blockedBot) === $needle) {
                return true;
            }
        }

        return false;
    }

    /** @param array{blocked_bots: list<string>, blocked_ip_hashes: list<string>} $settings */
    private function save(array $settings): void
    {
        SiteSetting::put(self::SETTING_KEY, [
            'blocked_bots' => array_slice(array_values(array_unique($settings['blocked_bots'])), -250),
            'blocked_ip_hashes' => array_slice(array_values(array_unique($settings['blocked_ip_hashes'])), -1000),
        ]);
    }

    private function cleanBotName(string $botName): string
    {
        return trim(Str::limit(strip_tags($botName), 80, ''));
    }

    /** @return list<string> */
    private function stringList(mixed $values): array
    {
        if (! is_array($values)) {
            return [];
        }

        return array_values(array_filter(array_map(
            fn (mixed $value): string => strtolower(trim((string) $value)),
            $values,
        )));
    }
}
