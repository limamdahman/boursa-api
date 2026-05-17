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
