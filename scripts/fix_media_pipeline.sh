#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."

echo "==> 1. Réécriture propre du job"
cat > app/Jobs/Media/ProcessVehicleMediaJob.php << 'PHP_END'
<?php

declare(strict_types=1);

namespace App\Jobs\Media;

use App\Models\VehicleMedia;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\ImageInterface;
use RuntimeException;
use Throwable;

final class ProcessVehicleMediaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 30;
    public int $timeout = 120;

    public function __construct(
        public readonly string $mediaId,
        public readonly string $originalKey,
    ) {}

    public function handle(): void
    {
        $media = VehicleMedia::find($this->mediaId);

        if (! $media) {
            Log::channel('single')->warning('Media not found for processing', [
                'media_id' => $this->mediaId,
            ]);
            return;
        }

        $disk = Storage::disk('s3');

        try {
            $originalBytes = $disk->get($this->originalKey);
        } catch (Throwable $e) {
            throw new RuntimeException(sprintf(
                'Failed to fetch S3 object %s: %s',
                $this->originalKey,
                $e->getMessage()
            ), 0, $e);
        }

        if ($originalBytes === null || $originalBytes === '') {
            throw new RuntimeException(sprintf('Empty or missing S3 object: %s', $this->originalKey));
        }

        $manager = new ImageManager(new Driver);
        $watermark = $this->loadWatermark($manager);

        $variants = [
            'thumb' => ['width' => 300,  'height' => 200,  'quality' => 70],
            'md'    => ['width' => 800,  'height' => 600,  'quality' => 80],
            'lg'    => ['width' => 1600, 'height' => 1200, 'quality' => 85],
        ];

        $updates = [];
        $width = null;
        $height = null;

        foreach ($variants as $name => $opts) {
            $image = $manager->read($originalBytes);
            $image->scaleDown(width: $opts['width'], height: $opts['height']);

            if ($name !== 'thumb') {
                $this->applyWatermark($image, $watermark);
            }

            if ($width === null) {
                $width = $image->width();
                $height = $image->height();
            }

            $variantKey = $this->buildVariantKey($this->originalKey, $name);
            $encoded = $image->toWebp(quality: $opts['quality'])->toString();

            $disk->put($variantKey, $encoded, ['visibility' => 'public']);

            $column = $name === 'thumb' ? 'url_thumb' : ('url_webp_'.$name);
            $updates[$column] = $disk->url($variantKey);
        }

        $updates['watermarked'] = true;
        $updates['width'] = $width;
        $updates['height'] = $height;

        $media->update($updates);

        Log::channel('single')->info('Vehicle media processed', [
            'media_id' => $media->id,
            'vehicle_id' => $media->vehicle_id,
            'variants' => array_keys($variants),
        ]);
    }

    private function loadWatermark(ImageManager $manager): ImageInterface
    {
        $pngPath = storage_path('app/watermark/boursa-logo.png');
        if (file_exists($pngPath)) {
            return $manager->read(file_get_contents($pngPath));
        }
        return $this->buildTextWatermark($manager);
    }

    private function buildTextWatermark(ImageManager $manager): ImageInterface
    {
        $image = $manager->create(400, 80)->fill('rgba(0, 0, 0, 0.55)');
        $fontPath = public_path('fonts/DejaVuSans-Bold.ttf');

        $image->text('BOURSA', 200, 45, function ($font) use ($fontPath): void {
            $font->filename($fontPath);
            $font->size(38);
            $font->color('#FFFFFF');
            $font->align('center');
            $font->valign('middle');
        });

        $image->text('boursa.mr', 200, 68, function ($font) use ($fontPath): void {
            $font->filename($fontPath);
            $font->size(14);
            $font->color('#FFD700');
            $font->align('center');
            $font->valign('middle');
        });

        return $image;
    }

    private function applyWatermark(ImageInterface $image, ImageInterface $watermark): void
    {
        $targetWidth = (int) max(200, $image->width() * 0.30);
        $wm = clone $watermark;
        $wm->scaleDown(width: $targetWidth);

        $offsetX = (int) ($image->width() * 0.03);
        $offsetY = (int) ($image->height() * 0.03);

        $image->place($wm, 'bottom-right', $offsetX, $offsetY);
    }

    private function buildVariantKey(string $originalKey, string $variant): string
    {
        $base = preg_replace('#/originals/#', '/'.$variant.'/', $originalKey, 1);
        $base = preg_replace('/\.(jpe?g|png|webp)$/i', '.webp', (string) $base);
        return (string) $base;
    }

    public function failed(Throwable $e): void
    {
        Log::channel('single')->error('Vehicle media processing failed', [
            'media_id' => $this->mediaId,
            'original_key' => $this->originalKey,
            'error' => $e->getMessage(),
        ]);
    }
}
PHP_END

echo "==> 2. Vérif syntax"
php -l app/Jobs/Media/ProcessVehicleMediaJob.php

echo "==> 3. Clear caches"
php artisan optimize:clear

echo "==> ✅ Job réécrit. Lance maintenant :"
echo "    php artisan tinker"
echo "    >>> Voir étape suivante dans le terminal"
