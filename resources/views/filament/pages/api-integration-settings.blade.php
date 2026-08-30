<x-filament-panels::page>
    @if ($this->section === 'cloudflare')
        <div class="rounded-xl border border-blue-200 bg-blue-50 p-4 text-sm leading-6 text-blue-900 dark:border-blue-900 dark:bg-blue-950/40 dark:text-blue-100">
            <p class="font-semibold">Safe ecommerce caching</p>
            <p class="mt-1">This module manages a dedicated static-asset rule and preserves every existing Cloudflare rule. Dynamic HTML is intentionally excluded so carts, sessions, checkout forms, and CSRF tokens cannot leak between visitors.</p>
        </div>
    @endif

    <form wire:submit="save" class="space-y-6" autocomplete="off">
        {{ $this->form }}

        <div class="flex justify-end">
            <x-filament::button type="submit" icon="heroicon-o-shield-check">
                Save {{ \App\Filament\Pages\ApiIntegrationSettings::SECTIONS[$this->section] }}
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
