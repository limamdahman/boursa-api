#!/usr/bin/env bash
# Boursa — Ajoute des photos watermarkées à tous les véhicules du seed
#
# Génère 1 photo couleur unie par véhicule (couleur dérivée du UUID),
# upload sur MinIO, dispatch le job de processing (3 variantes WebP + watermark).
#
# Usage: bash scripts/seed_vehicle_photos.sh

set -euo pipefail

PROJECT_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$PROJECT_ROOT"

echo "==> 1. Vérif prérequis"
command -v convert >/dev/null 2>&1 || { echo "ImageMagick (convert) requis."; exit 1; }
command -v php >/dev/null 2>&1 || { echo "PHP requis."; exit 1; }

# Backend doit tourner pour la queue
if ! curl -fsS http://127.0.0.1:8000/api/v1/health > /dev/null 2>&1; then
  echo "⚠️  Le serveur Laravel n'est pas actif sur :8000"
  echo "Lance-le avant : php artisan serve --host=0.0.0.0 &"
  exit 1
fi

echo "==> 2. Démarrer un worker queue (si pas déjà actif)"
if ! pgrep -f "queue:work" > /dev/null; then
  php artisan queue:work --queue=default --tries=3 --timeout=120 > /tmp/seed-photos-queue.log 2>&1 &
  QUEUE_PID=$!
  echo "  → Worker démarré (PID $QUEUE_PID)"
  sleep 1
else
  echo "  → Worker déjà actif"
  QUEUE_PID=""
fi

cleanup() {
  echo ""
  echo "──[ Cleanup ]"
  [ -n "$QUEUE_PID" ] && kill "$QUEUE_PID" 2>/dev/null || true
}
trap cleanup EXIT

echo ""
echo "==> 3. Création des photos et upload via tinker"

# Génère un script PHP one-shot et l'exécute via artisan tinker
cat > /tmp/seed_photos.php << 'PHP_END'
<?php

use App\Jobs\Media\ProcessVehicleMediaJob;
use App\Models\Vehicle;
use App\Models\VehicleMedia;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

// 16 couleurs "voiture" plausibles (silver, black, white, red, blue, gris, etc.)
$carColors = [
    '#1a1a1a', '#2a2a2a', '#4a4a4a', '#7a7a7a', '#9a9a9a',
    '#c0c0c0', '#e0e0e0', '#f5f5f5',
    '#1e3a8a', '#2563eb', '#7c3aed',
    '#991b1b', '#dc2626', '#ea580c',
    '#15803d', '#059669',
];

$vehicles = Vehicle::with('brand', 'vehicleModel')
    ->whereIn('status', ['active', 'pending'])
    ->get();

echo sprintf('Found %d vehicles to process.', $vehicles->count()).PHP_EOL;

$disk = Storage::disk('s3');
$processed = 0;

