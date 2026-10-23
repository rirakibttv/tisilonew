<?php

namespace App\Filament\Pages;

use App\Models\SiteSetting;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Str;

class GeneralSettings extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'general-settings';

    protected string $view = 'filament.pages.general-settings';

    /** @var array<string, mixed> */
    public array $data = [];

    public string $section = 'general';

    /** @var array<string, string> */
    public const SECTIONS = [
        'general' => 'General Setting',
        'social' => 'Social Media',
        'contact' => 'Contact',
        'pages' => 'Create Page',
        'order_restriction' => 'Order Restriction',
        'email' => 'Email Settings',
        'cronjob' => 'Cronjob',
    ];

    public function mount(): void
    {
        $this->section = request()->string('section', 'general')->toString();
        abort_unless(array_key_exists($this->section, self::SECTIONS), 404);

        $values = SiteSetting::valuesFor($this->section);
        if ($this->section === 'general') {
            $legacySeo = SiteSetting::valuesFor('seo');
            $values['site_title'] ??= $values['site_name'] ?? 'Tisilo';
            $values['meta_description'] ??= $values['footer_about_text'] ?? '';
            $values['meta_keywords'] ??= $legacySeo['meta_keywords'] ?? $legacySeo['meta_tags'] ?? '';
            $values['search_console_verification'] ??= $legacySeo['search_console_verification'] ?? '';
        }

        if ($this->section === 'pages' && ($pageSlug = request()->string('page')->toString()) !== '' && $pageSlug !== 'create') {
            $page = collect($values['pages'] ?? [])->firstWhere('slug', $pageSlug);
            abort_unless($page, 404);
            $values = $page;
        }
        $secrets = SiteSetting::secretsFor($this->section);

        foreach ($this->secretFields() as $field) {
            if ($field === 'MAIL_PASSWORD' || str_contains($field, 'api_key')) {
                $secrets[$field] = null;
            }
        }

        $this->form->fill([...$values, ...$secrets]);
    }

    public function getTitle(): string|Htmlable
    {
        if ($this->section === 'pages' && ($pageSlug = request()->string('page')->toString()) !== '' && $pageSlug !== 'create') {
            return data_get(collect(SiteSetting::valuesFor('pages')['pages'] ?? [])->firstWhere('slug', $pageSlug), 'name', 'Edit Page');
        }

        return self::SECTIONS[$this->section];
    }

    public function getSubheading(): ?string
    {
        return 'Tisilo marketplace global configuration';
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components($this->components())
            ->statePath('data');
    }

    public function save(): void
    {
        $values = $this->form->getState();
        $secretUpdates = [];

        foreach ($this->secretFields() as $field) {
            $value = $values[$field] ?? null;
            unset($values[$field]);

            if (filled($value)) {
                $secretUpdates[$field] = $value;
            }
        }

        if ($this->section === 'pages' && ($pageSlug = request()->string('page')->toString()) !== '' && $pageSlug !== 'create') {
            $page = [...$values, 'slug' => Str::slug($values['slug'] ?? $pageSlug)];
            $pages = collect(SiteSetting::valuesFor('pages')['pages'] ?? [])->values();
            $index = $pages->search(fn (array $existing): bool => ($existing['slug'] ?? null) === $pageSlug);

            if ($index === false) {
                $pages->push($page);
            } else {
                $pages[$index] = [...$pages[$index], ...$page];
            }

            $values = ['pages' => $pages->values()->all()];
        } elseif ($this->section === 'pages') {
            $values['pages'] = collect($values['pages'] ?? [])->map(function (array $page): array {
                $page['slug'] = Str::slug($page['slug'] ?: $page['name']);

                return $page;
            })->values()->all();
        }

        SiteSetting::put(
            $this->section,
            [...SiteSetting::valuesFor($this->section), ...$values],
            $secretUpdates,
        );

        Notification::make()
            ->success()
            ->title(self::SECTIONS[$this->section].' saved')
            ->send();

        $savedValues = SiteSetting::valuesFor($this->section);
        if ($this->section === 'pages' && ($pageSlug = request()->string('page')->toString()) !== '' && $pageSlug !== 'create') {
            $savedValues = collect($savedValues['pages'] ?? [])->firstWhere('slug', $pageSlug) ?? [];
        }

        $this->form->fill([
            ...$savedValues,
            ...collect(SiteSetting::secretsFor($this->section))
                ->except(['MAIL_PASSWORD', 'fraud_api_key', 'duplicate_order_api_key'])
                ->all(),
        ]);
    }

    /** @return array<string> */
    private function secretFields(): array
    {
        return match ($this->section) {
            'email' => ['MAIL_PASSWORD'],
            default => [],
        };
    }

    /** @return array<mixed> */
    private function components(): array
    {
        return match ($this->section) {
            'general' => $this->generalComponents(),
            'social' => $this->socialComponents(),
            'contact' => $this->contactComponents(),
            'pages' => $this->pageComponents(),
            'order_restriction' => $this->orderRestrictionComponents(),
            'email' => $this->emailComponents(),
            'cronjob' => $this->cronjobComponents(),
        };
    }

    /** @return array<mixed> */
    private function generalComponents(): array
    {
        return [
            Section::make('Site Title & Homepage SEO')->description('The site title is used for storefront branding and page titles. Homepage metadata is emitted in the storefront HTML; Google may choose a different search snippet.')
                ->columns(2)->schema([
                    TextInput::make('site_title')->label('Site Title')->required()->maxLength(120)
                        ->helperText('Used as the storefront name in the header, footer and page titles.'),
                    Textarea::make('meta_description')->label('Meta Description')->rows(3)->maxLength(320)
                        ->helperText('Emitted as entered on the homepage. Google may choose a different search snippet.')
                        ->columnSpanFull(),
                    Textarea::make('meta_keywords')->label('Meta Keywords')->rows(3)->maxLength(1000)
                        ->helperText('Comma-separated keywords. Included in the HTML meta tag; Google does not use this tag for ranking.')
                        ->columnSpanFull(),
                    TextInput::make('search_console_verification')->label('Google Search Console Verification Token')
                        ->maxLength(500)->columnSpanFull()
                        ->helperText('Paste the token from the HTML tag method. This is separate from the Search Console API connection.'),
                    TextInput::make('google_play_link')->label('Google Play Link')->url(),
                    TextInput::make('app_store_link')->label('App Store Link')->url(),
                    TextInput::make('facebook_page_username')->maxLength(120),
                    Textarea::make('footer_about_text')->rows(3)->columnSpanFull(),
                ]),
            Section::make('Theme Appearance')->columns(4)->schema([
                ColorPicker::make('primary_color')->default('#f97316'),
                ColorPicker::make('secondary_color')->default('#0f172a'),
                ColorPicker::make('footer_color')->default('#020617'),
                ColorPicker::make('copyright_color')->default('#0f172a'),
                FileUpload::make('white_logo')->image()->disk('public')->directory('settings/logos'),
                FileUpload::make('dark_logo')->image()->disk('public')->directory('settings/logos'),
                FileUpload::make('favicon')->image()->disk('public')->directory('settings/icons'),
                FileUpload::make('og_banner')->image()->disk('public')->directory('settings/social'),
            ]),
            Section::make('Business Logic')->columns(3)->schema([
                Toggle::make('vendor_enabled')->default(true),
            ]),
        ];
    }

    /** @return array<mixed> */
    private function socialComponents(): array
    {
        return [
            Section::make('Social Media Links')->schema([
                Repeater::make('links')->reorderable()->collapsible()->schema([
                    TextInput::make('title')->required(),
                    TextInput::make('icon')->placeholder('heroicon-o-share'),
                    TextInput::make('link')->url()->required(),
                    ColorPicker::make('color')->default('#2563eb'),
                    Toggle::make('status')->default(true),
                ])->columns(5)->defaultItems(0),
            ]),
        ];
    }

    /** @return array<mixed> */
    private function contactComponents(): array
    {
        return [
            Section::make('Contact Information')->columns(2)->schema([
                TextInput::make('hotline')->tel(),
                TextInput::make('phone')->tel(),
                TextInput::make('email')->email(),
                TextInput::make('hotmail')->email(),
                TextInput::make('whatsapp')->tel(),
                TextInput::make('maplink')->url(),
                Textarea::make('address')->required()->columnSpanFull(),
                Toggle::make('status')->default(true),
            ]),
        ];
    }

    /** @return array<mixed> */
    private function pageComponents(): array
    {
        $pageSlug = request()->string('page')->toString();
        if ($pageSlug !== '' && $pageSlug !== 'create') {
            return [
                Section::make('Page Content')->columns(2)->schema([
                    TextInput::make('name')->required()->maxLength(120),
                    TextInput::make('title')->required()->maxLength(180),
                    TextInput::make('slug')->disabled()->dehydrated()->required(),
                    Toggle::make('status')->label('Published')->default(false),
                    RichEditor::make('description')->required()->columnSpanFull(),
                ]),
            ];
        }

        return [
            Section::make('Storefront Pages')->schema([
                Repeater::make('pages')->reorderable()->collapsible()->itemLabel(fn (array $state): ?string => $state['name'] ?? null)->schema([
                    TextInput::make('name')->required(),
                    TextInput::make('title')->required(),
                    TextInput::make('slug')->helperText('Leave blank to generate from name'),
                    Toggle::make('status')->default(true),
                    RichEditor::make('description')->required()->columnSpanFull(),
                ])->columns(2)->defaultItems(0),
            ]),
        ];
    }

    /** @return array<mixed> */
    private function orderRestrictionComponents(): array
    {
        return [
            Section::make('Order Restriction Configuration')->columns(3)->schema([
                Toggle::make('enabled')->default(false),
                TextInput::make('order_limit_time')->label('Restriction Time (Hours)')->numeric()->required()->minValue(1),
                TextInput::make('order_limit_qty')->label('Maximum Order Quantity')->numeric()->required()->minValue(1),
            ]),
        ];
    }

    /** @return array<mixed> */
    private function emailComponents(): array
    {
        return [
            Section::make('SMTP Configuration')->columns(2)->schema([
                Select::make('MAIL_MAILER')->options(['smtp' => 'SMTP', 'sendmail' => 'Sendmail', 'log' => 'Log'])->required(),
                TextInput::make('MAIL_HOST')->required(),
                TextInput::make('MAIL_PORT')->numeric()->required(),
                Select::make('MAIL_ENCRYPTION')->options(['tls' => 'TLS', 'ssl' => 'SSL', 'none' => 'None'])->required(),
                TextInput::make('MAIL_USERNAME'),
                TextInput::make('MAIL_PASSWORD')->password()->revealable()->placeholder('Leave blank to keep the saved password'),
                TextInput::make('MAIL_FROM_ADDRESS')->email()->required(),
                TextInput::make('MAIL_FROM_NAME')->required(),
            ]),
        ];
    }

    /** @return array<mixed> */
    private function cronjobComponents(): array
    {
        return [
            Section::make('Laravel Scheduler')->columns(2)->schema([
                Toggle::make('scheduler_enabled')->default(true),
                TextInput::make('frequency_minutes')->label('Scheduler Frequency (Minutes)')->numeric()->minValue(1)->maxValue(1440)->required(),
                TextInput::make('batch_size')->numeric()->minValue(1)->maxValue(500)->required(),
                TextInput::make('server_command')->disabled()->columnSpanFull(),
                TextInput::make('last_run_at')->disabled(),
                TextInput::make('last_run_status')->disabled(),
            ]),
            Section::make('GitHub Production Auto-Deploy')->columns(2)->schema([
                TextInput::make('github_deploy_frequency_minutes')
                    ->label('GitHub Check Frequency (Minutes)')
                    ->numeric()
                    ->disabled()
                    ->dehydrated()
                    ->default(1),
                TextInput::make('github_deploy_branch')
                    ->label('Deployment Branch')
                    ->disabled()
                    ->dehydrated()
                    ->default('main'),
                TextInput::make('github_deploy_command')
                    ->label('cPanel Cron Schedule')
                    ->disabled()
                    ->dehydrated()
                    ->columnSpanFull(),
            ]),
        ];
    }
}
