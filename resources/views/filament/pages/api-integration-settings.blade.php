<x-filament-panels::page>
    <form wire:submit="save" class="space-y-6" autocomplete="off">
        {{ $this->form }}

        <div class="flex justify-end">
            <x-filament::button type="submit" icon="heroicon-o-shield-check">
                Save {{ \App\Filament\Pages\ApiIntegrationSettings::SECTIONS[$this->section] }}
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
