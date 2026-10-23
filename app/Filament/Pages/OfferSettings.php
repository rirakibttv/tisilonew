<?php

namespace App\Filament\Pages;

use App\Models\SiteSetting;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class OfferSettings extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'offer-settings';

    protected string $view = 'filament.pages.offer-settings';

    /** @var array<string, mixed> */
    public array $data = [];

    public function mount(): void
    {
        $this->form->fill(SiteSetting::valuesFor('offers'));
    }

    public function getTitle(): string
    {
        return 'Offer Settings';
    }

    public function getSubheading(): ?string
    {
        return 'Manage homepage offer countdowns and default Flash Sale scheduling.';
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Homepage Offer Countdown')
                    ->description('The Hot Deal countdown uses this date. Each Flash Sale campaign still has its own schedule.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('hot_deal_end_date')
                            ->label('Hot Deal End Date')
                            ->type('date'),
                        TextInput::make('flash_sale_default_end_date')
                            ->label('Flash Sale End Date')
                            ->type('date')
                            ->helperText('Used as the default end date when creating a new Flash Sale; you can change it per campaign.'),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        SiteSetting::put('offers', $this->form->getState());
        $this->form->fill(SiteSetting::valuesFor('offers'));

        Notification::make()
            ->success()
            ->title('Offer settings saved')
            ->send();
    }
}
