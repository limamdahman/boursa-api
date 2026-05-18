<?php

declare(strict_types=1);

namespace App\Services\Media;

use App\Jobs\Media\ProcessVehicleMediaJob;
use App\Models\Vehicle;
use App\Models\VehicleMedia;
use DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class MediaUploadService
{
    public const MAX_FILE_SIZE_BYTES = 15 * 1024 * 1024;

    public const MAX_PHOTOS_PER_VEHICLE = 15;

    private const ALLOWED_MIMES = ['image/jpeg', 'image/png', 'image/webp'];

    public function store(Vehicle $vehicle, UploadedFile $file, bool $setAsCover = false): VehicleMedia
    {
        $this->validate($vehicle, $file);

        $extension = $this->safeExtension($file);
        $key = sprintf(
            'vehicles/%s/originals/%s.%s',
            $vehicle->id,
            Str::uuid()->toString(),
            $extension
        );

        $disk = Storage::disk('s3');
        $disk->putFileAs(dirname($key), $file, basename($key), 'public');

        $url = $disk->url($key);

        $currentCount = $vehicle->media()->count();
        $shouldBeCover = $setAsCover || $currentCount === 0;

        if ($shouldBeCover) {
            $vehicle->media()->update(['is_cover' => false]);
        }

        $media = VehicleMedia::create([
            'vehicle_id' => $vehicle->id,
            'url_original' => $url,
            'sort_order' => $currentCount,
            'is_cover' => $shouldBeCover,
            'watermarked' => false,
            'size_bytes' => $file->getSize(),
        ]);

        ProcessVehicleMediaJob::dispatch($media->id, $key);

        return $media->fresh();
    }

    public function delete(VehicleMedia $media): void
    {
        $disk = Storage::disk('s3');

        foreach ([$media->url_original, $media->url_webp_lg, $media->url_webp_md, $media->url_thumb] as $url) {
            if ($url === null) {
                continue;
            }

            $path = $this->urlToPath($url);
            if ($path !== null && $disk->exists($path)) {
                $disk->delete($path);
            }
        }

        $media->delete();
    }

    private function validate(Vehicle $vehicle, UploadedFile $file): void
    {
        if ($file->getSize() > self::MAX_FILE_SIZE_BYTES) {
            throw new DomainException(sprintf(
                'Fichier trop volumineux (max %d MB).',
                self::MAX_FILE_SIZE_BYTES / 1024 / 1024
            ));
        }

        $mime = (string) $file->getMimeType();
        if (! in_array($mime, self::ALLOWED_MIMES, true)) {
            throw new DomainException('Format non supporté. JPG, PNG ou WebP attendu.');
        }

        $count = $vehicle->media()->count();
        if ($count >= self::MAX_PHOTOS_PER_VEHICLE) {
            throw new DomainException(sprintf(
                'Maximum %d photos par véhicule.',
                self::MAX_PHOTOS_PER_VEHICLE
            ));
        }
    }

    private function safeExtension(UploadedFile $file): string
    {
        $extension = mb_strtolower($file->getClientOriginalExtension());

        return match ($extension) {
            'jpg', 'jpeg' => 'jpg',
            'png' => 'png',
            'webp' => 'webp',
            default => 'jpg',
        };
    }

    private function urlToPath(string $url): ?string
    {
        $bucket = config('filesystems.disks.s3.bucket');
        if (! is_string($bucket) || $bucket === '') {
            return null;
        }

        $marker = '/'.$bucket.'/';
        $pos = mb_strpos($url, $marker);
        if ($pos === false) {
            return null;
        }

        return mb_substr($url, $pos + mb_strlen($marker));
    }
}
