#!/usr/bin/env bash
# Réinit complète des migrations métier Boursa
# Usage: bash scripts/reset_migrations.sh

set -euo pipefail

PROJECT_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
MIG_DIR="$PROJECT_ROOT/database/migrations"

cd "$PROJECT_ROOT"

echo "==> 1. Suppression des anciennes migrations métier vides"

# Migrations à supprimer (créées vides, on les régénère)
for pattern in \
  "*enable_postgis*" \
  "*create_brands_table*" \
  "*create_vehicle_models_table*" \
  "*create_cities_table*" \
  "*create_agencies_table*" \
  "*create_vehicles_table*" \
  "*create_vehicle_media_table*" \
  "*create_favorites_table*" \
  "*create_leads_table*" \
  "*create_otp_codes_table*" \
  "*create_analytics_events_table*" \
  "*create_search_history_table*"; do
  rm -f $MIG_DIR/$pattern || true
done

echo "==> 2. Écriture des migrations avec contenu correct"

TS="2026_05_17_120000"
inc() { TS=$(printf "2026_05_17_%06d" $((10#${TS##*_} + 1))); }

# --- 1. Extensions PostGIS ---
cat > "$MIG_DIR/${TS}_enable_postgis_and_extensions.php" << 'EOF'
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS postgis');
        DB::statement('CREATE EXTENSION IF NOT EXISTS "uuid-ossp"');
        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');
    }

    public function down(): void
    {
        // Ne pas drop : d'autres BDD peuvent dépendre
    }
};
EOF
inc

# --- 2. brands ---
cat > "$MIG_DIR/${TS}_create_brands_table.php" << 'EOF'
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brands', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('slug', 100)->unique();
            $table->string('logo_url', 500)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('brands');
    }
};
EOF
inc

# --- 3. vehicle_models ---
cat > "$MIG_DIR/${TS}_create_vehicle_models_table.php" << 'EOF'
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_models', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->constrained('brands')->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('slug', 100);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['brand_id', 'slug']);
            $table->index(['brand_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_models');
    }
};
EOF
inc

# --- 4. cities (+ PostGIS) ---
cat > "$MIG_DIR/${TS}_create_cities_table.php" << 'EOF'
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cities', function (Blueprint $table) {
            $table->id();
            $table->string('name_fr', 100);
            $table->string('name_ar', 100)->nullable();
            $table->string('slug', 100)->unique();
            $table->string('region', 100)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        DB::statement('ALTER TABLE cities ADD COLUMN location GEOGRAPHY(POINT, 4326)');
        DB::statement('CREATE INDEX cities_location_gix ON cities USING GIST (location)');
    }

    public function down(): void
    {
        Schema::dropIfExists('cities');
    }
};
EOF
inc

# --- 5. modify users (UUID + role + phone) ---
# On modifie la migration users existante au lieu d'en créer une nouvelle
echo "==> 3. Réécriture de la migration users (UUID + Boursa fields)"

USERS_MIG=$(ls -1 "$MIG_DIR" | grep "create_users_table" | head -1)
if [ -z "$USERS_MIG" ]; then
  echo "ERREUR: migration users introuvable" >&2
  exit 1
fi

cat > "$MIG_DIR/$USERS_MIG" << 'EOF'
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 150);
            $table->string('email', 150)->nullable()->unique();
            $table->string('phone', 20)->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('phone_verified_at')->nullable();
            $table->string('password')->nullable();
            $table->string('role', 20)->default('user');
            $table->string('language', 5)->default('fr');
            $table->string('avatar_url', 500)->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->rememberToken();
            $table->softDeletes();
            $table->timestamps();

            $table->index('role');
            $table->index('phone_verified_at');
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->uuid('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
EOF

echo "==> 4. Écriture des migrations dépendantes (agencies, vehicles...)"

# --- 6. agencies ---
cat > "$MIG_DIR/${TS}_create_agencies_table.php" << 'EOF'
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agencies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->string('name', 200);
            $table->string('slug', 200)->unique();
            $table->string('logo_url', 500)->nullable();
            $table->text('description')->nullable();
            $table->string('address', 500)->nullable();
            $table->foreignId('city_id')->nullable()->constrained('cities')->nullOnDelete();
            $table->string('rc_number', 50)->nullable();
            $table->string('phone_whatsapp', 20)->nullable();
            $table->string('phone_call', 20)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('website', 255)->nullable();
            $table->string('status', 20)->default('pending');
            $table->timestamp('verified_at')->nullable();
            $table->string('subscription_tier', 20)->default('free');
            $table->unsignedSmallInteger('quota_active_listings')->default(10);
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->index('status');
            $table->index('subscription_tier');
        });

        DB::statement('ALTER TABLE agencies ADD COLUMN location GEOGRAPHY(POINT, 4326)');
        DB::statement('CREATE INDEX agencies_location_gix ON agencies USING GIST (location)');
    }

    public function down(): void
    {
        Schema::dropIfExists('agencies');
    }
};
EOF
inc

