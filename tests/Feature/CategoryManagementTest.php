<?php

namespace Tests\Feature;

use App\Filament\Resources\Categories\CategoryResource;
use App\Filament\Resources\Categories\RelationManagers\SubcategoriesRelationManager;
use App\Models\Category;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

class CategoryManagementTest extends TestCase
{
    use DatabaseTransactions;

    public function test_category_resource_query_only_returns_main_categories(): void
    {
        $suffix = Str::lower(Str::random(8));
        $mainCategory = Category::query()->create([
            'name' => 'Main Category '.$suffix,
            'slug' => 'main-category-'.$suffix,
            'status' => true,
        ]);
        $subcategory = $mainCategory->children()->create([
            'name' => 'Subcategory '.$suffix,
            'slug' => 'subcategory-'.$suffix,
            'status' => true,
        ]);

        $visibleCategoryIds = CategoryResource::getEloquentQuery()
            ->whereIn('id', [$mainCategory->id, $subcategory->id])
            ->pluck('id');

        $this->assertTrue($visibleCategoryIds->contains($mainCategory->id));
        $this->assertFalse($visibleCategoryIds->contains($subcategory->id));
        $this->assertTrue($mainCategory->children->contains($subcategory));
        $this->assertTrue(class_exists(SubcategoriesRelationManager::class));
        $this->assertContains(SubcategoriesRelationManager::class, CategoryResource::getRelations());
    }

    public function test_same_subcategory_slug_can_be_used_under_different_main_categories_only(): void
    {
        $suffix = Str::lower(Str::random(8));
        $firstParent = Category::query()->create([
            'name' => 'First Parent '.$suffix,
            'slug' => 'first-parent-'.$suffix,
            'status' => true,
        ]);
        $secondParent = Category::query()->create([
            'name' => 'Second Parent '.$suffix,
            'slug' => 'second-parent-'.$suffix,
            'status' => true,
        ]);
        $sharedSlug = 'shared-subcategory-'.$suffix;

        $firstParent->children()->create([
            'name' => 'First Child',
            'slug' => $sharedSlug,
            'status' => true,
        ]);
        $secondChild = $secondParent->children()->create([
            'name' => 'Second Child',
            'slug' => $sharedSlug,
            'status' => true,
        ]);

        $this->assertSame($secondParent->id, $secondChild->parent_id);

        $this->expectException(QueryException::class);
        $firstParent->children()->create([
            'name' => 'Duplicate First Child',
            'slug' => $sharedSlug,
            'status' => true,
        ]);
    }
}
