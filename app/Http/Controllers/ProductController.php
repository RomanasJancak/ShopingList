<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProductController extends Controller
{
    private const MAX_PICTURE_KB = 40096;

    public function index(): JsonResponse
    {
        $products = Product::query()
            ->orderBy('name')
            ->get(['id', 'name', 'picture', 'description', 'quantity_type']);

        return response()->json($products->map(fn (Product $product) => $this->toApiProduct($product))->values());
    }

    public function store(Request $request): JsonResponse
    {
        $this->ensureValidPictureUpload($request);

        $validated = $request->validate($this->productValidationRules(), $this->productValidationMessages());

        $picturePath = null;
        if ($request->hasFile('picture')) {
            $picturePath = $this->storeUploadedPicture($request->file('picture'));
        }

        $product = Product::create([
            'name' => $validated['name'],
            'picture' => $picturePath,
            'description' => $validated['description'] ?? null,
            'quantity_type' => $validated['quantity_type'],
        ]);

        return response()->json($this->toApiProduct($product), 201);
    }

    public function update(Product $product, Request $request): JsonResponse
    {
        $this->ensureValidPictureUpload($request);

        $validated = $request->validate($this->productValidationRules(), $this->productValidationMessages());

        $newPicturePath = $product->picture;
        if ($request->hasFile('picture')) {
            $newPicturePath = $this->storeUploadedPicture($request->file('picture'));
            $this->deletePictureIfExists($product->picture);
        }

        $product->update([
            'name' => $validated['name'],
            'picture' => $newPicturePath,
            'description' => $validated['description'] ?? null,
            'quantity_type' => $validated['quantity_type'],
        ]);

        return response()->json($this->toApiProduct($product));
    }

    public function destroy(Product $product): JsonResponse
    {
        abort_if($product->shoppingListItems()->exists(), 422, 'Product is used in shopping list items and cannot be deleted.');

        $this->deletePictureIfExists($product->picture);
        $product->delete();

        return response()->json([
            'message' => 'Product deleted successfully.',
        ]);
    }

    private function toApiProduct(Product $product): array
    {
        return [
            'id' => $product->id,
            'name' => $product->name,
            'picture' => $product->picture,
            'picture_url' => $product->picture ? asset('storage/'.$product->picture) : null,
            'description' => $product->description,
            'quantity_type' => $product->quantity_type,
        ];
    }

    private function storeUploadedPicture(UploadedFile $picture): string
    {
        $extension = strtolower($picture->getClientOriginalExtension() ?: $picture->extension() ?: 'jpg');
        $filename = Str::uuid().'.'.$extension;

        Storage::disk('public')->putFileAs('products', $picture, $filename);

        return 'products/'.$filename;
    }

    private function deletePictureIfExists(?string $path): void
    {
        if (! $path) {
            return;
        }

        Storage::disk('public')->delete($path);
    }

    private function productValidationRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'picture' => ['nullable', 'image', 'max:'.self::MAX_PICTURE_KB, 'mimes:jpg,jpeg,png,webp,gif'],
            'description' => ['nullable', 'string'],
            'quantity_type' => ['required', 'string', Rule::in(['kg', 'pcs'])],
        ];
    }

    private function productValidationMessages(): array
    {
        return [
            'picture.image' => 'Picture must be a valid image file.',
            'picture.max' => 'Picture must be 4 MB or smaller.',
            'picture.mimes' => 'Picture must be a JPG, PNG, WEBP, or GIF file.',
        ];
    }

    private function ensureValidPictureUpload(Request $request): void
    {
        $file = $request->file('picture');
        if (! $file instanceof UploadedFile || $file->isValid()) {
            return;
        }

        $serverLimitDetails = sprintf(
            'Server limits: upload_max_filesize=%s, post_max_size=%s.',
            ini_get('upload_max_filesize') ?: 'unknown',
            ini_get('post_max_size') ?: 'unknown',
        );

        $message = match ($file->getError()) {
            UPLOAD_ERR_INI_SIZE => 'Picture exceeds server upload size limit. '.$serverLimitDetails,
            UPLOAD_ERR_FORM_SIZE => 'Picture exceeds form upload size limit.',
            UPLOAD_ERR_PARTIAL => 'Picture was only partially uploaded. Please try again.',
            UPLOAD_ERR_NO_FILE => 'No picture file was uploaded.',
            UPLOAD_ERR_NO_TMP_DIR => 'Server is missing a temporary upload directory.',
            UPLOAD_ERR_CANT_WRITE => 'Server failed to write the uploaded picture to disk.',
            UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the picture upload.',
            default => 'Picture upload failed due to an unknown server error.',
        };

        throw ValidationException::withMessages([
            'picture' => [$message],
        ]);
    }
}