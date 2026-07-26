<?php
// app/Services/ImageService.php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ImageService
{
    private string $cloudName;
    private string $apiKey;
    private string $apiSecret;

    public function __construct()
    {
        $this->cloudName = config('services.cloudinary.cloud_name', '');
        $this->apiKey    = config('services.cloudinary.api_key', '');
        $this->apiSecret = config('services.cloudinary.api_secret', '');
    }

    /**
     * Upload a product image. Uses Cloudinary if configured, otherwise
     * stores locally in storage/app/public/products/.
     *
     * @return array{secure_url: string, public_id: string, width: int, height: int}
     */
    public function uploadProductImage(
        UploadedFile $file,
        string $folder = 'knl-atelier/products',
        ?string $publicId = null
    ): array {
        if ($this->isConfigured()) {
            return $this->uploadToCloudinary($file, $folder, $publicId);
        }

        return $this->uploadToLocal($file);
    }

    /**
     * Delete an image. Cloudinary by public_id or local file by path.
     */
    public function deleteImage(string $identifier): bool
    {
        if ($this->isConfigured()) {
            return $this->deleteFromCloudinary($identifier);
        }

        return $this->deleteLocal($identifier);
    }

    // ── Cloudinary ─────────────────────────────────────────

    private function uploadToCloudinary(
        UploadedFile $file,
        string $folder,
        ?string $publicId
    ): array {
        $timestamp = time();

        $params = [
            'folder'    => $folder,
            'timestamp' => $timestamp,
        ];

        if ($publicId) {
            $params['public_id'] = $publicId;
            $params['overwrite'] = 'true';
        }

        $signature = $this->generateSignature($params);

        $response = Http::attach(
            'file',
            file_get_contents($file->getRealPath()),
            $file->getClientOriginalName()
        )->post(
            "https://api.cloudinary.com/v1_1/{$this->cloudName}/image/upload",
            array_merge($params, [
                'api_key'   => $this->apiKey,
                'signature' => $signature,
            ])
        );

        if (!$response->successful()) {
            $error = $response->json('error.message', 'Unknown Cloudinary error');
            Log::error("Cloudinary upload failed: {$error}");
            throw new \RuntimeException("Image upload failed: {$error}");
        }

        return $response->json();
    }

    private function deleteFromCloudinary(string $publicId): bool
    {
        if (!$publicId) return false;

        $timestamp = time();
        $signature = $this->generateSignature([
            'public_id' => $publicId,
            'timestamp' => $timestamp,
        ]);

        $response = Http::post(
            "https://api.cloudinary.com/v1_1/{$this->cloudName}/image/destroy",
            [
                'public_id' => $publicId,
                'api_key'   => $this->apiKey,
                'signature' => $signature,
                'timestamp' => $timestamp,
            ]
        );

        return $response->successful() && $response->json('result') === 'ok';
    }

    private function generateSignature(array $params): string
    {
        ksort($params);

        $paramString = collect($params)
            ->map(fn ($value, $key) => "{$key}={$value}")
            ->implode('&');

        return sha1($paramString . $this->apiSecret);
    }

    // ── Local Storage ──────────────────────────────────────

    private function uploadToLocal(UploadedFile $file): array
    {
        $disk = \Storage::disk('public');
        $dir  = 'products';

        $filename = $dir . '/' . Str::uuid() . '.' . $file->getClientOriginalExtension();
        $disk->put($filename, file_get_contents($file->getRealPath()));

        $url = \Storage::disk('public')->url($filename);

        $width  = 0;
        $height = 0;
        $size   = @getimagesize($file->getRealPath());
        if ($size) {
            $width  = $size[0];
            $height = $size[1];
        }

        return [
            'secure_url' => $url,
            'public_id'  => $filename,
            'width'      => $width,
            'height'     => $height,
        ];
    }

    private function deleteLocal(string $path): bool
    {
        $disk = \Storage::disk('public');

        // Strip full URL to get relative path
        if (str_contains($path, '/storage/')) {
            $path = after($path, '/storage/');
        }

        if ($disk->exists($path)) {
            return $disk->delete($path);
        }

        return false;
    }

    // ── Helpers ────────────────────────────────────────────

    private function isConfigured(): bool
    {
        return !empty($this->cloudName)
            && !empty($this->apiKey)
            && !empty($this->apiSecret);
    }
}
