<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class InventoryManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_guests_cannot_manage_inventory(): void
    {
        $this->getJson('/api/brands')->assertForbidden();
        $this->getJson('/api/categories')->assertForbidden();
        $this->getJson('/api/products')->assertForbidden();
    }

    public function test_authorized_user_can_create_update_and_delete_a_brand(): void
    {
        $actor = $this->inventoryUser(['brands.view', 'brands.create', 'brands.edit', 'brands.delete']);

        $this->actingAs($actor)->postJson('/api/brands', [
            'name' => 'Bosch',
        ])->assertCreated()->assertJsonPath('name', 'Bosch');

        $brand = Brand::query()->where('name', 'Bosch')->firstOrFail();

        $this->actingAs($actor)->putJson("/api/brands/{$brand->id}", [
            'name' => 'Bosch Auto',
        ])->assertOk()->assertJsonPath('name', 'Bosch Auto');

        $this->actingAs($actor)->deleteJson("/api/brands/{$brand->id}")->assertOk();
        $this->assertDatabaseMissing('brands', ['id' => $brand->id]);
    }

    public function test_category_with_products_cannot_be_deleted(): void
    {
        $actor = $this->inventoryUser(['categories.delete']);
        $product = Product::factory()->create();

        $this->actingAs($actor)
            ->deleteJson("/api/categories/{$product->category_id}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['category']);

        $this->assertDatabaseHas('categories', ['id' => $product->category_id]);
    }

    public function test_authorized_user_can_create_a_product_and_calculate_retail_price(): void
    {
        Storage::fake('public');

        $actor = $this->inventoryUser(['products.view', 'products.create', 'products.edit', 'products.delete']);
        $category = Category::factory()->create();
        $brand = Brand::factory()->create();

        $this->actingAs($actor)->post('/api/products', [
            'name' => 'Brake pad',
            'unit_price' => '10.00',
            'margin' => '50',
            'stock_quantity' => 4,
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'is_taxable' => 1,
            'images' => [UploadedFile::fake()->image('pad.jpg')],
        ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('retail_price', '15.00')
            ->assertJsonPath('brand_name', $brand->name);

        $product = Product::query()->where('name', 'Brake pad')->firstOrFail();
        $this->assertCount(1, $product->images);
        Storage::disk('public')->assertExists($product->images->first()->path);

        $this->actingAs($actor)->putJson("/api/products/{$product->id}", [
            'name' => 'Ceramic brake pad',
            'unit_price' => '12.00',
            'margin' => '25',
            'category_id' => $category->id,
            'is_taxable' => true,
            'retained_image_ids' => [$product->images->first()->id],
            'main_existing_image_id' => $product->images->first()->id,
        ])->assertOk()->assertJsonPath('name', 'Ceramic brake pad')->assertJsonPath('retail_price', '15.00');

        $this->actingAs($actor)->deleteJson("/api/products/{$product->id}")->assertOk();
        $this->assertDatabaseMissing('products', ['id' => $product->id]);
    }

    /**
     * @param  array<int, string>  $permissions
     */
    private function inventoryUser(array $permissions): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo($permissions);

        return $user;
    }
}