# --- 7. vehicles ---
cat > "$MIG_DIR/${TS}_create_vehicles_table.php" << 'EOF'
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->nullable();
            $table->uuid('agency_id')->nullable();
            $table->foreignId('brand_id')->constrained('brands')->restrictOnDelete();
            $table->foreignId('vehicle_model_id')->constrained('vehicle_models')->restrictOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('mileage_km')->nullable();
            $table->unsignedBigInteger('price_mru');
            $table->boolean('price_negotiable')->default(false);
            $table->string('currency', 3)->default('MRU');
            $table->string('fuel', 20)->nullable();
            $table->string('transmission', 20)->nullable();
            $table->string('body_type', 30)->nullable();
            $table->string('color', 30)->nullable();
            $table->string('condition', 20)->default('used');
            $table->text('description_fr')->nullable();
            $table->text('description_ar')->nullable();
            $table->foreignId('city_id')->nullable()->constrained('cities')->nullOnDelete();
            $table->jsonb('specs')->nullable();
            $table->string('status', 20)->default('draft');
            $table->text('moderation_notes')->nullable();
            $table->uuid('moderated_by')->nullable();
            $table->timestamp('moderated_at')->nullable();
            $table->unsignedBigInteger('views_count')->default(0);
            $table->unsignedBigInteger('contacts_count')->default(0);
            $table->timestamp('published_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('agency_id')->references('id')->on('agencies')->cascadeOnDelete();
            $table->foreign('moderated_by')->references('id')->on('users')->nullOnDelete();

            $table->index(['status', 'published_at']);
            $table->index(['brand_id', 'vehicle_model_id']);
            $table->index('agency_id');
            $table->index(['year', 'price_mru']);
        });

        DB::statement('ALTER TABLE vehicles ADD COLUMN location GEOGRAPHY(POINT, 4326)');
        DB::statement('CREATE INDEX vehicles_location_gix ON vehicles USING GIST (location)');
        DB::statement('CREATE INDEX vehicles_specs_gin ON vehicles USING GIN (specs)');
        DB::statement('CREATE INDEX vehicles_desc_fr_trgm ON vehicles USING GIN (description_fr gin_trgm_ops)');
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
EOF
inc

# --- 8. vehicle_media ---
cat > "$MIG_DIR/${TS}_create_vehicle_media_table.php" << 'EOF'
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_media', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('vehicle_id');
            $table->string('url_original', 500);
            $table->string('url_webp_lg', 500)->nullable();
            $table->string('url_webp_md', 500)->nullable();
            $table->string('url_thumb', 500)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_cover')->default(false);
            $table->boolean('watermarked')->default(false);
            $table->unsignedSmallInteger('width')->nullable();
            $table->unsignedSmallInteger('height')->nullable();
            $table->unsignedInteger('size_bytes')->nullable();
            $table->timestamps();

            $table->foreign('vehicle_id')->references('id')->on('vehicles')->cascadeOnDelete();
            $table->index(['vehicle_id', 'sort_order']);
            $table->index(['vehicle_id', 'is_cover']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_media');
    }
};
EOF
inc

# --- 9. favorites ---
cat > "$MIG_DIR/${TS}_create_favorites_table.php" << 'EOF'
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('favorites', function (Blueprint $table) {
            $table->uuid('user_id');
            $table->uuid('vehicle_id');
            $table->timestamp('created_at')->useCurrent();

            $table->primary(['user_id', 'vehicle_id']);
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('vehicle_id')->references('id')->on('vehicles')->cascadeOnDelete();
            $table->index('vehicle_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('favorites');
    }
};
EOF
inc

# --- 10. leads ---
cat > "$MIG_DIR/${TS}_create_leads_table.php" << 'EOF'
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('vehicle_id');
            $table->uuid('agency_id')->nullable();
            $table->uuid('sender_user_id')->nullable();
            $table->string('sender_name', 150)->nullable();
            $table->string('sender_phone', 20)->nullable();
            $table->string('type', 20);
            $table->text('message')->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamps();

            $table->foreign('vehicle_id')->references('id')->on('vehicles')->cascadeOnDelete();
            $table->foreign('agency_id')->references('id')->on('agencies')->cascadeOnDelete();
            $table->foreign('sender_user_id')->references('id')->on('users')->nullOnDelete();

            $table->index(['agency_id', 'is_read', 'created_at']);
            $table->index(['vehicle_id', 'created_at']);
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
EOF
inc

# --- 11. otp_codes ---
cat > "$MIG_DIR/${TS}_create_otp_codes_table.php" << 'EOF'
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('otp_codes', function (Blueprint $table) {
            $table->id();
            $table->string('phone', 20);
            $table->string('code_hash', 255);
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index(['phone', 'consumed_at']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('otp_codes');
    }
};
EOF
inc

# --- 12. analytics_events ---
cat > "$MIG_DIR/${TS}_create_analytics_events_table.php" << 'EOF'
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analytics_events', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('user_id')->nullable();
            $table->uuid('vehicle_id')->nullable();
            $table->string('event_type', 50);
            $table->jsonb('metadata')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->string('fbclid', 255)->nullable();
            $table->string('utm_source', 100)->nullable();
            $table->string('utm_campaign', 100)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('vehicle_id')->references('id')->on('vehicles')->nullOnDelete();

            $table->index(['event_type', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index(['vehicle_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_events');
    }
};
EOF
inc

# --- 13. search_history ---
cat > "$MIG_DIR/${TS}_create_search_history_table.php" << 'EOF'
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('search_history', function (Blueprint $table) {
            $table->id();
            $table->uuid('user_id')->nullable();
            $table->jsonb('filters');
            $table->unsignedInteger('results_count')->default(0);
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_history');
    }
};
EOF

echo "==> 5. migrate:fresh"
php artisan migrate:fresh --force

echo "==> 6. Vérifications PostGIS"
docker exec boursa-pg psql -U boursa -d boursa -c \
  "SELECT f_table_name, f_geography_column, type FROM geography_columns WHERE f_table_schema='public' ORDER BY f_table_name;"

docker exec boursa-pg psql -U boursa -d boursa -c \
  "SELECT indexname FROM pg_indexes WHERE schemaname='public' AND (indexname LIKE '%_gix' OR indexname LIKE '%_gin%') ORDER BY indexname;"

docker exec boursa-pg psql -U boursa -d boursa -c \
  "SELECT count(*) AS tables_count FROM pg_tables WHERE schemaname='public';"

echo "==> ✅ Reset terminé"
