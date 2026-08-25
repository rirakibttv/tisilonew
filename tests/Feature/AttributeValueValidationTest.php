<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Filament\Resources\AttributeValues\Pages\CreateAttributeValue;
use App\Filament\Resources\AttributeValues\Pages\EditAttributeValue;
use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class AttributeValueValidationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_duplicate_value_in_the_same_attribute_shows_a_validation_error(): void
    {
        $this->actingAs($this->admin());
        $attribute = $this->attribute('Color '.Str::random(8));

        AttributeValue::query()->create([
            'attribute_id' => $attribute->id,
            'value' => 'Red',
            'slug' => 'red',
            'sort_order' => 0,
            'status' => true,
        ]);

        Livewire::test(CreateAttributeValue::class)
            ->fillForm([
                'attribute_id' => $attribute->id,
                'value' => 'Red',
                'slug' => 'red',
                'sort_order' => 1,
                'status' => true,
            ])
            ->call('create')
            ->assertHasFormErrors([
                'value' => 'unique',
                'slug' => 'unique',
            ])
            ->assertSee('এই Attribute-এর জন্য এই Value ইতোমধ্যে যোগ করা আছে। অন্য Value দিন।');

        $this->assertSame(1, AttributeValue::query()->where('attribute_id', $attribute->id)->count());
    }

    public function test_edit_ignores_the_current_record_but_rejects_another_duplicate(): void
    {
        $this->actingAs($this->admin());
        $attribute = $this->attribute('Size '.Str::random(8));
        $small = $this->value($attribute, 'Small', 'small');
        $large = $this->value($attribute, 'Large', 'large');

        Livewire::test(EditAttributeValue::class, ['record' => $small->getRouteKey()])
            ->fillForm([
                'attribute_id' => $attribute->id,
                'value' => 'Small',
                'slug' => 'small',
                'sort_order' => 2,
                'status' => true,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        Livewire::test(EditAttributeValue::class, ['record' => $large->getRouteKey()])
            ->fillForm([
                'attribute_id' => $attribute->id,
                'value' => 'Small',
                'slug' => 'small',
                'sort_order' => 3,
                'status' => true,
            ])
            ->call('save')
            ->assertHasFormErrors([
                'value' => 'unique',
                'slug' => 'unique',
            ]);
    }

    private function admin(): User
    {
        return User::factory()->create([
            'role' => UserRole::Admin,
            'status' => UserStatus::Active,
        ]);
    }

    private function attribute(string $name): Attribute
    {
        return Attribute::query()->create([
            'name' => $name,
            'slug' => str($name)->slug(),
            'type' => 'text',
            'sort_order' => 0,
            'status' => true,
        ]);
    }

    private function value(Attribute $attribute, string $value, string $slug): AttributeValue
    {
        return AttributeValue::query()->create([
            'attribute_id' => $attribute->id,
            'value' => $value,
            'slug' => $slug,
            'sort_order' => 0,
            'status' => true,
        ]);
    }
}
