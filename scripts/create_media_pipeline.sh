#!/usr/bin/env bash
# Boursa — Pipeline media (sous-étape 1)
# Crée: Service upload + Job de traitement async + extension du modèle Vehicle
#
# Usage depuis ~/projects/boursa/boursa-api :
#   bash scripts/create_media_pipeline.sh

set -euo pipefail

PROJECT_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$PROJECT_ROOT"

echo "==> 1. Logo Boursa SVG (watermark placeholder)"
mkdir -p storage/app/watermark
cat > storage/app/watermark/boursa-logo.svg << 'EOF'
<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 60" width="200" height="60">
  <rect width="200" height="60" rx="8" fill="rgba(0,0,0,0.55)"/>
  <text x="100" y="38" text-anchor="middle" font-family="Arial, sans-serif"
        font-weight="700" font-size="26" fill="#FFFFFF" letter-spacing="2">
    BOURSA
  </text>
  <text x="100" y="53" text-anchor="middle" font-family="Arial, sans-serif"
        font-weight="400" font-size="10" fill="#FFD700">
    boursa.mr
  </text>
</svg>
EOF

echo "==> 2. Service MediaUploadService"
mkdir -p app/Services/Media
cat > app/Services/Media/MediaUploadService.php << 'EOF'
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
EOF

echo "==> 3. Job ProcessVehicleMediaJob (compression WebP + watermark async)"
mkdir -p app/Jobs/Media
cat > app/Jobs/Media/ProcessVehicleMediaJob.php << 'EOF'
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

        if (! $disk->exists($this->originalKey)) {
            throw new RuntimeException(sprintf(
                'Original file missing on S3: %s',
                $this->originalKey
            ));
        }

        $originalBytes = $disk->get($this->originalKey);
        if ($originalBytes === null) {
            throw new RuntimeException('Failed to read original from S3.');
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
        $svgPath = storage_path('app/watermark/boursa-logo.svg');

        if (! file_exists($svgPath)) {
            throw new RuntimeException('Watermark logo missing.');
        }

        // GD ne lit pas le SVG nativement → on génère un PNG depuis un placeholder via Intervention
        // Workaround : si on a déjà un PNG à côté, on le préfère ; sinon on dessine du texte.
        $pngPath = storage_path('app/watermark/boursa-logo.png');
        if (file_exists($pngPath)) {
            return $manager->read(file_get_contents($pngPath));
        }

        // Fallback : on crée un PNG en mémoire avec du texte
        return $this->buildTextWatermark($manager);
    }

    private function buildTextWatermark(ImageManager $manager): ImageInterface
    {
        $image = $manager->create(400, 80)->fill('rgba(0, 0, 0, 0.55)');

        $image->text('BOURSA', 200, 45, function ($font): void {
            $font->filename(public_path('fonts/DejaVuSans-Bold.ttf'));
            $font->size(38);
            $font->color('#FFFFFF');
            $font->align('center');
            $font->valign('middle');
        });

        $image->text('boursa.mr', 200, 68, function ($font): void {
            $font->filename(public_path('fonts/DejaVuSans-Bold.ttf'));
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

    public function failed(\Throwable $e): void
    {
        Log::channel('single')->error('Vehicle media processing failed', [
            'media_id' => $this->mediaId,
            'original_key' => $this->originalKey,
            'error' => $e->getMessage(),
        ]);
    }
}
EOF

echo "==> 4. Bind de DejaVuSans-Bold.ttf (police TTF pour watermark fallback)"
mkdir -p public/fonts
if [ ! -f public/fonts/DejaVuSans-Bold.ttf ]; then
  if [ -f /usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf ]; then
    cp /usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf public/fonts/
    echo "  → Copié depuis /usr/share/fonts/truetype/dejavu/"
  elif [ -f /usr/share/fonts/TTF/DejaVuSans-Bold.ttf ]; then
    cp /usr/share/fonts/TTF/DejaVuSans-Bold.ttf public/fonts/
    echo "  → Copié depuis /usr/share/fonts/TTF/"
  else
    echo "  ⚠ DejaVuSans-Bold.ttf introuvable sur le système."
    echo "    Installe-la : sudo apt install -y fonts-dejavu-core"
    echo "    Puis relance le script ou copie manuellement dans public/fonts/"
  fi
else
  echo "  → Déjà présente."
fi

echo "==> 5. Vérification du package Intervention Image"
if ! grep -q '"intervention/image"' composer.json; then
  echo "  ⚠ intervention/image absent de composer.json"
  echo "    Lance : composer require intervention/image"
else
  echo "  → Présent dans composer.json."
fi

echo ""
echo "==> ✅ Pipeline media généré"
echo ""
echo "Prochaines étapes :"
echo "  1. Vérifier que la police existe :"
echo "       ls -la public/fonts/DejaVuSans-Bold.ttf"
echo "  2. Lancer un worker queue dans un terminal séparé :"
echo "       php artisan queue:work --queue=default --tries=3"
echo "  3. Tester l'upload (script de test fourni dans la prochaine étape)"
