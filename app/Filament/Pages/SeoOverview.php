<?php

namespace App\Filament\Pages;

use App\Services\SeoOverviewReportService;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;

class SeoOverview extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'seo-overview';

    protected string $view = 'filament.pages.seo-overview';

    public int $period = 7;

    public string $platform = 'all';

    public function mount(): void
    {
        $requestedPeriod = request()->integer('period', 7);
        $requestedPlatform = request()->string('platform', 'all')->toString();
        $this->period = in_array($requestedPeriod, [1, 7, 30, 90], true) ? $requestedPeriod : 7;
        $this->platform = in_array($requestedPlatform, ['all', 'facebook', 'google'], true) ? $requestedPlatform : 'all';
    }

    /** @return array<string, mixed> */
    public function report(): array
    {
        return app(SeoOverviewReportService::class)->report($this->period, $this->platform);
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
