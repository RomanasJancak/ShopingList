<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductImageUploadTest extends TestCase
{
    use RefreshDatabase;

    private const SAMPLE_PNG_PATH = __DIR__.'/../Fixtures/product-sample.png';

    public function test_product_picture_is_uploaded_and_resized_to_128x128_on_create(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $this->actingAs($user);

        $response = $this->post('/api/products', [
            'name' => 'Milk',
            'picture' => UploadedFile::fake()->createWithContent('milk.png', file_get_contents(self::SAMPLE_PNG_PATH)),
            'description' => '2% fat',
            'quantity_type' => 'pcs',
        ]);

        $response->assertCreated();

        $path = $response->json('picture');
        $this->assertNotNull($path);

        Storage::disk('public')->assertExists($path);

        $imageData = Storage::disk('public')->get($path);
        [$width, $height] = getimagesizefromstring($imageData);

        $this->assertSame(128, $width);
        $this->assertSame(128, $height);
    }

    public function test_product_picture_is_replaced_and_resized_on_update(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $this->actingAs($user);

        $createResponse = $this->post('/api/products', [
            'name' => 'Bananas',
            'picture' => UploadedFile::fake()->createWithContent('bananas.png', file_get_contents(self::SAMPLE_PNG_PATH)),
            'description' => 'Fresh bananas',
            'quantity_type' => 'kg',
        ])->assertCreated();

        $productId = $createResponse->json('id');
        $oldPath = $createResponse->json('picture');

        $updateResponse = $this->post("/api/products/{$productId}", [
            '_method' => 'PUT',
            'name' => 'Bananas Updated',
            'picture' => UploadedFile::fake()->createWithContent('bananas-new.png', file_get_contents(self::SAMPLE_PNG_PATH)),
            'description' => 'Updated',
            'quantity_type' => 'kg',
        ]);

        $updateResponse->assertOk();

        $newPath = $updateResponse->json('picture');
        $this->assertNotSame($oldPath, $newPath);

        Storage::disk('public')->assertMissing($oldPath);
        Storage::disk('public')->assertExists($newPath);

        $imageData = Storage::disk('public')->get($newPath);
        [$width, $height] = getimagesizefromstring($imageData);

        $this->assertSame(128, $width);
        $this->assertSame(128, $height);
    }
}