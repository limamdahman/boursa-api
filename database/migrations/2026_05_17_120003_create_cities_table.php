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
