<x-filament-panels::page>
    <form wire:submit="save" class="space-y-6">
        {{ $this->form }}

        <div class="flex justify-end">
            <x-filament::button type="submit" icon="heroicon-o-check-circle">
                Save {{ \App\Filament\Pages\GeneralSettings::SECTIONS[$this->section] }}
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
