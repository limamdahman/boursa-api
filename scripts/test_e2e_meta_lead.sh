#!/usr/bin/env bash
# Boursa — Tests E2E lead tracking + Meta CAPI
#
# Pré-requis : migrations + seeders OK, scripts précédents exécutés

set -euo pipefail

PROJECT_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$PROJECT_ROOT"

BASE_URL="http://localhost:8000/api/v1"
TMP_DIR="/tmp/boursa-e2e-meta"
mkdir -p "$TMP_DIR"

echo "════════════════════════════════════════════════════════════"
echo " Boursa — Tests E2E Lead Tracking + Meta CAPI"
echo "════════════════════════════════════════════════════════════"

# ─── 1. Serveur Laravel ───────────────────────────────────────────────────────
if curl -fsS "$BASE_URL/health" > /dev/null 2>&1; then
  echo "──[ 1 ] Serveur déjà actif"
  SERVER_PID=""
else
  php artisan serve > "$TMP_DIR/server.log" 2>&1 &
  SERVER_PID=$!
  echo "──[ 1 ] Serveur démarré (PID $SERVER_PID)"
  sleep 2
fi

# ─── 2. Queue worker ──────────────────────────────────────────────────────────
php artisan queue:work --queue=default --tries=3 --timeout=30 > "$TMP_DIR/queue.log" 2>&1 &
QUEUE_PID=$!
echo "──[ 2 ] Queue worker démarré (PID $QUEUE_PID)"
sleep 1

cleanup() {
  echo ""
  echo "──[ Cleanup ]"
  [ -n "$SERVER_PID" ] && kill "$SERVER_PID" 2>/dev/null || true
  kill "$QUEUE_PID" 2>/dev/null || true
  wait 2>/dev/null || true
}
trap cleanup EXIT

# ─── 3. Récupérer un véhicule ACTIVE existant ────────────────────────────────
echo ""
echo "──[ 3 ] Récupérer un véhicule public ACTIVE"

VEHICLE_RESP=$(curl -sS "$BASE_URL/vehicles?per_page=5")
VEHICLE_ID=$(echo "$VEHICLE_RESP" | jq -r '.data[0].id')
VEHICLE_BRAND=$(echo "$VEHICLE_RESP" | jq -r '.data[0].brand.name')
VEHICLE_PRICE=$(echo "$VEHICLE_RESP" | jq -r '.data[0].price_mru')

echo "  → VEHICLE_ID=$VEHICLE_ID"
echo "  → Brand: $VEHICLE_BRAND, prix: $VEHICLE_PRICE MRU"

# ─── 4. POST /vehicles/{id}/track-view (anonyme) ─────────────────────────────
echo ""
echo "──[ 4 ] POST /vehicles/{id}/track-view (anonyme)"

VIEW_RESP=$(curl -sS -X POST "$BASE_URL/vehicles/$VEHICLE_ID/track-view" \
  -H "Content-Type: application/json" \
  -d '{}')
echo "$VIEW_RESP" | jq .

# ─── 5. Re-essayer track-view → rate-limited (dedup naturel) ────────────────
echo ""
echo "──[ 5 ] Re-track immédiat → rate-limited"

VIEW2_RESP=$(curl -sS -X POST "$BASE_URL/vehicles/$VEHICLE_ID/track-view" \
  -H "Content-Type: application/json" \
  -d '{}')
echo "$VIEW2_RESP" | jq .

# ─── 6. POST /vehicles/{id}/lead — WhatsApp click anonyme ───────────────────
echo ""
echo "──[ 6 ] POST /vehicles/{id}/lead (whatsapp_click, anonyme)"

LEAD1=$(curl -sS -X POST "$BASE_URL/vehicles/$VEHICLE_ID/lead" \
  -H "Content-Type: application/json" \
  -d '{
    "type": "whatsapp_click",
    "sender_name": "Test Visiteur",
    "sender_phone": "44 12 34 56"
  }')
echo "$LEAD1" | jq .

# ─── 7. POST /vehicles/{id}/lead — Message texte authentifié ────────────────
echo ""
echo "──[ 7 ] Login user pour test authentifié"

# Reset password de l'admin pour le test
php artisan tinker --execute='
$u = App\Models\User::where("phone", "22244000001")->first();
$u->password = bcrypt("admin1234");
$u->save();
' 2>/dev/null > /dev/null

USER_TOKEN=$(curl -sS -X POST "$BASE_URL/auth/login" \
  -H "Content-Type: application/json" \
  -d '{"identifier": "22244000001", "password": "admin1234"}' \
  | jq -r '.token')

echo "  → Token: ${USER_TOKEN:0:30}..."

echo ""
echo "──[ 8 ] POST /vehicles/{id}/lead (message, authentifié)"

LEAD2=$(curl -sS -X POST "$BASE_URL/vehicles/$VEHICLE_ID/lead" \
  -H "Authorization: Bearer $USER_TOKEN" \
  -H "Content-Type: application/json" \
  -H "X-Source: app" \
  -d '{
    "type": "message",
    "message": "Bonjour, le véhicule est-il toujours disponible ? Merci de me rappeler."
  }')
echo "$LEAD2" | jq .

# ─── 9. POST /vehicles/{id}/lead — Call click avec fbclid ───────────────────
echo ""
echo "──[ 9 ] POST /vehicles/{id}/lead?fbclid=... (attribution Meta)"

