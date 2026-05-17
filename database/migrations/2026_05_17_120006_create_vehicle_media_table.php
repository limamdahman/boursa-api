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
