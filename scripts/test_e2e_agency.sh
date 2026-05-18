#!/usr/bin/env bash
# Boursa — Tests end-to-end CRUD agence (sous-étape 3)
#
# Pré-requis :
#   - php artisan serve doit tourner en arrière-plan (le script le démarre)
#   - php artisan queue:work doit tourner (le script le démarre aussi)
#   - jq et curl installés

set -euo pipefail

PROJECT_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$PROJECT_ROOT"

BASE_URL="http://localhost:8000/api/v1"
TMP_DIR="/tmp/boursa-e2e"
mkdir -p "$TMP_DIR"

echo "════════════════════════════════════════════════════════════"
echo " Boursa — Tests E2E CRUD Agence"
echo "════════════════════════════════════════════════════════════"

# ─── 1. Créer un mot de passe pour une agence existante du seeder ────────────
echo ""
echo "──[ 1 ] Préparer un user agency avec password connu"

AGENCY_USER_ID=$(php artisan tinker --execute='
$u = App\Models\User::where("role", "agency")->first();
if ($u) {
    $u->password = bcrypt("agency1234");
    $u->save();
    echo $u->id . "|" . $u->phone;
}
' 2>/dev/null | tail -1)

if [ -z "$AGENCY_USER_ID" ] || [[ "$AGENCY_USER_ID" != *"|"* ]]; then
  echo "  ✗ Impossible de récupérer un user agency. As-tu lancé migrate:fresh --seed ?"
  exit 1
fi

USER_UUID="${AGENCY_USER_ID%%|*}"
USER_PHONE="${AGENCY_USER_ID##*|}"

echo "  → User UUID: $USER_UUID"
echo "  → User phone: $USER_PHONE"
echo "  → Password: agency1234"

# ─── 2. Lancer le serveur (si pas déjà actif) ─────────────────────────────────
echo ""
echo "──[ 2 ] Démarrer Laravel server"

if curl -fsS "$BASE_URL/health" > /dev/null 2>&1; then
  echo "  → Serveur déjà actif."
  SERVER_PID=""
else
  php artisan serve > "$TMP_DIR/server.log" 2>&1 &
  SERVER_PID=$!
  echo "  → Serveur démarré (PID $SERVER_PID)"
  sleep 2
fi

# ─── 3. Lancer le worker queue ───────────────────────────────────────────────
echo ""
echo "──[ 3 ] Démarrer queue worker"
php artisan queue:work --queue=default --tries=3 --timeout=120 > "$TMP_DIR/queue.log" 2>&1 &
QUEUE_PID=$!
echo "  → Queue worker démarré (PID $QUEUE_PID)"
sleep 1

cleanup() {
  echo ""
  echo "──[ Cleanup ] Arrêt des processes"
  [ -n "$SERVER_PID" ] && kill "$SERVER_PID" 2>/dev/null || true
  kill "$QUEUE_PID" 2>/dev/null || true
  wait 2>/dev/null || true
}
trap cleanup EXIT

# ─── 4. Login agence ──────────────────────────────────────────────────────────
echo ""
echo "──[ 4 ] POST /auth/login (agency)"

LOGIN_RESPONSE=$(curl -sS -X POST "$BASE_URL/auth/login" \
  -H "Content-Type: application/json" \
  -d "{\"identifier\": \"$USER_PHONE\", \"password\": \"agency1234\"}")

TOKEN=$(echo "$LOGIN_RESPONSE" | jq -r '.token // empty')

if [ -z "$TOKEN" ]; then
  echo "  ✗ Login échoué"
  echo "$LOGIN_RESPONSE" | jq .
  exit 1
fi

echo "  ✓ Token obtenu: ${TOKEN:0:30}..."

H_AUTH="Authorization: Bearer $TOKEN"

# ─── 5. GET /agency/profile ──────────────────────────────────────────────────
echo ""
echo "──[ 5 ] GET /agency/profile"

PROFILE=$(curl -sS "$BASE_URL/agency/profile" -H "$H_AUTH")
echo "$PROFILE" | jq '.data | {id, name, status, is_verified, subscription_tier}'

AGENCY_ID=$(echo "$PROFILE" | jq -r '.data.id')

# ─── 6. POST /agency/vehicles (création DRAFT) ────────────────────────────────
echo ""
echo "──[ 6 ] POST /agency/vehicles (DRAFT)"

BRAND_ID=$(curl -sS "$BASE_URL/vehicles?per_page=5" | jq -r '.data[0].brand.id // 1')
MODEL_ID=$(php artisan tinker --execute="
echo App\\Models\\VehicleModel::where('brand_id', $BRAND_ID)->first()?->id;
" 2>/dev/null | tail -1)

if [ -z "$MODEL_ID" ] || ! [[ "$MODEL_ID" =~ ^[0-9]+$ ]]; then
  MODEL_ID=1
fi

echo "  → brand_id=$BRAND_ID, vehicle_model_id=$MODEL_ID"

CREATE_PAYLOAD=$(cat << JSONEOF
{
  "brand_id": $BRAND_ID,
  "vehicle_model_id": $MODEL_ID,
  "year": 2022,
  "mileage_km": 45000,
  "price_mru": 2500000,
  "price_negotiable": true,
  "fuel": "diesel",
  "transmission": "automatic",
  "body_type": "pickup",
  "color": "Blanc",
  "condition": "used",
  "description_fr": "Véhicule de test E2E créé par script. Première main, entretien régulier.",
  "city_id": 1,
  "specs": {
    "options": ["climatisation", "abs", "airbag", "bluetooth"],
    "doors": 5,
    "seats": 5
  },
  "latitude": 18.0735,
  "longitude": -15.9785
}
JSONEOF
)

CREATE_RESPONSE=$(curl -sS -X POST "$BASE_URL/agency/vehicles" \
  -H "$H_AUTH" \
  -H "Content-Type: application/json" \
  -d "$CREATE_PAYLOAD")

VEHICLE_ID=$(echo "$CREATE_RESPONSE" | jq -r '.data.id // empty')

if [ -z "$VEHICLE_ID" ]; then
  echo "  ✗ Création échouée"
  echo "$CREATE_RESPONSE" | jq .
  exit 1
fi

echo "  ✓ Véhicule créé: $VEHICLE_ID"
echo "$CREATE_RESPONSE" | jq '.data | {id, status, brand: .brand.name, model: .model.name, year, price_mru}'

# ─── 7. Générer 2 images JPG factices ─────────────────────────────────────────
echo ""
echo "──[ 7 ] Générer 2 images JPG de test"

php -r '
$im1 = imagecreatetruecolor(1200, 800);
imagefilledrectangle($im1, 0, 0, 1200, 800, imagecolorallocate($im1, 60, 110, 180));
imagestring($im1, 5, 500, 380, "BOURSA TEST PHOTO 1", imagecolorallocate($im1, 255, 255, 255));
imagejpeg($im1, "/tmp/boursa-e2e/test1.jpg", 90);

$im2 = imagecreatetruecolor(1200, 800);
imagefilledrectangle($im2, 0, 0, 1200, 800, imagecolorallocate($im2, 180, 80, 60));
imagestring($im2, 5, 500, 380, "BOURSA TEST PHOTO 2", imagecolorallocate($im2, 255, 255, 255));
imagejpeg($im2, "/tmp/boursa-e2e/test2.jpg", 90);

echo "  ✓ Images générées: test1.jpg + test2.jpg" . PHP_EOL;
'

ls -lh "$TMP_DIR"/test*.jpg

# ─── 8. POST /agency/vehicles/{id}/media (photo 1, cover) ────────────────────
echo ""
echo "──[ 8 ] POST /agency/vehicles/{id}/media (photo 1, is_cover=true)"

UPLOAD1=$(curl -sS -X POST "$BASE_URL/agency/vehicles/$VEHICLE_ID/media" \
  -H "$H_AUTH" \
  -F "photo=@$TMP_DIR/test1.jpg" \
  -F "is_cover=1")

echo "$UPLOAD1" | jq .

MEDIA1_ID=$(echo "$UPLOAD1" | jq -r '.media.id // empty')

if [ -z "$MEDIA1_ID" ]; then
  echo "  ✗ Upload 1 échoué"
  exit 1
fi

echo "  ✓ Photo 1 uploadée: $MEDIA1_ID"

# ─── 9. POST /agency/vehicles/{id}/media (photo 2) ───────────────────────────
echo ""
echo "──[ 9 ] POST /agency/vehicles/{id}/media (photo 2)"

UPLOAD2=$(curl -sS -X POST "$BASE_URL/agency/vehicles/$VEHICLE_ID/media" \
  -H "$H_AUTH" \
  -F "photo=@$TMP_DIR/test2.jpg")

echo "$UPLOAD2" | jq .

# ─── 10. Attendre traitement async (compression + watermark) ────────────────
echo ""
echo "──[ 10 ] Attendre le traitement async (max 15s)"

for i in $(seq 1 15); do
  WATERMARKED=$(php artisan tinker --execute="
echo App\\Models\\VehicleMedia::where('vehicle_id', '$VEHICLE_ID')->where('watermarked', true)->count();
" 2>/dev/null | tail -1)

  if [ "$WATERMARKED" = "2" ]; then
    echo "  ✓ Les 2 photos sont watermarkées (après ${i}s)"
    break
  fi
  echo "  ... ($i/15) watermarked=$WATERMARKED"
  sleep 1
done

# ─── 11. Vérifier les URLs dans la fiche véhicule ───────────────────────────
echo ""
echo "──[ 11 ] GET /agency/vehicles/{id} avec ses media"

VEHICLE_DETAIL=$(curl -sS "$BASE_URL/agency/vehicles/$VEHICLE_ID" -H "$H_AUTH")
echo "$VEHICLE_DETAIL" | jq '.data | {id, status, media: [.media[] | {id, is_cover, watermarked, url_original, url_md, url_thumb}]}'

# ─── 12. Vérifier les variantes sur MinIO ────────────────────────────────────
echo ""
echo "──[ 12 ] Vérifier la structure des fichiers MinIO"

php artisan tinker --execute="
\$disk = Storage::disk('s3');
\$files = \$disk->allFiles('vehicles/$VEHICLE_ID');
echo PHP_EOL . count(\$files) . ' fichiers sur S3 :' . PHP_EOL;
foreach (\$files as \$f) {
    \$size = \$disk->size(\$f);
    echo sprintf('  - %s (%s KB)', \$f, round(\$size / 1024, 1)) . PHP_EOL;
}
" 2>/dev/null | tail -20

# ─── 13. POST /agency/vehicles/{id}/publish ──────────────────────────────────
echo ""
echo "──[ 13 ] POST /agency/vehicles/{id}/publish"

PUBLISH=$(curl -sS -X POST "$BASE_URL/agency/vehicles/$VEHICLE_ID/publish" -H "$H_AUTH")
echo "$PUBLISH" | jq '.data | {id, status, published_at, expires_at}'

# ─── 14. Vérifier dans le listing public ────────────────────────────────────
echo ""
echo "──[ 14 ] GET /vehicles (public) — le nouveau véhicule doit apparaître"

PUBLIC_FOUND=$(curl -sS "$BASE_URL/vehicles?per_page=50" \
  | jq -r ".data[] | select(.id == \"$VEHICLE_ID\") | .id")

if [ "$PUBLIC_FOUND" = "$VEHICLE_ID" ]; then
  echo "  ✓ Véhicule visible dans le listing public"
  curl -sS "$BASE_URL/vehicles/$VEHICLE_ID" \
    | jq '.data | {id, brand: .brand.name, model: .model.name, year, price_mru, cover: .media[0].url_md // null, media_count: (.media | length)}'
else
  echo "  ✗ Véhicule NON trouvé dans le listing public"
  exit 1
fi

# ─── 15. PATCH reorder ───────────────────────────────────────────────────────
echo ""
echo "──[ 15 ] PATCH /agency/vehicles/{id}/media/reorder (inversion)"

MEDIA_IDS=$(curl -sS "$BASE_URL/agency/vehicles/$VEHICLE_ID" -H "$H_AUTH" \
  | jq -r '[.data.media[].id] | reverse | @json')

REORDER=$(curl -sS -X PATCH "$BASE_URL/agency/vehicles/$VEHICLE_ID/media/reorder" \
  -H "$H_AUTH" \
  -H "Content-Type: application/json" \
  -d "{\"order\": $MEDIA_IDS}")

echo "$REORDER" | jq .

# ─── 16. DELETE photo 2 ──────────────────────────────────────────────────────
echo ""
echo "──[ 16 ] DELETE /agency/vehicles/{id}/media/{mediaId}"

MEDIA2_ID=$(echo "$UPLOAD2" | jq -r '.media.id')
DELETE=$(curl -sS -X DELETE "$BASE_URL/agency/vehicles/$VEHICLE_ID/media/$MEDIA2_ID" -H "$H_AUTH")
echo "$DELETE" | jq .

# ─── 17. Tentative d'accès cross-agency (doit échouer 403) ──────────────────
echo ""
echo "──[ 17 ] Sécurité : un AUTRE agency ne peut PAS modifier ce véhicule"

OTHER_USER_PHONE=$(php artisan tinker --execute="
\$u = App\\Models\\User::where('role', 'agency')->where('id', '!=', '$USER_UUID')->first();
if (\$u) { \$u->password = bcrypt('other1234'); \$u->save(); echo \$u->phone; }
" 2>/dev/null | tail -1)

if [ -n "$OTHER_USER_PHONE" ]; then
  OTHER_LOGIN=$(curl -sS -X POST "$BASE_URL/auth/login" \
    -H "Content-Type: application/json" \
    -d "{\"identifier\": \"$OTHER_USER_PHONE\", \"password\": \"other1234\"}")
  OTHER_TOKEN=$(echo "$OTHER_LOGIN" | jq -r '.token')

  HTTP_CODE=$(curl -sS -o /dev/null -w "%{http_code}" \
    -X DELETE "$BASE_URL/agency/vehicles/$VEHICLE_ID" \
    -H "Authorization: Bearer $OTHER_TOKEN")

  if [ "$HTTP_CODE" = "403" ]; then
    echo "  ✓ DELETE bloqué (HTTP 403) — policy fonctionne"
  else
    echo "  ✗ ATTENDU 403 mais reçu HTTP $HTTP_CODE"
  fi
fi

# ─── Bilan ───────────────────────────────────────────────────────────────────
echo ""
echo "════════════════════════════════════════════════════════════"
echo " ✅ Tests E2E terminés"
echo "════════════════════════════════════════════════════════════"
echo "Console MinIO : http://localhost:9001 (user: boursa)"
echo "  → bucket boursa-media → vehicles/$VEHICLE_ID/"
echo "Listing public : $BASE_URL/vehicles"
echo "  → véhicule créé visible avec ses photos watermarkées"
