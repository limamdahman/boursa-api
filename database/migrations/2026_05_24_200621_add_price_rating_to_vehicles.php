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
        Schema::table('vehicles', function (Blueprint $table) {
            $table->string('price_rating', 20)->nullable()->after('price_negotiable');
            $table->decimal('price_score', 6, 4)->nullable()->after('price_rating');
            $table->jsonb('price_benchmark_data')->nullable()->after('price_score');
            $table->timestamp('price_rating_computed_at')->nullable()->after('price_benchmark_data');
        });

        // Index pour filtrage rapide
        DB::statement('CREATE INDEX IF NOT EXISTS vehicles_price_rating_idx ON vehicles(price_rating) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS vehicles_price_rating_idx');
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropColumn(['price_rating', 'price_score', 'price_benchmark_data', 'price_rating_computed_at']);
        });
    }
};
