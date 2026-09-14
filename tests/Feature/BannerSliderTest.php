<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\BannerSlider;
use App\Models\User;
use App\Services\DeploymentDataSnapshot;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\TestCase;

class BannerSliderTest extends TestCase
{
    use DatabaseTransactions;

    public function test_admin_has_one_banner_slider_list_without_placement_selection(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'status' => UserStatus::Active,
        ]);

        $this->actingAs($admin)->get('/admin')
            ->assertOk()
            ->assertSeeInOrder(['Banner &amp; Slider', 'PopUp Offer'], false);

        $this->actingAs($admin)->get('/admin/banner-sliders')
            ->assertOk()
            ->assertSee('Banner &amp; Slider', false)
            ->assertSee('Create New Slider');

        $this->actingAs($admin)->get('/admin/banner-sliders/create')
            ->assertOk()
            ->assertSee('Slider Artwork')
            ->assertSee('Destination URL')
            ->assertSee('Active Mode')
            ->assertDontSee('Select Placement Category');
    }

    public function test_sliders_receive_sequential_names_and_only_active_sliders_show_on_homepage(): void
    {
        $first = BannerSlider::query()->create([
            'image' => 'banner-sliders/first.jpg',
            'destination_url' => '/shop',
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $second = BannerSlider::query()->create([
            'image' => 'banner-sliders/second.jpg',
            'is_active' => false,
            'sort_order' => 2,
        ]);

        $this->assertSame('Slider '.$first->id, $first->fresh()->name);
        $this->assertSame('Slider '.$second->id, $second->fresh()->name);

        $this->get('/')
            ->assertOk()
            ->assertSee('data-responsive-banner-slider', false)
            ->assertSee('class="block h-auto w-full"', false)
            ->assertDontSee('aspect-[1060/395]', false)
            ->assertSee('storage/banner-sliders/first.jpg', false)
            ->assertSee('href="/shop"', false)
            ->assertDontSee('storage/banner-sliders/second.jpg', false)
            ->assertDontSee('Biggest Online Supermarket');
    }

    public function test_banner_slider_is_preserved_in_deployment_snapshot(): void
    {
        $slider = BannerSlider::query()->create([
            'image' => 'banner-sliders/deployment.jpg',
            'destination_url' => 'https://www.tisilo.com/shop',
            'is_active' => true,
            'sort_order' => 7,
        ]);
        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'tisilo-slider-'.Str::random(10).'.json';

        try {
            $service = app(DeploymentDataSnapshot::class);
            $service->export($path);
            $snapshot = json_decode(File::get($path), true, 512, JSON_THROW_ON_ERROR);

            $this->assertTrue(collect($snapshot['banner_sliders'])->contains(
                fn (array $data): bool => $data['uuid'] === $slider->uuid,
            ));

            $slider->update(['destination_url' => null, 'is_active' => false]);
            $service->import($path);

            $this->assertSame('https://www.tisilo.com/shop', $slider->fresh()->destination_url);
            $this->assertTrue($slider->fresh()->is_active);
        } finally {
            File::delete($path);
        }
    }
}
