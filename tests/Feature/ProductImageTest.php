<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Support\StoreContext;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductImageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_returns_401_when_no_token_is_provided(): void
    {
        $this->postJson('/api/v1/products/1/image')->assertUnauthorized();
    }

    public function test_uploads_image_and_returns_public_url(): void
    {
        Storage::fake('public');
        $token = $this->registerOwner();
        $productId = $this->createProduct($token);

        $response = $this->withToken($token)->post("/api/v1/products/{$productId}/image", [
            'image' => UploadedFile::fake()->image('soap.jpg', 200, 200),
        ]);

        $response->assertOk();

        $product = Product::find($productId);
        $this->assertNotNull($product->image);
        $this->assertStringStartsWith('products/', $product->image);
        Storage::disk('public')->assertExists($product->image);

        $image = $response->json('data.image');
        $this->assertIsString($image);
        $this->assertStringContainsString('/storage/products/', $image);
    }

    public function test_lookup_search_returns_uploaded_image_url(): void
    {
        Storage::fake('public');
        $token = $this->registerOwner();
        $productId = $this->createProduct($token, ['name' => 'Camera Soap']);

        $this->withToken($token)->post("/api/v1/products/{$productId}/image", [
            'image' => UploadedFile::fake()->image('soap.jpg'),
        ])->assertOk();

        $response = $this->withToken($token)->getJson('/api/v1/lookup/search?q=Camera');

        $response->assertOk();
        $this->assertStringContainsString('/storage/products/', $response->json('data.0.image'));
    }

    public function test_replaces_previous_image_file(): void
    {
        Storage::fake('public');
        $token = $this->registerOwner();
        $productId = $this->createProduct($token);

        $this->withToken($token)->post("/api/v1/products/{$productId}/image", [
            'image' => UploadedFile::fake()->image('first.jpg'),
        ])->assertOk();

        $firstPath = Product::find($productId)->image;

        $this->withToken($token)->post("/api/v1/products/{$productId}/image", [
            'image' => UploadedFile::fake()->image('second.jpg'),
        ])->assertOk();

        $secondPath = Product::find($productId)->image;
        $this->assertNotSame($firstPath, $secondPath);
        Storage::disk('public')->assertMissing($firstPath);
        Storage::disk('public')->assertExists($secondPath);
    }

    public function test_deletes_image_and_clears_product_field(): void
    {
        Storage::fake('public');
        $token = $this->registerOwner();
        $productId = $this->createProduct($token);

        $this->withToken($token)->post("/api/v1/products/{$productId}/image", [
            'image' => UploadedFile::fake()->image('soap.jpg'),
        ])->assertOk();

        $path = Product::find($productId)->image;

        $response = $this->withToken($token)->deleteJson("/api/v1/products/{$productId}/image");

        $response->assertOk()
            ->assertJsonPath('data.image', null);

        $this->assertNull(Product::find($productId)->image);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_rejects_non_image_upload_with_422(): void
    {
        Storage::fake('public');
        $token = $this->registerOwner();
        $productId = $this->createProduct($token);

        $response = $this->withToken($token)->post("/api/v1/products/{$productId}/image", [
            'image' => UploadedFile::fake()->create('notes.pdf', 40, 'application/pdf'),
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['image']);

        $this->assertNull(Product::find($productId)->image);
    }

    public function test_create_payload_cannot_set_image_url(): void
    {
        $token = $this->registerOwner();

        $response = $this->withToken($token)->postJson('/api/v1/products', [
            'name' => 'Soap',
            'type' => 'simple',
            'selling_price' => 50,
            'image' => 'https://evil.example/photo.png',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.image', null);

        $this->assertNull(Product::find($response->json('data.id'))->image);
    }

    public function test_returns_404_when_uploading_image_for_another_store_product(): void
    {
        Storage::fake('public');
        $ownerA = $this->registerOwner('photo-a@test.com');
        $productId = $this->createProduct($ownerA);
        $ownerB = $this->registerOwner('photo-b@test.com');

        Auth::forgetGuards();
        StoreContext::clear();

        $this->withToken($ownerB)->post("/api/v1/products/{$productId}/image", [
            'image' => UploadedFile::fake()->image('soap.jpg'),
        ])->assertNotFound();

        $this->assertNull(
            DB::table('products')->where('id', $productId)->value('image')
        );
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function createProduct(string $token, array $overrides = []): int
    {
        $response = $this->withToken($token)->postJson('/api/v1/products', array_merge([
            'name' => 'Soap',
            'type' => 'simple',
            'selling_price' => 50,
            'cost_price' => 30,
            'track_stock' => true,
            'stock_quantity' => 5,
        ], $overrides));

        $response->assertCreated();

        return (int) $response->json('data.id');
    }

    protected function registerOwner(string $email = 'photo@test.com'): string
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'store_name' => 'Photo Store',
            'owner_name' => 'Owner',
            'email' => $email,
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ]);

        $response->assertCreated();

        return $response->json('data.token');
    }
}
