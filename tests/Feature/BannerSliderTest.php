<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\BannerSlider;
use App\Models\SliderGroup;
use App\Models\User;
use App\Services\DeploymentDataSnapshot;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\TestCase;

class BannerSliderTest extends TestCase
{
    use DatabaseTransactions;

    public function test_admin_manages_master_sliders_and_their_nested_slide_lists(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'status' => UserStatus::Active,
        ]);

        $this->actingAs($admin)->get('/admin')
            ->assertOk()
            ->assertSeeInOrder(['Banner &amp; Slider', 'PopUp Offer'], false);

        $mainSlider = SliderGroup::main();

        $this->actingAs($admin)->get('/admin/slider-groups')
            ->assertOk()
            ->assertSee('Slider Panel')
            ->assertSee('Main Slider')
            ->assertSee('Create Master Slider');

        $this->actingAs($admin)->get('/admin/slider-groups/'.$mainSlider->getKey().'/edit')
            ->assertOk()
            ->assertSee('Homepage Placement')
            ->assertSee('Slides')
            ->assertSee('Add Slide')
            ->assertDontSee('Select Placement Category');
    }

    public function test_slides_belong_to_master_sliders_and_render_at_their_homepage_positions(): void
    {
        $mainSlider = SliderGroup::main();
        $first = BannerSlider::query()->create([
            'slider_group_id' => $mainSlider->getKey(),
            'image' => 'banner-sliders/first.jpg',
            'destination_url' => '/shop',
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $second = BannerSlider::query()->create([
            'slider_group_id' => $mainSlider->getKey(),
            'image' => 'banner-sliders/second.jpg',
            'is_active' => false,
            'sort_order' => 2,
        ]);

        $this->assertSame('Slider 1', $first->fresh()->name);
        $this->assertSame('Slider 2', $second->fresh()->name);

        $categorySlider = SliderGroup::query()->create([
            'name' => 'Category Offers',
            'slug' => 'category-offers',
            'placement' => 'after_categories',
            'is_active' => true,
            'sort_order' => 2,
        ]);
        BannerSlider::query()->create([
            'slider_group_id' => $categorySlider->getKey(),
            'image' => 'banner-sliders/category-offer.jpg',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee('data-responsive-banner-slider', false)
            ->assertSee('class="block h-auto w-full"', false)
            ->assertDontSee('aspect-[1060/395]', false)
            ->assertSee('storage/banner-sliders/first.jpg', false)
            ->assertSee('href="/shop"', false)
            ->assertDontSee('storage/banner-sliders/second.jpg', false)
            ->assertSee('data-managed-slider', false)
            ->assertSee('data-slider-name="category-offers"', false)
            ->assertSee('storage/banner-sliders/category-offer.jpg', false)
            ->assertDontSee('Biggest Online Supermarket');
    }

    public function test_master_slider_and_its_slides_are_preserved_in_deployment_snapshot(): void
    {
        $footerSlider = SliderGroup::query()->create([
            'name' => 'Footer Offers',
            'slug' => 'footer-offers',
            'placement' => 'before_footer',
            'is_active' => true,
            'sort_order' => 5,
        ]);
        $slider = BannerSlider::query()->create([
            'slider_group_id' => $footerSlider->getKey(),
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
                fn (array $data): bool => $data['uuid'] === $slider->uuid
                    && $data['slider_group_slug'] === 'footer-offers',
            ));
            $this->assertTrue(collect($snapshot['slider_groups'])->contains(
                fn (array $data): bool => $data['uuid'] === $footerSlider->uuid,
            ));

            $footerSlider->update(['name' => 'Changed', 'is_active' => false]);
            $slider->update(['destination_url' => null, 'is_active' => false]);
            $service->import($path);

            $this->assertSame('Footer Offers', $footerSlider->fresh()->name);
            $this->assertTrue($footerSlider->fresh()->is_active);
            $this->assertSame('https://www.tisilo.com/shop', $slider->fresh()->destination_url);
            $this->assertTrue($slider->fresh()->is_active);
            $this->assertTrue($slider->fresh()->group->is($footerSlider));
        } finally {
            File::delete($path);
        }
    }
}
