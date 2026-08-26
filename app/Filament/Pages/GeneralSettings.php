<?php

namespace App\Filament\Pages;

use App\Models\Product;
use App\Models\SiteSetting;
use Filament\Actions\Action;
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
use Illuminate\Support\Facades\File;
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
        'seo' => 'SEO Settings',
        'social' => 'Social Media',
        'contact' => 'Contact',
        'pages' => 'Create Page',
        'order_restriction' => 'Order Restriction',
        'email' => 'Email Settings',
        'cronjob' => 'Cronjob',
        'sitemap' => 'Sitemap Settings',
        'fraud' => 'Fraud API Settings',
        'shipping' => 'Shipping Settings',
    ];

    public function mount(): void
    {
        $this->section = request()->string('section', 'general')->toString();
        abort_unless(array_key_exists($this->section, self::SECTIONS), 404);

        $values = SiteSetting::valuesFor($this->section);
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

        if ($this->section === 'pages') {
            $values['pages'] = collect($values['pages'] ?? [])->map(function (array $page): array {
                $page['slug'] = Str::slug($page['slug'] ?: $page['name']);

                return $page;
            })->values()->all();
        }

        SiteSetting::put($this->section, $values, $secretUpdates);

        Notification::make()
            ->success()
            ->title(self::SECTIONS[$this->section].' saved')
            ->send();

        $this->form->fill([
            ...SiteSetting::valuesFor($this->section),
            ...collect(SiteSetting::secretsFor($this->section))
                ->except(['MAIL_PASSWORD', 'fraud_api_key', 'duplicate_order_api_key'])
                ->all(),
        ]);
    }

    /** @return array<Action> */
    protected function getHeaderActions(): array
    {
        if ($this->section !== 'sitemap') {
            return [];
        }

        return [
            Action::make('generateSitemap')
                ->label('Generate Sitemap Now')
                ->icon('heroicon-o-arrow-path')
                ->action('generateSitemap'),
        ];
    }

    public function generateSitemap(): void
    {
        $settings = SiteSetting::valuesFor('sitemap');
        $urls = collect([
            ['loc' => route('store.home'), 'priority' => '1.0'],
            ['loc' => route('store.products.index'), 'priority' => '0.9'],
        ]);

        if ($settings['include_products'] ?? true) {
            $urls->push(...Product::query()->where('status', 'published')->get(['slug'])->map(
                fn (Product $product): array => ['loc' => route('store.products.show', $product), 'priority' => '0.8'],
            ));
        }

        if ($settings['include_pages'] ?? true) {
            $urls->push(...collect(SiteSetting::valuesFor('pages')['pages'] ?? [])
                ->where('status', true)
                ->map(fn (array $page): array => [
                    'loc' => route('store.pages.show', ['slug' => $page['slug']]),
                    'priority' => '0.6',
                ]));
        }

        $frequency = $settings['change_frequency'] ?? 'daily';
        $body = $urls->unique('loc')->map(function (array $url) use ($frequency): string {
            $location = htmlspecialchars($url['loc'], ENT_XML1);

            return "  <url>\n    <loc>{$location}</loc>\n    <changefreq>{$frequency}</changefreq>\n    <priority>{$url['priority']}</priority>\n  </url>";
        })->implode("\n");
        $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n{$body}\n</urlset>\n";

        File::put(public_path('sitemap.xml'), $xml);
        SiteSetting::put('sitemap', [
            ...$settings,
            'last_generated_at' => now()->toISOString(),
        ]);

        Notification::make()->success()->title('Sitemap generated successfully')->send();
    }

    /** @return array<string> */
    private function secretFields(): array
    {
        return match ($this->section) {
            'email' => ['MAIL_PASSWORD'],
            'fraud' => ['fraud_api_key', 'duplicate_order_api_key'],
            default => [],
        };
    }

    /** @return array<mixed> */
    private function components(): array
    {
        return match ($this->section) {
            'general' => $this->generalComponents(),
            'seo' => $this->seoComponents(),
            'social' => $this->socialComponents(),
            'contact' => $this->contactComponents(),
            'pages' => $this->pageComponents(),
            'order_restriction' => $this->orderRestrictionComponents(),
            'email' => $this->emailComponents(),
            'cronjob' => $this->cronjobComponents(),
            'sitemap' => $this->sitemapComponents(),
            'fraud' => $this->fraudComponents(),
            'shipping' => $this->shippingComponents(),
        };
    }

    /** @return array<mixed> */
    private function generalComponents(): array
    {
        return [
            Section::make('Brand & Store Information')->columns(2)->schema([
                TextInput::make('site_name')->required()->maxLength(120),
                TextInput::make('facebook_page_username')->maxLength(120),
                Textarea::make('top_headline')->rows(2)->columnSpanFull(),
                Textarea::make('footer_about_text')->rows(3)->columnSpanFull(),
                TextInput::make('google_play_link')->url(),
                TextInput::make('app_store_link')->url(),
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
                TextInput::make('hot_deal_end_date')->type('date'),
                TextInput::make('flash_sale_end_date')->type('date'),
                Toggle::make('show_all_products')->default(true),
                Toggle::make('show_category_wise_products')->default(true),
                Toggle::make('vendor_enabled')->default(true),
                Toggle::make('reseller_enabled')->default(false),
                TextInput::make('reseller_deposit_min')->numeric()->minValue(0),
                TextInput::make('reseller_deposit_max')->numeric()->minValue(0),
                TextInput::make('reseller_wallet_min_balance')->numeric()->minValue(0),
            ]),
            Section::make('Policies & Notes')->schema([
                RichEditor::make('checkout_note'),
                RichEditor::make('order_policy'),
            ]),
        ];
    }

    /** @return array<mixed> */
    private function seoComponents(): array
    {
        return [
            Section::make('Search Engine Metadata')->columns(2)->schema([
                TextInput::make('meta_title')->maxLength(255)->columnSpanFull(),
                Textarea::make('meta_description')->rows(4)->maxLength(500)->columnSpanFull(),
                TextInput::make('meta_tags')->helperText('Comma-separated keywords'),
                TextInput::make('search_console_verification'),
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

    /** @return array<mixed> */
    private function sitemapComponents(): array
    {
        return [
            Section::make('Sitemap Configuration')->columns(2)->schema([
                Toggle::make('auto_generate')->default(true),
                Select::make('change_frequency')->options([
                    'always' => 'Always', 'hourly' => 'Hourly', 'daily' => 'Daily',
                    'weekly' => 'Weekly', 'monthly' => 'Monthly',
                ])->required(),
                Toggle::make('include_products')->default(true),
                Toggle::make('include_pages')->default(true),
                TextInput::make('path')->disabled(),
                TextInput::make('last_generated_at')->disabled(),
            ]),
        ];
    }

    /** @return array<mixed> */
    private function fraudComponents(): array
    {
        return [
            Section::make('Fraud Checker API')->columns(2)->schema([
                Toggle::make('enabled')->default(false),
                TextInput::make('provider')->required(),
                TextInput::make('endpoint')->url()->required()->columnSpanFull(),
                TextInput::make('fraud_api_key')->password()->revealable()->placeholder('Leave blank to keep the saved key'),
                TextInput::make('duplicate_order_api_key')->password()->revealable()->placeholder('Leave blank to keep the saved key'),
            ]),
        ];
    }

    /** @return array<mixed> */
    private function shippingComponents(): array
    {
        return [
            Section::make('Shipping Zones & Charges')->schema([
                Repeater::make('zones')->reorderable()->collapsible()->itemLabel(fn (array $state): ?string => $state['name'] ?? null)->schema([
                    TextInput::make('name')->required(),
                    TextInput::make('amount')->numeric()->prefix('৳')->required()->minValue(0),
                    TextInput::make('estimated_days')->numeric()->suffix('days')->required()->minValue(1),
                    Toggle::make('status')->default(true),
                ])->columns(4),
            ]),
        ];
    }
}