foreach ($vehicles as $vehicle) {
    // Skip si déjà des photos
    if ($vehicle->media()->count() > 0) {
        echo sprintf('  - %s (%s): already has photos, skip.', $vehicle->id, $vehicle->brand?->name).PHP_EOL;
        continue;
    }

    // Couleur déterministe basée sur UUID
    $colorIdx = hexdec(substr(str_replace('-', '', $vehicle->id), 0, 2)) % count($carColors);
    $color = $carColors[$colorIdx];

    $brand = $vehicle->brand?->name ?? '?';
    $model = $vehicle->vehicleModel?->name ?? '?';
    $year = $vehicle->year;
    $title = sprintf('%s %s %d', $brand, $model, $year);

    // Génère une image 1200x800 avec couleur + texte au centre via ImageMagick
    $tmpPath = sys_get_temp_dir().'/boursa-seed-'.$vehicle->id.'.jpg';

    $cmd = sprintf(
        'convert -size 1200x800 xc:%s '.
        '-font /usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf '.
        '-fill white -gravity center '.
        '-pointsize 60 -annotate +0-30 %s '.
        '-pointsize 36 -annotate +0+40 %s '.
        '-quality 85 %s',
        escapeshellarg($color),
        escapeshellarg(strtoupper($brand)),
        escapeshellarg(sprintf('%s %d', $model, $year)),
        escapeshellarg($tmpPath)
    );

    exec($cmd, $output, $rc);
    if ($rc !== 0 || ! file_exists($tmpPath)) {
        echo sprintf('  ✗ %s: image generation failed.', $vehicle->id).PHP_EOL;
        continue;
    }

    // Upload original sur S3/MinIO
    $mediaUuid = (string) Str::uuid();
    $originalKey = sprintf(
        'vehicles/%s/originals/%s.jpg',
        $vehicle->id,
        $mediaUuid
    );

    $bytes = file_get_contents($tmpPath);
    $disk->put($originalKey, $bytes, ['visibility' => 'public']);

    // Crée le record VehicleMedia
    $media = VehicleMedia::create([
        'id' => $mediaUuid,
        'vehicle_id' => $vehicle->id,
        'url_original' => $disk->url($originalKey),
        'is_cover' => true,
        'sort_order' => 0,
        'watermarked' => false,
    ]);

    // Dispatch le job de processing
    ProcessVehicleMediaJob::dispatch($media->id, $originalKey);

    // Nettoie le fichier temporaire
    @unlink($tmpPath);

    $processed++;
    if ($processed % 10 === 0) {
        echo sprintf('  → %d photos dispatched...', $processed).PHP_EOL;
    }
}

echo PHP_EOL;
echo sprintf('✓ %d photos uploaded + dispatched to queue.', $processed).PHP_EOL;
echo 'Wait ~10-30s for the queue worker to process all variants...'.PHP_EOL;
PHP_END

php artisan tinker --execute="require '/tmp/seed_photos.php';"

echo ""
echo "==> 4. Attente du traitement async (max 60s)"

for i in {1..30}; do
  PENDING=$(docker exec boursa-pg psql -U boursa -d boursa -t -A -c \
    "SELECT COUNT(*) FROM vehicle_media WHERE watermarked = false" 2>/dev/null || echo "?")
  PROCESSED=$(docker exec boursa-pg psql -U boursa -d boursa -t -A -c \
    "SELECT COUNT(*) FROM vehicle_media WHERE watermarked = true" 2>/dev/null || echo "?")

  echo "  ... ($i/30) pending=$PENDING processed=$PROCESSED"

  if [ "$PENDING" = "0" ] && [ "$PROCESSED" != "?" ] && [ "$PROCESSED" != "0" ]; then
    echo ""
    echo "  ✓ Toutes les photos sont traitées !"
    break
  fi
  sleep 2
done

echo ""
echo "==> 5. Statistiques finales"

docker exec boursa-pg psql -U boursa -d boursa -c "
SELECT
  COUNT(*) AS total_media,
  SUM(CASE WHEN watermarked THEN 1 ELSE 0 END) AS watermarked,
  SUM(CASE WHEN url_md IS NOT NULL THEN 1 ELSE 0 END) AS has_md,
  SUM(CASE WHEN url_thumb IS NOT NULL THEN 1 ELSE 0 END) AS has_thumb
FROM vehicle_media
"

echo ""
echo "──[ Échantillon ]"
SAMPLE_URL=$(docker exec boursa-pg psql -U boursa -d boursa -t -A -c \
  "SELECT url_webp_md FROM vehicle_media WHERE watermarked=true LIMIT 1" 2>/dev/null)
echo "URL exemple: $SAMPLE_URL"

echo ""
echo "════════════════════════════════════════════════════════════"
echo " ✅ Seed photos terminé"
echo "════════════════════════════════════════════════════════════"
echo ""
echo "Rafraîchis ton app Flutter (R dans le terminal flutter run)"
echo "ou recharge la page Chrome."
