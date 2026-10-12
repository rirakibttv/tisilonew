<?php

namespace App\Filament\Pages;

use App\Services\BotTrafficControlService;
use App\Services\SeoOverviewReportService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;

class SeoOverview extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'seo-overview';

    protected string $view = 'filament.pages.seo-overview';

    public int $period = 1;

    public string $platform = 'all';

    public function mount(): void
    {
        $requestedPeriod = request()->integer('period', 1);
        $requestedPlatform = request()->string('platform', 'all')->toString();
        $this->period = in_array($requestedPeriod, [1, 7, 30, 90], true) ? $requestedPeriod : 1;
        $this->platform = in_array($requestedPlatform, ['all', 'facebook', 'google'], true) ? $requestedPlatform : 'all';
    }

    /** @return array<string, mixed> */
    public function report(): array
    {
        return app(SeoOverviewReportService::class)->report($this->period, $this->platform);
    }

    public function blockBot(string $botName, BotTrafficControlService $control): void
    {
        $control->blockBot($botName);
        Notification::make()->title("{$botName} is now blocked")->success()->send();
    }

    public function unblockBot(string $botName, BotTrafficControlService $control): void
    {
        $control->unblockBot($botName);
        Notification::make()->title("{$botName} is now allowed")->success()->send();
    }

    public function blockIp(string $ipHash, BotTrafficControlService $control): void
    {
        $control->blockIpHash($ipHash);
        Notification::make()->title($control->maskedFingerprint($ipHash).' is now blocked')->success()->send();
    }

    public function unblockIp(string $ipHash, BotTrafficControlService $control): void
    {
        $control->unblockIpHash($ipHash);
        Notification::make()->title($control->maskedFingerprint($ipHash).' is now allowed')->success()->send();
    }

    public function getTitle(): string|Htmlable
    {
        return match ($this->platform) {
            'facebook' => 'Facebook SEO Overview',
            'google' => 'Google SEO Overview',
            default => 'SEO Overview',
        };
    }

    public function getSubheading(): ?string
    {
        return 'First-party traffic attribution, search visibility and conversion intelligence';
    }
}
