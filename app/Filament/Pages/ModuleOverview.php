<?php

namespace App\Filament\Pages;

use App\Enums\AdminNavigationGroup;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;

class ModuleOverview extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'module-overview';

    protected string $view = 'filament.pages.module-overview';

    public string $module = '';

    public function mount(): void
    {
        $this->module = request()->string('module')->toString();

        abort_unless($this->getModuleGroup(), 404);
    }

    public function getModuleGroup(): ?AdminNavigationGroup
    {
        return AdminNavigationGroup::fromSlug($this->module);
    }

    public function getTitle(): string|Htmlable
    {
        return $this->getModuleGroup()?->getLabel() ?? 'Module Overview';
    }

    public function getSubheading(): ?string
    {
        return 'Tisilo enterprise marketplace administration module';
    }
}
