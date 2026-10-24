<?php

namespace Tests\Feature;

use Filament\Enums\ThemeMode;
use Filament\Facades\Filament;
use Tests\TestCase;

class PanelThemePreferenceTest extends TestCase
{
    public function test_admin_and_seller_panels_follow_the_system_theme_by_default(): void
    {
        foreach (['admin', 'seller'] as $panelId) {
            $panel = Filament::getPanel($panelId);

            $this->assertTrue($panel->hasDarkMode());
            $this->assertFalse($panel->hasDarkModeForced());
            $this->assertSame(ThemeMode::System, $panel->getDefaultThemeMode());
            $this->assertFalse($panel->hasThemeSwitcher());
        }
    }

    public function test_panel_pages_replace_any_old_saved_theme_choice_with_system_preference(): void
    {
        $this->get('/admin/login')
            ->assertOk()
            ->assertSee("localStorage.setItem('theme', 'system')", false);

        $this->get('/seller/login')
            ->assertOk()
            ->assertSee("localStorage.setItem('theme', 'system')", false);
    }
}
