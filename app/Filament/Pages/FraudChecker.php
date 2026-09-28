<?php

namespace App\Filament\Pages;

use App\Models\FraudCheckHistory;
use App\Models\SiteSetting;
use App\Services\FraudCheckerApiException;
use App\Services\FraudCheckerService;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

class FraudChecker extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'fraud-checker';

    protected string $view = 'filament.pages.fraud-checker';

    public string $mobile = '';

    /** @var array<string, mixed>|null */
    public ?array $result = null;

    public ?string $errorMessage = null;

    public string $errorType = 'failed';

    /** @var array<int, array<string, mixed>> */
    public array $history = [];

    public function mount(): void
    {
        $this->refreshHistory();
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasPermission('fraud.manage') ?? false;
    }

    public function getTitle(): string|Htmlable
    {
        return 'Fraud Checker API';
    }

    public function getSubheading(): ?string
    {
        return 'Courier delivery history দিয়ে customer risk যাচাই করুন';
    }

    /** @return array{enabled: bool, provider: string, endpoint_ready: bool, api_key_ready: bool, whitelisted_server_ip: string} */
    public function configuration(): array
    {
        $values = SiteSetting::valuesFor('fraud');
        $configuredIp = trim((string) ($values['whitelisted_server_ip'] ?? config('services.fraud_checker.outbound_ip')));

        return [
            'enabled' => filter_var($values['enabled'] ?? false, FILTER_VALIDATE_BOOL),
            'provider' => trim((string) ($values['provider'] ?? 'BD Courier')) ?: 'BD Courier',
            'endpoint_ready' => filter_var($values['endpoint'] ?? null, FILTER_VALIDATE_URL) !== false,
            'api_key_ready' => filled(SiteSetting::secretsFor('fraud')['fraud_api_key'] ?? null),
            'whitelisted_server_ip' => filter_var($configuredIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)
                ? $configuredIp
                : (string) config('services.fraud_checker.outbound_ip'),
        ];
    }

    public function check(FraudCheckerService $checker): void
    {
        $this->validate([
            'mobile' => ['required', 'string', 'max:30'],
        ], [
            'mobile.required' => 'মোবাইল নম্বর লিখুন।',
            'mobile.max' => 'মোবাইল নম্বরটি সঠিক নয়।',
        ]);

        $this->reset(['result', 'errorMessage']);
        $this->errorType = 'failed';

        try {
            $this->result = $checker->check($this->mobile);
            $this->mobile = (string) $this->result['phone'];
            $this->recordSuccess($this->result);
        } catch (FraudCheckerApiException $exception) {
            $this->errorMessage = $exception->getMessage();
            $this->errorType = $exception->providerBlocked ? 'blocked' : 'failed';
            $this->recordFailure($checker, $exception);
        }

        $this->refreshHistory();
    }

    /** @param array<string, mixed> $result */
    protected function recordSuccess(array $result): void
    {
        if (! Schema::hasTable('fraud_check_histories')) {
            return;
        }

        $summary = $result['summary'];
        $phone = (string) $result['phone'];

        FraudCheckHistory::query()->create([
            'checked_by' => $this->checkerId(),
            'vendor_id' => $this->historyVendorId(),
            'provider' => $result['provider'],
            'mobile' => $phone,
            'mobile_masked' => $this->maskMobile($phone),
            'mobile_hash' => $this->mobileHash($phone),
            'status' => 'success',
            'total_parcel' => $summary['total_parcel'],
            'success_parcel' => $summary['success_parcel'],
            'cancelled_parcel' => $summary['cancelled_parcel'],
            'success_ratio' => $summary['success_ratio'],
            'risk_level' => $summary['risk_level'],
            'message' => null,
            'response_payload' => [
                'couriers' => $result['couriers'],
                'reports' => $result['reports'],
            ],
        ]);
    }

    protected function recordFailure(FraudCheckerService $checker, FraudCheckerApiException $exception): void
    {
        if (! Schema::hasTable('fraud_check_histories')) {
            return;
        }

        try {
            $phone = $checker->normalizeBangladeshMobile($this->mobile);
        } catch (FraudCheckerApiException) {
            return;
        }

        FraudCheckHistory::query()->create([
            'checked_by' => $this->checkerId(),
            'vendor_id' => $this->historyVendorId(),
            'provider' => $this->configuration()['provider'],
            'mobile' => $phone,
            'mobile_masked' => $this->maskMobile($phone),
            'mobile_hash' => $this->mobileHash($phone),
            'status' => $exception->providerBlocked ? 'blocked' : 'failed',
            'message' => str($exception->getMessage())->limit(1000)->toString(),
        ]);
    }

    protected function refreshHistory(): void
    {
        if (! Schema::hasTable('fraud_check_histories')) {
            $this->history = [];

            return;
        }

        $this->history = $this->historyQuery()
            ->with('checker:id,name')
            ->latest()
            ->limit(20)
            ->get()
            ->map(fn (FraudCheckHistory $check): array => [
                'id' => $check->id,
                'mobile' => $check->mobile_masked,
                'provider' => $check->provider,
                'status' => $check->status,
                'total_parcel' => $check->total_parcel,
                'success_parcel' => $check->success_parcel,
                'cancelled_parcel' => $check->cancelled_parcel,
                'success_ratio' => $check->success_ratio !== null ? (float) $check->success_ratio : null,
                'risk_level' => $check->risk_level,
                'message' => $check->message,
                'checker' => $check->checker?->name ?? 'System',
                'checked_at' => $check->created_at?->format('d M Y, h:i A'),
            ])
            ->all();
    }

    protected function historyQuery(): Builder
    {
        return FraudCheckHistory::query();
    }

    protected function historyVendorId(): ?int
    {
        return null;
    }

    protected function checkerId(): ?int
    {
        return Filament::auth()->id() ?? auth()->id();
    }

    private function maskMobile(string $mobile): string
    {
        return substr($mobile, 0, 3).'*****'.substr($mobile, -3);
    }

    private function mobileHash(string $mobile): string
    {
        return hash_hmac('sha256', $mobile, (string) config('app.key'));
    }
}
