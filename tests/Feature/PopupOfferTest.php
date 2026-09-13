<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\PopupOffer;
use App\Models\User;
use App\Services\DeploymentDataSnapshot;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\TestCase;

class PopupOfferTest extends TestCase
{
    use DatabaseTransactions;

    public function test_admin_can_open_popup_offer_module(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'status' => UserStatus::Active,
        ]);

        $this->actingAs($admin)->get('/admin')
            ->assertOk()
            ->assertSee('PopUp Offer');

        $this->actingAs($admin)->get('/admin/popup-offers')
            ->assertOk()
            ->assertSee('PopUp Offer')
            ->assertSee('Create Popup Offer');

        $this->actingAs($admin)->get('/admin/popup-offers/create')
            ->assertOk()
            ->assertSee('Popup Image')
            ->assertSee('Popup Active')
            ->assertSee('Text / Button (Optional)');
    }

    public function test_only_one_popup_offer_remains_active(): void
    {
        $first = PopupOffer::query()->create(['image' => 'popup-offers/first.jpg', 'is_active' => true]);
        $second = PopupOffer::query()->create(['image' => 'popup-offers/second.jpg', 'is_active' => true]);

        $this->assertFalse($first->fresh()->is_active);
        $this->assertTrue($second->fresh()->is_active);
    }

    public function test_active_popup_is_rendered_once_per_campaign_by_the_browser(): void
    {
        $offer = PopupOffer::query()->create([
            'title' => 'Festival Cashback',
            'description' => 'A special campaign for new visitors.',
            'image' => 'popup-offers/festival.jpg',
            'link_url' => 'https://www.tisilo.com/shop',
            'button_text' => 'Shop now',
            'footer_text' => 'Terms apply',
            'is_active' => true,
            'display_delay_seconds' => 2,
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee('Festival Cashback')
            ->assertSee('popup-offers/festival.jpg')
            ->assertSee('tisilo_popup_offer_'.$offer->uuid)
            ->assertSee('data-delay="2000"', false)
            ->assertSee('window.localStorage.setItem', false);
    }

    public function test_inactive_or_future_popup_is_not_rendered(): void
    {
        PopupOffer::query()->create([
            'title' => 'Future Popup',
            'image' => 'popup-offers/future.jpg',
            'is_active' => true,
            'starts_at' => now()->addHour(),
        ]);

        $this->get('/')->assertOk()->assertDontSee('Future Popup');
    }

    public function test_popup_offer_is_preserved_in_deployment_snapshot(): void
    {
        $offer = PopupOffer::query()->create([
            'title' => 'Deployment Popup',
            'image' => 'popup-offers/deployment.jpg',
            'is_active' => true,
        ]);
        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'tisilo-popup-'.Str::random(10).'.json';

        try {
            $service = app(DeploymentDataSnapshot::class);
            $service->export($path);
            $snapshot = json_decode(File::get($path), true, 512, JSON_THROW_ON_ERROR);

            $this->assertTrue(collect($snapshot['popup_offers'])->contains(
                fn (array $data): bool => $data['uuid'] === $offer->uuid,
            ));

            $offer->update(['title' => 'Changed Popup', 'is_active' => false]);
            $service->import($path);

            $this->assertSame('Deployment Popup', $offer->fresh()->title);
            $this->assertTrue($offer->fresh()->is_active);
        } finally {
            File::delete($path);
        }
    }
}
