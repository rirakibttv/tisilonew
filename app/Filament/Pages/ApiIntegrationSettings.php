<?php

namespace App\Filament\Pages;

use App\Models\SiteSetting;
use App\Services\CloudflareApiService;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use RuntimeException;
use Throwable;

class ApiIntegrationSettings extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'api-integrations';

    protected string $view = 'filament.pages.api-integration-settings';

    /** @var array<string, mixed> */
    public array $data = [];

    public string $section = 'payment';

    /** @var array<string, string> */
    public const SECTIONS = [
        'payment' => 'Payment Gateway',
        'sms' => 'SMS Gateway',
        'courier' => 'Courier API',
        'facebook_capi' => 'Facebook CAPI',
        'facebook_auto_post' => 'FB Auto Post',
        'search_console' => 'Google Search Console',
        'fraud' => 'Manage Fraud API',
        'google_analytics' => 'Google Analytics',
        'google_tag_manager' => 'Google Tag Manager',
        'cloudflare' => 'Cloudflare API',
    ];

    public function mount(): void
    {
        $this->section = request()->string('section', 'payment')->toString();
        abort_unless(array_key_exists($this->section, self::SECTIONS), 404);

        $values = SiteSetting::valuesFor($this->settingKey());

        foreach ($this->secretFields() as $field) {
            $values[$field] = null;
        }

        $this->form->fill($values);
    }

    public function getTitle(): string|Htmlable
    {
        return self::SECTIONS[$this->section];
    }

    public function getSubheading(): ?string
    {
        if ($this->section === 'cloudflare') {
            return 'Securely connect the Cloudflare zone, verify access, and purge CDN cache.';
        }

        return 'Credentials are encrypted. Saving settings never sends a test request, SMS, event, or social post.';
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

        SiteSetting::put(
            $this->settingKey(),
            [...SiteSetting::valuesFor($this->settingKey()), ...$values],
            $secretUpdates,
        );

        Notification::make()
            ->success()
            ->title(self::SECTIONS[$this->section].' saved securely')
            ->send();

        $fresh = SiteSetting::valuesFor($this->settingKey());
        foreach ($this->secretFields() as $field) {
            $fresh[$field] = null;
        }
        $this->form->fill($fresh);
    }

    private function settingKey(): string
    {
        return match ($this->section) {
            'search_console' => 'seo',
            'fraud' => 'fraud',
            default => $this->section,
        };
    }

    /** @return array<string> */
    private function secretFields(): array
    {
        return match ($this->section) {
            'payment' => [
                'bkash_password', 'bkash_app_key', 'bkash_app_secret',
                'sslcommerz_store_password', 'shurjopay_password',
                'aamarpay_signature_key', 'uddoktapay_api_key',
            ],
            'sms' => ['api_key', 'admin_phone_list'],
            'courier' => [
                'steadfast_api_key', 'steadfast_secret_key',
                'pathao_client_secret', 'pathao_password', 'pathao_access_token',
                'redx_access_token',
            ],
            'facebook_capi' => ['access_token'],
            'facebook_auto_post' => ['page_access_token'],
            'search_console' => ['search_console_service_account_json'],
            'fraud' => ['fraud_api_key', 'duplicate_order_api_key'],
            'google_analytics' => ['measurement_protocol_secret'],
            'google_tag_manager' => ['environment_auth', 'environment_preview'],
            'cloudflare' => ['api_token'],
            default => [],
        };
    }

    /** @return array<mixed> */
    private function components(): array
    {
        return match ($this->section) {
            'payment' => $this->paymentComponents(),
            'sms' => $this->smsComponents(),
            'courier' => $this->courierComponents(),
            'facebook_capi' => $this->facebookCapiComponents(),
            'facebook_auto_post' => $this->facebookAutoPostComponents(),
            'search_console' => $this->searchConsoleComponents(),
            'fraud' => $this->fraudComponents(),
            'google_analytics' => $this->googleAnalyticsComponents(),
            'google_tag_manager' => $this->googleTagManagerComponents(),
            'cloudflare' => $this->cloudflareComponents(),
        };
    }

    /** @return array<Action> */
    protected function getHeaderActions(): array
    {
        if ($this->section !== 'cloudflare') {
            return [];
        }

        return [
            Action::make('testCloudflareConnection')
                ->label('Verify Connection')
                ->icon('heroicon-o-signal')
                ->action('testCloudflareConnection'),
            Action::make('purgeCloudflareCache')
                ->label('Purge CDN Cache')
                ->icon('heroicon-o-arrow-path')
                ->color('danger')
                ->requiresConfirmation()
                ->modalDescription('This clears all cached files for this Cloudflare zone. The origin may receive extra traffic while the cache warms again.')
                ->action('purgeCloudflareCache'),
        ];
    }

    public function testCloudflareConnection(CloudflareApiService $cloudflare): void
    {
        try {
            [$values, $apiToken] = $this->cloudflareConfiguration();
            $zone = $cloudflare->zone($apiToken, (string) $values['zone_id']);

            $this->storeCloudflareStatus([
                'zone_name' => $zone['name'] ?? $values['hostname'] ?? null,
                'zone_status' => $zone['status'] ?? 'unknown',
                'development_mode' => (int) ($zone['development_mode'] ?? 0),
                'last_verified_at' => now()->toIso8601String(),
            ]);

            Notification::make()
                ->success()
                ->title('Cloudflare connection verified')
                ->body('Zone: '.($zone['name'] ?? $values['hostname']).' · Status: '.($zone['status'] ?? 'unknown'))
                ->send();
        } catch (Throwable $exception) {
            $this->cloudflareFailure('Cloudflare verification failed', $exception);
        }
    }

    public function purgeCloudflareCache(CloudflareApiService $cloudflare): void
    {
        try {
            [$values, $apiToken] = $this->cloudflareConfiguration();
            $cloudflare->purgeEverything($apiToken, (string) $values['zone_id']);

            $this->storeCloudflareStatus([
                'last_purged_at' => now()->toIso8601String(),
            ]);

            Notification::make()
                ->success()
                ->title('Cloudflare cache purged')
                ->body('The next requests will repopulate the CDN cache with fresh files.')
                ->send();
        } catch (Throwable $exception) {
            $this->cloudflareFailure('Cloudflare cache purge failed', $exception);
        }
    }

    /** @return array<mixed> */
    private function paymentComponents(): array
    {
        return [
            Section::make('Checkout Payment Options')->columns(3)->schema([
                Toggle::make('cod_enabled')->label('Cash on Delivery')->default(true),
                Select::make('default_gateway')->options([
                    'cod' => 'Cash on Delivery',
                    'bkash' => 'bKash',
                    'sslcommerz' => 'SSLCommerz',
                    'shurjopay' => 'ShurjoPay',
                    'aamarpay' => 'AamarPay',
                    'uddoktapay' => 'UddoktaPay',
                ])->default('cod')->required(),
                TextInput::make('currency')->default('BDT')->maxLength(3)->required(),
            ]),
            Section::make('bKash Tokenized Checkout')->columns(3)->schema([
                Toggle::make('bkash_enabled')->default(false),
                Select::make('bkash_environment')->options(['sandbox' => 'Sandbox', 'production' => 'Production'])->default('sandbox'),
                TextInput::make('bkash_username')->maxLength(255),
                $this->secretInput('bkash_password', 'bKash Password'),
                $this->secretInput('bkash_app_key', 'bKash App Key'),
                $this->secretInput('bkash_app_secret', 'bKash App Secret'),
            ]),
            Section::make('SSLCommerz')->columns(3)->schema([
                Toggle::make('sslcommerz_enabled')->default(false),
                Select::make('sslcommerz_environment')->options(['sandbox' => 'Sandbox', 'production' => 'Production'])->default('sandbox'),
                TextInput::make('sslcommerz_store_id')->maxLength(255),
                $this->secretInput('sslcommerz_store_password', 'Store Password'),
            ]),
            Section::make('Hosted Payment Gateways')->columns(3)->schema([
                Toggle::make('shurjopay_enabled')->default(false),
                TextInput::make('shurjopay_username')->maxLength(255),
                $this->secretInput('shurjopay_password', 'ShurjoPay Password'),
                TextInput::make('shurjopay_prefix')->maxLength(100),
                Toggle::make('aamarpay_enabled')->default(false),
                TextInput::make('aamarpay_store_id')->maxLength(255),
                $this->secretInput('aamarpay_signature_key', 'AamarPay Signature Key'),
                Toggle::make('uddoktapay_enabled')->default(false),
                TextInput::make('uddoktapay_base_url')->url()->maxLength(500),
                $this->secretInput('uddoktapay_api_key', 'UddoktaPay API Key'),
            ]),
        ];
    }

    /** @return array<mixed> */
    private function smsComponents(): array
    {
        return [
            Section::make('SMS Provider')->columns(2)->schema([
                Toggle::make('enabled')->default(false),
                Select::make('provider')->options([
                    'bulksmsbd' => 'BulkSMSBD',
                    'mimsms' => 'MIM SMS',
                    'ssl_wireless' => 'SSL Wireless',
                    'custom' => 'Custom HTTP API',
                ])->default('bulksmsbd')->required(),
                TextInput::make('endpoint')->url()->maxLength(500)->columnSpanFull(),
                TextInput::make('sender_id')->maxLength(100),
                $this->secretInput('api_key', 'API Key'),
                $this->secretInput('admin_phone_list', 'Admin Phone List')->helperText('Comma-separated recipients; encrypted at rest.'),
            ]),
            Section::make('Automated SMS')->columns(3)->schema([
                Toggle::make('order_confirmation')->default(true),
                Toggle::make('password_reset')->default(true),
                Toggle::make('admin_new_order_alert')->default(true),
            ]),
        ];
    }

    /** @return array<mixed> */
    private function courierComponents(): array
    {
        return [
            Section::make('Steadfast Courier')->columns(3)->schema([
                Toggle::make('steadfast_enabled')->default(false),
                Select::make('steadfast_environment')->options(['sandbox' => 'Sandbox', 'production' => 'Production'])->default('sandbox'),
                TextInput::make('steadfast_endpoint')->url()->maxLength(500),
                $this->secretInput('steadfast_api_key', 'Steadfast API Key'),
                $this->secretInput('steadfast_secret_key', 'Steadfast Secret Key'),
            ]),
            Section::make('Pathao Courier')->columns(3)->schema([
                Toggle::make('pathao_enabled')->default(false),
                Select::make('pathao_environment')->options(['sandbox' => 'Sandbox', 'production' => 'Production'])->default('sandbox'),
                TextInput::make('pathao_endpoint')->url()->maxLength(500),
                TextInput::make('pathao_client_id')->maxLength(255),
                $this->secretInput('pathao_client_secret', 'Pathao Client Secret'),
                TextInput::make('pathao_username')->maxLength(255),
                $this->secretInput('pathao_password', 'Pathao Password'),
                $this->secretInput('pathao_access_token', 'Pathao Access Token'),
                TextInput::make('pathao_store_id')->maxLength(100),
            ]),
            Section::make('RedX Courier')->columns(3)->schema([
                Toggle::make('redx_enabled')->default(false),
                Select::make('redx_environment')->options(['sandbox' => 'Sandbox', 'production' => 'Production'])->default('sandbox'),
                TextInput::make('redx_endpoint')->url()->maxLength(500),
                $this->secretInput('redx_access_token', 'RedX Access Token'),
                TextInput::make('redx_store_id')->maxLength(100),
                TextInput::make('webhook_url')->url()->maxLength(500),
            ]),
        ];
    }

    /** @return array<mixed> */
    private function facebookCapiComponents(): array
    {
        return [
            Section::make('Meta Conversions API')->description('Configuration only. No visitor or order data is sent merely by saving this form.')->columns(2)->schema([
                Toggle::make('enabled')->default(false),
                TextInput::make('pixel_id')->label('Dataset / Pixel ID')->regex('/^[0-9]{5,32}$/')->maxLength(32),
                Select::make('api_version')->options(['v23.0' => 'v23.0', 'v22.0' => 'v22.0', 'v21.0' => 'v21.0'])->default('v23.0'),
                TextInput::make('test_event_code')->maxLength(100),
                $this->secretInput('access_token', 'CAPI Access Token')->columnSpanFull(),
                CheckboxList::make('events')->options([
                    'PageView' => 'PageView',
                    'ViewContent' => 'ViewContent',
                    'AddToCart' => 'AddToCart',
                    'InitiateCheckout' => 'InitiateCheckout',
                    'Purchase' => 'Purchase',
                ])->columns(3)->columnSpanFull(),
            ]),
        ];
    }

    /** @return array<mixed> */
    private function facebookAutoPostComponents(): array
    {
        return [
            Section::make('Facebook Page Publishing')->description('Saving credentials does not publish anything. Posting requires a separate explicit action.')->columns(2)->schema([
                Toggle::make('enabled')->default(false),
                Toggle::make('post_on_product_publish')->default(false),
                TextInput::make('page_id')->maxLength(100),
                Select::make('api_version')->options(['v23.0' => 'v23.0', 'v22.0' => 'v22.0', 'v21.0' => 'v21.0'])->default('v23.0'),
                $this->secretInput('page_access_token', 'Page Access Token')->columnSpanFull(),
                Textarea::make('default_message_template')->rows(5)->maxLength(2000)->columnSpanFull()
                    ->helperText('Available placeholders: {product_name}, {price}, {url}'),
            ]),
        ];
    }

    /** @return array<mixed> */
    private function searchConsoleComponents(): array
    {
        return [
            Section::make('Google Search Console')->columns(2)->schema([
                Toggle::make('search_console_enabled')->default(false),
                TextInput::make('search_console_property_url')->url()->maxLength(500),
                TextInput::make('search_console_verification')->label('HTML Meta Verification Token')->maxLength(500)->columnSpanFull(),
                TextInput::make('search_console_sitemap_url')->url()->default(fn (): string => url('/sitemap.xml'))->maxLength(500),
                Textarea::make('search_console_service_account_json')->rows(7)->columnSpanFull()
                    ->placeholder('Leave blank to keep the encrypted service-account JSON')
                    ->helperText('Optional API credential. Stored encrypted and never exported to Git.'),
            ]),
        ];
    }

    /** @return array<mixed> */
    private function fraudComponents(): array
    {
        return [
            Section::make('Fraud & Duplicate Order API')->columns(2)->schema([
                Toggle::make('enabled')->default(false),
                Select::make('provider')->options([
                    'BD Courier' => 'BD Courier',
                    'FraudBD' => 'FraudBD',
                    'custom' => 'Custom Provider',
                ])->default('BD Courier')->required(),
                TextInput::make('endpoint')->url()->maxLength(500)->columnSpanFull(),
                TextInput::make('timeout_seconds')->numeric()->minValue(1)->maxValue(30)->default(8),
                TextInput::make('risk_threshold')->numeric()->minValue(0)->maxValue(100)->default(70),
                $this->secretInput('fraud_api_key', 'Fraud API Key'),
                $this->secretInput('duplicate_order_api_key', 'Duplicate Order API Key'),
            ]),
        ];
    }

    /** @return array<mixed> */
    private function googleAnalyticsComponents(): array
    {
        return [
            Section::make('Google Analytics 4')->description('Tracking remains off until Enabled is saved and your storefront consent policy allows it.')->columns(2)->schema([
                Toggle::make('enabled')->default(false),
                Toggle::make('enhanced_ecommerce')->default(true),
                TextInput::make('measurement_id')->label('GA4 Measurement ID')->regex('/^G-[A-Z0-9]{4,20}$/')->placeholder('G-XXXXXXXXXX'),
                TextInput::make('stream_id')->maxLength(100),
                Toggle::make('anonymize_ip')->default(true),
                $this->secretInput('measurement_protocol_secret', 'Measurement Protocol API Secret'),
            ]),
        ];
    }

    /** @return array<mixed> */
    private function googleTagManagerComponents(): array
    {
        return [
            Section::make('Google Tag Manager')->description('Container loading remains off until Enabled is saved and your storefront consent policy allows it.')->columns(2)->schema([
                Toggle::make('enabled')->default(false),
                TextInput::make('container_id')->regex('/^GTM-[A-Z0-9]{4,20}$/')->placeholder('GTM-XXXXXXX'),
                TextInput::make('server_container_url')->url()->maxLength(500)->columnSpanFull(),
                $this->secretInput('environment_auth', 'Environment Auth Token'),
                $this->secretInput('environment_preview', 'Environment Preview Token'),
            ]),
        ];
    }

    /** @return array<mixed> */
    private function cloudflareComponents(): array
    {
        $defaultHostname = parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'www.tisilo.com';

        return [
            Section::make('Cloudflare Zone Connection')
                ->description('Create a scoped API token with Zone Read and Cache Purge permissions. Cache Rules and TTL values remain controlled from the Cloudflare Dashboard.')
                ->columns(2)
                ->schema([
                    TextInput::make('zone_id')
                        ->label('Zone ID')
                        ->required()
                        ->regex('/^[a-f0-9]{32}$/i')
                        ->maxLength(32),
                    TextInput::make('hostname')
                        ->required()
                        ->default($defaultHostname)
                        ->regex('/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/i')
                        ->placeholder('www.tisilo.com'),
                    $this->secretInput('api_token', 'Cloudflare API Token')
                        ->helperText('Paste the token secret only. “Bearer”, Authorization headers and Global API Keys are not required. It is encrypted and excluded from Git/deployment snapshots.'),
                ]),
            Section::make('Connection & Cache Status')
                ->columns(2)
                ->schema([
                    TextInput::make('zone_name')->disabled(),
                    TextInput::make('zone_status')->disabled(),
                    TextInput::make('development_mode')->label('Development Mode (seconds)')->disabled(),
                    TextInput::make('last_verified_at')->disabled(),
                    TextInput::make('last_purged_at')->disabled(),
                ]),
        ];
    }

    /** @return array{0: array<string, mixed>, 1: string} */
    private function cloudflareConfiguration(): array
    {
        $values = SiteSetting::valuesFor('cloudflare');
        $apiToken = (string) (SiteSetting::secretsFor('cloudflare')['api_token'] ?? '');

        if (blank($values['zone_id'] ?? null) || blank($values['hostname'] ?? null) || blank($apiToken)) {
            throw new RuntimeException('Save the Cloudflare Zone ID, hostname, and API token first.');
        }

        return [$values, $apiToken];
    }

    /** @param array<string, mixed> $status */
    private function storeCloudflareStatus(array $status): void
    {
        SiteSetting::put('cloudflare', [
            ...SiteSetting::valuesFor('cloudflare'),
            ...$status,
        ]);

        $fresh = SiteSetting::valuesFor('cloudflare');
        $fresh['api_token'] = null;
        $this->form->fill($fresh);
    }

    private function cloudflareFailure(string $title, Throwable $exception): void
    {
        report($exception);

        Notification::make()
            ->danger()
            ->title($title)
            ->body($exception->getMessage())
            ->persistent()
            ->send();
    }

    private function secretInput(string $name, string $label): TextInput
    {
        return TextInput::make($name)
            ->label($label)
            ->password()
            ->revealable()
            ->autocomplete('new-password')
            ->placeholder('Leave blank to keep the saved secret')
            ->maxLength(4000);
    }
}
