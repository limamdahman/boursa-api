<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('reviewer_user_id');
            $table->uuid('seller_user_id')->nullable();
            $table->uuid('seller_agency_id')->nullable();
            $table->uuid('vehicle_id')->nullable();
            $table->tinyInteger('rating');
            $table->text('comment')->nullable();
            $table->timestamps();

            $table->foreign('reviewer_user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('seller_user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('seller_agency_id')->references('id')->on('agencies')->nullOnDelete();
            $table->foreign('vehicle_id')->references('id')->on('vehicles')->nullOnDelete();

            $table->index('seller_user_id');
            $table->index('seller_agency_id');
            $table->index('reviewer_user_id');

            // Un user ne peut laisser qu'un seul avis par seller (particulier ou agence)
            $table->unique(['reviewer_user_id', 'seller_user_id'], 'unique_reviewer_seller_user');
            $table->unique(['reviewer_user_id', 'seller_agency_id'], 'unique_reviewer_seller_agency');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
