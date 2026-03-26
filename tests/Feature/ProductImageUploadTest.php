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
    private const PRODUCT_PICTURES_DISK = 'product_pictures';

    public function test_product_picture_is_uploaded_on_create(): void
    {
        Storage::fake(self::PRODUCT_PICTURES_DISK);
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

        Storage::disk(self::PRODUCT_PICTURES_DISK)->assertExists(basename($path));

        $imageData = Storage::disk(self::PRODUCT_PICTURES_DISK)->get(basename($path));
        $this->assertNotFalse(getimagesizefromstring($imageData));
    }

    public function test_product_picture_is_replaced_on_update(): void
    {
        Storage::fake(self::PRODUCT_PICTURES_DISK);
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

        Storage::disk(self::PRODUCT_PICTURES_DISK)->assertMissing(basename($oldPath));
        Storage::disk(self::PRODUCT_PICTURES_DISK)->assertExists(basename($newPath));

        $imageData = Storage::disk(self::PRODUCT_PICTURES_DISK)->get(basename($newPath));
        $this->assertNotFalse(getimagesizefromstring($imageData));
    }
}