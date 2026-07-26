<?php
// app/Http/Controllers/Api/ImageController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductImage;
use App\Services\ImageService;
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Support\Facades\DB;

class ImageController extends Controller
{
    public function __construct(private ImageService $imageService) {}

    /**
     * POST /api/admin/products/{product}/images
     *
     * Upload one or more images for a product.
     * Accepts multipart/form-data with a "images[]" field.
     */
    public function store(Request $request, Product $product): JsonResponse
    {
        $request->validate([
            'images'            => 'required|array|min:1|max:8',
            'images.*'          => 'image|mimes:jpeg,png,webp|max:5120', // 5MB each
            'set_primary_index' => 'nullable|integer|min:0', // which index becomes primary
        ]);

        $uploaded = [];

        return DB::transaction(function () use ($request, $product, &$uploaded) {
            $setPrimaryAt = $request->integer('set_primary_index', 0);
            $sortStart    = $product->images()->max('sort_order') + 1;

            foreach ($request->file('images') as $index => $file) {
                $result = $this->imageService->uploadProductImage($file);

                $image = ProductImage::create([
                    'product_id'    => $product->id,
                    'image_url'     => $result['secure_url'],
                    'thumbnail_url' => $this->buildThumbnailUrl($result['secure_url']),
                    'alt_text'      => trim(($product->brand->name ?? '') . ' ' . $product->name),
                    'sort_order'    => $sortStart + $index,
                    'is_primary'    => $index === $setPrimaryAt && $product->images()->count() === 0,
                ]);

                $uploaded[] = $image;
            }

            return response()->json([
                'success' => true,
                'message' => count($uploaded) . ' image(s) uploaded successfully.',
                'data'    => ['images' => $uploaded],
            ], 201);
        });
    }

    /**
     * DELETE /api/admin/products/{product}/images/{image}
     */
    public function destroy(Product $product, ProductImage $image): JsonResponse
    {
        if ($image->product_id !== $product->id) {
            return response()->json(['success' => false, 'message' => 'Image not found.'], 404);
        }

        // Extract the Cloudinary public_id from the URL to delete from storage too.
        // URL format: https://res.cloudinary.com/{cloud}/image/upload/{public_id}.{ext}
        $publicId = $this->extractPublicId($image->image_url);
        if ($publicId) {
            $this->imageService->deleteImage($publicId);
        }

        $image->delete();

        return response()->json(['success' => true, 'message' => 'Image deleted.']);
    }

    /**
     * PUT /api/admin/products/{product}/images/{image}/set-primary
     *
     * Set a specific image as the primary (main) product photo.
     */
    public function setPrimary(Product $product, ProductImage $image): JsonResponse
    {
        if ($image->product_id !== $product->id) {
            return response()->json(['success' => false, 'message' => 'Image not found.'], 404);
        }

        // Clear existing primary flag, then set new one atomically
        DB::transaction(function () use ($product, $image) {
            ProductImage::where('product_id', $product->id)
                ->update(['is_primary' => false]);
            $image->update(['is_primary' => true]);
        });

        return response()->json([
            'success' => true,
            'message' => 'Primary image updated.',
            'data'    => ['image' => $image->fresh()],
        ]);
    }

    // ── Helpers ───────────────────────────────────────────────

    /**
     * Build a thumbnail URL by inserting Cloudinary transformation params.
     * w_400,h_400,c_fit resizes to 400×400 while maintaining aspect ratio.
     */
    private function buildThumbnailUrl(string $secureUrl): string
    {
        return str_replace(
            '/upload/',
            '/upload/w_400,h_400,c_fit,q_auto,f_auto/',
            $secureUrl
        );
    }

    /**
     * Extract the Cloudinary public_id from a secure_url.
     * https://res.cloudinary.com/{cloud}/image/upload/knl-atelier/products/abc123.jpg
     * → knl-atelier/products/abc123
     */
    private function extractPublicId(string $url): ?string
    {
        if (!str_contains($url, 'cloudinary.com')) return null;

        // Strip the base URL and file extension
        $parts    = explode('/upload/', $url);
        $withExt  = $parts[1] ?? '';
        $noExt    = preg_replace('/\.[^.]+$/', '', $withExt);

        // Strip any transformation string (v1234567/...)
        return preg_replace('/^v\d+\//', '', $noExt);
    }
}
