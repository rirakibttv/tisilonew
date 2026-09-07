<?php

namespace App\Services;

use App\Models\SiteSetting;

class PaymentMethodService
{
    /** @return array<string, array{label: string, description: string}> */
    public function enabled(): array
    {
        $settings = SiteSetting::valuesFor('payment');
        $methods = [];

        if ($this->enabledValue($settings['cod_enabled'] ?? true)) {
            $methods['cod'] = [
                'label' => 'ক্যাশ অন ডেলিভারি',
                'description' => 'পণ্য হাতে পাওয়ার পর মূল্য পরিশোধ করুন।',
            ];
        }

        if ($this->enabledValue($settings['bkash_enabled'] ?? false)) {
            $methods['bkash'] = [
                'label' => 'bKash',
                'description' => 'নিরাপদ bKash checkout-এ গিয়ে পেমেন্ট সম্পন্ন করুন।',
            ];
        }

        return $methods;
    }

    /** @return array<int, string> */
    public function keys(): array
    {
        return array_keys($this->enabled());
    }

    public function default(): ?string
    {
        $methods = $this->enabled();
        $configured = (string) (SiteSetting::valuesFor('payment')['default_gateway'] ?? 'cod');

        return array_key_exists($configured, $methods)
            ? $configured
            : array_key_first($methods);
    }

    public function label(string $method): string
    {
        return $this->enabled()[$method]['label'] ?? match ($method) {
            'cod' => 'ক্যাশ অন ডেলিভারি',
            'bkash' => 'bKash',
            default => str($method)->replace('_', ' ')->headline()->toString(),
        };
    }

    private function enabledValue(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOL);
    }
}