LEAD3=$(curl -sS -X POST "$BASE_URL/vehicles/$VEHICLE_ID/lead?fbclid=IwAR0test123&utm_source=facebook&utm_campaign=mvp_launch" \
  -H "Content-Type: application/json" \
  -d '{"type": "call_click"}')
echo "$LEAD3" | jq .

# ─── 10. Rate limit lead (6e tentative → 429) ────────────────────────────────
echo ""
echo "──[ 10 ] Rate-limit lead (5 max / 10 min)"

for i in 4 5 6; do
  CODE=$(curl -sS -o /dev/null -w "%{http_code}" -X POST "$BASE_URL/vehicles/$VEHICLE_ID/lead" \
    -H "Content-Type: application/json" \
    -d '{"type": "whatsapp_click"}')
  echo "  Tentative #$i → HTTP $CODE"
done

# ─── 11. Vérifier les leads en BDD ───────────────────────────────────────────
echo ""
echo "──[ 11 ] Vérifier les leads en BDD"

docker exec boursa-pg psql -U boursa -d boursa -c "
SELECT
    LEFT(id::text, 8) AS id,
    type,
    sender_name,
    sender_phone,
    LEFT(COALESCE(message, ''), 40) AS message_preview,
    created_at
FROM leads
ORDER BY created_at DESC
LIMIT 10
"

# ─── 12. Vérifier les analytics_events ───────────────────────────────────────
echo ""
echo "──[ 12 ] Vérifier les analytics_events"

docker exec boursa-pg psql -U boursa -d boursa -c "
SELECT
    id,
    event_type,
    LEFT(vehicle_id::text, 8) AS vehicle,
    LEFT(COALESCE(user_id::text, 'anon'), 8) AS user,
    metadata->>'lead_type' AS lead_type,
    fbclid,
    utm_source,
    utm_campaign,
    created_at
FROM analytics_events
ORDER BY id DESC
LIMIT 10
"

# ─── 13. Vérifier que contacts_count a été incrémenté ────────────────────────
echo ""
echo "──[ 13 ] contacts_count incrémenté ?"

docker exec boursa-pg psql -U boursa -d boursa -c "
SELECT
    LEFT(id::text, 8) AS id,
    contacts_count,
    views_count
FROM vehicles
WHERE id = '$VEHICLE_ID'
"

# ─── 14. Vérifier le log Meta CAPI (mode log-only) ───────────────────────────
echo ""
echo "──[ 14 ] Events Meta CAPI envoyés (mode log-only)"

sleep 2  # laisser le worker traiter les jobs CAPI

echo "  Lignes [Meta CAPI:no-op] dans le log :"
grep -c "Meta CAPI:no-op" storage/logs/laravel.log 2>/dev/null || echo "0"

echo ""
echo "  Derniers events Meta loggés :"
grep "Meta CAPI:no-op" storage/logs/laravel.log 2>/dev/null \
  | tail -3 \
  | sed -E 's/.*"event_name":"([^"]+)".*"event_id":"([^"]+)".*/  - \1 / \2/'

# ─── 15. Inspecter un payload Meta complet ──────────────────────────────────
echo ""
echo "──[ 15 ] Dernier payload Lead envoyé (event complet)"

php artisan tinker --execute='
$lines = file(storage_path("logs/laravel.log"));
foreach (array_reverse($lines) as $line) {
    if (str_contains($line, "Meta CAPI:no-op") && str_contains($line, "\"Lead\"")) {
        $matches = [];
        preg_match("/\\{.+\\}/", $line, $matches);
        if (!empty($matches[0])) {
            echo json_encode(json_decode($matches[0], true)["event"], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
            break;
        }
    }
}
' 2>/dev/null | tail -80

# ─── 16. Lead sur véhicule non-active → 404 ─────────────────────────────────
echo ""
echo "──[ 16 ] Lead sur UUID inexistant → 404"

CODE=$(curl -sS -o /dev/null -w "%{http_code}" -X POST \
  "$BASE_URL/vehicles/00000000-0000-0000-0000-000000000000/lead" \
  -H "Content-Type: application/json" \
  -d '{"type": "whatsapp_click"}')
echo "  HTTP $CODE"

# ─── 17. Validation : type invalide → 422 ───────────────────────────────────
echo ""
echo "──[ 17 ] Validation type invalide → 422"

INVALID=$(curl -sS -X POST "$BASE_URL/vehicles/$VEHICLE_ID/lead" \
  -H "Content-Type: application/json" \
  -d '{"type": "bogus"}')
echo "$INVALID" | jq .

# ─── 18. Validation : message requis si type=message → 422 ──────────────────
echo ""
echo "──[ 18 ] Validation type=message sans message → 422"

INVALID2=$(curl -sS -X POST "$BASE_URL/vehicles/$VEHICLE_ID/lead" \
  -H "Content-Type: application/json" \
  -d '{"type": "message"}')
echo "$INVALID2" | jq .

# ─── Bilan ───────────────────────────────────────────────────────────────────
echo ""
echo "════════════════════════════════════════════════════════════"
echo " ✅ Tests E2E Lead + Meta CAPI terminés"
echo "════════════════════════════════════════════════════════════"
echo "  - Leads créés : voir étape 11"
echo "  - Analytics events : voir étape 12"
echo "  - Meta events loggés (no-op) : voir étape 14"
echo "  - Payload Lead complet : voir étape 15"
echo ""
echo "Pour activer l'envoi réel à Meta :"
echo "  → Remplir META_PIXEL_ID + META_CAPI_ACCESS_TOKEN dans .env"
echo "  → php artisan optimize:clear"
